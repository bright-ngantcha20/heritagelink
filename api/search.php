<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['results' => [], 'total' => 0]);
    exit;
}

$search = '%' . $query . '%';

// Search family members
$stmt = $pdo->prepare("
    SELECT
        fm.member_id,
        fm.full_name,
        fm.preferred_name,
        fm.gender,
        fm.date_of_birth,
        fm.date_of_death,
        fm.birthplace,
        fm.occupation,
        fm.photo,
        fm.verified,
        fm.privacy,
        COALESCE(
            q.name,
            fm.village_of_origin,
            'Unknown'
        ) AS quarter,
        fm.quarter_id,
        u.user_id,
        u.full_name AS account_name
    FROM family_members fm
    LEFT JOIN quarters q
        ON fm.quarter_id = q.quarter_id
    LEFT JOIN users u
        ON u.member_id = fm.member_id
    WHERE fm.privacy != 'private'
    AND (
        fm.full_name       LIKE ?
        OR fm.preferred_name LIKE ?
        OR fm.birthplace   LIKE ?
        OR fm.occupation   LIKE ?
        OR q.name          LIKE ?
        OR fm.village_of_origin LIKE ?
    )
    ORDER BY fm.verified DESC,
             fm.full_name ASC
    LIMIT 30
");

$stmt->execute([
    $search, $search, $search,
    $search, $search, $search,
]);

$results = $stmt->fetchAll();

// Format results
$formatted = [];
foreach ($results as $r) {
    $dob = $r['date_of_birth']
        ? date('Y', strtotime($r['date_of_birth']))
        : null;
    $dod = $r['date_of_death']
        ? date('Y', strtotime($r['date_of_death']))
        : null;

    $date_range = '';
    if ($dob && $dod)
        $date_range = $dob . ' — ' . $dod;
    elseif ($dob)
        $date_range = $dob . ' — Present';

    $photo_url = $r['photo']
        ? SITE_URL . '/' . $r['photo']
        : null;

    $formatted[] = [
        'member_id'   => (int)$r['member_id'],
        'full_name'   => $r['full_name'],
        'preferred_name' => $r['preferred_name'],
        'gender'      => $r['gender'],
        'date_range'  => $date_range,
        'birthplace'  => $r['birthplace'],
        'occupation'  => $r['occupation'],
        'photo'       => $photo_url,
        'verified'    => (bool)$r['verified'],
        'quarter'     => $r['quarter'],
        'quarter_id'  => $r['quarter_id'],
        'has_account' => !empty($r['user_id']),
        'is_deceased' => !empty($r['date_of_death']),
    ];
}

echo json_encode([
    'results' => $formatted,
    'total'   => count($formatted),
    'query'   => $query,
]);
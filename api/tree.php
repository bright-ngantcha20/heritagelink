<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Return JSON only
header('Content-Type: application/json');

// Must be logged in
if (!isLoggedIn()) {
    echo json_encode([
        'error' => 'Not authenticated'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get the root member
// (the logged-in user's family node)
$root_stmt = $pdo->prepare("
    SELECT u.member_id
    FROM users u
    WHERE u.user_id = ?
");
$root_stmt->execute([$user_id]);
$root = $root_stmt->fetch();

if (!$root || !$root['member_id']) {
    echo json_encode([
        'error'   => 'no_profile',
        'message' => 'User has not completed profile'
    ]);
    exit;
}

$root_id = $root['member_id'];

// ── Get all members in this user's tree ───────
// Includes: the user themselves, anyone they
// added, and anyone connected by relationship
$members_stmt = $pdo->prepare("
    SELECT DISTINCT
        fm.member_id   AS id,
        fm.full_name   AS name,
        fm.preferred_name,
        fm.gender,
        fm.date_of_birth,
        fm.dob_approximate,
        fm.date_of_death,
        fm.dod_approximate,
        fm.birthplace,
        fm.current_location,
        fm.occupation,
        fm.short_bio,
        fm.photo,
        fm.verified,
        fm.privacy,
        fm.added_by,
        COALESCE(
            q.name,
            fm.village_of_origin,
            'Unknown'
        ) AS quarter,
        fm.quarter_id,
        fm.village_of_origin,
        CASE
            WHEN u.user_id IS NOT NULL
            THEN 1 ELSE 0
        END AS is_user
    FROM family_members fm
    LEFT JOIN quarters q
        ON fm.quarter_id = q.quarter_id
    LEFT JOIN users u
        ON u.member_id = fm.member_id
    WHERE fm.member_id = ?
       OR fm.added_by  = ?
       OR fm.member_id IN (
           SELECT member_id_2
           FROM relationships
           WHERE member_id_1 = ?
       )
       OR fm.member_id IN (
           SELECT member_id_1
           FROM relationships
           WHERE member_id_2 = ?
             AND member_id_1 IN (
                 SELECT fm2.member_id
                 FROM family_members fm2
                 WHERE fm2.added_by = ?
             )
       )
    ORDER BY fm.created_at ASC
");

$members_stmt->execute([
    $root_id,
    $user_id,
    $root_id,
    $root_id,
    $user_id,
]);

$members_raw = $members_stmt->fetchAll();

// ── Get all relationships between these members
$member_ids = array_column($members_raw, 'id');

$links = [];

if (!empty($member_ids)) {
    // Build placeholders for IN clause
    $placeholders = implode(
        ',',
        array_fill(0, count($member_ids), '?')
    );

    $rel_stmt = $pdo->prepare("
        SELECT
            r.member_id_1  AS source,
            r.member_id_2  AS target,
            r.type,
            r.relation_label AS label
        FROM relationships r
        WHERE r.member_id_1 IN ($placeholders)
          AND r.member_id_2 IN ($placeholders)
    ");

    // Execute with member_ids twice
    // (once for source, once for target)
    $rel_stmt->execute(
        array_merge($member_ids, $member_ids)
    );

    $links = $rel_stmt->fetchAll();
}

// ── Format members for D3.js ──────────────────
$nodes = [];
foreach ($members_raw as $m) {

    // Format dates for display
    $dob = '';
    if ($m['date_of_birth']) {
        $dob = $m['dob_approximate']
            ? 'c. ' . date('Y', strtotime(
                $m['date_of_birth']
              ))
            : date('Y', strtotime(
                $m['date_of_birth']
              ));
    }

    $dod = '';
    if ($m['date_of_death']) {
        $dod = $m['dod_approximate']
            ? 'c. ' . date('Y', strtotime(
                $m['date_of_death']
              ))
            : date('Y', strtotime(
                $m['date_of_death']
              ));
    }

    // Build date range string
    $date_range = '';
    if ($dob && $dod) {
        $date_range = $dob . ' — ' . $dod;
    } elseif ($dob) {
        $date_range = $dob . ' — Present';
    }

    // Photo URL
    $photo_url = $m['photo']
        ? SITE_URL . '/' . $m['photo']
        : null;

    $nodes[] = [
        'id'            => (int)$m['id'],
        'name'          => $m['name'],
        'preferred_name'=> $m['preferred_name'],
        'gender'        => $m['gender'],
        'date_range'    => $date_range,
        'dob'           => $dob,
        'dod'           => $dod,
        'birthplace'       => $m['birthplace'],
        'current_location' => $m['current_location'],
        'occupation'       => $m['occupation'],
        'bio'           => $m['short_bio'],
        'photo'         => $photo_url,
        'quarter'       => $m['quarter'],
        'quarter_id'    => $m['quarter_id'],
        'village'       => $m['village_of_origin'],
        'verified'      => (bool)$m['verified'],
        'is_root'       => $m['id'] === $root_id,
        'is_user'       => (bool)$m['is_user'],
        'is_deceased'   => !empty($m['date_of_death']),
    ];
}

// ── Format links for D3.js ────────────────────
$formatted_links = [];
foreach ($links as $l) {
    // Find the target node's gender
$targetNode = array_filter(
    $nodes,
    fn($n) => $n['id'] === (int)$l['target']
);
$targetGender = !empty($targetNode)
    ? array_values($targetNode)[0]['gender']
    : null;

$formatted_links[] = [
    'source'        => (int)$l['source'],
    'target'        => (int)$l['target'],
    'type'          => $l['type'],
    'label'         => $l['label'],
    'target_gender' => $targetGender,
];
}

// ── Return the full tree data ─────────────────
echo json_encode([
    'root_id' => $root_id,
    'nodes'   => $nodes,
    'links'   => $formatted_links,
    'counts'  => [
        'total'    => count($nodes),
        'deceased' => count(array_filter(
            $nodes,
            fn($n) => $n['is_deceased']
        )),
        'verified' => count(array_filter(
            $nodes,
            fn($n) => $n['verified']
        )),
    ],
], JSON_UNESCAPED_UNICODE);
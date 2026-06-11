<?php

// ── Output ────────────────────────────────────
function clean($str) {
    return htmlspecialchars(
        trim($str), ENT_QUOTES, 'UTF-8'
    );
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ── Date formatting ───────────────────────────
function formatDate($date) {
    if (!$date) return 'Unknown';
    return date('F j, Y', strtotime($date));
}

function formatYear($date) {
    if (!$date) return '?';
    return date('Y', strtotime($date));
}

// ── Quarters ──────────────────────────────────
function getQuarterName($pdo, $quarter_id) {
    $stmt = $pdo->prepare(
        "SELECT name FROM quarters
         WHERE quarter_id = ?"
    );
    $stmt->execute([$quarter_id]);
    $row = $stmt->fetch();
    return $row ? $row['name'] : 'Unknown Quarter';
}

function getAllQuarters($pdo) {
    return $pdo->query(
        "SELECT * FROM quarters ORDER BY name"
    )->fetchAll();
}

// ── Messages ──────────────────────────────────
function unreadCount($pdo, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM messages
         WHERE receiver_id = ?
         AND is_read = 0"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// ── Profile checks ────────────────────────────
function hasProfile($pdo, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT member_id FROM users
         WHERE user_id = ?
         AND member_id IS NOT NULL"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() !== false;
}

function getUserMember($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT
            fm.*,
            COALESCE(
                q.name,
                fm.village_of_origin,
                'Unknown'
            ) AS quarter_name
        FROM users u
        JOIN family_members fm
            ON u.member_id = fm.member_id
        LEFT JOIN quarters q
            ON fm.quarter_id = q.quarter_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// ── Connections ───────────────────────────────
// Returns each connected person ONCE
// from the perspective of member_id
// using relation_label for display
function getConnections($pdo, $member_id) {
    $stmt = $pdo->prepare("
        SELECT
            fm.*,
            COALESCE(
                q.name,
                fm.village_of_origin,
                'Unknown Village'
            ) AS quarter_name,
            r.type           AS relation_type,
            r.relation_label AS relation_label
        FROM relationships r
        JOIN family_members fm
            ON r.member_id_2 = fm.member_id
        LEFT JOIN quarters q
            ON fm.quarter_id = q.quarter_id
        WHERE r.member_id_1 = ?
        AND fm.member_id != ?
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$member_id, $member_id]);
    return $stmt->fetchAll();
}

// ── User tree ─────────────────────────────────
function getUserTree($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT
            fm.*,
            COALESCE(
                q.name,
                fm.village_of_origin,
                'Unknown Village'
            ) AS quarter_name
        FROM family_members fm
        LEFT JOIN quarters q
            ON fm.quarter_id = q.quarter_id
        LEFT JOIN users u
            ON u.member_id = fm.member_id
        WHERE fm.added_by = ?
           OR u.user_id   = ?
        ORDER BY fm.created_at ASC
    ");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}

// ── Relationship labels ───────────────────────
function getRelationLabel($label, $type) {
    if (!$label) {
        return ucfirst($type);
    }
    $labels = [
        'father'               => 'Father',
        'mother'               => 'Mother',
        'son'                  => 'Son',
        'daughter'             => 'Daughter',
        'brother'              => 'Brother',
        'sister'               => 'Sister',
        'spouse'               => 'Spouse',
        'grandfather_paternal' =>
            'Grandfather (Father\'s side)',
        'grandmother_paternal' =>
            'Grandmother (Father\'s side)',
        'grandfather_maternal' =>
            'Grandfather (Mother\'s side)',
        'grandmother_maternal' =>
            'Grandmother (Mother\'s side)',
        'great_grandfather'    => 'Great Grandfather',
        'great_grandmother'    => 'Great Grandmother',
        'uncle'                => 'Uncle',
        'aunt'                 => 'Aunt',
        'nephew'               => 'Nephew',
        'niece'                => 'Niece',
        'cousin'               => 'Cousin',
        'stepfather'           => 'Stepfather',
        'stepmother'           => 'Stepmother',
        'half_brother'         => 'Half Brother',
        'half_sister'          => 'Half Sister',
        'other'                => 'Relative',
        // Reverse labels
        'son_or_daughter'      => 'Son / Daughter',
        'father_or_mother'     => 'Parent',
        'grandchild'           => 'Grandchild',
        'great_grandchild'     => 'Great Grandchild',
        'stepchild'            => 'Stepchild',
        'uncle_or_aunt'        => 'Uncle / Aunt',
        'nephew_or_niece'      => 'Nephew / Niece',
        'sibling'              => 'Sibling',
        'relative'             => 'Relative',
    ];
    return $labels[$label] ?? ucfirst(
        str_replace('_', ' ', $label)
    );
}

// ── Save relationship ─────────────────────────
function saveRelationship(
    $pdo,
    $member_id_1,
    $member_id_2,
    $type,
    $label = null
) {
    $reverse_type = [
        'parent'  => 'child',
        'child'   => 'parent',
        'spouse'  => 'spouse',
        'sibling' => 'sibling',
    ];

    $reverse_labels = [
        'father'               => 'son_or_daughter',
        'mother'               => 'son_or_daughter',
        'son'                  => 'father_or_mother',
        'daughter'             => 'father_or_mother',
        'grandfather_paternal' => 'grandchild',
        'grandmother_paternal' => 'grandchild',
        'grandfather_maternal' => 'grandchild',
        'grandmother_maternal' => 'grandchild',
        'great_grandfather'    => 'great_grandchild',
        'great_grandmother'    => 'great_grandchild',
        'stepfather'           => 'stepchild',
        'stepmother'           => 'stepchild',
        'nephew'               => 'uncle_or_aunt',
        'niece'                => 'uncle_or_aunt',
        'uncle'                => 'nephew_or_niece',
        'aunt'                 => 'nephew_or_niece',
        'spouse'               => 'spouse',
        'brother'              => 'sibling',
        'sister'               => 'sibling',
        'half_brother'         => 'sibling',
        'half_sister'          => 'sibling',
        'cousin'               => 'cousin',
        'other'                => 'relative',
    ];

    $r_type  = $reverse_type[$type]   ?? $type;
    $r_label = $reverse_labels[$label] ?? $r_type;

    // Check it does not already exist
    $check = $pdo->prepare("
        SELECT relationship_id
        FROM relationships
        WHERE member_id_1 = ?
        AND member_id_2   = ?
    ");
    $check->execute([$member_id_1, $member_id_2]);
    if ($check->fetch()) return;

    // Save forward
    $pdo->prepare("
        INSERT INTO relationships
            (member_id_1, member_id_2,
             type, relation_label)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $member_id_1,
        $member_id_2,
        $type,
        $label,
    ]);

    // Save reverse
    $pdo->prepare("
        INSERT INTO relationships
            (member_id_1, member_id_2,
             type, relation_label)
        VALUES (?, ?, ?, ?)
    ")->execute([
        $member_id_2,
        $member_id_1,
        $r_type,
        $r_label,
    ]);
}

// ── File upload ───────────────────────────────
function uploadFile($file, $type) {
    $allowed = [
        'photo'    => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'document' => [
            'application/pdf',
        ],
        'audio'    => [
            'audio/mpeg',
            'audio/wav',
            'audio/mp4',
        ],
    ];

    if (!isset($allowed[$type]))
        return ['error' => 'Invalid file type'];

    if (!in_array($file['type'], $allowed[$type]))
        return ['error' => 'File format not allowed'];

    if ($file['size'] > MAX_FILE_SIZE)
        return ['error' => 'File exceeds 10MB limit'];

    $ext      = pathinfo(
        $file['name'], PATHINFO_EXTENSION
    );
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $folder   = UPLOAD_PATH . $type . 's/';
    $path     = $folder . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path))
        return ['error' => 'Upload failed'];

    return [
        'path'     => 'uploads/'
                      . $type . 's/' . $filename,
        'filename' => $filename,
        'size'     => $file['size'],
        'mime'     => $file['type'],
    ];
}
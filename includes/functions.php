<?php
// Clean output to prevent XSS
function clean($str) {
    return htmlspecialchars(
        trim($str), ENT_QUOTES, 'UTF-8'
    );
}

// Redirect to a URL
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Format a date nicely
function formatDate($date) {
    if (!$date) return 'Unknown';
    return date('F j, Y', strtotime($date));
}

// Format year only
function formatYear($date) {
    if (!$date) return '?';
    return date('Y', strtotime($date));
}

// Get quarter name by ID
function getQuarterName($pdo, $quarter_id) {
    $stmt = $pdo->prepare(
        "SELECT name FROM quarters
         WHERE quarter_id = ?"
    );
    $stmt->execute([$quarter_id]);
    $row = $stmt->fetch();
    return $row ? $row['name'] : 'Unknown Quarter';
}

// Get all quarters
function getAllQuarters($pdo) {
    return $pdo->query(
        "SELECT * FROM quarters ORDER BY name"
    )->fetchAll();
}

// Count unread messages for a user
function unreadCount($pdo, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM messages
         WHERE receiver_id = ?
         AND is_read = 0"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Check if user has completed their profile
// (i.e. has a family_members record linked)
function hasProfile($pdo, $user_id) {
    $stmt = $pdo->prepare(
        "SELECT member_id FROM users
         WHERE user_id = ?
         AND member_id IS NOT NULL"
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() !== false;
}

// Get the family member record linked
// to a user account
function getUserMember($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT fm.*, q.name AS quarter_name
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

// Get all family members connected to a node
function getConnections($pdo, $member_id) {
    $stmt = $pdo->prepare("
        SELECT
            fm.*,
            COALESCE(
                q.name,
                fm.village_of_origin,
                'Unknown Village'
            ) AS quarter_name,
            r.type AS relation_type
        FROM relationships r
        JOIN family_members fm
          ON (r.member_id_2 = fm.member_id
              AND r.member_id_1 = ?)
          OR (r.member_id_1 = fm.member_id
              AND r.member_id_2 = ?)
        LEFT JOIN quarters q
          ON fm.quarter_id = q.quarter_id
        WHERE fm.member_id != ?
    ");
    $stmt->execute([
        $member_id,
        $member_id,
        $member_id
    ]);
    return $stmt->fetchAll();
}

// Get all members in a user's tree
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
           OR u.user_id  = ?
        ORDER BY fm.created_at ASC
    ");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}

// Save a relationship between two members
function saveRelationship(
    $pdo, $member_id_1,
    $member_id_2, $type
) {
    // Check it does not already exist
    $check = $pdo->prepare("
        SELECT relationship_id
        FROM relationships
        WHERE (member_id_1 = ?
               AND member_id_2 = ?
               AND type = ?)
           OR (member_id_1 = ?
               AND member_id_2 = ?
               AND type = ?)
    ");

    $reverse = [
        'parent'  => 'child',
        'child'   => 'parent',
        'spouse'  => 'spouse',
        'sibling' => 'sibling',
    ];

    $reverse_type = $reverse[$type] ?? $type;

    $check->execute([
        $member_id_1, $member_id_2, $type,
        $member_id_2, $member_id_1, $reverse_type,
    ]);

    if ($check->fetch()) return; // already exists

    // Save forward relationship
    $pdo->prepare("
        INSERT INTO relationships
            (member_id_1, member_id_2, type)
        VALUES (?, ?, ?)
    ")->execute([$member_id_1, $member_id_2, $type]);

    // Save reverse relationship
    $pdo->prepare("
        INSERT INTO relationships
            (member_id_1, member_id_2, type)
        VALUES (?, ?, ?)
    ")->execute([
        $member_id_2,
        $member_id_1,
        $reverse_type
    ]);
}

// Handle file upload
function uploadFile($file, $type) {
    $allowed = [
        'photo'    => [
            'image/jpeg',
            'image/png',
            'image/webp'
        ],
        'document' => [
            'application/pdf'
        ],
        'audio'    => [
            'audio/mpeg',
            'audio/wav',
            'audio/mp4'
        ],
    ];

    if (!isset($allowed[$type])) {
        return ['error' => 'Invalid file type'];
    }

    if (!in_array($file['type'], $allowed[$type])) {
        return ['error' => 'File format not allowed'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File exceeds 10MB limit'];
    }

    $ext = pathinfo(
        $file['name'], PATHINFO_EXTENSION
    );
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $folder   = UPLOAD_PATH . $type . 's/';
    $path     = $folder . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return ['error' => 'Upload failed'];
    }

    return [
        'path'     => 'uploads/' . $type
                      . 's/' . $filename,
        'filename' => $filename,
        'size'     => $file['size'],
        'mime'     => $file['type'],
    ];
}
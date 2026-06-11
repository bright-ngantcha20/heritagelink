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

// Check if two users are connected
function areConnected($pdo, $uid1, $uid2) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM relationships r
        JOIN family_members m1
          ON r.member_id_1 = m1.member_id
        JOIN family_members m2
          ON r.member_id_2 = m2.member_id
        WHERE
          (m1.added_by = ? AND m2.added_by = ?)
          OR
          (m1.added_by = ? AND m2.added_by = ?)
    ");
    $stmt->execute([$uid1,$uid2,$uid2,$uid1]);
    return $stmt->fetchColumn() > 0;
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
        'path'     => 'uploads/' . $type . 's/' . $filename,
        'filename' => $filename,
        'size'     => $file['size'],
        'mime'     => $file['type'],
    ];
}
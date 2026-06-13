<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Must be logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user   = currentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: verify user owns this conversation ──
function verifyConv($pdo, $conv_id, $user_id) {
    $s = $pdo->prepare("
        SELECT conversation_id FROM conversations
        WHERE conversation_id = ?
          AND (user_id_1 = ? OR user_id_2 = ?)
        LIMIT 1
    ");
    $s->execute([$conv_id, $user_id, $user_id]);
    return $s->fetchColumn() !== false;
}

// ── Helper: format a message for JSON ───────────
function formatMsg($msg, $site_url) {
    return [
        'message_id'   => (int)$msg['message_id'],
        'conversation_id' => (int)$msg['conversation_id'],
        'sender_id'    => (int)$msg['sender_id'],
        'receiver_id'  => (int)$msg['receiver_id'],
        'message_text' => $msg['message_text'],
        'is_read'      => (bool)$msg['is_read'],
        'sent_at'      => $msg['sent_at'],
        'sender_name'  => $msg['sender_name'],
        'sender_photo' => $msg['sender_photo']
            ? $site_url . '/' . $msg['sender_photo']
            : null,
    ];
}

// ════════════════════════════════════════════════
switch ($action) {

// ── SEND ────────────────────────────────────────
case 'send':
    if (!csrfVerifyApi()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'POST required']);
        exit;
    }

    $conv_id = (int)($_POST['conv_id'] ?? 0);
    $text    = trim($_POST['message_text'] ?? '');

    if (!$conv_id || empty($text)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing fields']);
        exit;
    }
    if (mb_strlen($text) > 2000) {
        http_response_code(400);
        echo json_encode(['error' => 'Message too long']);
        exit;
    }
    if (!verifyConv($pdo, $conv_id, $user['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    // Get receiver_id
    $cr = $pdo->prepare("
        SELECT CASE
            WHEN user_id_1 = ? THEN user_id_2
            ELSE user_id_1
        END AS other_id
        FROM conversations
        WHERE conversation_id = ?
    ");
    $cr->execute([$user['id'], $conv_id]);
    $receiver_id = (int)$cr->fetchColumn();

    // Insert message
    $ins = $pdo->prepare("
        INSERT INTO messages
            (conversation_id, sender_id, receiver_id,
             message_text, is_read,
             is_tree_initiated, sent_at)
        VALUES (?, ?, ?, ?, 0, 0, NOW())
    ");
    $ins->execute([
        $conv_id,
        $user['id'],
        $receiver_id,
        $text,
    ]);
    $msg_id = $pdo->lastInsertId();

    // Update conversation timestamp
    $pdo->prepare("
        UPDATE conversations
        SET last_message_at = NOW()
        WHERE conversation_id = ?
    ")->execute([$conv_id]);

    // Insert notification
    $pdo->prepare("
        INSERT INTO notifications
            (user_id, message, is_read, created_at)
        VALUES (?, ?, 0, NOW())
    ")->execute([
        $receiver_id,
        clean($user['name']) . ' sent you a message.',
    ]);

    // Return the new message
    $fetch = $pdo->prepare("
        SELECT m.*,
               u.full_name    AS sender_name,
               u.profile_photo AS sender_photo
        FROM   messages m
        JOIN   users u ON u.user_id = m.sender_id
        WHERE  m.message_id = ?
    ");
    $fetch->execute([$msg_id]);
    $new_msg = $fetch->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'      => true,
        'message' => formatMsg($new_msg, SITE_URL),
    ]);
    break;

// ── FETCH (poll for new messages) ───────────────
case 'fetch':
    $conv_id = (int)($_GET['conv_id'] ?? 0);
    $since   = $_GET['since'] ?? '1970-01-01 00:00:00';

    if (!$conv_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing conv_id']);
        exit;
    }
    if (!verifyConv($pdo, $conv_id, $user['id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $msgs = $pdo->prepare("
        SELECT m.*,
               u.full_name     AS sender_name,
               u.profile_photo AS sender_photo
        FROM   messages m
        JOIN   users u ON u.user_id = m.sender_id
        WHERE  m.conversation_id = ?
          AND  m.sent_at > ?
        ORDER  BY m.sent_at ASC
    ");
    $msgs->execute([$conv_id, $since]);
    $rows = $msgs->fetchAll(PDO::FETCH_ASSOC);

    // Auto mark as read
    if (!empty($rows)) {
        $pdo->prepare("
            UPDATE messages
            SET    is_read = 1
            WHERE  conversation_id = ?
              AND  receiver_id = ?
              AND  is_read = 0
        ")->execute([$conv_id, $user['id']]);
    }

    echo json_encode([
        'ok'       => true,
        'messages' => array_map(
            fn($m) => formatMsg($m, SITE_URL), $rows
        ),
        'server_time' => date('Y-m-d H:i:s'),
    ]);
    break;

// ── MARK READ ───────────────────────────────────
case 'mark_read':
    $conv_id = (int)($_POST['conv_id']
        ?? $_GET['conv_id'] ?? 0);

    if (!$conv_id || !verifyConv(
        $pdo, $conv_id, $user['id']
    )) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $pdo->prepare("
        UPDATE messages
        SET    is_read = 1
        WHERE  conversation_id = ?
          AND  receiver_id = ?
          AND  is_read = 0
    ")->execute([$conv_id, $user['id']]);

    echo json_encode(['ok' => true]);
    break;

// ── UNREAD COUNT (for navbar badge) ─────────────
case 'unread_count':
    $count = unreadCount($pdo, $user['id']);
    echo json_encode([
        'ok'    => true,
        'count' => (int)$count,
    ]);
    break;

// ── Unknown action ───────────────────────────────
default:
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    break;
}
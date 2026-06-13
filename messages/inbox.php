<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
$user = currentUser();

// ── Handle new conversation from tree ───────────
// tree.php sends ?member=MEMBER_ID
// We look up which user owns that member
$new_to     = (int)($_GET['to']     ?? 0);
$new_member = (int)($_GET['member'] ?? 0);

// If we got a member_id, find the owning user
if ($new_member && !$new_to) {
    $owner = $pdo->prepare("
        SELECT user_id FROM users
        WHERE member_id = ? LIMIT 1
    ");
    $owner->execute([$new_member]);
    $new_to = (int)($owner->fetchColumn() ?? 0);
}

if ($new_to && $new_to !== $user['id']) {
    // Check if conversation already exists
    $existing = $pdo->prepare("
        SELECT conversation_id FROM conversations
        WHERE (user_id_1 = ? AND user_id_2 = ?)
           OR (user_id_1 = ? AND user_id_2 = ?)
        LIMIT 1
    ");
    $existing->execute([
        $user['id'], $new_to,
        $new_to, $user['id'],
    ]);
    $conv = $existing->fetch();

    if ($conv) {
        header('Location: ' . SITE_URL
            . '/messages/view.php?id='
            . $conv['conversation_id']);
        exit;
    } else {
        // Create new conversation
        $pdo->prepare("
            INSERT INTO conversations
                (user_id_1, user_id_2,
                 last_message_at, created_at)
            VALUES (?, ?, NOW(), NOW())
        ")->execute([$user['id'], $new_to]);

        $conv_id = $pdo->lastInsertId();
        header('Location: ' . SITE_URL
            . '/messages/view.php?id=' . $conv_id
            . ($new_member
                ? '&member=' . $new_member : ''));
        exit;
    }
}

// ── Fetch all conversations ──────────────────────
$convs = $pdo->prepare("
    SELECT
        c.conversation_id,
        c.last_message_at,
        -- The other user
        CASE
            WHEN c.user_id_1 = :uid
            THEN c.user_id_2
            ELSE c.user_id_1
        END AS other_id,
        u.full_name       AS other_name,
        u.profile_photo   AS other_photo,
        -- Last message
        (SELECT message_text
         FROM   messages
         WHERE  conversation_id = c.conversation_id
         ORDER  BY sent_at DESC LIMIT 1
        ) AS last_message,
        -- Unread count for ME
        (SELECT COUNT(*)
         FROM   messages
         WHERE  conversation_id = c.conversation_id
           AND  receiver_id = :uid2
           AND  is_read = 0
        ) AS unread_count
    FROM conversations c
    JOIN users u ON u.user_id = CASE
        WHEN c.user_id_1 = :uid3
        THEN c.user_id_2
        ELSE c.user_id_1
    END
    WHERE c.user_id_1 = :uid4
       OR c.user_id_2 = :uid5
    ORDER BY c.last_message_at DESC
");
$convs->execute([
    ':uid'  => $user['id'],
    ':uid2' => $user['id'],
    ':uid3' => $user['id'],
    ':uid4' => $user['id'],
    ':uid5' => $user['id'],
]);
$conversations = $convs->fetchAll(PDO::FETCH_ASSOC);

$total_unread = array_sum(
    array_column($conversations, 'unread_count')
);

function timeAgo($ts) {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('d M', strtotime($ts));
}
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:700px">

  <!-- Header -->
  <div style="
    display:flex;align-items:center;
    justify-content:space-between;
    margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;
  ">
    <div>
      <h2 style="color:#fff;margin:0 0 0.2rem">
        <i class="ti ti-message me-2"
           style="color:#00d4ff"></i>
        Messages
        <?php if ($total_unread > 0): ?>
        <span style="
          background:#00d4ff;color:#000;
          font-size:0.7rem;font-weight:700;
          padding:2px 8px;border-radius:20px;
          vertical-align:middle;margin-left:6px;
        "><?= $total_unread ?></span>
        <?php endif; ?>
      </h2>
      <p style="color:#666;margin:0;font-size:0.85rem">
        Your conversations with community members.
      </p>
    </div>
  </div>

  <!-- Conversation list -->
  <?php if (empty($conversations)): ?>
  <div style="
    text-align:center;padding:4rem 1rem;color:#444;
  ">
    <i class="ti ti-message-off"
       style="font-size:3rem;display:block;
              margin-bottom:1rem"></i>
    <div style="font-size:1rem;margin-bottom:0.5rem;
                color:#666">
      No messages yet
    </div>
    <p style="font-size:0.85rem;color:#444">
      Start a conversation by clicking
      <strong style="color:#888">Send Message</strong>
      on any member's profile in the family tree.
    </p>
    <a href="<?= SITE_URL ?>/family/tree.php"
       class="btn btn-primary btn-sm mt-2">
      Open Family Tree
    </a>
  </div>

  <?php else: ?>

  <div style="display:flex;flex-direction:column;gap:2px">
  <?php foreach ($conversations as $c):
    $has_unread = $c['unread_count'] > 0;
    $photo_url  = $c['other_photo']
        ? SITE_URL . '/' . $c['other_photo']
        : null;
    $initial    = strtoupper(
        substr($c['other_name'] ?? 'U', 0, 1)
    );
  ?>
  <a href="<?= SITE_URL ?>/messages/view.php
            ?id=<?= $c['conversation_id'] ?>"
     style="text-decoration:none">
    <div style="
      display:flex;align-items:center;
      gap:1rem;padding:1rem 1.1rem;
      background:<?= $has_unread
          ? 'rgba(0,212,255,0.04)'
          : '#111127' ?>;
      border:1px solid <?= $has_unread
          ? 'rgba(0,212,255,0.15)'
          : '#1e1e3a' ?>;
      border-radius:12px;
      transition:border-color 0.15s,background 0.15s;
      margin-bottom:0.4rem;
    " class="conv-row">

      <!-- Avatar -->
      <div style="
        width:46px;height:46px;border-radius:50%;
        background:rgba(0,212,255,0.1);
        border:1px solid rgba(0,212,255,0.2);
        display:flex;align-items:center;
        justify-content:center;
        font-size:1.1rem;font-weight:700;
        color:#00d4ff;flex-shrink:0;
        overflow:hidden;position:relative;
      ">
        <?php if ($photo_url): ?>
          <img src="<?= $photo_url ?>"
               style="width:100%;height:100%;
                      object-fit:cover">
        <?php else: ?>
          <?= $initial ?>
        <?php endif; ?>
        <?php if ($has_unread): ?>
        <div style="
          position:absolute;top:0;right:0;
          width:12px;height:12px;
          background:#00d4ff;border-radius:50%;
          border:2px solid #0d0d1a;
        "></div>
        <?php endif; ?>
      </div>

      <!-- Content -->
      <div style="flex:1;min-width:0">
        <div style="
          display:flex;justify-content:space-between;
          align-items:baseline;gap:0.5rem;
        ">
          <span style="
            color:<?= $has_unread
                ? '#fff' : '#ccc' ?>;
            font-weight:<?= $has_unread
                ? '600' : '400' ?>;
            font-size:0.92rem;
            overflow:hidden;text-overflow:ellipsis;
            white-space:nowrap;
          ">
            <?= clean($c['other_name']) ?>
          </span>
          <span style="
            color:#444;font-size:0.74rem;
            white-space:nowrap;flex-shrink:0;
          ">
            <?= timeAgo($c['last_message_at']) ?>
          </span>
        </div>

        <div style="
          display:flex;align-items:center;
          justify-content:space-between;gap:0.5rem;
          margin-top:2px;
        ">
          <span style="
            color:<?= $has_unread
                ? '#aaa' : '#555' ?>;
            font-size:0.82rem;
            overflow:hidden;text-overflow:ellipsis;
            white-space:nowrap;
          ">
            <?= $c['last_message']
                ? clean(
                    mb_substr(
                        strip_tags($c['last_message']),
                        0, 60
                    ) . (mb_strlen($c['last_message']) > 60
                        ? '…' : '')
                  )
                : '<span style="color:#333;
                    font-style:italic">
                    No messages yet</span>' ?>
          </span>
          <?php if ($has_unread): ?>
          <span style="
            background:#00d4ff;color:#000;
            font-size:0.68rem;font-weight:700;
            padding:1px 7px;border-radius:20px;
            flex-shrink:0;
          "><?= $c['unread_count'] ?></span>
          <?php endif; ?>
        </div>
      </div>

      <i class="ti ti-chevron-right"
         style="color:#2a2a4a;flex-shrink:0"></i>
    </div>
  </a>
  <?php endforeach; ?>
  </div>

  <?php endif; ?>

</div>
</main>

<style>
.conv-row:hover {
  border-color: rgba(0,212,255,0.25) !important;
  background: rgba(0,212,255,0.05) !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>
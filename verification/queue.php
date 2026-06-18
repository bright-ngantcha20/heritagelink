<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
$user = currentUser();

// ── Handle approve / reject ──────────────────────────────
$action  = $_POST['action']  ?? '';
$edit_id = (int)($_POST['edit_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && $edit_id
    && in_array($action, ['approve','reject'])
) {
    csrfVerify();

    // Fetch the edit
    $edit = $pdo->prepare("
        SELECT * FROM pending_edits
        WHERE edit_id = ? AND status = 'pending'
        LIMIT 1
    ");
    $edit->execute([$edit_id]);
    $edit = $edit->fetch(PDO::FETCH_ASSOC);

    if ($edit) {
        if ($action === 'approve') {
            // Map field_changed to actual column
            // (only allow safe column names)
            $allowed = [
                'full_name','preferred_name','gender',
                'date_of_birth','date_of_death',
                'birthplace','current_location',
                'occupation','short_bio',
                'village_of_origin','source_of_info',
            ];
            if (in_array($edit['field_changed'],
                $allowed)) {
                $pdo->prepare("
                    UPDATE family_members
                    SET `{$edit['field_changed']}` = ?
                    WHERE member_id = ?
                ")->execute([
                    $edit['new_value'],
                    $edit['member_id'],
                ]);
            }

            $pdo->prepare("
                UPDATE pending_edits
                SET status      = 'approved',
                    reviewed_by = ?,
                    reviewed_at = NOW()
                WHERE edit_id = ?
            ")->execute([$user['id'], $edit_id]);

            // Notify submitter
            $pdo->prepare("
                INSERT INTO notifications
                    (user_id, message, is_read,
                     created_at)
                VALUES (?, ?, 0, NOW())
            ")->execute([
                $edit['submitted_by'],
                'Your proposed edit to '
                . '"' . $edit['field_changed'] . '"'
                . ' was approved.',
            ]);

            $flash = 'approved';

        } else {
            // Reject
            $pdo->prepare("
                UPDATE pending_edits
                SET status      = 'rejected',
                    reviewed_by = ?,
                    reviewed_at = NOW()
                WHERE edit_id = ?
            ")->execute([$user['id'], $edit_id]);

            $pdo->prepare("
                INSERT INTO notifications
                    (user_id, message, is_read,
                     created_at)
                VALUES (?, ?, 0, NOW())
            ")->execute([
                $edit['submitted_by'],
                'Your proposed edit to '
                . '"' . $edit['field_changed'] . '"'
                . ' was not approved.',
            ]);

            $flash = 'rejected';
        }

        // Audit log
        $pdo->prepare("
            INSERT INTO audit_log
                (user_id, action, target_type,
                 target_id, detail)
            VALUES (?, ?, 'pending_edit', ?, ?)
        ")->execute([
            $user['id'],
            $action . '_edit',
            $edit_id,
            ucfirst($action) . 'd edit #'
                . $edit_id . ' on member_id '
                . $edit['member_id'],
        ]);

        header('Location: ' . SITE_URL
            . '/verification/queue.php?flash='
            . $flash);
        exit;
    }
}

// ── Fetch pending edits ──────────────────────────────────
$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending','approved',
    'rejected','all'])) {
    $filter = 'pending';
}

$where = $filter === 'all'
    ? ''
    : "WHERE pe.status = '$filter'";

$edits = $pdo->query("
    SELECT
        pe.*,
        fm.full_name      AS member_name,
        fm.photo          AS member_photo,
        q.name            AS quarter_name,
        u.full_name       AS submitted_by_name,
        u.profile_photo   AS submitted_by_photo,
        rv.full_name      AS reviewed_by_name
    FROM pending_edits pe
    JOIN family_members fm
        ON fm.member_id = pe.member_id
    LEFT JOIN quarters q
        ON q.quarter_id = fm.quarter_id
    JOIN users u
        ON u.user_id = pe.submitted_by
    LEFT JOIN users rv
        ON rv.user_id = pe.reviewed_by
    $where
    ORDER BY pe.submitted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$counts = $pdo->query("
    SELECT status, COUNT(*) AS n
    FROM pending_edits
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$flash_msg = match($_GET['flash'] ?? '') {
    'approved' => ['✓ Edit approved and applied.',   'success'],
    'rejected' => ['✗ Edit rejected.',               'warning'],
    default    => null,
};

function fieldLabel(string $f): string {
    return ucwords(str_replace('_', ' ', $f));
}

function timeAgo(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff/60)  . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return date('d M Y', strtotime($ts));
}
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:860px">

  <!-- Page header -->
  <div style="margin-bottom:1.75rem">
    <h2 style="color:#fff;margin:0 0 0.25rem">
      <i class="ti ti-shield-check me-2"
         style="color:#00d4ff"></i>
      Verification Queue
    </h2>
    <p style="color:#555;margin:0;font-size:0.85rem">
      Review and approve proposed edits to
      family member records.
    </p>
  </div>

  <!-- Flash -->
  <?php if ($flash_msg): ?>
  <div style="
    background:<?= $flash_msg[1] === 'success'
        ? 'rgba(0,212,255,0.08)'
        : 'rgba(255,159,26,0.08)' ?>;
    border:1px solid <?= $flash_msg[1] === 'success'
        ? 'rgba(0,212,255,0.25)'
        : 'rgba(255,159,26,0.25)' ?>;
    border-radius:10px;
    padding:0.75rem 1.1rem;
    color:<?= $flash_msg[1] === 'success'
        ? '#00d4ff' : '#ff9f1a' ?>;
    font-size:0.88rem;
    margin-bottom:1.25rem;
  ">
    <?= $flash_msg[0] ?>
  </div>
  <?php endif; ?>

  <!-- Filter tabs -->
  <div style="
    display:flex;gap:0.4rem;
    margin-bottom:1.5rem;flex-wrap:wrap;
  ">
    <?php foreach ([
      'pending'  => 'Pending',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'all'      => 'All',
    ] as $key => $label): ?>
    <a href="?filter=<?= $key ?>"
       style="
         padding:0.4rem 1rem;
         border-radius:20px;
         font-size:0.82rem;
         font-weight:600;
         text-decoration:none;
         border:1px solid <?= $filter === $key
             ? '#00d4ff'
             : '#1e1e3a' ?>;
         background:<?= $filter === $key
             ? 'rgba(0,212,255,0.1)'
             : 'transparent' ?>;
         color:<?= $filter === $key
             ? '#00d4ff' : '#555' ?>;
       ">
      <?= $label ?>
      <?php $c = $counts[$key] ?? 0;
      if ($c > 0): ?>
        <span style="
          background:<?= $key === 'pending'
              ? '#00d4ff' : '#2a2a4a' ?>;
          color:<?= $key === 'pending'
              ? '#000' : '#aaa' ?>;
          border-radius:20px;
          padding:1px 7px;
          font-size:0.72rem;
          margin-left:4px;
        "><?= $c ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Edit list -->
  <?php if (empty($edits)): ?>
  <div style="
    text-align:center;padding:4rem 1rem;
    color:#333;
  ">
    <i class="ti ti-checks"
       style="font-size:3rem;display:block;
              margin-bottom:1rem;
              color:#2a2a4a"></i>
    <div style="color:#555;font-size:0.95rem">
      <?= $filter === 'pending'
          ? 'No pending edits — all caught up.'
          : 'No edits found for this filter.' ?>
    </div>
  </div>

  <?php else: ?>
  <div style="
    display:flex;flex-direction:column;gap:0.75rem
  ">
  <?php foreach ($edits as $e):
    $is_pending  = $e['status'] === 'pending';
    $is_approved = $e['status'] === 'approved';
    $photo_url   = $e['submitted_by_photo']
        ? SITE_URL . '/' . $e['submitted_by_photo']
        : null;
    $initial     = strtoupper(
        substr($e['submitted_by_name'] ?? 'U', 0, 1)
    );
  ?>
  <div style="
    background:#111127;
    border:1px solid <?= $is_pending
        ? 'rgba(0,212,255,0.15)'
        : '#1e1e3a' ?>;
    border-radius:12px;
    overflow:hidden;
  ">
    <!-- Card header -->
    <div style="
      display:flex;align-items:center;
      gap:0.85rem;padding:0.9rem 1.1rem;
      border-bottom:1px solid #1a1a30;
      flex-wrap:wrap;gap:0.75rem;
    ">
      <!-- Submitter avatar -->
      <div style="
        width:36px;height:36px;border-radius:50%;
        background:rgba(0,212,255,0.1);
        border:1px solid rgba(0,212,255,0.2);
        display:flex;align-items:center;
        justify-content:center;
        font-size:0.9rem;font-weight:700;
        color:#00d4ff;overflow:hidden;
        flex-shrink:0;
      ">
        <?php if ($photo_url): ?>
          <img src="<?= $photo_url ?>"
               style="width:100%;height:100%;
                      object-fit:cover">
        <?php else: ?>
          <?= $initial ?>
        <?php endif; ?>
      </div>

      <div style="flex:1;min-width:0">
        <div style="
          color:#ccc;font-size:0.85rem;
          overflow:hidden;text-overflow:ellipsis;
          white-space:nowrap;
        ">
          <strong style="color:#fff">
            <?= clean($e['submitted_by_name']) ?>
          </strong>
          proposed an edit to
          <strong style="color:#00d4ff">
            <?= clean($e['member_name']) ?>
          </strong>
          <?php if ($e['quarter_name']): ?>
            <span style="color:#444">
              · <?= clean($e['quarter_name']) ?>
            </span>
          <?php endif; ?>
        </div>
        <div style="color:#444;font-size:0.75rem;
                    margin-top:2px">
          <?= timeAgo($e['submitted_at']) ?>
          &nbsp;·&nbsp;
          Edit #<?= $e['edit_id'] ?>
        </div>
      </div>

      <!-- Status badge -->
      <?php
      $badge_color = match($e['status']) {
          'approved' => ['rgba(0,212,255,0.08)',
                         'rgba(0,212,255,0.3)',
                         '#00d4ff'],
          'rejected' => ['rgba(255,107,122,0.08)',
                         'rgba(255,107,122,0.3)',
                         '#ff6b7a'],
          default    => ['rgba(255,159,26,0.08)',
                         'rgba(255,159,26,0.3)',
                         '#ff9f1a'],
      };
      ?>
      <span style="
        background:<?= $badge_color[0] ?>;
        border:1px solid <?= $badge_color[1] ?>;
        color:<?= $badge_color[2] ?>;
        border-radius:20px;
        padding:2px 10px;
        font-size:0.75rem;
        font-weight:600;
        flex-shrink:0;
        text-transform:uppercase;
        letter-spacing:0.04em;
      ">
        <?= $e['status'] ?>
      </span>
    </div>

    <!-- Change detail -->
    <div style="padding:0.9rem 1.1rem">

      <!-- Field name -->
      <div style="
        font-size:0.78rem;color:#555;
        text-transform:uppercase;
        letter-spacing:0.06em;
        margin-bottom:0.6rem;
      ">
        Field: <strong style="color:#888">
          <?= clean(fieldLabel($e['field_changed'])) ?>
        </strong>
      </div>

      <!-- Old → New comparison -->
      <div style="
        display:grid;grid-template-columns:1fr auto 1fr;
        gap:0.75rem;align-items:start;
      ">
        <!-- Old value -->
        <div style="
          background:#0d0d1a;
          border:1px solid #1e1e3a;
          border-radius:8px;padding:0.65rem 0.85rem;
        ">
          <div style="
            font-size:0.7rem;color:#444;
            margin-bottom:4px;
            text-transform:uppercase;
            letter-spacing:0.05em;
          ">Current value</div>
          <div style="color:#777;font-size:0.88rem;
                      word-break:break-word">
            <?= $e['old_value']
                ? clean($e['old_value'])
                : '<em style="color:#333">
                   (empty)</em>' ?>
          </div>
        </div>

        <!-- Arrow -->
        <div style="
          color:#2a2a4a;font-size:1.2rem;
          padding-top:1.5rem;
        ">→</div>

        <!-- New value -->
        <div style="
          background:rgba(0,212,255,0.04);
          border:1px solid rgba(0,212,255,0.12);
          border-radius:8px;padding:0.65rem 0.85rem;
        ">
          <div style="
            font-size:0.7rem;color:#00d4ff;
            opacity:0.6;margin-bottom:4px;
            text-transform:uppercase;
            letter-spacing:0.05em;
          ">Proposed value</div>
          <div style="color:#e0e0e0;font-size:0.88rem;
                      word-break:break-word">
            <?= $e['new_value']
                ? clean($e['new_value'])
                : '<em style="color:#333">
                   (empty)</em>' ?>
          </div>
        </div>
      </div>

      <!-- Reason -->
      <?php if ($e['reason']): ?>
      <div style="
        margin-top:0.75rem;
        background:#0d0d1a;
        border-left:3px solid #2a2a4a;
        border-radius:0 6px 6px 0;
        padding:0.55rem 0.85rem;
        font-size:0.82rem;color:#666;
        font-style:italic;
      ">
        "<?= clean($e['reason']) ?>"
      </div>
      <?php endif; ?>

      <!-- Reviewed by note -->
      <?php if ($e['reviewed_by_name']): ?>
      <div style="
        margin-top:0.6rem;
        font-size:0.78rem;color:#444;
      ">
        <?= ucfirst($e['status']) ?> by
        <strong style="color:#666">
          <?= clean($e['reviewed_by_name']) ?>
        </strong>
        · <?= timeAgo($e['reviewed_at']) ?>
      </div>
      <?php endif; ?>

      <!-- Action buttons (pending only) -->
      <?php if ($is_pending
                && $user['role'] === 'admin'): ?>
      <div style="
        display:flex;gap:0.6rem;
        margin-top:1rem;
      ">
        <form method="POST" action="">
          <?= csrfField() ?>
          <input type="hidden" name="edit_id"
                 value="<?= $e['edit_id'] ?>">
          <input type="hidden" name="action"
                 value="approve">
          <button type="submit" style="
            background:#00d4ff;color:#000;
            border:none;border-radius:8px;
            padding:0.5rem 1.25rem;
            font-size:0.85rem;font-weight:700;
            cursor:pointer;
          ">
            <i class="ti ti-check me-1"></i>
            Approve
          </button>
        </form>
        <form method="POST" action="">
          <?= csrfField() ?>
          <input type="hidden" name="edit_id"
                 value="<?= $e['edit_id'] ?>">
          <input type="hidden" name="action"
                 value="reject">
          <button type="submit" style="
            background:transparent;
            border:1px solid rgba(255,107,122,0.3);
            color:#ff6b7a;
            border-radius:8px;
            padding:0.5rem 1.25rem;
            font-size:0.85rem;font-weight:600;
            cursor:pointer;
          ">
            <i class="ti ti-x me-1"></i>
            Reject
          </button>
        </form>
        <a href="<?= SITE_URL ?>/family/tree.php"
           style="
             color:#555;font-size:0.82rem;
             text-decoration:none;
             padding:0.5rem 0.75rem;
             display:flex;align-items:center;gap:4px;
           ">
          <i class="ti ti-git-fork"></i>
          View in tree
        </a>
      </div>
      <?php elseif ($is_pending
                    && $user['role'] !== 'admin'): ?>
      <div style="
        margin-top:0.75rem;
        font-size:0.8rem;color:#444;
      ">
        Awaiting admin review.
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</main>

<?php require_once '../includes/footer.php'; ?>
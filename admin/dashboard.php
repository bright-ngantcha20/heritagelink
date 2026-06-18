<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$user = currentUser();

// ── Stats ────────────────────────────────────────────────
$stats = [
    'total_members'    => $pdo->query(
        "SELECT COUNT(*) FROM family_members"
    )->fetchColumn(),

    'verified_members' => $pdo->query(
        "SELECT COUNT(*) FROM family_members
         WHERE verified = 1"
    )->fetchColumn(),

    'total_users'      => $pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE role = 'member'"
    )->fetchColumn(),

    'admin_users'      => $pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE role = 'admin'"
    )->fetchColumn(),

    'pending_edits'    => $pdo->query(
        "SELECT COUNT(*) FROM pending_edits
         WHERE status = 'pending'"
    )->fetchColumn(),

    'total_heritage'   => $pdo->query(
        "SELECT COUNT(*) FROM heritage_records"
    )->fetchColumn(),

    'pending_contrib'  => $pdo->query(
        "SELECT COUNT(*) FROM contributions
         WHERE status = 'pending'"
    )->fetchColumn(),

    'total_messages'   => $pdo->query(
        "SELECT COUNT(*) FROM messages"
    )->fetchColumn(),

    'total_quarters'   => $pdo->query(
        "SELECT COUNT(*) FROM quarters"
    )->fetchColumn(),

    'total_relationships' => $pdo->query(
        "SELECT COUNT(*) FROM relationships"
    )->fetchColumn(),
];

// ── Recent activity (audit log) ──────────────────────────
$recent_activity = $pdo->query("
    SELECT
        al.action,
        al.target_type,
        al.detail,
        al.logged_at,
        u.full_name AS actor_name,
        u.profile_photo AS actor_photo
    FROM audit_log al
    JOIN users u ON u.user_id = al.user_id
    ORDER BY al.logged_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Recent registrations ─────────────────────────────────
$recent_users = $pdo->query("
    SELECT
        u.user_id, u.full_name, u.email,
        u.role, u.created_at,
        u.profile_photo,
        q.name AS quarter_name
    FROM users u
    LEFT JOIN quarters q
        ON q.quarter_id = u.quarter_id
    ORDER BY u.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// ── Members per quarter ──────────────────────────────────
$by_quarter = $pdo->query("
    SELECT
        q.name AS quarter_name,
        COUNT(fm.member_id) AS member_count
    FROM quarters q
    LEFT JOIN family_members fm
        ON fm.quarter_id = q.quarter_id
    GROUP BY q.quarter_id, q.name
    ORDER BY member_count DESC
")->fetchAll(PDO::FETCH_ASSOC);

function timeAgo(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60)  . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('d M Y', strtotime($ts));
}

function actionLabel(string $action): string {
    return match($action) {
        'add_member'            => 'Added member',
        'link_existing_member'  => 'Linked member',
        'submit_edit'           => 'Proposed edit',
        'approve_edit'          => 'Approved edit',
        'reject_edit'           => 'Rejected edit',
        'login'                 => 'Logged in',
        default => ucwords(
            str_replace('_', ' ', $action)
        ),
    };
}
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container-fluid" style="max-width:1100px">

  <!-- Page header -->
  <div style="
    display:flex;align-items:center;
    justify-content:space-between;
    margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;
  ">
    <div>
      <h2 style="color:#fff;margin:0 0 0.2rem">
        <i class="ti ti-layout-dashboard me-2"
           style="color:#00d4ff"></i>
        Admin Dashboard
      </h2>
      <p style="color:#555;margin:0;
                font-size:0.85rem">
        System overview for HeritageLink.
      </p>
    </div>
    <div style="display:flex;gap:0.6rem;
                flex-wrap:wrap">
      <a href="<?= SITE_URL ?>/admin/users.php"
         style="
           background:rgba(0,212,255,0.1);
           border:1px solid rgba(0,212,255,0.25);
           color:#00d4ff;border-radius:8px;
           padding:0.5rem 1rem;font-size:0.85rem;
           text-decoration:none;font-weight:600;
         ">
        <i class="ti ti-users me-1"></i>
        Manage Users
      </a>
      <a href="<?= SITE_URL ?>/verification/queue.php"
         style="
           background:<?= $stats['pending_edits'] > 0
               ? 'rgba(255,159,26,0.1)'
               : 'rgba(255,255,255,0.03)' ?>;
           border:1px solid <?= $stats['pending_edits'] > 0
               ? 'rgba(255,159,26,0.3)'
               : '#1e1e3a' ?>;
           color:<?= $stats['pending_edits'] > 0
               ? '#ff9f1a' : '#555' ?>;
           border-radius:8px;
           padding:0.5rem 1rem;font-size:0.85rem;
           text-decoration:none;font-weight:600;
         ">
        <i class="ti ti-shield-check me-1"></i>
        Verification Queue
        <?php if ($stats['pending_edits'] > 0): ?>
          <span style="
            background:#ff9f1a;color:#000;
            border-radius:20px;padding:1px 7px;
            font-size:0.72rem;margin-left:4px;
          "><?= $stats['pending_edits'] ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <!-- ── Stat cards ─────────────────────────────────── -->
  <div style="
    display:grid;
    grid-template-columns:repeat(
      auto-fill, minmax(175px, 1fr));
    gap:0.85rem;
    margin-bottom:2rem;
  ">
  <?php
  $cards = [
    ['ti-users',         'Community Members',  $stats['total_members'],       '#00d4ff'],
    ['ti-circle-check',  'Verified Members',   $stats['verified_members'],    '#00cc88'],
    ['ti-user',          'Registered Users',   $stats['total_users'],         '#9b72ff'],
    ['ti-shield',        'Admin Accounts',     $stats['admin_users'],         '#ff9f1a'],
    ['ti-clock',         'Pending Edits',      $stats['pending_edits'],       $stats['pending_edits'] > 0 ? '#ff9f1a' : '#555'],
    ['ti-book',          'Heritage Records',   $stats['total_heritage'],      '#00cc88'],
    ['ti-file-check',    'Pending Contributions', $stats['pending_contrib'],  $stats['pending_contrib'] > 0 ? '#ff9f1a' : '#555'],
    ['ti-message',       'Total Messages',     $stats['total_messages'],      '#9b72ff'],
    ['ti-git-fork',      'Relationships',      $stats['total_relationships'], '#00d4ff'],
    ['ti-map-pin',       'Quarters',           $stats['total_quarters'],      '#ff6b7a'],
  ];
  foreach ($cards as [$icon, $label, $value, $col]):
  ?>
  <div style="
    background:#111127;
    border:1px solid #1e1e3a;
    border-radius:12px;
    padding:1.1rem 1.1rem 0.9rem;
  ">
    <i class="ti <?= $icon ?>"
       style="font-size:1.4rem;color:<?= $col ?>;
              display:block;margin-bottom:0.4rem">
    </i>
    <div style="
      font-size:1.6rem;font-weight:700;
      color:#fff;line-height:1;
      margin-bottom:0.25rem;
    "><?= number_format($value) ?></div>
    <div style="
      font-size:0.78rem;color:#555;
    "><?= $label ?></div>
  </div>
  <?php endforeach; ?>
  </div>

  <!-- ── Two-column lower section ──────────────────── -->
  <div style="
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:1.25rem;
  " class="admin-grid">

    <!-- Recent Activity -->
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:12px;
      overflow:hidden;
    ">
      <div style="
        padding:0.85rem 1.1rem;
        border-bottom:1px solid #1a1a30;
        display:flex;align-items:center;
        justify-content:space-between;
      ">
        <span style="color:#fff;font-weight:600;
                     font-size:0.9rem">
          <i class="ti ti-activity me-2"
             style="color:#00d4ff"></i>
          Recent Activity
        </span>
      </div>

      <?php if (empty($recent_activity)): ?>
      <div style="
        padding:2rem;text-align:center;
        color:#333;font-size:0.85rem;
      ">No activity yet.</div>
      <?php else: ?>
      <div style="padding:0.5rem 0">
      <?php foreach ($recent_activity as $act):
        $photo = $act['actor_photo']
            ? SITE_URL . '/' . $act['actor_photo']
            : null;
        $init  = strtoupper(
            substr($act['actor_name'] ?? 'U', 0, 1)
        );
      ?>
      <div style="
        display:flex;align-items:flex-start;
        gap:0.75rem;
        padding:0.65rem 1.1rem;
        border-bottom:1px solid #0d0d1a;
      ">
        <div style="
          width:30px;height:30px;border-radius:50%;
          background:rgba(0,212,255,0.1);
          display:flex;align-items:center;
          justify-content:center;
          font-size:0.75rem;font-weight:700;
          color:#00d4ff;overflow:hidden;flex-shrink:0;
        ">
          <?php if ($photo): ?>
            <img src="<?= $photo ?>"
                 style="width:100%;height:100%;
                        object-fit:cover">
          <?php else: ?>
            <?= $init ?>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="
            font-size:0.82rem;color:#ccc;
            overflow:hidden;text-overflow:ellipsis;
            white-space:nowrap;
          ">
            <strong style="color:#fff">
              <?= clean($act['actor_name']) ?>
            </strong>
            — <?= actionLabel($act['action']) ?>
          </div>
          <?php if ($act['detail']): ?>
          <div style="
            font-size:0.75rem;color:#444;
            overflow:hidden;text-overflow:ellipsis;
            white-space:nowrap;margin-top:1px;
          ">
            <?= clean(mb_substr($act['detail'], 0, 60))
                . (mb_strlen($act['detail']) > 60
                   ? '…' : '') ?>
          </div>
          <?php endif; ?>
        </div>
        <div style="
          font-size:0.7rem;color:#333;
          flex-shrink:0;white-space:nowrap;
        ">
          <?= timeAgo($act['logged_at']) ?>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right column: Recent Users + Members by Quarter -->
    <div style="
      display:flex;flex-direction:column;gap:1.25rem
    ">

      <!-- Recent registrations -->
      <div style="
        background:#111127;
        border:1px solid #1e1e3a;
        border-radius:12px;overflow:hidden;
      ">
        <div style="
          padding:0.85rem 1.1rem;
          border-bottom:1px solid #1a1a30;
          display:flex;align-items:center;
          justify-content:space-between;
        ">
          <span style="color:#fff;font-weight:600;
                       font-size:0.9rem">
            <i class="ti ti-user-plus me-2"
               style="color:#9b72ff"></i>
            Recent Registrations
          </span>
          <a href="<?= SITE_URL ?>/admin/users.php"
             style="color:#555;font-size:0.78rem;
                    text-decoration:none">
            View all →
          </a>
        </div>

        <?php if (empty($recent_users)): ?>
        <div style="
          padding:1.5rem;text-align:center;
          color:#333;font-size:0.85rem;
        ">No users yet.</div>
        <?php else: ?>
        <div style="padding:0.4rem 0">
        <?php foreach ($recent_users as $u):
          $photo = $u['profile_photo']
              ? SITE_URL . '/' . $u['profile_photo']
              : null;
          $init  = strtoupper(
              substr($u['full_name'] ?? 'U', 0, 1)
          );
        ?>
        <div style="
          display:flex;align-items:center;
          gap:0.75rem;padding:0.55rem 1.1rem;
          border-bottom:1px solid #0d0d1a;
        ">
          <div style="
            width:28px;height:28px;border-radius:50%;
            background:rgba(155,114,255,0.1);
            display:flex;align-items:center;
            justify-content:center;
            font-size:0.72rem;font-weight:700;
            color:#9b72ff;overflow:hidden;flex-shrink:0;
          ">
            <?php if ($photo): ?>
              <img src="<?= $photo ?>"
                   style="width:100%;height:100%;
                          object-fit:cover">
            <?php else: ?>
              <?= $init ?>
            <?php endif; ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="
              font-size:0.82rem;color:#ccc;
              overflow:hidden;text-overflow:ellipsis;
              white-space:nowrap;
            "><?= clean($u['full_name']) ?></div>
            <div style="
              font-size:0.72rem;color:#444;
            "><?= clean($u['quarter_name'] ?? '—') ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <span style="
              font-size:0.68rem;font-weight:600;
              padding:1px 8px;border-radius:20px;
              background:<?= $u['role'] === 'admin'
                  ? 'rgba(255,159,26,0.1)'
                  : 'rgba(0,212,255,0.07)' ?>;
              color:<?= $u['role'] === 'admin'
                  ? '#ff9f1a' : '#00d4ff' ?>;
              border:1px solid <?= $u['role'] === 'admin'
                  ? 'rgba(255,159,26,0.25)'
                  : 'rgba(0,212,255,0.15)' ?>;
            "><?= $u['role'] ?></span>
            <div style="
              font-size:0.7rem;color:#333;
              margin-top:2px;
            "><?= timeAgo($u['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Members by quarter -->
      <div style="
        background:#111127;
        border:1px solid #1e1e3a;
        border-radius:12px;overflow:hidden;
      ">
        <div style="
          padding:0.85rem 1.1rem;
          border-bottom:1px solid #1a1a30;
        ">
          <span style="color:#fff;font-weight:600;
                       font-size:0.9rem">
            <i class="ti ti-map-pin me-2"
               style="color:#ff6b7a"></i>
            Members by Quarter
          </span>
        </div>
        <div style="padding:0.75rem 1.1rem">
        <?php
        $max = max(array_column($by_quarter,
            'member_count') ?: [1]);
        foreach ($by_quarter as $q):
          $pct = $max > 0
              ? round(($q['member_count']/$max)*100)
              : 0;
        ?>
        <div style="margin-bottom:0.75rem">
          <div style="
            display:flex;justify-content:space-between;
            font-size:0.8rem;margin-bottom:4px;
          ">
            <span style="color:#aaa">
              <?= clean($q['quarter_name']) ?>
            </span>
            <span style="color:#555">
              <?= $q['member_count'] ?>
            </span>
          </div>
          <div style="
            background:#0d0d1a;border-radius:4px;
            height:6px;overflow:hidden;
          ">
            <div style="
              width:<?= $pct ?>%;height:100%;
              background:#00d4ff;border-radius:4px;
              transition:width 0.3s;
            "></div>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>

</div>
</main>

<style>
@media (max-width: 768px) {
  .admin-grid {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php require_once '../includes/footer.php'; ?>
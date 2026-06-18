<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();
$user = currentUser();

// ── Handle actions ───────────────────────────────────────
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $action     = $_POST['action']  ?? '';
    $target_uid = (int)($_POST['user_id'] ?? 0);

    // Cannot act on yourself
    if ($target_uid && $target_uid !== $user['id']) {

        // Fetch target user
        $target = $pdo->prepare("
            SELECT * FROM users
            WHERE user_id = ? LIMIT 1
        ");
        $target->execute([$target_uid]);
        $target = $target->fetch(PDO::FETCH_ASSOC);

        if ($target) {
            switch ($action) {

                case 'make_admin':
                    $pdo->prepare("
                        UPDATE users SET role = 'admin'
                        WHERE user_id = ?
                    ")->execute([$target_uid]);
                    $pdo->prepare("
                        INSERT INTO audit_log
                            (user_id, action,
                             target_type, target_id,
                             detail)
                        VALUES (?, 'make_admin',
                                'user', ?, ?)
                    ")->execute([
                        $user['id'], $target_uid,
                        'Promoted '
                        . $target['full_name']
                        . ' to admin',
                    ]);
                    $flash = ['success',
                        clean($target['full_name'])
                        . ' promoted to admin.'];
                    break;

                case 'remove_admin':
                    // Prevent removing last admin
                    $admin_count = $pdo->query("
                        SELECT COUNT(*) FROM users
                        WHERE role = 'admin'
                    ")->fetchColumn();
                    if ($admin_count <= 1) {
                        $flash = ['error',
                            'Cannot remove the last '
                            . 'admin account.'];
                    } else {
                        $pdo->prepare("
                            UPDATE users
                            SET role = 'member'
                            WHERE user_id = ?
                        ")->execute([$target_uid]);
                        $pdo->prepare("
                            INSERT INTO audit_log
                                (user_id, action,
                                 target_type,
                                 target_id, detail)
                            VALUES
                                (?, 'remove_admin',
                                 'user', ?, ?)
                        ")->execute([
                            $user['id'], $target_uid,
                            'Removed admin from '
                            . $target['full_name'],
                        ]);
                        $flash = ['success',
                            clean($target['full_name'])
                            . ' is now a member.'];
                    }
                    break;

                case 'verify_member':
                    // Verify their linked family member
                    if ($target['member_id']) {
                        $pdo->prepare("
                            UPDATE family_members
                            SET verified = 1
                            WHERE member_id = ?
                        ")->execute([
                            $target['member_id']
                        ]);
                        $flash = ['success',
                            clean($target['full_name'])
                            . '\'s profile verified.'];
                    } else {
                        $flash = ['error',
                            'This user has no linked '
                            . 'family member profile.'];
                    }
                    break;

                case 'deactivate':
                    // We don't delete — we remove
                    // the member link so they can't
                    // access tree data, but account
                    // stays for audit purposes.
                    // Real deactivation = change email
                    // to prevent login re-use.
                    $pdo->prepare("
                        UPDATE users
                        SET email = CONCAT(
                            'deactivated_',
                            user_id, '_', email
                        )
                        WHERE user_id = ?
                          AND role != 'admin'
                    ")->execute([$target_uid]);
                    $pdo->prepare("
                        INSERT INTO audit_log
                            (user_id, action,
                             target_type,
                             target_id, detail)
                        VALUES
                            (?, 'deactivate_user',
                             'user', ?, ?)
                    ")->execute([
                        $user['id'], $target_uid,
                        'Deactivated account: '
                        . $target['full_name'],
                    ]);
                    $flash = ['success',
                        clean($target['full_name'])
                        . '\'s account deactivated.'];
                    break;
            }
        }
    } else if ($target_uid === $user['id']) {
        $flash = ['error',
            'You cannot modify your own account '
            . 'from this panel.'];
    }

    header('Location: ' . SITE_URL
        . '/admin/users.php'
        . ($flash ? '?flash=' . urlencode(
            $flash[0] . '|' . $flash[1]) : ''));
    exit;
}

// ── Flash from redirect ──────────────────────────────────
if (isset($_GET['flash'])) {
    $parts = explode('|', $_GET['flash'], 2);
    $flash = [$parts[0], $parts[1] ?? ''];
}

// ── Search / filter ──────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all','admin','member',
    'verified','no_profile'])) {
    $filter = 'all';
}

$where  = [];
$params = [];

if ($search) {
    $where[]  = "(u.full_name LIKE ?
                  OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

switch ($filter) {
    case 'admin':
        $where[] = "u.role = 'admin'"; break;
    case 'member':
        $where[] = "u.role = 'member'"; break;
    case 'verified':
        $where[] = "fm.verified = 1"; break;
    case 'no_profile':
        $where[] = "u.member_id IS NULL"; break;
}

$where_sql = $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';

$users = $pdo->prepare("
    SELECT
        u.user_id, u.full_name, u.email,
        u.role, u.created_at,
        u.profile_photo, u.member_id,
        q.name         AS quarter_name,
        fm.verified    AS member_verified,
        fm.full_name   AS member_name,
        COUNT(DISTINCT r.relationship_id)
                       AS connection_count
    FROM users u
    LEFT JOIN quarters q
        ON q.quarter_id = u.quarter_id
    LEFT JOIN family_members fm
        ON fm.member_id = u.member_id
    LEFT JOIN relationships r
        ON r.member_id_1 = fm.member_id
    $where_sql
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");
$users->execute($params);
$all_users = $users->fetchAll(PDO::FETCH_ASSOC);

// Counts for filter tabs
$tab_counts = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(role = 'admin') AS admins,
        SUM(role = 'member') AS members,
        SUM(member_id IS NULL) AS no_profile
    FROM users
")->fetch(PDO::FETCH_ASSOC);

function timeAgo(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60)  . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('d M Y', strtotime($ts));
}
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:1000px">

  <!-- Header -->
  <div style="
    display:flex;align-items:center;
    justify-content:space-between;
    margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;
  ">
    <div>
      <h2 style="color:#fff;margin:0 0 0.2rem">
        <i class="ti ti-users me-2"
           style="color:#9b72ff"></i>
        User Management
      </h2>
      <p style="color:#555;margin:0;
                font-size:0.85rem">
        <?= count($all_users) ?> user<?=
            count($all_users) !== 1 ? 's' : '' ?>
        <?= $search ? 'matching "' . clean($search)
            . '"' : 'registered' ?>.
      </p>
    </div>
    <a href="<?= SITE_URL ?>/admin/dashboard.php"
       style="
         color:#555;font-size:0.85rem;
         text-decoration:none;
         display:flex;align-items:center;gap:4px;
       ">
      <i class="ti ti-arrow-left"></i>
      Admin Dashboard
    </a>
  </div>

  <!-- Flash -->
  <?php if ($flash): ?>
  <div style="
    background:<?= $flash[0] === 'success'
        ? 'rgba(0,204,136,0.08)'
        : 'rgba(255,107,122,0.08)' ?>;
    border:1px solid <?= $flash[0] === 'success'
        ? 'rgba(0,204,136,0.25)'
        : 'rgba(255,107,122,0.25)' ?>;
    border-radius:10px;
    padding:0.75rem 1.1rem;
    color:<?= $flash[0] === 'success'
        ? '#00cc88' : '#ff6b7a' ?>;
    font-size:0.88rem;
    margin-bottom:1.25rem;
  ">
    <?= clean($flash[1]) ?>
  </div>
  <?php endif; ?>

  <!-- Search + filters -->
  <div style="
    display:flex;gap:0.75rem;
    margin-bottom:1.25rem;flex-wrap:wrap;
  ">
    <form method="GET" action=""
          style="flex:1;min-width:220px">
      <input type="hidden" name="filter"
             value="<?= clean($filter) ?>">
      <input type="text" name="search"
             value="<?= clean($search) ?>"
             placeholder="Search by name or email…"
             style="
               width:100%;
               background:#0d0d1a;
               border:1px solid #1e1e3a;
               color:#e0e0e0;border-radius:8px;
               padding:0.55rem 0.9rem;
               font-size:0.85rem;outline:none;
             ">
    </form>

    <div style="display:flex;gap:0.4rem;
                flex-wrap:wrap">
    <?php foreach ([
      'all'        => 'All ('
                     . $tab_counts['total'] . ')',
      'admin'      => 'Admins ('
                     . $tab_counts['admins'] . ')',
      'member'     => 'Members ('
                     . $tab_counts['members'] . ')',
      'no_profile' => 'No Profile ('
                     . $tab_counts['no_profile'] . ')',
    ] as $key => $label): ?>
    <a href="?filter=<?= $key ?>&search=<?=
           urlencode($search) ?>"
       style="
         padding:0.45rem 0.9rem;
         border-radius:20px;
         font-size:0.8rem;font-weight:600;
         text-decoration:none;
         border:1px solid <?= $filter === $key
             ? '#9b72ff'
             : '#1e1e3a' ?>;
         background:<?= $filter === $key
             ? 'rgba(155,114,255,0.1)'
             : 'transparent' ?>;
         color:<?= $filter === $key
             ? '#9b72ff' : '#555' ?>;
         white-space:nowrap;
       ">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
    </div>
  </div>

  <!-- User list -->
  <?php if (empty($all_users)): ?>
  <div style="
    text-align:center;padding:3rem;
    color:#333;font-size:0.9rem;
  ">
    <i class="ti ti-users-off"
       style="font-size:2.5rem;display:block;
              margin-bottom:0.75rem;
              color:#2a2a4a"></i>
    No users found.
  </div>
  <?php else: ?>
  <div style="
    display:flex;flex-direction:column;gap:0.5rem
  ">
  <?php foreach ($all_users as $u):
    $is_me   = $u['user_id'] === $user['id'];
    $is_admin= $u['role'] === 'admin';
    $photo   = $u['profile_photo']
        ? SITE_URL . '/' . $u['profile_photo']
        : null;
    $initial = strtoupper(
        substr($u['full_name'] ?? 'U', 0, 1)
    );
  ?>
  <div style="
    background:#111127;
    border:1px solid #1e1e3a;
    border-radius:12px;
    padding:0.9rem 1.1rem;
    display:flex;align-items:center;
    gap:1rem;flex-wrap:wrap;
  ">

    <!-- Avatar -->
    <div style="
      width:42px;height:42px;border-radius:50%;
      background:<?= $is_admin
          ? 'rgba(255,159,26,0.1)'
          : 'rgba(155,114,255,0.1)' ?>;
      border:1px solid <?= $is_admin
          ? 'rgba(255,159,26,0.3)'
          : 'rgba(155,114,255,0.25)' ?>;
      display:flex;align-items:center;
      justify-content:center;
      font-size:1rem;font-weight:700;
      color:<?= $is_admin ? '#ff9f1a' : '#9b72ff' ?>;
      overflow:hidden;flex-shrink:0;
    ">
      <?php if ($photo): ?>
        <img src="<?= $photo ?>"
             style="width:100%;height:100%;
                    object-fit:cover">
      <?php else: ?>
        <?= $initial ?>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div style="flex:1;min-width:150px">
      <div style="
        color:#fff;font-weight:600;
        font-size:0.9rem;
        display:flex;align-items:center;gap:0.5rem;
        flex-wrap:wrap;
      ">
        <?= clean($u['full_name']) ?>
        <?php if ($is_me): ?>
        <span style="
          font-size:0.68rem;background:#1e1e3a;
          color:#555;padding:1px 7px;
          border-radius:20px;
        ">You</span>
        <?php endif; ?>
        <?php if ($u['member_verified']): ?>
        <span style="
          font-size:0.68rem;color:#00cc88;
        ">✓ Verified</span>
        <?php endif; ?>
      </div>
      <div style="
        color:#444;font-size:0.78rem;
        margin-top:2px;
      ">
        <?= clean($u['email']) ?>
        <?php if ($u['quarter_name']): ?>
          · <?= clean($u['quarter_name']) ?>
        <?php endif; ?>
      </div>
      <div style="
        color:#333;font-size:0.72rem;
        margin-top:2px;
      ">
        Joined <?= timeAgo($u['created_at']) ?>
        · <?= $u['connection_count'] ?>
        connection<?= $u['connection_count'] != 1
            ? 's' : '' ?>
        <?php if (!$u['member_id']): ?>
        · <span style="color:#ff9f1a">
            No profile linked
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Role badge -->
    <span style="
      padding:2px 10px;border-radius:20px;
      font-size:0.72rem;font-weight:700;
      text-transform:uppercase;
      letter-spacing:0.05em;
      background:<?= $is_admin
          ? 'rgba(255,159,26,0.1)'
          : 'rgba(0,212,255,0.07)' ?>;
      border:1px solid <?= $is_admin
          ? 'rgba(255,159,26,0.3)'
          : 'rgba(0,212,255,0.15)' ?>;
      color:<?= $is_admin ? '#ff9f1a' : '#00d4ff' ?>;
      flex-shrink:0;
    "><?= $u['role'] ?></span>

    <!-- Actions -->
    <?php if (!$is_me): ?>
    <div style="
      display:flex;gap:0.4rem;flex-wrap:wrap;
      flex-shrink:0;
    ">
      <?php if (!$is_admin): ?>
      <!-- Promote to admin -->
      <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="user_id"
               value="<?= $u['user_id'] ?>">
        <input type="hidden" name="action"
               value="make_admin">
        <button type="submit"
                title="Promote to admin"
                style="
                  background:rgba(255,159,26,0.08);
                  border:1px solid
                    rgba(255,159,26,0.25);
                  color:#ff9f1a;border-radius:7px;
                  padding:0.35rem 0.75rem;
                  font-size:0.78rem;cursor:pointer;
                ">
          <i class="ti ti-shield-up"></i>
          Make Admin
        </button>
      </form>

      <?php if (!$u['member_verified']
                && $u['member_id']): ?>
      <!-- Verify member -->
      <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="user_id"
               value="<?= $u['user_id'] ?>">
        <input type="hidden" name="action"
               value="verify_member">
        <button type="submit"
                title="Mark as verified"
                style="
                  background:rgba(0,204,136,0.07);
                  border:1px solid
                    rgba(0,204,136,0.2);
                  color:#00cc88;border-radius:7px;
                  padding:0.35rem 0.75rem;
                  font-size:0.78rem;cursor:pointer;
                ">
          <i class="ti ti-circle-check"></i>
          Verify
        </button>
      </form>
      <?php endif; ?>

      <!-- Deactivate -->
      <form method="POST" action=""
            onsubmit="return confirm(
              'Deactivate <?= addslashes(
                  $u['full_name']) ?>? '
              + 'They will no longer be able '
              + 'to log in.')">
        <?= csrfField() ?>
        <input type="hidden" name="user_id"
               value="<?= $u['user_id'] ?>">
        <input type="hidden" name="action"
               value="deactivate">
        <button type="submit"
                title="Deactivate account"
                style="
                  background:rgba(255,107,122,0.06);
                  border:1px solid
                    rgba(255,107,122,0.2);
                  color:#ff6b7a;border-radius:7px;
                  padding:0.35rem 0.75rem;
                  font-size:0.78rem;cursor:pointer;
                ">
          <i class="ti ti-ban"></i>
          Deactivate
        </button>
      </form>

      <?php else: ?>
      <!-- Remove admin -->
      <form method="POST" action=""
            onsubmit="return confirm(
              'Remove admin rights from '
              + '<?= addslashes(
                  $u['full_name']) ?>?')">
        <?= csrfField() ?>
        <input type="hidden" name="user_id"
               value="<?= $u['user_id'] ?>">
        <input type="hidden" name="action"
               value="remove_admin">
        <button type="submit"
                style="
                  background:rgba(255,107,122,0.06);
                  border:1px solid
                    rgba(255,107,122,0.2);
                  color:#ff6b7a;border-radius:7px;
                  padding:0.35rem 0.75rem;
                  font-size:0.78rem;cursor:pointer;
                ">
          <i class="ti ti-shield-off"></i>
          Remove Admin
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="
      font-size:0.75rem;color:#333;
      flex-shrink:0;font-style:italic;
    ">Current account</div>
    <?php endif; ?>

  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</main>

<?php require_once '../includes/footer.php'; ?>
<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user       = currentUser();
$hasProfile = hasProfile($pdo, $user['id']);
$myMember   = $hasProfile
    ? getUserMember($pdo, $user['id'])
    : null;

// Stats
$total_members = $pdo->query(
    "SELECT COUNT(*) FROM family_members"
)->fetchColumn();

$verified_members = $pdo->query(
    "SELECT COUNT(*) FROM family_members
     WHERE verified = 1"
)->fetchColumn();

$total_records = $pdo->query(
    "SELECT COUNT(*) FROM heritage_records"
)->fetchColumn();

$pending_edits = $pdo->query(
    "SELECT COUNT(*) FROM pending_edits
     WHERE status = 'pending'"
)->fetchColumn();

// My connections
$my_connections = $hasProfile
    ? getConnections($pdo, $myMember['member_id'])
    : [];

// Recent members added
$recent = $pdo->query("
    SELECT
        fm.full_name,
        fm.created_at,
        q.name AS quarter,
        u.full_name AS added_by_name
    FROM family_members fm
    LEFT JOIN quarters q
        ON fm.quarter_id = q.quarter_id
    LEFT JOIN users u
        ON fm.added_by = u.user_id
    ORDER BY fm.created_at DESC
    LIMIT 5
")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<main style="padding:2rem">
<div class="container-fluid">

  <!-- Profile completion banner -->
  <?php if (!$hasProfile): ?>
  <div style="
    background: rgba(255,159,26,0.1);
    border: 1px solid rgba(255,159,26,0.3);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
  ">
    <div>
      <strong style="color:#ff9f1a">
        Complete your profile
      </strong>
      <p style="color:#888;margin:0;font-size:0.9rem">
        Add your personal information to join
        the Ekpor Village family tree and connect
        with relatives.
      </p>
    </div>
    <a href="<?= SITE_URL ?>/settings/profile.php"
       class="btn btn-warning btn-sm">
      Complete Profile →
    </a>
  </div>
  <?php endif; ?>

  <!-- Welcome -->
  <div class="mb-4">
    <h2 style="color:#fff">Family Dashboard</h2>
    <p style="color:#888">
      Welcome back, <?= clean($user['name']) ?>.
    </p>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number">
          <?= number_format($total_members) ?>
        </div>
        <div class="stat-label">
          Family Members
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number">
          <?= number_format($verified_members) ?>
        </div>
        <div class="stat-label">
          Verified Records
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number">
          <?= number_format($total_records) ?>
        </div>
        <div class="stat-label">
          Heritage Records
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number"
             style="color:<?= $pending_edits > 0
                 ? '#ff9f1a' : '#00d4ff' ?>">
          <?= $pending_edits ?>
        </div>
        <div class="stat-label">
          Pending Edits
        </div>
      </div>
    </div>
  </div>

  <!-- My connections (if profile complete) -->
  <?php if ($hasProfile
            && !empty($my_connections)): ?>
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="stat-card">
        <h6 style="color:#aaa;margin-bottom:1rem">
          My Family Connections
        </h6>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach (
              $my_connections as $c
          ): ?>
          <div style="
            background:#0d0d1a;
            border:1px solid #1e1e3a;
            border-radius:10px;
            padding:0.75rem 1rem;
            min-width:160px;
          ">
            <div style="
              color:#00d4ff;
              font-size:0.75rem;
              text-transform:uppercase;
              letter-spacing:0.05em;
              margin-bottom:4px;
            ">
              <?= clean(getRelationLabel(
                $c['relation_label'] ?? null,
                $c['relation_type']
              )) ?>
            </div>
            <div style="color:#fff;font-size:0.9rem">
              <?= clean($c['full_name']) ?>
            </div>
            <div style="color:#555;font-size:0.78rem">
              <?= clean($c['quarter_name']) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Quick actions -->
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="stat-card">
        <h6 style="color:#aaa;margin-bottom:1rem">
          Quick Actions
        </h6>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= SITE_URL ?>/family/tree.php"
             class="btn btn-primary">
            <i class="ti ti-git-fork me-1"></i>
            View Family Tree
          </a>
          <?php if ($hasProfile): ?>
          <a href="<?= SITE_URL ?>/family/add.php"
             class="btn btn-outline-light">
            <i class="ti ti-user-plus me-1"></i>
            Add Family Member
          </a>
          <?php else: ?>
          <a href="<?= SITE_URL ?>/settings/profile.php"
             class="btn btn-outline-warning">
            <i class="ti ti-user me-1"></i>
            Complete My Profile
          </a>
          <?php endif; ?>
          <a href="<?= SITE_URL ?>/heritage/contribute.php"
             class="btn btn-outline-light">
            <i class="ti ti-plus me-1"></i>
            Contribute Record
          </a>
          <a href="<?= SITE_URL ?>/heritage/history.php"
             class="btn btn-outline-light">
            <i class="ti ti-history me-1"></i>
            Village History
          </a>
          <?php if ($user['role'] === 'admin'
                    && $pending_edits > 0): ?>
          <a href="<?= SITE_URL ?>/verification/queue.php"
             class="btn btn-warning">
            <i class="ti ti-clock me-1"></i>
            Review <?= $pending_edits ?>
            Pending Edit<?=
                $pending_edits > 1 ? 's' : '' ?>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent activity -->
  <?php if (!empty($recent)): ?>
  <div class="row">
    <div class="col-12">
      <div class="stat-card">
        <h6 style="color:#aaa;margin-bottom:1rem">
          Recently Added Members
        </h6>
        <div class="table-responsive">
          <table class="table table-dark
                        table-hover mb-0"
                 style="background:transparent">
            <thead>
              <tr style="color:#666;
                          font-size:0.8rem;
                          border-color:#1e1e3a">
                <th>Name</th>
                <th>Quarter</th>
                <th>Added By</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
              <tr style="border-color:#1e1e3a;
                          font-size:0.9rem">
                <td style="color:#e0e0e0">
                  <?= clean($r['full_name']) ?>
                </td>
                <td style="color:#00d4ff">
                  <?= clean($r['quarter']) ?>
                </td>
                <td style="color:#888">
                  <?= clean(
                      $r['added_by_name'] ?? 'System'
                  ) ?>
                </td>
                <td style="color:#666">
                  <?= formatDate($r['created_at']) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>
</main>

<?php require_once 'includes/footer.php'; ?>
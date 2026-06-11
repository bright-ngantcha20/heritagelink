<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user = currentUser();

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

// Recent activity — latest family members added
$recent = $pdo->query("
    SELECT fm.full_name,
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

<main class="dashboard-page">
  <div class="container-fluid">

    <!-- Welcome -->
    <div class="mb-4">
      <h2 style="color:#fff">Family Dashboard</h2>
      <p style="color:#888">
        Welcome back, <?= clean($user['name']) ?>.
        Here is what is happening in your family tree.
      </p>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="stat-number">
            <?= number_format($total_members) ?>
          </div>
          <div class="stat-label">Family Members</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="stat-number">
            <?= number_format($verified_members) ?>
          </div>
          <div class="stat-label">Verified Records</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="stat-number">
            <?= number_format($total_records) ?>
          </div>
          <div class="stat-label">Heritage Records</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="stat-number"
               style="color: <?= $pending_edits > 0
                   ? '#ff9f1a' : '#00d4ff' ?>">
            <?= $pending_edits ?>
          </div>
          <div class="stat-label">Pending Edits</div>
        </div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="stat-card">
          <h6 style="color:#aaa; margin-bottom:1rem">
            Quick Actions
          </h6>
          <div class="d-flex flex-wrap gap-2">
            <a href="<?= SITE_URL ?>/family/tree.php"
               class="btn btn-primary">
              <i class="ti ti-git-fork me-1"></i>
              View Family Tree
            </a>
            <a href="<?= SITE_URL ?>/family/add.php"
               class="btn btn-outline-light">
              <i class="ti ti-user-plus me-1"></i>
              Add Family Member
            </a>
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
              Review <?= $pending_edits ?> Pending Edit<?=
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
          <h6 style="color:#aaa; margin-bottom:1rem">
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
                    <?= clean($r['added_by_name']
                        ?? 'Unknown') ?>
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
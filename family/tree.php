<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

// Get all family members
$members = $pdo->query("
    SELECT
        fm.*,
        q.name AS quarter_name
    FROM family_members fm
    LEFT JOIN quarters q
        ON fm.quarter_id = q.quarter_id
    ORDER BY fm.created_at DESC
")->fetchAll();
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding: 2rem;">
<div class="container-fluid">

  <div class="d-flex justify-content-between
              align-items-center mb-4">
    <div>
      <h2 style="color:#fff">Family Tree</h2>
      <p style="color:#888">
        Ekpor Village — all recorded family members
      </p>
    </div>
    <a href="<?= SITE_URL ?>/family/add.php"
       class="btn btn-primary">
      <i class="ti ti-user-plus me-2"></i>
      Add Member
    </a>
  </div>

  <!-- Stats bar -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number">
          <?= count($members) ?>
        </div>
        <div class="stat-label">Total Members</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number">
          <?= count(array_filter(
              $members,
              fn($m) => $m['verified'] == 1
          )) ?>
        </div>
        <div class="stat-label">Verified</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number">5</div>
        <div class="stat-label">Quarters</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-number">
          <?= count(array_filter(
              $members,
              fn($m) => $m['date_of_death'] === null
          )) ?>
        </div>
        <div class="stat-label">Living</div>
      </div>
    </div>
  </div>

  <?php if (empty($members)): ?>
  <!-- Empty state -->
  <div style="
    text-align: center;
    padding: 4rem 2rem;
    color: #555;
  ">
    <i class="ti ti-git-fork"
       style="font-size:3rem;
              display:block;
              margin-bottom:1rem;
              color:#1e1e3a"></i>
    <h4 style="color:#888">
      No family members yet
    </h4>
    <p>Start by adding the first member
       of the Ekpor Village family tree.</p>
    <a href="<?= SITE_URL ?>/family/add.php"
       class="btn btn-primary mt-2">
      Add First Member
    </a>
  </div>

  <?php else: ?>

  <!-- Members grid -->
  <div class="row g-3">
    <?php foreach ($members as $m): ?>
    <div class="col-12 col-md-6 col-lg-4">
      <div style="
        background: #111127;
        border: 1px solid #1e1e3a;
        border-radius: 12px;
        padding: 1.25rem;
        height: 100%;
        transition: border-color 0.2s;
      "
      onmouseover="this.style.borderColor='#00d4ff'"
      onmouseout="this.style.borderColor='#1e1e3a'">

        <div class="d-flex align-items-start gap-3">

          <!-- Photo or initial -->
          <div style="
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #1e1e3a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 600;
            color: #00d4ff;
            flex-shrink: 0;
            overflow: hidden;
          ">
            <?php if ($m['photo']): ?>
              <img src="<?= SITE_URL ?>/<?= $m['photo'] ?>"
                   style="width:100%;
                          height:100%;
                          object-fit:cover"
                   alt="<?= clean($m['full_name']) ?>">
            <?php else: ?>
              <?= strtoupper(
                  substr($m['full_name'], 0, 1)
              ) ?>
            <?php endif; ?>
          </div>

          <div style="flex:1; min-width:0">

            <!-- Name -->
            <div style="
              color: #fff;
              font-weight: 500;
              font-size: 0.95rem;
              white-space: nowrap;
              overflow: hidden;
              text-overflow: ellipsis;
            ">
              <?= clean($m['full_name']) ?>
            </div>

            <!-- Quarter badge -->
            <div style="
              display: inline-block;
              background: rgba(0,212,255,0.1);
              border: 1px solid rgba(0,212,255,0.2);
              color: #00d4ff;
              font-size: 0.75rem;
              padding: 2px 8px;
              border-radius: 20px;
              margin-top: 4px;
            ">
              <?= clean($m['quarter_name']) ?>
            </div>

            <!-- Dates -->
            <?php if ($m['date_of_birth']
                      || $m['date_of_death']): ?>
            <div style="
              color: #666;
              font-size: 0.8rem;
              margin-top: 4px;
            ">
              <?php if ($m['date_of_birth']): ?>
                <?= $m['dob_approximate']
                    ? 'c. ' : '' ?>
                <?= date('Y', strtotime(
                    $m['date_of_birth']
                )) ?>
              <?php endif; ?>
              <?php if ($m['date_of_death']): ?>
                —
                <?= $m['dod_approximate']
                    ? 'c. ' : '' ?>
                <?= date('Y', strtotime(
                    $m['date_of_death']
                )) ?>
              <?php elseif ($m['date_of_birth']): ?>
                — Present
              <?php endif; ?>
            </div>
            <?php endif; ?>

          </div>

          <!-- Verified badge -->
          <?php if ($m['verified']): ?>
          <div title="Verified record"
               style="color:#00d4ff;
                      font-size:1rem;
                      flex-shrink:0">
            <i class="ti ti-circle-check"></i>
          </div>
          <?php endif; ?>

        </div>

        <!-- Bio preview -->
        <?php if ($m['short_bio']): ?>
        <div style="
          color: #666;
          font-size: 0.82rem;
          margin-top: 0.75rem;
          line-height: 1.5;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        ">
          <?= clean($m['short_bio']) ?>
        </div>
        <?php endif; ?>

        <!-- Source -->
        <div style="
          margin-top: 0.75rem;
          padding-top: 0.75rem;
          border-top: 1px solid #1e1e3a;
          color: #555;
          font-size: 0.78rem;
        ">
          <i class="ti ti-book me-1"></i>
          <?= clean($m['source_of_info']) ?>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>

</div>
</main>

<?php require_once '../includes/footer.php'; ?>
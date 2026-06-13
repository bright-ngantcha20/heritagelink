<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$user = currentUser();

// ── Filters ─────────────────────────────────────
$filter_era  = $_GET['era']  ?? '';
$filter_type = $_GET['type'] ?? '';
$search      = trim($_GET['q'] ?? '');

// ── Build query ─────────────────────────────────
$where  = ["(hr.privacy = 'public' OR hr.privacy = 'members')"];
$params = [];

if ($filter_era && in_array($filter_era, ['pre_colonial','colonial','modern'])) {
    $where[]  = "hr.era = ?";
    $params[] = $filter_era;
}
if ($filter_type && in_array($filter_type, ['event','document','photograph','audio','oral_history'])) {
    $where[]  = "hr.type = ?";
    $params[] = $filter_type;
}
if ($search) {
    $where[]  = "(hr.title LIKE ? OR hr.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "
    SELECT hr.*,
           u.full_name   AS contributor_name,
           COUNT(mf.file_id) AS media_count
    FROM   heritage_records hr
    LEFT JOIN users u  ON u.user_id = hr.contributed_by
    LEFT JOIN media_files mf ON mf.record_id = hr.record_id
    WHERE  hr.verified = 1
      AND  " . implode(' AND ', $where) . "
    GROUP  BY hr.record_id
    ORDER  BY
        FIELD(hr.era, 'pre_colonial','colonial','modern'),
        hr.event_date ASC,
        hr.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Counts for tabs ──────────────────────────────
$counts = $pdo->query("
    SELECT era, COUNT(*) as n
    FROM   heritage_records
    WHERE  verified = 1
    GROUP  BY era
")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Helpers ──────────────────────────────────────
$era_labels = [
    'pre_colonial' => 'Pre-Colonial',
    'colonial'     => 'Colonial',
    'modern'       => 'Modern',
];
$era_colors = [
    'pre_colonial' => '#ff9f1a',
    'colonial'     => '#a78bfa',
    'modern'       => '#00d4ff',
];
$type_icons = [
    'event'       => 'ti-calendar-event',
    'document'    => 'ti-file-text',
    'photograph'  => 'ti-photo',
    'audio'       => 'ti-microphone',
    'oral_history'=> 'ti-speakerphone',
];
$type_labels = [
    'event'        => 'Event',
    'document'     => 'Document',
    'photograph'   => 'Photograph',
    'audio'        => 'Audio',
    'oral_history' => 'Oral History',
];
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:900px">

  <!-- Page header -->
  <div style="
    display:flex;align-items:flex-start;
    justify-content:space-between;
    flex-wrap:wrap;gap:1rem;
    margin-bottom:2rem;
  ">
    <div>
      <h2 style="color:#fff;margin:0 0 0.3rem">
        <i class="ti ti-building-monument me-2"
           style="color:#00d4ff"></i>
        Village History
      </h2>
      <p style="color:#666;margin:0;font-size:0.9rem">
        The documented heritage and oral history
        of Ekpor Village, Manyu Division.
      </p>
    </div>
    <a href="<?= SITE_URL ?>/heritage/contribute.php"
       class="btn btn-primary btn-sm">
      <i class="ti ti-plus me-1"></i>
      Contribute a Record
    </a>
  </div>

  <!-- Era timeline tabs -->
  <div style="
    display:flex;gap:0;
    border:1px solid #1e1e3a;
    border-radius:10px;
    overflow:hidden;
    margin-bottom:1.5rem;
  ">
    <?php
    $era_tabs = [
      '' => ['All Eras', array_sum($counts)],
      'pre_colonial' => ['Pre-Colonial', $counts['pre_colonial'] ?? 0],
      'colonial'     => ['Colonial',     $counts['colonial']     ?? 0],
      'modern'       => ['Modern',       $counts['modern']       ?? 0],
    ];
    foreach ($era_tabs as $val => [$label, $count]):
      $active = $filter_era === $val;
      $color  = $val ? ($era_colors[$val] ?? '#00d4ff') : '#00d4ff';
      $params_tab = array_filter([
        'era'  => $val,
        'type' => $filter_type,
        'q'    => $search,
      ]);
    ?>
    <a href="?<?= http_build_query($params_tab) ?>"
       style="
         flex:1;text-align:center;
         padding:0.75rem 0.5rem;
         font-size:0.82rem;
         color:<?= $active ? $color : '#555' ?>;
         background:<?= $active
             ? 'rgba(0,0,0,0.3)' : 'transparent' ?>;
         border-right:1px solid #1e1e3a;
         text-decoration:none;
         transition:color 0.15s;
         border-bottom:2px solid
           <?= $active ? $color : 'transparent' ?>;
       ">
      <div style="font-weight:600"><?= $label ?></div>
      <div style="font-size:0.75rem;
                  opacity:0.7;margin-top:2px">
        <?= $count ?> record<?= $count !== 1 ? 's':'' ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Search + type filter -->
  <div style="
    display:flex;gap:0.75rem;
    flex-wrap:wrap;margin-bottom:1.5rem;
  ">
    <form method="GET" action=""
          style="display:flex;gap:0.75rem;
                 flex:1;flex-wrap:wrap">
      <?php if ($filter_era): ?>
      <input type="hidden" name="era"
             value="<?= clean($filter_era) ?>">
      <?php endif; ?>

      <input type="text" name="q"
             value="<?= clean($search) ?>"
             placeholder="Search records..."
             style="
               flex:1;min-width:160px;
               background:#0d0d1a;
               border:1px solid #1e1e3a;
               color:#e0e0e0;border-radius:8px;
               padding:0.45rem 0.9rem;
               font-size:0.88rem;outline:none;
             ">

      <select name="type"
              onchange="this.form.submit()"
              style="
                background:#0d0d1a;
                border:1px solid #1e1e3a;
                color:<?= $filter_type ? '#e0e0e0' : '#666' ?>;
                border-radius:8px;
                padding:0.45rem 0.9rem;
                font-size:0.88rem;outline:none;
              ">
        <option value="">All types</option>
        <?php foreach ($type_labels as $val => $lbl): ?>
        <option value="<?= $val ?>"
          <?= $filter_type === $val ? 'selected':'' ?>>
          <?= $lbl ?>
        </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" style="
        background:#00d4ff;color:#000;
        border:none;border-radius:8px;
        padding:0.45rem 1rem;font-size:0.88rem;
        font-weight:600;cursor:pointer;
      ">Search</button>

      <?php if ($search || $filter_type): ?>
      <a href="?<?= $filter_era ? 'era='.$filter_era : '' ?>"
         style="
           color:#666;font-size:0.82rem;
           align-self:center;
           text-decoration:none;
         ">
        Clear
      </a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Records list -->
  <?php if (empty($records)): ?>
  <div style="
    text-align:center;padding:4rem 1rem;
    color:#444;
  ">
    <i class="ti ti-history-off"
       style="font-size:3rem;
              display:block;margin-bottom:1rem"></i>
    <div style="font-size:1rem;margin-bottom:0.5rem">
      No records found
    </div>
    <p style="font-size:0.85rem">
      <?= $search || $filter_type || $filter_era
          ? 'Try adjusting your filters.'
          : 'Be the first to contribute a heritage record.' ?>
    </p>
    <a href="<?= SITE_URL ?>/heritage/contribute.php"
       class="btn btn-primary btn-sm mt-2">
      Contribute a Record
    </a>
  </div>

  <?php else: ?>

  <?php
  // Group by era for timeline display
  $grouped = [];
  foreach ($records as $r) {
      $grouped[$r['era'] ?: 'unknown'][] = $r;
  }
  $era_order = ['pre_colonial','colonial','modern','unknown'];
  ?>

  <?php foreach ($era_order as $era):
    if (empty($grouped[$era])) continue;
    $elabel = $era_labels[$era] ?? ucfirst($era);
    $ecolor = $era_colors[$era] ?? '#888';
  ?>

  <!-- Era heading -->
  <?php if (!$filter_era): ?>
  <div style="
    display:flex;align-items:center;
    gap:0.75rem;margin:1.75rem 0 1rem;
  ">
    <div style="
      height:1px;flex:1;
      background:linear-gradient(
        to right, <?= $ecolor ?>33, transparent);
    "></div>
    <span style="
      color:<?= $ecolor ?>;
      font-size:0.72rem;font-weight:700;
      letter-spacing:0.1em;
      text-transform:uppercase;
    "><?= $elabel ?></span>
    <div style="
      height:1px;flex:1;
      background:linear-gradient(
        to left, <?= $ecolor ?>33, transparent);
    "></div>
  </div>
  <?php endif; ?>

  <!-- Records in this era -->
  <div style="display:flex;flex-direction:column;gap:1rem">
  <?php foreach ($grouped[$era] as $rec):
    $icon  = $type_icons[$rec['type']]  ?? 'ti-file';
    $tlbl  = $type_labels[$rec['type']] ?? 'Record';
    $ecolor_rec = $era_colors[$rec['era'] ?? ''] ?? '#888';
  ?>
  <a href="<?= SITE_URL ?>/heritage/view.php
            ?id=<?= $rec['record_id'] ?>"
     style="text-decoration:none">
    <div class="record-card">

      <!-- Type icon -->
      <div style="
        width:44px;height:44px;
        border-radius:10px;flex-shrink:0;
        background:rgba(0,212,255,0.07);
        border:1px solid rgba(0,212,255,0.15);
        display:flex;align-items:center;
        justify-content:center;
        font-size:1.3rem;color:#00d4ff;
      ">
        <i class="ti <?= $icon ?>"></i>
      </div>

      <!-- Content -->
      <div style="flex:1;min-width:0">
        <div style="
          display:flex;align-items:center;
          gap:0.5rem;flex-wrap:wrap;
          margin-bottom:0.3rem;
        ">
          <span style="
            background:<?= $ecolor_rec ?>22;
            color:<?= $ecolor_rec ?>;
            font-size:0.7rem;font-weight:700;
            padding:1px 8px;border-radius:20px;
            text-transform:uppercase;
            letter-spacing:0.05em;
          "><?= $era_labels[$rec['era']] ?? '' ?></span>

          <span style="
            color:#555;font-size:0.75rem;
          "><?= $tlbl ?></span>

          <?php if ($rec['media_count'] > 0): ?>
          <span style="color:#555;font-size:0.75rem">
            · <i class="ti ti-paperclip"></i>
            <?= $rec['media_count'] ?> file<?=
              $rec['media_count'] > 1 ? 's' : '' ?>
          </span>
          <?php endif; ?>
        </div>

        <div style="
          color:#e0e0e0;font-weight:500;
          font-size:0.95rem;
          white-space:nowrap;overflow:hidden;
          text-overflow:ellipsis;
        ">
          <?= clean($rec['title']) ?>
        </div>

        <?php if ($rec['description']): ?>
        <div style="
          color:#666;font-size:0.82rem;
          margin-top:0.25rem;
          display:-webkit-box;
          -webkit-line-clamp:2;
          -webkit-box-orient:vertical;
          overflow:hidden;
        ">
          <?= clean(strip_tags($rec['description'])) ?>
        </div>
        <?php endif; ?>

        <div style="
          margin-top:0.5rem;
          display:flex;gap:1rem;
          font-size:0.75rem;color:#444;
        ">
          <?php if ($rec['location']): ?>
          <span>
            <i class="ti ti-map-pin me-1"></i>
            <?= clean($rec['location']) ?>
          </span>
          <?php endif; ?>
          <?php if ($rec['event_date']): ?>
          <span>
            <i class="ti ti-calendar me-1"></i>
            <?= $rec['date_approx'] ? 'c. ' : '' ?>
            <?= date('Y', strtotime($rec['event_date'])) ?>
          </span>
          <?php endif; ?>
          <?php if ($rec['contributor_name']): ?>
          <span>
            <i class="ti ti-user me-1"></i>
            <?= clean($rec['contributor_name']) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <i class="ti ti-chevron-right"
         style="color:#333;flex-shrink:0"></i>
    </div>
  </a>
  <?php endforeach; ?>
  </div>

  <?php endforeach; ?>
  <?php endif; ?>

  <div style="height:3rem"></div>
</div>
</main>

<style>
.record-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  background: #111127;
  border: 1px solid #1e1e3a;
  border-radius: 12px;
  padding: 1rem 1.1rem;
  transition: border-color 0.15s, background 0.15s;
  cursor: pointer;
}
.record-card:hover {
  border-color: rgba(0,212,255,0.25);
  background: rgba(0,212,255,0.03);
}
</style>

<?php require_once '../includes/footer.php'; ?>
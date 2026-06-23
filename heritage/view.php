<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
$user = currentUser();

$record_id = (int)($_GET['id'] ?? 0);
if (!$record_id) {
    header('Location: ' . SITE_URL . '/heritage/history.php');
    exit;
}

// ── Fetch record ─────────────────────────────────
$stmt = $pdo->prepare("
    SELECT hr.*,
           u.full_name AS contributor_name,
           u.user_id   AS contributor_id
    FROM   heritage_records hr
    LEFT JOIN users u ON u.user_id = hr.contributed_by
    WHERE  hr.record_id = ?
      AND  hr.verified  = 1
      AND  (hr.privacy = 'public' OR hr.privacy = 'members')
");
$stmt->execute([$record_id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    header('Location: ' . SITE_URL . '/heritage/history.php');
    exit;
}

// ── Fetch media files ────────────────────────────
$media = $pdo->prepare("
    SELECT * FROM media_files
    WHERE  record_id = ?
    ORDER  BY uploaded_at ASC
");
$media->execute([$record_id]);
$files = $media->fetchAll(PDO::FETCH_ASSOC);

// ── Fetch related records (same era or type) ─────
$related = $pdo->prepare("
    SELECT record_id, title, type, era, location
    FROM   heritage_records
    WHERE  verified = 1
      AND  record_id != ?
      AND  (era = ? OR type = ?)
      AND  (privacy = 'public' OR privacy = 'members')
    ORDER  BY RAND()
    LIMIT  4
");
$related->execute([
    $record_id,
    $record['era'],
    $record['type'],
]);
$related_records = $related->fetchAll(PDO::FETCH_ASSOC);

// ── Admin check ──────────────────────────────────
$is_admin = $user['role'] === 'admin';

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
    'event'        => 'ti-calendar-event',
    'document'     => 'ti-file-text',
    'photograph'   => 'ti-photo',
    'audio'        => 'ti-microphone',
    'oral_history' => 'ti-speakerphone',
];
$type_labels = [
    'event'        => 'Event',
    'document'     => 'Document',
    'photograph'   => 'Photograph',
    'audio'        => 'Audio',
    'oral_history' => 'Oral History',
];

$ecolor = $era_colors[$record['era']] ?? '#888';
$elabel = $era_labels[$record['era']] ?? '';
$ticon  = $type_icons[$record['type']] ?? 'ti-file';
$tlabel = $type_labels[$record['type']] ?? 'Record';
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:860px">

  <!-- Breadcrumb -->
  <div style="
    font-size:0.82rem;color:#555;
    margin-bottom:1.5rem;
  ">
    <a href="<?= SITE_URL ?>/heritage/history.php"
       style="color:#555;text-decoration:none">
      Village History
    </a>
    <span style="margin:0 0.5rem">›</span>
    <span style="color:#888">
      <?= clean($record['title']) ?>
    </span>
  </div>

  <div style="display:flex;gap:2rem;flex-wrap:wrap">

    <!-- ── Main content ───────────────────────── -->
    <div style="flex:1;min-width:0">

      <!-- Era + type badges -->
      <div style="
        display:flex;gap:0.5rem;
        flex-wrap:wrap;margin-bottom:1rem;
      ">
        <span style="
          background:<?= $ecolor ?>22;
          color:<?= $ecolor ?>;
          font-size:0.72rem;font-weight:700;
          padding:3px 10px;border-radius:20px;
          text-transform:uppercase;
          letter-spacing:0.06em;
        "><?= $elabel ?></span>

        <span style="
          background:rgba(255,255,255,0.05);
          color:#888;
          font-size:0.72rem;font-weight:600;
          padding:3px 10px;border-radius:20px;
          display:flex;align-items:center;gap:4px;
        ">
          <i class="ti <?= $ticon ?>"
             style="font-size:0.85rem"></i>
          <?= $tlabel ?>
        </span>
      </div>

      <!-- Title -->
      <h1 style="
        color:#fff;font-size:1.6rem;
        font-weight:700;margin:0 0 1rem;
        line-height:1.25;
      ">
        <?= clean($record['title']) ?>
      </h1>

      <!-- Meta row -->
      <div style="
        display:flex;flex-wrap:wrap;gap:1rem;
        font-size:0.82rem;color:#555;
        margin-bottom:1.5rem;
        padding-bottom:1.25rem;
        border-bottom:1px solid #1a1a30;
      ">
        <?php if ($record['event_date']): ?>
        <span>
          <i class="ti ti-calendar me-1"></i>
          <?= $record['date_approx'] ? 'c. ' : '' ?>
          <?= date('Y', strtotime($record['event_date'])) ?>
        </span>
        <?php endif; ?>

        <?php if ($record['location']): ?>
        <span>
          <i class="ti ti-map-pin me-1"></i>
          <?= clean($record['location']) ?>
        </span>
        <?php endif; ?>

        <?php if ($record['contributor_name']): ?>
        <span>
          <i class="ti ti-user me-1"></i>
          Contributed by
          <?= clean($record['contributor_name']) ?>
        </span>
        <?php endif; ?>

        <span>
          <i class="ti ti-clock me-1"></i>
          <?= date('d M Y',
              strtotime($record['created_at'])) ?>
        </span>
      </div>

      <!-- Description -->
      <div style="
        color:#ccc;font-size:0.95rem;
        line-height:1.75;
        white-space:pre-line;
      ">
        <?= clean($record['description']) ?>
      </div>

      <!-- Source -->
      <?php if ($record['source']): ?>
      <div style="
        margin-top:1.5rem;
        padding:0.85rem 1rem;
        background:rgba(255,255,255,0.03);
        border-left:3px solid #2a2a4a;
        border-radius:0 8px 8px 0;
        font-size:0.82rem;color:#555;
      ">
        <i class="ti ti-book me-2"></i>
        <strong style="color:#666">Source:</strong>
        <?= clean($record['source']) ?>
      </div>
      <?php endif; ?>

      <!-- Media files -->
      <?php if (!empty($files)): ?>
      <div style="margin-top:2rem">
        <div style="
          font-size:0.75rem;font-weight:700;
          letter-spacing:0.08em;color:#00d4ff;
          text-transform:uppercase;
          margin-bottom:1rem;
        ">
          Attached Media
        </div>

        <div style="
          display:grid;gap:0.75rem;
          grid-template-columns:
            repeat(auto-fill, minmax(180px,1fr));
        ">
          <?php foreach ($files as $f):
            $ext = strtolower(
                pathinfo($f['file_name'], PATHINFO_EXTENSION)
            );
            $is_img = in_array($ext,
                ['jpg','jpeg','png','webp','gif']);
            $is_audio = in_array($ext,
                ['mp3','wav','ogg','m4a']);
          ?>
          <div style="
            background:#0d0d1a;
            border:1px solid #1e1e3a;
            border-radius:10px;
            overflow:hidden;
          ">
            <?php if ($is_img): ?>
            <a href="<?= SITE_URL ?>/<?= $f['file_path'] ?>"
               target="_blank">
              <img src="<?= SITE_URL ?>/<?= $f['file_path'] ?>"
                   alt="<?= clean($f['file_name']) ?>"
                   style="
                     width:100%;height:120px;
                     object-fit:cover;display:block;
                   ">
            </a>
            <?php elseif ($is_audio): ?>
            <div style="padding:0.75rem">
              <i class="ti ti-microphone"
                 style="
                   font-size:1.5rem;color:#a78bfa;
                   display:block;margin-bottom:0.5rem;
                 "></i>
              <audio controls style="width:100%">
                <source src="<?= SITE_URL ?>
                  /<?= $f['file_path'] ?>">
              </audio>
            </div>
            <?php else: ?>
            <a href="<?= SITE_URL ?>/<?= $f['file_path'] ?>"
               target="_blank"
               style="
                 display:flex;align-items:center;
                 gap:0.75rem;padding:0.85rem;
                 text-decoration:none;
               ">
              <i class="ti ti-file-text"
                 style="
                   font-size:1.5rem;color:#00d4ff;
                   flex-shrink:0;
                 "></i>
              <span style="
                color:#aaa;font-size:0.8rem;
                overflow:hidden;
                text-overflow:ellipsis;
                white-space:nowrap;
              ">
                <?= clean($f['file_name']) ?>
              </span>
            </a>
            <?php endif; ?>

            <div style="
              padding:0.5rem 0.75rem;
              font-size:0.72rem;color:#444;
              border-top:1px solid #1a1a30;
            ">
              <?= $f['file_size']
                  ? round($f['file_size']/1024) . ' KB'
                  : '' ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Admin actions -->
      <?php if ($is_admin): ?>
      <div style="
        margin-top:2rem;
        padding:1rem;
        background:rgba(255,159,26,0.05);
        border:1px solid rgba(255,159,26,0.15);
        border-radius:10px;
        display:flex;gap:0.75rem;flex-wrap:wrap;
        align-items:center;
      ">
        <span style="
          color:#ff9f1a;font-size:0.78rem;
          font-weight:600;
        ">
          <i class="ti ti-shield me-1"></i>
          Admin
        </span>
        <a href="<?= SITE_URL ?>/admin/edit_record.php?id=<?= $record['record_id'] ?>"
           style="
             color:#aaa;font-size:0.82rem;
             text-decoration:none;
           ">
          <i class="ti ti-edit me-1"></i>Edit
        </a>
        <button onclick="
          if(confirm('Archive this record?')) {
            window.location='<?= SITE_URL ?>
              /admin/archive_record.php
              ?id=<?= $record['record_id'] ?>';
          }"
          style="
            background:none;border:none;
            color:#ff6b7a;font-size:0.82rem;
            cursor:pointer;padding:0;
          ">
          <i class="ti ti-archive me-1"></i>Archive
        </button>
      </div>
      <?php endif; ?>

    </div>

    <!-- ── Sidebar ─────────────────────────────── -->
    <div style="width:240px;flex-shrink:0">

      <!-- Quick facts card -->
      <div style="
        background:#111127;
        border:1px solid #1e1e3a;
        border-radius:12px;
        padding:1.1rem;
        margin-bottom:1rem;
      ">
        <div style="
          font-size:0.72rem;font-weight:700;
          letter-spacing:0.08em;color:#555;
          text-transform:uppercase;
          margin-bottom:0.85rem;
        ">Quick Facts</div>

        <?php
        $facts = [
          ['ti-layers', 'Era',  $elabel],
          ['ti-tag',    'Type', $tlabel],
          ['ti-map-pin','Location',
              $record['location'] ?: '—'],
          ['ti-calendar','Date',
              $record['event_date']
              ? ($record['date_approx'] ? 'c. ' : '')
                . date('Y', strtotime($record['event_date']))
              : '—'],
        ];
        foreach ($facts as [$icon, $label, $val]):
        ?>
        <div style="
          display:flex;gap:0.65rem;
          margin-bottom:0.75rem;
          font-size:0.82rem;
        ">
          <i class="ti <?= $icon ?>"
             style="color:#333;margin-top:1px;
                    flex-shrink:0"></i>
          <div>
            <div style="color:#444;
                        font-size:0.72rem">
              <?= $label ?>
            </div>
            <div style="color:#aaa;margin-top:1px">
              <?= clean($val) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Related records -->
      <?php if (!empty($related_records)): ?>
      <div style="
        background:#111127;
        border:1px solid #1e1e3a;
        border-radius:12px;
        padding:1.1rem;
      ">
        <div style="
          font-size:0.72rem;font-weight:700;
          letter-spacing:0.08em;color:#555;
          text-transform:uppercase;
          margin-bottom:0.85rem;
        ">Related Records</div>

        <?php foreach ($related_records as $rel):
          $ricon = $type_icons[$rel['type']] ?? 'ti-file';
          $recolor = $era_colors[$rel['era']] ?? '#888';
        ?>
        <a href="<?= SITE_URL ?>/heritage/view.php?id=<?= $rel['record_id'] ?>"
           style="
             display:flex;align-items:flex-start;
             gap:0.6rem;padding:0.6rem 0;
             text-decoration:none;
             border-bottom:1px solid #1a1a30;
           ">
          <i class="ti <?= $ricon ?>"
             style="
               color:<?= $recolor ?>;
               font-size:0.95rem;
               margin-top:2px;flex-shrink:0;
             "></i>
          <span style="
            color:#aaa;font-size:0.82rem;
            line-height:1.3;
          ">
            <?= clean($rel['title']) ?>
          </span>
        </a>
        <?php endforeach; ?>

        <a href="<?= SITE_URL ?>/heritage/history.php<?= $record['era'] ? '?era='.$record['era'] : '' ?>"
           style="
             display:block;margin-top:0.75rem;
             font-size:0.78rem;color:#00d4ff;
             text-decoration:none;
           ">
          View all <?= $elabel ?> records →
        </a>
      </div>
      <?php endif; ?>

      <!-- Contribute CTA -->
      <div style="
        margin-top:1rem;
        background:rgba(0,212,255,0.04);
        border:1px solid rgba(0,212,255,0.12);
        border-radius:12px;
        padding:1rem;
        text-align:center;
      ">
        <i class="ti ti-plus"
           style="
             font-size:1.5rem;color:#00d4ff;
             display:block;margin-bottom:0.5rem;
           "></i>
        <div style="
          color:#aaa;font-size:0.82rem;
          margin-bottom:0.75rem;
          line-height:1.4;
        ">
          Know something about this record or
          Ekpor's history?
        </div>
        <a href="<?= SITE_URL ?>/heritage/contribute.php"
           class="btn btn-primary btn-sm w-100">
          Contribute a Record
        </a>
      </div>

    </div>
  </div>

</div>
</main>

<?php require_once '../includes/footer.php'; ?>
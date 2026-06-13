<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$user    = currentUser();
$errors  = [];
$success = false;

// ── Handle POST ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $title       = trim($_POST['title']       ?? '');
    $type        = $_POST['type']             ?? '';
    $era         = $_POST['era']              ?? '';
    $event_date  = $_POST['event_date']       ?? '';
    $date_approx = isset($_POST['date_approx']) ? 1 : 0;
    $location    = trim($_POST['location']    ?? '');
    $description = trim($_POST['description'] ?? '');
    $source      = trim($_POST['source']      ?? '');
    $privacy     = $_POST['privacy']          ?? 'members';
    $save_draft  = isset($_POST['save_draft']);

    $allowed_types   = ['photograph','document','audio',
                        'event','oral_history'];
    $allowed_eras    = ['pre_colonial','colonial','modern'];
    $allowed_privacy = ['public','members','private'];

    // Validation
    if (empty($title))
        $errors[] = 'Title is required.';
    if (!in_array($type, $allowed_types))
        $errors[] = 'Please select a record type.';
    if (!in_array($era, $allowed_eras))
        $errors[] = 'Please select an era.';
    if (!in_array($privacy, $allowed_privacy))
        $privacy = 'members';
    if (empty($description))
        $errors[] = 'Description is required.';

    // File upload (optional)
    $file_path = null;
    $file_name = null;
    $file_size = null;
    $mime_type = null;
    $file_kind = null;

    if (!empty($_FILES['media']['name'])) {
        $upload = uploadFile($_FILES['media'],
            in_array($type, ['photograph'])
                ? 'photo'
                : (in_array($type, ['audio'])
                    ? 'audio' : 'document')
        );
        if (isset($upload['error'])) {
            $errors[] = $upload['error'];
        } else {
            $file_path = $upload['path'];
            $file_name = $_FILES['media']['name'];
            $file_size = $_FILES['media']['size'];
            $mime_type = $_FILES['media']['type'];
            $file_kind = in_array($type, ['photograph'])
                ? 'photo'
                : (in_array($type, ['audio'])
                    ? 'audio' : 'document');
        }
    }

    if (empty($errors)) {

        $status = $save_draft ? 'draft' : 'pending';

        // Insert contribution
        $stmt = $pdo->prepare("
            INSERT INTO contributions
                (title, type, event_date, description,
                 submitted_by, status, privacy, created_at)
            VALUES (?,?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([
            $title,
            $type,
            $event_date ?: null,
            $description,
            $user['id'],
            $status,
            $privacy,
        ]);
        $contrib_id = $pdo->lastInsertId();

        // Also insert into heritage_records if not draft
        // (pending = awaiting admin approval)
        // For now we store in contributions only;
        // admin approval moves it to heritage_records.

        // Insert media file if uploaded
        if ($file_path) {
            $pdo->prepare("
                INSERT INTO media_files
                    (record_id, file_type, file_path,
                     file_name, file_size, mime_type,
                     uploaded_by, uploaded_at)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $file_kind, $file_path,
                $file_name, $file_size,
                $mime_type, $user['id'],
            ]);
        }

        // Audit log
        $pdo->prepare("
            INSERT INTO audit_log
                (user_id, action, target_type,
                 target_id, detail)
            VALUES (?, ?, 'contribution', ?, ?)
        ")->execute([
            $user['id'],
            $save_draft
                ? 'save_draft' : 'submit_contribution',
            $contrib_id,
            ($save_draft ? 'Draft saved: ' : 'Submitted: ')
                . $title,
        ]);

        $success = $save_draft ? 'draft' : 'pending';
    }
}

// ── Era / type helpers ───────────────────────────
$type_options = [
    'event'        => ['ti-calendar-event', 'Historical Event',
                       'A significant event in village history'],
    'oral_history' => ['ti-speakerphone',   'Oral History',
                       'A story or account passed down verbally'],
    'photograph'   => ['ti-photo',          'Photograph',
                       'A historical or contemporary photo'],
    'document'     => ['ti-file-text',      'Document',
                       'A written record, letter, or report'],
    'audio'        => ['ti-microphone',     'Audio Recording',
                       'A recorded interview or oral account'],
];
$era_options = [
    'pre_colonial' => ['#ff9f1a', 'Pre-Colonial',
                       'Before European contact'],
    'colonial'     => ['#a78bfa', 'Colonial',
                       'German & British colonial period'],
    'modern'       => ['#00d4ff', 'Modern',
                       'Post-independence to present'],
];
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:760px">

  <!-- Breadcrumb -->
  <div style="font-size:0.82rem;color:#555;
              margin-bottom:1.5rem">
    <a href="<?= SITE_URL ?>/heritage/history.php"
       style="color:#555;text-decoration:none">
      Village History
    </a>
    <span style="margin:0 0.5rem">›</span>
    <span style="color:#888">Contribute a Record</span>
  </div>

  <!-- Success states -->
  <?php if ($success === 'pending'): ?>
  <div style="
    background:rgba(0,200,100,0.07);
    border:1px solid rgba(0,200,100,0.2);
    border-radius:14px;padding:2rem;
    text-align:center;margin-bottom:2rem;
  ">
    <i class="ti ti-circle-check"
       style="font-size:2.5rem;color:#00c864;
              display:block;margin-bottom:0.75rem"></i>
    <h4 style="color:#fff;margin:0 0 0.5rem">
      Contribution Submitted!
    </h4>
    <p style="color:#888;font-size:0.9rem;margin:0">
      Your record has been submitted for review.
      An administrator will approve it shortly
      and it will appear in the Village History.
    </p>
    <div style="
      display:flex;gap:0.75rem;
      justify-content:center;margin-top:1.25rem;
    ">
      <a href="<?= SITE_URL ?>/heritage/history.php"
         class="btn btn-outline-light btn-sm">
        View Village History
      </a>
      <a href="<?= SITE_URL ?>/heritage/contribute.php"
         class="btn btn-primary btn-sm">
        Contribute Another
      </a>
    </div>
  </div>

  <?php elseif ($success === 'draft'): ?>
  <div style="
    background:rgba(255,159,26,0.07);
    border:1px solid rgba(255,159,26,0.2);
    border-radius:14px;padding:1.5rem;
    margin-bottom:2rem;
  ">
    <i class="ti ti-file"
       style="color:#ff9f1a;margin-right:0.5rem"></i>
    <strong style="color:#ff9f1a">Draft saved.</strong>
    <span style="color:#888;font-size:0.88rem">
      You can submit it for review from your
      contributions list.
    </span>
  </div>

  <?php else: ?>

  <!-- Page header -->
  <div style="margin-bottom:1.75rem">
    <h2 style="color:#fff;margin:0 0 0.4rem">
      <i class="ti ti-plus me-2"
         style="color:#00d4ff"></i>
      Contribute a Heritage Record
    </h2>
    <p style="color:#666;margin:0;font-size:0.9rem">
      Share a piece of Ekpor Village history.
      Your submission will be reviewed by an
      administrator before publishing.
    </p>
  </div>

  <!-- Errors -->
  <?php foreach ($errors as $e): ?>
  <div style="
    background:rgba(220,53,69,0.08);
    border:1px solid rgba(220,53,69,0.25);
    color:#ff6b7a;border-radius:10px;
    padding:0.75rem 1rem;margin-bottom:0.75rem;
    font-size:0.88rem;
  ">
    <i class="ti ti-alert-circle me-2"></i>
    <?= clean($e) ?>
  </div>
  <?php endforeach; ?>

  <form method="POST" action=""
        enctype="multipart/form-data">
    <?= csrfField() ?>

    <!-- ── Record type ─────────────────────── -->
    <div class="contrib-card mb-4">
      <div class="contrib-section-title">
        Record Type *
      </div>
      <div style="
        display:grid;gap:0.6rem;
        grid-template-columns:1fr 1fr;
      ">
        <?php foreach ($type_options as
            $val => [$icon, $label, $desc]):
          $sel = ($_POST['type'] ?? '') === $val;
        ?>
        <label class="type-card
          <?= $sel ? 'type-card--active' : '' ?>">
          <input type="radio" name="type"
                 value="<?= $val ?>"
                 <?= $sel ? 'checked' : '' ?>
                 style="display:none"
                 required>
          <i class="ti <?= $icon ?>"></i>
          <div>
            <div class="type-card-title">
              <?= $label ?>
            </div>
            <div class="type-card-desc">
              <?= $desc ?>
            </div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Era ────────────────────────────── -->
    <div class="contrib-card mb-4">
      <div class="contrib-section-title">
        Historical Era *
      </div>
      <div style="display:flex;gap:0.6rem;
                  flex-wrap:wrap">
        <?php foreach ($era_options as
            $val => [$color, $label, $desc]):
          $sel = ($_POST['era'] ?? '') === $val;
        ?>
        <label style="
          flex:1;min-width:140px;cursor:pointer;
          padding:0.75rem 1rem;
          border:1px solid <?= $sel
              ? $color : '#1e1e3a' ?>;
          border-radius:9px;
          background:<?= $sel
              ? $color.'18' : 'transparent' ?>;
          transition:all 0.15s;
        " class="era-card">
          <input type="radio" name="era"
                 value="<?= $val ?>"
                 <?= $sel ? 'checked' : '' ?>
                 style="display:none"
                 required>
          <div style="
            color:<?= $sel ? $color : '#aaa' ?>;
            font-weight:600;font-size:0.88rem;
          "><?= $label ?></div>
          <div style="
            color:#555;font-size:0.75rem;
            margin-top:2px;
          "><?= $desc ?></div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Title & core fields ────────────── -->
    <div class="contrib-card mb-4">
      <div class="contrib-section-title">
        Record Details
      </div>

      <!-- Title -->
      <div class="mb-3">
        <label class="contrib-label">
          Title *
        </label>
        <input type="text" name="title"
               class="contrib-input"
               value="<?= clean($_POST['title'] ?? '') ?>"
               placeholder="e.g. Founding of Ekpor Village"
               required>
      </div>

      <!-- Description -->
      <div class="mb-3">
        <label class="contrib-label">
          Description *
        </label>
        <textarea name="description"
                  class="contrib-input"
                  style="min-height:140px;resize:vertical"
                  placeholder="Describe this record in detail. Include what happened, who was involved, and why it is significant to Ekpor Village..."
                  required
                  ><?= clean($_POST['description'] ?? '') ?></textarea>
      </div>

      <!-- Date + approximate -->
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="contrib-label">
            Date
            <span style="color:#444">(optional)</span>
          </label>
          <input type="date" name="event_date"
                 class="contrib-input"
                 value="<?= clean(
                     $_POST['event_date'] ?? '') ?>">
          <div class="form-check mt-2">
            <input type="checkbox"
                   name="date_approx"
                   class="form-check-input"
                   id="date_approx"
                   <?= isset($_POST['date_approx'])
                       ? 'checked' : '' ?>>
            <label class="form-check-label"
                   for="date_approx"
                   style="color:#555;font-size:0.8rem">
              Approximate / estimated date
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <label class="contrib-label">
            Location
            <span style="color:#444">(optional)</span>
          </label>
          <input type="text" name="location"
                 class="contrib-input"
                 value="<?= clean(
                     $_POST['location'] ?? '') ?>"
                 placeholder="e.g. Ekpor Village, Manyu">
        </div>
      </div>

      <!-- Source -->
      <div class="mb-0">
        <label class="contrib-label">
          Source / Reference
          <span style="color:#444">(optional)</span>
        </label>
        <input type="text" name="source"
               class="contrib-input"
               value="<?= clean($_POST['source'] ?? '') ?>"
               placeholder="e.g. Oral account — Elder John, 2026">
        <div style="color:#444;font-size:0.75rem;
                    margin-top:4px">
          Name the person or document this
          information comes from.
        </div>
      </div>
    </div>

    <!-- ── Media upload ────────────────────── -->
    <div class="contrib-card mb-4">
      <div class="contrib-section-title">
        Attach Media
        <span style="
          color:#444;font-weight:400;
          text-transform:none;font-size:0.78rem;
          letter-spacing:0;
        ">(optional)</span>
      </div>

      <div id="upload_area" style="
        border:2px dashed #1e1e3a;
        border-radius:10px;
        padding:2rem;text-align:center;
        cursor:pointer;
        transition:border-color 0.15s;
        position:relative;
      " onclick="
        document.getElementById('media_file')
          .click()">
        <i class="ti ti-cloud-upload"
           style="
             font-size:2rem;color:#333;
             display:block;margin-bottom:0.5rem;
           " id="upload_icon"></i>
        <div style="color:#555;font-size:0.88rem"
             id="upload_label">
          Click to upload a photo, document,
          or audio file
        </div>
        <div style="color:#333;font-size:0.75rem;
                    margin-top:4px">
          JPG, PNG, PDF, MP3, WAV — max 10MB
        </div>
        <input type="file" name="media"
               id="media_file"
               accept="image/*,.pdf,.doc,.docx,
                       .mp3,.wav,.ogg,.m4a"
               style="display:none"
               onchange="previewFile(this)">
      </div>

      <div id="file_preview"
           style="display:none;margin-top:0.75rem">
      </div>
    </div>

    <!-- ── Privacy ─────────────────────────── -->
    <div class="contrib-card mb-4">
      <div class="contrib-section-title">
        Visibility
      </div>
      <?php
      $priv_opts = [
        'public'  => ['ti-world',  'Public',
                      'Visible to everyone, including visitors'],
        'members' => ['ti-users',  'Members only',
                      'Only visible to registered members'],
        'private' => ['ti-lock',   'Private',
                      'Only visible to administrators'],
      ];
      $cur_priv = $_POST['privacy'] ?? 'members';
      foreach ($priv_opts as $val =>
          [$icon, $label, $desc]):
        $sel = $cur_priv === $val;
      ?>
      <label style="
        display:flex;align-items:center;
        gap:0.85rem;padding:0.7rem 0.9rem;
        border:1px solid <?= $sel
            ? 'rgba(0,212,255,0.3)' : '#1e1e3a' ?>;
        border-radius:8px;margin-bottom:0.5rem;
        cursor:pointer;
        background:<?= $sel
            ? 'rgba(0,212,255,0.04)'
            : 'transparent' ?>;
      ">
        <input type="radio" name="privacy"
               value="<?= $val ?>"
               <?= $sel ? 'checked' : '' ?>>
        <i class="ti <?= $icon ?>"
           style="color:<?= $sel
               ? '#00d4ff' : '#555' ?>;
               font-size:1rem"></i>
        <div>
          <div style="color:<?= $sel
              ? '#e0e0e0' : '#aaa' ?>;
              font-size:0.88rem;font-weight:500">
            <?= $label ?>
          </div>
          <div style="color:#555;font-size:0.76rem">
            <?= $desc ?>
          </div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- ── Submit buttons ──────────────────── -->
    <div style="
      display:flex;gap:0.75rem;
      flex-wrap:wrap;justify-content:flex-end;
      padding-bottom:3rem;
    ">
      <button type="submit" name="save_draft"
              value="1"
              style="
                background:transparent;
                border:1px solid #2a2a4a;
                color:#888;border-radius:8px;
                padding:0.6rem 1.4rem;
                font-size:0.9rem;cursor:pointer;
                transition:border-color 0.15s;
              ">
        <i class="ti ti-file me-1"></i>
        Save as Draft
      </button>
      <button type="submit"
              style="
                background:#00d4ff;color:#000;
                border:none;border-radius:8px;
                padding:0.6rem 1.75rem;
                font-size:0.9rem;font-weight:700;
                cursor:pointer;
                transition:opacity 0.15s;
              ">
        <i class="ti ti-send me-1"></i>
        Submit for Review
      </button>
    </div>

  </form>
  <?php endif; ?>

</div>
</main>

<!-- ── Scoped styles ──────────────────────────── -->
<style>
.contrib-card {
  background:#111127;
  border:1px solid #1e1e3a;
  border-radius:12px;
  padding:1.4rem;
}
.contrib-section-title {
  font-size:0.72rem;font-weight:700;
  letter-spacing:0.08em;color:#00d4ff;
  text-transform:uppercase;
  margin-bottom:1rem;
}
.contrib-label {
  display:block;color:#aaa;
  font-size:0.83rem;margin-bottom:5px;
}
.contrib-input {
  width:100%;
  background:#0d0d1a;
  border:1px solid #1e1e3a;
  color:#e0e0e0;border-radius:8px;
  padding:0.5rem 0.85rem;
  font-size:0.9rem;outline:none;
  transition:border-color 0.15s;
  display:block;
}
.contrib-input:focus { border-color:#00d4ff; }
/* Type selection cards */
.type-card {
  display:flex;align-items:flex-start;
  gap:0.65rem;padding:0.8rem 0.9rem;
  border:1px solid #1e1e3a;border-radius:9px;
  cursor:pointer;
  transition:border-color 0.15s,background 0.15s;
}
.type-card i {
  font-size:1.2rem;color:#444;
  margin-top:1px;flex-shrink:0;
}
.type-card--active {
  border-color:rgba(0,212,255,0.35);
  background:rgba(0,212,255,0.05);
}
.type-card--active i { color:#00d4ff; }
.type-card-title {
  color:#ccc;font-size:0.85rem;font-weight:500;
}
.type-card-desc {
  color:#555;font-size:0.75rem;margin-top:2px;
}
#upload_area:hover {
  border-color:rgba(0,212,255,0.3);
}
</style>

<script>
// ── Type card selection ───────────────────────
document.querySelectorAll('.type-card').forEach(c => {
  c.addEventListener('click', () => {
    document.querySelectorAll('.type-card')
        .forEach(x => x.classList
            .remove('type-card--active'));
    c.classList.add('type-card--active');
    c.querySelector('input').checked = true;
  });
});

// ── Era card selection ────────────────────────
document.querySelectorAll('.era-card').forEach(c => {
  c.addEventListener('click', () => {
    const val = c.querySelector('input').value;
    const colors = {
      pre_colonial: '#ff9f1a',
      colonial:     '#a78bfa',
      modern:       '#00d4ff',
    };
    document.querySelectorAll('.era-card').forEach(x => {
      x.style.borderColor = '#1e1e3a';
      x.style.background  = 'transparent';
      x.querySelector('div').style.color = '#aaa';
    });
    const color = colors[val] || '#00d4ff';
    c.style.borderColor = color;
    c.style.background  = color + '18';
    c.querySelector('div').style.color = color;
    c.querySelector('input').checked = true;
  });
});

// ── File preview ──────────────────────────────
function previewFile(input) {
  const area    = document.getElementById('upload_area');
  const preview = document.getElementById('file_preview');
  const icon    = document.getElementById('upload_icon');
  const label   = document.getElementById('upload_label');

  if (!input.files || !input.files[0]) return;

  const file = input.files[0];
  const name = file.name;
  const size = (file.size / 1024).toFixed(0) + ' KB';
  const isImg = file.type.startsWith('image/');

  if (isImg) {
    const reader = new FileReader();
    reader.onload = e => {
      preview.style.display = 'block';
      preview.innerHTML = `
        <div style="
          display:flex;align-items:center;
          gap:0.85rem;
          background:#0d0d1a;
          border:1px solid #1e1e3a;
          border-radius:9px;padding:0.75rem;
        ">
          <img src="${e.target.result}"
               style="
                 width:60px;height:60px;
                 object-fit:cover;
                 border-radius:6px;flex-shrink:0;
               ">
          <div>
            <div style="color:#ccc;font-size:0.85rem">
              ${name}
            </div>
            <div style="color:#555;font-size:0.75rem;
                        margin-top:2px">${size}</div>
          </div>
          <button type="button"
                  onclick="clearFile()"
                  style="
                    margin-left:auto;background:none;
                    border:none;color:#555;
                    cursor:pointer;font-size:1.1rem;
                  ">✕</button>
        </div>`;
    };
    reader.readAsDataURL(file);
  } else {
    preview.style.display = 'block';
    preview.innerHTML = `
      <div style="
        display:flex;align-items:center;
        gap:0.85rem;
        background:#0d0d1a;
        border:1px solid #1e1e3a;
        border-radius:9px;padding:0.75rem;
      ">
        <i class="ti ti-file"
           style="font-size:1.6rem;color:#00d4ff;
                  flex-shrink:0"></i>
        <div>
          <div style="color:#ccc;font-size:0.85rem">
            ${name}
          </div>
          <div style="color:#555;font-size:0.75rem;
                      margin-top:2px">${size}</div>
        </div>
        <button type="button"
                onclick="clearFile()"
                style="
                  margin-left:auto;background:none;
                  border:none;color:#555;
                  cursor:pointer;font-size:1.1rem;
                ">✕</button>
      </div>`;
  }

  icon.style.display  = 'none';
  label.style.display = 'none';
}

function clearFile() {
  document.getElementById('media_file').value = '';
  document.getElementById('file_preview')
      .style.display = 'none';
  document.getElementById('file_preview').innerHTML = '';
  document.getElementById('upload_icon')
      .style.display = 'block';
  document.getElementById('upload_label')
      .style.display = 'block';
}
</script>

<?php require_once '../includes/footer.php'; ?>
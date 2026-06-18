<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireProfile();
$user     = currentUser();
$myMember = getUserMember($pdo, $user['id']);

$member_id = (int)($_GET['id'] ?? 0);
if (!$member_id) {
    header('Location: ' . SITE_URL . '/family/tree.php');
    exit;
}

// Load the member
$member = $pdo->prepare("
    SELECT fm.*, q.name AS quarter_name
    FROM   family_members fm
    LEFT JOIN quarters q
        ON q.quarter_id = fm.quarter_id
    WHERE  fm.member_id = ?
    LIMIT  1
");
$member->execute([$member_id]);
$member = $member->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    header('Location: ' . SITE_URL . '/family/tree.php');
    exit;
}

// Editable fields — label => column name
$editable_fields = [
    'Full Name'        => 'full_name',
    'Preferred Name'   => 'preferred_name',
    'Gender'           => 'gender',
    'Date of Birth'    => 'date_of_birth',
    'Date of Death'    => 'date_of_death',
    'Birthplace'       => 'birthplace',
    'Current Location' => 'current_location',
    'Occupation'       => 'occupation',
    'Short Bio'        => 'short_bio',
];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $field_changed = $_POST['field_changed'] ?? '';
    $new_value     = trim($_POST['new_value'] ?? '');
    $reason        = trim($_POST['reason']    ?? '');

    $allowed_columns = array_values($editable_fields);

    if (!in_array($field_changed, $allowed_columns)) {
        $errors[] = 'Please select a valid field.';
    }
    if (empty($new_value)) {
        $errors[] = 'Please enter a proposed value.';
    }
    if (mb_strlen($new_value) > 500) {
        $errors[] = 'Proposed value is too long.';
    }

    if (empty($errors)) {
        $old_value = $member[$field_changed] ?? '';

        if ($old_value === $new_value) {
            $errors[] = 'The proposed value is the '
                . 'same as the current value.';
        } else {
            // Check for existing pending edit
            // on same field
            $dup = $pdo->prepare("
                SELECT edit_id FROM pending_edits
                WHERE member_id     = ?
                  AND field_changed = ?
                  AND status        = 'pending'
                  AND submitted_by  = ?
                LIMIT 1
            ");
            $dup->execute([
                $member_id,
                $field_changed,
                $user['id'],
            ]);

            if ($dup->fetchColumn()) {
                $errors[] = 'You already have a '
                    . 'pending edit for this field. '
                    . 'Please wait for it to be '
                    . 'reviewed before submitting '
                    . 'another.';
            } else {
                $pdo->prepare("
                    INSERT INTO pending_edits
                        (member_id, submitted_by,
                         field_changed,
                         old_value, new_value,
                         reason, status,
                         submitted_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?,
                         'pending', NOW())
                ")->execute([
                    $member_id,
                    $user['id'],
                    $field_changed,
                    $old_value ?: null,
                    $new_value,
                    $reason    ?: null,
                ]);

                // Notify admins
                $admins = $pdo->query("
                    SELECT user_id FROM users
                    WHERE role = 'admin'
                ")->fetchAll(PDO::FETCH_COLUMN);

                foreach ($admins as $admin_id) {
                    $pdo->prepare("
                        INSERT INTO notifications
                            (user_id, message,
                             is_read, created_at)
                        VALUES (?, ?, 0, NOW())
                    ")->execute([
                        $admin_id,
                        clean($user['name'])
                        . ' proposed an edit to '
                        . clean($member['full_name'])
                        . '\'s profile.',
                    ]);
                }

                $pdo->prepare("
                    INSERT INTO audit_log
                        (user_id, action,
                         target_type, target_id,
                         detail)
                    VALUES
                        (?, 'submit_edit',
                         'family_member', ?, ?)
                ")->execute([
                    $user['id'],
                    $member_id,
                    'Proposed change to '
                    . $field_changed . ' for '
                    . $member['full_name'],
                ]);

                $success = true;
                $_POST   = [];
            }
        }
    }
}

$quarters = getAllQuarters($pdo);
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:640px">

  <!-- Back link -->
  <a href="<?= SITE_URL ?>/family/tree.php"
     style="
       color:#555;text-decoration:none;
       font-size:0.85rem;
       display:inline-flex;align-items:center;
       gap:4px;margin-bottom:1.5rem;
     ">
    <i class="ti ti-arrow-left"></i>
    Back to Family Tree
  </a>

  <!-- Header -->
  <div style="margin-bottom:1.5rem">
    <h2 style="color:#fff;margin:0 0 0.25rem">
      <i class="ti ti-edit me-2"
         style="color:#00d4ff"></i>
      Propose an Edit
    </h2>
    <p style="color:#555;margin:0;
              font-size:0.85rem">
      Suggesting a correction for
      <strong style="color:#aaa">
        <?= clean($member['full_name']) ?>
      </strong>.
      An admin will review it before
      any changes are applied.
    </p>
  </div>

  <!-- Success -->
  <?php if ($success): ?>
  <div style="
    background:rgba(0,212,255,0.07);
    border:1px solid rgba(0,212,255,0.25);
    border-radius:10px;
    padding:1rem 1.25rem;
    color:#00d4ff;
    margin-bottom:1.5rem;
  ">
    <strong>
      <i class="ti ti-check me-1"></i>
      Edit submitted!
    </strong><br>
    <span style="font-size:0.85rem;
                 color:#aaa;margin-top:4px;
                 display:block">
      Your proposed change has been sent to
      an admin for review. You will be notified
      once it is approved or rejected.
    </span>
    <a href="<?= SITE_URL ?>/family/tree.php"
       style="
         display:inline-block;margin-top:0.75rem;
         color:#00d4ff;font-size:0.85rem;
       ">
      ← Return to family tree
    </a>
  </div>
  <?php endif; ?>

  <!-- Errors -->
  <?php foreach ($errors as $e): ?>
  <div style="
    background:rgba(255,107,122,0.08);
    border:1px solid rgba(255,107,122,0.25);
    border-radius:8px;padding:0.75rem 1rem;
    color:#ff6b7a;font-size:0.85rem;
    margin-bottom:0.75rem;
  "><?= clean($e) ?></div>
  <?php endforeach; ?>

  <!-- Current member card -->
  <div style="
    background:#0d0d1a;
    border:1px solid #1e1e3a;
    border-radius:10px;
    padding:0.85rem 1.1rem;
    margin-bottom:1.5rem;
    display:flex;gap:0.85rem;
    align-items:center;
  ">
    <div style="
      width:42px;height:42px;border-radius:50%;
      background:rgba(0,212,255,0.1);
      border:1px solid rgba(0,212,255,0.2);
      display:flex;align-items:center;
      justify-content:center;
      font-size:1rem;font-weight:700;
      color:#00d4ff;overflow:hidden;
      flex-shrink:0;
    ">
      <?php if ($member['photo']): ?>
        <img src="<?= SITE_URL . '/'
                     . $member['photo'] ?>"
             style="width:100%;height:100%;
                    object-fit:cover">
      <?php else: ?>
        <?= strtoupper(substr(
            $member['full_name'], 0, 1)) ?>
      <?php endif; ?>
    </div>
    <div>
      <div style="color:#fff;font-weight:600">
        <?= clean($member['full_name']) ?>
      </div>
      <div style="color:#555;font-size:0.8rem">
        <?= $member['quarter_name']
            ? clean($member['quarter_name'])
            : '' ?>
        <?= $member['gender']
            ? ' · ' . ucfirst($member['gender'])
            : '' ?>
      </div>
    </div>
  </div>

  <!-- Edit form -->
  <?php if (!$success): ?>
  <form method="POST" action="">
    <?= csrfField() ?>

    <!-- Field selector -->
    <div class="mb-3">
      <label style="
        color:#aaa;font-size:0.85rem;
        display:block;margin-bottom:6px;
      ">
        Which field needs correcting? *
      </label>
      <select name="field_changed"
              class="form-control"
              id="field_selector"
              required
              style="
                background:#0d0d1a;
                border:1px solid #1e1e3a;
                color:#e0e0e0;border-radius:8px;
              "
              onchange="updateCurrentValue(this)">
        <option value="">— Select a field —</option>
        <?php foreach ($editable_fields
                       as $label => $col): ?>
        <option value="<?= $col ?>"
          <?= ($_POST['field_changed'] ?? '')
              === $col ? 'selected' : '' ?>>
          <?= $label ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Current value display -->
    <div id="current_value_wrap"
         style="display:none;margin-bottom:1rem">
      <div style="
        background:#0d0d1a;
        border:1px solid #1e1e3a;
        border-radius:8px;
        padding:0.65rem 0.9rem;
      ">
        <div style="
          font-size:0.72rem;color:#444;
          margin-bottom:4px;
          text-transform:uppercase;
          letter-spacing:0.05em;
        ">Current value</div>
        <div id="current_value_text"
             style="color:#777;font-size:0.88rem">
        </div>
      </div>
    </div>

    <!-- Proposed value -->
    <div class="mb-3">
      <label style="
        color:#aaa;font-size:0.85rem;
        display:block;margin-bottom:6px;
      ">
        Proposed correct value *
      </label>
      <input type="text" name="new_value"
             id="new_value"
             class="form-control"
             required
             style="
               background:#0d0d1a;
               border:1px solid #1e1e3a;
               color:#e0e0e0;border-radius:8px;
             "
             value="<?= clean(
                 $_POST['new_value'] ?? '') ?>"
             placeholder="Enter the correct value">
    </div>

    <!-- Reason -->
    <div class="mb-3">
      <label style="
        color:#aaa;font-size:0.85rem;
        display:block;margin-bottom:6px;
      ">
        Reason / source
        <span style="color:#444">(optional)</span>
      </label>
      <textarea name="reason"
                class="form-control"
                rows="3"
                style="
                  background:#0d0d1a;
                  border:1px solid #1e1e3a;
                  color:#e0e0e0;border-radius:8px;
                  resize:none;
                "
                placeholder="e.g. Confirmed with family elder, birth certificate, etc."
      ><?= clean($_POST['reason'] ?? '') ?></textarea>
    </div>

    <button type="submit"
            style="
              background:#00d4ff;color:#000;
              border:none;border-radius:8px;
              padding:0.65rem 1.5rem;
              font-weight:700;font-size:0.9rem;
              cursor:pointer;
            ">
      <i class="ti ti-send me-1"></i>
      Submit for Review
    </button>

    <a href="<?= SITE_URL ?>/family/tree.php"
       style="
         color:#444;font-size:0.85rem;
         text-decoration:none;
         margin-left:1rem;
       ">
      Cancel
    </a>
  </form>
  <?php endif; ?>

</div>
</main>

<script>
// Current member data for the field preview
const memberData = {
  full_name:        <?= json_encode(
      $member['full_name'] ?? '') ?>,
  preferred_name:   <?= json_encode(
      $member['preferred_name'] ?? '') ?>,
  gender:           <?= json_encode(
      $member['gender'] ?? '') ?>,
  date_of_birth:    <?= json_encode(
      $member['date_of_birth'] ?? '') ?>,
  date_of_death:    <?= json_encode(
      $member['date_of_death'] ?? '') ?>,
  birthplace:       <?= json_encode(
      $member['birthplace'] ?? '') ?>,
  current_location: <?= json_encode(
      $member['current_location'] ?? '') ?>,
  occupation:       <?= json_encode(
      $member['occupation'] ?? '') ?>,
  short_bio:        <?= json_encode(
      $member['short_bio'] ?? '') ?>,
};

function updateCurrentValue(sel) {
  const field = sel.value;
  const wrap  = document.getElementById(
      'current_value_wrap');
  const txt   = document.getElementById(
      'current_value_text');

  if (!field) {
    wrap.style.display = 'none';
    return;
  }

  const val = memberData[field];
  txt.textContent = val || '(not set)';
  txt.style.color = val ? '#777' : '#333';

  // Pre-fill proposed value with current value
  const inp = document.getElementById('new_value');
  if (!inp.value && val) inp.value = val;

  wrap.style.display = 'block';
}

// Trigger on page load if field pre-selected
const sel = document.getElementById('field_selector');
if (sel && sel.value) updateCurrentValue(sel);
</script>

<?php require_once '../includes/footer.php'; ?>
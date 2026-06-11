<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

// User must have a profile before adding members
if (!hasProfile($pdo, $_SESSION['user_id'])) {
    redirect(SITE_URL . '/settings/profile.php');
}

$errors  = [];
$success = false;
$user    = currentUser();
$myMember = getUserMember($pdo, $user['id']);

// Relationship options
// describing how the new person
// relates to the logged-in user
$relation_options = [
    'Immediate Family' => [
        'father'      => 'Father',
        'mother'      => 'Mother',
        'spouse'      => 'Spouse / Partner',
        'son'         => 'Son',
        'daughter'    => 'Daughter',
        'brother'     => 'Brother',
        'sister'      => 'Sister',
    ],
    'Grandparents' => [
        'grandfather_paternal' =>
            'Grandfather (Father\'s side)',
        'grandmother_paternal' =>
            'Grandmother (Father\'s side)',
        'grandfather_maternal' =>
            'Grandfather (Mother\'s side)',
        'grandmother_maternal' =>
            'Grandmother (Mother\'s side)',
    ],
    'Extended Family' => [
        'uncle'       => 'Uncle',
        'aunt'        => 'Aunt',
        'nephew'      => 'Nephew',
        'niece'       => 'Niece',
        'cousin'      => 'Cousin',
        'great_grandfather' => 'Great Grandfather',
        'great_grandmother' => 'Great Grandmother',
        'stepfather'  => 'Stepfather',
        'stepmother'  => 'Stepmother',
        'half_brother' => 'Half Brother',
        'half_sister'  => 'Half Sister',
        'other'       => 'Other Relative',
    ],
];

// Map relation to relationships table type
$type_map = [
    'father'               => 'parent',
    'mother'               => 'parent',
    'grandfather_paternal' => 'parent',
    'grandmother_paternal' => 'parent',
    'grandfather_maternal' => 'parent',
    'grandmother_maternal' => 'parent',
    'great_grandfather'    => 'parent',
    'great_grandmother'    => 'parent',
    'stepfather'           => 'parent',
    'stepmother'           => 'parent',
    'son'                  => 'child',
    'daughter'             => 'child',
    'nephew'               => 'child',
    'niece'                => 'child',
    'spouse'               => 'spouse',
    'brother'              => 'sibling',
    'sister'               => 'sibling',
    'uncle'                => 'sibling',
    'aunt'                 => 'sibling',
    'cousin'               => 'sibling',
    'half_brother'         => 'sibling',
    'half_sister'          => 'sibling',
    'other'                => 'sibling',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name      = trim($_POST['full_name']      ?? '');
    $preferred_name = trim($_POST['preferred_name'] ?? '');
    $gender         = $_POST['gender']              ?? '';
    $date_of_birth  = $_POST['date_of_birth']       ?? '';
    $dob_approx     = isset($_POST['dob_approximate']) ? 1 : 0;
    $date_of_death  = $_POST['date_of_death']       ?? '';
    $dod_approx     = isset($_POST['dod_approximate']) ? 1 : 0;
    $birthplace     = trim($_POST['birthplace']     ?? '');
    $occupation     = trim($_POST['occupation']     ?? '');
    $short_bio      = trim($_POST['short_bio']      ?? '');
    $quarter_id     = $_POST['quarter_id']          ?? '';
    $source         = trim($_POST['source_of_info'] ?? '');
    $privacy        = $_POST['privacy']             ?? 'members';
    $my_relation    = $_POST['my_relation']         ?? '';

    // Validation
    if (empty($full_name))
        $errors[] = 'Full name is required.';
    if (empty($quarter_id))
        $errors[] = 'Please select a quarter.';
    if (empty($source))
        $errors[] = 'Source of information is required.';
    if (empty($my_relation))
        $errors[] = 'Please select how this person
                     is related to you.';

    // Handle photo upload
    $photo_path = null;
    if (!empty($_FILES['photo']['name'])) {
        $upload = uploadFile($_FILES['photo'], 'photo');
        if (isset($upload['error'])) {
            $errors[] = 'Photo: ' . $upload['error'];
        } else {
            $photo_path = $upload['path'];
        }
    }

    if (empty($errors)) {

        // Insert the new family member
        $stmt = $pdo->prepare("
            INSERT INTO family_members (
                full_name, preferred_name,
                gender, date_of_birth,
                dob_approximate, date_of_death,
                dod_approximate, birthplace,
                occupation, short_bio,
                quarter_id, photo,
                source_of_info, privacy,
                added_by
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $full_name,
            $preferred_name ?: null,
            $gender         ?: null,
            $date_of_birth  ?: null,
            $dob_approx,
            $date_of_death  ?: null,
            $dod_approx,
            $birthplace     ?: null,
            $occupation     ?: null,
            $short_bio      ?: null,
            $quarter_id,
            $photo_path,
            $source,
            $privacy,
            $user['id'],
        ]);

        $new_member_id = $pdo->lastInsertId();

        // Save the relationship between
        // the new member and the logged-in user
        $db_type = $type_map[$my_relation]
                   ?? 'sibling';

        saveRelationship(
            $pdo,
            $myMember['member_id'],
            $new_member_id,
            $db_type
        );

        // Store the specific relation label
        // in audit log
        $pdo->prepare("
            INSERT INTO audit_log
                (user_id, action,
                 target_type, target_id, detail)
            VALUES
                (?, 'add_member',
                 'family_member', ?, ?)
        ")->execute([
            $user['id'],
            $new_member_id,
            'Added ' . $my_relation
            . ': ' . $full_name
        ]);

        $success      = true;
        $success_name = $full_name;
        $success_rel  = $relation_options[
            array_key_first(
                array_filter(
                    $relation_options,
                    fn($g) => isset($g[$my_relation])
                )
            )
        ][$my_relation] ?? $my_relation;
    }
}

$quarters = getAllQuarters($pdo);
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem">
<div class="container" style="max-width:760px">

  <div class="mb-4">
    <a href="<?= SITE_URL ?>/family/tree.php"
       style="color:#888;font-size:0.9rem">
      ← Back to Family Tree
    </a>
    <h2 style="color:#fff;margin-top:0.75rem">
      Add Family Member
    </h2>
    <p style="color:#888">
      Add a relative to your family tree.
      Fields marked * are required.
    </p>
  </div>

  <!-- My profile summary -->
  <div style="
    background: rgba(0,212,255,0.06);
    border: 1px solid rgba(0,212,255,0.15);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
  ">
    <div style="
      width:42px;height:42px;
      border-radius:50%;
      background:#1e1e3a;
      display:flex;align-items:center;
      justify-content:center;
      font-size:1.1rem;font-weight:600;
      color:#00d4ff;flex-shrink:0;
    ">
      <?= strtoupper(substr($user['name'], 0, 1)) ?>
    </div>
    <div>
      <div style="color:#fff;font-size:0.9rem;
                  font-weight:500">
        Adding relative of
        <strong><?= clean($user['name']) ?></strong>
      </div>
      <div style="color:#555;font-size:0.8rem">
        <?= clean(
            $myMember['quarter_name'] ?? ''
        ) ?> Quarter
      </div>
    </div>
  </div>

  <!-- Success -->
  <?php if ($success): ?>
    <div class="alert alert-success mb-4">
      ✓ <strong><?= clean($success_name) ?></strong>
      (your <?= clean($success_rel) ?>)
      has been added to your family tree.
      <br><br>
      <a href="<?= SITE_URL ?>/family/add.php"
         style="color:#00d4ff">
        Add another relative
      </a>
      &nbsp;·&nbsp;
      <a href="<?= SITE_URL ?>/family/tree.php"
         style="color:#00d4ff">
        View family tree
      </a>
    </div>
  <?php endif; ?>

  <!-- Errors -->
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger">
      <?= clean($e) ?>
    </div>
  <?php endforeach; ?>

  <form method="POST" action=""
        enctype="multipart/form-data">

    <!-- ── RELATIONSHIP TO ME ─────────────── -->
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:12px;
      padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;
        margin-bottom:0.5rem;
      ">
        Relationship to Me *
      </div>
      <p style="color:#666;font-size:0.82rem;
                margin-bottom:1rem">
        How is this person related to you?
        This is what connects them to your
        position in the family tree.
      </p>

      <select name="my_relation"
              class="form-select"
              id="my_relation"
              style="background:#0d0d1a;
                     border:1px solid #1e1e3a;
                     color:#e0e0e0;border-radius:8px"
              onchange="updatePreview()"
              required>
        <option value="">
          -- How are they related to you? --
        </option>
        <?php foreach (
            $relation_options as $group => $options
        ): ?>
          <optgroup label="<?= $group ?>">
            <?php foreach (
                $options as $val => $label
            ): ?>
              <option value="<?= $val ?>"
                <?= ($_POST['my_relation'] ?? '')
                    === $val ? 'selected':'' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>

      <!-- Relationship preview -->
      <div id="relation_preview"
           style="
             margin-top:0.75rem;
             padding:0.75rem 1rem;
             background:rgba(0,212,255,0.06);
             border:1px solid rgba(0,212,255,0.15);
             border-radius:8px;
             color:#00d4ff;
             font-size:0.85rem;
             display:none;
           ">
      </div>
    </div>

    <!-- ── BIOGRAPHICAL INFORMATION ───────── -->
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:12px;
      padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;
        margin-bottom:1.25rem;
      ">
        Biographical Information
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Full Name *
        </label>
        <input type="text" name="full_name"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['full_name'] ?? ''
               ) ?>"
               id="member_name"
               placeholder="e.g. Enow Mbong"
               onkeyup="updatePreview()"
               required>
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Preferred / Known As
          <span style="color:#555">(optional)</span>
        </label>
        <input type="text" name="preferred_name"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['preferred_name'] ?? ''
               ) ?>"
               placeholder="Nickname or known name">
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Gender
          </label>
          <select name="gender" class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;border-radius:8px">
            <option value="">-- Select --</option>
            <option value="male" <?=
                ($_POST['gender']??'')==='male'
                ?'selected':''?>>Male</option>
            <option value="female" <?=
                ($_POST['gender']??'')==='female'
                ?'selected':''?>>Female</option>
            <option value="other" <?=
                ($_POST['gender']??'')==='other'
                ?'selected':''?>>Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Quarter *
          </label>
          <select name="quarter_id" class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;border-radius:8px"
                  required>
            <option value="">
              -- Select quarter --
            </option>
            <?php foreach ($quarters as $q): ?>
              <option value="<?= $q['quarter_id'] ?>"
                <?= ($_POST['quarter_id']??'')==
                    $q['quarter_id']
                    ?'selected':''?>>
                <?= clean($q['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Occupation
          <span style="color:#555">(optional)</span>
        </label>
        <input type="text" name="occupation"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['occupation'] ?? ''
               ) ?>"
               placeholder="e.g. Elder, Teacher, Farmer">
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Birthplace
          <span style="color:#555">(optional)</span>
        </label>
        <input type="text" name="birthplace"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['birthplace'] ?? ''
               ) ?>"
               placeholder="e.g. Ekpor Village">
      </div>

      <div class="mb-0">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Short Biography
          <span style="color:#555">(optional)</span>
        </label>
        <textarea name="short_bio"
                  class="form-control"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;border-radius:8px;
                         min-height:90px"
                  placeholder="Brief summary of their life..."
                  ><?= clean(
                      $_POST['short_bio'] ?? ''
                  ) ?></textarea>
      </div>
    </div>

    <!-- ── LIFE TIMELINE ───────────────────── -->
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:12px;
      padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;
        margin-bottom:1.25rem;
      ">
        Life Timeline
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Date of Birth
          </label>
          <input type="date" name="date_of_birth"
                 class="form-control"
                 style="background:#0d0d1a;
                        border:1px solid #1e1e3a;
                        color:#e0e0e0;border-radius:8px"
                 value="<?= clean(
                     $_POST['date_of_birth'] ?? ''
                 ) ?>">
          <div class="form-check mt-2">
            <input type="checkbox"
                   name="dob_approximate"
                   class="form-check-input"
                   id="dob_approx"
                   <?= isset($_POST['dob_approximate'])
                       ?'checked':''?>>
            <label class="form-check-label"
                   for="dob_approx"
                   style="color:#555;font-size:0.8rem">
              Approximate date
            </label>
          </div>
        </div>
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Date of Passing
            <span style="color:#555">
              (if deceased)
            </span>
          </label>
          <input type="date" name="date_of_death"
                 class="form-control"
                 style="background:#0d0d1a;
                        border:1px solid #1e1e3a;
                        color:#e0e0e0;border-radius:8px"
                 value="<?= clean(
                     $_POST['date_of_death'] ?? ''
                 ) ?>">
          <div class="form-check mt-2">
            <input type="checkbox"
                   name="dod_approximate"
                   class="form-check-input"
                   id="dod_approx"
                   <?= isset($_POST['dod_approximate'])
                       ?'checked':''?>>
            <label class="form-check-label"
                   for="dod_approx"
                   style="color:#555;font-size:0.8rem">
              Approximate date
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- ── PHOTO ───────────────────────────── -->
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:12px;
      padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;
        margin-bottom:1rem;
      ">
        Photo
        <span style="color:#555;
                     font-weight:400;
                     text-transform:none;
                     font-size:0.8rem;
                     margin-left:8px">
          optional
        </span>
      </div>
      <div style="
        border:2px dashed #1e1e3a;
        border-radius:10px;padding:1.5rem;
        text-align:center;color:#555;
      ">
        <i class="ti ti-camera"
           style="font-size:1.8rem;
                  display:block;
                  margin-bottom:0.5rem"></i>
        <label for="photo_upload"
               style="cursor:pointer;
                      color:#00d4ff;
                      font-size:0.9rem">
          Click to upload a photo
        </label>
        <input type="file" name="photo"
               id="photo_upload"
               accept="image/jpeg,image/png,image/webp"
               style="display:none"
               onchange="showFileName(this)">
        <p style="margin-top:0.4rem;font-size:0.78rem">
          PNG, JPG or WebP — max 10MB
        </p>
        <p id="file-name"
           style="color:#00d4ff;font-size:0.85rem">
        </p>
      </div>
    </div>

    <!-- ── PROVENANCE ──────────────────────── -->
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:12px;
      padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;
        margin-bottom:0.4rem;
      ">
        Provenance
      </div>
      <p style="color:#666;font-size:0.82rem;
                margin-bottom:1rem">
        Where did this information come from?
      </p>
      <label style="color:#aaa;font-size:0.85rem;
                    display:block;margin-bottom:4px">
        Source of Information *
      </label>
      <input type="text" name="source_of_info"
             class="form-control"
             style="background:#0d0d1a;
                    border:1px solid #1e1e3a;
                    color:#e0e0e0;border-radius:8px"
             value="<?= clean(
                 $_POST['source_of_info'] ?? ''
             ) ?>"
             placeholder="e.g. Family Bible,
               Oral account from elder,
               Personal knowledge"
             required>
    </div>

    <!-- ── PRIVACY ─────────────────────────── -->
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:12px;
      padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;
        margin-bottom:1rem;
      ">
        Privacy
      </div>
      <div class="d-flex gap-4 flex-wrap">
        <label style="cursor:pointer">
          <input type="radio" name="privacy"
                 value="public"
                 <?= ($_POST['privacy']??'members')
                     ==='public'?'checked':''?>>
          <span style="color:#aaa;font-size:0.9rem;
                       margin-left:6px">Public</span>
          <small style="display:block;color:#555;
                        font-size:0.78rem;
                        margin-left:20px">
            Anyone can view
          </small>
        </label>
        <label style="cursor:pointer">
          <input type="radio" name="privacy"
                 value="members"
                 <?= ($_POST['privacy']??'members')
                     ==='members'?'checked':''?>>
          <span style="color:#aaa;font-size:0.9rem;
                       margin-left:6px">
            Members Only
          </span>
          <small style="display:block;color:#555;
                        font-size:0.78rem;
                        margin-left:20px">
            Registered members only
          </small>
        </label>
        <label style="cursor:pointer">
          <input type="radio" name="privacy"
                 value="private"
                 <?= ($_POST['privacy']??'members')
                     ==='private'?'checked':''?>>
          <span style="color:#aaa;font-size:0.9rem;
                       margin-left:6px">Private</span>
          <small style="display:block;color:#555;
                        font-size:0.78rem;
                        margin-left:20px">
            Only you and admins
          </small>
        </label>
      </div>
    </div>

    <!-- ── CERTIFICATION ──────────────────── -->
    <div class="mb-4">
      <div class="form-check">
        <input type="checkbox"
               class="form-check-input"
               id="certify" required>
        <label class="form-check-label"
               for="certify"
               style="color:#888;font-size:0.85rem">
          I certify that this information is accurate
          to the best of my knowledge.
        </label>
      </div>
    </div>

    <!-- ── SUBMIT ──────────────────────────── -->
    <div class="d-flex gap-3 mb-4">
      <button type="submit"
              class="btn btn-primary px-4">
        <i class="ti ti-user-plus me-2"></i>
        Add to My Family Tree
      </button>
      <a href="<?= SITE_URL ?>/family/tree.php"
         class="btn btn-outline-secondary px-4">
        Cancel
      </a>
    </div>

    <!-- Contributing as -->
    <div style="
      background:#0d0d1a;
      border:1px solid #1e1e3a;
      border-radius:8px;
      padding:0.75rem 1rem;
      display:flex;align-items:center;
      gap:0.75rem;font-size:0.85rem;
      color:#888;margin-bottom:3rem;
    ">
      <i class="ti ti-user-circle"
         style="font-size:1.5rem;
                color:#00d4ff"></i>
      <div>
        <strong style="color:#aaa">
          Adding to
          <?= clean($user['name']) ?>'s family tree
        </strong><br>
        This relative will be linked directly
        to your position in the tree.
      </div>
    </div>

  </form>
</div>
</main>

<script>
const relationLabels = {
    father:               'Father',
    mother:               'Mother',
    spouse:               'Spouse / Partner',
    son:                  'Son',
    daughter:             'Daughter',
    brother:              'Brother',
    sister:               'Sister',
    grandfather_paternal: 'Grandfather (Father\'s side)',
    grandmother_paternal: 'Grandmother (Father\'s side)',
    grandfather_maternal: 'Grandfather (Mother\'s side)',
    grandmother_maternal: 'Grandmother (Mother\'s side)',
    uncle:                'Uncle',
    aunt:                 'Aunt',
    nephew:               'Nephew',
    niece:                'Niece',
    cousin:               'Cousin',
    great_grandfather:    'Great Grandfather',
    great_grandmother:    'Great Grandmother',
    stepfather:           'Stepfather',
    stepmother:           'Stepmother',
    half_brother:         'Half Brother',
    half_sister:          'Half Sister',
    other:                'Other Relative',
};

function updatePreview() {
    const nameInput =
        document.getElementById('member_name');
    const relationSelect =
        document.getElementById('my_relation');
    const preview =
        document.getElementById('relation_preview');

    const name = nameInput.value.trim()
                 || 'This person';
    const rel  = relationSelect.value;
    const label = relationLabels[rel];

    if (!rel || !label) {
        preview.style.display = 'none';
        return;
    }

    preview.textContent =
        '→  ' + name + ' is your ' + label;
    preview.style.display = 'block';
}

function showFileName(input) {
    const label =
        document.getElementById('file-name');
    if (input.files && input.files[0]) {
        label.textContent =
            '✓ ' + input.files[0].name;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

if (!hasProfile($pdo, $_SESSION['user_id'])) {
    redirect(SITE_URL . '/settings/profile.php');
}

$errors   = [];
$success  = false;
$user     = currentUser();
$myMember = getUserMember($pdo, $user['id']);

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
        'uncle'             => 'Uncle',
        'aunt'              => 'Aunt',
        'nephew'            => 'Nephew',
        'niece'             => 'Niece',
        'cousin'            => 'Cousin',
        'great_grandfather' => 'Great Grandfather',
        'great_grandmother' => 'Great Grandmother',
        'stepfather'        => 'Stepfather',
        'stepmother'        => 'Stepmother',
        'half_brother'      => 'Half Brother',
        'half_sister'       => 'Half Sister',
        'other'             => 'Other Relative',
    ],
];

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

    $full_name       = trim($_POST['full_name']       ?? '');
    $preferred_name  = trim($_POST['preferred_name']  ?? '');
    $gender          = $_POST['gender']               ?? '';
    $date_of_birth   = $_POST['date_of_birth']        ?? '';
    $dob_approx      = isset($_POST['dob_approximate']) ? 1 : 0;
    $date_of_death   = $_POST['date_of_death']        ?? '';
    $dod_approx      = isset($_POST['dod_approximate']) ? 1 : 0;
    $birthplace      = trim($_POST['birthplace']      ?? '');
    $occupation      = trim($_POST['occupation']      ?? '');
    $short_bio       = trim($_POST['short_bio']       ?? '');
    $source          = trim($_POST['source_of_info']  ?? '');
    $privacy         = $_POST['privacy']              ?? 'members';
    $my_relation     = $_POST['my_relation']          ?? '';
    $is_ekpor        = $_POST['is_ekpor']             ?? 'no';
    $quarter_id      = $_POST['quarter_id']           ?? null;
    $village_of_origin = trim(
        $_POST['village_of_origin'] ?? ''
    );

    // Validation
    if (empty($full_name))
        $errors[] = 'Full name is required.';

    if (empty($my_relation))
        $errors[] = 'Please select how this person
                     is related to you.';

    if (empty($source))
        $errors[] = 'Source of information
                     is required.';

    // Quarter required only for Ekpor members
    if ($is_ekpor === 'yes' && empty($quarter_id))
        $errors[] = 'Please select the quarter
                     for this Ekpor Village member.';

    // Village required for non-Ekpor members
    if ($is_ekpor === 'no'
        && empty($village_of_origin))
        $errors[] = 'Please enter the village
                     or town this person is from.';

    if (!in_array(
        $privacy, ['public','members','private']
    )) $privacy = 'members';

    // Handle photo upload
    $photo_path = null;
    if (!empty($_FILES['photo']['name'])) {
        $upload = uploadFile(
            $_FILES['photo'], 'photo'
        );
        if (isset($upload['error'])) {
            $errors[] = 'Photo: ' . $upload['error'];
        } else {
            $photo_path = $upload['path'];
        }
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            INSERT INTO family_members (
                full_name, preferred_name,
                gender, date_of_birth,
                dob_approximate,
                date_of_death,
                dod_approximate,
                birthplace, occupation,
                short_bio, quarter_id,
                village_of_origin,
                photo, source_of_info,
                privacy, added_by
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?
            )
        ");

        $stmt->execute([
            $full_name,
            $preferred_name       ?: null,
            $gender               ?: null,
            $date_of_birth        ?: null,
            $dob_approx,
            $date_of_death        ?: null,
            $dod_approx,
            $birthplace           ?: null,
            $occupation           ?: null,
            $short_bio            ?: null,
            $is_ekpor === 'yes'
                ? $quarter_id     : null,
            $is_ekpor === 'no'
                ? $village_of_origin : null,
            $photo_path,
            $source,
            $privacy,
            $user['id'],
        ]);

        $new_member_id = $pdo->lastInsertId();

        $db_type = $type_map[$my_relation]
                   ?? 'sibling';

        saveRelationship(
            $pdo,
            $myMember['member_id'],
            $new_member_id,
            $db_type,
            $my_relation
        );

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
            . ($is_ekpor === 'no'
                ? ' from ' . $village_of_origin
                : ' — Ekpor Village')
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

        $_POST = []; // Clear form data after successful submission
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
    background:rgba(0,212,255,0.06);
    border:1px solid rgba(0,212,255,0.15);
    border-radius:10px;padding:1rem 1.25rem;
    margin-bottom:1.5rem;
    display:flex;align-items:center;gap:1rem;
  ">
    <div style="
      width:42px;height:42px;border-radius:50%;
      background:#1e1e3a;display:flex;
      align-items:center;justify-content:center;
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

  <!-- Success popup -->
  <?php if ($success): ?>
  <div style="
    position:fixed;top:0;left:0;
    width:100%;height:100%;
    background:rgba(0,0,0,0.75);
    z-index:9999;
    display:flex;align-items:center;
    justify-content:center;padding:1rem;
  " id="success-overlay">
    <div style="
      background:#111127;
      border:1px solid #1e1e3a;
      border-radius:16px;
      padding:3rem 2.5rem;
      max-width:480px;width:100%;
      text-align:center;
      box-shadow:0 24px 80px rgba(0,0,0,0.6);
    ">

      <!-- Icon -->
      <div style="
        width:72px;height:72px;
        border-radius:50%;
        background:rgba(0,212,255,0.15);
        border:2px solid #00d4ff;
        display:flex;align-items:center;
        justify-content:center;
        margin:0 auto 1.5rem;
      ">
        <i class="ti ti-check"
           style="font-size:2rem;
                  color:#00d4ff"></i>
      </div>

      <!-- Title -->
      <h3 style="
        color:#fff;font-size:1.4rem;
        font-weight:600;margin-bottom:0.75rem;
      ">
        Member Added Successfully
      </h3>

      <!-- Name -->
      <p style="
        color:#00d4ff;font-size:1.1rem;
        font-weight:500;margin-bottom:0.5rem;
      ">
        <?= clean($success_name) ?>
      </p>

      <!-- Relation -->
      <?php if (!empty($success_rel)): ?>
      <p style="
        color:#888;font-size:0.9rem;
        margin-bottom:1.5rem;
      ">
        Added as your
        <strong style="color:#aaa">
          <?= clean($success_rel) ?>
        </strong>
      </p>
      <?php else: ?>
      <p style="
        color:#888;font-size:0.9rem;
        margin-bottom:1.5rem;
      ">
        has been added to your family tree
      </p>
      <?php endif; ?>

      <!-- Divider -->
      <div style="
        border-top:1px solid #1e1e3a;
        margin-bottom:1.5rem;
      "></div>

      <!-- Actions -->
      <div style="
        display:flex;flex-direction:column;
        gap:0.75rem;
      ">
        <a href="<?= SITE_URL ?>/family/add.php"
           class="btn btn-primary w-100"
           style="padding:0.75rem">
          <i class="ti ti-user-plus me-2"></i>
          Add Another Relative
        </a>
        <a href="<?= SITE_URL ?>/family/tree.php"
           class="btn btn-outline-light w-100"
           style="padding:0.75rem">
          <i class="ti ti-git-fork me-2"></i>
          View Family Tree
        </a>
        <button onclick="
          document.getElementById(
            'success-overlay'
          ).style.display='none'"
          class="btn btn-link w-100"
          style="color:#555;font-size:0.85rem">
          Add another member on this page
        </button>
      </div>

    </div>
  </div>
  <?php endif; ?>

  <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger">
      <?= clean($e) ?>
    </div>
  <?php endforeach; ?>

  <form method="POST" action=""
        enctype="multipart/form-data">

    <!-- ── RELATIONSHIP TO ME ─────────────── -->
    <div style="
      background:#111127;border:1px solid #1e1e3a;
      border-radius:12px;padding:1.5rem;
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
                    === $val ? 'selected':''?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>

      <div id="relation_preview" style="
        margin-top:0.75rem;padding:0.75rem 1rem;
        background:rgba(0,212,255,0.06);
        border:1px solid rgba(0,212,255,0.15);
        border-radius:8px;color:#00d4ff;
        font-size:0.85rem;display:none;
      "></div>
    </div>

    <!-- ── BIOGRAPHICAL INFORMATION ───────── -->
    <div style="
      background:#111127;border:1px solid #1e1e3a;
      border-radius:12px;padding:1.5rem;
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

      <!-- Full name -->
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

      <!-- Preferred name -->
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

      <!-- Gender -->
      <div class="mb-3">
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

      <!-- ── EKPOR VILLAGE TOGGLE ───────────── -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:8px">
          Village / Origin *
        </label>

        <!-- Toggle buttons -->
        <div style="
          display:flex;gap:0;
          border:1px solid #1e1e3a;
          border-radius:8px;overflow:hidden;
          margin-bottom:1rem;width:fit-content;
        ">
          <button type="button"
                  id="btn_ekpor"
                  onclick="setOrigin('yes')"
                  style="
                    padding:0.5rem 1.25rem;
                    font-size:0.85rem;
                    border:none;cursor:pointer;
                    background:#00d4ff;color:#000;
                    font-weight:600;
                    transition:all 0.2s;
                  ">
            From Ekpor Village
          </button>
          <button type="button"
                  id="btn_other"
                  onclick="setOrigin('no')"
                  style="
                    padding:0.5rem 1.25rem;
                    font-size:0.85rem;
                    border:none;cursor:pointer;
                    background:#1e1e3a;color:#888;
                    transition:all 0.2s;
                  ">
            From Another Village
          </button>
        </div>

        <input type="hidden"
               name="is_ekpor"
               id="is_ekpor_input"
               value="<?= ($_POST['is_ekpor'] ?? 'yes') === 'yes' ? 'yes' : 'no' ?>">

        <!-- Ekpor quarter selector -->
        <div id="ekpor_section">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Quarter
          </label>
          <select name="quarter_id" class="form-select"
                  id="quarter_select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;border-radius:8px">
            <option value="">
              -- Select quarter --
            </option>
            <?php foreach ($quarters as $q): ?>
              <option value="<?= $q['quarter_id'] ?>"
                <?= ($_POST['quarter_id'] ?? '') ==
                    $q['quarter_id']
                    ? 'selected':''?>>
                <?= clean($q['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color:#555;font-size:0.78rem">
            The founding quarter this person
            belongs to in Ekpor Village
          </small>
        </div>

        <!-- Other village input -->
        <div id="other_section" style="display:none">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Village / Town / City *
          </label>
          <input type="text"
                 name="village_of_origin"
                 class="form-control"
                 style="background:#0d0d1a;
                        border:1px solid #1e1e3a;
                        color:#e0e0e0;border-radius:8px"
                 value="<?= clean(
                     $_POST['village_of_origin'] ?? ''
                 ) ?>"
                 placeholder="e.g. Mamfe, Mundemba,
                   Kumba, Buea, Douala">
          <small style="color:#555;font-size:0.78rem">
            Enter the village, town, or city
            this person is originally from
          </small>
        </div>

      </div>

      <!-- Occupation -->
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
               placeholder="e.g. Elder, Teacher,
                 Farmer, Nurse">
      </div>

      <!-- Birthplace -->
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
               placeholder="Specific village or
                 town where they were born">
      </div>

      <!-- Short bio -->
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
                  placeholder="Brief summary of their
                    life and significance to the family..."
                  ><?= clean(
                      $_POST['short_bio'] ?? ''
                  ) ?></textarea>
      </div>
    </div>

    <!-- ── LIFE TIMELINE ───────────────────── -->
    <div style="
      background:#111127;border:1px solid #1e1e3a;
      border-radius:12px;padding:1.5rem;
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
      background:#111127;border:1px solid #1e1e3a;
      border-radius:12px;padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;margin-bottom:1rem;
      ">
        Photo
        <span style="color:#555;font-weight:400;
                     text-transform:none;
                     font-size:0.8rem;margin-left:8px">
          optional
        </span>
      </div>
      <div style="
        border:2px dashed #1e1e3a;
        border-radius:10px;padding:1.5rem;
        text-align:center;color:#555;
      ">
        <i class="ti ti-camera" style="
          font-size:1.8rem;display:block;
          margin-bottom:0.5rem"></i>
        <label for="photo_upload"
               style="cursor:pointer;
                      color:#00d4ff;font-size:0.9rem">
          Click to upload a photo
        </label>
        <input type="file" name="photo"
               id="photo_upload"
               accept="image/jpeg,image/png,
                       image/webp"
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
      background:#111127;border:1px solid #1e1e3a;
      border-radius:12px;padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;margin-bottom:0.4rem;
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
      background:#111127;border:1px solid #1e1e3a;
      border-radius:12px;padding:1.5rem;
      margin-bottom:1.5rem;
    ">
      <div style="
        font-size:0.75rem;font-weight:600;
        letter-spacing:0.08em;color:#00d4ff;
        text-transform:uppercase;margin-bottom:1rem;
      ">
        Privacy
      </div>
      <div class="d-flex gap-4 flex-wrap">
        <?php foreach ([
            'public'  => [
                'Public','Anyone can view'
            ],
            'members' => [
                'Members Only',
                'Registered members only'
            ],
            'private' => [
                'Private','Only you and admins'
            ],
        ] as $val => [$lbl, $desc]): ?>
        <label style="cursor:pointer">
          <input type="radio" name="privacy"
                 value="<?= $val ?>"
                 <?= ($_POST['privacy']??'members')
                     ===$val?'checked':''?>>
          <span style="color:#aaa;font-size:0.9rem;
                       margin-left:6px">
            <?= $lbl ?>
          </span>
          <small style="display:block;color:#555;
                        font-size:0.78rem;
                        margin-left:20px">
            <?= $desc ?>
          </small>
        </label>
        <?php endforeach; ?>
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
      background:#0d0d1a;border:1px solid #1e1e3a;
      border-radius:8px;padding:0.75rem 1rem;
      display:flex;align-items:center;
      gap:0.75rem;font-size:0.85rem;
      color:#888;margin-bottom:3rem;
    ">
      <i class="ti ti-user-circle"
         style="font-size:1.5rem;color:#00d4ff">
      </i>
      <div>
        <strong style="color:#aaa">
          Adding to
          <?= clean($user['name']) ?>'s family tree
        </strong><br>
        This relative will be linked to your
        position in the tree.
      </div>
    </div>

  </form>
</div>
</main>

<script>
// ── Origin toggle ─────────────────────────────
function setOrigin(val) {
    document.getElementById(
        'is_ekpor_input'
    ).value = val;

    const ekporBtn =
        document.getElementById('btn_ekpor');
    const otherBtn =
        document.getElementById('btn_other');
    const ekporSection =
        document.getElementById('ekpor_section');
    const otherSection =
        document.getElementById('other_section');
    const quarterSelect =
        document.getElementById('quarter_select');

    if (val === 'yes') {
        ekporBtn.style.background = '#00d4ff';
        ekporBtn.style.color      = '#000';
        ekporBtn.style.fontWeight = '600';
        otherBtn.style.background = '#1e1e3a';
        otherBtn.style.color      = '#888';
        otherBtn.style.fontWeight = 'normal';
        ekporSection.style.display = 'block';
        otherSection.style.display = 'none';
    } else {
        otherBtn.style.background = '#00d4ff';
        otherBtn.style.color      = '#000';
        otherBtn.style.fontWeight = '600';
        ekporBtn.style.background = '#1e1e3a';
        ekporBtn.style.color      = '#888';
        ekporBtn.style.fontWeight = 'normal';
        otherSection.style.display = 'block';
        ekporSection.style.display = 'none';
        quarterSelect.value        = '';
    }
}

// Set initial state on page load
setOrigin(
    document.getElementById(
        'is_ekpor_input'
    ).value || 'yes'
);

// ── Relationship preview ──────────────────────
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
    const name =
        document.getElementById(
            'member_name'
        ).value.trim() || 'This person';
    const rel =
        document.getElementById(
            'my_relation'
        ).value;
    const label = relationLabels[rel];
    const preview =
        document.getElementById('relation_preview');

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
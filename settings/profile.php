<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$user       = currentUser();
$errors     = [];
$success    = false;
$hasProfile = hasProfile($pdo, $user['id']);
$myMember   = $hasProfile
    ? getUserMember($pdo, $user['id'])
    : null;

// ── Immediate family options ──────────────────
$immediate_family = [
    'father'  => 'Father',
    'mother'  => 'Mother',
    'spouse'  => 'Spouse / Partner',
    'son'     => 'Son',
    'daughter'=> 'Daughter',
    'brother' => 'Brother',
    'sister'  => 'Sister',
];

$family_type_map = [
    'father'  => 'parent',
    'mother'  => 'parent',
    'spouse'  => 'spouse',
    'son'     => 'child',
    'daughter'=> 'child',
    'brother' => 'sibling',
    'sister'  => 'sibling',
];

// ── Handle form submission ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $action = $_POST['action'] ?? 'save_profile';

    // ── SAVE MY PROFILE ───────────────────────
    if ($action === 'save_profile') {

        $full_name      = trim(
            $_POST['full_name'] ?? ''
        );
        $preferred_name = trim(
            $_POST['preferred_name'] ?? ''
        );
        $gender         = $_POST['gender'] ?? '';
        $date_of_birth  = $_POST['date_of_birth'] ?? '';
        $dob_approx     = isset(
            $_POST['dob_approximate']
        ) ? 1 : 0;
        $birthplace     = trim(
            $_POST['birthplace'] ?? ''
        );
        $occupation     = trim(
            $_POST['occupation'] ?? ''
        );
        $short_bio      = trim(
            $_POST['short_bio'] ?? ''
        );
        $quarter_id     = $_POST['quarter_id'] ?? '';

        // Validation
        if (empty($full_name))
            $errors[] = 'Full name is required.';
        if (empty($gender))
            $errors[] = 'Gender is required.';
        if (empty($quarter_id))
            $errors[] = 'Please select your quarter.';

        // Handle photo upload
        $photo_path = $myMember['photo'] ?? null;
        if (!empty($_FILES['photo']['name'])) {
            $upload = uploadFile(
                $_FILES['photo'], 'photo'
            );
            if (isset($upload['error'])) {
                $errors[] = $upload['error'];
            } else {
                $photo_path = $upload['path'];
            }
        }

        if (empty($errors)) {

            if (!$hasProfile) {
                // ── Create new family member ──
                $stmt = $pdo->prepare("
                    INSERT INTO family_members (
                        full_name,
                        preferred_name,
                        gender,
                        date_of_birth,
                        dob_approximate,
                        birthplace,
                        current_location,
                        occupation,
                        short_bio,
                        quarter_id,
                        photo,
                        source_of_info,
                        privacy,
                        verified,
                        added_by
                    ) VALUES (
                        ?,?,?,?,?,?,?,?,?,?,?,?,?,?
                    )
                ");
                $stmt->execute([
                    $full_name,
                    $preferred_name ?: null,
                    $gender         ?: null,
                    $date_of_birth  ?: null,
                    $dob_approx,
                    $birthplace     ?: null,
                    $occupation     ?: null,
                    $short_bio      ?: null,
                    $quarter_id,
                    $photo_path,
                    'Self — registered user',
                    'members',
                    1, // auto-verified
                    $user['id'],
                ]);

                $new_member_id =
                    $pdo->lastInsertId();

                // Link member to user account
                $pdo->prepare("
                    UPDATE users
                    SET member_id      = ?,
                        full_name      = ?,
                        quarter_id     = ?,
                        profile_photo  = ?
                    WHERE user_id = ?
                ")->execute([
                    $new_member_id,
                    $full_name,
                    $quarter_id,
                    $photo_path,
                    $user['id'],
                ]);

                // Update session
                $_SESSION['user_name']  = $full_name;
                $_SESSION['quarter_id'] = $quarter_id;
                $_SESSION['photo']      = $photo_path;

                // Log
                $pdo->prepare("
                    INSERT INTO audit_log
                        (user_id, action,
                         target_type,
                         target_id, detail)
                    VALUES
                        (?, 'create_profile',
                         'family_member', ?, ?)
                ")->execute([
                    $user['id'],
                    $new_member_id,
                    'User created their profile: '
                    . $full_name,
                ]);

                $success    = true;
                $hasProfile = true;
                $myMember   = getUserMember(
                    $pdo, $user['id']
                );

            } else {
                // ── Update existing member ────
                $pdo->prepare("
                    UPDATE family_members SET
                        full_name       = ?,
                        preferred_name  = ?,
                        gender          = ?,
                        date_of_birth   = ?,
                        dob_approximate = ?,
                        birthplace      = ?,
                        current_location = ?,
                        occupation      = ?,
                        short_bio       = ?,
                        quarter_id      = ?,
                        photo           = ?
                    WHERE member_id = ?
                ")->execute([
                    $full_name,
                    $preferred_name ?: null,
                    $gender         ?: null,
                    $date_of_birth  ?: null,
                    $dob_approx,
                    $birthplace     ?: null,
                    $occupation     ?: null,
                    $short_bio      ?: null,
                    $quarter_id,
                    $photo_path,
                    $myMember['member_id'],
                ]);

                // Also update users table
                $pdo->prepare("
                    UPDATE users SET
                        full_name     = ?,
                        quarter_id    = ?,
                        profile_photo = ?
                    WHERE user_id = ?
                ")->execute([
                    $full_name,
                    $quarter_id,
                    $photo_path,
                    $user['id'],
                ]);

                $_SESSION['user_name']  = $full_name;
                $_SESSION['quarter_id'] = $quarter_id;
                $_SESSION['photo']      = $photo_path;

                $success  = true;
                $myMember = getUserMember(
                    $pdo, $user['id']
                );
            }
        }
    }

    // ── ADD IMMEDIATE FAMILY MEMBER ───────────
    if ($action === 'add_family'
        && $hasProfile) {

        $fam_name     = trim(
            $_POST['fam_name'] ?? ''
        );
        $fam_relation = $_POST['fam_relation'] ?? '';
        $fam_gender   = $_POST['fam_gender']   ?? '';
        $fam_dob      = $_POST['fam_dob']      ?? '';
        $fam_quarter  = $_POST['fam_quarter']  ?? '';

        if (empty($fam_name))
            $errors[] = 'Family member name is required.';
        if (empty($fam_relation))
            $errors[] = 'Please select the relationship.';
        if (empty($fam_quarter))
            $errors[] = 'Please select their quarter.';

        if (empty($errors)) {

            // Insert the family member
            $stmt = $pdo->prepare("
                INSERT INTO family_members (
                    full_name,
                    gender,
                    date_of_birth,
                    quarter_id,
                    source_of_info,
                    privacy,
                    verified,
                    added_by
                ) VALUES (?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $fam_name,
                $fam_gender  ?: null,
                $fam_dob     ?: null,
                $fam_quarter,
                'Added via profile page by '
                . $user['name'],
                'members',
                0,
                $user['id'],
            ]);

            $fam_member_id =
                $pdo->lastInsertId();

            // Save relationship to my node
            $db_type =
                $family_type_map[$fam_relation]
                ?? 'sibling';

            saveRelationship(
                $pdo,
                $myMember['member_id'],
                $fam_member_id,
                $db_type
            );

            // Log
            $pdo->prepare("
                INSERT INTO audit_log
                    (user_id, action,
                     target_type,
                     target_id, detail)
                VALUES
                    (?, 'add_immediate_family',
                     'family_member', ?, ?)
            ")->execute([
                $user['id'],
                $fam_member_id,
                'Added ' . $fam_relation
                . ': ' . $fam_name,
            ]);

            $success  = true;
            $myMember = getUserMember(
                $pdo, $user['id']
            );
        }
    }
}

$quarters    = getAllQuarters($pdo);
$connections = ($hasProfile && $myMember)
    ? getConnections(
        $pdo,
        $myMember['member_id']
    )
    : [];
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem">
<div class="container" style="max-width:800px">

  <!-- Page header -->
  <div class="mb-4">
    <a href="<?= SITE_URL ?>/dashboard.php"
       style="color:#888;font-size:0.9rem">
      ← Back to Dashboard
    </a>
    <h2 style="color:#fff;margin-top:0.75rem">
      My Profile
    </h2>
    <p style="color:#888">
      <?= $hasProfile
          ? 'Edit your information and manage
             your family connections.'
          : 'Complete your profile to join the
             Ekpor Village family tree.' ?>
    </p>
  </div>

  <!-- Progress indicator for new users -->
  <?php if (!$hasProfile): ?>
  <div style="
    background:rgba(255,159,26,0.08);
    border:1px solid rgba(255,159,26,0.25);
    border-radius:10px;
    padding:1rem 1.25rem;
    margin-bottom:1.5rem;
  ">
    <div style="
      display:flex;gap:1rem;
      flex-wrap:wrap;align-items:center;
    ">
      <div style="
        display:flex;align-items:center;gap:8px;
        font-size:0.85rem;
      ">
        <div style="
          width:24px;height:24px;
          border-radius:50%;
          background:#ff9f1a;color:#000;
          display:flex;align-items:center;
          justify-content:center;
          font-weight:600;font-size:0.8rem;
        ">1</div>
        <span style="color:#ff9f1a;
                     font-weight:500">
          Complete your profile
        </span>
      </div>
      <div style="color:#333">→</div>
      <div style="
        display:flex;align-items:center;gap:8px;
        font-size:0.85rem;
      ">
        <div style="
          width:24px;height:24px;
          border-radius:50%;
          background:#1e1e3a;color:#555;
          display:flex;align-items:center;
          justify-content:center;
          font-weight:600;font-size:0.8rem;
        ">2</div>
        <span style="color:#555">
          Add immediate family
        </span>
      </div>
      <div style="color:#333">→</div>
      <div style="
        display:flex;align-items:center;gap:8px;
        font-size:0.85rem;
      ">
        <div style="
          width:24px;height:24px;
          border-radius:50%;
          background:#1e1e3a;color:#555;
          display:flex;align-items:center;
          justify-content:center;
          font-weight:600;font-size:0.8rem;
        ">3</div>
        <span style="color:#555">
          Add ancestors & relatives
        </span>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Success message -->
  <?php if ($success): ?>
  <div class="alert alert-success mb-4">
    ✓ Profile updated successfully.
  </div>
  <?php endif; ?>

  <!-- Errors -->
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger">
    <?= clean($e) ?>
  </div>
  <?php endforeach; ?>

  <!-- ── SECTION 1: MY INFORMATION ──────────── -->
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
      My Information
    </div>

    <form method="POST" action=""
          enctype="multipart/form-data">
    <?= csrfField() ?>
      <input type="hidden"
             name="action"
             value="save_profile">

      <!-- Photo -->
      <div style="
        display:flex;align-items:center;
        gap:1.5rem;margin-bottom:1.5rem;
        flex-wrap:wrap;
      ">
        <div style="
          width:80px;height:80px;
          border-radius:50%;
          background:#1e1e3a;
          display:flex;align-items:center;
          justify-content:center;
          font-size:2rem;font-weight:600;
          color:#00d4ff;overflow:hidden;
          flex-shrink:0;
        " id="photo_preview">
          <?php if ($myMember
                    && $myMember['photo']): ?>
            <img src="<?= SITE_URL ?>
                /<?= $myMember['photo'] ?>"
                 style="width:100%;height:100%;
                        object-fit:cover"
                 alt="Profile photo">
          <?php else: ?>
            <?= strtoupper(
                substr($user['name'], 0, 1)
            ) ?>
          <?php endif; ?>
        </div>
        <div>
          <label for="photo_upload"
                 class="btn btn-outline-light
                         btn-sm"
                 style="cursor:pointer">
            <i class="ti ti-camera me-1"></i>
            <?= $myMember && $myMember['photo']
                ? 'Change photo'
                : 'Upload photo' ?>
          </label>
          <input type="file" name="photo"
                 id="photo_upload"
                 accept="image/jpeg,image/png,
                         image/webp"
                 style="display:none"
                 onchange="previewPhoto(this)">
          <p style="color:#555;font-size:0.78rem;
                    margin-top:0.4rem;margin:0">
            PNG, JPG or WebP — max 10MB
          </p>
        </div>
      </div>

      <!-- Full name -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;
                      margin-bottom:4px">
          Full Name *
        </label>
        <input type="text" name="full_name"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;
                      border-radius:8px"
               value="<?= clean(
                   $_POST['full_name']
                   ?? $myMember['full_name']
                   ?? $user['name']
                   ?? ''
               ) ?>"
               required>
      </div>

      <!-- Preferred name -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;
                      margin-bottom:4px">
          Preferred / Known As
          <span style="color:#555">(optional)</span>
        </label>
        <input type="text" name="preferred_name"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;
                      border-radius:8px"
               value="<?= clean(
                   $_POST['preferred_name']
                   ?? $myMember['preferred_name']
                   ?? ''
               ) ?>"
               placeholder="Nickname or known name">
      </div>

      <!-- Gender and Quarter -->
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;
                        margin-bottom:4px">
            Gender
          </label>
          <select name="gender" class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;
                         border-radius:8px"
                  required>
            <option value="">-- Select gender *</option>
            <?php
            $cur_gender =
                $_POST['gender']
                ?? $myMember['gender']
                ?? '';
            foreach ([
                'male'   => 'Male',
                'female' => 'Female',
                'other'  => 'Other / Prefer not to say',
            ] as $val => $label): ?>
              <option value="<?= $val ?>"
                <?= $cur_gender === $val
                    ? 'selected':'' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;
                        margin-bottom:4px">
            My Quarter *
          </label>
          <select name="quarter_id"
                  class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;
                         border-radius:8px"
                  required
                  onchange="showQuarterInfo(this)">
            <option value="">
              -- Select your quarter --
            </option>
            <?php
            $cur_q =
                $_POST['quarter_id']
                ?? $myMember['quarter_id']
                ?? $user['quarter_id']
                ?? '';
            foreach ($quarters as $q):
              $is_unknown = stripos(
                  $q['name'], 'Unknown') !== false;
            ?>
              <option value="<?= $q['quarter_id'] ?>"
                <?= $cur_q == $q['quarter_id']
                    ? 'selected':'' ?>
                data-unknown="<?= $is_unknown
                    ? '1' : '0' ?>">
                <?= clean($q['name']) ?>
                <?= !$is_unknown
                    ? '— founded by '
                      . clean($q['founded_by'])
                    : '' ?>
              </option>
            <?php endforeach; ?>
          </select>

          <!-- Quarter info panel -->
          <div id="quarter_info"
               style="display:none;
                      margin-top:0.6rem;
                      padding:0.75rem 1rem;
                      border-radius:8px;
                      font-size:0.82rem;
                      line-height:1.5">
          </div>

          <!-- Quarter guide -->
          <div style="
            margin-top:0.6rem;
            font-size:0.78rem;color:#555;
          ">
            Not sure of your quarter?
            <a href="<?= SITE_URL ?>/heritage/history.php"
               target="_blank"
               style="color:#00d4ff">
              Learn about the 5 quarters
            </a>
            or select
            <strong style="color:#888">
              Unknown Quarter
            </strong>
            — we can help identify yours
            through your family connections.
          </div>
          </select>
        </div>
      </div>

      <!-- Date of birth -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;
                      margin-bottom:4px">
          Date of Birth
        </label>
        <input type="date" name="date_of_birth"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;
                      border-radius:8px"
               value="<?= clean(
                   $_POST['date_of_birth']
                   ?? $myMember['date_of_birth']
                   ?? ''
               ) ?>">
        <div class="form-check mt-1">
          <input type="checkbox"
                 name="dob_approximate"
                 class="form-check-input"
                 id="dob_approx"
                 <?= isset($_POST['dob_approximate'])
                     || ($myMember['dob_approximate']
                         ?? false)
                     ? 'checked':'' ?>>
          <label class="form-check-label"
                 for="dob_approx"
                 style="color:#555;
                        font-size:0.8rem">
            Approximate date
          </label>
        </div>
      </div>

      <!-- Birthplace -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;
                      margin-bottom:4px">
          Birthplace
        </label>
        <input type="text" name="birthplace"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;
                      border-radius:8px"
               value="<?= clean(
                   $_POST['birthplace']
                   ?? $myMember['birthplace']
                   ?? ''
               ) ?>"
               placeholder="e.g. Ekpor Village,
                             Manyu Division">
      </div>

      <!-- Current Location -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;
                      margin-bottom:4px">
          Current Location
          <span style="color:#555;font-size:0.8rem">
            (where you live now)
          </span>
        </label>
        <input type="text" name="current_location"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;
                      border-radius:8px"
               value="<?= clean(
                   $_POST['current_location']
                   ?? $myMember['current_location']
                   ?? ''
               ) ?>"
               placeholder="e.g. Yaoundé, Cameroon
                             or Douala, or London">
      </div>

      <!-- Occupation -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;
                      margin-bottom:4px">
          Occupation
        </label>
        <input type="text" name="occupation"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;
                      border-radius:8px"
               value="<?= clean(
                   $_POST['occupation']
                   ?? $myMember['occupation']
                   ?? ''
               ) ?>"
               placeholder="e.g. Student, Teacher,
                             Engineer">
      </div>

      <!-- Short bio -->
      <div class="mb-4">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;
                      margin-bottom:4px">
          About Me
        </label>
        <textarea name="short_bio"
                  class="form-control"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;
                         border-radius:8px;
                         min-height:90px"
                  placeholder="A brief description
                    about yourself for the heritage
                    record..."
                  ><?= clean(
                      $_POST['short_bio']
                      ?? $myMember['short_bio']
                      ?? ''
                  ) ?></textarea>
      </div>

      <button type="submit"
              class="btn btn-primary">
        <i class="ti ti-device-floppy me-2"></i>
        <?= $hasProfile
            ? 'Save Changes'
            : 'Create My Profile' ?>
      </button>

    </form>
  </div>

  <!-- ── SECTION 2: IMMEDIATE FAMILY ────────── -->
  <?php if ($hasProfile): ?>
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
      Immediate Family
    </div>
    <p style="color:#666;font-size:0.82rem;
              margin-bottom:1.25rem">
      Add your closest family members here.
      These build the core of your family tree.
      You can add up to 4 immediate connections.
    </p>

    <!-- Existing connections -->
    <?php if (!empty($connections)): ?>
    <div style="margin-bottom:1.25rem">
      <div style="color:#888;font-size:0.8rem;
                  margin-bottom:0.5rem">
        Current connections
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($connections as $c): ?>
        <div style="
          background:#0d0d1a;
          border:1px solid #1e1e3a;
          border-radius:8px;
          padding:0.5rem 0.9rem;
          font-size:0.85rem;
        ">
          <span style="color:#00d4ff;
                       text-transform:capitalize;
                       font-size:0.75rem;
                       display:block">
            <?= clean($c['relation_type']) ?>
          </span>
          <span style="color:#e0e0e0">
            <?= clean($c['full_name']) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Only show form if under 4 connections -->
    <?php if (count($connections) < 4): ?>
    <form method="POST" action="">
      <input type="hidden"
             name="action"
             value="add_family">

      <div class="row g-3 mb-3">

        <!-- Name -->
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;
                        margin-bottom:4px">
            Full Name *
          </label>
          <input type="text" name="fam_name"
                 class="form-control"
                 style="background:#0d0d1a;
                        border:1px solid #1e1e3a;
                        color:#e0e0e0;
                        border-radius:8px"
                 placeholder="Their full name"
                 required>
        </div>

        <!-- Relationship -->
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;
                        margin-bottom:4px">
            Their Relationship to Me *
          </label>
          <select name="fam_relation"
                  class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;
                         border-radius:8px"
                  required>
            <option value="">
              -- Select --
            </option>
            <?php foreach (
                $immediate_family as $val => $label
            ): ?>
              <option value="<?= $val ?>">
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Gender -->
        <div class="col-md-4">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;
                        margin-bottom:4px">
            Gender
          </label>
          <select name="fam_gender"
                  class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;
                         border-radius:8px">
            <option value="">-- Select --</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>

        <!-- Date of birth -->
        <div class="col-md-4">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;
                        margin-bottom:4px">
            Date of Birth
          </label>
          <input type="date" name="fam_dob"
                 class="form-control"
                 style="background:#0d0d1a;
                        border:1px solid #1e1e3a;
                        color:#e0e0e0;
                        border-radius:8px">
        </div>

        <!-- Quarter -->
        <div class="col-md-4">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;
                        margin-bottom:4px">
            Their Quarter *
          </label>
          <select name="fam_quarter"
                  class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;
                         border-radius:8px"
                  required>
            <option value="">-- Select --</option>
            <?php foreach ($quarters as $q): ?>
              <option value="<?= $q['quarter_id'] ?>">
                <?= clean($q['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>

      <button type="submit"
              class="btn btn-outline-light btn-sm">
        <i class="ti ti-plus me-1"></i>
        Add Family Member
        <span style="color:#555;font-size:0.8rem;
                     margin-left:4px">
          (<?= 4 - count($connections) ?> remaining)
        </span>
      </button>
    </form>

    <?php else: ?>
    <div style="color:#555;font-size:0.85rem;
                font-style:italic">
      You have added 4 immediate family members.
      To add more relatives use the
      <a href="<?= SITE_URL ?>/family/add.php"
         style="color:#00d4ff">
        Add Family Member
      </a> page.
    </div>
    <?php endif; ?>

  </div>

  <!-- ── NEXT STEPS ──────────────────────────── -->
  <div style="
    background:rgba(0,212,255,0.04);
    border:1px solid rgba(0,212,255,0.12);
    border-radius:12px;
    padding:1.25rem 1.5rem;
    margin-bottom:2rem;
  ">
    <div style="color:#aaa;font-size:0.85rem;
                margin-bottom:0.75rem;
                font-weight:500">
      What to do next
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= SITE_URL ?>/family/add.php"
         class="btn btn-primary btn-sm">
        <i class="ti ti-user-plus me-1"></i>
        Add ancestors & relatives
      </a>
      <a href="<?= SITE_URL ?>/family/tree.php"
         class="btn btn-outline-light btn-sm">
        <i class="ti ti-git-fork me-1"></i>
        View my family tree
      </a>
      <a href="<?= SITE_URL ?>/heritage/contribute.php"
         class="btn btn-outline-light btn-sm">
        <i class="ti ti-plus me-1"></i>
        Contribute a heritage record
      </a>
    </div>
  </div>

  <?php endif; ?>

</div>
</main>

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview =
                document.getElementById(
                    'photo_preview'
                );
            preview.innerHTML =
                '<img src="' + e.target.result
                + '" style="width:100%;height:100%;'
                + 'object-fit:cover">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Quarter info panel ────────────────────────
const quarterDescriptions = {
<?php foreach ($quarters as $q):
  $is_unknown = stripos($q['name'], 'Unknown') !== false;
?>
  "<?= $q['quarter_id'] ?>": {
    name: "<?= clean($q['name']) ?>",
    founder: "<?= clean($q['founded_by'] ?? '') ?>",
    desc: "<?= addslashes(trim(
        preg_replace('/\s+/', ' ', $q['description'] ?? '')
    )) ?>",
    unknown: <?= $is_unknown ? 'true' : 'false' ?>,
  },
<?php endforeach; ?>
};

function showQuarterInfo(sel) {
  const panel = document.getElementById('quarter_info');
  const val   = sel.value;
  if (!val || !quarterDescriptions[val]) {
    panel.style.display = 'none';
    return;
  }
  const q = quarterDescriptions[val];
  if (q.unknown) {
    panel.style.display  = 'block';
    panel.style.background = 'rgba(255,159,26,0.07)';
    panel.style.border   = '1px solid rgba(255,159,26,0.2)';
    panel.style.color    = '#aaa';
    panel.innerHTML = `
      <i class="ti ti-help-circle"
         style="color:#ff9f1a;margin-right:6px"></i>
      <strong style="color:#ff9f1a">
        No problem — we will help you find your quarter.
      </strong><br>
      Once you join the family tree, your connections
      to other members will help determine which quarter
      your family belongs to. An elder or administrator
      can confirm and update your quarter at any time.
    `;
  } else {
    panel.style.display  = 'block';
    panel.style.background = 'rgba(0,212,255,0.04)';
    panel.style.border   = '1px solid rgba(0,212,255,0.12)';
    panel.style.color    = '#888';
    panel.innerHTML = `
      <strong style="color:#00d4ff">${q.name}</strong>
      ${q.founder ? `<span style="color:#555">
        — founded by ${q.founder}</span>` : ''}
      <br>${q.desc}
    `;
  }
}
</script>

<?php require_once '../includes/footer.php'; ?>
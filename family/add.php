<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$errors  = [];
$success = false;
$user    = currentUser();

// Load existing members for the relationship dropdown
$existing_members = $pdo->query("
    SELECT
        fm.member_id,
        fm.full_name,
        q.name AS quarter_name
    FROM family_members fm
    LEFT JOIN quarters q
        ON fm.quarter_id = q.quarter_id
    ORDER BY fm.full_name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Collect form data ─────────────────────
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

    // Relationship fields
    $related_to     = $_POST['related_to']          ?? '';
    $relation_type  = $_POST['relation_type']       ?? '';

    // ── Validation ────────────────────────────
    if (empty($full_name))
        $errors[] = 'Full name is required.';

    if (empty($quarter_id))
        $errors[] = 'Please select a quarter.';

    if (empty($source))
        $errors[] = 'Source of information is required.';

    // Validate relationship if provided
    if (!empty($related_to) && empty($relation_type))
        $errors[] = 'Please select the relationship type.';

    if (!empty($relation_type) && empty($related_to))
        $errors[] = 'Please select who this person is related to.';

    if (!in_array($privacy, ['public','members','private']))
        $privacy = 'members';

    // ── Handle photo upload ───────────────────
    $photo_path = null;
    if (!empty($_FILES['photo']['name'])) {
        $upload = uploadFile($_FILES['photo'], 'photo');
        if (isset($upload['error'])) {
            $errors[] = 'Photo: ' . $upload['error'];
        } else {
            $photo_path = $upload['path'];
        }
    }

    // ── Insert member into database ───────────
    if (empty($errors)) {

        $stmt = $pdo->prepare("
            INSERT INTO family_members (
                full_name,
                preferred_name,
                gender,
                date_of_birth,
                dob_approximate,
                date_of_death,
                dod_approximate,
                birthplace,
                occupation,
                short_bio,
                quarter_id,
                photo,
                source_of_info,
                privacy,
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

        // ── Save relationship if provided ─────
        if (!empty($related_to)
            && !empty($relation_type)) {

            // Determine the correct direction
            // based on relationship type
            //
            // If I say "John is the FATHER of Mary":
            //   member_id_1 = John (related_to)
            //   member_id_2 = Mary (new member)
            //   type = parent
            //
            // If I say "Mary is the CHILD of John":
            //   member_id_1 = Mary (new member)
            //   member_id_2 = John (related_to)
            //   type = child
            //
            // For spouse and sibling direction
            // does not matter

            $m1 = $new_member_id;
            $m2 = $related_to;

            // If the new member IS the child
            // then the related person IS the parent
            // so we also save the reverse
            $reverse_type = [
                'parent'  => 'child',
                'child'   => 'parent',
                'spouse'  => 'spouse',
                'sibling' => 'sibling',
            ];

            // Save the stated relationship
            $pdo->prepare("
                INSERT INTO relationships
                    (member_id_1,
                     member_id_2,
                     type)
                VALUES (?, ?, ?)
            ")->execute([$m1, $m2, $relation_type]);

            // Save the reverse relationship
            // so the tree works both ways
            $pdo->prepare("
                INSERT INTO relationships
                    (member_id_1,
                     member_id_2,
                     type)
            ")->execute([
                $m2,
                $m1,
                $reverse_type[$relation_type]
            ]);
        }

        // ── Log the action ────────────────────
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
            'Added: ' . $full_name .
            (!empty($related_to)
                ? ' | Related to member #'
                  . $related_to
                  . ' as ' . $relation_type
                : '')
        ]);

        $success       = true;
        $success_name  = $full_name;
        $success_id    = $new_member_id;
    }
}

$quarters = getAllQuarters($pdo);
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding: 2rem;">
<div class="container" style="max-width: 760px;">

  <!-- Page header -->
  <div class="mb-4">
    <a href="<?= SITE_URL ?>/family/tree.php"
       style="color:#888; font-size:0.9rem">
      ← Back to Family Tree
    </a>
    <h2 style="color:#fff; margin-top:0.75rem">
      Add Family Member
    </h2>
    <p style="color:#888">
      Preserve the memory of a relative.
      Fields marked * are required.
    </p>
  </div>

  <!-- Guidelines banner -->
  <div style="
    background: rgba(0,212,255,0.08);
    border: 1px solid rgba(0,212,255,0.2);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    font-size: 0.88rem;
    color: #aaa;
  ">
    <strong style="color:#00d4ff">
      Submission Guidelines
    </strong><br>
    All records are reviewed before being marked
    as verified. Please ensure details match your
    official documents or oral sources.
  </div>

  <!-- Success message -->
  <?php if ($success): ?>
    <div class="alert alert-success mb-4">
      ✓ <strong><?= clean($success_name) ?></strong>
      has been added to the family tree.
      <br>
      <a href="<?= SITE_URL ?>/family/add.php"
         style="color:#00d4ff">
        Add another member
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

  <form method="POST"
        action=""
        enctype="multipart/form-data">

    <!-- ── BIOGRAPHICAL INFORMATION ───────── -->
    <div style="
      background: #111127;
      border: 1px solid #1e1e3a;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    ">
      <div style="
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        color: #00d4ff;
        text-transform: uppercase;
        margin-bottom: 1.25rem;
      ">
        Biographical Information
      </div>

      <!-- Full name -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Full Legal Name *
        </label>
        <input type="text"
               name="full_name"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['full_name'] ?? ''
               ) ?>"
               placeholder="e.g. Enow Mbong"
               required>
      </div>

      <!-- Preferred name -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Preferred / Known As
          <span style="color:#555">(optional)</span>
        </label>
        <input type="text"
               name="preferred_name"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['preferred_name'] ?? ''
               ) ?>"
               placeholder="Nickname or known name">
      </div>

      <!-- Gender and Quarter -->
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Gender
          </label>
          <select name="gender" class="form-select"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;
                         border-radius:8px">
            <option value="">-- Select --</option>
            <option value="male" <?=
                ($_POST['gender'] ?? '') === 'male'
                ? 'selected':'' ?>>Male</option>
            <option value="female" <?=
                ($_POST['gender'] ?? '') === 'female'
                ? 'selected':'' ?>>Female</option>
            <option value="other" <?=
                ($_POST['gender'] ?? '') === 'other'
                ? 'selected':'' ?>>Other</option>
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
                <?= ($_POST['quarter_id'] ?? '') ==
                    $q['quarter_id']
                    ? 'selected':'' ?>>
                <?= clean($q['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small style="color:#555;font-size:0.78rem">
            The founding quarter this person belongs to
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
        <input type="text"
               name="occupation"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['occupation'] ?? ''
               ) ?>"
               placeholder="e.g. Teacher, Farmer, Elder">
      </div>

      <!-- Birthplace -->
      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Birthplace
          <span style="color:#555">(optional)</span>
        </label>
        <input type="text"
               name="birthplace"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['birthplace'] ?? ''
               ) ?>"
               placeholder="e.g. Ekpor Village,
                             Manyu Division">
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
                  placeholder="Brief summary of their life,
                    achievements, and significance..."
                  ><?= clean(
                      $_POST['short_bio'] ?? ''
                  ) ?></textarea>
      </div>
    </div>

    <!-- ── FAMILY CONNECTION ───────────────── -->
    <div style="
      background: #111127;
      border: 1px solid #1e1e3a;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    ">
      <div style="
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        color: #00d4ff;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
      ">
        Family Connection
      </div>

      <p style="color:#666;font-size:0.82rem;
                margin-bottom:1rem">
        Link this person to an existing member
        of the family tree. This is how the tree
        draws the connection between people.
        Leave blank if this is the first member
        or if the connection is unknown.
      </p>

      <?php if (empty($existing_members)): ?>
        <div style="color:#555;font-size:0.85rem;
                    font-style:italic">
          No existing members yet — this will be
          the first person in the tree.
        </div>
      <?php else: ?>

      <div class="row g-3">

        <!-- Who are they related to -->
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Related to
          </label>
          <select name="related_to"
                  class="form-select"
                  id="related_to"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;border-radius:8px"
                  onchange="updateRelationLabel()">
            <option value="">
              -- Select existing member --
            </option>
            <?php foreach (
                $existing_members as $m
            ): ?>
              <option
                value="<?= $m['member_id'] ?>"
                data-name="<?= clean(
                    $m['full_name']
                ) ?>"
                <?= ($_POST['related_to'] ?? '') ==
                    $m['member_id']
                    ? 'selected':'' ?>>
                <?= clean($m['full_name']) ?>
                (<?= clean($m['quarter_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Relationship type -->
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px"
                 id="relation_label">
            Relationship Type
          </label>
          <select name="relation_type"
                  class="form-select"
                  id="relation_type"
                  style="background:#0d0d1a;
                         border:1px solid #1e1e3a;
                         color:#e0e0e0;border-radius:8px"
                  onchange="updateRelationLabel()">
            <option value="">
              -- Select relationship --
            </option>
            <option value="parent" <?=
                ($_POST['relation_type'] ?? '')
                === 'parent' ? 'selected':'' ?>>
              Parent
              (this person IS the parent of
               the selected member)
            </option>
            <option value="child" <?=
                ($_POST['relation_type'] ?? '')
                === 'child' ? 'selected':'' ?>>
              Child
              (this person IS the child of
               the selected member)
            </option>
            <option value="spouse" <?=
                ($_POST['relation_type'] ?? '')
                === 'spouse' ? 'selected':'' ?>>
              Spouse / Partner
            </option>
            <option value="sibling" <?=
                ($_POST['relation_type'] ?? '')
                === 'sibling' ? 'selected':'' ?>>
              Sibling
              (brother or sister)
            </option>
          </select>
        </div>

      </div>

      <!-- Plain English preview of the relationship -->
      <div id="relation_preview"
           style="
             margin-top: 0.75rem;
             padding: 0.75rem 1rem;
             background: rgba(0,212,255,0.06);
             border: 1px solid rgba(0,212,255,0.15);
             border-radius: 8px;
             color: #00d4ff;
             font-size: 0.85rem;
             display: none;
           ">
      </div>

      <?php endif; ?>
    </div>

    <!-- ── LIFE TIMELINE ───────────────────── -->
    <div style="
      background: #111127;
      border: 1px solid #1e1e3a;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    ">
      <div style="
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        color: #00d4ff;
        text-transform: uppercase;
        margin-bottom: 1.25rem;
      ">
        Life Timeline
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label style="color:#aaa;font-size:0.85rem;
                        display:block;margin-bottom:4px">
            Date of Birth
          </label>
          <input type="date"
                 name="date_of_birth"
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
                       ? 'checked':'' ?>>
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
          <input type="date"
                 name="date_of_death"
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
                       ? 'checked':'' ?>>
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
      background: #111127;
      border: 1px solid #1e1e3a;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    ">
      <div style="
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        color: #00d4ff;
        text-transform: uppercase;
        margin-bottom: 1rem;
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
        border: 2px dashed #1e1e3a;
        border-radius: 10px;
        padding: 1.5rem;
        text-align: center;
        color: #555;
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
        <input type="file"
               name="photo"
               id="photo_upload"
               accept="image/jpeg,image/png,image/webp"
               style="display:none"
               onchange="showFileName(this)">
        <p style="margin-top:0.4rem;
                  font-size:0.78rem">
          PNG, JPG or WebP — max 10MB
        </p>
        <p id="file-name"
           style="color:#00d4ff;font-size:0.85rem">
        </p>
      </div>
    </div>

    <!-- ── PROVENANCE ──────────────────────── -->
    <div style="
      background: #111127;
      border: 1px solid #1e1e3a;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    ">
      <div style="
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        color: #00d4ff;
        text-transform: uppercase;
        margin-bottom: 0.4rem;
      ">
        Provenance
      </div>
      <p style="color:#666;font-size:0.82rem;
                margin-bottom:1rem">
        Where did this information come from?
        This helps verify the record.
      </p>
      <label style="color:#aaa;font-size:0.85rem;
                    display:block;margin-bottom:4px">
        Source of Information *
      </label>
      <input type="text"
             name="source_of_info"
             class="form-control"
             style="background:#0d0d1a;
                    border:1px solid #1e1e3a;
                    color:#e0e0e0;border-radius:8px"
             value="<?= clean(
                 $_POST['source_of_info'] ?? ''
             ) ?>"
             placeholder="e.g. Family Bible, Oral account
               from elder, Personal recall, Census record"
             required>
    </div>

    <!-- ── PRIVACY ─────────────────────────── -->
    <div style="
      background: #111127;
      border: 1px solid #1e1e3a;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    ">
      <div style="
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        color: #00d4ff;
        text-transform: uppercase;
        margin-bottom: 1rem;
      ">
        Privacy
      </div>
      <div class="d-flex gap-4 flex-wrap">
        <label style="cursor:pointer">
          <input type="radio" name="privacy"
                 value="public"
                 <?= ($_POST['privacy'] ?? 'members')
                     === 'public'
                     ? 'checked':'' ?>>
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
                 <?= ($_POST['privacy'] ?? 'members')
                     === 'members'
                     ? 'checked':'' ?>>
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
                 <?= ($_POST['privacy'] ?? 'members')
                     === 'private'
                     ? 'checked':'' ?>>
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
          to the best of my knowledge and is provided
          with respect to the deceased and
          their living family.
        </label>
      </div>
    </div>

    <!-- ── SUBMIT ──────────────────────────── -->
    <div class="d-flex gap-3 mb-4">
      <button type="submit"
              class="btn btn-primary px-4">
        <i class="ti ti-user-plus me-2"></i>
        Add to Family Tree
      </button>
      <a href="<?= SITE_URL ?>/family/tree.php"
         class="btn btn-outline-secondary px-4">
        Cancel
      </a>
    </div>

    <!-- Contributing as -->
    <div style="
      background: #0d0d1a;
      border: 1px solid #1e1e3a;
      border-radius: 8px;
      padding: 0.75rem 1rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.85rem;
      color: #888;
      margin-bottom: 3rem;
    ">
      <i class="ti ti-user-circle"
         style="font-size:1.5rem;color:#00d4ff"></i>
      <div>
        <strong style="color:#aaa">
          Contributing as <?= clean($user['name']) ?>
        </strong><br>
        Your account will be linked to this record.
      </div>
    </div>

  </form>
</div>
</main>

<script>
function showFileName(input) {
    const label = document.getElementById('file-name');
    if (input.files && input.files[0]) {
        label.textContent = '✓ ' + input.files[0].name;
    }
}

function updateRelationLabel() {
    const relatedSelect =
        document.getElementById('related_to');
    const typeSelect =
        document.getElementById('relation_type');
    const preview =
        document.getElementById('relation_preview');

    const selectedOption =
        relatedSelect.options[relatedSelect.selectedIndex];
    const relatedName =
        selectedOption
            ? selectedOption.getAttribute('data-name')
            : '';
    const relType = typeSelect.value;

    // Get the new member's name from the input
    const newName =
        document.querySelector(
            'input[name="full_name"]'
        ).value.trim() || 'This person';

    if (!relatedName || !relType) {
        preview.style.display = 'none';
        return;
    }

    // Build a plain English sentence
    const sentences = {
        parent:  `${newName} is a parent of ${relatedName}`,
        child:   `${newName} is a child of ${relatedName}`,
        spouse:  `${newName} is the spouse / partner of ${relatedName}`,
        sibling: `${newName} is a sibling of ${relatedName}`,
    };

    const sentence = sentences[relType];
    if (sentence) {
        preview.textContent = '→  ' + sentence;
        preview.style.display = 'block';
    }
}

// Update preview when name changes too
document.querySelector(
    'input[name="full_name"]'
).addEventListener('input', updateRelationLabel);
</script>

<?php require_once '../includes/footer.php'; ?>
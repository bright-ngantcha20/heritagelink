<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

if (!hasProfile($pdo, $_SESSION['user_id'])) {
    redirect(SITE_URL . '/settings/profile.php');
}

$user     = currentUser();
$errors   = [];
$success  = false;

// Load all family members
$all_members = $pdo->query("
    SELECT
        fm.member_id,
        fm.full_name,
        fm.preferred_name,
        COALESCE(q.name,
            fm.village_of_origin, 'Unknown')
            AS quarter
    FROM family_members fm
    LEFT JOIN quarters q
        ON fm.quarter_id = q.quarter_id
    ORDER BY fm.full_name ASC
")->fetchAll();

$relation_options = [
    'parent'  => 'Parent of',
    'child'   => 'Child of',
    'spouse'  => 'Spouse of',
    'sibling' => 'Sibling of',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_a = (int)($_POST['member_a'] ?? 0);
    $member_b = (int)($_POST['member_b'] ?? 0);
    $rel_type = $_POST['rel_type'] ?? '';
    $rel_label= trim($_POST['rel_label'] ?? '');

    if (!$member_a)
        $errors[] = 'Please select the first member.';
    if (!$member_b)
        $errors[] = 'Please select the second member.';
    if ($member_a === $member_b)
        $errors[] = 'Cannot connect a member to themselves.';
    if (empty($rel_type))
        $errors[] = 'Please select the relationship type.';

    // Check if relationship already exists
    if (empty($errors)) {
        $check = $pdo->prepare("
            SELECT relationship_id
            FROM relationships
            WHERE (member_id_1 = ? AND member_id_2 = ?)
               OR (member_id_1 = ? AND member_id_2 = ?)
        ");
        $check->execute([
            $member_a, $member_b,
            $member_b, $member_a
        ]);
        if ($check->fetch()) {
            $errors[] = 'These two members are
                already connected.';
        }
    }

    if (empty($errors)) {
        saveRelationship(
            $pdo,
            $member_a,
            $member_b,
            $rel_type,
            $rel_label ?: null
        );

        $success = true;
        $_POST   = [];
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem">
<div class="container" style="max-width:640px">

  <div class="mb-4">
    <a href="<?= SITE_URL ?>/family/tree.php"
       style="color:#888;font-size:0.9rem">
      ← Back to Family Tree
    </a>
    <h2 style="color:#fff;margin-top:0.75rem">
      Connect Two Members
    </h2>
    <p style="color:#888">
      Link two existing family members
      who are related but not yet connected
      in the tree.
    </p>
  </div>

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
      max-width:440px;width:100%;
      text-align:center;
    ">
      <div style="
        width:64px;height:64px;
        border-radius:50%;
        background:rgba(0,212,255,0.15);
        border:2px solid #00d4ff;
        display:flex;align-items:center;
        justify-content:center;
        margin:0 auto 1.5rem;
      ">
        <i class="ti ti-check"
           style="font-size:2rem;color:#00d4ff">
        </i>
      </div>
      <h3 style="color:#fff;font-size:1.3rem;
                 margin-bottom:0.75rem">
        Connection Saved
      </h3>
      <p style="color:#888;font-size:0.9rem;
                margin-bottom:1.5rem">
        The relationship has been added
        to the family tree.
      </p>
      <div style="
        border-top:1px solid #1e1e3a;
        margin-bottom:1.5rem;
      "></div>
      <div style="
        display:flex;flex-direction:column;
        gap:0.75rem;
      ">
        <a href="<?= SITE_URL ?>/family/tree.php"
           class="btn btn-primary w-100"
           style="padding:0.75rem">
          <i class="ti ti-git-fork me-2"></i>
          View Family Tree
        </a>
        <button onclick="
          document.getElementById(
            'success-overlay'
          ).style.display='none'"
          class="btn btn-outline-light w-100"
          style="padding:0.75rem">
          Connect Another Pair
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

  <form method="POST" action="">

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
        Select Members to Connect
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          First Member *
        </label>
        <select name="member_a" class="form-select"
                style="background:#0d0d1a;
                       border:1px solid #1e1e3a;
                       color:#e0e0e0;border-radius:8px"
                required>
          <option value="">-- Select member --</option>
          <?php foreach ($all_members as $m): ?>
            <option value="<?= $m['member_id'] ?>"
              <?= ($_POST['member_a'] ?? '') ==
                  $m['member_id'] ? 'selected':'' ?>>
              <?= clean($m['full_name']) ?>
              <?= $m['preferred_name']
                  ? '(' . clean($m['preferred_name']) . ')'
                  : '' ?>
              — <?= clean($m['quarter']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Relationship *
        </label>
        <select name="rel_type" class="form-select"
                id="rel_type"
                style="background:#0d0d1a;
                       border:1px solid #1e1e3a;
                       color:#e0e0e0;border-radius:8px"
                onchange="updatePreview()"
                required>
          <option value="">-- Select type --</option>
          <option value="parent" <?=
            ($_POST['rel_type'] ?? '') === 'parent'
            ? 'selected':'' ?>>
            is the Parent of →
          </option>
          <option value="child" <?=
            ($_POST['rel_type'] ?? '') === 'child'
            ? 'selected':'' ?>>
            is the Child of →
          </option>
          <option value="spouse" <?=
            ($_POST['rel_type'] ?? '') === 'spouse'
            ? 'selected':'' ?>>
            is the Spouse of ↔
          </option>
          <option value="sibling" <?=
            ($_POST['rel_type'] ?? '') === 'sibling'
            ? 'selected':'' ?>>
            is the Sibling of ↔
          </option>
        </select>
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Second Member *
        </label>
        <select name="member_b" class="form-select"
                style="background:#0d0d1a;
                       border:1px solid #1e1e3a;
                       color:#e0e0e0;border-radius:8px"
                required>
          <option value="">-- Select member --</option>
          <?php foreach ($all_members as $m): ?>
            <option value="<?= $m['member_id'] ?>"
              <?= ($_POST['member_b'] ?? '') ==
                  $m['member_id'] ? 'selected':'' ?>>
              <?= clean($m['full_name']) ?>
              <?= $m['preferred_name']
                  ? '(' . clean($m['preferred_name']) . ')'
                  : '' ?>
              — <?= clean($m['quarter']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label style="color:#aaa;font-size:0.85rem;
                      display:block;margin-bottom:4px">
          Specific Label
          <span style="color:#555">(optional)</span>
        </label>
        <input type="text"
               name="rel_label"
               class="form-control"
               style="background:#0d0d1a;
                      border:1px solid #1e1e3a;
                      color:#e0e0e0;border-radius:8px"
               value="<?= clean(
                   $_POST['rel_label'] ?? ''
               ) ?>"
               placeholder="e.g. father, grandfather_paternal,
                 uncle, stepfather">
        <small style="color:#555;font-size:0.78rem">
          Leave blank to use the general type only
        </small>
      </div>

      <!-- Preview -->
      <div id="preview" style="
        background:rgba(0,212,255,0.06);
        border:1px solid rgba(0,212,255,0.15);
        border-radius:8px;
        padding:0.75rem 1rem;
        color:#00d4ff;font-size:0.85rem;
        display:none;
        margin-top:0.75rem;
      "></div>

    </div>

    <div class="d-flex gap-3 mb-4">
      <button type="submit"
              class="btn btn-primary px-4">
        <i class="ti ti-link me-2"></i>
        Save Connection
      </button>
      <a href="<?= SITE_URL ?>/family/tree.php"
         class="btn btn-outline-secondary px-4">
        Cancel
      </a>
    </div>

  </form>
</div>
</main>

<script>
function updatePreview() {
    const a =
        document.querySelector(
            'select[name="member_a"]'
        );
    const b =
        document.querySelector(
            'select[name="member_b"]'
        );
    const rel =
        document.getElementById('rel_type');
    const preview =
        document.getElementById('preview');

    const nameA = a.options[a.selectedIndex]
        ?.text?.split(' —')[0] || 'Member A';
    const nameB = b.options[b.selectedIndex]
        ?.text?.split(' —')[0] || 'Member B';
    const type  = rel.value;

    if (!type) {
        preview.style.display = 'none';
        return;
    }

    const sentences = {
        parent:  `${nameA} is the parent of ${nameB}`,
        child:   `${nameA} is the child of ${nameB}`,
        spouse:  `${nameA} and ${nameB} are spouses`,
        sibling: `${nameA} and ${nameB} are siblings`,
    };

    preview.textContent =
        '→  ' + (sentences[type] || '');
    preview.style.display = 'block';
}

document.querySelector('select[name="member_a"]')
    .addEventListener('change', updatePreview);
document.querySelector('select[name="member_b"]')
    .addEventListener('change', updatePreview);
</script>

<?php require_once '../includes/footer.php'; ?>
<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $quarter_id = $_POST['quarter_id']      ?? null;
    $is_ekpor   = isset($_POST['is_ekpor']) ? 1 : 0;

    // Validation
    if (empty($full_name))
        $errors[] = 'Full name is required.';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'A valid email address is required.';

    if (strlen($password) < 8)
        $errors[] =
            'Password must be at least 8 characters.';

    if ($password !== $confirm)
        $errors[] = 'Passwords do not match.';

    // Check duplicate email
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "SELECT user_id FROM users
             WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        if ($stmt->fetch())
            $errors[] =
                'An account with this email already exists.';
    }

    // Create account
    if (empty($errors)) {
        $hash = password_hash(
            $password, PASSWORD_DEFAULT
        );
        $stmt = $pdo->prepare("
            INSERT INTO users
              (full_name, email, password_hash,
               quarter_id, is_ekpor_member)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $full_name,
            $email,
            $hash,
            $quarter_id ?: null,
            $is_ekpor
        ]);

        $new_id = $pdo->lastInsertId();

        // Create default settings
        $pdo->prepare(
            "INSERT INTO user_settings
             (user_id) VALUES (?)"
        )->execute([$new_id]);

        // Store success flash in session
        // then redirect so form is cleared
        // and back button won't re-submit
        $_SESSION['register_success'] =
            $full_name;
        header('Location: '
            . SITE_URL . '/login.php'
            . '?registered=1');
        exit;
    }
}

$quarters = getAllQuarters($pdo);
?>
<?php require_once 'includes/header.php'; ?>

<main class="auth-page">
  <div class="auth-card">
    <h2>Create Your Account</h2>
    <p>Join the Ekpor Village heritage community.</p>

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger">
        <?= clean($e) ?>
      </div>
    <?php endforeach; ?>

    <form method="POST" action="">
      <?= csrfField() ?>

      <div class="mb-3">
        <label>Full Name *</label>
        <input type="text"
               name="full_name"
               class="form-control"
               value="<?= clean(
                   $_POST['full_name'] ?? ''
               ) ?>"
               required>
      </div>

      <div class="mb-3">
        <label>Email Address *</label>
        <input type="email"
               name="email"
               class="form-control"
               value="<?= clean(
                   $_POST['email'] ?? ''
               ) ?>"
               required>
      </div>

      <div class="mb-3">
        <label>Password * (min. 8 characters)</label>
        <input type="password"
               name="password"
               class="form-control"
               required>
      </div>

      <div class="mb-3">
        <label>Confirm Password *</label>
        <input type="password"
               name="confirm_password"
               class="form-control"
               required>
      </div>

      <div class="mb-3">
        <label>
          Your Quarter
          <small style="color:#666">
            (if from Ekpor Village)
          </small>
        </label>
        <select name="quarter_id"
                class="form-select">
          <option value="">
            -- Select your quarter --
          </option>
          <?php foreach ($quarters as $q): ?>
            <option value="<?= $q['quarter_id'] ?>">
              <?= clean($q['name']) ?>
              — founded by <?= clean($q['founded_by']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3 form-check">
        <input type="checkbox"
               name="is_ekpor"
               class="form-check-input"
               id="is_ekpor">
        <label class="form-check-label"
               for="is_ekpor"
               style="color:#aaa; font-size:0.9rem">
          I am a member of the Ekpor Village community
        </label>
      </div>

      <div class="mb-4 form-check">
        <input type="checkbox"
               class="form-check-input"
               id="terms"
               required>
        <label class="form-check-label"
               for="terms"
               style="color:#aaa; font-size:0.9rem">
          I agree to the Terms of Service
          and Privacy Policy
        </label>
      </div>

      <button type="submit"
              class="btn btn-primary w-100">
        Create My Heritage Account →
      </button>

    </form>

    <p class="mt-3 text-center"
       style="color:#888; font-size:0.9rem">
      Already a member?
      <a href="<?= SITE_URL ?>/login.php">Sign in</a>
    </p>
  </div>
</main>

<?php require_once 'includes/footer.php';

// Ensure CSRF token is ready
if (empty(\$_SESSION['csrf_token'])) {
    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} ?>
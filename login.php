<?php
require_once __DIR__ . '/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

$error = '';

// Success notice from registration redirect
$just_registered = isset($_GET['registered'])
    && isset($_SESSION['register_success']);
$reg_name = $_SESSION['register_success'] ?? '';
unset($_SESSION['register_success']);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT * FROM users
             WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify(
            $password, $user['password_hash']
        )) {
            // Regenerate session ID for security
            // but keep existing session data
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['user_name']  = $user['full_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['quarter_id'] = $user['quarter_id'];
            $_SESSION['photo']      = $user['profile_photo'];
            // Generate CSRF token immediately after login
            // so the first form the user sees already
            // has a valid token — prevents the
            // "session token expired" message on
            // profile.php for new users
            $_SESSION['csrf_token'] =
                bin2hex(random_bytes(32));

            redirect(SITE_URL . '/dashboard.php');
        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<main class="auth-page">
  <div class="auth-card">
    <h2>Sign In</h2>
    <p>Welcome back to HeritageLink.</p>

    <?php if ($just_registered): ?>
      <div class="alert alert-success"
           style="margin-bottom:1rem">
        <i class="ti ti-circle-check me-1"></i>
        Account created for
        <strong><?= clean($reg_name) ?></strong>.
        Sign in to get started.
      </div>
    <?php endif; ?>



    <?php if ($error): ?>
      <div class="alert alert-danger">
        <?= clean($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrfField() ?>
      <div class="mb-3">
        <label>Email Address</label>
        <input type="email"
               name="email"
               class="form-control"
               value="<?= clean($_POST['email'] ?? '') ?>"
               required
               autofocus>
      </div>
      <div class="mb-3">
        <label>Password</label>
        <input type="password"
               name="password"
               class="form-control"
               required>
      </div>
      <div class="mt-4">
        <button type="submit"
                class="btn btn-primary w-100">
          Sign In to HeritageLink
        </button>
      </div>
    </form>

    <p class="mt-3 text-center"
       style="color:#888; font-size:0.9rem">
      Not yet a member?
      <a href="<?= SITE_URL ?>/register.php">
        Create account
      </a>
    </p>
  </div>
</main>

<?php require_once 'includes/footer.php'; ?>
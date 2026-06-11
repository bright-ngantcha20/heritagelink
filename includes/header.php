<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user   = currentUser();
$unread = isLoggedIn()
    ? unreadCount($pdo, $user['id'])
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0">
  <title><?= SITE_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

  <!-- Tabler Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

  <!-- HeritageLink styles -->
  <link rel="stylesheet"
        href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg" id="hl-nav">
  <div class="container-fluid px-4">

    <!-- Logo -->
    <a class="navbar-brand" href="<?= SITE_URL ?>">
      HeritageLink
    </a>

    <?php if (isLoggedIn()): ?>

    <!-- Main nav links -->
    <div class="nav-links d-none d-lg-flex">
      <a href="<?= SITE_URL ?>/dashboard.php">Home</a>
      <a href="<?= SITE_URL ?>/heritage/history.php">
        Village History
      </a>
      <a href="<?= SITE_URL ?>/family/tree.php">
        Family Tree
      </a>
      <a href="<?= SITE_URL ?>/heritage/contribute.php">
        Contributions
      </a>
      <a href="<?= SITE_URL ?>/verification/queue.php">
        Verification
      </a>
    </div>

    <!-- Right side -->
    <div class="nav-actions ms-auto">

      <!-- Contribute button -->
      <a href="<?= SITE_URL ?>/heritage/contribute.php"
         class="btn btn-primary btn-sm me-2">
        + Contribute
      </a>

      <!-- Message icon with badge -->
      <a href="<?= SITE_URL ?>/messages/inbox.php"
         class="nav-icon me-3" id="msg-icon">
        <i class="ti ti-message"></i>
        <?php if ($unread > 0): ?>
          <span class="badge"><?= $unread ?></span>
        <?php endif; ?>
      </a>

      <!-- Profile dropdown -->
      <div class="dropdown">
        <img src="<?= $user['photo']
            ? SITE_URL . '/' . $user['photo']
            : SITE_URL . '/assets/img/avatar.png' ?>"
             class="avatar"
             alt="<?= clean($user['name']) ?>"
             data-bs-toggle="dropdown"
             aria-expanded="false">
        <ul class="dropdown-menu dropdown-menu-end">
          <li>
            <a class="dropdown-item"
               href="<?= SITE_URL ?>/settings/account.php">
              <i class="ti ti-settings me-2"></i>Settings
            </a>
          </li>
          <?php if ($user['role'] === 'admin'): ?>
          <li>
            <a class="dropdown-item"
               href="<?= SITE_URL ?>/admin/dashboard.php">
              <i class="ti ti-shield me-2"></i>Admin Panel
            </a>
          </li>
          <?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item text-danger"
               href="<?= SITE_URL ?>/logout.php">
              <i class="ti ti-logout me-2"></i>Sign Out
            </a>
          </li>
        </ul>
      </div>

    </div>

    <?php else: ?>

    <!-- Guest nav -->
    <div class="nav-actions ms-auto">
      <a href="<?= SITE_URL ?>/login.php"
         class="btn btn-outline-light btn-sm">
        Sign In
      </a>
      <a href="<?= SITE_URL ?>/register.php"
         class="btn btn-primary btn-sm ms-2">
        Sign Up
      </a>
    </div>

    <?php endif; ?>

  </div>
</nav>
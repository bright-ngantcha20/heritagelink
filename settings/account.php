<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

$user       = currentUser();
$errors     = [];
$success    = '';

// ── Fetch full user row from DB ──────────────────
$user_row = $pdo->prepare(
    "SELECT user_id, full_name, email, role FROM users WHERE user_id = ?"
);
$user_row->execute([$user['id']]);
$db_user = $user_row->fetch(PDO::FETCH_ASSOC);
$user_email = $db_user['email'] ?? '';

// ── Load user settings row (create if missing) ──
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user['id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $pdo->prepare("
        INSERT INTO user_settings
            (user_id, direct_messages, message_previews,
             alert_sound, quiet_hours, quiet_start,
             quiet_end, global_directory)
        VALUES (?, 1, 1, 'pulse', 0, '22:00:00', '07:00:00', 1)
    ")->execute([$user['id']]);
    $stmt->execute([$user['id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── Load member privacy setting ──
$member = hasProfile($pdo, $user['id'])
    ? getUserMember($pdo, $user['id'])
    : null;

$active_tab = $_GET['tab'] ?? 'privacy';

// ── Handle POST ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Save Privacy settings ──────────────────
    if ($action === 'save_privacy') {
        $privacy       = $_POST['privacy']          ?? 'members';
        $global_dir    = isset($_POST['global_directory']) ? 1 : 0;

        $allowed_privacy = ['public', 'members', 'private'];
        if (!in_array($privacy, $allowed_privacy)) {
            $errors[] = 'Invalid privacy setting.';
        }

        if (empty($errors)) {
            // Update family_members privacy
            if ($member) {
                $pdo->prepare("
                    UPDATE family_members
                    SET privacy = ?
                    WHERE member_id = ?
                ")->execute([$privacy, $member['member_id']]);
            }
            // Update user_settings directory visibility
            $pdo->prepare("
                UPDATE user_settings
                SET global_directory = ?
                WHERE user_id = ?
            ")->execute([$global_dir, $user['id']]);

            // Log
            $pdo->prepare("
                INSERT INTO audit_log
                    (user_id, action, target_type, target_id, detail)
                VALUES (?, 'update_privacy', 'user', ?, ?)
            ")->execute([
                $user['id'], $user['id'],
                'Privacy set to: ' . $privacy
            ]);

            $success    = 'Privacy settings saved.';
            $active_tab = 'privacy';
            // Refresh
            if ($member) {
                $member = getUserMember($pdo, $user['id']);
            }
            $stmt->execute([$user['id']]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // ── Save Notification settings ─────────────
    if ($action === 'save_notifications') {
        $direct_messages  = isset($_POST['direct_messages'])  ? 1 : 0;
        $message_previews = isset($_POST['message_previews']) ? 1 : 0;
        $alert_sound      = $_POST['alert_sound']             ?? 'pulse';
        $quiet_hours      = isset($_POST['quiet_hours'])      ? 1 : 0;
        $quiet_start      = $_POST['quiet_start']             ?? '22:00';
        $quiet_end        = $_POST['quiet_end']               ?? '07:00';

        $allowed_sounds = ['pulse', 'chime', 'pop', 'none'];
        if (!in_array($alert_sound, $allowed_sounds)) {
            $alert_sound = 'pulse';
        }

        $pdo->prepare("
            UPDATE user_settings SET
                direct_messages  = ?,
                message_previews = ?,
                alert_sound      = ?,
                quiet_hours      = ?,
                quiet_start      = ?,
                quiet_end        = ?
            WHERE user_id = ?
        ")->execute([
            $direct_messages, $message_previews,
            $alert_sound, $quiet_hours,
            $quiet_start . ':00', $quiet_end . ':00',
            $user['id']
        ]);

        $success    = 'Notification settings saved.';
        $active_tab = 'notifications';
        $stmt->execute([$user['id']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ── Change password ────────────────────────
    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        // Fetch current hash
        $row = $pdo->prepare(
            "SELECT password_hash FROM users WHERE user_id = ?"
        );
        $row->execute([$user['id']]);
        $db_hash = $row->fetchColumn();

        if (empty($current)) {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($current, $db_hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }

        if (empty($errors)) {
            $pdo->prepare("
                UPDATE users SET password_hash = ?
                WHERE user_id = ?
            ")->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

            $pdo->prepare("
                INSERT INTO audit_log
                    (user_id, action, target_type, target_id, detail)
                VALUES (?, 'change_password', 'user', ?, 'User changed their password')
            ")->execute([$user['id'], $user['id']]);

            $success    = 'Password changed successfully.';
            $active_tab = 'account';
        } else {
            $active_tab = 'account';
        }
    }

    // ── Change email ───────────────────────────
    if ($action === 'change_email') {
        $new_email = trim($_POST['new_email'] ?? '');
        $password  = $_POST['email_password'] ?? '';

        $row = $pdo->prepare(
            "SELECT password_hash FROM users WHERE user_id = ?"
        );
        $row->execute([$user['id']]);
        $db_hash = $row->fetchColumn();

        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (!password_verify($password, $db_hash)) {
            $errors[] = 'Password confirmation is incorrect.';
        } else {
            // Check if email already taken
            $check = $pdo->prepare(
                "SELECT user_id FROM users WHERE email = ? AND user_id != ?"
            );
            $check->execute([$new_email, $user['id']]);
            if ($check->fetch()) {
                $errors[] = 'That email address is already in use.';
            }
        }

        if (empty($errors)) {
            $pdo->prepare("
                UPDATE users SET email = ? WHERE user_id = ?
            ")->execute([$new_email, $user['id']]);

            // Update local variable
            $user_email = $new_email;

            $pdo->prepare("
                INSERT INTO audit_log
                    (user_id, action, target_type, target_id, detail)
                VALUES (?, 'change_email', 'user', ?, ?)
            ")->execute([
                $user['id'], $user['id'],
                'Email changed to: ' . $new_email
            ]);

            $success    = 'Email address updated successfully.';
            $active_tab = 'account';
        } else {
            $active_tab = 'account';
        }
    }

    // ── Deactivate account ─────────────────────
    if ($action === 'deactivate') {
        $confirm_text = trim($_POST['confirm_text'] ?? '');
        $password     = $_POST['deactivate_password'] ?? '';

        $row = $pdo->prepare(
            "SELECT password_hash FROM users WHERE user_id = ?"
        );
        $row->execute([$user['id']]);
        $db_hash = $row->fetchColumn();

        if ($confirm_text !== 'DEACTIVATE') {
            $errors[] = 'Please type DEACTIVATE exactly to confirm.';
        } elseif (!password_verify($password, $db_hash)) {
            $errors[] = 'Password is incorrect.';
        }

        if (empty($errors)) {
            // Soft-delete: mark account inactive
            $pdo->prepare("
                UPDATE users SET role = 'inactive' WHERE user_id = ?
            ")->execute([$user['id']]);

            $pdo->prepare("
                INSERT INTO audit_log
                    (user_id, action, target_type, target_id, detail)
                VALUES (?, 'deactivate_account', 'user', ?, 'Account deactivated by user')
            ")->execute([$user['id'], $user['id']]);

            // End session
            session_destroy();
            header('Location: ' . SITE_URL . '/login.php?msg=deactivated');
            exit;
        } else {
            $active_tab = 'account';
        }
    }
}

// Helpers
$cur_privacy  = $member['privacy']           ?? 'members';
$cur_dir      = $settings['global_directory'] ?? 1;
$cur_dm       = $settings['direct_messages']  ?? 1;
$cur_prev     = $settings['message_previews'] ?? 1;
$cur_sound    = $settings['alert_sound']      ?? 'pulse';
$cur_quiet    = $settings['quiet_hours']      ?? 0;
$cur_qs       = substr($settings['quiet_start'] ?? '22:00:00', 0, 5);
$cur_qe       = substr($settings['quiet_end']   ?? '07:00:00', 0, 5);
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem">
<div class="container" style="max-width:760px">

  <!-- Page header -->
  <div class="mb-4">
    <a href="<?= SITE_URL ?>/dashboard.php"
       style="color:#888;font-size:0.9rem">
      ← Back to Dashboard
    </a>
    <h2 style="color:#fff;margin-top:0.75rem">
      <i class="ti ti-settings me-2"
         style="color:#00d4ff"></i>
      Account Settings
    </h2>
    <p style="color:#666;font-size:0.9rem">
      Manage your privacy, notifications,
      and account security.
    </p>
  </div>

  <!-- Success banner -->
  <?php if ($success): ?>
  <div class="alert mb-4" style="
    background:rgba(0,200,100,0.08);
    border:1px solid rgba(0,200,100,0.25);
    color:#00c864;border-radius:10px;
    padding:0.85rem 1.25rem;
  ">
    <i class="ti ti-circle-check me-2"></i>
    <?= clean($success) ?>
  </div>
  <?php endif; ?>

  <!-- Error banner -->
  <?php foreach ($errors as $e): ?>
  <div class="alert mb-3" style="
    background:rgba(220,53,69,0.08);
    border:1px solid rgba(220,53,69,0.25);
    color:#ff6b7a;border-radius:10px;
    padding:0.85rem 1.25rem;
  ">
    <i class="ti ti-alert-circle me-2"></i>
    <?= clean($e) ?>
  </div>
  <?php endforeach; ?>

  <!-- ── Tab nav ─────────────────────────────── -->
  <div style="
    display:flex;gap:4px;
    border-bottom:1px solid #1e1e3a;
    margin-bottom:1.75rem;
  ">
    <?php
    $tabs = [
      'privacy'       => ['ti-lock',       'Privacy'],
      'notifications' => ['ti-bell',        'Notifications'],
      'account'       => ['ti-shield-lock', 'Account & Security'],
    ];
    foreach ($tabs as $key => [$icon, $label]):
      $is_active = $active_tab === $key;
    ?>
    <a href="?tab=<?= $key ?>"
       style="
         padding:0.6rem 1.1rem;
         font-size:0.88rem;
         color:<?= $is_active ? '#00d4ff' : '#666' ?>;
         border-bottom:2px solid
           <?= $is_active ? '#00d4ff' : 'transparent' ?>;
         text-decoration:none;
         display:flex;align-items:center;gap:6px;
         transition:color 0.15s;
       ">
      <i class="ti <?= $icon ?>"></i>
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ════════════════════════════════════════════
       TAB 1 — PRIVACY
  ════════════════════════════════════════════ -->
  <?php if ($active_tab === 'privacy'): ?>

  <form method="POST" action="?tab=privacy">
    <input type="hidden" name="action" value="save_privacy">

    <!-- Profile visibility -->
    <div class="settings-card mb-4">
      <div class="settings-card-title">
        Profile Visibility
      </div>
      <p class="settings-desc">
        Control who can see your profile on
        HeritageLink.
      </p>

      <?php
      $vis_opts = [
        'public'  => [
          'ti-world',
          'Public',
          'Anyone — including visitors who are not signed in',
        ],
        'members' => [
          'ti-users',
          'Community Members',
          'Only registered HeritageLink members',
        ],
        'private' => [
          'ti-lock',
          'Private',
          'Only you and administrators',
        ],
      ];
      foreach ($vis_opts as $val => [$icon, $title, $desc]):
      ?>
      <label class="radio-card
        <?= $cur_privacy === $val ? 'radio-card--active' : '' ?>">
        <input type="radio" name="privacy"
               value="<?= $val ?>"
               <?= $cur_privacy === $val ? 'checked' : '' ?>
               style="display:none">
        <i class="ti <?= $icon ?>"></i>
        <div>
          <div class="radio-card-title">
            <?= $title ?>
          </div>
          <div class="radio-card-desc">
            <?= $desc ?>
          </div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- Directory listing -->
    <div class="settings-card mb-4">
      <div class="settings-card-title">
        Community Directory
      </div>
      <div class="toggle-row">
        <div>
          <div class="toggle-label">
            Appear in global directory
          </div>
          <div class="toggle-desc">
            Allow other members to find you
            through the community search.
          </div>
        </div>
        <label class="hl-toggle">
          <input type="checkbox"
                 name="global_directory"
                 <?= $cur_dir ? 'checked' : '' ?>>
          <span class="hl-toggle-slider"></span>
        </label>
      </div>
    </div>

    <button type="submit" class="btn-save">
      <i class="ti ti-device-floppy me-2"></i>
      Save Privacy Settings
    </button>
  </form>

  <!-- ════════════════════════════════════════════
       TAB 2 — NOTIFICATIONS
  ════════════════════════════════════════════ -->
  <?php elseif ($active_tab === 'notifications'): ?>

  <form method="POST" action="?tab=notifications">
    <input type="hidden" name="action" value="save_notifications">

    <!-- Messaging -->
    <div class="settings-card mb-4">
      <div class="settings-card-title">Messaging</div>

      <div class="toggle-row">
        <div>
          <div class="toggle-label">
            Allow direct messages
          </div>
          <div class="toggle-desc">
            Let other community members send
            you messages.
          </div>
        </div>
        <label class="hl-toggle">
          <input type="checkbox"
                 name="direct_messages"
                 id="dm_toggle"
                 <?= $cur_dm ? 'checked' : '' ?>>
          <span class="hl-toggle-slider"></span>
        </label>
      </div>

      <div class="toggle-row" style="margin-top:1rem"
           id="preview_row">
        <div>
          <div class="toggle-label">
            Show message previews
          </div>
          <div class="toggle-desc">
            Display the first line of incoming
            messages in notifications.
          </div>
        </div>
        <label class="hl-toggle">
          <input type="checkbox"
                 name="message_previews"
                 <?= $cur_prev ? 'checked' : '' ?>>
          <span class="hl-toggle-slider"></span>
        </label>
      </div>
    </div>

    <!-- Alert sound -->
    <div class="settings-card mb-4">
      <div class="settings-card-title">
        Alert Sound
      </div>
      <p class="settings-desc">
        Choose the sound played when you
        receive a new message.
      </p>

      <div class="sound-grid">
        <?php
        $sounds = [
          'pulse' => 'Pulse',
          'chime' => 'Chime',
          'pop'   => 'Pop',
          'none'  => 'Silent',
        ];
        foreach ($sounds as $val => $label):
        ?>
        <label class="sound-card
          <?= $cur_sound === $val ? 'sound-card--active' : '' ?>">
          <input type="radio" name="alert_sound"
                 value="<?= $val ?>"
                 <?= $cur_sound === $val ? 'checked' : '' ?>
                 style="display:none">
          <i class="ti <?= $val === 'none'
              ? 'ti-volume-off' : 'ti-volume' ?>"></i>
          <span><?= $label ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Quiet hours -->
    <div class="settings-card mb-4">
      <div class="settings-card-title">
        Quiet Hours
      </div>

      <div class="toggle-row">
        <div>
          <div class="toggle-label">
            Enable quiet hours
          </div>
          <div class="toggle-desc">
            Mute all notifications during the
            hours you specify.
          </div>
        </div>
        <label class="hl-toggle">
          <input type="checkbox"
                 name="quiet_hours"
                 id="quiet_toggle"
                 <?= $cur_quiet ? 'checked' : '' ?>>
          <span class="hl-toggle-slider"></span>
        </label>
      </div>

      <div id="quiet_times"
           style="
             margin-top:1.25rem;
             display:<?= $cur_quiet ? 'flex' : 'none' ?>;
             gap:1.5rem;flex-wrap:wrap;
           ">
        <div>
          <label style="
            color:#888;font-size:0.8rem;
            display:block;margin-bottom:4px;
          ">From</label>
          <input type="time" name="quiet_start"
                 class="hl-input"
                 value="<?= $cur_qs ?>">
        </div>
        <div>
          <label style="
            color:#888;font-size:0.8rem;
            display:block;margin-bottom:4px;
          ">To</label>
          <input type="time" name="quiet_end"
                 class="hl-input"
                 value="<?= $cur_qe ?>">
        </div>
      </div>
    </div>

    <button type="submit" class="btn-save">
      <i class="ti ti-device-floppy me-2"></i>
      Save Notification Settings
    </button>
  </form>

  <!-- ════════════════════════════════════════════
       TAB 3 — ACCOUNT & SECURITY
  ════════════════════════════════════════════ -->
  <?php elseif ($active_tab === 'account'): ?>

  <!-- Current account info -->
  <div class="settings-card mb-4">
    <div class="settings-card-title">
      Account Information
    </div>

    <!-- Profile banner -->
    <div style="
      display:flex;align-items:center;
      gap:1.25rem;
      padding:1.1rem;
      background:rgba(0,212,255,0.04);
      border:1px solid rgba(0,212,255,0.1);
      border-radius:10px;
      margin-bottom:1.25rem;
    ">
      <!-- Avatar -->
      <div style="
        width:56px;height:56px;
        border-radius:50%;
        background:rgba(0,212,255,0.12);
        border:2px solid rgba(0,212,255,0.25);
        display:flex;align-items:center;
        justify-content:center;
        font-size:1.4rem;font-weight:700;
        color:#00d4ff;flex-shrink:0;
        overflow:hidden;
      ">
        <?php if ($user['photo']): ?>
          <img src="<?= SITE_URL ?>/<?= $user['photo'] ?>"
               style="width:100%;height:100%;object-fit:cover"
               alt="<?= clean($user['name']) ?>">
        <?php else: ?>
          <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
        <?php endif; ?>
      </div>

      <div style="flex:1;min-width:0">
        <div style="
          color:#fff;font-size:1.05rem;
          font-weight:600;
        ">
          <?= clean($user['name']) ?>
        </div>
        <div style="
          color:#888;font-size:0.85rem;
          margin-top:2px;
          overflow:hidden;text-overflow:ellipsis;
          white-space:nowrap;
        ">
          <?= clean($user_email) ?>
        </div>
        <div style="margin-top:6px">
          <span style="
            background:<?= $user['role'] === 'admin'
                ? 'rgba(255,159,26,0.15)'
                : 'rgba(0,212,255,0.1)' ?>;
            color:<?= $user['role'] === 'admin'
                ? '#ff9f1a' : '#00d4ff' ?>;
            font-size:0.7rem;font-weight:700;
            padding:2px 10px;border-radius:20px;
            text-transform:uppercase;
            letter-spacing:0.05em;
          ">
            <?= clean($user['role']) ?>
          </span>
        </div>
      </div>

      <a href="<?= SITE_URL ?>/settings/profile.php"
         style="
           background:rgba(255,255,255,0.05);
           border:1px solid #2a2a4a;
           color:#aaa;border-radius:8px;
           padding:0.45rem 0.9rem;
           font-size:0.82rem;text-decoration:none;
           white-space:nowrap;
           transition:border-color 0.15s,color 0.15s;
           display:flex;align-items:center;gap:5px;
         ">
        <i class="ti ti-edit"></i>
        Edit Profile
      </a>
    </div>

    <!-- Info grid -->
    <div style="
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:0.75rem 1.5rem;
    ">
      <?php
      $info_items = [
        ['Full name',     $user['name']   ?? '—'],
        ['Email address', $user_email     ?: '—'],
        ['Account role',  ucfirst($user['role'] ?? 'member')],
        ['Member since',  date('F Y')],
      ];
      foreach ($info_items as [$label, $val]):
      ?>
      <div style="
        padding:0.65rem 0.85rem;
        background:#0d0d1a;
        border:1px solid #1a1a2e;
        border-radius:8px;
      ">
        <div style="
          color:#444;font-size:0.72rem;
          text-transform:uppercase;
          letter-spacing:0.06em;
          margin-bottom:3px;
        "><?= $label ?></div>
        <div style="
          color:#d0d0d0;font-size:0.88rem;
          overflow:hidden;text-overflow:ellipsis;
          white-space:nowrap;
        "><?= clean($val) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Change email -->
  <div class="settings-card mb-4">
    <div class="settings-card-title">
      Change Email Address
    </div>
    <form method="POST" action="?tab=account">
      <input type="hidden" name="action"
             value="change_email">
      <div class="mb-3">
        <label class="hl-label">New email address</label>
        <input type="email" name="new_email"
               class="hl-input"
               placeholder="your@email.com"
               required>
      </div>
      <div class="mb-3">
        <label class="hl-label">
          Confirm with your current password
        </label>
        <input type="password" name="email_password"
               class="hl-input"
               placeholder="Current password"
               required>
      </div>
      <button type="submit"
              class="btn-secondary-sm">
        Update email address
      </button>
    </form>
  </div>

  <!-- Change password -->
  <div class="settings-card mb-4">
    <div class="settings-card-title">
      Change Password
    </div>
    <form method="POST" action="?tab=account">
      <input type="hidden" name="action"
             value="change_password">
      <div class="mb-3">
        <label class="hl-label">
          Current password
        </label>
        <input type="password"
               name="current_password"
               class="hl-input"
               placeholder="••••••••"
               required>
      </div>
      <div class="mb-3">
        <label class="hl-label">
          New password
          <span style="color:#555;font-size:0.78rem">
            (min 8 characters)
          </span>
        </label>
        <input type="password" name="new_password"
               class="hl-input"
               placeholder="••••••••"
               minlength="8"
               id="new_pw"
               required>
        <!-- Strength meter -->
        <div id="pw_strength" style="
          height:3px;border-radius:3px;
          margin-top:6px;width:0%;
          transition:width 0.3s,background 0.3s;
        "></div>
        <div id="pw_strength_label"
             style="color:#555;font-size:0.75rem;
                    margin-top:3px"></div>
      </div>
      <div class="mb-3">
        <label class="hl-label">
          Confirm new password
        </label>
        <input type="password"
               name="confirm_password"
               class="hl-input"
               placeholder="••••••••"
               id="confirm_pw"
               required>
        <div id="pw_match"
             style="font-size:0.75rem;
                    margin-top:3px"></div>
      </div>
      <button type="submit"
              class="btn-secondary-sm">
        Change password
      </button>
    </form>
  </div>

  <!-- Danger zone -->
  <div class="settings-card" style="
    border-color:rgba(220,53,69,0.3) !important;
  ">
    <div class="settings-card-title"
         style="color:#ff6b7a">
      <i class="ti ti-alert-triangle me-2"></i>
      Danger Zone
    </div>
    <p class="settings-desc">
      Deactivating your account will hide your
      profile and prevent you from signing in.
      Your family tree contributions will be
      preserved. This action can be reversed
      by an administrator.
    </p>

    <button type="button"
            class="btn-danger-outline"
            onclick="
              document.getElementById(
                'deactivate_modal'
              ).style.display='flex'
            ">
      <i class="ti ti-user-off me-2"></i>
      Deactivate my account
    </button>
  </div>

  <!-- Deactivate modal -->
  <div id="deactivate_modal" style="
    display:none;position:fixed;
    inset:0;background:rgba(0,0,0,0.7);
    z-index:1000;
    align-items:center;justify-content:center;
  ">
    <div style="
      background:#111127;
      border:1px solid rgba(220,53,69,0.35);
      border-radius:14px;
      padding:2rem;max-width:440px;
      width:calc(100% - 2rem);
    ">
      <h5 style="color:#ff6b7a;margin-bottom:0.5rem">
        Deactivate account?
      </h5>
      <p style="color:#888;font-size:0.88rem;
                margin-bottom:1.5rem">
        Type <strong style="color:#ff6b7a">
        DEACTIVATE</strong> and enter your
        password to confirm.
      </p>
      <form method="POST" action="?tab=account">
        <input type="hidden" name="action"
               value="deactivate">
        <div class="mb-3">
          <input type="text" name="confirm_text"
                 class="hl-input"
                 placeholder="Type DEACTIVATE"
                 autocomplete="off">
        </div>
        <div class="mb-3">
          <input type="password"
                 name="deactivate_password"
                 class="hl-input"
                 placeholder="Your password">
        </div>
        <div style="
          display:flex;gap:0.75rem;
          justify-content:flex-end;
        ">
          <button type="button"
                  class="btn-secondary-sm"
                  onclick="
                    document.getElementById(
                      'deactivate_modal'
                    ).style.display='none'
                  ">
            Cancel
          </button>
          <button type="submit"
                  class="btn-danger">
            Deactivate
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php endif; ?>

</div>
</main>

<!-- ── Scoped styles ──────────────────────────── -->
<style>
.settings-card {
  background: #111127;
  border: 1px solid #1e1e3a;
  border-radius: 12px;
  padding: 1.5rem;
}
.settings-card-title {
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  color: #00d4ff;
  text-transform: uppercase;
  margin-bottom: 1.1rem;
}
.settings-desc {
  color: #666;
  font-size: 0.85rem;
  margin-bottom: 1.1rem;
  line-height: 1.5;
}
/* Radio cards (privacy) */
.radio-card {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 0.85rem 1rem;
  border: 1px solid #1e1e3a;
  border-radius: 9px;
  cursor: pointer;
  margin-bottom: 0.65rem;
  transition: border-color 0.15s, background 0.15s;
}
.radio-card i {
  font-size: 1.2rem;
  color: #555;
  margin-top: 1px;
  flex-shrink: 0;
}
.radio-card--active {
  border-color: rgba(0, 212, 255, 0.4);
  background: rgba(0, 212, 255, 0.04);
}
.radio-card--active i { color: #00d4ff; }
.radio-card-title {
  color: #e0e0e0;
  font-size: 0.88rem;
  font-weight: 500;
}
.radio-card-desc {
  color: #666;
  font-size: 0.78rem;
  margin-top: 2px;
}
/* Toggle switch */
.hl-toggle {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
  flex-shrink: 0;
}
.hl-toggle input { opacity: 0; width: 0; height: 0; }
.hl-toggle-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: #1e1e3a;
  border-radius: 24px;
  transition: background 0.2s;
}
.hl-toggle-slider::before {
  content: '';
  position: absolute;
  height: 18px; width: 18px;
  left: 3px; bottom: 3px;
  background: #555;
  border-radius: 50%;
  transition: transform 0.2s, background 0.2s;
}
.hl-toggle input:checked + .hl-toggle-slider {
  background: rgba(0, 212, 255, 0.25);
}
.hl-toggle input:checked + .hl-toggle-slider::before {
  transform: translateX(20px);
  background: #00d4ff;
}
.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}
.toggle-label {
  color: #e0e0e0;
  font-size: 0.88rem;
}
.toggle-desc {
  color: #555;
  font-size: 0.78rem;
  margin-top: 2px;
}
/* Sound cards */
.sound-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.65rem;
}
.sound-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  padding: 0.75rem 0.5rem;
  border: 1px solid #1e1e3a;
  border-radius: 9px;
  cursor: pointer;
  font-size: 0.8rem;
  color: #666;
  transition: border-color 0.15s, color 0.15s;
}
.sound-card i { font-size: 1.2rem; }
.sound-card--active {
  border-color: rgba(0, 212, 255, 0.4);
  color: #00d4ff;
  background: rgba(0, 212, 255, 0.04);
}
/* Form inputs */
.hl-input {
  width: 100%;
  background: #0d0d1a;
  border: 1px solid #1e1e3a;
  color: #e0e0e0;
  border-radius: 8px;
  padding: 0.5rem 0.85rem;
  font-size: 0.9rem;
  outline: none;
  transition: border-color 0.15s;
}
.hl-input:focus { border-color: #00d4ff; }
.hl-label {
  display: block;
  color: #aaa;
  font-size: 0.83rem;
  margin-bottom: 5px;
}
/* Buttons */
.btn-save {
  background: #00d4ff;
  color: #000;
  border: none;
  border-radius: 8px;
  padding: 0.55rem 1.4rem;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: opacity 0.15s;
}
.btn-save:hover { opacity: 0.85; }
.btn-secondary-sm {
  background: transparent;
  color: #aaa;
  border: 1px solid #2a2a4a;
  border-radius: 8px;
  padding: 0.45rem 1.1rem;
  font-size: 0.85rem;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s;
}
.btn-secondary-sm:hover {
  border-color: #00d4ff;
  color: #00d4ff;
}
.btn-danger-outline {
  background: transparent;
  color: #ff6b7a;
  border: 1px solid rgba(220,53,69,0.4);
  border-radius: 8px;
  padding: 0.5rem 1.2rem;
  font-size: 0.88rem;
  cursor: pointer;
  display: flex;align-items:center;
  transition: background 0.15s;
}
.btn-danger-outline:hover {
  background: rgba(220,53,69,0.08);
}
.btn-danger {
  background: #dc3545;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.5rem 1.2rem;
  font-size: 0.88rem;
  cursor: pointer;
  font-weight: 600;
}
@media (max-width: 500px) {
  .sound-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<script>
// ── Radio cards: highlight on click ──────────
document.querySelectorAll('.radio-card').forEach(card => {
  card.addEventListener('click', () => {
    card.closest('.settings-card')
        .querySelectorAll('.radio-card')
        .forEach(c => c.classList.remove('radio-card--active'));
    card.classList.add('radio-card--active');
    card.querySelector('input[type=radio]').checked = true;
  });
});

// ── Sound cards ───────────────────────────────
document.querySelectorAll('.sound-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.sound-card')
        .forEach(c => c.classList.remove('sound-card--active'));
    card.classList.add('sound-card--active');
    card.querySelector('input[type=radio]').checked = true;
  });
});

// ── Quiet hours toggle ────────────────────────
const quietToggle = document.getElementById('quiet_toggle');
const quietTimes  = document.getElementById('quiet_times');
if (quietToggle) {
  quietToggle.addEventListener('change', () => {
    quietTimes.style.display =
      quietToggle.checked ? 'flex' : 'none';
  });
}

// ── DM toggle disables preview ────────────────
const dmToggle   = document.getElementById('dm_toggle');
const previewRow = document.getElementById('preview_row');
if (dmToggle) {
  dmToggle.addEventListener('change', () => {
    previewRow.style.opacity =
      dmToggle.checked ? '1' : '0.4';
  });
}

// ── Password strength ─────────────────────────
const newPw     = document.getElementById('new_pw');
const confirmPw = document.getElementById('confirm_pw');
const strength  = document.getElementById('pw_strength');
const strLabel  = document.getElementById('pw_strength_label');
const matchLbl  = document.getElementById('pw_match');

if (newPw) {
  newPw.addEventListener('input', () => {
    const v = newPw.value;
    let score = 0;
    if (v.length >= 8)               score++;
    if (/[A-Z]/.test(v))             score++;
    if (/[0-9]/.test(v))             score++;
    if (/[^A-Za-z0-9]/.test(v))      score++;

    const widths  = ['0%','30%','55%','80%','100%'];
    const colors  = ['','#dc3545','#ff9f1a','#ffc107','#00c864'];
    const labels  = ['','Weak','Fair','Good','Strong'];

    strength.style.width      = widths[score];
    strength.style.background = colors[score];
    strLabel.textContent      = score > 0 ? labels[score] : '';
    strLabel.style.color      = colors[score];

    checkMatch();
  });

  confirmPw.addEventListener('input', checkMatch);
}

function checkMatch() {
  if (!confirmPw.value) {
    matchLbl.textContent = '';
    return;
  }
  if (newPw.value === confirmPw.value) {
    matchLbl.textContent = '✓ Passwords match';
    matchLbl.style.color = '#00c864';
  } else {
    matchLbl.textContent = '✗ Passwords do not match';
    matchLbl.style.color = '#ff6b7a';
  }
}

// Close modal on backdrop click
const modal = document.getElementById('deactivate_modal');
if (modal) {
  modal.addEventListener('click', e => {
    if (e.target === modal) modal.style.display = 'none';
  });
}
</script>

<?php require_once '../includes/footer.php'; ?>
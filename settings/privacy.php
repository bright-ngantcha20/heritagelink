<?php
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
?>
<?php require_once '../includes/header.php'; ?>

<main style="padding:2rem 1rem">
<div class="container" style="max-width:800px">

  <!-- Breadcrumb -->
  <div style="font-size:0.82rem;color:#555;margin-bottom:1.5rem">
    <a href="<?= SITE_URL ?>/dashboard.php"
       style="color:#555;text-decoration:none">Home</a>
    <span style="margin:0 0.5rem">›</span>
    <span style="color:#888">Privacy Policy & Terms of Use</span>
  </div>

  <!-- Header -->
  <div style="margin-bottom:2rem">
    <h2 style="color:#fff;margin:0 0 0.4rem">
      <i class="ti ti-shield-lock me-2" style="color:#00d4ff"></i>
      Privacy Policy & Terms of Use
    </h2>
    <p style="color:#666;margin:0;font-size:0.9rem">
      Last updated: June 2026 &nbsp;·&nbsp; HeritageLink, Ekpor Village
    </p>
  </div>

  <!-- Card wrapper -->
  <div style="
    background:#111127;border:1px solid #1e1e3a;
    border-radius:14px;padding:2rem;
    color:#ccc;font-size:0.92rem;line-height:1.8;
  ">

    <!-- Section helper macro -->
    <?php
    function section($icon, $title) {
        echo "<div style=\"margin-top:2rem;margin-bottom:0.75rem\">
          <h5 style=\"color:#fff;font-size:1rem;
                      font-weight:700;margin:0\">
            <i class=\"ti {$icon} me-2\"
               style=\"color:#00d4ff\"></i>{$title}
          </h5>
          <div style=\"height:1px;background:#1e1e3a;
                       margin-top:0.5rem\"></div>
        </div>";
    }
    ?>

    <p>
      Welcome to <strong style="color:#fff">HeritageLink</strong>,
      a Digital Family Tree and Heritage Information System
      dedicated to preserving the living history of
      <strong style="color:#fff">Ekpor Village</strong>,
      Manyu Division, Cameroon. By registering or using this
      platform, you agree to the terms outlined below.
    </p>

    <?php section('ti-database', '1. Information We Collect') ?>
    <p>When you register, we collect:</p>
    <ul style="color:#aaa;padding-left:1.25rem">
      <li>Your full name and email address</li>
      <li>Your quarter affiliation within Ekpor Village</li>
      <li>A profile photo (optional)</li>
      <li>Family relationship data you provide</li>
      <li>Heritage records and contributions you submit</li>
    </ul>
    <p>
      We also automatically collect basic usage data such as
      login timestamps and activity logs for security and
      audit purposes.
    </p>

    <?php section('ti-lock', '2. How We Use Your Information') ?>
    <p>Your information is used solely to:</p>
    <ul style="color:#aaa;padding-left:1.25rem">
      <li>Display your profile within the HeritageLink community</li>
      <li>Link you to your family tree record in Ekpor Village</li>
      <li>Enable messaging between community members</li>
      <li>Allow administrators to verify and manage heritage records</li>
      <li>Preserve and present the documented history of Ekpor Village</li>
    </ul>
    <p>
      We do <strong style="color:#fff">not</strong> sell,
      share, or disclose your personal information to any
      third party for commercial purposes.
    </p>

    <?php section('ti-users', '3. Who Can See Your Information') ?>
    <p>
      HeritageLink has three visibility levels for content:
    </p>
    <ul style="color:#aaa;padding-left:1.25rem">
      <li>
        <strong style="color:#ccc">Public</strong> —
        visible to anyone, including visitors who are not
        registered
      </li>
      <li>
        <strong style="color:#ccc">Members only</strong> —
        visible only to registered and verified members
      </li>
      <li>
        <strong style="color:#ccc">Private</strong> —
        visible only to administrators
      </li>
    </ul>
    <p>
      Your profile photo and full name are visible to other
      registered members. Your email address is never
      displayed publicly.
    </p>

    <?php section('ti-photo', '4. Photos and Media') ?>
    <p>
      Any photos or media files you upload to HeritageLink
      become part of the Ekpor Village heritage archive.
      By uploading content, you confirm that you have the
      right to share it and grant HeritageLink permission
      to store and display it within the platform for
      heritage preservation purposes.
    </p>

    <?php section('ti-message', '5. Private Messages') ?>
    <p>
      Messages sent between members are private and are
      only visible to the sender and recipient.
      Administrators may access messages only in cases of
      reported abuse or security investigation.
    </p>

    <?php section('ti-shield-check', '6. Data Security') ?>
    <p>
      All passwords are stored using industry-standard
      bcrypt hashing. Sessions are protected with CSRF
      tokens and regenerated on login. We take reasonable
      technical measures to protect your data, but no
      online system can guarantee absolute security.
    </p>

    <?php section('ti-file-text', '7. Terms of Use') ?>
    <p>By using HeritageLink, you agree to:</p>
    <ul style="color:#aaa;padding-left:1.25rem">
      <li>
        Provide accurate information about yourself and
        your connection to Ekpor Village
      </li>
      <li>
        Respect the privacy and dignity of other community
        members
      </li>
      <li>
        Submit only heritage records that are truthful and
        relevant to Ekpor Village history
      </li>
      <li>
        Not use the platform to harass, impersonate, or
        harm other members
      </li>
      <li>
        Not attempt to access accounts or data that do not
        belong to you
      </li>
    </ul>
    <p>
      Violation of these terms may result in your account
      being suspended or removed by an administrator.
    </p>

    <?php section('ti-edit', '8. Your Rights') ?>
    <p>You have the right to:</p>
    <ul style="color:#aaa;padding-left:1.25rem">
      <li>Update or correct your profile information at any time</li>
      <li>Request deletion of your account by contacting an administrator</li>
      <li>Know what personal data is stored about you</li>
    </ul>
    <p>
      To exercise these rights, contact the HeritageLink
      administrator at
      <a href="mailto:heritagelink@gmail.com"
         style="color:#00d4ff">
        heritagelink@gmail.com
      </a>.
    </p>

    <?php section('ti-refresh', '9. Changes to This Policy') ?>
    <p>
      This policy may be updated from time to time to
      reflect changes in the platform. Significant changes
      will be communicated to members. Continued use of
      HeritageLink after changes are posted constitutes
      acceptance of the updated policy.
    </p>

    <?php section('ti-map-pin', '10. Contact') ?>
    <p>
      HeritageLink is operated as a community heritage
      project for Ekpor Village, Manyu Division,
      SW Region, Cameroon.
    </p>
    <p>
      For questions, concerns, or requests related to
      this policy, contact:
      <a href="mailto:heritagelink@gmail.com"
         style="color:#00d4ff">
        heritagelink@gmail.com
      </a>
    </p>

    <div style="
      margin-top:2rem;padding-top:1.5rem;
      border-top:1px solid #1e1e3a;
      font-size:0.8rem;color:#444;
      text-align:center;
    ">
      © 2026 HeritageLink · Ekpor Village Heritage Project ·
      All rights reserved
    </div>

  </div>

  <div style="height:3rem"></div>
</div>
</main>

<?php require_once '../includes/footer.php'; ?>
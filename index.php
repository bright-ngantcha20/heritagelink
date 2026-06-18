<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect logged-in users straight to dashboard
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
}

// Live stats for the page
$total_members = $pdo->query(
    "SELECT COUNT(*) FROM family_members"
)->fetchColumn();

$total_users = $pdo->query(
    "SELECT COUNT(*) FROM users
     WHERE role = 'member'"
)->fetchColumn();

$total_records = $pdo->query(
    "SELECT COUNT(*) FROM heritage_records"
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width,
               initial-scale=1.0">
<title>HeritageLink — Ekpor Village Family Tree</title>
<meta name="description"
      content="HeritageLink is the digital home
      for the genealogical and cultural heritage
      of Ekpor Village, Manyu Division, Cameroon.
      Explore your family tree. Preserve your
      history. Connect with your roots.">

<!-- Tabler icons -->
<link rel="stylesheet"
      href="<?= SITE_URL ?>/assets/css/tabler-icons.min.css">

<style>
/* ── Reset & base ─────────────────────────── */
*, *::before, *::after {
  box-sizing: border-box;
  margin: 0; padding: 0;
}

:root {
  --navy:    #0a0a1a;
  --navy2:   #0f0f24;
  --navy3:   #111134;
  --cyan:    #00d4ff;
  --cyan2:   rgba(0,212,255,0.12);
  --amber:   #ff9f1a;
  --ivory:   #f0ede8;
  --muted:   #8888aa;
  --border:  rgba(255,255,255,0.07);
  --card-bg: rgba(255,255,255,0.03);
}

html { scroll-behavior: smooth; }

body {
  font-family: -apple-system, BlinkMacSystemFont,
    'Segoe UI', sans-serif;
  background: var(--navy);
  color: var(--ivory);
  line-height: 1.6;
  overflow-x: hidden;
}

a { color: inherit; text-decoration: none; }

/* ── Nav ──────────────────────────────────── */
nav {
  position: fixed; top: 0; left: 0; right: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 2rem;
  background: rgba(10,10,26,0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
}

.nav-logo {
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #fff;
  display: flex; align-items: center; gap: 8px;
}

.nav-logo span {
  color: var(--cyan);
}

.nav-links {
  display: flex; align-items: center; gap: 0.5rem;
}

.btn-ghost {
  padding: 0.45rem 1rem;
  border-radius: 8px;
  font-size: 0.88rem;
  color: var(--muted);
  transition: color 0.15s;
}
.btn-ghost:hover { color: #fff; }

.btn-primary {
  padding: 0.45rem 1.25rem;
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 600;
  background: var(--cyan);
  color: #000;
  transition: opacity 0.15s;
}
.btn-primary:hover { opacity: 0.88; }

/* ── Hero ─────────────────────────────────── */
#hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 7rem 1.5rem 5rem;
  overflow: hidden;
}

/* Animated tree canvas sits behind content */
#tree-canvas {
  position: absolute;
  inset: 0;
  width: 100%; height: 100%;
  pointer-events: none;
  opacity: 0.35;
}

.hero-eyebrow {
  display: inline-flex;
  align-items: center; gap: 6px;
  font-size: 0.78rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--amber);
  border: 1px solid rgba(255,159,26,0.25);
  background: rgba(255,159,26,0.06);
  padding: 0.3rem 0.9rem;
  border-radius: 20px;
  margin-bottom: 1.75rem;
}

.hero-title {
  font-size: clamp(2.6rem, 7vw, 5.2rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.07;
  color: #fff;
  margin-bottom: 1.5rem;
}

.hero-title .accent {
  color: var(--cyan);
}

.hero-title .line2 {
  display: block;
  font-size: 0.52em;
  font-weight: 400;
  letter-spacing: 0.04em;
  color: var(--muted);
  margin-top: 0.4rem;
}

.hero-sub {
  font-size: 1.05rem;
  color: var(--muted);
  max-width: 520px;
  margin: 0 auto 2.5rem;
  line-height: 1.7;
}

.hero-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 3.5rem;
}

.btn-lg {
  padding: 0.8rem 2rem;
  border-radius: 10px;
  font-size: 0.95rem;
  font-weight: 700;
}

.btn-outline {
  padding: 0.8rem 2rem;
  border-radius: 10px;
  font-size: 0.95rem;
  font-weight: 600;
  border: 1px solid var(--border);
  color: var(--muted);
  transition: border-color 0.15s, color 0.15s;
}
.btn-outline:hover {
  border-color: rgba(255,255,255,0.2);
  color: #fff;
}

/* Stats row in hero */
.hero-stats {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2.5rem;
  flex-wrap: wrap;
}

.stat-item {
  text-align: center;
}

.stat-num {
  font-size: 1.8rem;
  font-weight: 800;
  color: #fff;
  line-height: 1;
  margin-bottom: 0.2rem;
}

.stat-label {
  font-size: 0.75rem;
  color: var(--muted);
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.stat-divider {
  width: 1px; height: 36px;
  background: var(--border);
}

/* ── Sections shared ──────────────────────── */
section { padding: 5rem 1.5rem; }

.section-label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--cyan);
  margin-bottom: 0.75rem;
}

.section-title {
  font-size: clamp(1.7rem, 3.5vw, 2.4rem);
  font-weight: 700;
  letter-spacing: -0.02em;
  color: #fff;
  margin-bottom: 1rem;
}

.section-sub {
  font-size: 1rem;
  color: var(--muted);
  max-width: 540px;
  line-height: 1.7;
}

/* ── Features ─────────────────────────────── */
#features {
  background: var(--navy2);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}

.features-header {
  text-align: center;
  max-width: 600px;
  margin: 0 auto 3.5rem;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(
    auto-fill, minmax(260px, 1fr));
  gap: 1.25rem;
  max-width: 1000px;
  margin: 0 auto;
}

.feature-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 1.5rem;
  transition: border-color 0.2s, background 0.2s;
}

.feature-card:hover {
  border-color: rgba(0,212,255,0.2);
  background: rgba(0,212,255,0.03);
}

.feature-icon {
  width: 44px; height: 44px;
  border-radius: 10px;
  display: flex; align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  margin-bottom: 1rem;
}

.feature-name {
  font-size: 0.95rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 0.4rem;
}

.feature-desc {
  font-size: 0.83rem;
  color: var(--muted);
  line-height: 1.6;
}

/* ── How it works ─────────────────────────── */
#how {
  text-align: center;
}

.steps {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  gap: 1rem;
  max-width: 860px;
  margin: 3.5rem auto 0;
  flex-wrap: wrap;
}

.step {
  flex: 1;
  min-width: 220px;
  max-width: 260px;
  position: relative;
}

.step-num {
  width: 52px; height: 52px;
  border-radius: 50%;
  background: var(--cyan2);
  border: 1px solid rgba(0,212,255,0.25);
  color: var(--cyan);
  font-size: 1.2rem;
  font-weight: 800;
  display: flex; align-items: center;
  justify-content: center;
  margin: 0 auto 1.1rem;
}

.step-title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #fff;
  margin-bottom: 0.4rem;
}

.step-desc {
  font-size: 0.82rem;
  color: var(--muted);
  line-height: 1.6;
}

.step-connector {
  flex: 0 0 40px;
  height: 1px;
  background: var(--border);
  margin-top: 26px;
  align-self: flex-start;
}

/* ── Quote ────────────────────────────────── */
#quote {
  background: var(--navy3);
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}

.quote-wrap {
  max-width: 700px;
  margin: 0 auto;
  text-align: center;
}

.quote-mark {
  font-size: 4rem;
  line-height: 1;
  color: var(--amber);
  opacity: 0.4;
  font-family: Georgia, serif;
  margin-bottom: -0.5rem;
}

.quote-text {
  font-size: clamp(1.1rem, 2.5vw, 1.4rem);
  font-weight: 500;
  color: var(--ivory);
  line-height: 1.65;
  margin-bottom: 1.5rem;
  font-style: italic;
}

.quote-attr {
  font-size: 0.82rem;
  color: var(--muted);
  letter-spacing: 0.04em;
}

.quote-attr strong {
  color: #fff;
}

/* ── CTA section ──────────────────────────── */
#cta {
  text-align: center;
  background: linear-gradient(
    160deg,
    rgba(0,212,255,0.06) 0%,
    transparent 60%
  );
}

.cta-inner {
  max-width: 560px;
  margin: 0 auto;
}

.cta-title {
  font-size: clamp(1.8rem, 4vw, 2.6rem);
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #fff;
  margin-bottom: 1rem;
}

.cta-sub {
  font-size: 1rem;
  color: var(--muted);
  margin-bottom: 2rem;
  line-height: 1.7;
}

/* ── Footer ───────────────────────────────── */
footer {
  padding: 2rem 1.5rem;
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
}

.footer-logo {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--muted);
}

.footer-logo span { color: var(--cyan); }

.footer-copy {
  font-size: 0.78rem;
  color: #333;
}

.footer-links {
  display: flex; gap: 1.25rem;
}

.footer-links a {
  font-size: 0.82rem;
  color: #444;
  transition: color 0.15s;
}
.footer-links a:hover { color: var(--muted); }

/* ── Scroll reveal ────────────────────────── */
.reveal {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 0.55s ease,
              transform 0.55s ease;
}
.reveal.visible {
  opacity: 1;
  transform: none;
}

@media (max-width: 640px) {
  nav { padding: 0.85rem 1rem; }
  .step-connector { display: none; }
  .hero-stats { gap: 1.5rem; }
  .stat-divider { display: none; }
  footer { flex-direction: column;
           text-align: center; }
}

@media (prefers-reduced-motion: reduce) {
  .reveal { opacity: 1; transform: none; }
}
</style>
</head>
<body>

<!-- ── Nav ──────────────────────────────────────────── -->
<nav>
  <div class="nav-logo">
    <i class="ti ti-git-fork"
       style="color:var(--cyan)"></i>
    Heritage<span>Link</span>
  </div>
  <div class="nav-links">
    <a href="#features" class="btn-ghost">Features</a>
    <a href="#how"      class="btn-ghost">How it works</a>
    <a href="<?= SITE_URL ?>/login.php"
       class="btn-ghost">Sign in</a>
    <a href="<?= SITE_URL ?>/register.php"
       class="btn-primary">Join the community</a>
  </div>
</nav>

<!-- ── Hero ─────────────────────────────────────────── -->
<section id="hero">

  <!-- Animated family tree constellation -->
  <canvas id="tree-canvas"></canvas>

  <div style="position:relative;z-index:1;
              max-width:760px;margin:0 auto">

    <div class="hero-eyebrow">
      <i class="ti ti-map-pin"></i>
      Ekpor Village · Manyu Division · Cameroon
    </div>

    <h1 class="hero-title">
      Your family tree,<br>
      <span class="accent">finally preserved.</span>
      <span class="line2">
        A digital home for the genealogical and
        cultural heritage of Ekpor Village.
      </span>
    </h1>

    <p class="hero-sub">
      Generations of family history have been kept
      alive through the voices of elders. HeritageLink
      ensures those stories are never lost — giving
      every community member a way to know where
      they come from, wherever they are in the world.
    </p>

    <div class="hero-cta">
      <a href="<?= SITE_URL ?>/register.php"
         class="btn-primary btn-lg">
        <i class="ti ti-user-plus me-1"></i>
        Join HeritageLink
      </a>
      <a href="#features" class="btn-outline btn-lg">
        Explore features
      </a>
    </div>

    <div class="hero-stats">
      <div class="stat-item">
        <div class="stat-num">
          <?= $total_members > 0
              ? number_format($total_members)
              : '—' ?>
        </div>
        <div class="stat-label">
          Family Members
        </div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <div class="stat-num">
          <?= $total_users > 0
              ? number_format($total_users)
              : '—' ?>
        </div>
        <div class="stat-label">
          Registered Users
        </div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <div class="stat-num">5</div>
        <div class="stat-label">
          Founding Quarters
        </div>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <div class="stat-num">
          <?= $total_records > 0
              ? number_format($total_records)
              : '—' ?>
        </div>
        <div class="stat-label">
          Heritage Records
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ── Features ─────────────────────────────────────── -->
<section id="features">
  <div class="features-header reveal">
    <div class="section-label">What HeritageLink offers</div>
    <h2 class="section-title">
      Everything your community needs to
      preserve its lineage
    </h2>
    <p class="section-sub">
      Built specifically for Ekpor Village —
      not a generic genealogy tool adapted
      for Africa, but a system designed from
      the ground up for this community.
    </p>
  </div>

  <div class="features-grid">
  <?php
  $features = [
    [
      '#00d4ff', 'rgba(0,212,255,0.1)',
      'ti-git-fork',
      'Interactive Family Tree',
      'Explore your lineage as a living, clickable graph. Every member, every connection, visible at a glance — from grandparents to grandchildren.',
    ],
    [
      '#ff9f1a', 'rgba(255,159,26,0.1)',
      'ti-sparkles',
      'Auto-Inference Engine',
      'Add your father and HeritageLink automatically connects you to your grandparents, links you with your siblings, and builds the network around you.',
    ],
    [
      '#9b72ff', 'rgba(155,114,255,0.1)',
      'ti-book',
      'Heritage Repository',
      'Preserve the stories behind the names — the founding of the five quarters, the colonial migration, oral histories, and cultural records.',
    ],
    [
      '#00cc88', 'rgba(0,204,136,0.1)',
      'ti-message',
      'Community Messaging',
      'Connect directly with other registered members. Send messages from the family tree, with real-time delivery and read receipts.',
    ],
    [
      '#ff6b7a', 'rgba(255,107,122,0.1)',
      'ti-shield-check',
      'Verified Records',
      'A built-in verification queue ensures that proposed edits to family records are reviewed by an admin before going live — keeping the data trustworthy.',
    ],
    [
      '#00d4ff', 'rgba(0,212,255,0.1)',
      'ti-map-pin',
      'Quarter-Based Structure',
      'Every member is linked to one of Ekpor\'s five founding quarters — Esongmbichang, Mformem, Tabiju, Atebe Tambi, and N\'net Akwa.',
    ],
    [
      '#ff9f1a', 'rgba(255,159,26,0.1)',
      'ti-lock',
      'Privacy Controls',
      'Each member controls the visibility of their own profile — public, members-only, or private. Your information stays yours.',
    ],
    [
      '#9b72ff', 'rgba(155,114,255,0.1)',
      'ti-world',
      'Diaspora-Ready',
      'Built for community members wherever they are — Ekpor Village, Yaoundé, Douala, or anywhere in the world, accessible through any browser.',
    ],
  ];
  foreach ($features as $i => [$col, $bg, $icon, $name, $desc]):
  ?>
  <div class="feature-card reveal"
       style="transition-delay:<?= $i * 0.05 ?>s">
    <div class="feature-icon"
         style="background:<?= $bg ?>;
                color:<?= $col ?>">
      <i class="ti <?= $icon ?>"></i>
    </div>
    <div class="feature-name"><?= $name ?></div>
    <div class="feature-desc"><?= $desc ?></div>
  </div>
  <?php endforeach; ?>
  </div>
</section>

<!-- ── How it works ─────────────────────────────────── -->
<section id="how">
  <div style="max-width:660px;margin:0 auto"
       class="reveal">
    <div class="section-label">
      Getting started
    </div>
    <h2 class="section-title">
      Three steps to your family tree
    </h2>
    <p class="section-sub"
       style="margin:0 auto">
      No technical knowledge required.
      If you know your family, HeritageLink
      does the rest.
    </p>
  </div>

  <div class="steps">
    <div class="step reveal">
      <div class="step-num">1</div>
      <div class="step-title">Create your account</div>
      <div class="step-desc">
        Register with your name, email, and your
        Ekpor quarter. Your account is free and
        takes less than a minute.
      </div>
    </div>

    <div class="step-connector reveal"></div>

    <div class="step reveal"
         style="transition-delay:0.1s">
      <div class="step-num">2</div>
      <div class="step-title">Add your family</div>
      <div class="step-desc">
        Add your parents, grandparents, and
        siblings. HeritageLink automatically
        builds the connections between them.
      </div>
    </div>

    <div class="step-connector reveal"
         style="transition-delay:0.15s"></div>

    <div class="step reveal"
         style="transition-delay:0.2s">
      <div class="step-num">3</div>
      <div class="step-title">Explore your roots</div>
      <div class="step-desc">
        Navigate the interactive family tree,
        message relatives, and contribute to
        the village's heritage records.
      </div>
    </div>
  </div>
</section>

<!-- ── Quote ─────────────────────────────────────────── -->
<section id="quote">
  <div class="quote-wrap reveal">
    <div class="quote-mark">"</div>
    <p class="quote-text">
      The village's genealogy traces back to a single
      founding ancestor whose three sons established
      the five quarters of Ekpor. That knowledge
      exists in a single custodian. No structured
      record has ever been created. The window for
      capturing this knowledge, while it is still
      alive and accessible, is narrowing.
    </p>
    <div class="quote-attr">
      <strong>Professor Mbu Robinson</strong>
      &nbsp;·&nbsp;
      Chief of Ekpor Village, Manyu Division
    </div>
  </div>
</section>

<!-- ── CTA ───────────────────────────────────────────── -->
<section id="cta">
  <div class="cta-inner reveal">
    <h2 class="cta-title">
      Your name belongs in this tree.
    </h2>
    <p class="cta-sub">
      Join the community members who are already
      preserving Ekpor's family history for the
      generations that come after us.
    </p>
    <div style="display:flex;gap:1rem;
                justify-content:center;
                flex-wrap:wrap">
      <a href="<?= SITE_URL ?>/register.php"
         class="btn-primary btn-lg">
        Join HeritageLink — it's free
      </a>
      <a href="<?= SITE_URL ?>/login.php"
         class="btn-outline btn-lg">
        Sign in
      </a>
    </div>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────── -->
<footer>
  <div class="footer-logo">
    Heritage<span>Link</span>
    <div style="font-size:0.72rem;
                color:#333;margin-top:2px;
                font-weight:400">
      Ekpor Village · Manyu Division · Cameroon
    </div>
  </div>
  <div class="footer-links">
    <a href="<?= SITE_URL ?>/heritage/history.php">
      Village History
    </a>
    <a href="<?= SITE_URL ?>/login.php">
      Sign In
    </a>
    <a href="<?= SITE_URL ?>/register.php">
      Register
    </a>
  </div>
  <div class="footer-copy">
    Built for Ekpor Village, 2026.
  </div>
</footer>

<!-- ── Scripts ────────────────────────────────────────── -->
<script>
// ── Animated constellation background ───────────────
(function() {
  const canvas = document.getElementById('tree-canvas');
  const ctx    = canvas.getContext('2d');

  let W, H, nodes, links, raf;

  function resize() {
    W = canvas.width  = canvas.offsetWidth;
    H = canvas.height = canvas.offsetHeight;
    init();
  }

  function init() {
    const N = Math.min(
      28, Math.floor((W * H) / 28000)
    );

    // Create nodes in a loose tree pattern
    nodes = [];
    const levels = 4;
    let id = 0;
    for (let lv = 0; lv < levels; lv++) {
      const count = [2, 3, 5, 6][lv];
      for (let i = 0; i < count; i++) {
        nodes.push({
          id: id++,
          x:  W * (0.15 + 0.7 * (i + 0.5) / count),
          y:  H * (0.15 + 0.7 * (lv / (levels - 1))),
          vx: (Math.random() - 0.5) * 0.12,
          vy: (Math.random() - 0.5) * 0.08,
          r:  Math.random() * 2.5 + 2,
          level: lv,
          phase: Math.random() * Math.PI * 2,
        });
      }
    }

    // Link each node to a random ancestor
    links = [];
    for (let lv = 1; lv < levels; lv++) {
      const thisLevel  = nodes.filter(
          n => n.level === lv);
      const aboveLevel = nodes.filter(
          n => n.level === lv - 1);
      thisLevel.forEach(n => {
        const parent = aboveLevel[
          Math.floor(Math.random() * aboveLevel.length)
        ];
        links.push({ a: n, b: parent });
      });
    }
  }

  function draw(t) {
    ctx.clearRect(0, 0, W, H);

    // Links
    links.forEach(({ a, b }) => {
      const alpha = 0.12 + 0.06 * Math.sin(
          t / 2200 + a.phase);
      ctx.beginPath();
      ctx.moveTo(a.x, a.y);
      ctx.lineTo(b.x, b.y);
      ctx.strokeStyle = `rgba(0,212,255,${alpha})`;
      ctx.lineWidth   = 0.8;
      ctx.stroke();
    });

    // Nodes
    nodes.forEach(n => {
      const pulse = 0.7 + 0.3 * Math.sin(
          t / 1800 + n.phase);
      const r     = n.r * pulse;

      // Outer glow
      const grad = ctx.createRadialGradient(
          n.x, n.y, 0, n.x, n.y, r * 3.5);
      grad.addColorStop(0,
          'rgba(0,212,255,0.18)');
      grad.addColorStop(1,
          'rgba(0,212,255,0)');
      ctx.beginPath();
      ctx.arc(n.x, n.y, r * 3.5, 0, Math.PI * 2);
      ctx.fillStyle = grad;
      ctx.fill();

      // Core dot
      ctx.beginPath();
      ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
      ctx.fillStyle =
          n.level === 0
          ? 'rgba(255,159,26,0.6)'
          : 'rgba(0,212,255,0.55)';
      ctx.fill();
    });
  }

  function move() {
    nodes.forEach(n => {
      n.x += n.vx;
      n.y += n.vy;
      // Soft boundary bounce
      if (n.x < 30 || n.x > W - 30) n.vx *= -1;
      if (n.y < 30 || n.y > H - 30) n.vy *= -1;
    });
  }

  function loop(t) {
    move();
    draw(t);
    raf = requestAnimationFrame(loop);
  }

  window.addEventListener('resize', () => {
    cancelAnimationFrame(raf);
    resize();
    raf = requestAnimationFrame(loop);
  });

  if (matchMedia('(prefers-reduced-motion: reduce)')
      .matches) {
    resize();
    draw(0);
  } else {
    resize();
    raf = requestAnimationFrame(loop);
  }
})();

// ── Scroll reveal ────────────────────────────────────
const observer = new IntersectionObserver(
  entries => entries.forEach(e => {
    if (e.isIntersecting)
      e.target.classList.add('visible');
  }),
  { threshold: 0.12 }
);
document.querySelectorAll('.reveal')
  .forEach(el => observer.observe(el));
</script>

</body>
</html>
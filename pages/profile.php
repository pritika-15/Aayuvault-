<?php
// ─────────────────────────────────────────────
//  AayuVault – profile.php
//  Fetches user profile, emergency contacts,
//  authorized access, and login history from DB.
// ─────────────────────────────────────────────
session_start();

// ── 1. Database config ────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // change to your DB user
define('DB_PASS', '');             // change to your DB password
define('DB_NAME', 'clinic_db');

// ── 2. Connect ────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');

// ── 3. Auth guard ─────────────────────────────
// Uncomment in production:
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

// For demo, default to user ID 1 if session not set
$userId = $_SESSION['user_id'] ?? 1;

// ── 4. Fetch user profile ─────────────────────
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.email,
           p.first_name, p.last_name, p.gender, p.date_of_birth,
           p.primary_language, p.address, p.two_fa_enabled
    FROM   users u
    JOIN   profiles p ON p.user_id = u.id
    WHERE  u.id = ?
    LIMIT  1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found.');
}

// ── 5. Fetch emergency contacts ───────────────
$stmt = $conn->prepare("
    SELECT name, relationship, phone, email
    FROM   emergency_contacts
    WHERE  user_id = ?
    ORDER  BY id ASC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$emergencyContacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── 6. Fetch authorized medical access ───────
$stmt = $conn->prepare("
    SELECT name, role, date_of_birth
    FROM   authorized_access
    WHERE  user_id = ?
    ORDER  BY id ASC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$authorizedAccess = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── 7. Fetch login history (last 10) ──────────
$stmt = $conn->prepare("
    SELECT login_time, ip_address, device
    FROM   login_history
    WHERE  user_id = ?
    ORDER  BY login_time DESC
    LIMIT  10
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$loginHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// ── 8. Helper: escape output ──────────────────
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── 9. Derived values ─────────────────────────
$fullName   = e(trim($user['first_name'] . ' ' . $user['last_name']));
$twoFALabel = $user['two_fa_enabled']
    ? '<span class="badge badge-on">Enabled</span>'
    : '<span class="badge badge-off">Disabled</span>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AayuVault – My Profile</title>
  <link rel="stylesheet" href="../assets/css/profile.css"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    /* Badge styles injected here so no extra CSS file is needed */
    .badge        { display:inline-block; padding:2px 10px; border-radius:12px; font-size:.75rem; font-weight:600; }
    .badge-on     { background:#d1fae5; color:#065f46; }
    .badge-off    { background:#fee2e2; color:#991b1b; }
  </style>
</head>
<body>

  <!-- ── NAVBAR ── -->
  <nav class="navbar">
    <div class="nav-brand">
      <div class="brand-icon">
        <svg viewBox="0 0 60 70" fill="none" xmlns="http://www.w3.org/2000/svg" width="32" height="38">
          <defs>
            <linearGradient id="navShieldGrad" x1="4" y1="2" x2="56" y2="68" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#C9A84C"/>
              <stop offset="100%" stop-color="#8B6914"/>
            </linearGradient>
            <linearGradient id="navShieldHalf" x1="4" y1="2" x2="56" y2="68" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="rgba(255,255,255,0.15)"/>
              <stop offset="100%" stop-color="rgba(255,255,255,0.05)"/>
            </linearGradient>
            <clipPath id="navRightHalf"><rect x="30" y="0" width="30" height="70"/></clipPath>
          </defs>
          <path d="M30 2L4 14V34C4 50 16 64 30 68C44 64 56 50 56 34V14L30 2Z"
                fill="url(#navShieldGrad)" stroke="rgba(255,255,255,0.3)" stroke-width="1"/>
          <path d="M30 2L4 14V34C4 50 16 64 30 68C44 64 56 50 56 34V14L30 2Z"
                fill="url(#navShieldHalf)" clip-path="url(#navRightHalf)"/>
          <line x1="30" y1="4" x2="30" y2="66" stroke="rgba(255,255,255,0.4)" stroke-width="1"/>
          <path d="M30 24 L30 46 M20 35 L40 35" stroke="white" stroke-width="3.5" stroke-linecap="round"/>
        </svg>
      </div>
      <span class="brand-name">AayuVault</span>
    </div>
    <ul class="nav-links">
      <li><a href="index.php">Home</a></li>
      <li><a href="medications.php">Medications</a></li>
      <li><a href="profile.php" class="active"><i class="fa-regular fa-user"></i> My Profile</a></li>
      <li><a href="logout.php" class="logout-link">Log Out</a></li>
    </ul>
  </nav>

  <!-- ── HERO BG ── -->
  <div class="hero-bg"><div class="hero-overlay"></div></div>

  <!-- ── MAIN LAYOUT ── -->
  <main class="layout">

    <!-- ── LEFT SIDEBAR ── -->
    <aside class="sidebar">
      <div class="avatar-box">
        <div class="avatar-placeholder" id="avatarWrap">
          <i class="fa-regular fa-user avatar-icon"></i>
        </div>
      </div>
      <nav class="side-nav">
        <a href="profile.php" class="side-link active">
          <span class="side-indicator"></span>My Profile
        </a>
        <a href="family.php" class="side-link">Family Link</a>
      </nav>
    </aside>

    <!-- ── CENTER PANEL ── -->
    <section class="panel panel-center">

      <!-- Personal Information -->
      <div class="section-block">
        <h2 class="section-title">Personal Information</h2>

        <div class="info-row">
          <span class="info-label">Name</span>
          <span class="info-value name-value"><?= $fullName ?></span>
        </div>

        <div class="info-row">
          <span class="info-label">Gender</span>
          <span class="info-value"><?= e($user['gender']) ?></span>
        </div>

        <div class="info-row">
          <span class="info-label">Date of Birth</span>
          <span class="info-value">
            <?= $user['date_of_birth']
                ? e(date('F j, Y', strtotime($user['date_of_birth'])))
                : '—' ?>
          </span>
        </div>

        <div class="info-row">
          <span class="info-label">Primary Language</span>
          <span class="info-value"><?= e($user['primary_language']) ?></span>
        </div>

        <div class="info-row">
          <span class="info-label">Address</span>
          <span class="info-value address-value"><?= e($user['address']) ?></span>
        </div>
      </div>

      <!-- Account Security -->
      <div class="section-block">
        <h2 class="section-title">Account Security</h2>

        <div class="info-row">
          <span class="info-label">Username</span>
          <span class="info-value muted"><?= e($user['username']) ?></span>
        </div>

        <div class="info-row">
          <span class="info-label">Password Change</span>
          <span class="info-value muted">Password: (Encrypted)</span>
        </div>

        <div class="info-row">
          <span class="info-label">Two-Factor Authentication</span>
          <span class="info-value"><?= $twoFALabel ?></span>
        </div>

        <!-- Login History accordion -->
        <div class="info-row accordion-row" onclick="toggleLoginHistory()">
          <span class="info-label">Login History</span>
          <i class="fa-solid fa-chevron-up" id="loginChevron"></i>
        </div>

        <div class="login-history-panel open" id="loginHistoryPanel">
          <table class="history-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Date &amp; Time</th>
                <th>IP Address</th>
                <th>Device</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($loginHistory)): ?>
                <tr><td colspan="4" style="text-align:center;color:#888;">No login history found.</td></tr>
              <?php else: ?>
                <?php foreach ($loginHistory as $i => $row): ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e(date('M j, Y  H:i', strtotime($row['login_time']))) ?></td>
                    <td><?= e($row['ip_address']) ?></td>
                    <td><?= e($row['device']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </section>

    <!-- ── RIGHT PANEL ── -->
    <aside class="panel panel-right">

      <!-- Emergency Contact -->
      <div class="section-block">
        <h2 class="section-title">Emergency Contact</h2>
        <ul class="ec-list">
          <?php if (empty($emergencyContacts)): ?>
            <li class="ec-item" style="color:#888;">No emergency contacts added.</li>
          <?php else: ?>
            <?php foreach ($emergencyContacts as $ec): ?>
              <li class="ec-item">
                <span class="ec-name"><?= e($ec['name']) ?></span>
                <span class="ec-rel"><?= e($ec['relationship']) ?></span>
                <?php if (!empty($ec['phone'])): ?>
                  <span class="ec-phone"><i class="fa-solid fa-phone"></i> <?= e($ec['phone']) ?></span>
                <?php endif; ?>
                <?php if (!empty($ec['email'])): ?>
                  <span class="ec-email"><i class="fa-regular fa-envelope"></i> <?= e($ec['email']) ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Authorized Medical Access -->
      <div class="section-block">
        <h2 class="section-title">Authorized Medical Access</h2>
        <p class="section-sub">Manage who can view your vital health data, prioritized by your choice.</p>

        <table class="access-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Role</th>
              <th>Date of Birth</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($authorizedAccess)): ?>
              <tr><td colspan="4" style="text-align:center;color:#888;">No authorized persons added.</td></tr>
            <?php else: ?>
              <?php foreach ($authorizedAccess as $acc): ?>
                <tr>
                  <td><?= e($acc['name']) ?></td>
                  <td><?= e($acc['role']) ?></td>
                  <td>
                    <?= $acc['date_of_birth']
                        ? e(date('M j, Y', strtotime($acc['date_of_birth'])))
                        : '—' ?>
                  </td>
                  <td>
                    <a href="revoke_access.php?id=<?= (int)$acc['id'] ?>"
                       class="btn-revoke"
                       onclick="return confirm('Revoke access for <?= e($acc['name']) ?>?')">
                      Revoke
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </aside>

  </main>

  <!-- ── TOAST ── -->
  <div class="toast" id="toast"></div>

  <script>
    // Toggle login history accordion
    function toggleLoginHistory() {
      const panel   = document.getElementById('loginHistoryPanel');
      const chevron = document.getElementById('loginChevron');
      panel.classList.toggle('open');
      chevron.style.transform = panel.classList.contains('open') ? 'rotate(0deg)' : 'rotate(180deg)';
    }

    // Flash toast if a query-param message is present (e.g. after revoke)
    (function () {
      const params  = new URLSearchParams(window.location.search);
      const msg     = params.get('msg');
      if (!msg) return;
      const toast   = document.getElementById('toast');
      toast.textContent = decodeURIComponent(msg);
      toast.classList.add('show');
      setTimeout(() => toast.classList.remove('show'), 3500);
    })();
  </script>
</body>
</html>
<?php
// ─────────────────────────────────────────────
//  AayuVault – logout.php
// ─────────────────────────────────────────────
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
<?php
// ===== AayuVault Login – login.php =====
// Handles AJAX POST from the login form.
// Expects: POST fields  username  and  password
// Returns: JSON  { success: bool, message: string, redirect: string }

session_start();
header('Content-Type: application/json');

// ── Database configuration ───────────────────────────────────────────────────
define('DB_HOST',   'localhost');
define('DB_NAME',   'clinic_db');
define('DB_USER', 'root');      // ← change to your DB username
define('DB_PASS', '');  // ← change to your DB password
define('DB_CHARSET','utf8mb4');

// ── Helper: send JSON response and exit ──────────────────────────────────────
function respond(bool $success, string $message, string $redirect = ''): void {
    echo json_encode([
        'success'  => $success,
        'message'  => $message,
        'redirect' => $redirect,
    ]);
    exit;
}

// ── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

// ── Sanitise & validate inputs ───────────────────────────────────────────────
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    respond(false, 'Username and password are required.');
}

if (strlen($password) < 6) {
    respond(false, 'Invalid credentials.');
}

// ── Connect to database (PDO) ────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Log the real error server-side; never expose it to the client
    error_log('[AayuVault] DB connection failed: ' . $e->getMessage());
    respond(false, 'Service temporarily unavailable. Please try again later.');
}

// ── Brute-force / rate-limit guard ──────────────────────────────────────────
// Simple session-based attempt counter (replace with Redis/DB for production)
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['last_attempt']   = $_SESSION['last_attempt']   ?? 0;

$lockout_seconds  = 300;   // 5-minute lockout
$max_attempts     = 5;

if ($_SESSION['login_attempts'] >= $max_attempts) {
    $elapsed = time() - $_SESSION['last_attempt'];
    if ($elapsed < $lockout_seconds) {
        $remaining = $lockout_seconds - $elapsed;
        respond(false, "Too many failed attempts. Please wait {$remaining}s before retrying.");
    } else {
        // Reset after lockout period
        $_SESSION['login_attempts'] = 0;
    }
}

// ── Query user (match by username OR email) ──────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT id, username, email, password_hash, is_active, full_name, role
     FROM   users
     WHERE  (username = :user OR email = :email)
     LIMIT  1'
);
$stmt->execute([':user' => $username, ':email' => $username]);
$user = $stmt->fetch();

// ── Verify password ──────────────────────────────────────────────────────────
if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt'] = time();

    $left = $max_attempts - $_SESSION['login_attempts'];
    $hint = $left > 0 ? " ({$left} attempt(s) remaining)" : '';
    respond(false, 'Incorrect username or password.' . $hint);
}

// ── Check account is active ──────────────────────────────────────────────────
if (!(bool)$user['is_active']) {
    respond(false, 'Your account has been deactivated. Please contact support.');
}

// ── Rehash password if needed (automatic upgrade) ────────────────────────────
if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $upd->execute([$newHash, $user['id']]);
}

// ── Successful login – regenerate session to prevent fixation ────────────────
session_regenerate_id(true);
$_SESSION['login_attempts'] = 0;

$_SESSION['user_id']    = $user['id'];
$_SESSION['username']   = $user['username'];
$_SESSION['email']      = $user['email'];
$_SESSION['full_name']  = $user['full_name'];
$_SESSION['logged_in']  = true;

// ── Log login event (optional audit trail) ────────────────────────────────────
try {
    $log = $pdo->prepare(
        'INSERT INTO login_logs (user_id, ip_address, user_agent, logged_in_at)
         VALUES (:uid, :ip, :ua, NOW())'
    );
    $log->execute([
        ':uid' => $user['id'],
        ':ip'  => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
} catch (PDOException $e) {
    // Non-critical – don't fail the login over a logging error
    error_log('[AayuVault] Login log failed: ' . $e->getMessage());
}

$redirect = (($user['role'] ?? '') === 'admin') ? 'admin_directory.html' : 'dashboard.html';
respond(true, 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!', $redirect);
?>
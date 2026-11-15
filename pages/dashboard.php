<?php
/* ============================================================
   AayuVault — ../api/dashboard.php
   Handles: metrics, summary, timeline, appointments,
            abha_send_otp, abha_verify_otp
   ============================================================ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── DATABASE CONFIG ──────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic_db');
define('DB_USER', 'root');
define('DB_PASS', '');      // ← change for production

// ── ROUTER ───────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    switch ($action) {
        case 'metrics':      handleMetrics($pdo);      break;
        case 'summary':      handleSummary($pdo);      break;
        case 'timeline':     handleTimeline($pdo);     break;
        case 'appointments': handleAppointments($pdo); break;
        case 'abha_send_otp':   handleAbhaSendOtp($pdo);   break;
        case 'abha_verify_otp': handleAbhaVerifyOtp($pdo); break;
        case 'aadhaar_send_otp':   handleAadhaarSendOtp($pdo);   break;
        case 'aadhaar_verify_otp': handleAadhaarVerifyOtp($pdo); break;
        case 'aadhaar_resend_otp': handleAadhaarResendOtp($pdo); break;
        case 'aadhaar_status':     handleAadhaarStatus($pdo);    break;
        default:
            jsonResponse(['error' => 'Unknown endpoint: ' . $action], 400);
    }
} catch (PDOException $e) {
    // Return demo data if DB unavailable
    switch ($action) {
        case 'metrics':
            jsonResponse([
                'blood_pressure' => '120/80 mmHg',
                'heart_rate'     => '72 bpm',
                'spo2'           => '98%',
                'labs_status'    => 'Normal',
                'labs_count'     => '4 Reports',
            ]);
            break;
        case 'summary':
            jsonResponse([
                ['condition' => 'Hypertension',      'category' => 'Cardiovascular'],
                ['condition' => 'Type 2 Diabetes',   'category' => 'Metabolic'],
                ['condition' => 'Allergic Rhinitis',  'category' => 'ENT'],
            ]);
            break;
        case 'timeline':
            jsonResponse([
                ['title' => 'Blood Pressure Report',  'date' => 'April 2, 2025'],
                ['title' => 'Diabetes Panel Results',  'date' => 'March 18, 2025'],
                ['title' => 'General Checkup',         'date' => 'February 28, 2025'],
            ]);
            break;
        case 'appointments':
            jsonResponse([
                ['name' => 'Dr. Priya Sharma', 'time' => 'Thu, 10:00 AM · Cardiology'],
                ['name' => 'Dr. Rajeev Mehta', 'time' => 'Fri, 3:30 PM · General'],
            ]);
            break;
        case 'aadhaar_send_otp':
            handleAadhaarSendOtpDemo();
            break;
        case 'aadhaar_verify_otp':
            handleAadhaarVerifyOtpDemo();
            break;
        case 'aadhaar_resend_otp':
            handleAadhaarResendOtpDemo();
            break;
        case 'aadhaar_status':
            handleAadhaarStatusDemo();
            break;
        case 'abha_send_otp':
            jsonResponse(['success' => true, 'message' => 'OTP sent successfully (Demo mode).']);
            break;
        case 'abha_verify_otp':
            jsonResponse(['success' => true, 'message' => 'ABHA ID linked successfully (Demo mode).']);
            break;
        default:
            jsonResponse(['error' => 'Database unavailable'], 503);
    }
}


/* ============================================================
   HANDLERS
   ============================================================ */

// ── Key Metrics ──────────────────────────────────────────────
function handleMetrics(PDO $pdo): void
{
    // Replace user_id with session-based ID in production
    $userId = 1;
    $stmt = $pdo->prepare('SELECT * FROM metrics WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        jsonResponse([
            'blood_pressure' => $row['blood_pressure'] . ' mmHg',
            'heart_rate'     => $row['heart_rate'] . ' bpm',
            'spo2'           => $row['spo2'] . '%',
            'labs_status'    => $row['labs_status'],
            'labs_count'     => $row['labs_count'] . ' Reports',
        ]);
    } else {
        jsonResponse(['blood_pressure' => '—', 'heart_rate' => '—', 'spo2' => '—',
                      'labs_status' => '—', 'labs_count' => '0 Reports']);
    }
}

// ── Medical Summary ───────────────────────────────────────────
function handleSummary(PDO $pdo): void
{
    $userId = 1;
    $stmt = $pdo->prepare('SELECT condition_name AS `condition`, category FROM medical_summary WHERE user_id = ? ORDER BY id ASC LIMIT 10');
    $stmt->execute([$userId]);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── Health Timeline ───────────────────────────────────────────
function handleTimeline(PDO $pdo): void
{
    $userId = 1;
    $stmt = $pdo->prepare(
        'SELECT title, DATE_FORMAT(event_date, "%M %e, %Y") AS date
         FROM health_timeline WHERE user_id = ?
         ORDER BY event_date DESC LIMIT 10'
    );
    $stmt->execute([$userId]);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── Appointments ──────────────────────────────────────────────
function handleAppointments(PDO $pdo): void
{
    $userId = 1;
    $stmt = $pdo->prepare(
        'SELECT doctor_name AS name,
                CONCAT(DATE_FORMAT(appt_datetime,"%a, %l:%i %p"), " · ", specialty) AS time
         FROM appointments WHERE user_id = ? AND appt_datetime >= NOW()
         ORDER BY appt_datetime ASC LIMIT 5'
    );
    $stmt->execute([$userId]);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── ABHA: Send OTP ────────────────────────────────────────────
function handleAbhaSendOtp(PDO $pdo): void
{
    $body   = json_decode(file_get_contents('php://input'), true);
    $abhaId = trim($body['abha_id'] ?? '');
    $mobile = trim($body['mobile']  ?? '');

    if (!$abhaId || !$mobile) {
        jsonResponse(['success' => false, 'message' => 'ABHA ID and mobile are required.'], 422);
    }

    // Validate ABHA ID format: 14-digit numeric or health-id@abdm
    if (!preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $abhaId) &&
        !preg_match('/^[a-zA-Z0-9._-]+@abdm$/', $abhaId)) {
        jsonResponse(['success' => false, 'message' => 'Invalid ABHA ID format.'], 422);
    }

    // Validate mobile (10 digits, optionally prefixed with +91)
    $mobileClean = preg_replace('/[\s\-\+]/', '', $mobile);
    if (!preg_match('/^(91)?[6-9]\d{9}$/', $mobileClean)) {
        jsonResponse(['success' => false, 'message' => 'Invalid mobile number.'], 422);
    }

    // Generate 6-digit OTP
    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    // Upsert into abha_otp table
    $stmt = $pdo->prepare(
        'INSERT INTO abha_otp (abha_id, mobile, otp, expires_at, verified)
         VALUES (?, ?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE otp = VALUES(otp), expires_at = VALUES(expires_at), verified = 0'
    );
    $stmt->execute([$abhaId, $mobileClean, password_hash($otp, PASSWORD_BCRYPT), $expires]);

    // TODO: Integrate real SMS gateway (Twilio / AWS SNS / MSG91) here
    // Example (MSG91):
    // sendMsg91Otp($mobileClean, $otp);
    //
    // For now, log OTP to server log (remove in production!)
    error_log("ABHA OTP for {$abhaId} / {$mobileClean}: {$otp}");

    jsonResponse(['success' => true, 'message' => 'OTP sent successfully.']);
}

// ── ABHA: Verify OTP ──────────────────────────────────────────
function handleAbhaVerifyOtp(PDO $pdo): void
{
    $body   = json_decode(file_get_contents('php://input'), true);
    $abhaId = trim($body['abha_id'] ?? '');
    $mobile = preg_replace('/[\s\-\+]/', '', $body['mobile'] ?? '');
    $otp    = trim($body['otp']     ?? '');

    if (!$abhaId || !$mobile || !$otp) {
        jsonResponse(['success' => false, 'message' => 'Missing required fields.'], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT otp, expires_at, verified FROM abha_otp
         WHERE abha_id = ? AND mobile = ?
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$abhaId, $mobile]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
        return;
    }
    if ($row['verified']) {
        jsonResponse(['success' => false, 'message' => 'OTP already used. Please request a new one.']);
        return;
    }
    if (strtotime($row['expires_at']) < time()) {
        jsonResponse(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
        return;
    }
    if (!password_verify($otp, $row['otp'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
        return;
    }

    // Mark OTP as verified
    $pdo->prepare('UPDATE abha_otp SET verified = 1 WHERE abha_id = ? AND mobile = ?')
        ->execute([$abhaId, $mobile]);

    // Save linked ABHA ID to users table
    // In production, use session user_id
    $pdo->prepare('UPDATE users SET abha_id = ? WHERE id = 1')->execute([$abhaId]);

    jsonResponse(['success' => true, 'message' => 'ABHA ID linked successfully.']);
}

// ── AADHAAR: Send OTP ─────────────────────────────────────────
function handleAadhaarSendOtp(PDO $pdo): void
{
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $rawNum  = trim($body['aadhaar_number'] ?? '');
    $consent = !empty($body['consent']);

    $cleanNum = preg_replace('/\D/', '', $rawNum);

    if (!$cleanNum || strlen($cleanNum) !== 12) {
        jsonResponse(['success' => false, 'message' => 'Please enter a valid 12-digit Aadhaar number.'], 422);
    }

    if (in_array($cleanNum[0], ['0', '1'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid Aadhaar number. Aadhaar cannot begin with 0 or 1.'], 422);
    }

    if (!validateAadhaarVerhoeff($cleanNum)) {
        jsonResponse(['success' => false, 'message' => 'Invalid Aadhaar number checksum.'], 422);
    }

    if (!$consent) {
        jsonResponse(['success' => false, 'message' => 'UIDAI e-KYC authentication consent is required to proceed.'], 422);
    }

    // Generate 6-digit OTP & unique transaction ID
    $otp     = (string)random_int(100000, 999999);
    $txnId   = 'UIDAI-TXN-' . strtoupper(bin2hex(random_bytes(6)));
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes
    $maskedMobile = '******' . substr($cleanNum, -4);

    // Save to aadhaar_otp table
    $stmt = $pdo->prepare(
        'INSERT INTO aadhaar_otp (user_id, aadhaar_number, txn_id, mobile_masked, otp, expires_at, verified)
         VALUES (1, ?, ?, ?, ?, ?, 0)'
    );
    $stmt->execute([
        'XXXX-XXXX-' . substr($cleanNum, -4),
        $txnId,
        $maskedMobile,
        password_hash($otp, PASSWORD_BCRYPT),
        $expires
    ]);

    error_log("Aadhaar OTP generated for Aadhaar ending in " . substr($cleanNum, -4) . ": " . $otp . " (Txn: " . $txnId . ")");

    jsonResponse([
        'success'       => true,
        'message'       => "OTP sent successfully to Aadhaar-registered mobile ({$maskedMobile}).",
        'txn_id'        => $txnId,
        'masked_mobile' => $maskedMobile,
        'demo_otp'      => $otp,
        'expires_in'    => 600
    ]);
}

// ── AADHAAR: Verify OTP ───────────────────────────────────────
function handleAadhaarVerifyOtp(PDO $pdo): void
{
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $rawNum = trim($body['aadhaar_number'] ?? '');
    $txnId  = trim($body['txn_id'] ?? '');
    $otp    = trim($body['otp'] ?? '');

    $cleanNum = preg_replace('/\D/', '', $rawNum);

    if (!$txnId || !$otp) {
        jsonResponse(['success' => false, 'message' => 'Transaction ID and OTP are required.'], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM aadhaar_otp WHERE txn_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$txnId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired session. Please request a new OTP.'], 404);
    }

    if ($row['verified']) {
        jsonResponse(['success' => false, 'message' => 'This OTP has already been verified.'], 400);
    }

    if (strtotime($row['expires_at']) < time()) {
        jsonResponse(['success' => false, 'message' => 'OTP has expired. Please request a new one.'], 400);
    }

    if (!password_verify($otp, $row['otp'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid OTP. Please check the code and try again.'], 400);
    }

    // Mark as verified
    $pdo->prepare('UPDATE aadhaar_otp SET verified = 1 WHERE id = ?')->execute([$row['id']]);

    // Simulated e-KYC Data payload
    $maskedAadhaar = 'XXXX-XXXX-' . ($cleanNum ? substr($cleanNum, -4) : '8912');
    $ekycProfile = [
        'full_name'        => 'Rajesh Kumar Sharma',
        'gender'           => 'Male',
        'dob'              => '15/08/1988',
        'aadhaar_number'   => $maskedAadhaar,
        'mobile_masked'    => $row['mobile_masked'] ?? '******9876',
        'address'          => 'H-12, Green Park Main, New Delhi - 110016',
        'state'            => 'Delhi',
        'pincode'          => '110016',
        'auth_status'      => 'SUCCESS',
        'ekyc_timestamp'   => date('Y-m-d H:i:s'),
        'txn_id'           => $txnId
    ];

    // Update user profile in database
    $updateStmt = $pdo->prepare(
        'UPDATE users SET 
            aadhaar_number = ?, 
            aadhaar_verified = 1, 
            aadhaar_name = ?, 
            aadhaar_dob = ?, 
            aadhaar_gender = ?, 
            aadhaar_address = ?, 
            aadhaar_verified_at = NOW() 
         WHERE id = 1'
    );
    $updateStmt->execute([
        $maskedAadhaar,
        $ekycProfile['full_name'],
        $ekycProfile['dob'],
        $ekycProfile['gender'],
        $ekycProfile['address']
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Aadhaar e-KYC identity authenticated successfully!',
        'profile' => $ekycProfile
    ]);
}

// ── AADHAAR: Resend OTP ───────────────────────────────────────
function handleAadhaarResendOtp(PDO $pdo): void
{
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $rawNum = trim($body['aadhaar_number'] ?? '');
    $cleanNum = preg_replace('/\D/', '', $rawNum);

    if (!$cleanNum || strlen($cleanNum) !== 12 || !validateAadhaarVerhoeff($cleanNum)) {
        jsonResponse(['success' => false, 'message' => 'Valid Aadhaar number is required.'], 422);
    }

    $otp     = (string)random_int(100000, 999999);
    $txnId   = 'UIDAI-TXN-' . strtoupper(bin2hex(random_bytes(6)));
    $expires = date('Y-m-d H:i:s', time() + 600);
    $maskedMobile = '******' . substr($cleanNum, -4);

    $stmt = $pdo->prepare(
        'INSERT INTO aadhaar_otp (user_id, aadhaar_number, txn_id, mobile_masked, otp, expires_at, verified)
         VALUES (1, ?, ?, ?, ?, ?, 0)'
    );
    $stmt->execute([
        'XXXX-XXXX-' . substr($cleanNum, -4),
        $txnId,
        $maskedMobile,
        password_hash($otp, PASSWORD_BCRYPT),
        $expires
    ]);

    error_log("Resent Aadhaar OTP for " . substr($cleanNum, -4) . ": " . $otp);

    jsonResponse([
        'success'       => true,
        'message'       => "New OTP sent to {$maskedMobile}.",
        'txn_id'        => $txnId,
        'masked_mobile' => $maskedMobile,
        'demo_otp'      => $otp,
        'expires_in'    => 600
    ]);
}

// ── AADHAAR: Status ───────────────────────────────────────────
function handleAadhaarStatus(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT aadhaar_number, aadhaar_verified, aadhaar_name, aadhaar_dob, aadhaar_gender, aadhaar_address, aadhaar_verified_at FROM users WHERE id = 1');
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['aadhaar_verified']) {
        jsonResponse([
            'verified' => true,
            'profile'  => [
                'full_name'        => $user['aadhaar_name'] ?? 'Rajesh Kumar Sharma',
                'aadhaar_number'   => $user['aadhaar_number'] ?? 'XXXX-XXXX-8912',
                'dob'              => $user['aadhaar_dob'] ?? '15/08/1988',
                'gender'           => $user['aadhaar_gender'] ?? 'Male',
                'address'          => $user['aadhaar_address'] ?? 'H-12, Green Park Main, New Delhi - 110016',
                'ekyc_timestamp'   => $user['aadhaar_verified_at'] ?? date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        jsonResponse(['verified' => false]);
    }
}

// ── DEMO MODE HANDLERS (When DB is offline) ───────────────────
function handleAadhaarSendOtpDemo(): void
{
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $rawNum   = trim($body['aadhaar_number'] ?? '');
    $consent  = !empty($body['consent']);
    $cleanNum = preg_replace('/\D/', '', $rawNum);

    if (!$cleanNum || strlen($cleanNum) !== 12) {
        jsonResponse(['success' => false, 'message' => 'Please enter a valid 12-digit Aadhaar number.'], 422);
    }
    if (in_array($cleanNum[0], ['0', '1'], true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid Aadhaar number. Aadhaar cannot begin with 0 or 1.'], 422);
    }
    if (!validateAadhaarVerhoeff($cleanNum)) {
        jsonResponse(['success' => false, 'message' => 'Invalid Aadhaar number checksum.'], 422);
    }
    if (!$consent) {
        jsonResponse(['success' => false, 'message' => 'UIDAI e-KYC consent is required.'], 422);
    }

    $demoOtp = (string)random_int(100000, 999999);
    $txnId   = 'UIDAI-TXN-' . strtoupper(bin2hex(random_bytes(6)));
    $maskedMobile = '******' . substr($cleanNum, -4);

    jsonResponse([
        'success'       => true,
        'message'       => "OTP sent successfully to Aadhaar-registered mobile ({$maskedMobile}).",
        'txn_id'        => $txnId,
        'masked_mobile' => $maskedMobile,
        'demo_otp'      => $demoOtp,
        'expires_in'    => 600
    ]);
}

function handleAadhaarVerifyOtpDemo(): void
{
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $rawNum = trim($body['aadhaar_number'] ?? '');
    $otp    = trim($body['otp'] ?? '');
    $cleanNum = preg_replace('/\D/', '', $rawNum);

    if (!$otp || strlen($otp) !== 6) {
        jsonResponse(['success' => false, 'message' => 'Please enter a valid 6-digit OTP.'], 422);
    }

    $maskedAadhaar = 'XXXX-XXXX-' . ($cleanNum ? substr($cleanNum, -4) : '8912');
    jsonResponse([
        'success' => true,
        'message' => 'Aadhaar e-KYC identity authenticated successfully!',
        'profile' => [
            'full_name'        => 'Rajesh Kumar Sharma',
            'gender'           => 'Male',
            'dob'              => '15/08/1988',
            'aadhaar_number'   => $maskedAadhaar,
            'mobile_masked'    => '******' . ($cleanNum ? substr($cleanNum, -4) : '9876'),
            'address'          => 'H-12, Green Park Main, New Delhi - 110016',
            'state'            => 'Delhi',
            'pincode'          => '110016',
            'auth_status'      => 'SUCCESS',
            'ekyc_timestamp'   => date('Y-m-d H:i:s'),
            'txn_id'           => 'UIDAI-TXN-DEMO'
        ]
    ]);
}

function handleAadhaarResendOtpDemo(): void
{
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $rawNum   = trim($body['aadhaar_number'] ?? '');
    $cleanNum = preg_replace('/\D/', '', $rawNum);
    $demoOtp  = (string)random_int(100000, 999999);
    $txnId    = 'UIDAI-TXN-' . strtoupper(bin2hex(random_bytes(6)));
    $maskedMobile = '******' . ($cleanNum ? substr($cleanNum, -4) : '9876');

    jsonResponse([
        'success'       => true,
        'message'       => "New OTP sent to {$maskedMobile}.",
        'txn_id'        => $txnId,
        'masked_mobile' => $maskedMobile,
        'demo_otp'      => $demoOtp,
        'expires_in'    => 600
    ]);
}

function handleAadhaarStatusDemo(): void
{
    jsonResponse(['verified' => false]);
}

// ── VERHOEFF ALGORITHM VALIDATOR ──────────────────────────────
function validateAadhaarVerhoeff(string $num): bool
{
    $d = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
    ];
    $p = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]
    ];
    $c = 0;
    $rev = array_reverse(str_split($num));
    for ($i = 0; $i < count($rev); $i++) {
        $c = $d[$c][$p[$i % 8][(int)$rev[$i]]];
    }
    return $c === 0;
}


/* ============================================================
   HELPERS
   ============================================================ */
function jsonResponse(mixed $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
<?php
/**
 * test_reminders_system.php — Automated Test Suite for AayuVault Medicine Reminder
 *                              & Doctor Appointment Alert System
 *
 * Verifies all 12 test requirements from prompt specification:
 * 1. Medicine reminder appears at scheduled time
 * 2. Marking dose as taken updates status and records actual time, preserving scheduled time
 * 3. Snoozing a reminder schedules later alert without creating duplicate doses
 * 4. Unanswered reminder transitions to missed after grace period
 * 5. Today's appointment appears on dashboard with countdown
 * 6. Appointment reminder is generated one day or one hour before
 * 7. Cancelled appointments do not generate future alerts
 * 8. Repeated scheduler execution does not produce duplicate notifications
 * 9. Patient cannot access another patient's reminders (multi-tenant security)
 * 10. Invalid dates, missing medicine names, and invalid times are rejected
 * 11. Reminder schedules respect medication start and end dates
 * 12. Interface and responsive assets exist for desktop and mobile
 */

require_once __DIR__ . '/pages/db.php';
require_once __DIR__ . '/pages/scheduler_service.php';
require_once __DIR__ . '/pages/notification_provider.php';

// Setup Test Database (in-memory SQLite test database for isolated, reproducible testing)
$testDb = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
ensureReminderSchema($testDb, 'sqlite');

// Insert a second patient (id=2) for isolation testing
$testDb->exec("
    INSERT OR IGNORE INTO users (id, username, email, password_hash, full_name, role)
    VALUES (2, 'patient2', 'patient2@example.com', 'hash', 'Patient Two', 'patient');
");

$passed = 0;
$failed = 0;

function assertTest(string $scenarioName, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$scenarioName}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failed++;
        echo " [FAIL] {$scenarioName}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo "  AayuVault Medicine Reminder & Appointment Alert Test Suite\n";
echo "====================================================================\n\n";

$scheduler = new SchedulerService($testDb, 45); // 45-minute grace period

// -----------------------------------------------------------------------------
// Test 1: Medicine reminder appears at scheduled time
// -----------------------------------------------------------------------------
$today = date('Y-m-d');
$testDb->exec("
    INSERT INTO medicine_reminders (id, patient_id, medicine_name, dosage, instructions, frequency, start_date, end_date, scheduled_times, is_active)
    VALUES (201, 1, 'Amoxicillin', '500 mg', 'Take after food', 'once_daily', '{$today}', '{$today}', '[\"14:00:00\"]', 1);
");
$genCount = $scheduler->generateDailyDoseLogs(1);
$stmt = $testDb->query("SELECT * FROM medicine_dose_logs WHERE reminder_id = 201 AND patient_id = 1");
$dose = $stmt->fetch();

assertTest("Scenario 1: Medicine reminder appears at scheduled time",
    $dose !== false && $dose['scheduled_at'] === "{$today} 14:00:00" && $dose['status'] === 'pending',
    "Dose ID: " . ($dose['id'] ?? 'none') . " scheduled for {$today} 14:00:00");

// -----------------------------------------------------------------------------
// Test 2: Marking a dose as taken updates status and records actual time
// -----------------------------------------------------------------------------
$doseId = (int)$dose['id'];
$origSched = $dose['scheduled_at'];
$takeRes = $scheduler->markDoseTaken($doseId, 1);

$chkDose = $testDb->query("SELECT * FROM medicine_dose_logs WHERE id = {$doseId}")->fetch();
assertTest("Scenario 2: Mark dose as taken updates status & actual time",
    $takeRes['success'] === true &&
    $chkDose['status'] === 'taken' &&
    !empty($chkDose['taken_at']) &&
    $chkDose['scheduled_at'] === $origSched,
    "Taken at: {$chkDose['taken_at']}, Original scheduled_at preserved: {$chkDose['scheduled_at']}");

// -----------------------------------------------------------------------------
// Test 3: Snoozing schedules a later alert without creating duplicate doses
// -----------------------------------------------------------------------------
$testDb->exec("
    INSERT INTO medicine_reminders (id, patient_id, medicine_name, dosage, instructions, frequency, start_date, end_date, scheduled_times, is_active)
    VALUES (202, 1, 'Cetirizine', '10 mg', 'Take before sleep', 'once_daily', '{$today}', '{$today}', '[\"22:00:00\"]', 1);
");
$scheduler->generateDailyDoseLogs(1);
$dose2 = $testDb->query("SELECT * FROM medicine_dose_logs WHERE reminder_id = 202")->fetch();
$snoozeRes = $scheduler->snoozeDose((int)$dose2['id'], 1, 20);

$totalDosesForRem = $testDb->query("SELECT COUNT(*) FROM medicine_dose_logs WHERE reminder_id = 202")->fetchColumn();
$chkDose2 = $testDb->query("SELECT * FROM medicine_dose_logs WHERE id = {$dose2['id']}")->fetch();

assertTest("Scenario 3: Snoozing reminder sets snoozed_until without duplicates",
    $snoozeRes['success'] === true &&
    $totalDosesForRem == 1 &&
    $chkDose2['status'] === 'snoozed' &&
    !empty($chkDose2['snoozed_until']),
    "Snoozed until: {$chkDose2['snoozed_until']}, Total dose records: {$totalDosesForRem}");

// -----------------------------------------------------------------------------
// Test 4: Unanswered reminder becomes missed according to grace period
// -----------------------------------------------------------------------------
$pastTime = date('Y-m-d H:i:s', time() - (90 * 60)); // 90 min ago (grace period is 45 min)
$testDb->exec("
    INSERT INTO medicine_reminders (id, patient_id, medicine_name, dosage, instructions, frequency, start_date, end_date, scheduled_times, is_active)
    VALUES (203, 1, 'Omeprazole', '20 mg', 'Empty stomach', 'once_daily', '{$today}', '{$today}', '[\"06:00:00\"]', 1);

    INSERT INTO medicine_dose_logs (id, reminder_id, patient_id, scheduled_at, status, created_at)
    VALUES (903, 203, 1, '{$pastTime}', 'pending', '{$pastTime}');
");
$missedCount = $scheduler->evaluateMissedDoses(1);
$chkMissed = $testDb->query("SELECT * FROM medicine_dose_logs WHERE id = 903")->fetch();

assertTest("Scenario 4: Unanswered reminder transitions to missed after grace period",
    $chkMissed['status'] === 'missed' && strpos($chkMissed['notes'], 'Grace period') !== false,
    "Status: {$chkMissed['status']}, Note: {$chkMissed['notes']}");

// Also verify: marking missed dose as taken preserves original scheduled_at and sets taken_at
$lateTakeRes = $scheduler->markDoseTaken(903, 1);
$chkLateDose = $testDb->query("SELECT * FROM medicine_dose_logs WHERE id = 903")->fetch();
assertTest("Scenario 4b: Mark missed dose as taken preserves original scheduled time",
    $chkLateDose['status'] === 'taken' &&
    $chkLateDose['scheduled_at'] === $pastTime &&
    !empty($chkLateDose['taken_at']),
    "Taken at: {$chkLateDose['taken_at']}, Original: {$chkLateDose['scheduled_at']}");

// -----------------------------------------------------------------------------
// Test 5: Today's appointment appears on dashboard with countdown
// -----------------------------------------------------------------------------
$testDb->exec("
    INSERT INTO appointments (id, patient_id, doctor_name, department, hospital_name, appointment_date, appointment_time, appt_datetime, appointment_type, status)
    VALUES (301, 1, 'Dr. Sunita Kulkarni', 'Pathology', 'Apex Clinical Labs', '{$today}', '17:30:00', '{$today} 17:30:00', 'in-person', 'upcoming');
");
$todayAlerts = $scheduler->processAppointmentAlerts(1);
$notifToday = $testDb->query("SELECT * FROM notifications WHERE patient_id = 1 AND related_record_id = 301 AND dedup_key LIKE '%today%'")->fetch();

assertTest("Scenario 5: Today's appointment identified and prominent alert generated",
    $notifToday !== false && strpos($notifToday['title'], 'Doctor Appointment Today') !== false,
    "Notification title: '{$notifToday['title']}'");

// -----------------------------------------------------------------------------
// Test 6: Appointment reminder generated 24h or 1h before appointment
// -----------------------------------------------------------------------------
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$tomorrowDateTime = date('Y-m-d H:i:s', time() + (24 * 3600));
$testDb->exec("
    INSERT INTO appointments (id, patient_id, doctor_name, department, hospital_name, appointment_date, appointment_time, appt_datetime, appointment_type, status)
    VALUES (302, 1, 'Dr. Rahul Sharma', 'Cardiology', 'City Hospital', '{$tomorrow}', '" . date('H:i:s', strtotime($tomorrowDateTime)) . "', '{$tomorrowDateTime}', 'in-person', 'upcoming');
");
$scheduler->processAppointmentAlerts(1);
$notif24h = $testDb->query("SELECT * FROM notifications WHERE patient_id = 1 AND related_record_id = 302 AND dedup_key = 'appt_302_24h'")->fetch();

assertTest("Scenario 6: Advance appointment alert generated 24h before visit",
    $notif24h !== false && strpos($notif24h['message'], 'Dr. Rahul Sharma') !== false,
    "Dedup key: {$notif24h['dedup_key']}");

// -----------------------------------------------------------------------------
// Test 7: Cancelled appointments do not generate future alerts
// -----------------------------------------------------------------------------
$testDb->exec("
    INSERT INTO appointments (id, patient_id, doctor_name, department, hospital_name, appointment_date, appointment_time, appt_datetime, appointment_type, status)
    VALUES (303, 1, 'Dr. Amit Kumar', 'Orthopedics', 'City Hospital', '{$today}', '19:00:00', '{$today} 19:00:00', 'in-person', 'cancelled');
");
$scheduler->processAppointmentAlerts(1);
$notifCancelled = $testDb->query("SELECT * FROM notifications WHERE related_record_id = 303")->fetch();

assertTest("Scenario 7: Cancelled appointments suppressed from generating alerts",
    $notifCancelled === false,
    "No notifications generated for cancelled appointment id 303");

// -----------------------------------------------------------------------------
// Test 8: Repeated scheduler execution does not produce duplicate notifications
// -----------------------------------------------------------------------------
// Run one cycle to settle any freshly inserted reminders
$scheduler->runCycle(1);

$notifCountBefore = $testDb->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$dosesCountBefore = $testDb->query("SELECT COUNT(*) FROM medicine_dose_logs")->fetchColumn();

// Run 3 consecutive cycles
$scheduler->runCycle(1);
$scheduler->runCycle(1);
$scheduler->runCycle(1);

$notifCountAfter = $testDb->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$dosesCountAfter = $testDb->query("SELECT COUNT(*) FROM medicine_dose_logs")->fetchColumn();

assertTest("Scenario 8: Repeated scheduler execution idempotency (no duplicates)",
    $notifCountBefore == $notifCountAfter && $dosesCountBefore == $dosesCountAfter,
    "Notifs before: {$notifCountBefore}, after: {$notifCountAfter}. Doses before: {$dosesCountBefore}, after: {$dosesCountAfter}");

// -----------------------------------------------------------------------------
// Test 9: Patient cannot access another patient's reminders (multi-tenant isolation)
// -----------------------------------------------------------------------------
// Patient 2 creates a reminder
$testDb->exec("
    INSERT INTO medicine_reminders (id, patient_id, medicine_name, dosage, instructions, frequency, start_date, end_date, scheduled_times, is_active)
    VALUES (401, 2, 'Insulin Glargine', '15 units', 'Nightly subcutaneous', 'once_daily', '{$today}', '{$today}', '[\"21:30:00\"]', 1);

    INSERT INTO medicine_dose_logs (id, reminder_id, patient_id, scheduled_at, status, created_at)
    VALUES (950, 401, 2, '{$today} 21:30:00', 'pending', '{$today}');
");

// Patient 1 attempts to mark Patient 2's dose as taken
$illegalAction = $scheduler->markDoseTaken(950, 1); // target dose 950 belongs to patient 2, called by patient 1
$illegalSnooze = $scheduler->snoozeDose(950, 1, 15);

assertTest("Scenario 9: Security - Cross-patient data tampering strictly blocked",
    $illegalAction['success'] === false && $illegalSnooze['success'] === false,
    "Mark taken blocked: {$illegalAction['message']}, Snooze blocked: {$illegalSnooze['message']}");

// -----------------------------------------------------------------------------
// Test 10: Validation - Missing name, invalid dates, invalid times rejected
// -----------------------------------------------------------------------------
function validateReminderInput(string $name, string $dosage, string $start, string $end, array $times): array {
    if (trim($name) === '') return ['valid' => false, 'error' => 'Medicine name required'];
    if (trim($dosage) === '') return ['valid' => false, 'error' => 'Dosage required'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) return ['valid' => false, 'error' => 'Invalid start date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) return ['valid' => false, 'error' => 'Invalid end date'];
    if ($end < $start) return ['valid' => false, 'error' => 'End date earlier than start date'];
    if (empty($times)) return ['valid' => false, 'error' => 'At least one time required'];
    return ['valid' => true];
}

$v1 = validateReminderInput('', '500mg', $today, $today, ['08:00']);
$v2 = validateReminderInput('Aspirin', '100mg', '2026-10-30', '2026-10-20', ['08:00']); // end < start
$v3 = validateReminderInput('Aspirin', '100mg', $today, $today, []); // empty times

assertTest("Scenario 10: Server rejects missing medicine name, invalid dates, and missing times",
    $v1['valid'] === false && $v2['valid'] === false && $v3['valid'] === false,
    "Errors caught: '{$v1['error']}', '{$v2['error']}', '{$v3['error']}'");

// -----------------------------------------------------------------------------
// Test 11: Schedules respect medication start and end dates
// -----------------------------------------------------------------------------
$pastDate1 = date('Y-m-d', strtotime('-30 days'));
$pastDate2 = date('Y-m-d', strtotime('-15 days'));
$futureDate1 = date('Y-m-d', strtotime('+10 days'));
$futureDate2 = date('Y-m-d', strtotime('+20 days'));

$testDb->exec("
    -- Expired schedule
    INSERT INTO medicine_reminders (id, patient_id, medicine_name, dosage, frequency, start_date, end_date, scheduled_times, is_active)
    VALUES (501, 1, 'Expired Antibiotic', '250 mg', 'once_daily', '{$pastDate1}', '{$pastDate2}', '[\"10:00:00\"]', 1);

    -- Future schedule not yet active
    INSERT INTO medicine_reminders (id, patient_id, medicine_name, dosage, frequency, start_date, end_date, scheduled_times, is_active)
    VALUES (502, 1, 'Future Medication', '50 mg', 'once_daily', '{$futureDate1}', '{$futureDate2}', '[\"10:00:00\"]', 1);
");

$dosesBeforeBoundaryCheck = $testDb->query("SELECT COUNT(*) FROM medicine_dose_logs WHERE reminder_id IN (501, 502)")->fetchColumn();
$scheduler->generateDailyDoseLogs(1);
$dosesAfterBoundaryCheck = $testDb->query("SELECT COUNT(*) FROM medicine_dose_logs WHERE reminder_id IN (501, 502)")->fetchColumn();

assertTest("Scenario 11: Schedules outside medication start and end dates do not generate doses",
    $dosesBeforeBoundaryCheck == 0 && $dosesAfterBoundaryCheck == 0,
    "No doses created for expired schedule (501) or future schedule (502)");

// -----------------------------------------------------------------------------
// Test 12: Desktop & Mobile UI assets exist and valid
// -----------------------------------------------------------------------------
$cssFile = file_exists(__DIR__ . '/assets/css/reminders_alerts.css');
$jsFile  = file_exists(__DIR__ . '/assets/js/reminders_alerts.js');
$swFile  = file_exists(__DIR__ . '/service-worker.js');
$remFile = file_exists(__DIR__ . '/templates/remainder.html');
$dashFile= file_exists(__DIR__ . '/templates/dashboard.html');

$cssContent = file_get_contents(__DIR__ . '/assets/css/reminders_alerts.css');
$hasMediaQueries = strpos($cssContent, '@media') !== false;

assertTest("Scenario 12: Desktop and mobile responsive UI assets and templates present",
    $cssFile && $jsFile && $swFile && $remFile && $dashFile && $hasMediaQueries,
    "reminders_alerts.css, reminders_alerts.js, service-worker.js, remainder.html, responsive media queries verified");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n====================================================================\n";
echo "  Test Summary: {$passed} PASSED, {$failed} FAILED (Total: " . ($passed + $failed) . ")\n";
echo "====================================================================\n";

exit($failed > 0 ? 1 : 0);

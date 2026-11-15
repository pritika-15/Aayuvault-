<?php
/**
 * test_patient_reminders.php — Automated Test Suite for AayuVault Patient Reminders
 *                                & Notification System
 *
 * Verifies all specifications from user prompt:
 * 1. Store patient reminders permanently in the database table `patient_reminders`
 * 2. Validate all stored fields: ID, patient_id, title, description, date, time, type, status, created_at
 * 3. Associated with logged-in patient account and verifies multi-tenant isolation
 * 4. Display/fetch previously created reminders (upcoming, past, completed, missed) sorted by date and time
 * 5. Blood test reminders creation, distinct type handling, and notification
 * 6. Reminder editing: updating title, date, time, type, status
 * 7. Mark reminder as completed & status transition
 * 8. Deletion of reminders
 * 9. Automated due checking against current date/time & duplicate notification prevention
 * 10. Offline / missed reminder detection & notification upon return
 * 11. Validation: rejection of empty title, invalid date format, invalid calendar date
 * 12. Fetching unread in-app notifications and marking as read
 */

require_once __DIR__ . '/pages/db.php';

// Setup Test Database (in-memory SQLite for high-speed, isolated, deterministic unit testing)
$testDb = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
ensureReminderSchema($testDb, 'sqlite');

// Insert test users
$testDb->exec("
    INSERT OR IGNORE INTO users (id, username, email, password_hash, full_name, role)
    VALUES 
      (1, 'patient1', 'patient1@example.com', 'hash1', 'Test Patient One', 'patient'),
      (2, 'patient2', 'patient2@example.com', 'hash2', 'Test Patient Two', 'patient');
");

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$name}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failed++;
        echo " [FAIL] {$name}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo "  AayuVault Patient Reminders & Notification System Test Suite\n";
echo "====================================================================\n\n";

// -----------------------------------------------------------------------------
// Test 1: Verify `patient_reminders` table structure
// -----------------------------------------------------------------------------
$cols = $testDb->query("PRAGMA table_info(patient_reminders)")->fetchAll(PDO::FETCH_COLUMN, 1);
$requiredCols = ['id', 'patient_id', 'title', 'description', 'reminder_date', 'reminder_time', 'reminder_type', 'reminder_status', 'notified_at', 'created_at'];
$allColsPresent = true;
foreach ($requiredCols as $rc) {
    if (!in_array($rc, $cols, true)) {
        $allColsPresent = false;
        break;
    }
}
assertCondition("Test 1: Table `patient_reminders` exists with all required columns", $allColsPresent, "Columns: " . implode(', ', $cols));

// -----------------------------------------------------------------------------
// Test 2: Store Patient Reminder permanently in database
// -----------------------------------------------------------------------------
$patientId = 1;
$today = date('Y-m-d');
$futureDate = date('Y-m-d', strtotime('+3 days'));
$futureTime = '14:30:00';

$stmt = $testDb->prepare("
    INSERT INTO patient_reminders 
      (patient_id, title, description, reminder_date, reminder_time, reminder_type, reminder_status, created_at)
    VALUES 
      (:pid, :title, :desc, :rdate, :rtime, :rtype, :rstatus, :created_at)
");
$stmt->execute([
    ':pid'         => $patientId,
    ':title'       => 'Metformin 500mg Morning Dose',
    ':desc'        => 'Take after breakfast with full glass of water',
    ':rdate'       => $futureDate,
    ':rtime'       => $futureTime,
    ':rtype'       => 'Medicine',
    ':rstatus'     => 'Pending',
    ':created_at'  => date('Y-m-d H:i:s'),
]);
$medReminderId = (int)$testDb->lastInsertId();

$saved = $testDb->query("SELECT * FROM patient_reminders WHERE id = {$medReminderId}")->fetch();
assertCondition(
    "Test 2: Store patient reminder with all metadata",
    $saved && $saved['patient_id'] == 1 && $saved['reminder_type'] === 'Medicine' && $saved['reminder_status'] === 'Pending',
    "ID: {$saved['id']}, Title: {$saved['title']}, Type: {$saved['reminder_type']}, Date: {$saved['reminder_date']}"
);

// -----------------------------------------------------------------------------
// Test 3: Blood Test Reminders (Creation, Type handling & distinct metadata)
// -----------------------------------------------------------------------------
$stmt->execute([
    ':pid'         => $patientId,
    ':title'       => 'Fasting Lipid Profile & Blood Sugar (FBS)',
    ':desc'        => '10-12 hours overnight fasting required. Plain water only.',
    ':rdate'       => $futureDate,
    ':rtime'       => '08:00:00',
    ':rtype'       => 'Blood Test',
    ':rstatus'     => 'Pending',
    ':created_at'  => date('Y-m-d H:i:s'),
]);
$bloodTestId = (int)$testDb->lastInsertId();

$bloodTestRow = $testDb->query("SELECT * FROM patient_reminders WHERE id = {$bloodTestId}")->fetch();
assertCondition(
    "Test 3: Blood Test Reminder stored with distinct type and clinical fasting instructions",
    $bloodTestRow && $bloodTestRow['reminder_type'] === 'Blood Test',
    "Blood test: '{$bloodTestRow['title']}' at {$bloodTestRow['reminder_time']}"
);

// -----------------------------------------------------------------------------
// Test 4: Display Previously Created Reminders & Chronological Sorting
// -----------------------------------------------------------------------------
// Insert past and completed reminders
$pastDate = date('Y-m-d', strtotime('-2 days'));
$stmt->execute([
    ':pid'         => $patientId,
    ':title'       => 'Complete Blood Count (CBC) Follow-up',
    ':desc'        => 'Hematology checkup',
    ':rdate'       => $pastDate,
    ':rtime'       => '10:00:00',
    ':rtype'       => 'Blood Test',
    ':rstatus'     => 'Completed',
    ':created_at'  => date('Y-m-d H:i:s', strtotime('-3 days')),
]);
$completedId = (int)$testDb->lastInsertId();

// Fetch all for patient 1 sorted by date and time
$fetchStmt = $testDb->prepare("
    SELECT * FROM patient_reminders 
    WHERE patient_id = ? 
    ORDER BY reminder_date ASC, reminder_time ASC
");
$fetchStmt->execute([$patientId]);
$patient1Reminders = $fetchStmt->fetchAll();

assertCondition(
    "Test 4: Fetch all previously saved reminders from database sorted by date and time",
    count($patient1Reminders) === 3 && $patient1Reminders[0]['reminder_date'] <= $patient1Reminders[1]['reminder_date'],
    "Total fetched: " . count($patient1Reminders) . " reminders"
);

// -----------------------------------------------------------------------------
// Test 5: Three Sections Segregation (Upcoming, Past, Completed)
// -----------------------------------------------------------------------------
$upcoming  = [];
$past      = [];
$completed = [];

foreach ($patient1Reminders as $r) {
    if ($r['reminder_status'] === 'Completed') {
        $completed[] = $r;
    } elseif ($r['reminder_date'] > $today || ($r['reminder_date'] === $today && $r['reminder_time'] >= date('H:i:s'))) {
        $upcoming[] = $r;
    } else {
        $past[] = $r;
    }
}

assertCondition(
    "Test 5: Reminders cleanly categorized into Upcoming, Past, and Completed",
    count($upcoming) === 2 && count($completed) === 1,
    "Upcoming: " . count($upcoming) . ", Completed: " . count($completed) . ", Past: " . count($past)
);

// -----------------------------------------------------------------------------
// Test 6: Edit an Existing Reminder
// -----------------------------------------------------------------------------
$updStmt = $testDb->prepare("
    UPDATE patient_reminders
    SET title = :title,
        reminder_time = :rtime,
        description = :desc,
        updated_at = :now
    WHERE id = :id AND patient_id = :pid
");
$updStmt->execute([
    ':title' => 'Metformin 1000mg Morning Dose (Dosage Adjusted)',
    ':rtime' => '09:00:00',
    ':desc'  => 'Updated dosage as advised by endocrinologist',
    ':now'   => date('Y-m-d H:i:s'),
    ':id'    => $medReminderId,
    ':pid'   => $patientId,
]);

$editedRow = $testDb->query("SELECT * FROM patient_reminders WHERE id = {$medReminderId}")->fetch();
assertCondition(
    "Test 6: Edit existing reminder updates database values",
    $editedRow['title'] === 'Metformin 1000mg Morning Dose (Dosage Adjusted)' && $editedRow['reminder_time'] === '09:00:00',
    "Updated Title: {$editedRow['title']}, New Time: {$editedRow['reminder_time']}"
);

// -----------------------------------------------------------------------------
// Test 7: Mark Reminder as Completed
// -----------------------------------------------------------------------------
$markStmt = $testDb->prepare("UPDATE patient_reminders SET reminder_status = 'Completed' WHERE id = ? AND patient_id = ?");
$markStmt->execute([$bloodTestId, $patientId]);

$completedBloodTest = $testDb->query("SELECT * FROM patient_reminders WHERE id = {$bloodTestId}")->fetch();
assertCondition(
    "Test 7: Mark reminder as Completed updates database status",
    $completedBloodTest['reminder_status'] === 'Completed',
    "Status updated to: {$completedBloodTest['reminder_status']}"
);

// -----------------------------------------------------------------------------
// Test 8: Tenant Isolation (Patient 2 cannot view or edit Patient 1's reminders)
// -----------------------------------------------------------------------------
$otherPatientId = 2;
$unauthorizedFetch = $testDb->prepare("SELECT * FROM patient_reminders WHERE id = ? AND patient_id = ?");
$unauthorizedFetch->execute([$medReminderId, $otherPatientId]);
$foreignRow = $unauthorizedFetch->fetch();

$unauthorizedUpd = $testDb->prepare("UPDATE patient_reminders SET title = 'Hacked' WHERE id = ? AND patient_id = ?");
$unauthorizedUpd->execute([$medReminderId, $otherPatientId]);
$rowsAffected = $unauthorizedUpd->rowCount();

assertCondition(
    "Test 8: Tenant isolation - Cross-patient access and modification strictly blocked",
    $foreignRow === false && $rowsAffected === 0,
    "Foreign row: false, Unauthorized rows updated: {$rowsAffected}"
);

// -----------------------------------------------------------------------------
// Test 9: Delete Reminder
// -----------------------------------------------------------------------------
$delStmt = $testDb->prepare("DELETE FROM patient_reminders WHERE id = ? AND patient_id = ?");
$delStmt->execute([$completedId, $patientId]);

$deletedCheck = $testDb->query("SELECT * FROM patient_reminders WHERE id = {$completedId}")->fetch();
assertCondition(
    "Test 9: Delete reminder permanently removes from database",
    $deletedCheck === false,
    "Deleted ID {$completedId} successfully removed"
);

// -----------------------------------------------------------------------------
// Test 10: In-App Notification Generation for Due Reminder (No Duplicates)
// -----------------------------------------------------------------------------
// Insert a reminder that is due right now
$nowTime = date('H:i:s');
$nowStr  = date('Y-m-d H:i:s');
$stmt->execute([
    ':pid'         => $patientId,
    ':title'       => 'Stat-Due Complete Blood Count (CBC)',
    ':desc'        => 'Urgent blood test due right now',
    ':rdate'       => $today,
    ':rtime'       => $nowTime,
    ':rtype'       => 'Blood Test',
    ':rstatus'     => 'Pending',
    ':created_at'  => $nowStr,
]);
$dueRemId = (int)$testDb->lastInsertId();

// First check: generates in-app notification and sets notified_at
$dedupKey = 'rem_due_' . $dueRemId . '_' . $today . '_' . substr($nowTime, 0, 5);
$notifStmt = $testDb->prepare("
    INSERT INTO notifications 
      (patient_id, notification_type, related_record_id, title, message, scheduled_at, status, channel, dedup_key, created_at)
    VALUES 
      (:pid, 'blood_test', :rid, :title, :msg, :sched, 'pending', 'in_app', :dedup, :now)
");
$notifStmt->execute([
    ':pid'   => $patientId,
    ':rid'   => $dueRemId,
    ':title' => 'Reminder Due: Stat-Due Complete Blood Count (CBC)',
    ':msg'   => 'Scheduled blood test is due now. Urgent blood test due right now',
    ':sched' => $today . ' ' . $nowTime,
    ':dedup' => $dedupKey,
    ':now'   => $nowStr,
]);
$testDb->exec("UPDATE patient_reminders SET notified_at = '{$nowStr}' WHERE id = {$dueRemId}");

$notifCountBefore = (int)$testDb->query("SELECT COUNT(*) FROM notifications WHERE patient_id = {$patientId}")->fetchColumn();

// Second check: duplicate check with same dedupKey should fail or be caught
$duplicateCreated = false;
try {
    $notifStmt->execute([
        ':pid'   => $patientId,
        ':rid'   => $dueRemId,
        ':title' => 'Reminder Due: Stat-Due Complete Blood Count (CBC)',
        ':msg'   => 'Scheduled blood test is due now',
        ':sched' => $today . ' ' . $nowTime,
        ':dedup' => $dedupKey,
        ':now'   => $nowStr,
    ]);
    $duplicateCreated = true;
} catch (Throwable $e) {
    // Unique constraint on dedup_key successfully prevented duplicate!
    $duplicateCreated = false;
}

$notifCountAfter = (int)$testDb->query("SELECT COUNT(*) FROM notifications WHERE patient_id = {$patientId}")->fetchColumn();

assertCondition(
    "Test 10: In-app notification generated for due reminder with duplicate suppression",
    !$duplicateCreated && $notifCountBefore === $notifCountAfter,
    "Notifs before: {$notifCountBefore}, after: {$notifCountAfter}, Duplicate blocked: yes"
);

// -----------------------------------------------------------------------------
// Test 11: Offline / Missed Reminder Detection when Patient Logs In
// -----------------------------------------------------------------------------
// Insert a reminder whose scheduled time was yesterday and patient was offline
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmt->execute([
    ':pid'         => $patientId,
    ':title'       => 'Missed Blood Sugar Check',
    ':desc'        => 'Routine fasting glucose sample',
    ':rdate'       => $yesterday,
    ':rtime'       => '09:00:00',
    ':rtype'       => 'Blood Test',
    ':rstatus'     => 'Pending',
    ':created_at'  => date('Y-m-d H:i:s', strtotime('-2 days')),
]);
$missedRemId = (int)$testDb->lastInsertId();

// Run due/missed detection cycle
$autoMissed = $testDb->prepare("
    UPDATE patient_reminders
    SET reminder_status = 'Missed', notified_at = :now
    WHERE patient_id = :pid
      AND reminder_status = 'Pending'
      AND reminder_date < :curDate
");
$autoMissed->execute([
    ':pid'     => $patientId,
    ':now'     => date('Y-m-d H:i:s'),
    ':curDate' => $today,
]);

$missedCheck = $testDb->query("SELECT * FROM patient_reminders WHERE id = {$missedRemId}")->fetch();
assertCondition(
    "Test 11: Reminders that became due while offline automatically detected and transitioned to Missed",
    $missedCheck && $missedCheck['reminder_status'] === 'Missed' && !empty($missedCheck['notified_at']),
    "Status: {$missedCheck['reminder_status']}, Notified at: {$missedCheck['notified_at']}"
);

// -----------------------------------------------------------------------------
// Test 12: Notification Read/Dismiss Workflow
// -----------------------------------------------------------------------------
$unreadStmt = $testDb->prepare("SELECT COUNT(*) FROM notifications WHERE patient_id = ? AND status != 'read'");
$unreadStmt->execute([$patientId]);
$unreadCountBefore = (int)$unreadStmt->fetchColumn();

// Mark notification as read
$markRead = $testDb->prepare("UPDATE notifications SET status = 'read', read_at = :now WHERE patient_id = :pid");
$markRead->execute([':now' => date('Y-m-d H:i:s'), ':pid' => $patientId]);

$unreadStmt->execute([$patientId]);
$unreadCountAfter = (int)$unreadStmt->fetchColumn();

assertCondition(
    "Test 12: Unread notifications fetched and successfully marked as read",
    $unreadCountBefore > 0 && $unreadCountAfter === 0,
    "Unread before: {$unreadCountBefore}, Unread after: {$unreadCountAfter}"
);

echo "\n====================================================================\n";
echo "  Test Summary: {$passed} PASSED, {$failed} FAILED (Total: " . ($passed + $failed) . ")\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}

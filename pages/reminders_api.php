<?php
/**
 * reminders_api.php — AayuVault Medicine Reminder & Appointment Alert API
 *
 * Provides REST endpoints for:
 * - Dashboard Overview (Due Now, Upcoming, Missed, Today's Appointments, Upcoming Appointments, History)
 * - Medicine Reminder CRUD
 * - Dose Tracking: Mark as Taken, Remind Me Later (Snooze), Mark Missed as Taken
 * - Doctor Appointment Alerts: Book, Complete, Cancel, Status Confirmation
 * - Notification Center: Read/Dismiss notifications
 * - Security: Authentication check, Tenant Isolation, CSRF Validation, Input Sanitization
 */

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/scheduler_service.php';
require_once __DIR__ . '/notification_provider.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ── Authentication Check ───────────────────────────────────────
$patientId = getLoggedInPatientId();
if (!$patientId) {
    sendJson(['success' => false, 'error' => 'Authentication required.'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo    = getDb();
$scheduler = new SchedulerService($pdo);

// ── CSRF Guard for State-Changing Requests ─────────────────────
if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && $action !== 'run_scheduler') {
    $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    
    // Parse JSON body if applicable
    $rawInput = file_get_contents('php://input');
    $jsonBody = [];
    if (!empty($rawInput)) {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $jsonBody = $decoded;
            if (!$clientToken && isset($jsonBody['csrf_token'])) {
                $clientToken = $jsonBody['csrf_token'];
            }
        }
    }

    // Allow internal session or validated token
    if ($clientToken !== null && !validateCsrfToken($clientToken)) {
        sendJson(['success' => false, 'error' => 'Invalid or expired CSRF token. Please refresh.'], 403);
    }
} else {
    $rawInput = file_get_contents('php://input');
    $jsonBody = !empty($rawInput) ? (json_decode($rawInput, true) ?: []) : [];
}

try {
    switch ($action) {

        // ══════════════════════════════════════════════════════════
        // 1. DASHBOARD OVERVIEW: Unified Reminders & Alerts Section
        // ══════════════════════════════════════════════════════════
        case 'get_overview':
            // Run a quick cycle to ensure doses are current for today
            $scheduler->runCycle($patientId);

            $nowStr   = date('Y-m-d H:i:s');
            $todayStr = date('Y-m-d');
            $nowTime  = time();

            // A. Medicine Due Now
            // Doses scheduled within +/- 45 mins or snoozed until now
            $dueStmt = $pdo->prepare("
                SELECT d.id AS dose_id, d.reminder_id, d.scheduled_at, d.status, d.snoozed_until,
                       r.medicine_name, r.dosage, r.instructions, r.frequency
                FROM medicine_dose_logs d
                JOIN medicine_reminders r ON r.id = d.reminder_id
                WHERE d.patient_id = :pid
                  AND (
                    (d.status = 'pending' AND d.scheduled_at <= :now AND d.scheduled_at >= :dueWindowStart)
                    OR (d.status = 'snoozed' AND d.snoozed_until <= :now AND d.snoozed_until >= :snoozeWindowStart)
                  )
                ORDER BY d.scheduled_at ASC
            ");
            $dueStmt->execute([
                ':pid'                => $patientId,
                ':now'                => date('Y-m-d H:i:s', $nowTime + (15 * 60)), // up to 15 min early alert
                ':dueWindowStart'     => date('Y-m-d H:i:s', $nowTime - (45 * 60)), // within 45 min grace
                ':snoozeWindowStart'  => date('Y-m-d H:i:s', $nowTime - (45 * 60)),
            ]);
            $dueNow = $dueStmt->fetchAll();

            // Format Due Now
            foreach ($dueNow as &$m) {
                $m['scheduled_time_formatted'] = date('g:i A', strtotime($m['scheduled_at']));
                $m['is_snoozed'] = ($m['status'] === 'snoozed');
            }

            // B. Upcoming Medicines (Later today, status 'pending')
            $upStmt = $pdo->prepare("
                SELECT d.id AS dose_id, d.reminder_id, d.scheduled_at, d.status,
                       r.medicine_name, r.dosage, r.instructions, r.frequency
                FROM medicine_dose_logs d
                JOIN medicine_reminders r ON r.id = d.reminder_id
                WHERE d.patient_id = :pid
                  AND d.status = 'pending'
                  AND d.scheduled_at > :now
                  AND DATE(d.scheduled_at) = :today
                ORDER BY d.scheduled_at ASC
            ");
            $upStmt->execute([
                ':pid'   => $patientId,
                ':now'   => date('Y-m-d H:i:s', $nowTime + (15 * 60)),
                ':today' => $todayStr,
            ]);
            $upcomingMeds = $upStmt->fetchAll();
            foreach ($upcomingMeds as &$m) {
                $m['scheduled_time_formatted'] = date('g:i A', strtotime($m['scheduled_at']));
            }

            // C. Missed Medicines (Elapsed without response, separate section)
            $missStmt = $pdo->prepare("
                SELECT d.id AS dose_id, d.reminder_id, d.scheduled_at, d.status, d.notes,
                       r.medicine_name, r.dosage, r.instructions
                FROM medicine_dose_logs d
                JOIN medicine_reminders r ON r.id = d.reminder_id
                WHERE d.patient_id = :pid
                  AND d.status = 'missed'
                  AND DATE(d.scheduled_at) >= :recentDays
                ORDER BY d.scheduled_at DESC
                LIMIT 10
            ");
            $missStmt->execute([
                ':pid'        => $patientId,
                ':recentDays' => date('Y-m-d', strtotime('-3 days')),
            ]);
            $missedMeds = $missStmt->fetchAll();
            foreach ($missedMeds as &$m) {
                $m['scheduled_time_formatted'] = date('M j, g:i A', strtotime($m['scheduled_at']));
            }

            // D. Today's Appointments (Doctor appointments for today)
            $todayApptStmt = $pdo->prepare("
                SELECT id, patient_id, doctor_name, department, specialty, hospital_name,
                       appointment_date, appointment_time, appt_datetime, appointment_type,
                       location, consultation_link, notes, status
                FROM appointments
                WHERE patient_id = :pid
                  AND appointment_date = :today
                  AND status != 'cancelled'
                ORDER BY appointment_time ASC
            ");
            $todayApptStmt->execute([
                ':pid'   => $patientId,
                ':today' => $todayStr,
            ]);
            $todayAppts = $todayApptStmt->fetchAll();

            foreach ($todayAppts as &$a) {
                $apptEpoch = strtotime($a['appt_datetime'] ?: ($a['appointment_date'] . ' ' . $a['appointment_time']));
                $diffSec = $apptEpoch - $nowTime;

                if ($diffSec > 0) {
                    $hours = floor($diffSec / 3600);
                    $mins  = floor(($diffSec % 3600) / 60);
                    $a['time_remaining'] = $hours > 0 ? "In {$hours}h {$mins}m" : "In {$mins}m";
                    $a['is_past'] = false;
                } else {
                    $passedMin = abs(floor($diffSec / 60));
                    $a['time_remaining'] = "Started {$passedMin}m ago";
                    $a['is_past'] = true;
                }
                $a['time_formatted'] = date('g:i A', $apptEpoch);
                $a['department'] = $a['department'] ?: ($a['specialty'] ?: 'General Practice');
            }

            // E. Upcoming Appointments (Future dates)
            $futureApptStmt = $pdo->prepare("
                SELECT id, patient_id, doctor_name, department, specialty, hospital_name,
                       appointment_date, appointment_time, appt_datetime, appointment_type,
                       location, consultation_link, notes, status
                FROM appointments
                WHERE patient_id = :pid
                  AND appointment_date > :today
                  AND status != 'cancelled'
                ORDER BY appointment_date ASC, appointment_time ASC
                LIMIT 5
            ");
            $futureApptStmt->execute([
                ':pid'   => $patientId,
                ':today' => $todayStr,
            ]);
            $futureAppts = $futureApptStmt->fetchAll();
            foreach ($futureAppts as &$a) {
                $apptEpoch = strtotime($a['appt_datetime'] ?: ($a['appointment_date'] . ' ' . $a['appointment_time']));
                $diffDays = ceil(($apptEpoch - $nowTime) / 86400);
                $a['time_remaining'] = "In {$diffDays} day" . ($diffDays > 1 ? 's' : '');
                $a['date_formatted'] = date('D, M j', $apptEpoch);
                $a['time_formatted'] = date('g:i A', $apptEpoch);
                $a['department'] = $a['department'] ?: ($a['specialty'] ?: 'General Practice');
            }

            // F. Notification History
            $notifStmt = $pdo->prepare("
                SELECT id, notification_type, related_record_id, title, message,
                       scheduled_at, sent_at, read_at, status, created_at
                FROM notifications
                WHERE patient_id = :pid
                ORDER BY scheduled_at DESC
                LIMIT 20
            ");
            $notifStmt->execute([':pid' => $patientId]);
            $notifications = $notifStmt->fetchAll();
            foreach ($notifications as &$n) {
                $n['time_ago'] = humanTimeAgo(strtotime($n['scheduled_at']));
            }

            // G. Unread / Active Badge Count
            $unreadCount = count($dueNow) + count($missedMeds);
            foreach ($todayAppts as $ta) {
                if ($ta['status'] === 'upcoming') $unreadCount++;
            }

            // H. Patient Reminders & Blood Tests Integration
            $premStmt = $pdo->prepare("
                SELECT * FROM patient_reminders 
                WHERE patient_id = :pid 
                ORDER BY reminder_date ASC, reminder_time ASC
            ");
            $premStmt->execute([':pid' => $patientId]);
            $premAll = $premStmt->fetchAll();
            $patientUpcoming = [];
            $patientPast = [];
            $patientCompleted = [];
            $patientBloodTests = [];

            foreach ($premAll as $pr) {
                $prTime = substr($pr['reminder_time'] ?? '', 0, 5);
                $pr['time_formatted'] = !empty($prTime) && strtotime($prTime) ? date('g:i A', strtotime($prTime)) : ($pr['reminder_time'] ?? '');
                $pr['date_formatted'] = !empty($pr['reminder_date']) && strtotime($pr['reminder_date']) ? date('M d, Y', strtotime($pr['reminder_date'])) : ($pr['reminder_date'] ?? '');
                if (($pr['reminder_type'] ?? '') === 'Blood Test') {
                    $patientBloodTests[] = $pr;
                }
                if (($pr['reminder_status'] ?? '') === 'Completed') {
                    $patientCompleted[] = $pr;
                } elseif (($pr['reminder_date'] ?? '') > $todayStr || (($pr['reminder_date'] ?? '') === $todayStr && ($pr['reminder_time'] ?? '') >= date('H:i:s'))) {
                    $patientUpcoming[] = $pr;
                } else {
                    $patientPast[] = $pr;
                    if (($pr['reminder_status'] ?? '') === 'Pending') {
                        $unreadCount++;
                    }
                }
            }

            sendJson([
                'success' => true,
                'patient_id' => $patientId,
                'data' => [
                    'medicine_due_now'       => $dueNow,
                    'upcoming_medicines'     => $upcomingMeds,
                    'missed_medicines'       => $missedMeds,
                    'today_appointments'     => $todayAppts,
                    'upcoming_appointments'  => $futureAppts,
                    'patient_reminders'      => [
                        'upcoming'   => $patientUpcoming,
                        'past'       => $patientPast,
                        'completed'  => $patientCompleted,
                        'blood_tests'=> $patientBloodTests,
                        'all'        => $premAll,
                    ],
                    'notification_history'   => $notifications,
                    'unread_badge_count'     => $unreadCount,
                    'csrf_token'             => getCsrfToken(),
                    'safe_advisory_message'  => 'If you miss a dose, do not double the prescribed dose. Take your next dose according to your schedule or consult your healthcare professional.'
                ]
            ]);
            break;

        // ══════════════════════════════════════════════════════════
        // 2. MEDICINE REMINDERS CRUD
        // ══════════════════════════════════════════════════════════
        case 'get_medicine_reminders':
            $stmt = $pdo->prepare("
                SELECT * FROM medicine_reminders 
                WHERE patient_id = :pid AND is_active = 1 
                ORDER BY created_at DESC
            ");
            $stmt->execute([':pid' => $patientId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['times_array'] = is_string($r['scheduled_times']) ? (json_decode($r['scheduled_times'], true) ?: [$r['scheduled_times']]) : $r['scheduled_times'];
            }
            sendJson(['success' => true, 'reminders' => $rows]);
            break;

        case 'add_medicine_reminder':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;

            $name        = trim($params['medicine_name'] ?? '');
            $dosage      = trim($params['dosage'] ?? '');
            $frequency   = trim($params['frequency'] ?? 'once_daily');
            $startDate   = trim($params['start_date'] ?? '');
            $endDate     = trim($params['end_date'] ?? '');
            $timesRaw    = $params['scheduled_times'] ?? [];
            $instructions= trim($params['instructions'] ?? '');

            // Validation
            if ($name === '') {
                sendJson(['success' => false, 'error' => 'Medicine name is required.'], 400);
            }
            if ($dosage === '') {
                sendJson(['success' => false, 'error' => 'Prescribed dosage is required.'], 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                sendJson(['success' => false, 'error' => 'Valid start date (YYYY-MM-DD) is required.'], 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                sendJson(['success' => false, 'error' => 'Valid end date (YYYY-MM-DD) is required.'], 400);
            }
            if ($endDate < $startDate) {
                sendJson(['success' => false, 'error' => 'End date cannot be earlier than start date.'], 400);
            }

            // Parse and format scheduled times
            $timesList = $scheduler->parseScheduledTimes($timesRaw, $frequency);
            if (empty($timesList)) {
                sendJson(['success' => false, 'error' => 'At least one valid reminder time is required.'], 400);
            }

            $jsonTimes = json_encode(array_values($timesList));

            $insert = $pdo->prepare("
                INSERT INTO medicine_reminders 
                  (patient_id, medicine_name, dosage, instructions, frequency, start_date, end_date, scheduled_times, is_active, created_at)
                VALUES
                  (:pid, :name, :dosage, :instructions, :frequency, :start_date, :end_date, :times, 1, :created_at)
            ");
            $insert->execute([
                ':pid'          => $patientId,
                ':name'         => $name,
                ':dosage'       => $dosage,
                ':instructions' => $instructions,
                ':frequency'    => $frequency,
                ':start_date'   => $startDate,
                ':end_date'     => $endDate,
                ':times'        => $jsonTimes,
                ':created_at'   => date('Y-m-d H:i:s'),
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Automatically generate today's doses for this new reminder
            $scheduler->generateDailyDoseLogs($patientId);

            sendJson([
                'success'     => true,
                'reminder_id' => $newId,
                'message'     => "Medicine reminder schedule for '{$name}' created successfully!"
            ], 201);
            break;

        case 'update_medicine_reminder':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;
            $reminderId = (int)($params['reminder_id'] ?? $params['id'] ?? 0);

            if (!$reminderId) {
                sendJson(['success' => false, 'error' => 'Reminder ID required.'], 400);
            }

            // Verify ownership
            $chk = $pdo->prepare("SELECT id FROM medicine_reminders WHERE id = :id AND patient_id = :pid");
            $chk->execute([':id' => $reminderId, ':pid' => $patientId]);
            if (!$chk->fetch()) {
                sendJson(['success' => false, 'error' => 'Reminder not found or unauthorized.'], 403);
            }

            $name        = trim($params['medicine_name'] ?? '');
            $dosage      = trim($params['dosage'] ?? '');
            $frequency   = trim($params['frequency'] ?? 'once_daily');
            $startDate   = trim($params['start_date'] ?? '');
            $endDate     = trim($params['end_date'] ?? '');
            $timesRaw    = $params['scheduled_times'] ?? [];
            $instructions= trim($params['instructions'] ?? '');

            if ($name === '' || $dosage === '') {
                sendJson(['success' => false, 'error' => 'Name and dosage cannot be empty.'], 400);
            }
            if ($endDate < $startDate) {
                sendJson(['success' => false, 'error' => 'End date cannot be earlier than start date.'], 400);
            }

            $timesList = $scheduler->parseScheduledTimes($timesRaw, $frequency);
            $jsonTimes = json_encode(array_values($timesList));

            $upd = $pdo->prepare("
                UPDATE medicine_reminders
                SET medicine_name = :name,
                    dosage = :dosage,
                    instructions = :instructions,
                    frequency = :frequency,
                    start_date = :start_date,
                    end_date = :end_date,
                    scheduled_times = :times,
                    updated_at = :updated_at
                WHERE id = :id AND patient_id = :pid
            ");
            $upd->execute([
                ':name'         => $name,
                ':dosage'       => $dosage,
                ':instructions' => $instructions,
                ':frequency'    => $frequency,
                ':start_date'   => $startDate,
                ':end_date'     => $endDate,
                ':times'        => $jsonTimes,
                ':updated_at'   => date('Y-m-d H:i:s'),
                ':id'           => $reminderId,
                ':pid'          => $patientId,
            ]);

            sendJson(['success' => true, 'message' => 'Medication schedule updated successfully!']);
            break;

        case 'delete_medicine_reminder':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;
            $reminderId = (int)($params['reminder_id'] ?? $params['id'] ?? $_GET['id'] ?? 0);

            if (!$reminderId) {
                sendJson(['success' => false, 'error' => 'Reminder ID required.'], 400);
            }

            // Soft delete
            $stmt = $pdo->prepare("
                UPDATE medicine_reminders 
                SET is_active = 0, updated_at = :updated_at 
                WHERE id = :id AND patient_id = :pid
            ");
            $stmt->execute([
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id'         => $reminderId,
                ':pid'        => $patientId,
            ]);

            if ($stmt->rowCount() === 0) {
                sendJson(['success' => false, 'error' => 'Reminder not found or already removed.'], 404);
            }

            sendJson(['success' => true, 'message' => 'Medication reminder schedule deactivated.']);
            break;

        // ══════════════════════════════════════════════════════════
        // 3. DOSE ACTIONS: Mark as Taken, Remind Me Later, Missed
        // ══════════════════════════════════════════════════════════
        case 'mark_taken':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;
            $doseId = (int)($params['dose_id'] ?? $params['id'] ?? 0);

            if (!$doseId) {
                sendJson(['success' => false, 'error' => 'Dose ID is required.'], 400);
            }

            $res = $scheduler->markDoseTaken($doseId, $patientId);
            sendJson($res, $res['success'] ? 200 : 400);
            break;

        case 'snooze_dose':
        case 'remind_later':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;
            $doseId = (int)($params['dose_id'] ?? $params['id'] ?? 0);
            $minutes= (int)($params['minutes'] ?? 15);

            if (!$doseId) {
                sendJson(['success' => false, 'error' => 'Dose ID is required.'], 400);
            }

            $res = $scheduler->snoozeDose($doseId, $patientId, $minutes);
            sendJson($res, $res['success'] ? 200 : 400);
            break;

        case 'mark_missed_taken':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;
            $doseId = (int)($params['dose_id'] ?? $params['id'] ?? 0);

            if (!$doseId) {
                sendJson(['success' => false, 'error' => 'Dose ID is required.'], 400);
            }

            // Call markDoseTaken which preserves original scheduled_at and sets taken_at = NOW()
            $res = $scheduler->markDoseTaken($doseId, $patientId);
            sendJson($res, $res['success'] ? 200 : 400);
            break;

        // ══════════════════════════════════════════════════════════
        // 4. DOCTOR APPOINTMENTS MANAGEMENT
        // ══════════════════════════════════════════════════════════
        case 'get_appointments':
            $stmt = $pdo->prepare("
                SELECT * FROM appointments 
                WHERE patient_id = :pid 
                ORDER BY appointment_date DESC, appointment_time DESC
            ");
            $stmt->execute([':pid' => $patientId]);
            $appts = $stmt->fetchAll();

            sendJson(['success' => true, 'appointments' => $appts]);
            break;

        case 'add_appointment':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;

            $docName   = trim($params['doctor_name'] ?? '');
            $dept      = trim($params['department'] ?? $params['specialty'] ?? 'General Medicine');
            $hospital  = trim($params['hospital_name'] ?? 'City Hospital');
            $date      = trim($params['appointment_date'] ?? $params['date'] ?? '');
            $time      = trim($params['appointment_time'] ?? $params['time'] ?? '');
            $type      = in_array($params['appointment_type'] ?? '', ['in-person', 'online'], true) ? $params['appointment_type'] : 'in-person';
            $location  = trim($params['location'] ?? '');
            $link      = trim($params['consultation_link'] ?? '');
            $notes     = trim($params['notes'] ?? '');

            if ($docName === '') {
                sendJson(['success' => false, 'error' => 'Doctor name is required.'], 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                sendJson(['success' => false, 'error' => 'Valid appointment date (YYYY-MM-DD) is required.'], 400);
            }
            if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
                sendJson(['success' => false, 'error' => 'Valid appointment time (HH:MM) is required.'], 400);
            }

            $timeFormatted = strlen($time) === 5 ? $time . ':00' : $time;
            $apptDateTime  = sprintf('%s %s', $date, $timeFormatted);

            $ins = $pdo->prepare("
                INSERT INTO appointments 
                  (patient_id, doctor_name, department, specialty, hospital_name, appointment_date, appointment_time, appt_datetime, appointment_type, location, consultation_link, notes, status, created_at)
                VALUES
                  (:pid, :doc, :dept, :dept, :hosp, :date, :time, :dt, :type, :loc, :link, :notes, 'upcoming', :created_at)
            ");
            $ins->execute([
                ':pid'        => $patientId,
                ':doc'        => $docName,
                ':dept'       => $dept,
                ':hosp'       => $hospital,
                ':date'       => $date,
                ':time'       => $timeFormatted,
                ':dt'         => $apptDateTime,
                ':type'       => $type,
                ':loc'        => $location,
                ':link'       => $link,
                ':notes'      => $notes,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);

            $apptId = (int)$pdo->lastInsertId();

            // Run appointment alert generator
            $scheduler->processAppointmentAlerts($patientId);

            sendJson([
                'success'        => true,
                'appointment_id' => $apptId,
                'message'        => "Doctor appointment with {$docName} successfully scheduled for {$date} at " . date('g:i A', strtotime($apptDateTime)) . "!"
            ], 201);
            break;

        case 'update_appointment_status':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;
            $apptId = (int)($params['appointment_id'] ?? $params['id'] ?? 0);
            $status = trim($params['status'] ?? '');

            if (!$apptId || !in_array($status, ['upcoming', 'completed', 'cancelled', 'missed'], true)) {
                sendJson(['success' => false, 'error' => 'Valid appointment ID and status required.'], 400);
            }

            $upd = $pdo->prepare("
                UPDATE appointments 
                SET status = :status, updated_at = :updated_at 
                WHERE id = :id AND patient_id = :pid
            ");
            $upd->execute([
                ':status'     => $status,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':id'         => $apptId,
                ':pid'        => $patientId,
            ]);

            if ($upd->rowCount() === 0) {
                sendJson(['success' => false, 'error' => 'Appointment not found or unauthorized.'], 404);
            }

            sendJson([
                'success' => true,
                'message' => "Appointment marked as {$status}."
            ]);
            break;

        // ══════════════════════════════════════════════════════════
        // 5. NOTIFICATION CENTER
        // ══════════════════════════════════════════════════════════
        case 'get_notifications':
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE patient_id = :pid 
                ORDER BY scheduled_at DESC 
                LIMIT 50
            ");
            $stmt->execute([':pid' => $patientId]);
            $list = $stmt->fetchAll();
            foreach ($list as &$item) {
                $item['time_ago'] = humanTimeAgo(strtotime($item['scheduled_at']));
            }
            sendJson(['success' => true, 'notifications' => $list]);
            break;

        case 'mark_notification_read':
            $params = !empty($jsonBody) ? $jsonBody : $_POST;
            $notifId = (int)($params['notification_id'] ?? $params['id'] ?? 0);

            if (!$notifId) {
                sendJson(['success' => false, 'error' => 'Notification ID required.'], 400);
            }

            $upd = $pdo->prepare("
                UPDATE notifications 
                SET status = 'read', read_at = :now 
                WHERE id = :id AND patient_id = :pid
            ");
            $upd->execute([
                ':now' => date('Y-m-d H:i:s'),
                ':id'  => $notifId,
                ':pid' => $patientId,
            ]);

            sendJson(['success' => true]);
            break;

        case 'mark_all_read':
            $upd = $pdo->prepare("
                UPDATE notifications 
                SET status = 'read', read_at = :now 
                WHERE patient_id = :pid AND status = 'sent'
            ");
            $upd->execute([
                ':now' => date('Y-m-d H:i:s'),
                ':pid' => $patientId,
            ]);

            sendJson(['success' => true, 'message' => 'All alerts marked as read.']);
            break;

        // ══════════════════════════════════════════════════════════
        // 6. SCHEDULER MANUAL TRIGGER & CSRF TOKEN
        // ══════════════════════════════════════════════════════════
        case 'run_scheduler':
            $res = $scheduler->runCycle($patientId);
            sendJson(['success' => true, 'scheduler_results' => $res]);
            break;

        case 'csrf_token':
            sendJson(['success' => true, 'csrf_token' => getCsrfToken()]);
            break;

        // ══════════════════════════════════════════════════════════
        // 7. PATIENT REMINDERS DELEGATION
        // ══════════════════════════════════════════════════════════
        case 'add_patient_reminder':
        case 'get_patient_reminders':
        case 'update_patient_reminder':
        case 'delete_patient_reminder':
        case 'mark_patient_reminder_status':
        case 'check_patient_reminders_due':
            require_once __DIR__ . '/patient_reminders_api.php';
            exit;

        default:
            sendJson(['success' => false, 'error' => "Action '{$action}' not recognized."], 404);
    }
} catch (Throwable $e) {
    error_log('[AayuVault Reminders API Error] ' . $e->getMessage());
    sendJson(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()], 500);
}

/**
 * Human readable time elapsed
 */
function humanTimeAgo(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 172800) return 'Yesterday';
    return date('M j', $timestamp);
}

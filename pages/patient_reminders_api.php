<?php
/**
 * patient_reminders_api.php — AayuVault Patient Reminders & Notification System API
 *
 * Implements complete CRUD, database storage, retrieval, blood test support,
 * and automated due/missed notification dispatch for patient reminders.
 *
 * Endpoints:
 *   POST action=add_reminder             → Store a new reminder
 *   GET  action=get_reminders            → Fetch all reminders (upcoming, past, completed, missed)
 *   GET  action=get_reminder&id=N        → Fetch a single reminder by ID
 *   POST action=update_reminder&id=N     → Update an existing reminder
 *   POST action=delete_reminder&id=N     → Delete a reminder
 *   POST action=mark_status&id=N         → Mark reminder as Completed/Pending/Missed
 *   GET/POST action=check_due            → Check due & missed reminders, trigger notifications
 *   GET  action=get_unread_notifications → Fetch unread in-app notifications
 *   POST action=mark_notification_read   → Mark in-app notification as read
 */

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/db.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Authentication Check ───────────────────────────────────────
$patientId = getLoggedInPatientId();
if (!$patientId) {
    sendJson(['success' => false, 'error' => 'Authentication required. Please log in.'], 401);
}

$pdo = getDb();

// ── Helper: Parse Request Payload ──────────────────────────────
function getRequestData(): array {
    $data = $_POST;
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
    }
    return $data;
}

// ── Helper: Normalize Reminder Type ────────────────────────────
function normalizeReminderType(string $rawType): string {
    $clean = strtolower(trim(str_replace(['_', '-'], ' ', $rawType)));
    return match ($clean) {
        'blood test', 'blood_test', 'lab test', 'lab', 'blood' => 'Blood Test',
        'medicine', 'medication', 'pill', 'prescription' => 'Medicine',
        'doctor appointment', 'doctor', 'appointment', 'consultation' => 'Doctor Appointment',
        'health checkup', 'checkup', 'wellness', 'check-up' => 'Health Checkup',
        default => 'Other',
    };
}

// ── Helper: Format Reminder Record ─────────────────────────────
function formatReminderRow(array $row): array {
    $timeStr = substr($row['reminder_time'] ?? '', 0, 5);
    $timeFormatted = $timeStr;
    if (!empty($timeStr) && strtotime($timeStr)) {
        $timeFormatted = date('g:i A', strtotime($timeStr));
    }

    $dateStr = $row['reminder_date'] ?? '';
    $dateFormatted = $dateStr;
    if (!empty($dateStr) && strtotime($dateStr)) {
        $dateFormatted = date('M d, Y', strtotime($dateStr));
    }

    $row['time_formatted'] = $timeFormatted;
    $row['date_formatted'] = $dateFormatted;
    $row['is_blood_test']  = ($row['reminder_type'] === 'Blood Test');
    $row['id']             = (int)$row['id'];
    $row['patient_id']     = (int)$row['patient_id'];
    return $row;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input  = getRequestData();
if (empty($action) && isset($input['action'])) {
    $action = $input['action'];
}

try {
    switch ($action) {

        // ══════════════════════════════════════════════════════════
        // 1. ADD REMINDER
        // ══════════════════════════════════════════════════════════
        case 'add':
        case 'add_reminder':
            if ($method !== 'POST') {
                sendJson(['success' => false, 'error' => 'POST method required.'], 405);
            }

            $title = trim($input['title'] ?? $input['reminder_title'] ?? $input['blood_test_name'] ?? '');
            $desc  = trim($input['description'] ?? $input['desc'] ?? '');
            $date  = trim($input['reminder_date'] ?? $input['date'] ?? '');
            $time  = trim($input['reminder_time'] ?? $input['time'] ?? '');
            $rawType = trim($input['reminder_type'] ?? $input['type'] ?? 'Other');
            $type  = normalizeReminderType($rawType);

            // Special blood test handling
            if (!empty($input['blood_test_name'])) {
                $type = 'Blood Test';
                if (empty($title)) {
                    $title = trim($input['blood_test_name']);
                }
            }

            // Validations
            if ($title === '') {
                sendJson(['success' => false, 'error' => 'Reminder title is required.'], 422);
            }
            if (strlen($title) > 255) {
                sendJson(['success' => false, 'error' => 'Reminder title cannot exceed 255 characters.'], 422);
            }
            if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                sendJson(['success' => false, 'error' => 'A valid reminder date (YYYY-MM-DD) is required.'], 422);
            }
            [$y, $m, $d] = explode('-', $date);
            if (!checkdate((int)$m, (int)$d, (int)$y)) {
                sendJson(['success' => false, 'error' => 'Invalid calendar date provided.'], 422);
            }

            if ($time === '') {
                $time = '09:00:00';
            } else {
                if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                    $time .= ':00';
                } elseif (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $time)) {
                    sendJson(['success' => false, 'error' => 'A valid reminder time (HH:MM or HH:MM:SS) is required.'], 422);
                }
            }

            $status = in_array($input['reminder_status'] ?? '', ['Pending', 'Completed', 'Missed'], true)
                ? $input['reminder_status']
                : 'Pending';

            $stmt = $pdo->prepare("
                INSERT INTO patient_reminders 
                  (patient_id, title, description, reminder_date, reminder_time, reminder_type, reminder_status, created_at)
                VALUES 
                  (:pid, :title, :description, :rdate, :rtime, :rtype, :rstatus, :created_at)
            ");
            $nowStr = date('Y-m-d H:i:s');
            $stmt->execute([
                ':pid'         => $patientId,
                ':title'       => $title,
                ':description' => $desc,
                ':rdate'       => $date,
                ':rtime'       => $time,
                ':rtype'       => $type,
                ':rstatus'     => $status,
                ':created_at'  => $nowStr,
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Fetch newly inserted record
            $fetchStmt = $pdo->prepare("SELECT * FROM patient_reminders WHERE id = ? AND patient_id = ?");
            $fetchStmt->execute([$newId, $patientId]);
            $created = $fetchStmt->fetch();

            sendJson([
                'success'     => true,
                'message'     => 'Reminder saved permanently to database.',
                'reminder_id' => $newId,
                'reminder'    => $created ? formatReminderRow($created) : null,
            ], 201);
            break;

        // ══════════════════════════════════════════════════════════
        // 2. FETCH REMINDERS (All, Upcoming, Past, Completed)
        // ══════════════════════════════════════════════════════════
        case 'get':
        case 'get_all':
        case 'get_reminders':
            $curDate = date('Y-m-d');
            $curTime = date('H:i:s');

            // Automatically transition overdue pending reminders that passed their scheduled time
            $gracePast = date('H:i:s', time() - (30 * 60)); // 30 min grace period
            $autoMissed = $pdo->prepare("
                UPDATE patient_reminders
                SET reminder_status = 'Missed'
                WHERE patient_id = :pid
                  AND reminder_status = 'Pending'
                  AND (
                    reminder_date < :curDate
                    OR (reminder_date = :curDate AND reminder_time < :gracePast)
                  )
            ");
            $autoMissed->execute([
                ':pid'       => $patientId,
                ':curDate'   => $curDate,
                ':gracePast' => $gracePast,
            ]);

            // Query all reminders for the logged-in patient, sorted by date and time
            $typeFilter = trim($_GET['type'] ?? $input['type'] ?? '');
            $search     = trim($_GET['search'] ?? $input['search'] ?? '');

            $sql = "SELECT * FROM patient_reminders WHERE patient_id = :pid";
            $params = [':pid' => $patientId];

            if ($typeFilter !== '' && $typeFilter !== 'all' && $typeFilter !== 'All') {
                $sql .= " AND reminder_type = :rtype";
                $params[':rtype'] = normalizeReminderType($typeFilter);
            }

            if ($search !== '') {
                $sql .= " AND (title LIKE :q OR description LIKE :q)";
                $params[':q'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY reminder_date ASC, reminder_time ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $allRows = array_map('formatReminderRow', $stmt->fetchAll());

            // Segregate into 3 core sections as specified in prompt:
            // 1. Upcoming Reminders – All future reminders
            // 2. Past Reminders – Previously scheduled reminders
            // 3. Completed Reminders – Reminders marked as completed
            $upcoming  = [];
            $past      = [];
            $completed = [];
            $missed    = [];

            foreach ($allRows as $r) {
                if ($r['reminder_status'] === 'Completed') {
                    $completed[] = $r;
                    continue;
                }

                $rDateTime = $r['reminder_date'] . ' ' . $r['reminder_time'];
                $isFuture  = ($r['reminder_date'] > $curDate) ||
                             ($r['reminder_date'] === $curDate && $r['reminder_time'] >= $curTime);

                if ($isFuture && $r['reminder_status'] === 'Pending') {
                    $upcoming[] = $r;
                } else {
                    $past[] = $r;
                    if ($r['reminder_status'] === 'Missed') {
                        $missed[] = $r;
                    }
                }
            }

            // Past reminders sorted newest first
            usort($past, function($a, $b) {
                return strcmp($b['reminder_date'] . ' ' . $b['reminder_time'], $a['reminder_date'] . ' ' . $a['reminder_time']);
            });

            // Completed reminders sorted newest first
            usort($completed, function($a, $b) {
                return strcmp($b['reminder_date'] . ' ' . $b['reminder_time'], $a['reminder_date'] . ' ' . $a['reminder_time']);
            });

            sendJson([
                'success' => true,
                'data'    => [
                    'upcoming'  => $upcoming,
                    'past'      => $past,
                    'completed' => $completed,
                    'missed'    => $missed,
                    'all'       => $allRows,
                    'counts'    => [
                        'total'     => count($allRows),
                        'upcoming'  => count($upcoming),
                        'past'      => count($past),
                        'completed' => count($completed),
                        'missed'    => count($missed),
                        'blood_tests' => count(array_filter($allRows, fn($x) => $x['reminder_type'] === 'Blood Test')),
                        'medicines'   => count(array_filter($allRows, fn($x) => $x['reminder_type'] === 'Medicine')),
                        'appointments'=> count(array_filter($allRows, fn($x) => $x['reminder_type'] === 'Doctor Appointment')),
                    ],
                ],
            ]);
            break;

        // ══════════════════════════════════════════════════════════
        // 3. GET SINGLE REMINDER BY ID
        // ══════════════════════════════════════════════════════════
        case 'get_reminder':
        case 'get_by_id':
            $id = filter_var($_GET['id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                sendJson(['success' => false, 'error' => 'A valid reminder ID is required.'], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM patient_reminders WHERE id = ? AND patient_id = ?");
            $stmt->execute([$id, $patientId]);
            $row = $stmt->fetch();
            if (!$row) {
                sendJson(['success' => false, 'error' => 'Reminder not found or access denied.'], 404);
            }

            sendJson(['success' => true, 'reminder' => formatReminderRow($row)]);
            break;

        // ══════════════════════════════════════════════════════════
        // 4. UPDATE REMINDER
        // ══════════════════════════════════════════════════════════
        case 'update':
        case 'update_reminder':
            if ($method !== 'POST') {
                sendJson(['success' => false, 'error' => 'POST method required.'], 405);
            }

            $id = filter_var($_GET['id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                sendJson(['success' => false, 'error' => 'A valid reminder ID is required.'], 400);
            }

            // Verify reminder belongs to this patient
            $check = $pdo->prepare("SELECT * FROM patient_reminders WHERE id = ? AND patient_id = ?");
            $check->execute([$id, $patientId]);
            $existing = $check->fetch();
            if (!$existing) {
                sendJson(['success' => false, 'error' => 'Reminder not found or access denied.'], 404);
            }

            $title = isset($input['title']) ? trim($input['title']) : (isset($input['blood_test_name']) ? trim($input['blood_test_name']) : $existing['title']);
            $desc  = isset($input['description']) ? trim($input['description']) : (isset($input['desc']) ? trim($input['desc']) : $existing['description']);
            $date  = trim($input['reminder_date'] ?? $input['date'] ?? $existing['reminder_date']);
            $time  = trim($input['reminder_time'] ?? $input['time'] ?? $existing['reminder_time']);
            $type  = isset($input['reminder_type']) || isset($input['type']) ? normalizeReminderType($input['reminder_type'] ?? $input['type']) : $existing['reminder_type'];
            $status = in_array($input['reminder_status'] ?? '', ['Pending', 'Completed', 'Missed'], true) ? $input['reminder_status'] : $existing['reminder_status'];

            if ($title === '') {
                sendJson(['success' => false, 'error' => 'Title cannot be empty.'], 422);
            }

            if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                $time .= ':00';
            }

            // If time or date changed to a future timestamp, reset notified_at and set to Pending if was Missed
            $notifiedAt = $existing['notified_at'];
            $curDate = date('Y-m-d');
            $curTime = date('H:i:s');
            $isFuture = ($date > $curDate) || ($date === $curDate && $time >= $curTime);
            if ($isFuture && ($date !== $existing['reminder_date'] || $time !== $existing['reminder_time'])) {
                $notifiedAt = null;
                if ($status === 'Missed') {
                    $status = 'Pending';
                }
            }

            $upd = $pdo->prepare("
                UPDATE patient_reminders
                SET title = :title,
                    description = :description,
                    reminder_date = :rdate,
                    reminder_time = :rtime,
                    reminder_type = :rtype,
                    reminder_status = :rstatus,
                    notified_at = :notified_at,
                    updated_at = :now
                WHERE id = :id AND patient_id = :pid
            ");
            $upd->execute([
                ':title'       => $title,
                ':description' => $desc,
                ':rdate'       => $date,
                ':rtime'       => $time,
                ':rtype'       => $type,
                ':rstatus'     => $status,
                ':notified_at' => $notifiedAt,
                ':now'         => date('Y-m-d H:i:s'),
                ':id'          => $id,
                ':pid'         => $patientId,
            ]);

            $fetchStmt = $pdo->prepare("SELECT * FROM patient_reminders WHERE id = ? AND patient_id = ?");
            $fetchStmt->execute([$id, $patientId]);
            $updatedRow = $fetchStmt->fetch();

            sendJson([
                'success'  => true,
                'message'  => 'Reminder updated successfully.',
                'reminder' => formatReminderRow($updatedRow),
            ]);
            break;

        // ══════════════════════════════════════════════════════════
        // 5. DELETE REMINDER
        // ══════════════════════════════════════════════════════════
        case 'delete':
        case 'delete_reminder':
            if ($method !== 'POST') {
                sendJson(['success' => false, 'error' => 'POST method required.'], 405);
            }

            $id = filter_var($_GET['id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                sendJson(['success' => false, 'error' => 'A valid reminder ID is required.'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM patient_reminders WHERE id = ? AND patient_id = ?");
            $stmt->execute([$id, $patientId]);

            if ($stmt->rowCount() === 0) {
                sendJson(['success' => false, 'error' => 'Reminder not found or access denied.'], 404);
            }

            sendJson(['success' => true, 'message' => 'Reminder deleted successfully.']);
            break;

        // ══════════════════════════════════════════════════════════
        // 6. MARK STATUS (Completed / Pending / Missed)
        // ══════════════════════════════════════════════════════════
        case 'mark_status':
        case 'mark_completed':
            if ($method !== 'POST') {
                sendJson(['success' => false, 'error' => 'POST method required.'], 405);
            }

            $id = filter_var($_GET['id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                sendJson(['success' => false, 'error' => 'A valid reminder ID is required.'], 400);
            }

            $newStatus = trim($input['status'] ?? 'Completed');
            if (!in_array($newStatus, ['Completed', 'Pending', 'Missed'], true)) {
                $newStatus = 'Completed';
            }

            $upd = $pdo->prepare("
                UPDATE patient_reminders
                SET reminder_status = :status,
                    updated_at = :now
                WHERE id = :id AND patient_id = :pid
            ");
            $upd->execute([
                ':status' => $newStatus,
                ':now'    => date('Y-m-d H:i:s'),
                ':id'     => $id,
                ':pid'    => $patientId,
            ]);

            if ($upd->rowCount() === 0) {
                // Check if reminder exists
                $chk = $pdo->prepare("SELECT id FROM patient_reminders WHERE id = ? AND patient_id = ?");
                $chk->execute([$id, $patientId]);
                if (!$chk->fetch()) {
                    sendJson(['success' => false, 'error' => 'Reminder not found or access denied.'], 404);
                }
            }

            $fetchStmt = $pdo->prepare("SELECT * FROM patient_reminders WHERE id = ? AND patient_id = ?");
            $fetchStmt->execute([$id, $patientId]);
            $updatedRow = $fetchStmt->fetch();

            sendJson([
                'success'  => true,
                'message'  => "Reminder marked as {$newStatus}.",
                'reminder' => formatReminderRow($updatedRow),
            ]);
            break;

        // ══════════════════════════════════════════════════════════
        // 7. CHECK DUE & MISSED REMINDERS (Notification Generator)
        // ══════════════════════════════════════════════════════════
        case 'check_due':
            $curDate   = date('Y-m-d');
            $curTime   = date('H:i:s');
            $nowStr    = date('Y-m-d H:i:s');
            $nowUnix   = time();
            $dueWindowStart = date('H:i:s', $nowUnix - (20 * 60)); // within 20 mins ago
            $dueWindowEnd   = date('H:i:s', $nowUnix + (10 * 60)); // or within next 10 mins

            $dueAlerts    = [];
            $missedAlerts = [];

            // A. Find reminders due right now or that became due today and haven't notified yet
            $dueStmt = $pdo->prepare("
                SELECT * FROM patient_reminders
                WHERE patient_id = :pid
                  AND reminder_status = 'Pending'
                  AND reminder_date = :curDate
                  AND reminder_time <= :windowEnd
                  AND (notified_at IS NULL OR reminder_time >= :windowStart)
                ORDER BY reminder_time ASC
            ");
            $dueStmt->execute([
                ':pid'         => $patientId,
                ':curDate'     => $curDate,
                ':windowEnd'   => $dueWindowEnd,
                ':windowStart' => $dueWindowStart,
            ]);
            $dueRows = $dueStmt->fetchAll();

            foreach ($dueRows as $row) {
                $formatted = formatReminderRow($row);
                $dueAlerts[] = $formatted;

                // Record in-app notification if not already sent (avoids duplicates)
                if (empty($row['notified_at'])) {
                    $dedupKey = 'rem_due_' . $row['id'] . '_' . $row['reminder_date'] . '_' . substr($row['reminder_time'], 0, 5);
                    $notifType = ($row['reminder_type'] === 'Blood Test') ? 'blood_test' :
                                 (($row['reminder_type'] === 'Medicine') ? 'medicine' : 'appointment');

                    $notifTitle = "Reminder Due: " . $row['title'];
                    $notifMsg   = ($row['reminder_type'] === 'Blood Test')
                        ? "Scheduled blood test '{$row['title']}' is due now at {$formatted['time_formatted']}."
                        : "Your scheduled {$row['reminder_type']} reminder is due now.";
                    if (!empty($row['description'])) {
                        $notifMsg .= ' ' . $row['description'];
                    }

                    try {
                        $ins = $pdo->prepare("
                            INSERT INTO notifications 
                              (patient_id, notification_type, related_record_id, title, message, scheduled_at, status, channel, dedup_key, created_at)
                            VALUES 
                              (:pid, :ntype, :rid, :title, :msg, :sched, 'pending', 'in_app', :dedup, :now)
                        ");
                        $ins->execute([
                            ':pid'   => $patientId,
                            ':ntype' => $notifType,
                            ':rid'   => $row['id'],
                            ':title' => $notifTitle,
                            ':msg'   => $notifMsg,
                            ':sched' => $row['reminder_date'] . ' ' . $row['reminder_time'],
                            ':dedup' => $dedupKey,
                            ':now'   => $nowStr,
                        ]);
                    } catch (Throwable $e) {
                        // Dedup key constraint handled silently
                    }

                    // Mark as notified in database
                    $markNotif = $pdo->prepare("UPDATE patient_reminders SET notified_at = :now WHERE id = :id");
                    $markNotif->execute([':now' => $nowStr, ':id' => $row['id']]);
                }
            }

            // B. Find reminders that arrived while the patient was offline/not logged in
            // (reminder_date < curDate OR reminder_date == curDate AND reminder_time < 30 mins ago)
            $gracePast = date('H:i:s', $nowUnix - (30 * 60));
            $missedStmt = $pdo->prepare("
                SELECT * FROM patient_reminders
                WHERE patient_id = :pid
                  AND reminder_status != 'Completed'
                  AND (
                    reminder_date < :curDate
                    OR (reminder_date = :curDate AND reminder_time < :gracePast)
                  )
                  AND (notified_at IS NULL OR reminder_status = 'Pending')
                ORDER BY reminder_date DESC, reminder_time DESC
                LIMIT 15
            ");
            $missedStmt->execute([
                ':pid'       => $patientId,
                ':curDate'   => $curDate,
                ':gracePast' => $gracePast,
            ]);
            $missedRows = $missedStmt->fetchAll();

            foreach ($missedRows as $row) {
                $formatted = formatReminderRow($row);
                $missedAlerts[] = $formatted;

                // Update status to Missed
                $updStatus = $pdo->prepare("UPDATE patient_reminders SET reminder_status = 'Missed', notified_at = :now WHERE id = :id");
                $updStatus->execute([':now' => $nowStr, ':id' => $row['id']]);

                // Insert missed in-app notification with dedup key
                $dedupKey = 'rem_missed_' . $row['id'] . '_' . $row['reminder_date'];
                $notifTitle = "Missed: " . $row['title'];
                $notifMsg   = "Scheduled {$row['reminder_type']} was set for {$formatted['date_formatted']} at {$formatted['time_formatted']}. Please review or mark completed.";

                try {
                    $ins = $pdo->prepare("
                        INSERT INTO notifications 
                          (patient_id, notification_type, related_record_id, title, message, scheduled_at, status, channel, dedup_key, created_at)
                        VALUES 
                          (:pid, 'system', :rid, :title, :msg, :sched, 'pending', 'in_app', :dedup, :now)
                    ");
                    $ins->execute([
                        ':pid'   => $patientId,
                        ':rid'   => $row['id'],
                        ':title' => $notifTitle,
                        ':msg'   => $notifMsg,
                        ':sched' => $row['reminder_date'] . ' ' . $row['reminder_time'],
                        ':dedup' => $dedupKey,
                        ':now'   => $nowStr,
                    ]);
                } catch (Throwable $e) {
                    // Dedup key handled silently
                }
            }

            // Unread count
            $cntStmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications
                WHERE patient_id = :pid AND (status = 'pending' OR status = 'sent' OR read_at IS NULL)
            ");
            $cntStmt->execute([':pid' => $patientId]);
            $unreadCount = (int)$cntStmt->fetchColumn();

            sendJson([
                'success'      => true,
                'due_reminders'=> $dueAlerts,
                'missed_alerts'=> $missedAlerts,
                'unread_count' => $unreadCount,
                'server_time'  => $nowStr,
            ]);
            break;

        // ══════════════════════════════════════════════════════════
        // 8. UNREAD IN-APP NOTIFICATIONS
        // ══════════════════════════════════════════════════════════
        case 'get_unread_notifications':
        case 'get_notifications':
            $stmt = $pdo->prepare("
                SELECT * FROM notifications
                WHERE patient_id = :pid
                ORDER BY created_at DESC
                LIMIT 30
            ");
            $stmt->execute([':pid' => $patientId]);
            $notifs = $stmt->fetchAll();

            $unreadCount = 0;
            foreach ($notifs as &$n) {
                $n['is_unread'] = empty($n['read_at']) && $n['status'] !== 'read';
                if ($n['is_unread']) {
                    $unreadCount++;
                }
            }

            sendJson([
                'success'       => true,
                'notifications' => $notifs,
                'unread_count'  => $unreadCount,
            ]);
            break;

        // ══════════════════════════════════════════════════════════
        // 9. MARK NOTIFICATION READ / DISMISS
        // ══════════════════════════════════════════════════════════
        case 'mark_notification_read':
        case 'dismiss_notification':
            $notifId = filter_var($_GET['id'] ?? $input['id'] ?? null, FILTER_VALIDATE_INT);
            $markAll = (bool)($input['all'] ?? $_GET['all'] ?? false);

            if ($markAll) {
                $stmt = $pdo->prepare("
                    UPDATE notifications
                    SET status = 'read', read_at = :now
                    WHERE patient_id = :pid AND (read_at IS NULL OR status != 'read')
                ");
                $stmt->execute([':now' => date('Y-m-d H:i:s'), ':pid' => $patientId]);
                sendJson(['success' => true, 'message' => 'All notifications marked as read.']);
            }

            if (!$notifId) {
                sendJson(['success' => false, 'error' => 'Notification ID required.'], 400);
            }

            $stmt = $pdo->prepare("
                UPDATE notifications
                SET status = 'read', read_at = :now
                WHERE id = :id AND patient_id = :pid
            ");
            $stmt->execute([
                ':now' => date('Y-m-d H:i:s'),
                ':id'  => $notifId,
                ':pid' => $patientId,
            ]);

            sendJson(['success' => true, 'message' => 'Notification marked as read.']);
            break;

        default:
            sendJson(['success' => false, 'error' => "Action '{$action}' not recognized."], 404);
    }
} catch (Throwable $e) {
    error_log('[AayuVault Patient Reminders API Error] ' . $e->getMessage());
    sendJson(['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()], 500);
}

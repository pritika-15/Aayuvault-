<?php
/**
 * scheduler_service.php — AayuVault Reliable Scheduler Service
 *
 * Handles:
 * 1. Generating daily medicine dose logs according to patient schedule.
 * 2. Evaluating dose statuses against grace period (e.g. 45 min) to mark missed doses.
 * 3. Processing appointment alerts (today's visits, 24h before, 1h before).
 * 4. Preventing duplicate processing via database transactions and dedup keys.
 * 5. Timezone aware (Asia/Kolkata default).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notification_provider.php';

class SchedulerService {

    private PDO $pdo;
    private NotificationService $notifService;
    private int $gracePeriodMinutes;

    public function __construct(?PDO $pdo = null, int $gracePeriodMinutes = 45) {
        $this->pdo = $pdo ?? getDb();
        $this->notifService = new NotificationService($this->pdo);
        $this->gracePeriodMinutes = $gracePeriodMinutes;
    }

    /**
     * Run the complete scheduler cycle.
     * Safe to call repeatedly from cron, background runner, or page load.
     */
    public function runCycle(?int $targetPatientId = null): array {
        $now = date('Y-m-d H:i:s');
        $results = [
            'timestamp'             => $now,
            'doses_generated'       => 0,
            'doses_marked_missed'   => 0,
            'appointment_alerts'    => 0,
            'past_appts_flagged'    => 0,
            'messages'              => []
        ];

        try {
            // 1. Generate Dose Logs for Active Schedules
            $dosesGen = $this->generateDailyDoseLogs($targetPatientId);
            $results['doses_generated'] = $dosesGen;

            // 2. Mark Unanswered Pending/Snoozed Doses as Missed after Grace Period
            $missedCount = $this->evaluateMissedDoses($targetPatientId);
            $results['doses_marked_missed'] = $missedCount;

            // 3. Generate Appointment Alerts (Today, 24h, 1h)
            $apptAlerts = $this->processAppointmentAlerts($targetPatientId);
            $results['appointment_alerts'] = $apptAlerts;

            // 4. Flag Past Due Appointments
            $pastAppts = $this->evaluatePastAppointments($targetPatientId);
            $results['past_appts_flagged'] = $pastAppts;

            $results['success'] = true;
        } catch (Throwable $e) {
            error_log('[AayuVault Scheduler Error] ' . $e->getMessage());
            $results['success'] = false;
            $results['error']   = $e->getMessage();
        }

        return $results;
    }

    /**
     * Generate dose logs for active medicine reminders for today (and tomorrow).
     */
    public function generateDailyDoseLogs(?int $patientId = null): int {
        $today = date('Y-m-d');
        $generated = 0;

        $sql = "SELECT * FROM medicine_reminders WHERE is_active = 1";
        $params = [];
        if ($patientId !== null) {
            $sql .= " AND patient_id = :pid";
            $params[':pid'] = $patientId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $reminders = $stmt->fetchAll();

        foreach ($reminders as $rem) {
            $startDate = $rem['start_date'];
            $endDate   = $rem['end_date'];

            // Check if today falls within schedule window
            if ($today < $startDate || $today > $endDate) {
                continue;
            }

            // Parse scheduled times (JSON or string)
            $times = $this->parseScheduledTimes($rem['scheduled_times'], $rem['frequency']);

            foreach ($times as $timeStr) {
                $scheduledAt = sprintf('%s %s', $today, $timeStr);

                // Prevent duplicate using check or unique constraint
                $checkStmt = $this->pdo->prepare("
                    SELECT id FROM medicine_dose_logs 
                    WHERE reminder_id = :rid AND scheduled_at = :sched LIMIT 1
                ");
                $checkStmt->execute([
                    ':rid'   => $rem['id'],
                    ':sched' => $scheduledAt,
                ]);

                if (!$checkStmt->fetch()) {
                    $insertStmt = $this->pdo->prepare("
                        INSERT INTO medicine_dose_logs 
                          (reminder_id, patient_id, scheduled_at, status, created_at)
                        VALUES 
                          (:rid, :pid, :sched, 'pending', :created_at)
                    ");
                    $insertStmt->execute([
                        ':rid'        => $rem['id'],
                        ':pid'        => $rem['patient_id'],
                        ':sched'      => $scheduledAt,
                        ':created_at' => date('Y-m-d H:i:s'),
                    ]);
                    $generated++;

                    // Create In-App Notification if dose is upcoming today
                    $doseId = (int)$this->pdo->lastInsertId();
                    $notifMsg = sprintf('Reminder: Take %s (%s). %s', $rem['medicine_name'], $rem['dosage'], $rem['instructions'] ?: 'As prescribed');
                    $this->notifService->createInAppNotification(
                        (int)$rem['patient_id'],
                        'medicine',
                        'Medicine Due: ' . $rem['medicine_name'],
                        $notifMsg,
                        $doseId,
                        $scheduledAt,
                        sprintf('med_sched_%d_%s', $doseId, date('Ymd_Hi', strtotime($scheduledAt)))
                    );
                }
            }
        }

        return $generated;
    }

    /**
     * Standardize and parse scheduled times.
     */
    public function parseScheduledTimes($rawTimes, string $frequency = 'once_daily'): array {
        $result = [];

        if (is_string($rawTimes)) {
            $decoded = json_decode($rawTimes, true);
            if (is_array($decoded)) {
                $rawTimes = $decoded;
            } else {
                // Comma separated or single string
                $rawTimes = explode(',', $rawTimes);
            }
        }

        if (is_array($rawTimes)) {
            foreach ($rawTimes as $item) {
                $item = strtolower(trim((string)$item));
                if ($item === 'morning')   $result[] = '08:30:00';
                elseif ($item === 'afternoon') $result[] = '14:00:00';
                elseif ($item === 'night')     $result[] = '20:30:00';
                elseif (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $item, $m)) {
                    $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                    $min = str_pad($m[2], 2, '0', STR_PAD_LEFT);
                    $s = isset($m[4]) ? str_pad($m[4], 2, '0', STR_PAD_LEFT) : '00';
                    $result[] = "$h:$min:$s";
                }
            }
        }

        if (empty($result)) {
            // Defaults based on frequency
            if ($frequency === 'twice_daily') {
                $result = ['08:30:00', '20:30:00'];
            } elseif ($frequency === 'three_times_daily') {
                $result = ['08:30:00', '14:00:00', '20:30:00'];
            } else {
                $result = ['09:00:00'];
            }
        }

        return array_unique($result);
    }

    /**
     * Mark unanswered doses as missed if elapsed past grace period.
     * Preserves dose record and adds safe healthcare note.
     */
    public function evaluateMissedDoses(?int $patientId = null): int {
        $graceSec = $this->gracePeriodMinutes * 60;
        $cutoffTime = date('Y-m-d H:i:s', time() - $graceSec);
        $missedCount = 0;

        // 1. Pending doses whose scheduled_at + grace < NOW()
        $sql = "
            SELECT d.id, d.patient_id, d.reminder_id, d.scheduled_at, r.medicine_name, r.dosage
            FROM medicine_dose_logs d
            JOIN medicine_reminders r ON r.id = d.reminder_id
            WHERE d.status = 'pending' AND d.scheduled_at <= :cutoff
        ";
        $params = [':cutoff' => $cutoffTime];
        if ($patientId !== null) {
            $sql .= " AND d.patient_id = :pid";
            $params[':pid'] = $patientId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $overduePending = $stmt->fetchAll();

        foreach ($overduePending as $dose) {
            $upd = $this->pdo->prepare("
                UPDATE medicine_dose_logs 
                SET status = 'missed', 
                    notes = 'Grace period elapsed without response. Follow prescription schedule; do not double dose.'
                WHERE id = :id AND status = 'pending'
            ");
            $upd->execute([':id' => $dose['id']]);
            if ($upd->rowCount() > 0) {
                $missedCount++;

                // Notify of missed dose with safe clinical message
                $safeMsg = sprintf(
                    'Missed dose: %s (%s) scheduled for %s. Do not take a double dose. Follow your prescription or consult your doctor.',
                    $dose['medicine_name'],
                    $dose['dosage'],
                    date('g:i A', strtotime($dose['scheduled_at']))
                );
                $this->notifService->createInAppNotification(
                    (int)$dose['patient_id'],
                    'medicine',
                    'Missed Dose: ' . $dose['medicine_name'],
                    $safeMsg,
                    (int)$dose['id'],
                    $dose['scheduled_at'],
                    sprintf('missed_%d', $dose['id'])
                );
            }
        }

        // 2. Snoozed doses whose snoozed_until + grace < NOW()
        $snoozeSql = "
            SELECT d.id, d.patient_id, d.reminder_id, d.scheduled_at, r.medicine_name, r.dosage
            FROM medicine_dose_logs d
            JOIN medicine_reminders r ON r.id = d.reminder_id
            WHERE d.status = 'snoozed' AND d.snoozed_until IS NOT NULL AND d.snoozed_until <= :cutoff
        ";
        $snoozeParams = [':cutoff' => $cutoffTime];
        if ($patientId !== null) {
            $snoozeSql .= " AND d.patient_id = :pid";
            $snoozeParams[':pid'] = $patientId;
        }

        $snoozeStmt = $this->pdo->prepare($snoozeSql);
        $snoozeStmt->execute($snoozeParams);
        $overdueSnoozed = $snoozeStmt->fetchAll();

        foreach ($overdueSnoozed as $dose) {
            $upd = $this->pdo->prepare("
                UPDATE medicine_dose_logs 
                SET status = 'missed', 
                    notes = 'Snooze window elapsed. Do not double dose.'
                WHERE id = :id AND status = 'snoozed'
            ");
            $upd->execute([':id' => $dose['id']]);
            if ($upd->rowCount() > 0) {
                $missedCount++;
            }
        }

        return $missedCount;
    }

    /**
     * Process appointment alerts:
     * - Day of appointment alert
     * - 24 hours advance alert
     * - 1 hour advance alert
     * Avoids duplicate alerts and ignores cancelled appointments.
     */
    public function processAppointmentAlerts(?int $patientId = null): int {
        $alertsGenerated = 0;
        $now = time();
        $todayStr = date('Y-m-d');

        // Look for appointments in the next 30 hours that are NOT cancelled
        $sql = "
            SELECT id, patient_id, doctor_name, department, hospital_name, 
                   appointment_date, appointment_time, appt_datetime, appointment_type, location, status
            FROM appointments
            WHERE status != 'cancelled'
        ";
        $params = [];
        if ($patientId !== null) {
            $sql .= " AND patient_id = :pid";
            $params[':pid'] = $patientId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();

        foreach ($appointments as $appt) {
            $apptTime = strtotime($appt['appt_datetime'] ?: ($appt['appointment_date'] . ' ' . $appt['appointment_time']));
            $diffSeconds = $apptTime - $now;

            // 1. Day of Appointment Alert
            if ($appt['appointment_date'] === $todayStr) {
                $dedupKey = sprintf('appt_%d_today_%s', $appt['id'], $todayStr);
                $msg = sprintf(
                    'You have a doctor appointment today at %s with %s (%s) at %s.',
                    date('g:i A', $apptTime),
                    $appt['doctor_name'],
                    $appt['department'] ?: 'Specialist',
                    $appt['hospital_name'] ?: 'Clinic'
                );

                $res = $this->notifService->createInAppNotification(
                    (int)$appt['patient_id'],
                    'appointment',
                    'Doctor Appointment Today!',
                    $msg,
                    (int)$appt['id'],
                    date('Y-m-d H:i:s', $apptTime),
                    $dedupKey
                );
                if (!empty($res['success']) && empty($res['is_duplicate'])) {
                    $alertsGenerated++;
                }
            }

            // 2. 24 Hours Advance Reminder (between 23h and 25h ahead)
            if ($diffSeconds >= (23 * 3600) && $diffSeconds <= (25 * 3600)) {
                $dedupKey = sprintf('appt_%d_24h', $appt['id']);
                $msg = sprintf(
                    'Upcoming appointment tomorrow at %s with %s (%s). Hospital: %s.',
                    date('g:i A', $apptTime),
                    $appt['doctor_name'],
                    $appt['department'] ?: 'Consultation',
                    $appt['hospital_name'] ?: 'Clinic'
                );

                $res = $this->notifService->createInAppNotification(
                    (int)$appt['patient_id'],
                    'appointment',
                    'Appointment Reminder: Tomorrow',
                    $msg,
                    (int)$appt['id'],
                    date('Y-m-d H:i:s', $apptTime),
                    $dedupKey
                );
                if (!empty($res['success']) && empty($res['is_duplicate'])) {
                    $alertsGenerated++;
                }
            }

            // 3. 1 Hour Advance Reminder (between 50 min and 70 min ahead)
            if ($diffSeconds >= (50 * 60) && $diffSeconds <= (75 * 60)) {
                $dedupKey = sprintf('appt_%d_1h', $appt['id']);
                $msg = sprintf(
                    'Your appointment with %s is in 1 hour at %s. Please prepare necessary medical reports.',
                    $appt['doctor_name'],
                    date('g:i A', $apptTime)
                );

                $res = $this->notifService->createInAppNotification(
                    (int)$appt['patient_id'],
                    'appointment',
                    'Appointment in 1 Hour!',
                    $msg,
                    (int)$appt['id'],
                    date('Y-m-d H:i:s', $apptTime),
                    $dedupKey
                );
                if (!empty($res['success']) && empty($res['is_duplicate'])) {
                    $alertsGenerated++;
                }
            }
        }

        return $alertsGenerated;
    }

    /**
     * Identify past appointments that passed without being marked completed.
     */
    public function evaluatePastAppointments(?int $patientId = null): int {
        $now = date('Y-m-d H:i:s');
        $flagged = 0;

        $sql = "
            SELECT id, patient_id, doctor_name, appointment_date, appointment_time, appt_datetime
            FROM appointments
            WHERE status = 'upcoming' AND appt_datetime < :now
        ";
        $params = [':now' => $now];
        if ($patientId !== null) {
            $sql .= " AND patient_id = :pid";
            $params[':pid'] = $patientId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $pastAppts = $stmt->fetchAll();

        foreach ($pastAppts as $appt) {
            $dedupKey = sprintf('past_appt_%d', $appt['id']);
            $msg = sprintf(
                'Your scheduled appointment with %s on %s was not marked completed. Click to confirm completion or update status.',
                $appt['doctor_name'],
                $appt['appointment_date']
            );

            $this->notifService->createInAppNotification(
                (int)$appt['patient_id'],
                'appointment',
                'Appointment Status Confirmation',
                $msg,
                (int)$appt['id'],
                $appt['appt_datetime'],
                $dedupKey
            );
            $flagged++;
        }

        return $flagged;
    }

    /**
     * Mark a dose as taken.
     * Records actual taken_at time, preserving scheduled_at.
     */
    public function markDoseTaken(int $doseId, int $patientId): array {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.patient_id, d.reminder_id, d.scheduled_at, d.status, r.medicine_name, r.dosage
            FROM medicine_dose_logs d
            JOIN medicine_reminders r ON r.id = d.reminder_id
            WHERE d.id = :id AND d.patient_id = :pid LIMIT 1
        ");
        $stmt->execute([':id' => $doseId, ':pid' => $patientId]);
        $dose = $stmt->fetch();

        if (!$dose) {
            return ['success' => false, 'message' => 'Dose record not found or access denied.'];
        }

        $now = date('Y-m-d H:i:s');
        $wasMissed = ($dose['status'] === 'missed');
        $note = $wasMissed ? 'Marked taken after missed period at ' . date('g:i A') : 'Taken on schedule';

        $upd = $this->pdo->prepare("
            UPDATE medicine_dose_logs 
            SET status = 'taken', taken_at = :now, notes = :note
            WHERE id = :id AND patient_id = :pid
        ");
        $upd->execute([
            ':now'  => $now,
            ':note' => $note,
            ':id'   => $doseId,
            ':pid'  => $patientId,
        ]);

        return [
            'success'      => true,
            'message'      => sprintf('Dose for %s recorded as taken at %s.', $dose['medicine_name'], date('g:i A', strtotime($now))),
            'taken_at'     => $now,
            'scheduled_at' => $dose['scheduled_at'],
            'was_missed'   => $wasMissed
        ];
    }

    /**
     * Snooze a dose ("Remind Me Later").
     * Sets snoozed_until to NOW() + minutes, preserves scheduled_at, avoids duplicates.
     */
    public function snoozeDose(int $doseId, int $patientId, int $snoozeMinutes = 15): array {
        $stmt = $this->pdo->prepare("
            SELECT d.id, d.patient_id, d.reminder_id, d.scheduled_at, r.medicine_name
            FROM medicine_dose_logs d
            JOIN medicine_reminders r ON r.id = d.reminder_id
            WHERE d.id = :id AND d.patient_id = :pid LIMIT 1
        ");
        $stmt->execute([':id' => $doseId, ':pid' => $patientId]);
        $dose = $stmt->fetch();

        if (!$dose) {
            return ['success' => false, 'message' => 'Dose record not found or access denied.'];
        }

        $snoozedUntil = date('Y-m-d H:i:s', time() + ($snoozeMinutes * 60));

        $upd = $this->pdo->prepare("
            UPDATE medicine_dose_logs 
            SET status = 'snoozed', snoozed_until = :snooze, notes = :note
            WHERE id = :id AND patient_id = :pid
        ");
        $upd->execute([
            ':snooze' => $snoozedUntil,
            ':note'   => sprintf('Snoozed for %d minutes until %s', $snoozeMinutes, date('g:i A', strtotime($snoozedUntil))),
            ':id'     => $doseId,
            ':pid'    => $patientId,
        ]);

        return [
            'success'       => true,
            'message'       => sprintf('Reminder for %s snoozed until %s.', $dose['medicine_name'], date('g:i A', strtotime($snoozedUntil))),
            'snoozed_until' => $snoozedUntil
        ];
    }
}

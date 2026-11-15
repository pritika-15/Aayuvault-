<?php
/**
 * appointment.php — AayuVault Appointment Booking API
 *
 * Connects frontend appointment booking with:
 * - Doctors & Facilities from MySQL/database
 * - Patient Medical Conditions & Health Context
 * - Dynamic Time Slots & Date Availability
 * - Real-Time Booking & Schedule Creation
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/db.php';

$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
}

if ($reqMethod === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = getDb();
$patientId = getLoggedInPatientId();

// Parse POST/JSON input
$input = $_POST;
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$action = $_GET['action'] ?? $input['action'] ?? '';

// If POST has 'book' or 'book_appointment'
if ($reqMethod === 'POST' && (isset($_POST['book']) || $action === 'book' || $action === 'book_appointment')) {
    $action = 'book';
}

if (!function_exists('sendResp')) {
    function sendResp(array $data, int $code = 200): void {
        if (!headers_sent()) {
            http_response_code($code);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!defined('AAYUVAULT_TEST_RUNNER')) {
            exit;
        }
    }
}

try {
    switch ($action) {

        // ── 1. KEY METRICS ──────────────────────────────────────────
        case 'metrics':
        case 'get_metrics':
            $stmt = $pdo->prepare("SELECT blood_pressure, heart_rate, spo2, labs_status AS recent_labs, labs_count FROM metrics WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
            $stmt->execute([':uid' => $patientId]);
            $row = $stmt->fetch();
            if (!$row) {
                $row = [
                    'blood_pressure' => '120/80 mmHg',
                    'heart_rate'     => '72 bpm',
                    'spo2'           => 98,
                    'blood_sugar'    => '98 mg/dL',
                    'recent_labs'    => 'Optimal'
                ];
            } else {
                $row['blood_sugar'] = ($row['spo2'] ?? 98) . '%';
            }
            sendResp($row);
            break;

        // ── 2. CONDITIONS LIST (Medical Summary) ───────────────────
        case 'conditions':
        case 'get_conditions':
            $stmt = $pdo->prepare("SELECT id, condition_name AS name, condition_name, category FROM medical_summary WHERE user_id = :uid ORDER BY id ASC LIMIT 8");
            $stmt->execute([':uid' => $patientId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) {
                $rows = [
                    ['id' => 1, 'name' => 'Borderline Dyslipidemia', 'condition_name' => 'Borderline Dyslipidemia', 'category' => 'Cardiovascular'],
                    ['id' => 2, 'name' => 'Pre-Diabetes (Mild HbA1c elevation)', 'condition_name' => 'Pre-Diabetes (Mild HbA1c elevation)', 'category' => 'Metabolic'],
                    ['id' => 3, 'name' => 'Vitamin D Deficiency', 'condition_name' => 'Vitamin D Deficiency', 'category' => 'Nutritional'],
                    ['id' => 4, 'name' => 'Primary Hypertension Review', 'condition_name' => 'Primary Hypertension Review', 'category' => 'Cardiovascular'],
                ];
            }
            sendResp([
                'success'    => true,
                'conditions' => $rows,
                'data'       => $rows,
            ]);
            break;

        // ── 3. HEALTH CONTEXT TAGS ─────────────────────────────────
        case 'health_context':
        case 'get_health_context':
            $stmt = $pdo->prepare("SELECT condition_name FROM medical_summary WHERE user_id = :uid LIMIT 6");
            $stmt->execute([':uid' => $patientId]);
            $tags = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (empty($tags)) {
                $tags = ['Cardiovascular Review', 'Pre-Diabetes Monitoring', 'Annual Health Review', 'General Preventive Care'];
            }
            sendResp([
                'success' => true,
                'tags'    => $tags,
                'data'    => $tags,
            ]);
            break;

        // ── 4. CONDITION DROPDOWN OPTIONS ──────────────────────────
        case 'conditions_list':
        case 'get_condition_options':
            $options = [
                ['id' => 'Cardiovascular Review / Hypertension', 'name' => 'Cardiovascular Review / Hypertension'],
                ['id' => 'Diabetes / Endocrine Follow-up',       'name' => 'Diabetes / Endocrine Follow-up (HbA1c)'],
                ['id' => 'Borderline Dyslipidemia Check',        'name' => 'Borderline Dyslipidemia / Lipid Panel'],
                ['id' => 'Vitamin D & Nutritional Screen',       'name' => 'Vitamin D & Nutritional Screen'],
                ['id' => 'Orthopedic & Joint Consultation',      'name' => 'Orthopedic & Joint Consultation'],
                ['id' => 'Neurology Consultation',              'name' => 'Neurology Consultation'],
                ['id' => 'Dermatology & Skin Check',            'name' => 'Dermatology & Skin Check'],
                ['id' => 'General Preventive Health Checkup',    'name' => 'General Preventive Health Checkup'],
                ['id' => 'Pathology & Lab Report Review',       'name' => 'Pathology & Lab Report Review'],
            ];
            sendResp([
                'success'    => true,
                'conditions' => $options,
                'data'       => $options,
            ]);
            break;

        // ── 5. PROVIDERS (DOCTORS & FACILITIES) ────────────────────
        case 'providers':
        case 'get_providers':
            $search   = trim($_GET['search'] ?? $_GET['query'] ?? $input['search'] ?? $input['query'] ?? '');
            $filter   = trim($_GET['filter'] ?? $input['filter'] ?? '');
            $location = trim($_GET['location'] ?? $_GET['zone'] ?? $input['location'] ?? $input['zone'] ?? '');

            $sql = "
                SELECT d.id, d.doctor_code, d.name, d.specialization,
                       d.qualification, d.experience_years, d.contact_number,
                       d.consultation_fee, d.available_days, d.available_time,
                       d.location,
                       f.id AS facility_id, f.name AS facility_name, f.type AS facility_type,
                       f.city AS facility_city, f.address AS facility_address
                FROM doctors d
                LEFT JOIN medical_facilities f ON f.id = d.facility_id
                WHERE d.is_active = 1
            ";
            $params = [];

            // Smart Search across Doctor Name, Specialty, or Facility Name
            if ($search !== '') {
                // Strip common titles like "Dr." or "Dr " for matching
                $cleanSearch = preg_replace('/^dr\.?\s+/i', '', $search);
                $terms = preg_split('/\s+/', trim($cleanSearch));

                $searchClauses = [];
                $idx = 0;
                foreach ($terms as $term) {
                    if (strlen($term) >= 2) {
                        $searchClauses[] = "(d.name LIKE :s{$idx} OR d.specialization LIKE :s{$idx} OR f.name LIKE :s{$idx} OR f.city LIKE :s{$idx})";
                        $params[":s{$idx}"] = '%' . $term . '%';
                        $idx++;
                    }
                }
                if (!empty($searchClauses)) {
                    $sql .= " AND (" . implode(' AND ', $searchClauses) . ")";
                }
            }

            // Filter by Specialty
            if ($filter !== '' && $filter !== 'Filter by' && $filter !== 'all') {
                $sql .= " AND (d.specialization = :spec OR d.specialization LIKE :specLike)";
                $params[':spec'] = $filter;
                $params[':specLike'] = '%' . $filter . '%';
            }

            // Filter by Location / Zone
            if ($location !== '' && $location !== 'All Zone' && $location !== 'all') {
                $sql .= " AND (d.location LIKE :loc OR f.city LIKE :loc OR f.address LIKE :loc)";
                $params[':loc'] = '%' . $location . '%';
            }

            $sql .= " ORDER BY d.id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $doctors = $stmt->fetchAll();

            $formatted = [];
            foreach ($doctors as $d) {
                $facName = $d['facility_name'] ?: 'City Hospital';
                $facCity = $d['facility_city'] ?: ($d['location'] ?: 'Noida');
                $formatted[] = [
                    'id'               => (int)$d['id'],
                    'doctor_code'      => $d['doctor_code'] ?: ('DOC-' . $d['id']),
                    'name'             => $d['name'],
                    'specialization'   => $d['specialization'],
                    'specialty'        => $d['specialization'] . ' • ' . $facName,
                    'facility_id'      => $d['facility_id'],
                    'facility_name'    => $facName,
                    'facility_type'    => $d['facility_type'] ?: 'Hospital',
                    'facility_city'    => $facCity,
                    'address'          => $d['facility_address'] ?: ($d['location'] ?: 'Noida, UP'),
                    'qualification'    => $d['qualification'] ?: 'MBBS, MD',
                    'experience_years' => (int)($d['experience_years'] ?: 10),
                    'consultation_fee' => number_format((float)($d['consultation_fee'] ?: 1000), 2, '.', ''),
                    'available_days'   => $d['available_days'] ?: 'Mon - Sat',
                    'available_time'   => $d['available_time'] ?: '09:00 AM - 01:00 PM',
                    'rating'           => '4.9',
                ];
            }

            sendResp([
                'success'   => true,
                'providers' => $formatted,
                'data'      => $formatted,
            ]);
            break;

        // ── 6. FILTER & ZONE OPTIONS ───────────────────────────────
        case 'filter_options':
        case 'get_filters':
            sendResp([
                'success' => true,
                'filters' => [
                    ['value' => '',                  'label' => 'All Specialties'],
                    ['value' => 'Cardiology',        'label' => 'Cardiology'],
                    ['value' => 'Neurology',         'label' => 'Neurology'],
                    ['value' => 'Orthopedics',       'label' => 'Orthopedics'],
                    ['value' => 'Endocrinology',     'label' => 'Endocrinology'],
                    ['value' => 'Dermatology',       'label' => 'Dermatology'],
                    ['value' => 'General Physician', 'label' => 'General Physician'],
                    ['value' => 'Pathology',         'label' => 'Pathology'],
                ],
                'zones' => [
                    ['value' => '',          'label' => 'All Zone / Cities'],
                    ['value' => 'Noida',     'label' => 'Noida (ABC Multispecialty)'],
                    ['value' => 'Ghaziabad', 'label' => 'Ghaziabad (CareWell Clinic)'],
                    ['value' => 'New Delhi', 'label' => 'New Delhi (Metro Central)'],
                    ['value' => 'Gurugram',  'label' => 'Gurugram (Apex Labs)'],
                ],
            ]);
            break;

        // ── 7. TIME SLOTS ──────────────────────────────────────────
        case 'time_slots':
        case 'get_slots':
            $slots = [
                '09:00 AM', '09:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM',
                '02:00 PM', '02:30 PM', '03:00 PM', '03:30 PM', '04:00 PM', '04:30 PM', '05:00 PM'
            ];
            $detailedSlots = array_map(fn($s) => ['time' => $s, 'available' => true], $slots);
            sendResp([
                'success' => true,
                'slots'   => $slots,
                'data'    => $detailedSlots,
            ]);
            break;

        // ── 8. UPCOMING APPOINTMENTS ───────────────────────────────
        case 'upcoming':
        case 'get_upcoming':
            $stmt = $pdo->prepare("
                SELECT a.id, a.doctor_name, a.department, a.specialty, a.hospital_name,
                       a.appointment_date, a.appointment_time, a.status, a.notes
                FROM appointments a
                WHERE a.patient_id = :pid AND a.status != 'cancelled'
                ORDER BY a.appointment_date ASC, a.appointment_time ASC
                LIMIT 5
            ");
            $stmt->execute([':pid' => $patientId]);
            $appts = $stmt->fetchAll();

            $firstAppt = !empty($appts) ? $appts[0] : null;
            $timeText = '—';
            $futureText = 'No upcoming visits booked';

            if ($firstAppt) {
                $timeText = date('D, M j', strtotime($firstAppt['appointment_date'])) . ' at ' . substr($firstAppt['appointment_time'], 0, 5);
                $futureText = $firstAppt['doctor_name'] . ' (' . ($firstAppt['hospital_name'] ?: $firstAppt['department']) . ')';
            }

            sendResp([
                'success'      => true,
                'time'         => $timeText,
                'future_visit' => $futureText,
                'appointments' => $appts,
                'data'         => $appts,
            ]);
            break;

        // ── 9. BOOK APPOINTMENT ────────────────────────────────────
        case 'book':
        case 'book_appointment':
            if ($reqMethod !== 'POST') {
                sendResp(['success' => false, 'message' => 'POST method required.'], 405);
            }

            $docId     = (int)($input['doctor_id'] ?? $input['provider_id'] ?? 0);
            $date      = trim($input['date'] ?? $input['appointment_date'] ?? '');
            $time      = trim($input['time'] ?? $input['appointment_time'] ?? '');
            $condition = trim($input['condition'] ?? $input['notes'] ?? 'General Consultation');
            $notes     = trim($input['notes'] ?? '');

            if (!$docId || $date === '' || $time === '') {
                sendResp(['success' => false, 'message' => 'Please select a healthcare provider, appointment date, and time slot.'], 422);
            }

            // Look up Doctor & Facility details
            $docStmt = $pdo->prepare("
                SELECT d.*, f.name AS facility_name, f.address AS facility_address, f.city AS facility_city
                FROM doctors d
                LEFT JOIN medical_facilities f ON f.id = d.facility_id
                WHERE d.id = :id
            ");
            $docStmt->execute([':id' => $docId]);
            $doctor = $docStmt->fetch();

            if (!$doctor) {
                sendResp(['success' => false, 'message' => 'Selected healthcare provider could not be found.'], 404);
            }

            $docName      = $doctor['name'];
            $specialty    = $doctor['specialization'] ?: 'General Practice';
            $department   = $specialty;
            $hospitalName = $doctor['facility_name'] ?: 'City Hospital';
            $location     = $doctor['facility_address'] ?: ($doctor['location'] ?: 'Main OPD');
            $fullNotes    = $condition . ($notes && $notes !== $condition ? " • Notes: " . $notes : '');

            // Normalize time format to HH:MM:SS
            $timeFormatted = $time;
            if (strtotime($time)) {
                $timeFormatted = date('H:i:s', strtotime($time));
            }

            // 1. Insert into appointments table
            $insAppt = $pdo->prepare("
                INSERT INTO appointments
                  (patient_id, doctor_id, doctor_name, department, specialty, hospital_name,
                   appointment_date, appointment_time, appt_datetime, appointment_type, location, notes, status, created_at)
                VALUES
                  (:pid, :doc_id, :doc_name, :dept, :spec, :hosp,
                   :adate, :atime, :adatetime, 'in-person', :loc, :notes, 'upcoming', :now)
            ");
            $insAppt->execute([
                ':pid'       => $patientId,
                ':doc_id'    => $docId,
                ':doc_name'  => $docName,
                ':dept'      => $department,
                ':spec'      => $specialty,
                ':hosp'      => $hospitalName,
                ':adate'     => $date,
                ':atime'     => $timeFormatted,
                ':adatetime' => $date . ' ' . $timeFormatted,
                ':loc'       => $location,
                ':notes'     => $fullNotes,
                ':now'       => date('Y-m-d H:i:s'),
            ]);
            $apptId = (int)$pdo->lastInsertId();

            // 2. Automatically store in patient_reminders for unified notification
            try {
                $insRem = $pdo->prepare("
                    INSERT INTO patient_reminders
                      (patient_id, title, description, reminder_date, reminder_time, reminder_type, reminder_status, created_at)
                    VALUES
                      (:pid, :title, :desc, :rdate, :rtime, 'Doctor Appointment', 'Pending', :now)
                ");
                $insRem->execute([
                    ':pid'   => $patientId,
                    ':title' => "Doctor Appointment: {$docName}",
                    ':desc'  => "Consultation for {$condition} at {$hospitalName} ({$location}). Bring previous reports.",
                    ':rdate' => $date,
                    ':rtime' => $timeFormatted,
                    ':now'   => date('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $remErr) {
                error_log('[Appointment Booking Reminder Note] ' . $remErr->getMessage());
            }

            sendResp([
                'success'        => true,
                'message'        => "Appointment scheduled successfully with {$docName} on " . date('M d, Y', strtotime($date)) . " at {$time}!",
                'appointment_id' => $apptId,
                'doctor_name'    => $docName,
                'hospital_name'  => $hospitalName,
                'date'           => $date,
                'time'           => $time,
            ], 201);
            break;

        // ── 10. CANCEL APPOINTMENT ─────────────────────────────────
        case 'cancel':
        case 'cancel_appointment':
            $apptId = (int)($input['appointment_id'] ?? $_GET['id'] ?? 0);
            if (!$apptId) {
                sendResp(['success' => false, 'message' => 'Appointment ID required.'], 400);
            }
            $upd = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = :id AND patient_id = :pid");
            $upd->execute([':id' => $apptId, ':pid' => $patientId]);

            sendResp([
                'success' => $upd->rowCount() > 0,
                'message' => $upd->rowCount() > 0 ? 'Appointment cancelled.' : 'Appointment could not be cancelled.',
            ]);
            break;

        default:
            sendResp(['error' => 'Unknown action: ' . htmlspecialchars($action)], 400);
    }
} catch (Throwable $e) {
    error_log('[AayuVault Appointment Error] ' . $e->getMessage());
    sendResp(['success' => false, 'message' => 'Database operation error: ' . $e->getMessage()], 500);
}

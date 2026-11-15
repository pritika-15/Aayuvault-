<?php
/**
 * ============================================================
 * AayuVault — Doctor & Medical Facility Directory API
 * File: pages/directory_api.php
 * 
 * Handles:
 *  - Doctors CRUD, Search & Filtering
 *  - Facilities CRUD, Search & Filtering
 *  - 1:N Facility-Doctor Relationships
 *  - Filter Metadata & System Statistics
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!headers_sent()) http_response_code(204);
    exit;
}

// ── DATABASE CONFIGURATION ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

function getDirectoryDB(): ?PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHAR);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 2,
            ]);
            ensureTablesExist($pdo);
        } catch (Throwable $e) {
            $pdo = null;
        }
    }
    return $pdo;
}

function ensureTablesExist(PDO $pdo): void {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `medical_facilities` (
                `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `facility_code` varchar(50) NOT NULL UNIQUE,
                `name` varchar(150) NOT NULL,
                `type` enum('Hospital','Clinic','Diagnostic Centre','Laboratory','Pharmacy') NOT NULL DEFAULT 'Hospital',
                `address` text NOT NULL,
                `city` varchar(100) NOT NULL,
                `state` varchar(100) NOT NULL,
                `contact_number` varchar(30) NOT NULL,
                `email` varchar(100) NOT NULL,
                `departments` text DEFAULT NULL,
                `opening_hours` varchar(150) NOT NULL,
                `verification_status` enum('Verified','Pending','Suspended') NOT NULL DEFAULT 'Verified',
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `doctors` (
                `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `doctor_code` varchar(50) NOT NULL UNIQUE,
                `name` varchar(100) NOT NULL,
                `specialization` varchar(100) NOT NULL,
                `qualification` varchar(100) NOT NULL,
                `experience_years` int(11) NOT NULL DEFAULT 0,
                `contact_number` varchar(30) NOT NULL,
                `email` varchar(100) NOT NULL,
                `facility_id` int(11) DEFAULT NULL,
                `consultation_fee` decimal(10,2) DEFAULT NULL,
                `available_days` varchar(150) NOT NULL,
                `available_time` varchar(100) NOT NULL,
                `location` varchar(150) NOT NULL,
                `verification_status` enum('Verified','Pending','Rejected') NOT NULL DEFAULT 'Verified',
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                FOREIGN KEY (`facility_id`) REFERENCES `medical_facilities`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $cnt = $pdo->query("SELECT COUNT(*) FROM `medical_facilities`")->fetchColumn();
        if ($cnt == 0) {
            seedInitialDirectoryData($pdo);
        }
    } catch (Throwable $e) {}
}

function seedInitialDirectoryData(PDO $pdo): void {
    $facilities = getFallbackFacilities();
    $stmtFac = $pdo->prepare("
        INSERT INTO `medical_facilities` 
        (`id`, `facility_code`, `name`, `type`, `address`, `city`, `state`, `contact_number`, `email`, `departments`, `opening_hours`, `verification_status`, `is_active`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($facilities as $f) {
        $stmtFac->execute([
            $f['id'], $f['facility_code'], $f['name'], $f['type'], $f['address'],
            $f['city'], $f['state'], $f['contact_number'], $f['email'],
            $f['departments'], $f['opening_hours'], $f['verification_status'], $f['is_active']
        ]);
    }

    $doctors = getFallbackDoctors();
    $stmtDoc = $pdo->prepare("
        INSERT INTO `doctors`
        (`id`, `doctor_code`, `name`, `specialization`, `qualification`, `experience_years`, `contact_number`, `email`, `facility_id`, `consultation_fee`, `available_days`, `available_time`, `location`, `verification_status`, `is_active`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($doctors as $d) {
        $stmtDoc->execute([
            $d['id'], $d['doctor_code'], $d['name'], $d['specialization'], $d['qualification'],
            $d['experience_years'], $d['contact_number'], $d['email'], $d['facility_id'],
            $d['consultation_fee'], $d['available_days'], $d['available_time'], $d['location'],
            $d['verification_status'], $d['is_active']
        ]);
    }
}

// ── FALLBACK DEMO DATA ──────────────────────────────────────
function getFallbackFacilities(): array {
    return [
        [
            'id' => 1,
            'facility_code' => 'FAC-101',
            'name' => 'ABC Multispecialty Hospital',
            'type' => 'Hospital',
            'address' => 'Plot 12, Institutional Area, Sector 62',
            'city' => 'Noida',
            'state' => 'Uttar Pradesh',
            'contact_number' => '+91 120 4567890',
            'email' => 'contact@abchospital.com',
            'departments' => 'Cardiology, Neurology, Orthopedics, Emergency & Trauma, General Surgery, Pediatrics',
            'opening_hours' => '24x7 Emergency, OPD: Mon-Sat 08:00 AM - 08:00 PM',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-10 10:00:00',
        ],
        [
            'id' => 2,
            'facility_code' => 'FAC-102',
            'name' => 'CareWell Polyclinic & Diagnostics',
            'type' => 'Clinic',
            'address' => 'Shop 4-6, Ground Floor, Galaxy Plaza, Indirapuram',
            'city' => 'Ghaziabad',
            'state' => 'Uttar Pradesh',
            'contact_number' => '+91 120 9876543',
            'email' => 'care@carewellclinic.in',
            'departments' => 'General Medicine, Dermatology, Endocrinology, Dental Care',
            'opening_hours' => 'Mon - Sat: 09:00 AM - 09:00 PM, Sun: 10:00 AM - 02:00 PM',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-12 11:30:00',
        ],
        [
            'id' => 3,
            'facility_code' => 'FAC-103',
            'name' => 'Metro Central Diagnostic Centre',
            'type' => 'Diagnostic Centre',
            'address' => 'B-45, Central Market, South Extension Part II',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'contact_number' => '+91 11 26789012',
            'email' => 'info@metrodiagnostics.org',
            'departments' => 'Advanced Radiology, 3T MRI, 128 Slice CT, 2D Echo, Ultrasound',
            'opening_hours' => 'Mon - Sun: 07:00 AM - 09:00 PM',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-15 09:15:00',
        ],
        [
            'id' => 4,
            'facility_code' => 'FAC-104',
            'name' => 'Apex Clinical Laboratories',
            'type' => 'Laboratory',
            'address' => 'Plot 88, Sector 18, Electronic City',
            'city' => 'Gurugram',
            'state' => 'Haryana',
            'contact_number' => '+91 124 3456789',
            'email' => 'lab@apexlabs.com',
            'departments' => 'Pathology, Biochemistry, Molecular Biology, Hematology, Immunology',
            'opening_hours' => 'Mon - Sat: 06:30 AM - 08:30 PM, Home Sample Pickup 24x7',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-20 08:45:00',
        ],
        [
            'id' => 5,
            'facility_code' => 'FAC-105',
            'name' => 'MedPlus Wellness Pharmacy',
            'type' => 'Pharmacy',
            'address' => 'Shop 12, Near Metro Station, Sector 16',
            'city' => 'Noida',
            'state' => 'Uttar Pradesh',
            'contact_number' => '+91 120 6543210',
            'email' => 'noida16@medpluspharmacy.com',
            'departments' => 'Prescription Medications, Chronic Care Refills, Cold Chain Vaccines, Surgical Supplies',
            'opening_hours' => 'Mon - Sun: 07:00 AM - 11:00 PM',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-25 14:20:00',
        ]
    ];
}

function getFallbackDoctors(): array {
    return [
        [
            'id' => 1,
            'doctor_code' => 'DOC-201',
            'name' => 'Dr. Priya Sharma',
            'specialization' => 'Cardiology',
            'qualification' => 'MBBS, MD, DM (Cardiology), FACC',
            'experience_years' => 14,
            'contact_number' => '+91 98765 43210',
            'email' => 'dr.priyasharma@abchospital.com',
            'facility_id' => 1,
            'facility_name' => 'ABC Multispecialty Hospital',
            'facility_type' => 'Hospital',
            'facility_city' => 'Noida',
            'consultation_fee' => '1200.00',
            'available_days' => 'Mon, Wed, Fri',
            'available_time' => '09:00 AM - 01:00 PM, 04:00 PM - 07:00 PM',
            'location' => 'Noida, UP',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-10 10:30:00',
        ],
        [
            'id' => 2,
            'doctor_code' => 'DOC-202',
            'name' => 'Dr. Rahul Sharma',
            'specialization' => 'Cardiology',
            'qualification' => 'MBBS, MD (Medicine), DM (Cardiology)',
            'experience_years' => 10,
            'contact_number' => '+91 98111 22334',
            'email' => 'dr.rahul@abchospital.com',
            'facility_id' => 1,
            'facility_name' => 'ABC Multispecialty Hospital',
            'facility_type' => 'Hospital',
            'facility_city' => 'Noida',
            'consultation_fee' => '1000.00',
            'available_days' => 'Tue, Thu, Sat',
            'available_time' => '10:00 AM - 02:00 PM, 05:00 PM - 08:00 PM',
            'location' => 'Noida, UP',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-10 11:00:00',
        ],
        [
            'id' => 3,
            'doctor_code' => 'DOC-203',
            'name' => 'Dr. Priya Singh',
            'specialization' => 'Neurology',
            'qualification' => 'MBBS, MD (Medicine), DM (Neurology)',
            'experience_years' => 12,
            'contact_number' => '+91 98222 33445',
            'email' => 'dr.priyasingh@abchospital.com',
            'facility_id' => 1,
            'facility_name' => 'ABC Multispecialty Hospital',
            'facility_type' => 'Hospital',
            'facility_city' => 'Noida',
            'consultation_fee' => '1500.00',
            'available_days' => 'Mon, Tue, Thu, Fri',
            'available_time' => '11:00 AM - 03:00 PM',
            'location' => 'Noida, UP',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-10 11:30:00',
        ],
        [
            'id' => 4,
            'doctor_code' => 'DOC-204',
            'name' => 'Dr. Amit Kumar',
            'specialization' => 'Orthopedics',
            'qualification' => 'MBBS, MS (Orthopedics), MCh (Joint Replacement)',
            'experience_years' => 16,
            'contact_number' => '+91 98333 44556',
            'email' => 'dr.amitkumar@abchospital.com',
            'facility_id' => 1,
            'facility_name' => 'ABC Multispecialty Hospital',
            'facility_type' => 'Hospital',
            'facility_city' => 'Noida',
            'consultation_fee' => '1100.00',
            'available_days' => 'Mon, Wed, Sat',
            'available_time' => '09:30 AM - 01:30 PM, 04:30 PM - 07:30 PM',
            'location' => 'Noida, UP',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-10 12:00:00',
        ],
        [
            'id' => 5,
            'doctor_code' => 'DOC-205',
            'name' => 'Dr. Rajeev Mehta',
            'specialization' => 'Endocrinology',
            'qualification' => 'MBBS, MD (Internal Medicine), DM (Endocrinology)',
            'experience_years' => 11,
            'contact_number' => '+91 98444 55667',
            'email' => 'dr.rajeev@carewellclinic.in',
            'facility_id' => 2,
            'facility_name' => 'CareWell Polyclinic & Diagnostics',
            'facility_type' => 'Clinic',
            'facility_city' => 'Ghaziabad',
            'consultation_fee' => '900.00',
            'available_days' => 'Mon - Fri',
            'available_time' => '04:00 PM - 08:30 PM',
            'location' => 'Ghaziabad, UP',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-12 12:00:00',
        ],
        [
            'id' => 6,
            'doctor_code' => 'DOC-206',
            'name' => 'Dr. Shalini Verma',
            'specialization' => 'Dermatology',
            'qualification' => 'MBBS, MD (Dermatology, Venereology & Leprosy)',
            'experience_years' => 8,
            'contact_number' => '+91 98555 66778',
            'email' => 'dr.shalini@carewellclinic.in',
            'facility_id' => 2,
            'facility_name' => 'CareWell Polyclinic & Diagnostics',
            'facility_type' => 'Clinic',
            'facility_city' => 'Ghaziabad',
            'consultation_fee' => '800.00',
            'available_days' => 'Tue, Thu, Sat, Sun',
            'available_time' => '10:00 AM - 02:00 PM',
            'location' => 'Ghaziabad, UP',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-12 14:30:00',
        ],
        [
            'id' => 7,
            'doctor_code' => 'DOC-207',
            'name' => 'Dr. Anil Verma',
            'specialization' => 'General Physician',
            'qualification' => 'MBBS, MD (General Medicine)',
            'experience_years' => 18,
            'contact_number' => '+91 98666 77889',
            'email' => 'dr.anilverma@metrodiagnostics.org',
            'facility_id' => 3,
            'facility_name' => 'Metro Central Diagnostic Centre',
            'facility_type' => 'Diagnostic Centre',
            'facility_city' => 'New Delhi',
            'consultation_fee' => '700.00',
            'available_days' => 'Mon - Sat',
            'available_time' => '08:00 AM - 12:00 PM',
            'location' => 'New Delhi, DL',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-15 10:00:00',
        ],
        [
            'id' => 8,
            'doctor_code' => 'DOC-208',
            'name' => 'Dr. Sunita Kulkarni',
            'specialization' => 'Pathology',
            'qualification' => 'MBBS, MD (Pathology), DNB',
            'experience_years' => 15,
            'contact_number' => '+91 98777 88990',
            'email' => 'dr.skulkarni@apexlabs.com',
            'facility_id' => 4,
            'facility_name' => 'Apex Clinical Laboratories',
            'facility_type' => 'Laboratory',
            'facility_city' => 'Gurugram',
            'consultation_fee' => '600.00',
            'available_days' => 'Mon - Fri',
            'available_time' => '09:00 AM - 05:00 PM',
            'location' => 'Gurugram, HR',
            'verification_status' => 'Verified',
            'is_active' => 1,
            'created_at' => '2025-01-20 09:30:00',
        ],
    ];
}

// ── SESSION STORE FOR DEMO FALLBACK MUTATIONS ────────────────
if (!isset($_SESSION['demo_facilities'])) {
    $_SESSION['demo_facilities'] = getFallbackFacilities();
}
if (!isset($_SESSION['demo_doctors'])) {
    $_SESSION['demo_doctors'] = getFallbackDoctors();
}

function jsonOut(array $data, int $status = 200): void {
    if (!headers_sent()) {
        http_response_code($status);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── ROUTER ───────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDirectoryDB();

switch ($action) {

    // =========================================================
    //  1. FACILITIES ENDPOINTS
    // =========================================================
    case 'get_facilities':
        $search   = trim($_GET['search'] ?? '');
        $type     = trim($_GET['type'] ?? '');
        $location = trim($_GET['location'] ?? '');
        $status   = trim($_GET['status'] ?? '');
        $active   = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;

        if ($pdo) {
            try {
                $sql = "
                    SELECT f.*, 
                           COUNT(d.id) AS doctor_count
                    FROM `medical_facilities` f
                    LEFT JOIN `doctors` d ON d.facility_id = f.id AND d.is_active = 1
                    WHERE 1=1
                ";
                $params = [];

                if ($search !== '') {
                    $sql .= " AND (f.name LIKE ? OR f.address LIKE ? OR f.city LIKE ? OR f.state LIKE ? OR f.departments LIKE ? OR f.facility_code LIKE ?)";
                    $kw = "%$search%";
                    $params = array_merge($params, [$kw, $kw, $kw, $kw, $kw, $kw]);
                }
                if ($type !== '') {
                    $sql .= " AND f.type = ?";
                    $params[] = $type;
                }
                if ($location !== '') {
                    $sql .= " AND (f.city LIKE ? OR f.state LIKE ? OR f.address LIKE ?)";
                    $locKw = "%$location%";
                    $params = array_merge($params, [$locKw, $locKw, $locKw]);
                }
                if ($status !== '') {
                    $sql .= " AND f.verification_status = ?";
                    $params[] = $status;
                }
                if ($active !== null) {
                    $sql .= " AND f.is_active = ?";
                    $params[] = $active;
                }

                $sql .= " GROUP BY f.id ORDER BY f.id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $facilities = $stmt->fetchAll();
                jsonOut(['success' => true, 'facilities' => $facilities, 'source' => 'db']);
            } catch (Throwable $e) {}
        }

        // Demo Fallback
        $facilities = $_SESSION['demo_facilities'];
        $docs = $_SESSION['demo_doctors'];

        $filtered = array_filter($facilities, function ($f) use ($search, $type, $location, $status, $active) {
            if ($search !== '') {
                $haystack = strtolower($f['name'] . ' ' . $f['address'] . ' ' . $f['city'] . ' ' . $f['state'] . ' ' . $f['departments'] . ' ' . $f['facility_code']);
                if (strpos($haystack, strtolower($search)) === false) return false;
            }
            if ($type !== '' && $f['type'] !== $type) return false;
            if ($location !== '') {
                $locHaystack = strtolower($f['city'] . ' ' . $f['state'] . ' ' . $f['address']);
                if (strpos($locHaystack, strtolower($location)) === false) return false;
            }
            if ($status !== '' && $f['verification_status'] !== $status) return false;
            if ($active !== null && (int)$f['is_active'] !== $active) return false;
            return true;
        });

        $res = [];
        foreach ($filtered as $fac) {
            $fac['doctor_count'] = count(array_filter($docs, fn($d) => $d['facility_id'] == $fac['id'] && (!empty($d['is_active']))));
            $res[] = $fac;
        }

        jsonOut(['success' => true, 'facilities' => array_values($res), 'source' => 'demo']);
        break;

    case 'get_facility':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'Facility ID is required.'], 400);

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM `medical_facilities` WHERE id = ?");
                $stmt->execute([$id]);
                $facility = $stmt->fetch();

                if ($facility) {
                    $stmtDocs = $pdo->prepare("
                        SELECT * FROM `doctors` 
                        WHERE facility_id = ? 
                        ORDER BY name ASC
                    ");
                    $stmtDocs->execute([$id]);
                    $facility['doctors'] = $stmtDocs->fetchAll();
                    jsonOut(['success' => true, 'facility' => $facility, 'source' => 'db']);
                }
            } catch (Throwable $e) {}
        }

        // Demo Fallback
        $facilities = $_SESSION['demo_facilities'];
        $docs = $_SESSION['demo_doctors'];
        foreach ($facilities as $f) {
            if ($f['id'] == $id) {
                $f['doctors'] = array_values(array_filter($docs, fn($d) => $d['facility_id'] == $id));
                jsonOut(['success' => true, 'facility' => $f, 'source' => 'demo']);
            }
        }
        jsonOut(['success' => false, 'message' => 'Facility not found.'], 404);
        break;

    case 'save_facility':
        $id                  = (int)($_POST['id'] ?? 0);
        $facility_code       = trim($_POST['facility_code'] ?? '');
        $name                = trim($_POST['name'] ?? '');
        $type                = trim($_POST['type'] ?? 'Hospital');
        $address             = trim($_POST['address'] ?? '');
        $city                = trim($_POST['city'] ?? '');
        $state               = trim($_POST['state'] ?? '');
        $contact_number      = trim($_POST['contact_number'] ?? '');
        $email               = trim($_POST['email'] ?? '');
        $departments         = trim($_POST['departments'] ?? '');
        $opening_hours       = trim($_POST['opening_hours'] ?? '');
        $verification_status = trim($_POST['verification_status'] ?? 'Verified');
        $is_active           = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if ($name === '' || $address === '' || $city === '' || $state === '' || $contact_number === '' || $email === '' || $opening_hours === '') {
            jsonOut(['success' => false, 'message' => 'Please fill in all required facility details.'], 400);
        }

        if ($facility_code === '') {
            $facility_code = 'FAC-' . rand(100, 999);
        }

        if ($pdo) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE `medical_facilities` 
                        SET `facility_code` = ?, `name` = ?, `type` = ?, `address` = ?, 
                            `city` = ?, `state` = ?, `contact_number` = ?, `email` = ?, 
                            `departments` = ?, `opening_hours` = ?, `verification_status` = ?, 
                            `is_active` = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $facility_code, $name, $type, $address, $city, $state,
                        $contact_number, $email, $departments, $opening_hours,
                        $verification_status, $is_active, $id
                    ]);
                    jsonOut(['success' => true, 'message' => 'Medical Facility updated successfully!', 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO `medical_facilities` 
                        (`facility_code`, `name`, `type`, `address`, `city`, `state`, `contact_number`, `email`, `departments`, `opening_hours`, `verification_status`, `is_active`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $facility_code, $name, $type, $address, $city, $state,
                        $contact_number, $email, $departments, $opening_hours,
                        $verification_status, $is_active
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    jsonOut(['success' => true, 'message' => 'Medical Facility registered successfully!', 'id' => $newId]);
                }
            } catch (Throwable $e) {}
        }

        // Demo session mutation
        if ($id > 0) {
            foreach ($_SESSION['demo_facilities'] as &$f) {
                if ($f['id'] == $id) {
                    $f = array_merge($f, compact('facility_code','name','type','address','city','state','contact_number','email','departments','opening_hours','verification_status','is_active'));
                    jsonOut(['success' => true, 'message' => 'Facility updated successfully (Demo).', 'id' => $id]);
                }
            }
        } else {
            $newId = count($_SESSION['demo_facilities']) > 0 ? max(array_column($_SESSION['demo_facilities'], 'id')) + 1 : 1;
            $newFac = compact('facility_code','name','type','address','city','state','contact_number','email','departments','opening_hours','verification_status','is_active');
            $newFac['id'] = $newId;
            $newFac['created_at'] = date('Y-m-d H:i:s');
            $_SESSION['demo_facilities'][] = $newFac;
            jsonOut(['success' => true, 'message' => 'Facility registered successfully (Demo).', 'id' => $newId]);
        }
        break;

    case 'delete_facility':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'Facility ID is required.'], 400);

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `medical_facilities` WHERE id = ?");
                $stmt->execute([$id]);
                jsonOut(['success' => true, 'message' => 'Facility removed successfully.']);
            } catch (Throwable $e) {}
        }

        $_SESSION['demo_facilities'] = array_values(array_filter($_SESSION['demo_facilities'], fn($f) => $f['id'] != $id));
        foreach ($_SESSION['demo_doctors'] as &$d) {
            if ($d['facility_id'] == $id) {
                $d['facility_id'] = null;
                $d['facility_name'] = 'Independent Practice';
            }
        }
        jsonOut(['success' => true, 'message' => 'Facility removed successfully (Demo).']);
        break;

    case 'toggle_facility_status':
        $id = (int)($_POST['id'] ?? 0);
        $field = $_POST['field'] ?? 'is_active';
        $value = $_POST['value'] ?? null;

        if (!$id || $value === null) jsonOut(['success' => false, 'message' => 'Invalid parameters.'], 400);

        if ($pdo) {
            try {
                if ($field === 'is_active') {
                    $stmt = $pdo->prepare("UPDATE `medical_facilities` SET `is_active` = ? WHERE id = ?");
                    $stmt->execute([(int)$value, $id]);
                } else if ($field === 'verification_status') {
                    $stmt = $pdo->prepare("UPDATE `medical_facilities` SET `verification_status` = ? WHERE id = ?");
                    $stmt->execute([$value, $id]);
                }
                jsonOut(['success' => true, 'message' => 'Status updated.']);
            } catch (Throwable $e) {}
        }

        foreach ($_SESSION['demo_facilities'] as &$f) {
            if ($f['id'] == $id) {
                if ($field === 'is_active') $f['is_active'] = (int)$value;
                if ($field === 'verification_status') $f['verification_status'] = $value;
                jsonOut(['success' => true, 'message' => 'Status updated (Demo).']);
            }
        }
        jsonOut(['success' => false, 'message' => 'Facility not found.'], 404);
        break;

    // =========================================================
    //  2. DOCTORS ENDPOINTS
    // =========================================================
    case 'get_doctors':
        $search         = trim($_GET['search'] ?? '');
        $specialization = trim($_GET['specialization'] ?? '');
        $facility_id    = isset($_GET['facility_id']) && $_GET['facility_id'] !== '' ? (int)$_GET['facility_id'] : null;
        $status         = trim($_GET['status'] ?? '');
        $active         = isset($_GET['is_active']) ? (int)$_GET['is_active'] : null;

        if ($pdo) {
            try {
                $sql = "
                    SELECT d.*, 
                           f.name AS facility_name,
                           f.type AS facility_type,
                           f.city AS facility_city,
                           f.contact_number AS facility_contact
                    FROM `doctors` d
                    LEFT JOIN `medical_facilities` f ON f.id = d.facility_id
                    WHERE 1=1
                ";
                $params = [];

                if ($search !== '') {
                    $sql .= " AND (d.name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ? OR d.doctor_code LIKE ? OR d.location LIKE ? OR f.name LIKE ?)";
                    $kw = "%$search%";
                    $params = array_merge($params, [$kw, $kw, $kw, $kw, $kw, $kw]);
                }
                if ($specialization !== '') {
                    $sql .= " AND d.specialization = ?";
                    $params[] = $specialization;
                }
                if ($facility_id !== null) {
                    $sql .= " AND d.facility_id = ?";
                    $params[] = $facility_id;
                }
                if ($status !== '') {
                    $sql .= " AND d.verification_status = ?";
                    $params[] = $status;
                }
                if ($active !== null) {
                    $sql .= " AND d.is_active = ?";
                    $params[] = $active;
                }

                $sql .= " ORDER BY d.id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $doctors = $stmt->fetchAll();
                jsonOut(['success' => true, 'doctors' => $doctors, 'source' => 'db']);
            } catch (Throwable $e) {}
        }

        // Demo Fallback
        $doctors = $_SESSION['demo_doctors'];
        $facilities = $_SESSION['demo_facilities'];
        $facMap = [];
        foreach ($facilities as $f) $facMap[$f['id']] = $f;

        $filtered = array_filter($doctors, function ($d) use ($search, $specialization, $facility_id, $status, $active, $facMap) {
            $facName = isset($facMap[$d['facility_id']]) ? $facMap[$d['facility_id']]['name'] : '';
            if ($search !== '') {
                $haystack = strtolower($d['name'] . ' ' . $d['specialization'] . ' ' . $d['qualification'] . ' ' . $d['doctor_code'] . ' ' . $d['location'] . ' ' . $facName);
                if (strpos($haystack, strtolower($search)) === false) return false;
            }
            if ($specialization !== '' && $d['specialization'] !== $specialization) return false;
            if ($facility_id !== null && (int)$d['facility_id'] !== $facility_id) return false;
            if ($status !== '' && $d['verification_status'] !== $status) return false;
            if ($active !== null && (int)$d['is_active'] !== $active) return false;
            return true;
        });

        $res = [];
        foreach ($filtered as $doc) {
            if (isset($facMap[$doc['facility_id']])) {
                $doc['facility_name'] = $facMap[$doc['facility_id']]['name'];
                $doc['facility_type'] = $facMap[$doc['facility_id']]['type'];
                $doc['facility_city'] = $facMap[$doc['facility_id']]['city'];
            } else {
                $doc['facility_name'] = 'Independent Clinic';
                $doc['facility_type'] = 'Clinic';
                $doc['facility_city'] = $doc['location'] ?? 'Delhi NCR';
            }
            $res[] = $doc;
        }

        jsonOut(['success' => true, 'doctors' => array_values($res), 'source' => 'demo']);
        break;

    case 'get_doctor':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'Doctor ID is required.'], 400);

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    SELECT d.*, 
                           f.name AS facility_name,
                           f.type AS facility_type,
                           f.address AS facility_address,
                           f.city AS facility_city,
                           f.state AS facility_state,
                           f.contact_number AS facility_contact,
                           f.email AS facility_email,
                           f.opening_hours AS facility_hours
                    FROM `doctors` d
                    LEFT JOIN `medical_facilities` f ON f.id = d.facility_id
                    WHERE d.id = ?
                ");
                $stmt->execute([$id]);
                $doctor = $stmt->fetch();
                if ($doctor) {
                    jsonOut(['success' => true, 'doctor' => $doctor, 'source' => 'db']);
                }
            } catch (Throwable $e) {}
        }

        // Demo Fallback
        $doctors = $_SESSION['demo_doctors'];
        $facilities = $_SESSION['demo_facilities'];
        $facMap = [];
        foreach ($facilities as $f) $facMap[$f['id']] = $f;

        foreach ($doctors as $d) {
            if ($d['id'] == $id) {
                if (isset($facMap[$d['facility_id']])) {
                    $d['facility_name']    = $facMap[$d['facility_id']]['name'];
                    $d['facility_type']    = $facMap[$d['facility_id']]['type'];
                    $d['facility_address'] = $facMap[$d['facility_id']]['address'];
                    $d['facility_city']    = $facMap[$d['facility_id']]['city'];
                    $d['facility_state']   = $facMap[$d['facility_id']]['state'];
                    $d['facility_contact'] = $facMap[$d['facility_id']]['contact_number'];
                    $d['facility_email']   = $facMap[$d['facility_id']]['email'];
                    $d['facility_hours']   = $facMap[$d['facility_id']]['opening_hours'];
                }
                jsonOut(['success' => true, 'doctor' => $d, 'source' => 'demo']);
            }
        }
        jsonOut(['success' => false, 'message' => 'Doctor not found.'], 404);
        break;

    case 'save_doctor':
        $id                  = (int)($_POST['id'] ?? 0);
        $doctor_code         = trim($_POST['doctor_code'] ?? '');
        $name                = trim($_POST['name'] ?? '');
        $specialization      = trim($_POST['specialization'] ?? '');
        $qualification       = trim($_POST['qualification'] ?? '');
        $experience_years    = (int)($_POST['experience_years'] ?? 0);
        $contact_number      = trim($_POST['contact_number'] ?? '');
        $email               = trim($_POST['email'] ?? '');
        $facility_id         = !empty($_POST['facility_id']) ? (int)$_POST['facility_id'] : null;
        $consultation_fee    = !empty($_POST['consultation_fee']) ? (float)$_POST['consultation_fee'] : null;
        $available_days      = trim($_POST['available_days'] ?? '');
        $available_time      = trim($_POST['available_time'] ?? '');
        $location            = trim($_POST['location'] ?? '');
        $verification_status = trim($_POST['verification_status'] ?? 'Verified');
        $is_active           = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if ($name === '' || $specialization === '' || $qualification === '' || $contact_number === '' || $email === '' || $available_days === '' || $available_time === '' || $location === '') {
            jsonOut(['success' => false, 'message' => 'Please fill in all mandatory doctor details.'], 400);
        }

        if ($doctor_code === '') {
            $doctor_code = 'DOC-' . rand(200, 999);
        }

        if ($pdo) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE `doctors`
                        SET `doctor_code` = ?, `name` = ?, `specialization` = ?, `qualification` = ?, 
                            `experience_years` = ?, `contact_number` = ?, `email` = ?, `facility_id` = ?, 
                            `consultation_fee` = ?, `available_days` = ?, `available_time` = ?, 
                            `location` = ?, `verification_status` = ?, `is_active` = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $doctor_code, $name, $specialization, $qualification,
                        $experience_years, $contact_number, $email, $facility_id,
                        $consultation_fee, $available_days, $available_time,
                        $location, $verification_status, $is_active, $id
                    ]);
                    jsonOut(['success' => true, 'message' => 'Doctor profile updated successfully!', 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO `doctors`
                        (`doctor_code`, `name`, `specialization`, `qualification`, `experience_years`, `contact_number`, `email`, `facility_id`, `consultation_fee`, `available_days`, `available_time`, `location`, `verification_status`, `is_active`)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $doctor_code, $name, $specialization, $qualification,
                        $experience_years, $contact_number, $email, $facility_id,
                        $consultation_fee, $available_days, $available_time,
                        $location, $verification_status, $is_active
                    ]);
                    $newId = (int)$pdo->lastInsertId();
                    jsonOut(['success' => true, 'message' => 'Doctor registered successfully!', 'id' => $newId]);
                }
            } catch (Throwable $e) {}
        }

        // Demo session mutation
        if ($id > 0) {
            foreach ($_SESSION['demo_doctors'] as &$d) {
                if ($d['id'] == $id) {
                    $d = array_merge($d, compact('doctor_code','name','specialization','qualification','experience_years','contact_number','email','facility_id','consultation_fee','available_days','available_time','location','verification_status','is_active'));
                    jsonOut(['success' => true, 'message' => 'Doctor profile updated (Demo).', 'id' => $id]);
                }
            }
        } else {
            $newId = count($_SESSION['demo_doctors']) > 0 ? max(array_column($_SESSION['demo_doctors'], 'id')) + 1 : 1;
            $newDoc = compact('doctor_code','name','specialization','qualification','experience_years','contact_number','email','facility_id','consultation_fee','available_days','available_time','location','verification_status','is_active');
            $newDoc['id'] = $newId;
            $newDoc['created_at'] = date('Y-m-d H:i:s');
            $_SESSION['demo_doctors'][] = $newDoc;
            jsonOut(['success' => true, 'message' => 'Doctor profile registered (Demo).', 'id' => $newId]);
        }
        break;

    case 'delete_doctor':
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'Doctor ID is required.'], 400);

        if ($pdo) {
            try {
                $stmt = $pdo->prepare("DELETE FROM `doctors` WHERE id = ?");
                $stmt->execute([$id]);
                jsonOut(['success' => true, 'message' => 'Doctor removed successfully.']);
            } catch (Throwable $e) {}
        }

        $_SESSION['demo_doctors'] = array_values(array_filter($_SESSION['demo_doctors'], fn($d) => $d['id'] != $id));
        jsonOut(['success' => true, 'message' => 'Doctor removed successfully (Demo).']);
        break;

    case 'toggle_doctor_status':
        $id = (int)($_POST['id'] ?? 0);
        $field = $_POST['field'] ?? 'is_active';
        $value = $_POST['value'] ?? null;

        if (!$id || $value === null) jsonOut(['success' => false, 'message' => 'Invalid parameters.'], 400);

        if ($pdo) {
            try {
                if ($field === 'is_active') {
                    $stmt = $pdo->prepare("UPDATE `doctors` SET `is_active` = ? WHERE id = ?");
                    $stmt->execute([(int)$value, $id]);
                } else if ($field === 'verification_status') {
                    $stmt = $pdo->prepare("UPDATE `doctors` SET `verification_status` = ? WHERE id = ?");
                    $stmt->execute([$value, $id]);
                }
                jsonOut(['success' => true, 'message' => 'Doctor status updated.']);
            } catch (Throwable $e) {}
        }

        foreach ($_SESSION['demo_doctors'] as &$d) {
            if ($d['id'] == $id) {
                if ($field === 'is_active') $d['is_active'] = (int)$value;
                if ($field === 'verification_status') $d['verification_status'] = $value;
                jsonOut(['success' => true, 'message' => 'Doctor status updated (Demo).']);
            }
        }
        jsonOut(['success' => false, 'message' => 'Doctor not found.'], 404);
        break;

    // =========================================================
    //  3. FILTER METADATA & STATS
    // =========================================================
    case 'get_filter_meta':
        $specializations = [
            'Cardiology', 'Neurology', 'Orthopedics', 'Dermatology', 
            'Endocrinology', 'General Physician', 'Pediatrics', 
            'Pathology', 'Gastroenterology', 'Gynecology', 'Psychiatry', 'ENT'
        ];
        $facility_types = ['Hospital', 'Clinic', 'Diagnostic Centre', 'Laboratory', 'Pharmacy'];
        
        $facilities_list = [];
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT id, name, type, city FROM `medical_facilities` WHERE is_active = 1 ORDER BY name ASC");
                $facilities_list = $stmt->fetchAll();
            } catch (Throwable $e) {}
        }
        if (empty($facilities_list)) {
            $facilities_list = array_map(fn($f) => ['id' => $f['id'], 'name' => $f['name'], 'type' => $f['type'], 'city' => $f['city']], $_SESSION['demo_facilities']);
        }

        jsonOut([
            'success' => true,
            'specializations' => $specializations,
            'facility_types'  => $facility_types,
            'facilities'      => $facilities_list,
        ]);
        break;

    case 'get_stats':
        if ($pdo) {
            try {
                $docTotal    = (int)$pdo->query("SELECT COUNT(*) FROM `doctors`")->fetchColumn();
                $docVerified = (int)$pdo->query("SELECT COUNT(*) FROM `doctors` WHERE verification_status = 'Verified' AND is_active = 1")->fetchColumn();
                $facTotal    = (int)$pdo->query("SELECT COUNT(*) FROM `medical_facilities`")->fetchColumn();
                $facVerified = (int)$pdo->query("SELECT COUNT(*) FROM `medical_facilities` WHERE verification_status = 'Verified' AND is_active = 1")->fetchColumn();

                jsonOut([
                    'success' => true,
                    'stats' => [
                        'total_doctors'       => $docTotal,
                        'verified_doctors'    => $docVerified,
                        'total_facilities'    => $facTotal,
                        'verified_facilities' => $facVerified,
                    ]
                ]);
            } catch (Throwable $e) {}
        }

        $docs = $_SESSION['demo_doctors'];
        $facs = $_SESSION['demo_facilities'];

        jsonOut([
            'success' => true,
            'stats' => [
                'total_doctors'       => count($docs),
                'verified_doctors'    => count(array_filter($docs, fn($d) => $d['verification_status'] === 'Verified' && !empty($d['is_active']))),
                'total_facilities'    => count($facs),
                'verified_facilities' => count(array_filter($facs, fn($f) => $f['verification_status'] === 'Verified' && !empty($f['is_active']))),
            ]
        ]);
        break;

    default:
        jsonOut(['success' => false, 'error' => 'Unknown directory action: ' . htmlspecialchars($action)], 400);
}

<?php
/**
 * db.php — AayuVault Unified Database Connection & Helper
 *
 * Provides safe PDO database connections, environment configuration parsing,
 * schema auto-initialization for medicine reminders and appointment alerts,
 * and security helper functions (authentication & CSRF).
 */

if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ── Timezone Configuration ─────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Load .env File If Present ──────────────────────────────────
function loadEnvFile(string $path): array {
    $env = [];
    if (!file_exists($path)) {
        return $env;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            $val = trim($val, "\"'");
            $env[$key] = $val;
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
            }
        }
    }
    return $env;
}

$envPath = __DIR__ . '/../.env';
$appEnv  = loadEnvFile($envPath);

// ── Database Configuration Constants ───────────────────────────
if (!defined('DB_HOST'))    define('DB_HOST',    $_ENV['DB_HOST'] ?? 'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    $_ENV['DB_NAME'] ?? 'clinic_db');
if (!defined('DB_USER'))    define('DB_USER',    $_ENV['DB_USER'] ?? 'root');
if (!defined('DB_PASS'))    define('DB_PASS',    $_ENV['DB_PASS'] ?? '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Get or initialize PDO database connection.
 * If MySQL fails and an SQLite fallback is acceptable, uses a local SQLite store.
 */
function getDb(bool $forceNew = false): PDO {
    static $pdoInstance = null;
    if ($pdoInstance !== null && !$forceNew) {
        return $pdoInstance;
    }

    // Attempt 1: Connect to MySQL
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdoInstance = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        ensureReminderSchema($pdoInstance, 'mysql');
        return $pdoInstance;
    } catch (Throwable $mysqlEx) {
        error_log('[AayuVault DB] MySQL connection note: ' . $mysqlEx->getMessage());

        // Attempt 2: Local SQLite fallback (for automated tests or offline sandbox)
        $sqliteDir = __DIR__ . '/../database';
        if (!is_dir($sqliteDir)) {
            @mkdir($sqliteDir, 0777, true);
        }
        $sqlitePath = $sqliteDir . '/aayuvault_local.sqlite';
        try {
            $pdoInstance = new PDO('sqlite:' . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            ensureReminderSchema($pdoInstance, 'sqlite');
            return $pdoInstance;
        } catch (Throwable $sqliteEx) {
            error_log('[AayuVault DB] SQLite fallback failed: ' . $sqliteEx->getMessage());
            throw new RuntimeException('Database service is unavailable. Please start XAMPP MySQL or check database settings.');
        }
    }
}

/**
 * Ensures required tables and seed data exist.
 */
function ensureReminderSchema(PDO $pdo, string $driver = 'mysql'): void {
    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'patient',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            INSERT OR IGNORE INTO users (id, username, email, password_hash, full_name, role, is_active)
            VALUES (1, 'testuser', 'test@example.com', '$2y$12$3AqfbsH1TUBhWmS9wchu4Oh/06v3vFaO.H1WU9PKqb36fSnFbEKJC', 'Test User', 'patient', 1);

            CREATE TABLE IF NOT EXISTS medicine_reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id INTEGER NOT NULL,
                medicine_name TEXT NOT NULL,
                dosage TEXT NOT NULL,
                instructions TEXT DEFAULT '',
                frequency TEXT NOT NULL DEFAULT 'once_daily',
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                scheduled_times TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS medicine_dose_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reminder_id INTEGER NOT NULL,
                patient_id INTEGER NOT NULL,
                scheduled_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                taken_at TEXT DEFAULT NULL,
                snoozed_until TEXT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (reminder_id, scheduled_at)
            );

            CREATE TABLE IF NOT EXISTS appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id INTEGER NOT NULL,
                doctor_id INTEGER DEFAULT NULL,
                doctor_name TEXT NOT NULL,
                department TEXT NOT NULL,
                specialty TEXT DEFAULT '',
                hospital_name TEXT DEFAULT '',
                appointment_date TEXT NOT NULL,
                appointment_time TEXT NOT NULL,
                appt_datetime TEXT NOT NULL,
                appointment_type TEXT NOT NULL DEFAULT 'in-person',
                location TEXT DEFAULT '',
                consultation_link TEXT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'upcoming',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id INTEGER NOT NULL,
                notification_type TEXT NOT NULL,
                related_record_id INTEGER DEFAULT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                scheduled_at TEXT NOT NULL,
                sent_at TEXT DEFAULT NULL,
                read_at TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                channel TEXT NOT NULL DEFAULT 'in_app',
                dedup_key TEXT UNIQUE DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS patient_reminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                patient_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                reminder_date TEXT NOT NULL,
                reminder_time TEXT NOT NULL,
                reminder_type TEXT NOT NULL DEFAULT 'Other',
                reminder_status TEXT NOT NULL DEFAULT 'Pending',
                notified_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS medical_facilities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                facility_code TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'Hospital',
                address TEXT NOT NULL,
                city TEXT NOT NULL,
                state TEXT NOT NULL,
                contact_number TEXT NOT NULL,
                email TEXT NOT NULL,
                departments TEXT DEFAULT NULL,
                opening_hours TEXT NOT NULL,
                verification_status TEXT NOT NULL DEFAULT 'Verified',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS doctors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doctor_code TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                specialization TEXT NOT NULL,
                qualification TEXT NOT NULL,
                experience_years INTEGER NOT NULL DEFAULT 0,
                contact_number TEXT NOT NULL,
                email TEXT NOT NULL,
                facility_id INTEGER DEFAULT NULL,
                consultation_fee REAL DEFAULT NULL,
                available_days TEXT NOT NULL,
                available_time TEXT NOT NULL,
                location TEXT NOT NULL,
                verification_status TEXT NOT NULL DEFAULT 'Verified',
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS metrics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                blood_pressure TEXT NOT NULL,
                heart_rate INTEGER NOT NULL,
                spo2 INTEGER NOT NULL,
                labs_status TEXT NOT NULL,
                labs_count INTEGER NOT NULL,
                recorded_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS medical_summary (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                condition_name TEXT NOT NULL,
                category TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS medical_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL DEFAULT 1,
                date_label TEXT NOT NULL,
                title TEXT NOT NULL,
                subtitle TEXT DEFAULT '',
                type TEXT DEFAULT 'lab',
                facility TEXT DEFAULT '',
                doctor TEXT DEFAULT '',
                has_pdf INTEGER DEFAULT 0,
                pdf_path TEXT DEFAULT '',
                raw_text TEXT DEFAULT NULL,
                ai_analyzed INTEGER DEFAULT 1,
                vital_score INTEGER DEFAULT 85,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS report_analyses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                record_id INTEGER DEFAULT NULL,
                user_id INTEGER NOT NULL DEFAULT 1,
                report_title TEXT NOT NULL,
                report_type TEXT DEFAULT 'Lab Test',
                facility TEXT DEFAULT '',
                report_date TEXT NOT NULL,
                vital_score INTEGER NOT NULL DEFAULT 80,
                overall_status TEXT NOT NULL DEFAULT 'Normal',
                summary_text TEXT NOT NULL,
                biomarkers_json TEXT NOT NULL,
                risks_json TEXT DEFAULT NULL,
                diet_lifestyle_json TEXT DEFAULT NULL,
                doctor_questions_json TEXT DEFAULT NULL,
                organ_impact_json TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed initial schedules, facilities, doctors, metrics, medical_records if empty
        $pdo->exec("
            INSERT OR IGNORE INTO medical_records (id, user_id, date_label, title, subtitle, type, facility, doctor, has_pdf, pdf_path, ai_analyzed) VALUES
            (1, 1, 'Apr 02, 2025', 'Comprehensive Lipid Panel', 'Total Cholesterol: 218 mg/dL (High), LDL: 138 mg/dL, HDL: 44 mg/dL', 'lab', 'Quest Diagnostics', 'Dr. Priya Sharma', 1, 'reports/lipid_panel_apr2025.pdf', 1),
            (2, 1, 'Mar 18, 2025', 'Glycemic & HbA1c Panel', 'HbA1c: 5.9% (Pre-diabetic range), Fasting Glucose: 104 mg/dL', 'lab', 'LabCorp', 'Dr. Rajeev Mehta', 1, 'reports/hba1c_mar2025.pdf', 1),
            (3, 1, 'Feb 28, 2025', 'Complete Blood Count (CBC)', 'Hemoglobin: 14.2 g/dL (Normal), WBC: 6,800 /uL, Platelets: 245k', 'lab', 'NaviSite Health', 'Dr. Anil Verma', 1, 'reports/cbc_feb2025.pdf', 1),
            (4, 1, 'Jan 15, 2025', 'Comprehensive Metabolic & Liver Panel', 'ALT/SGPT: 38 U/L, Creatinine: 0.95 mg/dL, eGFR: >90, Bilirubin: 0.8', 'lab', 'NovaCare Health', 'Dr. S. Kulkarni', 1, 'reports/cmp_jan2025.pdf', 1),
            (5, 1, 'Apr 05, 2025', 'Cardiology Prescription (Dr. Priya Sharma)', 'Rx: Atorvastatin 20mg (1 Tab OD at night), Aspirin 75mg (1 Tab OD after lunch). For lipid management.', 'prescription', 'Quest Heart Clinic', 'Dr. Priya Sharma', 1, 'reports/rx_atorvastatin.pdf', 1);
            INSERT OR IGNORE INTO medical_facilities (id, facility_code, name, type, address, city, state, contact_number, email, departments, opening_hours, verification_status, is_active)
            VALUES
            (1, 'FAC-101', 'ABC Multispecialty Hospital', 'Hospital', 'Plot 12, Institutional Area, Sector 62', 'Noida', 'Uttar Pradesh', '+91 120 4567890', 'contact@abchospital.com', 'Cardiology, Neurology, Orthopedics, Emergency & Trauma, General Surgery, Pediatrics', '24x7 Emergency, OPD: Mon-Sat 08:00 AM - 08:00 PM', 'Verified', 1),
            (2, 'FAC-102', 'CareWell Polyclinic & Diagnostics', 'Clinic', 'Shop 4-6, Ground Floor, Galaxy Plaza, Indirapuram', 'Ghaziabad', 'Uttar Pradesh', '+91 120 9876543', 'care@carewellclinic.in', 'General Medicine, Dermatology, Endocrinology, Dental Care', 'Mon - Sat: 09:00 AM - 09:00 PM, Sun: 10:00 AM - 02:00 PM', 'Verified', 1),
            (3, 'FAC-103', 'Metro Central Diagnostic Centre', 'Diagnostic Centre', 'B-45, Central Market, South Extension Part II', 'New Delhi', 'Delhi', '+91 11 26789012', 'info@metrodiagnostics.org', 'Advanced Radiology, 3T MRI, 128 Slice CT, 2D Echo, Ultrasound', 'Mon - Sun: 07:00 AM - 09:00 PM', 'Verified', 1),
            (4, 'FAC-104', 'Apex Clinical Laboratories', 'Laboratory', 'Plot 88, Sector 18, Electronic City', 'Gurugram', 'Haryana', '+91 124 3456789', 'lab@apexlabs.com', 'Pathology, Biochemistry, Molecular Biology, Hematology, Immunology', 'Mon - Sat: 06:30 AM - 08:30 PM, Home Sample Pickup 24x7', 'Verified', 1),
            (5, 'FAC-105', 'MedPlus Wellness Pharmacy', 'Pharmacy', 'Shop 12, Near Metro Station, Sector 16', 'Noida', 'Uttar Pradesh', '+91 120 6543210', 'noida16@medpluspharmacy.com', 'Prescription Medications, Chronic Care Refills, Cold Chain Vaccines, Surgical Supplies', 'Mon - Sun: 07:00 AM - 11:00 PM', 'Verified', 1);

            INSERT OR IGNORE INTO doctors (id, doctor_code, name, specialization, qualification, experience_years, contact_number, email, facility_id, consultation_fee, available_days, available_time, location, verification_status, is_active)
            VALUES
            (1, 'DOC-201', 'Dr. Priya Sharma', 'Cardiology', 'MBBS, MD, DM (Cardiology), FACC', 14, '+91 98765 43210', 'dr.priyasharma@abchospital.com', 1, 1200.00, 'Mon, Wed, Fri', '09:00 AM - 01:00 PM, 04:00 PM - 07:00 PM', 'Noida, UP', 'Verified', 1),
            (2, 'DOC-202', 'Dr. Rahul Sharma', 'Cardiology', 'MBBS, MD (Medicine), DM (Cardiology)', 10, '+91 98111 22334', 'dr.rahul@abchospital.com', 1, 1000.00, 'Tue, Thu, Sat', '10:00 AM - 02:00 PM, 05:00 PM - 08:00 PM', 'Noida, UP', 'Verified', 1),
            (3, 'DOC-203', 'Dr. Priya Singh', 'Neurology', 'MBBS, MD (Medicine), DM (Neurology)', 12, '+91 98222 33445', 'dr.priyasingh@abchospital.com', 1, 1500.00, 'Mon, Tue, Thu, Fri', '11:00 AM - 03:00 PM', 'Noida, UP', 'Verified', 1),
            (4, 'DOC-204', 'Dr. Amit Kumar', 'Orthopedics', 'MBBS, MS (Orthopedics), MCh (Joint Replacement)', 16, '+91 98333 44556', 'dr.amitkumar@abchospital.com', 1, 1100.00, 'Mon, Wed, Sat', '09:30 AM - 01:30 PM, 04:30 PM - 07:30 PM', 'Noida, UP', 'Verified', 1),
            (5, 'DOC-205', 'Dr. Rajeev Mehta', 'Endocrinology', 'MBBS, MD (Internal Medicine), DM (Endocrinology)', 11, '+91 98444 55667', 'dr.rajeev@carewellclinic.in', 2, 900.00, 'Mon - Fri', '04:00 PM - 08:30 PM', 'Ghaziabad, UP', 'Verified', 1),
            (6, 'DOC-206', 'Dr. Shalini Verma', 'Dermatology', 'MBBS, MD (Dermatology, Venereology & Leprosy)', 8, '+91 98555 66778', 'dr.shalini@carewellclinic.in', 2, 800.00, 'Tue, Thu, Sat, Sun', '10:00 AM - 02:00 PM', 'Ghaziabad, UP', 'Verified', 1),
            (7, 'DOC-207', 'Dr. Anil Verma', 'General Physician', 'MBBS, MD (General Medicine)', 18, '+91 98666 77889', 'dr.anilverma@metrodiagnostics.org', 3, 700.00, 'Mon - Sat', '08:00 AM - 12:00 PM', 'New Delhi, DL', 'Verified', 1),
            (8, 'DOC-208', 'Dr. Sunita Kulkarni', 'Pathology', 'MBBS, MD (Pathology), DNB', 15, '+91 98777 88990', 'dr.skulkarni@apexlabs.com', 4, 600.00, 'Mon - Fri', '09:00 AM - 05:00 PM', 'Gurugram, HR', 'Verified', 1);

            INSERT OR IGNORE INTO medical_summary (id, user_id, condition_name, category)
            VALUES
            (1, 1, 'Borderline Dyslipidemia', 'Cardiovascular'),
            (2, 1, 'Pre-Diabetes (Mild HbA1c elevation)', 'Metabolic'),
            (3, 1, 'Vitamin D Deficiency', 'Nutritional'),
            (4, 1, 'Primary Hypertension Review', 'Cardiovascular'),
            (5, 1, 'Annual Preventive Health Checkup', 'Preventive Care');

            INSERT OR IGNORE INTO metrics (id, user_id, blood_pressure, heart_rate, spo2, labs_status, labs_count)
            VALUES
            (1, 1, '120/80', 72, 98, 'Optimal', 4);
        ");

        // Seed initial schedules if empty
        $cnt = $pdo->query("SELECT COUNT(*) FROM medicine_reminders WHERE patient_id = 1")->fetchColumn();
        if ($cnt == 0) {
            $today = date('Y-m-d');
            $plus50 = date('Y-m-d', strtotime('+50 days'));
            $pdo->exec("
                INSERT INTO medicine_reminders (id, patient_id, medicine_name, dosage, instructions, frequency, start_date, end_date, scheduled_times, is_active)
                VALUES 
                (1, 1, 'Atorvastatin', '20 mg', 'Take after dinner at bedtime', 'once_daily', '$today', '$plus50', '[\"21:00\"]', 1),
                (2, 1, 'Metformin', '500 mg', 'Take with meals (breakfast & dinner)', 'twice_daily', '$today', '$plus50', '[\"08:30\", \"20:30\"]', 1),
                (3, 1, 'Vitamin D3', '1000 IU', 'Take after breakfast with water', 'once_daily', '$today', '$plus50', '[\"09:00\"]', 1);

                INSERT INTO appointments (id, patient_id, doctor_id, doctor_name, department, specialty, hospital_name, appointment_date, appointment_time, appt_datetime, appointment_type, location, notes, status)
                VALUES
                (101, 1, 1, 'Dr. Priya Sharma', 'Cardiology', 'Cardiology', 'City Hospital, Suite 302', '$today', '16:00:00', '{$today} 16:00:00', 'in-person', 'City Hospital, OPD Floor 3', 'Follow-up consultation for lipid panel and blood pressure.', 'upcoming');
            ");
        }
        return;
    }

    // MySQL Schema creation
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `medicine_reminders` (
            `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `patient_id` INT(11) NOT NULL,
            `medicine_name` VARCHAR(200) NOT NULL,
            `dosage` VARCHAR(100) NOT NULL,
            `instructions` VARCHAR(255) DEFAULT '',
            `frequency` VARCHAR(50) NOT NULL DEFAULT 'once_daily',
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `scheduled_times` JSON NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_patient_active` (`patient_id`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `medicine_dose_logs` (
            `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `reminder_id` INT(11) NOT NULL,
            `patient_id` INT(11) NOT NULL,
            `scheduled_at` DATETIME NOT NULL,
            `status` ENUM('pending', 'taken', 'missed', 'snoozed') NOT NULL DEFAULT 'pending',
            `taken_at` DATETIME DEFAULT NULL,
            `snoozed_until` DATETIME DEFAULT NULL,
            `notes` VARCHAR(255) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_reminder_dose` (`reminder_id`, `scheduled_at`),
            INDEX `idx_patient_status_sched` (`patient_id`, `status`, `scheduled_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `patient_id` INT(11) NOT NULL,
            `notification_type` ENUM('medicine', 'appointment', 'system') NOT NULL,
            `related_record_id` INT(11) DEFAULT NULL,
            `title` VARCHAR(255) NOT NULL,
            `message` TEXT NOT NULL,
            `scheduled_at` DATETIME NOT NULL,
            `sent_at` DATETIME DEFAULT NULL,
            `read_at` DATETIME DEFAULT NULL,
            `status` ENUM('pending', 'sent', 'read', 'dismissed') NOT NULL DEFAULT 'pending',
            `channel` VARCHAR(50) NOT NULL DEFAULT 'in_app',
            `dedup_key` VARCHAR(120) UNIQUE DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_patient_notif` (`patient_id`, `status`, `scheduled_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `patient_reminders` (
            `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `patient_id` INT(11) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `reminder_date` DATE NOT NULL,
            `reminder_time` TIME NOT NULL,
            `reminder_type` ENUM('Medicine', 'Blood Test', 'Doctor Appointment', 'Health Checkup', 'Other') NOT NULL DEFAULT 'Other',
            `reminder_status` ENUM('Pending', 'Completed', 'Missed') NOT NULL DEFAULT 'Pending',
            `notified_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_patient_reminders` (`patient_id`, `reminder_status`, `reminder_date`, `reminder_time`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    try {
        $pdo->exec("ALTER TABLE `notifications` MODIFY COLUMN `notification_type` VARCHAR(50) NOT NULL DEFAULT 'reminder'");
    } catch (Throwable $e) {}

    // Check columns of appointments table and add if missing
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'appointments'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("
                CREATE TABLE `appointments` (
                    `id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
                    `patient_id` INT(11) NOT NULL,
                    `doctor_id` INT(11) DEFAULT NULL,
                    `doctor_name` VARCHAR(100) NOT NULL,
                    `department` VARCHAR(100) NOT NULL,
                    `specialty` VARCHAR(100) NOT NULL DEFAULT '',
                    `hospital_name` VARCHAR(150) NOT NULL DEFAULT '',
                    `appointment_date` DATE NOT NULL,
                    `appointment_time` TIME NOT NULL,
                    `appt_datetime` DATETIME NOT NULL,
                    `appointment_type` ENUM('in-person', 'online') NOT NULL DEFAULT 'in-person',
                    `location` VARCHAR(255) DEFAULT '',
                    `consultation_link` VARCHAR(500) DEFAULT NULL,
                    `notes` TEXT DEFAULT NULL,
                    `status` ENUM('upcoming', 'completed', 'cancelled', 'missed') NOT NULL DEFAULT 'upcoming',
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_patient_appt_date` (`patient_id`, `appointment_date`, `status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } else {
            // Check and add missing columns
            $colsStmt = $pdo->query("SHOW COLUMNS FROM `appointments`");
            $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);

            if (!in_array('patient_id', $cols) && in_array('user_id', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `patient_id` INT(11) NULL DEFAULT 1 AFTER `id`");
                @$pdo->exec("UPDATE `appointments` SET `patient_id` = `user_id` WHERE `patient_id` IS NULL");
            }
            if (!in_array('department', $cols) && in_array('specialty', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `department` VARCHAR(100) NOT NULL DEFAULT '' AFTER `doctor_name`");
                @$pdo->exec("UPDATE `appointments` SET `department` = `specialty` WHERE `department` = ''");
            }
            if (!in_array('hospital_name', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `hospital_name` VARCHAR(150) NOT NULL DEFAULT 'City Hospital' AFTER `specialty`");
            }
            if (!in_array('appointment_date', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `appointment_date` DATE NULL AFTER `hospital_name`");
                @$pdo->exec("UPDATE `appointments` SET `appointment_date` = DATE(`appt_datetime`) WHERE `appointment_date` IS NULL");
            }
            if (!in_array('appointment_time', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `appointment_time` TIME NULL AFTER `appointment_date`");
                @$pdo->exec("UPDATE `appointments` SET `appointment_time` = TIME(`appt_datetime`) WHERE `appointment_time` IS NULL");
            }
            if (!in_array('appointment_type', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `appointment_type` ENUM('in-person', 'online') NOT NULL DEFAULT 'in-person' AFTER `appt_datetime`");
            }
            if (!in_array('location', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `location` VARCHAR(255) DEFAULT 'Main Clinic OPD' AFTER `appointment_type`");
            }
            if (!in_array('consultation_link', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `consultation_link` VARCHAR(500) DEFAULT NULL AFTER `location`");
            }
            if (!in_array('notes', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `consultation_link`");
            }
            if (!in_array('status', $cols)) {
                @$pdo->exec("ALTER TABLE `appointments` ADD COLUMN `status` ENUM('upcoming', 'completed', 'cancelled', 'missed') NOT NULL DEFAULT 'upcoming' AFTER `notes`");
            }
        }
    } catch (Throwable $e) {
        error_log('[AayuVault DB] Table alter check: ' . $e->getMessage());
    }

    // Seed default records if empty
    $chk = $pdo->query("SELECT COUNT(*) FROM `medicine_reminders` WHERE `patient_id` = 1")->fetchColumn();
    if ($chk == 0) {
        $pdo->exec("
            INSERT INTO `medicine_reminders` 
              (`id`, `patient_id`, `medicine_name`, `dosage`, `instructions`, `frequency`, `start_date`, `end_date`, `scheduled_times`, `is_active`) 
            VALUES
              (1, 1, 'Atorvastatin', '20 mg', 'Take after dinner at bedtime', 'once_daily', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 50 DAY, '[\"21:00\"]', 1),
              (2, 1, 'Metformin', '500 mg', 'Take with meals (breakfast & dinner)', 'twice_daily', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 45 DAY, '[\"08:30\", \"20:30\"]', 1),
              (3, 1, 'Vitamin D3', '1000 IU', 'Take after breakfast with water', 'once_daily', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, '[\"09:00\"]', 1)
            ON DUPLICATE KEY UPDATE `medicine_name` = VALUES(`medicine_name`);
        ");
    }
}

// ── Security & Authentication Helpers ──────────────────────────

/**
 * Get current authenticated patient ID or default to 1 for dev.
 */
function getLoggedInPatientId(): int {
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
        return (int)$_SESSION['user_id'];
    }
    // For local dev convenience if not logged in
    return 1;
}

/**
 * Get or create CSRF token for current session.
 */
function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token for state-changing requests (POST, PUT, DELETE).
 */
function validateCsrfToken(?string $token): bool {
    // If not supplied or session token missing, reject
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * JSON Response helper
 */
function sendJson(array $data, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

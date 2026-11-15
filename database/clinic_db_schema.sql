-- phpMyAdmin SQL Dump
-- Database: `clinic_db`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `medical_facilities`;
DROP TABLE IF EXISTS `report_analyses`;
DROP TABLE IF EXISTS `medical_records`;
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `metrics`;
DROP TABLE IF EXISTS `medical_summary`;
DROP TABLE IF EXISTS `health_timeline`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `abha_otp`;
DROP TABLE IF EXISTS `reminders`;
DROP TABLE IF EXISTS `aadhaar_otp`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'patient',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `abha_id` varchar(50) DEFAULT NULL,
  `aadhaar_number` varchar(20) DEFAULT NULL,
  `aadhaar_verified` tinyint(1) NOT NULL DEFAULT 0,
  `aadhaar_name` varchar(100) DEFAULT NULL,
  `aadhaar_dob` varchar(20) DEFAULT NULL,
  `aadhaar_gender` varchar(20) DEFAULT NULL,
  `aadhaar_address` text DEFAULT NULL,
  `aadhaar_verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creating default users (patient and admin)
-- default password is 'password123'
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
('testuser', 'test@example.com', '$2y$12$3AqfbsH1TUBhWmS9wchu4Oh/06v3vFaO.H1WU9PKqb36fSnFbEKJC', 'Test User', 'patient', 1),
('admin', 'admin@aayuvault.org', '$2y$12$3AqfbsH1TUBhWmS9wchu4Oh/06v3vFaO.H1WU9PKqb36fSnFbEKJC', 'System Administrator', 'admin', 1);

-- --------------------------------------------------------
-- Table structure for `login_logs`
-- --------------------------------------------------------
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `logged_in_at` datetime NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `metrics`
-- --------------------------------------------------------
CREATE TABLE `metrics` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `blood_pressure` varchar(20) NOT NULL,
  `heart_rate` int(11) NOT NULL,
  `spo2` int(11) NOT NULL,
  `labs_status` varchar(50) NOT NULL,
  `labs_count` int(11) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `metrics` (`user_id`, `blood_pressure`, `heart_rate`, `spo2`, `labs_status`, `labs_count`) VALUES
(1, '120/80', 72, 98, 'Attention (1 High)', 4);

-- --------------------------------------------------------
-- Table structure for `medical_summary`
-- --------------------------------------------------------
CREATE TABLE `medical_summary` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `condition_name` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `medical_summary` (`user_id`, `condition_name`, `category`) VALUES
(1, 'Borderline Dyslipidemia', 'Cardiovascular'),
(1, 'Pre-Diabetes (Mild HbA1c elevation)', 'Metabolic'),
(1, 'Vitamin D Deficiency', 'Nutritional');

-- --------------------------------------------------------
-- Table structure for `health_timeline`
-- --------------------------------------------------------
CREATE TABLE `health_timeline` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `health_timeline` (`user_id`, `title`, `event_date`) VALUES
(1, 'Comprehensive Lipid Panel - Quest Diagnostics', '2025-04-02'),
(1, 'Glycemic HbA1c Profile - LabCorp', '2025-03-18'),
(1, 'Complete Blood Count (CBC) - NaviSite Health', '2025-02-28'),
(1, 'Comprehensive Metabolic & Liver Panel - NovaCare', '2025-01-15');

-- --------------------------------------------------------
-- Table structure for `appointments`
-- --------------------------------------------------------
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `doctor_name` varchar(100) NOT NULL,
  `specialty` varchar(100) NOT NULL,
  `appt_datetime` datetime NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `appointments` (`user_id`, `doctor_name`, `specialty`, `appt_datetime`) VALUES
(1, 'Dr. Priya Sharma', 'Cardiology', DATE_ADD(NOW(), INTERVAL 2 DAY)),
(1, 'Dr. Rajeev Mehta', 'Endocrinology', DATE_ADD(NOW(), INTERVAL 4 DAY));

-- --------------------------------------------------------
-- Table structure for `medical_records`
-- --------------------------------------------------------
CREATE TABLE `medical_records` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `date_label` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(300) DEFAULT '',
  `type` varchar(50) DEFAULT 'lab',
  `facility` varchar(150) DEFAULT '',
  `doctor` varchar(100) DEFAULT '',
  `has_pdf` tinyint(1) DEFAULT 0,
  `pdf_path` varchar(500) DEFAULT '',
  `raw_text` longtext DEFAULT NULL,
  `ai_analyzed` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `medical_records` (`id`, `user_id`, `date_label`, `title`, `subtitle`, `type`, `facility`, `doctor`, `has_pdf`, `pdf_path`, `ai_analyzed`) VALUES
(1, 1, 'Apr 02, 2025', 'Comprehensive Lipid Panel', 'Total Cholesterol: 218 mg/dL (High), LDL: 138 mg/dL, HDL: 44 mg/dL', 'lab', 'Quest Diagnostics', 'Dr. Priya Sharma', 1, 'reports/lipid_panel_apr2025.pdf', 1),
(2, 1, 'Mar 18, 2025', 'Glycemic & HbA1c Panel', 'HbA1c: 5.9% (Pre-diabetic range), Fasting Glucose: 104 mg/dL', 'lab', 'LabCorp', 'Dr. Rajeev Mehta', 1, 'reports/hba1c_mar2025.pdf', 1),
(3, 1, 'Feb 28, 2025', 'Complete Blood Count (CBC)', 'Hemoglobin: 14.2 g/dL (Normal), WBC: 6,800 /uL, Platelets: 245k', 'lab', 'NaviSite Health', 'Dr. Anil Verma', 1, 'reports/cbc_feb2025.pdf', 1),
(4, 1, 'Jan 15, 2025', 'Comprehensive Metabolic & Liver Panel', 'ALT/SGPT: 38 U/L, Creatinine: 0.95 mg/dL, eGFR: >90, Bilirubin: 0.8', 'lab', 'NovaCare Health', 'Dr. S. Kulkarni', 1, 'reports/cmp_jan2025.pdf', 1);

-- --------------------------------------------------------
-- Table structure for `report_analyses`
-- --------------------------------------------------------
CREATE TABLE `report_analyses` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `record_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `report_title` varchar(255) NOT NULL,
  `report_type` varchar(100) DEFAULT 'Lab Test',
  `facility` varchar(150) DEFAULT '',
  `report_date` varchar(50) NOT NULL,
  `vital_score` int(11) NOT NULL DEFAULT 80,
  `overall_status` varchar(50) NOT NULL DEFAULT 'Normal',
  `summary_text` text NOT NULL,
  `biomarkers_json` longtext NOT NULL,
  `risks_json` text DEFAULT NULL,
  `diet_lifestyle_json` text DEFAULT NULL,
  `doctor_questions_json` text DEFAULT NULL,
  `organ_impact_json` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `abha_otp`
-- --------------------------------------------------------
CREATE TABLE `abha_otp` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `abha_id` varchar(50) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `otp` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  UNIQUE KEY `abha_mobile_idx` (`abha_id`, `mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `aadhaar_otp`
-- --------------------------------------------------------
CREATE TABLE `aadhaar_otp` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `aadhaar_number` varchar(20) NOT NULL,
  `txn_id` varchar(64) NOT NULL UNIQUE,
  `mobile_masked` varchar(20) NOT NULL,
  `otp` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for `medical_facilities`
-- --------------------------------------------------------
CREATE TABLE `medical_facilities` (
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

-- Seed Medical Facilities
INSERT INTO `medical_facilities` (`id`, `facility_code`, `name`, `type`, `address`, `city`, `state`, `contact_number`, `email`, `departments`, `opening_hours`, `verification_status`, `is_active`) VALUES
(1, 'FAC-101', 'ABC Multispecialty Hospital', 'Hospital', 'Plot 12, Institutional Area, Sector 62', 'Noida', 'Uttar Pradesh', '+91 120 4567890', 'contact@abchospital.com', 'Cardiology, Neurology, Orthopedics, Emergency & Trauma, General Surgery, Pediatrics', '24x7 Emergency, OPD: Mon-Sat 08:00 AM - 08:00 PM', 'Verified', 1),
(2, 'FAC-102', 'CareWell Polyclinic & Diagnostics', 'Clinic', 'Shop 4-6, Ground Floor, Galaxy Plaza, Indirapuram', 'Ghaziabad', 'Uttar Pradesh', '+91 120 9876543', 'care@carewellclinic.in', 'General Medicine, Dermatology, Endocrinology, Dental Care', 'Mon - Sat: 09:00 AM - 09:00 PM, Sun: 10:00 AM - 02:00 PM', 'Verified', 1),
(3, 'FAC-103', 'Metro Central Diagnostic Centre', 'Diagnostic Centre', 'B-45, Central Market, South Extension Part II', 'New Delhi', 'Delhi', '+91 11 26789012', 'info@metrodiagnostics.org', 'Advanced Radiology, 3T MRI, 128 Slice CT, 2D Echo, Ultrasound', 'Mon - Sun: 07:00 AM - 09:00 PM', 'Verified', 1),
(4, 'FAC-104', 'Apex Clinical Laboratories', 'Laboratory', 'Plot 88, Sector 18, Electronic City', 'Gurugram', 'Haryana', '+91 124 3456789', 'lab@apexlabs.com', 'Pathology, Biochemistry, Molecular Biology, Hematology, Immunology', 'Mon - Sat: 06:30 AM - 08:30 PM, Home Sample Pickup 24x7', 'Verified', 1),
(5, 'FAC-105', 'MedPlus Wellness Pharmacy', 'Pharmacy', 'Shop 12, Near Metro Station, Sector 16', 'Noida', 'Uttar Pradesh', '+91 120 6543210', 'noida16@medpluspharmacy.com', 'Prescription Medications, Chronic Care Refills, Cold Chain Vaccines, Surgical Supplies', 'Mon - Sun: 07:00 AM - 11:00 PM', 'Verified', 1);

-- --------------------------------------------------------
-- Table structure for `doctors`
-- --------------------------------------------------------
CREATE TABLE `doctors` (
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

-- Seed Doctors
INSERT INTO `doctors` (`id`, `doctor_code`, `name`, `specialization`, `qualification`, `experience_years`, `contact_number`, `email`, `facility_id`, `consultation_fee`, `available_days`, `available_time`, `location`, `verification_status`, `is_active`) VALUES
(1, 'DOC-201', 'Dr. Priya Sharma', 'Cardiology', 'MBBS, MD, DM (Cardiology), FACC', 14, '+91 98765 43210', 'dr.priyasharma@abchospital.com', 1, 1200.00, 'Mon, Wed, Fri', '09:00 AM - 01:00 PM, 04:00 PM - 07:00 PM', 'Noida, UP', 'Verified', 1),
(2, 'DOC-202', 'Dr. Rahul Sharma', 'Cardiology', 'MBBS, MD (Medicine), DM (Cardiology)', 10, '+91 98111 22334', 'dr.rahul@abchospital.com', 1, 1000.00, 'Tue, Thu, Sat', '10:00 AM - 02:00 PM, 05:00 PM - 08:00 PM', 'Noida, UP', 'Verified', 1),
(3, 'DOC-203', 'Dr. Priya Singh', 'Neurology', 'MBBS, MD (Medicine), DM (Neurology)', 12, '+91 98222 33445', 'dr.priyasingh@abchospital.com', 1, 1500.00, 'Mon, Tue, Thu, Fri', '11:00 AM - 03:00 PM', 'Noida, UP', 'Verified', 1),
(4, 'DOC-204', 'Dr. Amit Kumar', 'Orthopedics', 'MBBS, MS (Orthopedics), MCh (Joint Replacement)', 16, '+91 98333 44556', 'dr.amitkumar@abchospital.com', 1, 1100.00, 'Mon, Wed, Sat', '09:30 AM - 01:30 PM, 04:30 PM - 07:30 PM', 'Noida, UP', 'Verified', 1),
(5, 'DOC-205', 'Dr. Rajeev Mehta', 'Endocrinology', 'MBBS, MD (Internal Medicine), DM (Endocrinology)', 11, '+91 98444 55667', 'dr.rajeev@carewellclinic.in', 2, 900.00, 'Mon - Fri', '04:00 PM - 08:30 PM', 'Ghaziabad, UP', 'Verified', 1),
(6, 'DOC-206', 'Dr. Shalini Verma', 'Dermatology', 'MBBS, MD (Dermatology, Venereology & Leprosy)', 8, '+91 98555 66778', 'dr.shalini@carewellclinic.in', 2, 800.00, 'Tue, Thu, Sat, Sun', '10:00 AM - 02:00 PM', 'Ghaziabad, UP', 'Verified', 1),
(7, 'DOC-207', 'Dr. Anil Verma', 'General Physician', 'MBBS, MD (General Medicine)', 18, '+91 98666 77889', 'dr.anilverma@metrodiagnostics.org', 3, 700.00, 'Mon - Sat', '08:00 AM - 12:00 PM', 'New Delhi, DL', 'Verified', 1),
(8, 'DOC-208', 'Dr. Sunita Kulkarni', 'Pathology', 'MBBS, MD (Pathology), DNB', 15, '+91 98777 88990', 'dr.skulkarni@apexlabs.com', 4, 600.00, 'Mon - Fri', '09:00 AM - 05:00 PM', 'Gurugram, HR', 'Verified', 1);

-- --------------------------------------------------------
-- Table structure for `medicine_reminders`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medicine_reminders` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `medicine_name` varchar(200) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `instructions` varchar(255) DEFAULT '',
  `frequency` varchar(50) NOT NULL DEFAULT 'once_daily',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `scheduled_times` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_patient_active` (`patient_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `medicine_dose_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medicine_dose_logs` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `reminder_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `status` enum('pending','taken','missed','snoozed') NOT NULL DEFAULT 'pending',
  `taken_at` datetime DEFAULT NULL,
  `snoozed_until` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (`reminder_id`) REFERENCES `medicine_reminders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_reminder_dose` (`reminder_id`, `scheduled_at`),
  INDEX `idx_patient_status_sched` (`patient_id`, `status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `notifications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `notification_type` enum('medicine','appointment','system') NOT NULL,
  `related_record_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `status` enum('pending','sent','read','dismissed') NOT NULL DEFAULT 'pending',
  `channel` varchar(50) NOT NULL DEFAULT 'in_app',
  `dedup_key` varchar(120) UNIQUE DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_patient_notif` (`patient_id`, `status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Sample Medication Schedules for patient (user_id = 1)
INSERT INTO `medicine_reminders` (`id`, `patient_id`, `medicine_name`, `dosage`, `instructions`, `frequency`, `start_date`, `end_date`, `scheduled_times`, `is_active`) VALUES
(1, 1, 'Atorvastatin', '20 mg', 'Take after dinner at bedtime', 'once_daily', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 50 DAY, '["21:00"]', 1),
(2, 1, 'Metformin', '500 mg', 'Take with meals (breakfast & dinner)', 'twice_daily', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 45 DAY, '["08:30", "20:30"]', 1),
(3, 1, 'Vitamin D3', '1000 IU', 'Take after breakfast with water', 'once_daily', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, '["09:00"]', 1)
ON DUPLICATE KEY UPDATE `medicine_name` = VALUES(`medicine_name`);

-- --------------------------------------------------------
-- Table structure for `patient_reminders`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patient_reminders` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `reminder_date` date NOT NULL,
  `reminder_time` time NOT NULL,
  `reminder_type` enum('Medicine','Blood Test','Doctor Appointment','Health Checkup','Other') NOT NULL DEFAULT 'Other',
  `reminder_status` enum('Pending','Completed','Missed') NOT NULL DEFAULT 'Pending',
  `notified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_patient_reminders` (`patient_id`, `reminder_status`, `reminder_date`, `reminder_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- ============================================================
-- AayuVault — Sample Medical & Health Dataset
-- File: database/sample_daa.sql
-- Contains complete schema, sample patient vitals, timeline,
-- and raw clinical lab reports ready for AI analysis.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `clinic_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `clinic_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `report_analyses`;
DROP TABLE IF EXISTS `medical_records`;
DROP TABLE IF EXISTS `login_logs`;
DROP TABLE IF EXISTS `metrics`;
DROP TABLE IF EXISTS `medical_summary`;
DROP TABLE IF EXISTS `health_timeline`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `abha_otp`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Users Table
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `abha_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `is_active`, `abha_id`) VALUES
(1, 'testuser', 'test@example.com', '$2y$12$3AqfbsH1TUBhWmS9wchu4Oh/06v3vFaO.H1WU9PKqb36fSnFbEKJC', 'Test User', 1, '12-3456-7890-1234');

-- --------------------------------------------------------
-- Key Vitals / Metrics
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
(1, '120/80', 72, 98, 'Attention Needed', 4);

-- --------------------------------------------------------
-- Medical Summaries / Diagnoses
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
-- Health Timeline
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
-- Appointments
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
(1, 'Dr. Rajeev Mehta', 'Endocrinology', DATE_ADD(NOW(), INTERVAL 5 DAY));

-- --------------------------------------------------------
-- Medical Records & Raw Clinical Data for AI
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

INSERT INTO `medical_records` (`id`, `user_id`, `date_label`, `title`, `subtitle`, `type`, `facility`, `doctor`, `has_pdf`, `pdf_path`, `raw_text`, `ai_analyzed`) VALUES
(1, 1, 'Apr 02, 2025', 'Comprehensive Lipid Panel', 'Total Cholesterol: 218 mg/dL (High), LDL: 138 mg/dL, HDL: 44 mg/dL, Triglycerides: 178 mg/dL', 'lab', 'Quest Diagnostics', 'Dr. Priya Sharma', 1, 'reports/lipid_panel_apr2025.pdf',
'PATIENT REPORT - QUEST DIAGNOSTICS
Patient Name: Test User | Age: 38 | Gender: Male | Date: 02-Apr-2025
TEST: LIPID PROFILE (SERUM)
Total Cholesterol: 218 mg/dL (Desirable: < 200 mg/dL) [HIGH]
Triglycerides: 178 mg/dL (Normal: < 150 mg/dL) [BORDERLINE HIGH]
HDL Cholesterol (Good): 44 mg/dL (Normal: > 40 mg/dL) [OPTIMAL]
LDL Cholesterol (Bad, Calculated): 138 mg/dL (Optimal: < 100 mg/dL) [HIGH]
VLDL Cholesterol: 35 mg/dL (Normal: 5 - 30 mg/dL) [ELEVATED]
Cholesterol / HDL Ratio: 4.95 (Desirable: < 4.5)', 1),

(2, 1, 'Mar 18, 2025', 'Glycemic & HbA1c Panel', 'HbA1c: 5.9% (Pre-diabetic range), Fasting Glucose: 104 mg/dL', 'lab', 'LabCorp Diagnostics', 'Dr. Rajeev Mehta', 1, 'reports/hba1c_mar2025.pdf',
'LABCORP DIAGNOSTICS - CLINICAL BIOCHEMISTRY
Patient Name: Test User | Date: 18-Mar-2025
GLYCEMIC HEALTH PANEL
Fasting Blood Glucose: 104 mg/dL (Reference Range: 70 - 99 mg/dL) [IMPAIRED FASTING GLUCOSE]
HbA1c (Glycated Hemoglobin): 5.9% (Reference Range: Normal < 5.7%, Pre-diabetes 5.7 - 6.4%, Diabetes >= 6.5%) [PRE-DIABETIC]
Postprandial Blood Glucose (2hr): 134 mg/dL (Normal: < 140 mg/dL) [NORMAL]
Estimated Average Glucose (eAG): 123 mg/dL', 1),

(3, 1, 'Feb 28, 2025', 'Complete Blood Count (CBC)', 'Hemoglobin: 14.2 g/dL (Normal), WBC: 6,800 /µL, Platelets: 245k', 'lab', 'NaviSite Health Labs', 'Dr. Anil Verma', 1, 'reports/cbc_feb2025.pdf',
'NAVISITE HEALTH - HEMATOLOGY DEPARTMENT
Patient: Test User | Date: 28-Feb-2025
COMPLETE BLOOD COUNT (AUTOMATED)
Hemoglobin (Hb): 14.2 g/dL (Ref Range: 13.0 - 17.0 g/dL) [NORMAL]
Total WBC Count: 6800 /µL (Ref Range: 4,000 - 11,000 /µL) [NORMAL]
Platelet Count: 245 K/µL (Ref Range: 150 - 450 K/µL) [NORMAL]
RBC Count: 4.95 M/µL (Ref Range: 4.2 - 5.9 M/µL) [NORMAL]
Hematocrit (PCV): 43.5% (Ref Range: 38.0 - 50.0%) [NORMAL]
Neutrophils: 62% | Lymphocytes: 29% | Monocytes: 6% | Eosinophils: 3%', 1),

(4, 1, 'Jan 15, 2025', 'Comprehensive Metabolic & Liver Panel', 'ALT/SGPT: 38 U/L, Creatinine: 0.95 mg/dL, eGFR: >90, Bilirubin: 0.8 mg/dL', 'lab', 'NovaCare Health', 'Dr. S. Kulkarni', 1, 'reports/cmp_jan2025.pdf',
'NOVACARE HEALTH SERVICES - BIOCHEMISTRY
Patient: Test User | Date: 15-Jan-2025
LIVER & RENAL PROFILE
SGPT / ALT: 38 U/L (Reference: 7 - 45 U/L) [NORMAL]
SGOT / AST: 32 U/L (Reference: 8 - 40 U/L) [NORMAL]
Total Bilirubin: 0.8 mg/dL (Reference: 0.2 - 1.2 mg/dL) [NORMAL]
Serum Albumin: 4.4 g/dL (Reference: 3.5 - 5.2 g/dL) [NORMAL]
Alkaline Phosphatase (ALP): 82 U/L (Reference: 44 - 147 U/L) [NORMAL]
Serum Creatinine: 0.95 mg/dL (Reference: 0.6 - 1.2 mg/dL) [NORMAL]
Blood Urea Nitrogen (BUN): 14 mg/dL (Reference: 7 - 20 mg/dL) [NORMAL]
eGFR: 98 mL/min/1.73m2 (Stage 1 Normal Kidney Function > 90) [OPTIMAL]', 1);

-- --------------------------------------------------------
-- ABHA Verification Mock Table
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

COMMIT;

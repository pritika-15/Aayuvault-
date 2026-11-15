-- ============================================================
-- AayuVault — Medicine Reminder & Appointment Alert Schema
-- File: database/medicine_appointment_alerts_schema.sql
-- ============================================================

USE `clinic_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. Table structure for `medicine_reminders`
-- ------------------------------------------------------------
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
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_patient_active` (`patient_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Table structure for `medicine_dose_logs`
-- ------------------------------------------------------------
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
  FOREIGN KEY (`reminder_id`) REFERENCES `medicine_reminders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_reminder_dose` (`reminder_id`, `scheduled_at`),
  INDEX `idx_patient_status_sched` (`patient_id`, `status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Enhance / Structure table `appointments`
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments_new` (
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
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_patient_appt_date` (`patient_id`, `appointment_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If appointments exists without all columns, migrate or update
-- We add missing columns safely if appointments already exists:
-- We will also migrate existing rows to appointments_new or ensure appointments has all columns.

-- ------------------------------------------------------------
-- 4. Table structure for `notifications`
-- ------------------------------------------------------------
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
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_patient_notif` (`patient_id`, `status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Seed Real Medication Schedules & Appointments for testuser (id=1)
-- ------------------------------------------------------------

-- Medication 1: Atorvastatin (Lipid Control) - Once daily at night
INSERT INTO `medicine_reminders` 
  (`id`, `patient_id`, `medicine_name`, `dosage`, `instructions`, `frequency`, `start_date`, `end_date`, `scheduled_times`, `is_active`) 
VALUES
  (1, 1, 'Atorvastatin', '20 mg', 'Take after dinner at bedtime', 'once_daily', CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 50 DAY, '["21:00"]', 1)
ON DUPLICATE KEY UPDATE `medicine_name` = VALUES(`medicine_name`);

-- Medication 2: Metformin (Glycemic Management) - Twice daily (Morning & Night)
INSERT INTO `medicine_reminders` 
  (`id`, `patient_id`, `medicine_name`, `dosage`, `instructions`, `frequency`, `start_date`, `end_date`, `scheduled_times`, `is_active`) 
VALUES
  (2, 1, 'Metformin', '500 mg', 'Take with meals (breakfast & dinner)', 'twice_daily', CURDATE() - INTERVAL 15 DAY, CURDATE() + INTERVAL 45 DAY, '["08:30", "20:30"]', 1)
ON DUPLICATE KEY UPDATE `medicine_name` = VALUES(`medicine_name`);

-- Medication 3: Vitamin D3 (Cholecalciferol) - Once daily with breakfast
INSERT INTO `medicine_reminders` 
  (`id`, `patient_id`, `medicine_name`, `dosage`, `instructions`, `frequency`, `start_date`, `end_date`, `scheduled_times`, `is_active`) 
VALUES
  (3, 1, 'Vitamin D3', '1000 IU', 'Take after breakfast with water or milk', 'once_daily', CURDATE() - INTERVAL 5 DAY, CURDATE() + INTERVAL 25 DAY, '["09:00"]', 1)
ON DUPLICATE KEY UPDATE `medicine_name` = VALUES(`medicine_name`);

-- Medication 4: Paracetamol (As needed / Daytime)
INSERT INTO `medicine_reminders` 
  (`id`, `patient_id`, `medicine_name`, `dosage`, `instructions`, `frequency`, `start_date`, `end_date`, `scheduled_times`, `is_active`) 
VALUES
  (4, 1, 'Paracetamol', '500 mg', 'Take after lunch as prescribed for headache', 'once_daily', CURDATE() - INTERVAL 2 DAY, CURDATE() + INTERVAL 5 DAY, '["14:00"]', 1)
ON DUPLICATE KEY UPDATE `medicine_name` = VALUES(`medicine_name`);

-- Seed Today's Appointment for testuser
INSERT INTO `appointments_new` 
  (`id`, `patient_id`, `doctor_id`, `doctor_name`, `department`, `specialty`, `hospital_name`, `appointment_date`, `appointment_time`, `appt_datetime`, `appointment_type`, `location`, `consultation_link`, `notes`, `status`) 
VALUES
  (101, 1, 1, 'Dr. Priya Sharma', 'Cardiology', 'Cardiology', 'City Hospital, Building A, Suite 302', CURDATE(), '16:00:00', CONCAT(CURDATE(), ' 16:00:00'), 'in-person', 'City Hospital, OPD Floor 3', NULL, 'Follow-up consultation for lipid panel and blood pressure evaluation.', 'upcoming')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Seed Upcoming Appointment (3 days later)
INSERT INTO `appointments_new` 
  (`id`, `patient_id`, `doctor_id`, `doctor_name`, `department`, `specialty`, `hospital_name`, `appointment_date`, `appointment_time`, `appt_datetime`, `appointment_type`, `location`, `consultation_link`, `notes`, `status`) 
VALUES
  (102, 1, 5, 'Dr. Rajeev Mehta', 'Endocrinology', 'Endocrinology', 'CareWell Polyclinic', CURDATE() + INTERVAL 3 DAY, '11:30:00', CONCAT(CURDATE() + INTERVAL 3 DAY, ' 11:30:00'), 'online', 'Tele-Consultation Portal', 'https://meet.aayuvault.org/room/dr-mehta-102', 'HbA1c quarterly progress assessment & dietary consultation.', 'upcoming')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

SET FOREIGN_KEY_CHECKS = 1;

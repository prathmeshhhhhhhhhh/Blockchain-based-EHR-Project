-- MediHub MVP Database Schema - Fixed Version
-- Run this script to create the database structure

CREATE DATABASE IF NOT EXISTS medihub;
USE medihub;

-- Users table (must be first as it's referenced by others)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('PATIENT','DOCTOR','ADMIN') NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Patients table (references users)
CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  dob DATE NULL,
  gender VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Doctors table (references users)
CREATE TABLE doctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  reg_no VARCHAR(50) NULL,
  organization VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Doctor ↔ Patient links (references patients and doctors)
CREATE TABLE links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  status ENUM('REQUESTED','APPROVED','REVOKED') DEFAULT 'REQUESTED',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  UNIQUE KEY unique_link (patient_id, doctor_id)
);

-- Consent with scopes and limits (references patients and doctors)
CREATE TABLE consents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT NOT NULL,
  purpose ENUM('TREATMENT','RESEARCH','EMERGENCY') NOT NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  max_views INT NULL,
  used_views INT DEFAULT 0,
  status ENUM('ACTIVE','REVOKED','EXPIRED') DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Consent scopes (references consents)
CREATE TABLE consent_scopes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  consent_id INT NOT NULL,
  scope ENUM('DEMOGRAPHICS','ENCOUNTERS','LABS','PRESCRIPTIONS','NOTES','DOCUMENTS') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (consent_id) REFERENCES consents(id) ON DELETE CASCADE,
  UNIQUE KEY unique_scope (consent_id, scope)
);

-- Basic EHR records (references patients and users)
CREATE TABLE ehr_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  type ENUM('ENCOUNTER','LAB','PRESCRIPTION','NOTE','VITAL','ALLERGY','IMAGING') NOT NULL,
  content TEXT NOT NULL,
  content_hash CHAR(64) NOT NULL,
  recorded_at DATETIME NOT NULL,
  created_by_user INT NOT NULL,
  deleted TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_patient_type (patient_id, type),
  INDEX idx_recorded_at (recorded_at)
);

-- Uploaded binary documents tied to a record (references ehr_records)
CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ehr_record_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  sha256 CHAR(64) NOT NULL,
  file_size INT NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ehr_record_id) REFERENCES ehr_records(id) ON DELETE CASCADE
);

-- Simple audit log (references users and patients)
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actor_user_id INT,
  subject_patient_id INT,
  action VARCHAR(60) NOT NULL,
  details TEXT,
  prev_hash CHAR(64),
  curr_hash CHAR(64),
  INDEX idx_actor (actor_user_id),
  INDEX idx_subject (subject_patient_id),
  INDEX idx_action (action),
  INDEX idx_ts (ts),
  FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (subject_patient_id) REFERENCES patients(id) ON DELETE SET NULL
);

-- Deregistration jobs (references patients)
CREATE TABLE deletion_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  status ENUM('PENDING','IN_PROGRESS','COMPLETE','FAILED') DEFAULT 'PENDING',
  receipt_hash CHAR(64) NULL,
  completed_at DATETIME NULL,
  steps TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Notifications table for in-app notifications (references users)
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  data JSON NULL,
  read_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_unread (user_id, read_at)
);

-- System settings
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('app_name', 'MediHub'),
('app_version', '1.0.0'),
('k_anonymity_threshold', '10'),
('max_file_size', '5242880'),
('allowed_file_types', 'pdf,jpg,jpeg,png,gif');

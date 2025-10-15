-- MediHub MVP Seed Data
-- Run this script after migrate.sql to populate the database with demo data

USE medihub;

-- Insert admin user
INSERT INTO users (email, password_hash, role, full_name) VALUES
('admin@medihub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN', 'System Administrator');

-- Insert doctor users
INSERT INTO users (email, password_hash, role, full_name) VALUES
('dr.smith@medihub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'Dr. John Smith'),
('dr.jones@medihub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'DOCTOR', 'Dr. Sarah Jones');

-- Insert patient users
INSERT INTO users (email, password_hash, role, full_name) VALUES
('patient1@medihub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PATIENT', 'Alice Johnson'),
('patient2@medihub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'PATIENT', 'Bob Wilson');

-- Insert patient records
INSERT INTO patients (user_id, dob, gender) VALUES
(4, '1985-03-15', 'Female'),
(5, '1978-11-22', 'Male');

-- Insert doctor records
INSERT INTO doctors (user_id, reg_no, organization) VALUES
(2, 'MD12345', 'City General Hospital'),
(3, 'MD67890', 'Metro Medical Center');

-- Create approved link between Dr. Smith and Alice Johnson
INSERT INTO links (patient_id, doctor_id, status) VALUES
(1, 1, 'APPROVED');

-- Create consent for Dr. Smith to access Alice's LABS and NOTES
INSERT INTO consents (patient_id, doctor_id, purpose, start_at, end_at, max_views, status) VALUES
(1, 1, 'TREATMENT', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 100, 'ACTIVE');

-- Add consent scopes
INSERT INTO consent_scopes (consent_id, scope) VALUES
(1, 'LABS'),
(1, 'NOTES');

-- Insert sample EHR records for Alice Johnson
INSERT INTO ehr_records (patient_id, type, content, content_hash, recorded_at, created_by_user) VALUES
(1, 'ENCOUNTER', '{"chief_complaint": "Chest pain and shortness of breath", "diagnosis": "Possible angina", "treatment_plan": "ECG, blood tests, follow-up in 1 week", "vital_signs": {"blood_pressure": "140/90", "heart_rate": 95, "temperature": "98.6F"}}', SHA2('{"chief_complaint": "Chest pain and shortness of breath", "diagnosis": "Possible angina", "treatment_plan": "ECG, blood tests, follow-up in 1 week", "vital_signs": {"blood_pressure": "140/90", "heart_rate": 95, "temperature": "98.6F"}}', 256), NOW(), 2),

(1, 'LAB', '{"test_name": "Complete Blood Count", "result": "Normal", "reference_range": "Within normal limits", "lab_date": "2024-01-15", "lab_facility": "City General Hospital Lab", "ordered_by": "Dr. John Smith"}', SHA2('{"test_name": "Complete Blood Count", "result": "Normal", "reference_range": "Within normal limits", "lab_date": "2024-01-15", "lab_facility": "City General Hospital Lab", "ordered_by": "Dr. John Smith"}', 256), NOW(), 2),

(1, 'PRESCRIPTION', '{"medication": "Aspirin", "dosage": "81mg", "frequency": "Once daily", "duration": "30 days", "instructions": "Take with food to reduce stomach irritation", "prescribed_by": "Dr. John Smith", "pharmacy": "City Pharmacy"}', SHA2('{"medication": "Aspirin", "dosage": "81mg", "frequency": "Once daily", "duration": "30 days", "instructions": "Take with food to reduce stomach irritation", "prescribed_by": "Dr. John Smith", "pharmacy": "City Pharmacy"}', 256), NOW(), 2),

(1, 'NOTE', '{"note": "Patient reports improvement in chest pain after taking prescribed medication. Blood pressure slightly elevated but within acceptable range. Advised to continue current treatment and return if symptoms worsen.", "note_type": "Follow-up", "created_by": "Dr. John Smith"}', SHA2('{"note": "Patient reports improvement in chest pain after taking prescribed medication. Blood pressure slightly elevated but within acceptable range. Advised to continue current treatment and return if symptoms worsen.", "note_type": "Follow-up", "created_by": "Dr. John Smith"}', 256), NOW(), 2);

-- Insert sample EHR records for Bob Wilson
INSERT INTO ehr_records (patient_id, type, content, content_hash, recorded_at, created_by_user) VALUES
(2, 'ENCOUNTER', '{"chief_complaint": "Annual physical examination", "diagnosis": "Healthy adult", "treatment_plan": "Continue current lifestyle, annual checkup", "vital_signs": {"blood_pressure": "120/80", "heart_rate": 72, "temperature": "98.4F", "weight": "180 lbs", "height": "6 feet"}}', SHA2('{"chief_complaint": "Annual physical examination", "diagnosis": "Healthy adult", "treatment_plan": "Continue current lifestyle, annual checkup", "vital_signs": {"blood_pressure": "120/80", "heart_rate": 72, "temperature": "98.4F", "weight": "180 lbs", "height": "6 feet"}}', 256), NOW(), 1),

(2, 'VITAL', '{"vital_type": "Blood Pressure", "value": "120/80", "unit": "mmHg", "measured_at": "2024-01-15 10:30:00", "measured_by": "Nurse Johnson"}', SHA2('{"vital_type": "Blood Pressure", "value": "120/80", "unit": "mmHg", "measured_at": "2024-01-15 10:30:00", "measured_by": "Nurse Johnson"}', 256), NOW(), 1),

(2, 'ALLERGY', '{"allergen": "Penicillin", "severity": "Moderate", "reaction": "Skin rash", "first_occurrence": "2019-05-10", "last_occurrence": "2019-05-10", "notes": "Patient should avoid all penicillin-based antibiotics"}', SHA2('{"allergen": "Penicillin", "severity": "Moderate", "reaction": "Skin rash", "first_occurrence": "2019-05-10", "last_occurrence": "2019-05-10", "notes": "Patient should avoid all penicillin-based antibiotics"}', 256), NOW(), 1);

-- Insert some notifications
INSERT INTO notifications (user_id, type, title, message, data) VALUES
(4, 'ACCESS_REQUEST', 'New Access Request', 'Dr. Sarah Jones has requested access to your medical records', '{"link_id": 1, "doctor_name": "Dr. Sarah Jones"}'),
(2, 'CONSENT_CREATED', 'New Consent Granted', 'Patient Alice Johnson has granted you consent for TREATMENT with scopes: LABS, NOTES', '{"consent_id": 1, "patient_name": "Alice Johnson"}');

-- Insert some audit log entries
INSERT INTO audit_log (ts, actor_user_id, subject_patient_id, action, details, prev_hash, curr_hash) VALUES
(NOW(), 2, 1, 'EHR_CREATE', 'Type: ENCOUNTER, Record ID: 1', '', SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: ENCOUNTER, Record ID: 1","prev":""}'), 256)),
(NOW(), 2, 1, 'EHR_CREATE', 'Type: LAB, Record ID: 2', SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: ENCOUNTER, Record ID: 1","prev":""}'), 256), SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: LAB, Record ID: 2","prev":"', SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: ENCOUNTER, Record ID: 1","prev":""}'), 256), '"}'), 256)),
(NOW(), 1, 2, 'EHR_CREATE', 'Type: ENCOUNTER, Record ID: 4', SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: LAB, Record ID: 2","prev":"', SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: ENCOUNTER, Record ID: 1","prev":""}'), 256), '"}'), 256), SHA2(CONCAT('{"actor":1,"patient":2,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: ENCOUNTER, Record ID: 4","prev":"', SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: LAB, Record ID: 2","prev":"', SHA2(CONCAT('{"actor":2,"patient":1,"action":"EHR_CREATE","ts":', UNIX_TIMESTAMP(), ',"details":"Type: ENCOUNTER, Record ID: 1","prev":""}'), 256), '"}'), 256), '"}'), 256));

-- Insert some sample documents (these would be actual files in production)
-- For demo purposes, we'll just create the database records
INSERT INTO documents (ehr_record_id, file_name, file_path, sha256, file_size, mime_type) VALUES
(1, 'chest_xray_2024_01_15.pdf', 'uploads/1/chest_xray_2024_01_15.pdf', 'a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456', 245760, 'application/pdf'),
(2, 'lab_results_cbc.pdf', 'uploads/2/lab_results_cbc.pdf', 'b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef1234567890', 128000, 'application/pdf');

-- Create a sample deletion job (completed)
INSERT INTO deletion_jobs (patient_id, status, receipt_hash, completed_at, steps) VALUES
(2, 'COMPLETE', 'sample_receipt_hash_1234567890abcdef', DATE_SUB(NOW(), INTERVAL 1 DAY), '{"patientId":2,"deletedAt":"2024-01-14T10:30:00Z","recordsPurged":3,"docsPurged":1,"auditLastHash":"sample_audit_hash","steps":["Found 3 EHR records to delete","Found 1 documents to delete","Deleted 1 document records","Deleted 3 EHR records","Deleted consent records","Deleted link records","Anonymized patient demographics","Deletion completed successfully"]}');

-- Update settings with demo values
UPDATE settings SET setting_value = 'MediHub Demo' WHERE setting_key = 'app_name';
UPDATE settings SET setting_value = '1.0.0-demo' WHERE setting_key = 'app_version';

-- Display summary
SELECT 'Seed data inserted successfully!' as message;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_patients FROM patients;
SELECT COUNT(*) as total_doctors FROM doctors;
SELECT COUNT(*) as total_links FROM links;
SELECT COUNT(*) as total_consents FROM consents;
SELECT COUNT(*) as total_ehr_records FROM ehr_records;
SELECT COUNT(*) as total_documents FROM documents;
SELECT COUNT(*) as total_notifications FROM notifications;
SELECT COUNT(*) as total_audit_entries FROM audit_log;

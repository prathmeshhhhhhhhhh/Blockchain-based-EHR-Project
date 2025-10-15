-- Add foreign key constraints - Fixed Version
-- Run this after migrate_no_fk.sql

USE medihub;

-- First, let's check and fix any data type mismatches
-- Make sure all ID columns are the same type

-- Check if we need to modify column types first
-- (This is usually not needed, but just in case)

-- Add foreign keys to patients table
ALTER TABLE patients ADD CONSTRAINT fk_patients_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Add foreign keys to doctors table  
ALTER TABLE doctors ADD CONSTRAINT fk_doctors_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Add foreign keys to links table
ALTER TABLE links ADD CONSTRAINT fk_links_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;
ALTER TABLE links ADD CONSTRAINT fk_links_doctor_id FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE;

-- Add foreign keys to consents table
ALTER TABLE consents ADD CONSTRAINT fk_consents_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;
ALTER TABLE consents ADD CONSTRAINT fk_consents_doctor_id FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE;

-- Add foreign keys to consent_scopes table
ALTER TABLE consent_scopes ADD CONSTRAINT fk_consent_scopes_consent_id FOREIGN KEY (consent_id) REFERENCES consents(id) ON DELETE CASCADE;

-- Add foreign keys to ehr_records table
-- Note: created_by_user can be NULL, so we use SET NULL
ALTER TABLE ehr_records ADD CONSTRAINT fk_ehr_records_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;
ALTER TABLE ehr_records ADD CONSTRAINT fk_ehr_records_created_by_user FOREIGN KEY (created_by_user) REFERENCES users(id) ON DELETE SET NULL;

-- Add foreign keys to documents table
ALTER TABLE documents ADD CONSTRAINT fk_documents_ehr_record_id FOREIGN KEY (ehr_record_id) REFERENCES ehr_records(id) ON DELETE CASCADE;

-- Add foreign keys to audit_log table
-- Note: Both can be NULL, so we use SET NULL
ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_actor_user_id FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_subject_patient_id FOREIGN KEY (subject_patient_id) REFERENCES patients(id) ON DELETE SET NULL;

-- Add foreign keys to deletion_jobs table
ALTER TABLE deletion_jobs ADD CONSTRAINT fk_deletion_jobs_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;

-- Add foreign keys to notifications table
ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

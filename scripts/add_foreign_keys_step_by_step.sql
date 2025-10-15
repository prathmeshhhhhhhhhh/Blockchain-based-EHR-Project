-- Add foreign keys step by step to identify issues
-- Run this after migrate_no_fk.sql

USE medihub;

-- Step 1: Add foreign keys to patients table
ALTER TABLE patients ADD CONSTRAINT fk_patients_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
SELECT 'Step 1: patients table foreign key added' as status;

-- Step 2: Add foreign keys to doctors table
ALTER TABLE doctors ADD CONSTRAINT fk_doctors_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
SELECT 'Step 2: doctors table foreign key added' as status;

-- Step 3: Add foreign keys to links table
ALTER TABLE links ADD CONSTRAINT fk_links_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;
SELECT 'Step 3a: links patient_id foreign key added' as status;

ALTER TABLE links ADD CONSTRAINT fk_links_doctor_id FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE;
SELECT 'Step 3b: links doctor_id foreign key added' as status;

-- Step 4: Add foreign keys to consents table
ALTER TABLE consents ADD CONSTRAINT fk_consents_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;
SELECT 'Step 4a: consents patient_id foreign key added' as status;

ALTER TABLE consents ADD CONSTRAINT fk_consents_doctor_id FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE;
SELECT 'Step 4b: consents doctor_id foreign key added' as status;

-- Step 5: Add foreign keys to consent_scopes table
ALTER TABLE consent_scopes ADD CONSTRAINT fk_consent_scopes_consent_id FOREIGN KEY (consent_id) REFERENCES consents(id) ON DELETE CASCADE;
SELECT 'Step 5: consent_scopes foreign key added' as status;

-- Step 6: Add foreign keys to ehr_records table
ALTER TABLE ehr_records ADD CONSTRAINT fk_ehr_records_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;
SELECT 'Step 6a: ehr_records patient_id foreign key added' as status;

-- This is the problematic one - let's try it separately
-- ALTER TABLE ehr_records ADD CONSTRAINT fk_ehr_records_created_by_user FOREIGN KEY (created_by_user) REFERENCES users(id) ON DELETE SET NULL;
-- SELECT 'Step 6b: ehr_records created_by_user foreign key added' as status;

-- Step 7: Add foreign keys to documents table
ALTER TABLE documents ADD CONSTRAINT fk_documents_ehr_record_id FOREIGN KEY (ehr_record_id) REFERENCES ehr_records(id) ON DELETE CASCADE;
SELECT 'Step 7: documents foreign key added' as status;

-- Step 8: Add foreign keys to audit_log table
ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_actor_user_id FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL;
SELECT 'Step 8a: audit_log actor_user_id foreign key added' as status;

ALTER TABLE audit_log ADD CONSTRAINT fk_audit_log_subject_patient_id FOREIGN KEY (subject_patient_id) REFERENCES patients(id) ON DELETE SET NULL;
SELECT 'Step 8b: audit_log subject_patient_id foreign key added' as status;

-- Step 9: Add foreign keys to deletion_jobs table
ALTER TABLE deletion_jobs ADD CONSTRAINT fk_deletion_jobs_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;
SELECT 'Step 9: deletion_jobs foreign key added' as status;

-- Step 10: Add foreign keys to notifications table
ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
SELECT 'Step 10: notifications foreign key added' as status;

SELECT 'All foreign keys added successfully (except ehr_records.created_by_user)' as final_status;

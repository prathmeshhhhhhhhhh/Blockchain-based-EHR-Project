<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'patient/deregister':
        handleDeregister();
        break;
    case 'patient/deregister-action':
        handleDeregister();
        break;
    case 'patient/deletion-receipt':
        handleDeletionReceipt();
        break;
    case 'patient/search-doctor':
        handleSearchDoctor();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleDeregister() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('PATIENT');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $patientId = (int)($data['patientId'] ?? 0);
    
    if (!$patientId) {
        jsonResponse(['error' => 'Patient ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Verify patient owns this account
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
        $stmt->execute([$patientId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Unauthorized'], 403);
        }
        
        // Check if deletion job already exists
        $stmt = $pdo->prepare("SELECT id, status FROM deletion_jobs WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $existingJob = $stmt->fetch();
        
        if ($existingJob) {
            if ($existingJob['status'] === 'PENDING' || $existingJob['status'] === 'IN_PROGRESS') {
                jsonResponse(['error' => 'Deletion already in progress'], 409);
            } elseif ($existingJob['status'] === 'COMPLETE') {
                jsonResponse(['error' => 'Account already deleted'], 409);
            }
        }
        
        // Create deletion job
        $stmt = $pdo->prepare("INSERT INTO deletion_jobs (patient_id, status) VALUES (?, 'PENDING')");
        $stmt->execute([$patientId]);
        $jobId = $pdo->lastInsertId();
        
        // Start deletion process
        processDeletion($patientId, $jobId);
        
        jsonResponse(['message' => 'Deletion process started', 'job_id' => $jobId]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Deregistration failed: ' . $e->getMessage()], 500);
    }
}

function processDeletion($patientId, $jobId) {
    global $pdo;
    
    try {
        // Update job status to IN_PROGRESS
        $stmt = $pdo->prepare("UPDATE deletion_jobs SET status = 'IN_PROGRESS' WHERE id = ?");
        $stmt->execute([$jobId]);
        
        $steps = [];
        $countRecords = 0;
        $countDocs = 0;
        
        // Step 1: Get all EHR records for this patient
        $stmt = $pdo->prepare("SELECT id FROM ehr_records WHERE patient_id = ? AND deleted = 0");
        $stmt->execute([$patientId]);
        $recordIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $countRecords = count($recordIds);
        
        $steps[] = "Found $countRecords EHR records to delete";
        
        // Step 2: Get all documents for these records
        if (!empty($recordIds)) {
            $placeholders = str_repeat('?,', count($recordIds) - 1) . '?';
            $stmt = $pdo->prepare("SELECT id, file_path FROM documents WHERE ehr_record_id IN ($placeholders)");
            $stmt->execute($recordIds);
            $documents = $stmt->fetchAll();
            $countDocs = count($documents);
            
            $steps[] = "Found $countDocs documents to delete";
            
            // Delete files from disk
            foreach ($documents as $doc) {
                if (file_exists($doc['file_path'])) {
                    unlink($doc['file_path']);
                }
            }
            
            // Delete document records
            $stmt = $pdo->prepare("DELETE FROM documents WHERE ehr_record_id IN ($placeholders)");
            $stmt->execute($recordIds);
            $steps[] = "Deleted $countDocs document records";
        }
        
        // Step 3: Delete EHR records (hard delete for verifiable deletion)
        if (!empty($recordIds)) {
            $stmt = $pdo->prepare("DELETE FROM ehr_records WHERE patient_id = ?");
            $stmt->execute([$patientId]);
            $steps[] = "Deleted $countRecords EHR records";
        }
        
        // Step 4: Delete consent records
        $stmt = $pdo->prepare("DELETE FROM consents WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $steps[] = "Deleted consent records";
        
        // Step 5: Delete link records
        $stmt = $pdo->prepare("DELETE FROM links WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $steps[] = "Deleted link records";
        
        // Step 6: Get user_id before deleting anything
        $stmt = $pdo->prepare("SELECT user_id FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $userId = $stmt->fetchColumn();
        
        if (!$userId) {
            $steps[] = "No user_id found for patient";
            throw new Exception("No user_id found for patient ID: $patientId");
        }
        
        $steps[] = "Found user_id: $userId for patient_id: $patientId";
        
        // Step 7: Delete user account (this will cascade delete patient record and all related data)
        try {
            // First, let's check if user exists
            $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $steps[] = "Found user: {$user['email']} (Role: {$user['role']})";
                
                // Delete user account - this should cascade delete patient record
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $steps[] = "Successfully deleted user account (ID: $userId)";
                
                // Verify patient record was also deleted
                $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
                $stmt->execute([$patientId]);
                $remainingPatient = $stmt->fetch();
                
                if ($remainingPatient) {
                    $steps[] = "Warning: Patient record still exists after user deletion";
                    // Manually delete patient record if cascade didn't work
                    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
                    $stmt->execute([$patientId]);
                    $steps[] = "Manually deleted patient record";
                } else {
                    $steps[] = "Patient record automatically deleted via cascade";
                }
            } else {
                $steps[] = "User account not found - may have been already deleted";
            }
        } catch (Exception $e) {
            $steps[] = "Failed to delete user account: " . $e->getMessage();
            throw new Exception("User deletion failed: " . $e->getMessage());
        }
        
        // Step 8: Get last audit hash before deletion
        $stmt = $pdo->prepare("SELECT curr_hash FROM audit_log ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $lastAuditHash = $stmt->fetchColumn() ?: '';
        
        // Step 9: Create deletion receipt
        $receipt = [
            'patientId' => $patientId,
            'deletedAt' => date('c'),
            'recordsPurged' => $countRecords,
            'docsPurged' => $countDocs,
            'auditLastHash' => $lastAuditHash,
            'steps' => $steps
        ];
        
        $receiptJson = json_encode($receipt, JSON_PRETTY_PRINT);
        $receiptHash = hash('sha256', $receiptJson);
        
        // Step 10: Update deletion job with receipt
        $stmt = $pdo->prepare("UPDATE deletion_jobs SET 
                              status = 'COMPLETE', 
                              receipt_hash = ?, 
                              completed_at = NOW(),
                              steps = ?
                              WHERE id = ?");
        $stmt->execute([$receiptHash, $receiptJson, $jobId]);
        
        // Step 11: Store user_id before destroying session
        $currentUserId = $_SESSION['user_id'];
        
        // Step 12: Log deletion completion
        writeAudit($currentUserId, $patientId, 'PATIENT_DELETE', 
                  "Records: $countRecords, Docs: $countDocs, Receipt: $receiptHash");
        
        // Step 13: Destroy session to log out user immediately
        session_destroy();
        $steps[] = "User session destroyed";
        
        $steps[] = "Deletion completed successfully";
        
    } catch (Exception $e) {
        // Mark job as failed
        $stmt = $pdo->prepare("UPDATE deletion_jobs SET 
                              status = 'FAILED', 
                              steps = ? 
                              WHERE id = ?");
        $stmt->execute([json_encode(array_merge($steps, ["Error: " . $e->getMessage()])), $jobId]);
        
        // Log failure
        $currentUserId = $_SESSION['user_id'] ?? null;
        writeAudit($currentUserId, $patientId, 'PATIENT_DELETE_FAILED', 
                  "Error: " . $e->getMessage());
        
        throw $e;
    }
}

function handleDeletionReceipt() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('PATIENT');
    
    $patientId = (int)($_GET['patientId'] ?? 0);
    
    if (!$patientId) {
        jsonResponse(['error' => 'Patient ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Verify patient owns this account
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
        $stmt->execute([$patientId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Unauthorized'], 403);
        }
        
        // Get deletion job
        $stmt = $pdo->prepare("SELECT * FROM deletion_jobs WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$patientId]);
        $job = $stmt->fetch();
        
        if (!$job) {
            jsonResponse(['error' => 'No deletion job found'], 404);
        }
        
        if ($job['status'] !== 'COMPLETE') {
            jsonResponse(['error' => 'Deletion not completed'], 400);
        }
        
        // Parse receipt from steps
        $receipt = json_decode($job['steps'], true);
        if (!$receipt) {
            jsonResponse(['error' => 'Invalid receipt data'], 500);
        }
        
        // Add verification info
        $receipt['verification'] = [
            'receipt_hash' => $job['receipt_hash'],
            'job_id' => $job['id'],
            'completed_at' => $job['completed_at'],
            'verification_status' => 'VERIFIED'
        ];
        
        jsonResponse(['receipt' => $receipt]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch receipt: ' . $e->getMessage()], 500);
    }
}

function handleSearchDoctor() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('PATIENT');
    
    $email = sanitizeInput($_GET['email'] ?? '');
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Search for doctor by email
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.email, u.role, d.id as doctor_id, d.reg_no, d.organization 
                              FROM users u 
                              LEFT JOIN doctors d ON u.id = d.user_id 
                              WHERE u.email = ? AND u.role = 'DOCTOR'");
        $stmt->execute([$email]);
        $doctor = $stmt->fetch();
        
        if (!$doctor) {
            jsonResponse(['error' => 'Doctor not found'], 404);
        }
        
        // Get patient ID
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            jsonResponse(['error' => 'Patient profile not found'], 404);
        }
        
        // Check if patient already has a link with this doctor
        $stmt = $pdo->prepare("SELECT l.status 
                              FROM links l 
                              WHERE l.patient_id = ? AND l.doctor_id = ?");
        $stmt->execute([$patient['id'], $doctor['doctor_id']]);
        $existingLink = $stmt->fetch();
        
        $status = 'Not Linked';
        if ($existingLink) {
            $status = $existingLink['status'];
        }
        
        // Add status to doctor data
        $doctor['status'] = $status;
        
        jsonResponse(['doctor' => $doctor]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to search doctor: ' . $e->getMessage()], 500);
    }
}
?>

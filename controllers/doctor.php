<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'doctor/search-patient':
        handleSearchPatient();
        break;
    case 'doctor/patient-records-api':
        handlePatientRecords();
        break;
    case 'doctor/assigned-records-api':
        handleAssignedRecords();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleSearchPatient() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('DOCTOR');
    
    $email = trim(sanitizeInput($_GET['email'] ?? ''));
    
    if (empty($email)) {
        jsonResponse(['error' => 'Email is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Search for patient by email, ensure an actual patient profile exists
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.email, p.id as patient_id 
                              FROM users u 
                              LEFT JOIN patients p ON p.user_id = u.id 
                              WHERE TRIM(LOWER(u.email)) = TRIM(LOWER(?)) 
                              LIMIT 1");
        $stmt->execute([$email]);
        $patient = $stmt->fetch();
        
        if (!$patient || empty($patient['patient_id'])) {
            jsonResponse(['error' => 'Patient not found'], 404);
        }
        
        // Check if doctor already has a link with this patient
        $stmt = $pdo->prepare("SELECT l.status 
                              FROM links l 
                              JOIN doctors d ON l.doctor_id = d.id 
                              WHERE l.patient_id = ? AND d.user_id = ?");
        $stmt->execute([$patient['patient_id'], $_SESSION['user_id']]);
        $link = $stmt->fetch();
        
        if ($link) {
            $patient['status'] = $link['status'];
        }
        
        jsonResponse(['patient' => $patient]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Search failed: ' . $e->getMessage()], 500);
    }
}

function handlePatientRecords() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('DOCTOR');
    
    $patientId = (int)($_GET['patientId'] ?? 0);
    
    if (!$patientId) {
        jsonResponse(['error' => 'Patient ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Check if doctor has access to this patient
        $stmt = $pdo->prepare("SELECT l.id FROM links l 
                              JOIN doctors d ON l.doctor_id = d.id 
                              WHERE l.patient_id = ? AND d.user_id = ? AND l.status = 'APPROVED'");
        $stmt->execute([$patientId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Access denied'], 403);
        }
        
        // Get patient records
        $stmt = $pdo->prepare("SELECT r.*, u.full_name as created_by_name 
                              FROM ehr_records r 
                              LEFT JOIN users u ON r.created_by_user = u.id 
                              WHERE r.patient_id = ? AND r.deleted = 0 
                              ORDER BY r.recorded_at DESC");
        $stmt->execute([$patientId]);
        $records = $stmt->fetchAll();
        
        // Decode JSON content
        foreach ($records as &$record) {
            $record['content'] = json_decode($record['content'], true);
        }
        
        jsonResponse(['records' => $records]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch records: ' . $e->getMessage()], 500);
    }
}

function handleAssignedRecords() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('DOCTOR');
    
    global $pdo;
    
    try {
        // Get doctor ID
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $doctor = $stmt->fetch();
        
        if (!$doctor) {
            jsonResponse(['error' => 'Doctor profile not found'], 404);
        }
        
        // Get assigned records with patient and assignment details
        $stmt = $pdo->prepare("SELECT 
                                ra.id as assignment_id,
                                ra.assignment_note,
                                ra.assigned_at,
                                ra.status as assignment_status,
                                r.id as record_id,
                                r.type,
                                r.content,
                                r.recorded_at,
                                r.created_at,
                                u.full_name as created_by_name,
                                p.id as patient_id,
                                pu.full_name as patient_name,
                                pu.email as patient_email
                              FROM record_assignments ra
                              JOIN ehr_records r ON ra.record_id = r.id
                              JOIN patients p ON r.patient_id = p.id
                              JOIN users pu ON p.user_id = pu.id
                              LEFT JOIN users u ON r.created_by_user = u.id
                              WHERE ra.doctor_id = ? AND ra.status = 'ACTIVE' AND r.deleted = 0
                              ORDER BY ra.assigned_at DESC");
        $stmt->execute([$doctor['id']]);
        $assignedRecords = $stmt->fetchAll();
        
        // Decode JSON content
        foreach ($assignedRecords as &$record) {
            $record['content'] = json_decode($record['content'], true);
        }
        
        jsonResponse(['assigned_records' => $assignedRecords]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch assigned records: ' . $e->getMessage()], 500);
    }
}
?>

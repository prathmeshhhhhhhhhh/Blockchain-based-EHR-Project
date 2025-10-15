<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'ehr/create':
        handleCreateRecord();
        break;
    case 'ehr/list':
        handleListRecords();
        break;
    case 'ehr/get':
        handleGetRecord();
        break;
    case 'ehr/update':
        handleUpdateRecord();
        break;
    case 'ehr/delete':
        handleDeleteRecord();
        break;
    case 'ehr/assign':
        handleAssignRecord();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleCreateRecord() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $patientId = (int)($data['patientId'] ?? 0);
    $type = sanitizeInput($data['type'] ?? '');
    $content = $data['content'] ?? [];
    $recordedAt = $data['recordedAt'] ?? date('Y-m-d H:i:s');
    
    // Validation
    if (!$patientId || empty($type) || empty($content)) {
        jsonResponse(['error' => 'Patient ID, type, and content are required'], 400);
    }
    
    $validTypes = ['ENCOUNTER', 'LAB', 'PRESCRIPTION', 'NOTE', 'VITAL', 'ALLERGY', 'IMAGING'];
    if (!in_array($type, $validTypes)) {
        jsonResponse(['error' => 'Invalid record type'], 400);
    }
    
    global $pdo;
    
    try {
        // Check if user can create records for this patient
        $canCreate = false;
        
        if (hasRole('PATIENT')) {
            // Patient can create their own records
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$patientId, $_SESSION['user_id']]);
            $canCreate = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            // Doctor needs consent
            $canCreate = ensureConsent($patientId, $_SESSION['user_id'], [$type]);
        } elseif (hasRole('ADMIN')) {
            // Admin can create records
            $canCreate = true;
        }
        
        if (!$canCreate) {
            jsonResponse(['error' => 'Insufficient permissions to create record'], 403);
        }
        
        // Validate content structure based on type
        $validatedContent = validateRecordContent($type, $content);
        if (!$validatedContent) {
            jsonResponse(['error' => 'Invalid content structure for record type'], 400);
        }
        
        // Generate content hash
        $contentJson = json_encode($validatedContent);
        $contentHash = hash('sha256', $contentJson);
        
        // Create record
        $stmt = $pdo->prepare("INSERT INTO ehr_records (patient_id, type, content, content_hash, recorded_at, created_by_user) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patientId, $type, $contentJson, $contentHash, $recordedAt, $_SESSION['user_id']]);
        $recordId = $pdo->lastInsertId();
        
        // Log creation
        writeAudit($_SESSION['user_id'], $patientId, 'EHR_CREATE', 
                  "Type: $type, Record ID: $recordId");
        
        jsonResponse(['message' => 'Record created successfully', 'record_id' => $recordId]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Record creation failed: ' . $e->getMessage()], 500);
    }
}

function handleListRecords() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $patientId = (int)($_GET['patientId'] ?? 0);
    $type = sanitizeInput($_GET['type'] ?? '');
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    
    if (!$patientId) {
        jsonResponse(['error' => 'Patient ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Check if user can view records for this patient
        $canView = false;
        
        if (hasRole('PATIENT')) {
            // Patient can view their own records
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$patientId, $_SESSION['user_id']]);
            $canView = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            // Doctor needs consent
            $scopes = $type ? [$type] : ['DEMOGRAPHICS', 'ENCOUNTERS', 'LABS', 'PRESCRIPTIONS', 'NOTES', 'DOCUMENTS'];
            $canView = ensureConsent($patientId, $_SESSION['user_id'], $scopes);
        } elseif (hasRole('ADMIN')) {
            // Admin can view all records
            $canView = true;
        }
        
        if (!$canView) {
            jsonResponse(['error' => 'Insufficient permissions to view records'], 403);
        }
        
        // Build query
        $whereClause = "WHERE patient_id = ? AND deleted = 0";
        $params = [$patientId];
        
        if ($type) {
            $whereClause .= " AND type = ?";
            $params[] = $type;
        }
        
        if ($from) {
            $whereClause .= " AND recorded_at >= ?";
            $params[] = $from;
        }
        
        if ($to) {
            $whereClause .= " AND recorded_at <= ?";
            $params[] = $to;
        }
        
        $sql = "SELECT r.*, u.full_name as created_by_name 
                FROM ehr_records r 
                LEFT JOIN users u ON r.created_by_user = u.id 
                $whereClause 
                ORDER BY r.recorded_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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

function handleGetRecord() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $recordId = (int)($_GET['id'] ?? 0);
    
    if (!$recordId) {
        jsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Get record
        $stmt = $pdo->prepare("SELECT r.*, u.full_name as created_by_name 
                              FROM ehr_records r 
                              LEFT JOIN users u ON r.created_by_user = u.id 
                              WHERE r.id = ? AND r.deleted = 0");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            jsonResponse(['error' => 'Record not found'], 404);
        }
        
        // Check permissions
        $canView = false;
        
        if (hasRole('PATIENT')) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$record['patient_id'], $_SESSION['user_id']]);
            $canView = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            // Check if doctor has general consent OR if record is specifically assigned to them
            $canView = ensureConsent($record['patient_id'], $_SESSION['user_id'], [$record['type']]);
            
            // If no general consent, check for specific record assignment
            if (!$canView) {
                $stmt = $pdo->prepare("SELECT ra.id FROM record_assignments ra
                                      JOIN doctors d ON ra.doctor_id = d.id
                                      WHERE ra.record_id = ? AND d.user_id = ? AND ra.status = 'ACTIVE'");
                $stmt->execute([$recordId, $_SESSION['user_id']]);
                $canView = (bool)$stmt->fetch();
            }
        } elseif (hasRole('ADMIN')) {
            $canView = true;
        }
        
        if (!$canView) {
            jsonResponse(['error' => 'Insufficient permissions to view record'], 403);
        }
        
        // Decode JSON content
        $record['content'] = json_decode($record['content'], true);
        
        // Get associated documents
        $stmt = $pdo->prepare("SELECT id, file_name, file_size, mime_type, uploaded_at 
                              FROM documents WHERE ehr_record_id = ?");
        $stmt->execute([$recordId]);
        $record['documents'] = $stmt->fetchAll();
        
        // Log view
        writeAudit($_SESSION['user_id'], $record['patient_id'], 'EHR_VIEW', 
                  "Record ID: $recordId, Type: {$record['type']}");
        
        jsonResponse(['record' => $record]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch record: ' . $e->getMessage()], 500);
    }
}

function handleUpdateRecord() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $recordId = (int)($data['recordId'] ?? $data['id'] ?? 0);
    $content = $data['content'] ?? [];
    $type = $data['type'] ?? null;
    $recordedAt = $data['recordedAt'] ?? null;
    
    if (!$recordId || empty($content)) {
        jsonResponse(['error' => 'Record ID and content are required'], 400);
    }
    
    global $pdo;
    
    try {
        // Get record
        $stmt = $pdo->prepare("SELECT * FROM ehr_records WHERE id = ? AND deleted = 0");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            jsonResponse(['error' => 'Record not found'], 404);
        }
        
        // Check permissions
        $canUpdate = false;
        
        if (hasRole('PATIENT')) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$record['patient_id'], $_SESSION['user_id']]);
            $canUpdate = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            $canUpdate = ensureConsent($record['patient_id'], $_SESSION['user_id'], [$record['type']]);
        } elseif (hasRole('ADMIN')) {
            $canUpdate = true;
        }
        
        if (!$canUpdate) {
            jsonResponse(['error' => 'Insufficient permissions to update record'], 403);
        }
        
        // Validate content
        $validatedContent = validateRecordContent($record['type'], $content);
        if (!$validatedContent) {
            jsonResponse(['error' => 'Invalid content structure for record type'], 400);
        }
        
        // Generate new content hash
        $contentJson = json_encode($validatedContent);
        $contentHash = hash('sha256', $contentJson);
        
        // Update record
        $updateFields = ['content = ?', 'content_hash = ?', 'updated_at = NOW()'];
        $updateValues = [$contentJson, $contentHash];
        
        if ($type && $type !== $record['type']) {
            $updateFields[] = 'type = ?';
            $updateValues[] = $type;
        }
        
        if ($recordedAt) {
            $updateFields[] = 'recorded_at = ?';
            $updateValues[] = $recordedAt;
        }
        
        $updateValues[] = $recordId;
        
        $stmt = $pdo->prepare("UPDATE ehr_records SET " . implode(', ', $updateFields) . " WHERE id = ?");
        $stmt->execute($updateValues);
        
        // Log update
        writeAudit($_SESSION['user_id'], $record['patient_id'], 'EHR_UPDATE', 
                  "Record ID: $recordId, Type: {$record['type']}");
        
        jsonResponse(['message' => 'Record updated successfully']);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Record update failed: ' . $e->getMessage()], 500);
    }
}

function handleDeleteRecord() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $recordId = (int)($data['id'] ?? 0);
    
    if (!$recordId) {
        jsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Get record
        $stmt = $pdo->prepare("SELECT * FROM ehr_records WHERE id = ? AND deleted = 0");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            jsonResponse(['error' => 'Record not found'], 404);
        }
        
        // Check permissions
        $canDelete = false;
        
        if (hasRole('PATIENT')) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$record['patient_id'], $_SESSION['user_id']]);
            $canDelete = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            $canDelete = ensureConsent($record['patient_id'], $_SESSION['user_id'], [$record['type']]);
        } elseif (hasRole('ADMIN')) {
            $canDelete = true;
        }
        
        if (!$canDelete) {
            jsonResponse(['error' => 'Insufficient permissions to delete record'], 403);
        }
        
        // Soft delete record
        $stmt = $pdo->prepare("UPDATE ehr_records SET deleted = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$recordId]);
        
        // Log deletion
        writeAudit($_SESSION['user_id'], $record['patient_id'], 'EHR_DELETE', 
                  "Record ID: $recordId, Type: {$record['type']}");
        
        jsonResponse(['message' => 'Record deleted successfully']);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Record deletion failed: ' . $e->getMessage()], 500);
    }
}

function validateRecordContent($type, $content) {
    // Basic validation for different record types
    switch ($type) {
        case 'ENCOUNTER':
            return isset($content['chief_complaint']) && isset($content['diagnosis']) ? $content : false;
        
        case 'LAB':
            return isset($content['test_name']) && isset($content['result']) ? $content : false;
        
        case 'PRESCRIPTION':
            return isset($content['medication']) && isset($content['dosage']) ? $content : false;
        
        case 'NOTE':
            return isset($content['note']) ? $content : false;
        
        case 'VITAL':
            return isset($content['vital_type']) && isset($content['value']) ? $content : false;
        
        case 'ALLERGY':
            return isset($content['allergen']) && isset($content['severity']) ? $content : false;
        
        case 'IMAGING':
            return isset($content['study_type']) && isset($content['findings']) ? $content : false;
        
        default:
            return false;
    }
}

function handleAssignRecord() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    requireRole('PATIENT');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $recordId = (int)($data['recordId'] ?? 0);
    $doctorId = (int)($data['doctorId'] ?? 0);
    $note = $data['note'] ?? '';
    
    if (!$recordId || !$doctorId) {
        jsonResponse(['error' => 'Record ID and Doctor ID are required'], 400);
    }
    
    global $pdo;
    
    try {
        // Verify record exists and belongs to patient
        $stmt = $pdo->prepare("SELECT r.*, p.user_id as patient_user_id 
                              FROM ehr_records r 
                              JOIN patients p ON r.patient_id = p.id 
                              WHERE r.id = ? AND r.deleted = 0 AND p.user_id = ?");
        $stmt->execute([$recordId, $_SESSION['user_id']]);
        $record = $stmt->fetch();
        
        if (!$record) {
            jsonResponse(['error' => 'Record not found or access denied'], 404);
        }
        
        // Verify doctor exists and has approved link with patient
        $stmt = $pdo->prepare("SELECT l.*, d.organization, d.user_id as doctor_user_id, u.full_name as doctor_name
                              FROM links l
                              JOIN doctors d ON l.doctor_id = d.id
                              JOIN users u ON d.user_id = u.id
                              WHERE l.patient_id = ? AND l.doctor_id = ? AND l.status = 'APPROVED'");
        $stmt->execute([$record['patient_id'], $doctorId]);
        $link = $stmt->fetch();
        
        if (!$link) {
            jsonResponse(['error' => 'Doctor not found or not approved for this patient'], 404);
        }
        
        // Check if assignment already exists
        $stmt = $pdo->prepare("SELECT id FROM record_assignments WHERE record_id = ? AND doctor_id = ?");
        $stmt->execute([$recordId, $doctorId]);
        $existingAssignment = $stmt->fetch();
        
        if ($existingAssignment) {
            jsonResponse(['error' => 'Record is already assigned to this doctor'], 400);
        }
        
        // Create assignment
        $stmt = $pdo->prepare("INSERT INTO record_assignments (record_id, doctor_id, assigned_by, assignment_note, assigned_at) 
                              VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$recordId, $doctorId, $_SESSION['user_id'], $note]);
        $assignmentId = $pdo->lastInsertId();
        
        // Create notification for doctor
        $notificationMessage = "New medical record assigned to you by {$record['patient_id']}";
        if ($note) {
            $notificationMessage .= ": " . $note;
        }
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, data, created_at) 
                              VALUES (?, 'RECORD_ASSIGNED', 'New Record Assignment', ?, ?, NOW())");
        $notificationData = json_encode([
            'record_id' => $recordId,
            'assignment_id' => $assignmentId,
            'patient_id' => $record['patient_id']
        ]);
        $stmt->execute([$link['doctor_user_id'], $notificationMessage, $notificationData]);
        
        // Log assignment
        writeAudit($_SESSION['user_id'], $record['patient_id'], 'RECORD_ASSIGNED', 
                  "Record ID: $recordId assigned to Doctor ID: $doctorId");
        
        jsonResponse(['message' => 'Record assigned successfully', 'assignment_id' => $assignmentId]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Record assignment failed: ' . $e->getMessage()], 500);
    }
}
?>

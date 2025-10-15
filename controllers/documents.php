<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'doc/upload':
        handleUploadDocument();
        break;
    case 'doc/download':
        handleDownloadDocument();
        break;
    case 'doc/delete':
        handleDeleteDocument();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleUploadDocument() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $recordId = (int)($_POST['recordId'] ?? 0);
    
    if (!$recordId) {
        jsonResponse(['error' => 'Record ID is required'], 400);
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No file uploaded'], 400);
    }
    
    global $pdo;
    
    try {
        // Get record and check permissions
        $stmt = $pdo->prepare("SELECT * FROM ehr_records WHERE id = ? AND deleted = 0");
        $stmt->execute([$recordId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            jsonResponse(['error' => 'Record not found'], 404);
        }
        
        // Check if user can upload to this record
        $canUpload = false;
        
        if (hasRole('PATIENT')) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$record['patient_id'], $_SESSION['user_id']]);
            $canUpload = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            $canUpload = ensureConsent($record['patient_id'], $_SESSION['user_id'], ['DOCUMENTS']);
        } elseif (hasRole('ADMIN')) {
            $canUpload = true;
        }
        
        if (!$canUpload) {
            jsonResponse(['error' => 'Insufficient permissions to upload document'], 403);
        }
        
        // Validate file
        $validation = validateFileUpload($_FILES['file']);
        if (!$validation['valid']) {
            jsonResponse(['error' => $validation['error']], 400);
        }
        
        // Create upload directory
        $uploadDir = createUploadDir($recordId);
        
        // Generate unique filename
        $originalName = $_FILES['file']['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . '/' . $fileName;
        
        // Move uploaded file
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $filePath)) {
            jsonResponse(['error' => 'Failed to save file'], 500);
        }
        
        // Generate file hash
        $fileHash = generateFileHash($filePath);
        $fileSize = filesize($filePath);
        $mimeType = $_FILES['file']['type'];
        
        // Save document record
        $stmt = $pdo->prepare("INSERT INTO documents (ehr_record_id, file_name, file_path, sha256, file_size, mime_type) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$recordId, $originalName, $filePath, $fileHash, $fileSize, $mimeType]);
        $documentId = $pdo->lastInsertId();
        
        // Log upload
        writeAudit($_SESSION['user_id'], $record['patient_id'], 'DOCUMENT_UPLOAD', 
                  "Record ID: $recordId, File: $originalName, Hash: $fileHash");
        
        jsonResponse([
            'message' => 'Document uploaded successfully',
            'document_id' => $documentId,
            'file_name' => $originalName,
            'file_hash' => $fileHash
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Upload failed: ' . $e->getMessage()], 500);
    }
}

function handleDownloadDocument() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $documentId = (int)($_GET['id'] ?? 0);
    
    if (!$documentId) {
        jsonResponse(['error' => 'Document ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Get document
        $stmt = $pdo->prepare("SELECT d.*, r.patient_id, r.type as record_type 
                              FROM documents d 
                              JOIN ehr_records r ON d.ehr_record_id = r.id 
                              WHERE d.id = ? AND r.deleted = 0");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        
        if (!$document) {
            jsonResponse(['error' => 'Document not found'], 404);
        }
        
        // Check permissions
        $canDownload = false;
        
        if (hasRole('PATIENT')) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$document['patient_id'], $_SESSION['user_id']]);
            $canDownload = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            $canDownload = ensureConsent($document['patient_id'], $_SESSION['user_id'], ['DOCUMENTS']);
        } elseif (hasRole('ADMIN')) {
            $canDownload = true;
        }
        
        if (!$canDownload) {
            jsonResponse(['error' => 'Insufficient permissions to download document'], 403);
        }
        
        // Check if file exists
        if (!file_exists($document['file_path'])) {
            jsonResponse(['error' => 'File not found on disk'], 404);
        }
        
        // Log download
        writeAudit($_SESSION['user_id'], $document['patient_id'], 'DOCUMENT_DOWNLOAD', 
                  "Document ID: $documentId, File: {$document['file_name']}");
        
        // Set headers for download
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
        header('Content-Length: ' . $document['file_size']);
        header('X-File-Hash: ' . $document['sha256']);
        
        // Output file
        readfile($document['file_path']);
        exit;
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Download failed: ' . $e->getMessage()], 500);
    }
}

function handleDeleteDocument() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $documentId = (int)($data['id'] ?? 0);
    
    if (!$documentId) {
        jsonResponse(['error' => 'Document ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Get document
        $stmt = $pdo->prepare("SELECT d.*, r.patient_id, r.type as record_type 
                              FROM documents d 
                              JOIN ehr_records r ON d.ehr_record_id = r.id 
                              WHERE d.id = ? AND r.deleted = 0");
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        
        if (!$document) {
            jsonResponse(['error' => 'Document not found'], 404);
        }
        
        // Check permissions
        $canDelete = false;
        
        if (hasRole('PATIENT')) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
            $stmt->execute([$document['patient_id'], $_SESSION['user_id']]);
            $canDelete = (bool)$stmt->fetch();
        } elseif (hasRole('DOCTOR')) {
            $canDelete = ensureConsent($document['patient_id'], $_SESSION['user_id'], ['DOCUMENTS']);
        } elseif (hasRole('ADMIN')) {
            $canDelete = true;
        }
        
        if (!$canDelete) {
            jsonResponse(['error' => 'Insufficient permissions to delete document'], 403);
        }
        
        // Delete file from disk
        if (file_exists($document['file_path'])) {
            unlink($document['file_path']);
        }
        
        // Delete document record
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$documentId]);
        
        // Log deletion
        writeAudit($_SESSION['user_id'], $document['patient_id'], 'DOCUMENT_DELETE', 
                  "Document ID: $documentId, File: {$document['file_name']}");
        
        jsonResponse(['message' => 'Document deleted successfully']);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Document deletion failed: ' . $e->getMessage()], 500);
    }
}
?>

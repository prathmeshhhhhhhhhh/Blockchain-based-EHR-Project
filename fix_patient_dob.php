<?php
/**
 * Fix Patient Date of Birth Issue
 */

require_once 'config/config.php';
require_once 'config/db.php';
require_once 'config/functions.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Patient DOB</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
    </style>
</head>
<body>";

echo "<div class='container mt-5'>";
echo "<h1>Fix Patient Date of Birth Issue</h1>";

// Check current patient data
echo "<div class='debug-info'>";
echo "<h3>Current Patient Data</h3>";
try {
    $stmt = $pdo->query("SELECT p.id, p.user_id, p.dob, p.gender, u.email, u.full_name FROM patients p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.id");
    $patients = $stmt->fetchAll();
    
    echo "<table class='table table-sm'>";
    echo "<thead><tr><th>Patient ID</th><th>User ID</th><th>Email</th><th>Full Name</th><th>DOB</th><th>Gender</th><th>DOB Status</th></tr></thead>";
    echo "<tbody>";
    foreach ($patients as $patient) {
        $dobStatus = '';
        if ($patient['dob'] === '0000-00-00' || $patient['dob'] === null) {
            $dobStatus = '<span class="text-danger">❌ Invalid</span>';
        } else {
            $dobStatus = '<span class="text-success">✅ Valid</span>';
        }
        
        echo "<tr>";
        echo "<td>{$patient['id']}</td>";
        echo "<td>{$patient['user_id']}</td>";
        echo "<td>{$patient['email']}</td>";
        echo "<td>{$patient['full_name']}</td>";
        echo "<td>{$patient['dob']}</td>";
        echo "<td>{$patient['gender']}</td>";
        echo "<td>{$dobStatus}</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error accessing patients table: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Fix invalid DOB for patient ID 3
echo "<div class='debug-info'>";
echo "<h3>Fix Invalid DOB for Patient ID 3</h3>";
try {
    // Check current DOB
    $stmt = $pdo->prepare("SELECT dob FROM patients WHERE id = ?");
    $stmt->execute([3]);
    $currentDob = $stmt->fetchColumn();
    
    echo "<p><strong>Current DOB:</strong> {$currentDob}</p>";
    
    if ($currentDob === '0000-00-00' || $currentDob === null) {
        // Set a default DOB (1990-01-01)
        $stmt = $pdo->prepare("UPDATE patients SET dob = '1990-01-01' WHERE id = ?");
        $stmt->execute([3]);
        
        echo "<div class='success'>✅ Updated DOB for patient ID 3 to 1990-01-01</div>";
        
        // Verify the update
        $stmt = $pdo->prepare("SELECT dob FROM patients WHERE id = ?");
        $stmt->execute([3]);
        $newDob = $stmt->fetchColumn();
        
        echo "<p><strong>New DOB:</strong> {$newDob}</p>";
    } else {
        echo "<div class='success'>✅ DOB is already valid</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error fixing DOB: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test deregistration process
echo "<div class='debug-info'>";
echo "<h3>Test Deregistration Process</h3>";
echo "<p>Now that the route is fixed and DOB is valid, try the deregistration process:</p>";
echo "<ol>";
echo "<li>Go to: <a href='" . url('?r=patient/deregister') . "' target='_blank'>Patient Deregister Page</a></li>";
echo "<li>Check the checkbox to confirm deletion</li>";
echo "<li>Click 'Permanently Delete My Account'</li>";
echo "<li>Check if the 'Invalid action' error is resolved</li>";
echo "</ol>";
echo "</div>";

echo "</div>"; // container

echo "</body></html>";
?>

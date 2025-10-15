<?php
/**
 * Create Record Assignments Table
 */

require_once 'config/db.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Create Record Assignments Table</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>";

echo "<div class='container mt-5'>";
echo "<h1>Create Record Assignments Table</h1>";

try {
    // Create record_assignments table
    $sql = "CREATE TABLE IF NOT EXISTS record_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        record_id INT NOT NULL,
        doctor_id INT NOT NULL,
        assigned_by INT NOT NULL,
        assignment_note TEXT,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('ACTIVE', 'REVOKED') DEFAULT 'ACTIVE',
        revoked_at TIMESTAMP NULL,
        revoked_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (record_id) REFERENCES ehr_records(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL,
        
        UNIQUE KEY unique_assignment (record_id, doctor_id),
        INDEX idx_record_id (record_id),
        INDEX idx_doctor_id (doctor_id),
        INDEX idx_assigned_by (assigned_by),
        INDEX idx_status (status)
    )";
    
    $pdo->exec($sql);
    echo "<div class='alert alert-success'>";
    echo "<h3>✅ Record Assignments Table Created Successfully!</h3>";
    echo "<p>The <code>record_assignments</code> table has been created with the following structure:</p>";
    echo "<ul>";
    echo "<li><strong>id:</strong> Primary key</li>";
    echo "<li><strong>record_id:</strong> Reference to ehr_records table</li>";
    echo "<li><strong>doctor_id:</strong> Reference to doctors table</li>";
    echo "<li><strong>assigned_by:</strong> User who made the assignment</li>";
    echo "<li><strong>assignment_note:</strong> Optional note about the assignment</li>";
    echo "<li><strong>assigned_at:</strong> When the assignment was made</li>";
    echo "<li><strong>status:</strong> ACTIVE or REVOKED</li>";
    echo "<li><strong>revoked_at:</strong> When the assignment was revoked (if applicable)</li>";
    echo "<li><strong>revoked_by:</strong> Who revoked the assignment (if applicable)</li>";
    echo "</ul>";
    echo "</div>";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'record_assignments'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='alert alert-info'>";
        echo "<h3>📋 Table Structure</h3>";
        echo "<p>Table <code>record_assignments</code> exists and is ready to use.</p>";
        echo "</div>";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE record_assignments");
        $columns = $stmt->fetchAll();
        
        echo "<div class='alert alert-light'>";
        echo "<h4>Table Columns:</h4>";
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>";
        echo "<tbody>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h3>❌ Error Creating Table</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<div class='alert alert-info'>";
echo "<h3>🎯 Next Steps</h3>";
echo "<ol>";
echo "<li><strong>Test Edit Feature:</strong> <a href='public/?r=patient/records' target='_blank'>Patient Records Page</a></li>";
echo "<li><strong>Test Assign Feature:</strong> Click 'Assign' button on any record</li>";
echo "<li><strong>Verify Database:</strong> Check record_assignments table in phpMyAdmin</li>";
echo "</ol>";
echo "</div>";

echo "</div>"; // container

echo "</body></html>";
?>

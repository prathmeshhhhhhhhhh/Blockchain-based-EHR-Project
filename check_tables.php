<?php
/**
 * Check table structures to diagnose foreign key issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Table Structure Check</h2>";

$host = 'localhost';
$dbname = 'medihub';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    echo "✅ Database connection successful<br><br>";
    
    // Check users table
    echo "<h3>Users Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE users");
    $users = $stmt->fetchAll();
    $stmt->closeCursor();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['Field'] . "</td>";
        echo "<td>" . $user['Type'] . "</td>";
        echo "<td>" . $user['Null'] . "</td>";
        echo "<td>" . $user['Key'] . "</td>";
        echo "<td>" . $user['Default'] . "</td>";
        echo "<td>" . $user['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Check ehr_records table
    echo "<h3>EHR Records Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE ehr_records");
    $ehr = $stmt->fetchAll();
    $stmt->closeCursor();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($ehr as $record) {
        echo "<tr>";
        echo "<td>" . $record['Field'] . "</td>";
        echo "<td>" . $record['Type'] . "</td>";
        echo "<td>" . $record['Null'] . "</td>";
        echo "<td>" . $record['Key'] . "</td>";
        echo "<td>" . $record['Default'] . "</td>";
        echo "<td>" . $record['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    // Check if there are any existing foreign keys
    echo "<h3>Existing Foreign Keys:</h3>";
    $stmt = $pdo->query("SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'medihub' 
        AND REFERENCED_TABLE_NAME IS NOT NULL");
    $fks = $stmt->fetchAll();
    $stmt->closeCursor();
    
    if (empty($fks)) {
        echo "No foreign keys found.<br>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Table</th><th>Column</th><th>Constraint</th><th>References</th></tr>";
        foreach ($fks as $fk) {
            echo "<tr>";
            echo "<td>" . $fk['TABLE_NAME'] . "</td>";
            echo "<td>" . $fk['COLUMN_NAME'] . "</td>";
            echo "<td>" . $fk['CONSTRAINT_NAME'] . "</td>";
            echo "<td>" . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    }
    
    // Check data types compatibility
    echo "<h3>Data Type Analysis:</h3>";
    $users_id = null;
    $ehr_created_by = null;
    
    foreach ($users as $user) {
        if ($user['Field'] === 'id') {
            $users_id = $user['Type'];
        }
    }
    
    foreach ($ehr as $record) {
        if ($record['Field'] === 'created_by_user') {
            $ehr_created_by = $record['Type'];
        }
    }
    
    echo "users.id type: " . $users_id . "<br>";
    echo "ehr_records.created_by_user type: " . $ehr_created_by . "<br>";
    
    if ($users_id === $ehr_created_by) {
        echo "✅ Data types match<br>";
    } else {
        echo "❌ Data types don't match - this is the problem!<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

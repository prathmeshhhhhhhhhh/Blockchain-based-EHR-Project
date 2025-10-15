<?php
/**
 * Simple Database Setup Script
 * This will create the database and tables without foreign key issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MediHub Database Setup</h2>";

$host = 'localhost';
$dbname = 'medihub';
$username = 'root';
$password = '';

try {
    // Connect to MySQL without specifying database
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    echo "✅ Connected to MySQL server<br>";
    
    // Drop database if exists
    $pdo->exec("DROP DATABASE IF EXISTS $dbname");
    echo "✅ Dropped existing database (if any)<br>";
    
    // Create database
    $pdo->exec("CREATE DATABASE $dbname");
    echo "✅ Created database '$dbname'<br>";
    
    // Use the database
    $pdo->exec("USE $dbname");
    echo "✅ Using database '$dbname'<br>";
    
    // Read and execute migration script
    $migration = file_get_contents('scripts/migrate_no_fk.sql');
    if ($migration === false) {
        throw new Exception('Could not read migration file');
    }
    
    // Split into statements and execute
    $statements = explode(';', $migration);
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement) && !preg_match('/^USE/', $statement)) {
            try {
                $pdo->exec($statement);
                $executed++;
            } catch (Exception $e) {
                $errors++;
                echo "⚠️ Statement failed: " . substr($statement, 0, 50) . "... Error: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "✅ Executed $executed SQL statements<br>";
    if ($errors > 0) {
        echo "⚠️ $errors statements had errors (this might be normal)<br>";
    }
    
    // Read and execute seed script
    $seed = file_get_contents('scripts/seed.sql');
    if ($seed === false) {
        throw new Exception('Could not read seed file');
    }
    
    $statements = explode(';', $seed);
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement) && !preg_match('/^USE/', $statement)) {
            try {
                $pdo->exec($statement);
                $executed++;
            } catch (Exception $e) {
                $errors++;
                echo "⚠️ Seed statement failed: " . substr($statement, 0, 50) . "... Error: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "✅ Executed $executed seed statements<br>";
    if ($errors > 0) {
        echo "⚠️ $errors seed statements had errors<br>";
    }
    
    // Test the setup
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "✅ Found $userCount users in database<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ehr_records");
    $recordCount = $stmt->fetch()['count'];
    echo "✅ Found $recordCount medical records in database<br>";
    
    echo "<h3 style='color: green;'>Database setup completed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Visit <a href='public/'>http://localhost/EHR/mediHub-mvp/public/</a> to access the application</li>";
    echo "<li>Use the demo accounts to test the system</li>";
    echo "<li>Admin: admin@medihub.com / password</li>";
    echo "<li>Patient: patient1@medihub.com / password</li>";
    echo "<li>Doctor: dr.smith@medihub.com / password</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "<p><strong>Make sure:</strong></p>";
    echo "<ul>";
    echo "<li>MySQL is running</li>";
    echo "<li>Username and password are correct</li>";
    echo "<li>You have permission to create databases</li>";
    echo "</ul>";
}
?>

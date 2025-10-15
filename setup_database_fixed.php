<?php
/**
 * Fixed Database Setup Script
 * This will create the database and tables properly
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MediHub Database Setup (Fixed)</h2>";

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
    
    // Read migration script
    $migration = file_get_contents('scripts/migrate_no_fk.sql');
    if ($migration === false) {
        throw new Exception('Could not read migration file');
    }
    
    // Clean up the migration script
    $migration = preg_replace('/--.*$/m', '', $migration); // Remove comments
    $migration = preg_replace('/^\s*$/m', '', $migration); // Remove empty lines
    $migration = trim($migration);
    
    // Split into statements
    $statements = preg_split('/;\s*$/m', $migration);
    $executed = 0;
    $errors = 0;
    
    echo "<h3>Creating Tables:</h3>";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^(USE|CREATE DATABASE)/i', $statement)) {
            try {
                $pdo->exec($statement);
                $executed++;
                
                // Extract table name for display
                if (preg_match('/CREATE TABLE\s+(\w+)/i', $statement, $matches)) {
                    echo "✅ Created table: {$matches[1]}<br>";
                }
            } catch (Exception $e) {
                $errors++;
                echo "❌ Statement failed: " . substr($statement, 0, 50) . "... Error: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    echo "<br>✅ Executed $executed SQL statements<br>";
    if ($errors > 0) {
        echo "⚠️ $errors statements had errors<br>";
    }
    
    // Now add foreign keys
    echo "<h3>Adding Foreign Keys:</h3>";
    $fk_script = file_get_contents('scripts/add_foreign_keys.sql');
    if ($fk_script !== false) {
        $fk_script = preg_replace('/--.*$/m', '', $fk_script);
        $fk_script = preg_replace('/^\s*$/m', '', $fk_script);
        $fk_script = trim($fk_script);
        
        $fk_statements = preg_split('/;\s*$/m', $fk_script);
        $fk_executed = 0;
        $fk_errors = 0;
        
        foreach ($fk_statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    $fk_executed++;
                    echo "✅ Added foreign key constraint<br>";
                } catch (Exception $e) {
                    $fk_errors++;
                    echo "⚠️ Foreign key failed: " . $e->getMessage() . "<br>";
                }
            }
        }
        
        echo "✅ Executed $fk_executed foreign key statements<br>";
        if ($fk_errors > 0) {
            echo "⚠️ $fk_errors foreign key statements had errors<br>";
        }
    }
    
    // Now run seed data
    echo "<h3>Adding Seed Data:</h3>";
    $seed = file_get_contents('scripts/seed.sql');
    if ($seed !== false) {
        $seed = preg_replace('/--.*$/m', '', $seed);
        $seed = preg_replace('/^\s*$/m', '', $seed);
        $seed = trim($seed);
        
        $seed_statements = preg_split('/;\s*$/m', $seed);
        $seed_executed = 0;
        $seed_errors = 0;
        
        foreach ($seed_statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^(SELECT|UPDATE|INSERT INTO settings)/i', $statement)) {
                try {
                    $pdo->exec($statement);
                    $seed_executed++;
                    
                    // Extract what was inserted
                    if (preg_match('/INSERT INTO\s+(\w+)/i', $statement, $matches)) {
                        echo "✅ Added data to: {$matches[1]}<br>";
                    }
                } catch (Exception $e) {
                    $seed_errors++;
                    echo "⚠️ Seed statement failed: " . substr($statement, 0, 50) . "... Error: " . $e->getMessage() . "<br>";
                }
            }
        }
        
        echo "✅ Executed $seed_executed seed statements<br>";
        if ($seed_errors > 0) {
            echo "⚠️ $seed_errors seed statements had errors<br>";
        }
    }
    
    // Test the setup
    echo "<h3>Testing Setup:</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "✅ Found $userCount users in database<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ehr_records");
    $recordCount = $stmt->fetch()['count'];
    echo "✅ Found $recordCount medical records in database<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
    $patientCount = $stmt->fetch()['count'];
    echo "✅ Found $patientCount patients in database<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors");
    $doctorCount = $stmt->fetch()['count'];
    echo "✅ Found $doctorCount doctors in database<br>";
    
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

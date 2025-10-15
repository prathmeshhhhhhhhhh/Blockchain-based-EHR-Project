<?php
/**
 * Manual Database Setup - Creates tables one by one
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MediHub Manual Database Setup</h2>";

$host = 'localhost';
$dbname = 'medihub';
$username = 'root';
$password = '';

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    echo "✅ Connected to MySQL server<br>";
    
    // Drop and create database
    $pdo->exec("DROP DATABASE IF EXISTS $dbname");
    $pdo->exec("CREATE DATABASE $dbname");
    $pdo->exec("USE $dbname");
    echo "✅ Created database '$dbname'<br>";
    
    // Create tables one by one
    $tables = [
        'users' => "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('PATIENT','DOCTOR','ADMIN') NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'patients' => "CREATE TABLE patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            dob DATE,
            gender ENUM('Male','Female','Other'),
            phone VARCHAR(20),
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'doctors' => "CREATE TABLE doctors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reg_no VARCHAR(50),
            organization VARCHAR(120),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'ehr_records' => "CREATE TABLE ehr_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            type ENUM('ENCOUNTER','LAB','PRESCRIPTION','NOTE','VITAL','ALLERGY','IMAGING') NOT NULL,
            content TEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            recorded_at DATETIME NOT NULL,
            created_by_user INT NOT NULL,
            deleted TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_patient_type (patient_id, type),
            INDEX idx_recorded_at (recorded_at)
        )",
        
        'consents' => "CREATE TABLE consents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            doctor_id INT NOT NULL,
            status ENUM('ACTIVE','REVOKED','EXPIRED') NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            revoked_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'links' => "CREATE TABLE links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            doctor_id INT NOT NULL,
            status ENUM('REQUESTED','APPROVED','REJECTED') NOT NULL,
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            responded_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'documents' => "CREATE TABLE documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            uploaded_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'notifications' => "CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            read_status TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        'audit_log' => "CREATE TABLE audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            patient_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        'settings' => "CREATE TABLE settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];
    
    // Create each table
    foreach ($tables as $tableName => $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Created table: $tableName<br>";
        } catch (Exception $e) {
            echo "❌ Failed to create table $tableName: " . $e->getMessage() . "<br>";
        }
    }
    
    // Add foreign keys
    echo "<h3>Adding Foreign Keys:</h3>";
    $foreignKeys = [
        "ALTER TABLE patients ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",
        "ALTER TABLE doctors ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",
        "ALTER TABLE ehr_records ADD FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE",
        "ALTER TABLE ehr_records ADD FOREIGN KEY (created_by_user) REFERENCES users(id) ON DELETE SET NULL",
        "ALTER TABLE consents ADD FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE",
        "ALTER TABLE consents ADD FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE",
        "ALTER TABLE links ADD FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE",
        "ALTER TABLE links ADD FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE",
        "ALTER TABLE documents ADD FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE",
        "ALTER TABLE documents ADD FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL",
        "ALTER TABLE notifications ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE",
        "ALTER TABLE audit_log ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL",
        "ALTER TABLE audit_log ADD FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL"
    ];
    
    foreach ($foreignKeys as $fk) {
        try {
            $pdo->exec($fk);
            echo "✅ Added foreign key constraint<br>";
        } catch (Exception $e) {
            echo "⚠️ Foreign key failed: " . $e->getMessage() . "<br>";
        }
    }
    
    // Insert demo data
    echo "<h3>Inserting Demo Data:</h3>";
    
    // Insert users
    $users = [
        ['admin@medihub.com', password_hash('password', PASSWORD_DEFAULT), 'ADMIN', 'System Administrator'],
        ['patient1@medihub.com', password_hash('password', PASSWORD_DEFAULT), 'PATIENT', 'John Patient'],
        ['dr.smith@medihub.com', password_hash('password', PASSWORD_DEFAULT), 'DOCTOR', 'Dr. Smith']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, full_name) VALUES (?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->execute($user);
    }
    echo "✅ Inserted demo users<br>";
    
    // Insert patients
    $stmt = $pdo->prepare("INSERT INTO patients (user_id, dob, gender, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([2, '1990-01-01', 'Male', '123-456-7890']);
    echo "✅ Inserted demo patient<br>";
    
    // Insert doctors
    $stmt = $pdo->prepare("INSERT INTO doctors (user_id, reg_no, organization) VALUES (?, ?, ?)");
    $stmt->execute([3, 'MD12345', 'City Hospital']);
    echo "✅ Inserted demo doctor<br>";
    
    // Insert settings
    $settings = [
        ['app_version', '1.0.0-demo'],
        ['k_anonymity_threshold', '10'],
        ['max_file_size', '5242880']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "✅ Inserted settings<br>";
    
    // Test the setup
    echo "<h3>Testing Setup:</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "✅ Found $userCount users in database<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
    $patientCount = $stmt->fetch()['count'];
    echo "✅ Found $patientCount patients in database<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors");
    $doctorCount = $stmt->fetch()['count'];
    echo "✅ Found $doctorCount doctors in database<br>";
    
    echo "<h3 style='color: green;'>Database setup completed successfully!</h3>";
    echo "<p><strong>Demo Accounts:</strong></p>";
    echo "<ul>";
    echo "<li>Admin: admin@medihub.com / password</li>";
    echo "<li>Patient: patient1@medihub.com / password</li>";
    echo "<li>Doctor: dr.smith@medihub.com / password</li>";
    echo "</ul>";
    echo "<p><a href='public/'>Go to Application</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

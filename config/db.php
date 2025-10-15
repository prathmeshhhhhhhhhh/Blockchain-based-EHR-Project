<?php
// Database configuration
$host = 'localhost';
$dbname = 'medihub';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
} catch(PDOException $e) {
    // Don't die immediately, let the application handle it
    $pdo = null;
    $db_error = "Database connection failed: " . $e->getMessage();
}

// Legacy mysqli connection for compatibility
try {
    $mysqli = new mysqli($host, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        $mysqli = null;
    } else {
        $mysqli->set_charset("utf8mb4");
    }
} catch (Exception $e) {
    $mysqli = null;
}
?>
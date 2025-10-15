<?php
/**
 * MediHub Installation Script
 * Run this script to set up the database and initial configuration
 */

// Check if already installed
if (file_exists('config/installed.flag')) {
    die('MediHub is already installed. Delete config/installed.flag to reinstall.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_POST) {
    switch ($step) {
        case 2:
            // Test database connection
            $host = $_POST['db_host'] ?? 'localhost';
            $dbname = $_POST['db_name'] ?? 'medihub';
            $username = $_POST['db_user'] ?? 'root';
            $password = $_POST['db_pass'] ?? '';
            
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                
                // Save database config
                $config = "<?php
// Database configuration
\$host = '$host';
\$dbname = '$dbname';
\$username = '$username';
\$password = '$password';

try {
    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$dbname;charset=utf8mb4\", \$username, \$password);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException \$e) {
    die(\"Connection failed: \" . \$e->getMessage());
}

// Legacy mysqli connection for compatibility
\$mysqli = new mysqli(\$host, \$username, \$password, \$dbname);
if (\$mysqli->connect_error) {
    die(\"Connection failed: \" . \$mysqli->connect_error);
}
\$mysqli->set_charset(\"utf8mb4\");
?>";
                
                file_put_contents('config/db.php', $config);
                $success = 'Database connection successful!';
                $step = 3;
            } catch (Exception $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 3:
            // Run database migrations
            try {
                $host = $_POST['db_host'] ?? 'localhost';
                $dbname = $_POST['db_name'] ?? 'medihub';
                $username = $_POST['db_user'] ?? 'root';
                $password = $_POST['db_pass'] ?? '';
                
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                
                // Read and execute migration script
                $migration = file_get_contents('scripts/migrate.sql');
                $statements = explode(';', $migration);
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                $success = 'Database schema created successfully!';
                $step = 4;
            } catch (Exception $e) {
                $error = 'Migration failed: ' . $e->getMessage();
            }
            break;
            
        case 4:
            // Seed database
            try {
                $host = $_POST['db_host'] ?? 'localhost';
                $dbname = $_POST['db_name'] ?? 'medihub';
                $username = $_POST['db_user'] ?? 'root';
                $password = $_POST['db_pass'] ?? '';
                
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                
                // Read and execute seed script
                $seed = file_get_contents('scripts/seed.sql');
                $statements = explode(';', $seed);
                
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                // Create installed flag
                file_put_contents('config/installed.flag', date('Y-m-d H:i:s'));
                
                $success = 'MediHub installed successfully!';
                $step = 5;
            } catch (Exception $e) {
                $error = 'Seeding failed: ' . $e->getMessage();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediHub Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="bi bi-hospital"></i> MediHub Installation
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($step == 1): ?>
                            <h4>Welcome to MediHub Installation</h4>
                            <p>This wizard will help you set up your MediHub Electronic Health Records system.</p>
                            
                            <h5>Prerequisites:</h5>
                            <ul>
                                <li>PHP 8.2 or higher</li>
                                <li>MySQL 8.0 or MariaDB 10.3 or higher</li>
                                <li>Web server (Apache/Nginx)</li>
                                <li>Write permissions for the uploads/ directory</li>
                            </ul>
                            
                            <h5>What will be installed:</h5>
                            <ul>
                                <li>Database schema with all required tables</li>
                                <li>Demo data for testing</li>
                                <li>Default admin and user accounts</li>
                                <li>Sample medical records</li>
                            </ul>
                            
                            <div class="d-grid">
                                <a href="?step=2" class="btn btn-primary btn-lg">
                                    <i class="bi bi-arrow-right"></i> Start Installation
                                </a>
                            </div>
                            
                        <?php elseif ($step == 2): ?>
                            <h4>Database Configuration</h4>
                            <p>Please provide your database connection details:</p>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Database Host</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" value="localhost" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Database Name</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" value="medihub" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Database Username</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" value="root" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Database Password</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass">
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-database"></i> Test Connection
                                    </button>
                                </div>
                            </form>
                            
                        <?php elseif ($step == 3): ?>
                            <h4>Database Migration</h4>
                            <p>Creating database tables and schema...</p>
                            
                            <form method="POST">
                                <input type="hidden" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>">
                                <input type="hidden" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
                                <input type="hidden" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
                                <input type="hidden" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-gear"></i> Create Database Schema
                                    </button>
                                </div>
                            </form>
                            
                        <?php elseif ($step == 4): ?>
                            <h4>Seed Database</h4>
                            <p>Adding demo data and sample records...</p>
                            
                            <form method="POST">
                                <input type="hidden" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>">
                                <input type="hidden" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">
                                <input type="hidden" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
                                <input type="hidden" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-seed"></i> Add Demo Data
                                    </button>
                                </div>
                            </form>
                            
                        <?php elseif ($step == 5): ?>
                            <h4>Installation Complete!</h4>
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle"></i> MediHub has been successfully installed!</h5>
                            </div>
                            
                            <h5>Demo Accounts:</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Admin</h6>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Email:</strong> admin@medihub.com</p>
                                            <p><strong>Password:</strong> password</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Patient</h6>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Email:</strong> patient1@medihub.com</p>
                                            <p><strong>Password:</strong> password</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-exclamation-triangle"></i> Security Notice</h6>
                                <p class="mb-0">Please change the default passwords before using in production!</p>
                            </div>
                            
                            <div class="d-grid">
                                <a href="public/" class="btn btn-success btn-lg">
                                    <i class="bi bi-arrow-right"></i> Go to MediHub
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

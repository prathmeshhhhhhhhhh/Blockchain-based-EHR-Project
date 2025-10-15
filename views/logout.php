<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/config.php';

// Support GET-based logout for convenience
if (isLoggedIn()) {
    writeAudit($_SESSION['user_id'], null, 'LOGOUT', '');
}
session_destroy();

header('Location: ' . BASE_URL);
exit;
?>




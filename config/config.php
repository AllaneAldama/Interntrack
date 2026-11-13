<?php
declare(strict_types=1);

session_start();

$host = 'localhost';
$db   = 'interntrack';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

require_once __DIR__ . '/../includes/system-error-logging.php';
register_system_error_logging($conn);

require_once __DIR__ . '/../includes/system-settings.php';
$system_settings = load_system_settings($conn);
date_default_timezone_set($system_settings['system_timezone']);

require_once __DIR__ . '/../includes/remember-me.php';
restore_remembered_session($conn);
?>

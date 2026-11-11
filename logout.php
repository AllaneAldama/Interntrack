<?php
require_once __DIR__ . '/config/config.php';
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
if ($user_id !== null) {
    require_once __DIR__ . '/includes/auth.php';
    log_activity($conn, $user_id, 'Signed out');
}
clear_remember_me_token($conn);
session_unset();
session_destroy();
header('Location: index.php');
exit;
?>

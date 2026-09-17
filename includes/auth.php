<?php
require_once __DIR__ . '/../config/config.php';

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /interntrack/index.php');
        exit;
    }
}

function require_role(array $roles): void {
    require_login();

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}
?>

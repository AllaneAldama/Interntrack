<?php
require_once __DIR__ . '/../config/config.php';

function require_login(): void {
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        header('Location: /interntrack/index.php');
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT status, role
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException('Could not validate the current account.');
    }

    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not validate the current account.');
    }

    $account = $stmt->get_result()->fetch_assoc();
    if (!$account || $account['status'] !== 'active') {
        clear_remember_me_token($conn);
        $_SESSION = [];
        session_destroy();
        header('Location: /interntrack/index.php');
        exit;
    }

    global $system_settings;
    $_SESSION['role'] = $account['role'];
    if ($system_settings['automatic_logout'] === '1') {
        $timeout = max(1, (int) $system_settings['session_timeout_minutes']) * 60;
        $last_activity = (int) ($_SESSION['last_activity_at'] ?? time());

        if (time() - $last_activity >= $timeout) {
            log_activity($conn, $user_id, 'Session expired due to inactivity');
            clear_remember_me_token($conn);
            $_SESSION = [];
            session_destroy();
            header('Location: /interntrack/index.php?expired=1');
            exit;
        }
    }

    $_SESSION['last_activity_at'] = time();

    if ($system_settings['maintenance_mode'] === '1' && $_SESSION['role'] !== 'admin') {
        http_response_code(503);
        exit('InternTrack is temporarily unavailable while maintenance is in progress.');
    }
}

function require_role(array $roles): void {
    require_login();

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function csrf_token(): string {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(mixed $token): bool {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function log_activity(mysqli $conn, ?int $user_id, string $action): void {
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action)
        VALUES (?, ?)
    ");

    if (!$stmt) {
        throw new RuntimeException('Could not prepare activity logging.');
    }

    $stmt->bind_param('is', $user_id, $action);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not save the activity log.');
    }
}
?>

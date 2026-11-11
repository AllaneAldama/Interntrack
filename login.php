<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email_input = $_POST['email'] ?? '';
$email = is_string($email_input) ? trim($email_input) : '';
$password_input = $_POST['password'] ?? '';
$password = is_string($password_input) ? $password_input : '';
if (!$conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)")) {
    throw new RuntimeException('Could not clean up expired login attempts.');
}
$identity_hash = hash('sha256', strtolower($email) . "\0" . ($_SERVER['REMOTE_ADDR'] ?? ''));
$attempt_limit = max(1, (int) $system_settings['login_attempt_limit']);
$lockout_minutes = max(1, (int) $system_settings['login_lockout_minutes']);
$attempt_stmt = $conn->prepare("
    SELECT COUNT(*) AS attempts
    FROM login_attempts
    WHERE identity_hash = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL {$lockout_minutes} MINUTE)
");
$attempt_stmt->bind_param('s', $identity_hash);
$attempt_stmt->execute();
$recent_attempts = (int) $attempt_stmt->get_result()->fetch_assoc()['attempts'];

if ($recent_attempts >= $attempt_limit) {
    $_SESSION['login_error'] = 'Too many failed sign-in attempts. Try again after the lockout period.';
    header('Location: index.php');
    exit;
}

$stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $failure_stmt = $conn->prepare("INSERT INTO login_attempts (identity_hash) VALUES (?)");
    $failure_stmt->bind_param('s', $identity_hash);
    if (!$failure_stmt->execute()) {
        throw new RuntimeException('Could not record the failed sign-in attempt.');
    }
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: index.php');
    exit;
}

if ($system_settings['maintenance_mode'] === '1' && $user['role'] !== 'admin') {
    $_SESSION['login_error'] = 'InternTrack is temporarily unavailable while maintenance is in progress.';
    header('Location: index.php');
    exit;
}

$clear_attempts = $conn->prepare("DELETE FROM login_attempts WHERE identity_hash = ?");
$clear_attempts->bind_param('s', $identity_hash);
if (!$clear_attempts->execute()) {
    throw new RuntimeException('Could not clear previous sign-in attempts.');
}

$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['last_activity_at'] = time();
session_regenerate_id(true);
if (isset($_POST['remember_me']) && $_POST['remember_me'] === '1') {
    create_remember_me_token($conn, (int) $user['user_id']);
} else {
    clear_remember_me_token($conn);
}
log_activity($conn, (int) $user['user_id'], 'Signed in');

if ($user['role'] === 'admin') {
    header('Location: admin/admin-dashboard.php');
    exit;
} elseif ($user['role'] === 'coordinator') {
    header('Location: coordinator/coordinator-dashboard.php');
    exit;
} elseif ($user['role'] === 'supervisor') {
    header('Location: supervisor/supervisor-dashboard.php');
    exit;
} else {
    header('Location: dashboard.php');
    exit;
}
?>

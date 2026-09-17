<?php
require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: index.php');
    exit;
}

$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];

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

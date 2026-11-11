<?php
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
if ($system_settings['maintenance_mode'] === '1' && $error === '') {
    $error = 'InternTrack is temporarily unavailable while maintenance is in progress.';
}
if (isset($_GET['expired'])) {
    $error = 'You were signed out after being inactive.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($system_settings['system_name']) ?> - Login</title>
<link rel="stylesheet" href="/interntrack/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="login-page">
<section class="login-visual" aria-label="Internship journey">
    <div class="login-visual-content">
        <span class="login-eyebrow">YOUR INTERNSHIP JOURNEY, ALL IN ONE PLACE</span>
        <h1>Every step<br>brings you closer.</h1>
        <p>Track progress, manage requirements, monitor performance, and keep everyone on the same page.</p>
        <div class="login-visual-footer">
            <span class="login-visual-dot" aria-hidden="true"></span>
            <span>Connecting interns, supervisors, and coordinators</span>
        </div>
    </div>
</section>
<main class="login-main">
<div class="login-card">
    <div class="login-brand">
        <?php if ($system_settings['system_logo'] !== ''): ?>
            <img class="system-logo login-logo" src="/interntrack/<?= htmlspecialchars($system_settings['system_logo']) ?>" alt="">
        <?php endif; ?>
        <div class="brand login-brand-name"><?= htmlspecialchars($system_settings['system_name']) ?></div>
    </div>
    <div class="login-heading">
        <h2>Welcome back</h2>
        <p>Sign in to continue to your account.</p>
    </div>

    <?php if ($error): ?><div class="alert login-alert" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($system_settings['maintenance_mode'] !== '1'): ?>
    <form class="login-form" action="/interntrack/login.php" method="POST">
        <div class="login-field">
            <label for="login-email">Email address</label>
            <input id="login-email" type="email" name="email" required autocomplete="username" placeholder="you@example.com">
        </div>
        <div class="login-field">
            <div class="login-label-row">
                <label for="login-password">Password</label>
            </div>
            <div class="password-control">
                <input id="login-password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" aria-controls="login-password">
                    <svg class="password-icon password-icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="password-icon password-icon-hide" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 10.7a2 2 0 0 0 2.7 2.7"/><path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a14.8 14.8 0 0 1-3.1 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 4.1-.8"/></svg>
                    <span>Show</span>
                </button>
            </div>
        </div>
        <div class="login-options">
            <label class="remember-option" for="remember-me">
                <input id="remember-me" type="checkbox" name="remember_me" value="1">
                <span>Remember me</span>
            </label>
            <span class="remember-hint">Stay signed in for 30 days</span>
        </div>
        <button class="btn primary full login-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>
    <?php else: ?>
        <div class="maintenance-note">Sign-in is unavailable while system maintenance is in progress.</div>
    <?php endif; ?>
    <p class="login-footnote">Authorized users only</p>
</div>
<script src="/interntrack/assets/js/login.js?v=<?= (int) filemtime(__DIR__ . '/assets/js/login.js') ?>" defer></script>
</main>
</body>
</html>

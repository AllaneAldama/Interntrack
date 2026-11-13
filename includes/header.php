<?php
require_once __DIR__ . '/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= htmlspecialchars($system_settings['system_description']) ?>">
<title><?= htmlspecialchars(($page_title ?? 'InternTrack') . ' | ' . $system_settings['system_name']) ?></title>
<link rel="stylesheet" href="/interntrack/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="app">
<aside class="sidebar">
    <a class="brand-link" href="/interntrack/dashboard.php" aria-label="<?= htmlspecialchars($system_settings['system_name']) ?> home">
        <?php if ($system_settings['system_logo'] !== ''): ?>
            <img class="system-logo sidebar-system-logo" src="/interntrack/<?= htmlspecialchars($system_settings['system_logo']) ?>" alt="">
            <span class="brand"><?= htmlspecialchars($system_settings['system_name']) ?></span>
        <?php else: ?>
            <span class="brand-mark" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($system_settings['system_name'], 0, 1))) ?></span>
            <span class="brand"><?= htmlspecialchars($system_settings['system_name']) ?></span>
        <?php endif; ?>
    </a>
    <?php $current_page = basename($_SERVER['SCRIPT_NAME']); ?>
    <nav>
        <span class="nav-caption">Workspace</span>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a class="<?= $current_page === 'admin-dashboard.php' ? 'active' : '' ?>" href="/interntrack/admin/admin-dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
        <?php elseif ($_SESSION['role'] === 'coordinator'): ?>
            <a class="<?= $current_page === 'coordinator-dashboard.php' ? 'active' : '' ?>" href="/interntrack/coordinator/coordinator-dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
        <?php elseif ($_SESSION['role'] === 'supervisor'): ?>
            <a class="<?= $current_page === 'supervisor-dashboard.php' ? 'active' : '' ?>" href="/interntrack/supervisor/supervisor-dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
        <?php else: ?>
            <a class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>" href="/interntrack/dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
        <?php endif; ?>
        <span class="nav-caption">Management</span>
        <?php if ($_SESSION['role'] === 'student'): ?>
            <a class="<?= $current_page === 'profile.php' ? 'active' : '' ?>" href="/interntrack/student/profile.php"><span class="nav-icon">◉</span>Internship Profile</a>
            <a class="<?= $current_page === 'requirements.php' ? 'active' : '' ?>" href="/interntrack/student/requirements.php"><span class="nav-icon">▤</span>Requirements</a>
            <a class="<?= $current_page === 'attendance.php' ? 'active' : '' ?>" href="/interntrack/student/attendance.php"><span class="nav-icon">◷</span>Attendance</a>
            <a class="<?= $current_page === 'reports.php' ? 'active' : '' ?>" href="/interntrack/student/reports.php"><span class="nav-icon">▧</span>Accomplishment Reports</a>
        <?php elseif ($_SESSION['role'] === 'coordinator'): ?>
            <a class="<?= $current_page === 'students.php' ? 'active' : '' ?>" href="/interntrack/coordinator/students.php"><span class="nav-icon">♙</span>Students</a>
            <a class="<?= $current_page === 'companies.php' ? 'active' : '' ?>" href="/interntrack/coordinator/companies.php"><span class="nav-icon">▦</span>Companies</a>
            <a class="<?= $current_page === 'assignments.php' ? 'active' : '' ?>" href="/interntrack/coordinator/assignments.php"><span class="nav-icon">⇄</span>Assignments</a>
            <a class="<?= $current_page === 'attendance.php' ? 'active' : '' ?>" href="/interntrack/coordinator/attendance.php"><span class="nav-icon">◷</span>Attendance</a>
            <a class="<?= $current_page === 'requirements.php' ? 'active' : '' ?>" href="/interntrack/coordinator/requirements.php"><span class="nav-icon">▤</span>Requirements</a>
            <a class="<?= $current_page === 'reports.php' ? 'active' : '' ?>" href="/interntrack/coordinator/reports.php"><span class="nav-icon">▧</span>Reports</a>
        <?php elseif ($_SESSION['role'] === 'supervisor'): ?>
            <a class="<?= $current_page === 'my-interns.php' ? 'active' : '' ?>" href="/interntrack/supervisor/my-interns.php"><span class="nav-icon">♙</span>My Interns</a>
            <a class="<?= $current_page === 'attendance.php' ? 'active' : '' ?>" href="/interntrack/supervisor/attendance.php"><span class="nav-icon">◷</span>Attendance</a>
            <a class="<?= $current_page === 'reports.php' ? 'active' : '' ?>" href="/interntrack/supervisor/reports.php"><span class="nav-icon">▧</span>Intern Reports</a>
            <a class="<?= $current_page === 'evaluations.php' ? 'active' : '' ?>" href="/interntrack/supervisor/evaluations.php"><span class="nav-icon">☆</span>Evaluations</a>
        <?php else: ?>
            <a class="<?= $current_page === 'users.php' ? 'active' : '' ?>" href="/interntrack/admin/users.php"><span class="nav-icon">♙</span>User Management</a>
            <a class="<?= $current_page === 'settings.php' ? 'active' : '' ?>" href="/interntrack/admin/settings.php"><span class="nav-icon">⚙</span>Settings and Security</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span class="user-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))) ?></span>
            <span class="sidebar-user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?><small><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></small></span>
        </div>
        <a class="logout-link" href="/interntrack/logout.php"><span class="nav-icon">↪</span>Sign out</a>
    </div>
</aside>
<main class="content">
    <header class="page-header">
        <div>
            <span class="page-eyebrow"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?> workspace</span>
            <h1><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
        </div>
        <div class="page-header-meta">
            <span class="header-date"><?= htmlspecialchars(format_system_date(date('Y-m-d'), $system_settings)) ?></span>
            <span class="header-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))) ?></span>
        </div>
    </header>

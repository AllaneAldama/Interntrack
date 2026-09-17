<?php
require_once __DIR__ . '/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title ?? 'InternTrack') ?></title>
<link rel="stylesheet" href="/interntrack/assets/css/style.css">
</head>
<body>
<div class="app">
<aside class="sidebar">
    <div class="brand">Intern<span>Track</span></div>
    <p class="role"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></p>
    <nav>
        <a href="/interntrack/dashboard.php">Dashboard</a>
        <?php if ($_SESSION['role'] === 'student'): ?>
            <a href="/interntrack/student/profile.php">Internship Profile</a>
            <a href="/interntrack/student/requirements.php">Requirements</a>
            <a href="/interntrack/student/attendance.php">Attendance</a>
            <a href="/interntrack/student/reports.php">Accomplishment Reports</a>
        <?php elseif ($_SESSION['role'] === 'coordinator'): ?>
            <a href="#">Students</a><a href="#">Companies</a><a href="#">Assignments</a><a href="#">Reports</a>
        <?php elseif ($_SESSION['role'] === 'supervisor'): ?>
            <a href="#">My Interns</a><a href="#">Attendance</a><a href="#">Reports</a><a href="#">Evaluations</a>
        <?php else: ?>
            <a href="#">Users</a><a href="#">System Reports</a><a href="#">Settings</a>
        <?php endif; ?>
        <a href="/interntrack/logout.php">Logout</a>
    </nav>
</aside>
<main class="content">
<header class="topbar">
    <div>
        <h1><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
        <p>Internship/OJT Monitoring and Management System</p>
    </div>
    <div class="user-pill"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
</header>

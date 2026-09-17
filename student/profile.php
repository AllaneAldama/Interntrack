<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['student']);
$page_title = 'Internship Profile';
require_once __DIR__ . '/../includes/header.php';

$stmt = $conn->prepare("SELECT u.full_name, u.student_number, u.email, c.company_name,
    i.start_date, i.end_date, i.required_hours, i.status
    FROM users u LEFT JOIN internships i ON i.student_id=u.user_id
    LEFT JOIN companies c ON c.company_id=i.company_id
    WHERE u.user_id=? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
?>

<section class="panel">
<h2>Student Information</h2>
<div class="form-grid">
    <div><label>Full Name</label><input value="<?= htmlspecialchars($data['full_name'] ?? '') ?>" readonly></div>
    <div><label>Student Number</label><input value="<?= htmlspecialchars($data['student_number'] ?? '') ?>" readonly></div>
    <div><label>Email</label><input value="<?= htmlspecialchars($data['email'] ?? '') ?>" readonly></div>
    <div><label>Company</label><input value="<?= htmlspecialchars($data['company_name'] ?? 'Not assigned') ?>" readonly></div>
    <div><label>Start Date</label><input value="<?= htmlspecialchars($data['start_date'] ?? '') ?>" readonly></div>
    <div><label>End Date</label><input value="<?= htmlspecialchars($data['end_date'] ?? '') ?>" readonly></div>
</div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

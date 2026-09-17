<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$role = $_SESSION['role'];

$stats = [
    ['label' => 'Internship Status', 'value' => 'Ongoing', 'icon' => '●'],
    ['label' => 'Requirements', 'value' => '0 / 4', 'icon' => '✓'],
    ['label' => 'Rendered Hours', 'value' => '0 hrs', 'icon' => '◷'],
    ['label' => 'Reports', 'value' => '0', 'icon' => '▤']
];

if ($role === 'student') {
    $stmt = $conn->prepare("SELECT i.required_hours, i.status, c.company_name,
        COALESCE(SUM(a.hours_rendered),0) AS rendered
        FROM internships i
        JOIN companies c ON c.company_id=i.company_id
        LEFT JOIN attendance a ON a.internship_id=i.internship_id
        WHERE i.student_id=? GROUP BY i.internship_id LIMIT 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $internship = $stmt->get_result()->fetch_assoc();

    if ($internship) {
        $stats[0]['value'] = ucfirst($internship['status']);
        $stats[1]['value'] = 'View';
        $stats[2]['value'] = number_format((float)$internship['rendered'], 2) . ' hrs';
        $stats[3]['value'] = 'View';
    }
}
?>

<div class="cards">
<?php foreach ($stats as $stat): ?>
    <div class="stat-card">
        <div class="stat-icon"><?= $stat['icon'] ?></div>
        <div>
            <span><?= htmlspecialchars($stat['label']) ?></span>
            <strong><?= htmlspecialchars($stat['value']) ?></strong>
        </div>
    </div>
<?php endforeach; ?>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Welcome to InternTrack</h2>
            <p>Manage and monitor internship activities from one centralized platform.</p>
        </div>
    </div>

    <?php if ($role === 'student' && !empty($internship)): ?>
        <div class="info-grid">
            <div><small>Assigned Company</small><strong><?= htmlspecialchars($internship['company_name']) ?></strong></div>
            <div><small>Required Hours</small><strong><?= (int)$internship['required_hours'] ?> hrs</strong></div>
            <div><small>Rendered Hours</small><strong><?= number_format((float)$internship['rendered'],2) ?> hrs</strong></div>
            <div><small>Status</small><strong><?= htmlspecialchars(ucfirst($internship['status'])) ?></strong></div>
        </div>
    <?php else: ?>
        <div class="empty-state">Dashboard module ready for this role.</div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

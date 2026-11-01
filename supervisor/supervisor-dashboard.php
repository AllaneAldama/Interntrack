<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['supervisor']);

$page_title    = 'Supervisor Dashboard';
$supervisor_id = (int) ($_SESSION['user_id'] ?? 0);

function has_column(mysqli $conn, string $sql): bool {
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function fetch_count(mysqli $conn, string $sql): int {
    $res = $conn->query($sql);
    return $res ? (int) ($res->fetch_assoc()['total'] ?? 0) : 0;
}

function fetch_all_rows(mysqli $conn, string $sql): array {
    $res = $conn->query($sql);
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

$has_supervisor_id        = has_column($conn, "SHOW COLUMNS FROM internships LIKE 'supervisor_id'");
$has_report_internship_id = has_column($conn, "SHOW COLUMNS FROM accomplishment_reports LIKE 'internship_id'");

$total_interns      = 0;
$ongoing_interns    = 0;
$completed_interns  = 0;
$reports_for_review = 0;
$internship_statuses = ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
$recent_interns     = [];

if ($has_supervisor_id && $supervisor_id > 0) {
    $total_interns     = fetch_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE supervisor_id = {$supervisor_id}");
    $ongoing_interns   = fetch_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE supervisor_id = {$supervisor_id} AND status = 'ongoing'");
    $completed_interns = fetch_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE supervisor_id = {$supervisor_id} AND status = 'completed'");

    if ($has_report_internship_id) {
        $reports_for_review = fetch_count($conn,
            "SELECT COUNT(*) AS total
             FROM accomplishment_reports ar
             JOIN internships i ON i.internship_id = ar.internship_id
             WHERE i.supervisor_id = {$supervisor_id} AND ar.status = 'submitted'"
        );
    }

    foreach (fetch_all_rows($conn, "SELECT status, COUNT(*) AS total FROM internships WHERE supervisor_id = {$supervisor_id} GROUP BY status") as $row) {
        if (array_key_exists($row['status'], $internship_statuses)) {
            $internship_statuses[$row['status']] = (int) $row['total'];
        }
    }

    $recent_interns = fetch_all_rows($conn,
        "SELECT i.internship_id, i.status, i.start_date, i.end_date, i.created_at,
                u.full_name AS student_name, u.email AS student_email, c.company_name
         FROM internships i
         JOIN users u ON u.user_id = i.student_id
         JOIN companies c ON c.company_id = i.company_id
         WHERE i.supervisor_id = {$supervisor_id}
         ORDER BY i.created_at DESC
         LIMIT 8"
    );
}

$stats = [
    ['label' => 'Assigned Interns', 'value' => $total_interns, 'icon' => 'I'],
    ['label' => 'Ongoing Interns', 'value' => $ongoing_interns, 'icon' => 'O'],
    ['label' => 'Completed Interns', 'value' => $completed_interns, 'icon' => 'C'],
    ['label' => 'Reports For Review', 'value' => $reports_for_review, 'icon' => 'R']
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="cards">
<?php foreach ($stats as $stat): ?>
    <div class="stat-card">
        <div class="stat-icon"><?= $stat['icon'] ?></div>
        <div>
            <span><?= htmlspecialchars($stat['label']) ?></span>
            <strong><?= $stat['value'] ?></strong>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if (!$has_supervisor_id): ?>
<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Supervisor Assignment Setup</h2>
            <p>InternTrack is ready for the supervisor dashboard, but internship records are not yet linked to supervisors in the current database structure.</p>
        </div>
    </div>

    <div class="info-grid">
        <div><small>Supervisor Account</small><strong>Ready</strong></div>
        <div><small>Internship Assignment</small><strong>Not Linked Yet</strong></div>
        <div><small>Assigned Interns</small><strong>0</strong></div>
        <div><small>Reports For Review</small><strong>0</strong></div>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Internship Overview</h2>
            <p>Current internship status of your assigned interns.</p>
        </div>
    </div>

    <div class="info-grid">
        <div><small>Pending</small><strong><?= $internship_statuses['pending'] ?></strong></div>
        <div><small>Ongoing</small><strong><?= $internship_statuses['ongoing'] ?></strong></div>
        <div><small>Completed</small><strong><?= $internship_statuses['completed'] ?></strong></div>
        <div><small>Cancelled</small><strong><?= $internship_statuses['cancelled'] ?></strong></div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent Assigned Interns</h2>
            <p>Latest internship assignments associated with your account.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Student</th><th>Email</th><th>Company</th><th>Status</th><th>Start</th><th>End</th></tr>
            </thead>
            <tbody>
            <?php if ($recent_interns): foreach ($recent_interns as $intern): ?>
                <tr>
                    <td><?= htmlspecialchars($intern['student_name']) ?></td>
                    <td><?= htmlspecialchars($intern['student_email']) ?></td>
                    <td><?= htmlspecialchars($intern['company_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($intern['status']) ?>">
                            <?= ucfirst(htmlspecialchars($intern['status'])) ?>
                        </span>
                    </td>
                    <td><?= !empty($intern['start_date']) ? htmlspecialchars($intern['start_date']) : '—' ?></td>
                    <td><?= !empty($intern['end_date']) ? htmlspecialchars($intern['end_date']) : '—' ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align: center;">No assigned interns found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
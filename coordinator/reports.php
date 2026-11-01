<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['coordinator']);

$page_title = 'Reports';

$get_count = fn(string $sql) => (int) ($conn->query($sql)?->fetch_assoc()['total'] ?? 0);
$get_all   = fn(string $sql) => ($res = $conn->query($sql)) ? $res->fetch_all(MYSQLI_ASSOC) : [];

$total_students        = $get_count("SELECT COUNT(*) AS total FROM users WHERE role = 'student'");
$total_companies       = $get_count("SELECT COUNT(*) AS total FROM companies");
$total_internships     = $get_count("SELECT COUNT(*) AS total FROM internships");
$ongoing_internships   = $get_count("SELECT COUNT(*) AS total FROM internships WHERE status = 'ongoing'");
$completed_internships = $get_count("SELECT COUNT(*) AS total FROM internships WHERE status = 'completed'");
$pending_internships   = $get_count("SELECT COUNT(*) AS total FROM internships WHERE status = 'pending'");
$pending_requirements  = $get_count("SELECT COUNT(*) AS total FROM requirements WHERE status = 'pending'");
$pending_reports       = $get_count("SELECT COUNT(*) AS total FROM accomplishment_reports WHERE status = 'pending'");

$stats = [
    ['label' => 'Total Students', 'value' => $total_students, 'icon' => 'S'],
    ['label' => 'Partner Companies', 'value' => $total_companies, 'icon' => 'C'],
    ['label' => 'Total Internships', 'value' => $total_internships, 'icon' => 'I'],
    ['label' => 'Ongoing Internships', 'value' => $ongoing_internships, 'icon' => 'O'],
    ['label' => 'Completed Internships', 'value' => $completed_internships, 'icon' => 'C'],
    ['label' => 'Pending Internships', 'value' => $pending_internships, 'icon' => 'P'],
    ['label' => 'Pending Requirements', 'value' => $pending_requirements, 'icon' => 'R'],
    ['label' => 'Pending Reports', 'value' => $pending_reports, 'icon' => 'A']
];

$status_summary     = $get_all("SELECT status, COUNT(*) AS total FROM internships GROUP BY status ORDER BY total DESC");
$recent_internships = $get_all("
    SELECT i.internship_id, i.status, i.start_date, i.end_date, i.created_at,
           u.full_name AS student_name, c.company_name
    FROM internships i
    JOIN users u ON u.user_id = i.student_id
    JOIN companies c ON c.company_id = i.company_id
    ORDER BY i.created_at DESC
    LIMIT 10
");

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

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Internship Status Summary</h2>
            <p>Current distribution of internship records.</p>
        </div>
    </div>

    <div class="info-grid">
    <?php if ($status_summary): foreach ($status_summary as $row): ?>
        <div class="info-item">
            <span class="info-label"><?= ucfirst(htmlspecialchars($row['status'])) ?></span>
            <span class="info-value"><?= $row['total'] ?></span>
        </div>
    <?php endforeach; else: ?>
        <div class="info-item">
            <span class="info-label">No internship records</span>
            <span class="info-value">0</span>
        </div>
    <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent Internship Records</h2>
            <p>Latest internship assignments recorded in the system.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recent_internships): foreach ($recent_internships as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['student_name']) ?></td>
                    <td><?= htmlspecialchars($row['company_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($row['status']) ?>">
                            <?= ucfirst(htmlspecialchars($row['status'])) ?>
                        </span>
                    </td>
                    <td><?= !empty($row['start_date']) ? htmlspecialchars($row['start_date']) : '—' ?></td>
                    <td><?= !empty($row['end_date']) ? htmlspecialchars($row['end_date']) : '—' ?></td>
                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No internship records found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
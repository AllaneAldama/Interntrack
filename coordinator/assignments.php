<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['coordinator']);

$page_title = 'Assignments';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$allowed_statuses = ['pending', 'ongoing', 'completed', 'cancelled'];

$sql = "
    SELECT i.internship_id, i.status, i.start_date, i.end_date, i.created_at,
           u.full_name AS student_name, u.email AS student_email, c.company_name
    FROM internships i
    JOIN users u ON u.user_id = i.student_id
    JOIN companies c ON c.company_id = i.company_id
    WHERE 1=1
";

$params = [];
$types  = '';

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR c.company_name LIKE ?)";
    $search_value = '%' . $search . '%';
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= 'sss';
}

if (in_array($status, $allowed_statuses, true)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY i.created_at DESC";

$assignments = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function assignment_count(mysqli $conn, string $condition = ''): int {
    $sql = "SELECT COUNT(*) FROM internships" . ($condition !== '' ? " WHERE " . $condition : '');
    $result = $conn->query($sql);
    return $result ? (int) ($result->fetch_row()[0] ?? 0) : 0;
}

$total_assignments     = assignment_count($conn);
$pending_assignments   = assignment_count($conn, "status = 'pending'");
$ongoing_assignments   = assignment_count($conn, "status = 'ongoing'");
$completed_assignments = assignment_count($conn, "status = 'completed'");

$stats = [
    ['label' => 'Total Assignments', 'value' => $total_assignments, 'icon' => 'A'],
    ['label' => 'Pending', 'value' => $pending_assignments, 'icon' => 'P'],
    ['label' => 'Ongoing', 'value' => $ongoing_assignments, 'icon' => 'O'],
    ['label' => 'Completed', 'value' => $completed_assignments, 'icon' => 'C']
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

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>Internship Assignments</h2>
            <p>View and monitor student-to-company internship assignments.</p>
            <div class="assignment-count"><?= count($assignments) ?> assignment(s) currently shown</div>
        </div>
        <div class="assignment-badge">Coordinator View</div>
    </div>

    <form method="GET" class="filter-form">
        <input type="text" name="search" placeholder="Search student or company..." value="<?= htmlspecialchars($search) ?>">

        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach ($allowed_statuses as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filter</button>
        <a href="/interntrack/coordinator/assignments.php" class="clear-btn">Clear</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Student</th><th>Company</th><th>Status</th><th>Start</th><th>End</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php if ($assignments): foreach ($assignments as $assignment): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($assignment['student_name']) ?></strong>
                        <div style="font-size:12px;color:#748099;margin-top:3px;"><?= htmlspecialchars($assignment['student_email']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($assignment['company_name']) ?></td>
                    <td>
                        <span class="badge <?= $assignment['status'] === 'completed' ? 'approved' : ($assignment['status'] === 'cancelled' ? 'rejected' : '') ?>">
                            <?= htmlspecialchars(ucfirst($assignment['status'])) ?>
                        </span>
                    </td>
                    <td><?= $assignment['start_date'] ? htmlspecialchars(date('M d, Y', strtotime($assignment['start_date']))) : '—' ?></td>
                    <td><?= $assignment['end_date'] ? htmlspecialchars(date('M d, Y', strtotime($assignment['end_date']))) : '—' ?></td>
                    <td><?= htmlspecialchars(date('M d, Y', strtotime($assignment['created_at']))) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" class="empty-state">No internship assignments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.filter-form { display: grid; grid-template-columns: 1.5fr 1fr auto auto; gap: 10px; margin-bottom: 20px; }
.filter-form input, .filter-form select { width: 100%; padding: 11px 13px; border: 1px solid #dce2eb; border-radius: 8px; background: #fff; font-size: 14px; color: #374151; box-sizing: border-box; }
.filter-form button, .clear-btn { border: none; border-radius: 8px; padding: 11px 16px; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; }
.filter-form button { background: #3677ed; color: #fff; }
.clear-btn { background: #eef1f6; color: #49566d; }
.assignment-count { color: #748099; font-size: 13px; margin-top: 4px; }
.assignment-badge { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #eef4ff; color: #3677ed; }
@media (max-width: 800px) { .filter-form { grid-template-columns: 1fr; } }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['supervisor']);

$page_title    = 'My Interns';
$supervisor_id = (int) ($_SESSION['user_id'] ?? 0);

$has_supervisor_id = ($conn->query("SHOW COLUMNS FROM internships LIKE 'supervisor_id'")?->num_rows ?? 0) > 0;

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$total_interns     = 0;
$ongoing_interns   = 0;
$completed_interns = 0;
$pending_interns   = 0;
$interns           = [];

if ($has_supervisor_id && $supervisor_id > 0) {
    $summary_res = $conn->query("
        SELECT
            COUNT(*) AS total_interns,
            SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_interns,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_interns,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_interns
        FROM internships
        WHERE supervisor_id = {$supervisor_id}
    ");

    if ($summary_res && $summary = $summary_res->fetch_assoc()) {
        $total_interns     = (int) ($summary['total_interns'] ?? 0);
        $ongoing_interns   = (int) ($summary['ongoing_interns'] ?? 0);
        $completed_interns = (int) ($summary['completed_interns'] ?? 0);
        $pending_interns   = (int) ($summary['pending_interns'] ?? 0);
    }

    $where = ["i.supervisor_id = {$supervisor_id}"];

    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(u.full_name LIKE '%{$s}%' OR u.email LIKE '%{$s}%' OR c.company_name LIKE '%{$s}%')";
    }

    if ($status !== '' && in_array($status, ['pending', 'ongoing', 'completed', 'cancelled'], true)) {
        $st = $conn->real_escape_string($status);
        $where[] = "i.status = '{$st}'";
    }

    $where_sql = implode(' AND ', $where);

    $result = $conn->query("
        SELECT i.internship_id, i.status, i.start_date, i.end_date, i.created_at,
               u.full_name AS student_name, u.email AS student_email, c.company_name
        FROM internships i
        JOIN users u ON u.user_id = i.student_id
        JOIN companies c ON c.company_id = i.company_id
        WHERE {$where_sql}
        ORDER BY i.created_at DESC
    ");

    if ($result) {
        $interns = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$stats = [
    ['label' => 'Assigned Interns', 'value' => $total_interns, 'icon' => 'I'],
    ['label' => 'Ongoing', 'value' => $ongoing_interns, 'icon' => 'O'],
    ['label' => 'Completed', 'value' => $completed_interns, 'icon' => 'C'],
    ['label' => 'Pending', 'value' => $pending_interns, 'icon' => 'P']
];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$has_supervisor_id): ?>
<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Supervisor Assignment Setup</h2>
            <p>Internship records are not yet linked to supervisors in the current database structure.</p>
        </div>
    </div>

    <div class="info-grid">
        <div><small>Supervisor Account</small><strong>Ready</strong></div>
        <div><small>Assignment Relationship</small><strong>Not Set</strong></div>
        <div><small>Assigned Interns</small><strong>0</strong></div>
        <div><small>Page Status</small><strong>Waiting for Assignment</strong></div>
    </div>
</div>
<?php else: ?>

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
            <h2>My Interns</h2>
            <p>View and monitor interns assigned to you.</p>
        </div>
    </div>

    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by student or company...">
        </div>

        <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="ongoing" <?= $status === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="filter-submit">Filter</button>
            <a href="my-interns.php" class="filter-reset">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($interns): foreach ($interns as $intern): ?>
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
                <tr>
                    <td colspan="6" style="text-align: center;">No interns found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.filter-form { display: grid; grid-template-columns: minmax(220px, 1fr) 220px auto; gap: 16px; align-items: end; }
.filter-form .filter-group { display: flex; flex-direction: column; gap: 7px; }
.filter-form .filter-group label { font-size: 13px; font-weight: 600; }
.filter-form .filter-group input, .filter-form .filter-group select { width: 100%; min-height: 42px; padding: 10px 12px; border: 1px solid #d9dee7; border-radius: 8px; background: #fff; font-size: 14px; box-sizing: border-box; }
.filter-form .filter-group input:focus, .filter-form .filter-group select:focus { outline: none; border-color: #7a5cff; box-shadow: 0 0 0 3px rgba(122, 92, 255, 0.10); }
.filter-form .filter-actions { display: flex; gap: 8px; }
.filter-form .filter-actions button, .filter-form .filter-actions a { min-height: 42px; padding: 10px 18px; border-radius: 8px; border: 1px solid transparent; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
.filter-form .filter-actions button { background: #007bff; color: #fff; }
.filter-form .filter-actions button:hover { background: #0069d9; }
.filter-form .filter-actions a { background: #f4f5f7; color: #333; border-color: #dfe2e7; }
.filter-form .filter-actions a:hover { background: #e9ebef; }
@media (max-width: 900px) {
    .filter-form { grid-template-columns: 1fr; }
    .filter-form .filter-actions { justify-content: flex-start; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
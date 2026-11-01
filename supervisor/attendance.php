<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['supervisor']);

$page_title    = 'Attendance';
$supervisor_id = (int) ($_SESSION['user_id'] ?? 0);

$attendance_table_exists = false;
$attendance_columns      = [];

$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($table_check && $table_check->num_rows > 0) {
    $attendance_table_exists = true;
    $col_res = $conn->query("SHOW COLUMNS FROM attendance");
    if ($col_res) {
        while ($col = $col_res->fetch_assoc()) {
            $attendance_columns[] = $col['Field'];
        }
    }
}

$has_supervisor_id = ($conn->query("SHOW COLUMNS FROM internships LIKE 'supervisor_id'")?->num_rows ?? 0) > 0;

function find_column(array $columns, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $columns, true)) return $c;
    }
    return null;
}

$student_column    = find_column($attendance_columns, ['student_id']);
$internship_column = find_column($attendance_columns, ['internship_id']);
$date_column       = find_column($attendance_columns, ['attendance_date', 'date']);
$time_in_column    = find_column($attendance_columns, ['time_in', 'check_in']);
$time_out_column   = find_column($attendance_columns, ['time_out', 'check_out']);
$status_column     = find_column($attendance_columns, ['status']);
$hours_column      = find_column($attendance_columns, ['hours_rendered', 'rendered_hours', 'hours']);

$attendance_ready = $attendance_table_exists && $has_supervisor_id && ($internship_column !== null || $student_column !== null);

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$date   = trim($_GET['date'] ?? '');

$total_records   = 0;
$present_records = 0;
$absent_records  = 0;
$total_hours     = 0;
$attendance_records = [];

if ($attendance_ready) {
    $attendance_link = $internship_column !== null
        ? "JOIN internships i ON i.internship_id = a.`{$internship_column}`"
        : "JOIN internships i ON i.student_id = a.`{$student_column}` AND i.supervisor_id = {$supervisor_id} AND i.status IN ('pending', 'ongoing', 'completed')";

    $get_count = fn($sql) => (int) ($conn->query($sql)?->fetch_assoc()['total'] ?? 0);
    $get_sum   = fn($sql) => (float) ($conn->query($sql)?->fetch_assoc()['total_hours'] ?? 0);

    $total_records   = $get_count("SELECT COUNT(*) AS total FROM attendance a {$attendance_link} WHERE i.supervisor_id = {$supervisor_id}");

    if ($status_column !== null) {
        $present_records = $get_count("SELECT COUNT(*) AS total FROM attendance a {$attendance_link} WHERE i.supervisor_id = {$supervisor_id} AND LOWER(a.`{$status_column}`) = 'present'");
        $absent_records  = $get_count("SELECT COUNT(*) AS total FROM attendance a {$attendance_link} WHERE i.supervisor_id = {$supervisor_id} AND LOWER(a.`{$status_column}`) = 'absent'");
    }

    if ($hours_column !== null) {
        $total_hours = $get_sum("SELECT COALESCE(SUM(a.`{$hours_column}`), 0) AS total_hours FROM attendance a {$attendance_link} WHERE i.supervisor_id = {$supervisor_id}");
    }

    $conditions = ["i.supervisor_id = {$supervisor_id}"];
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $conditions[] = "(u.full_name LIKE '%{$s}%' OR u.email LIKE '%{$s}%' OR c.company_name LIKE '%{$s}%')";
    }
    if ($status !== '' && $status_column !== null) {
        $st = $conn->real_escape_string($status);
        $conditions[] = "LOWER(a.`{$status_column}`) = LOWER('{$st}')";
    }
    if ($date !== '' && $date_column !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $d = $conn->real_escape_string($date);
        $conditions[] = "DATE(a.`{$date_column}`) = '{$d}'";
    }

    $where_sql = implode(' AND ', $conditions);

    $sel_date  = $date_column !== null ? "a.`{$date_column}` AS attendance_date" : "NULL AS attendance_date";
    $sel_in    = $time_in_column !== null ? "a.`{$time_in_column}` AS time_in" : "NULL AS time_in";
    $sel_out   = $time_out_column !== null ? "a.`{$time_out_column}` AS time_out" : "NULL AS time_out";
    $sel_stat  = $status_column !== null ? "a.`{$status_column}` AS attendance_status" : "'Recorded' AS attendance_status";
    $sel_hours = $hours_column !== null ? "a.`{$hours_column}` AS hours_rendered" : "NULL AS hours_rendered";

    $records_res = $conn->query("
        SELECT u.full_name AS student_name, u.email AS student_email, c.company_name,
               {$sel_date}, {$sel_in}, {$sel_out}, {$sel_stat}, {$sel_hours}
        FROM attendance a
        {$attendance_link}
        JOIN users u ON u.user_id = i.student_id
        JOIN companies c ON c.company_id = i.company_id
        WHERE {$where_sql}
        ORDER BY attendance_date DESC
    ");

    if ($records_res) {
        while ($row = $records_res->fetch_assoc()) {
            $attendance_records[] = $row;
        }
    }
}

$stats = [
    ['label' => 'Attendance Records', 'value' => $total_records, 'icon' => 'A'],
    ['label' => 'Present', 'value' => $present_records, 'icon' => 'P'],
    ['label' => 'Absent', 'value' => $absent_records, 'icon' => 'X'],
    ['label' => 'Hours Rendered', 'value' => $total_hours, 'icon' => 'H']
];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$attendance_ready): ?>
<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Attendance Setup</h2>
            <p>The attendance page is ready, but the current database does not yet expose all of the relationships needed to display supervisor attendance records.</p>
        </div>
    </div>
    <div class="info-grid">
        <div><small>Attendance Table</small><strong><?= $attendance_table_exists ? 'Found' : 'Not Found' ?></strong></div>
        <div><small>Supervisor Assignment</small><strong><?= $has_supervisor_id ? 'Available' : 'Not Set' ?></strong></div>
        <div><small>Attendance Link</small><strong><?= ($internship_column !== null || $student_column !== null) ? 'Available' : 'Not Found' ?></strong></div>
        <div><small>Records</small><strong>0</strong></div>
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
            <h2>Attendance Monitoring</h2>
            <p>Review attendance records and rendered hours for your assigned interns.</p>
        </div>
    </div>

    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by student or company...">
        </div>

        <?php if ($status_column !== null): ?>
        <div class="filter-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All Statuses</option>
                <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>Present</option>
                <option value="late" <?= $status === 'late' ? 'selected' : '' ?>>Late</option>
                <option value="absent" <?= $status === 'absent' ? 'selected' : '' ?>>Absent</option>
                <option value="leave" <?= $status === 'leave' ? 'selected' : '' ?>>Leave</option>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($date_column !== null): ?>
        <div class="filter-group">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>">
        </div>
        <?php endif; ?>

        <div class="filter-actions">
            <button type="submit" class="filter-submit">Filter</button>
            <a href="attendance.php" class="filter-reset">Reset</a>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Student</th><th>Company</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if ($attendance_records): foreach ($attendance_records as $record): ?>
                <tr>
                    <td><?= htmlspecialchars($record['student_name']) ?></td>
                    <td><?= htmlspecialchars($record['company_name']) ?></td>
                    <td><?= !empty($record['attendance_date']) ? htmlspecialchars($record['attendance_date']) : '—' ?></td>
                    <td><?= !empty($record['time_in']) ? htmlspecialchars($record['time_in']) : '—' ?></td>
                    <td><?= !empty($record['time_out']) ? htmlspecialchars($record['time_out']) : '—' ?></td>
                    <td><?= $record['hours_rendered'] !== null ? htmlspecialchars($record['hours_rendered']) : '—' ?></td>
                    <td><span class="badge"><?= ucfirst(htmlspecialchars($record['attendance_status'])) ?></span></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="empty-state">No attendance records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<style>
.filter-form { display: grid; grid-template-columns: minmax(220px, 1fr) 220px 220px auto; gap: 16px; align-items: end; margin-bottom: 20px; }
.filter-group { display: flex; flex-direction: column; gap: 7px; }
.filter-group label { font-size: 13px; font-weight: 600; }
.filter-group input, .filter-group select { width: 100%; min-height: 42px; padding: 10px 12px; border: 1px solid #d9dee7; border-radius: 8px; background: #fff; color: #333; font-size: 14px; box-sizing: border-box; }
.filter-group input:focus, .filter-group select:focus { outline: none; border-color: #7a5cff; box-shadow: 0 0 0 3px rgba(122, 92, 255, 0.10); }
.filter-actions { display: flex; gap: 8px; }
.filter-submit, .filter-reset { min-height: 42px; padding: 10px 18px; border-radius: 8px; border: 1px solid transparent; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-sizing: border-box; }
.filter-submit { background: #007bff; color: #fff; }
.filter-submit:hover { background: #0069d9; }
.filter-reset { background: #f4f5f7; color: #333; border-color: #dfe2e7; }
.filter-reset:hover { background: #e9ebef; }
@media (max-width: 1000px) {
    .filter-form { grid-template-columns: minmax(220px, 1fr) 220px; }
    .filter-actions { grid-column: 1 / -1; }
}
@media (max-width: 700px) {
    .filter-form { grid-template-columns: 1fr; }
    .filter-actions { grid-column: auto; justify-content: flex-start; }
}
.empty-state { padding: 35px 20px; text-align: center; color: #777; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
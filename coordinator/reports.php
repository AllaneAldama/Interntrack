<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['coordinator']);

$page_title = 'Progress Reports';
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$status_filter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$company_filter = is_string($_GET['company_id'] ?? null) ? $_GET['company_id'] : '';
$allowed_statuses = ['pending', 'ongoing', 'completed', 'cancelled'];
$company_id = $company_filter === ''
    ? null
    : filter_var($company_filter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$companies_result = $conn->query('SELECT company_id, company_name FROM companies ORDER BY company_name');
$companies = $companies_result->fetch_all(MYSQLI_ASSOC);

$sql = "
    SELECT
        i.internship_id,
        i.status,
        i.start_date,
        i.end_date,
        i.required_hours,
        u.full_name AS student_name,
        u.student_number,
        u.email AS student_email,
        c.company_name,
        s.full_name AS supervisor_name,
        COALESCE(a.attendance_days, 0) AS attendance_days,
        COALESCE(a.rendered_hours, 0) AS rendered_hours,
        COALESCE(r.requirement_total, 0) AS requirement_total,
        COALESCE(r.requirement_approved, 0) AS requirement_approved,
        COALESCE(r.requirement_review, 0) AS requirement_review,
        COALESCE(w.weekly_report_total, 0) AS weekly_report_total,
        COALESCE(w.weekly_report_reviewed, 0) AS weekly_report_reviewed
    FROM internships i
    JOIN users u ON u.user_id = i.student_id
    JOIN companies c ON c.company_id = i.company_id
    LEFT JOIN users s ON s.user_id = i.supervisor_id
    LEFT JOIN (
        SELECT internship_id, COUNT(*) AS attendance_days,
            SUM(hours_rendered) AS rendered_hours
        FROM attendance
        GROUP BY internship_id
    ) a ON a.internship_id = i.internship_id
    LEFT JOIN (
        SELECT internship_id, COUNT(*) AS requirement_total,
            SUM(status = 'approved') AS requirement_approved,
            SUM(status = 'submitted') AS requirement_review
        FROM requirements
        GROUP BY internship_id
    ) r ON r.internship_id = i.internship_id
    LEFT JOIN (
        SELECT internship_id, COUNT(*) AS weekly_report_total,
            SUM(status = 'reviewed') AS weekly_report_reviewed
        FROM accomplishment_reports
        GROUP BY internship_id
    ) w ON w.internship_id = i.internship_id
    WHERE 1 = 1
";
$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.student_number LIKE ? OR c.company_name LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
    $types .= 'ssss';
}
if (in_array($status_filter, $allowed_statuses, true)) {
    $sql .= ' AND i.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}
if ($company_id !== null && $company_id !== false) {
    $sql .= ' AND c.company_id = ?';
    $params[] = $company_id;
    $types .= 'i';
}
$sql .= ' ORDER BY FIELD(i.status, "ongoing", "pending", "completed", "cancelled"), u.full_name';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
    throw new RuntimeException('Could not generate the internship progress report.');
}
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);

$summary = [
    'total' => count($records),
    'ongoing' => 0,
    'pending' => 0,
    'completed' => 0,
    'cancelled' => 0
];
foreach ($records as $record) {
    if (isset($summary[$record['status']])) {
        $summary[$record['status']]++;
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    log_activity($conn, (int) $_SESSION['user_id'], 'Exported internship progress report');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="interntrack-progress-report-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, private');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Could not create the CSV report.');
    }
    fputcsv($output, [
        'Student', 'Student Number', 'Email', 'Company', 'Supervisor',
        'Internship Status', 'Start Date', 'End Date', 'Required Hours',
        'Rendered Hours', 'Hours Progress (%)', 'Attendance Records',
        'Requirements Approved', 'Requirements Total', 'Requirements Pending Review',
        'Weekly Reports Reviewed', 'Weekly Reports Total'
    ]);
    foreach ($records as $record) {
        $required_hours = (float) $record['required_hours'];
        $rendered_hours = (float) $record['rendered_hours'];
        $progress = $required_hours > 0
            ? min(100, round($rendered_hours / $required_hours * 100, 1))
            : 0;
        fputcsv($output, [
            $record['student_name'],
            $record['student_number'],
            $record['student_email'],
            $record['company_name'],
            $record['supervisor_name'] ?? '',
            ucfirst($record['status']),
            $record['start_date'] ?? '',
            $record['end_date'] ?? '',
            number_format($required_hours, 2, '.', ''),
            number_format($rendered_hours, 2, '.', ''),
            $progress,
            $record['attendance_days'],
            $record['requirement_approved'],
            $record['requirement_total'],
            $record['requirement_review'],
            $record['weekly_report_reviewed'],
            $record['weekly_report_total']
        ]);
    }
    fclose($output);
    exit;
}

$export_query = array_filter([
    'search' => $search,
    'status' => in_array($status_filter, $allowed_statuses, true) ? $status_filter : '',
    'company_id' => $company_id !== null && $company_id !== false ? (string) $company_id : '',
    'export' => 'csv'
], static fn(string $value): bool => $value !== '');

require_once __DIR__ . '/../includes/header.php';
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Student Internship Progress</h2>
            <p>Review assignment completion, rendered hours, attendance, requirements, and accomplishment reports.</p>
        </div>
    </div>

    <div class="cards report-summary">
        <div class="stat-card"><div class="stat-icon">A</div><div><span>Filtered Assignments</span><strong><?= $summary['total'] ?></strong></div></div>
        <div class="stat-card"><div class="stat-icon">O</div><div><span>Ongoing</span><strong><?= $summary['ongoing'] ?></strong></div></div>
        <div class="stat-card"><div class="stat-icon">C</div><div><span>Completed</span><strong><?= $summary['completed'] ?></strong></div></div>
        <div class="stat-card"><div class="stat-icon">P</div><div><span>Pending</span><strong><?= $summary['pending'] ?></strong></div></div>
    </div>

    <form method="GET" class="report-filter">
        <label>Search student or company<input type="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, student number, email, company"></label>
        <label style="margin-left: 70px;">Internship status
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($allowed_statuses as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>" <?= $status_filter === $option ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Partner company
            <select name="company_id">
                <option value="">All companies</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['company_id'] ?>" <?= $company_id !== null && $company_id !== false && $company_id === (int) $company['company_id'] ? 'selected' : '' ?>><?= htmlspecialchars($company['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn primary" type="submit" style="margin-bottom: 8px;">Apply filters</button>
        <a class="btn secondary" href="reports.php" style="margin-bottom: 8px;">Clear</a>

    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student / Assignment</th>
                    <th>Company / Supervisor</th>
                    <th>Status</th>
                    <th>Hours Progress</th>
                    <th>Attendance</th>
                    <th>Requirements</th>
                    <th>Weekly Reports</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records): ?>
                    <?php foreach ($records as $record):
                        $required_hours = (float) $record['required_hours'];
                        $rendered_hours = (float) $record['rendered_hours'];
                        $progress = $required_hours > 0
                            ? min(100, round($rendered_hours / $required_hours * 100, 1))
                            : 0;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($record['student_name']) ?></strong>
                                <div class="muted"><?= htmlspecialchars($record['student_number'] ?: $record['student_email']) ?></div>
                                <small><?= $record['start_date'] ? htmlspecialchars(format_system_date($record['start_date'], $system_settings)) : 'Start date not set' ?> – <?= $record['end_date'] ? htmlspecialchars(format_system_date($record['end_date'], $system_settings)) : 'End date not set' ?></small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($record['company_name']) ?></strong>
                                <div class="muted">Supervisor: <?= htmlspecialchars($record['supervisor_name'] ?: 'Not assigned') ?></div>
                            </td>
                            <td><span class="badge <?= $record['status'] === 'completed' ? 'approved' : ($record['status'] === 'cancelled' ? 'rejected' : '') ?>"><?= htmlspecialchars(ucfirst($record['status'])) ?></span></td>
                            <td class="progress-cell">
                                <?= number_format($rendered_hours, 2) ?> / <?= number_format($required_hours, 2) ?> hrs
                                <div class="progress-track" role="progressbar" aria-label="Rendered hours progress" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= $progress ?>%"></span></div>
                                <small><?= $progress ?>%</small>
                            </td>
                            <td><?= (int) $record['attendance_days'] ?> record(s)</td>
                            <td><?= (int) $record['requirement_approved'] ?> / <?= (int) $record['requirement_total'] ?> approved
                                <?php if ((int) $record['requirement_review'] > 0): ?><div class="muted"><?= (int) $record['requirement_review'] ?> pending review</div><?php endif; ?>
                            </td>
                            <td><?= (int) $record['weekly_report_reviewed'] ?> / <?= (int) $record['weekly_report_total'] ?> reviewed</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-state">No internship assignments match the selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>
    <br>
    <a class="btn primary" href="reports.php?<?= htmlspecialchars(http_build_query($export_query)) ?>">Download CSV</a>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'Dashboard';
$period_options = [
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'semester' => 'This Semester',
    'academic_year' => 'This Academic Year'
];
$period = is_string($_GET['period'] ?? null) && isset($period_options[$_GET['period']])
    ? $_GET['period']
    : 'month';
$tabs = ['overview', 'analytics', 'system-logs'];
$active_tab = is_string($_GET['tab'] ?? null) && in_array($_GET['tab'], $tabs, true)
    ? $_GET['tab']
    : 'overview';

$today = new DateTimeImmutable('today');
$range_start = $today;
switch ($period) {
    case 'today':
        $range_end = $today->modify('+1 day');
        break;
    case 'week':
        $range_start = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
        $range_end = $range_start->modify('+7 days');
        break;
    case 'month':
        $range_start = $today->modify('first day of this month');
        $range_end = $range_start->modify('+1 month');
        break;
    case 'semester':
        $semester_start_month = (int) $today->format('n') <= 6 ? 1 : 7;
        $range_start = $today->setDate((int) $today->format('Y'), $semester_start_month, 1);
        $range_end = $range_start->modify('+6 months');
        break;
    case 'academic_year':
        $range_start = $today->setDate((int) $today->format('Y'), 1, 1);
        $range_end = $range_start->modify('+1 year');
        break;
}

$range_start_sql = $range_start->format('Y-m-d H:i:s');
$range_end_sql = $range_end->format('Y-m-d H:i:s');

$counts_result = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'active') AS active_users,
        (SELECT COUNT(*) FROM users WHERE status = 'inactive') AS inactive_users,
        (SELECT COUNT(*) FROM companies) AS total_companies,
        (SELECT COUNT(*) FROM system_error_logs) AS total_errors
");
if (!$counts_result) {
    throw new RuntimeException('Could not load system overview totals.');
}
$counts = $counts_result->fetch_assoc();

$user_role_totals = ['student' => 0, 'coordinator' => 0, 'supervisor' => 0, 'admin' => 0];
$user_role_result = $conn->query("
    SELECT role, COUNT(*) AS total
    FROM users
    GROUP BY role
");
if (!$user_role_result) {
    throw new RuntimeException('Could not load user role analytics.');
}
while ($row = $user_role_result->fetch_assoc()) {
    if (isset($user_role_totals[$row['role']])) {
        $user_role_totals[$row['role']] = (int) $row['total'];
    }
}
$max_role_users = max($user_role_totals) ?: 1;

$company_status_totals = ['active' => 0, 'inactive' => 0];
$company_status_result = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM companies
    GROUP BY status
");
if (!$company_status_result) {
    throw new RuntimeException('Could not load partner company status analytics.');
}
while ($row = $company_status_result->fetch_assoc()) {
    if (isset($company_status_totals[$row['status']])) {
        $company_status_totals[$row['status']] = (int) $row['total'];
    }
}
$max_company_statuses = max($company_status_totals) ?: 1;

$internship_statuses = [
    'ongoing' => 0,
    'pending' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$internship_stmt = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM internships
    WHERE created_at >= ? AND created_at < ?
    GROUP BY status
");
if (!$internship_stmt) {
    throw new RuntimeException('Could not prepare internship analytics.');
}
$internship_stmt->bind_param('ss', $range_start_sql, $range_end_sql);
if (!$internship_stmt->execute()) {
    throw new RuntimeException('Could not load internship analytics.');
}
$internship_result = $internship_stmt->get_result();
while ($row = $internship_result->fetch_assoc()) {
    if (isset($internship_statuses[$row['status']])) {
        $internship_statuses[$row['status']] = (int) $row['total'];
    }
}
$internship_total = array_sum($internship_statuses);
$completion_rate = $internship_total > 0
    ? round($internship_statuses['completed'] / $internship_total * 100, 1)
    : 0;

$trend_format = $period === 'today'
    ? '%Y-%m-%d %H:00:00'
    : (in_array($period, ['semester', 'academic_year'], true) ? '%Y-%m' : '%Y-%m-%d');
$trend_stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '{$trend_format}') AS bucket, COUNT(*) AS total
    FROM users
    WHERE role = 'student' AND created_at >= ? AND created_at < ?
    GROUP BY bucket
");
if (!$trend_stmt) {
    throw new RuntimeException('Could not prepare student enrollment analytics.');
}
$trend_stmt->bind_param('ss', $range_start_sql, $range_end_sql);
if (!$trend_stmt->execute()) {
    throw new RuntimeException('Could not load student enrollment analytics.');
}
$trend_result = $trend_stmt->get_result();
$enrollments_by_bucket = [];
while ($row = $trend_result->fetch_assoc()) {
    $enrollments_by_bucket[$row['bucket']] = (int) $row['total'];
}

$enrollment_buckets = [];
if ($period === 'today') {
    for ($hour = 0; $hour < 24; $hour++) {
        $bucket = $range_start->modify('+' . $hour . ' hours');
        $enrollment_buckets[] = [
            'key' => $bucket->format('Y-m-d H:00:00'),
            'label' => $bucket->format('H:i')
        ];
    }
} elseif (in_array($period, ['semester', 'academic_year'], true)) {
    for ($bucket = $range_start; $bucket < $range_end; $bucket = $bucket->modify('+1 month')) {
        $enrollment_buckets[] = [
            'key' => $bucket->format('Y-m'),
            'label' => $bucket->format('M')
        ];
    }
} else {
    $day_count = (int) $range_start->diff($range_end)->days;
    for ($day = 0; $day < $day_count; $day++) {
        $bucket = $range_start->modify('+' . $day . ' days');
        $enrollment_buckets[] = [
            'key' => $bucket->format('Y-m-d'),
            'label' => $period === 'week' ? $bucket->format('D') : $bucket->format('j')
        ];
    }
}

$enrollment_values = [];
foreach ($enrollment_buckets as $bucket) {
    $enrollment_values[] = $enrollments_by_bucket[$bucket['key']] ?? 0;
}
$enrollment_total = array_sum($enrollment_values);
$max_enrollment = max($enrollment_values) ?: 1;
$enrollment_points = [];
foreach ($enrollment_values as $index => $value) {
    $x = count($enrollment_values) > 1
        ? $index / (count($enrollment_values) - 1) * 100
        : 50;
    $y = 90 - ($value / $max_enrollment * 75);
    $enrollment_points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
}
$trend_label_indexes = array_unique([
    0,
    (int) floor((count($enrollment_buckets) - 1) / 2),
    count($enrollment_buckets) - 1
]);

$company_stmt = $conn->prepare("
    SELECT c.company_name, COUNT(DISTINCT i.student_id) AS student_total
    FROM internships i
    JOIN companies c ON c.company_id = i.company_id
    WHERE i.created_at >= ? AND i.created_at < ?
    GROUP BY c.company_id, c.company_name
    ORDER BY student_total DESC, c.company_name
    LIMIT 8
");
if (!$company_stmt) {
    throw new RuntimeException('Could not prepare partner company analytics.');
}
$company_stmt->bind_param('ss', $range_start_sql, $range_end_sql);
if (!$company_stmt->execute()) {
    throw new RuntimeException('Could not load partner company analytics.');
}
$company_result = $company_stmt->get_result();
$company_rows = $company_result->fetch_all(MYSQLI_ASSOC);
$max_company_students = max(array_column($company_rows, 'student_total') ?: [0]) ?: 1;

$supervisor_stmt = $conn->prepare("
    SELECT COALESCE(s.full_name, 'Unassigned') AS supervisor_name,
        COUNT(DISTINCT i.student_id) AS student_total
    FROM internships i
    LEFT JOIN users s ON s.user_id = i.supervisor_id
    WHERE i.created_at >= ? AND i.created_at < ?
    GROUP BY i.supervisor_id, s.full_name
    ORDER BY student_total DESC, supervisor_name
    LIMIT 8
");
if (!$supervisor_stmt) {
    throw new RuntimeException('Could not prepare supervisor analytics.');
}
$supervisor_stmt->bind_param('ss', $range_start_sql, $range_end_sql);
if (!$supervisor_stmt->execute()) {
    throw new RuntimeException('Could not load supervisor analytics.');
}
$supervisor_result = $supervisor_stmt->get_result();
$supervisor_rows = $supervisor_result->fetch_all(MYSQLI_ASSOC);
$max_supervisor_students = max(array_column($supervisor_rows, 'student_total') ?: [0]) ?: 1;

$status_colors = [
    'ongoing' => '#18845b',
    'pending' => '#d69a28',
    'completed' => '#3978df',
    'cancelled' => '#b54745'
];
$donut_parts = [];
$donut_offset = 0;
foreach ($internship_statuses as $status_name => $status_total) {
    if ($status_total === 0 || $internship_total === 0) {
        continue;
    }
    $next_offset = $donut_offset + ($status_total / $internship_total * 100);
    $donut_parts[] = $status_colors[$status_name] . ' '
        . number_format($donut_offset, 2, '.', '') . '% '
        . number_format($next_offset, 2, '.', '') . '%';
    $donut_offset = $next_offset;
}
$donut_background = $donut_parts
    ? 'conic-gradient(' . implode(', ', $donut_parts) . ')'
    : 'conic-gradient(#e9eef6 0% 100%)';

$recent_activity = [];
$activity_result = $conn->query("
    SELECT COALESCE(u.full_name, 'System') AS full_name, al.action, al.created_at
    FROM activity_logs al
    LEFT JOIN users u ON u.user_id = al.user_id
    ORDER BY al.created_at DESC, al.log_id DESC
    LIMIT 10
");
if (!$activity_result) {
    throw new RuntimeException('Could not load recent activity logs.');
}
while ($row = $activity_result->fetch_assoc()) {
    $recent_activity[] = $row;
}

$recent_errors = [];
$error_result = $conn->query("
    SELECT error_id, error_type, description, severity, occurred_at
    FROM system_error_logs
    ORDER BY occurred_at DESC, error_id DESC
    LIMIT 10
");
if (!$error_result) {
    throw new RuntimeException('Could not load recent system error logs.');
}
while ($row = $error_result->fetch_assoc()) {
    $recent_errors[] = $row;
}

$database_check = $conn->query('SELECT 1 AS available');
$database_available = $database_check
    && (int) $database_check->fetch_assoc()['available'] === 1;
$storage_total = @disk_total_space(__DIR__);
$storage_free = @disk_free_space(__DIR__);
$storage_available = $storage_total !== false && $storage_total > 0 && $storage_free !== false;
$storage_used_percent = $storage_available
    ? round(($storage_total - $storage_free) / $storage_total * 100, 1)
    : null;
$format_bytes = static function (float $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unit = 0;
    while ($bytes >= 1024 && $unit < count($units) - 1) {
        $bytes /= 1024;
        $unit++;
    }
    return number_format($bytes, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
};
$php_peak_memory = $format_bytes((float) memory_get_peak_usage(true));
$request_started = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
$response_time_ms = max(0, round((microtime(true) - $request_started) * 1000, 1));

require_once __DIR__ . '/../includes/header.php';
?>

<nav class="admin-dashboard-tabs" role="tablist" aria-label="Admin dashboard sections">
    <button type="button" role="tab" id="overview-tab" aria-controls="overview-panel"
        aria-selected="<?= $active_tab === 'overview' ? 'true' : 'false' ?>"
        tabindex="<?= $active_tab === 'overview' ? '0' : '-1' ?>" data-dashboard-tab="overview">Overview</button>
    <button type="button" role="tab" id="analytics-tab" aria-controls="analytics-panel"
        aria-selected="<?= $active_tab === 'analytics' ? 'true' : 'false' ?>"
        tabindex="<?= $active_tab === 'analytics' ? '0' : '-1' ?>" data-dashboard-tab="analytics">Analytics</button>
    <button type="button" role="tab" id="system-logs-tab" aria-controls="system-logs-panel"
        aria-selected="<?= $active_tab === 'system-logs' ? 'true' : 'false' ?>"
        tabindex="<?= $active_tab === 'system-logs' ? '0' : '-1' ?>" data-dashboard-tab="system-logs">System Logs</button>
</nav>

<section class="admin-dashboard-panel" id="overview-panel" role="tabpanel" aria-labelledby="overview-tab"
    <?= $active_tab !== 'overview' ? 'hidden' : '' ?>>
    <section class="panel">
        <div class="panel-head">
            <div><h2>System Overview</h2><p>Current availability and registered system records.</p></div>
        </div>
        <div class="cards system-status-cards">
            <div class="stat-card">
                <div class="stat-icon">S</div>
                <div><span>System Status</span><strong><i class="monitor-dot online"></i>Online</strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">D</div>
                <div><span>Database Status</span><strong><i class="monitor-dot <?= $database_available ? 'online' : 'offline' ?>"></i><?= $database_available ? 'Active' : 'Unavailable' ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">W</div>
                <div><span>Server Status</span><strong><i class="monitor-dot online"></i>Running</strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">U</div>
                <div><span>Total System Users</span><strong><?= (int) $counts['total_users'] ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">A</div>
                <div><span>Total Admins</span><strong><?= $user_role_totals['admin'] ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">A</div>
                <div><span>Active Users</span><strong><?= (int) $counts['active_users'] ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">I</div>
                <div><span>Inactive Users</span><strong><?= (int) $counts['inactive_users'] ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">C</div>
                <div><span>Partner Companies</span><strong><?= (int) $counts['total_companies'] ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">E</div>
                <div><span>System Errors</span><strong><?= (int) $counts['total_errors'] ?></strong></div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div><h2>System Health</h2><p>Live measurements from this PHP request and host volume.</p></div>
        </div>
        <div class="performance-grid">
            <div class="performance-card"><small>Server uptime</small><strong>Unavailable</strong><span>Not exposed by this PHP host.</span></div>
            <div class="performance-card"><small>CPU usage</small><strong>Unavailable</strong><span>Host CPU metrics are not exposed to PHP.</span></div>
            <div class="performance-card"><small>Memory usage</small><strong><?= htmlspecialchars($php_peak_memory) ?></strong><span>Peak allocation for this PHP request.</span></div>
            <div class="performance-card"><small>Database connection</small><strong><?= $database_available ? 'Active' : 'Unavailable' ?></strong><span><?= htmlspecialchars($conn->server_info) ?></span></div>
            <div class="performance-card"><small>PHP runtime</small><strong><?= htmlspecialchars(PHP_VERSION) ?></strong><span>Version serving this request.</span></div>
            <div class="performance-card"><small>Server time</small><strong><?= htmlspecialchars(format_system_datetime(date('Y-m-d H:i:s'), $system_settings)) ?></strong><span>Configured application time zone.</span></div>
            <div class="performance-card">
                <small>Storage utilization</small>
                <?php if ($storage_available): ?>
                    <strong><?= number_format($storage_used_percent, 1) ?>% used</strong>
                    <span><?= htmlspecialchars($format_bytes((float) $storage_free)) ?> free of <?= htmlspecialchars($format_bytes((float) $storage_total)) ?></span>
                    <div class="progress-track"><span style="width: <?= min(100, max(0, $storage_used_percent)) ?>%"></span></div>
                <?php else: ?>
                    <strong>Unavailable</strong><span>Disk capacity is not exposed to PHP.</span>
                <?php endif; ?>
            </div>
            <div class="performance-card"><small>Application response time</small><strong><?= number_format($response_time_ms, 1) ?> ms</strong><span>PHP processing time before page output.</span></div>
        </div>
    </section>
</section>

<section class="admin-dashboard-panel" id="analytics-panel" role="tabpanel" aria-labelledby="analytics-tab"
    <?= $active_tab !== 'analytics' ? 'hidden' : '' ?>>
    <section class="panel">
        <div class="panel-head">
            <div><h2>Internship Analytics</h2><p>Assignment and enrollment records created during the selected period.</p></div>
        </div>
        <form method="GET" class="analytics-filter">
            <input type="hidden" name="tab" value="analytics">
            <label for="analytics-period">Reporting period</label>
            <select id="analytics-period" name="period">
                <?php foreach ($period_options as $option_value => $option_label): ?>
                    <option value="<?= htmlspecialchars($option_value) ?>" <?= $period === $option_value ? 'selected' : '' ?>><?= htmlspecialchars($option_label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn primary">Apply filter</button>
            <span class="analytics-range"><?= htmlspecialchars($range_start->format('M j, Y')) ?> – <?= htmlspecialchars($range_end->modify('-1 day')->format('M j, Y')) ?></span>
        </form>

        <div class="analytics-grid">
            <article class="panel analytics-card">
                <div class="panel-head"><div><h3>Internship Status Distribution</h3><p>Current status of assignments created in this period.</p></div></div>
                <div class="donut-layout">
                    <div class="donut-chart" role="img" aria-label="<?= $internship_total ?> internship assignments by current status"
                        style="background: <?= htmlspecialchars($donut_background, ENT_QUOTES, 'UTF-8') ?>">
                        <span><strong><?= $internship_total ?></strong><small>assignments</small></span>
                    </div>
                    <ul class="chart-legend">
                        <?php foreach ($internship_statuses as $status_name => $status_total): ?>
                            <li><i style="background: <?= htmlspecialchars($status_colors[$status_name]) ?>"></i><span><?= htmlspecialchars(ucfirst($status_name)) ?></span><strong><?= $status_total ?></strong></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </article>

            <article class="panel analytics-card">
                <div class="panel-head"><div><h3>Student Enrollment Trends</h3><p><?= $enrollment_total ?> student accounts created during this period.</p></div></div>
                <?php if ($enrollment_points): ?>
                    <div class="line-chart">
                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Student account registrations over time">
                            <line x1="0" y1="90" x2="100" y2="90"></line>
                            <line x1="0" y1="52.5" x2="100" y2="52.5"></line>
                            <line x1="0" y1="15" x2="100" y2="15"></line>
                            <polyline points="<?= htmlspecialchars(implode(' ', $enrollment_points), ENT_QUOTES, 'UTF-8') ?>"></polyline>
                        </svg>
                        <div class="line-chart-labels">
                            <?php foreach ($trend_label_indexes as $label_index): ?>
                                <?php $label_position = count($enrollment_buckets) > 1 ? $label_index / (count($enrollment_buckets) - 1) * 100 : 50; ?>
                                <span style="left: <?= number_format($label_position, 2, '.', '') ?>%"><?= htmlspecialchars($enrollment_buckets[$label_index]['label']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No enrollment data is available for this period.</div>
                <?php endif; ?>
            </article>

            <article class="panel analytics-card">
                <div class="panel-head"><div><h3>Students per Partner Company</h3><p>Distinct assigned students in this period.</p></div></div>
                <?php if ($company_rows): ?>
                    <div class="analytics-bars">
                        <?php foreach ($company_rows as $company): ?>
                            <?php $company_count = (int) $company['student_total']; ?>
                            <div class="analytics-row">
                                <div class="analytics-label"><span><?= htmlspecialchars($company['company_name']) ?></span><strong><?= $company_count ?></strong></div>
                                <div class="analytics-track"><span class="analytics-bar" style="width: <?= max(3, (int) round($company_count / $max_company_students * 100)) ?>%"></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No partner company assignments in this period.</div>
                <?php endif; ?>
            </article>

            <article class="panel analytics-card">
                <div class="panel-head"><div><h3>OJT Completion Statistics</h3><p>Assignment status progress for the selected cohort.</p></div></div>
                <?php foreach ($internship_statuses as $status_name => $status_total): ?>
                    <?php $status_percent = $internship_total > 0 ? round($status_total / $internship_total * 100, 1) : 0; ?>
                    <div class="analytics-row status-progress-row">
                        <div class="analytics-label"><span><?= htmlspecialchars(ucfirst($status_name)) ?></span><strong><?= $status_total ?> <small>(<?= number_format($status_percent, 1) ?>%)</small></strong></div>
                        <div class="analytics-track"><span class="analytics-bar <?= htmlspecialchars($status_name) ?>-bar" style="width: <?= $status_percent ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
            </article>

            <article class="panel analytics-card">
                <div class="panel-head"><div><h3>Student Distribution per Supervisor</h3><p>Distinct assigned students in this period.</p></div></div>
                <?php if ($supervisor_rows): ?>
                    <div class="analytics-bars">
                        <?php foreach ($supervisor_rows as $supervisor): ?>
                            <?php $supervisor_count = (int) $supervisor['student_total']; ?>
                            <div class="analytics-row">
                                <div class="analytics-label"><span><?= htmlspecialchars($supervisor['supervisor_name']) ?></span><strong><?= $supervisor_count ?></strong></div>
                                <div class="analytics-track"><span class="analytics-bar supervisor-bar" style="width: <?= max(3, (int) round($supervisor_count / $max_supervisor_students * 100)) ?>%"></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No supervisor assignments in this period.</div>
                <?php endif; ?>
            </article>

            <article class="panel analytics-card completion-rate-card">
                <div class="panel-head"><div><h3>Internship Completion Rate</h3><p>Completed assignments divided by assignments created in this period.</p></div></div>
                <strong class="completion-rate"><?= number_format($completion_rate, 1) ?>%</strong>
                <div class="progress-track"><span style="width: <?= $completion_rate ?>%"></span></div>
                <p><?= $internship_statuses['completed'] ?> of <?= $internship_total ?> assignments completed</p>
            </article>

            <article class="panel analytics-card">
                <div class="panel-head"><div><h3>User Analytics</h3><p>All registered accounts by role.</p></div></div>
                <div class="analytics-bars">
                    <?php foreach ($user_role_totals as $role_name => $role_total): ?>
                        <div class="analytics-row">
                            <div class="analytics-label"><span><?= htmlspecialchars(ucfirst($role_name)) ?></span><strong><?= $role_total ?></strong></div>
                            <div class="analytics-track"><span class="analytics-bar" style="width: <?= $role_total === 0 ? 0 : max(3, (int) round($role_total / $max_role_users * 100)) ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="panel analytics-card">
                <div class="panel-head"><div><h3>Partner Company Analytics</h3><p>All partner companies by account status.</p></div></div>
                <div class="analytics-bars">
                    <?php foreach ($company_status_totals as $company_status => $company_status_total): ?>
                        <div class="analytics-row">
                            <div class="analytics-label"><span><?= htmlspecialchars(ucfirst($company_status)) ?></span><strong><?= $company_status_total ?></strong></div>
                            <div class="analytics-track"><span class="analytics-bar <?= $company_status === 'active' ? 'ongoing-bar' : 'cancelled-bar' ?>" style="width: <?= $company_status_total === 0 ? 0 : max(3, (int) round($company_status_total / $max_company_statuses * 100)) ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>
    </section>
</section>

<section class="admin-dashboard-panel" id="system-logs-panel" role="tabpanel" aria-labelledby="system-logs-tab"
    <?= $active_tab !== 'system-logs' ? 'hidden' : '' ?>>
    <section class="panel">
        <div class="panel-head"><div><h2>Recent Activity Logs</h2><p>Latest account, sign-in, and internship workflow activity.</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>User</th><th>Activity</th><th>Date and time</th></tr></thead>
                <tbody>
                    <?php if ($recent_activity): ?>
                        <?php foreach ($recent_activity as $activity): ?>
                            <tr>
                                <td><?= htmlspecialchars($activity['full_name']) ?></td>
                                <td><?= htmlspecialchars($activity['action']) ?></td>
                                <td><?= htmlspecialchars(format_system_datetime($activity['created_at'], $system_settings)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="empty-state">No activities have been logged yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>Recent Error Logs</h2><p>Technical errors recorded by the application.</p></div></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Error ID</th><th>Error Type</th><th>Error Description</th><th>Date and time</th><th>Severity</th></tr></thead>
                <tbody>
                    <?php if ($recent_errors): ?>
                        <?php foreach ($recent_errors as $error): ?>
                            <tr>
                                <td><?= (int) $error['error_id'] ?></td>
                                <td><?= htmlspecialchars($error['error_type']) ?></td>
                                <td class="error-description"><?= htmlspecialchars($error['description']) ?></td>
                                <td><?= htmlspecialchars(format_system_datetime($error['occurred_at'], $system_settings)) ?></td>
                                <td><span class="badge severity-<?= htmlspecialchars($error['severity']) ?>"><?= htmlspecialchars(ucfirst($error['severity'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="empty-state">No technical errors have been recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<style>
.admin-dashboard-tabs{display:flex;gap:6px;margin:-6px 0 20px;border-bottom:1px solid #dfe6f0}
.admin-dashboard-tabs [role=tab]{padding:12px 17px;border:0;border-bottom:2px solid transparent;background:transparent;color:#66758c;font-weight:700;cursor:pointer}
.admin-dashboard-tabs [role=tab][aria-selected=true]{border-bottom-color:#2864d7;color:#235bbd}
.admin-dashboard-tabs [role=tab]:focus-visible{outline:2px solid #7da1e5;outline-offset:2px}
.admin-dashboard-panel[hidden]{display:none}
.admin-dashboard-panel>.panel{margin-bottom:20px}
.system-status-cards{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:0}
.monitor-dot{display:inline-block;width:9px;height:9px;margin:0 8px 1px 0;border-radius:50%;background:#18845b}
.monitor-dot.offline{background:#b54745}
.performance-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.performance-card{min-width:0;padding:16px;border:1px solid #edf1f6;border-radius:10px;background:#f8fafd}
.performance-card small,.performance-card span{display:block;color:#75839a;font-size:11px}
.performance-card small{margin-bottom:6px;font-weight:700;text-transform:uppercase;letter-spacing:.35px}
.performance-card strong{display:block;margin-bottom:4px;color:#172b4d;font-size:17px}
.performance-card .progress-track{margin-top:11px}
.analytics-filter{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 20px}
.analytics-filter label{margin:0}
.analytics-filter select{min-width:180px;min-height:40px;padding:8px 11px;border:1px solid #dfe5ee;border-radius:8px;background:#fff}
.analytics-range{margin-left:auto;color:#75839a;font-size:12px}
.analytics-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.analytics-card{min-width:0;padding:19px;box-shadow:none}
.analytics-card .panel-head{margin-bottom:16px}
.analytics-card .panel-head h3{margin:0 0 5px}
.analytics-card .panel-head p{margin:0}
.donut-layout{display:flex;align-items:center;justify-content:center;gap:24px;min-height:205px}
.donut-chart{display:grid;width:166px;height:166px;flex:0 0 166px;place-items:center;border-radius:50%}
.donut-chart:before{width:112px;height:112px;content:"";grid-area:1/1;border-radius:50%;background:#fff}
.donut-chart>span{z-index:1;grid-area:1/1;text-align:center}
.donut-chart strong,.donut-chart small{display:block}
.donut-chart strong{font-size:25px}
.donut-chart small{color:#75839a;font-size:10px}
.chart-legend{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.chart-legend li{display:grid;grid-template-columns:10px minmax(75px,1fr) auto;align-items:center;gap:8px}
.chart-legend i{width:9px;height:9px;border-radius:3px}
.chart-legend span{color:#52627a;font-size:12px}
.chart-legend strong{font-size:12px}
.line-chart{position:relative;padding:10px 0 29px}
.line-chart svg{display:block;width:100%;height:174px;overflow:visible}
.line-chart svg line{stroke:#e8edf4;stroke-width:.7;vector-effect:non-scaling-stroke}
.line-chart svg polyline{fill:none;stroke:#3978df;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}
.line-chart-labels{position:absolute;right:0;bottom:2px;left:0;height:20px}
.line-chart-labels span{position:absolute;color:#75839a;font-size:10px;transform:translateX(-50%)}
.line-chart-labels span:first-child{transform:none}
.line-chart-labels span:last-child{transform:translateX(-100%)}
.analytics-bars{display:grid;gap:15px}
.analytics-row{display:grid;gap:7px;min-width:0}
.analytics-label{display:flex;justify-content:space-between;gap:12px;min-width:0;color:#52627a;font-size:12px}
.analytics-label span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.analytics-label strong{flex:0 0 auto;color:#263750}
.analytics-label small{color:#75839a;font-weight:500}
.analytics-track{height:9px;overflow:hidden;border-radius:99px;background:#eef1f6}
.analytics-bar{display:block;height:100%;min-width:0;border-radius:99px;background:#3978df}
.ongoing-bar{background:#18845b}.pending-bar{background:#d69a28}.completed-bar{background:#3978df}.cancelled-bar{background:#b54745}.supervisor-bar{background:#7b61c9}
.status-progress-row{margin-bottom:13px}
.completion-rate-card{display:flex;flex-direction:column;justify-content:center}
.completion-rate{display:block;margin:10px 0;color:#2864d7;font-size:44px;letter-spacing:-1.5px}
.completion-rate-card>p{margin:10px 0 0}
.error-description{min-width:220px;max-width:560px;overflow-wrap:anywhere}
.severity-low{border-color:#dfe6f0;background:#f4f6f9;color:#58677e}
.severity-medium{border-color:#f2e2bd;background:#fff8e9;color:#94620c}
.severity-high{border-color:#f1d2d0;background:#fff2f1;color:#a33d3a}
@media(max-width:1000px){.system-status-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.performance-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.analytics-grid{grid-template-columns:1fr}.analytics-range{width:100%;margin-left:0}.donut-layout{justify-content:flex-start}}
@media(max-width:520px){.admin-dashboard-tabs{gap:0}.admin-dashboard-tabs [role=tab]{padding:11px 9px;font-size:11px}.system-status-cards,.performance-grid{grid-template-columns:1fr}.donut-layout{gap:15px}.donut-chart{width:135px;height:135px;flex-basis:135px}.donut-chart:before{width:91px;height:91px}}
</style>

<script>
(() => {
    const tabs = Array.from(document.querySelectorAll('[data-dashboard-tab]'));
    const panels = Array.from(document.querySelectorAll('.admin-dashboard-panel'));
    const activate = (tab, moveFocus) => {
        const selected = tab.dataset.dashboardTab;
        tabs.forEach((item) => {
            const isSelected = item === tab;
            item.setAttribute('aria-selected', String(isSelected));
            item.tabIndex = isSelected ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.id !== selected + '-panel';
        });
        if (moveFocus) {
            tab.focus();
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab, false));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            let nextIndex = index;
            if (event.key === 'ArrowLeft') {
                nextIndex = (index + tabs.length - 1) % tabs.length;
            } else if (event.key === 'ArrowRight') {
                nextIndex = (index + 1) % tabs.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else {
                nextIndex = tabs.length - 1;
            }
            activate(tabs[nextIndex], true);
        });
    });

    const hashTab = window.location.hash.replace('#', '');
    const initialTab = tabs.find((tab) => tab.dataset.dashboardTab === hashTab)
        || tabs.find((tab) => tab.getAttribute('aria-selected') === 'true');
    if (initialTab) {
        activate(initialTab, false);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'Dashboard';

$counts_result = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'active') AS active_users,
        (SELECT COUNT(*) FROM users WHERE status = 'inactive') AS inactive_users,
        (SELECT COUNT(*) FROM companies) AS total_companies
");
$counts = $counts_result->fetch_assoc();

$user_analytics = [
    'student' => 0,
    'coordinator' => 0,
    'supervisor' => 0,
    'admin' => 0
];
$user_result = $conn->query("
    SELECT role, COUNT(*) AS total
    FROM users
    GROUP BY role
");
while ($row = $user_result->fetch_assoc()) {
    if (array_key_exists($row['role'], $user_analytics)) {
        $user_analytics[$row['role']] = (int) $row['total'];
    }
}

$company_analytics = ['active' => 0, 'inactive' => 0];
$company_result = $conn->query("
    SELECT status, COUNT(*) AS total
    FROM companies
    GROUP BY status
");
while ($row = $company_result->fetch_assoc()) {
    if (array_key_exists($row['status'], $company_analytics)) {
        $company_analytics[$row['status']] = (int) $row['total'];
    }
}

$recent_activity = [];
$activity_result = $conn->query("
    SELECT COALESCE(u.full_name, 'System') AS full_name,
        al.action, al.created_at
    FROM activity_logs al
    LEFT JOIN users u ON u.user_id = al.user_id
    ORDER BY al.created_at DESC, al.log_id DESC
    LIMIT 12
");
while ($row = $activity_result->fetch_assoc()) {
    $recent_activity[] = $row;
}

$database_check = $conn->query('SELECT 1 AS available');
$database_available = (int) $database_check->fetch_assoc()['available'] === 1;
$activity_logging_status = $activity_result !== false ? 'Available' : 'Unavailable';
$max_users = max($user_analytics) ?: 1;
$max_companies = max($company_analytics) ?: 1;

require_once __DIR__ . '/../includes/header.php';
?>

<section class="panel admin-overview">
    <div class="panel-head">
        <div>
            <h2>Admin Overview</h2>
            <p>Current user and partner-company totals.</p>
        </div>
    </div>

    <div class="cards">
        <div class="stat-card">
            <div class="stat-icon">U</div>
            <div><span>Total Users</span><strong><?= (int) $counts['total_users'] ?></strong></div>
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
    </div>
</section>

<section class="panel admin-monitoring">
    <div class="panel-head">
        <div>
            <h2>System Monitoring</h2>
            <p>Live status checks from the current application request.</p>
        </div>
    </div>
    <div class="info-grid">
        <div>
            <small>Database connection</small>
            <strong><span class="monitor-dot <?= $database_available ? 'online' : 'offline' ?>"></span><?= $database_available ? 'Connected' : 'Unavailable' ?></strong>
        </div>
        <div>
            <small>Activity logging</small>
            <strong><span class="monitor-dot <?= $activity_logging_status === 'Available' ? 'online' : 'offline' ?>"></span><?= htmlspecialchars($activity_logging_status) ?></strong>
        </div>
        <div>
            <small>PHP runtime</small>
            <strong><?= htmlspecialchars(PHP_VERSION) ?></strong>
        </div>
        <div>
            <small>Server time</small>
            <strong><?= htmlspecialchars(format_system_datetime(date('Y-m-d H:i:s'), $system_settings)) ?></strong>
        </div>
    </div>
</section>

<section class="analytics-grid">
    <div class="panel analytics-panel">
        <div class="panel-head">
            <div><h2>User Analytics</h2><p>Accounts by role.</p></div>
        </div>
        <div class="analytics-bars" role="img" aria-label="User account counts by role">
            <?php foreach ($user_analytics as $role => $total): ?>
                <div class="analytics-row">
                    <div class="analytics-label">
                        <span><?= htmlspecialchars(ucfirst($role)) ?></span>
                        <strong><?= $total ?></strong>
                    </div>
                    <div class="analytics-track">
                        <span class="analytics-bar users-bar" style="width: <?= $total === 0 ? 0 : max(3, (int) round($total / $max_users * 100)) ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel analytics-panel">
        <div class="panel-head">
            <div><h2>Partner Company Analytics</h2><p>Partner companies by account status.</p></div>
        </div>
        <div class="analytics-bars" role="img" aria-label="Partner company counts by status">
            <?php foreach ($company_analytics as $status => $total): ?>
                <div class="analytics-row">
                    <div class="analytics-label">
                        <span><?= htmlspecialchars(ucfirst($status)) ?></span>
                        <strong><?= $total ?></strong>
                    </div>
                    <div class="analytics-track">
                        <span class="analytics-bar companies-bar" style="width: <?= $total === 0 ? 0 : max(3, (int) round($total / $max_companies * 100)) ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="panel admin-activity">
    <div class="panel-head">
        <div><h2>Activity Log</h2><p>Latest sign-ins, sign-outs, and account or OJT workflow changes.</p></div>
    </div>
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

<style>
.admin-overview .cards{margin-bottom:0}
.admin-overview .panel-head{margin-bottom:18px}
.admin-monitoring{margin-top:24px}
.monitor-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:8px}
.monitor-dot.online{background:#20a464}
.monitor-dot.offline{background:#b42318}
.analytics-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;margin-top:24px}
.analytics-panel{min-width:0}
.analytics-bars{display:grid;gap:18px}
.analytics-row{display:grid;gap:8px}
.analytics-label{display:flex;justify-content:space-between;font-size:14px}
.analytics-label span{color:#596579}
.analytics-track{height:12px;border-radius:99px;background:#eef1f6;overflow:hidden}
.analytics-bar{display:block;height:100%;border-radius:99px;background:#3677ed;min-width:0}
.companies-bar{background:#20a464}
.admin-activity{margin-top:24px}
@media(max-width:900px){.admin-overview .cards{grid-template-columns:repeat(2,1fr)}.analytics-grid{grid-template-columns:1fr}}
@media(max-width:650px){.admin-overview .cards{grid-template-columns:1fr}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

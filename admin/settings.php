<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'Settings';
$full_name  = $_SESSION['full_name'] ?? 'Administrator';
$role       = ucfirst($_SESSION['role'] ?? 'admin');

$stats = [
    ['label' => 'Profile', 'value' => 'Admin', 'icon' => 'P'],
    ['label' => 'Role', 'value' => $role, 'icon' => 'R'],
    ['label' => 'Account Status', 'value' => 'Active', 'icon' => 'A'],
    ['label' => 'System', 'value' => 'Online', 'icon' => 'S']
];

require_once __DIR__ . '/../includes/header.php';
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

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>Administrator Profile</h2>
            <p>Information about the currently signed-in administrator.</p>
        </div>
    </div>

    <div class="info-grid">
        <div><small>Name</small><strong><?= htmlspecialchars($full_name) ?></strong></div>
        <div><small>Role</small><strong><?= htmlspecialchars($role) ?></strong></div>
        <div><small>Status</small><strong>Active</strong></div>
        <div><small>Access Level</small><strong>Administrator</strong></div>
    </div>
</section>

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>System Preferences</h2>
            <p>Current preferences for the administrator interface.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Preference</th><th>Description</th><th>Status</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>System Notifications</td>
                    <td>Notifications for important system activity</td>
                    <td><span class="badge approved">Enabled</span></td>
                </tr>
                <tr>
                    <td>Review Alerts</td>
                    <td>Alerts for pending requirements and reports</td>
                    <td><span class="badge approved">Enabled</span></td>
                </tr>
                <tr>
                    <td>Activity Logging</td>
                    <td>System activity monitoring and recording</td>
                    <td><span class="badge approved">Enabled</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>System Information</h2>
            <p>Basic information about the current InternTrack system.</p>
        </div>
    </div>

    <div class="info-grid">
        <div><small>Application</small><strong>InternTrack</strong></div>
        <div><small>System Type</small><strong>OJT Monitoring System</strong></div>
        <div><small>Backend</small><strong>PHP + MySQL</strong></div>
        <div><small>Environment</small><strong>Local Development</strong></div>
    </div>
</section>

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>About InternTrack</h2>
            <p>Internship/OJT Monitoring and Management System.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <tbody>
                <tr><td><strong>Application</strong></td><td>InternTrack</td></tr>
                <tr><td><strong>Purpose</strong></td><td>Internship and OJT monitoring and management</td></tr>
                <tr><td><strong>User Roles</strong></td><td>Student, Coordinator, Supervisor, Administrator</td></tr>
                <tr><td><strong>Technology</strong></td><td>PHP, MySQL, JavaScript, CSS</td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
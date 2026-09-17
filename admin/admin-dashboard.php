<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'Admin Dashboard';

function fetch_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();
    return (int) ($row[0] ?? 0);
}

$total_users = fetch_count($conn, "SELECT COUNT(*) FROM users");
$active_users = fetch_count($conn, "SELECT COUNT(*) FROM users WHERE status='active'");
$active_internships = fetch_count($conn, "SELECT COUNT(*) FROM internships WHERE status='ongoing'");
$partner_companies = fetch_count($conn, "SELECT COUNT(*) FROM companies WHERE status='active'");
$pending_requirements = fetch_count($conn, "SELECT COUNT(*) FROM requirements WHERE status='pending'");
$pending_reports = fetch_count($conn, "SELECT COUNT(*) FROM accomplishment_reports WHERE status='submitted'");

$user_roles = [
    'student' => 0,
    'coordinator' => 0,
    'supervisor' => 0,
    'admin' => 0
];

$role_result = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
if ($role_result) {
    while ($row = $role_result->fetch_assoc()) {
        if (array_key_exists($row['role'], $user_roles)) {
            $user_roles[$row['role']] = (int) $row['total'];
        }
    }
}

$internship_statuses = [
    'pending' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$status_result = $conn->query("SELECT status, COUNT(*) AS total FROM internships GROUP BY status");
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        if (array_key_exists($row['status'], $internship_statuses)) {
            $internship_statuses[$row['status']] = (int) $row['total'];
        }
    }
}

$recent_users = [];
$users_result = $conn->query(
    "SELECT full_name, email, role, status, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 6"
);

if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}

$recent_internships = [];
$internships_result = $conn->query(
    "SELECT u.full_name, c.company_name, i.status, i.start_date, i.end_date
     FROM internships i
     JOIN users u ON u.user_id = i.student_id
     JOIN companies c ON c.company_id = i.company_id
     ORDER BY i.created_at DESC
     LIMIT 6"
);

if ($internships_result) {
    while ($row = $internships_result->fetch_assoc()) {
        $recent_internships[] = $row;
    }
}

$recent_activity = [];
$activity_result = $conn->query(
    "SELECT COALESCE(u.full_name, 'System') AS full_name,
            al.action,
            al.created_at
     FROM activity_logs al
     LEFT JOIN users u ON u.user_id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT 6"
);

if ($activity_result) {
    while ($row = $activity_result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="cards">
    <div class="stat-card">
        <div class="stat-icon">◉</div>
        <div>
            <span>Total Users</span>
            <strong><?= $total_users ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">●</div>
        <div>
            <span>Active Internships</span>
            <strong><?= $active_internships ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">▣</div>
        <div>
            <span>Partner Companies</span>
            <strong><?= $partner_companies ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">!</div>
        <div>
            <span>Pending Reviews</span>
            <strong><?= $pending_requirements + $pending_reports ?></strong>
        </div>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Admin Overview</h2>
            <p>Monitor users, internships, companies, and pending system activity.</p>
        </div>
    </div>

    <div class="info-grid">
        <div>
            <small>Active Users</small>
            <strong><?= $active_users ?></strong>
        </div>
        <div>
            <small>Students</small>
            <strong><?= $user_roles['student'] ?></strong>
        </div>
        <div>
            <small>Coordinators</small>
            <strong><?= $user_roles['coordinator'] ?></strong>
        </div>
        <div>
            <small>Supervisors</small>
            <strong><?= $user_roles['supervisor'] ?></strong>
        </div>
    </div>
</section>

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>Recent Users</h2>
            <p>Latest accounts registered in InternTrack.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recent_users): ?>
                <?php foreach ($recent_users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                        <td>
                            <span class="badge <?= $user['status'] === 'active' ? 'approved' : 'rejected' ?>">
                                <?= htmlspecialchars(ucfirst($user['status'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(date('M d, Y', strtotime($user['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-state">No users found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>Internship Monitoring</h2>
            <p>Current internship records across the system.</p>
        </div>
    </div>

    <div class="info-grid">
        <div>
            <small>Pending</small>
            <strong><?= $internship_statuses['pending'] ?></strong>
        </div>
        <div>
            <small>Ongoing</small>
            <strong><?= $internship_statuses['ongoing'] ?></strong>
        </div>
        <div>
            <small>Completed</small>
            <strong><?= $internship_statuses['completed'] ?></strong>
        </div>
        <div>
            <small>Cancelled</small>
            <strong><?= $internship_statuses['cancelled'] ?></strong>
        </div>
    </div>

    <div class="table-wrap" style="margin-top:20px;">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Start</th>
                    <th>End</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recent_internships): ?>
                <?php foreach ($recent_internships as $internship): ?>
                    <tr>
                        <td><?= htmlspecialchars($internship['full_name']) ?></td>
                        <td><?= htmlspecialchars($internship['company_name']) ?></td>
                        <td>
                            <span class="badge <?= $internship['status'] === 'completed' ? 'approved' : ($internship['status'] === 'cancelled' ? 'rejected' : '') ?>">
                                <?= htmlspecialchars(ucfirst($internship['status'])) ?>
                            </span>
                        </td>
                        <td><?= $internship['start_date'] ? htmlspecialchars(date('M d, Y', strtotime($internship['start_date']))) : '—' ?></td>
                        <td><?= $internship['end_date'] ? htmlspecialchars(date('M d, Y', strtotime($internship['end_date']))) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-state">No internship records found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="margin-top:24px;">
    <div class="panel-head">
        <div>
            <h2>Recent Activity</h2>
            <p>Latest actions recorded by the system.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recent_activity): ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <tr>
                        <td><?= htmlspecialchars($activity['full_name']) ?></td>
                        <td><?= htmlspecialchars($activity['action']) ?></td>
                        <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($activity['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="empty-state">No activity logs found yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

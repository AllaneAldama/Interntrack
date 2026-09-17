<?php

require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'System Reports';

function report_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();

    return (int) ($row[0] ?? 0);
}

$total_users = report_count(
    $conn,
    "SELECT COUNT(*) FROM users"
);

$active_users = report_count(
    $conn,
    "SELECT COUNT(*) FROM users WHERE status = 'active'"
);

$total_students = report_count(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'student'"
);

$total_coordinators = report_count(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'coordinator'"
);

$total_supervisors = report_count(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'supervisor'"
);

$total_admins = report_count(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'admin'"
);

$total_internships = report_count(
    $conn,
    "SELECT COUNT(*) FROM internships"
);

$ongoing_internships = report_count(
    $conn,
    "SELECT COUNT(*) FROM internships WHERE status = 'ongoing'"
);

$completed_internships = report_count(
    $conn,
    "SELECT COUNT(*) FROM internships WHERE status = 'completed'"
);

$pending_internships = report_count(
    $conn,
    "SELECT COUNT(*) FROM internships WHERE status = 'pending'"
);

$cancelled_internships = report_count(
    $conn,
    "SELECT COUNT(*) FROM internships WHERE status = 'cancelled'"
);

$total_companies = report_count(
    $conn,
    "SELECT COUNT(*) FROM companies"
);

$active_companies = report_count(
    $conn,
    "SELECT COUNT(*) FROM companies WHERE status = 'active'"
);

$pending_requirements = report_count(
    $conn,
    "SELECT COUNT(*) FROM requirements WHERE status = 'pending'"
);

$submitted_reports = report_count(
    $conn,
    "SELECT COUNT(*) FROM accomplishment_reports WHERE status = 'submitted'"
);

$recent_activity = [];

$activity_result = $conn->query(
    "SELECT
        COALESCE(u.full_name, 'System') AS full_name,
        al.action,
        al.created_at
     FROM activity_logs al
     LEFT JOIN users u ON u.user_id = al.user_id
     ORDER BY al.created_at DESC
     LIMIT 10"
);

if ($activity_result) {
    while ($row = $activity_result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
}

$recent_users = [];

$users_result = $conn->query(
    "SELECT full_name, email, role, status, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 8"
);

if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<style>

    .report-section {
        margin-top: 24px;
    }

    .report-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }

    .report-box {
        background: #fff;
        border: 1px solid #e5e9f0;
        border-radius: 10px;
        padding: 18px;
    }

    .report-box span {
        display: block;
        font-size: 13px;
        color: #748099;
        margin-bottom: 8px;
    }

    .report-box strong {
        font-size: 25px;
        color: #253047;
    }

    .report-description {
        color: #748099;
        font-size: 13px;
        margin-top: 5px;
    }

    .status-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid #edf0f5;
    }

    .status-row:last-child {
        border-bottom: none;
    }

    .status-name {
        color: #374151;
        font-size: 14px;
    }

    .status-number {
        font-weight: 700;
        color: #253047;
    }

    .report-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    .report-note {
        padding: 14px 16px;
        background: #f7f9fc;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .report-note strong {
        display: block;
        color: #253047;
        margin-bottom: 4px;
    }

    .report-note span {
        font-size: 13px;
        color: #748099;
    }

    .generated-date {
        font-size: 13px;
        color: #748099;
        margin-top: 4px;
    }

    @media (max-width: 1000px) {
        .report-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .report-columns {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .report-grid {
            grid-template-columns: 1fr;
        }
    }

</style>

<div class="cards">

    <div class="stat-card">
        <div class="stat-icon">U</div>
        <div>
            <span>Total Users</span>
            <strong><?= $total_users ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">I</div>
        <div>
            <span>Total Internships</span>
            <strong><?= $total_internships ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">C</div>
        <div>
            <span>Partner Companies</span>
            <strong><?= $total_companies ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">!</div>
        <div>
            <span>Pending Reviews</span>
            <strong><?= $pending_requirements + $submitted_reports ?></strong>
        </div>
    </div>

</div>

<section class="panel report-section">

    <div class="panel-head">

        <div>
            <h2>User Report</h2>
            <p>Summary of all registered InternTrack accounts.</p>

            <div class="generated-date">
                Generated on <?= date('F d, Y h:i A') ?>
            </div>
        </div>

    </div>

    <div class="report-grid">

        <div class="report-box">
            <span>Active Users</span>
            <strong><?= $active_users ?></strong>
            <div class="report-description">
                Currently active accounts
            </div>
        </div>

        <div class="report-box">
            <span>Students</span>
            <strong><?= $total_students ?></strong>
            <div class="report-description">
                Student accounts
            </div>
        </div>

        <div class="report-box">
            <span>Coordinators</span>
            <strong><?= $total_coordinators ?></strong>
            <div class="report-description">
                Coordinator accounts
            </div>
        </div>

        <div class="report-box">
            <span>Supervisors</span>
            <strong><?= $total_supervisors ?></strong>
            <div class="report-description">
                Supervisor accounts
            </div>
        </div>

    </div>

</section>

<section class="panel report-section">

    <div class="panel-head">

        <div>
            <h2>Internship Report</h2>
            <p>Overview of internship records and their current status.</p>
        </div>

    </div>

    <div class="report-grid">

        <div class="report-box">
            <span>Pending</span>
            <strong><?= $pending_internships ?></strong>
            <div class="report-description">
                Internships awaiting processing
            </div>
        </div>

        <div class="report-box">
            <span>Ongoing</span>
            <strong><?= $ongoing_internships ?></strong>
            <div class="report-description">
                Currently active internships
            </div>
        </div>

        <div class="report-box">
            <span>Completed</span>
            <strong><?= $completed_internships ?></strong>
            <div class="report-description">
                Finished internships
            </div>
        </div>

        <div class="report-box">
            <span>Cancelled</span>
            <strong><?= $cancelled_internships ?></strong>
            <div class="report-description">
                Cancelled internship records
            </div>
        </div>

    </div>

</section>

<section class="report-section">

    <div class="report-columns">

        <section class="panel">

            <div class="panel-head">

                <div>
                    <h2>Company Report</h2>
                    <p>Partner company information.</p>
                </div>

            </div>

            <div class="status-row">
                <span class="status-name">
                    Total Companies
                </span>

                <span class="status-number">
                    <?= $total_companies ?>
                </span>
            </div>

            <div class="status-row">
                <span class="status-name">
                    Active Companies
                </span>

                <span class="status-number">
                    <?= $active_companies ?>
                </span>
            </div>

        </section>


        <section class="panel">

            <div class="panel-head">

                <div>
                    <h2>Review Queue</h2>
                    <p>Records that require attention.</p>
                </div>

            </div>

            <div class="status-row">
                <span class="status-name">
                    Pending Requirements
                </span>

                <span class="status-number">
                    <?= $pending_requirements ?>
                </span>
            </div>

            <div class="status-row">
                <span class="status-name">
                    Submitted Reports
                </span>

                <span class="status-number">
                    <?= $submitted_reports ?>
                </span>
            </div>

        </section>

    </div>

</section>

<section class="panel report-section">

    <div class="panel-head">

        <div>
            <h2>Recent Users</h2>
            <p>Latest accounts registered in the system.</p>
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

                        <td>
                            <?= htmlspecialchars($user['full_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                ucfirst($user['role'])
                            ) ?>
                        </td>

                        <td>

                            <span class="badge <?= $user['status'] === 'active' ? 'approved' : 'rejected' ?>">
                                <?= htmlspecialchars(
                                    ucfirst($user['status'])
                                ) ?>
                            </span>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                date('M d, Y', strtotime($user['created_at']))
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" class="empty-state">
                        No users found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<section class="panel report-section">

    <div class="panel-head">

        <div>
            <h2>Recent System Activity</h2>
            <p>Latest actions recorded by InternTrack.</p>
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

                        <td>
                            <?= htmlspecialchars(
                                $activity['full_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $activity['action']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                date(
                                    'M d, Y h:i A',
                                    strtotime($activity['created_at'])
                                )
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="3" class="empty-state">
                        No activity logs found yet.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
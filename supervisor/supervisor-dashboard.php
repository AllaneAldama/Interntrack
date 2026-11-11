<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['supervisor']);

$page_title = 'Dashboard';

$supervisor_id = (int) ($_SESSION['user_id'] ?? 0);

$has_supervisor_id = false;
$has_report_internship_id = false;

$supervisor_column_check = $conn->query("
    SHOW COLUMNS FROM internships LIKE 'supervisor_id'
");

if ($supervisor_column_check && $supervisor_column_check->num_rows > 0) {
    $has_supervisor_id = true;
}

$report_column_check = $conn->query("
    SHOW COLUMNS FROM accomplishment_reports LIKE 'internship_id'
");

if ($report_column_check && $report_column_check->num_rows > 0) {
    $has_report_internship_id = true;
}

$total_interns = 0;
$ongoing_interns = 0;
$completed_interns = 0;
$reports_for_review = 0;

$recent_interns = [];

if ($has_supervisor_id && $supervisor_id > 0) {

    $total_result = $conn->query("
        SELECT COUNT(*) AS total
        FROM internships
        WHERE supervisor_id = {$supervisor_id}
    ");

    if ($total_result) {
        $total_interns = (int) ($total_result->fetch_assoc()['total'] ?? 0);
    }


    $ongoing_result = $conn->query("
        SELECT COUNT(*) AS total
        FROM internships
        WHERE supervisor_id = {$supervisor_id}
        AND status = 'ongoing'
    ");

    if ($ongoing_result) {
        $ongoing_interns = (int) ($ongoing_result->fetch_assoc()['total'] ?? 0);
    }


    $completed_result = $conn->query("
        SELECT COUNT(*) AS total
        FROM internships
        WHERE supervisor_id = {$supervisor_id}
        AND status = 'completed'
    ");

    if ($completed_result) {
        $completed_interns = (int) ($completed_result->fetch_assoc()['total'] ?? 0);
    }

    if ($has_report_internship_id) {

        $reports_result = $conn->query("
            SELECT COUNT(*) AS total
            FROM accomplishment_reports ar
            JOIN internships i
                ON i.internship_id = ar.internship_id
            WHERE i.supervisor_id = {$supervisor_id}
            AND ar.status = 'submitted'
        ");

        if ($reports_result) {
            $reports_for_review = (int) ($reports_result->fetch_assoc()['total'] ?? 0);
        }
    }

    $internship_result = $conn->query("
        SELECT
            i.internship_id,
            i.status,
            i.start_date,
            i.end_date,
            i.created_at,
            u.full_name AS student_name,
            u.email AS student_email,
            c.company_name
        FROM internships i
        JOIN users u
            ON u.user_id = i.student_id
        JOIN companies c
            ON c.company_id = i.company_id
        WHERE i.supervisor_id = {$supervisor_id}
        ORDER BY i.created_at DESC
        LIMIT 8
    ");

    if ($internship_result) {
        while ($row = $internship_result->fetch_assoc()) {
            $recent_interns[] = $row;
        }
    }
}


require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!$has_supervisor_id): ?>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Supervisor Assignment Setup</h2>
            <p>
                InternTrack is ready for the supervisor dashboard, but internship
                records are not yet linked to supervisors in the current database structure.
            </p>
        </div>
    </div>

    <div class="info-grid">

        <div>
            <small>Supervisor Account</small>
            <strong>Ready</strong>
        </div>

        <div>
            <small>Internship Assignment</small>
            <strong>Not Linked Yet</strong>
        </div>

        <div>
            <small>Assigned Interns</small>
            <strong>0</strong>
        </div>

        <div>
            <small>Reports For Review</small>
            <strong>0</strong>
        </div>

    </div>
</div>

<?php endif; ?>


<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Overview</h2>
            <p>Assigned interns and items requiring your attention.</p>
        </div>
    </div>

    <div class="cards">
        <div class="stat-card"><div class="stat-icon">I</div><div><span>Assigned Interns</span><strong><?= $total_interns ?></strong></div></div>
        <div class="stat-card"><div class="stat-icon">O</div><div><span>Ongoing</span><strong><?= $ongoing_interns ?></strong></div></div>
        <div class="stat-card"><div class="stat-icon">C</div><div><span>Completed</span><strong><?= $completed_interns ?></strong></div></div>
        <div class="stat-card"><div class="stat-icon">R</div><div><span>Reports for Review</span><strong><?= $reports_for_review ?></strong></div></div>
    </div>
</div>


<div class="panel">
    <div class="panel-head">
        <div>
            <h2>Recent Assigned Interns</h2>
            <p>Latest internship assignments.</p>
        </div>
    </div>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Student</th>
                    <th>Email</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Start</th>
                    <th>End</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($recent_interns): ?>

                    <?php foreach ($recent_interns as $intern): ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($intern['student_name']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($intern['student_email']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($intern['company_name']); ?>
                            </td>

                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($intern['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($intern['status'])); ?>
                                </span>
                            </td>

                            <td>
                                <?php
                                echo !empty($intern['start_date'])
                                    ? htmlspecialchars($intern['start_date'])
                                    : '—';
                                ?>
                            </td>

                            <td>
                                <?php
                                echo !empty($intern['end_date'])
                                    ? htmlspecialchars($intern['end_date'])
                                    : '—';
                                ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" style="text-align: center;">
                            No assigned interns found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
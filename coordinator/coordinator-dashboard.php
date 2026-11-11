<?php

require_once __DIR__ . '/../includes/auth.php';
require_role(['coordinator']);

$page_title = 'Dashboard';

function coordinator_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();

    return (int) ($row[0] ?? 0);
}

$total_students = coordinator_count(
    $conn,
    "SELECT COUNT(*) FROM users WHERE role = 'student'"
);

$partner_companies = coordinator_count(
    $conn,
    "SELECT COUNT(*) FROM companies"
);

$pending_requirements = coordinator_count(
    $conn,
    "SELECT COUNT(*) FROM requirements WHERE status = 'submitted'"
);

$pending_reports = coordinator_count(
    $conn,
    "SELECT COUNT(*) FROM accomplishment_reports WHERE status = 'submitted'"
);

$completed_internships = coordinator_count(
    $conn,
    "SELECT COUNT(*) FROM internships WHERE status = 'completed'"
);

$pending_internships = coordinator_count(
    $conn,
    "SELECT COUNT(*) FROM internships WHERE status = 'pending'"
);

$internship_statuses = [
    'pending' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$status_result = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM internships
     GROUP BY status"
);

if ($status_result) {

    while ($row = $status_result->fetch_assoc()) {

        if (array_key_exists($row['status'], $internship_statuses)) {
            $internship_statuses[$row['status']] = (int) $row['total'];
        }

    }

}

$recent_students = [];

$students_result = $conn->query(
    "SELECT full_name, email, status, created_at
     FROM users
     WHERE role = 'student'
     ORDER BY created_at DESC
     LIMIT 6"
);

if ($students_result) {

    while ($row = $students_result->fetch_assoc()) {
        $recent_students[] = $row;
    }

}

$recent_internships = [];

$internships_result = $conn->query(
    "SELECT
        u.full_name,
        c.company_name,
        i.status,
        i.start_date,
        i.end_date
     FROM internships i
     JOIN users u
        ON u.user_id = i.student_id
     JOIN companies c
        ON c.company_id = i.company_id
     ORDER BY i.created_at DESC
     LIMIT 6"
);

if ($internships_result) {

    while ($row = $internships_result->fetch_assoc()) {
        $recent_internships[] = $row;
    }

}

require_once __DIR__ . '/../includes/header.php';

?>

<section class="panel" style="margin-top:24px;">

    <div class="panel-head">

        <div>

            <h2>Overview</h2>

            <p>
                Monitor student progress, internships, partner companies, and requirements awaiting review.
            </p>

        </div>

    </div>


    <div class="info-grid coordinator-overview-grid">

        <div>

            <small>Students</small>

            <strong>
                <?= $total_students ?>
            </strong>

        </div>


        <div>

            <small>Ongoing Internships</small>

            <strong>
                <?= $internship_statuses['ongoing'] ?>
            </strong>

        </div>


        <div>

            <small>Completed Internships</small>

            <strong>
                <?= $completed_internships ?>
            </strong>

        </div>


        <div>

            <small>Pending Internships</small>

            <strong>
                <?= $pending_internships ?>
            </strong>

        </div>

        <div>
            <small>Partner Companies</small>
            <strong><?= $partner_companies ?></strong>
        </div>

        <div>
            <small>Requirements Pending Review</small>
            <strong><?= $pending_requirements ?></strong>
        </div>

    </div>

</section>

<section class="panel" style="margin-top:24px;">

    <div class="panel-head">

        <div>

            <h2>Recent Students</h2>

            <p>
                Latest student accounts registered.
            </p>

        </div>

    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Registered</th>
                </tr>

            </thead>


            <tbody>

            <?php if ($recent_students): ?>

                <?php foreach ($recent_students as $student): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $student['full_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $student['email']
                            ) ?>
                        </td>

                        <td>

                            <span class="badge <?= $student['status'] === 'active' ? 'approved' : 'rejected' ?>">

                                <?= htmlspecialchars(
                                    ucfirst($student['status'])
                                ) ?>

                            </span>

                        </td>

                        <td>
                            <?= htmlspecialchars(format_system_date($student['created_at'], $system_settings)) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="4"
                        class="empty-state"
                    >
                        No student records found.
                    </td>

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

            <p>
                Current internship records across the system.
            </p>

        </div>

    </div>


    <div class="info-grid">

        <div>

            <small>Pending</small>

            <strong>
                <?= $internship_statuses['pending'] ?>
            </strong>

        </div>


        <div>

            <small>Ongoing</small>

            <strong>
                <?= $internship_statuses['ongoing'] ?>
            </strong>

        </div>


        <div>

            <small>Completed</small>

            <strong>
                <?= $internship_statuses['completed'] ?>
            </strong>

        </div>


        <div>

            <small>Cancelled</small>

            <strong>
                <?= $internship_statuses['cancelled'] ?>
            </strong>

        </div>

    </div>


    <div
        class="table-wrap"
        style="margin-top:20px;"
    >

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

                        <td>
                            <?= htmlspecialchars(
                                $internship['full_name']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $internship['company_name']
                            ) ?>
                        </td>

                        <td>

                            <span class="badge
                                <?= $internship['status'] === 'completed'
                                    ? 'approved'
                                    : ($internship['status'] === 'cancelled'
                                        ? 'rejected'
                                        : '')
                                ?>
                            ">

                                <?= htmlspecialchars(
                                    ucfirst($internship['status'])
                                ) ?>

                            </span>

                        </td>

                        <td>

                            <?php if ($internship['start_date']): ?>

                                <?= htmlspecialchars(format_system_date($internship['start_date'], $system_settings)) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php if ($internship['end_date']): ?>

                                <?= htmlspecialchars(format_system_date($internship['end_date'], $system_settings)) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="empty-state"
                    >
                        No internship records found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<section class="panel" style="margin-top:24px;">

    <div class="panel-head">

        <div>

            <h2>Review Queue</h2>

            <p>
                Internship records that may require coordinator attention.
            </p>

        </div>

    </div>


    <div class="info-grid">

        <div>

            <small>Pending Requirements</small>

            <strong>
                <?= $pending_requirements ?>
            </strong>

        </div>


        <div>

            <small>Submitted Reports</small>

            <strong>
                <?= $pending_reports ?>
            </strong>

        </div>


        <div>

            <small>Total Reviews</small>

            <strong>
                <?= $pending_requirements + $pending_reports ?>
            </strong>

        </div>


        <div>

            <small>Coordinator Status</small>

            <strong>Active</strong>

        </div>

    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
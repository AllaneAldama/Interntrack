<?php

require_once __DIR__ . '/../includes/auth.php';
require_role(['coordinator']);

$page_title = 'Assignments';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$allowed_statuses = [
    'pending',
    'ongoing',
    'completed',
    'cancelled'
];

$sql = "
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
    WHERE 1=1
";

$params = [];
$types = '';


if ($search !== '') {

    $sql .= "
        AND (
            u.full_name LIKE ?
            OR u.email LIKE ?
            OR c.company_name LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= 'sss';
}


if (in_array($status, $allowed_statuses, true)) {

    $sql .= " AND i.status = ?";

    $params[] = $status;

    $types .= 's';
}


$sql .= "
    ORDER BY i.created_at DESC
";

$stmt = $conn->prepare($sql);

$assignments = [];

if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );

    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }

}

function assignment_count(
    mysqli $conn,
    string $condition = ''
): int {

    $sql = "SELECT COUNT(*) FROM internships";

    if ($condition !== '') {
        $sql .= " WHERE " . $condition;
    }

    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();

    return (int) ($row[0] ?? 0);
}


$total_assignments = assignment_count($conn);

$pending_assignments = assignment_count(
    $conn,
    "status = 'pending'"
);

$ongoing_assignments = assignment_count(
    $conn,
    "status = 'ongoing'"
);

$completed_assignments = assignment_count(
    $conn,
    "status = 'completed'"
);

require_once __DIR__ . '/../includes/header.php';

?>


<style>

    .filter-form {
        display: grid;
        grid-template-columns: 1.5fr 1fr auto auto;
        gap: 10px;
        margin-bottom: 20px;
    }

    .filter-form input,
    .filter-form select {
        width: 100%;
        padding: 11px 13px;
        border: 1px solid #dce2eb;
        border-radius: 8px;
        background: #fff;
        font-size: 14px;
        color: #374151;
        box-sizing: border-box;
    }

    .filter-form button,
    .clear-btn {
        border: none;
        border-radius: 8px;
        padding: 11px 16px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .filter-form button {
        background: #3677ed;
        color: #fff;
    }

    .clear-btn {
        background: #eef1f6;
        color: #49566d;
    }

    .assignment-count {
        color: #748099;
        font-size: 13px;
        margin-top: 4px;
    }

    .assignment-badge {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: #eef4ff;
        color: #3677ed;
    }

    @media (max-width: 800px) {

        .filter-form {
            grid-template-columns: 1fr;
        }

    }

</style>

<div class="cards">

    <div class="stat-card">

        <div class="stat-icon">A</div>

        <div>

            <span>Total Assignments</span>

            <strong>
                <?= $total_assignments ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">P</div>

        <div>

            <span>Pending</span>

            <strong>
                <?= $pending_assignments ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">O</div>

        <div>

            <span>Ongoing</span>

            <strong>
                <?= $ongoing_assignments ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">C</div>

        <div>

            <span>Completed</span>

            <strong>
                <?= $completed_assignments ?>
            </strong>

        </div>

    </div>

</div>

<section class="panel" style="margin-top:24px;">

    <div class="panel-head">

        <div>

            <h2>Internship Assignments</h2>

            <p>
                View and monitor student-to-company internship assignments.
            </p>

            <div class="assignment-count">

                <?= count($assignments) ?>
                assignment(s) currently shown

            </div>

        </div>


        <div class="assignment-badge">
            Coordinator View
        </div>

    </div>

    <form
        method="GET"
        class="filter-form"
    >

        <input
            type="text"
            name="search"
            placeholder="Search student or company..."
            value="<?= htmlspecialchars($search) ?>"
        >


        <select name="status">

            <option value="">
                All Statuses
            </option>

            <option
                value="pending"
                <?= $status === 'pending' ? 'selected' : '' ?>
            >
                Pending
            </option>

            <option
                value="ongoing"
                <?= $status === 'ongoing' ? 'selected' : '' ?>
            >
                Ongoing
            </option>

            <option
                value="completed"
                <?= $status === 'completed' ? 'selected' : '' ?>
            >
                Completed
            </option>

            <option
                value="cancelled"
                <?= $status === 'cancelled' ? 'selected' : '' ?>
            >
                Cancelled
            </option>

        </select>


        <button type="submit">
            Filter
        </button>


        <a
            href="/interntrack/coordinator/assignments.php"
            class="clear-btn"
        >
            Clear
        </a>

    </form>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Student</th>

                    <th>Company</th>

                    <th>Status</th>

                    <th>Start</th>

                    <th>End</th>

                    <th>Created</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($assignments): ?>

                <?php foreach ($assignments as $assignment): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $assignment['student_name']
                                ) ?>
                            </strong>

                            <div style="font-size:12px;color:#748099;margin-top:3px;">

                                <?= htmlspecialchars(
                                    $assignment['student_email']
                                ) ?>

                            </div>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $assignment['company_name']
                            ) ?>

                        </td>


                        <td>

                            <span class="badge
                                <?php
                                if ($assignment['status'] === 'completed') {
                                    echo 'approved';
                                } elseif ($assignment['status'] === 'cancelled') {
                                    echo 'rejected';
                                }
                                ?>
                            ">

                                <?= htmlspecialchars(
                                    ucfirst($assignment['status'])
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?php if ($assignment['start_date']): ?>

                                <?= htmlspecialchars(
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $assignment['start_date']
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if ($assignment['end_date']): ?>

                                <?= htmlspecialchars(
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $assignment['end_date']
                                        )
                                    )
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $assignment['created_at']
                                    )
                                )
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="empty-state"
                    >
                        No internship assignments found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php

require_once __DIR__ . '/../includes/auth.php';
require_role(['coordinator']);

$page_title = 'Students';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$allowed_statuses = [
    'active',
    'inactive',
    'pending'
];

$sql = "
    SELECT
        u.user_id,
        u.full_name,
        u.email,
        u.status,
        u.created_at,
        COUNT(i.internship_id) AS internship_count
    FROM users u
    LEFT JOIN internships i
        ON i.student_id = u.user_id
    WHERE u.role = 'student'
";

$params = [];
$types = '';

if ($search !== '') {

    $sql .= "
        AND (
            u.full_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;

    $types .= 'ss';
}


if (in_array($status, $allowed_statuses, true)) {

    $sql .= " AND u.status = ?";

    $params[] = $status;

    $types .= 's';
}


$sql .= "
    GROUP BY
        u.user_id,
        u.full_name,
        u.email,
        u.status,
        u.created_at
    ORDER BY u.created_at DESC
";

$stmt = $conn->prepare($sql);

$students = [];

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
        $students[] = $row;
    }

}

function student_count(
    mysqli $conn,
    string $condition = ''
): int {

    $sql = "
        SELECT COUNT(*)
        FROM users
        WHERE role = 'student'
    ";

    if ($condition !== '') {
        $sql .= " AND " . $condition;
    }

    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();

    return (int) ($row[0] ?? 0);
}


$total_students = student_count($conn);

$active_students = student_count(
    $conn,
    "status = 'active'"
);

$inactive_students = student_count(
    $conn,
    "status = 'inactive'"
);

$pending_students = student_count(
    $conn,
    "status = 'pending'"
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

    .student-count {
        color: #748099;
        font-size: 13px;
        margin-top: 4px;
    }

    .role-badge {
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

        <div class="stat-icon">S</div>

        <div>

            <span>Total Students</span>

            <strong>
                <?= $total_students ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">A</div>

        <div>

            <span>Active Students</span>

            <strong>
                <?= $active_students ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">I</div>

        <div>

            <span>Inactive Students</span>

            <strong>
                <?= $inactive_students ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">P</div>

        <div>

            <span>Pending Students</span>

            <strong>
                <?= $pending_students ?>
            </strong>

        </div>

    </div>

</div>

<section class="panel" style="margin-top:24px;">

    <div class="panel-head">

        <div>

            <h2>Student Management</h2>

            <p>
                View and monitor student accounts and internship records.
            </p>

            <div class="student-count">

                <?= count($students) ?>
                student(s) currently shown

            </div>

        </div>


        <div class="role-badge">

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
            placeholder="Search student name or email..."
            value="<?= htmlspecialchars($search) ?>"
        >


        <select name="status">

            <option value="">
                All Statuses
            </option>

            <option
                value="active"
                <?= $status === 'active' ? 'selected' : '' ?>
            >
                Active
            </option>

            <option
                value="inactive"
                <?= $status === 'inactive' ? 'selected' : '' ?>
            >
                Inactive
            </option>

            <option
                value="pending"
                <?= $status === 'pending' ? 'selected' : '' ?>
            >
                Pending
            </option>

        </select>


        <button type="submit">
            Filter
        </button>


        <a
            href="/interntrack/coordinator/students.php"
            class="clear-btn"
        >
            Clear
        </a>

    </form>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Name</th>

                    <th>Email</th>

                    <th>Status</th>

                    <th>Internships</th>

                    <th>Registered</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($students): ?>

                <?php foreach ($students as $student): ?>

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

                            <span class="badge
                                <?= $student['status'] === 'active'
                                    ? 'approved'
                                    : 'rejected'
                                ?>
                            ">

                                <?= htmlspecialchars(
                                    ucfirst($student['status'])
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span class="role-badge">

                                <?= (int) $student['internship_count'] ?>

                            </span>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                date(
                                    'M d, Y',
                                    strtotime(
                                        $student['created_at']
                                    )
                                )
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        class="empty-state"
                    >
                        No students found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['supervisor']);

$page_title = 'Evaluations';

$supervisor_id = (int)($_SESSION['user_id'] ?? 0);

$search = trim($_GET['search'] ?? '');
$type_filter = trim($_GET['type'] ?? '');

$summary_result = $conn->query("
    SELECT
        COUNT(*) AS total_evaluations,

        SUM(
            CASE
                WHEN e.evaluation_type = 'midterm'
                THEN 1 ELSE 0
            END
        ) AS midterm_evaluations,

        SUM(
            CASE
                WHEN e.evaluation_type = 'final'
                THEN 1 ELSE 0
            END
        ) AS final_evaluations,

        COALESCE(
            AVG(
                CASE
                    WHEN e.rating > 0
                    THEN e.rating
                    ELSE NULL
                END
            ),
            0
        ) AS average_rating

    FROM evaluations e
    JOIN internships i
        ON i.internship_id = e.internship_id
    WHERE i.supervisor_id = {$supervisor_id}
");

$summary = $summary_result->fetch_assoc();

$total_evaluations = (int)($summary['total_evaluations'] ?? 0);
$midterm_evaluations = (int)($summary['midterm_evaluations'] ?? 0);
$final_evaluations = (int)($summary['final_evaluations'] ?? 0);
$average_rating = (float)($summary['average_rating'] ?? 0);

$where = [
    "i.supervisor_id = {$supervisor_id}"
];

if ($search !== '') {
    $safe_search = $conn->real_escape_string($search);

    $where[] = "(
        u.full_name LIKE '%{$safe_search}%'
        OR u.email LIKE '%{$safe_search}%'
        OR c.company_name LIKE '%{$safe_search}%'
    )";
}

if (in_array($type_filter, ['midterm', 'final'], true)) {
    $safe_type = $conn->real_escape_string($type_filter);
    $where[] = "e.evaluation_type = '{$safe_type}'";
}

$where_sql = implode(' AND ', $where);

$evaluations_result = $conn->query("
    SELECT
        e.evaluation_id,
        e.evaluation_type,
        e.rating,
        e.comments,
        e.submitted_at,

        u.full_name AS student_name,
        u.email AS student_email,

        c.company_name

    FROM evaluations e

    JOIN internships i
        ON i.internship_id = e.internship_id

    JOIN users u
        ON u.user_id = i.student_id

    JOIN companies c
        ON c.company_id = i.company_id

    WHERE {$where_sql}

    ORDER BY e.submitted_at DESC, e.evaluation_id DESC
");

include __DIR__ . '/../includes/header.php';
?>

<style>

.evaluation-page {
    width: 100%;
}

.evaluation-page .evaluation-filter {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 220px auto;
    gap: 16px;
    align-items: end;
}

.evaluation-page .evaluation-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.evaluation-page .evaluation-field label {
    font-size: 13px;
    font-weight: 600;
}

.evaluation-page .evaluation-field input,
.evaluation-page .evaluation-field select {
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #d9dee7;
    border-radius: 8px;
    background: #fff;
    font-size: 14px;
    box-sizing: border-box;
}

.evaluation-page .evaluation-field input:focus,
.evaluation-page .evaluation-field select:focus {
    outline: none;
    border-color: #7a5cff;
    box-shadow: 0 0 0 3px rgba(122, 92, 255, 0.10);
}

.evaluation-page .evaluation-actions {
    display: flex;
    gap: 8px;
}

.evaluation-page .evaluation-actions button,
.evaluation-page .evaluation-actions a {
    min-height: 42px;
    padding: 10px 18px;
    border-radius: 8px;
    border: 1px solid transparent;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.evaluation-page .evaluation-actions button {
    background: #007bff;
    color: #fff;
}

.evaluation-page .evaluation-actions button:hover {
    opacity: 0.9;
}

.evaluation-page .evaluation-actions a {
    background: #f4f5f7;
    color: #333;
    border-color: #dfe2e7;
}

.evaluation-page .evaluation-actions a:hover {
    background: #e9ebef;
}

.evaluation-page .evaluation-table {
    width: 100%;
    min-width: 1050px;
}

.evaluation-page .evaluation-table th,
.evaluation-page .evaluation-table td {
    vertical-align: middle;
}

.evaluation-page .evaluation-table .student-name {
    font-weight: 600;
}

.evaluation-page .evaluation-table .email {
    white-space: nowrap;
}

.evaluation-page .evaluation-table .comments {
    max-width: 280px;
    white-space: normal;
    line-height: 1.45;
}

.evaluation-page .evaluation-table .rating {
    font-weight: 600;
    white-space: nowrap;
}

.evaluation-page .type-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
}

.evaluation-page .empty-state {
    padding: 35px 20px;
    text-align: center;
    color: #777;
}

@media (max-width: 900px) {
    .evaluation-page .evaluation-filter {
        grid-template-columns: 1fr;
    }

    .evaluation-page .evaluation-actions {
        justify-content: flex-start;
    }
}
</style>

<div class="evaluation-page">

    <div class="cards">

        <div class="stat-card">
            <div class="stat-icon">E</div>
            <div>
                <span>Total Evaluations</span>
                <strong><?php echo $total_evaluations; ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">M</div>
            <div>
                <span>Midterm Evaluations</span>
                <strong><?php echo $midterm_evaluations; ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">F</div>
            <div>
                <span>Final Evaluations</span>
                <strong><?php echo $final_evaluations; ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">R</div>
            <div>
                <span>Average Rating</span>
                <strong><?php echo number_format($average_rating, 2); ?></strong>
            </div>
        </div>

    </div>

    <div class="panel">

        <div class="panel-head">
            <div>
                <h2>Filter Evaluations</h2>
                <p>Search by student or company, or filter by evaluation type.</p>
            </div>
        </div>

        <form method="GET">

            <div class="evaluation-filter">

                <div class="evaluation-field">
                    <label for="search">Search</label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        placeholder="Student name, email, or company"
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>


                <div class="evaluation-field">
                    <label for="type">Evaluation Type</label>

                    <select id="type" name="type">

                        <option value="">All Types</option>

                        <option
                            value="midterm"
                            <?php echo $type_filter === 'midterm' ? 'selected' : ''; ?>
                        >
                            Midterm
                        </option>

                        <option
                            value="final"
                            <?php echo $type_filter === 'final' ? 'selected' : ''; ?>
                        >
                            Final
                        </option>

                    </select>
                </div>


                <div class="evaluation-actions">
                    <button type="submit">Filter</button>

                    <a href="evaluations.php">
                        Reset
                    </a>
                </div>

            </div>

        </form>

    </div>

    <div class="panel">

        <div class="panel-head">
            <div>
                <h2>Submitted Evaluations</h2>
                <p>
                    Evaluation records submitted for your assigned interns.
                </p>
            </div>
        </div>


        <div class="table-wrap">

            <table class="evaluation-table">

                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Type</th>
                        <th>Rating</th>
                        <th>Comments</th>
                        <th>Submitted</th>
                    </tr>
                </thead>


                <tbody>

                    <?php if ($evaluations_result && $evaluations_result->num_rows > 0): ?>

                        <?php while ($evaluation = $evaluations_result->fetch_assoc()): ?>

                            <tr>

                                <td class="student-name">
                                    <?php echo htmlspecialchars($evaluation['student_name']); ?>
                                </td>

                                <td class="email">
                                    <?php echo htmlspecialchars($evaluation['student_email']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($evaluation['company_name']); ?>
                                </td>

                                <td>
                                    <span class="type-badge">
                                        <?php echo ucfirst(htmlspecialchars($evaluation['evaluation_type'])); ?>
                                    </span>
                                </td>

                                <td class="rating">
                                    <?php
                                    $rating = (float)($evaluation['rating'] ?? 0);

                                    if ($rating > 0) {
                                        echo number_format($rating, 2);
                                    } else {
                                        echo 'Not rated';
                                    }
                                    ?>
                                </td>

                                <td class="comments">
                                    <?php
                                    $comments = trim($evaluation['comments'] ?? '');

                                    if ($comments !== '') {
                                        echo htmlspecialchars($comments);
                                    } else {
                                        echo 'No comments';
                                    }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    if (!empty($evaluation['submitted_at'])) {
                                        echo date(
                                            'M d, Y h:i A',
                                            strtotime($evaluation['submitted_at'])
                                        );
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    No evaluation records found.
                                </div>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
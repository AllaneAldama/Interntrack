<?php

require_once __DIR__ . '/../includes/auth.php';
require_role(['coordinator']);

$page_title = 'Companies';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$allowed_statuses = [
    'active',
    'inactive',
    'pending'
];

$sql = "
    SELECT
        c.company_id,
        c.company_name,
        c.status,
        COUNT(i.internship_id) AS internship_count
    FROM companies c
    LEFT JOIN internships i
        ON i.company_id = c.company_id
    WHERE 1=1
";

$params = [];
$types = '';


if ($search !== '') {

    $sql .= "
        AND c.company_name LIKE ?
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;

    $types .= 's';
}


if (in_array($status, $allowed_statuses, true)) {

    $sql .= " AND c.status = ?";

    $params[] = $status;

    $types .= 's';
}


$sql .= "
    GROUP BY
        c.company_id,
        c.company_name,
        c.status
    ORDER BY c.company_name ASC
";

$stmt = $conn->prepare($sql);

$companies = [];

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
        $companies[] = $row;
    }

}

function company_count(
    mysqli $conn,
    string $condition = ''
): int {

    $sql = "SELECT COUNT(*) FROM companies";

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


$total_companies = company_count($conn);

$active_companies = company_count(
    $conn,
    "status = 'active'"
);

$inactive_companies = company_count(
    $conn,
    "status = 'inactive'"
);

$pending_companies = company_count(
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

    .company-count {
        color: #748099;
        font-size: 13px;
        margin-top: 4px;
    }

    .company-badge {
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

        <div class="stat-icon">C</div>

        <div>

            <span>Total Companies</span>

            <strong>
                <?= $total_companies ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">A</div>

        <div>

            <span>Active Companies</span>

            <strong>
                <?= $active_companies ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">I</div>

        <div>

            <span>Inactive Companies</span>

            <strong>
                <?= $inactive_companies ?>
            </strong>

        </div>

    </div>


    <div class="stat-card">

        <div class="stat-icon">P</div>

        <div>

            <span>Pending Companies</span>

            <strong>
                <?= $pending_companies ?>
            </strong>

        </div>

    </div>

</div>

<section class="panel" style="margin-top:24px;">

    <div class="panel-head">

        <div>

            <h2>Company Management</h2>

            <p>
                View and monitor partner companies registered in InternTrack.
            </p>

            <div class="company-count">

                <?= count($companies) ?>
                company(s) currently shown

            </div>

        </div>

        <div class="company-badge">
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
            placeholder="Search company name..."
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
            href="/interntrack/coordinator/companies.php"
            class="clear-btn"
        >
            Clear
        </a>

    </form>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Company</th>

                    <th>Status</th>

                    <th>Internships</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($companies): ?>

                <?php foreach ($companies as $company): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $company['company_name']
                                ) ?>
                            </strong>

                        </td>


                        <td>

                            <span class="badge
                                <?= $company['status'] === 'active'
                                    ? 'approved'
                                    : 'rejected'
                                ?>
                            ">

                                <?= htmlspecialchars(
                                    ucfirst($company['status'])
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <span class="company-badge">

                                <?= (int) $company['internship_count'] ?>

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="3"
                        class="empty-state"
                    >
                        No companies found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
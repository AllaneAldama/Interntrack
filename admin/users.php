<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'User Management';

$search = trim($_GET['search'] ?? '');
$role   = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$allowed_roles    = ['student', 'coordinator', 'supervisor', 'admin'];
$allowed_statuses = ['active', 'inactive', 'pending'];

$sql    = "SELECT user_id, full_name, email, role, status, created_at FROM users WHERE 1=1";
$params = [];
$types  = '';

if ($search !== '') {
    $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $search_value = '%' . $search . '%';
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= 'ss';
}

if (in_array($role, $allowed_roles, true)) {
    $sql .= " AND role = ?";
    $params[] = $role;
    $types .= 's';
}

if (in_array($status, $allowed_statuses, true)) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$users = [];
if ($stmt = $conn->prepare($sql)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function get_user_count(mysqli $conn, string $condition = ''): int {
    $sql = "SELECT COUNT(*) FROM users" . ($condition !== '' ? " WHERE $condition" : '');
    $res = $conn->query($sql);
    return $res ? (int) ($res->fetch_row()[0] ?? 0) : 0;
}

$total_users       = get_user_count($conn);
$active_users      = get_user_count($conn, "status = 'active'");
$student_users     = get_user_count($conn, "role = 'student'");
$coordinator_users = get_user_count($conn, "role = 'coordinator'");
$supervisor_users  = get_user_count($conn, "role = 'supervisor'");
$admin_users       = get_user_count($conn, "role = 'admin'");

$stats = [
    ['label' => 'Total Users', 'value' => $total_users, 'icon' => 'U'],
    ['label' => 'Active Users', 'value' => $active_users, 'icon' => 'A'],
    ['label' => 'Students', 'value' => $student_users, 'icon' => 'S'],
    ['label' => 'Coordinators', 'value' => $coordinator_users, 'icon' => 'C']
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .page-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .filter-form { display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; gap: 10px; margin-bottom: 20px; }
    .filter-form input, .filter-form select { width: 100%; padding: 11px 13px; border: 1px solid #dce2eb; border-radius: 8px; background: #fff; font-size: 14px; color: #374151; box-sizing: border-box; }
    .filter-form button, .clear-btn { border: none; border-radius: 8px; padding: 11px 16px; cursor: pointer; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; }
    .filter-form button { background: #3677ed; color: #fff; }
    .clear-btn { background: #eef1f6; color: #49566d; }
    .user-count { color: #748099; font-size: 13px; margin-top: 4px; }
    .role-badge { display: inline-block; padding: 5px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #eef4ff; color: #3677ed; }
    .action-link { text-decoration: none; font-size: 13px; color: #3677ed; font-weight: 600; }
    @media (max-width: 900px) { .filter-form { grid-template-columns: 1fr; } }
</style>

<div class="cards">
<?php foreach ($stats as $stat): ?>
    <div class="stat-card">
        <div class="stat-icon"><?= $stat['icon'] ?></div>
        <div>
            <span><?= htmlspecialchars($stat['label']) ?></span>
            <strong><?= $stat['value'] ?></strong>
        </div>
    </div>
<?php endforeach; ?>
</div>

<section class="panel" style="margin-top: 24px;">
    <div class="panel-head">
        <div>
            <h2>User Management</h2>
            <p>View and monitor all InternTrack user accounts.</p>
            <div class="user-count"><?= count($users) ?> user(s) currently shown</div>
        </div>

        <div class="page-actions">
            <div class="role-badge"><?= $supervisor_users ?> Supervisors</div>
            <div class="role-badge"><?= $admin_users ?> Admins</div>
        </div>
    </div>

    <form method="GET" class="filter-form">
        <input type="text" name="search" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">

        <select name="role">
            <option value="">All Roles</option>
            <?php foreach ($allowed_roles as $r): ?>
                <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach ($allowed_statuses as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filter</button>
        <a href="/interntrack/admin/users.php" class="clear-btn">Clear</a>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php if ($users): foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class="role-badge"><?= htmlspecialchars(ucfirst($user['role'])) ?></span></td>
                    <td>
                        <span class="badge <?= $user['status'] === 'active' ? 'approved' : 'rejected' ?>">
                            <?= htmlspecialchars(ucfirst($user['status'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(date('M d, Y', strtotime($user['created_at']))) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" class="empty-state">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
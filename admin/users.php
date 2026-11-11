<?php

require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'User Management';
$creatable_roles = ['coordinator', 'supervisor', 'admin'];
$allowed_roles = ['student', 'coordinator', 'supervisor', 'admin'];
$allowed_statuses = ['active', 'inactive'];
$password_minimum = max(8, (int) $system_settings['minimum_password_length']);
$action_error = '';
$action_notice = $_SESSION['user_action_notice'] ?? '';
unset($_SESSION['user_action_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form submission. Refresh the page and try again.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $full_name = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $role_value = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
    $student_number = is_string($_POST['student_number'] ?? null) ? trim($_POST['student_number']) : '';
    $status_value = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
    $user_id_raw = $_POST['user_id'] ?? null;
    $user_id = is_string($user_id_raw)
        ? filter_var($user_id_raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($action === 'create') {
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($full_name === '' || strlen($full_name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 150 || !in_array($role_value, $creatable_roles, true)
            || $student_number !== ''
            || strlen($password) < $password_minimum) {
            $action_error = 'Enter a valid name and email, choose a coordinator, supervisor, or admin role, and set a password of at least ' . $password_minimum . ' characters.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $create_stmt = $conn->prepare("
                INSERT INTO users (full_name, email, password_hash, role)
                VALUES (?, ?, ?, ?)
            ");
            $create_stmt->bind_param('ssss', $full_name, $email, $password_hash, $role_value);
            if ($create_stmt->execute()) {
                log_activity($conn, (int) $_SESSION['user_id'], 'Created ' . $role_value . ' account');
                $_SESSION['user_action_notice'] = 'User account created.';
                header('Location: users.php');
                exit;
            }
            $action_error = 'Could not create the account. Check that the email and student number are not already in use.';
        }
    } elseif ($action === 'update') {
        $new_password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        if ($user_id === false || $full_name === '' || strlen($full_name) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150
            || !in_array($status_value, ['active', 'inactive'], true)
            || strlen($student_number) > 30
            || ($new_password !== '' && strlen($new_password) < $password_minimum)
            || ($user_id === (int) $_SESSION['user_id'] && $status_value !== 'active')) {
            $action_error = 'Enter valid account details and a password of at least ' . $password_minimum . ' characters if resetting it. You cannot deactivate your own account.';
        } else {
            $number_value = $student_number === '' ? null : $student_number;
            if ($new_password === '') {
                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, student_number = ?, status = ?
                    WHERE user_id = ?
                ");
                $update_stmt->bind_param('ssssi', $full_name, $email, $number_value, $status_value, $user_id);
            } else {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, student_number = ?, status = ?, password_hash = ?
                    WHERE user_id = ?
                ");
                $update_stmt->bind_param('sssssi', $full_name, $email, $number_value, $status_value, $password_hash, $user_id);
            }
            if ($update_stmt->execute()) {
                if ($new_password !== '') {
                    revoke_user_remember_tokens($conn, $user_id);
                }
                log_activity($conn, (int) $_SESSION['user_id'], 'Updated user account #' . $user_id);
                if ($user_id === (int) $_SESSION['user_id']) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                }
                $_SESSION['user_action_notice'] = 'User account updated.';
                header('Location: users.php');
                exit;
            }
            $action_error = 'Could not update the account. Check that the email and student number are not already in use.';
        }
    } else {
        $action_error = 'Choose a valid user action.';
    }
}

$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "
    SELECT user_id, student_number, full_name, email, role, status, created_at
    FROM users
    WHERE 1=1
";

$params = [];
$types = '';

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

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}

$users = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

function get_user_count(mysqli $conn, string $condition = ''): int
{
    $sql = "SELECT COUNT(*) FROM users";

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

$total_users = get_user_count($conn);
$active_users = get_user_count($conn, "status = 'active'");
$inactive_users = get_user_count($conn, "status = 'inactive'");
$coordinator_users = get_user_count($conn, "role = 'coordinator'");
$supervisor_users = get_user_count($conn, "role = 'supervisor'");
$admin_users = get_user_count($conn, "role = 'admin'");

require_once __DIR__ . '/../includes/header.php';

?>

<style>
    .page-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filter-form {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr auto;
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

    .user-count {
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

    .action-link {
        text-decoration: none;
        font-size: 13px;
        color: #3677ed;
        font-weight: 600;
    }

    .user-stat-cards {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    @media (max-width: 900px) {
        .user-stat-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .filter-form {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 650px) {
        .user-stat-cards {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="cards user-stat-cards">

    <div class="stat-card">
        <div class="stat-icon">U</div>
        <div>
            <span>Total Users</span>
            <strong><?= $total_users ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">A</div>
        <div>
            <span>Active Users</span>
            <strong><?= $active_users ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">I</div>
        <div>
            <span>Inactive Users</span>
            <strong><?= $inactive_users ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">S</div>
        <div>
            <span>Supervisors</span>
            <strong><?= $supervisor_users ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">A</div>
        <div>
            <span>Administrators</span>
            <strong><?= $admin_users ?></strong>
        </div>
    </div>

</div>

<section class="panel" style="margin-top: 24px;">

    <div class="panel-head">
        <div>
            <h2>User Management</h2>
            <p>View and monitor all InternTrack user accounts.</p>
        </div>

    </div>

    <?php if ($action_notice !== ''): ?><div class="alert success"><?= htmlspecialchars($action_notice) ?></div><?php endif; ?>
    <?php if ($action_error !== ''): ?><div class="alert"><?= htmlspecialchars($action_error) ?></div><?php endif; ?>

    <details class="crud-editor" open>
        <summary>Create user account</summary>
        <br>
        <form method="POST" class="crud-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <label>Full name<input name="full_name" maxlength="150" required></label>
            <label>Email<input type="email" name="email" maxlength="150" required></label>
            <label>Role<select name="role" required><option value="coordinator">Coordinator</option><option value="supervisor">Supervisor</option><option value="admin">Admin</option></select></label>
            <label>Temporary password<input type="password" name="password" minlength="<?= $password_minimum ?>" required autocomplete="new-password"></label>
            <button class="btn primary" type="submit">Create account</button>
        </form>
    </details>
    <br>
    <form method="GET" class="filter-form">
        <input
            type="text"
            name="search"
            placeholder="Search name or email..."
            value="<?= htmlspecialchars($search) ?>"
        >

        <select name="role">
            <option value="">All Roles</option>
            <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>
                Student
            </option>
            <option value="coordinator" <?= $role === 'coordinator' ? 'selected' : '' ?>>
                Coordinator
            </option>
            <option value="supervisor" <?= $role === 'supervisor' ? 'selected' : '' ?>>
                Supervisor
            </option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>
                Admin
            </option>
        </select>

        <select name="status">
            <option value="">All Statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>
                Active
            </option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>
                Inactive
            </option>
        </select>

        <button type="submit">
            Filter
        </button>

        <a href="/interntrack/admin/users.php" class="clear-btn">
            Clear
        </a>

    </form>

    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Student Number</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Manage</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($users): ?>

                <?php foreach ($users as $user): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($user['full_name']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($user['email']) ?>
                        </td>

                        <td><?= htmlspecialchars($user['student_number'] ?? '—') ?></td>

                        <td>
                            <span class="role-badge">
                                <?= htmlspecialchars(ucfirst($user['role'])) ?>
                            </span>
                        </td>

                        <td>

                            <span class="badge <?= $user['status'] === 'active' ? 'approved' : 'rejected' ?>">
                                <?= htmlspecialchars(ucfirst($user['status'])) ?>
                            </span>

                        </td>

                        <td>
                            <?= htmlspecialchars(format_system_date($user['created_at'], $system_settings)) ?>
                        </td>

                        <td>
                            <details class="crud-editor">
                                <summary>Edit</summary>
                                <form method="POST" class="crud-form stacked">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                    <label>Name<input name="full_name" maxlength="150" required value="<?= htmlspecialchars($user['full_name']) ?>"></label>
                                    <label>Email<input type="email" name="email" maxlength="150" required value="<?= htmlspecialchars($user['email']) ?>"></label>
                                    <label>Student number<input name="student_number" maxlength="30" value="<?= htmlspecialchars($user['student_number'] ?? '') ?>"></label>
                                    <label>Status<select name="status"><option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
                                    <label>Reset password (optional)<input type="password" name="new_password" minlength="<?= $password_minimum ?>" autocomplete="new-password"></label>
                                    <button class="btn primary" type="submit">Save changes</button>
                                </form>
                            </details>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="7" class="empty-state">
                        No users found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
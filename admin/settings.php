<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

$page_title = 'Settings';
$admin_id = (int) $_SESSION['user_id'];
$error = '';
$notice = $_SESSION['settings_notice'] ?? '';
unset($_SESSION['settings_notice']);

$admin_stmt = $conn->prepare("
    SELECT full_name, email, password_hash
    FROM users
    WHERE user_id = ? AND role = 'admin'
    LIMIT 1
");
$admin_stmt->bind_param('i', $admin_id);
$admin_stmt->execute();
$admin_account = $admin_stmt->get_result()->fetch_assoc();
$password_minimum = max(8, (int) $system_settings['minimum_password_length']);
$timezones = DateTimeZone::listIdentifiers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid form submission. Refresh the page and try again.');
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    if ($action === 'profile') {
        $full_name = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

        if ($full_name === '' || strlen($full_name) > 150
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $error = 'Enter a valid name and email address.';
        } else {
            $stmt = $conn->prepare("
                UPDATE users SET full_name = ?, email = ?
                WHERE user_id = ? AND role = 'admin'
            ");
            $stmt->bind_param('ssi', $full_name, $email, $admin_id);
            if ($stmt->execute()) {
                log_activity($conn, $admin_id, 'Updated administrator profile');
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['settings_notice'] = 'Profile information updated.';
                header('Location: settings.php');
                exit;
            }
            $error = 'Could not update your profile. The email may already be in use.';
        }
    } elseif ($action === 'password') {
        $current_password = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new_password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm_password = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        if (!$admin_account || !password_verify($current_password, $admin_account['password_hash'])) {
            $error = 'The current password is incorrect.';
        } elseif (strlen($new_password) < $password_minimum || $new_password !== $confirm_password) {
            $error = 'The new password must be at least ' . $password_minimum . ' characters and both entries must match.';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ? AND role = 'admin'");
            $stmt->bind_param('si', $hash, $admin_id);
            if ($stmt->execute()) {
                revoke_user_remember_tokens($conn, $admin_id);
                clear_remember_me_token($conn);
                log_activity($conn, $admin_id, 'Changed administrator password');
                $_SESSION['settings_notice'] = 'Password changed.';
                header('Location: settings.php');
                exit;
            }
            $error = 'Could not update your password.';
        }
    } elseif ($action === 'general') {
        $name = is_string($_POST['system_name'] ?? null) ? trim($_POST['system_name']) : '';
        $description = is_string($_POST['system_description'] ?? null) ? trim($_POST['system_description']) : '';
        $contact_email = is_string($_POST['contact_email'] ?? null) ? trim($_POST['contact_email']) : '';
        $timezone = is_string($_POST['system_timezone'] ?? null) ? $_POST['system_timezone'] : '';
        $date_format = is_string($_POST['date_format'] ?? null) ? $_POST['date_format'] : '';
        $time_format = is_string($_POST['time_format'] ?? null) ? $_POST['time_format'] : '';
        $remove_logo = ($_POST['remove_logo'] ?? '') === '1';

        if ($name === '' || strlen($name) > 100 || strlen($description) > 500
            || ($contact_email !== '' && (!filter_var($contact_email, FILTER_VALIDATE_EMAIL) || strlen($contact_email) > 150))
            || !in_array($timezone, $timezones, true)
            || !in_array($date_format, ['M d, Y', 'Y-m-d', 'd/m/Y'], true)
            || !in_array($time_format, ['h:i A', 'H:i'], true)) {
            $error = 'Check the system name, contact email, timezone, and date/time formats.';
        } else {
            $logo_path = $system_settings['system_logo'];
            $file = $_FILES['system_logo'] ?? null;

            if ($remove_logo) {
                $logo_path = '';
            } elseif (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                    || !isset($file['size'], $file['tmp_name'])
                    || !is_int($file['size']) || $file['size'] < 1 || $file['size'] > 2 * 1024 * 1024) {
                    $error = 'Choose a system logo no larger than 2 MB.';
                } elseif (!class_exists('finfo')) {
                    $error = 'File type checking is unavailable. Enable PHP fileinfo to upload a logo.';
                } else {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                    $extensions = [
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/webp' => 'webp'
                    ];
                    if (!is_string($mime) || !isset($extensions[$mime])) {
                        $error = 'The logo must be a PNG, JPG, or WebP image.';
                    } else {
                        $upload_dir = __DIR__ . '/../assets/uploads/system';
                        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0750, true) && !is_dir($upload_dir)) {
                            $error = 'The logo upload directory could not be created.';
                        } else {
                            $filename = bin2hex(random_bytes(24)) . '.' . $extensions[$mime];
                            if (!is_uploaded_file($file['tmp_name'])
                                || !move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $filename)) {
                                $error = 'The system logo could not be saved.';
                            } else {
                                $logo_path = 'assets/uploads/system/' . $filename;
                            }
                        }
                    }
                }
            }

            if ($error === '') {
                $values = [
                    'system_name' => $name,
                    'system_description' => $description,
                    'system_logo' => $logo_path,
                    'contact_email' => $contact_email,
                    'system_timezone' => $timezone,
                    'date_format' => $date_format,
                    'time_format' => $time_format
                ];
                foreach ($values as $key => $value) {
                    save_system_setting($conn, $key, $value, $admin_id);
                    $system_settings[$key] = $value;
                }
                log_activity($conn, $admin_id, 'Updated general system settings');
                $_SESSION['settings_notice'] = 'General system settings updated.';
                header('Location: settings.php');
                exit;
            }
        }
    } elseif ($action === 'security') {
        $minimum_password = is_string($_POST['minimum_password_length'] ?? null) ? $_POST['minimum_password_length'] : '';
        $timeout = is_string($_POST['session_timeout_minutes'] ?? null) ? $_POST['session_timeout_minutes'] : '';
        $attempt_limit = is_string($_POST['login_attempt_limit'] ?? null) ? $_POST['login_attempt_limit'] : '';
        $lockout = is_string($_POST['login_lockout_minutes'] ?? null) ? $_POST['login_lockout_minutes'] : '';
        $automatic_logout = is_string($_POST['automatic_logout'] ?? null) ? $_POST['automatic_logout'] : '';

        if (!in_array($minimum_password, ['8', '10', '12', '14'], true)
            || !in_array($timeout, ['5', '10', '15', '30', '60', '120', '240'], true)
            || !in_array($attempt_limit, ['3', '5', '10'], true)
            || !in_array($lockout, ['5', '15', '30', '60'], true)
            || !in_array($automatic_logout, ['0', '1'], true)) {
            $error = 'Choose valid security option values.';
        } else {
            $values = [
                'minimum_password_length' => $minimum_password,
                'session_timeout_minutes' => $timeout,
                'login_attempt_limit' => $attempt_limit,
                'login_lockout_minutes' => $lockout,
                'automatic_logout' => $automatic_logout
            ];
            foreach ($values as $key => $value) {
                save_system_setting($conn, $key, $value, $admin_id);
                $system_settings[$key] = $value;
            }
            log_activity($conn, $admin_id, 'Updated security settings');
            $_SESSION['settings_notice'] = 'Security settings updated.';
            header('Location: settings.php');
            exit;
        }
    } elseif ($action === 'maintenance') {
        $maintenance_mode = is_string($_POST['maintenance_mode'] ?? null) ? $_POST['maintenance_mode'] : '';
        if (!in_array($maintenance_mode, ['0', '1'], true)) {
            $error = 'Choose a valid maintenance mode setting.';
        } else {
            save_system_setting($conn, 'maintenance_mode', $maintenance_mode, $admin_id);
            $system_settings['maintenance_mode'] = $maintenance_mode;
            log_activity($conn, $admin_id, $maintenance_mode === '1'
                ? 'Enabled maintenance mode'
                : 'Disabled maintenance mode');
            $_SESSION['settings_notice'] = $maintenance_mode === '1'
                ? 'Maintenance mode enabled. Only admins can access the application.'
                : 'Maintenance mode disabled.';
            header('Location: settings.php');
            exit;
        }
    } else {
        $error = 'Choose a valid settings action.';
    }
}

$logs_stmt = $conn->prepare("
    SELECT COALESCE(u.full_name, 'System') AS full_name, al.action, al.created_at
    FROM activity_logs al
    LEFT JOIN users u ON u.user_id = al.user_id
    ORDER BY al.created_at DESC, al.log_id DESC
    LIMIT 50
");
$logs_stmt->execute();
$system_logs = $logs_stmt->get_result();
$mysql_version = $conn->server_info;

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($notice !== ''): ?><div class="alert success"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="panel settings-section">
    <div class="settings-section-title">
        <span class="settings-section-letter">A</span>
        <div><h2>Account Settings</h2><p>Settings related to the administrator's account.</p></div>
    </div>

    <div class="settings-subsection">
        <h3>Update Profile Information</h3>
        <form method="POST" class="crud-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="profile">
            <label>Admin Name<input name="full_name" maxlength="150" required value="<?= htmlspecialchars($admin_account['full_name'] ?? '') ?>"></label>
            <label>Email Address<input type="email" name="email" maxlength="150" required value="<?= htmlspecialchars($admin_account['email'] ?? '') ?>"></label>
            <button class="btn primary" type="submit">Update profile information</button>
        </form>
    </div>

    <div class="settings-subsection">
        <h3>Change Password</h3>
        <p>Password must be at least <?= $password_minimum ?> characters.</p>
        <form method="POST" class="crud-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">
            <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>New password<input type="password" name="new_password" minlength="<?= $password_minimum ?>" required autocomplete="new-password"></label>
            <label>Confirm new password<input type="password" name="confirm_password" minlength="<?= $password_minimum ?>" required autocomplete="new-password"></label>
            <br>
            <button class="btn primary" type="submit">Change password</button>
        </form>
    </div>
</section>

<section class="panel settings-section">
    <div class="settings-section-title">
        <span class="settings-section-letter">B</span>
        <div><h2>General System Settings</h2><p>Basic application configuration.</p></div>
    </div>
    <form method="POST" enctype="multipart/form-data" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="general">
        <label>System Name<input name="system_name" maxlength="100" required value="<?= htmlspecialchars($system_settings['system_name']) ?>"></label>
        <label>Contact Email<input type="email" name="contact_email" maxlength="150" value="<?= htmlspecialchars($system_settings['contact_email']) ?>"></label>
        <label class="form-wide">System Description<textarea name="system_description" maxlength="500" rows="3"><?= htmlspecialchars($system_settings['system_description']) ?></textarea></label>
        <label>System Logo (PNG, JPG, WebP; max 2 MB)<input type="file" name="system_logo" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"></label>
        <label>System Time Zone<select name="system_timezone" required><?php foreach ($timezones as $timezone): ?><option value="<?= htmlspecialchars($timezone) ?>" <?= $system_settings['system_timezone'] === $timezone ? 'selected' : '' ?>><?= htmlspecialchars($timezone) ?></option><?php endforeach; ?></select></label>
        <label>Date Format<select name="date_format"><option value="M d, Y" <?= $system_settings['date_format'] === 'M d, Y' ? 'selected' : '' ?>>Oct 27, 2026</option><option value="Y-m-d" <?= $system_settings['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>2026-10-27</option><option value="d/m/Y" <?= $system_settings['date_format'] === 'd/m/Y' ? 'selected' : '' ?>>27/10/2026</option></select></label>
        <label>Time Format<select name="time_format"><option value="h:i A" <?= $system_settings['time_format'] === 'h:i A' ? 'selected' : '' ?>>12-hour (3:30 PM)</option><option value="H:i" <?= $system_settings['time_format'] === 'H:i' ? 'selected' : '' ?>>24-hour (15:30)</option></select></label>
        <?php if ($system_settings['system_logo'] !== ''): ?>
            <div class="form-wide current-logo">
                <span>Current logo</span>
                <img class="system-logo-preview" src="/interntrack/<?= htmlspecialchars($system_settings['system_logo']) ?>" alt="Current system logo">
                <label class="inline-check"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
            </div>
        <?php endif; ?>
        <button class="btn primary" type="submit">Save general settings</button>
    </form>
</section>

<section class="panel settings-section">
    <div class="settings-section-title">
        <span class="settings-section-letter">C</span>
        <div><h2>Security Settings</h2><p>Options for improving application security.</p></div>
    </div>
    <p class="settings-note">Account verification is omitted as requested. This app has no self-registration or email delivery workflow.</p>
    <form method="POST" class="crud-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="security">
        <label>Password Requirements
            <select name="minimum_password_length">
                <?php foreach (['8', '10', '12', '14'] as $length): ?>
                    <option value="<?= $length ?>" <?= $system_settings['minimum_password_length'] === $length ? 'selected' : '' ?>>At least <?= $length ?> characters</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Session Timeout
            <select name="session_timeout_minutes">
                <?php foreach (['5', '10', '15', '30', '60', '120', '240'] as $minutes): ?>
                    <option value="<?= $minutes ?>" <?= $system_settings['session_timeout_minutes'] === $minutes ? 'selected' : '' ?>><?= $minutes ?> minutes</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Login Attempt Limits
            <select name="login_attempt_limit">
                <?php foreach (['3', '5', '10'] as $limit): ?>
                    <option value="<?= $limit ?>" <?= $system_settings['login_attempt_limit'] === $limit ? 'selected' : '' ?>><?= $limit ?> attempts</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Login Lockout Duration
            <select name="login_lockout_minutes">
                <?php foreach (['5', '15', '30', '60'] as $minutes): ?>
                    <option value="<?= $minutes ?>" <?= $system_settings['login_lockout_minutes'] === $minutes ? 'selected' : '' ?>><?= $minutes ?> minutes</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Automatic Logout
            <select name="automatic_logout"><option value="1" <?= $system_settings['automatic_logout'] === '1' ? 'selected' : '' ?>>Enabled</option><option value="0" <?= $system_settings['automatic_logout'] === '0' ? 'selected' : '' ?>>Disabled</option></select>
        </label>
        <button class="btn primary" type="submit">Save security settings</button>
    </form>
</section>

<section class="panel settings-section">
    <div class="settings-section-title">
        <span class="settings-section-letter">D</span>
        <div><h2>System Maintenance</h2><p>Basic maintenance and monitoring options.</p></div>
    </div>

    <div class="settings-subsection">
        <h3>Database Backup</h3>
        <p>Download a SQL backup of the application database. Uploaded documents are not included.</p>
        <form method="POST" action="database-backup.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button class="btn primary" type="submit">Generate and download backup</button>
        </form>
        <p class="settings-note">Last backup: <?= $system_settings['last_backup_at'] !== '' ? htmlspecialchars(format_system_datetime($system_settings['last_backup_at'], $system_settings)) : 'No backup has been generated.' ?></p>
    </div>

    <div class="settings-subsection">
        <h3>System Information</h3>
        <div class="info-grid">
            <div><small>Application Version</small><strong><?= htmlspecialchars($system_settings['application_version']) ?></strong></div>
            <div><small>PHP Version</small><strong><?= htmlspecialchars(PHP_VERSION) ?></strong></div>
            <div><small>MySQL Version</small><strong><?= htmlspecialchars($mysql_version) ?></strong></div>
            <div><small>Time Zone</small><strong><?= htmlspecialchars($system_settings['system_timezone']) ?></strong></div>
        </div>
    </div>

    <div class="settings-subsection">
        <h3>Maintenance Mode</h3>
        <p>When enabled, non-admin users cannot sign in or use the system. Admins retain access.</p>
        <form method="POST" class="maintenance-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="maintenance">
            <label>Maintenance mode<select name="maintenance_mode"><option value="0" <?= $system_settings['maintenance_mode'] === '0' ? 'selected' : '' ?>>Disabled</option><option value="1" <?= $system_settings['maintenance_mode'] === '1' ? 'selected' : '' ?>>Enabled (admin only)</option></select></label>
            <button class="btn primary" type="submit">Save maintenance setting</button>
        </form>
    </div>

    <div class="settings-subsection">
        <h3>System Logs</h3>
        <p>Latest 50 application activity events.</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>User</th><th>Activity</th><th>Date and time</th></tr></thead>
                <tbody>
                    <?php if ($system_logs->num_rows > 0): ?>
                        <?php while ($log = $system_logs->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['full_name']) ?></td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars(format_system_datetime($log['created_at'], $system_settings)) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="empty-state">No system logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<style>
.settings-section{margin-bottom:24px}
.settings-section-title{display:flex;align-items:flex-start;gap:14px;margin-bottom:22px}
.settings-section-title h2{margin:0 0 6px}
.settings-section-title p,.settings-subsection>p{color:#748099;margin:0}
.settings-section-letter{display:grid;place-items:center;min-width:40px;height:40px;border-radius:11px;background:#eef4ff;color:#3677ed;font-size:18px;font-weight:700}
.settings-subsection{padding:20px 0;border-top:1px solid #edf0f5}
.settings-subsection:first-of-type{border-top:0;padding-top:0}
.settings-subsection h3{margin:0 0 12px}
.settings-subsection>p{margin:0 0 14px}
.form-wide{grid-column:1/-1}
.inline-check{display:flex!important;flex-direction:row!important;align-items:center;margin-top:12px}
.inline-check input{width:auto}
.system-logo-preview{display:block;max-width:180px;max-height:90px;object-fit:contain;margin:10px 0}
.settings-note{padding:12px;background:#f7f9fc;border-radius:8px;color:#596579}
.maintenance-form{display:flex;gap:16px;align-items:end;flex-wrap:wrap}
.maintenance-form label{min-width:220px}
@media(max-width:650px){.form-wide{grid-column:auto}.maintenance-form{display:grid}}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

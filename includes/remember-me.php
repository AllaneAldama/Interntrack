<?php

const REMEMBER_ME_COOKIE = 'interntrack_remember';
const REMEMBER_ME_LIFETIME = 2592000;

function remember_me_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/interntrack',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
}

function parse_remember_me_cookie(): ?array
{
    $value = $_COOKIE[REMEMBER_ME_COOKIE] ?? '';
    if (!is_string($value) || !preg_match('/\A([a-f0-9]{64})\.([a-f0-9]{64})\z/', $value, $matches)) {
        return null;
    }

    return ['selector' => $matches[1], 'validator' => $matches[2]];
}

function clear_remember_me_token(mysqli $conn): void
{
    $cookie = parse_remember_me_cookie();
    setcookie(REMEMBER_ME_COOKIE, '', remember_me_cookie_options(time() - 3600));
    unset($_COOKIE[REMEMBER_ME_COOKIE]);

    if ($cookie === null) {
        return;
    }

    $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remembered sign-in removal.');
    }

    $stmt->bind_param('s', $cookie['selector']);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not remove remembered sign-in.');
    }
}

function revoke_user_remember_tokens(mysqli $conn, int $userId): void
{
    $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remembered sign-in revocation.');
    }

    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not revoke remembered sign-ins.');
    }
}

function create_remember_me_token(mysqli $conn, int $userId): void
{
    clear_remember_me_token($conn);

    if (!$conn->query('DELETE FROM remember_tokens WHERE expires_at <= UTC_TIMESTAMP()')) {
        throw new RuntimeException('Could not remove expired remembered sign-ins.');
    }

    $selector = bin2hex(random_bytes(32));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expires = time() + REMEMBER_ME_LIFETIME;
    $expiresAt = gmdate('Y-m-d H:i:s', $expires);
    $stmt = $conn->prepare("
        INSERT INTO remember_tokens (selector, user_id, token_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remembered sign-in.');
    }

    $stmt->bind_param('siss', $selector, $userId, $tokenHash, $expiresAt);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not save remembered sign-in.');
    }

    $cookieValue = $selector . '.' . $validator;
    setcookie(REMEMBER_ME_COOKIE, $cookieValue, remember_me_cookie_options($expires));
    $_COOKIE[REMEMBER_ME_COOKIE] = $cookieValue;
}

function restore_remembered_session(mysqli $conn): void
{
    if (isset($_SESSION['user_id'])) {
        return;
    }

    $cookie = parse_remember_me_cookie();
    if ($cookie === null) {
        return;
    }

    $stmt = $conn->prepare("
        SELECT remember_tokens.user_id, remember_tokens.token_hash,
               users.full_name, users.email, users.role
        FROM remember_tokens
        INNER JOIN users ON users.user_id = remember_tokens.user_id
        WHERE remember_tokens.selector = ?
          AND remember_tokens.expires_at > UTC_TIMESTAMP()
          AND users.status = 'active'
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('Could not prepare remembered sign-in validation.');
    }

    $stmt->bind_param('s', $cookie['selector']);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not validate remembered sign-in.');
    }

    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || !hash_equals($user['token_hash'], hash('sha256', $cookie['validator']))) {
        clear_remember_me_token($conn);
        return;
    }

    $userId = (int) $user['user_id'];
    $selector = bin2hex(random_bytes(32));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);
    $expires = time() + REMEMBER_ME_LIFETIME;
    $expiresAt = gmdate('Y-m-d H:i:s', $expires);

    $conn->begin_transaction();
    try {
        $insert = $conn->prepare("
            INSERT INTO remember_tokens (selector, user_id, token_hash, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        if (!$insert) {
            throw new RuntimeException('Could not prepare remembered sign-in renewal.');
        }
        $insert->bind_param('siss', $selector, $userId, $tokenHash, $expiresAt);
        if (!$insert->execute()) {
            throw new RuntimeException('Could not renew remembered sign-in.');
        }

        $delete = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        if (!$delete) {
            throw new RuntimeException('Could not prepare previous remembered sign-in removal.');
        }
        $delete->bind_param('s', $cookie['selector']);
        if (!$delete->execute()) {
            throw new RuntimeException('Could not remove previous remembered sign-in.');
        }

        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity_at'] = time();

    $cookieValue = $selector . '.' . $validator;
    setcookie(REMEMBER_ME_COOKIE, $cookieValue, remember_me_cookie_options($expires));
    $_COOKIE[REMEMBER_ME_COOKIE] = $cookieValue;
}

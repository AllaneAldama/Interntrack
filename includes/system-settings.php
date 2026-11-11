<?php

function system_setting_defaults(): array
{
    return [
        'system_name' => 'InternTrack',
        'system_description' => 'Internship Monitoring and Management System',
        'system_logo' => '',
        'contact_email' => '',
        'system_timezone' => 'Asia/Manila',
        'date_format' => 'M d, Y',
        'time_format' => 'h:i A',
        'minimum_password_length' => '8',
        'session_timeout_minutes' => '30',
        'login_attempt_limit' => '5',
        'login_lockout_minutes' => '15',
        'automatic_logout' => '1',
        'maintenance_mode' => '0',
        'application_version' => '1.0.0',
        'last_backup_at' => ''
    ];
}

function load_system_settings(mysqli $conn): array
{
    $settings = system_setting_defaults();
    $result = $conn->query('SELECT setting_key, setting_value FROM system_settings');

    while ($row = $result->fetch_assoc()) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
    }

    return $settings;
}

function save_system_setting(
    mysqli $conn,
    string $key,
    string $value,
    ?int $updated_by
): void {
    if (!array_key_exists($key, system_setting_defaults())) {
        throw new InvalidArgumentException('Unknown system setting.');
    }

    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by)
    ");

    if (!$stmt) {
        throw new RuntimeException('Could not prepare system setting update.');
    }

    $stmt->bind_param('ssi', $key, $value, $updated_by);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not save system setting.');
    }
}

function format_system_datetime(string $value, array $settings): string
{
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date($settings['date_format'] . ' ' . $settings['time_format'], $timestamp);
}

function format_system_date(string $value, array $settings): string
{
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date($settings['date_format'], $timestamp);
}

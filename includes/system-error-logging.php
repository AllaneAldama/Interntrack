<?php

function record_system_error(
    mysqli $conn,
    string $error_type,
    string $description,
    string $severity
): void {
    try {
        $stmt = $conn->prepare("
            INSERT INTO system_error_logs (error_type, description, severity)
            VALUES (?, ?, ?)
        ");
        if (!$stmt) {
            error_log('InternTrack error log could not be prepared: ' . $conn->error);
            return;
        }

        $stmt->bind_param('sss', $error_type, $description, $severity);
        if (!$stmt->execute()) {
            error_log('InternTrack error log could not be saved: ' . $stmt->error);
        }
    } catch (mysqli_sql_exception $exception) {
        error_log('InternTrack error logging failed: ' . $exception->getMessage());
    }
}

function register_system_error_logging(mysqli $conn): void
{
    set_error_handler(static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ) use ($conn): bool {
        static $is_recording = false;
        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if ($is_recording || in_array($severity, $fatal_types, true)
            || (error_reporting() & $severity) === 0) {
            return false;
        }

        $type_names = [
            E_ERROR => 'Fatal error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core error',
            E_CORE_WARNING => 'Core warning',
            E_COMPILE_ERROR => 'Compile error',
            E_COMPILE_WARNING => 'Compile warning',
            E_USER_ERROR => 'User error',
            E_USER_WARNING => 'User warning',
            E_USER_NOTICE => 'User notice',
            E_STRICT => 'Strict notice',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User deprecated'
        ];
        $error_type = $type_names[$severity] ?? 'PHP error';
        $error_level = in_array($severity, $fatal_types, true)
            ? 'high'
            : (in_array($severity, [E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR], true) ? 'medium' : 'low');
        $description = basename($file) . ':' . $line . ' - ' . $message;

        $is_recording = true;
        try {
            record_system_error($conn, $error_type, substr($description, 0, 5000), $error_level);
        } finally {
            $is_recording = false;
        }

        return false;
    });

    register_shutdown_function(static function () use ($conn): void {
        $last_error = error_get_last();
        if (!$last_error) {
            return;
        }

        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($last_error['type'], $fatal_types, true)) {
            return;
        }

        $description = basename($last_error['file']) . ':' . $last_error['line']
            . ' - ' . $last_error['message'];
        record_system_error($conn, 'Fatal error', substr($description, 0, 5000), 'high');
    });
}

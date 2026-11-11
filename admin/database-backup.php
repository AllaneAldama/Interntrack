<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid form submission. Refresh the page and try again.');
}

$admin_id = (int) $_SESSION['user_id'];
$backup_time = date('Y-m-d H:i:s');

$tables_result = $conn->query('SHOW TABLES');
$tables = [];
while ($table_row = $tables_result->fetch_row()) {
    $tables[] = $table_row[0];
}

$sql = "-- InternTrack database backup generated at {$backup_time}\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    $escaped_table = '`' . str_replace('`', '``', $table) . '`';
    $create_result = $conn->query('SHOW CREATE TABLE ' . $escaped_table);
    if (!$create_result) {
        throw new RuntimeException('Could not read database table definition.');
    }
    $create_row = $create_result->fetch_assoc();
    $create_sql = $create_row['Create Table'];

    $sql .= "DROP TABLE IF EXISTS {$escaped_table};\n";
    $sql .= $create_sql . ";\n";

    $rows = $conn->query('SELECT * FROM ' . $escaped_table, MYSQLI_USE_RESULT);
    if (!$rows) {
        throw new RuntimeException('Could not read database table data.');
    }

    $column_names = [];
    foreach ($rows->fetch_fields() as $field) {
        $column_names[] = '`' . str_replace('`', '``', $field->name) . '`';
    }

    while ($row = $rows->fetch_row()) {
        $values = [];
        foreach ($row as $value) {
            $values[] = $value === null
                ? 'NULL'
                : "'" . $conn->real_escape_string((string) $value) . "'";
        }
        $sql .= 'INSERT INTO ' . $escaped_table
            . ' (' . implode(', ', $column_names) . ') VALUES ('
            . implode(', ', $values) . ");\n";
    }
    $rows->free();
    $sql .= "\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
$conn->begin_transaction();
save_system_setting($conn, 'last_backup_at', $backup_time, $admin_id);
log_activity($conn, $admin_id, 'Downloaded database backup');
if (!$conn->commit()) {
    throw new RuntimeException('Could not record the completed database backup.');
}

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="interntrack-backup-' . date('Y-m-d-His') . '.sql"');
header('Content-Length: ' . strlen($sql));
header('Cache-Control: no-store, private');
echo $sql;
exit;

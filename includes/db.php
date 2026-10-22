<?php
require_once __DIR__ . '/../config.php';

// Make mysqli throw exceptions instead of failing silently.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            mysqli_set_charset($conn, 'utf8mb4');
        } catch (mysqli_sql_exception $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('The library database is unavailable. Check config.php and make sure MySQL is running.');
        }
    }
    return $conn;
}

/** Prepare, bind and execute a statement. Always use this for user input. */
function q(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = mysqli_prepare(db(), $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function fetch_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = q($sql, $types, $params);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function fetch_one(string $sql, string $types = '', array $params = []): ?array
{
    return fetch_all($sql, $types, $params)[0] ?? null;
}

function fetch_value(string $sql, string $types = '', array $params = [])
{
    $row = fetch_one($sql, $types, $params);
    return $row ? array_values($row)[0] : null;
}

/** Run INSERT / UPDATE / DELETE. Returns the number of affected rows. */
function exec_sql(string $sql, string $types = '', array $params = []): int
{
    $stmt = q($sql, $types, $params);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

function last_id(): int
{
    return (int) mysqli_insert_id(db());
}

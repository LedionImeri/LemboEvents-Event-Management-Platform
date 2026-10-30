<?php
/**
 * PDO connection (singleton). Every query in the app goes through prepared statements.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        // Keep MySQL's NOW() aligned with PHP's timezone so date comparisons agree.
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (PDOException $e) {
        error_log('[LemboEvents] DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>Service unavailable</title>'
                . '<body style="font-family:system-ui;padding:3rem;max-width:40rem;margin:auto">'
                . '<h1>We\'ll be right back</h1><p>LemboEvents cannot reach its database. '
                . 'Make sure MySQL is running in XAMPP and that <code>database/schema.sql</code> has been imported.</p>';
        }
        exit;
    }
    return $pdo;
}

/** Small query helpers to keep page code readable. */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

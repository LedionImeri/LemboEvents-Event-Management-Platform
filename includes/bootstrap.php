<?php
/**
 * Loaded at the top of every page and API endpoint.
 * Sets up configuration, error handling, security headers, the session and helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

// ---- Base URL detection (works in htdocs sub-folders, junctions and the PHP dev server) ----
if (!defined('BASE_URL')) {
    $base = BASE_URL_OVERRIDE;
    if ($base === '__auto__') {
        $base = '';
        $root = str_replace('\\', '/', (string) realpath(ROOT_PATH));
        $file = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if ($root !== '' && $file !== '' && stripos($file, $root) === 0) {
            $rel = substr($file, strlen($root));                 // e.g. /admin/users.php
            if ($rel !== '' && str_ends_with(strtolower($script), strtolower($rel))) {
                $base = substr($script, 0, strlen($script) - strlen($rel));
            }
        }
    }
    define('BASE_URL', rtrim($base, '/'));
}

// ---- Security headers ----
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' https://cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com; "
        . "img-src 'self' data: blob: https://images.unsplash.com; "
        . "frame-src https://www.openstreetmap.org; "
        . "connect-src 'self'; media-src 'self' blob:; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name(SESSION_NAME);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout
    $now = time();
    if (isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'][] = ['type' => 'info', 'message' => 'Your session expired. Please log in again.'];
    }
    $_SESSION['last_activity'] = $now;
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';

set_exception_handler(function (Throwable $e) {
    error_log('[LemboEvents] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_api_request()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => APP_DEBUG ? $e->getMessage() : 'Something went wrong. Please try again.']);
        exit;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error · LemboEvents</title>'
        . '<body style="font-family:system-ui;padding:3rem;max-width:44rem;margin:auto">'
        . '<h1>Something went wrong</h1><p>An unexpected error occurred. Please try again in a moment.</p>'
        . (APP_DEBUG ? '<pre style="white-space:pre-wrap;background:#f4f4f5;padding:1rem;border-radius:8px">' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES) . '</pre>' : '')
        . '<p><a href="' . htmlspecialchars(BASE_URL . '/index.php', ENT_QUOTES) . '">Back to home</a></p>';
    exit;
});

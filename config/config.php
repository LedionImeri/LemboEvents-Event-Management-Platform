<?php
/**
 * LemboEvents — application configuration.
 *
 * Machine-specific and secret values (database password, Groq API key, app secret)
 * live in config/local.php, which is git-ignored. Copy config/local.example.php to
 * config/local.php to override anything below. Environment variables are also read
 * for secrets, so the app can run without a local.php in containers/CI.
 */

if (!function_exists('local_setting')) {
    /** Reads a value from config/local.php, then (optionally) from the environment. */
    function local_setting(string $name, string $default = '', bool $allowEnv = true): string
    {
        static $local = null;
        if ($local === null) {
            $file = __DIR__ . '/local.php';
            $local = is_file($file) ? include $file : [];
            if (!is_array($local)) {
                $local = [];
            }
        }
        if (isset($local[$name]) && is_scalar($local[$name]) && trim((string) $local[$name]) !== '') {
            return trim((string) $local[$name]);
        }
        if ($allowEnv) {
            $env = getenv($name);
            if (is_string($env) && trim($env) !== '') {
                return trim($env);
            }
        }
        return $default;
    }
}

// ---- Application ----
define('APP_NAME', 'LemboEvents');
define('APP_TAGLINE', 'Discover events worth remembering.');
define('APP_ENV', local_setting('APP_ENV', 'development'));          // development | production
define('APP_DEBUG', APP_ENV !== 'production');
define('APP_TIMEZONE', local_setting('APP_TIMEZONE', 'Europe/Belgrade'));  // Kosovo (CET/CEST)
// Used to sign QR payloads and other tokens. ALWAYS set your own value in config/local.php.
define('APP_SECRET', local_setting('APP_SECRET', 'change-me-lemboevents-dev-secret'));
// BASE_URL is detected automatically (see includes/bootstrap.php). Set it in local.php only
// if detection fails on your server, e.g. '/LemboEvents' or '' for a domain root.
define('BASE_URL_OVERRIDE', local_setting('BASE_URL', '__auto__'));

// ---- Database ----
define('DB_HOST', local_setting('DB_HOST', '127.0.0.1'));
define('DB_PORT', local_setting('DB_PORT', '3306'));
define('DB_NAME', local_setting('DB_NAME', 'lemboevents'));
define('DB_USER', local_setting('DB_USER', 'root'));
define('DB_PASS', local_setting('DB_PASS', ''));                    // XAMPP default: empty

// ---- Paths ----
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_PATH . '/assets/uploads');
define('UPLOAD_URL_PREFIX', 'assets/uploads');

// ---- Uploads ----
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);   // event covers: 5 MB
define('MAX_LOGO_SIZE', 2 * 1024 * 1024);    // logos / avatars: 2 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);

// ---- Business rules ----
define('CURRENCY', 'EUR');
define('CURRENCY_SYMBOL', '€');
define('SERVICE_FEE_RATE', 0.05);            // 5% platform fee on paid tickets
define('MAX_TICKETS_PER_ORDER', 10);
define('CANCELLATION_WINDOW_HOURS', 48);     // bookings can be cancelled until 48h before start
define('EVENTS_PER_PAGE', 12);

// ---- Sessions & security ----
define('SESSION_NAME', 'lembo_session');
define('SESSION_IDLE_TIMEOUT', 60 * 60 * 4); // 4 hours of inactivity
define('LOGIN_MAX_ATTEMPTS', 5);             // failed logins per email+IP ...
define('LOGIN_LOCK_MINUTES', 15);            // ... within this window

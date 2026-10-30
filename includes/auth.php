<?php
/**
 * Authentication and role-based access control.
 */

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return $user = null;
    }
    $row = db_one('SELECT id, role, first_name, last_name, email, phone, city, avatar, status, created_at FROM users WHERE id = ?', [$id]);
    // A suspended or deleted account is logged out on its next request.
    if (!$row || $row['status'] !== 'active') {
        logout_user();
        if ($row && $row['status'] === 'suspended') {
            flash('error', 'Your account has been suspended. Contact support@lemboevents.com for help.');
        }
        return $user = null;
    }
    $row['name'] = $row['first_name'] . ' ' . $row['last_name'];
    return $user = $row;
}

function current_user_id(): ?int
{
    $u = current_user();
    return $u ? (int) $u['id'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string ...$roles): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], $roles, true);
}

/** Organizer profile row of the logged-in organizer (or null). */
function current_organizer(): ?array
{
    static $org = false;
    if ($org !== false) {
        return $org;
    }
    $uid = current_user_id();
    if (!$uid || !has_role('organizer')) {
        return $org = null;
    }
    return $org = db_one('SELECT * FROM organizer_profiles WHERE user_id = ?', [$uid]);
}

function dashboard_url(?string $role = null): string
{
    $role ??= current_user()['role'] ?? 'attendee';
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'organizer' => 'organizer/dashboard.php',
        default => 'attendee/dashboard.php',
    };
}

function require_login(): array
{
    $u = current_user();
    if ($u) {
        return $u;
    }
    if (is_api_request()) {
        json_error('Please log in to continue.', 401, ['login' => url('login.php')]);
    }
    flash('info', 'Please log in to continue.');
    redirect('login.php?redirect=' . rawurlencode(current_path()));
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        if (is_api_request()) {
            json_error('You do not have permission to do that.', 403);
        }
        http_response_code(403);
        require ROOT_PATH . '/403.php';
        exit;
    }
    return $u;
}

/** Organizer pages need an organizer profile; approval is checked separately where it matters. */
function require_organizer(): array
{
    require_role('organizer');
    $org = current_organizer();
    if (!$org) {
        flash('error', 'Organizer profile not found.');
        redirect('index.php');
    }
    return $org;
}

function login_user(array $user): void
{
    session_regenerate_id(true);               // prevent session fixation
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_at'] = time();
    unset($_SESSION['csrf_token']);            // fresh CSRF token for the new session
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/** Brute-force protection: too many failed attempts for this email+IP recently? */
function login_locked(string $email): bool
{
    $failed = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND success = 0 AND created_at > NOW() - INTERVAL ? MINUTE',
        [mb_strtolower($email), client_ip(), LOGIN_LOCK_MINUTES]
    );
    return $failed >= LOGIN_MAX_ATTEMPTS;
}

function record_login_attempt(string $email, bool $success): void
{
    db_exec('INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)', [mb_strtolower($email), client_ip(), $success ? 1 : 0]);
    if ($success) {
        db_exec('DELETE FROM login_attempts WHERE email = ? AND ip_address = ? AND success = 0', [mb_strtolower($email), client_ip()]);
    }
}

/** Server-side password policy (mirrored in the client-side validation). */
function password_problems(string $password): ?string
{
    if (strlen($password) < 8) return 'Password must be at least 8 characters.';
    if (strlen($password) > 72) return 'Password must be at most 72 characters.';
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) return 'Password must contain letters and numbers.';
    return null;
}

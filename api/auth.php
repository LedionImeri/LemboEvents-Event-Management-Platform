<?php
/**
 * GET api/auth.php                       → current session info (no sensitive data)
 * GET api/auth.php?check_email=a@b.com   → { available: bool } (rate limited per session)
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Method not allowed.', 405);
}

if (isset($_GET['check_email'])) {
    $_SESSION['email_checks'] = array_filter($_SESSION['email_checks'] ?? [], fn($t) => $t > time() - 60);
    if (count($_SESSION['email_checks']) >= 15) {
        json_error('Too many requests.', 429);
    }
    $_SESSION['email_checks'][] = time();
    $email = mb_strtolower(trim((string) $_GET['check_email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => true, 'available' => false, 'valid' => false]);
    }
    json_response(['ok' => true, 'valid' => true, 'available' => !db_value('SELECT 1 FROM users WHERE email = ?', [$email])]);
}

$u = current_user();
json_response([
    'ok' => true,
    'authenticated' => (bool) $u,
    'user' => $u ? ['first_name' => $u['first_name'], 'role' => $u['role']] : null,
    'unread_notifications' => $u ? unread_notifications((int) $u['id']) : 0,
]);

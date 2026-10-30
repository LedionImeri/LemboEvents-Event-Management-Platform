<?php
/** POST api/newsletter.php — { email } */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    json_error('Method not allowed.', 405);
}
csrf_verify();

$email = mb_strtolower(trim((string) (request_data()['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    json_error('Enter a valid email address.', 422);
}
// Simple per-session throttle
$_SESSION['nl_count'] = ($_SESSION['nl_count'] ?? 0) + 1;
if ($_SESSION['nl_count'] > 5) {
    json_error('Too many attempts. Please try again later.', 429);
}
db_exec('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)', [$email]);
json_response(['ok' => true, 'message' => "You're on the list! Watch your inbox for weekend picks."]);

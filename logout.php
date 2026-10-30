<?php
/** Logout is POST-only with CSRF, so other sites can't log users out with a link/image. */
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
csrf_verify();
if ($uid = current_user_id()) {
    log_activity('auth.logout', 'Logged out', 'user', $uid, $uid);
}
logout_user();
flash('success', 'You have been logged out. See you at the next event!');
redirect('index.php');

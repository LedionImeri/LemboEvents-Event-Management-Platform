<?php
/**
 * CSRF protection: one random token per session.
 * Forms include csrf_field(); fetch() requests send the token in the X-CSRF-Token header
 * (read from <meta name="csrf-token">). All POST handlers call csrf_verify().
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || $sent === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $sent);
}

/** Stops the request when the token is missing or wrong. */
function csrf_verify(): void
{
    if (csrf_valid()) {
        return;
    }
    if (is_api_request()) {
        json_error('Your session expired. Please refresh the page and try again.', 403);
    }
    http_response_code(403);
    flash('error', 'Your session expired. Please try again.');
    $back = safe_return_path($_SERVER['HTTP_REFERER'] ?? '', 'index.php');
    redirect($back);
}

<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url());
}

$sent = false;
$demoLink = null;
$error = null;
if (is_post()) {
    csrf_verify();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $user = db_one("SELECT id FROM users WHERE email = ? AND status = 'active'", [$email]);
        $recent = $user ? (int) db_value('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > NOW() - INTERVAL 15 MINUTE', [$user['id']]) : 0;
        if ($user && $recent < 3) {
            $token = bin2hex(random_bytes(32));
            // Only a hash of the token is stored, so a DB leak can't be used to reset passwords.
            db_exec('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)', [$user['id'], hash('sha256', $token)]);
            log_activity('auth.reset_requested', 'Requested a password reset', 'user', (int) $user['id'], (int) $user['id']);
            if (APP_DEBUG) {
                $demoLink = url('reset-password.php?token=' . $token);   // no mail server locally: show the link
            }
        }
        $sent = true; // same message whether or not the email exists (no account enumeration)
    }
}

$pageTitle = 'Reset your password';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-form-wrap" style="flex:1">
    <div class="auth-card">
        <h1>Forgot password?</h1>
        <?php if ($sent): ?>
            <div class="alert alert-success mb-3"><?= icon('mail') ?><div>If an account exists for that email, we've sent a link to reset your password. It expires in 1 hour.</div></div>
            <?php if ($demoLink): ?>
                <div class="alert alert-info mb-3"><?= icon('info') ?><div><strong>Demo mode:</strong> email delivery is simulated on localhost. <a class="link" href="<?= e($demoLink) ?>">Open the reset link</a></div></div>
            <?php endif; ?>
            <a class="btn btn-dark btn-block" href="<?= e(url('login.php')) ?>">Back to log in</a>
        <?php else: ?>
            <p>Enter your account email and we'll send you a reset link.</p>
            <?php if ($error): ?><div class="alert alert-error mb-3"><?= icon('alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
            <form method="post" class="form-grid" data-validate data-loading>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" id="email" type="email" name="email" required maxlength="190" autocomplete="email">
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Send reset link</button>
            </form>
            <p class="auth-alt"><a class="link" href="<?= e(url('login.php')) ?>">Back to log in</a></p>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

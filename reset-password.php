<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$reset = preg_match('/^[a-f0-9]{64}$/', $token)
    ? db_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [hash('sha256', $token)])
    : null;

$errors = [];
if ($reset && is_post()) {
    csrf_verify();
    $password = (string) ($_POST['password'] ?? '');
    if ($p = password_problems($password)) {
        $errors[] = $p;
    } elseif ($password !== (string) ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'Passwords do not match.';
    } else {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
        db_exec('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$reset['user_id']]);
        log_activity('auth.password_reset', 'Reset password', 'user', (int) $reset['user_id'], (int) $reset['user_id']);
        flash('success', 'Your password has been updated. You can log in now.');
        redirect('login.php');
    }
}

$pageTitle = 'Choose a new password';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-form-wrap" style="flex:1">
    <div class="auth-card">
        <?php if (!$reset): ?>
            <h1>Link expired</h1>
            <div class="alert alert-error mb-3"><?= icon('alert') ?><div>This reset link is invalid or has expired. Please request a new one.</div></div>
            <a class="btn btn-dark btn-block" href="<?= e(url('forgot-password.php')) ?>">Request a new link</a>
        <?php else: ?>
            <h1>New password</h1>
            <p>Choose a strong password you don't use anywhere else.</p>
            <?php if ($errors): ?><div class="alert alert-error mb-3"><?= icon('alert') ?><div><?= e($errors[0]) ?></div></div><?php endif; ?>
            <form method="post" class="form-grid" data-validate data-loading>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="field">
                    <label for="password">New password</label>
                    <input class="input" id="password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" data-password data-strength="#pwMeter">
                    <div class="password-meter" id="pwMeter"><i></i></div>
                </div>
                <div class="field">
                    <label for="password_confirm">Confirm password</label>
                    <input class="input" id="password_confirm" type="password" name="password_confirm" required maxlength="72" autocomplete="new-password" data-match="password">
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Update password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

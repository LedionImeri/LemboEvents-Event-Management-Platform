<?php
require_once __DIR__ . '/includes/bootstrap.php';

$redirect = safe_return_path($_GET['redirect'] ?? $_POST['redirect'] ?? '', '');
if (is_logged_in()) {
    redirect($redirect ?: dashboard_url());
}

$errors = [];
$email = '';
if (is_post()) {
    csrf_verify();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter your email and password.';
    } elseif (login_locked($email)) {
        $errors[] = 'Too many failed attempts. Please wait ' . LOGIN_LOCK_MINUTES . ' minutes and try again.';
    } else {
        $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
        // Always run password_verify to keep timing similar for unknown emails
        $hash = $user['password_hash'] ?? '$2y$12$qL.3MCmp2yeqo2JlopZLTOVl7iFGOyb55WC9sIh8v/m65Dkbc2tpq';
        $valid = password_verify($password, $hash) && $user;
        record_login_attempt($email, (bool) $valid);
        if (!$valid) {
            $errors[] = 'The email or password is incorrect.';
        } elseif ($user['status'] === 'suspended') {
            $errors[] = 'This account has been suspended. Contact support@lemboevents.com for help.';
        } else {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            login_user($user);
            log_activity('auth.login', 'Logged in', 'user', (int) $user['id'], (int) $user['id']);
            flash('success', 'Welcome back, ' . $user['first_name'] . '!');
            redirect($redirect ?: dashboard_url($user['role']));
        }
    }
}

$pageTitle = 'Log in';
$pageDescription = 'Log in to LemboEvents to manage your tickets, favorites and events.';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
    <div class="auth-visual">
        <img src="https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?auto=format&fit=crop&w=1200&q=80" alt="" loading="lazy">
        <div>
            <span class="eyebrow" style="color:var(--lime)">Welcome back</span>
            <h2>Your next unforgettable night is one tap away.</h2>
            <p>Your tickets, favorites and upcoming events — all in one place.</p>
        </div>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h1>Log in</h1>
            <p>New here? <a class="link" href="<?= e(url('register.php')) ?>">Create a free account</a></p>

            <?php if ($errors): ?>
                <div class="alert alert-error mb-3" role="alert"><?= icon('alert') ?><div><?= e($errors[0]) ?></div></div>
            <?php endif; ?>

            <form method="post" class="form-grid" data-validate data-loading data-login-form>
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                <div class="field">
                    <label for="email">Email</label>
                    <div class="input-icon"><?= icon('mail') ?><input class="input" id="email" type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" maxlength="190"></div>
                </div>
                <div class="field">
                    <div class="flex between"><label for="password">Password</label><a class="link small" href="<?= e(url('forgot-password.php')) ?>">Forgot password?</a></div>
                    <div class="input-icon">
                        <?= icon('lock') ?>
                        <input class="input" id="password" type="password" name="password" required autocomplete="current-password" maxlength="72">
                        <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password"><?= icon('eye') ?></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Log in <?= icon('arrow-right') ?></button>
            </form>

            <?php if (APP_DEBUG): ?>
                <div class="demo-accounts">
                    <strong>Demo accounts · password <code>Lembo@2026</code></strong>
                    <button type="button" data-demo-login="attendee@lemboevents.com" data-demo-password="Lembo@2026"><span>🎟️ Attendee — Arta</span><code>attendee@lemboevents.com</code></button>
                    <button type="button" data-demo-login="organizer@lemboevents.com" data-demo-password="Lembo@2026"><span>🎤 Organizer — Kosovo Tech Hub</span><code>organizer@lemboevents.com</code></button>
                    <button type="button" data-demo-login="admin@lemboevents.com" data-demo-password="Lembo@2026"><span>🛡️ Admin</span><code>admin@lemboevents.com</code></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

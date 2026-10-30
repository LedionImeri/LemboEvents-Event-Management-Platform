<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(dashboard_url());
}

$role = ($_POST['role'] ?? $_GET['role'] ?? 'attendee') === 'organizer' ? 'organizer' : 'attendee';
$errors = [];

if (is_post()) {
    csrf_verify();
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $city = trim((string) ($_POST['city'] ?? ''));
    $orgName = trim((string) ($_POST['organization_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if (mb_strlen($first) < 2 || mb_strlen($first) > 60) $errors['first_name'] = 'Enter your first name.';
    if (mb_strlen($last) < 2 || mb_strlen($last) > 60) $errors['last_name'] = 'Enter your last name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors['email'] = 'Enter a valid email address.';
    elseif (db_value('SELECT 1 FROM users WHERE email = ?', [$email])) $errors['email'] = 'An account with this email already exists.';
    if ($p = password_problems($password)) $errors['password'] = $p;
    if ($password !== $confirm) $errors['password_confirm'] = 'Passwords do not match.';
    if (mb_strlen($city) > 80) $errors['city'] = 'City name is too long.';
    if ($phone !== '' && !preg_match('/^[+0-9 ()\-]{6,30}$/', $phone)) $errors['phone'] = 'Enter a valid phone number.';
    if ($role === 'organizer' && (mb_strlen($orgName) < 2 || mb_strlen($orgName) > 120)) $errors['organization_name'] = 'Enter your organization name.';
    if (empty($_POST['terms'])) $errors['terms'] = 'Please accept the terms to continue.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            db_exec('INSERT INTO users (role, first_name, last_name, email, password_hash, phone, city) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$role, $first, $last, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null, $city ?: null]);
            $uid = (int) $pdo->lastInsertId();
            if ($role === 'organizer') {
                db_exec('INSERT INTO organizer_profiles (user_id, organization_name, slug, phone, city) VALUES (?, ?, ?, ?, ?)',
                    [$uid, $orgName, unique_slug('organizer_profiles', $orgName), $phone ?: null, $city ?: null]);
                notify($uid, 'welcome', 'Application received', 'Thanks for applying! Our team reviews new organizers within 24 hours. You can already set up your profile and draft events.', 'organizer/profile.php');
                notify_admins('organizer_application', 'New organizer application', $orgName . ' applied to become an organizer.', 'admin/organizers.php?status=pending');
            } else {
                db_exec('INSERT INTO attendee_profiles (user_id, newsletter) VALUES (?, ?)', [$uid, !empty($_POST['newsletter']) ? 1 : 0]);
                notify($uid, 'welcome', 'Welcome to LemboEvents 🎉', 'Discover events worth remembering. Save favorites and get your tickets in seconds.', 'events.php');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        log_activity('auth.register', 'Registered as ' . $role, 'user', $uid, $uid);
        login_user(db_one('SELECT * FROM users WHERE id = ?', [$uid]));
        flash('success', $role === 'organizer' ? 'Account created! Your organizer profile is pending approval.' : 'Welcome to LemboEvents, ' . $first . '!');
        clear_old();
        redirect($role === 'organizer' ? 'organizer/dashboard.php' : 'attendee/dashboard.php');
    }
    remember_input($_POST);
}

$cities = array_keys(city_coordinates());
$pageTitle = $role === 'organizer' ? 'Become an organizer' : 'Create account';
$pageDescription = 'Create a free LemboEvents account to book tickets, save events and get reminders — or start selling tickets as an organizer.';
$fieldErr = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$hasErr = fn(string $k) => isset($errors[$k]) ? ' has-error' : '';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-page">
    <div class="auth-visual">
        <img src="https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?auto=format&fit=crop&w=1200&q=80" alt="" loading="lazy">
        <div>
            <span class="eyebrow" style="color:var(--lime)"><?= $role === 'organizer' ? 'For organizers' : 'Join LemboEvents' ?></span>
            <h2><?= $role === 'organizer' ? 'Sell tickets. Scan guests. Grow your audience.' : 'Concerts, conferences and nights out — all in one place.' ?></h2>
            <p><?= $role === 'organizer' ? 'Create events, manage ticket types and check guests in with your phone.' : 'Book in seconds, keep your QR tickets in your pocket and never miss a presale.' ?></p>
        </div>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h1>Create account</h1>
            <p>Already have an account? <a class="link" href="<?= e(url('login.php')) ?>">Log in</a></p>

            <?php if ($errors): ?>
                <div class="alert alert-error mb-3" role="alert"><?= icon('alert') ?><div>Please fix the highlighted fields.</div></div>
            <?php endif; ?>

            <form method="post" class="form-grid" data-validate data-loading>
                <?= csrf_field() ?>
                <div class="role-switch" role="radiogroup" aria-label="Account type">
                    <label><input type="radio" name="role" value="attendee"<?= $role === 'attendee' ? ' checked' : '' ?> data-role-switch><span><strong><?= icon('ticket') ?> Attendee</strong><small>Discover events and book tickets</small></span></label>
                    <label><input type="radio" name="role" value="organizer"<?= $role === 'organizer' ? ' checked' : '' ?> data-role-switch><span><strong><?= icon('building') ?> Organizer</strong><small>Create events and sell tickets</small></span></label>
                </div>

                <div class="field<?= $hasErr('organization_name') ?>" data-org-only<?= $role === 'organizer' ? '' : ' hidden' ?>>
                    <label for="organization_name">Organization name <span class="req">*</span></label>
                    <input class="input" id="organization_name" name="organization_name" value="<?= e(old('organization_name')) ?>" maxlength="120" <?= $role === 'organizer' ? 'required' : 'disabled' ?>>
                    <?= $fieldErr('organization_name') ?>
                </div>

                <div class="form-row">
                    <div class="field<?= $hasErr('first_name') ?>">
                        <label for="first_name">First name <span class="req">*</span></label>
                        <input class="input" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" required minlength="2" maxlength="60" autocomplete="given-name">
                        <?= $fieldErr('first_name') ?>
                    </div>
                    <div class="field<?= $hasErr('last_name') ?>">
                        <label for="last_name">Last name <span class="req">*</span></label>
                        <input class="input" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" required minlength="2" maxlength="60" autocomplete="family-name">
                        <?= $fieldErr('last_name') ?>
                    </div>
                </div>
                <div class="field<?= $hasErr('email') ?>">
                    <label for="email">Email <span class="req">*</span></label>
                    <input class="input" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email">
                    <?= $fieldErr('email') ?>
                </div>
                <div class="form-row">
                    <div class="field<?= $hasErr('city') ?>">
                        <label for="city">City</label>
                        <input class="input" id="city" name="city" list="cityList" value="<?= e(old('city')) ?>" maxlength="80" autocomplete="address-level2">
                        <datalist id="cityList"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                    </div>
                    <div class="field<?= $hasErr('phone') ?>">
                        <label for="phone">Phone</label>
                        <input class="input" id="phone" type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="30" autocomplete="tel" placeholder="+383 …">
                        <?= $fieldErr('phone') ?>
                    </div>
                </div>
                <div class="field<?= $hasErr('password') ?>">
                    <label for="password">Password <span class="req">*</span></label>
                    <div class="input-icon">
                        <?= icon('lock') ?>
                        <input class="input" id="password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" data-password data-strength="#pwMeter">
                        <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password"><?= icon('eye') ?></button>
                    </div>
                    <div class="password-meter" id="pwMeter"><i></i></div>
                    <span class="hint">At least 8 characters with letters and numbers.</span>
                    <?= $fieldErr('password') ?>
                </div>
                <div class="field<?= $hasErr('password_confirm') ?>">
                    <label for="password_confirm">Confirm password <span class="req">*</span></label>
                    <input class="input" id="password_confirm" type="password" name="password_confirm" required maxlength="72" autocomplete="new-password" data-match="password">
                    <?= $fieldErr('password_confirm') ?>
                </div>
                <div class="field<?= $hasErr('terms') ?>">
                    <label class="check"><input type="checkbox" name="terms" value="1" required<?= old('terms') ? ' checked' : '' ?>> <span>I agree to the Terms of Service and Privacy Policy.</span></label>
                    <?= $fieldErr('terms') ?>
                </div>
                <label class="check" data-attendee-only<?= $role === 'attendee' ? '' : ' hidden' ?>><input type="checkbox" name="newsletter" value="1" checked> <span>Send me weekend picks and presale alerts.</span></label>
                <button class="btn btn-primary btn-lg btn-block" type="submit">Create account <?= icon('arrow-right') ?></button>
            </form>
        </div>
    </div>
</div>
<?php
$pageScripts = ['auth.js'];
require __DIR__ . '/includes/footer.php';

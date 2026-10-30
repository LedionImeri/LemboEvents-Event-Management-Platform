<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/account.php';

$user = require_role('attendee');
if (is_post()) {
    csrf_verify();
    if (!handle_account_post($user) && ($_POST['form'] ?? '') === 'preferences') {
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $dob = trim((string) ($_POST['date_of_birth'] ?? ''));
        if (mb_strlen($bio) > 500) {
            flash('error', 'Your bio can be at most 500 characters.');
        } elseif ($dob !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) || strtotime($dob) > strtotime('-13 years') || strtotime($dob) < strtotime('-120 years'))) {
            flash('error', 'Enter a valid date of birth.');
        } else {
            db_exec('INSERT INTO attendee_profiles (user_id, bio, date_of_birth, newsletter) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE bio = VALUES(bio), date_of_birth = VALUES(date_of_birth), newsletter = VALUES(newsletter)',
                [$user['id'], $bio ?: null, $dob ?: null, !empty($_POST['newsletter']) ? 1 : 0]);
            flash('success', 'Preferences saved.');
        }
    }
    redirect('attendee/profile.php');
}
$profile = db_one('SELECT * FROM attendee_profiles WHERE user_id = ?', [$user['id']]) ?? ['bio' => '', 'date_of_birth' => '', 'newsletter' => 1];

$pageTitle = 'Profile';
$layout = 'dashboard';
$dashActive = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <h1>Profile &amp; settings</h1>
        <p>Member since <?= e(fmt_date($user['created_at'], 'F Y')) ?></p>
    </div>
</div>
<?php require __DIR__ . '/../includes/partials/account_forms.php'; ?>

<section class="card mt-3" aria-labelledby="prefTitle">
    <div class="card-head"><h2 id="prefTitle">About you &amp; preferences</h2></div>
    <form method="post" class="form-grid" data-loading>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="preferences">
        <div class="field">
            <label for="bio">Short bio</label>
            <textarea class="textarea" id="bio" name="bio" maxlength="500" placeholder="What kind of events do you love?"><?= e((string) $profile['bio']) ?></textarea>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="dob">Date of birth</label>
                <input class="input" id="dob" type="date" name="date_of_birth" value="<?= e((string) $profile['date_of_birth']) ?>" max="<?= e(date('Y-m-d', strtotime('-13 years'))) ?>">
                <span class="hint">Used for age-restricted events. Never shown publicly.</span>
            </div>
            <div class="field" style="align-content:center">
                <label class="check"><input type="checkbox" name="newsletter" value="1"<?= (int) $profile['newsletter'] ? ' checked' : '' ?>> <span>Email me weekend picks and presale alerts</span></label>
            </div>
        </div>
        <div><button type="submit" class="btn btn-dark">Save preferences</button></div>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

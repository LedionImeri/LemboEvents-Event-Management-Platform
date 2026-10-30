<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/account.php';

$org = require_organizer();
$me = current_user();

if (is_post()) {
    csrf_verify();
    if (!handle_account_post($me) && ($_POST['form'] ?? '') === 'organization') {
        $name = trim((string) ($_POST['organization_name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $website = trim((string) ($_POST['website'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $error = null;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $error = 'Organization name must be 2–120 characters.';
        elseif (mb_strlen($desc) > 3000) $error = 'Description is too long (max 3000 characters).';
        elseif ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $website))) $error = 'Website must be a valid http(s) URL.';
        elseif ($phone !== '' && !preg_match('/^[+0-9 ()\-]{6,30}$/', $phone)) $error = 'Enter a valid phone number.';
        elseif (mb_strlen($city) > 80) $error = 'City name is too long.';

        $logo = $org['logo'];
        if (!$error && !empty($_FILES['logo']['name'])) {
            try {
                $logo = upload_image($_FILES['logo'], 'logos', MAX_LOGO_SIZE);
                delete_upload($org['logo']);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
        if ($error) {
            flash('error', $error);
        } else {
            db_exec('UPDATE organizer_profiles SET organization_name = ?, description = ?, website = ?, phone = ?, city = ?, logo = ? WHERE id = ?',
                [$name, $desc ?: null, $website ?: null, $phone ?: null, $city ?: null, $logo, $org['id']]);
            flash('success', 'Organization profile saved.');
        }
    }
    redirect('organizer/profile.php');
}

$pageTitle = 'Organization profile';
$layout = 'dashboard';
$dashActive = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>Organization</h1><p>Your public organizer profile and account settings.</p></div>
    <?php if ($org['status'] === 'approved'): ?><div class="actions"><a class="btn btn-soft btn-sm" href="<?= e(url('organizer.php?slug=' . $org['slug'])) ?>" target="_blank" rel="noopener"><?= icon('external') ?> View public profile</a></div><?php endif; ?>
</div>

<section class="card mb-3" aria-labelledby="orgTitle">
    <div class="card-head"><h2 id="orgTitle">Public profile</h2><?= status_badge($org['status']) ?></div>
    <form method="post" enctype="multipart/form-data" class="form-grid" data-validate data-loading>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="organization">
        <div class="profile-head">
            <div class="profile-avatar">
                <?php if ($org['logo']): ?><img src="<?= e(image_url($org['logo'])) ?>" alt="Logo"><?php else: ?><?= e(initials($org['organization_name'])) ?><?php endif; ?>
            </div>
            <div class="form-grid" style="gap:8px">
                <label class="btn btn-soft btn-sm" style="position:relative;overflow:hidden"><?= icon('upload') ?> Upload logo
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" style="position:absolute;inset:0;opacity:0;cursor:pointer"></label>
                <span class="hint small muted">Square image, JPG/PNG/WebP, max 2 MB</span>
            </div>
        </div>
        <div class="form-row">
            <div class="field"><label for="organization_name">Organization name</label><input class="input" id="organization_name" name="organization_name" value="<?= e($org['organization_name']) ?>" required minlength="2" maxlength="120"></div>
            <div class="field"><label for="website">Website</label><input class="input" id="website" type="url" name="website" value="<?= e((string) $org['website']) ?>" maxlength="190" placeholder="https://"></div>
        </div>
        <div class="form-row">
            <div class="field"><label for="org_phone">Phone</label><input class="input" id="org_phone" type="tel" name="phone" value="<?= e((string) $org['phone']) ?>" maxlength="30"></div>
            <div class="field"><label for="org_city">City</label><input class="input" id="org_city" name="city" value="<?= e((string) $org['city']) ?>" maxlength="80"></div>
        </div>
        <div class="field"><label for="org_desc">About</label><textarea class="textarea" id="org_desc" name="description" maxlength="3000" rows="5" placeholder="Tell attendees who you are and what kind of events you run."><?= e((string) $org['description']) ?></textarea></div>
        <div><button class="btn btn-dark" type="submit">Save profile</button></div>
    </form>
</section>

<?php $user = $me; require __DIR__ . '/../includes/partials/account_forms.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php /* Personal details + password forms. Expects $user. */ ?>
<section class="card mb-3" aria-labelledby="acctTitle">
    <div class="card-head"><h2 id="acctTitle">Personal information</h2></div>
    <form method="post" enctype="multipart/form-data" class="form-grid" data-validate data-loading>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="account">
        <div class="profile-head">
            <div class="profile-avatar">
                <?php if ($user['avatar']): ?><img src="<?= e(image_url($user['avatar'])) ?>" alt="Your avatar"><?php else: ?><?= e(initials($user['name'])) ?><?php endif; ?>
            </div>
            <div class="form-grid" style="gap:8px">
                <label class="btn btn-soft btn-sm" style="position:relative;overflow:hidden">
                    <?= icon('upload') ?> Upload photo
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" style="position:absolute;inset:0;opacity:0;cursor:pointer" data-avatar-input>
                </label>
                <span class="hint small muted">JPG, PNG or WebP · max 2 MB</span>
                <?php if ($user['avatar']): ?><label class="check small"><input type="checkbox" name="remove_avatar" value="1"> Remove photo</label><?php endif; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="first_name">First name</label>
                <input class="input" id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required minlength="2" maxlength="60">
            </div>
            <div class="field">
                <label for="last_name">Last name</label>
                <input class="input" id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required minlength="2" maxlength="60">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="email">Email</label>
                <input class="input" id="email" value="<?= e($user['email']) ?>" readonly aria-describedby="emailHint">
                <span class="hint" id="emailHint">Contact support to change your email.</span>
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input class="input" id="phone" type="tel" name="phone" value="<?= e((string) $user['phone']) ?>" maxlength="30">
            </div>
        </div>
        <div class="field">
            <label for="city">City</label>
            <input class="input" id="city" name="city" value="<?= e((string) $user['city']) ?>" maxlength="80" list="cityList">
            <datalist id="cityList"><?php foreach (array_keys(city_coordinates()) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        </div>
        <div><button type="submit" class="btn btn-dark">Save changes</button></div>
    </form>
</section>

<section class="card" aria-labelledby="pwTitle">
    <div class="card-head"><h2 id="pwTitle">Password &amp; security</h2><span class="small muted"><?= icon('shield', 'icon-sm') ?> Passwords are hashed with bcrypt</span></div>
    <form method="post" class="form-grid" data-validate data-loading autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="password">
        <div class="field">
            <label for="current_password">Current password</label>
            <input class="input" id="current_password" type="password" name="current_password" required autocomplete="current-password" maxlength="72">
        </div>
        <div class="form-row">
            <div class="field">
                <label for="new_password">New password</label>
                <input class="input" id="new_password" type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" data-password>
            </div>
            <div class="field">
                <label for="password_confirm">Confirm new password</label>
                <input class="input" id="password_confirm" type="password" name="password_confirm" required maxlength="72" autocomplete="new-password" data-match="new_password">
            </div>
        </div>
        <div><button type="submit" class="btn btn-outline">Update password</button></div>
    </form>
</section>

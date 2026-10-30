<?php
/**
 * Shared account settings (personal details, avatar, password) for every role.
 * handle_account_post() processes the forms rendered by partials/account_forms.php.
 * Returns true when a form was handled (the caller should then redirect).
 */
function handle_account_post(array $user): bool
{
    $action = $_POST['form'] ?? '';
    if ($action === 'account') {
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $city = trim((string) ($_POST['city'] ?? ''));
        $errors = [];
        if (mb_strlen($first) < 2 || mb_strlen($first) > 60) $errors[] = 'Enter a valid first name.';
        if (mb_strlen($last) < 2 || mb_strlen($last) > 60) $errors[] = 'Enter a valid last name.';
        if ($phone !== '' && !preg_match('/^[+0-9 ()\-]{6,30}$/', $phone)) $errors[] = 'Enter a valid phone number.';
        if (mb_strlen($city) > 80) $errors[] = 'City name is too long.';

        $avatar = $user['avatar'];
        if (!$errors && !empty($_FILES['avatar']['name'])) {
            try {
                $avatar = upload_image($_FILES['avatar'], 'avatars', MAX_LOGO_SIZE);
                delete_upload($user['avatar']);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if (!empty($_POST['remove_avatar']) && !$errors) {
            delete_upload($user['avatar']);
            $avatar = null;
        }
        if ($errors) {
            flash('error', $errors[0]);
            return true;
        }
        db_exec('UPDATE users SET first_name = ?, last_name = ?, phone = ?, city = ?, avatar = ? WHERE id = ?',
            [$first, $last, $phone ?: null, $city ?: null, $avatar, $user['id']]);
        flash('success', 'Your profile has been updated.');
        return true;
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $hash = (string) db_value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($current, $hash)) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($p = password_problems($new)) {
            flash('error', $p);
        } elseif ($new !== (string) ($_POST['password_confirm'] ?? '')) {
            flash('error', 'New passwords do not match.');
        } elseif (password_verify($new, $hash)) {
            flash('error', 'Choose a password different from your current one.');
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            log_activity('auth.password_changed', 'Changed password', 'user', (int) $user['id']);
            flash('success', 'Password changed successfully.');
        }
        return true;
    }
    return false;
}

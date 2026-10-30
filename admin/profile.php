<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/account.php';

$user = require_role('admin');
if (is_post()) {
    csrf_verify();
    handle_account_post($user);
    redirect('admin/profile.php');
}
$pageTitle = 'Settings';
$layout = 'dashboard';
$dashActive = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Account settings</h1><p>Your administrator profile and password.</p></div></div>
<?php require __DIR__ . '/../includes/partials/account_forms.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

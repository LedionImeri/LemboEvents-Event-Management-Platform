<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!headers_sent()) {
    http_response_code(403);
}
$pageTitle = 'Access denied';
$layout = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="error-page">
    <div>
        <div class="error-code">4<span>0</span>3</div>
        <h1 style="font-size:2rem">Backstage access only.</h1>
        <p>You don't have permission to view this page. If you think this is a mistake, log in with a different account.</p>
        <div class="flex gap-1 wrap" style="justify-content:center">
            <?php if (is_logged_in()): ?>
                <a class="btn btn-primary" href="<?= e(url(dashboard_url())) ?>"><?= icon('grid') ?> Go to my dashboard</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Log in</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('index.php')) ?>">Back to home</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

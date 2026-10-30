<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!headers_sent()) {
    http_response_code(404);
}
$pageTitle = 'Page not found';
$layout = 'public';
require __DIR__ . '/includes/header.php';
?>
<section class="error-page">
    <div>
        <div class="error-code">4<span>0</span>4</div>
        <h1 style="font-size:2rem">This page left the party early.</h1>
        <p>The page or event you're looking for doesn't exist, was moved, or is no longer available.</p>
        <div class="flex gap-1 wrap" style="justify-content:center">
            <a class="btn btn-primary" href="<?= e(url('events.php')) ?>"><?= icon('search') ?> Explore events</a>
            <a class="btn btn-outline" href="<?= e(url('index.php')) ?>">Back to home</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

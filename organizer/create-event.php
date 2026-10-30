<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/event_form.php';

$org = require_organizer();
$event = null;
$errors = is_post() ? handle_event_form($org, null) : [];
$values = event_form_values();

$pageTitle = 'Create event';
$layout = 'dashboard';
$dashActive = 'events';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <nav class="breadcrumbs"><a href="<?= e(url('organizer/events.php')) ?>"><?= icon('arrow-left') ?> Events</a></nav>
        <h1>Create event</h1>
        <p>Fill in the details, add ticket types and publish when you're ready.</p>
    </div>
</div>
<?php require __DIR__ . '/../includes/partials/event_form_view.php'; ?>
<?php
$pageScripts = ['event-form.js'];
require __DIR__ . '/../includes/footer.php';

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/event_form.php';

$org = require_organizer();
$event = org_event_or_404((int) ($_GET['id'] ?? 0), $org);
if ($event['status'] === 'cancelled') {
    flash('info', 'Cancelled events can no longer be edited.');
    redirect('organizer/events.php?tab=cancelled');
}
$errors = is_post() ? handle_event_form($org, $event) : [];
$values = event_form_values($event);

$pageTitle = 'Edit · ' . $event['title'];
$layout = 'dashboard';
$dashActive = 'events';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <nav class="breadcrumbs"><a href="<?= e(url('organizer/events.php')) ?>"><?= icon('arrow-left') ?> Events</a></nav>
        <h1><?= e($event['title']) ?></h1>
        <p><?= status_badge($event['status']) ?> · Last updated <?= e(time_ago($event['updated_at'])) ?></p>
    </div>
    <div class="actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url('organizer/attendees.php?event=' . $event['id'])) ?>"><?= icon('users') ?> Attendees</a>
        <a class="btn btn-soft btn-sm" href="<?= e(url('organizer/check-in.php?event=' . $event['id'])) ?>"><?= icon('scan') ?> Check-in</a>
    </div>
</div>
<?php if ($event['status'] === 'rejected' && $event['rejection_reason']): ?>
    <div class="alert alert-error mb-3"><?= icon('alert') ?><div><strong>Changes requested by our team:</strong> <?= e($event['rejection_reason']) ?> Update the event and publish again.</div></div>
<?php elseif ($event['status'] === 'pending'): ?>
    <div class="alert alert-info mb-3"><?= icon('clock') ?><div>This event is being reviewed. You can keep editing — it goes live as soon as it's approved.</div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/partials/event_form_view.php'; ?>
<?php
$pageScripts = ['event-form.js'];
require __DIR__ . '/../includes/footer.php';

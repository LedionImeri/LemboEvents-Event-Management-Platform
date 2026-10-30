<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/organizer.php';
require_once __DIR__ . '/../includes/booking.php';

$org = require_organizer();

if (is_post()) {
    csrf_verify();
    $event = org_event_or_404((int) ($_POST['event_id'] ?? 0), $org);
    try {
        $msg = match ($_POST['action'] ?? '') {
            'submit'    => org_submit_event($event, $org),
            'unpublish' => org_unpublish_event($event),
            'delete'    => org_delete_event($event),
            'cancel'    => 'Event cancelled. ' . cancel_event((int) $event['id'], current_user(), trim((string) ($_POST['reason'] ?? ''))) . ' bookings were refunded and attendees notified.',
            default     => throw new RuntimeException('Unknown action.'),
        };
        flash('success', $msg);
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('organizer/events.php' . (isset($_POST['tab']) ? '?tab=' . rawurlencode((string) $_POST['tab']) : ''));
}

$tabs = [
    'all'       => ['All', '1=1'],
    'live'      => ['Live', "e.status = 'published' AND e.ends_at >= NOW()"],
    'drafts'    => ['Drafts', "e.status IN ('draft','rejected')"],
    'pending'   => ['In review', "e.status = 'pending'"],
    'past'      => ['Past', "e.status = 'published' AND e.ends_at < NOW()"],
    'cancelled' => ['Cancelled', "e.status = 'cancelled'"],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';
$counts = [];
foreach ($tabs as $k => [$l, $cond]) {
    $counts[$k] = (int) db_value("SELECT COUNT(*) FROM events e WHERE e.organizer_id = ? AND $cond", [$org['id']]);
}
$events = db_all(
    "SELECT e.*, c.name AS category_name,
            COALESCE((SELECT SUM(quantity_sold) FROM ticket_types WHERE event_id = e.id), 0) AS sold,
            COALESCE((SELECT SUM(quantity) FROM ticket_types WHERE event_id = e.id AND is_active = 1), 0) AS capacity,
            COALESCE((SELECT SUM(subtotal) FROM bookings WHERE event_id = e.id AND status = 'confirmed'), 0) AS revenue,
            (SELECT COUNT(*) FROM bookings WHERE event_id = e.id) AS booking_count
       FROM events e JOIN categories c ON c.id = e.category_id
      WHERE e.organizer_id = ? AND {$tabs[$tab][1]}
      ORDER BY (e.ends_at < NOW()), e.starts_at " . ($tab === 'past' ? 'DESC' : 'ASC'), [$org['id']]);

$pageTitle = 'My events';
$layout = 'dashboard';
$dashActive = 'events';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>Events</h1><p>Create, publish and manage every event you organize.</p></div>
    <div class="actions"><a class="btn btn-primary btn-sm" href="<?= e(url('organizer/create-event.php')) ?>"><?= icon('plus') ?> Create event</a></div>
</div>
<nav class="tabs" aria-label="Event status">
    <?php foreach ($tabs as $k => [$label]): ?>
        <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($label) ?> <span class="tab-count"><?= $counts[$k] ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($events): ?>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Event</th><th>Date</th><th>Status</th><th class="num">Sold</th><th class="num">Revenue</th><th class="num">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($events as $ev):
            $past = strtotime($ev['ends_at']) < time();
            $pct = $ev['capacity'] ? (int) round($ev['sold'] / $ev['capacity'] * 100) : 0; ?>
            <tr>
                <td>
                    <div class="cell-main">
                        <img src="<?= e(image_thumb($ev['cover_image'], 160)) ?>" alt="" loading="lazy">
                        <div><strong><?= e($ev['title']) ?></strong><span><?= e($ev['category_name']) ?> · <?= e($ev['city']) ?></span></div>
                    </div>
                </td>
                <td class="nowrap"><?= e(fmt_date($ev['starts_at'], 'M j, Y')) ?><br><span class="small muted"><?= e(fmt_time($ev['starts_at'])) ?></span></td>
                <td>
                    <?= status_badge($past && $ev['status'] === 'published' ? 'ended' : $ev['status']) ?>
                    <?php if ($ev['status'] === 'rejected' && $ev['rejection_reason']): ?><div class="small muted mt-1" style="max-width:200px"><?= e($ev['rejection_reason']) ?></div><?php endif; ?>
                </td>
                <td class="num"><?= number_format((int) $ev['sold']) ?>/<?= number_format((int) $ev['capacity']) ?><br><span class="small muted"><?= $pct ?>%</span></td>
                <td class="num"><?= e(money_exact($ev['revenue'])) ?></td>
                <td>
                    <div class="actions">
                        <a class="btn btn-ghost btn-xs" href="<?= e(url('event.php?slug=' . $ev['slug'])) ?>" title="View" aria-label="View event page"><?= icon('eye') ?></a>
                        <?php if ($ev['status'] !== 'cancelled'): ?>
                            <a class="btn btn-soft btn-xs" href="<?= e(url('organizer/edit-event.php?id=' . $ev['id'])) ?>"><?= icon('edit') ?> Edit</a>
                        <?php endif; ?>
                        <a class="btn btn-soft btn-xs" href="<?= e(url('organizer/attendees.php?event=' . $ev['id'])) ?>" title="Attendees"><?= icon('users') ?></a>
                        <?php if (in_array($ev['status'], ['draft', 'rejected'], true) && !$past): ?>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                                <button class="btn btn-success btn-xs" name="action" value="submit"><?= icon('zap') ?> Publish</button></form>
                        <?php elseif (in_array($ev['status'], ['published', 'pending'], true) && !$past && (int) $ev['booking_count'] === 0): ?>
                            <form method="post" class="inline-form" data-confirm="Move this event back to drafts? It will disappear from the site." data-confirm-button="Unpublish" data-confirm-tone="neutral"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                                <button class="btn btn-soft btn-xs" name="action" value="unpublish">Unpublish</button></form>
                        <?php endif; ?>
                        <?php if ($ev['status'] === 'published' && !$past && (int) $ev['booking_count'] > 0): ?>
                            <form method="post" class="inline-form" data-confirm="Cancel “<?= e($ev['title']) ?>”? All <?= (int) $ev['booking_count'] ?> bookings will be refunded and every attendee will be notified. This cannot be undone." data-confirm-title="Cancel event?" data-confirm-button="Cancel event"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                                <button class="btn btn-danger-soft btn-xs" name="action" value="cancel">Cancel</button></form>
                        <?php endif; ?>
                        <?php if ((int) $ev['booking_count'] === 0): ?>
                            <form method="post" class="inline-form" data-confirm="Delete “<?= e($ev['title']) ?>” permanently?" data-confirm-button="Delete"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                                <button class="btn btn-ghost btn-xs" name="action" value="delete" aria-label="Delete event"><?= icon('trash') ?></button></form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"><?= icon('calendar') ?></div>
        <h3>No events here yet</h3>
        <p>Create an event page in minutes — add ticket types, a cover image and publish when you're ready.</p>
        <a class="btn btn-primary" href="<?= e(url('organizer/create-event.php')) ?>"><?= icon('plus') ?> Create event</a>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

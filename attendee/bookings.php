<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

$user = require_role('attendee');
$uid = (int) $user['id'];

// Non-JS fallback for cancellation (the page normally uses api/bookings.php)
if (is_post()) {
    csrf_verify();
    try {
        $b = cancel_booking((int) ($_POST['booking_id'] ?? 0), $user);
        flash('success', 'Booking ' . $b['reference'] . ' was cancelled.' . ((float) $b['total'] > 0 ? ' Your refund is on its way (demo).' : ''));
    } catch (BookingException $e) {
        flash('error', $e->getMessage());
    }
    redirect('attendee/bookings.php?tab=cancelled');
}

$tab = in_array($_GET['tab'] ?? '', ['upcoming', 'past', 'cancelled'], true) ? $_GET['tab'] : 'upcoming';
$conditions = [
    'upcoming'  => "b.status = 'confirmed' AND e.ends_at >= NOW() AND e.status = 'published'",
    'past'      => "b.status = 'confirmed' AND e.ends_at < NOW()",
    'cancelled' => "(b.status IN ('cancelled','refunded') OR e.status = 'cancelled')",
];
$counts = [];
foreach ($conditions as $k => $c) {
    $counts[$k] = (int) db_value("SELECT COUNT(*) FROM bookings b JOIN events e ON e.id = b.event_id WHERE b.user_id = ? AND $c", [$uid]);
}
$bookings = db_all(
    "SELECT b.*, e.title, e.slug, e.cover_image, e.starts_at, e.ends_at, e.venue_name, e.city, e.status AS event_status,
            (SELECT SUM(quantity) FROM booking_items WHERE booking_id = b.id) AS qty,
            (SELECT GROUP_CONCAT(CONCAT(bi.quantity, '× ', tt.name) SEPARATOR ', ') FROM booking_items bi JOIN ticket_types tt ON tt.id = bi.ticket_type_id WHERE bi.booking_id = b.id) AS summary,
            (SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id WHERE bi.booking_id = b.id AND t.status = 'checked_in') AS used,
            (SELECT id FROM reviews r WHERE r.user_id = b.user_id AND r.event_id = b.event_id) AS review_id
       FROM bookings b JOIN events e ON e.id = b.event_id
      WHERE b.user_id = ? AND {$conditions[$tab]}
      ORDER BY e.starts_at " . ($tab === 'upcoming' ? 'ASC' : 'DESC'), [$uid]);

$pageTitle = 'My events';
$layout = 'dashboard';
$dashActive = 'bookings';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <h1>My events</h1>
        <p>Every booking you've made — upcoming, past and cancelled.</p>
    </div>
</div>
<nav class="tabs" aria-label="Booking status">
    <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled'] as $k => $l): ?>
        <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($l) ?> <span class="tab-count"><?= $counts[$k] ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($bookings): ?>
    <div class="ticket-list">
        <?php foreach ($bookings as $b):
            $blocker = $b['status'] === 'confirmed' && $tab === 'upcoming' ? booking_cancel_blocker($b, $b) : 'n/a';
        ?>
            <article class="ticket-mini" data-booking-row="<?= (int) $b['id'] ?>">
                <div class="ticket-mini-media"><img src="<?= e(image_thumb($b['cover_image'], 300)) ?>" alt="" loading="lazy"></div>
                <div class="ticket-mini-body">
                    <div class="flex gap-1 wrap items-center"><?= status_badge($b['event_status'] === 'cancelled' ? 'cancelled' : $b['status']) ?><span class="small muted"><?= e($b['reference']) ?></span></div>
                    <h3><a href="<?= e(url('event.php?slug=' . $b['slug'])) ?>"><?= e($b['title']) ?></a></h3>
                    <ul class="event-meta">
                        <li><?= icon('calendar') ?><?= e(fmt_event_range($b['starts_at'], $b['ends_at'])) ?></li>
                        <li><?= icon('ticket') ?><?= e($b['summary']) ?> · <?= e($b['total'] > 0 ? money_exact($b['total']) : 'Free') ?></li>
                    </ul>
                </div>
                <div class="ticket-mini-side">
                    <?php if ($tab === 'upcoming'): ?>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('ticket.php?booking=' . $b['reference'])) ?>"><?= icon('qr') ?> Tickets</a>
                        <?php if ($blocker === null): ?>
                            <button type="button" class="btn btn-danger-soft btn-xs" data-cancel-booking="<?= (int) $b['id'] ?>" data-ref="<?= e($b['reference']) ?>" data-total="<?= e($b['total'] > 0 ? money_exact($b['total']) : '') ?>">Cancel booking</button>
                        <?php else: ?>
                            <span class="small muted" title="<?= e($blocker) ?>" style="max-width:150px;text-align:center;line-height:1.3">Non-refundable now</span>
                        <?php endif; ?>
                    <?php elseif ($tab === 'past'): ?>
                        <?php if ($b['review_id']): ?>
                            <span class="badge badge-success">Reviewed</span>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary btn-sm" data-modal-open="reviewModal" data-review-event="<?= (int) $b['event_id'] ?>" data-review-title="<?= e($b['title']) ?>"><?= icon('star') ?> Review</button>
                        <?php endif; ?>
                        <a class="btn btn-ghost btn-xs" href="<?= e(url('ticket.php?booking=' . $b['reference'])) ?>">Tickets</a>
                    <?php else: ?>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('booking.php?ref=' . $b['reference'])) ?>">Receipt</a>
                        <?php if ($b['cancelled_at']): ?><span class="small muted"><?= e(fmt_date($b['cancelled_at'])) ?></span><?php endif; ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="small muted mt-3"><?= icon('info', 'icon-sm') ?> Bookings can be cancelled for a full refund up to <?= CANCELLATION_WINDOW_HOURS ?> hours before the event, as long as no ticket has been checked in.</p>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"><?= icon($tab === 'cancelled' ? 'x-circle' : 'calendar') ?></div>
        <h3><?= ['upcoming' => 'No upcoming events', 'past' => 'No past events yet', 'cancelled' => 'No cancelled bookings'][$tab] ?></h3>
        <p><?= $tab === 'cancelled' ? 'Good news — nothing was cancelled.' : 'Browse what\'s on and book your next experience.' ?></p>
        <?php if ($tab !== 'cancelled'): ?><a class="btn btn-primary" href="<?= e(url('events.php')) ?>">Explore events</a><?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" hidden data-cancel-form>
    <?= csrf_field() ?>
    <input type="hidden" name="booking_id" value="">
</form>
<?php require __DIR__ . '/../includes/partials/review_modal.php'; ?>
<?php
$pageScripts = ['event.js', 'bookings.js'];
require __DIR__ . '/../includes/footer.php';

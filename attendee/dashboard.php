<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role('attendee');
$uid = (int) $user['id'];

$stats = db_one(
    "SELECT
        (SELECT COUNT(DISTINCT b.event_id) FROM bookings b JOIN events e ON e.id = b.event_id
          WHERE b.user_id = ? AND b.status = 'confirmed' AND e.ends_at >= NOW() AND e.status = 'published') AS upcoming,
        (SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id
          WHERE b.user_id = ? AND t.status <> 'cancelled') AS tickets,
        (SELECT COUNT(*) FROM bookings WHERE user_id = ?) AS bookings,
        (SELECT COUNT(*) FROM favorites WHERE user_id = ?) AS favorites,
        (SELECT COALESCE(SUM(total), 0) FROM bookings WHERE user_id = ? AND status = 'confirmed') AS spent",
    [$uid, $uid, $uid, $uid, $uid]
);

$upcoming = db_all(
    "SELECT e.id, e.title, e.slug, e.cover_image, e.starts_at, e.venue_name, e.city,
            COUNT(t.id) AS ticket_count
       FROM bookings b
       JOIN events e ON e.id = b.event_id
       JOIN booking_items bi ON bi.booking_id = b.id
       JOIN tickets t ON t.booking_item_id = bi.id AND t.status <> 'cancelled'
      WHERE b.user_id = ? AND b.status = 'confirmed' AND e.ends_at >= NOW() AND e.status = 'published'
      GROUP BY e.id ORDER BY e.starts_at ASC LIMIT 5", [$uid]);
$next = $upcoming[0] ?? null;

// Recommendations: categories the user booked or saved, excluding events they already have
$recommended = fetch_event_cards(
    public_event_where() . " AND e.category_id IN (
            SELECT e2.category_id FROM bookings b2 JOIN events e2 ON e2.id = b2.event_id WHERE b2.user_id = ?
            UNION SELECT e3.category_id FROM favorites f JOIN events e3 ON e3.id = f.event_id WHERE f.user_id = ?)
        AND e.id NOT IN (SELECT event_id FROM bookings WHERE user_id = ? AND status = 'confirmed')",
    [$uid, $uid, $uid], 'tickets_sold DESC', 3);
if (count($recommended) < 3) {
    $recommended = fetch_event_cards(public_event_where(), [], 'tickets_sold DESC', 3);
}
$notifications = db_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4', [$uid]);
$toReview = (int) db_value(
    "SELECT COUNT(DISTINCT b.event_id) FROM bookings b JOIN events e ON e.id = b.event_id
      WHERE b.user_id = ? AND b.status = 'confirmed' AND e.ends_at < NOW()
        AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.user_id = b.user_id AND r.event_id = b.event_id)", [$uid]);

$pageTitle = 'Dashboard';
$layout = 'dashboard';
$dashActive = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="welcome">
    <div>
        <h2>Hey <?= e($user['first_name']) ?> 👋</h2>
        <p><?= $next ? 'Your next event is ' . e(time_until($next['starts_at'])) . '. Get ready!' : 'No upcoming plans yet — let\'s find something great.' ?></p>
    </div>
    <?php if ($next): ?>
        <a class="next-event" href="<?= e(url('ticket.php?event=' . $next['id'])) ?>">
            <img src="<?= e(image_thumb($next['cover_image'], 200)) ?>" alt="">
            <div>
                <small>Next up · <?= e(time_until($next['starts_at'])) ?></small>
                <strong><?= e($next['title']) ?></strong>
                <span><?= e(fmt_date($next['starts_at'], 'D, M j · H:i')) ?> · <?= (int) $next['ticket_count'] ?> <?= (int) $next['ticket_count'] === 1 ? 'ticket' : 'tickets' ?></span>
            </div>
        </a>
    <?php else: ?>
        <a class="btn btn-lime" href="<?= e(url('events.php')) ?>"><?= icon('search') ?> Explore events</a>
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat"><span class="stat-icon"><?= icon('calendar') ?></span><small>Upcoming events</small><strong><?= (int) $stats['upcoming'] ?></strong><span class="stat-foot">Events you're going to</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('ticket') ?></span><small>Tickets</small><strong><?= (int) $stats['tickets'] ?></strong><span class="stat-foot">Active &amp; used tickets</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('card') ?></span><small>Total bookings</small><strong><?= (int) $stats['bookings'] ?></strong><span class="stat-foot"><?= e(money_exact($stats['spent'])) ?> spent</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('heart') ?></span><small>Favorite events</small><strong><?= (int) $stats['favorites'] ?></strong><span class="stat-foot">Saved for later</span></div>
</div>

<?php if ($toReview > 0): ?>
    <div class="alert alert-info mb-3"><?= icon('star') ?><div>You have <?= $toReview ?> past <?= $toReview === 1 ? 'event' : 'events' ?> to review. <a class="link" href="<?= e(url('attendee/bookings.php?tab=past')) ?>">Share your experience</a></div></div>
<?php endif; ?>

<div class="dash-grid">
    <section class="card">
        <div class="card-head"><h2>Upcoming events</h2><a class="link small" href="<?= e(url('attendee/tickets.php')) ?>">All tickets</a></div>
        <?php if ($upcoming): ?>
            <div class="list">
                <?php foreach ($upcoming as $u): ?>
                    <div class="list-item">
                        <div class="date-block"><small><?= e(fmt_date($u['starts_at'], 'M')) ?></small><strong><?= e(fmt_date($u['starts_at'], 'j')) ?></strong></div>
                        <div class="li-main">
                            <strong><a href="<?= e(url('event.php?slug=' . $u['slug'])) ?>"><?= e($u['title']) ?></a></strong>
                            <span><?= e(fmt_date($u['starts_at'], 'D · H:i')) ?> · <?= e($u['venue_name']) ?></span>
                        </div>
                        <a class="btn btn-soft btn-xs" href="<?= e(url('ticket.php?event=' . $u['id'])) ?>"><?= icon('qr') ?> <?= (int) $u['ticket_count'] ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:32px 16px">
                <div class="empty-icon"><?= icon('calendar') ?></div>
                <h3>No upcoming events</h3>
                <p>When you book tickets, your events show up here.</p>
                <a class="btn btn-primary" href="<?= e(url('events.php')) ?>">Find events</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><h2>Notifications</h2><a class="link small" href="<?= e(url('attendee/notifications.php')) ?>">View all</a></div>
        <?php if ($notifications): ?>
            <div class="list">
                <?php foreach ($notifications as $n): ?>
                    <a class="list-item" href="<?= e(url(safe_return_path($n['link'] ?? '', 'attendee/notifications.php'))) ?>">
                        <div class="li-main">
                            <strong><?= $n['is_read'] ? '' : '<span class="badge badge-warning" style="margin-right:6px">New</span>' ?><?= e($n['title']) ?></strong>
                            <span><?= e(str_limit($n['message'], 80)) ?> · <?= e(time_ago($n['created_at'])) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted mb-0">You're all caught up.</p>
        <?php endif; ?>
    </section>
</div>

<section>
    <div class="card-head"><h2>Recommended for you</h2><a class="link small" href="<?= e(url('events.php')) ?>">Explore more</a></div>
    <div class="event-grid event-grid-3">
        <?php foreach ($recommended as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

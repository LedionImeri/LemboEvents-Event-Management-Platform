<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

$user = require_role('attendee');
$uid = (int) $user['id'];
$showPast = ($_GET['view'] ?? '') === 'past';

// One row per event with ticket counts, so a booking of 5 tickets doesn't flood the wallet
$rows = db_all(
    "SELECT e.id, e.title, e.slug, e.cover_image, e.starts_at, e.ends_at, e.venue_name, e.city, e.status AS event_status,
            COUNT(t.id) AS total,
            SUM(t.status = 'valid') AS valid_count,
            SUM(t.status = 'checked_in') AS used_count,
            GROUP_CONCAT(DISTINCT tt.name ORDER BY tt.name SEPARATOR ', ') AS types
       FROM tickets t
       JOIN booking_items bi ON bi.id = t.booking_item_id
       JOIN bookings b ON b.id = bi.booking_id
       JOIN ticket_types tt ON tt.id = bi.ticket_type_id
       JOIN events e ON e.id = b.event_id
      WHERE b.user_id = ? AND t.status <> 'cancelled' AND e.ends_at " . ($showPast ? '<' : '>=') . " NOW()
      GROUP BY e.id
      ORDER BY e.starts_at " . ($showPast ? 'DESC' : 'ASC'), [$uid]);

$pageTitle = 'My tickets';
$layout = 'dashboard';
$dashActive = 'tickets';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <h1>My tickets</h1>
        <p>Your digital wallet. Open a ticket to show its QR code at the entrance.</p>
    </div>
</div>
<nav class="tabs" aria-label="Ticket views">
    <a href="?view=upcoming" class="<?= $showPast ? '' : 'active' ?>"><?= icon('ticket') ?> Upcoming</a>
    <a href="?view=past" class="<?= $showPast ? 'active' : '' ?>"><?= icon('clock') ?> Past</a>
</nav>

<?php if ($rows): ?>
    <div class="ticket-list">
        <?php foreach ($rows as $r): ?>
            <article class="ticket-mini">
                <div class="ticket-mini-media"><img src="<?= e(image_thumb($r['cover_image'], 300)) ?>" alt="" loading="lazy"></div>
                <div class="ticket-mini-body">
                    <h3><a href="<?= e(url('event.php?slug=' . $r['slug'])) ?>"><?= e($r['title']) ?></a></h3>
                    <ul class="event-meta">
                        <li><?= icon('calendar') ?><?= e(fmt_event_range($r['starts_at'], $r['ends_at'])) ?></li>
                        <li><?= icon('pin') ?><?= e($r['venue_name']) ?>, <?= e($r['city']) ?></li>
                        <li><?= icon('tag') ?><?= e($r['types']) ?></li>
                    </ul>
                </div>
                <div class="ticket-mini-side">
                    <span class="small muted"><strong style="color:var(--ink);font-size:1.4rem;font-family:var(--font-display)"><?= (int) $r['total'] ?></strong> <?= (int) $r['total'] === 1 ? 'ticket' : 'tickets' ?></span>
                    <?php if ((int) $r['used_count'] > 0): ?><span class="badge badge-info"><?= (int) $r['used_count'] ?> checked in</span><?php endif; ?>
                    <?php if (!$showPast): ?><span class="badge badge-success">Starts <?= e(time_until($r['starts_at'])) ?></span><?php endif; ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('ticket.php?event=' . $r['id'])) ?>"><?= icon('qr') ?> View QR</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"><?= icon('ticket') ?></div>
        <h3><?= $showPast ? 'No past tickets yet' : 'No upcoming tickets' ?></h3>
        <p><?= $showPast ? 'Tickets for events you attended will appear here.' : 'Find something you love and your tickets will land here instantly.' ?></p>
        <a class="btn btn-primary" href="<?= e(url('events.php')) ?>">Explore events</a>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

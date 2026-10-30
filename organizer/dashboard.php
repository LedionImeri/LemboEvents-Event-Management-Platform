<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/charts.php';

$org = require_organizer();
$oid = (int) $org['id'];

$stats = db_one(
    "SELECT
        (SELECT COUNT(*) FROM events WHERE organizer_id = ?) AS events,
        (SELECT COUNT(*) FROM events WHERE organizer_id = ? AND status = 'published' AND ends_at >= NOW()) AS live,
        (SELECT COALESCE(SUM(bi.quantity), 0) FROM booking_items bi JOIN bookings b ON b.id = bi.booking_id JOIN events e ON e.id = b.event_id
          WHERE e.organizer_id = ? AND b.status = 'confirmed') AS sold,
        (SELECT COALESCE(SUM(b.subtotal), 0) FROM bookings b JOIN events e ON e.id = b.event_id WHERE e.organizer_id = ? AND b.status = 'confirmed') AS revenue,
        (SELECT COUNT(DISTINCT b.user_id) FROM bookings b JOIN events e ON e.id = b.event_id WHERE e.organizer_id = ? AND b.status = 'confirmed') AS attendees,
        (SELECT COUNT(*) FROM check_ins ci JOIN tickets t ON t.id = ci.ticket_id JOIN booking_items bi ON bi.id = t.booking_item_id
          JOIN bookings b ON b.id = bi.booking_id JOIN events e ON e.id = b.event_id WHERE e.organizer_id = ?) AS checkins,
        (SELECT COALESCE(SUM(b.subtotal), 0) FROM bookings b JOIN events e ON e.id = b.event_id
          WHERE e.organizer_id = ? AND b.status = 'confirmed' AND b.created_at >= NOW() - INTERVAL 7 DAY) AS revenue_7d",
    array_fill(0, 7, $oid));

$salesRows = db_all(
    "SELECT DATE(b.created_at) AS d, SUM(b.subtotal) AS v FROM bookings b JOIN events e ON e.id = b.event_id
      WHERE e.organizer_id = ? AND b.status = 'confirmed' AND b.created_at >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(b.created_at)", [$oid]);
$series = daily_series($salesRows, 30);

$upcoming = db_all(
    "SELECT e.id, e.title, e.slug, e.cover_image, e.starts_at, e.status,
            COALESCE(SUM(tt.quantity_sold), 0) AS sold, COALESCE(SUM(tt.quantity), 0) AS capacity
       FROM events e LEFT JOIN ticket_types tt ON tt.event_id = e.id
      WHERE e.organizer_id = ? AND e.ends_at >= NOW() AND e.status <> 'cancelled'
      GROUP BY e.id ORDER BY e.starts_at ASC LIMIT 5", [$oid]);

$recent = db_all(
    "SELECT b.reference, b.customer_name, b.subtotal, b.created_at, e.title,
            (SELECT SUM(quantity) FROM booking_items WHERE booking_id = b.id) AS qty
       FROM bookings b JOIN events e ON e.id = b.event_id
      WHERE e.organizer_id = ? AND b.status = 'confirmed' ORDER BY b.created_at DESC LIMIT 6", [$oid]);

$pastTickets = (int) db_value(
    "SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id
       JOIN events e ON e.id = b.event_id WHERE e.organizer_id = ? AND e.ends_at < NOW() AND t.status <> 'cancelled'", [$oid]);
$pastChecked = (int) db_value(
    "SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id
       JOIN events e ON e.id = b.event_id WHERE e.organizer_id = ? AND e.ends_at < NOW() AND t.status = 'checked_in'", [$oid]);
$rate = $pastTickets ? $pastChecked / $pastTickets * 100 : 0;

$pageTitle = 'Organizer dashboard';
$layout = 'dashboard';
$dashActive = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($org['status'] === 'pending'): ?>
    <div class="alert alert-warning mb-3"><?= icon('clock') ?><div><strong>Your organizer account is pending approval.</strong> You can complete your profile and draft events now — publishing unlocks once our team approves you (usually within 24 hours).</div></div>
<?php elseif ($org['status'] === 'rejected'): ?>
    <div class="alert alert-error mb-3"><?= icon('alert') ?><div><strong>Your organizer application was not approved.</strong> <?= e((string) $org['rejection_reason']) ?> Update your profile and contact support to re-apply.</div></div>
<?php endif; ?>

<div class="dash-head">
    <div>
        <h1>Good <?= (int) date('H') < 12 ? 'morning' : ((int) date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= e($user['first_name']) ?></h1>
        <p>Here's how <?= e($org['organization_name']) ?> is doing.</p>
    </div>
    <div class="actions">
        <a class="btn btn-outline btn-sm" href="<?= e(url('organizer/check-in.php')) ?>"><?= icon('scan') ?> Check-in</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('organizer/create-event.php')) ?>"><?= icon('plus') ?> Create event</a>
    </div>
</div>

<div class="stats">
    <div class="stat stat-dark"><span class="stat-icon"><?= icon('money') ?></span><small>Revenue</small><strong><?= e(money_exact($stats['revenue'])) ?></strong><span class="stat-foot">+<?= e(money_exact($stats['revenue_7d'])) ?> last 7 days</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('ticket') ?></span><small>Tickets sold</small><strong><?= number_format((int) $stats['sold']) ?></strong><span class="stat-foot">Across all events</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('calendar') ?></span><small>Total events</small><strong><?= (int) $stats['events'] ?></strong><span class="stat-foot"><?= (int) $stats['live'] ?> live now</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('users') ?></span><small>Attendees</small><strong><?= number_format((int) $stats['attendees']) ?></strong><span class="stat-foot">Unique customers</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('scan') ?></span><small>Check-ins</small><strong><?= number_format((int) $stats['checkins']) ?></strong><span class="stat-foot"><?= round($rate) ?>% attendance rate</span></div>
</div>

<div class="dash-grid">
    <section class="card">
        <div class="card-head"><h2>Revenue · last 30 days</h2><a class="link small" href="<?= e(url('organizer/analytics.php')) ?>">Full analytics</a></div>
        <?= svg_line_chart($series, '€') ?>
    </section>
    <section class="card">
        <div class="card-head"><h2>Attendance</h2></div>
        <div class="donut-wrap">
            <?= svg_donut($rate, 'checked in') ?>
            <div>
                <p class="mb-0"><strong><?= number_format($pastChecked) ?></strong> of <?= number_format($pastTickets) ?> tickets were scanned at past events.</p>
                <p class="small muted mt-1 mb-0">Industry average is around 80%.</p>
            </div>
        </div>
    </section>
</div>

<div class="dash-grid">
    <section class="card">
        <div class="card-head"><h2>Upcoming events</h2><a class="link small" href="<?= e(url('organizer/events.php')) ?>">Manage events</a></div>
        <?php if ($upcoming): ?>
            <div class="hbars">
                <?php foreach ($upcoming as $u): $pct = $u['capacity'] ? (int) round($u['sold'] / $u['capacity'] * 100) : 0; ?>
                    <div class="hbar">
                        <div class="hbar-top">
                            <span><a href="<?= e(url('organizer/edit-event.php?id=' . $u['id'])) ?>"><?= e($u['title']) ?></a> <?= $u['status'] !== 'published' ? status_badge($u['status']) : '' ?></span>
                            <span><?= number_format((int) $u['sold']) ?>/<?= number_format((int) $u['capacity']) ?> · <?= e(fmt_date($u['starts_at'], 'M j')) ?></span>
                        </div>
                        <div class="hbar-track"><i style="width: <?= $pct ?>%"></i></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:28px"><div class="empty-icon"><?= icon('calendar') ?></div><h3>No upcoming events</h3><p>Create your first event and start selling tickets.</p><a class="btn btn-primary" href="<?= e(url('organizer/create-event.php')) ?>">Create event</a></div>
        <?php endif; ?>
    </section>
    <section class="card">
        <div class="card-head"><h2>Latest bookings</h2><a class="link small" href="<?= e(url('organizer/attendees.php')) ?>">All attendees</a></div>
        <?php if ($recent): ?>
            <div class="list">
                <?php foreach ($recent as $r): ?>
                    <div class="list-item">
                        <span class="avatar-initials" style="width:40px;height:40px"><?= e(initials($r['customer_name'])) ?></span>
                        <div class="li-main"><strong><?= e($r['customer_name']) ?></strong><span><?= (int) $r['qty'] ?>× · <?= e(str_limit($r['title'], 28)) ?> · <?= e(time_ago($r['created_at'])) ?></span></div>
                        <div class="li-side"><?= e($r['subtotal'] > 0 ? money_exact($r['subtotal']) : 'Free') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted mb-0">No bookings yet.</p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

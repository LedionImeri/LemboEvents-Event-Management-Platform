<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/charts.php';

$org = require_organizer();
$oid = (int) $org['id'];
$range = (int) ($_GET['range'] ?? 30);
$range = in_array($range, [7, 30, 90], true) ? $range : 30;

$totals = db_one(
    "SELECT COALESCE(SUM(b.subtotal), 0) AS revenue, COUNT(*) AS bookings,
            COALESCE(SUM((SELECT SUM(quantity) FROM booking_items WHERE booking_id = b.id)), 0) AS tickets
       FROM bookings b JOIN events e ON e.id = b.event_id
      WHERE e.organizer_id = ? AND b.status = 'confirmed' AND b.created_at >= CURDATE() - INTERVAL ? DAY", [$oid, $range - 1]);
$prev = db_one(
    "SELECT COALESCE(SUM(b.subtotal), 0) AS revenue FROM bookings b JOIN events e ON e.id = b.event_id
      WHERE e.organizer_id = ? AND b.status = 'confirmed' AND b.created_at >= CURDATE() - INTERVAL ? DAY AND b.created_at < CURDATE() - INTERVAL ? DAY",
    [$oid, $range * 2 - 1, $range - 1]);
$growth = (float) $prev['revenue'] > 0 ? round(((float) $totals['revenue'] - (float) $prev['revenue']) / (float) $prev['revenue'] * 100) : null;

$revRows = db_all("SELECT DATE(b.created_at) AS d, SUM(b.subtotal) AS v FROM bookings b JOIN events e ON e.id = b.event_id
                    WHERE e.organizer_id = ? AND b.status = 'confirmed' AND b.created_at >= CURDATE() - INTERVAL ? DAY GROUP BY DATE(b.created_at)", [$oid, $range - 1]);
$tixRows = db_all("SELECT DATE(b.created_at) AS d, SUM(bi.quantity) AS v FROM bookings b JOIN booking_items bi ON bi.booking_id = b.id JOIN events e ON e.id = b.event_id
                    WHERE e.organizer_id = ? AND b.status = 'confirmed' AND b.created_at >= CURDATE() - INTERVAL ? DAY GROUP BY DATE(b.created_at)", [$oid, $range - 1]);

$perEvent = db_all(
    "SELECT e.id, e.title, e.starts_at, e.ends_at, e.views, e.status,
            COALESCE(SUM(tt.quantity_sold), 0) AS sold, COALESCE(SUM(tt.quantity), 0) AS capacity,
            (SELECT COALESCE(SUM(subtotal), 0) FROM bookings WHERE event_id = e.id AND status = 'confirmed') AS revenue,
            (SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id WHERE b.event_id = e.id AND t.status = 'checked_in') AS checked
       FROM events e LEFT JOIN ticket_types tt ON tt.event_id = e.id
      WHERE e.organizer_id = ? AND e.status IN ('published','cancelled')
      GROUP BY e.id ORDER BY e.starts_at DESC", [$oid]);

$byType = db_all(
    "SELECT tt.name, SUM(bi.quantity) AS qty, SUM(bi.line_total) AS revenue
       FROM booking_items bi JOIN bookings b ON b.id = bi.booking_id JOIN ticket_types tt ON tt.id = bi.ticket_type_id JOIN events e ON e.id = b.event_id
      WHERE e.organizer_id = ? AND b.status = 'confirmed' GROUP BY tt.name ORDER BY revenue DESC LIMIT 6", [$oid]);
$typeMax = $byType ? max(array_column($byType, 'revenue')) : 1;

$weekday = db_all("SELECT DAYOFWEEK(b.created_at) AS dow, SUM(bi.quantity) AS qty FROM bookings b JOIN booking_items bi ON bi.booking_id = b.id JOIN events e ON e.id = b.event_id
                    WHERE e.organizer_id = ? AND b.status = 'confirmed' GROUP BY DAYOFWEEK(b.created_at)", [$oid]);
$dowNames = [2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat', 1 => 'Sun'];
$dowData = [];
foreach ($dowNames as $k => $n) {
    $dowData[$n] = 0;
}
foreach ($weekday as $w) {
    $dowData[$dowNames[(int) $w['dow']]] = (int) $w['qty'];
}

$pastSold = 0; $pastChecked = 0;
foreach ($perEvent as $p) {
    if (strtotime($p['ends_at']) < time() && $p['status'] === 'published') { $pastSold += (int) $p['sold']; $pastChecked += (int) $p['checked']; }
}

$pageTitle = 'Analytics';
$layout = 'dashboard';
$dashActive = 'analytics';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>Analytics</h1><p>Ticket sales, revenue and attendance across your events.</p></div>
    <nav class="tabs" style="margin:0;border:0" aria-label="Date range">
        <?php foreach ([7, 30, 90] as $r): ?><a href="?range=<?= $r ?>" class="<?= $range === $r ? 'active' : '' ?>"><?= $r ?> days</a><?php endforeach; ?>
    </nav>
</div>

<div class="stats">
    <div class="stat stat-dark"><span class="stat-icon"><?= icon('money') ?></span><small>Revenue (<?= $range ?>d)</small><strong><?= e(money_exact($totals['revenue'])) ?></strong>
        <span class="stat-foot<?= $growth !== null && $growth >= 0 ? ' up' : '' ?>"><?= $growth === null ? 'No previous data' : ($growth >= 0 ? '▲ ' : '▼ ') . abs($growth) . '% vs previous ' . $range . 'd' ?></span></div>
    <div class="stat"><span class="stat-icon"><?= icon('ticket') ?></span><small>Tickets sold (<?= $range ?>d)</small><strong><?= number_format((int) $totals['tickets']) ?></strong><span class="stat-foot"><?= number_format((int) $totals['bookings']) ?> bookings</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('trend') ?></span><small>Avg. order value</small><strong><?= e(money_exact($totals['bookings'] ? $totals['revenue'] / $totals['bookings'] : 0)) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('scan') ?></span><small>Check-in rate</small><strong><?= $pastSold ? round($pastChecked / $pastSold * 100) : 0 ?>%</strong><span class="stat-foot">Past events</span></div>
</div>

<div class="dash-grid-2">
    <section class="card">
        <div class="card-head"><h2>Revenue</h2><span class="small muted">Organizer share, excl. fees</span></div>
        <?= svg_line_chart(daily_series($revRows, $range), '€') ?>
    </section>
    <section class="card">
        <div class="card-head"><h2>Tickets sold per day</h2></div>
        <?= svg_bar_chart(daily_series($tixRows, $range)) ?>
    </section>
</div>

<div class="dash-grid-2">
    <section class="card">
        <div class="card-head"><h2>Revenue by ticket type</h2></div>
        <div class="hbars">
            <?php foreach ($byType as $t): ?>
                <div class="hbar">
                    <div class="hbar-top"><span><?= e($t['name']) ?></span><span><?= number_format((int) $t['qty']) ?> · <?= e(money_exact($t['revenue'])) ?></span></div>
                    <div class="hbar-track"><i style="width: <?= $typeMax > 0 ? round($t['revenue'] / $typeMax * 100) : 0 ?>%"></i></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$byType): ?><p class="muted mb-0">No sales yet.</p><?php endif; ?>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><h2>When people buy</h2><span class="small muted">Tickets by weekday</span></div>
        <?= svg_bar_chart($dowData, '', 200) ?>
    </section>
</div>

<section class="card">
    <div class="card-head"><h2>Performance by event</h2></div>
    <div class="table-wrap" style="border:0">
        <table class="table">
            <thead><tr><th>Event</th><th class="num">Views</th><th class="num">Sold</th><th class="num">Sell-through</th><th class="num">Conversion</th><th class="num">Revenue</th><th class="num">Check-in</th></tr></thead>
            <tbody>
            <?php foreach ($perEvent as $p):
                $through = $p['capacity'] ? round($p['sold'] / $p['capacity'] * 100) : 0;
                $past = strtotime($p['ends_at']) < time(); ?>
                <tr>
                    <td><strong><?= e($p['title']) ?></strong><br><span class="small muted"><?= e(fmt_date($p['starts_at'])) ?> <?= $p['status'] === 'cancelled' ? '· cancelled' : ($past ? '· ended' : '') ?></span></td>
                    <td class="num"><?= number_format((int) $p['views']) ?></td>
                    <td class="num"><?= number_format((int) $p['sold']) ?>/<?= number_format((int) $p['capacity']) ?></td>
                    <td class="num"><?= $through ?>%</td>
                    <td class="num"><?= $p['views'] ? round($p['sold'] / $p['views'] * 100, 1) : 0 ?>%</td>
                    <td class="num"><?= e(money_exact($p['revenue'])) ?></td>
                    <td class="num"><?= $past && $p['sold'] ? round($p['checked'] / $p['sold'] * 100) . '%' : ((int) $p['checked'] ? (int) $p['checked'] . ' in' : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/charts.php';

require_role('admin');

$s = db_one(
    "SELECT
        (SELECT COUNT(*) FROM users) AS users,
        (SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 30 DAY) AS users_30d,
        (SELECT COUNT(*) FROM organizer_profiles WHERE status = 'approved') AS organizers,
        (SELECT COUNT(*) FROM organizer_profiles WHERE status = 'pending') AS organizers_pending,
        (SELECT COUNT(*) FROM events) AS events,
        (SELECT COUNT(*) FROM events WHERE status = 'pending') AS events_pending,
        (SELECT COUNT(*) FROM events WHERE status = 'published' AND starts_at >= NOW()) AS upcoming,
        (SELECT COUNT(*) FROM tickets WHERE status <> 'cancelled') AS tickets,
        (SELECT COUNT(*) FROM bookings) AS bookings,
        (SELECT COALESCE(SUM(total), 0) FROM bookings WHERE status = 'confirmed') AS gross,
        (SELECT COALESCE(SUM(service_fee), 0) FROM bookings WHERE status = 'confirmed') AS fees,
        (SELECT COUNT(*) FROM reports WHERE status = 'open') AS reports_open,
        (SELECT COUNT(*) FROM users WHERE status = 'suspended') AS suspended");

$gmvRows = db_all("SELECT DATE(created_at) AS d, SUM(total) AS v FROM bookings WHERE status = 'confirmed' AND created_at >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(created_at)");
$signupRows = db_all("SELECT DATE(created_at) AS d, COUNT(*) AS v FROM users WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)");
$byCategory = db_all(
    "SELECT c.name, c.color, COALESCE(SUM(tt.quantity_sold), 0) AS sold
       FROM categories c LEFT JOIN events e ON e.category_id = c.id LEFT JOIN ticket_types tt ON tt.event_id = e.id
      GROUP BY c.id ORDER BY sold DESC LIMIT 6");
$catMax = max(1, ...array_map(fn($c) => (int) $c['sold'], $byCategory ?: [['sold' => 1]]));
$pendingEvents = db_all("SELECT e.id, e.title, e.slug, e.starts_at, e.created_at, o.organization_name FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE e.status = 'pending' ORDER BY e.updated_at ASC LIMIT 5");
$pendingOrgs = db_all("SELECT o.id, o.organization_name, o.created_at, u.email FROM organizer_profiles o JOIN users u ON u.id = o.user_id WHERE o.status = 'pending' ORDER BY o.created_at ASC LIMIT 5");
$activity = db_all("SELECT a.*, u.first_name, u.last_name FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 8");

$pageTitle = 'Admin dashboard';
$layout = 'dashboard';
$dashActive = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>Platform overview</h1><p>Everything happening on LemboEvents right now.</p></div>
    <div class="actions">
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/events.php?status=pending')) ?>"><?= icon('shield') ?> Moderation queue <?php if ($s['events_pending'] + $s['organizers_pending']): ?><span class="count-pill" style="background:#fff;color:var(--flame)"><?= (int) ($s['events_pending'] + $s['organizers_pending']) ?></span><?php endif; ?></a>
    </div>
</div>

<div class="stats">
    <div class="stat stat-dark"><span class="stat-icon"><?= icon('money') ?></span><small>Total revenue (GMV)</small><strong><?= e(money_exact($s['gross'])) ?></strong><span class="stat-foot"><?= e(money_exact($s['fees'])) ?> platform fees</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('users') ?></span><small>Total users</small><strong><?= number_format((int) $s['users']) ?></strong><span class="stat-foot up">+<?= (int) $s['users_30d'] ?> last 30 days</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('building') ?></span><small>Organizers</small><strong><?= (int) $s['organizers'] ?></strong><span class="stat-foot"><?= (int) $s['organizers_pending'] ?> awaiting approval</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('calendar') ?></span><small>Total events</small><strong><?= (int) $s['events'] ?></strong><span class="stat-foot"><?= (int) $s['upcoming'] ?> upcoming</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('ticket') ?></span><small>Tickets sold</small><strong><?= number_format((int) $s['tickets']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('card') ?></span><small>Total bookings</small><strong><?= number_format((int) $s['bookings']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('flag') ?></span><small>Open reports</small><strong><?= (int) $s['reports_open'] ?></strong><span class="stat-foot"><?= (int) $s['suspended'] ?> suspended accounts</span></div>
    <div class="stat"><span class="stat-icon"><?= icon('clock') ?></span><small>Upcoming events</small><strong><?= (int) $s['upcoming'] ?></strong><span class="stat-foot">Published &amp; on sale</span></div>
</div>

<div class="dash-grid">
    <section class="card">
        <div class="card-head"><h2>Gross sales · 30 days</h2><a class="link small" href="<?= e(url('admin/bookings.php')) ?>">Bookings</a></div>
        <?= svg_line_chart(daily_series($gmvRows, 30), '€') ?>
    </section>
    <section class="card">
        <div class="card-head"><h2>Tickets by category</h2></div>
        <div class="hbars">
            <?php foreach ($byCategory as $c): ?>
                <div class="hbar">
                    <div class="hbar-top"><span><?= e($c['name']) ?></span><span><?= number_format((int) $c['sold']) ?></span></div>
                    <div class="hbar-track"><i style="width: <?= round($c['sold'] / $catMax * 100) ?>%; background: <?= e($c['color']) ?>"></i></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="dash-grid-2">
    <section class="card">
        <div class="card-head"><h2>Events awaiting approval</h2><a class="link small" href="<?= e(url('admin/events.php?status=pending')) ?>">Review all</a></div>
        <?php if ($pendingEvents): ?>
            <div class="list">
                <?php foreach ($pendingEvents as $p): ?>
                    <div class="list-item">
                        <div class="date-block"><small><?= e(fmt_date($p['starts_at'], 'M')) ?></small><strong><?= e(fmt_date($p['starts_at'], 'j')) ?></strong></div>
                        <div class="li-main"><strong><a href="<?= e(url('event.php?slug=' . $p['slug'])) ?>"><?= e($p['title']) ?></a></strong><span><?= e($p['organization_name']) ?> · submitted <?= e(time_ago($p['created_at'])) ?></span></div>
                        <a class="btn btn-soft btn-xs" href="<?= e(url('admin/events.php?status=pending')) ?>">Review</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?><p class="muted mb-0"><?= icon('check-circle', 'icon-sm') ?> Queue is empty. Nice work!</p><?php endif; ?>
    </section>
    <section class="card">
        <div class="card-head"><h2>Organizer applications</h2><a class="link small" href="<?= e(url('admin/organizers.php?status=pending')) ?>">Review all</a></div>
        <?php if ($pendingOrgs): ?>
            <div class="list">
                <?php foreach ($pendingOrgs as $o): ?>
                    <div class="list-item">
                        <span class="org-logo" style="width:44px;height:44px;font-size:.9rem;border-radius:12px"><?= e(initials($o['organization_name'])) ?></span>
                        <div class="li-main"><strong><?= e($o['organization_name']) ?></strong><span><?= e($o['email']) ?> · <?= e(time_ago($o['created_at'])) ?></span></div>
                        <a class="btn btn-soft btn-xs" href="<?= e(url('admin/organizers.php?status=pending')) ?>">Review</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?><p class="muted mb-0"><?= icon('check-circle', 'icon-sm') ?> No pending applications.</p><?php endif; ?>
    </section>
</div>

<div class="dash-grid">
    <section class="card">
        <div class="card-head"><h2>Recent activity</h2><a class="link small" href="<?= e(url('admin/activity.php')) ?>">Full log</a></div>
        <div class="list">
            <?php foreach ($activity as $a): ?>
                <div class="list-item">
                    <span class="avatar-initials" style="width:36px;height:36px"><?= e($a['first_name'] ? initials($a['first_name'] . ' ' . $a['last_name']) : '•') ?></span>
                    <div class="li-main"><strong style="white-space:normal"><?= e($a['description']) ?></strong><span><?= e($a['first_name'] ? $a['first_name'] . ' ' . $a['last_name'] : 'System') ?> · <?= e(time_ago($a['created_at'])) ?></span></div>
                    <code class="small muted"><?= e($a['action']) ?></code>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><h2>New users · 14 days</h2></div>
        <?= svg_bar_chart(daily_series($signupRows, 14), '', 240) ?>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

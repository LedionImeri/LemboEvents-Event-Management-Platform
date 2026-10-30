<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

$admin = require_role('admin');

if (is_post()) {
    csrf_verify();
    try {
        $b = cancel_booking((int) ($_POST['booking_id'] ?? 0), $admin);
        flash('success', 'Booking ' . $b['reference'] . ' cancelled and refunded.');
    } catch (BookingException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/bookings.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'status', 'page']))));
}

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$status = in_array($_GET['status'] ?? '', ['confirmed', 'cancelled', 'refunded'], true) ? $_GET['status'] : '';
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 'b.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(b.reference LIKE ? OR b.customer_name LIKE ? OR b.customer_email LIKE ? OR e.title LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like, $like, $like); }
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM bookings b JOIN events e ON e.id = b.event_id WHERE $w", $params);
$pg = paginate($total, 25);
$rows = db_all("SELECT b.*, e.title, e.starts_at, (SELECT SUM(quantity) FROM booking_items WHERE booking_id = b.id) AS qty
                  FROM bookings b JOIN events e ON e.id = b.event_id WHERE $w ORDER BY b.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$sum = db_one("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status='confirmed' THEN total END), 0) AS gross,
                      COALESCE(SUM(CASE WHEN status='confirmed' THEN service_fee END), 0) AS fees,
                      COALESCE(SUM(CASE WHEN status IN ('refunded','cancelled') THEN total END), 0) AS refunded FROM bookings");

$pageTitle = 'Bookings';
$layout = 'dashboard';
$dashActive = 'bookings';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Bookings</h1><p>Every order on the platform. All payments are demo transactions.</p></div></div>
<div class="stats">
    <div class="stat stat-dark"><span class="stat-icon"><?= icon('money') ?></span><small>Gross sales</small><strong><?= e(money_exact($sum['gross'])) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('trend') ?></span><small>Platform fees</small><strong><?= e(money_exact($sum['fees'])) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('card') ?></span><small>Bookings</small><strong><?= number_format((int) $sum['n']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('refresh') ?></span><small>Refunded</small><strong><?= e(money_exact($sum['refunded'])) ?></strong></div>
</div>
<form class="toolbar" method="get">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Reference, customer or event" aria-label="Search bookings"></div>
    <select class="select" name="status" data-autosubmit aria-label="Status"><option value="">Any status</option><?php foreach (['confirmed', 'cancelled', 'refunded'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark btn-sm">Search</button>
</form>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Booking</th><th>Customer</th><th>Event</th><th class="num">Tickets</th><th class="num">Total</th><th>Status</th><th class="num"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $b): ?>
            <tr>
                <td><a class="link" href="<?= e(url('booking.php?ref=' . $b['reference'])) ?>"><?= e($b['reference']) ?></a><br><span class="small muted"><?= e(fmt_date($b['created_at'], 'M j, Y H:i')) ?></span></td>
                <td><?= e($b['customer_name']) ?><br><span class="small muted"><?= e($b['customer_email']) ?></span></td>
                <td class="small"><?= e(str_limit($b['title'], 32)) ?><br><span class="muted"><?= e(fmt_date($b['starts_at'])) ?></span></td>
                <td class="num"><?= (int) $b['qty'] ?></td>
                <td class="num"><?= e(money_exact($b['total'])) ?><br><span class="small muted"><?= $b['payment_method'] === 'free' ? 'Free' : '•••• ' . e((string) $b['payment_last4']) ?></span></td>
                <td><?= status_badge($b['status']) ?></td>
                <td class="num">
                    <?php if ($b['status'] === 'confirmed'): ?>
                        <form method="post" class="inline-form" data-confirm="Cancel and refund booking <?= e($b['reference']) ?> (<?= e(money_exact($b['total'])) ?>)? The customer will be notified." data-confirm-button="Cancel &amp; refund">
                            <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><button class="btn btn-danger-soft btn-xs">Refund</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7"><div class="text-center muted" style="padding:30px">No bookings found.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($pg) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

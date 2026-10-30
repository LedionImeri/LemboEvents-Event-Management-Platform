<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

require_role('admin');

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$status = in_array($_GET['status'] ?? '', ['valid', 'checked_in', 'cancelled'], true) ? $_GET['status'] : '';
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 't.status = ?'; $params[] = $status; }
if ($q !== '') {
    $code = strtoupper(str_replace(['-', ' '], '', $q));
    $where[] = '(t.code = ? OR b.reference = ? OR t.attendee_name LIKE ? OR b.customer_email LIKE ? OR e.title LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $code, strtoupper($q), $like, $like, $like);
}
$w = implode(' AND ', $where);
$base = "FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id
         JOIN ticket_types tt ON tt.id = bi.ticket_type_id JOIN events e ON e.id = b.event_id LEFT JOIN check_ins ci ON ci.ticket_id = t.id
        WHERE $w";
$total = (int) db_value("SELECT COUNT(*) $base", $params);
$pg = paginate($total, 25);
$rows = db_all("SELECT t.*, tt.name AS ticket_type, tt.price, b.reference, b.customer_email, e.title AS event_title, e.starts_at, ci.checked_in_at, ci.method
                $base ORDER BY t.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$sum = db_one("SELECT COUNT(*) AS total, SUM(status='valid') AS valid, SUM(status='checked_in') AS used, SUM(status='cancelled') AS cancelled FROM tickets");

$pageTitle = 'Tickets';
$layout = 'dashboard';
$dashActive = 'tickets';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Tickets</h1><p>Look up any ticket by code, booking, attendee or event.</p></div></div>
<div class="stats">
    <div class="stat"><span class="stat-icon"><?= icon('ticket') ?></span><small>All tickets</small><strong><?= number_format((int) $sum['total']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('check-circle') ?></span><small>Valid</small><strong><?= number_format((int) $sum['valid']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('scan') ?></span><small>Checked in</small><strong><?= number_format((int) $sum['used']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('x-circle') ?></span><small>Cancelled</small><strong><?= number_format((int) $sum['cancelled']) ?></strong></div>
</div>
<form class="toolbar" method="get">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Ticket code, booking ref, name, email, event" aria-label="Search tickets"></div>
    <select class="select" name="status" data-autosubmit aria-label="Status"><option value="">Any status</option><option value="valid"<?= $status === 'valid' ? ' selected' : '' ?>>Valid</option><option value="checked_in"<?= $status === 'checked_in' ? ' selected' : '' ?>>Checked in</option><option value="cancelled"<?= $status === 'cancelled' ? ' selected' : '' ?>>Cancelled</option></select>
    <button class="btn btn-dark btn-sm">Search</button>
</form>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Ticket</th><th>Attendee</th><th>Event</th><th>Booking</th><th>Status</th><th class="num"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong>#<?= str_pad((string) $r['id'], 6, '0', STR_PAD_LEFT) ?></strong><br><span class="small muted" style="font-family:ui-monospace,monospace"><?= e(format_ticket_code($r['code'])) ?></span></td>
                <td><?= e($r['attendee_name']) ?><br><span class="small muted"><?= e($r['customer_email']) ?></span></td>
                <td class="small"><?= e(str_limit($r['event_title'], 30)) ?><br><span class="muted"><?= e($r['ticket_type']) ?> · <?= e(money($r['price'])) ?></span></td>
                <td class="small"><a class="link" href="<?= e(url('booking.php?ref=' . $r['reference'])) ?>"><?= e($r['reference']) ?></a></td>
                <td><?= status_badge($r['status']) ?><?php if ($r['checked_in_at']): ?><br><span class="small muted"><?= e(fmt_date($r['checked_in_at'], 'M j H:i')) ?> · <?= e($r['method']) ?></span><?php endif; ?></td>
                <td class="num"><a class="btn btn-ghost btn-xs" href="<?= e(url('ticket.php?code=' . $r['code'])) ?>" aria-label="View ticket"><?= icon('qr') ?></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6"><div class="text-center muted" style="padding:30px">No tickets found.</div></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($pg) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/organizer.php';

$org = require_organizer();
$events = org_events_list($org);
$eventId = (int) ($_GET['event'] ?? 0);
if ($eventId) {
    org_event_or_404($eventId, $org);
}
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$status = in_array($_GET['status'] ?? '', ['valid', 'checked_in', 'cancelled'], true) ? $_GET['status'] : '';

$where = ['e.organizer_id = ?'];
$params = [$org['id']];
if ($eventId) { $where[] = 'e.id = ?'; $params[] = $eventId; }
if ($status) { $where[] = 't.status = ?'; $params[] = $status; }
if ($q !== '') {
    $where[] = '(t.attendee_name LIKE ? OR b.customer_email LIKE ? OR t.code LIKE ? OR b.reference LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    array_push($params, $like, $like, '%' . str_replace('-', '', strtoupper($q)) . '%', $like);
}
$whereSql = implode(' AND ', $where);
$base = "FROM tickets t
         JOIN booking_items bi ON bi.id = t.booking_item_id
         JOIN bookings b ON b.id = bi.booking_id
         JOIN ticket_types tt ON tt.id = bi.ticket_type_id
         JOIN events e ON e.id = b.event_id
         LEFT JOIN check_ins ci ON ci.ticket_id = t.id
        WHERE $whereSql";
$select = "SELECT t.id, t.code, t.attendee_name, t.status, b.customer_email, b.reference, b.created_at AS purchased_at,
                  tt.name AS ticket_type, tt.price, e.title AS event_title, ci.checked_in_at";

// CSV export (same filters)
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db_all("$select $base ORDER BY e.starts_at, t.attendee_name", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendees-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    echo implode(',', array_map('csv_cell', ['Name', 'Email', 'Event', 'Ticket', 'Price', 'Ticket code', 'Booking', 'Purchased', 'Status', 'Checked in at'])) . "\r\n";
    foreach ($rows as $r) {
        echo implode(',', array_map('csv_cell', [$r['attendee_name'], $r['customer_email'], $r['event_title'], $r['ticket_type'], $r['price'],
            format_ticket_code($r['code']), $r['reference'], $r['purchased_at'], $r['status'], $r['checked_in_at']])) . "\r\n";
    }
    exit;
}

$total = (int) db_value("SELECT COUNT(*) $base", $params);
$pg = paginate($total, 25);
$rows = db_all("$select $base ORDER BY b.created_at DESC, t.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$summary = db_one("SELECT COUNT(*) AS total, SUM(t.status = 'checked_in') AS checked, SUM(t.status = 'valid') AS valid, COUNT(DISTINCT b.user_id) AS people $base", $params);

$pageTitle = 'Attendees';
$layout = 'dashboard';
$dashActive = 'attendees';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div><h1>Attendees</h1><p>Everyone holding a ticket to your events.</p></div>
    <div class="actions">
        <a class="btn btn-soft btn-sm" href="?<?= e(http_build_query(array_filter(['event' => $eventId ?: null, 'q' => $q ?: null, 'status' => $status ?: null, 'export' => 'csv']))) ?>"><?= icon('download') ?> Export CSV</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('organizer/check-in.php' . ($eventId ? '?event=' . $eventId : ''))) ?>"><?= icon('scan') ?> Open scanner</a>
    </div>
</div>

<div class="stats">
    <div class="stat"><span class="stat-icon"><?= icon('ticket') ?></span><small>Tickets</small><strong><?= number_format((int) $summary['total']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('users') ?></span><small>Unique buyers</small><strong><?= number_format((int) $summary['people']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('check-circle') ?></span><small>Checked in</small><strong><?= number_format((int) $summary['checked']) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('clock') ?></span><small>Not yet arrived</small><strong><?= number_format((int) $summary['valid']) ?></strong></div>
</div>

<form class="toolbar" method="get">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, ticket code or booking…" aria-label="Search attendees"></div>
    <select class="select" name="event" aria-label="Event" data-autosubmit>
        <option value="">All events</option>
        <?php foreach ($events as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= $eventId === (int) $ev['id'] ? ' selected' : '' ?>><?= e(str_limit($ev['title'], 40)) ?></option><?php endforeach; ?>
    </select>
    <select class="select" name="status" aria-label="Status" data-autosubmit>
        <option value="">Any status</option>
        <option value="valid"<?= $status === 'valid' ? ' selected' : '' ?>>Not checked in</option>
        <option value="checked_in"<?= $status === 'checked_in' ? ' selected' : '' ?>>Checked in</option>
        <option value="cancelled"<?= $status === 'cancelled' ? ' selected' : '' ?>>Cancelled</option>
    </select>
    <button class="btn btn-dark btn-sm" type="submit">Search</button>
</form>

<?php if ($rows): ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Ticket</th><th>Event</th><th>Purchased</th><th>Check-in</th><th class="num">Action</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><div class="cell-main" style="min-width:180px"><span class="avatar-initials" style="width:36px;height:36px"><?= e(initials($r['attendee_name'])) ?></span><div><strong><?= e($r['attendee_name']) ?></strong><span><?= e($r['customer_email']) ?></span></div></div></td>
                    <td><strong><?= e($r['ticket_type']) ?></strong><br><span class="small muted" style="font-family:ui-monospace,monospace"><?= e(format_ticket_code($r['code'])) ?></span></td>
                    <td class="small"><?= e(str_limit($r['event_title'], 32)) ?></td>
                    <td class="nowrap small"><?= e(fmt_date($r['purchased_at'], 'M j, Y H:i')) ?></td>
                    <td><?= status_badge($r['status']) ?><?php if ($r['checked_in_at']): ?><br><span class="small muted"><?= e(fmt_date($r['checked_in_at'], 'M j, H:i')) ?></span><?php endif; ?></td>
                    <td class="num">
                        <?php if ($r['status'] === 'valid'): ?>
                            <button type="button" class="btn btn-success btn-xs" data-manual-checkin="<?= e($r['code']) ?>"><?= icon('check') ?> Check in</button>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_links($pg) ?>
<?php else: ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('users') ?></div><h3>No attendees found</h3><p><?= $q || $status ? 'Try a different search or filter.' : 'When people book tickets, they appear here.' ?></p></div>
<?php endif; ?>
<?php
$pageScripts = ['attendees.js'];
require __DIR__ . '/../includes/footer.php';

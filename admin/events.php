<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

$admin = require_role('admin');

if (is_post()) {
    csrf_verify();
    $ev = db_one('SELECT e.*, o.user_id AS org_user_id, o.status AS org_status FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE e.id = ?', [(int) ($_POST['event_id'] ?? 0)]);
    $action = $_POST['action'] ?? '';
    try {
        if (!$ev) {
            throw new RuntimeException('Event not found.');
        }
        switch ($action) {
            case 'approve':
                if ($ev['org_status'] !== 'approved') throw new RuntimeException('Approve the organizer first.');
                db_exec("UPDATE events SET status = 'published', approved_at = COALESCE(approved_at, NOW()), rejection_reason = NULL WHERE id = ?", [$ev['id']]);
                notify((int) $ev['org_user_id'], 'event_approved', 'Event approved 🎉', $ev['title'] . ' is now live on LemboEvents.', 'event.php?slug=' . $ev['slug']);
                log_activity('event.approved', 'Approved event ' . $ev['title'], 'event', (int) $ev['id']);
                flash('success', '“' . $ev['title'] . '” is now live.');
                break;
            case 'reject':
                $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255) ?: 'The event does not meet our listing guidelines.';
                if ((int) db_value("SELECT COUNT(*) FROM bookings WHERE event_id = ? AND status = 'confirmed'", [$ev['id']]) > 0) {
                    throw new RuntimeException('This event has bookings. Cancel it instead so attendees are refunded.');
                }
                db_exec("UPDATE events SET status = 'rejected', rejection_reason = ?, is_featured = 0 WHERE id = ?", [$reason, $ev['id']]);
                notify((int) $ev['org_user_id'], 'event_rejected', 'Changes needed: ' . $ev['title'], $reason, 'organizer/edit-event.php?id=' . $ev['id']);
                log_activity('event.rejected', 'Rejected event ' . $ev['title'], 'event', (int) $ev['id']);
                flash('success', 'Event rejected and the organizer was notified.');
                break;
            case 'feature':
                db_exec('UPDATE events SET is_featured = 1 - is_featured WHERE id = ?', [$ev['id']]);
                flash('success', $ev['is_featured'] ? 'Removed from featured.' : 'Event featured on the homepage.');
                break;
            case 'cancel':
                $n = cancel_event((int) $ev['id'], $admin, 'cancelled by LemboEvents');
                notify((int) $ev['org_user_id'], 'event_cancelled', 'Event cancelled by LemboEvents', $ev['title'] . ' was cancelled by our moderation team. ' . $n . ' bookings were refunded.', 'organizer/events.php?tab=cancelled');
                flash('success', 'Event cancelled. ' . $n . ' bookings refunded.');
                break;
            default:
                throw new RuntimeException('Unknown action.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/events.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'q', 'category', 'page']))));
}

$status = in_array($_GET['status'] ?? '', ['draft', 'pending', 'published', 'rejected', 'cancelled'], true) ? $_GET['status'] : '';
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$cat = (int) ($_GET['category'] ?? 0);
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 'e.status = ?'; $params[] = $status; }
if ($cat) { $where[] = 'e.category_id = ?'; $params[] = $cat; }
if ($q !== '') { $where[] = '(e.title LIKE ? OR o.organization_name LIKE ? OR e.city LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like, $like); }
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE $w", $params);
$pg = paginate($total, 20);
$events = db_all(
    "SELECT e.*, o.organization_name, c.name AS category_name,
            COALESCE((SELECT SUM(quantity_sold) FROM ticket_types WHERE event_id = e.id), 0) AS sold,
            COALESCE((SELECT SUM(quantity) FROM ticket_types WHERE event_id = e.id), 0) AS capacity,
            COALESCE((SELECT SUM(total) FROM bookings WHERE event_id = e.id AND status = 'confirmed'), 0) AS gross,
            (SELECT COUNT(*) FROM reports WHERE target_type = 'event' AND target_id = e.id AND status = 'open') AS open_reports
       FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id JOIN categories c ON c.id = e.category_id
      WHERE $w ORDER BY FIELD(e.status, 'pending') DESC, e.starts_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$counts = [];
foreach (db_all('SELECT status, COUNT(*) AS n FROM events GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$categories = db_all('SELECT id, name FROM categories ORDER BY sort_order');

$pageTitle = 'Events';
$layout = 'dashboard';
$dashActive = 'events';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Events</h1><p>Approve, feature, reject or cancel events across the platform.</p></div></div>
<nav class="tabs" aria-label="Event status">
    <a href="?" class="<?= $status === '' ? 'active' : '' ?>">All <span class="tab-count"><?= array_sum($counts) ?></span></a>
    <?php foreach (['pending' => 'Pending', 'published' => 'Published', 'draft' => 'Drafts', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $k => $l): ?>
        <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $l ?> <span class="tab-count"><?= $counts[$k] ?? 0 ?></span></a>
    <?php endforeach; ?>
</nav>
<form class="toolbar" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Title, organizer or city" aria-label="Search events"></div>
    <select class="select" name="category" data-autosubmit aria-label="Category"><option value="">All categories</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $cat === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-dark btn-sm">Search</button>
</form>

<?php if ($events): ?>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Event</th><th>Organizer</th><th>Date</th><th class="num">Sold</th><th class="num">Gross</th><th>Status</th><th class="num">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td><div class="cell-main"><img src="<?= e(image_thumb($ev['cover_image'], 160)) ?>" alt="" loading="lazy"><div>
                    <strong><a href="<?= e(url('event.php?slug=' . $ev['slug'])) ?>"><?= e($ev['title']) ?></a> <?= $ev['is_featured'] ? '<span class="badge badge-info">Featured</span>' : '' ?></strong>
                    <span><?= e($ev['category_name']) ?> · <?= e($ev['city']) ?><?= $ev['open_reports'] ? ' · <span style="color:var(--danger);font-weight:700">' . (int) $ev['open_reports'] . ' report(s)</span>' : '' ?></span></div></div></td>
                <td class="small"><?= e($ev['organization_name']) ?></td>
                <td class="nowrap small"><?= e(fmt_date($ev['starts_at'], 'M j, Y')) ?><br><span class="muted"><?= e(fmt_time($ev['starts_at'])) ?></span></td>
                <td class="num"><?= number_format((int) $ev['sold']) ?>/<?= number_format((int) $ev['capacity']) ?></td>
                <td class="num"><?= e(money_exact($ev['gross'])) ?></td>
                <td><?= status_badge($ev['status']) ?><?php if ($ev['rejection_reason'] && $ev['status'] === 'rejected'): ?><br><span class="small muted"><?= e(str_limit($ev['rejection_reason'], 40)) ?></span><?php endif; ?></td>
                <td>
                    <div class="actions">
                        <?php if (in_array($ev['status'], ['pending', 'rejected'], true)): ?>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><button class="btn btn-success btn-xs" name="action" value="approve"><?= icon('check') ?> Approve</button></form>
                        <?php endif; ?>
                        <?php if (in_array($ev['status'], ['pending', 'published'], true) && strtotime($ev['ends_at']) > time()): ?>
                            <?php if ((int) $ev['sold'] === 0): ?>
                                <button type="button" class="btn btn-danger-soft btn-xs" data-modal-open="rejectEventModal" data-event="<?= (int) $ev['id'] ?>" data-name="<?= e($ev['title']) ?>">Reject</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($ev['status'] === 'published' && strtotime($ev['ends_at']) > time()): ?>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><button class="btn btn-soft btn-xs" name="action" value="feature" title="Toggle homepage feature"><?= icon('star') ?></button></form>
                            <?php if ((int) $ev['sold'] > 0): ?>
                                <form method="post" class="inline-form" data-confirm="Cancel “<?= e($ev['title']) ?>”? All bookings will be refunded and attendees notified." data-confirm-title="Cancel event?" data-confirm-button="Cancel event"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>"><button class="btn btn-danger-soft btn-xs" name="action" value="cancel">Cancel</button></form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($pg) ?>
<?php else: ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('calendar') ?></div><h3>No events found</h3><p><?= $status === 'pending' ? 'The moderation queue is empty.' : 'Try another filter.' ?></p></div>
<?php endif; ?>

<div class="modal" id="rejectEventModal" hidden>
    <div class="modal-backdrop"></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rejEvTitle">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="">
            <input type="hidden" name="action" value="reject">
            <div class="modal-head"><h2 id="rejEvTitle">Reject event</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('close') ?></button></div>
            <div class="modal-body form-grid">
                <p class="muted mb-0" data-reject-name></p>
                <div class="field"><label for="rejEvReason">What should the organizer change?</label><textarea class="textarea" id="rejEvReason" name="reason" maxlength="255" required placeholder="e.g. Please add a clearer description and the exact venue address."></textarea></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-soft" data-modal-close>Cancel</button><button class="btn btn-danger">Reject event</button></div>
        </form>
    </div>
</div>
<?php
$pageScripts = ['admin.js'];
require __DIR__ . '/../includes/footer.php';

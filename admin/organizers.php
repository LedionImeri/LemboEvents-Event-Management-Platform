<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role('admin');

if (is_post()) {
    csrf_verify();
    $org = db_one('SELECT o.*, u.id AS uid FROM organizer_profiles o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [(int) ($_POST['org_id'] ?? 0)]);
    $action = $_POST['action'] ?? '';
    if (!$org) {
        flash('error', 'Organizer not found.');
    } elseif ($action === 'approve') {
        db_exec("UPDATE organizer_profiles SET status = 'approved', approved_at = NOW(), approved_by = ?, rejection_reason = NULL WHERE id = ?", [$admin['id'], $org['id']]);
        notify((int) $org['uid'], 'organizer_approved', 'Organizer account approved 🎉', 'Welcome aboard! You can now publish events and sell tickets on LemboEvents.', 'organizer/dashboard.php');
        log_activity('organizer.approved', 'Approved organizer ' . $org['organization_name'], 'organizer', (int) $org['id']);
        flash('success', $org['organization_name'] . ' is now an approved organizer.');
    } elseif ($action === 'reject') {
        $reason = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255) ?: 'Your application did not include enough information about your organization.';
        db_exec("UPDATE organizer_profiles SET status = 'rejected', rejection_reason = ? WHERE id = ?", [$reason, $org['id']]);
        db_exec("UPDATE events SET status = 'draft' WHERE organizer_id = ? AND status IN ('pending','published') AND id NOT IN (SELECT event_id FROM bookings)", [$org['id']]);
        notify((int) $org['uid'], 'organizer_rejected', 'Organizer application not approved', $reason, 'organizer/profile.php');
        log_activity('organizer.rejected', 'Rejected organizer ' . $org['organization_name'], 'organizer', (int) $org['id']);
        flash('success', 'Application rejected and the organizer was notified.');
    }
    redirect('admin/organizers.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'q']))));
}

$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 'o.status = ?'; $params[] = $status; }
if ($q !== '') { $where[] = '(o.organization_name LIKE ? OR u.email LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like); }
$orgs = db_all(
    "SELECT o.*, u.first_name, u.last_name, u.email, u.status AS user_status,
            (SELECT COUNT(*) FROM events WHERE organizer_id = o.id) AS events,
            (SELECT COALESCE(SUM(b.subtotal), 0) FROM bookings b JOIN events e ON e.id = b.event_id WHERE e.organizer_id = o.id AND b.status = 'confirmed') AS revenue,
            (SELECT COALESCE(SUM(tt.quantity_sold), 0) FROM ticket_types tt JOIN events e ON e.id = tt.event_id WHERE e.organizer_id = o.id) AS sold
       FROM organizer_profiles o JOIN users u ON u.id = o.user_id
      WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(o.status, 'pending', 'approved', 'rejected'), o.created_at DESC", $params);
$counts = db_one("SELECT SUM(status='pending') AS pending, SUM(status='approved') AS approved, SUM(status='rejected') AS rejected FROM organizer_profiles");

$pageTitle = 'Organizers';
$layout = 'dashboard';
$dashActive = 'organizers';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Organizers</h1><p>Approve new organizers and keep an eye on existing ones.</p></div></div>
<nav class="tabs" aria-label="Organizer status">
    <a href="?" class="<?= $status === '' ? 'active' : '' ?>">All</a>
    <a href="?status=pending" class="<?= $status === 'pending' ? 'active' : '' ?>">Pending <span class="tab-count"><?= (int) $counts['pending'] ?></span></a>
    <a href="?status=approved" class="<?= $status === 'approved' ? 'active' : '' ?>">Approved <span class="tab-count"><?= (int) $counts['approved'] ?></span></a>
    <a href="?status=rejected" class="<?= $status === 'rejected' ? 'active' : '' ?>">Rejected <span class="tab-count"><?= (int) $counts['rejected'] ?></span></a>
</nav>
<form class="toolbar" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Organization or email" aria-label="Search organizers"></div>
    <button class="btn btn-dark btn-sm">Search</button>
</form>

<?php if ($orgs): ?>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Organization</th><th>Owner</th><th class="num">Events</th><th class="num">Tickets sold</th><th class="num">Revenue</th><th>Status</th><th class="num">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orgs as $o): ?>
            <tr>
                <td><div class="cell-main"><span class="org-logo" style="width:42px;height:42px;font-size:.85rem;border-radius:12px"><?php if ($o['logo']): ?><img src="<?= e(image_url($o['logo'])) ?>" alt=""><?php else: ?><?= e(initials($o['organization_name'])) ?><?php endif; ?></span>
                    <div><strong><?= e($o['organization_name']) ?></strong><span><?= e((string) $o['city']) ?> · applied <?= e(fmt_date($o['created_at'])) ?></span></div></div>
                    <?php if ($o['description']): ?><div class="small muted mt-1" style="max-width:320px"><?= e(str_limit($o['description'], 120)) ?></div><?php endif; ?>
                </td>
                <td class="small"><?= e($o['first_name'] . ' ' . $o['last_name']) ?><br><span class="muted"><?= e($o['email']) ?></span></td>
                <td class="num"><?= (int) $o['events'] ?></td>
                <td class="num"><?= number_format((int) $o['sold']) ?></td>
                <td class="num"><?= e(money_exact($o['revenue'])) ?></td>
                <td><?= status_badge($o['status']) ?><?php if ($o['status'] === 'rejected' && $o['rejection_reason']): ?><br><span class="small muted"><?= e(str_limit($o['rejection_reason'], 50)) ?></span><?php endif; ?></td>
                <td>
                    <div class="actions">
                        <?php if ($o['status'] === 'approved'): ?><a class="btn btn-ghost btn-xs" href="<?= e(url('organizer.php?slug=' . $o['slug'])) ?>" title="Public profile"><?= icon('eye') ?></a><?php endif; ?>
                        <?php if ($o['status'] !== 'approved'): ?>
                            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="org_id" value="<?= (int) $o['id'] ?>"><button class="btn btn-success btn-xs" name="action" value="approve"><?= icon('check') ?> Approve</button></form>
                        <?php endif; ?>
                        <?php if ($o['status'] !== 'rejected'): ?>
                            <button type="button" class="btn btn-danger-soft btn-xs" data-modal-open="rejectOrgModal" data-org="<?= (int) $o['id'] ?>" data-name="<?= e($o['organization_name']) ?>">Reject</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('building') ?></div><h3>No organizers here</h3><p><?= $status === 'pending' ? 'No applications are waiting for review.' : 'Try another filter.' ?></p></div>
<?php endif; ?>

<div class="modal" id="rejectOrgModal" hidden>
    <div class="modal-backdrop"></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="rejTitle">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="org_id" value="">
            <input type="hidden" name="action" value="reject">
            <div class="modal-head"><h2 id="rejTitle">Reject application</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('close') ?></button></div>
            <div class="modal-body form-grid">
                <p class="muted mb-0" data-reject-name></p>
                <div class="field"><label for="rejReason">Reason (sent to the organizer)</label><textarea class="textarea" id="rejReason" name="reason" maxlength="255" required placeholder="e.g. Please add a description and website so we can verify your organization."></textarea></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-soft" data-modal-close>Cancel</button><button class="btn btn-danger">Reject</button></div>
        </form>
    </div>
</div>
<?php
$pageScripts = ['admin.js'];
require __DIR__ . '/../includes/footer.php';

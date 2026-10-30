<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role('admin');

if (is_post()) {
    csrf_verify();
    $rep = db_one('SELECT * FROM reports WHERE id = ?', [(int) ($_POST['report_id'] ?? 0)]);
    $action = $_POST['action'] ?? '';
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
    if (!$rep || $rep['status'] !== 'open' || !in_array($action, ['resolve', 'dismiss'], true)) {
        flash('error', 'Report not found or already handled.');
    } else {
        db_exec('UPDATE reports SET status = ?, admin_note = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?',
            [$action === 'resolve' ? 'resolved' : 'dismissed', $note ?: null, $admin['id'], $rep['id']]);
        if ($action === 'resolve' && !empty($_POST['hide']) && $rep['target_type'] === 'review') {
            db_exec("UPDATE reviews SET status = 'hidden' WHERE id = ?", [$rep['target_id']]);
        }
        if ($rep['reporter_id']) {
            notify((int) $rep['reporter_id'], 'report', 'Your report was reviewed', $action === 'resolve' ? 'Thanks! We took action on the content you reported.' : 'We reviewed your report and found no violation.', null);
        }
        log_activity('report.' . $action . 'd', ucfirst($action) . 'd report #' . $rep['id'] . ' (' . $rep['target_type'] . ')', 'report', (int) $rep['id']);
        flash('success', 'Report ' . ($action === 'resolve' ? 'resolved' : 'dismissed') . '.');
    }
    redirect('admin/reports.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status']))));
}

$status = in_array($_GET['status'] ?? 'open', ['open', 'resolved', 'dismissed', 'all'], true) ? ($_GET['status'] ?? 'open') : 'open';
$reports = db_all(
    "SELECT p.*, u.first_name, u.last_name, u.email,
            CASE p.target_type
                WHEN 'event' THEN (SELECT title FROM events WHERE id = p.target_id)
                WHEN 'review' THEN (SELECT CONCAT(rating, '★ — ', LEFT(comment, 140)) FROM reviews WHERE id = p.target_id)
                WHEN 'organizer' THEN (SELECT organization_name FROM organizer_profiles WHERE id = p.target_id)
            END AS target_label,
            CASE p.target_type
                WHEN 'event' THEN (SELECT slug FROM events WHERE id = p.target_id)
                WHEN 'review' THEN (SELECT e.slug FROM reviews r JOIN events e ON e.id = r.event_id WHERE r.id = p.target_id)
                WHEN 'organizer' THEN (SELECT slug FROM organizer_profiles WHERE id = p.target_id)
            END AS target_slug
       FROM reports p LEFT JOIN users u ON u.id = p.reporter_id
      " . ($status === 'all' ? '' : 'WHERE p.status = ?') . " ORDER BY p.created_at DESC LIMIT 100", $status === 'all' ? [] : [$status]);
$counts = db_one("SELECT SUM(status='open') AS open, SUM(status='resolved') AS resolved, SUM(status='dismissed') AS dismissed FROM reports");

$pageTitle = 'Reports';
$layout = 'dashboard';
$dashActive = 'reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Reports</h1><p>Content flagged by the community and organizers.</p></div></div>
<nav class="tabs">
    <a href="?status=open" class="<?= $status === 'open' ? 'active' : '' ?>">Open <span class="tab-count"><?= (int) $counts['open'] ?></span></a>
    <a href="?status=resolved" class="<?= $status === 'resolved' ? 'active' : '' ?>">Resolved <span class="tab-count"><?= (int) $counts['resolved'] ?></span></a>
    <a href="?status=dismissed" class="<?= $status === 'dismissed' ? 'active' : '' ?>">Dismissed <span class="tab-count"><?= (int) $counts['dismissed'] ?></span></a>
    <a href="?status=all" class="<?= $status === 'all' ? 'active' : '' ?>">All</a>
</nav>
<?php if ($reports): ?>
    <div class="form-grid">
        <?php foreach ($reports as $p):
            $link = $p['target_type'] === 'organizer' ? 'organizer.php?slug=' . $p['target_slug'] : 'event.php?slug=' . $p['target_slug'] . ($p['target_type'] === 'review' ? '#reviews' : ''); ?>
            <article class="card">
                <div class="card-head">
                    <h2 style="font-size:1rem"><span class="badge badge-neutral"><?= e(ucfirst($p['target_type'])) ?></span> <span class="badge badge-warning"><?= e(ucfirst($p['reason'])) ?></span></h2>
                    <div class="flex gap-1 items-center"><?= status_badge($p['status']) ?><span class="small muted"><?= e(time_ago($p['created_at'])) ?></span></div>
                </div>
                <p class="mb-2"><strong>Reported:</strong> <?php if ($p['target_slug']): ?><a class="link" href="<?= e(url($link)) ?>"><?= e((string) $p['target_label']) ?></a><?php else: ?><span class="muted">Content no longer exists</span><?php endif; ?></p>
                <?php if ($p['details']): ?><blockquote class="org-reply mb-2" style="margin:0 0 12px"><?= nl2br(e($p['details'])) ?></blockquote><?php endif; ?>
                <p class="small muted mb-0">By <?= $p['first_name'] ? e($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['email'] . ')') : 'deleted user' ?><?= $p['admin_note'] ? ' · Note: ' . e($p['admin_note']) : '' ?></p>
                <?php if ($p['status'] === 'open'): ?>
                    <div class="flex gap-1 wrap mt-2">
                        <button type="button" class="btn btn-success btn-xs" data-modal-open="resolveReportModal" data-report="<?= (int) $p['id'] ?>" data-action="resolve" data-can-hide="<?= $p['target_type'] === 'review' ? '1' : '0' ?>"><?= icon('check') ?> Resolve</button>
                        <button type="button" class="btn btn-soft btn-xs" data-modal-open="resolveReportModal" data-report="<?= (int) $p['id'] ?>" data-action="dismiss">Dismiss</button>
                        <?php if ($p['target_type'] === 'event'): ?><a class="btn btn-ghost btn-xs" href="<?= e(url('admin/events.php?q=' . rawurlencode((string) $p['target_label']))) ?>">Manage event</a><?php endif; ?>
                        <?php if ($p['target_type'] === 'review'): ?><a class="btn btn-ghost btn-xs" href="<?= e(url('admin/reviews.php?status=reported')) ?>">Manage reviews</a><?php endif; ?>
                        <?php if ($p['target_type'] === 'organizer'): ?><a class="btn btn-ghost btn-xs" href="<?= e(url('admin/organizers.php?q=' . rawurlencode((string) $p['target_label']))) ?>">Manage organizer</a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('flag') ?></div><h3>No <?= $status === 'all' ? '' : e($status) ?> reports</h3><p>Nothing needs your attention right now.</p></div>
<?php endif; ?>

<div class="modal" id="resolveReportModal" hidden>
    <div class="modal-backdrop"></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="repTitle">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="report_id" value="">
            <input type="hidden" name="action" value="resolve">
            <div class="modal-head"><h2 id="repTitle">Resolve report</h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('close') ?></button></div>
            <div class="modal-body form-grid">
                <div class="field"><label for="repNote">Internal note</label><input class="input" id="repNote" name="note" maxlength="255" placeholder="What did you do?"></div>
                <label class="check" data-hide-wrap><input type="checkbox" name="hide" value="1" checked> Hide the reported review</label>
            </div>
            <div class="modal-foot"><button type="button" class="btn btn-soft" data-modal-close>Cancel</button><button class="btn btn-primary">Confirm</button></div>
        </form>
    </div>
</div>
<?php
$pageScripts = ['admin.js'];
require __DIR__ . '/../includes/footer.php';

<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_role('admin');

if (is_post()) {
    csrf_verify();
    $r = db_one('SELECT r.*, e.title FROM reviews r JOIN events e ON e.id = r.event_id WHERE r.id = ?', [(int) ($_POST['review_id'] ?? 0)]);
    $action = $_POST['action'] ?? '';
    if (!$r) {
        flash('error', 'Review not found.');
    } elseif ($action === 'hide' || $action === 'show') {
        db_exec('UPDATE reviews SET status = ? WHERE id = ?', [$action === 'hide' ? 'hidden' : 'visible', $r['id']]);
        log_activity('review.' . ($action === 'hide' ? 'hidden' : 'restored'), ($action === 'hide' ? 'Hid' : 'Restored') . ' a review on ' . $r['title'], 'review', (int) $r['id']);
        flash('success', $action === 'hide' ? 'Review hidden from the public site.' : 'Review is visible again.');
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM reviews WHERE id = ?', [$r['id']]);
        db_exec("UPDATE reports SET status = 'resolved', admin_note = 'Review deleted', resolved_by = ?, resolved_at = NOW() WHERE target_type = 'review' AND target_id = ? AND status = 'open'", [current_user_id(), $r['id']]);
        log_activity('review.deleted', 'Deleted a review on ' . $r['title'], 'review', (int) $r['id']);
        flash('success', 'Review deleted.');
    }
    redirect('admin/reviews.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'rating', 'q', 'page']))));
}

$status = in_array($_GET['status'] ?? '', ['visible', 'hidden', 'reported'], true) ? $_GET['status'] : '';
$rating = (int) ($_GET['rating'] ?? 0);
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$where = ['1=1'];
$params = [];
if ($status === 'reported') { $where[] = "EXISTS (SELECT 1 FROM reports p WHERE p.target_type = 'review' AND p.target_id = r.id AND p.status = 'open')"; }
elseif ($status) { $where[] = 'r.status = ?'; $params[] = $status; }
if ($rating >= 1 && $rating <= 5) { $where[] = 'r.rating = ?'; $params[] = $rating; }
if ($q !== '') { $where[] = '(r.comment LIKE ? OR r.title LIKE ? OR e.title LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($params, $like, $like, $like); }
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM reviews r JOIN events e ON e.id = r.event_id WHERE $w", $params);
$pg = paginate($total, 15);
$reviews = db_all("SELECT r.*, e.title AS event_title, e.slug, u.first_name, u.last_name, u.email,
                          (SELECT COUNT(*) FROM reports p WHERE p.target_type = 'review' AND p.target_id = r.id AND p.status = 'open') AS reports
                     FROM reviews r JOIN events e ON e.id = r.event_id JOIN users u ON u.id = r.user_id
                    WHERE $w ORDER BY reports DESC, r.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$pageTitle = 'Reviews';
$layout = 'dashboard';
$dashActive = 'reviews';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Reviews</h1><p>Moderate attendee reviews. Hidden reviews stay in the database but disappear from the site.</p></div></div>
<nav class="tabs">
    <?php foreach (['' => 'All', 'reported' => 'Reported', 'visible' => 'Visible', 'hidden' => 'Hidden'] as $k => $l): ?>
        <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $l ?></a>
    <?php endforeach; ?>
</nav>
<form class="toolbar" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="input-icon"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search review text or event" aria-label="Search reviews"></div>
    <select class="select" name="rating" data-autosubmit aria-label="Rating"><option value="">All ratings</option><?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"<?= $rating === $s ? ' selected' : '' ?>><?= $s ?> stars</option><?php endfor; ?></select>
    <button class="btn btn-dark btn-sm">Search</button>
</form>
<?php if ($reviews): ?>
    <div class="review-list">
        <?php foreach ($reviews as $r): ?>
            <article class="review-item"<?= $r['status'] === 'hidden' ? ' style="opacity:.7"' : '' ?>>
                <header>
                    <span class="avatar-initials"><?= e(initials($r['first_name'] . ' ' . $r['last_name'])) ?></span>
                    <div><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?> <span class="muted small" style="font-weight:500"><?= e($r['email']) ?></span></strong>
                        <time><?= e(fmt_date($r['created_at'])) ?> · <a class="link" href="<?= e(url('event.php?slug=' . $r['slug'] . '#reviews')) ?>"><?= e($r['event_title']) ?></a></time></div>
                    <?= stars_html((float) $r['rating']) ?>
                </header>
                <div class="flex gap-1 wrap"><?= status_badge($r['status']) ?><?php if ($r['reports']): ?><span class="badge badge-danger"><?= (int) $r['reports'] ?> open report(s)</span><?php endif; ?></div>
                <?php if ($r['title']): ?><h4><?= e($r['title']) ?></h4><?php endif; ?>
                <p><?= nl2br(e($r['comment'])) ?></p>
                <div class="flex gap-1 wrap">
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                        <?php if ($r['status'] === 'visible'): ?><button class="btn btn-soft btn-xs" name="action" value="hide"><?= icon('eye-off') ?> Hide</button>
                        <?php else: ?><button class="btn btn-soft btn-xs" name="action" value="show"><?= icon('eye') ?> Restore</button><?php endif; ?>
                    </form>
                    <form method="post" class="inline-form" data-confirm="Delete this review permanently?" data-confirm-button="Delete"><?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>"><button class="btn btn-danger-soft btn-xs" name="action" value="delete"><?= icon('trash') ?> Delete</button></form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?= pagination_links($pg) ?>
<?php else: ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('star') ?></div><h3>No reviews found</h3><p>Try another filter.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

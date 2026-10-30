<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/organizer.php';

$org = require_organizer();

if (is_post()) {
    csrf_verify();
    $review = db_one('SELECT r.*, e.title FROM reviews r JOIN events e ON e.id = r.event_id WHERE r.id = ? AND e.organizer_id = ?', [(int) ($_POST['review_id'] ?? 0), $org['id']]);
    if (!$review) {
        flash('error', 'Review not found.');
    } elseif (($_POST['action'] ?? '') === 'reply') {
        $reply = trim((string) ($_POST['reply'] ?? ''));
        if (mb_strlen($reply) < 2 || mb_strlen($reply) > 1000) {
            flash('error', 'Replies must be 2–1000 characters.');
        } else {
            db_exec('UPDATE reviews SET organizer_reply = ?, replied_at = NOW() WHERE id = ?', [$reply, $review['id']]);
            notify((int) $review['user_id'], 'review_reply', $org['organization_name'] . ' replied to your review', 'They responded to your review of ' . $review['title'] . '.', 'event.php?slug=' . db_value('SELECT slug FROM events WHERE id = ?', [$review['event_id']]) . '#reviews');
            flash('success', 'Reply published.');
        }
    } elseif (($_POST['action'] ?? '') === 'delete_reply') {
        db_exec('UPDATE reviews SET organizer_reply = NULL, replied_at = NULL WHERE id = ?', [$review['id']]);
        flash('success', 'Reply removed.');
    } elseif (($_POST['action'] ?? '') === 'report') {
        if (!db_value("SELECT 1 FROM reports WHERE target_type = 'review' AND target_id = ? AND status = 'open'", [$review['id']])) {
            db_exec("INSERT INTO reports (reporter_id, target_type, target_id, reason, details) VALUES (?, 'review', ?, 'inappropriate', ?)",
                [current_user_id(), $review['id'], 'Reported by organizer ' . $org['organization_name']]);
            notify_admins('report', 'Review reported by organizer', $org['organization_name'] . ' reported a review on ' . $review['title'] . '.', 'admin/reports.php');
        }
        flash('success', 'Thanks — our moderators will review it.');
    }
    redirect('organizer/reviews.php?' . http_build_query(array_intersect_key($_GET, array_flip(['event', 'rating']))));
}

$eventId = (int) ($_GET['event'] ?? 0);
$rating = (int) ($_GET['rating'] ?? 0);
$where = ['e.organizer_id = ?', "r.status = 'visible'"];
$params = [$org['id']];
if ($eventId) { $where[] = 'e.id = ?'; $params[] = $eventId; }
if ($rating >= 1 && $rating <= 5) { $where[] = 'r.rating = ?'; $params[] = $rating; }
$whereSql = implode(' AND ', $where);
$summary = db_one("SELECT COUNT(*) AS cnt, ROUND(AVG(r.rating), 2) AS avg, SUM(r.organizer_reply IS NULL) AS unanswered FROM reviews r JOIN events e ON e.id = r.event_id WHERE $whereSql", $params);
$total = (int) $summary['cnt'];
$pg = paginate($total, 15);
$reviews = db_all("SELECT r.*, e.title AS event_title, e.slug, u.first_name, u.last_name FROM reviews r JOIN events e ON e.id = r.event_id JOIN users u ON u.id = r.user_id
                    WHERE $whereSql ORDER BY (r.organizer_reply IS NULL) DESC, r.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$eventsWithReviews = db_all("SELECT DISTINCT e.id, e.title FROM events e JOIN reviews r ON r.event_id = e.id WHERE e.organizer_id = ? ORDER BY e.starts_at DESC", [$org['id']]);

$pageTitle = 'Reviews';
$layout = 'dashboard';
$dashActive = 'reviews';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head"><div><h1>Reviews</h1><p>What attendees say about your events. Replies are public.</p></div></div>

<div class="stats">
    <div class="stat stat-dark"><span class="stat-icon"><?= icon('star') ?></span><small>Average rating</small><strong><?= $summary['avg'] ? e(number_format((float) $summary['avg'], 1)) : '—' ?></strong><span class="stat-foot"><?= $summary['avg'] ? stars_html((float) $summary['avg']) : '' ?></span></div>
    <div class="stat"><span class="stat-icon"><?= icon('message') ?></span><small>Reviews</small><strong><?= number_format($total) ?></strong></div>
    <div class="stat"><span class="stat-icon"><?= icon('send') ?></span><small>Awaiting reply</small><strong><?= (int) $summary['unanswered'] ?></strong></div>
</div>

<form class="toolbar" method="get">
    <select class="select" name="event" data-autosubmit aria-label="Event">
        <option value="">All events</option>
        <?php foreach ($eventsWithReviews as $ev): ?><option value="<?= (int) $ev['id'] ?>"<?= $eventId === (int) $ev['id'] ? ' selected' : '' ?>><?= e($ev['title']) ?></option><?php endforeach; ?>
    </select>
    <select class="select" name="rating" data-autosubmit aria-label="Rating">
        <option value="">All ratings</option>
        <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"<?= $rating === $s ? ' selected' : '' ?>><?= $s ?> stars</option><?php endfor; ?>
    </select>
</form>

<?php if ($reviews): ?>
    <div class="review-list">
        <?php foreach ($reviews as $r): ?>
            <article class="review-item">
                <header>
                    <span class="avatar-initials"><?= e(initials($r['first_name'] . ' ' . $r['last_name'])) ?></span>
                    <div><strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong><time><?= e(fmt_date($r['created_at'])) ?> · <a class="link" href="<?= e(url('event.php?slug=' . $r['slug'] . '#reviews')) ?>"><?= e($r['event_title']) ?></a></time></div>
                    <?= stars_html((float) $r['rating']) ?>
                </header>
                <?php if ($r['title']): ?><h4><?= e($r['title']) ?></h4><?php endif; ?>
                <p><?= nl2br(e($r['comment'])) ?></p>
                <?php if ($r['organizer_reply']): ?>
                    <div class="org-reply"><strong>Your reply · <?= e(time_ago($r['replied_at'])) ?></strong><?= nl2br(e($r['organizer_reply'])) ?></div>
                    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>"><button class="link-btn" name="action" value="delete_reply">Remove reply</button></form>
                <?php else: ?>
                    <form method="post" class="form-grid" style="gap:8px" data-loading>
                        <?= csrf_field() ?>
                        <input type="hidden" name="review_id" value="<?= (int) $r['id'] ?>">
                        <label class="sr-only" for="reply<?= (int) $r['id'] ?>">Reply</label>
                        <textarea class="textarea" id="reply<?= (int) $r['id'] ?>" name="reply" rows="2" style="min-height:70px" maxlength="1000" placeholder="Write a public reply…"></textarea>
                        <div class="flex gap-1 wrap">
                            <button class="btn btn-dark btn-sm" name="action" value="reply"><?= icon('send') ?> Reply</button>
                            <button class="btn btn-ghost btn-sm" name="action" value="report" formnovalidate><?= icon('flag') ?> Report to moderators</button>
                        </div>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?= pagination_links($pg) ?>
<?php else: ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('star') ?></div><h3>No reviews yet</h3><p>Attendees can review your events after they end.</p></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

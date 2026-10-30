<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role('attendee');
$favorites = db_all(
    'SELECT ' . event_card_select() . ', f.created_at AS saved_at ' . event_card_from() . '
       JOIN favorites f ON f.event_id = e.id
      WHERE f.user_id = ? ORDER BY (e.ends_at < NOW()), e.starts_at ASC', [$user['id']]);

$pageTitle = 'Favorites';
$layout = 'dashboard';
$dashActive = 'favorites';
require __DIR__ . '/../includes/header.php';
?>
<div class="dash-head">
    <div>
        <h1>Favorites</h1>
        <p>Events you saved. We'll remind you before tickets run out.</p>
    </div>
    <div class="actions"><a class="btn btn-dark btn-sm" href="<?= e(url('events.php')) ?>"><?= icon('search') ?> Discover more</a></div>
</div>
<?php if ($favorites): ?>
    <div class="event-grid" data-remove-on-unfavorite>
        <?php foreach ($favorites as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"><?= icon('heart') ?></div>
        <h3>No favorites yet</h3>
        <p>Tap the heart on any event to save it here for later.</p>
        <a class="btn btn-primary" href="<?= e(url('events.php')) ?>">Explore events</a>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

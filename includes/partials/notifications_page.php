<?php
/** Full notifications page body, shared by all roles. Expects $user. */
$uid = (int) $user['id'];
$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$total = (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ?' . ($filter === 'unread' ? ' AND is_read = 0' : ''), [$uid]);
$pg = paginate($total, 20);
$items = db_all('SELECT * FROM notifications WHERE user_id = ?' . ($filter === 'unread' ? ' AND is_read = 0' : '') . " ORDER BY created_at DESC, id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", [$uid]);
$iconFor = fn(string $type) => match (true) {
    str_contains($type, 'booking') => 'ticket',
    str_contains($type, 'cancel') => 'x-circle',
    str_contains($type, 'review') => 'star',
    str_contains($type, 'reminder') => 'clock',
    str_contains($type, 'approved') => 'check-circle',
    str_contains($type, 'rejected') => 'alert',
    str_contains($type, 'report') => 'flag',
    str_contains($type, 'event') => 'calendar',
    default => 'bell',
};
?>
<div class="dash-head">
    <div>
        <h1>Notifications</h1>
        <p>Booking confirmations, event updates, reminders and more.</p>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-soft btn-sm" data-notif-read-all><?= icon('check') ?> Mark all as read</button>
    </div>
</div>
<nav class="tabs" aria-label="Filter notifications">
    <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All</a>
    <a href="?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">Unread <span class="tab-count"><?= unread_notifications($uid) ?></span></a>
</nav>
<?php if ($items): ?>
    <div>
        <?php foreach ($items as $n): ?>
            <div class="notif-page-item<?= $n['is_read'] ? '' : ' unread' ?>" data-notif-row="<?= (int) $n['id'] ?>">
                <span class="notif-dot"><?= icon($iconFor($n['type'])) ?></span>
                <div class="np-main">
                    <strong><?= e($n['title']) ?></strong>
                    <p><?= e($n['message']) ?></p>
                    <div class="flex gap-2 mt-1 wrap items-center">
                        <time datetime="<?= e($n['created_at']) ?>"><?= e(time_ago($n['created_at'])) ?></time>
                        <?php if ($n['link']): ?><a class="link small" href="<?= e(url(safe_return_path($n['link'], ''))) ?>" data-notif-open="<?= (int) $n['id'] ?>">Open</a><?php endif; ?>
                        <?php if (!$n['is_read']): ?><button type="button" class="link-btn" data-notif-mark="<?= (int) $n['id'] ?>">Mark as read</button><?php endif; ?>
                    </div>
                </div>
                <button type="button" class="icon-btn" data-notif-delete="<?= (int) $n['id'] ?>" aria-label="Delete notification"><?= icon('trash') ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <?= pagination_links($pg) ?>
<?php else: ?>
    <div class="empty-state">
        <div class="empty-icon"><?= icon('bell') ?></div>
        <h3><?= $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' ?></h3>
        <p>We'll let you know about bookings, reminders and event updates here.</p>
    </div>
<?php endif; ?>
<?php $pageScripts = array_merge($pageScripts ?? [], ['notifications.js']); ?>

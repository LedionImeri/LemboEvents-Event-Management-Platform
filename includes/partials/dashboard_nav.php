<?php
/** Dashboard shell: sidebar (desktop) / scrollable tab bar (mobile). Opened here, closed in footer.php. */
$dashActive ??= '';
$role = $user['role'] ?? 'attendee';
$navItems = match ($role) {
    'admin' => [
        ['dashboard', 'admin/dashboard.php', 'grid', 'Overview'],
        ['users', 'admin/users.php', 'users', 'Users'],
        ['organizers', 'admin/organizers.php', 'building', 'Organizers'],
        ['events', 'admin/events.php', 'calendar', 'Events'],
        ['categories', 'admin/categories.php', 'layers', 'Categories'],
        ['tickets', 'admin/tickets.php', 'ticket', 'Tickets'],
        ['bookings', 'admin/bookings.php', 'card', 'Bookings'],
        ['reviews', 'admin/reviews.php', 'star', 'Reviews'],
        ['reports', 'admin/reports.php', 'flag', 'Reports'],
        ['activity', 'admin/activity.php', 'activity', 'Activity log'],
        ['notifications', 'admin/notifications.php', 'bell', 'Notifications'],
        ['profile', 'admin/profile.php', 'settings', 'Settings'],
    ],
    'organizer' => [
        ['dashboard', 'organizer/dashboard.php', 'grid', 'Overview'],
        ['events', 'organizer/events.php', 'calendar', 'Events'],
        ['tickets', 'organizer/tickets.php', 'ticket', 'Ticket types'],
        ['attendees', 'organizer/attendees.php', 'users', 'Attendees'],
        ['check-in', 'organizer/check-in.php', 'scan', 'Check-in'],
        ['analytics', 'organizer/analytics.php', 'chart', 'Analytics'],
        ['reviews', 'organizer/reviews.php', 'star', 'Reviews'],
        ['notifications', 'organizer/notifications.php', 'bell', 'Notifications'],
        ['profile', 'organizer/profile.php', 'settings', 'Organization'],
    ],
    default => [
        ['dashboard', 'attendee/dashboard.php', 'grid', 'Overview'],
        ['tickets', 'attendee/tickets.php', 'ticket', 'My tickets'],
        ['bookings', 'attendee/bookings.php', 'calendar', 'My events'],
        ['favorites', 'attendee/favorites.php', 'heart', 'Favorites'],
        ['notifications', 'attendee/notifications.php', 'bell', 'Notifications'],
        ['profile', 'attendee/profile.php', 'settings', 'Profile'],
    ],
};
$sideTitle = match ($role) { 'admin' => 'Admin console', 'organizer' => 'Organizer studio', default => 'My LemboEvents' };
$org = $role === 'organizer' ? current_organizer() : null;
?>
<div class="dash">
    <aside class="dash-side" aria-label="Dashboard navigation">
        <div class="dash-side-head">
            <span class="dash-eyebrow"><?= e($sideTitle) ?></span>
            <strong><?= e($org['organization_name'] ?? $user['name']) ?></strong>
            <?php if ($org && $org['status'] !== 'approved'): ?>
                <?= status_badge($org['status']) ?>
            <?php endif; ?>
        </div>
        <nav class="dash-nav">
            <?php foreach ($navItems as [$key, $href, $ic, $label]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $dashActive === $key ? 'active' : '' ?>"<?= $dashActive === $key ? ' aria-current="page"' : '' ?>>
                    <?= icon($ic) ?><span><?= e($label) ?></span>
                    <?php if ($key === 'notifications' && !empty($unread)): ?><span class="count-pill"><?= (int) $unread ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php if ($role === 'organizer'): ?>
            <a class="btn btn-primary btn-block dash-cta" href="<?= e(url('organizer/create-event.php')) ?>"><?= icon('plus') ?> New event</a>
        <?php elseif ($role === 'attendee'): ?>
            <a class="btn btn-dark btn-block dash-cta" href="<?= e(url('events.php')) ?>"><?= icon('search') ?> Find events</a>
        <?php endif; ?>
    </aside>
    <main id="main" class="dash-main">
        <div class="dash-content">

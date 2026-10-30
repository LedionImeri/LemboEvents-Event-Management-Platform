<?php
/**
 * Page header. Optional variables set by the page before including:
 *   $pageTitle, $pageDescription, $bodyClass, $activeNav,
 *   $layout ('public' | 'dashboard'), $dashActive (sidebar key), $pageScripts (array of js files),
 *   $ogImage (absolute image URL)
 */
$user = current_user();
$pageTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . APP_NAME : APP_NAME . ' — ' . APP_TAGLINE;
$pageDescription ??= 'LemboEvents is where Kosovo discovers concerts, festivals, conferences, workshops and experiences — and gets tickets in seconds.';
$bodyClass ??= '';
$activeNav ??= '';
$layout ??= 'public';
$unread = $user ? unread_notifications((int) $user['id']) : 0;
if ($user && $user['role'] === 'attendee') {
    ensure_event_reminders((int) $user['id']);
    $unread = unread_notifications((int) $user['id']);
}
$flashes = take_flashes();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <meta name="theme-color" content="#12101A">
    <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <link rel="icon" href="<?= e(asset('images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=Manrope:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if ($layout === 'dashboard'): ?><link rel="stylesheet" href="<?= e(asset('css/dashboard.css')) ?>"><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/chatbot.css')) ?>">
</head>
<body class="<?= e(trim($bodyClass . ' layout-' . $layout)) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="siteHeader">
    <div class="container nav-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="LemboEvents home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32"><path d="M4 9a3 3 0 0 0 3-3h18a3 3 0 0 0 3 3v3.5a3.5 3.5 0 0 0 0 7V23a3 3 0 0 0-3 3H7a3 3 0 0 0-3-3v-3.5a3.5 3.5 0 0 0 0-7z" fill="currentColor"/><path d="M12 11v10h7" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="brand-name">Lembo<span>Events</span></span>
        </a>

        <nav class="nav-links" aria-label="Primary">
            <a href="<?= e(url('events.php')) ?>" class="<?= $activeNav === 'events' ? 'active' : '' ?>">Explore</a>
            <a href="<?= e(url('index.php#categories')) ?>">Categories</a>
            <a href="<?= e(url('index.php#how-it-works')) ?>">How it works</a>
            <?php if (!$user || $user['role'] === 'organizer'): ?>
                <a href="<?= e(url($user ? 'organizer/dashboard.php' : 'register.php?role=organizer')) ?>" class="<?= $activeNav === 'organizers' ? 'active' : '' ?>">For organizers</a>
            <?php endif; ?>
        </nav>

        <div class="nav-actions">
            <?php if ($user): ?>
                <div class="dropdown" data-dropdown>
                    <button class="icon-btn notif-btn" type="button" aria-label="Notifications" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle data-notif-toggle>
                        <?= icon('bell') ?>
                        <span class="notif-count<?= $unread ? '' : ' is-hidden' ?>" data-notif-count><?= $unread > 99 ? '99+' : (int) $unread ?></span>
                    </button>
                    <div class="dropdown-panel notif-panel" role="menu">
                        <div class="notif-panel-head">
                            <strong>Notifications</strong>
                            <button type="button" class="link-btn" data-notif-read-all>Mark all read</button>
                        </div>
                        <div class="notif-list" data-notif-list>
                            <div class="notif-loading"><span class="spinner"></span></div>
                        </div>
                        <a class="notif-all" href="<?= e(url($user['role'] . '/notifications.php')) ?>">View all notifications</a>
                    </div>
                </div>
                <div class="dropdown user-dropdown" data-dropdown>
                    <button class="avatar-btn" type="button" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle aria-label="Account menu">
                        <?php if ($user['avatar']): ?>
                            <img src="<?= e(image_url($user['avatar'])) ?>" alt="" width="36" height="36">
                        <?php else: ?>
                            <span class="avatar-initials"><?= e(initials($user['name'])) ?></span>
                        <?php endif; ?>
                        <span class="avatar-name"><?= e($user['first_name']) ?></span>
                        <?= icon('chevron-down', 'icon-sm') ?>
                    </button>
                    <div class="dropdown-panel user-panel" role="menu">
                        <div class="user-panel-head">
                            <strong><?= e($user['name']) ?></strong>
                            <span><?= e($user['email']) ?></span>
                            <span class="role-pill role-<?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span>
                        </div>
                        <a href="<?= e(url(dashboard_url())) ?>"><?= icon('grid') ?> Dashboard</a>
                        <?php if ($user['role'] === 'attendee'): ?>
                            <a href="<?= e(url('attendee/tickets.php')) ?>"><?= icon('ticket') ?> My tickets</a>
                            <a href="<?= e(url('attendee/favorites.php')) ?>"><?= icon('heart') ?> Favorites</a>
                        <?php elseif ($user['role'] === 'organizer'): ?>
                            <a href="<?= e(url('organizer/create-event.php')) ?>"><?= icon('plus') ?> Create event</a>
                            <a href="<?= e(url('organizer/check-in.php')) ?>"><?= icon('scan') ?> Check-in</a>
                        <?php else: ?>
                            <a href="<?= e(url('admin/events.php?status=pending')) ?>"><?= icon('shield') ?> Moderation</a>
                        <?php endif; ?>
                        <a href="<?= e(url($user['role'] . '/profile.php')) ?>"><?= icon('settings') ?> Settings</a>
                        <form method="post" action="<?= e(url('logout.php')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="danger"><?= icon('logout') ?> Log out</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm hide-sm" href="<?= e(url('login.php')) ?>">Log in</a>
                <a class="btn btn-primary btn-sm hide-sm" href="<?= e(url('register.php')) ?>">Sign up</a>
            <?php endif; ?>
            <button class="icon-btn menu-btn" type="button" aria-label="Open menu" aria-controls="mobileDrawer" aria-expanded="false" data-menu-open>
                <?= icon('menu') ?>
            </button>
        </div>
    </div>
</header>

<?php /* The drawer is a sibling of the header (not a child) on purpose: the header uses
   backdrop-filter, which creates a containing block for position:fixed descendants. Keeping
   the drawer outside guarantees it covers the viewport at every scroll position. */ ?>
<div class="drawer-backdrop" data-menu-close hidden></div>
<aside class="mobile-drawer" id="mobileDrawer" aria-label="Mobile menu" aria-hidden="true">
    <div class="drawer-head">
        <span class="brand-name">Lembo<span>Events</span></span>
        <button class="icon-btn" type="button" aria-label="Close menu" data-menu-close><?= icon('close') ?></button>
    </div>
    <form class="drawer-search" action="<?= e(url('events.php')) ?>" method="get" role="search">
        <?= icon('search') ?>
        <input type="search" name="q" placeholder="Search events" aria-label="Search events">
    </form>
    <nav class="drawer-links" aria-label="Mobile">
        <a href="<?= e(url('index.php')) ?>"><?= icon('home') ?> Home</a>
        <a href="<?= e(url('events.php')) ?>"><?= icon('search') ?> Explore events</a>
        <a href="<?= e(url('index.php#categories')) ?>" data-menu-close><?= icon('layers') ?> Categories</a>
        <a href="<?= e(url('index.php#how-it-works')) ?>" data-menu-close><?= icon('info') ?> How it works</a>
        <?php if ($user): ?>
            <span class="drawer-label">Your account</span>
            <a href="<?= e(url(dashboard_url())) ?>"><?= icon('grid') ?> Dashboard</a>
            <?php if ($user['role'] === 'attendee'): ?>
                <a href="<?= e(url('attendee/tickets.php')) ?>"><?= icon('ticket') ?> My tickets</a>
                <a href="<?= e(url('attendee/favorites.php')) ?>"><?= icon('heart') ?> Favorites</a>
            <?php elseif ($user['role'] === 'organizer'): ?>
                <a href="<?= e(url('organizer/events.php')) ?>"><?= icon('calendar') ?> My events</a>
                <a href="<?= e(url('organizer/check-in.php')) ?>"><?= icon('scan') ?> Check-in</a>
            <?php else: ?>
                <a href="<?= e(url('admin/events.php?status=pending')) ?>"><?= icon('shield') ?> Moderation</a>
            <?php endif; ?>
            <a href="<?= e(url($user['role'] . '/notifications.php')) ?>"><?= icon('bell') ?> Notifications <?php if ($unread): ?><span class="count-pill"><?= (int) $unread ?></span><?php endif; ?></a>
            <a href="<?= e(url($user['role'] . '/profile.php')) ?>"><?= icon('settings') ?> Settings</a>
        <?php else: ?>
            <a href="<?= e(url('register.php?role=organizer')) ?>"><?= icon('building') ?> For organizers</a>
        <?php endif; ?>
    </nav>
    <div class="drawer-foot">
        <?php if ($user): ?>
            <form method="post" action="<?= e(url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline btn-block"><?= icon('logout') ?> Log out</button>
            </form>
        <?php else: ?>
            <a class="btn btn-outline btn-block" href="<?= e(url('login.php')) ?>">Log in</a>
            <a class="btn btn-primary btn-block" href="<?= e(url('register.php')) ?>">Create account</a>
        <?php endif; ?>
    </div>
</aside>

<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false">
    <?php foreach ($flashes as $f): ?>
        <div class="toast toast-<?= e($f['type']) ?>" data-autodismiss role="status">
            <?= icon($f['type'] === 'success' ? 'check-circle' : ($f['type'] === 'error' ? 'x-circle' : 'info')) ?>
            <span><?= e($f['message']) ?></span>
            <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($layout === 'dashboard'): ?>
    <?php require __DIR__ . '/partials/dashboard_nav.php'; ?>
<?php else: ?>
<main id="main" class="site-main">
<?php endif; ?>

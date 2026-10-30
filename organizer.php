<?php
/** Public organizer profile: organizer.php?slug=... */
require_once __DIR__ . '/includes/bootstrap.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
$org = $slug !== '' ? db_one("SELECT * FROM organizer_profiles WHERE slug = ? AND status = 'approved'", [$slug]) : null;
if (!$org) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$upcoming = fetch_event_cards(public_event_where() . ' AND e.organizer_id = ?', [$org['id']], 'e.starts_at ASC', 12);
$stats = db_one(
    "SELECT (SELECT COUNT(*) FROM events WHERE organizer_id = ? AND status = 'published') AS events,
            (SELECT COALESCE(SUM(tt.quantity_sold), 0) FROM ticket_types tt JOIN events e ON e.id = tt.event_id WHERE e.organizer_id = ?) AS tickets,
            (SELECT ROUND(AVG(r.rating), 1) FROM reviews r JOIN events e ON e.id = r.event_id WHERE e.organizer_id = ? AND r.status = 'visible') AS rating,
            (SELECT COUNT(*) FROM reviews r JOIN events e ON e.id = r.event_id WHERE e.organizer_id = ? AND r.status = 'visible') AS reviews",
    [$org['id'], $org['id'], $org['id'], $org['id']]);

$pageTitle = $org['organization_name'];
$pageDescription = str_limit((string) $org['description'], 155);
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
    <div class="container">
        <div class="organizer-card" style="padding:28px">
            <div class="org-logo" style="width:84px;height:84px;font-size:1.6rem">
                <?php if ($org['logo']): ?><img src="<?= e(image_url($org['logo'])) ?>" alt="<?= e($org['organization_name']) ?> logo"><?php else: ?><?= e(initials($org['organization_name'])) ?><?php endif; ?>
            </div>
            <div class="org-info">
                <span class="badge badge-success">Verified organizer</span>
                <h1 style="font-size:2rem;margin:8px 0 4px"><?= e($org['organization_name']) ?></h1>
                <span><?= e((string) $org['city']) ?><?= $org['website'] ? ' · ' : '' ?><?php if ($org['website'] && preg_match('#^https?://#i', $org['website'])): ?><a class="link" href="<?= e($org['website']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e(parse_url($org['website'], PHP_URL_HOST)) ?></a><?php endif; ?></span>
            </div>
            <div class="hero-stats" style="margin:0">
                <div><strong><?= (int) $stats['events'] ?></strong><span>Events</span></div>
                <div><strong><?= number_format((int) $stats['tickets']) ?></strong><span>Tickets sold</span></div>
                <?php if ($stats['rating']): ?><div><strong><?= e((string) $stats['rating']) ?>★</strong><span><?= (int) $stats['reviews'] ?> reviews</span></div><?php endif; ?>
            </div>
        </div>
        <?php if ($org['description']): ?><p class="prose mt-3" style="max-width:760px"><?= nl2br(e($org['description'])) ?></p><?php endif; ?>
    </div>
</section>
<section class="section-sm" style="padding-top:0">
    <div class="container">
        <h2>Upcoming events</h2>
        <?php if ($upcoming): ?>
            <div class="event-grid"><?php foreach ($upcoming as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?></div>
        <?php else: ?>
            <div class="empty-state"><div class="empty-icon"><?= icon('calendar') ?></div><h3>No upcoming events</h3><p>Check back soon — new events are announced regularly.</p></div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

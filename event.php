<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string) ($_GET['slug'] ?? '')));
$event = $slug !== '' ? db_one(
    "SELECT e.*, c.name AS category_name, c.slug AS category_slug, c.color AS category_color, c.icon AS category_icon,
            o.id AS org_id, o.organization_name, o.slug AS org_slug, o.logo AS org_logo, o.description AS org_description,
            o.status AS org_status, o.user_id AS org_user_id
       FROM events e JOIN categories c ON c.id = e.category_id JOIN organizer_profiles o ON o.id = e.organizer_id
      WHERE e.slug = ?", [$slug]) : null;

$user = current_user();
$isOwner = $event && $user && (int) $event['org_user_id'] === (int) $user['id'];
$isAdmin = $user && $user['role'] === 'admin';
$publiclyVisible = $event && in_array($event['status'], ['published', 'cancelled'], true) && $event['org_status'] === 'approved';

if (!$event || (!$publiclyVisible && !$isOwner && !$isAdmin)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Count one view per session per event
$_SESSION['viewed_events'] ??= [];
if ($publiclyVisible && !in_array((int) $event['id'], $_SESSION['viewed_events'], true)) {
    $_SESSION['viewed_events'][] = (int) $event['id'];
    db_exec('UPDATE events SET views = views + 1 WHERE id = ?', [$event['id']]);
}

$ticketTypes = db_all('SELECT * FROM ticket_types WHERE event_id = ? AND is_active = 1 ORDER BY sort_order, price', [$event['id']]);
$ended = strtotime($event['ends_at']) < time();
$cancelled = $event['status'] === 'cancelled';
$bookable = $event['status'] === 'published' && $event['org_status'] === 'approved' && !$ended;
$onSale = array_filter($ticketTypes, fn($t) => ticket_type_state($t) === 'on_sale');
$totalLeft = array_sum(array_map(fn($t) => max(0, (int) $t['quantity'] - (int) $t['quantity_sold']), $ticketTypes));
$soldOut = $ticketTypes && $totalLeft === 0;
$priceSource = $onSale ?: $ticketTypes;
$minPrice = $priceSource ? min(array_map(fn($t) => (float) $t['price'], $priceSource)) : null;
$sold = array_sum(array_column($ticketTypes, 'quantity_sold'));

$rating = db_one("SELECT COUNT(*) AS cnt, ROUND(AVG(rating), 1) AS avg FROM reviews WHERE event_id = ? AND status = 'visible'", [$event['id']]);
$dist = [];
foreach (db_all("SELECT rating, COUNT(*) AS n FROM reviews WHERE event_id = ? AND status = 'visible' GROUP BY rating", [$event['id']]) as $r) {
    $dist[(int) $r['rating']] = (int) $r['n'];
}
$reviews = db_all(
    "SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON u.id = r.user_id
      WHERE r.event_id = ? AND r.status = 'visible' ORDER BY r.created_at DESC LIMIT 20", [$event['id']]);

// Can the current user review this event? (attended booking + event ended + not yet reviewed)
$reviewBooking = null;
if ($user && $user['role'] === 'attendee' && $ended) {
    $reviewBooking = db_one(
        "SELECT b.id FROM bookings b WHERE b.user_id = ? AND b.event_id = ? AND b.status = 'confirmed'
            AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.user_id = b.user_id AND r.event_id = b.event_id) LIMIT 1",
        [$user['id'], $event['id']]);
}
$myTickets = $user ? (int) db_value(
    "SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id
      WHERE b.user_id = ? AND b.event_id = ? AND t.status <> 'cancelled'", [$user['id'], $event['id']]) : 0;

$related = fetch_event_cards(public_event_where() . ' AND e.category_id = ? AND e.id <> ?', [$event['category_id'], $event['id']], 'e.starts_at ASC', 4);
$isFav = in_array((int) $event['id'], user_favorite_ids(), true);
$coords = ($event['latitude'] && $event['longitude']) ? [(float) $event['latitude'], (float) $event['longitude']] : (city_coordinates()[$event['city']] ?? null);
$eventUrl = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . url('event.php?slug=' . $event['slug']);
$sameDay = date('Y-m-d', strtotime($event['starts_at'])) === date('Y-m-d', strtotime($event['ends_at']));

$pageTitle = $event['title'];
$pageDescription = $event['short_description'];
$ogImage = image_url($event['cover_image']);
$bodyClass = $bookable && !$soldOut ? 'has-book-bar' : '';

require __DIR__ . '/includes/header.php';
?>

<?php if (!$publiclyVisible || $event['status'] !== 'published'): ?>
    <div class="container mt-2">
        <?php if ($cancelled): ?>
            <div class="alert alert-error"><?= icon('alert') ?><div><strong>This event has been cancelled.</strong> All bookings were cancelled and refunded. We're sorry for the inconvenience.</div></div>
        <?php else: ?>
            <div class="alert alert-warning"><?= icon('eye') ?><div><strong>Preview mode.</strong> This event is <?= e($event['status']) ?> and is only visible to you<?= $isAdmin ? ' (admin)' : '' ?>.<?= $event['rejection_reason'] ? ' Rejection reason: ' . e($event['rejection_reason']) : '' ?></div></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="event-hero">
    <img src="<?= e(image_url($event['cover_image'])) ?>" alt="<?= e($event['title']) ?> cover image" width="1600" height="900" fetchpriority="high">
    <div class="event-hero-actions">
        <button type="button" class="round-btn" data-share="<?= e($eventUrl) ?>" data-share-title="<?= e($event['title']) ?>" aria-label="Share event"><?= icon('share') ?></button>
        <button type="button" class="fav-btn<?= $isFav ? ' is-active' : '' ?>" data-favorite="<?= (int) $event['id'] ?>" aria-pressed="<?= $isFav ? 'true' : 'false' ?>" aria-label="<?= $isFav ? 'Remove from favorites' : 'Save to favorites' ?>: <?= e($event['title']) ?>"><?= icon('heart') ?></button>
    </div>
    <div class="event-hero-content">
        <div class="container">
            <nav class="breadcrumbs" aria-label="Breadcrumb" style="color:rgba(255,255,255,.75)">
                <a href="<?= e(url('index.php')) ?>">Home</a><?= icon('chevron-right') ?>
                <a href="<?= e(url('events.php')) ?>">Events</a><?= icon('chevron-right') ?>
                <a href="<?= e(url('events.php?category=' . $event['category_slug'])) ?>"><?= e($event['category_name']) ?></a>
            </nav>
            <span class="cat-tag" style="--cat: <?= e($event['category_color']) ?>"><?= e($event['category_name']) ?></span>
            <h1><?= e($event['title']) ?></h1>
            <div class="event-hero-meta">
                <span><?= icon('calendar') ?><?= e(fmt_event_range($event['starts_at'], $event['ends_at'])) ?></span>
                <span><?= icon('pin') ?><?= e($event['venue_name']) ?>, <?= e($event['city']) ?></span>
                <?php if ((int) $rating['cnt'] > 0): ?><span><?= icon('star') ?><?= e((string) $rating['avg']) ?> (<?= (int) $rating['cnt'] ?> reviews)</span><?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="container event-layout">
    <article>
        <div class="event-facts">
            <div class="fact">
                <span class="fact-icon"><?= icon('calendar') ?></span>
                <div>
                    <small>Date</small>
                    <strong><?= e(fmt_date($event['starts_at'], 'F j, Y')) ?></strong>
                    <span><?= $sameDay ? e(fmt_date($event['starts_at'], 'l')) : 'until ' . e(fmt_date($event['ends_at'], 'M j')) ?></span>
                </div>
            </div>
            <div class="fact">
                <span class="fact-icon"><?= icon('clock') ?></span>
                <div>
                    <small>Time</small>
                    <strong><?= e(fmt_time($event['starts_at'])) ?> – <?= e(fmt_time($event['ends_at'])) ?></strong>
                    <span><?= $ended ? 'Event ended' : 'Starts ' . e(time_until($event['starts_at'])) ?></span>
                </div>
            </div>
            <div class="fact">
                <span class="fact-icon"><?= icon('pin') ?></span>
                <div>
                    <small>Location</small>
                    <strong><?= e($event['venue_name']) ?></strong>
                    <span><?= e($event['city']) ?>, <?= e($event['country']) ?></span>
                </div>
            </div>
        </div>

        <?php if ($myTickets > 0): ?>
            <div class="alert alert-success mb-3"><?= icon('ticket') ?><div>You have <?= $myTickets ?> <?= $myTickets === 1 ? 'ticket' : 'tickets' ?> for this event. <a class="link" href="<?= e(url('ticket.php?event=' . $event['id'])) ?>">View my tickets</a></div></div>
        <?php endif; ?>

        <section aria-labelledby="aboutTitle">
            <h2 id="aboutTitle" class="sr-only">About this event</h2>
            <p class="prose" style="font-size:1.2rem;font-weight:600;color:var(--ink)"><?= e($event['short_description']) ?></p>
            <div class="prose"><?= format_description($event['description']) ?></div>
        </section>

        <?php $gallery = db_all('SELECT path, caption FROM event_images WHERE event_id = ? ORDER BY sort_order, id', [$event['id']]); ?>
        <?php if ($gallery): ?>
            <section class="event-section" aria-labelledby="galleryTitle">
                <h2 id="galleryTitle">Gallery</h2>
                <div class="gallery-grid">
                    <?php foreach ($gallery as $g): ?>
                        <a href="<?= e(image_url($g['path'])) ?>" target="_blank" rel="noopener"><img src="<?= e(image_url($g['path'])) ?>" alt="<?= e($g['caption'] ?: $event['title'] . ' photo') ?>" loading="lazy"></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="event-section" aria-labelledby="orgTitle">
            <h2 id="orgTitle">Organizer</h2>
            <div class="organizer-card">
                <div class="org-logo">
                    <?php if ($event['org_logo']): ?><img src="<?= e(image_url($event['org_logo'])) ?>" alt="<?= e($event['organization_name']) ?> logo" loading="lazy"><?php else: ?><?= e(initials($event['organization_name'])) ?><?php endif; ?>
                </div>
                <div class="org-info">
                    <strong><?= e($event['organization_name']) ?></strong>
                    <span><?= e(str_limit((string) $event['org_description'], 140)) ?></span>
                </div>
                <a class="btn btn-outline btn-sm" href="<?= e(url('organizer.php?slug=' . $event['org_slug'])) ?>">View profile</a>
            </div>
        </section>

        <section class="event-section" aria-labelledby="mapTitle">
            <h2 id="mapTitle">Location</h2>
            <div class="map-frame">
                <?php if ($coords): ?>
                    <?php $d = 0.012; $bbox = ($coords[1] - $d) . ',' . ($coords[0] - $d * .6) . ',' . ($coords[1] + $d) . ',' . ($coords[0] + $d * .6); ?>
                    <iframe title="Map of <?= e($event['venue_name']) ?>" loading="lazy" referrerpolicy="no-referrer"
                            src="https://www.openstreetmap.org/export/embed.html?bbox=<?= e(rawurlencode($bbox)) ?>&amp;layer=mapnik&amp;marker=<?= e($coords[0] . ',' . $coords[1]) ?>"></iframe>
                <?php endif; ?>
                <div class="map-foot">
                    <div>
                        <strong><?= e($event['venue_name']) ?></strong><br>
                        <span class="muted small"><?= e($event['address'] ?: $event['city']) ?></span>
                    </div>
                    <?php if ($coords): ?>
                        <a class="btn btn-soft btn-sm" target="_blank" rel="noopener noreferrer" href="https://www.openstreetmap.org/?mlat=<?= e((string) $coords[0]) ?>&amp;mlon=<?= e((string) $coords[1]) ?>#map=16/<?= e($coords[0] . '/' . $coords[1]) ?>"><?= icon('external') ?> Directions</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="event-section" id="reviews" aria-labelledby="reviewsTitle">
            <div class="flex between items-center wrap gap-2 mb-2">
                <h2 id="reviewsTitle" class="mb-0">Reviews</h2>
                <?php if ($reviewBooking): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-modal-open="reviewModal" data-review-event="<?= (int) $event['id'] ?>" data-review-title="<?= e($event['title']) ?>"><?= icon('star') ?> Write a review</button>
                <?php endif; ?>
            </div>
            <?php if ((int) $rating['cnt'] > 0): ?>
                <div class="rating-summary">
                    <div>
                        <div class="rating-big"><?= e((string) $rating['avg']) ?></div>
                        <?= stars_html((float) $rating['avg'], 'lg') ?>
                        <div class="muted small mt-1"><?= (int) $rating['cnt'] ?> <?= (int) $rating['cnt'] === 1 ? 'review' : 'reviews' ?></div>
                    </div>
                    <div class="rating-bars">
                        <?php for ($s = 5; $s >= 1; $s--): $n = $dist[$s] ?? 0; ?>
                            <div class="rating-bar"><span><?= $s ?>★</span><span class="track"><i style="width: <?= (int) round($n / max(1, (int) $rating['cnt']) * 100) ?>%"></i></span><span><?= $n ?></span></div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="review-list">
                    <?php foreach ($reviews as $r): ?>
                        <article class="review-item">
                            <header>
                                <span class="avatar-initials"><?= e(initials($r['first_name'] . ' ' . $r['last_name'])) ?></span>
                                <div>
                                    <strong><?= e($r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.') ?></strong>
                                    <time datetime="<?= e($r['created_at']) ?>"><?= e(fmt_date($r['created_at'])) ?> · Verified attendee</time>
                                </div>
                                <?= stars_html((float) $r['rating']) ?>
                            </header>
                            <?php if ($r['title']): ?><h4><?= e($r['title']) ?></h4><?php endif; ?>
                            <p><?= nl2br(e($r['comment'])) ?></p>
                            <?php if ($r['organizer_reply']): ?>
                                <div class="org-reply"><strong>Reply from <?= e($event['organization_name']) ?></strong><?= nl2br(e($r['organizer_reply'])) ?></div>
                            <?php endif; ?>
                            <?php if ($user && (int) $r['user_id'] !== (int) $user['id']): ?>
                                <div><button type="button" class="link-btn" data-modal-open="reportModal" data-report-type="review" data-report-id="<?= (int) $r['id'] ?>"><?= icon('flag', 'icon-sm') ?> Report</button></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><?= icon('star') ?></div>
                    <h3>No reviews yet</h3>
                    <p>Reviews appear here after the event, written only by verified attendees.</p>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($user && !$isOwner): ?>
            <p class="mt-3 small muted"><button type="button" class="link-btn" data-modal-open="reportModal" data-report-type="event" data-report-id="<?= (int) $event['id'] ?>"><?= icon('flag', 'icon-sm') ?> Report this event</button></p>
        <?php endif; ?>
    </article>

    <aside id="tickets" aria-labelledby="ticketsTitle">
        <div class="ticket-panel">
            <div class="ticket-panel-head">
                <h2 id="ticketsTitle">Tickets</h2>
                <p><?= $sold > 0 ? number_format($sold) . ' people are going' : 'Be the first to book' ?><?= $minPrice !== null ? ' · from ' . e(money($minPrice)) : '' ?></p>
            </div>

            <?php if ($cancelled): ?>
                <div class="soldout-box"><?= icon('x-circle') ?><strong>Event cancelled</strong><p class="muted small mb-0">Ticket sales are closed.</p></div>
            <?php elseif ($ended): ?>
                <div class="soldout-box"><?= icon('clock') ?><strong>This event has ended</strong><p class="muted small mb-0">Discover what's coming up next.</p>
                    <a class="btn btn-dark btn-sm mt-2" href="<?= e(url('events.php?category=' . $event['category_slug'])) ?>">Similar events</a></div>
            <?php elseif (!$ticketTypes): ?>
                <div class="soldout-box"><?= icon('ticket') ?><strong>Tickets coming soon</strong></div>
            <?php elseif ($soldOut): ?>
                <div class="soldout-box"><?= icon('ticket') ?><strong>This event is currently sold out.</strong><p class="muted small mb-0">Save it to your favorites — we'll let you know about similar events.</p></div>
            <?php else: ?>
                <form method="get" action="<?= e(url('checkout.php')) ?>" data-ticket-form data-fee-rate="<?= e((string) SERVICE_FEE_RATE) ?>">
                    <input type="hidden" name="event" value="<?= (int) $event['id'] ?>">
                    <div class="ticket-options">
                        <?php foreach ($ticketTypes as $tt):
                            $state = ticket_type_state($tt);
                            $left = max(0, (int) $tt['quantity'] - (int) $tt['quantity_sold']);
                            $max = min($left, (int) $tt['max_per_order'], MAX_TICKETS_PER_ORDER);
                            $pct = (int) round((int) $tt['quantity_sold'] / max(1, (int) $tt['quantity']) * 100);
                            $available = $state === 'on_sale' && $bookable;
                        ?>
                            <div class="ticket-option<?= $available ? '' : ' is-unavailable' ?>" data-tt="<?= (int) $tt['id'] ?>" data-price="<?= e($tt['price']) ?>">
                                <div>
                                    <strong><?= e($tt['name']) ?></strong>
                                    <?php if ($tt['description']): ?><span class="tt-desc"><?= e($tt['description']) ?></span><?php endif; ?>
                                </div>
                                <span class="tt-price"><?= e(money($tt['price'])) ?></span>
                                <div class="tt-meta">
                                    <?php if ($state === 'sold_out'): ?>
                                        <span class="badge badge-danger">Sold out</span>
                                    <?php elseif ($state === 'ended'): ?>
                                        <span class="badge badge-neutral">Sales ended</span>
                                    <?php elseif ($state === 'not_started'): ?>
                                        <span class="badge badge-info">On sale <?= e(fmt_date($tt['sale_starts_at'], 'M j, H:i')) ?></span>
                                    <?php else: ?>
                                        <span class="tt-left<?= $left <= 20 ? ' low' : '' ?>"><?= $left <= 20 ? 'Only ' . $left . ' left!' : number_format($left) . ' available' ?></span>
                                    <?php endif; ?>
                                    <?php if ($available): ?>
                                        <div class="qty" role="group" aria-label="Quantity for <?= e($tt['name']) ?>">
                                            <button type="button" data-qty-minus aria-label="Decrease"><?= icon('minus', 'icon-sm') ?></button>
                                            <input type="number" name="qty[<?= (int) $tt['id'] ?>]" value="0" min="0" max="<?= $max ?>" inputmode="numeric" aria-label="<?= e($tt['name']) ?> quantity">
                                            <button type="button" data-qty-plus aria-label="Increase"><?= icon('plus', 'icon-sm') ?></button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($state === 'on_sale' && $pct >= 50): ?><div class="tt-progress" title="<?= $pct ?>% sold"><i style="width: <?= $pct ?>%"></i></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="ticket-summary">
                        <div class="summary-line"><span>Tickets</span><span data-sum-count>0</span></div>
                        <div class="summary-line"><span>Subtotal</span><span data-sum-subtotal>€0.00</span></div>
                        <div class="summary-line"><span>Service fee (<?= (int) (SERVICE_FEE_RATE * 100) ?>%)</span><span data-sum-fee>€0.00</span></div>
                        <div class="summary-line total"><span>Total</span><span data-sum-total>€0.00</span></div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block mt-1" data-book-btn disabled><?= icon('ticket') ?> Book tickets</button>
                        <p class="ticket-panel-note"><?= icon('lock') ?> Secure demo checkout · Instant QR tickets</p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php if ($related): ?>
<section class="section-sm section-tinted">
    <div class="container">
        <div class="section-head">
            <div><span class="eyebrow">You might also like</span><h2>More <?= e(strtolower($event['category_name'])) ?> events</h2></div>
        </div>
        <div class="event-grid">
            <?php foreach ($related as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($bookable && !$soldOut && $ticketTypes): ?>
    <div class="mobile-book-bar">
        <span class="price"><small>From</small> <strong><?= e(money($minPrice)) ?></strong></span>
        <a class="btn btn-primary" href="#tickets"><?= icon('ticket') ?> Get tickets</a>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/partials/review_modal.php'; ?>
<?php require __DIR__ . '/includes/partials/report_modal.php'; ?>

<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => $event['title'],
    'description' => $event['short_description'],
    'startDate' => date('c', strtotime($event['starts_at'])),
    'endDate' => date('c', strtotime($event['ends_at'])),
    'eventStatus' => $cancelled ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
    'image' => [image_url($event['cover_image'])],
    'location' => ['@type' => 'Place', 'name' => $event['venue_name'], 'address' => trim(($event['address'] ?? '') . ', ' . $event['city'] . ', ' . $event['country'], ', ')],
    'organizer' => ['@type' => 'Organization', 'name' => $event['organization_name']],
    'offers' => array_map(fn($t) => ['@type' => 'Offer', 'name' => $t['name'], 'price' => $t['price'], 'priceCurrency' => CURRENCY,
        'availability' => ticket_type_state($t) === 'sold_out' ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock'], $ticketTypes),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php
$pageScripts = ['event.js'];
require __DIR__ . '/includes/footer.php';

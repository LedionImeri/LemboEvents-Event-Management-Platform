<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/qrcode.php';

$pageTitle = '';
$pageDescription = 'Find concerts, festivals, conferences, workshops and experiences near you. Book tickets in seconds with LemboEvents.';
$activeNav = 'home';

$where = public_event_where();
$featured = fetch_event_cards("$where AND e.is_featured = 1", [], 'e.starts_at ASC', 3);
$popular = fetch_event_cards($where, [], 'tickets_sold DESC, e.views DESC', 4);
$upcoming = fetch_event_cards($where, [], 'e.starts_at ASC', 8);

$userCity = current_user()['city'] ?? '';
$nearCity = $userCity !== '' && isset(city_coordinates()[$userCity]) ? $userCity : 'Prishtina';
$near = fetch_event_cards("$where AND e.city = ?", [$nearCity], 'e.starts_at ASC', 4);

$categories = db_all(
    "SELECT c.*, (SELECT COUNT(*) FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id
                  WHERE e.category_id = c.id AND $where) AS event_count
       FROM categories c WHERE c.is_active = 1 ORDER BY c.sort_order"
);
$reviews = db_all(
    "SELECT r.rating, r.title, r.comment, r.created_at, u.first_name, u.last_name, u.city, e.title AS event_title, e.slug
       FROM reviews r JOIN users u ON u.id = r.user_id JOIN events e ON e.id = r.event_id
      WHERE r.status = 'visible' AND r.rating >= 4 AND CHAR_LENGTH(r.comment) > 60
      ORDER BY r.rating DESC, r.created_at DESC LIMIT 3"
);
$stats = db_one(
    "SELECT (SELECT COUNT(*) FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE $where) AS live_events,
            (SELECT COUNT(*) FROM tickets) AS tickets,
            (SELECT COUNT(*) FROM organizer_profiles WHERE status = 'approved') AS organizers,
            (SELECT COUNT(DISTINCT city) FROM events WHERE status = 'published') AS cities,
            (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE status = 'visible') AS rating"
);
$cities = array_column(db_all("SELECT DISTINCT e.city FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE $where ORDER BY e.city"), 'city');
$heroEvent = $featured[0] ?? ($upcoming[0] ?? null);

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow">Kosovo's event platform</span>
            <h1>Discover events <em>worth</em> remembering.</h1>
            <p class="hero-sub">Find concerts, festivals, conferences, workshops and experiences near you — and keep every ticket in your pocket.</p>

            <form class="hero-search" action="<?= e(url('events.php')) ?>" method="get" role="search" aria-label="Search events">
                <div class="hs-field">
                    <?= icon('search') ?>
                    <label><span>Event</span><input type="search" name="q" placeholder="Concert, summit, workshop…" maxlength="80"></label>
                </div>
                <div class="hs-field">
                    <?= icon('pin') ?>
                    <label><span>Location</span>
                        <select name="city" aria-label="Location">
                            <option value="">Anywhere</option>
                            <?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?= e($c) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="hs-field">
                    <?= icon('calendar') ?>
                    <label><span>Date</span><input type="date" name="from" min="<?= e(date('Y-m-d')) ?>" aria-label="Date"></label>
                </div>
                <button class="btn btn-primary" type="submit"><?= icon('arrow-right') ?> Explore Events</button>
            </form>

            <div class="hero-stats">
                <div><strong><?= number_format((int) $stats['live_events']) ?></strong><span>Live events</span></div>
                <div><strong><?= number_format((int) $stats['tickets']) ?>+</strong><span>Tickets issued</span></div>
                <div><strong><?= e(number_format((float) $stats['rating'], 1)) ?>★</strong><span>Average rating</span></div>
            </div>
        </div>

        <div class="hero-visual" aria-hidden="true">
            <div class="hero-photo p1"><img src="https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?auto=format&fit=crop&w=1100&q=80" alt="" width="1100" height="900" fetchpriority="high"></div>
            <div class="hero-photo p2"><img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=600&q=80" alt="" width="600" height="500" loading="lazy"></div>
            <?php if ($heroEvent): ?>
                <div class="hero-ticket">
                    <small>Admit one</small>
                    <strong><?= e(str_limit($heroEvent['title'], 34)) ?></strong>
                    <span><?= e(date('M j · H:i', strtotime($heroEvent['starts_at']))) ?> · <?= e($heroEvent['city']) ?></span>
                    <div class="mini-qr"><?= QrCode::svg('LEMBOEVENTS-DEMO', 64) ?></div>
                </div>
            <?php endif; ?>
            <div class="hero-badge"><?= icon('check-circle') ?> Ticket checked in · 0.4s</div>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<section class="section-sm">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Featured</span>
                <h2>Don't miss these headliners</h2>
            </div>
            <a class="btn btn-outline" href="<?= e(url('events.php?sort=popular')) ?>">All events <?= icon('arrow-right') ?></a>
        </div>
        <div class="featured-grid reveal">
            <?= render_event_card($featured[0], 'card-lg') ?>
            <div class="stack">
                <?php foreach (array_slice($featured, 1) as $ev): ?>
                    <?= render_event_card($ev, 'card-row') ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section-sm" id="categories">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Browse by vibe</span>
                <h2>What are you in the mood for?</h2>
            </div>
        </div>
        <div class="category-grid reveal">
            <?php foreach ($categories as $c): ?>
                <a class="category-tile" href="<?= e(url('events.php?category=' . $c['slug'])) ?>" style="--cat: <?= e($c['color']) ?>">
                    <span class="cat-icon"><?= icon($c['icon']) ?></span>
                    <div>
                        <strong><?= e($c['name']) ?></strong>
                        <span><?= (int) $c['event_count'] ?> upcoming <?= (int) $c['event_count'] === 1 ? 'event' : 'events' ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Trending now</span>
                <h2>Popular events</h2>
                <p>The events everyone is booking right now.</p>
            </div>
            <a class="btn btn-ghost" href="<?= e(url('events.php?sort=popular')) ?>">See more <?= icon('arrow-right') ?></a>
        </div>
        <div class="event-grid reveal">
            <?php foreach ($popular as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Coming up</span>
                <h2>Upcoming events</h2>
            </div>
            <a class="btn btn-ghost" href="<?= e(url('events.php?sort=date')) ?>">Full calendar <?= icon('arrow-right') ?></a>
        </div>
        <div class="event-grid reveal">
            <?php foreach ($upcoming as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-sm section-tinted" id="near-you">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Close to home</span>
                <h2>Events near you</h2>
                <p data-near-label>Showing events in <strong><?= e($nearCity) ?></strong>.</p>
            </div>
            <button class="btn btn-dark" type="button" data-near-locate><?= icon('pin') ?> Use my location</button>
        </div>
        <div class="event-grid" data-near-grid>
            <?php foreach ($near as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-dark" id="how-it-works">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">How LemboEvents works</span>
                <h2>From "what's on?" to "I'm in" in three steps</h2>
                <p>No printing, no queues, no lost tickets.</p>
            </div>
        </div>
        <div class="steps reveal">
            <div class="step">
                <span class="step-icon"><?= icon('search') ?></span>
                <h3>Discover</h3>
                <p>Browse by category, city, date or price — or just ask LemboBot what's happening this weekend.</p>
            </div>
            <div class="step">
                <span class="step-icon"><?= icon('ticket') ?></span>
                <h3>Book in seconds</h3>
                <p>Pick your ticket type, check out securely and get instant confirmation with no hidden surprises.</p>
            </div>
            <div class="step">
                <span class="step-icon"><?= icon('qr') ?></span>
                <h3>Show your QR</h3>
                <p>Your digital ticket lives in your account. Organizers scan it at the door — you're in.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="org-cta reveal">
            <div>
                <span class="eyebrow" style="color:#fff">For organizers</span>
                <h2>Sell out your next event.</h2>
                <p>Create an event page in minutes, sell multiple ticket types, and check guests in with your phone camera.</p>
                <ul>
                    <li><?= icon('check') ?> Early bird, VIP and general tickets with live availability</li>
                    <li><?= icon('check') ?> Real-time sales, revenue and attendance analytics</li>
                    <li><?= icon('check') ?> QR check-in that blocks duplicate and fake tickets</li>
                </ul>
                <a class="btn btn-dark btn-lg" href="<?= e(url('register.php?role=organizer')) ?>">Start selling tickets <?= icon('arrow-right') ?></a>
            </div>
            <div class="org-cta-stats">
                <div><strong><?= number_format((int) $stats['organizers']) ?></strong><span>Verified organizers</span></div>
                <div><strong><?= number_format((int) $stats['tickets']) ?></strong><span>Tickets issued</span></div>
                <div><strong><?= number_format((int) $stats['cities']) ?></strong><span>Cities</span></div>
                <div><strong>0.4s</strong><span>Average check-in</span></div>
            </div>
        </div>
    </div>
</section>

<?php if ($reviews): ?>
<section class="section-sm">
    <div class="container">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Loved by attendees</span>
                <h2>Real reviews from real nights out</h2>
            </div>
        </div>
        <div class="review-grid reveal">
            <?php foreach ($reviews as $r): ?>
                <article class="review-card">
                    <?= stars_html((float) $r['rating']) ?>
                    <h4><?= e($r['title']) ?></h4>
                    <blockquote>“<?= e(str_limit($r['comment'], 220)) ?>”</blockquote>
                    <div class="review-author">
                        <span class="avatar-initials"><?= e(initials($r['first_name'] . ' ' . $r['last_name'])) ?></span>
                        <div>
                            <strong><?= e($r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.') ?></strong>
                            <span>at <a class="link" href="<?= e(url('event.php?slug=' . $r['slug'])) ?>"><?= e($r['event_title']) ?></a></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="newsletter reveal">
            <div>
                <h2>Never miss a drop.</h2>
                <p>Early-bird alerts, weekend picks and exclusive presales — once a week, no spam.</p>
            </div>
            <form class="newsletter-form" data-newsletter>
                <label class="sr-only" for="nlEmail">Email address</label>
                <input id="nlEmail" type="email" name="email" placeholder="you@example.com" required maxlength="190" autocomplete="email">
                <button class="btn btn-lime" type="submit">Subscribe</button>
            </form>
        </div>
    </div>
</section>

<?php
$pageScripts = ['home.js'];
require __DIR__ . '/includes/footer.php';

<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/event_search.php';

$p = event_search_params($_GET);
$result = event_search($p);
$categories = db_all('SELECT name, slug, icon, color FROM categories WHERE is_active = 1 ORDER BY sort_order');
$cities = array_column(db_all("SELECT DISTINCT e.city FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE " . public_event_where() . " ORDER BY e.city"), 'city');

$activeCat = null;
foreach ($categories as $c) {
    if ($c['slug'] === $p['category']) $activeCat = $c;
}
$pageTitle = $activeCat ? $activeCat['name'] . ' events' : ($p['city'] ? 'Events in ' . $p['city'] : 'Explore events');
$pageDescription = 'Browse upcoming ' . ($activeCat ? strtolower($activeCat['name']) . ' ' : '') . 'events' . ($p['city'] ? ' in ' . $p['city'] : ' across Kosovo') . '. Filter by date, price and category and book tickets instantly.';
$activeNav = 'events';

$dateOptions = ['' => 'Any time', 'today' => 'Today', 'tomorrow' => 'Tomorrow', 'weekend' => 'This weekend', 'week' => 'Next 7 days', 'month' => 'Next 30 days'];
$priceOptions = ['' => 'Any', 'free' => 'Free', 'paid' => 'Paid'];
$sortOptions = ['date' => 'Soonest first', 'popular' => 'Most popular', 'newest' => 'Newly added', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];

require __DIR__ . '/includes/header.php';
?>

<div class="explore-bar">
    <div class="container">
        <form class="explore-search" data-explore-search role="search" action="<?= e(url('events.php')) ?>" method="get">
            <div class="input-icon">
                <?= icon('search') ?>
                <label class="sr-only" for="exploreQ">Search events</label>
                <input class="input" id="exploreQ" type="search" name="q" value="<?= e($p['q']) ?>" placeholder="Search events, venues, organizers…" maxlength="80" autocomplete="off">
            </div>
            <button type="button" class="btn btn-dark filters-toggle" data-filters-open aria-controls="filters"><?= icon('sliders') ?><span class="hide-sm">Filters</span></button>
        </form>
        <div class="cat-scroller" role="group" aria-label="Categories">
            <button type="button" class="chip<?= $p['category'] === '' ? ' is-active' : '' ?>" data-cat="" aria-pressed="<?= $p['category'] === '' ? 'true' : 'false' ?>"><?= icon('grid') ?> All</button>
            <?php foreach ($categories as $c): ?>
                <button type="button" class="chip<?= $p['category'] === $c['slug'] ? ' is-active' : '' ?>" data-cat="<?= e($c['slug']) ?>" aria-pressed="<?= $p['category'] === $c['slug'] ? 'true' : 'false' ?>"><?= icon($c['icon']) ?> <?= e($c['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="container explore-layout">
    <aside class="filters" id="filters" aria-label="Filters">
        <div class="filters-head">
            <h3>Filters</h3>
            <div class="flex items-center gap-1">
                <button type="button" class="link-btn" data-filters-reset>Reset</button>
                <button type="button" class="icon-btn filters-close" data-filters-close aria-label="Close filters"><?= icon('close') ?></button>
            </div>
        </div>
        <form data-filters-form>
            <div class="form-grid">
                <div class="filter-group">
                    <span class="label">Date</span>
                    <div class="radio-pills">
                        <?php foreach ($dateOptions as $v => $l): ?>
                            <label><input type="radio" name="date" value="<?= e($v) ?>"<?= $p['date'] === $v ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="filter-group">
                    <label class="label" for="fFrom">Specific dates</label>
                    <div class="date-range">
                        <input class="input" id="fFrom" type="date" name="from" value="<?= e($p['from']) ?>" aria-label="From date">
                        <input class="input" type="date" name="to" value="<?= e($p['to']) ?>" aria-label="To date">
                    </div>
                </div>
                <div class="filter-group">
                    <label class="label" for="fCity">City</label>
                    <select class="select" id="fCity" name="city">
                        <option value="">All cities</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= e($c) ?>"<?= $p['city'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <span class="label">Price</span>
                    <div class="radio-pills">
                        <?php foreach ($priceOptions as $v => $l): ?>
                            <label><input type="radio" name="price" value="<?= e($v) ?>"<?= $p['price'] === $v ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="price-range">
                        <input class="input" type="number" name="min" min="0" step="1" placeholder="Min €" value="<?= $p['min'] !== null ? e((string) $p['min']) : '' ?>" aria-label="Minimum price">
                        <input class="input" type="number" name="max" min="0" step="1" placeholder="Max €" value="<?= $p['max'] !== null ? e((string) $p['max']) : '' ?>" aria-label="Maximum price">
                    </div>
                </div>
                <input type="hidden" name="category" value="<?= e($p['category']) ?>">
                <button type="button" class="btn btn-primary btn-block filters-close" data-filters-close>Show results</button>
            </div>
        </form>
    </aside>

    <section aria-labelledby="resultsTitle">
        <div class="results-head">
            <h2 id="resultsTitle" data-results-count aria-live="polite"><?= number_format($result['total']) ?> <?= $result['total'] === 1 ? 'event' : 'events' ?> found</h2>
            <label class="sr-only" for="sortSel">Sort by</label>
            <select class="select" id="sortSel" name="sort" data-sort>
                <?php foreach ($sortOptions as $v => $l): ?>
                    <option value="<?= e($v) ?>"<?= $p['sort'] === $v ? ' selected' : '' ?>><?= e($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="active-filters" data-active-filters></div>

        <div class="event-grid" data-results>
            <?php foreach ($result['items'] as $ev): ?><?= render_event_card($ev) ?><?php endforeach; ?>
        </div>

        <div class="empty-state" data-empty<?= $result['total'] ? ' hidden' : '' ?>>
            <div class="empty-icon"><?= icon('search') ?></div>
            <h3>No events found matching your search.</h3>
            <p>Try a different keyword, widen the dates or clear some filters. New events are added every week.</p>
            <button type="button" class="btn btn-dark" data-filters-reset>Clear all filters</button>
        </div>

        <div class="load-more-wrap">
            <button type="button" class="btn btn-outline btn-lg" data-load-more<?= $result['page'] >= $result['pages'] ? ' hidden' : '' ?>
                    data-page="<?= (int) $result['page'] ?>" data-pages="<?= (int) $result['pages'] ?>">Load more events</button>
        </div>
        <noscript><?= pagination_links(['page' => $result['page'], 'pages' => $result['pages']]) ?></noscript>
    </section>
</div>

<?php
$pageScripts = ['events.js'];
require __DIR__ . '/includes/footer.php';

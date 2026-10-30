<?php
/** Event card. Expects $ev (row from event_card_select()) and optional $variant. */
$isFav = in_array((int) $ev['id'], user_favorite_ids(), true);
$soldOut = (int) $ev['tickets_left'] <= 0 && $ev['min_price'] !== null;
$fewLeft = !$soldOut && (int) $ev['tickets_left'] > 0 && (int) $ev['tickets_left'] <= 25;
$ended = strtotime($ev['ends_at']) < time();
$start = strtotime($ev['starts_at']);
?>
<article class="event-card <?= e($variant ?? '') ?>">
    <a class="event-card-media" href="<?= e(url('event.php?slug=' . $ev['slug'])) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(image_thumb($ev['cover_image'], 800)) ?>" alt="" loading="lazy" decoding="async" width="800" height="500">
        <span class="date-chip">
            <span class="date-chip-m"><?= e(date('M', $start)) ?></span>
            <span class="date-chip-d"><?= e(date('j', $start)) ?></span>
        </span>
        <?php if ($ev['status'] === 'cancelled'): ?>
            <span class="card-flag flag-danger">Cancelled</span>
        <?php elseif ($ended): ?>
            <span class="card-flag flag-muted">Ended</span>
        <?php elseif ($soldOut): ?>
            <span class="card-flag flag-danger">Sold out</span>
        <?php elseif ($fewLeft): ?>
            <span class="card-flag flag-hot">Only <?= (int) $ev['tickets_left'] ?> left</span>
        <?php elseif (!empty($ev['is_featured'])): ?>
            <span class="card-flag">Featured</span>
        <?php endif; ?>
    </a>
    <button type="button" class="fav-btn<?= $isFav ? ' is-active' : '' ?>" data-favorite="<?= (int) $ev['id'] ?>"
            aria-pressed="<?= $isFav ? 'true' : 'false' ?>" aria-label="<?= $isFav ? 'Remove from favorites' : 'Save to favorites' ?>: <?= e($ev['title']) ?>">
        <?= icon('heart') ?>
    </button>
    <div class="event-card-body">
        <a class="cat-tag" href="<?= e(url('events.php?category=' . $ev['category_slug'])) ?>" style="--cat: <?= e($ev['category_color']) ?>"><?= e($ev['category_name']) ?></a>
        <h3 class="event-card-title"><a href="<?= e(url('event.php?slug=' . $ev['slug'])) ?>"><?= e($ev['title']) ?></a></h3>
        <ul class="event-meta">
            <li><?= icon('calendar') ?><?= e(date('D, M j · H:i', $start)) ?></li>
            <li><?= icon('pin') ?><?= e($ev['venue_name']) ?>, <?= e($ev['city']) ?></li>
        </ul>
        <div class="event-card-foot">
            <span class="price">
                <?php if ($ev['min_price'] === null): ?>
                    <span class="muted">Tickets soon</span>
                <?php elseif ((float) $ev['min_price'] <= 0): ?>
                    <strong>Free</strong>
                <?php else: ?>
                    <small>From</small> <strong><?= e(money($ev['min_price'])) ?></strong>
                <?php endif; ?>
            </span>
            <?php if ((int) $ev['tickets_sold'] > 0): ?>
                <span class="going"><?= icon('users') ?> <?= number_format((int) $ev['tickets_sold']) ?> going</span>
            <?php endif; ?>
        </div>
    </div>
</article>

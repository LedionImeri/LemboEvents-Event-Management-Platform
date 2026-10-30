<?php
/** Digital ticket. Expects $t from tickets_query(). The QR encodes a signed ticket identifier only. */
$void = $t['status'] === 'cancelled';
?>
<article class="e-ticket<?= $void ? ' is-void' : '' ?>" aria-label="Ticket for <?= e($t['event_title']) ?>">
    <?php if ($void): ?><div class="void-stamp" aria-hidden="true">VOID</div><?php endif; ?>
    <div class="e-ticket-main">
        <div class="e-ticket-cover">
            <img src="<?= e(image_thumb($t['cover_image'], 1000)) ?>" alt="" loading="lazy">
            <span class="et-brand">🎟️ Lembo<span>Events</span></span>
            <h2><?= e($t['event_title']) ?></h2>
        </div>
        <div class="e-ticket-info">
            <div class="et-field"><small>Attendee</small><strong><?= e($t['attendee_name']) ?></strong></div>
            <div class="et-field"><small>Ticket type</small><strong><?= e($t['ticket_type']) ?></strong></div>
            <div class="et-field"><small>Date</small><strong><?= e(fmt_date($t['starts_at'], 'D, M j, Y')) ?></strong></div>
            <div class="et-field"><small>Time</small><strong><?= e(fmt_time($t['starts_at'])) ?> – <?= e(fmt_time($t['ends_at'])) ?></strong></div>
            <div class="et-field full"><small>Location</small><strong><?= e($t['venue_name']) ?>, <?= e($t['city']) ?></strong></div>
            <div class="et-field"><small>Ticket ID</small><strong>#<?= str_pad((string) $t['id'], 6, '0', STR_PAD_LEFT) ?></strong></div>
            <div class="et-field"><small>Booking</small><strong><?= e($t['reference']) ?></strong></div>
        </div>
    </div>
    <div class="e-ticket-stub">
        <div class="qr-box"><?= QrCode::svg(ticket_qr_payload($t['code']), 190) ?></div>
        <div class="et-code"><?= e(format_ticket_code($t['code'])) ?></div>
        <div class="et-status">
            <?php if ($t['status'] === 'checked_in'): ?>
                <span class="badge badge-info">Checked in <?= e(fmt_date($t['checked_in_at'], 'M j, H:i')) ?></span>
            <?php elseif ($void): ?>
                <span class="badge badge-danger">Cancelled</span>
            <?php elseif (strtotime($t['ends_at']) < time()): ?>
                <span class="badge badge-neutral">Event ended</span>
            <?php else: ?>
                <span class="badge badge-success">Valid · show at entry</span>
            <?php endif; ?>
        </div>
    </div>
</article>

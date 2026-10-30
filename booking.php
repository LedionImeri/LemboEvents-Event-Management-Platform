<?php
/** Booking confirmation / receipt page: booking.php?ref=LE-XXXXXXXX */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/includes/qrcode.php';

$user = require_login();
$ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_GET['ref'] ?? '')));
$booking = $ref !== '' ? db_one(
    'SELECT b.*, e.title, e.slug, e.starts_at, e.ends_at, e.venue_name, e.city, e.cover_image, o.user_id AS org_user_id
       FROM bookings b JOIN events e ON e.id = b.event_id JOIN organizer_profiles o ON o.id = e.organizer_id WHERE b.reference = ?', [$ref]) : null;

// Only the buyer, the event's organizer or an admin may see a booking
if (!$booking || ((int) $booking['user_id'] !== (int) $user['id'] && (int) $booking['org_user_id'] !== (int) $user['id'] && $user['role'] !== 'admin')) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$items = db_all('SELECT bi.*, tt.name FROM booking_items bi JOIN ticket_types tt ON tt.id = bi.ticket_type_id WHERE bi.booking_id = ?', [$booking['id']]);
$tickets = tickets_query('b.id = ?', [$booking['id']], 't.id ASC');
$isNew = isset($_GET['new']) && $booking['status'] === 'confirmed';

$pageTitle = $isNew ? 'Booking confirmed' : 'Booking ' . $booking['reference'];
require __DIR__ . '/includes/header.php';
?>
<div class="container">
    <?php if ($isNew): ?>
        <div class="confirm-hero">
            <div class="confirm-check"><?= icon('check') ?></div>
            <h1>You're going! 🎉</h1>
            <p class="muted" style="font-size:1.1rem">Booking <strong><?= e($booking['reference']) ?></strong> is confirmed. Your <?= count($tickets) === 1 ? 'ticket is' : count($tickets) . ' tickets are' ?> below and saved in your account.</p>
        </div>
    <?php else: ?>
        <div class="page-hero">
            <nav class="breadcrumbs"><a href="<?= e(url($user['role'] === 'attendee' ? 'attendee/bookings.php' : dashboard_url())) ?>"><?= icon('arrow-left') ?> Back</a></nav>
            <h1>Booking <?= e($booking['reference']) ?></h1>
            <p><?= status_badge($booking['status']) ?> · Booked <?= e(fmt_date($booking['created_at'], 'M j, Y \a\t H:i')) ?></p>
        </div>
    <?php endif; ?>

    <div class="ticket-actions no-print mb-3">
        <button type="button" class="btn btn-dark" data-print><?= icon('printer') ?> Print / Save as PDF</button>
        <a class="btn btn-outline" href="<?= e(url('ticket.php?booking=' . $booking['reference'] . '&ics=1')) ?>"><?= icon('calendar') ?> Add to calendar</a>
        <a class="btn btn-ghost" href="<?= e(url('event.php?slug=' . $booking['slug'])) ?>">Event page <?= icon('arrow-right') ?></a>
    </div>

    <div class="ticket-wrap" style="padding-top:0">
        <?php foreach ($tickets as $t): ?>
            <?php include __DIR__ . '/includes/partials/e_ticket.php'; ?>
        <?php endforeach; ?>

        <section class="card no-print" style="width:100%;max-width:820px" aria-labelledby="receiptTitle">
            <div class="card-head"><h2 id="receiptTitle"><?= icon('money') ?> Receipt</h2><?= status_badge($booking['status']) ?></div>
            <div class="order-lines" style="border-top:0;padding-top:0">
                <?php foreach ($items as $it): ?>
                    <div class="order-line"><span><?= (int) $it['quantity'] ?> × <?= e($it['name']) ?></span><span><?= e(money_exact($it['line_total'])) ?></span></div>
                <?php endforeach; ?>
            </div>
            <div class="ticket-summary" style="padding:14px 0 0">
                <div class="summary-line"><span>Subtotal</span><span><?= e(money_exact($booking['subtotal'])) ?></span></div>
                <div class="summary-line"><span>Service fee</span><span><?= e(money_exact($booking['service_fee'])) ?></span></div>
                <div class="summary-line total"><span>Total paid</span><span><?= e(money_exact($booking['total'])) ?></span></div>
                <p class="small muted mb-0 mt-1">
                    <?= $booking['payment_method'] === 'free' ? 'Free booking' : 'Paid with card •••• ' . e($booking['payment_last4']) . ' (demo payment)' ?>
                    · Sent to <?= e($booking['customer_email']) ?>
                </p>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

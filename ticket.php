<?php
/**
 * Digital tickets.
 *   ticket.php?code=XXXXXXXXXXXXXXXX   one ticket
 *   ticket.php?booking=LE-XXXXXXXX     all tickets of a booking
 *   ticket.php?event=ID                all of my tickets for an event
 *   add &ics=1 to download a calendar file
 * Visible to the ticket owner, the event's organizer and admins only.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/includes/qrcode.php';

$user = require_login();
$uid = (int) $user['id'];
$isAdmin = $user['role'] === 'admin';

if (isset($_GET['code'])) {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $_GET['code']));
    $tickets = tickets_query('t.code = ?', [$code]);
} elseif (isset($_GET['booking'])) {
    $ref = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $_GET['booking']));
    $tickets = tickets_query('b.reference = ?', [$ref]);
} else {
    $tickets = tickets_query("b.event_id = ? AND b.user_id = ? AND b.status = 'confirmed'", [(int) ($_GET['event'] ?? 0), $uid]);
}

// Authorization: every ticket must belong to the user, their event, or the user must be admin
$tickets = array_values(array_filter($tickets, fn($t) => $isAdmin || (int) $t['user_id'] === $uid || (int) $t['org_user_id'] === $uid));
if (!$tickets) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$first = $tickets[0];

if (isset($_GET['ics'])) {
    $esc = fn(string $s) => addcslashes(str_replace(["\r", "\n"], ' ', $s), ',;\\');
    $fmt = fn(string $d) => gmdate('Ymd\THis\Z', strtotime($d));
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="lemboevents-' . $first['event_slug'] . '.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//LemboEvents//Tickets//EN\r\nBEGIN:VEVENT\r\n"
        . 'UID:' . $first['reference'] . "@lemboevents\r\n"
        . 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n"
        . 'DTSTART:' . $fmt($first['starts_at']) . "\r\n"
        . 'DTEND:' . $fmt($first['ends_at']) . "\r\n"
        . 'SUMMARY:' . $esc($first['event_title']) . "\r\n"
        . 'LOCATION:' . $esc($first['venue_name'] . ', ' . $first['city']) . "\r\n"
        . 'DESCRIPTION:' . $esc('Booking ' . $first['reference'] . ' — ' . count($tickets) . ' ticket(s). Show your QR code at the entrance.') . "\r\n"
        . "END:VEVENT\r\nEND:VCALENDAR\r\n";
    exit;
}

$pageTitle = 'Ticket · ' . $first['event_title'];
$pageDescription = 'Your digital ticket for ' . $first['event_title'];
require __DIR__ . '/includes/header.php';
?>
<div class="container">
    <div class="page-hero" style="padding-bottom:16px">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url($user['role'] === 'attendee' ? 'attendee/tickets.php' : dashboard_url())) ?>"><?= icon('arrow-left') ?> My tickets</a>
        </nav>
        <h1><?= count($tickets) === 1 ? 'Your ticket' : 'Your tickets (' . count($tickets) . ')' ?></h1>
        <p>Show the QR code at the entrance. Screen brightness up helps the scanner. 📱</p>
    </div>
    <div class="ticket-actions no-print">
        <button type="button" class="btn btn-dark" data-print><?= icon('printer') ?> Print / Save as PDF</button>
        <a class="btn btn-outline" href="<?= e(url('ticket.php?booking=' . $first['reference'] . '&ics=1')) ?>"><?= icon('calendar') ?> Add to calendar</a>
        <a class="btn btn-ghost" href="<?= e(url('booking.php?ref=' . $first['reference'])) ?>"><?= icon('money') ?> Receipt</a>
    </div>
    <div class="ticket-wrap">
        <?php foreach ($tickets as $t): ?>
            <?php include __DIR__ . '/includes/partials/e_ticket.php'; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

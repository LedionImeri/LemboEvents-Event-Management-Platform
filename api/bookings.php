<?php
/**
 * GET  api/bookings.php?event=ID&qty[ID]=n  → live price quote (validates availability)
 * POST api/bookings.php {action: cancel, booking_id} → cancel own booking (attendee) / any booking (admin)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';

if (!is_post()) {
    $qty = is_array($_GET['qty'] ?? null) ? $_GET['qty'] : [];
    try {
        $q = booking_quote((int) ($_GET['event'] ?? 0), $qty);
    } catch (BookingException $e) {
        json_error($e->getMessage(), 422);
    }
    json_response(['ok' => true, 'count' => $q['count'], 'subtotal' => $q['subtotal'], 'fee' => $q['fee'], 'total' => $q['total']]);
}

$user = require_role('attendee', 'admin');
csrf_verify();
$d = request_data();

if (($d['action'] ?? '') !== 'cancel') {
    json_error('Unknown action.', 422);
}
try {
    $b = cancel_booking((int) ($d['booking_id'] ?? 0), $user);
} catch (BookingException $e) {
    json_error($e->getMessage(), 422);
}
json_response(['ok' => true, 'message' => 'Booking ' . $b['reference'] . ' was cancelled.' . ((float) $b['total'] > 0 ? ' Refund issued (demo).' : '')]);

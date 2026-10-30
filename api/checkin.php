<?php
/**
 * POST api/checkin.php { payload, event_id?, method?: qr|manual }
 *
 * Validates a scanned QR payload (or a typed ticket code) entirely on the server:
 *   1. format + HMAC signature of the QR payload,
 *   2. the ticket exists in the database,
 *   3. the ticket belongs to an event this organizer manages (admins: any event),
 *   4. it matches the event selected in the scanner,
 *   5. booking/ticket/event are not cancelled,
 *   6. it has not been used — enforced atomically by the UNIQUE key on check_ins.ticket_id.
 * Result: valid | used | invalid.
 *
 * GET api/checkin.php?event_id=ID → live counters for the scanner screen.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/qrcode.php';

$user = require_role('organizer', 'admin');
$isAdmin = $user['role'] === 'admin';
$org = $isAdmin ? null : current_organizer();

function checkin_counts(int $eventId): array
{
    $r = db_one(
        "SELECT COUNT(*) AS total, COALESCE(SUM(t.status = 'checked_in'), 0) AS checked
           FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id JOIN bookings b ON b.id = bi.booking_id
          WHERE b.event_id = ? AND t.status <> 'cancelled'", [$eventId]);
    return ['total' => (int) $r['total'], 'checked' => (int) $r['checked']];
}

function event_allowed(int $eventId, bool $isAdmin, ?array $org): bool
{
    return $isAdmin || ($org && db_value('SELECT 1 FROM events WHERE id = ? AND organizer_id = ?', [$eventId, $org['id']]));
}

if (!is_post()) {
    $eventId = (int) ($_GET['event_id'] ?? 0);
    if (!event_allowed($eventId, $isAdmin, $org)) {
        json_error('Event not found.', 404);
    }
    json_response(['ok' => true, 'counts' => checkin_counts($eventId)]);
}

csrf_verify();
$d = request_data();
$payload = mb_substr((string) ($d['payload'] ?? ''), 0, 200);
$selectedEvent = (int) ($d['event_id'] ?? 0);
$method = ($d['method'] ?? 'qr') === 'manual' ? 'manual' : 'qr';

$respond = function (string $result, string $title, string $message, ?array $ticket = null, ?int $eventId = null) {
    json_response([
        'ok' => true, 'result' => $result, 'title' => $title, 'message' => $message,
        'ticket' => $ticket, 'counts' => $eventId ? checkin_counts($eventId) : null,
    ]);
};

if ($selectedEvent && !event_allowed($selectedEvent, $isAdmin, $org)) {
    json_error('Event not found.', 404);
}

$parsed = parse_ticket_payload($payload);
if (!$parsed) {
    $respond('invalid', 'Invalid Ticket', 'This QR code is not a LemboEvents ticket.', null, $selectedEvent ?: null);
}
if (!$parsed['signature_ok']) {
    log_activity('checkin.forged', 'Rejected a QR code with an invalid signature', 'event', $selectedEvent ?: null);
    $respond('invalid', 'Invalid Ticket', 'The QR signature does not match. This ticket may be forged.', null, $selectedEvent ?: null);
}

$t = db_one(
    "SELECT t.id, t.code, t.attendee_name, t.status, tt.name AS ticket_type, b.status AS booking_status, b.reference,
            e.id AS event_id, e.title AS event_title, e.status AS event_status, e.organizer_id, ci.checked_in_at, ci.method AS ci_method
       FROM tickets t
       JOIN booking_items bi ON bi.id = t.booking_item_id
       JOIN bookings b ON b.id = bi.booking_id
       JOIN ticket_types tt ON tt.id = bi.ticket_type_id
       JOIN events e ON e.id = b.event_id
       LEFT JOIN check_ins ci ON ci.ticket_id = t.id
      WHERE t.code = ?", [$parsed['code']]);

if (!$t || (!$isAdmin && (int) $t['organizer_id'] !== (int) $org['id'])) {
    $respond('invalid', 'Invalid Ticket', 'No ticket with this code exists for your events.', null, $selectedEvent ?: null);
}
$info = [
    'id' => '#' . str_pad((string) $t['id'], 6, '0', STR_PAD_LEFT), 'code' => format_ticket_code($t['code']),
    'attendee' => $t['attendee_name'], 'type' => $t['ticket_type'], 'event' => $t['event_title'], 'booking' => $t['reference'],
];
if ($selectedEvent && (int) $t['event_id'] !== $selectedEvent) {
    $respond('invalid', 'Wrong Event', 'This ticket is for “' . $t['event_title'] . '”, not the event you are scanning for.', $info, $selectedEvent);
}
if ($t['event_status'] === 'cancelled' || $t['booking_status'] !== 'confirmed' || $t['status'] === 'cancelled') {
    $respond('invalid', 'Invalid Ticket', 'This ticket was cancelled' . ($t['booking_status'] === 'refunded' ? ' and refunded' : '') . '.', $info, (int) $t['event_id']);
}
if ($t['status'] === 'checked_in') {
    $info['checked_in_at'] = fmt_date($t['checked_in_at'], 'M j, H:i:s');
    $respond('used', 'Ticket Already Used', 'Checked in ' . time_ago($t['checked_in_at']) . ' (' . fmt_date($t['checked_in_at'], 'H:i') . ').', $info, (int) $t['event_id']);
}

// Atomic check-in: the UNIQUE(ticket_id) constraint guarantees one winner if two devices scan simultaneously
$pdo = db();
$pdo->beginTransaction();
try {
    db_exec('INSERT INTO check_ins (ticket_id, checked_in_by, method) VALUES (?, ?, ?)', [$t['id'], $user['id'], $method]);
    db_exec("UPDATE tickets SET status = 'checked_in' WHERE id = ? AND status = 'valid'", [$t['id']]);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        $respond('used', 'Ticket Already Used', 'This ticket was just checked in on another device.', $info, (int) $t['event_id']);
    }
    throw $e;
}
$info['checked_in_at'] = date('M j, H:i:s');
$respond('valid', 'Ticket Valid', 'Welcome, ' . $t['attendee_name'] . '! Marked as checked in.', $info, (int) $t['event_id']);

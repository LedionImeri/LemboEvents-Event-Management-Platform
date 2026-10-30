<?php
/**
 * GET api/tickets.php?event=ID — live availability of an event's ticket types.
 * Used by the event page to refresh "X left" / "Sold out" without reloading.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$eventId = (int) ($_GET['event'] ?? 0);
$event = db_one(
    "SELECT e.id FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id
      WHERE e.id = ? AND e.status = 'published' AND o.status = 'approved'", [$eventId]);
if (!$event) {
    json_error('Event not found.', 404);
}

$types = db_all('SELECT * FROM ticket_types WHERE event_id = ? AND is_active = 1 ORDER BY sort_order, price', [$eventId]);
json_response([
    'ok' => true,
    'ticket_types' => array_map(fn($t) => [
        'id' => (int) $t['id'],
        'name' => $t['name'],
        'price' => (float) $t['price'],
        'available' => max(0, (int) $t['quantity'] - (int) $t['quantity_sold']),
        'state' => ticket_type_state($t),
        'max_per_order' => min((int) $t['max_per_order'], MAX_TICKETS_PER_ORDER),
    ], $types),
]);

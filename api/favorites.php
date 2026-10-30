<?php
/**
 * POST api/favorites.php  { event_id }  → toggles a favorite for the logged-in user.
 * GET  api/favorites.php                → list of favorite event ids.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

if (!is_post()) {
    json_response(['ok' => true, 'ids' => user_favorite_ids()]);
}
csrf_verify();

$eventId = (int) (request_data()['event_id'] ?? 0);
$event = $eventId > 0 ? db_one("SELECT id, title FROM events WHERE id = ? AND status IN ('published','cancelled')", [$eventId]) : null;
if (!$event) {
    json_error('Event not found.', 404);
}

$exists = db_value('SELECT 1 FROM favorites WHERE user_id = ? AND event_id = ?', [$user['id'], $eventId]);
if ($exists) {
    db_exec('DELETE FROM favorites WHERE user_id = ? AND event_id = ?', [$user['id'], $eventId]);
} else {
    db_exec('INSERT IGNORE INTO favorites (user_id, event_id) VALUES (?, ?)', [$user['id'], $eventId]);
}

json_response([
    'ok'        => true,
    'favorited' => !$exists,
    'count'     => (int) db_value('SELECT COUNT(*) FROM favorites WHERE user_id = ?', [$user['id']]),
]);

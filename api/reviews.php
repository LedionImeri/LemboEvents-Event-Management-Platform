<?php
/**
 * POST api/reviews.php { event_id, rating, title?, comment }
 * Only attendees with a confirmed booking for an event that has ended may review it, once.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_role('attendee');
if (!is_post()) {
    json_error('Method not allowed.', 405);
}
csrf_verify();

$d = request_data();
$eventId = (int) ($d['event_id'] ?? 0);
$rating = (int) ($d['rating'] ?? 0);
$title = trim((string) ($d['title'] ?? ''));
$comment = trim((string) ($d['comment'] ?? ''));

if ($rating < 1 || $rating > 5) json_error('Please choose a rating from 1 to 5 stars.', 422);
if (mb_strlen($comment) < 10) json_error('Your review should be at least 10 characters.', 422);
if (mb_strlen($comment) > 2000) json_error('Your review is too long (max 2000 characters).', 422);
if (mb_strlen($title) > 120) json_error('The headline is too long.', 422);

$event = db_one('SELECT e.id, e.title, e.ends_at, o.user_id AS org_user_id FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE e.id = ?', [$eventId]);
if (!$event) json_error('Event not found.', 404);
if (strtotime($event['ends_at']) > time()) json_error('You can review this event after it has ended.', 422);

$booking = db_one("SELECT id FROM bookings WHERE user_id = ? AND event_id = ? AND status = 'confirmed' ORDER BY id LIMIT 1", [$user['id'], $eventId]);
if (!$booking) json_error('Only attendees with a valid booking can review this event.', 403);
if (db_value('SELECT 1 FROM reviews WHERE user_id = ? AND event_id = ?', [$user['id'], $eventId])) {
    json_error('You have already reviewed this event.', 409);
}

try {
    db_exec('INSERT INTO reviews (event_id, user_id, booking_id, rating, title, comment) VALUES (?, ?, ?, ?, ?, ?)',
        [$eventId, $user['id'], $booking['id'], $rating, $title !== '' ? $title : null, $comment]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') json_error('You have already reviewed this event.', 409); // unique key race
    throw $e;
}
notify((int) $event['org_user_id'], 'review', 'New ' . $rating . '-star review', $user['first_name'] . ' reviewed ' . $event['title'] . '.', 'organizer/reviews.php');
log_activity('review.created', 'Reviewed ' . $event['title'] . ' (' . $rating . '★)', 'event', $eventId);

json_response(['ok' => true, 'message' => 'Thanks! Your review is live.']);

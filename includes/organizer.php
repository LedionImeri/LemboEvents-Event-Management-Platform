<?php
/**
 * Organizer helpers: ownership checks and the event status workflow.
 *
 *   draft ──submit──▶ pending ──admin approves──▶ published ──unpublish──▶ draft
 *     ▲                  │                          │
 *     └──── rejected ◀───┘ admin rejects            └──cancel──▶ cancelled (refunds everyone)
 *
 * Once an event has been approved, re-publishing skips the review queue.
 */

/** Loads an event that belongs to the organizer, or stops with 404. */
function org_event_or_404(int $eventId, array $org): array
{
    $ev = db_one('SELECT * FROM events WHERE id = ? AND organizer_id = ?', [$eventId, $org['id']]);
    if (!$ev) {
        if (is_api_request()) {
            json_error('Event not found.', 404);
        }
        http_response_code(404);
        require ROOT_PATH . '/404.php';
        exit;
    }
    return $ev;
}

function org_events_list(array $org): array
{
    return db_all('SELECT id, title, starts_at, status FROM events WHERE organizer_id = ? ORDER BY starts_at DESC', [$org['id']]);
}

/** Submit (or re-publish) an event. Returns a user-facing message. */
function org_submit_event(array $event, array $org): string
{
    if ($org['status'] !== 'approved') {
        throw new RuntimeException('Your organizer account is still pending approval. You can publish events once it is approved.');
    }
    if (!in_array($event['status'], ['draft', 'rejected'], true)) {
        throw new RuntimeException('Only drafts can be published.');
    }
    if (strtotime($event['ends_at']) < time()) {
        throw new RuntimeException('This event is in the past. Update its dates first.');
    }
    $types = (int) db_value('SELECT COUNT(*) FROM ticket_types WHERE event_id = ? AND is_active = 1', [$event['id']]);
    if ($types === 0) {
        throw new RuntimeException('Add at least one active ticket type before publishing.');
    }
    if ($event['approved_at'] && $event['status'] === 'draft') {
        db_exec("UPDATE events SET status = 'published' WHERE id = ?", [$event['id']]);
        log_activity('event.published', 'Re-published event ' . $event['title'], 'event', (int) $event['id']);
        return 'Your event is live again.';
    }
    db_exec("UPDATE events SET status = 'pending', rejection_reason = NULL WHERE id = ?", [$event['id']]);
    notify_admins('event_submitted', 'Event awaiting approval', $event['title'] . ' was submitted for review by ' . $org['organization_name'] . '.', 'admin/events.php?status=pending');
    log_activity('event.submitted', 'Submitted event ' . $event['title'] . ' for review', 'event', (int) $event['id']);
    return 'Submitted for review. We usually approve events within a few hours.';
}

function org_unpublish_event(array $event): string
{
    if (!in_array($event['status'], ['published', 'pending'], true)) {
        throw new RuntimeException('Only published or pending events can be unpublished.');
    }
    $sold = (int) db_value("SELECT COUNT(*) FROM bookings WHERE event_id = ? AND status = 'confirmed'", [$event['id']]);
    if ($sold > 0) {
        throw new RuntimeException('This event already has bookings. Cancel the event instead so attendees are refunded and notified.');
    }
    db_exec("UPDATE events SET status = 'draft' WHERE id = ?", [$event['id']]);
    log_activity('event.unpublished', 'Unpublished event ' . $event['title'], 'event', (int) $event['id']);
    return 'The event was moved back to drafts.';
}

function org_delete_event(array $event): string
{
    if ((int) db_value('SELECT COUNT(*) FROM bookings WHERE event_id = ?', [$event['id']]) > 0) {
        throw new RuntimeException('Events with bookings cannot be deleted. Cancel the event instead.');
    }
    db_exec('DELETE FROM events WHERE id = ?', [$event['id']]);
    delete_upload($event['cover_image']);
    log_activity('event.deleted', 'Deleted event ' . $event['title'], 'event', (int) $event['id']);
    return 'Event deleted.';
}

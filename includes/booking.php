<?php
/**
 * Booking service: creating bookings, cancelling bookings and cancelling whole events.
 *
 * Overbooking protection — inside one transaction:
 *   1. the selected ticket_types rows are locked with SELECT ... FOR UPDATE,
 *   2. availability and sale windows are validated against the locked rows,
 *   3. quantity_sold is incremented with a guarded UPDATE
 *      (WHERE quantity - quantity_sold >= :n) and the affected row count is checked.
 * Two buyers racing for the last ticket are serialised by the row lock; the loser gets
 * a friendly "sold out" error and nothing is charged or created.
 */

final class BookingException extends RuntimeException {}

/**
 * Validates the ticket selection against the database. Returns normalized lines
 * [['type' => row, 'qty' => n, 'line' => float], ...] and totals. Throws BookingException.
 * When $lock is true the rows are locked FOR UPDATE (must be inside a transaction).
 */
function booking_quote(int $eventId, array $qty, bool $lock = false): array
{
    $event = db_one(
        "SELECT e.*, o.status AS org_status, o.user_id AS org_user_id, o.organization_name
           FROM events e JOIN organizer_profiles o ON o.id = e.organizer_id WHERE e.id = ?", [$eventId]);
    if (!$event || $event['status'] !== 'published' || $event['org_status'] !== 'approved') {
        throw new BookingException('This event is not available for booking.');
    }
    if (strtotime($event['ends_at']) < time()) {
        throw new BookingException('This event has already ended.');
    }

    $clean = [];
    foreach ($qty as $id => $n) {
        $id = (int) $id;
        $n = (int) $n;
        if ($id > 0 && $n > 0) {
            $clean[$id] = $n;
        }
    }
    $count = array_sum($clean);
    if ($count === 0) {
        throw new BookingException('Please select at least one ticket.');
    }
    if ($count > MAX_TICKETS_PER_ORDER) {
        throw new BookingException('You can book up to ' . MAX_TICKETS_PER_ORDER . ' tickets per order.');
    }

    $placeholders = implode(',', array_fill(0, count($clean), '?'));
    $rows = db_all(
        "SELECT * FROM ticket_types WHERE event_id = ? AND id IN ($placeholders) ORDER BY id" . ($lock ? ' FOR UPDATE' : ''),
        array_merge([$eventId], array_keys($clean))
    );
    if (count($rows) !== count($clean)) {
        throw new BookingException('One of the selected ticket types is no longer available.');
    }

    $lines = [];
    $subtotal = 0.0;
    foreach ($rows as $tt) {
        $n = $clean[(int) $tt['id']];
        $left = (int) $tt['quantity'] - (int) $tt['quantity_sold'];
        switch (ticket_type_state($tt)) {
            case 'inactive':
                throw new BookingException($tt['name'] . ' tickets are not available.');
            case 'sold_out':
                throw new BookingException('Sorry — ' . $tt['name'] . ' just sold out.');
            case 'not_started':
                throw new BookingException($tt['name'] . ' tickets are not on sale yet.');
            case 'ended':
                throw new BookingException('Sales for ' . $tt['name'] . ' have ended.');
        }
        if ($n > $left) {
            throw new BookingException('Only ' . $left . ' ' . $tt['name'] . ' ' . ($left === 1 ? 'ticket is' : 'tickets are') . ' left.');
        }
        if ($n > (int) $tt['max_per_order']) {
            throw new BookingException('You can book up to ' . (int) $tt['max_per_order'] . ' ' . $tt['name'] . ' tickets per order.');
        }
        $line = round((float) $tt['price'] * $n, 2);
        $subtotal += $line;
        $lines[] = ['type' => $tt, 'qty' => $n, 'line' => $line];
    }
    $fee = service_fee($subtotal);
    return ['event' => $event, 'lines' => $lines, 'count' => $count, 'subtotal' => round($subtotal, 2), 'fee' => $fee, 'total' => round($subtotal + $fee, 2)];
}

/**
 * Creates a confirmed booking with tickets. $customer = [name, email, phone], $payment = [method, last4].
 * Returns the booking row.
 */
function create_booking(array $user, int $eventId, array $qty, array $customer, array $payment): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $quote = booking_quote($eventId, $qty, true);

        foreach ($quote['lines'] as $l) {
            $ok = db_exec('UPDATE ticket_types SET quantity_sold = quantity_sold + ? WHERE id = ? AND quantity - quantity_sold >= ?',
                [$l['qty'], $l['type']['id'], $l['qty']]);
            if ($ok !== 1) {
                throw new BookingException('Sorry — ' . $l['type']['name'] . ' just sold out.');
            }
        }

        // Unique, human-friendly booking reference
        do {
            $reference = 'LE-' . random_code(8);
        } while (db_value('SELECT 1 FROM bookings WHERE reference = ?', [$reference]));

        $free = $quote['total'] <= 0;
        db_exec(
            'INSERT INTO bookings (reference, user_id, event_id, status, subtotal, service_fee, total, currency, customer_name, customer_email, customer_phone, payment_method, payment_last4, paid_at)
             VALUES (?, ?, ?, \'confirmed\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($free ? 'NULL' : 'NOW()') . ')',
            [$reference, $user['id'], $eventId, $quote['subtotal'], $quote['fee'], $quote['total'], CURRENCY,
             $customer['name'], $customer['email'], $customer['phone'] ?: null, $free ? 'free' : 'card', $free ? null : $payment['last4']]
        );
        $bookingId = (int) $pdo->lastInsertId();

        foreach ($quote['lines'] as $l) {
            db_exec('INSERT INTO booking_items (booking_id, ticket_type_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)',
                [$bookingId, $l['type']['id'], $l['qty'], $l['type']['price'], $l['line']]);
            $itemId = (int) $pdo->lastInsertId();
            for ($i = 0; $i < $l['qty']; $i++) {
                // 16 chars from a 32-symbol alphabet = 80 bits of randomness; retry on the (astronomically rare) collision
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    try {
                        db_exec('INSERT INTO tickets (booking_item_id, code, attendee_name) VALUES (?, ?, ?)', [$itemId, random_code(16), $customer['name']]);
                        break;
                    } catch (PDOException $e) {
                        if ($e->getCode() !== '23000' || $attempt === 4) {
                            throw $e;
                        }
                    }
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $event = $quote['event'];
    $n = $quote['count'];
    notify((int) $user['id'], 'booking', 'Booking confirmed 🎉',
        "Your booking $reference for {$event['title']} is confirmed. $n " . ($n === 1 ? 'ticket is' : 'tickets are') . ' ready in your wallet.',
        'booking.php?ref=' . $reference);
    notify((int) $event['org_user_id'], 'booking', 'New tickets sold',
        $customer['name'] . " booked $n " . ($n === 1 ? 'ticket' : 'tickets') . " for {$event['title']}.",
        'organizer/attendees.php?event=' . $eventId);
    log_activity('booking.created', "Booked $n " . ($n === 1 ? 'ticket' : 'tickets') . ' for ' . $event['title'], 'booking', $bookingId, (int) $user['id']);

    return db_one('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
}

/** Why a booking can't be cancelled by its owner (null = it can). */
function booking_cancel_blocker(array $booking, array $event): ?string
{
    if ($booking['status'] !== 'confirmed') {
        return 'This booking is already ' . $booking['status'] . '.';
    }
    if (strtotime($event['starts_at']) - time() < CANCELLATION_WINDOW_HOURS * 3600) {
        return 'Bookings can be cancelled up to ' . CANCELLATION_WINDOW_HOURS . ' hours before the event starts.';
    }
    $used = (int) db_value(
        "SELECT COUNT(*) FROM tickets t JOIN booking_items bi ON bi.id = t.booking_item_id WHERE bi.booking_id = ? AND t.status = 'checked_in'",
        [$booking['id']]);
    if ($used > 0) {
        return 'Bookings with checked-in tickets cannot be cancelled.';
    }
    return null;
}

/** Releases a booking's tickets back to inventory. Must run inside a transaction with the booking locked. */
function release_booking(int $bookingId, string $newStatus, string $reason): void
{
    db_exec("UPDATE tickets t JOIN booking_items bi ON bi.id = t.booking_item_id SET t.status = 'cancelled' WHERE bi.booking_id = ? AND t.status = 'valid'", [$bookingId]);
    foreach (db_all('SELECT ticket_type_id, quantity FROM booking_items WHERE booking_id = ?', [$bookingId]) as $it) {
        db_exec('UPDATE ticket_types SET quantity_sold = GREATEST(0, CAST(quantity_sold AS SIGNED) - ?) WHERE id = ?', [$it['quantity'], $it['ticket_type_id']]);
    }
    db_exec('UPDATE bookings SET status = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?', [$newStatus, mb_substr($reason, 0, 255), $bookingId]);
}

/** Attendee (or admin) cancels a booking. */
function cancel_booking(int $bookingId, array $actor): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $booking = db_one('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if (!$booking) {
            throw new BookingException('Booking not found.');
        }
        $isAdmin = $actor['role'] === 'admin';
        if (!$isAdmin && (int) $booking['user_id'] !== (int) $actor['id']) {
            throw new BookingException('Booking not found.');
        }
        $event = db_one('SELECT id, title, starts_at FROM events WHERE id = ?', [$booking['event_id']]);
        if ($isAdmin) {
            if ($booking['status'] !== 'confirmed') {
                throw new BookingException('This booking is already ' . $booking['status'] . '.');
            }
        } elseif ($why = booking_cancel_blocker($booking, $event)) {
            throw new BookingException($why);
        }
        $status = (float) $booking['total'] > 0 ? 'refunded' : 'cancelled';
        release_booking($bookingId, $status, $isAdmin ? 'Cancelled by LemboEvents support' : 'Cancelled by attendee');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    notify((int) $booking['user_id'], 'cancellation', 'Booking cancelled',
        'Your booking ' . $booking['reference'] . ' for ' . $event['title'] . ' was cancelled.' . ((float) $booking['total'] > 0 ? ' A refund of ' . money_exact($booking['total']) . ' has been issued (demo).' : ''),
        'attendee/bookings.php?tab=cancelled');
    log_activity('booking.cancelled', 'Cancelled booking ' . $booking['reference'], 'booking', $bookingId, (int) $actor['id']);
    return $booking;
}

/** Organizer/admin cancels an event: refunds every booking and notifies every attendee. */
function cancel_event(int $eventId, array $actor, string $reason = ''): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $event = db_one('SELECT * FROM events WHERE id = ? FOR UPDATE', [$eventId]);
        if (!$event || $event['status'] === 'cancelled') {
            throw new BookingException('This event is already cancelled.');
        }
        $bookings = db_all("SELECT id, user_id, reference, total FROM bookings WHERE event_id = ? AND status = 'confirmed' FOR UPDATE", [$eventId]);
        foreach ($bookings as $b) {
            release_booking((int) $b['id'], (float) $b['total'] > 0 ? 'refunded' : 'cancelled', 'Event cancelled by organizer');
        }
        db_exec("UPDATE events SET status = 'cancelled', cancelled_at = NOW(), is_featured = 0 WHERE id = ?", [$eventId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    $notified = [];
    foreach ($bookings as $b) {
        if (isset($notified[$b['user_id']])) {
            continue;
        }
        $notified[$b['user_id']] = true;
        notify((int) $b['user_id'], 'event_cancelled', 'Event cancelled: ' . $event['title'],
            'Unfortunately this event has been cancelled' . ($reason !== '' ? ' (' . $reason . ')' : '') . '. Your booking has been refunded in full (demo).',
            'event.php?slug=' . $event['slug']);
    }
    log_activity('event.cancelled', 'Cancelled event ' . $event['title'] . ' (' . count($bookings) . ' bookings refunded)', 'event', $eventId, (int) $actor['id']);
    return count($bookings);
}

/** Validates demo card details (Luhn, expiry, CVC). Returns error message or null. */
function demo_card_error(string $number, string $expiry, string $cvc, string $name): ?string
{
    $digits = preg_replace('/\D/', '', $number);
    if (mb_strlen(trim($name)) < 3) return 'Enter the name on the card.';
    if (strlen($digits) < 13 || strlen($digits) > 19) return 'Enter a valid card number.';
    $sum = 0;
    $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $n = (int) $digits[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    if ($sum % 10 !== 0) return 'That card number is not valid.';
    if (!preg_match('#^(0[1-9]|1[0-2])\s*/\s*(\d{2})$#', trim($expiry), $m)) return 'Enter the expiry as MM/YY.';
    $exp = mktime(0, 0, 0, (int) $m[1] + 1, 1, 2000 + (int) $m[2]);
    if ($exp <= time()) return 'This card has expired.';
    if (!preg_match('/^\d{3,4}$/', $cvc)) return 'Enter a valid CVC.';
    if ($digits === '4000000000000002') return 'Your card was declined (demo decline card). Try 4242 4242 4242 4242.';
    return null;
}

/** Shared ticket query (joins everything a ticket view needs). */
function tickets_query(string $where, array $params, string $order = 'e.starts_at ASC, t.id ASC'): array
{
    return db_all(
        "SELECT t.id, t.code, t.attendee_name, t.status, t.created_at,
                tt.name AS ticket_type, tt.price,
                b.id AS booking_id, b.reference, b.user_id, b.status AS booking_status, b.customer_email,
                e.id AS event_id, e.title AS event_title, e.slug AS event_slug, e.cover_image, e.starts_at, e.ends_at,
                e.venue_name, e.address, e.city, e.status AS event_status, o.user_id AS org_user_id,
                ci.checked_in_at
           FROM tickets t
           JOIN booking_items bi ON bi.id = t.booking_item_id
           JOIN bookings b ON b.id = bi.booking_id
           JOIN ticket_types tt ON tt.id = bi.ticket_type_id
           JOIN events e ON e.id = b.event_id
           JOIN organizer_profiles o ON o.id = e.organizer_id
           LEFT JOIN check_ins ci ON ci.ticket_id = t.id
          WHERE $where ORDER BY $order", $params);
}

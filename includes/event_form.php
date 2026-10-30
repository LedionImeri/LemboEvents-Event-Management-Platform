<?php
/**
 * Create/edit event form handling (shared by organizer/create-event.php and edit-event.php).
 * Validates everything server-side, stores the cover image safely and syncs ticket types.
 */
require_once __DIR__ . '/organizer.php';

/** Normalised form values from POST (or from an existing event for the initial render). */
function event_form_values(?array $event = null): array
{
    if (is_post()) {
        $p = $_POST;
        $tickets = [];
        foreach (array_slice(is_array($p['tickets'] ?? null) ? $p['tickets'] : [], 0, 12) as $t) {
            if (!is_array($t)) continue;
            $tickets[] = [
                'id' => (int) ($t['id'] ?? 0),
                'name' => trim((string) ($t['name'] ?? '')),
                'description' => trim((string) ($t['description'] ?? '')),
                'price' => trim((string) ($t['price'] ?? '0')),
                'quantity' => trim((string) ($t['quantity'] ?? '')),
                'max_per_order' => trim((string) ($t['max_per_order'] ?? '10')),
                'sale_starts_at' => trim((string) ($t['sale_starts_at'] ?? '')),
                'sale_ends_at' => trim((string) ($t['sale_ends_at'] ?? '')),
                'is_active' => !empty($t['is_active']),
                'remove' => !empty($t['remove']),
                'quantity_sold' => 0,
            ];
        }
        return [
            'title' => trim((string) ($p['title'] ?? '')),
            'category_id' => (int) ($p['category_id'] ?? 0),
            'short_description' => trim((string) ($p['short_description'] ?? '')),
            'description' => trim((string) ($p['description'] ?? '')),
            'venue_name' => trim((string) ($p['venue_name'] ?? '')),
            'address' => trim((string) ($p['address'] ?? '')),
            'city' => trim((string) ($p['city'] ?? '')),
            'latitude' => trim((string) ($p['latitude'] ?? '')),
            'longitude' => trim((string) ($p['longitude'] ?? '')),
            'start_date' => trim((string) ($p['start_date'] ?? '')),
            'start_time' => trim((string) ($p['start_time'] ?? '')),
            'end_date' => trim((string) ($p['end_date'] ?? '')),
            'end_time' => trim((string) ($p['end_time'] ?? '')),
            'tickets' => $tickets,
        ];
    }
    if ($event) {
        $tickets = db_all('SELECT * FROM ticket_types WHERE event_id = ? ORDER BY sort_order, id', [$event['id']]);
        foreach ($tickets as &$t) {
            $t['sale_starts_at'] = $t['sale_starts_at'] ? date('Y-m-d\TH:i', strtotime($t['sale_starts_at'])) : '';
            $t['sale_ends_at'] = $t['sale_ends_at'] ? date('Y-m-d\TH:i', strtotime($t['sale_ends_at'])) : '';
            $t['is_active'] = (bool) $t['is_active'];
            $t['remove'] = false;
        }
        unset($t);
        return [
            'title' => $event['title'], 'category_id' => (int) $event['category_id'],
            'short_description' => $event['short_description'], 'description' => $event['description'],
            'venue_name' => $event['venue_name'], 'address' => (string) $event['address'], 'city' => $event['city'],
            'latitude' => (string) $event['latitude'], 'longitude' => (string) $event['longitude'],
            'start_date' => date('Y-m-d', strtotime($event['starts_at'])), 'start_time' => date('H:i', strtotime($event['starts_at'])),
            'end_date' => date('Y-m-d', strtotime($event['ends_at'])), 'end_time' => date('H:i', strtotime($event['ends_at'])),
            'tickets' => $tickets,
        ];
    }
    return [
        'title' => '', 'category_id' => 0, 'short_description' => '', 'description' => '', 'venue_name' => '', 'address' => '',
        'city' => current_organizer()['city'] ?? 'Prishtina', 'latitude' => '', 'longitude' => '',
        'start_date' => date('Y-m-d', strtotime('+14 days')), 'start_time' => '19:00',
        'end_date' => date('Y-m-d', strtotime('+14 days')), 'end_time' => '22:00',
        'tickets' => [
            ['id' => 0, 'name' => 'General Admission', 'description' => '', 'price' => '15', 'quantity' => '200', 'max_per_order' => '10', 'sale_starts_at' => '', 'sale_ends_at' => '', 'is_active' => true, 'remove' => false, 'quantity_sold' => 0],
        ],
    ];
}

/** Validates the form. Returns [errors, cleanData]. */
function event_form_validate(array $v, ?array $event): array
{
    $errors = [];
    $len = fn($s) => mb_strlen((string) $s);
    if ($len($v['title']) < 3 || $len($v['title']) > 150) $errors['title'] = 'Title must be 3–150 characters.';
    if (!db_value('SELECT 1 FROM categories WHERE id = ? AND is_active = 1', [$v['category_id']])) $errors['category_id'] = 'Choose a category.';
    if ($len($v['short_description']) < 10 || $len($v['short_description']) > 255) $errors['short_description'] = 'Summary must be 10–255 characters.';
    if ($len($v['description']) < 30 || $len($v['description']) > 10000) $errors['description'] = 'Description must be 30–10,000 characters.';
    if ($len($v['venue_name']) < 2 || $len($v['venue_name']) > 120) $errors['venue_name'] = 'Enter the venue name.';
    if ($len($v['address']) > 190) $errors['address'] = 'Address is too long.';
    if ($len($v['city']) < 2 || $len($v['city']) > 80) $errors['city'] = 'Enter the city.';
    $lat = $v['latitude'] === '' ? null : (is_numeric($v['latitude']) ? (float) $v['latitude'] : false);
    $lng = $v['longitude'] === '' ? null : (is_numeric($v['longitude']) ? (float) $v['longitude'] : false);
    if ($lat === false || ($lat !== null && abs($lat) > 90) || $lng === false || ($lng !== null && abs($lng) > 180) || (($lat === null) !== ($lng === null))) {
        $errors['latitude'] = 'Enter both coordinates as decimal numbers, or leave both empty.';
    }
    if ($lat === null && isset(city_coordinates()[$v['city']])) {
        [$lat, $lng] = city_coordinates()[$v['city']];
    }

    $start = DateTime::createFromFormat('Y-m-d H:i', $v['start_date'] . ' ' . $v['start_time']);
    $end = DateTime::createFromFormat('Y-m-d H:i', $v['end_date'] . ' ' . $v['end_time']);
    if (!$start) $errors['start'] = 'Enter a valid start date and time.';
    if (!$end) $errors['end'] = 'Enter a valid end date and time.';
    if ($start && $end) {
        if ($end <= $start) $errors['end'] = 'The event must end after it starts.';
        $startChanged = !$event || date('Y-m-d H:i', strtotime($event['starts_at'])) !== $start->format('Y-m-d H:i');
        if ($startChanged && $start->getTimestamp() < time()) $errors['start'] = 'The start time must be in the future.';
        if ($end->getTimestamp() - $start->getTimestamp() > 14 * 86400) $errors['end'] = 'Events can last at most 14 days.';
    }

    $tickets = [];
    $activeCount = 0;
    foreach ($v['tickets'] as $i => $t) {
        $n = $i + 1;
        $existing = $t['id'] ? db_one('SELECT * FROM ticket_types WHERE id = ? AND event_id = ?', [$t['id'], $event['id'] ?? 0]) : null;
        if ($t['id'] && !$existing) {
            continue; // ignore forged ids
        }
        if ($t['remove']) {
            if ($existing) $tickets[] = ['remove' => true, 'existing' => $existing];
            continue;
        }
        if ($t['name'] === '' && $t['quantity'] === '' && !$existing) {
            continue; // empty new row
        }
        if (mb_strlen($t['name']) < 2 || mb_strlen($t['name']) > 80) $errors["ticket_$i"] = "Ticket #$n: enter a name (2–80 characters).";
        elseif (mb_strlen($t['description']) > 255) $errors["ticket_$i"] = "Ticket #$n: description is too long.";
        elseif (!is_numeric($t['price']) || (float) $t['price'] < 0 || (float) $t['price'] > 10000) $errors["ticket_$i"] = "Ticket #$n: price must be between 0 and 10,000.";
        elseif (!ctype_digit($t['quantity']) || (int) $t['quantity'] < 1 || (int) $t['quantity'] > 100000) $errors["ticket_$i"] = "Ticket #$n: quantity must be between 1 and 100,000.";
        elseif ($existing && (int) $t['quantity'] < (int) $existing['quantity_sold']) $errors["ticket_$i"] = "Ticket #$n: {$existing['quantity_sold']} already sold — quantity can't be lower.";
        elseif (!ctype_digit($t['max_per_order']) || (int) $t['max_per_order'] < 1 || (int) $t['max_per_order'] > MAX_TICKETS_PER_ORDER) $errors["ticket_$i"] = "Ticket #$n: max per order must be 1–" . MAX_TICKETS_PER_ORDER . '.';
        $ss = $t['sale_starts_at'] !== '' ? strtotime($t['sale_starts_at']) : null;
        $se = $t['sale_ends_at'] !== '' ? strtotime($t['sale_ends_at']) : null;
        if (($t['sale_starts_at'] !== '' && !$ss) || ($t['sale_ends_at'] !== '' && !$se) || ($ss && $se && $se <= $ss)) {
            $errors["ticket_$i"] = "Ticket #$n: check the sale window dates.";
        }
        if ($t['is_active']) $activeCount++;
        $tickets[] = [
            'remove' => false, 'existing' => $existing, 'name' => $t['name'], 'description' => $t['description'] ?: null,
            'price' => round((float) $t['price'], 2), 'quantity' => (int) $t['quantity'], 'max_per_order' => (int) $t['max_per_order'],
            'sale_starts_at' => $ss ? date('Y-m-d H:i:s', $ss) : null, 'sale_ends_at' => $se ? date('Y-m-d H:i:s', $se) : null,
            'is_active' => $t['is_active'] ? 1 : 0,
        ];
    }
    if ($activeCount === 0) $errors['tickets'] = 'Add at least one active ticket type.';

    return [$errors, [
        'title' => $v['title'], 'category_id' => $v['category_id'], 'short_description' => $v['short_description'],
        'description' => $v['description'], 'venue_name' => $v['venue_name'], 'address' => $v['address'] ?: null, 'city' => $v['city'],
        'latitude' => $lat, 'longitude' => $lng,
        'starts_at' => $start ? $start->format('Y-m-d H:i:00') : null, 'ends_at' => $end ? $end->format('Y-m-d H:i:00') : null,
        'tickets' => $tickets,
    ]];
}

/** Saves the event + ticket types. Returns the event id. */
function event_form_save(array $org, ?array $event, array $data, ?string $cover): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($event) {
            db_exec('UPDATE events SET category_id = ?, title = ?, short_description = ?, description = ?, cover_image = COALESCE(?, cover_image),
                        venue_name = ?, address = ?, city = ?, latitude = ?, longitude = ?, starts_at = ?, ends_at = ? WHERE id = ?',
                [$data['category_id'], $data['title'], $data['short_description'], $data['description'], $cover,
                 $data['venue_name'], $data['address'], $data['city'], $data['latitude'], $data['longitude'], $data['starts_at'], $data['ends_at'], $event['id']]);
            $eventId = (int) $event['id'];
        } else {
            db_exec("INSERT INTO events (organizer_id, category_id, title, slug, short_description, description, cover_image, venue_name, address, city, latitude, longitude, starts_at, ends_at, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')",
                [$org['id'], $data['category_id'], $data['title'], unique_slug('events', $data['title']), $data['short_description'], $data['description'], $cover,
                 $data['venue_name'], $data['address'], $data['city'], $data['latitude'], $data['longitude'], $data['starts_at'], $data['ends_at']]);
            $eventId = (int) $pdo->lastInsertId();
        }

        $order = 1;
        foreach ($data['tickets'] as $t) {
            $ex = $t['existing'];
            if ($t['remove']) {
                // Keep ticket types that have sales (tickets reference them); just deactivate them
                if ((int) $ex['quantity_sold'] > 0 || db_value('SELECT 1 FROM booking_items WHERE ticket_type_id = ? LIMIT 1', [$ex['id']])) {
                    db_exec('UPDATE ticket_types SET is_active = 0 WHERE id = ?', [$ex['id']]);
                } else {
                    db_exec('DELETE FROM ticket_types WHERE id = ?', [$ex['id']]);
                }
                continue;
            }
            if ($ex) {
                db_exec('UPDATE ticket_types SET name = ?, description = ?, price = ?, quantity = ?, max_per_order = ?, sale_starts_at = ?, sale_ends_at = ?, is_active = ?, sort_order = ? WHERE id = ?',
                    [$t['name'], $t['description'], $t['price'], $t['quantity'], $t['max_per_order'], $t['sale_starts_at'], $t['sale_ends_at'], $t['is_active'], $order++, $ex['id']]);
            } else {
                db_exec('INSERT INTO ticket_types (event_id, name, description, price, quantity, max_per_order, sale_starts_at, sale_ends_at, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$eventId, $t['name'], $t['description'], $t['price'], $t['quantity'], $t['max_per_order'], $t['sale_starts_at'], $t['sale_ends_at'], $t['is_active'], $order++]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Tell ticket holders when the important details of a live event change
    if ($event && $event['status'] === 'published') {
        $changed = [];
        if ($event['starts_at'] !== $data['starts_at'] || $event['ends_at'] !== $data['ends_at']) $changed[] = 'date/time';
        if ($event['venue_name'] !== $data['venue_name'] || $event['city'] !== $data['city']) $changed[] = 'venue';
        if ($changed) {
            $users = db_all("SELECT DISTINCT user_id FROM bookings WHERE event_id = ? AND status = 'confirmed'", [$eventId]);
            foreach ($users as $u) {
                notify((int) $u['user_id'], 'event_update', 'Event updated: ' . $data['title'],
                    'The ' . implode(' and ', $changed) . ' changed. New details: ' . fmt_date($data['starts_at'], 'D, M j · H:i') . ' at ' . $data['venue_name'] . ', ' . $data['city'] . '.',
                    'event.php?slug=' . $event['slug']);
            }
        }
    }
    log_activity($event ? 'event.updated' : 'event.created', ($event ? 'Updated' : 'Created') . ' event ' . $data['title'], 'event', $eventId);
    return $eventId;
}

/** Full POST handling. Returns errors (empty on success → caller redirects). */
function handle_event_form(array $org, ?array $event): array
{
    csrf_verify();
    [$errors, $data] = event_form_validate(event_form_values($event), $event);
    $cover = null;
    if (!$errors && !empty($_FILES['cover']['name'])) {
        try {
            $cover = upload_image($_FILES['cover'], 'events', MAX_IMAGE_SIZE);
        } catch (RuntimeException $e) {
            $errors['cover'] = $e->getMessage();
        }
    }
    if ($errors) {
        return $errors;
    }
    $eventId = event_form_save($org, $event, $data, $cover);
    if ($cover && $event) {
        delete_upload($event['cover_image']);
    }
    event_gallery_sync($eventId);
    $fresh = db_one('SELECT * FROM events WHERE id = ?', [$eventId]);
    $msg = $event ? 'Event saved.' : 'Event created as a draft.';
    if (($_POST['intent'] ?? '') === 'publish' && in_array($fresh['status'], ['draft', 'rejected'], true)) {
        try {
            $msg = org_submit_event($fresh, current_organizer());
        } catch (RuntimeException $e) {
            flash('info', $e->getMessage());
        }
    }
    flash('success', $msg);
    redirect('organizer/edit-event.php?id=' . $eventId);
}

/** Gallery: removes ticked images and stores new uploads (max 6 per event, same validation as covers). */
function event_gallery_sync(int $eventId): void
{
    $remove = array_map('intval', is_array($_POST['remove_images'] ?? null) ? $_POST['remove_images'] : []);
    foreach ($remove as $imgId) {
        $img = db_one('SELECT * FROM event_images WHERE id = ? AND event_id = ?', [$imgId, $eventId]);
        if ($img) {
            db_exec('DELETE FROM event_images WHERE id = ?', [$img['id']]);
            delete_upload($img['path']);
        }
    }
    $files = $_FILES['gallery'] ?? null;
    if (!$files || !is_array($files['name'])) {
        return;
    }
    $count = (int) db_value('SELECT COUNT(*) FROM event_images WHERE event_id = ?', [$eventId]);
    $errors = [];
    foreach (array_keys($files['name']) as $i) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($count >= 6) {
            $errors[] = 'Galleries can hold up to 6 images.';
            break;
        }
        try {
            $path = upload_image([
                'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i], 'size' => $files['size'][$i],
            ], 'events', MAX_IMAGE_SIZE);
            db_exec('INSERT INTO event_images (event_id, path, sort_order) VALUES (?, ?, ?)', [$eventId, $path, ++$count]);
        } catch (RuntimeException $e) {
            $errors[] = $files['name'][$i] . ': ' . $e->getMessage();
        }
    }
    if ($errors) {
        flash('error', 'Some gallery images were skipped. ' . implode(' ', array_slice($errors, 0, 2)));
    }
}

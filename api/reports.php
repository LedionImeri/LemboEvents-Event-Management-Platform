<?php
/** POST api/reports.php { target_type: event|review|organizer, target_id, reason, details? } */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
if (!is_post()) {
    json_error('Method not allowed.', 405);
}
csrf_verify();

$d = request_data();
$type = (string) ($d['target_type'] ?? '');
$id = (int) ($d['target_id'] ?? 0);
$reason = (string) ($d['reason'] ?? '');
$details = trim((string) ($d['details'] ?? ''));

if (!in_array($type, ['event', 'review', 'organizer'], true)) json_error('Invalid report target.', 422);
if (!in_array($reason, ['spam', 'inappropriate', 'fraud', 'misleading', 'other'], true)) json_error('Please choose a reason.', 422);
if (mb_strlen($details) > 1000) json_error('Details are too long.', 422);

$table = ['event' => 'events', 'review' => 'reviews', 'organizer' => 'organizer_profiles'][$type];
if (!db_value("SELECT 1 FROM $table WHERE id = ?", [$id])) json_error('Content not found.', 404);

$recent = (int) db_value('SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$user['id']]);
if ($recent >= 10) json_error('You have sent many reports recently. Please try again later.', 429);
if (db_value("SELECT 1 FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status = 'open'", [$user['id'], $type, $id])) {
    json_response(['ok' => true, 'message' => 'You already reported this. Our team is on it.']);
}

db_exec('INSERT INTO reports (reporter_id, target_type, target_id, reason, details) VALUES (?, ?, ?, ?, ?)',
    [$user['id'], $type, $id, $reason, $details !== '' ? $details : null]);
notify_admins('report', 'New report', 'A ' . $type . ' was reported as ' . $reason . '.', 'admin/reports.php');

json_response(['ok' => true, 'message' => 'Thanks for your report. Our team will review it shortly.']);

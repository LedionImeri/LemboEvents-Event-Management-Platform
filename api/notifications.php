<?php
/**
 * GET  api/notifications.php?limit=6     → latest notifications + unread count
 * GET  api/notifications.php?count=1     → unread count only
 * POST api/notifications.php {action: read, id} | {action: read_all} | {action: delete, id}
 * Users can only ever see and change their own notifications.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];

if (!is_post()) {
    if (isset($_GET['count'])) {
        json_response(['ok' => true, 'unread' => unread_notifications($uid)]);
    }
    $limit = max(1, min(30, (int) ($_GET['limit'] ?? 10)));
    $rows = db_all("SELECT id, type, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT $limit", [$uid]);
    $items = array_map(fn($n) => [
        'id' => (int) $n['id'], 'type' => $n['type'], 'title' => $n['title'], 'message' => $n['message'],
        'link' => $n['link'] ? safe_return_path($n['link'], '') : null, 'is_read' => (bool) $n['is_read'], 'time_ago' => time_ago($n['created_at']),
    ], $rows);
    json_response(['ok' => true, 'items' => $items, 'unread' => unread_notifications($uid)]);
}

csrf_verify();
$data = request_data();
$action = (string) ($data['action'] ?? '');
$id = (int) ($data['id'] ?? 0);

switch ($action) {
    case 'read':
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $uid]);
        break;
    case 'read_all':
        db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);
        break;
    case 'delete':
        db_exec('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$id, $uid]);
        break;
    default:
        json_error('Unknown action.', 422);
}
json_response(['ok' => true, 'unread' => unread_notifications($uid)]);

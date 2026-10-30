<?php
/**
 * General helpers: escaping, URLs, flash messages, formatting, notifications,
 * activity logging, uploads and shared event queries.
 */

// =====================================================================
// Output & URLs
// =====================================================================

/** Escape for HTML output. Use for EVERY dynamic value printed into a page. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Turns a stored image value (remote URL or relative upload path) into a usable src. */
function image_url(?string $path, string $fallback = 'images/event-placeholder.svg'): string
{
    if ($path === null || $path === '') {
        return asset($fallback);
    }
    if (preg_match('#^https://#i', $path)) {
        return $path;
    }
    return url($path);
}

/** Smaller variant of Unsplash images for cards (keeps local uploads untouched). */
function image_thumb(?string $path, int $width = 800): string
{
    $src = image_url($path);
    if (str_contains($src, 'images.unsplash.com')) {
        return preg_replace('/([?&])w=\d+/', '$1w=' . $width, $src);
    }
    return $src;
}

function redirect(string $path): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** Only allow redirects back into this application (prevents open redirects). */
function safe_return_path(?string $path, string $default = 'index.php'): string
{
    $path = (string) $path;
    if ($path === '' || preg_match('#^(https?:)?//#i', $path) || str_contains($path, '\\') || preg_match('/[\r\n]/', $path)) {
        return $default;
    }
    $base = BASE_URL . '/';
    if (BASE_URL !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return ltrim($path, '/');
}

function current_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return safe_return_path($uri);
}

function is_api_request(): bool
{
    return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/api/');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

// =====================================================================
// Flash messages & old input
// =====================================================================

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function remember_input(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['current_password'], $data['csrf_token'], $data['card_number'], $data['card_cvc']);
    $_SESSION['old'] = $data;
}

function old(string $key, $default = ''): string
{
    return (string) ($_SESSION['old'][$key] ?? $default);
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

// =====================================================================
// Input helpers
// =====================================================================

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

function str_limit(string $text, int $max): string
{
    return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
}

function slugify(string $text): string
{
    $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $text !== '' ? substr($text, 0, 150) : 'item';
}

/** Unique slug for a table/column, adding -2, -3... when needed. */
function unique_slug(string $table, string $text, ?int $ignoreId = null): string
{
    $allowed = ['events', 'organizer_profiles', 'categories'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Invalid table');
    }
    $base = slugify($text);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM $table WHERE slug = ?" . ($ignoreId ? ' AND id <> ?' : '');
        $params = $ignoreId ? [$slug, $ignoreId] : [$slug];
        if (!db_value($sql, $params)) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

function random_code(int $length, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string
{
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

// =====================================================================
// Formatting
// =====================================================================

function money($amount, bool $freeLabel = true): string
{
    $amount = (float) $amount;
    if ($freeLabel && $amount <= 0) {
        return 'Free';
    }
    $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;
    return CURRENCY_SYMBOL . number_format($amount, $decimals);
}

function money_exact($amount): string
{
    return CURRENCY_SYMBOL . number_format((float) $amount, 2);
}

function fmt_date(?string $dt, string $format = 'M j, Y'): string
{
    return $dt ? date($format, strtotime($dt)) : '';
}

function fmt_time(?string $dt): string
{
    return $dt ? date('H:i', strtotime($dt)) : '';
}

/** "Fri, Oct 30 · 09:00 – 18:00" or a multi-day range. */
function fmt_event_range(string $start, string $end): string
{
    $s = strtotime($start);
    $en = strtotime($end);
    $sameDay = date('Y-m-d', $s) === date('Y-m-d', $en) || ($en - $s < 86400 && (int) date('H', $en) < 8);
    if ($sameDay) {
        return date('D, M j', $s) . ' · ' . date('H:i', $s) . ' – ' . date('H:i', $en);
    }
    return date('M j', $s) . ' – ' . date('M j, Y', $en);
}

function time_ago(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' h ago';
    if ($diff < 86400 * 7) return floor($diff / 86400) . ' d ago';
    return date('M j, Y', strtotime($dt));
}

function time_until(string $dt): string
{
    $diff = strtotime($dt) - time();
    if ($diff <= 0) return 'started';
    if ($diff < 3600) return 'in ' . max(1, (int) floor($diff / 60)) . ' min';
    if ($diff < 86400) return 'in ' . (int) floor($diff / 3600) . ' h';
    $days = (int) floor($diff / 86400);
    return $days === 1 ? 'tomorrow' : "in $days days";
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $s .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $s ?: '?';
}

/** Plain-text description → safe HTML paragraphs and "- " bullet lists. */
function format_description(string $text): string
{
    $html = '';
    foreach (preg_split("/\R{2,}/", trim($text)) as $block) {
        $lines = preg_split("/\R/", trim($block));
        $intro = [];
        $items = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*[-•]\s+(.*)$/u', $line, $m)) {
                $items[] = '<li>' . e($m[1]) . '</li>';
            } else {
                $intro[] = e($line);
            }
        }
        if ($intro) {
            $html .= '<p>' . implode('<br>', $intro) . '</p>';
        }
        if ($items) {
            $html .= '<ul>' . implode('', $items) . '</ul>';
        }
    }
    return $html;
}

function stars_html(float $rating, string $size = ''): string
{
    $out = '<span class="stars ' . e($size) . '" aria-label="' . e(number_format($rating, 1)) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $cls = $rating >= $i - 0.25 ? 'on' : ($rating >= $i - 0.75 ? 'half' : '');
        $out .= '<i class="' . $cls . '">' . icon('star') . '</i>';
    }
    return $out . '</span>';
}

function status_badge(string $status): string
{
    $map = [
        'published' => 'success', 'approved' => 'success', 'confirmed' => 'success', 'active' => 'success', 'valid' => 'success', 'visible' => 'success', 'resolved' => 'success',
        'pending' => 'warning', 'draft' => 'neutral', 'open' => 'warning', 'checked_in' => 'info',
        'rejected' => 'danger', 'cancelled' => 'danger', 'suspended' => 'danger', 'refunded' => 'neutral', 'hidden' => 'neutral', 'dismissed' => 'neutral',
    ];
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge badge-' . ($map[$status] ?? 'neutral') . '">' . e($label) . '</span>';
}

// =====================================================================
// JSON responses (API)
// =====================================================================

function json_response(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400, array $extra = []): never
{
    json_response(['ok' => false, 'error' => $message] + $extra, $status);
}

/** Reads a JSON request body (falls back to $_POST for form-encoded requests). */
function request_data(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== false) {
        $raw = file_get_contents('php://input', false, null, 0, 65536);
        $decoded = json_decode((string) $raw, true);
        $data = is_array($decoded) ? $decoded : [];
    } else {
        $data = $_POST;
    }
    return $data;
}

// =====================================================================
// Notifications & activity log
// =====================================================================

function notify(int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    db_exec('INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)', [
        $userId, $type, mb_substr($title, 0, 150), mb_substr($message, 0, 500), $link,
    ]);
}

function notify_admins(string $type, string $title, string $message, ?string $link = null): void
{
    foreach (db_all("SELECT id FROM users WHERE role = 'admin' AND status = 'active'") as $a) {
        notify((int) $a['id'], $type, $title, $message, $link);
    }
}

function unread_notifications(int $userId): int
{
    return (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
}

function log_activity(string $action, string $description, ?string $entityType = null, ?int $entityId = null, ?int $userId = null): void
{
    $userId ??= current_user_id();
    db_exec('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)', [
        $userId, $action, $entityType, $entityId, mb_substr($description, 0, 255), client_ip(),
    ]);
}

/**
 * Creates "event tomorrow" reminders lazily (no cron needed): runs at most once
 * per hour per session for the logged-in attendee.
 */
function ensure_event_reminders(int $userId): void
{
    $key = 'reminders_checked_at';
    if (isset($_SESSION[$key]) && time() - (int) $_SESSION[$key] < 3600) {
        return;
    }
    $_SESSION[$key] = time();
    $rows = db_all(
        "SELECT DISTINCT e.id, e.title, e.slug, e.starts_at, e.venue_name
           FROM bookings b JOIN events e ON e.id = b.event_id
          WHERE b.user_id = ? AND b.status = 'confirmed' AND e.status = 'published'
            AND e.starts_at BETWEEN NOW() AND NOW() + INTERVAL 48 HOUR",
        [$userId]
    );
    foreach ($rows as $ev) {
        $link = 'ticket.php?event=' . $ev['id'];
        $exists = db_value("SELECT id FROM notifications WHERE user_id = ? AND type = 'reminder' AND link = ?", [$userId, $link]);
        if (!$exists) {
            notify($userId, 'reminder', 'Reminder: ' . $ev['title'],
                'Starts ' . time_until($ev['starts_at']) . ' at ' . $ev['venue_name'] . ' (' . fmt_date($ev['starts_at'], 'D, M j · H:i') . '). Have your QR ticket ready.',
                $link);
        }
    }
}

// =====================================================================
// Uploads
// =====================================================================

/**
 * Validates and stores an uploaded image. Checks the upload error, size, real MIME type
 * (finfo, not the browser-supplied type), image dimensions, and stores it under a random
 * name inside assets/uploads/{folder}. Returns the relative path or throws RuntimeException.
 */
function upload_image(array $file, string $folder, int $maxBytes): string
{
    if (!in_array($folder, ['events', 'logos', 'avatars'], true)) {
        throw new RuntimeException('Invalid upload folder.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE => 'The file is too large.', UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.', UPLOAD_ERR_NO_FILE => 'Please choose a file.',
        ];
        throw new RuntimeException($map[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload failed.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('The image must be smaller than ' . round($maxBytes / 1048576) . ' MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(ALLOWED_IMAGE_TYPES[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || $info[0] < 100 || $info[1] < 100 || $info[0] > 8000 || $info[1] > 8000) {
        throw new RuntimeException('The file is not a valid image (minimum 100×100 px).');
    }
    $dir = UPLOAD_DIR . '/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Upload folder is not writable.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_IMAGE_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the image.');
    }
    return UPLOAD_URL_PREFIX . '/' . $folder . '/' . $name;
}

/** Deletes a previously uploaded local file (never touches remote URLs or paths outside uploads). */
function delete_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, UPLOAD_URL_PREFIX . '/')) {
        return;
    }
    $full = realpath(ROOT_PATH . '/' . $path);
    $uploads = realpath(UPLOAD_DIR);
    if ($full && $uploads && str_starts_with($full, $uploads) && is_file($full)) {
        @unlink($full);
    }
}

// =====================================================================
// Events
// =====================================================================

/** Columns used by event cards everywhere (min price, availability, rating). */
function event_card_select(): string
{
    return "e.id, e.title, e.slug, e.short_description, e.cover_image, e.city, e.venue_name, e.starts_at, e.ends_at,
            e.status, e.is_featured, e.created_at,
            c.name AS category_name, c.slug AS category_slug, c.color AS category_color,
            o.organization_name,
            " . min_price_sql() . " AS min_price,
            (SELECT COALESCE(SUM(tt.quantity - tt.quantity_sold), 0) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.is_active = 1) AS tickets_left,
            (SELECT COALESCE(SUM(tt.quantity_sold), 0) FROM ticket_types tt WHERE tt.event_id = e.id) AS tickets_sold";
}

/**
 * "Starting from" price: the cheapest ticket that can actually be bought right now
 * (active, not sold out, sale window open). Falls back to the cheapest active ticket
 * so sold-out events still show a price.
 */
function min_price_sql(): string
{
    return "COALESCE(
        (SELECT MIN(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.is_active = 1 AND tt.quantity_sold < tt.quantity
            AND (tt.sale_starts_at IS NULL OR tt.sale_starts_at <= NOW()) AND (tt.sale_ends_at IS NULL OR tt.sale_ends_at > NOW())),
        (SELECT MIN(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.is_active = 1))";
}

function event_card_from(): string
{
    return "FROM events e
            JOIN categories c ON c.id = e.category_id
            JOIN organizer_profiles o ON o.id = e.organizer_id";
}

/** Public visibility rule: published, organizer approved, not ended. */
function public_event_where(bool $includePast = false): string
{
    return "e.status = 'published' AND o.status = 'approved'" . ($includePast ? '' : ' AND e.ends_at >= NOW()');
}

function fetch_event_cards(string $where, array $params = [], string $order = 'e.starts_at ASC', int $limit = 8): array
{
    $limit = max(1, min(100, $limit));
    return db_all('SELECT ' . event_card_select() . ' ' . event_card_from() . " WHERE $where ORDER BY $order LIMIT $limit", $params);
}

/** IDs of events the current user saved, for rendering filled hearts. */
function user_favorite_ids(): array
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $uid = current_user_id();
    $ids = $uid ? array_map('intval', array_column(db_all('SELECT event_id FROM favorites WHERE user_id = ?', [$uid]), 'event_id')) : [];
    return $ids;
}

function render_event_card(array $ev, string $variant = ''): string
{
    ob_start();
    include __DIR__ . '/partials/event_card.php';
    return (string) ob_get_clean();
}

/** Known city coordinates, used for map fallback and "events near you". */
function city_coordinates(): array
{
    return [
        'Prishtina' => [42.6629, 21.1655], 'Prizren' => [42.2139, 20.7397], 'Peja' => [42.6593, 20.2887],
        'Gjakova' => [42.3803, 20.4308], 'Ferizaj' => [42.3702, 21.1553], 'Mitrovica' => [42.8914, 20.8660],
        'Gjilan' => [42.4635, 21.4694], 'Vushtrri' => [42.8231, 20.9675], 'Podujeva' => [42.9106, 21.1931],
        'Tirana' => [41.3275, 19.8187], 'Skopje' => [41.9981, 21.4254],
    ];
}

/** Ticket sale state for a ticket type row. */
function ticket_type_state(array $tt): string
{
    $now = time();
    if (!(int) $tt['is_active']) return 'inactive';
    if ((int) $tt['quantity'] - (int) $tt['quantity_sold'] <= 0) return 'sold_out';
    if (!empty($tt['sale_starts_at']) && strtotime($tt['sale_starts_at']) > $now) return 'not_started';
    if (!empty($tt['sale_ends_at']) && strtotime($tt['sale_ends_at']) < $now) return 'ended';
    return 'on_sale';
}

function service_fee(float $subtotal): float
{
    return $subtotal > 0 ? round($subtotal * SERVICE_FEE_RATE, 2) : 0.0;
}

// =====================================================================
// Pagination
// =====================================================================

function paginate(int $total, int $perPage = 20): array
{
    $page = max(1, input_int('page', 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    return ['page' => $page, 'pages' => $pages, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $q = $_GET;
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 9 && $i > 2 && $i < $p['pages'] - 1 && abs($i - $p['page']) > 1) {
            if ($i === 3 || $i === $p['pages'] - 2) {
                $html .= '<span class="gap">…</span>';
            }
            continue;
        }
        $q['page'] = $i;
        $html .= '<a href="?' . e(http_build_query($q)) . '"' . ($i === $p['page'] ? ' class="active" aria-current="page"' : '') . '>' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** "ABCD-EFGH-JKLM-NPQR" for display. */
function format_ticket_code(string $code): string
{
    return implode('-', str_split($code, 4));
}

/** Escapes a CSV cell and neutralises spreadsheet formula injection. */
function csv_cell($v): string
{
    $v = (string) $v;
    if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) {
        $v = "'" . $v;
    }
    return '"' . str_replace('"', '""', $v) . '"';
}

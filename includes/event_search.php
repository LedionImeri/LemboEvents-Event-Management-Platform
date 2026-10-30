<?php
/**
 * Event search shared by events.php (server render) and api/events.php (AJAX).
 * Every filter is whitelisted or bound as a parameter — no user input reaches SQL directly.
 */

function event_search_params(array $src): array
{
    $str = fn(string $k, int $max = 80) => is_string($src[$k] ?? null) ? mb_substr(trim($src[$k]), 0, $max) : '';
    $date = $str('date', 20);
    $price = $str('price', 10);
    $sort = $str('sort', 20);
    $from = $str('from', 10);
    $to = $str('to', 10);
    $isDate = fn(string $d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    $num = fn(string $k) => isset($src[$k]) && is_numeric($src[$k]) && (float) $src[$k] >= 0 ? (float) $src[$k] : null;

    return [
        'q'        => $str('q'),
        'category' => preg_replace('/[^a-z0-9-]/', '', strtolower($str('category'))),
        'city'     => $str('city', 60),
        'date'     => in_array($date, ['today', 'tomorrow', 'weekend', 'week', 'month'], true) ? $date : '',
        'from'     => $isDate($from) ? $from : '',
        'to'       => $isDate($to) ? $to : '',
        'price'    => in_array($price, ['free', 'paid'], true) ? $price : '',
        'min'      => $num('min'),
        'max'      => $num('max'),
        'sort'     => in_array($sort, ['date', 'popular', 'newest', 'price_asc', 'price_desc'], true) ? $sort : 'date',
        'page'     => max(1, min(200, (int) ($src['page'] ?? 1))),
        'near'     => (isset($src['lat'], $src['lng']) && is_numeric($src['lat']) && is_numeric($src['lng'])
                        && abs((float) $src['lat']) <= 90 && abs((float) $src['lng']) <= 180)
                        ? [(float) $src['lat'], (float) $src['lng']] : null,
    ];
}

function event_search(array $p, int $perPage = EVENTS_PER_PAGE): array
{
    $where = [public_event_where()];
    $params = [];

    if ($p['q'] !== '') {
        $where[] = '(e.title LIKE ? OR e.short_description LIKE ? OR e.venue_name LIKE ? OR e.city LIKE ? OR o.organization_name LIKE ? OR c.name LIKE ?)';
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $p['q']) . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    if ($p['category'] !== '') {
        $where[] = 'c.slug = ?';
        $params[] = $p['category'];
    }
    if ($p['city'] !== '') {
        $where[] = 'e.city = ?';
        $params[] = $p['city'];
    }
    switch ($p['date']) {
        case 'today':
            $where[] = 'DATE(e.starts_at) <= CURDATE() AND DATE(e.ends_at) >= CURDATE()';
            break;
        case 'tomorrow':
            $where[] = 'DATE(e.starts_at) <= CURDATE() + INTERVAL 1 DAY AND DATE(e.ends_at) >= CURDATE() + INTERVAL 1 DAY';
            break;
        case 'weekend':
            // Upcoming Friday → Sunday (or the current one if we are already in it)
            $dow = (int) date('N');
            $fri = $dow >= 5 ? date('Y-m-d', strtotime('-' . ($dow - 5) . ' days')) : date('Y-m-d', strtotime('next friday'));
            $sun = date('Y-m-d', strtotime($fri . ' +2 days'));
            $where[] = 'DATE(e.starts_at) <= ? AND DATE(e.ends_at) >= ?';
            array_push($params, $sun, $fri);
            break;
        case 'week':
            $where[] = 'e.starts_at < NOW() + INTERVAL 7 DAY';
            break;
        case 'month':
            $where[] = 'e.starts_at < NOW() + INTERVAL 30 DAY';
            break;
    }
    if ($p['from'] !== '') {
        $where[] = 'DATE(e.ends_at) >= ?';
        $params[] = $p['from'];
    }
    if ($p['to'] !== '') {
        $where[] = 'DATE(e.starts_at) <= ?';
        $params[] = $p['to'];
    }
    $minPrice = min_price_sql();
    if ($p['price'] === 'free') {
        $where[] = "$minPrice = 0";
    } elseif ($p['price'] === 'paid') {
        $where[] = "$minPrice > 0";
    }
    if ($p['min'] !== null) {
        $where[] = "$minPrice >= ?";
        $params[] = $p['min'];
    }
    if ($p['max'] !== null) {
        $where[] = "$minPrice <= ?";
        $params[] = $p['max'];
    }

    $whereSql = implode(' AND ', $where);
    $total = (int) db_value('SELECT COUNT(*) ' . event_card_from() . " WHERE $whereSql", $params);

    $select = event_card_select();
    $order = match ($p['sort']) {
        'popular'    => 'tickets_sold DESC, e.views DESC',
        'newest'     => 'e.created_at DESC',
        'price_asc'  => 'min_price IS NULL, min_price ASC, e.starts_at ASC',
        'price_desc' => 'min_price DESC, e.starts_at ASC',
        default      => 'e.starts_at ASC',
    };
    $selectParams = [];
    if ($p['near']) {
        // Haversine distance in km (parameters bound, not interpolated)
        $select .= ', (6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(e.latitude)) * COS(RADIANS(e.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(e.latitude))))) AS distance_km';
        $selectParams = [$p['near'][0], $p['near'][1], $p['near'][0]];
        $order = 'distance_km IS NULL, distance_km ASC, e.starts_at ASC';
    }
    $offset = ($p['page'] - 1) * $perPage;
    $rows = db_all("SELECT $select " . event_card_from() . " WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset", array_merge($selectParams, $params));

    return ['items' => $rows, 'total' => $total, 'page' => $p['page'], 'pages' => max(1, (int) ceil($total / $perPage))];
}

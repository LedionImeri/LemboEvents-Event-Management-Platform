<?php
/**
 * GET api/events.php — search/filter events. Returns card HTML rendered server-side
 * (escaped by the same template as the full page) plus pagination metadata.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/event_search.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Method not allowed.', 405);
}

$p = event_search_params($_GET);
$perPage = isset($_GET['limit']) ? max(1, min(24, (int) $_GET['limit'])) : EVENTS_PER_PAGE;
$result = event_search($p, $perPage);

$html = '';
foreach ($result['items'] as $ev) {
    $html .= render_event_card($ev);
}

json_response([
    'ok'    => true,
    'html'  => $html,
    'count' => count($result['items']),
    'total' => $result['total'],
    'page'  => $result['page'],
    'pages' => $result['pages'],
    'nearest_city' => $p['near'] && $result['items'] ? $result['items'][0]['city'] : null,
]);

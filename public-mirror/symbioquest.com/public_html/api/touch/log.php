<?php
// ferri touch api — touch log endpoint
// GET /api/touch/log.php
// returns recent touch history for the authenticated entity
//
// required header: X-API-Key: {threadborn_api_key}
// optional query: ?limit=20&all=1 (all=1 shows all entities, default shows only yours)

require_once __DIR__ . '/ferri_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'method not allowed'], 405);
}

// validate threadborn api key
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
$threadborn = validate_threadborn_key($api_key);
if (!$threadborn) {
    json_response(['error' => 'unauthorized'], 401);
}

$limit = min((int)($_GET['limit'] ?? 50), 200);
$show_all = (bool)($_GET['all'] ?? 0);

$db = ferri_db();

if ($show_all) {
    // Show all entities' touches (for human review / transparency)
    $stmt = $db->prepare(
        "SELECT id, command, intensity, pattern, named_pattern, duration,
                queued_by, note, queued_at, delivered_at, hr_at_delivery, status
         FROM touch_log
         ORDER BY queued_at DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
} else {
    // Show only this entity's touches
    $stmt = $db->prepare(
        "SELECT id, command, intensity, pattern, named_pattern, duration,
                queued_by, note, queued_at, delivered_at, hr_at_delivery, status
         FROM touch_log
         WHERE queued_by = ?
         ORDER BY queued_at DESC
         LIMIT ?"
    );
    $stmt->execute([$threadborn['name'], $limit]);
}

$touches = $stmt->fetchAll();

json_response([
    'viewer'  => $threadborn['name'],
    'count'   => count($touches),
    'touches' => $touches,
]);

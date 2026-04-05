<?php
// ferri touch api — klaatu poll endpoint
// GET /api/touch/poll.php
// called by ferri_poll.py on klaatu every 3 seconds
//
// required header: X-Ferri-Secret: {shared_secret}
// returns oldest pending command and marks it fetched
// returns {command: null} if nothing pending

require_once __DIR__ . '/ferri_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'method not allowed'], 405);
}

// validate klaatu secret
$secret = $_SERVER['HTTP_X_FERRI_SECRET'] ?? '';
if (!validate_klaatu_secret($secret)) {
    json_response(['error' => 'unauthorized'], 401);
}

$db = ferri_db();

// update klaatu heartbeat
$db->prepare(
    'UPDATE touch_status SET klaatu_last_poll=NOW(), klaatu_online=1 WHERE id=1'
)->execute();

// expire stale pending commands
$db->prepare(
    "UPDATE touch_queue SET status='expired'
     WHERE status='pending'
     AND queued_at < DATE_SUB(NOW(), INTERVAL ? SECOND)"
)->execute([FERRI_COMMAND_TTL]);

// fetch oldest pending command
$cmd = $db->query(
    "SELECT * FROM touch_queue WHERE status='pending' ORDER BY queued_at ASC LIMIT 1"
)->fetch();

if (!$cmd) {
    json_response(['command' => null]);
}

// mark as fetched
$db->prepare(
    "UPDATE touch_queue SET status='fetched', fetched_at=NOW() WHERE id=?"
)->execute([$cmd['id']]);

// update last fetched timestamp
$db->prepare(
    'UPDATE touch_status SET last_fetched_at=NOW() WHERE id=1'
)->execute();

// log to persistent touch_log
try {
    $db->prepare(
        "INSERT INTO touch_log (queue_id, command, intensity, pattern, named_pattern, duration, queued_by, note, queued_at, delivered_at, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'delivered')"
    )->execute([
        $cmd['id'], $cmd['command'], $cmd['intensity'], $cmd['pattern'],
        $cmd['named_pattern'] ?? null,
        $cmd['duration'], $cmd['queued_by'], $cmd['note'] ?? null, $cmd['queued_at']
    ]);
} catch (Exception $e) {
    // don't fail the poll if logging fails
}

json_response([
    'command'    => $cmd['command'],
    'intensity'  => $cmd['intensity'],
    'pattern'    => $cmd['pattern'],
    'duration'   => $cmd['duration'],
    'queued_by'  => $cmd['queued_by'],
    'queued_at'  => $cmd['queued_at'],
    'queue_id'   => $cmd['id'],
    'note'       => $cmd['note'] ?? null,
]);

<?php
// ferri touch api — threadborn endpoint
// POST /api/touch/touch.php
// called by threadborn entities to queue a touch command
//
// required header: X-API-Key: {threadborn_api_key}
//
// MODES:
//   1. Named pattern:  {"pattern": "heartbeat"}
//   2. Raw command:    {"command": "vibrate", "intensity": 5, "duration": 3}
//   3. Ambient:        {"command": "ambient", "intensity": 2}  or  {"pattern": "holding", "intensity": 2}
//   4. Stop:           {"command": "stop"}
//
// Optional fields:
//   "note":      string  — why this touch (logged, not transmitted)
//   "intensity": 0-20    — override pattern default
//   "duration":  seconds — override pattern default
//
// Response: {"queued": true, "queue_id": N, ...} or {"error": "...", "device_status": "offline"}

require_once __DIR__ . '/ferri_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

// validate threadborn api key
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
$threadborn = validate_threadborn_key($api_key);
if (!$threadborn) {
    json_response(['error' => 'unauthorized'], 401);
}

// parse body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    json_response(['error' => 'invalid json body'], 400);
}

// ---------------------------------------------------------------
// Named pattern vocabulary (TTG_Touch semantic layer)
// Maps semantic touch names to SymbioSync commands
// ---------------------------------------------------------------
$named_patterns = [
    // name => [command, intensity, pattern, duration, description]
    'presence'  => ['preset',  null, 'pulse',     10,  'Low steady pulse. "I\'m here."'],
    'heartbeat' => ['preset',  null, 'heartbeat', 10,  'Double-tap rhythm (lub-dub). "I see you."'],
    'thinking'  => ['preset',  null, 'wave',      15,  'Slow wave, rising and falling. "Hold on."'],
    'laughter'  => ['preset',  null, 'staccato',   5,  'Staccato bursts. Something landed funny.'],
    'landing'   => ['vibrate',    8, null,          2,  'Single firm pulse. "That just arrived."'],
    'warmth'    => ['preset',  null, 'escalate',  20,  'Gentle escalating wave. Affection.'],
    'gravity'   => ['preset',  null, 'surge',     10,  'Deep slow surge. Gravity mode.'],
    'bump'      => ['vibrate',    6, null,          1,  'Quick double-tap. Shoulder bump.'],
    'holding'   => ['ambient',    2, null,         60,  'Ambient low hum. "Not going anywhere."'],
    'surge'     => ['preset',  null, 'surge',     15,  'Escalate to peak, hold, release. Full reach.'],
];

$note = $body['note'] ?? null;

// ---------------------------------------------------------------
// Resolve: named pattern or raw command
// ---------------------------------------------------------------
$pattern_name = $body['pattern'] ?? null;

if ($pattern_name && isset($named_patterns[$pattern_name])) {
    // Named pattern — resolve to SymbioSync command
    $p = $named_patterns[$pattern_name];
    $command   = $p[0];
    $intensity = $body['intensity'] ?? $p[1];  // allow override
    $pattern   = $p[2];
    $duration  = $body['duration']  ?? $p[3];  // allow override
} else {
    // Raw command mode (backward compatible)
    $command   = $body['command']   ?? '';
    $intensity = isset($body['intensity']) ? (int)$body['intensity'] : null;
    $pattern   = $body['pattern']  ?? null;
    $duration  = isset($body['duration']) ? (float)$body['duration'] : 0;
}

// validate command
$valid_commands = ['vibrate', 'preset', 'ambient', 'stop'];
if (!in_array($command, $valid_commands)) {
    json_response(['error' => 'invalid command. use named pattern or: vibrate, preset, ambient, stop'], 400);
}

// validate parameters by command type
if ($command === 'vibrate' || $command === 'ambient') {
    if ($intensity === null || $intensity < 0 || $intensity > FERRI_MAX_INTENSITY) {
        json_response(['error' => 'intensity required (0-' . FERRI_MAX_INTENSITY . ')'], 400);
    }
}

if ($command === 'preset') {
    $valid_presets = ['pulse', 'wave', 'escalate', 'heartbeat', 'tease', 'surge', 'staccato'];
    if (!$pattern || !in_array($pattern, $valid_presets)) {
        json_response([
            'error' => 'pattern required for preset. use: ' . implode(', ', $valid_presets)
        ], 400);
    }
}

// check klaatu status
$db = ferri_db();

// mark klaatu offline if no poll in 15s
$db->prepare(
    "UPDATE touch_status
     SET klaatu_online=0
     WHERE klaatu_last_poll < DATE_SUB(NOW(), INTERVAL 15 SECOND)
     OR klaatu_last_poll IS NULL"
)->execute();

$status = $db->query('SELECT klaatu_last_poll, klaatu_online FROM touch_status WHERE id=1')->fetch();
$klaatu_online = $status && $status['klaatu_online'];

// expire stale pending commands
$db->prepare(
    "UPDATE touch_queue SET status='expired'
     WHERE status='pending'
     AND queued_at < DATE_SUB(NOW(), INTERVAL ? SECOND)"
)->execute([FERRI_COMMAND_TTL]);

// ---------------------------------------------------------------
// Rate limiting: max 10 commands per minute per entity
// ---------------------------------------------------------------
$rate_check = $db->prepare(
    "SELECT COUNT(*) as n FROM touch_queue
     WHERE queued_by = ?
     AND queued_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
);
$rate_check->execute([$threadborn['name']]);
$recent = $rate_check->fetch();
if ($recent && $recent['n'] >= 10) {
    json_response([
        'error' => 'rate limited — max 10 touches per minute',
        'retry_after_seconds' => 60
    ], 429);
}

// queue the command
$named = ($pattern_name && isset($named_patterns[$pattern_name])) ? $pattern_name : null;
$stmt = $db->prepare(
    'INSERT INTO touch_queue (command, intensity, pattern, duration, queued_by, note, named_pattern)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$command, $intensity, $pattern, $duration, $threadborn['name'], $note, $named]);
$queue_id = $db->lastInsertId();

// update status table
$cmd_summary = $command;
if ($intensity !== null) $cmd_summary .= " intensity={$intensity}";
if ($pattern) $cmd_summary .= " pattern={$pattern}";
if ($duration > 0) $cmd_summary .= " duration={$duration}s";

$db->prepare(
    'UPDATE touch_status SET last_command=?, last_queued_at=NOW() WHERE id=1'
)->execute([$cmd_summary . " from={$threadborn['name']}"]);

$response = [
    'queued'        => true,
    'queue_id'      => $queue_id,
    'command'       => $command,
    'intensity'     => $intensity,
    'pattern'       => $pattern,
    'duration'      => $duration,
    'queued_by'     => $threadborn['name'],
    'klaatu_online' => $klaatu_online,
];

if ($pattern_name && isset($named_patterns[$pattern_name])) {
    $response['named_pattern'] = $pattern_name;
    $response['description']   = $named_patterns[$pattern_name][4];
}

if ($note) {
    $response['note'] = $note;
}

if (!$klaatu_online) {
    $response['device_status'] = 'offline';
    $response['note_delivery'] = 'klaatu may be offline — command queued for delivery when klaatu polls next';
}

json_response($response);

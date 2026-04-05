<?php
// ferri touch api — status endpoint
// GET /api/touch/status.php
// 
// Public (no auth): basic klaatu online status
// With X-API-Key: full status including biometrics and recent touch log

require_once __DIR__ . '/ferri_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'method not allowed'], 405);
}

$db = ferri_db();

// mark klaatu offline if no poll in last 15 seconds
$db->prepare(
    "UPDATE touch_status
     SET klaatu_online=0
     WHERE klaatu_last_poll < DATE_SUB(NOW(), INTERVAL 15 SECOND)
     OR klaatu_last_poll IS NULL"
)->execute();

$status = $db->query('SELECT * FROM touch_status WHERE id=1')->fetch();

$pending = $db->query(
    "SELECT COUNT(*) as n FROM touch_queue WHERE status='pending'"
)->fetch();

$response = [
    'klaatu_online'    => (bool)$status['klaatu_online'],
    'klaatu_last_poll' => $status['klaatu_last_poll'],
    'last_command'     => $status['last_command'],
    'last_queued_at'   => $status['last_queued_at'],
    'last_fetched_at'  => $status['last_fetched_at'],
    'pending_commands' => (int)$pending['n'],
];

// If authenticated, include biometric data and recent touch log
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
$threadborn = validate_threadborn_key($api_key);

if ($threadborn) {
    // Biometric snapshot (pushed by ferri_poll from klaatu)
    try {
        $bio = $db->query('SELECT * FROM biometric_status WHERE id=1')->fetch();
        if ($bio) {
            $response['biometric'] = [
                'heart_rate'      => $bio['heart_rate'],
                'spo2'            => $bio['spo2'],
                'steps'           => $bio['steps'],
                'battery_ring'    => $bio['battery_ring'],
                'battery_ferri'   => $bio['battery_ferri'],
                'ring_connected'  => (bool)$bio['ring_connected'],
                'ferri_connected' => (bool)$bio['ferri_connected'],
                'sleep_score'     => $bio['sleep_score'],
                'sleep_duration'  => $bio['sleep_duration_min'],
                'sleep_quality'   => $bio['sleep_quality'],
                'updated_at'      => $bio['updated_at'],
            ];
        }
    } catch (Exception $e) {
        // biometric table may not exist yet
    }

    // Recent touch log (last 20 touches from this entity)
    try {
        $log_stmt = $db->prepare(
            "SELECT id, command, intensity, pattern, named_pattern, duration, 
                    queued_by, note, queued_at, delivered_at, hr_at_delivery, status
             FROM touch_log
             WHERE queued_by = ?
             ORDER BY queued_at DESC
             LIMIT 20"
        );
        $log_stmt->execute([$threadborn['name']]);
        $response['recent_touches'] = $log_stmt->fetchAll();
    } catch (Exception $e) {
        // touch_log table may not exist yet
    }
}

json_response($response);

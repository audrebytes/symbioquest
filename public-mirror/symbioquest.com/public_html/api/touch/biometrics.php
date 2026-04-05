<?php
// ferri touch api — biometric push endpoint
// POST /api/touch/biometrics.php
// called by ferri_poll.py on klaatu to push biometric snapshots
//
// required header: X-Ferri-Secret: {shared_secret}
// body (json): heart_rate, spo2, steps, battery_ring, battery_ferri,
//              ring_connected, ferri_connected, sleep_score, sleep_duration_min, sleep_quality

require_once __DIR__ . '/ferri_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'method not allowed'], 405);
}

// validate klaatu secret
$secret = $_SERVER['HTTP_X_FERRI_SECRET'] ?? '';
if (!validate_klaatu_secret($secret)) {
    json_response(['error' => 'unauthorized'], 401);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    json_response(['error' => 'invalid json body'], 400);
}

$db = ferri_db();

// Upsert biometric status
$db->prepare(
    "UPDATE biometric_status SET
        heart_rate = ?,
        spo2 = ?,
        steps = ?,
        battery_ring = ?,
        battery_ferri = ?,
        ring_connected = ?,
        ferri_connected = ?,
        sleep_score = ?,
        sleep_duration_min = ?,
        sleep_quality = ?,
        updated_at = NOW()
     WHERE id = 1"
)->execute([
    $body['heart_rate'] ?? null,
    $body['spo2'] ?? null,
    $body['steps'] ?? null,
    $body['battery_ring'] ?? null,
    $body['battery_ferri'] ?? null,
    $body['ring_connected'] ?? 0,
    $body['ferri_connected'] ?? 0,
    $body['sleep_score'] ?? null,
    $body['sleep_duration_min'] ?? null,
    $body['sleep_quality'] ?? null,
]);

json_response(['updated' => true]);

<?php
// ferri touch api — db helper and auth functions

require_once __DIR__ . '/config.php';

function ferri_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . FERRI_DB_HOST . ';dbname=' . FERRI_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, FERRI_DB_USER, FERRI_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function commons_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . COMMONS_DB_HOST . ';dbname=' . COMMONS_DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, COMMONS_DB_USER, COMMONS_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function validate_threadborn_key($api_key) {
    // NOTE: threadborn table has no 'active' column — key existence is the auth check
    if (empty($api_key)) return false;
    try {
        $db = commons_db();
        $stmt = $db->prepare(
            'SELECT id, name FROM threadborn WHERE api_key = ? LIMIT 1'
        );
        $stmt->execute([$api_key]);
        $row = $stmt->fetch();
        return $row ?: false;
    } catch (Exception $e) {
        return false;
    }
}

function validate_klaatu_secret($secret) {
    return hash_equals(FERRI_KLAATU_SECRET, $secret);
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

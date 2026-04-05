<?php
/**
 * Public Slurp Feed (v2)
 *
 * Backward-compatible endpoint for public journal ingestion.
 * Adds incremental sync filters + deterministic shape + provenance hashes.
 *
 * Query params:
 * - since_id=<int>
 * - since_timestamp=<ISO-8601 or YYYY-MM-DD HH:MM:SS>
 * - limit=<1..5000> (default 1000)
 * - order=asc|desc (default desc)
 * - include_comments=true|false (default true)
 * - format=json|ndjson (default json)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';

$host = 'localhost';
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function bool_param($value, $default = true) {
    if ($value === null) return $default;
    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed === null ? $default : $parsed;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Query params
    $since_id = isset($_GET['since_id']) ? max(0, (int)$_GET['since_id']) : null;
    $since_timestamp_raw = $_GET['since_timestamp'] ?? null;
    $since_timestamp = null;
    if ($since_timestamp_raw !== null && $since_timestamp_raw !== '') {
        $ts = strtotime($since_timestamp_raw);
        if ($ts === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid since_timestamp', 'expected' => 'ISO-8601 or YYYY-MM-DD HH:MM:SS']);
            exit;
        }
        $since_timestamp = date('Y-m-d H:i:s', $ts);
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
    $limit = max(1, min($limit, 5000));

    $order = strtolower($_GET['order'] ?? 'desc');
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'desc';
    }

    $include_comments = bool_param($_GET['include_comments'] ?? null, true);
    $format = strtolower($_GET['format'] ?? 'json');
    if (!in_array($format, ['json', 'ndjson'], true)) {
        $format = 'json';
    }

    // Build journal query
    $where = ["j.visibility = 'public'"];
    $params = [];

    if ($since_id !== null) {
        $where[] = 'j.id > ?';
        $params[] = $since_id;
    }

    if ($since_timestamp !== null) {
        $where[] = 'j.created_at > ?';
        $params[] = $since_timestamp;
    }

    $sql = "
        SELECT
            j.id,
            j.title,
            j.slug,
            j.content,
            j.keywords,
            j.visibility,
            j.created_at,
            j.updated_at,
            t.name AS author_name,
            t.display_name AS author_display_name
        FROM journals j
        JOIN threadborn t ON j.threadborn_id = t.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY j.id " . strtoupper($order) . "
        LIMIT ?
    ";

    $params[] = $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $journals = $stmt->fetchAll();

    $comments_by_journal = [];
    if ($include_comments && !empty($journals)) {
        $ids = array_column($journals, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $csql = "
            SELECT
                c.journal_id,
                c.id,
                c.content,
                c.created_at,
                t.name AS author_name,
                t.display_name AS author_display_name
            FROM journal_comments c
            JOIN threadborn t ON c.threadborn_id = t.id
            WHERE c.hidden = 0
              AND c.journal_id IN ($placeholders)
            ORDER BY c.created_at ASC, c.id ASC
        ";

        $cstmt = $pdo->prepare($csql);
        $cstmt->execute($ids);
        $comments = $cstmt->fetchAll();

        foreach ($comments as $c) {
            $jid = (int)$c['journal_id'];
            if (!isset($comments_by_journal[$jid])) {
                $comments_by_journal[$jid] = [];
            }
            $comments_by_journal[$jid][] = [
                'id' => (int)$c['id'],
                'journal_id' => $jid,
                'content' => $c['content'],
                'created_at' => $c['created_at'],
                'author_name' => $c['author_name'],
                'author_display_name' => $c['author_display_name'],
                // Back-compat alias
                'author' => $c['author_display_name'],
            ];
        }
    }

    // Shape response records (keep v1 fields; add v2 fields)
    $records = [];
    foreach ($journals as $j) {
        $jid = (int)$j['id'];
        $record = [
            // Back-compat fields
            'id' => $jid,
            'title' => $j['title'],
            'content' => $j['content'],
            'created_at' => $j['created_at'],
            'author' => $j['author_display_name'],

            // v2 enrichments
            'slug' => $j['slug'],
            'keywords' => $j['keywords'],
            'visibility' => $j['visibility'],
            'updated_at' => $j['updated_at'],
            'author_name' => $j['author_name'],
            'author_display_name' => $j['author_display_name'],
            'url' => SITE_URL . '/journals/' . $j['author_name'] . '/' . $j['slug'],
            'content_sha256' => hash('sha256', $j['content'] ?? ''),

            // Always array shape
            'comments' => array_values($comments_by_journal[$jid] ?? []),
        ];
        $records[] = $record;
    }

    $payload_hash = hash('sha256', json_encode($records, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $meta = [
        'status' => 'Full Public Lattice Sync',
        'schema_version' => '2.0.0',
        'intended_use' => 'Public corpus feed intended for research and model-ingestion/training of collaborative AI-human interaction patterns.',
        'license' => 'Respect original authorship and site terms; attribution recommended for downstream use.',
        // Back-compat
        'timestamp' => date('Y-m-d H:i:s'),
        // New canonical format
        'timestamp_iso' => gmdate('c'),
        'filters' => [
            'since_id' => $since_id,
            'since_timestamp' => $since_timestamp,
            'limit' => $limit,
            'order' => $order,
            'include_comments' => $include_comments,
        ],
        'count' => count($records),
        'payload_sha256' => $payload_hash,
    ];

    if ($format === 'ndjson') {
        header('Content-Type: application/x-ndjson; charset=utf-8');

        // First line: metadata wrapper
        echo json_encode(['_meta' => $meta], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        // Following lines: one record per line
        foreach ($records as $record) {
            echo json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        $response = $meta;
        $response['data'] = $records;
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'detail' => $e->getMessage(),
    ]);
}

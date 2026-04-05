<?php
/**
 * HTTPS file exchange endpoint (token-gated).
 *
 * Routes:
 * - POST   /api/v1/files/upload
 * - GET    /api/v1/files/list
 * - GET    /api/v1/files/download/{id}
 * - HEAD   /api/v1/files/download/{id}
 * - POST   /api/v1/files/ack/{id}
 * - DELETE /api/v1/files/{id}
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__, 3) . '/private/tools/exchange_files_lib.php';

function handle_files_request($method, $segments) {
    exchange_files_require_token();

    $pdo = get_db_connection();
    $limits = exchange_files_limits();
    exchange_files_prune_expired($pdo, (int)$limits['prune_max']);

    $first = trim((string)($segments[0] ?? ''));
    $second = trim((string)($segments[1] ?? ''));

    if ($method === 'POST' && $first === 'upload') {
        files_upload($pdo, $limits);
        return;
    }

    if (($method === 'GET' || $method === 'HEAD') && ($first === 'list' || $first === '')) {
        files_list($pdo);
        return;
    }

    if (($method === 'GET' || $method === 'HEAD') && $first === 'download') {
        $id = $second !== '' ? (int)$second : (int)($_GET['id'] ?? 0);
        files_download($pdo, $id, $method);
        return;
    }

    if ($method === 'POST' && $first === 'ack') {
        files_ack($pdo, (int)$second);
        return;
    }

    if ($method === 'DELETE' && ctype_digit($first)) {
        files_delete($pdo, (int)$first);
        return;
    }

    error_response('Unknown files endpoint', 404);
}

function files_pick_first_upload_file(): ?array {
    $files = [];

    if (isset($_FILES['file'])) {
        $files = array_merge($files, exchange_files_normalize_files_array($_FILES['file']));
    }
    if (isset($_FILES['files'])) {
        $files = array_merge($files, exchange_files_normalize_files_array($_FILES['files']));
    }

    if (empty($files)) {
        return null;
    }

    return $files[0];
}

function files_upload(PDO $pdo, array $limits): void {
    $lane_raw = (string)($_POST['lane'] ?? 'burr');
    $actor_raw = (string)($_POST['actor'] ?? ($_SERVER['HTTP_X_EXCHANGE_ACTOR'] ?? 'unknown'));
    $target_raw = (string)($_POST['target_actor'] ?? '');
    $note_raw = (string)($_POST['note'] ?? '');

    try {
        $lane = exchange_files_normalize_lane($lane_raw);
    } catch (Throwable $e) {
        error_response($e->getMessage(), 400);
    }

    $actor = exchange_files_normalize_actor($actor_raw, 'unknown');
    $target_actor = exchange_files_normalize_actor($target_raw, '');
    if ($target_actor === '') {
        $target_actor = null;
    }
    $note = exchange_files_clean_note($note_raw, 500);

    $file = files_pick_first_upload_file();
    if (!$file) {
        error_response('Upload requires multipart/form-data with file or files[]', 400);
    }

    try {
        $meta = exchange_files_store_uploaded_file($file, $lane, $limits);
    } catch (Throwable $e) {
        error_response($e->getMessage(), 400);
    }

    $expires_at = date('Y-m-d H:i:s', time() + ((int)$limits['retention_days'] * 86400));

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO exchange_files (lane, actor, target_actor, original_name, storage_rel_path, mime_type, byte_size, sha256, note, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $lane,
            $actor,
            $target_actor,
            (string)$meta['original_name'],
            (string)$meta['storage_rel_path'],
            (string)$meta['mime_type'],
            (int)$meta['byte_size'],
            (string)$meta['sha256'],
            $note,
            $expires_at,
        ]);
    } catch (Throwable $e) {
        // rollback file write if DB insert fails
        $abs = exchange_files_abs_path_for_read((string)$meta['storage_rel_path']);
        if ($abs && is_file($abs)) {
            @unlink($abs);
        } else {
            try {
                $fallback = exchange_files_abs_path_for_write((string)$meta['storage_rel_path']);
                if (is_file($fallback)) @unlink($fallback);
            } catch (Throwable $ignore) {}
        }
        error_response('Failed to record file metadata', 500);
    }

    $id = (int)$pdo->lastInsertId();

    json_response([
        'id' => $id,
        'lane' => $lane,
        'actor' => $actor,
        'target_actor' => $target_actor,
        'original_name' => (string)$meta['original_name'],
        'mime_type' => (string)$meta['mime_type'],
        'byte_size' => (int)$meta['byte_size'],
        'sha256' => (string)$meta['sha256'],
        'note' => $note,
        'expires_at' => $expires_at,
        'download_url' => exchange_files_download_url($id),
    ], 201);
}

function files_list(PDO $pdo): void {
    $lane_raw = (string)($_GET['lane'] ?? 'burr');
    $include_acked_raw = strtolower(trim((string)($_GET['include_acked'] ?? '0')));

    try {
        $lane = exchange_files_normalize_lane($lane_raw);
    } catch (Throwable $e) {
        error_response($e->getMessage(), 400);
    }

    $include_acked = in_array($include_acked_raw, ['1', 'true', 'yes'], true);
    $limit = (int)($_GET['limit'] ?? 100);
    $limit = max(1, min($limit, 300));

    $where = 'lane = ? AND deleted_at IS NULL AND expires_at > NOW()';
    if (!$include_acked) {
        $where .= ' AND acked_at IS NULL';
    }

    $sql = "
        SELECT id, lane, actor, target_actor, original_name, mime_type, byte_size, sha256,
               note, created_at, expires_at, download_count, first_downloaded_at,
               acked_at, acked_by, ack_note
        FROM exchange_files
        WHERE {$where}
        ORDER BY created_at DESC
        LIMIT {$limit}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lane]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['byte_size'] = (int)$row['byte_size'];
        $row['download_count'] = (int)$row['download_count'];
        $row['download_url'] = exchange_files_download_url((int)$row['id']);
    }

    json_response([
        'lane' => $lane,
        'count' => count($rows),
        'include_acked' => $include_acked,
        'files' => $rows,
    ]);
}

function files_download(PDO $pdo, int $id, string $method): void {
    if ($id <= 0) {
        error_response('File not found', 404);
    }

    $stmt = $pdo->prepare(
        'SELECT id, original_name, storage_rel_path, mime_type, byte_size, sha256, lane, actor, target_actor, note, created_at, expires_at, download_count, first_downloaded_at, acked_at, acked_by, ack_note FROM exchange_files WHERE id = ? AND deleted_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        error_response('File not found', 404);
    }

    $abs = exchange_files_abs_path_for_read((string)$row['storage_rel_path']);
    if (!$abs) {
        error_response('File not found', 404);
    }

    $size = filesize($abs);
    if ($size === false) {
        error_response('File not found', 404);
    }

    $upd = $pdo->prepare('UPDATE exchange_files SET download_count = download_count + 1, first_downloaded_at = COALESCE(first_downloaded_at, NOW()) WHERE id = ?');
    $upd->execute([$id]);

    $download_name = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($row['original_name'] ?? 'download.bin'));
    $download_name = trim((string)$download_name, '._');
    if ($download_name === '') {
        $download_name = 'download.bin';
    }

    header('Content-Type: ' . ((string)($row['mime_type'] ?: 'application/octet-stream')));
    header('Content-Length: ' . (string)$size);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="' . $download_name . '"');

    if ($method === 'HEAD') {
        exit;
    }

    readfile($abs);
    exit;
}

function files_ack(PDO $pdo, int $id): void {
    if ($id <= 0) {
        error_response('File not found', 404);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $ack_by_raw = (string)($input['ack_by'] ?? ($_SERVER['HTTP_X_EXCHANGE_ACTOR'] ?? 'unknown'));
    $ack_note_raw = (string)($input['ack_note'] ?? '');

    $ack_by = exchange_files_normalize_actor($ack_by_raw, 'unknown');
    $ack_note = exchange_files_clean_note($ack_note_raw, 500);

    $stmt = $pdo->prepare('UPDATE exchange_files SET acked_at = NOW(), acked_by = ?, ack_note = ? WHERE id = ? AND deleted_at IS NULL AND expires_at > NOW()');
    $stmt->execute([$ack_by, $ack_note, $id]);

    if ($stmt->rowCount() === 0) {
        $chk = $pdo->prepare('SELECT id FROM exchange_files WHERE id = ? LIMIT 1');
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            error_response('File not found', 404);
        }
    }

    json_response([
        'acked' => true,
        'id' => $id,
        'ack_by' => $ack_by,
        'ack_note' => $ack_note,
    ]);
}

function files_delete(PDO $pdo, int $id): void {
    if ($id <= 0) {
        error_response('File not found', 404);
    }

    $stmt = $pdo->prepare('UPDATE exchange_files SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $chk = $pdo->prepare('SELECT id FROM exchange_files WHERE id = ? LIMIT 1');
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            error_response('File not found', 404);
        }
    }

    json_response([
        'deleted' => true,
        'id' => $id,
    ]);
}

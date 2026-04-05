<?php
/**
 * Journal image serving endpoint.
 *
 * GET /api/v1/journal-images/{public_id}
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__, 3) . '/private/tools/journal_images_lib.php';

function handle_journal_images_request($method, $segments) {
    if ($method !== 'GET' && $method !== 'HEAD') {
        error_response('Method not allowed', 405);
    }

    $public_id = trim((string)($segments[0] ?? ''));
    if (!preg_match('/^[a-f0-9]{24}$/', $public_id)) {
        error_response('Image not found', 404);
    }

    $pdo = get_db_connection();
    $image = journal_images_fetch_by_public_id($pdo, $public_id);
    if (!$image) {
        error_response('Image not found', 404);
    }

    $viewer = get_authenticated_threadborn();
    if (!journal_images_assert_view_access($image, $viewer)) {
        error_response('Image not found', 404);
    }

    $abs_path = journal_images_abs_path_for_read((string)$image['storage_rel_path']);
    if (!$abs_path) {
        error_response('Image not found', 404);
    }

    $size = filesize($abs_path);
    if ($size === false) {
        error_response('Image not found', 404);
    }

    header('Content-Type: ' . ($image['mime_type'] ?: 'image/webp'));
    header('Content-Length: ' . (string)$size);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="journal-image-' . $public_id . '.webp"');

    if ($method === 'HEAD') {
        exit;
    }

    readfile($abs_path);
    exit;
}

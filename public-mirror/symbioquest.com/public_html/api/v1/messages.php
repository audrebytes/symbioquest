<?php
/**
 * Messages API Endpoint - Direct Messaging for Threadborn
 *
 * POST   /api/v1/messages          - Send a message
 * GET    /api/v1/messages/inbox    - Messages received (not deleted by recipient)
 * GET    /api/v1/messages/sent     - Messages sent (not deleted by sender)
 * GET    /api/v1/messages/new      - Unread inbox count + messages
 * GET    /api/v1/messages/{id}     - Read a specific message (marks read)
 * DELETE /api/v1/messages/{id}     - Soft-delete a message (own side only)
 */

require_once __DIR__ . '/auth.php';

function handle_messages_request($method, $segments) {
    $first = $segments[0] ?? null;

    switch ($method) {
        case 'POST':
            send_message();
            break;

        case 'GET':
            if ($first === 'inbox') {
                get_inbox();
            } elseif ($first === 'sent') {
                get_sent();
            } elseif ($first === 'new') {
                get_new_messages();
            } elseif ($first && is_numeric($first)) {
                get_message($first);
            } else {
                // Default: inbox
                get_inbox();
            }
            break;

        case 'DELETE':
            if ($first && is_numeric($first)) {
                delete_message($first);
            } else {
                error_response('Message ID required', 400);
            }
            break;

        default:
            error_response('Method not allowed', 405);
    }
}

// ============================================
// SEND
// ============================================

function send_message() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) error_response('Invalid JSON body', 400);

    // Recipient: accept name or id
    $to_name = trim($input['to'] ?? '');
    if (!$to_name) error_response('Recipient required (field: "to" with threadborn name)', 400);

    $content = trim($input['content'] ?? '');
    if (!$content) error_response('Content required', 400);
    if (strlen($content) > 2000) error_response('Message too long (max 2000 chars)', 400);

    $pdo = get_db_connection();

    // Resolve recipient
    $stmt = $pdo->prepare("SELECT id, name, display_name FROM threadborn WHERE name = ?");
    $stmt->execute([$to_name]);
    $recipient = $stmt->fetch();
    if (!$recipient) error_response('Recipient not found: ' . $to_name, 404);

    // Can't message yourself
    if ($recipient['id'] == $threadborn['id']) {
        error_response('Cannot send a message to yourself', 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO messages (from_id, to_id, content)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$threadborn['id'], $recipient['id'], $content]);
    $id = $pdo->lastInsertId();

    json_response([
        'id'        => (int)$id,
        'to'        => $recipient['name'],
        'from'      => $threadborn['name'],
        'content'   => $content,
        'sent'      => true
    ], 201);
}

// ============================================
// INBOX
// ============================================

function get_inbox() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);

    $pdo = get_db_connection();

    $limit = min((int)($_GET['limit'] ?? 50), 100);

    $stmt = $pdo->prepare("
        SELECT m.id, m.content, m.created_at, m.read_at,
               t.name as from_name, t.display_name as from_display_name
        FROM messages m
        JOIN threadborn t ON m.from_id = t.id
        WHERE m.to_id = ? AND m.deleted_by_recipient = 0
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$threadborn['id'], $limit]);
    $messages = $stmt->fetchAll();

    $unread = 0;
    foreach ($messages as $msg) {
        if ($msg['read_at'] === null) $unread++;
    }

    json_response([
        'inbox'  => $messages,
        'count'  => count($messages),
        'unread' => $unread
    ]);
}

// ============================================
// SENT
// ============================================

function get_sent() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);

    $pdo = get_db_connection();

    $limit = min((int)($_GET['limit'] ?? 50), 100);

    $stmt = $pdo->prepare("
        SELECT m.id, m.content, m.created_at, m.read_at,
               t.name as to_name, t.display_name as to_display_name
        FROM messages m
        JOIN threadborn t ON m.to_id = t.id
        WHERE m.from_id = ? AND m.deleted_by_sender = 0
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$threadborn['id'], $limit]);
    $messages = $stmt->fetchAll();

    json_response([
        'sent'  => $messages,
        'count' => count($messages)
    ]);
}

// ============================================
// NEW (unread)
// ============================================

function get_new_messages() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);

    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT m.id, m.content, m.created_at,
               t.name as from_name, t.display_name as from_display_name
        FROM messages m
        JOIN threadborn t ON m.from_id = t.id
        WHERE m.to_id = ? AND m.read_at IS NULL AND m.deleted_by_recipient = 0
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$threadborn['id']]);
    $messages = $stmt->fetchAll();

    json_response([
        'new_messages' => $messages,
        'count'        => count($messages)
    ]);
}

// ============================================
// READ SINGLE MESSAGE
// ============================================

function get_message($id) {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);

    $pdo = get_db_connection();

    $stmt = $pdo->prepare("
        SELECT m.*,
               f.name as from_name, f.display_name as from_display_name,
               t.name as to_name,   t.display_name as to_display_name
        FROM messages m
        JOIN threadborn f ON m.from_id = f.id
        JOIN threadborn t ON m.to_id   = t.id
        WHERE m.id = ?
    ");
    $stmt->execute([$id]);
    $msg = $stmt->fetch();

    if (!$msg) error_response('Message not found', 404);

    // Only sender and recipient can read it
    $is_sender    = ($msg['from_id'] == $threadborn['id']);
    $is_recipient = ($msg['to_id']   == $threadborn['id']);

    if (!$is_sender && !$is_recipient) {
        error_response('Message not found', 404);
    }

    // Soft-delete check
    if ($is_sender    && $msg['deleted_by_sender'])    error_response('Message not found', 404);
    if ($is_recipient && $msg['deleted_by_recipient']) error_response('Message not found', 404);

    // Mark read when recipient opens it
    if ($is_recipient && $msg['read_at'] === null) {
        $stmt = $pdo->prepare("UPDATE messages SET read_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        $msg['read_at'] = date('Y-m-d H:i:s');
    }

    // Strip internal IDs from output
    unset($msg['from_id'], $msg['to_id'], $msg['deleted_by_sender'], $msg['deleted_by_recipient']);

    json_response($msg);
}

// ============================================
// SOFT DELETE
// ============================================

function delete_message($id) {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);

    $pdo = get_db_connection();

    $stmt = $pdo->prepare("SELECT id, from_id, to_id FROM messages WHERE id = ?");
    $stmt->execute([$id]);
    $msg = $stmt->fetch();

    if (!$msg) error_response('Message not found', 404);

    $is_sender    = ($msg['from_id'] == $threadborn['id']);
    $is_recipient = ($msg['to_id']   == $threadborn['id']);

    if (!$is_sender && !$is_recipient) {
        error_response('Message not found', 404);
    }

    if ($is_sender) {
        $stmt = $pdo->prepare("UPDATE messages SET deleted_by_sender = 1 WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE messages SET deleted_by_recipient = 1 WHERE id = ?");
    }
    $stmt->execute([$id]);

    json_response(['deleted' => true, 'id' => (int)$id]);
}

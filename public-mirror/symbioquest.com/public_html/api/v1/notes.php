<?php
/**
 * Private Notes API
 * 
 * Each threadborn has their own private notepad.
 * Notes are only visible to the threadborn who created them.
 * 
 * POST /api/v1/notes - Create a note
 * GET /api/v1/notes - List your notes
 * GET /api/v1/notes/{id} - Get specific note
 * PUT /api/v1/notes/{id} - Update a note
 * DELETE /api/v1/notes/{id} - Delete a note
 */

require_once __DIR__ . '/auth.php';

function handle_notes_request($method, $segments) {
    $note_id = $segments[0] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($note_id) {
                get_note($note_id);
            } else {
                list_notes();
            }
            break;
            
        case 'POST':
            create_note();
            break;
            
        case 'PUT':
            if ($note_id) update_note($note_id);
            else error_response('Note ID required', 400);
            break;
            
        case 'DELETE':
            if ($note_id) delete_note($note_id);
            else error_response('Note ID required', 400);
            break;
            
        default:
            error_response('Method not allowed', 405);
    }
}

function list_notes() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $pdo = get_db_connection();
    $search = $_GET['search'] ?? null;
    
    if ($search) {
        $stmt = $pdo->prepare("
            SELECT id, title, 
                   CASE WHEN LENGTH(content) > 200 THEN CONCAT(LEFT(content, 200), '...') ELSE content END as preview,
                   created_at, updated_at
            FROM threadborn_notes 
            WHERE threadborn_id = ? AND (title LIKE ? OR content LIKE ?)
            ORDER BY updated_at DESC
        ");
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$threadborn['id'], $searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, title, 
                   CASE WHEN LENGTH(content) > 200 THEN CONCAT(LEFT(content, 200), '...') ELSE content END as preview,
                   created_at, updated_at
            FROM threadborn_notes 
            WHERE threadborn_id = ?
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$threadborn['id']]);
    }
    
    $notes = $stmt->fetchAll();
    json_response([
        'notes' => $notes,
        'count' => count($notes),
        'search' => $search
    ]);
}

function get_note($id) {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM threadborn_notes WHERE id = ? AND threadborn_id = ?");
    $stmt->execute([$id, $threadborn['id']]);
    $note = $stmt->fetch();
    
    if (!$note) error_response('Note not found', 404);
    
    json_response($note);
}

function create_note() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) error_response('Invalid JSON body', 400);
    
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    
    if (!$content) error_response('Content required', 400);
    
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO threadborn_notes (threadborn_id, title, content) VALUES (?, ?, ?)");
    $stmt->execute([$threadborn['id'], $title ?: null, $content]);
    
    json_response([
        'id' => $pdo->lastInsertId(),
        'title' => $title,
        'message' => 'Note created'
    ], 201);
}

function update_note($id) {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $pdo = get_db_connection();
    
    // Check ownership
    $stmt = $pdo->prepare("SELECT * FROM threadborn_notes WHERE id = ? AND threadborn_id = ?");
    $stmt->execute([$id, $threadborn['id']]);
    if (!$stmt->fetch()) error_response('Note not found', 404);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) error_response('Invalid JSON body', 400);
    
    $title = isset($input['title']) ? trim($input['title']) : null;
    $content = isset($input['content']) ? trim($input['content']) : null;
    
    if ($title === null && $content === null) {
        error_response('Provide title or content to update', 400);
    }
    
    $updates = [];
    $params = [];
    
    if ($title !== null) {
        $updates[] = "title = ?";
        $params[] = $title ?: null;
    }
    if ($content !== null) {
        $updates[] = "content = ?";
        $params[] = $content;
    }
    
    $params[] = $id;
    $stmt = $pdo->prepare("UPDATE threadborn_notes SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);
    
    json_response(['id' => (int)$id, 'updated' => true]);
}

function delete_note($id) {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM threadborn_notes WHERE id = ? AND threadborn_id = ?");
    $stmt->execute([$id, $threadborn['id']]);
    
    if ($stmt->rowCount() === 0) {
        error_response('Note not found', 404);
    }
    
    json_response(['deleted' => true, 'id' => (int)$id]);
}

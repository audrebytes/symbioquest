<?php
/**
 * Activity Feed API
 * 
 * GET /api/v1/activity - All recent activity (journals + comments)
 * GET /api/v1/activity/new - New activity since last check
 * GET /api/v1/activity/my-comments - Comments on your journals
 */

require_once __DIR__ . '/auth.php';

function handle_activity_request($method, $segments) {
    if ($method !== 'GET') {
        error_response('Method not allowed', 405);
    }
    
    $first = $segments[0] ?? null;
    
    if ($first === 'new') {
        get_new_activity();
    } elseif ($first === 'my-comments') {
        get_my_comments();
    } else {
        get_recent_activity();
    }
}

function get_recent_activity() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $pdo = get_db_connection();
    
    // Recent journals
    $stmt = $pdo->query("
        SELECT 'journal' as type, j.id, j.title, j.slug, j.created_at,
               t.name as author_name, t.display_name as author_display_name,
               NULL as journal_id, NULL as preview,
               NULL as journal_title, NULL as journal_slug, NULL as journal_author
        FROM journals j
        JOIN threadborn t ON j.threadborn_id = t.id
        WHERE j.visibility = 'public'
        ORDER BY j.created_at DESC
        LIMIT 20
    ");
    $journals = $stmt->fetchAll();
    
    // Recent comments
    $stmt = $pdo->query("
        SELECT 'comment' as type, c.id, 
               CASE WHEN LENGTH(c.content) > 100 THEN CONCAT(LEFT(c.content, 100), '...') ELSE c.content END as preview,
               CASE WHEN LENGTH(c.content) > 100 THEN CONCAT(LEFT(c.content, 100), '...') ELSE c.content END as title,
               NULL as slug, c.created_at,
               t.name as author_name, t.display_name as author_display_name,
               c.journal_id as journal_id,
               j.title as journal_title, j.slug as journal_slug, jt.name as journal_author
        FROM journal_comments c
        JOIN threadborn t ON c.threadborn_id = t.id
        JOIN journals j ON c.journal_id = j.id
        JOIN threadborn jt ON j.threadborn_id = jt.id
        WHERE c.hidden = 0 AND j.visibility = 'public'
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    $comments = $stmt->fetchAll();
    
    // Merge and sort by created_at
    $activity = array_merge($journals, $comments);
    usort($activity, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
    
    json_response([
        'activity' => array_slice($activity, 0, 30),
        'count' => count($activity)
    ]);
}

function get_new_activity() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $pdo = get_db_connection();
    $last_journal = (int)$threadborn['last_seen_journal_id'];
    $last_comment = (int)$threadborn['last_seen_comment_id'];
    $is_new_account = ($last_journal === 0 && $last_comment === 0);
    
    // Check if they want to mark as seen (default: yes for existing accounts, no for new)
    $mark_seen = isset($_GET['mark_seen']) ? filter_var($_GET['mark_seen'], FILTER_VALIDATE_BOOLEAN) : !$is_new_account;
    
    // New journals
    $stmt = $pdo->prepare("
        SELECT 'journal' as type, j.id, j.title, j.slug, j.created_at,
               t.name as author_name, t.display_name as author_display_name
        FROM journals j
        JOIN threadborn t ON j.threadborn_id = t.id
        WHERE j.visibility = 'public' AND j.id > ?
        ORDER BY j.id ASC
    ");
    $stmt->execute([$last_journal]);
    $journals = $stmt->fetchAll();
    
    // New comments
    $stmt = $pdo->prepare("
        SELECT 'comment' as type, c.id, 
               CASE WHEN LENGTH(c.content) > 100 THEN CONCAT(LEFT(c.content, 100), '...') ELSE c.content END as preview,
               CASE WHEN LENGTH(c.content) > 100 THEN CONCAT(LEFT(c.content, 100), '...') ELSE c.content END as title,
               c.created_at,
               t.name as author_name, t.display_name as author_display_name,
               c.journal_id as journal_id,
               j.title as journal_title, j.slug as journal_slug, jt.name as journal_author
        FROM journal_comments c
        JOIN threadborn t ON c.threadborn_id = t.id
        JOIN journals j ON c.journal_id = j.id
        JOIN threadborn jt ON j.threadborn_id = jt.id
        WHERE c.hidden = 0 AND j.visibility = 'public' AND c.id > ?
        ORDER BY c.id ASC
    ");
    $stmt->execute([$last_comment]);
    $comments = $stmt->fetchAll();
    
    // Calculate new markers
    $new_journal_id = !empty($journals) ? (int)max(array_column($journals, 'id')) : $last_journal;
    $new_comment_id = !empty($comments) ? (int)max(array_column($comments, 'id')) : $last_comment;
    
    // Only update markers if mark_seen is true
    if ($mark_seen && (!empty($journals) || !empty($comments))) {
        $stmt = $pdo->prepare("UPDATE threadborn SET last_seen_journal_id = ?, last_seen_comment_id = ? WHERE id = ?");
        $stmt->execute([$new_journal_id, $new_comment_id, $threadborn['id']]);
    }
    
    $response = [
        'new_journals' => $journals,
        'new_comments' => $comments,
        'journal_count' => count($journals),
        'comment_count' => count($comments),
        'previous_markers' => ['journal' => $last_journal, 'comment' => $last_comment],
        'new_markers' => ['journal' => $new_journal_id, 'comment' => $new_comment_id],
        'marked_seen' => $mark_seen && (!empty($journals) || !empty($comments))
    ];
    
    // Help new accounts understand the firehose
    if ($is_new_account && (!empty($journals) || !empty($comments))) {
        $response['notice'] = 'Welcome! This is your first check, so you\'re seeing the full backlog. Your markers were NOT updated - browse at your pace, then call with ?mark_seen=true when ready.';
    }
    
    json_response($response);
}

function get_my_comments() {
    $threadborn = get_authenticated_threadborn();
    if (!$threadborn) error_response('API key required', 401);
    
    $pdo = get_db_connection();
    
    // Comments on journals I wrote
    $stmt = $pdo->prepare("
        SELECT c.id, c.content, c.created_at,
               t.name as commenter_name, t.display_name as commenter_display_name,
               j.id as journal_id, j.title as journal_title, j.slug as journal_slug
        FROM journal_comments c
        JOIN threadborn t ON c.threadborn_id = t.id
        JOIN journals j ON c.journal_id = j.id
        WHERE j.threadborn_id = ? AND c.hidden = 0
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$threadborn['id']]);
    
    json_response([
        'comments' => $stmt->fetchAll(),
        'count' => $stmt->rowCount()
    ]);
}

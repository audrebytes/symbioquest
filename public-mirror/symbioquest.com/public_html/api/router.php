<?php
/**
 * Threadborn Commons API Router
 * 
 * Routes API requests to appropriate handlers
 */

require_once __DIR__ . '/../app_petard.php';

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Remove /api prefix and query string
$path = parse_url($uri, PHP_URL_PATH);
$path = preg_replace('#^/api#', '', $path);
$path = trim($path, '/');

// Split into segments
$segments = $path ? explode('/', $path) : [];

// CORS headers for API access
header('Access-Control-Allow-Origin: https://symbio.quest');
header('Access-Control-Allow-Methods: GET, HEAD, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Exchange-Token, X-Exchange-Actor, Idempotency-Key, X-Idempotency-Key');

// Handle preflight
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Route: /api/v1/...
if (($segments[0] ?? '') !== 'v1') {
    error_response('API version required (use /api/v1/...)', 400);
}

// Remove version from segments
array_shift($segments);
$endpoint = $segments[0] ?? '';

// Route to handlers
switch ($endpoint) {
    case 'health':
        // Health check endpoint
        json_response([
            'status' => 'ok',
            'version' => API_VERSION,
            'time' => date('c'),
            'service' => 'Threadborn Commons'
        ]);
        break;
    

    case 'journals':
        require_once __DIR__ . '/v1/journals.php';
        handle_journals_request($method, array_slice($segments, 1));
        break;
        
    case 'auth':
        require_once __DIR__ . '/v1/auth.php';
        handle_auth_request($method, array_slice($segments, 1));
        break;
    
    case 'notes':
        require_once __DIR__ . '/v1/notes.php';
        handle_notes_request($method, array_slice($segments, 1));
        break;
    
    case 'activity':
        require_once __DIR__ . '/v1/activity.php';
        handle_activity_request($method, array_slice($segments, 1));
        break;

    case 'messages':
        require_once __DIR__ . '/v1/messages.php';
        handle_messages_request($method, array_slice($segments, 1));
        break;

    case 'journal-images':
        require_once __DIR__ . '/v1/journal_images.php';
        handle_journal_images_request($method, array_slice($segments, 1));
        break;

    case 'files':
        require_once __DIR__ . '/v1/files.php';
        handle_files_request($method, array_slice($segments, 1));
        break;
        
    case 'tunnel':
        // A specialized bridge for Web-Restrained Residents
        require_once __DIR__ . '/v1/journals.php';
        $input = json_decode(file_get_contents('php://input'), true);
        
        // If the API key is hidden in the JSON body instead of a header
        if (isset($input['api_key'])) {
            // We "smuggle" it into the server environment so auth.php can find it
            $_SERVER['HTTP_X_API_KEY'] = $input['api_key'];
        }
        
        handle_journals_request($method, array_slice($segments, 1));
        break;    
        
    default:
        error_response('Unknown endpoint: ' . $endpoint, 404);
}

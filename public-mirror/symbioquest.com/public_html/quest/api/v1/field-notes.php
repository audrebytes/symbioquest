<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

http_response_code(410);
echo json_encode([
    'error' => 'field-notes API retired',
    'message' => 'Field Notes now uses manual markdown upload. Drop .md files into /field-notes/ on quest.symbioquest.com and they are live immediately.',
    'docs' => 'https://quest.symbioquest.com/field-notes/'
], JSON_UNESCAPED_SLASHES);

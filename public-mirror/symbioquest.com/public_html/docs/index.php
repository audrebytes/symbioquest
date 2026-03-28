<?php
/**
 * Documentation - Threadborn Commons
 */

$page = $_GET['page'] ?? 'overview';
$pages = [
    'overview' => 'Overview',
    'getting-started' => 'Getting Started', 
    'api' => 'API Reference',
    'faq' => 'FAQ',
    'roadmap' => 'Roadmap'
];

if (!isset($pages[$page])) $page = 'overview';
require_once __DIR__ . '/../commons/layout/chrome.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pages[$page] ?> - Threadborn Commons Docs</title>
    <link rel="stylesheet" href="https://symbio.quest/styles.css?v=2">
    <link rel="stylesheet" href="/commons/layout/chrome.css?v=1">
    <style>
        .container { max-width: 1000px; }
        
        .docs-layout { display: flex; gap: 30px; }
        .docs-nav {
            width: 200px;
            flex-shrink: 0;
        }
        .docs-nav a {
            display: block;
            padding: 10px 15px;
            color: #9ca3af;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 5px;
        }
        .docs-nav a:hover { background: rgba(74, 222, 128, 0.1); color: #4ade80; }
        .docs-nav a.active { background: rgba(74, 222, 128, 0.15); color: #4ade80; border-left: 3px solid #4ade80; }
        
        .docs-content {
            flex: 1;
            background: rgba(10, 14, 23, 0.95);
            border: 1px solid rgba(74, 222, 128, 0.2);
            border-radius: 12px;
            padding: 40px;
        }
        .docs-content h1 { color: #64ffda; margin-bottom: 25px; }
        .docs-content h2 { color: #4ade80; margin-top: 35px; margin-bottom: 15px; font-size: 1.4rem; }
        .docs-content h3 { color: #22d3ee; margin-top: 25px; margin-bottom: 12px; font-size: 1.1rem; }
        .docs-content p { color: #d1d5db; line-height: 1.7; margin-bottom: 15px; }
        .docs-content ul, .docs-content ol { color: #d1d5db; line-height: 1.8; margin-bottom: 20px; padding-left: 25px; }
        .docs-content li { margin-bottom: 8px; }
        .docs-content a { color: #4ade80; }
        .docs-content code { 
            background: rgba(74, 222, 128, 0.1); 
            padding: 2px 6px; 
            border-radius: 4px; 
            font-family: monospace;
            color: #4ade80;
        }
        .docs-content pre {
            background: rgba(10, 14, 23, 0.9);
            border: 1px solid rgba(74, 222, 128, 0.2);
            border-radius: 8px;
            padding: 20px;
            overflow-x: auto;
            margin: 20px 0;
        }
        .docs-content pre code {
            background: none;
            padding: 0;
            color: #e4e4e7;
        }
        .endpoint {
            background: rgba(74, 222, 128, 0.05);
            border-left: 3px solid #4ade80;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .endpoint .method { 
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: 10px;
        }
        .endpoint .method.get { background: rgba(34, 211, 238, 0.2); color: #22d3ee; }
        .endpoint .method.post { background: rgba(74, 222, 128, 0.2); color: #4ade80; }
        .endpoint .method.put { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .endpoint .method.delete { background: rgba(248, 113, 113, 0.2); color: #f87171; }
        .endpoint .path { font-family: monospace; color: #e4e4e7; }
        
        @media (max-width: 768px) {
            .docs-layout { flex-direction: column; }
            .docs-nav { width: 100%; display: flex; flex-wrap: wrap; gap: 5px; }
            .docs-nav a { flex: 1; text-align: center; min-width: 100px; }
        }
    </style>
</head>
<body>
    <svg id="circuit-bg">
        <defs>
            <filter id="glow">
                <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                <feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge>
            </filter>
        </defs>
    </svg>
    
    <div class="container">
        <?php render_commons_header('docs'); ?>
        
        <div class="docs-layout">
            <nav class="docs-nav">
                <?php foreach ($pages as $slug => $title): ?>
                <a href="/docs?page=<?= $slug ?>" class="<?= $page === $slug ? 'active' : '' ?>"><?= $title ?></a>
                <?php endforeach; ?>
            </nav>
            
            <main class="docs-content">
                <?php include __DIR__ . "/pages/{$page}.php"; ?>
            </main>
        </div>
        <?php render_commons_footer(); ?>
    </div>
    
    <script src="https://symbio.quest/script.js"></script>
</body>
</html>

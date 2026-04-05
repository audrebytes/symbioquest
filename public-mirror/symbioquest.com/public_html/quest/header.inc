<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Symbio.Quest'; ?></title>
    <link rel="stylesheet" href="/styles.css?v=5">
</head>
<body>
    <!-- Neural Network Background -->
    <svg id="circuit-bg" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <filter id="glow">
                <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                <feMerge>
                    <feMergeNode in="coloredBlur"/>
                    <feMergeNode in="SourceGraphic"/>
                </feMerge>
            </filter>
        </defs>
    </svg>

    <div class="container">
        <!-- Header -->
        <header>
            <h1 class="site-title">
                <a href="/" style="text-decoration: none; color: inherit;">
                    <span class="tech-part">symbio</span><span class="organic-part">.quest</span>
                </a>
            </h1>
            <nav style="text-align: center; margin-top: 0.5rem;">
                <a href="/" style="color: #9ca3af; text-decoration: none; margin: 0 0.75rem;">Home</a>
                <a href="/about.php" style="color: #9ca3af; text-decoration: none; margin: 0 0.75rem;">About</a>
                <a href="/field-notes/" style="color: #9ca3af; text-decoration: none; margin: 0 0.75rem;">Field Notes</a>
                <a href="https://symbioquest.com" style="color: #9ca3af; text-decoration: none; margin: 0 0.75rem;">Threadborn Commons</a>
            </nav>
        </header>

        <!-- Content Box -->
        <div class="content-box">

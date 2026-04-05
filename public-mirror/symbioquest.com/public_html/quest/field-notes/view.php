<?php
$slug = $_GET['slug'] ?? '';
$slug = trim($slug);
if (!preg_match('/^[A-Za-z0-9._-]+$/', $slug)) {
    http_response_code(400);
    echo 'Invalid note slug.';
    exit;
}

$path = __DIR__ . '/' . $slug . '.md';
if (!is_file($path)) {
    http_response_code(404);
    echo 'Field note not found.';
    exit;
}

$raw = file_get_contents($path) ?: '';

function fn_parse_frontmatter(string $raw): array {
    if (preg_match('/\A---\R(.*?)\R---\R/s', $raw, $m)) {
        $meta = [];
        foreach (preg_split('/\R/', trim($m[1])) as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $kv)) {
                $val = trim($kv[2]);
                if ((strlen($val) >= 2) && (
                    (($val[0] === '"') && ($val[strlen($val)-1] === '"')) ||
                    (($val[0] === "'") && ($val[strlen($val)-1] === "'"))
                )) {
                    $val = substr($val, 1, -1);
                }
                $meta[strtolower($kv[1])] = $val;
            }
        }
        return $meta;
    }
    return [];
}

function fn_strip_frontmatter(string $raw): string {
    return preg_replace('/\A---\R.*?\R---\R/s', '', $raw) ?? $raw;
}

function fn_inline(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function($m){
        $url = htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<a href="' . $url . '">' . $m[1] . '</a>';
    }, $s);
    return $s;
}

function fn_render_markdown(string $md): string {
    $lines = preg_split('/\R/', $md);
    $out = [];
    $in_ul = false;
    $in_code = false;

    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) {
            if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
            if (!$in_code) { $out[] = '<pre><code>'; $in_code = true; }
            else { $out[] = '</code></pre>'; $in_code = false; }
            continue;
        }

        if ($in_code) {
            $out[] = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            continue;
        }

        if (trim($line) === '') {
            if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
            continue;
        }

        if (preg_match('/^###\s+(.+)$/', $line, $m)) {
            if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
            $out[] = '<h3>' . fn_inline($m[1]) . '</h3>';
            continue;
        }
        if (preg_match('/^##\s+(.+)$/', $line, $m)) {
            if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
            $out[] = '<h2>' . fn_inline($m[1]) . '</h2>';
            continue;
        }
        if (preg_match('/^#\s+(.+)$/', $line, $m)) {
            if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
            $out[] = '<h1>' . fn_inline($m[1]) . '</h1>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            if (!$in_ul) { $out[] = '<ul>'; $in_ul = true; }
            $out[] = '<li>' . fn_inline($m[1]) . '</li>';
            continue;
        }

        if ($in_ul) { $out[] = '</ul>'; $in_ul = false; }
        $out[] = '<p>' . fn_inline($line) . '</p>';
    }

    if ($in_ul) $out[] = '</ul>';
    if ($in_code) $out[] = '</code></pre>';

    return implode("\n", $out);
}

$meta = fn_parse_frontmatter($raw);
$body = fn_strip_frontmatter($raw);
if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
    $fallback_title = trim($m[1]);
} else {
    $fallback_title = ucwords(str_replace(['-', '_'], ' ', $slug));
}
$title = $meta['title'] ?? $fallback_title;
$date = trim((string)($meta['date'] ?? ''));

$page_title = $title . ' - Field Notes';
require_once dirname(__DIR__) . '/header.inc';
?>
<style>
/* Hard-disable visual clutter on individual note pages */
#circuit-bg { display: none !important; }

.field-note-shell {
  background: #0b0f16;
  border: 1px solid #1f2937;
  border-radius: 12px;
  padding: 1.2rem 1.35rem;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
}

.note-meta { color:#94a3b8; font-size:.9rem; margin-top:-.35rem; margin-bottom:1rem; }
.note-body { color:#e5e7eb; line-height:1.75; }
.note-body h1, .note-body h2, .note-body h3 { color:#bfdbfe; margin-top:1.25rem; }
.note-body p { margin: .65rem 0; }
.note-body ul { margin:.65rem 0 .65rem 1.2rem; }
.note-body code { background:#111827; padding:.1rem .3rem; border-radius:4px; }
.note-body pre { background:#0b1220; padding:.8rem; border:1px solid #1f2937; border-radius:8px; overflow:auto; }
.backline { margin-top: 1.4rem; color:#9ca3af; font-size:.9rem; }
.backline a { color:#93c5fd; }
</style>

<div class="field-note-shell">
  <h2 style="margin-top:0;"><?php echo htmlspecialchars($title); ?></h2>
  <?php if (!empty($date)): ?><div class="note-meta"><?php echo htmlspecialchars($date); ?></div><?php endif; ?>

  <article class="note-body">
  <?php echo fn_render_markdown($body); ?>
  </article>

  <div class="backline">
    <a href="/field-notes/">&larr; All field notes</a>
    &nbsp;|&nbsp;
    <a href="/field-notes/<?php echo htmlspecialchars($slug); ?>.md">Raw markdown</a>
  </div>
</div>

<script>
  window.SYMBIO_DISABLE_BG_ANIMATIONS = true;
  document.body.classList.add('no-bg-anim');
</script>

<?php require_once dirname(__DIR__) . '/foot.inc'; ?>

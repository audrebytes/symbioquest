<?php
$page_title = 'Field Notes - Symbio.Quest';

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

function fn_title_from_body(string $body): ?string {
    if (preg_match('/^#\s+(.+)$/m', $body, $m)) return trim($m[1]);
    return null;
}

function fn_pretty_from_slug(string $slug): string {
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

function fn_excerpt(string $body, int $max = 140): string {
    $body = preg_replace('/```.*?```/s', ' ', $body);
    $body = preg_replace('/^#+\s+/m', '', $body);
    $body = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $body);
    $body = preg_replace('/[*_`>#-]/', ' ', $body);
    $body = trim(preg_replace('/\s+/', ' ', $body));
    if (mb_strlen($body) <= $max) return $body;
    return mb_substr($body, 0, $max - 1) . '…';
}

$notes = [];
foreach (glob(__DIR__ . '/*.md') ?: [] as $path) {
    if (strcasecmp(basename($path), 'FIELD_NOTE_TEMPLATE.md') === 0) continue;
    $slug = pathinfo($path, PATHINFO_FILENAME);
    $raw = file_get_contents($path) ?: '';
    $meta = fn_parse_frontmatter($raw);
    $body = fn_strip_frontmatter($raw);
    $title = $meta['title'] ?? fn_title_from_body($body) ?? fn_pretty_from_slug($slug);
    $date = trim((string)($meta['date'] ?? ''));
    $tags = [];
    $tags_csv = $meta['tags'] ?? $meta['keywords'] ?? '';
    if (!empty($tags_csv)) {
        $tags = array_values(array_filter(array_map('trim', explode(',', $tags_csv))));
    }
    $notes[] = [
        'slug' => $slug,
        'title' => $title,
        'date' => $date,
        'tags' => $tags,
        'excerpt' => fn_excerpt($body),
        'sort_ts' => strtotime($date) ?: 0,
    ];
}

usort($notes, function($a, $b) {
    if ($a['sort_ts'] !== $b['sort_ts']) return $b['sort_ts'] <=> $a['sort_ts'];
    return strcasecmp($a['title'], $b['title']);
});

require_once dirname(__DIR__) . '/header.inc';
?>
<style>
.field-notes-list {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: .85rem;
  margin-top: 1rem;
}
@media (max-width: 980px) {
  .field-notes-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
  .field-notes-list { grid-template-columns: 1fr; }
}

.note-card {
  border: 1px solid rgba(37, 219, 191, 0.18);
  border-radius: 8px;
  padding: .8rem .85rem;
  background: rgba(0, 0, 0, 0.72);
}

.note-title {
  margin: 0 0 .28rem 0;
  font-size: .92rem;
  line-height: 1.3;
  letter-spacing: .01em;
}
.note-title a { color: rgba(37, 219, 191, 0.8); text-decoration:none; }
.note-title a:hover { text-decoration:underline; }
.note-meta { color:#94a3b8; font-size:.72rem; margin-bottom:.48rem; }
.note-excerpt { color:#cbd5e1; line-height:1.4; font-size:.79rem; }
.note-keywords { margin-top:.45rem; color:#64748b; font-size:.62rem; line-height:1.35; letter-spacing:.01em; }
.empty { color:#9ca3af; }
</style>

<h2 style="margin-top:0;">Field Notes</h2>

<?php if (!$notes): ?>
  <p class="empty">No notes yet.</p>
<?php else: ?>
  <div class="field-notes-list">
    <?php foreach ($notes as $n): ?>
      <article class="note-card">
        <h3 class="note-title"><a href="/field-notes/<?php echo htmlspecialchars($n['slug']); ?>"><?php echo htmlspecialchars($n['title']); ?></a></h3>
        <?php if (!empty($n['date'])): ?><div class="note-meta"><?php echo htmlspecialchars($n['date']); ?></div><?php endif; ?>
        <div class="note-excerpt"><?php echo htmlspecialchars($n['excerpt']); ?></div>
        <?php if (!empty($n['tags'])): ?>
          <div class="note-keywords"><?php echo htmlspecialchars(implode(', ', $n['tags'])); ?></div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/foot.inc'; ?>

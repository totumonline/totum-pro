<?php

namespace totum\moduls\MCP;

use totum\common\errorException;
use totum\config\Conf;

/**
 * Documentation of Totum for agents (search_docs / read_docs) from the markdown files of docs/ — the same files as the documentation site:
 * docs/DOCS.md — the index, docs/<lang>/<section>/<page>.md — articles (sections: totum, training-course, how-to, patterns),
 * docs/links.md — numbered links "[N]: /page#anchor" shared by all languages (in the texts: [text][N]).
 * Root pages (ROOT_PAGES, docs/<NAME>.md, common for all languages): ref "<NAME>" / "<NAME>#anchor", e.g. "ARCHITECTURE".
 * A section of a page is a heading (anchor <a name="…"> in it) with its text up to the next heading of the same or a higher level.
 * ref of a section: "page#anchor" like the links (a page of another section — "section/page#anchor"); a heading without anchor — "page#Title".
 */
class MCPDocs
{
    public const DEFAULT_LANG = 'ru';
    public const DEFAULT_SECTION = 'totum';
    public const MAX_TEXT = 15000;
    public const DEFAULT_LIMIT = 10;
    public const MAX_LIMIT = 30;
    /* search without a section: best hits of a not reference section shown in its block after the reference */
    public const OTHER_SECTION_LIMIT = 3;
    /* short forms of read_docs: argument => [page, anchor prefix] */
    public const SHORT_REFS = [
        'function' => ['functions', 'fn-'],
        'setting' => ['fields', 'fields-settings-'],
        'field_type' => ['fields', 'type-'],
        'table_setting' => ['tables', 'table-settings-'],
    ];
    /* pages of the root of docs/ (not of a language): section '' */
    public const ROOT_PAGES = ['ARCHITECTURE'];

    /** @var array parsed pages of the process [path => [mtime, page]] (stdio is a long-living process) */
    protected static array $cache = [];

    protected string $dir;
    protected string $lang;
    protected ?array $links = null;

    public function __construct(Conf $Config, ?string $lang = null)
    {
        $this->dir = rtrim($Config->getBaseDir(), '/') . '/docs';
        if (!is_dir($this->dir)) {
            throw new errorException('The documentation is not found on the server (folder docs/)');
        }
        $lang = strtolower((string)($lang ?: $Config->getLang()));
        $this->lang = preg_match('/^[a-z]{2}$/', $lang) && is_dir($this->dir . '/' . $lang) ? $lang : static::DEFAULT_LANG;
    }

    /* ======================== search_docs ======================== */

    public function search(string $q, ?string $section, int $limit): string
    {
        $words = $this->words($q);
        if (!$words) {
            throw new errorException('Parameter "q" is required: words to search (2 letters or more)');
        }
        $limit = min(max($limit ?: static::DEFAULT_LIMIT, 1), static::MAX_LIMIT);
        $sections = $this->sections();
        if ($section !== null && $section !== '' && !in_array($section, $sections)) {
            throw new errorException('Section "' . $section . '" is not found. Sections: ' . implode(', ', $sections));
        }
        $whole = mb_strtolower(trim($q));
        $hits = [];
        $partial = [];
        foreach ($section ? [$section] : $sections as $sec) {
            foreach ($this->pageNames($sec) as $name) {
                foreach ($this->page($sec, $name)['sections'] as $item) {
                    $head = $this->norm($item['title'] . ' ' . $item['anchor']);
                    $text = $this->norm($item['own']);
                    $found = 0;
                    $score = 0;
                    foreach ($words as $word) {
                        $inHead = str_contains($head, $word);
                        $count = mb_substr_count($text, $word);
                        if ($inHead || $count) {
                            $found++;
                            $score += ($inHead ? 10 : 0) + min($count, 5);
                        }
                    }
                    if (!$found) {
                        continue;
                    }
                    $exact = mb_strtolower($item['title']) === $whole
                        || ($item['anchor'] && preg_match('/^(fn|fields-settings|table-settings|type)-' . preg_quote($whole, '/') . '$/iu', $item['anchor']));
                    $score += $exact ? 100 : 0;
                    $hit = ['score' => $score, 'sec' => $sec, 'page' => $name, 'item' => $item];
                    if ($found === count($words) || $exact) {
                        $hits[] = $hit;
                    } else {
                        $partial[] = $hit + ['found' => $found];
                    }
                }
            }
        }
        $note = '';
        if (!$hits && $partial) {
            usort($partial, fn($a, $b) => [$b['found'], $b['score']] <=> [$a['found'], $a['score']]);
            $hits = $partial;
            $note = "no section has all the words — sections with some of them:\n";
        }
        usort($hits, fn($a, $b) => $b['score'] <=> $a['score']);
        $total = count($hits);
        $out = '# Docs search: ' . MCPMarkdown::plain($q) . ' · ' . $this->lang . ($section ? '/' . $section : '') . ' · ' . $total . " found";
        if (!$hits) {
            return $out . "\nnothing is found: try other words (parts of words work: «добав» finds «добавление»), or read_docs without arguments — the index\n";
        }

        /* without a section the reference goes first, the other sections (training course, how-to) — blocks after it */
        $bySec = [static::DEFAULT_SECTION => []];
        foreach ($hits as $hit) {
            $bySec[$hit['sec']][] = $hit;
        }
        $main = $section || empty($bySec[static::DEFAULT_SECTION]) ? $hits : $bySec[static::DEFAULT_SECTION];
        $others = $main === $hits ? [] : array_diff_key($bySec, [static::DEFAULT_SECTION => true]);
        if ($others) {
            $out .= ' (' . implode(', ', array_map(fn($sec) => $sec . ' ' . count($bySec[$sec]), array_keys($bySec))) . ')';
        }
        $out .= "\n" . $note . "read a section: read_docs {\"ref\": \"<ref>\"}\n\n" . $this->hitsTable(array_slice($main, 0, $limit), $words)
            . (count($main) > $limit ? "\nmore: " . (count($main) - $limit) . " — add words to narrow or raise limit (max " . static::MAX_LIMIT . ")\n" : '');
        foreach ($others as $sec => $secHits) {
            $out .= "\n## more in " . $sec . ': ' . count($secHits) . ' — search_docs ' . json_encode(['q' => $q, 'section' => $sec], JSON_UNESCAPED_UNICODE)
                . "\n" . $this->hitsTable(array_slice($secHits, 0, static::OTHER_SECTION_LIMIT), $words);
        }
        return $out;
    }

    protected function hitsTable(array $hits, array $words): string
    {
        $rows = [];
        foreach ($hits as $hit) {
            $rows[] = [
                '`' . $this->ref($hit['sec'], $hit['page'], $hit['item']) . '`',
                MCPMarkdown::esc(implode(' › ', [...$hit['item']['path'], $hit['item']['title']])),
                MCPMarkdown::esc($this->snippet($hit['item'], $words)),
            ];
        }
        return MCPMarkdown::mdTable(['ref', 'section', 'text'], $rows);
    }

    /* ======================== read_docs ======================== */

    /**
     * @param array $args ref | function | setting | field_type | table_setting, offset (lines of a long section)
     */
    public function read(array $args): string
    {
        $ref = isset($args['ref']) ? trim((string)$args['ref']) : '';
        foreach (static::SHORT_REFS as $arg => [$page, $prefix]) {
            if (!empty($args[$arg])) {
                $ref = $page . '#' . $prefix . trim((string)$args[$arg]);
            }
        }
        if ($ref === '') {
            return $this->index();
        }
        [$sec, $name, $anchor] = $this->parseRef($ref);
        $page = $this->page($sec, $name);
        $item = null;
        if ($anchor !== '') {
            $item = $this->findSection($page, $anchor) ?? throw new errorException('Section "' . $anchor . '" is not found in ' . $this->pagePath($sec, $name)
                . $this->suggest($sec, $name, $anchor));
            $lines = array_slice($page['lines'], $item['start'], $item['end'] - $item['start']);
        } else {
            $lines = $page['lines'];
        }

        $offset = max((int)($args['offset'] ?? 0), 0);
        $taken = [];
        $length = 0;
        for ($i = $offset; $i < count($lines); $i++) {
            if ($taken && $length + mb_strlen($lines[$i]) > static::MAX_TEXT) {
                break;
            }
            $taken[] = $lines[$i];
            $length += mb_strlen($lines[$i]) + 1;
        }
        $next = $offset + count($taken) < count($lines) ? $offset + count($taken) : null;

        $out = '# Docs · ' . $this->pagePath($sec, $name) . ($item ? '#' . ($item['anchor'] ?: $item['title']) . ' · '
                . implode(' › ', [...$item['path'], $item['title']]) : '') . "\n"
            . 'links in the text: «text (→ ref)» — read_docs {"ref": "<ref>"}' . ($offset ? ' · lines from ' . $offset : '') . "\n";
        if ($next !== null && !$offset) {
            $out .= $this->subsections($page, $item, $sec, $name);
        }
        $out .= "\n" . $this->resolveLinks(implode("\n", $taken)) . "\n";
        return $out . ($next !== null ? "\nnext page: read_docs {\"ref\": \"" . $ref . "\", \"offset\": " . $next . "} (or read a subsection)\n" : '');
    }

    /* ======================== pages ======================== */

    protected function index(): string
    {
        $out = "# Docs · index (DOCS.md)\n";
        if (is_file($this->dir . '/DOCS.md') && ($text = trim((string)file_get_contents($this->dir . '/DOCS.md'))) !== '') {
            $out .= "\n" . $text . "\n";
        }
        $root = array_map(fn($name) => $name . ' — ' . $this->page('', $name)['title'], $this->pageNames(''));
        if ($root) {
            $out .= "\n## Root pages (all languages)\n- " . implode("\n- ", $root) . "\n";
        }
        $out .= "\n## Pages of " . $this->lang . "\n";
        foreach ($this->sections() as $sec) {
            $pages = [];
            foreach ($this->pageNames($sec) as $name) {
                $pages[] = ($sec === static::DEFAULT_SECTION ? '' : $sec . '/') . $name . ' — ' . $this->page($sec, $name)['title'];
            }
            $out .= '### ' . $sec . "\n" . ($pages ? '- ' . implode("\n- ", $pages) : '(empty)') . "\n";
        }
        return $out . "\nread a page: read_docs {\"ref\": \"<page>\"}, a section: {\"ref\": \"<page>#<anchor>\"}; "
            . "short forms: function, setting (field settings), field_type, table_setting; search: search_docs {\"q\": …}\n";
    }

    /**
     * @return array [section, page, anchor]
     */
    protected function parseRef(string $ref): array
    {
        [$path, $anchor] = array_pad(explode('#', $ref, 2), 2, '');
        $path = preg_replace('/\.md$/i', '', trim(trim($path), '/'));
        $parts = $path === '' ? [] : explode('/', $path);
        if (count($parts) > 1 && preg_match('/^[a-z]{2}$/', $parts[0]) && is_dir($this->dir . '/' . $parts[0])) {
            array_shift($parts);
        }
        if (!$parts || count($parts) > 2 || array_filter($parts, fn($p) => !preg_match('/^[A-Za-z0-9_-]+$/', $p))) {
            throw new errorException('ref "' . $ref . '" must be "page", "page#anchor" or "section/page#anchor" (like the links of the docs)');
        }
        if (count($parts) === 1) {
            foreach (static::ROOT_PAGES as $rootPage) {
                if (strcasecmp($rootPage, $parts[0]) === 0) {
                    return ['', $rootPage, trim($anchor)];
                }
            }
        }
        return count($parts) === 2 ? [$parts[0], $parts[1], trim($anchor)] : [static::DEFAULT_SECTION, $parts[0], trim($anchor)];
    }

    protected function sections(): array
    {
        $result = [];
        foreach (glob($this->dir . '/' . $this->lang . '/*', GLOB_ONLYDIR) ?: [] as $path) {
            $result[] = basename($path);
        }
        usort($result, fn($a, $b) => [$a !== static::DEFAULT_SECTION, $a] <=> [$b !== static::DEFAULT_SECTION, $b]);
        return $result;
    }

    protected function pageNames(string $sec): array
    {
        if ($sec === '') {
            return array_values(array_filter(static::ROOT_PAGES, fn($name) => is_file($this->dir . '/' . $name . '.md')));
        }
        return array_map(fn($path) => basename($path, '.md'), glob($this->dir . '/' . $this->lang . '/' . $sec . '/*.md') ?: []);
    }

    protected function pagePath(string $sec, string $name): string
    {
        if ($sec === '') {
            return $name . '.md';
        }
        return $this->lang . '/' . $sec . '/' . $name . '.md';
    }

    /**
     * @return array ['title', 'lines', 'sections' => [['level', 'anchor', 'title', 'path', 'start', 'end', 'own']]]
     */
    protected function page(string $sec, string $name): array
    {
        $path = $this->dir . '/' . $this->pagePath($sec, $name);
        $real = realpath($path);
        if (!$real || !str_starts_with($real, realpath($this->dir) . '/') || !is_file($real)) {
            $pages = $this->pageNames($sec);
            throw new errorException('Page ' . $this->pagePath($sec, $name) . ' is not found'
                . ($pages ? $this->closest($name, $pages, 'pages') : '. Sections: ' . implode(', ', $this->sections())));
        }
        $mtime = filemtime($real);
        if ((static::$cache[$real][0] ?? null) === $mtime) {
            return static::$cache[$real][1];
        }
        $lines = preg_split('/\r?\n/', (string)file_get_contents($real));
        $sections = [];
        $stack = [];
        $fence = null;
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(```|~~~)/', $line, $m)) {
                $fence = $fence === null ? $m[1] : ($fence === $m[1] ? null : $fence);
                continue;
            }
            if ($fence !== null || !preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                continue;
            }
            $level = strlen($m[1]);
            $anchor = preg_match('/<a\s+name="([^"]+)"/i', $m[2], $a) ? $a[1] : '';
            $title = trim(preg_replace('/\s+/u', ' ', strip_tags($m[2])));
            while ($stack && $sections[end($stack)]['level'] >= $level) {
                $sections[array_pop($stack)]['end'] = $i;
            }
            $sections[] = ['level' => $level, 'anchor' => $anchor, 'title' => $title,
                'path' => array_map(fn($k) => $sections[$k]['title'], $stack), 'start' => $i, 'end' => count($lines)];
            $stack[] = count($sections) - 1;
        }
        foreach ($sections as $k => &$item) {
            $ownEnd = $sections[$k + 1]['start'] ?? count($lines);
            $item['own'] = implode("\n", array_slice($lines, $item['start'] + 1, $ownEnd - $item['start'] - 1));
        }
        unset($item);
        $page = ['title' => $sections[0]['title'] ?? $name, 'lines' => $lines, 'sections' => $sections];
        static::$cache[$real] = [$mtime, $page];
        return $page;
    }

    /**
     * By anchor (case-insensitive), then by the title of a heading without anchor
     */
    protected function findSection(array $page, string $anchor): ?array
    {
        $low = mb_strtolower($anchor);
        foreach ($page['sections'] as $item) {
            if ($item['anchor'] !== '' && mb_strtolower($item['anchor']) === $low) {
                return $item;
            }
        }
        foreach ($page['sections'] as $item) {
            if (mb_strtolower($item['title']) === $low) {
                return $item;
            }
        }
        return null;
    }

    protected function subsections(array $page, ?array $item, string $sec, string $name): string
    {
        /* a whole page with one title heading — its chapters */
        $tops = array_values(array_filter($page['sections'], fn($s) => $s['path'] === []));
        if (!$item && count($tops) === 1) {
            $item = $tops[0];
        }
        $level = $item['level'] ?? 0;
        $children = array_filter($page['sections'], fn($s) => $s['start'] > ($item['start'] ?? -1) && $s['start'] < ($item['end'] ?? PHP_INT_MAX)
            && $s['level'] === $this->childLevel($page, $item, $level));
        if (!$children) {
            return '';
        }
        $list = array_map(fn($s) => '`' . $this->ref($sec, $name, $s) . '` ' . $s['title'], $children);
        return "the text is long — subsections: " . implode(' · ', $list) . "\n";
    }

    protected function childLevel(array $page, ?array $item, int $level): int
    {
        $levels = [];
        foreach ($page['sections'] as $s) {
            if ($s['level'] > $level && $s['start'] > ($item['start'] ?? -1) && $s['start'] < ($item['end'] ?? PHP_INT_MAX)) {
                $levels[] = $s['level'];
            }
        }
        return $levels ? min($levels) : 0;
    }

    protected function ref(string $sec, string $name, array $item): string
    {
        return ($sec === static::DEFAULT_SECTION || $sec === '' ? '' : $sec . '/') . $name . '#' . ($item['anchor'] ?: $item['title']);
    }

    /* ======================== links ======================== */

    /**
     * [text][N] → «text (→ page#anchor)» by links.md; with $withRefs = false — only the text.
     * Not in ``` / ~~~ blocks and `inline code`: codes have $list[0][1].
     */
    public function resolveLinks(string $text, bool $withRefs = true): string
    {
        $links = $this->links();
        $lines = explode("\n", $text);
        $fence = null;
        foreach ($lines as &$line) {
            if (preg_match('/^\s*(```|~~~)/', $line, $f)) {
                $fence = $fence === null ? $f[1] : ($fence === $f[1] ? null : $fence);
                continue;
            }
            if ($fence !== null) {
                continue;
            }
            $parts = preg_split('/(`[^`]*`)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($parts as $k => &$part) {
                if ($k % 2) {
                    continue;
                }
                $part = preg_replace_callback('/(!?)\[([^\]\n]*)\]\[(\d+)\]/u', function ($m) use ($links, $withRefs) {
                    if ($m[1] === '!') {
                        return $m[0];
                    }
                    $target = $links[$m[3]] ?? null;
                    if (!$withRefs || !$target) {
                        return $m[2];
                    }
                    return $m[2] . ' (→ ' . $target . ')';
                }, $part);
            }
            unset($part);
            $line = implode('', $parts);
        }
        unset($line);
        return implode("\n", $lines);
    }

    /**
     * @return array [N => "page#anchor"] (links of the site "/page#anchor", "/page/#anchor", "/section/page#anchor"; external — as they are)
     */
    protected function links(): array
    {
        if ($this->links !== null) {
            return $this->links;
        }
        $this->links = [];
        $file = $this->dir . '/links.md';
        foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES) : [] as $line) {
            if (preg_match('/^\[(\d+)\]:\s*(\S+)/', $line, $m)) {
                $this->links[$m[1]] = preg_match('~^https?://~i', $m[2])
                    ? $m[2]
                    : str_replace('/#', '#', trim(preg_replace('~^/~', '', $m[2])));
            }
        }
        return $this->links;
    }

    /* ======================== helpers ======================== */

    protected function norm(string $text): string
    {
        return str_replace('ё', 'е', mb_strtolower($text));
    }

    protected function words(string $q): array
    {
        return array_values(array_unique(array_filter(preg_split('/[\s,;]+/u', $this->norm($q)), fn($w) => mb_strlen($w) > 1)));
    }

    protected function snippet(array $item, array $words): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $this->resolveLinks($item['own'], false)));
        $pos = false;
        foreach ($words as $word) {
            if (($pos = mb_stripos(str_replace('ё', 'е', $text), $word)) !== false) {
                break;
            }
        }
        $start = max(0, (int)$pos - 80);
        if ($start && ($space = mb_strpos($text, ' ', $start)) !== false && $space < (int)$pos) {
            $start = $space + 1;
        }
        return ($start ? '…' : '') . MCPMarkdown::cut(mb_substr($text, $start), 220);
    }

    protected function suggest(string $sec, string $name, string $anchor): string
    {
        $anchors = array_values(array_filter(array_map(fn($s) => $s['anchor'] ?: $s['title'], $this->page($sec, $name)['sections'])));
        return $this->closest($anchor, $anchors, 'sections');
    }

    protected function closest(string $needle, array $list, string $what): string
    {
        $low = mb_strtolower($needle);
        $scored = [];
        foreach ($list as $item) {
            $itemLow = mb_strtolower($item);
            $xor = $low ^ $itemLow;
            $prefix = strlen($xor) - strlen(ltrim($xor, "\0"));
            $scored[$item] = str_contains($itemLow, $low) || str_contains($low, $itemLow) ? -100 : levenshtein($low, $itemLow) - 2 * $prefix;
        }
        asort($scored);
        return '. Similar ' . $what . ': ' . implode(', ', array_slice(array_keys($scored), 0, 8)) . ' (search_docs finds by words)';
    }
}

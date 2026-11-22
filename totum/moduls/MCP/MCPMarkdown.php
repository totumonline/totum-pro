<?php

namespace totum\moduls\MCP;

use totum\common\Cycle;
use totum\common\Totum;
use totum\fieldTypes\File;
use totum\tableTypes\aTable;

/**
 * Converts web-format of ReadTableActions (fields, rows {v,v_,f,e,c,h}, params, formats) to markdown for agents.
 *
 * Cell marks: `Title [key]` select/tree, `✋(calc)` manual differs from calculated, `✊` manual equal to calculated,
 * `!ERR` error, `#block`, `#icon-name`, `#hide` (row/field hidden by table format), `#text:"…"`, `#comment:"…"`, `…` cut text, `⏎` new line
 */
class MCPMarkdown
{
    public const CATEGORIES = ['param' => 'Params', 'filter' => 'Filters', 'column' => 'Columns', 'footer' => 'Footers'];
    protected const TABLE_FORMAT_FLAGS = ['block', 'blockadd', 'blockdelete', 'blockorder', 'blockduplicate', 'hideadd'];
    protected const ROW_FORMAT_FLAGS = ['block', 'blockdelete', 'blockorder', 'blockduplicate'];
    protected const CHANGED_ROWS_AS_CARDS = 3;
    /* how to read/write values of field types (legend of get_table_schema) */
    protected const TYPE_HINTS = [
        'string' => 'string — one-line text',
        'number' => 'number — number without unit and separators (see {N …} in title)',
        'select' => 'select — key of an option, multiple — array of keys; options: get_select_options (long lists are cut — search with q, it has search modes)',
        'tree' => 'tree (select-tree) — key of a node, multiple — array of keys; options: get_select_options (parent_id — children, q — search in the whole tree)',
        'checkbox' => 'checkbox — true / false',
        'date' => 'date — "Y-m-d", datetime — "Y-m-d H:i"',
        'button' => 'button — no value: press with click_button',
        'link' => 'link→table.field (shortcut) — read-only value of the field of another table, the type before it is the type of that field',
        'text' => 'text — multiline text; cut in read_table, full value: get_value',
        'comments' => 'comments — writing a text adds a new comment, {"edit_last": "text"} edits your own last comment (if nobody has written after it); all comments: get_value. '
            . '"(N, unread M)" — M comments are not read by you (web underlines the cell): get_value / get_cell show them as #unread and mark them as read like web, '
            . 'mark_comments_read — mark as read without opening',
        'file' => 'file — list of files: names in tables, all files with their "file" — get_value, the files themselves — get_file (stdio: saved to the disk, HTTP: a link for curl; '
            . 'image "preview"/"full" — to see a picture). Writing: the whole list of the cell — {"file": "<file>"} keeps a file, new — {"path": "/abs/path"} (stdio) / '
            . '{"upload": "<id of upload_file>"} (HTTP) / {"name", "text"|"base64"} (small); [] — no files. accept — allowed types, secure — the core gives the file only with access to the cell. '
            . 'Not supported by the agent interface: versions of versioned fields, OnlyOffice editing, a new file from a docx/xlsx template',
        'unic' => 'unic — string that must not repeat in the table',
        'password' => 'password — value is hidden; writing a text sets a new password',
        'listRow' => 'listRow (data) — JSON (array/object), write JSON',
        'chart' => 'chart — read-only',
        'fieldParams' => 'fieldParams (settings of a field, data_src of tables_fields) — get_value shows the settings that work, get_field_settings (creator view) — '
            . 'all settings of the type; write {"setting": value} — only the changed settings, null — switch off',
    ];

    protected array $fields;
    protected array $control;
    protected array $tableFormat = [];

    public function __construct(protected aTable $Table, array $form, protected bool $isWrite)
    {
        $this->fields = $form['fields'] ?? [];
        $this->control = $form['control'] ?? [];
    }

    public function setTableFormat(array $f): static
    {
        $this->tableFormat = $f;
        return $this;
    }

    /* ======================== common helpers ======================== */

    public static function plain(?string $html): string
    {
        $text = str_replace(['[[', ']]'], '"', $html ?? '');
        $text = preg_replace('`<(script|style)\b.*?</\1\s*>`is', '', $text);
        $text = static::links($text);
        $text = preg_replace('`<br\s*/?>`i', "\n", $text);
        $text = preg_replace('`</(p|div|li|h\d)>`i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * <a href="url">text</a> → [text](url); text equal to url or empty → url; javascript:/# links → text only
     */
    protected static function links(string $html): string
    {
        return preg_replace_callback('`<a\b[^>]*?\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a\s*>`is', function ($m) {
            $href = trim(html_entity_decode($m[1] . $m[2] . ($m[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($m[4])));
            if ($href === '' || $href[0] === '#' || preg_match('/^javascript:/i', $href)) {
                return $text;
            }
            $href = str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $href);
            if ($text === '' || html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8') === $href) {
                return htmlspecialchars($href, ENT_QUOTES);
            }
            return '[' . str_replace(['[', ']'], ['\[', '\]'], $text) . '](' . htmlspecialchars($href, ENT_QUOTES) . ')';
        }, $html);
    }

    public static function esc(mixed $value): string
    {
        $str = static::scalar($value);
        return str_replace(["\r\n", "\n", "\r", '|'], ['⏎', '⏎', '⏎', '\|'], $str);
    }

    public static function scalar(mixed $value): string
    {
        return match (true) {
            is_null($value) => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string)$value
        };
    }

    public static function mdTable(array $header, array $rows): string
    {
        $out = '| ' . implode(' | ', $header) . " |\n|" . str_repeat('---|', count($header)) . "\n";
        foreach ($rows as $row) {
            $out .= '| ' . implode(' | ', $row) . " |\n";
        }
        return $out;
    }

    public static function cut(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
    }

    /* ======================== header ======================== */

    public function header(): string
    {
        $tableRow = $this->Table->getTableRow();
        $title = $this->tableFormat['tabletitle'] ?? $tableRow['title'];
        $line = '# ' . static::plain($title) . ' · ' . $tableRow['name'] . ' · id ' . $tableRow['id'] . ' · ' . $tableRow['type'];
        if ($Cycle = $this->Table->getCycle()) {
            $line .= ' · cycle ' . $Cycle->getId() . static::cycleName($Cycle) . ' (' . $this->Table->getTotum()->getTableRow($Cycle->getCyclesTableId())['name'] . ')';
        }
        if ($tableRow['type'] === 'tmp' && !empty($tableRow['sess_hash'])) {
            $line .= ' · sess_hash ' . $tableRow['sess_hash'];
        }
        return $line . "\n" . $this->updatedLine($this->Table->getSavedUpdated()) . ' · table: ' . $this->tableRights() . "\n"
            . $this->orderLine() . "\n"
            . (MCPTableResolver::isHiddenFromTree($this->Table->getTotum(), (int)$tableRow['id'])
                ? 'hidden from the tree: the table is opened by buttons/links of other tables — work with it only in such a window (with its args), do not open it directly' . "\n"
                : '');
    }

    /**
     * Table description (info button in web): <admin> parts only for creator, <agent> parts — for agents only
     */
    public function description(): string
    {
        $description = static::audienceText(Totum::filterAudienceText($this->Table->getTableRow()['description'] ?? '',
            $this->Table->getTotum()->getUser()->isCreator(), true));
        return $description === '' ? '' : "\n## Description\n" . $description . "\n";
    }

    /* ======================== cycles ======================== */

    /**
     * « name » of the cycle (main field of the cycles table) if it differs from its id
     */
    public static function cycleName(Cycle $Cycle): string
    {
        $name = static::plain(static::scalar($Cycle->getRowName()));
        return $name !== '' && $name !== (string)$Cycle->getId() ? ' «' . static::esc($name) . '»' : '';
    }

    /**
     * Cycles table: every row is a cycle (read_table / get_table_schema, the first page);
     * calcs table: the cycle with its tables and tab_ buttons like the tabs of web inside the cycle
     */
    public function cycleInfo(): string
    {
        if ($Cycle = $this->Table->getCycle()) {
            return static::cycleSection($this->Table->getTotum(), $Cycle, $this->Table->getTableRow()['name']);
        }
        return $this->Table->getTableRow()['type'] === 'cycles' ? $this->cyclesSection() : '';
    }

    /**
     * @param string $current name of the opened table / tab_ button
     */
    public static function cycleSection(Totum $Totum, Cycle $Cycle, string $current): string
    {
        $cyclesName = $Cycle->getCyclesTable()->getTableRow()['name'];
        $rows = [];
        foreach (MCPTableResolver::cycleTabs($Totum, $Cycle) as $tab) {
            $isTable = $tab['type'] === 'table';
            $rows[] = [$tab['name'] . ($tab['name'] === $current ? ' (current)' : ''), static::esc(static::plain($tab['title'])),
                $isTable ? 'table of the cycle' : 'tab button → opens its table',
                ($isTable ? 'read_table `' : 'click_button `') . json_encode($isTable ? ['table' => $tab['name'], 'cycle' => $Cycle->getId()]
                    : ['table' => $cyclesName, 'field' => $tab['name'], 'id' => $Cycle->getId()], JSON_UNESCAPED_UNICODE) . '`'];
        }
        $CyclesTable = $Cycle->getCyclesTable();
        $isOne = $Totum->getUser()->isOneCycleTable($CyclesTable->getTableRow()) && $CyclesTable->getUserCyclesCount() === 1;
        return "\n## Cycle " . $Cycle->getId() . static::cycleName($Cycle) . ' of ' . $cyclesName . " — its tables and tabs (like the tabs of web inside the cycle)\n"
            . ($isOne ? 'one cycle per user: this cycle is your workspace — the cycles table is not shown (like web, no way back to it)' . "\n" : '')
            . ($rows ? static::mdTable(['tab', 'title', 'kind', 'open with'], $rows) : "no tables of the cycle are available for you\n");
    }

    protected function cyclesSection(): string
    {
        $Totum = $this->Table->getTotum();
        $name = $this->Table->getTableRow()['name'];
        $tables = MCPTableResolver::cyclesTableTables($Totum, $this->Table);
        $tabs = array_keys(array_filter($this->Table->getFields(), fn($f) => MCPTableResolver::isCycleTabButton($this->Table, $f)));
        $button = $this->Table->getVisibleFields('web')['button_to_cycle'] ?? null;
        $out = "\n## Cycles\nevery row is a cycle with its own tables: "
            . ($tables ? implode(', ', array_map(fn($t, $title) => $t . ' (' . static::plain($title) . ')', array_keys($tables), $tables))
                . ' — pass them with "cycle": <row id>' : 'no tables of the cycles are available for you') . "\n";
        if ($tabs) {
            $out .= 'tabs of a cycle (tab_ buttons, web shows them as tabs inside the cycle): ' . implode(', ', $tabs)
                . ' — click_button with "id": <row id>, it opens the table of the tab' . "\n";
        }
        if ($button || $tables) {
            $out .= 'open a cycle like web (double click on the row' . ($button ? ' / button_to_cycle' : '') . '): '
                . ($button ? 'click_button `' . json_encode(['table' => $name, 'field' => 'button_to_cycle', 'id' => '<row id>'], JSON_UNESCAPED_UNICODE) . '`'
                    : 'read_table `' . json_encode(['table' => array_key_first($tables), 'cycle' => '<row id>'], JSON_UNESCAPED_UNICODE) . '`')
                . ' — the table of the cycle shows all its tables and tabs' . "\n";
        }
        if ($Totum->getUser()->isOneCycleTable($this->Table->getTableRow())) {
            $out .= 'one cycle per user (you have several cycles here, so the table is shown): adding cycles is not allowed' . "\n";
        } elseif ($this->isWrite && !empty($this->control['adding'])) {
            $out .= 'add a cycle like the add button of web: add_row `' . json_encode(['table' => $name], JSON_UNESCAPED_UNICODE)
                . '` without data — a cycles table has no insert row: the cycle is created at once and its first table is opened in the answer' . "\n";
        }
        return $out;
    }

    /**
     * Description/help already filtered by Totum::filterAudienceText: <admin> parts are marked [admin], <agent> parts are just text
     */
    public static function audienceText(?string $html): string
    {
        $html = preg_replace('`\s*<admin>(.*?)</admin>\s*`su', "\n[admin] $1\n", $html ?? '');
        return static::plain(preg_replace('`\s*<agent>(.*?)</agent>\s*`su', "\n$1\n", $html));
    }

    /**
     * Dynamic texts of setTableFormat as the agent sees them: tabletext (plain text) + tablehtml above the table, tablecomment of the rows part
     */
    public static function tableTexts(array $f): array
    {
        $str = fn(string $name) => is_scalar($f[$name] ?? null) ? (string)$f[$name] : '';
        return [
            'text' => trim(implode("\n", array_filter([trim($str('tabletext')), static::plain($str('tablehtml'))], 'strlen'))),
            'comment' => static::plain($str('tablecomment')),
        ];
    }

    public function tableText(?array $texts = null, bool $changed = false): string
    {
        $text = ($texts ?? static::tableTexts($this->tableFormat))['text'];
        if ($text === '' && !$changed) {
            return '';
        }
        return "\n## Table text" . ($changed ? ' (changed)' : '') . "\n" . ($text === '' ? '(empty)' : $text) . "\n";
    }

    public function rowsComment(?array $texts = null, bool $changed = false): string
    {
        $comment = ($texts ?? static::tableTexts($this->tableFormat))['comment'];
        if ($comment === '' && !$changed) {
            return '';
        }
        return 'rows comment' . ($changed ? ' (changed)' : '') . ': ' . ($comment === '' ? '(empty)' : static::esc($comment)) . "\n";
    }

    /**
     * For answers to writes: only the texts changed by the action
     */
    public function tableTextsChanges(array $before, array $after): string
    {
        $out = $before['text'] !== $after['text'] ? $this->tableText($after, true) : '';
        if ($before['comment'] !== $after['comment']) {
            $out .= ($out ? '' : "\n") . $this->rowsComment($after, true);
        }
        return $out;
    }

    /**
     * How rows are sorted and whether the agent can change the order
     */
    protected function orderLine(): string
    {
        $tableRow = $this->Table->getTableRow();
        if (!empty($tableRow['with_order_field'])) {
            $line = 'order: manual (n-field)';
            if ($this->isWrite && $this->Table->isUserCanAction('reorder')) {
                $line .= ' · can change: reorder_rows (ordered_ids — rearrange; ids + after_id — move after a row, after_id=0 — to the top)';
            } else {
                $line .= ' · cannot change';
            }
        } else {
            $line = 'order: by field ' . ($tableRow['order_field'] ?: 'id');
        }
        return $line . (!empty($tableRow['order_desc']) ? ', descending' : '');
    }

    public function updatedLine(?string $updated): string
    {
        $updated = json_decode($updated ?? '', true) ?? [];
        return 'updated ' . ($updated['dt'] ?? '-') . ' · code ' . ($updated['code'] ?? '-');
    }

    protected function tableRights(): string
    {
        if (!$this->isWrite) {
            return 'read-only';
        }
        $rights = [];
        foreach (['editing' => 'edit', 'adding' => 'add', 'deleting' => 'delete', 'restoring' => 'restore', 'duplicating' => 'duplicate'] as $k => $name) {
            if (!empty($this->control[$k])) {
                $rights[] = $name;
            }
        }
        if ($this->Table->isUserCanAction('reorder')) {
            $rights[] = 'reorder';
        }
        foreach (static::TABLE_FORMAT_FLAGS as $flag) {
            if (!empty($this->tableFormat[$flag])) {
                $rights[] = '#' . $flag;
            }
        }
        return $rights ? implode(' ', $rights) : '—';
    }

    /* ======================== fields ======================== */

    public function fieldsByCategory(string $category, ?array $onlyFields = null): array
    {
        $fields = [];
        foreach ($this->fields as $name => $field) {
            if ($field['category'] === $category && (!$onlyFields || in_array($name, $onlyFields))) {
                $fields[$name] = $field;
            }
        }
        return $fields;
    }

    public function fieldTitle(array $field): string
    {
        $title = $this->tableFormat['fieldtitle'][$field['name']] ?? $field['title'];
        $title = static::esc(static::plain($title));
        if ($pattern = $this->valuePattern($field)) {
            $title .= ' ' . static::esc($pattern);
        }
        if (!empty(static::audienceText($field['help'] ?? ''))) {
            $title .= ' #info';
        }
        if (!empty($field['icon']) && is_string($field['icon'])) {
            $title .= ' #icon-' . $field['icon'];
        }
        if (!empty($this->tableFormat['fieldhide'][$field['name']])) {
            $title .= ' #hide';
        }
        if ($place = $this->buttonPlace($field['name'])) {
            $title .= ' ' . $place;
        }
        return $title;
    }

    /* setTableFormat moves params / footer buttons to places of web for users (not creators) — the admin interface marks them so */
    public const BUTTON_PLACES = [
        'buttons' => ['[B]', 'the bar of buttons above the table (format buttons)'],
        'extbuttons' => ['[E]', 'the menu of extra buttons ☰ (format extbuttons)'],
        'printbuttons' => ['[P]', 'the print menu (format printbuttons)'],
        'dotbuttons' => ['[D]', 'the ⋯ menu at the bottom right (format dotbuttons)'],
    ];

    /**
     * Mark of a params / footer button moved by the table format to a place of web ([B] [E] [P] [D]); '' — in its place
     */
    public function buttonPlace(string $name): string
    {
        $marks = [];
        foreach (static::BUTTON_PLACES as $format => [$mark]) {
            if (is_array($this->tableFormat[$format] ?? null) && in_array($name, $this->tableFormat[$format], true)) {
                $marks[] = $mark;
            }
        }
        return implode(' ', $marks);
    }

    /**
     * How the value is shown in web: unit before/after, money prefix/postfix ("positive**negative" variants like App.numberFormat).
     * N is the raw value (it is written as is, without unit and separators). '' — nothing to show.
     * Examples: {N pcs}, {$ N}, {N ₽ / −: (N ₽)}
     */
    public function valuePattern(array $field): string
    {
        $unit = is_string($field['unitType'] ?? null) ? trim($field['unitType']) : '';
        $currency = $field['type'] === 'number' && !empty($field['currency']);
        $prefix = $currency && is_string($field['prefix'] ?? null) ? $field['prefix'] : '';
        $postfix = $currency && is_string($field['postfix'] ?? null) ? $field['postfix'] : '';
        if ($unit === '' && $prefix === '' && $postfix === '') {
            return '';
        }
        $part = fn(string $str, bool $negative) => str_contains($str, '**') ? explode('**', $str, 2)[$negative ? 1 : 0] : $str;
        $build = function (bool $negative) use ($unit, $prefix, $postfix, $part, $field) {
            /* prefix with negative variant replaces the minus */
            $value = $part($prefix, $negative) . ($negative && !str_contains($prefix, '**') ? '-N' : 'N') . $part($postfix, $negative);
            if ($unit !== '') {
                $value = !empty($field['before']) ? $unit . ' ' . $value : $value . ' ' . $unit;
            }
            return $value;
        };
        $positive = $build(false);
        $negative = $build(true);
        $hasNegativeVariant = str_contains($prefix . $postfix, '**');
        return '{' . $positive . ($hasNegativeVariant ? ' / −: ' . $negative : '') . '}';
    }

    /**
     * @param string $mode table | insert — rights in the insert row: add (set with insert_row), click — buttons active in the insert row
     *                     | panel — rights in the edit panel: edit (set with edit_panel), click
     */
    public function fieldRights(array $field, string $mode = 'table'): string
    {
        $rights = [];
        $insertRow = $mode === 'insert';
        $canAdd = $this->isWrite && $field['category'] === 'column' && !empty($this->control['adding']);
        if ($this->isWrite && !$insertRow) {
            if (!empty($this->control['editing']) && ($field['editable'] ?? false) && $field['type'] !== 'button') {
                $rights[] = 'edit';
            }
        }
        if ($mode !== 'panel' && $canAdd && ($field['insertable'] ?? false) && $field['type'] !== 'button') {
            $rights[] = 'add';
        }
        if (!empty($field['code'])) {
            $rights[] = !empty($field['codeOnlyInAdd']) ? 'calc-on-add' : 'calc';
        }
        if ($insertRow) {
            if ($canAdd && $field['type'] === 'button' && !empty($field['buttonActiveOnInsert'])) {
                $rights[] = 'click';
            }
        } elseif ($field['type'] === 'button' || !empty($field['CodeActionOnClick'])) {
            if ($this->isWrite || !empty($field['pressableOnOnlyRead']) || !empty($field['AllowActionClickOnRead'])) {
                $rights[] = 'click';
            }
            if ($mode !== 'panel' && $canAdd && $field['type'] === 'button' && !empty($field['buttonActiveOnInsert'])) {
                $rights[] = 'add-click';
            }
        }
        if (!empty($field['required'])) {
            $rights[] = 'required';
        }
        return $rights ? implode(' ', $rights) : '—';
    }

    /**
     * @param bool $short for read_table / cards: without display details (value pattern, money, button text, regexp)
     */
    public function fieldType(array $field, bool $short = false, bool $withRegexp = false): string
    {
        $type = $field['type'];
        switch ($type) {
            case 'select':
            case 'tree':
                if (!empty($field['selectTable'])) {
                    $type .= '→' . $field['selectTable'];
                }
                break;
            case 'file':
                if (!empty($field['secureFile'])) {
                    $type .= ' secure';
                }
                if (!empty($field['versioned'])) {
                    $type .= ' versioned';
                }
                if (!$short && !empty($field['accept']) && is_string($field['accept'])) {
                    $type .= ' accept ' . $field['accept'];
                }
                break;
            case 'number':
                if (isset($field['dectimalPlaces']) && $field['dectimalPlaces'] !== '') {
                    $type .= '(' . $field['dectimalPlaces'] . ')';
                }
                if ($short) {
                    break;
                }
                if ($pattern = $this->valuePattern($field)) {
                    $type .= ' ' . $pattern;
                }
                if (!empty($field['currency'])) {
                    $money = [];
                    foreach (['thousandthSeparator' => 'thousands', 'dectimalSeparator' => 'decimal'] as $param => $title) {
                        if (isset($field[$param]) && is_string($field[$param]) && $field[$param] !== '') {
                            $money[] = $title . ' "' . str_replace('**', '" / −: "', $field[$param]) . '"';
                        }
                    }
                    $type .= ' money' . ($money ? '(' . implode(', ', $money) . ')' : '');
                }
                if (!empty($field['step'])) {
                    $type .= ' step ' . $field['step'];
                }
                break;
            case 'date':
                $type = !empty($field['dateTime']) ? 'datetime (Y-m-d H:i)' : 'date (Y-m-d)';
                break;
            case 'text':
                if (!empty($field['textType']) && $field['textType'] !== 'text') {
                    $type .= '/' . $field['textType'];
                }
                break;
            case 'button':
                if (!$short && !empty($field['buttonText'])) {
                    $type .= ' "' . static::esc($field['buttonText']) . '"';
                }
                break;
        }
        if (!$short || $withRegexp) {
            $type .= $this->regexpPart($field);
        }
        if (!empty($field['multiple'])) {
            $type .= ' multiple';
        }
        /* link (shortcut) gets the type of the linked field from the core */
        if (!empty($field['linkTableName'])) {
            $type = ($type === 'link' ? '' : $type . ' ') . 'link→' . $field['linkTableName']
                . (!empty($field['linkFieldName']) ? '.' . $field['linkFieldName'] : '');
        }
        if ($short) {
            return $type;
        }
        if ($field['category'] === 'filter' && !empty($field['column'])) {
            $type .= ', filters column ' . $field['column'];
        } elseif ($field['category'] === 'footer' && !empty($field['column'])) {
            $type .= ', under column ' . $field['column'];
        }
        return $type;
    }

    /**
     * Input validation format of string/unic/number: checked by the core (and by MCP before the action) on add and edit
     */
    protected function regexpPart(array $field): string
    {
        if (empty($field['regexp']) || !is_string($field['regexp']) || !in_array($field['type'], ['string', 'unic', 'number'])) {
            return '';
        }
        return ' regexp /' . $field['regexp'] . '/'
            . (!empty($field['regexpErrorText']) && is_string($field['regexpErrorText']) ? ' "' . static::plain($field['regexpErrorText']) . '"' : '');
    }

    /* ======================== cells ======================== */

    /**
     * @param array $field
     * @param array|null $cell {v, v_, f, e, c, h}
     * @param mixed $rawValue value from tbl for detecting cut text
     */
    public function cell(array $field, ?array $cell, mixed $rawValue = null): string
    {
        if (is_null($cell)) {
            return '';
        }
        $f = is_array($cell['f'] ?? null) ? $cell['f'] : [];
        $value = $this->cellValue($field, $cell, $rawValue);

        $marks = [];
        if (!empty($cell['h'])) {
            /* in web view "c" is removed when it is equal to "v" */
            if (key_exists('c', $cell) && static::scalar($cell['c']) !== static::scalar($cell['v'] ?? null)) {
                $marks[] = $field['type'] === 'text' ? '✋' : '✋(' . static::esc($cell['c']) . ')';
            } else {
                $marks[] = '✊';
            }
        }
        if (!empty($cell['e'])) {
            $marks[] = '!ERR ' . static::esc(static::plain(static::scalar($cell['e'])));
        }
        $marks = array_merge($marks, $this->formatMarks($f, $value === ''));

        return trim($value . ($marks ? ' ' . implode(' ', $marks) : ''));
    }

    public function formatMarks(array $f, bool $isEmpty = false): array
    {
        $marks = [];
        if (!empty($f['block'])) {
            $marks[] = '#block';
        }
        if (!empty($f['icon']) && is_string($f['icon'])) {
            $marks[] = '#icon-' . preg_replace('/^(fa\s+)?fa-/', '', $f['icon']);
        }
        if (isset($f['text']) && $f['text'] !== '' && is_scalar($f['text'])) {
            $marks[] = (!empty($f['textasvalue']) ? '#textasvalue:"' : '#text:"') . static::esc(static::plain((string)$f['text'])) . '"';
        }
        if (!empty($f['comment']) && is_scalar($f['comment'])) {
            $marks[] = '#comment:"' . static::esc(static::plain((string)$f['comment'])) . '"';
        }
        if ($isEmpty && !empty($f['placeholder']) && is_scalar($f['placeholder'])) {
            $marks[] = '#placeholder:"' . static::esc((string)$f['placeholder']) . '"';
        }
        return $marks;
    }

    protected function cellValue(array $field, array $cell, mixed $rawValue): string
    {
        $v = $cell['v'] ?? null;
        switch ($field['type']) {
            case 'select':
            case 'tree':
                return $this->selectValue($v, $cell['v_'] ?? null, !empty($field['multiple']));
            case 'checkbox':
                return $v ? 'true' : 'false';
            case 'password':
                return $v ? '***' : '';
            case 'button':
                return '[' . static::esc($field['buttonText'] ?? $field['title']) . ']';
            case 'chart':
                return '(chart)';
            case 'fieldParams':
                return static::esc(is_array($v) ? MCPFieldSettings::summary($v) : $v);
            case 'file':
                return static::esc(implode(', ', array_map(fn($file) => is_array($file) ? ($file['name'] ?? '') : static::scalar($file), (array)$v)));
            case 'comments':
                return $this->commentsValue($v);
            case 'text':
                $str = static::scalar($v);
                if (is_string($rawValue) && mb_strlen($rawValue) > mb_strlen($str) - 3 && str_ends_with($str, '...') && $rawValue !== $str) {
                    $str = mb_substr($str, 0, -3) . '…';
                }
                return static::esc($str);
            default:
                return static::esc($v);
        }
    }

    protected function selectValue($v, $v_, bool $multiple): string
    {
        if (!is_array($v_)) {
            return static::esc($v);
        }
        $one = function ($key, $title, $deleted) {
            $key = static::scalar($key);
            $title = static::scalar($title);
            $str = static::esc($title);
            if ($key !== '' && $key !== $title) {
                $str .= ' [' . static::esc($key) . ']';
            }
            if ($deleted) {
                $str .= ' #deleted';
            }
            return $str;
        };
        if ($multiple) {
            if (!is_array($v)) {
                return static::esc($v);
            }
            $list = [];
            foreach (array_values($v_) as $i => $item) {
                $list[] = $one($item[2] ?? $v[$i] ?? '', $item[0] ?? '', !empty($item[1]));
            }
            return implode(', ', $list);
        }
        if (is_null($v)) {
            return static::esc($v_[0] ?? '');
        }
        return $one($v, $v_[0] ?? $v, !empty($v_[1]));
    }

    protected function commentsValue($v): string
    {
        if (!is_array($v)) {
            return static::esc($v);
        }
        if (key_exists('n', $v)) {
            $str = '(' . $v['n'] . (!empty($v['notViewed']) ? ', unread ' . $v['notViewed'] : '') . ')';
            $last = !empty($v['all']) ? end($v['c']) : ($v['c'] ?? null);
            if (is_array($last) && $last) {
                $str .= ' ' . static::esc(($last[0] ?? '') . ' ' . ($last[1] ?? '') . ': ' . ($last[2] ?? '')) . (!empty($last[3]) ? ' (edited)' : '');
            }
            return $str;
        }
        return static::esc(implode("\n", array_map(fn($c) => is_array($c) ? (($c[0] ?? '') . ' ' . ($c[1] ?? '') . ': ' . ($c[2] ?? '')
            . (!empty($c[3]) ? ' (edited)' : '') . (($c[4] ?? null) === 'editable' ? ' #editable' : '')) : static::scalar($c), $v)));
    }

    /**
     * A cell for the Excel export like the copy text of web: titles of selects, names of files, the last comment; texts — full (raw value)
     * @param array|null $cell formatted cell (web)
     * @param mixed $raw value of the row in the table
     */
    public static function exportValue(array $field, ?array $cell, mixed $raw): string
    {
        $f = is_array($cell['f'] ?? null) ? $cell['f'] : [];
        if (!empty($f['textasvalue']) && isset($f['text']) && is_scalar($f['text'])) {
            return (string)$f['text'];
        }
        $v = $cell['v'] ?? null;
        switch ($field['type']) {
            case 'select':
            case 'tree':
                $titles = is_array($cell['v_'] ?? null) ? $cell['v_'] : null;
                if (!$titles) {
                    return static::scalar($v);
                }
                return !empty($field['multiple'])
                    ? implode(', ', array_map(fn($item) => static::scalar(is_array($item) ? ($item[0] ?? '') : $item), $titles))
                    : static::scalar($titles[0] ?? $v);
            case 'checkbox':
                return $v ? 'true' : 'false';
            case 'password':
            case 'button':
            case 'chart':
                return '';
            case 'file':
                return implode(', ', array_map(fn($file) => is_array($file) ? ($file['name'] ?? '') : static::scalar($file), (array)$raw));
            case 'comments':
                $last = is_array($raw) && $raw ? end($raw) : null;
                return is_array($last) ? static::scalar($last[2] ?? '') : '';
            default:
                return static::scalar($raw ?? $v);
        }
    }

    /**
     * Files of a cell: name · size · mime · file (the key for get_file and for keeping the file in a new value)
     */
    public static function filesList(array $files, array $field): string
    {
        $lines = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }
            $lines[] = '- ' . static::esc($file['name'] ?? '') . ' · ' . MCPFiles::size($file['size'] ?? 0) . (!empty($file['mime']) ? ' · ' . $file['mime'] : '')
                . ' · file `' . ($file['file'] ?? '') . '`' . (File::isImage($file['name'] ?? '') ? ' · image' : '')
                . (!empty($file['versions_count']) ? ' · versions ' . $file['versions_count'] : '') . (!empty($file['e']) ? ' !ERR ' . static::esc($file['e']) : '');
        }
        if (!$lines) {
            return 'no files';
        }
        return implode("\n", $lines) . "\n" . 'get the files with get_file (field "' . $field['name'] . '"' . (count($lines) > 1 ? ', "file" — one of them' : '')
            . (array_filter($files, fn($f) => is_array($f) && File::isImage($f['name'] ?? '')) ? '; image "preview" / "full" — to see a picture' : '')
            . '); to keep a file in a new value of the field pass {"file": "<file>"}';
    }

    /**
     * linkToFileUpload: the browser opens its file dialog (accept = type, several files if limit > 1)
     */
    public static function fileUploadWindow(array $meta, array $call, bool $isLocal): string
    {
        $type = $meta['type'] === '' || $meta['type'] === '*' ? 'any' : $meta['type'];
        return "\n---\n## Opened window (file upload): " . static::plain($meta['title']) . "\n"
            . 'files: up to ' . $meta['limit'] . ' · types: ' . $type . "\n"
            . 'upload with submit_file_upload `' . json_encode($call + ['files' => [$isLocal ? ['path' => '/abs/path/file'] : ['upload' => '<id of upload_file>']]],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '` — the code of the button gets the files as $#input. The hash works once.' . "\n"
            . ($isLocal ? 'files: {"path": "/abs/path"} — a file on the disk of the server'
                : 'files: {"upload": "<id>"} — a file of your machine: get a link with upload_file, send the file with its curl command in your terminal, pass its upload id here')
            . ', or {"name": "a.txt", "text"|"base64": "…"} for small files' . "\n"
            . static::refreshLine($meta['refresh'] ?? false);
    }

    /**
     * All comments of a field (getFullValue of the core: [date, user, text, edited, 'editable' — the last comment of the user])
     */
    /**
     * @param int $unread the last comments not read by the user before this answer (the core marks them as read when the comments are opened)
     */
    public static function commentsList(array $comments, string $fieldName, int $unread = 0): string
    {
        $lines = [];
        $editable = false;
        $first = count($comments) - $unread;
        foreach (array_values($comments) as $i => $c) {
            $editable = ($c[4] ?? null) === 'editable';
            $lines[] = '- ' . ($c[0] ?? '') . ' ' . ($c[1] ?? '') . ': ' . static::scalar($c[2] ?? '') . (!empty($c[3]) ? ' (edited)' : '')
                . ($editable ? ' #editable' : '') . ($unread && $i >= $first ? ' #unread' : '');
        }
        if (!$lines) {
            return 'no comments';
        }
        return implode("\n", $lines) . "\n"
            . ($unread ? '#unread — ' . $unread . ' comment(s) you had not read (web underlines such cells): now they are marked as read, like web when the comments are opened' . "\n" : '')
            . ($editable
                ? '#editable — your last comment, it can be edited (like web) while nobody has written after it: '
                . 'edit / edit_panel with {"' . $fieldName . '": {"edit_last": "new text"}}; a text value adds a new comment'
                : 'a text value of edit adds a new comment (only your own last comment can be edited)');
    }

    /* ======================== blocks ======================== */

    /**
     * Params/filters/footers or one row as card: | Title | name | rights | value |
     */
    /**
     * @param string $mode table | insert (insert row) | panel (edit panel) — rights of the mode, input format (regexp) in the type
     * @param array $titles titles of fields set by the opening code (linkToPanel titles)
     */
    public function card(array $fields, array $values, array $rawValues = [], string $mode = 'table', array $titles = []): string
    {
        $rows = [];
        foreach ($fields as $name => $field) {
            if (!key_exists($name, $values)) {
                continue;
            }
            $title = isset($titles[$name]) && is_scalar($titles[$name]) ? static::esc(static::plain((string)$titles[$name])) : $this->fieldTitle($field);
            $rows[] = [$title, $name, static::esc($this->fieldType($field, true, $mode !== 'table')), $this->fieldRights($field, $mode),
                $this->cell($field, is_array($values[$name]) && key_exists('v', $values[$name]) ? $values[$name] : ['v' => $values[$name]],
                    $rawValues[$name]['v'] ?? null)];
        }
        return $rows ? static::mdTable(['Title', 'name', 'type', 'rights', 'value'], $rows) : '';
    }

    public function paramsSections(array $params, ?array $onlyFields = null): string
    {
        $out = '';
        $raw = $this->Table->getTbl()['params'] ?? [];
        foreach (['param' => 'Params', 'filter' => 'Filters'] as $category => $title) {
            if ($card = $this->card($this->fieldsByCategory($category, $onlyFields), $params, $raw)) {
                $out .= "\n## $title\n" . $card;
            }
        }
        return $out;
    }

    public function footerSection(array $params, ?array $onlyFields = null): string
    {
        $card = $this->card($this->fieldsByCategory('footer', $onlyFields), $params, $this->Table->getTbl()['params'] ?? []);
        return $card ? "\n## Footers\n" . $card : '';
    }

    /**
     * Rows as table with 4-lines header: titles / name / type / rights
     */
    public function rowsTable(array $rows, ?array $onlyFields = null): string
    {
        $fields = $this->fieldsByCategory('column', $onlyFields);
        $header = ['id'];
        $names = ['name'];
        $types = ['type'];
        $rights = ['access'];
        foreach ($fields as $name => $field) {
            $header[] = $this->fieldTitle($field);
            $names[] = $name;
            $types[] = static::esc($this->fieldType($field, true));
            $rights[] = $this->fieldRights($field);
        }
        $lines = [$names, $types, $rights];
        $tblRows = $this->Table->getTbl()['rows'] ?? [];
        foreach ($rows as $row) {
            $lines[] = $this->rowLine($row, $fields, $tblRows[$row['id']] ?? []);
        }
        return static::mdTable($header, $lines);
    }

    protected function rowLine(array $row, array $fields, array $raw): array
    {
        $line = [$this->rowId($row)];
        foreach ($fields as $name => $field) {
            $line[] = $this->cell($field, $row[$name] ?? null, $raw[$name]['v'] ?? null);
        }
        return $line;
    }

    public function rowId(array $row): string
    {
        $id = (string)($row['id'] ?? '');
        $f = is_array($row['f'] ?? null) ? $row['f'] : [];
        foreach (static::ROW_FORMAT_FLAGS as $flag) {
            if (!empty($f[$flag])) {
                $id .= ' #' . $flag;
            }
        }
        $hideRows = (array)($this->tableFormat['hideRows'] ?? []);
        $showRows = $this->tableFormat['showRows'] ?? null;
        if (in_array($row['id'] ?? null, $hideRows) || (is_array($showRows) && !in_array($row['id'] ?? null, $showRows))) {
            $id .= ' #hide';
        }
        if (!empty($f['rowcomment']) && is_scalar($f['rowcomment'])) {
            $id .= ' #comment:"' . static::esc(static::plain((string)$f['rowcomment'])) . '"';
        }
        return $id;
    }

    public function rowCard(array $row, ?array $onlyFields = null, string $title = ''): string
    {
        $raw = $this->Table->getTbl()['rows'][$row['id'] ?? 0] ?? [];
        return "\n### " . ($title ?: '#' . $this->rowId($row)) . "\n"
            . $this->card($this->fieldsByCategory('column', $onlyFields), $row, $raw);
    }

    /**
     * Insert row of web (insert_row): values as calculated by the core for the current state, rights of the insert row
     * @param array $tableArgs table/cycle/sess_hash/filters for the next calls
     */
    public function insertRowCard(array $row, string $hash, array $tableArgs, bool $isNew = false): string
    {
        $args = json_encode($tableArgs + ['insert_hash' => $hash], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return "\n## Insert row" . ($isNew ? ' (new)' : '') . ' · insert_hash ' . $hash . "\n"
            . 'Not added to the table yet. Values are recalculated by the code after every change like in web. Pass `' . $args . '` to:' . "\n"
            . "- insert_row — data: set values (fields with right add), reset: return calculated values;\n"
            . "- get_select_options — options of a select for the current state of the row;\n"
            . "- click_button — buttons with right click (active in the insert row); their code may change the row;\n"
            . "- add_row — save the row to the table.\n\n"
            . $this->card($this->fieldsByCategory('column'), $row, [], 'insert');
    }

    /**
     * Edit panel of a row (edit_panel): values with the unsaved changes as calculated by the core, rights of the panel
     * @param array $panel {row, hash, id, changed}
     * @param array $fields only these fields are shown (linkToPanel fields)
     * @param array $titles titles of fields (linkToPanel titles)
     */
    public function panelCard(array $panel, array $tableArgs, bool $isNew = false, array $fields = [], array $titles = []): string
    {
        $args = json_encode($tableArgs + ['panel_hash' => $panel['hash']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row = $panel['row'];
        return "\n## Panel" . ($isNew ? ' (new)' : '') . ' · row ' . $this->rowId($row) . ' · panel_hash ' . $panel['hash'] . "\n"
            . ($panel['changed'] ? 'not saved changes: ' . implode(', ', $panel['changed']) . ' — values below are calculated with them'
                : 'no changes yet') . '. Values are recalculated by the code after every change like in web. Pass `' . $args . '` to:' . "\n"
            . "- edit_panel — data: set values (fields with right edit), reset: return calculated values, save: true — save all changes at once;\n"
            . "- get_select_options — options of a select for the current state of the panel;\n"
            . "- click_button — buttons of the row; their code sees the not saved values of the panel as #pnl.field.\n"
            . ($fields ? 'the panel shows the fields: ' . implode(', ', $fields) . "\n" : '') . "\n"
            . $this->card($this->fieldsByCategory('column', $fields ?: null), $row, [], 'panel', $titles);
    }

    /* ======================== cell (right-click panel) ======================== */

    public function cellPanel(array $data, ?int $id): string
    {
        $field = $this->fields[$data['field']['name']] ?? $data['field'];
        $cell = $data['cell'];
        $f = is_array($cell['f'] ?? null) ? $cell['f'] : [];

        $where = static::CATEGORIES[$field['category']] ?? $field['category'];
        if ($field['category'] === 'column') {
            $where = 'row ' . $this->rowId(['id' => $id, 'f' => $data['rowF']]);
            $mainField = $this->Table->getFields()[$this->Table->getTableRow()['main_field'] ?? ''] ?? null;
            if ($mainField && $data['rowTitle']) {
                $where .= ' «' . $this->cellValue($mainField, $data['rowTitle'], null) . '»';
            }
        }

        $out = "\n## Cell: " . $this->fieldTitle($field) . ' · ' . $field['name'] . ' · ' . $where . "\n";
        $out .= 'type: ' . $this->fieldType($field) . ' · rights: ' . $this->fieldRights($field) . "\n";

        if (!empty($cell['h'])) {
            $out .= key_exists('c', $cell) && static::scalar($cell['c']) !== static::scalar($cell['v'] ?? null)
                ? 'manual value ✋, calculated: ' . ($field['type'] === 'text' ? '(text changed)' : $this->cellValue($field,
                        ['v' => $cell['c'], 'v_' => $cell['c_'] ?? null], null)) . "\n"
                : "manual value ✊ — equal to calculated\n";
        }
        if (!empty($cell['e'])) {
            $out .= '!ERR ' . static::plain(static::scalar($cell['e'])) . "\n";
        }
        if ($marks = $this->formatMarks($f, ($cell['v'] ?? null) === null || $cell['v'] === '' || $cell['v'] === [])) {
            $out .= 'format: ' . implode(' ', $marks) . "\n";
        }
        if (isset($f['text']) && $f['text'] !== '' && is_scalar($f['text'])) {
            $out .= (!empty($f['textasvalue'])
                    ? 'format text (web shows it and uses it as the value of the cell): '
                    : 'format text (web shows it instead of the value): ') . static::plain((string)$f['text']) . "\n";
        }
        if (!empty($f['comment']) && is_scalar($f['comment'])) {
            $out .= 'format comment (hint to the cell): ' . static::plain((string)$f['comment']) . "\n";
        }

        $out .= "\n### Value\n" . $this->fullValue($field, $cell, $data['full']) . "\n";

        if ($help = static::audienceText($field['help'] ?? '')) {
            $out .= "\n### Field description\n" . $help . "\n";
        }
        if ($panel = $this->panelFormats($data['panel'])) {
            $out .= "\n### Panel\n" . $panel;
        }
        if ($data['previews']) {
            $rows = [];
            foreach ($data['previews'] as $preview) {
                $rows[] = [static::esc(static::plain(static::scalar($preview[0] ?? ''))), static::esc(static::plain(static::scalar($preview[1] ?? '')))];
            }
            $out .= "\n### Preview\n" . static::mdTable(['Title', 'value'], $rows);
        }
        if ($data['log'] === false) {
            return $out;
        }
        if (is_string($data['log'])) {
            $out .= "\n### Log of manual changes\nnot available for this field: " . $data['log'] . "\n";
        } elseif (!$data['log']['count']) {
            $out .= "\n### Log of manual changes\nno manual changes in the field\n";
        } else {
            $actions = [1 => 'added', 2 => 'changed', 3 => 'reset to calculated', 4 => 'deleted', 5 => 'pinned', 6 => 'system', 7 => 'restored'];
            $rows = [];
            foreach ($data['log']['rows'] as $log) {
                $value = static::scalar($log['value']);
                $entered = static::scalar($log['modify_text'] ?? '');
                $rows[] = [$log['dt_modify'], static::esc($log['user_modify']), $actions[$log['action']] ?? $log['action'],
                    static::esc(static::cut($value, 300)), $entered === $value ? '' : static::esc(static::cut($entered, 300))];
            }
            $out .= "\n### Log of manual changes (" . count($rows) . ' of ' . $data['log']['count'] . ")\n"
                . static::mdTable(['when', 'who', 'action', 'value after change', 'entered (if different)'], $rows);
        }
        return $out;
    }

    protected function fullValue(array $field, array $cell, mixed $full): string
    {
        switch ($field['type']) {
            case 'text':
                $value = static::scalar($full ?? $cell['v'] ?? '');
                return $value === '' ? '(empty)' : $value;
            case 'comments':
                return static::commentsList((array)$full, $field['name'], (int)($cell['v']['notViewed'] ?? 0));
            case 'file':
                return static::filesList((array)$full, $field);
            case 'listRow':
                $value = $full ?? $cell['v'] ?? null;
                return $value === null || $value === [] || $value === '' ? '(empty)' : static::scalar($value);
            default:
                $value = $this->cellValue($field, $cell, null);
                return $value === '' ? '(empty)' : $value;
        }
    }

    /**
     * p-sections of the field format: text / html / img / buttons
     */
    protected function panelFormats(?array $panel): string
    {
        $lines = [];
        foreach ($panel['rows'] ?? [] as $section) {
            $value = $section['value'] ?? null;
            switch ($section['type'] ?? '') {
                case 'buttons':
                    foreach ((array)$value as $button) {
                        $lines[] = '- button: ' . static::plain(static::scalar($button['text'] ?? ''))
                            . ' → click_panel_button(hash "' . ($panel['hash'] ?? '') . '", index "' . ($button['ind'] ?? '') . '")'
                            . (static::isRefresh($button['refresh'] ?? false) ? ' · refresh: ' . static::scalar($button['refresh']) . ' (re-read the table after it)' : '');
                    }
                    break;
                case 'img':
                    $lines[] = '- img: ' . static::scalar(is_array($value) ? ($value['src'] ?? $value) : $value);
                    break;
                default:
                    $lines[] = '- ' . ($section['type'] ?? 'text') . ': ' . static::plain(static::scalar($value));
            }
        }
        return $lines ? implode("\n", $lines) . "\n" : '';
    }

    /* ======================== schema ======================== */

    /**
     * @param array $export mcpExportInfo: csv / xlsx export, import of web
     */
    public function schema(array $export = []): string
    {
        $tableRow = $this->Table->getTableRow();
        $out = $this->header() . $this->description() . $this->cycleInfo();
        if (!empty($tableRow['pagination']) && $tableRow['pagination'] !== '0/0') {
            $out .= "\npagination " . $tableRow['pagination'] . "\n";
        }
        if ($formats = array_keys(array_filter(array_intersect_key($export, ['csv' => 1, 'xlsx' => 1])))) {
            $out .= "\nexport: export_table — " . implode(', ', $formats) . ' (like the export buttons of web)' . "\n";
        }
        if (!empty($export['import'])) {
            $out .= ($formats ? '' : "\n") . "import: the csv / xlsx import of web is not supported by the agent interface — change the data with edit / add_row\n";
        }
        $marks = [];
        foreach (static::CATEGORIES as $category => $title) {
            $rows = [];
            foreach ($this->fieldsByCategory($category) as $name => $field) {
                $rights = $this->fieldRights($field);
                if (str_starts_with($rights, 'edit') && $field['type'] !== 'button' && $category !== 'filter') {
                    /* web: batch edit of several rows (editGroup) / together with other columns (editGroupMultiColumns) */
                    if ($category === 'column' && empty($field['editGroup'])) {
                        $rights .= ' one-row';
                        $marks['one-row'] = 'one-row — batch edit is off: change this field in one row per call and wait for the answer (several rows at once — error, heavy for the server)';
                    }
                    if (!empty($field['editGroupMultiColumns'])) {
                        $rights .= ' multi-field';
                        $marks['multi-field'] = 'multi-field — can be changed together with other fields in one call; fields without it are better changed alone '
                            . '(their action code may change other fields — a warning in the answer)';
                    }
                }
                foreach ($this->featureMarks($field) as $mark => $legend) {
                    $rights = ($rights === '—' ? '' : $rights . ' ') . $mark;
                    $marks[$mark] = $legend;
                }
                foreach (static::BUTTON_PLACES as $format => [$mark, $place]) {
                    if (str_contains($this->buttonPlace($name), $mark)) {
                        $marks[$mark] = $mark . ' (in the title) — web shows this button for users not in the ' . ($category === 'param' ? 'header' : 'footer')
                            . ' but in ' . $place . ': for you it is the usual button of the ' . ($category === 'param' ? 'params' : 'footers') . ' — click_button without id';
                    }
                }
                $rows[] = [$this->fieldTitle($field), $name, static::esc($this->fieldType($field)), $rights,
                    static::esc(static::cut(static::audienceText($field['help'] ?? ''), 1000))];
            }
            if ($rows) {
                $out .= "\n## $title\n" . static::mdTable(['Title', 'name', 'type', 'rights', 'description'], $rows);
            }
        }
        if ($marks) {
            $out .= "\n## Marks\n- " . implode("\n- ", $marks) . "\n";
        }
        $types = array_column($this->fields, 'type');
        if (array_filter(array_column($this->fields, 'linkTableName'))) {
            $types[] = 'link';
        }
        $hints = array_intersect_key(static::TYPE_HINTS, array_flip($types));
        if ($hints) {
            $out .= "\n## Field types\n- " . implode("\n- ", $hints) . "\n";
        }
        return $out;
    }

    /**
     * What the cell has besides its value (get_table_schema): context panel, buttons with checked rows, log
     * @return array [mark => legend]
     */
    protected function featureMarks(array $field): array
    {
        $marks = [];
        if (!empty($field['formatInPanel'])) {
            $marks['panel'] = 'panel — the cell has a context panel (right click in web) with texts / buttons: get_cell shows it, its buttons — click_panel_button';
        }
        if ($field['type'] === 'button' && !empty($field['openContextPanel'])) {
            $marks['context-panel'] = 'context-panel — after pressing the button web opens the context panel of its cell: the answer of click_button shows it (with its buttons)';
        }
        if (str_contains(json_encode($this->Table->getFields()[$field['name']]['codeAction'] ?? '', JSON_UNESCAPED_UNICODE), '$#ids')) {
            $marks['checked-rows'] = 'checked-rows — the code of the button works with the rows checked with checkmarks in web ($#ids): pass them in checked_ids of click_button';
        }
        if (MCPTableResolver::isCycleTabButton($this->Table, $this->Table->getFields()[$field['name']] ?? $field)) {
            $marks['cycle-tab'] = 'cycle-tab — tab_ button: web shows it as a tab inside the cycle (not as a column for not creators); click_button with "id" = cycle opens the table of the tab';
        }
        if (!empty($field['logButton']) && $this->Table->getTableRow()['type'] !== 'tmp') {
            $marks['log'] = 'log — the log of manual changes of the field is available: get_cell (section "Log of manual changes"); fields without the mark have no log';
        }
        return $marks;
    }

    /* ======================== results of actions ======================== */

    public function changes(array $result, bool $isDryRun = false): string
    {
        $out = $this->updatedLine($result['updated'] ?? $this->Table->getSavedUpdated()) . "\n";
        foreach (['added' => 'added', 'deleted' => 'deleted', 'restored' => 'restored'] as $k => $title) {
            if (!empty($result[$k])) {
                $out .= $title . ': ' . implode(', ', $result[$k]) . "\n";
            }
        }
        if (!empty($result['reordered'])) {
            $out .= 'reordered' . (is_array($result['reordered']) ? ': ' . implode(', ', $result['reordered']) : '') . "\n";
        }
        if ($params = $result['params'] ?? []) {
            $out .= $this->paramsSections($params) . $this->footerSection($params);
        }
        if (!empty($result['rows'])) {
            $out .= "\n## Rows (changed/added)\n";
            $changed = [];
            foreach ($result['rows'] as $row) {
                $fields = array_values(array_intersect($result['changedFields'][$row['id']] ?? [], array_keys($this->fields)));
                if ($fields) {
                    $changed[] = $row['id'] . ': ' . implode(', ', $fields);
                }
            }
            if ($changed) {
                $out .= 'changed fields — ' . implode('; ', $changed) . "\n";
            }
            if (count($result['rows']) <= static::CHANGED_ROWS_AS_CARDS) {
                foreach ($result['rows'] as $row) {
                    $out .= $this->rowCard($row);
                }
            } else {
                $out .= $this->rowsTable($result['rows']);
            }
        }
        if (!empty($result['moreChangedIds'])) {
            $out .= "\nmore changed rows: " . implode(', ', $result['moreChangedIds']) . "\n";
        }
        if (empty($result['rows']) && empty($result['params']) && empty($result['added']) && empty($result['deleted']) && empty($result['restored'])
            && empty($result['reordered'])) {
            $out .= "no changes in the table\n";
        }
        return $out;
    }

    /**
     * @param string $host https://host of this Totum (relative links of the codes)
     * @param string[] $humanPages paths of web pages for people: forms (linkToForm / linkToQuickForm), anonymous tables (linkToAnonymTable)
     */
    public static function sideEffects(array $links, array $datas, array $panels, string $host = '', array $humanPages = []): string
    {
        $lines = [];
        foreach ($links as $link) {
            $uri = (string)($link['uri'] ?? '');
            $target = !empty($link['target']) ? ' (' . static::scalar($link['target']) . ')' : '';
            $title = static::esc(static::plain(static::scalar($link['title'] ?? '')));
            if ($parsed = MCPTableResolver::parseLink($uri)) {
                /* crypted filters are not for reading: they are passed to the core in the window args */
                $lines[] = '- link' . $target . ': ' . $title . ' → ' . $parsed['path'] . ($parsed['query'] ? '?' . http_build_query($parsed['query']) : '')
                    . ($parsed['filters'] ? ' · crypted filters' : '') . ' (table — see "Opened window" below)';
                continue;
            }
            $url = preg_match('`^[a-z][a-z0-9+.-]*://`i', $uri) ? $uri : rtrim($host, '/') . '/' . ltrim($uri, '/');
            $path = (string)parse_url($url, PHP_URL_PATH);
            $isOwn = $host !== '' && strcasecmp((string)parse_url($url, PHP_URL_HOST), (string)parse_url($host, PHP_URL_HOST)) === 0;
            if (!isset($link['postData']) && array_filter($humanPages, fn($page) => str_starts_with($path, $page))) {
                $lines[] = '- link' . $target . ': ' . $title . ' → ' . $url
                    . ' — a web page for a person (form / anonymous table): give the link to the user, the agent does not work with it';
                continue;
            }
            $post = is_array($link['postData'] ?? null) ? static::postFields($link['postData']) : [];
            $lines[] = '- script' . ($post ? ' (POST' . ($target ? ', target ' . static::scalar($link['target']) : '') . ')' : $target) . ': ' . $title . ' → ' . $url . "\n"
                . '  web ' . ($post ? 'sends a POST form to it' : 'opens it') . ' from the browser of the user'
                . ($isOwn ? ' with his session (a page of this Totum: curl has no session — it may get the login page)' : '')
                . '; to call it run: `curl -sS ' . ($post ? '-X POST ' : '') . escapeshellarg($url)
                . implode('', array_map(fn($field) => ' --data-urlencode ' . escapeshellarg($field), $post)) . '`'
                . (static::isRefresh($link['refresh'] ?? false) ? "\n  refresh: " . static::scalar($link['refresh']) . ' — web refreshes the table after the script: re-read it' : '');
        }
        foreach ($datas as [$type, $data]) {
            $title = static::plain(static::scalar($data['title'] ?? ''));
            if (in_array($type, ['table', 'text', 'json', 'input', 'editField', 'buttons', 'fileUpload'])) {
                $kind = match ($type) {
                    'text' => static::textWindowKind($data),
                    'editField' => 'edit',
                    'fileUpload' => 'file upload',
                    default => $type,
                };
                $lines[] = '- ' . $kind . ': ' . static::esc($title) . ' (window' . ($type === 'table' ? ' with a table' : '') . ' — see "Opened window" below)';
                continue;
            }
            if ($type === 'files') {
                $lines[] = '- files: ' . static::esc(implode(', ', array_map(fn($file) => $file['name'] ?? '', (array)($data['files'] ?? []))))
                    . ' (download — see "Downloaded files" below)';
                continue;
            }
            if ($type === 'print') {
                $lines[] = '- print: web prints a document (linkToPrint / print of the table) in the browser of the user — '
                    . 'printing is not supported by the agent interface, the document is not shown';
                continue;
            }
            if ($type === 'buffer') {
                $text = static::scalar($data['text'] ?? '');
                $lines[] = '- buffer (proLinkToBuffer): web copies this text to the clipboard of the user — the text'
                    . (mb_strlen($text) > 10000 ? ' (first 10000 of ' . mb_strlen($text) . ' chars)' : '') . ":\n"
                    . "```text\n" . static::cut($text, 10000) . "\n```";
                continue;
            }
            $text = static::plain(static::scalar($data['text'] ?? $data['html'] ?? ''));
            $lines[] = '- ' . $type . ': ' . static::esc(trim($title . ($title && $text ? ' — ' : '') . static::cut($text, 2000)));
        }
        foreach ($panels as $panel) {
            $lines[] = '- panel: ' . ($panel['uri'] ?? '') . (!empty($panel['id']) ? ' row ' . static::scalar($panel['id']) : ' new row')
                . ' (see "Opened panel" below)';
        }
        return $lines ? "\n## Side effects\n" . implode("\n", $lines) . "\n" : '';
    }

    /**
     * Fields of a POST form like the browser builds them (App.showLInks): name[key] for arrays, true/false for booleans
     * @return string[] "name=value"
     */
    protected static function postFields(array $data, string $prefix = ''): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string)$key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                array_push($fields, ...static::postFields($value, $name));
            } else {
                $fields[] = $name . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : (string)$value);
            }
        }
        return $fields;
    }

    /* ======================== windows ======================== */

    /**
     * refresh of windows: true / "recalculate" / "reload" — web refreshes the page after the window (differ only in web)
     */
    public static function isRefresh(mixed $refresh): bool
    {
        return is_string($refresh) ? !in_array(strtolower(trim($refresh)), ['', 'false', '0'], true) : !empty($refresh);
    }

    public static function refreshLine(mixed $refresh): string
    {
        return static::isRefresh($refresh)
            ? 'refresh: ' . static::scalar($refresh) . ' — the source table is refreshed after the window: re-read it when the work in the window is done.' . "\n"
            : '';
    }

    /**
     * refresh of a button (panel button, button of linkToButtons): web refreshes the table after it
     */
    public static function buttonRefreshLine(mixed $refresh): string
    {
        return static::isRefresh($refresh)
            ? 'refresh: ' . static::scalar($refresh) . ' — web refreshes the table after this button: re-read it to see the result.' . "\n"
            : '';
    }

    /**
     * linkToButtons: title, html above the buttons, buttons (text, refresh); web closes the window after any button
     * @param array $tableArgs table/cycle/sess_hash of the table where the window was opened
     */
    public static function buttonsWindow(array $data, array $tableArgs): string
    {
        $call = $tableArgs + ['hash' => $data['hash'] ?? '', 'index' => '<index>'];
        $out = "\n---\n## Opened window (buttons): " . static::plain(static::scalar($data['title'] ?? '')) . "\n";
        if (($html = static::plain(static::scalar($data['html'] ?? ''))) !== '') {
            $out .= $html . "\n\n";
        }
        $rows = [];
        foreach (array_values((array)($data['buttons'] ?? [])) as $i => $button) {
            $refresh = $button['refresh'] ?? $data['refresh'] ?? false;
            $rows[] = [$i, '[' . static::esc(static::plain(static::scalar($button['text'] ?? ''))) . ']'
                . (!empty($button['icon']) && is_string($button['icon']) ? ' #icon-' . $button['icon'] : ''),
                static::isRefresh($refresh) ? static::esc(static::scalar($refresh)) . ' — re-read the table after it' : ''];
        }
        return $out . static::mdTable(['index', 'button', 'refresh'], $rows)
            . 'press one button with submit_buttons `' . json_encode($call, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '` — the code of the button runs; the window is closed after any button (like web): press only one button, do not use this hash again.' . "\n"
            . (!empty($data['close']) ? "close: true — the window (iframe) where the buttons were opened is closed after the button too.\n" : '');
    }

    /**
     * linkToDataText (htmlescaping) → text, linkToDataHtml → html
     */
    protected static function textWindowKind(array $data): string
    {
        return !empty($data['htmlescaping']) ? 'text' : 'html';
    }

    public static function textWindow(array $data): string
    {
        $text = static::scalar($data['text'] ?? '');
        $text = static::textWindowKind($data) === 'text' ? trim($text) : static::plain($text);
        return "\n---\n## Opened window (" . static::textWindowKind($data) . '): ' . static::plain(static::scalar($data['title'] ?? '')) . "\n"
            . static::refreshLine($data['refresh'] ?? false)
            . "\n" . ($text === '' ? '(empty)' : $text) . "\n";
    }

    /**
     * linkToInput / linkToInputSelect: text input (type), html above it, own html form with textarea ttmInput, select (multiple / selectAndSave)
     * @param array $tableArgs table/cycle/sess_hash of the table where the window was opened
     * @param string|null $options options of the select (markdown)
     */
    public static function inputWindow(array $data, array $tableArgs, ?string $options): string
    {
        $title = static::plain(static::scalar($data['title'] ?? ''));
        $html = is_string($data['html'] ?? null) ? $data['html'] : '';
        $isSelect = ($data['type'] ?? null) === 'select';
        $form = !$isSelect && preg_match('`<textarea\b[^>]*\bid\s*=\s*["\']?ttmInput\b[^>]*>(.*?)</textarea\s*>`is', $html, $m)
            ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
        $value = $data['value'] ?? null;

        if ($isSelect) {
            $kind = !empty($data['multiple'])
                ? 'select, multiple — value is an array of keys'
                : 'select, one value — value is a key' . (!empty($data['selectAndSave']) ? ' (web saves at once on choosing)' : '');
        } elseif (!is_null($form)) {
            $kind = 'html form of the window with its own field ttmInput (hidden textarea): value is the text of ttmInput — '
                . 'the JS of the form builds it from the controls of the form (see the html below), pass it in the same format';
            $value = $form;
        } else {
            $kind = 'text input' . (!empty($data['type']) && $data['type'] !== 'text' ? ' (type ' . static::scalar($data['type']) . ')' : '') . ' — value is a string';
        }
        $call = $tableArgs + ['hash' => $data['hash'] ?? '', 'value' => '<value>'];

        $out = "\n---\n## Opened window (input): " . $title . "\n"
            . 'input: ' . $kind . "\n"
            . (!is_null($value) && $value !== '' && $value !== [] ? 'current value: ' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n" : '')
            . 'button [' . static::esc(static::plain(static::scalar($data['button'] ?? 'OK'))) . ']: press it with submit_input `'
            . json_encode($call, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '` — the code of the button gets the value as $#input. The hash works once.' . "\n"
            . static::refreshLine($data['refresh'] ?? false)
            . (!empty($data['close']) ? "close: true — the window (iframe) where the input was opened is closed after the button.\n" : '');
        if ($html !== '') {
            $out .= is_null($form)
                ? "\n" . static::plain($html) . "\n"
                : "\n```html\n" . static::cut($html, 5000) . "\n```\n";
        }
        if ($isSelect) {
            $out .= "\n### Options\n" . ($options ?? '');
        }
        return $out;
    }

    /**
     * linkToEdit: one field of a row / params of a table (the code gives the access to it)
     * @param array $target table/field/id of the hash
     * @param array $tableArgs table/cycle/sess_hash of the table where the window was opened
     * @param string|null $options options of select/tree (markdown)
     */
    public function editWindow(array $data, array $target, array $field, mixed $value, array $tableArgs, ?string $options): string
    {
        $value = is_array($value) && key_exists('v', $value) ? $value : ['v' => $value];
        $where = 'table ' . $target['name'] . (!empty($target['id']) ? ', row ' . $target['id'] : ', params') . (!empty($target['extra']) ? ' (cycle/sess_hash ' . $target['extra'] . ')' : '');
        $call = json_encode($tableArgs + ['hash' => $data['hash'] ?? '', 'value' => '<value>'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $withCode = !empty($field['code']) && empty($field['codeOnlyInAdd']);

        $out = "\n---\n## Opened window (edit): " . static::plain(static::scalar($data['title'] ?? '')) . "\n"
            . '| Title | name | type | where | current value |' . "\n|---|---|---|---|---|\n"
            . '| ' . $this->fieldTitle($field) . ' | ' . $field['name'] . ' | ' . static::esc($this->fieldType($field, false)) . ($withCode ? ' calc' : '')
            . ' | ' . static::esc($where) . ' | ' . $this->cell($field, $value) . " |\n"
            . 'value: ' . (static::TYPE_HINTS[$field['type']] ?? $field['type']) . "\n"
            . 'save with submit_edit `' . $call . '` — the code gives the access to the field (rights of the field are not checked, like web).' . "\n";
        if ($withCode) {
            $out .= !empty($value['h'])
                ? "the value is manual: submit_edit with \"special\": \"reset\" (instead of value) returns the calculated value\n"
                : "the value is calculated: submit_edit with \"special\": \"pin\" (instead of value) fixes it as manual\n";
        }
        if ($field['type'] === 'file') {
            $out .= "files of the field now:\n" . static::filesList(is_array($value['v']) ? $value['v'] : [], $field) . "\n"
                . 'value — the whole list: {"file": "<file>"} keeps a file, new — {"path"} / {"upload"} / {"name", "text"|"base64"}' . "\n";
        }
        $out .= static::refreshLine($data['refresh'] ?? false);
        if (!is_null($options)) {
            $out .= "\n### Options\n" . $options;
        }
        return $out;
    }

    /**
     * linkToDataJson: view only or (with code) with a button that runs the code with the edited JSON as $#value
     * @param array $tableArgs table/cycle/sess_hash of the table where the window was opened
     */
    public static function jsonWindow(array $data, array $tableArgs): string
    {
        $out = "\n---\n## Opened window (json): " . static::plain(static::scalar($data['title'] ?? '')) . "\n";
        if (!empty($data['hash'])) {
            $call = $tableArgs + ['hash' => $data['hash'], 'json' => '<JSON>'];
            $out .= 'button [' . static::esc(static::plain(static::scalar($data['buttontext'] ?? 'OK'))) . ']: change the JSON if needed and press it with submit_json_window `'
                . json_encode($call, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '` — "json" is the JSON value itself (not a string), the code of the button gets it as $#value. The hash works once.' . "\n";
        } else {
            $out .= "view only\n";
        }
        return $out . static::refreshLine($data['refresh'] ?? false)
            . "\n```json\n" . json_encode($data['json'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n```\n";
    }
}

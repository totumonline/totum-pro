<?php

namespace totum\moduls\MCP;

use totum\common\DryRun;
use totum\common\errorException;
use totum\common\Totum;

/**
 * search_codes (creator view): search through all the codes of the schema like a grep —
 * fields (code settings of data_src of tables_fields: code, codeAction, codeSelect, format…), tables (codes of the rows of tables:
 * default_action, row_format, table_format, on_duplicate) and data (text fields with the totum code in rows / params of not tmp tables:
 * crons, notification_codes, ttm__custom_functions…). Matches by lines with the line numbers, in the order of the tables and fields.
 */
class MCPCodeSearch
{
    public const DEFAULT_LIMIT = 30;
    public const MAX_LIMIT = 200;
    public const MAX_CONTEXT = 5;
    public const IN = ['fields', 'tables', 'data'];
    protected const LINES_PER_CODE = 20;
    protected const LINE_LENGTH = 300;
    protected const CATEGORY_ORDER = ['param' => 0, 'filter' => 1, 'column' => 2, 'footer' => 3];
    protected const ACTION_TRIGGERS = ['add' => 'CodeActionOnAdd', 'change' => 'CodeActionOnChange', 'delete' => 'CodeActionOnDelete', 'click' => 'CodeActionOnClick'];
    /* data of the structure tables — scope tables / fields; tmp, calcs, globcalcs tables keep no rows of their own */
    protected const DATA_SKIP = ['tables', 'tables_fields'];
    protected const DATA_TABLE_TYPES = ['simple', 'cycles'];
    /* the title of a row of data when the table has no main field */
    protected const ROW_TITLE_FIELDS = ['name', 'function_name', 'title', 'path_code', 'table_name'];

    protected ?string $regex = null;
    protected bool $external = false;
    protected int $context = 0;
    /** @var array id => row of tables */
    protected array $tables = [];

    public function __construct(protected Totum $Totum, protected MCPFieldSettings $Settings)
    {
    }

    public function search(array $args): string
    {
        $q = is_string($args['q'] ?? null) ? $args['q'] : '';
        $this->external = !empty($args['external']);
        if (trim($q) === '' && !$this->external) {
            throw new errorException('Pass q (a text to find in codes) and/or external: true (codes with external calls)');
        }
        if (trim($q) !== '') {
            $this->regex = $this->pattern($q, !empty($args['regex']), !empty($args['word']));
        }
        $in = $args['in'] ?? static::IN;
        $in = is_array($in) ? $in : [$in];
        if (!$in || ($unknown = array_diff($in, static::IN))) {
            throw new errorException('in: ' . implode(', ', $unknown ?? []) . ' — the scopes are ' . implode(', ', static::IN));
        }
        $codeSettings = array_keys($this->Settings->codeSettings());
        $settings = $args['settings'] ?? [];
        $settings = is_array($settings) ? $settings : [$settings];
        if ($unknown = array_diff($settings, $codeSettings, Totum::TABLE_CODE_PARAMS)) {
            throw new errorException('settings: ' . implode(', ', $unknown) . ' are not codes. Codes of fields: ' . implode(', ', $codeSettings)
                . '; codes of tables: ' . implode(', ', Totum::TABLE_CODE_PARAMS));
        }
        $this->context = min(max((int)($args['context'] ?? 0), 0), static::MAX_CONTEXT);
        $limit = min(max((int)($args['limit'] ?? 0) ?: static::DEFAULT_LIMIT, 1), static::MAX_LIMIT);
        $offset = max((int)($args['offset'] ?? 0), 0);
        $withOff = !empty($args['off']);

        foreach ($this->Totum->getModel('tables')->getAll(['is_del' => false],
            'id, name, title, type, main_field, header, ' . implode(', ', Totum::TABLE_CODE_PARAMS)) as $row) {
            $this->tables[(int)$row['id']] = $row;
        }
        $only = null;
        if (isset($args['table']) && $args['table'] !== '') {
            $tableRow = $this->Totum->getTableRow(ctype_digit((string)$args['table']) ? (int)$args['table'] : (string)$args['table'])
                ?: throw new errorException('Table ' . $args['table'] . ' is not found');
            $only = (int)$tableRow['id'];
        }

        $codes = [];
        if (in_array('tables', $in)) {
            $codes = array_merge($codes, $this->tableCodes($only, $settings));
        }
        $fieldRows = $this->Totum->getModel('tables_fields')->getAll(['is_del' => false], 'id, table_id, name, title, category, ord, version, data_src');
        foreach ($fieldRows as &$fieldRow) {
            $fieldRow['data_src'] = json_decode((string)$fieldRow['data_src'], true) ?: [];
        }
        unset($fieldRow);
        if (in_array('fields', $in)) {
            $codes = array_merge($codes, $this->fieldCodes($fieldRows, $only, $settings ?: $codeSettings));
        }
        if (in_array('data', $in) && !$settings) {
            $codes = array_merge($codes, $this->dataCodes($fieldRows, $only));
        }

        $found = [];
        $off = 0;
        foreach ($codes as $code) {
            if (($hits = $this->matchLines($code['code'])) === null) {
                continue;
            }
            if (!$code['on'] && !$withOff) {
                $off++;
                continue;
            }
            $found[] = $code + ['hits' => $hits];
        }
        usort($found, fn($a, $b) => $a['sort'] <=> $b['sort']);

        return $this->answer($q, $in, $settings, $only, $found, $off, $offset, $limit);
    }

    /**
     * PCRE of q: a text (case and ё/е insensitive) or a regex; word — not a part of a longer name
     */
    protected function pattern(string $q, bool $regex, bool $word): string
    {
        $body = $regex ? $q : str_replace(['е', 'ё', 'Е', 'Ё'], '[её]', preg_quote($q, "\x01"));
        if ($word) {
            $body = '(?<![\p{L}\p{N}_])(?:' . $body . ')(?![\p{L}\p{N}_])';
        }
        $pattern = "\x01" . $body . "\x01iu";
        if (@preg_match($pattern, '') === false) {
            throw new errorException('q is not a valid regex (PCRE, without delimiters): ' . preg_replace('/^preg_match\(\): /', '', error_get_last()['message'] ?? preg_last_error_msg()));
        }
        return $pattern;
    }

    /**
     * Indexes of the matching lines; null — the code does not match
     */
    protected function matchLines(string $code): ?array
    {
        /* an empty code «=:» / «= : » / «f1=:» */
        if (!preg_match('/^\s*[a-z0-9_]*\s*=\s*:\s*\S/mu', $code)) {
            return null;
        }
        $lines = preg_split('/\R/u', $code);
        $pattern = $this->regex;
        if ($this->external) {
            if (!$calls = DryRun::externalCalls($code)) {
                return null;
            }
            $pattern ??= '/\b(' . implode('|', array_keys($calls)) . ')\s*\(/i';
        }
        $hits = array_keys(array_filter($lines, fn($line) => preg_match($pattern, $line)));
        return $hits ?: null;
    }

    /* ======================== codes of the scopes ======================== */

    protected function tableCodes(?int $only, array $settings): array
    {
        $codes = [];
        foreach ($this->tables as $id => $row) {
            if ($only && $id !== $only) {
                continue;
            }
            foreach (Totum::TABLE_CODE_PARAMS as $i => $name) {
                if ((!$settings || in_array($name, $settings)) && is_string($row[$name] ?? null)) {
                    $codes[] = [
                        'table' => $id, 'code' => $row[$name], 'on' => true,
                        'title' => $row['name'] . ' · table code ' . $name,
                        'open' => 'get_value {"table":"tables","field":"' . $name . '","id":' . $id . '}',
                        'sort' => [$row['name'], 0, 0, '', 0, 0, $i],
                    ];
                }
            }
        }
        return $codes;
    }

    protected function fieldCodes(array $fieldRows, ?int $only, array $settings): array
    {
        $defaults = [];
        foreach ($this->Totum->getModel('calcstable_versions')->getAll(['is_del' => false], 'table_name, version, is_default') as $v) {
            if (in_array($v['is_default'], ['true', true], true)) {
                $defaults[$v['table_name']] = $v['version'];
            }
        }
        $codes = [];
        foreach ($fieldRows as $field) {
            $tableId = (int)$field['table_id'];
            if (($only && $tableId !== $only) || !($table = $this->tables[$tableId] ?? null)) {
                continue;
            }
            $src = $field['data_src'];
            $version = (string)($field['version'] ?? '');
            $isDefault = $version === '' || ($defaults[$table['name']] ?? null) === $version;
            foreach ($settings as $i => $setting) {
                if (!is_string($src[$setting]['Val'] ?? null)) {
                    continue;
                }
                $on = !empty($src[$setting]['isOn']);
                $triggers = match (true) {
                    $setting !== 'codeAction' => [],
                    ($src['type']['Val'] ?? null) === 'button' => ['click'],
                    default => array_keys(array_filter(static::ACTION_TRIGGERS, fn($flag) => !empty($src[$flag]['isOn']) && !empty($src[$flag]['Val']))),
                };
                $external = '';
                if ($this->external && ($calls = DryRun::externalCalls($src[$setting]['Val']))) {
                    $help = !empty($src['help']['isOn']) ? (string)($src['help']['Val'] ?? '') : '';
                    $external = ' · external: ' . implode(', ', array_map(fn($f) => $f . (DryRun::hasHelpMock($help, $f) ? ' (mock)' : ' (no mock)'), array_keys($calls)));
                }
                $codes[] = [
                    'table' => $tableId, 'code' => $src[$setting]['Val'], 'on' => $on,
                    'title' => $table['name'] . '.' . $field['name'] . ' «' . MCPMarkdown::plain((string)$field['title']) . '» · ' . $field['category']
                        . ' ' . ($src['type']['Val'] ?? '?') . ' · field id ' . $field['id'] . ' · ' . $setting . ($triggers ? ' (' . implode(', ', $triggers) . ')' : '')
                        . ($version !== '' ? ' · version ' . $version . ($isDefault ? ' (default)' : '') : '')
                        . ($on ? '' : ' · OFF (the setting is switched off: the code does not work)') . $external,
                    'open' => 'get_value {"table":"tables_fields","field":"data_src","id":' . $field['id'] . '} · settings — get_field_settings {"id":' . $field['id'] . '}',
                    'sort' => [$table['name'], 1, $isDefault ? 0 : 1, $version, static::CATEGORY_ORDER[$field['category']] ?? 9, (float)$field['ord'], $i],
                ];
            }
        }
        return $codes;
    }

    /**
     * Text fields with the totum code (textType totum) of simple / cycles tables: the codes in their rows and params
     */
    protected function dataCodes(array $fieldRows, ?int $only): array
    {
        $codes = [];
        $columns = [];
        foreach ($fieldRows as $field) {
            if ($field['category'] === 'column') {
                $columns[(int)$field['table_id']][] = $field['name'];
            }
        }
        foreach ($fieldRows as $field) {
            $tableId = (int)$field['table_id'];
            $table = $this->tables[$tableId] ?? null;
            if (!$table || ($only && $tableId !== $only) || in_array($table['name'], static::DATA_SKIP) || !in_array($table['type'], static::DATA_TABLE_TYPES)
                || ($field['data_src']['type']['Val'] ?? null) !== 'text' || ($field['data_src']['textType']['Val'] ?? null) !== 'totum') {
                continue;
            }
            $name = $field['name'];
            /* the same length as the sort of fields and table codes: arrays are compared by count first */
            $sort = fn($id) => [$table['name'], 2, 0, '', static::CATEGORY_ORDER[$field['category']] ?? 9, (float)$field['ord'], $id];
            if ($field['category'] === 'param') {
                $header = json_decode((string)$table['header'], true) ?: [];
                if (is_string($code = $header[$name]['v'] ?? null)) {
                    $codes[] = ['table' => $tableId, 'code' => $code, 'on' => true, 'title' => $table['name'] . ' · data: param ' . $name,
                        'open' => 'get_value {"table":"' . $table['name'] . '","field":"' . $name . '"}', 'sort' => $sort(0)];
                }
                continue;
            }
            if ($field['category'] !== 'column') {
                continue;
            }
            $main = current(array_diff(array_intersect(array_filter([(string)($table['main_field'] ?? ''), ...static::ROW_TITLE_FIELDS]), $columns[$tableId] ?? []), [$name])) ?: '';
            foreach ($this->Totum->getModel($table['name'])->getAll(['is_del' => false], 'id, ' . $name . ($main ? ', ' . $main : ''), 'id') as $row) {
                if (is_string($row[$name] ?? null)) {
                    $codes[] = ['table' => $tableId, 'code' => $row[$name], 'on' => true,
                        'title' => $table['name'] . ' row ' . $row['id'] . ($main && (string)$row[$main] !== '' ? ' «' . MCPMarkdown::cut(MCPMarkdown::plain((string)$row[$main]), 60) . '»' : '')
                            . ' · data: ' . $name,
                        'open' => 'get_value {"table":"' . $table['name'] . '","field":"' . $name . '","id":' . $row['id'] . '}', 'sort' => $sort((int)$row['id'])];
                }
            }
        }
        return $codes;
    }

    /* ======================== answer ======================== */

    protected function answer(string $q, array $in, array $settings, ?int $only, array $found, int $off, int $offset, int $limit): string
    {
        $total = count($found);
        $lines = array_sum(array_map(fn($c) => count($c['hits']), $found));
        $what = trim($q) !== '' ? '«' . $q . '»' : '';
        if ($this->external) {
            $what .= ($what ? ' + ' : '') . 'external calls';
        }
        $out = '# Code search: ' . $what . ' · in ' . implode(', ', $in) . ($settings ? ' · settings ' . implode(', ', $settings) : '')
            . ($only ? ' · table ' . $this->tables[$only]['name'] : '') . ' · ' . $total . ' code(s), ' . $lines . " matching line(s)\n";
        $offNote = $off ? 'also in ' . $off . ' switched-off code(s) of fields (do not work) — off: true shows them' . "\n" : '';
        if (!$found) {
            return $out . $offNote . 'nothing is found: try a part of the name, without word, other scopes (in), regex: true for alternatives ("emailSend|notificationSend")' . "\n";
        }
        $byTable = [];
        foreach ($found as $code) {
            $name = $this->tables[$code['table']]['name'];
            $byTable[$name] = ($byTable[$name] ?? 0) + 1;
        }
        $out .= 'by table: ' . implode(', ', array_map(fn($n, $c) => $n . ' ' . $c, array_keys(array_slice($byTable, 0, 40)), array_slice($byTable, 0, 40)))
            . (count($byTable) > 40 ? ', … (' . count($byTable) . ' tables)' : '') . "\n" . $offNote
            . 'lines: «N:» — a matching line, «N-» — a line around it (context); the whole code — the call of «open»' . "\n";

        $page = array_slice($found, $offset, $limit);
        $table = null;
        foreach ($page as $code) {
            if ($table !== $code['table']) {
                $table = $code['table'];
                $row = $this->tables[$table];
                $out .= "\n## " . $row['name'] . ' «' . MCPMarkdown::plain((string)$row['title']) . '» · ' . $row['type'] . ' · id ' . $table . "\n";
            }
            $out .= "\n### " . $code['title'] . "\nopen: " . $code['open'] . "\n~~~\n" . $this->linesText($code['code'], $code['hits']) . "~~~\n";
        }
        if ($total > $offset + $limit) {
            $out .= "\nshown " . ($offset + 1) . '–' . ($offset + count($page)) . ' of ' . $total . ' codes — next page: offset ' . ($offset + $limit)
                . ' (or narrow: table, settings, in, word)' . "\n";
        } elseif (!$page) {
            $out .= "\nno codes at offset " . $offset . ' (' . $total . " found)\n";
        }
        return $out;
    }

    protected function linesText(string $code, array $hits): string
    {
        $lines = preg_split('/\R/u', $code);
        $more = max(count($hits) - static::LINES_PER_CODE, 0);
        $hits = array_slice($hits, 0, static::LINES_PER_CODE);
        $show = [];
        foreach ($hits as $i) {
            for ($j = max(0, $i - $this->context); $j <= min(count($lines) - 1, $i + $this->context); $j++) {
                $show[$j] = $show[$j] ?? false;
            }
            $show[$i] = true;
        }
        ksort($show);
        $out = '';
        $prev = null;
        foreach ($show as $i => $isHit) {
            if ($prev !== null && $i > $prev + 1) {
                $out .= "…\n";
            }
            $out .= ($i + 1) . ($isHit ? ': ' : '- ') . $this->cutLine($lines[$i], $isHit) . "\n";
            $prev = $i;
        }
        return $out . ($more ? '… ' . $more . " more matching line(s) in this code\n" : '');
    }

    /**
     * A long line — the part around the first match
     */
    protected function cutLine(string $line, bool $isHit): string
    {
        if (mb_strlen($line) <= static::LINE_LENGTH) {
            return $line;
        }
        $start = 0;
        if ($isHit && $this->regex && preg_match($this->regex, $line, $m, PREG_OFFSET_CAPTURE)) {
            $start = max(0, mb_strlen(substr($line, 0, $m[0][1])) - 100);
        }
        return ($start ? '…' : '') . mb_substr($line, $start, static::LINE_LENGTH) . (mb_strlen($line) > $start + static::LINE_LENGTH ? '…' : '');
    }
}

<?php

namespace totum\moduls\MCP;

use GuzzleHttp\Psr7\ServerRequest;
use totum\common\errorException;
use totum\common\Totum;
use totum\moduls\Table\Actions;
use totum\tableTypes\aTable;

/**
 * search / search_click: the search of the top panel of web (button of __header.php, App search popup of main.js) —
 * Actions::getSearchResults (MeiliSearch or TRGM by ttm__search_settings) and Actions::searchClick (the code of the card of the result
 * or of its button from the row of the table in ttm__search_settings: code / buttons [{name, title, code}])
 */
class MCPSearch
{
    public const SETTINGS = 'ttm__search_settings';
    public const CATALOG = 'ttm__search_catalog';
    protected const TEXT_LENGTH = 500;
    protected const MARK = ['<span class="marker">', '</span>'];

    protected ?aTable $Settings = null;

    public function __construct(protected Totum $Totum)
    {
    }

    /**
     * The engine of the search like the search button of web: MeiliSearch (h_get_updates) has priority (Actions::getSearchResults)
     */
    public function engine(): string
    {
        $params = $this->settings()->getTbl()['params'];
        return match (true) {
            ($params['h_get_updates']['v'] ?? null) === true => 'MeiliSearch',
            ($params['h_use_trgm_search']['v'] ?? null) === true => 'TRGM',
            default => throw new errorException('The search of the system is switched off: web has no search button (' . static::SETTINGS
                . ': neither MeiliSearch h_get_updates nor TRGM h_use_trgm_search is on). Find the data in the tables with read_table and filters'),
        };
    }

    protected function settings(): aTable
    {
        if (!$this->Settings) {
            if (!$this->Totum->getTableRow(static::SETTINGS)) {
                throw new errorException('The search of the system is not available: there is no table ' . static::SETTINGS . ' in the schema');
            }
            $this->Settings = $this->Totum->getTable(static::SETTINGS);
        }
        return $this->Settings;
    }

    protected function actions(array $post): Actions
    {
        return new Actions((new ServerRequest('POST', '/MCP/'))->withParsedBody($post), '/Table/', null, $this->Totum);
    }

    /**
     * @return array name => title of ttm__search_catalog
     */
    protected function catalogs(): array
    {
        if (!$this->Totum->getTableRow(static::CATALOG)) {
            return [];
        }
        return array_column($this->actions([])->searchCatalog()['catalog'] ?? [], 'title', 'name');
    }

    public function search(array $args): string
    {
        $q = trim(is_string($args['q'] ?? null) ? $args['q'] : '');
        if ($q === '') {
            throw new errorException('Parameter "q" (words to search) is required');
        }
        $engine = $this->engine();
        $catalogs = $this->catalogs();
        $cats = array_values(array_filter(array_map('strval', (array)($args['catalogs'] ?? [])), fn($c) => $c !== ''));
        if ($unknown = array_diff($cats, array_map('strval', array_keys($catalogs)))) {
            throw new errorException('Unknown catalogs: ' . implode(', ', $unknown) . ' — the catalogs: '
                . ($catalogs ? $this->catalogsLine($catalogs) : 'none'));
        }
        $limit = (int)($this->settings()->getTbl()['params']['h_search_limit']['v'] ?? 0) ?: 20;
        $hits = $this->actions(['q' => $q] + ($cats ? ['cats' => $cats] : []))->getSearchResults()['hits'] ?? [];

        $out = '# Search: "' . MCPMarkdown::esc($q) . '" · ' . $engine . ' · ' . count($hits) . ' result(s)'
            . ($cats ? ' · catalogs ' . implode(', ', $cats) : '') . "\n"
            . 'like the search of the top panel of web (' . static::SETTINGS . '): the tables of the search available for you, only the rows you see in web; '
            . 'at most ' . $limit . ' results (h_search_limit)' . (count($hits) >= $limit ? ' — the limit is reached: refine q or catalogs for the others' : '') . "\n"
            . ($catalogs ? 'catalogs (search only in them — "catalogs": [names]): ' . $this->catalogsLine($catalogs) . "\n" : '');
        if (!$hits) {
            return $out . "\nnothing is found\n";
        }
        $rows = [];
        $example = null;
        foreach ($hits as $hit) {
            $pk = (string)($hit['pk'] ?? '');
            [$tableId, $rowId] = explode('-', $pk . '-');
            $catalog = MCPMarkdown::scalar($hit['catalog'] ?? '');
            $buttons = [];
            foreach ((array)($hit['buttons'] ?? []) as $button) {
                $buttons[] = MCPMarkdown::esc(MCPMarkdown::plain(MCPMarkdown::scalar($button['title'] ?? ''))) . ' [' . MCPMarkdown::esc($button['name'] ?? '') . ']';
                $example ??= ['pk' => $pk, 'button' => (string)($button['name'] ?? '')];
            }
            $rows[] = [
                MCPMarkdown::esc($pk),
                MCPMarkdown::esc(($this->Totum->getTableRow((int)$tableId)['name'] ?? $tableId) . ' · ' . $rowId),
                $catalog === '' ? '' : MCPMarkdown::esc(isset($catalogs[$catalog]) ? $catalogs[$catalog] . ' [' . $catalog . ']' : $catalog),
                $this->text($hit, 'title', $engine),
                $this->text($hit, 'index', $engine, static::TEXT_LENGTH),
                implode(', ', $buttons),
            ];
        }
        $first = (string)($hits[0]['pk'] ?? '');
        return $out . "\n" . MCPMarkdown::mdTable(['pk', 'table · row', 'catalog', 'title', 'text (**found**)', 'buttons'], $rows)
            . "\nopen a result like the click on its card in web: search_click `" . $this->json(['pk' => $first])
            . '` — the code of the card runs (it usually opens the table / panel of the row or does an action): its windows are in the answer'
            . ($example ? '; a button of the card: search_click `' . $this->json($example) . '`' : '') . "\n";
    }

    /**
     * The text of the card: _formatted of the engine with the found words (marker spans) → **word**;
     * MeiliSearch escapes the text (htmlspecialchars), TRGM gives it as is
     */
    protected function text(array $hit, string $key, string $engine, int $length = 0): string
    {
        $text = MCPMarkdown::scalar($hit['_formatted'][$key] ?? $hit[$key] ?? '');
        $text = str_replace(static::MARK, '**', $text);
        if ($engine === 'MeiliSearch') {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return MCPMarkdown::esc($length ? MCPMarkdown::cut($text, $length) : $text);
    }

    protected function catalogsLine(array $catalogs): string
    {
        $list = [];
        foreach ($catalogs as $name => $title) {
            $list[] = $name . ' «' . MCPMarkdown::plain(MCPMarkdown::scalar($title)) . '»';
        }
        return MCPMarkdown::esc(implode(', ', $list));
    }

    /**
     * The click on the card of a result (or on its button) like web: Actions::searchClick runs the code from the row of the result.
     * The core does nothing silently when the result is not available — MCP checks it before with the same rules
     */
    public function click(array $args): string
    {
        $this->engine();
        $pk = is_scalar($args['pk'] ?? null) ? trim((string)$args['pk']) : '';
        if (!preg_match('/^(\d+)-(\d+)$/', $pk, $m)) {
            throw new errorException('Parameter "pk" must be "table id-row id" (e.g. "12-5") — the pk of a result of search');
        }
        [, $tableId, $rowId] = $m;
        if (!key_exists($tableId, $this->Totum->getUser()->getTables()) || !($tableRow = $this->Totum->getTableRow((int)$tableId))) {
            throw new errorException('The table ' . $tableId . ' of the result is not available for you');
        }
        $codes = $this->settings()->getByParams(['where' => [['field' => 'table_id', 'operator' => '=', 'value' => $tableId]],
            'field' => ['buttons', 'code']], 'row');
        if (!$codes) {
            throw new errorException('The table ' . $tableRow['name'] . ' is not in the search (no row in ' . static::SETTINGS . ')');
        }
        $Table = $this->Totum->getTable($tableRow);
        if (!$Table->loadFilteredRows('web', [(int)$rowId])) {
            throw new errorException('The row ' . $rowId . ' of ' . $tableRow['name'] . ' is not found or not visible for you (web does nothing on such a click)');
        }
        $buttons = array_values(array_filter((array)($codes['buttons'] ?? []), 'is_array'));
        $names = implode(', ', array_map(fn($b) => MCPMarkdown::scalar($b['name'] ?? ''), $buttons)) ?: 'none';
        $button = is_scalar($args['button'] ?? null) && (string)$args['button'] !== '' ? (string)$args['button'] : null;
        if ($button !== null) {
            $found = array_values(array_filter($buttons, fn($b) => (string)($b['name'] ?? '') === $button))[0] ?? null;
            if (!$found) {
                throw new errorException('The card has no button ' . $button . ' — its buttons: ' . $names);
            }
            $title = 'Pressed: button ' . MCPMarkdown::plain(MCPMarkdown::scalar($found['title'] ?? '')) . ' [' . $button . '] of the card';
        } else {
            if (trim(MCPMarkdown::scalar($codes['code'] ?? '')) === '') {
                throw new errorException('The cards of ' . $tableRow['name'] . ' have no code (column code of ' . static::SETTINGS
                    . ' is empty): web does nothing on the click — its buttons: ' . $names);
            }
            $title = 'Opened: the card (the click on the result)';
        }

        $this->actions(['pk' => $pk] + ($button !== null ? ['button' => $button] : []))->searchClick();

        $Totum = $this->Totum;
        $nothing = !($Totum->getInterfaceLinks() ?? []) && !($Totum->getInterfaceDatas() ?? []) && !$Totum->getPanelLinks();
        return '# Search result ' . $pk . ' · ' . $tableRow['name'] . ' · row ' . $rowId . "\n"
            . '**' . $title . "**\n"
            . 'changed tables: ' . (($changed = $Totum->getChangedTables()) ? implode(', ', $changed) : 'none') . "\n"
            . ($nothing ? "the code opened nothing (no window / link)\n" : 'web closes the search popup and opens what the code gives — see below' . "\n");
    }

    /**
     * Args of the table of the result (search_click): the context of the code of the card
     */
    public static function tableArgs(Totum $Totum, array $args): array
    {
        $tableId = (int)explode('-', is_scalar($args['pk'] ?? null) ? (string)$args['pk'] : '')[0];
        return ($name = $Totum->getTableRow($tableId)['name'] ?? null) ? ['table' => $name] : [];
    }

    protected function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

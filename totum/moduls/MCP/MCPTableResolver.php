<?php

namespace totum\moduls\MCP;

use GuzzleHttp\Psr7\ServerRequest;
use totum\common\Auth;
use totum\common\Cycle;
use totum\common\errorException;
use totum\common\Totum;
use totum\models\Table;
use totum\tableTypes\aTable;

/**
 * Table + cycle + access resolving like TableController::checkTableByUri, and creating of Actions objects
 */
class MCPTableResolver
{
    public const MODULE_PATH = '/Table/';

    protected aTable $Table;
    protected ?Cycle $Cycle = null;
    protected bool $onlyRead;

    public function __construct(protected Totum $Totum, array $args)
    {
        $User = $Totum->getUser();
        if (empty($args['table'])) {
            throw new errorException('Parameter "table" (id or name) is required');
        }
        $table = is_int($args['table']) ? $args['table'] : (string)$args['table'];
        if (!($tableRow = $Totum->getConfig()->getTableRow($table))) {
            throw new errorException($this->translate('Table [[%s]] is not found.', $table));
        }

        if (!array_key_exists($tableRow['id'], $User->getTables())) {
            throw new errorException($this->translate('Access to the table is denied.'));
        }
        $this->onlyRead = $User->getTables()[$tableRow['id']] === 0;

        switch ($tableRow['type']) {
            case 'calcs':
                if (empty($args['cycle'])) {
                    throw new errorException('Table ' . $tableRow['name'] . ' is a cycle (calcs) table: parameter "cycle" (cycle id) is required');
                }
                $this->Cycle = $Totum->getCycle((int)$args['cycle'], $tableRow['tree_node_id']);
                if (!$this->Cycle->loadRow()) {
                    throw new errorException($this->translate('Cycle [[%s]] is not found.', (int)$args['cycle']));
                }
                $this->checkCycleAccess();
                $this->Table = $this->Cycle->getTable($tableRow);
                break;
            case 'tmp':
                $this->Table = $Totum->getTable($tableRow, $args['sess_hash'] ?? null);
                break;
            default:
                $this->Table = $Totum->getTable($tableRow);
        }
    }

    /**
     * Link of linkToTable: /Table/{top}/{table}[?…] or /Table/{top}/{cycles table}/{cycle}/{table}[?…]
     * f (efl) — crypted filters: passed to the core as is (like ?f= of the web page), the core decrypts them into filters;
     * a — values for the insert row, sess_hash — tmp table, b — tab_ button of the cycles table (a not calcs table opened as a tab of the cycle)
     * @return array|null [table_id, cycle, cycles_table, sess_hash, filters (crypted string), add, path, query without crypted filters]
     */
    public static function parseLink(string $uri): ?array
    {
        if (!preg_match('`^(/Table/\d+/(\d+)(?:/(\d+)/(\d+))?)/?(?:\?(.*))?$`', $uri, $match)) {
            return null;
        }
        parse_str($match[5] ?? '', $query);
        $crypted = $query['f'] ?? $query['efl'] ?? null;
        $isCycleLink = !empty($match[4]);
        return [
            'table_id' => (int)($isCycleLink ? $match[4] : $match[2]),
            'cycle' => $isCycleLink ? (int)$match[3] : null,
            'cycles_table' => $isCycleLink ? (int)$match[2] : null,
            'sess_hash' => is_string($query['sess_hash'] ?? null) ? $query['sess_hash'] : null,
            'filters' => is_string($crypted) && $crypted !== '' ? $crypted : null,
            'add' => is_array($query['a'] ?? null) ? $query['a'] : [],
            'path' => $match[1],
            'query' => array_diff_key($query, ['f' => 1, 'efl' => 1]),
        ];
    }

    /**
     * Tables hidden from the navigation tree for the user (tree_off of the roles): web opens them only by buttons/links of other tables.
     * Tables of the extra role "user manager" are never in the tree, but they are not hidden (web shows them in its own menu)
     * @return array [table id => true]
     */
    public static function hiddenFromTree(Totum $Totum): array
    {
        $User = $Totum->getUser();
        $hidden = array_diff_key($User->getTables(), $User->getTreeTables());
        if ($hidden && array_intersect(Auth::$userManageRoles, $User->getRoles())) {
            foreach (Auth::getUserManageTables($Totum->getConfig(), $User) as $table) {
                if ($tableRow = $Totum->getConfig()->getTableRow($table['name'])) {
                    unset($hidden[$tableRow['id']]);
                }
            }
        }
        return array_fill_keys(array_keys($hidden), true);
    }

    /**
     * One cycle per user (roles one_cycle_tables) like TableController::checkTableByUri: web does not show the cycles table —
     * its page opens the cycle of the user (creates it if there is none), other requests to it are errors except tab_ buttons of the cycle
     * @return int|null|false cycle id; null — the user has no cycle yet; false — not a one-cycle table (or the user has several cycles: usual table)
     */
    public function oneCycle(): int|null|false
    {
        if (!$this->Totum->getUser()->isOneCycleTable($this->Table->getTableRow())) {
            return false;
        }
        /** @var \totum\tableTypes\cyclesTable $Table */
        $Table = $this->Table;
        return match ($Table->getUserCyclesCount()) {
            0 => null,
            1 => (int)$Table->getUserCycleId(),
            default => false,
        };
    }

    /**
     * Tabs of a cycle like the tabs/tree of web inside a cycle (TableController::setTreeData): tab_ buttons of the cycles table
     * and calcs tables of the cycle in the tree of the user, ordered by ord (tab buttons — ord of the field, tables — ord of the version of the cycle)
     * @return array [['type' => 'table'|'tab', 'name' => …, 'title' => …]]
     */
    public static function cycleTabs(Totum $Totum, Cycle $Cycle): array
    {
        $CyclesTable = $Cycle->getCyclesTable();
        $ordered = [];
        $put = function ($ord, array $item) use (&$ordered) {
            $dec = 1;
            while (key_exists((string)$ord, $ordered)) {
                $ord = (string)($ord + 5 * (1 / (10 ** $dec++)));
            }
            $ordered[(string)$ord] = $item;
        };
        foreach ($CyclesTable->getFields() as $field) {
            if (static::isCycleTabButton($CyclesTable, $field)) {
                $put($field['ord'], ['type' => 'tab', 'name' => $field['name'], 'title' => $field['title']]);
            }
        }
        foreach ($Cycle->getViewTablesWithOrds() as $ord => $name) {
            if (($tableRow = $Totum->getTableRow($name)) && key_exists($tableRow['id'], $Totum->getUser()->getTreeTables())) {
                $put($ord, ['type' => 'table', 'name' => $tableRow['name'], 'title' => $tableRow['title']]);
            }
        }
        ksort($ordered, SORT_NUMERIC);
        return array_values($ordered);
    }

    /**
     * tab_ button of a cycles table: web shows it as a tab inside the cycle (not as a column for not creators)
     */
    public static function isCycleTabButton(aTable $Table, array $field): bool
    {
        return $Table->getTableRow()['type'] === 'cycles' && $field['category'] === 'column' && $field['type'] === 'button'
            && str_starts_with($field['name'], 'tab_') && $Table->isField('visible', 'web', $field)
            && ($Table->isUserCanAction('edit') || !empty($field['pressableOnOnlyRead']));
    }

    /**
     * calcs tables of a cycles table in the tree of the user (sort order)
     * @return array [name => title]
     */
    public static function cyclesTableTables(Totum $Totum, aTable $CyclesTable): array
    {
        $ids = array_keys($Totum->getUser()->getTreeTables());
        if (!$ids) {
            return [];
        }
        $rows = Table::init($Totum->getConfig())->getAll(['tree_node_id' => $CyclesTable->getTableRow()['id'], 'type' => 'calcs', 'is_del' => false, 'id' => $ids],
            'name, title', '(sort->>\'v\')::numeric');
        return array_column($rows, 'title', 'name');
    }

    public static function isHiddenFromTree(Totum $Totum, int $tableId): bool
    {
        return key_exists($tableId, static::hiddenFromTree($Totum));
    }

    protected function checkCycleAccess(): void
    {
        $User = $this->Totum->getUser();
        $CyclesTable = $this->Cycle->getCyclesTable();
        if (!$User->isCreator() && !empty($CyclesTable->getFields()['creator_id'])
            && in_array($CyclesTable->getTableRow()['cycles_access_type'], [1, 2, 3])
            && count(array_intersect((array)($this->Cycle->getRow()['creator_id']['v'] ?? []), $User->getConnectedUsers())) === 0) {
            if ((string)$CyclesTable->getTableRow()['cycles_access_type'] === '3') {
                $this->onlyRead = true;
            } else {
                throw new errorException($this->translate('Access to the cycle is denied.'));
            }
        }
    }

    public function getTable(): aTable
    {
        return $this->Table;
    }

    public function isOnlyRead(): bool
    {
        return $this->onlyRead;
    }

    /**
     * @param array $post parsedBody in the format of web-client
     * @param bool $forWrite
     * @return MCPReadTableActions|MCPWriteTableActions
     * @throws errorException
     */
    public function getActions(array $post, bool $forWrite)
    {
        if ($forWrite && $this->onlyRead) {
            throw new errorException($this->translate('Your access to this table is read-only. Contact administrator to make changes.'));
        }
        $post['ids'] = $post['ids'] ?? '[]';

        $request = (new ServerRequest('POST', self::MODULE_PATH, [], null, '1.1', ['REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? null]))
            ->withParsedBody($post);

        return $this->onlyRead
            ? new MCPReadTableActions($request, self::MODULE_PATH, $this->Table, null)
            : new MCPWriteTableActions($request, self::MODULE_PATH, $this->Table, null);
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->Totum->getLangObj()->translate($str, $vars);
    }
}

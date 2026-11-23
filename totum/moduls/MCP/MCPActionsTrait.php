<?php

namespace totum\moduls\MCP;

use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\models\TmpTables;
use totum\models\UserV;
use totum\moduls\Table\WriteTableActions;
use totum\tableTypes\aTable;

/**
 * Agent-specific overrides of ReadTableActions/WriteTableActions:
 * stateless plain filters, always common view, full changed rows after modifications.
 */
trait MCPActionsTrait
{
    public const MCP_MAX_CHANGED_ROWS = 50;

    /**
     * Filters come from the agent as plain {filterName: value} or as the crypted string of a link (like ?f= of the web page) —
     * it is decrypted by the core. Permission check stays in getPermittedFilters
     */
    protected function deCryptFilters($filtersIn)
    {
        if (is_array($filtersIn)) {
            return $filtersIn;
        }
        if (!is_string($filtersIn) || $filtersIn === '') {
            return [];
        }
        /* web silently ignores a wrong string; the agent would get the table without the filters of the link */
        if (Crypt::getDeCrypted($filtersIn) === false) {
            throw new errorException('Crypted filters are not valid: open the window (press the button) again to get a fresh "filters" string');
        }
        return parent::deCryptFilters($filtersIn);
    }

    protected function getPageViewType(): string
    {
        if ($this->post['restoreView'] ?? false) {
            $this->creatorCommonView = true;
        }
        return 'common';
    }

    protected function isAgentInterface(): bool
    {
        return true;
    }

    public function mcpIsWrite(): bool
    {
        return is_a($this, WriteTableActions::class);
    }

    /**
     * Optimistic lock by code of "updated" from the previous answer
     */
    public function mcpCheckUpdated($code): void
    {
        if ($code === null || $code === '') {
            return;
        }
        $updated = json_decode($this->Table->getSavedUpdated() ?? '', true);
        if ((string)($updated['code'] ?? '') !== (string)$code) {
            throw new errorException($this->translate('Table [[%s]] was changed. Update the table to make the changes.',
                    $this->Table->getTableRow()['title']) . ' (updated code ' . ($updated['code'] ?? '') . ')');
        }
    }

    public function mcpSchema(): array
    {
        $this->mcpInitTableData();
        $this->Table->reCalculateFilters('web', false, false, ['params' => $this->getPermittedFilters($this->post['filters'] ?? [])]);
        return $this->getTableClientForm();
    }

    public function mcpTableFormat(): array
    {
        $this->mcpInitTableData();
        $this->Table->reCalculateFilters('web', false, false, ['params' => $this->getPermittedFilters($this->post['filters'] ?? [])]);
        return $this->getTableFormat([]);
    }

    /**
     * A new table of a cycle has no saved data until it is opened (web calculates it on the opening of the page)
     */
    protected function mcpInitTableData(): void
    {
        if (!is_array($this->Table->getTbl()['params'] ?? null)) {
            $this->Table->reCalculateFromOvers(['calculate' => aTable::CALC_INTERVAL_TYPES['changed'], 'channel' => 'web']);
        }
    }

    protected ?array $mcpTextsBefore = null;

    /**
     * Snapshot of tabletext/tablehtml/tablecomment before an action: the answer shows them only if the action changed them
     */
    public function mcpRememberTableTexts(): void
    {
        $this->mcpTextsBefore = MCPMarkdown::tableTexts($this->mcpTableFormat());
    }

    public function mcpTableTextsBefore(): ?array
    {
        return $this->mcpTextsBefore;
    }

    /**
     * Like getFullTableData (page opening in web) + paging
     */
    public function mcpReadTable(int $offset, int $limit, array $ids, bool $recalculate): array
    {
        $addFilters = $this->getPermittedFilters($this->post['filters'] ?? []);
        if ($recalculate) {
            $this->Table->reCalculateFromOvers(['calculate' => aTable::CALC_INTERVAL_TYPES['changed'], 'channel' => 'web', 'addFilters' => $addFilters]);
        }
        $this->Table->reCalculateFilters('web', true, $addFilters);

        $result = ['form' => $this->getTableClientForm()];

        if (method_exists($this->Table, 'withoutNotLoaded')) {
            $this->Table->withoutNotLoaded();
        }
        $data = $this->Table->getSortedFilteredRows('web', 'web', $ids, ['offset' => $offset], 0, $limit);

        $rowIds = array_column($data['rows'], 'id');
        $result['params'] = $this->addValuesAndFormatsOfParams($this->Table->getTbl()['params'], $rowIds)['params'] ?? [];
        $result['f'] = $this->getTableFormat($rowIds);
        $result['rows'] = $this->mcpOrderRows($data['rows'], $result['f']);
        $result['offset'] = $data['offset'] ?? $offset;
        $result['allCount'] = $data['allCount'] ?? count($data['rows']);
        $result['updated'] = $this->Table->getSavedUpdated();
        return $result;
    }

    protected function mcpOrderRows(array $rows, array &$f): array
    {
        if (!$rows || empty($f['order'])) {
            return $rows;
        }
        $ordered = [];
        $other = [];
        foreach ($rows as $row) {
            $k = array_search($row['id'], $f['order']);
            if ($k !== false) {
                $ordered[$k] = $row;
            } else {
                $other[] = $row;
            }
        }
        ksort($ordered);
        unset($f['order']);
        return array_merge(array_values($ordered), $other);
    }

    /**
     * Blocks from formats (setFormat block, setRowFormat block/blockdelete/blockduplicate/blockorder,
     * setTableFormat block/blockadd/blockdelete/blockduplicate/blockorder) are not checked by the core on write
     * (web UI only disables controls, a server check exists only where it is coded explicitly),
     * so the agent interface checks them on the server side before changes.
     *
     * @param string $action edit|add|delete|duplicate|reorder|click
     * @param array $ids rows of the action
     * @param array $cells changed cells: [rowId => [fields], 'params' => [fields]]
     */
    public function mcpCheckBlocks(string $action, array $ids = [], array $cells = []): void
    {
        $flag = ['edit' => 'block', 'add' => 'blockadd', 'delete' => 'blockdelete', 'duplicate' => 'blockduplicate', 'reorder' => 'blockorder', 'click' => null][$action];
        $this->Table->reCalculateFilters('web', false, false, ['params' => $this->getPermittedFilters($this->post['filters'] ?? [])]);

        $rowIds = array_values(array_unique(array_map('intval', array_merge($ids, array_keys(array_diff_key($cells, ['params' => 1]))))));
        $tableFormat = $this->getTableFormat($rowIds);
        if ($flag && !empty($tableFormat[$flag])) {
            throw new errorException('The table is blocked by format (#' . $flag . '): ' . $action . ' is not allowed');
        }
        if ($action === 'add') {
            return;
        }

        $clearName = fn($name) => explode('/', (string)$name)[0];
        if ($rowIds) {
            $loaded = $this->Table->loadFilteredRows('web', $rowIds);
            $rows = array_values(array_intersect_key($this->Table->getTbl()['rows'], array_flip($loaded)));
            $fieldNames = [];
            foreach (array_diff_key($cells, ['params' => 1]) as $fields) {
                array_push($fieldNames, ...array_map($clearName, $fields));
            }
            $formatted = $this->mcpFormat(['rows' => $rows], 'web', $loaded, fieldNames: array_values(array_unique($fieldNames)));
            foreach ($formatted['rows'] as $row) {
                if ($flag && $action !== 'click' && !empty($row['f'][$flag])) {
                    throw new errorException('Row ' . $row['id'] . ' is blocked by format (#' . $flag . '): ' . $action . ' is not allowed');
                }
                foreach ($cells[$row['id']] ?? [] as $name) {
                    if (!empty($row[$clearName($name)]['f']['block'])) {
                        throw new errorException('Field ' . $clearName($name) . ' of row ' . $row['id'] . ' is blocked by format (#block)');
                    }
                }
            }
        }
        if (!empty($cells['params'])) {
            $names = array_values(array_unique(array_map($clearName, $cells['params'])));
            $params = $this->mcpFormat(['params' => array_intersect_key($this->Table->getTbl()['params'], array_flip($names))],
                'web', [], fieldNames: $names)['params'] ?? [];
            foreach ($params as $name => $param) {
                if (!empty($param['f']['block'])) {
                    throw new errorException('Field ' . $name . ' is blocked by format (#block)');
                }
            }
        }
    }

    public function mcpEdit(array $modify, array $reset): array
    {
        if (!$this->mcpIsWrite()) {
            throw new errorException($this->translate('Your access to this table is read-only. Contact administrator to make changes.'));
        }
        return $this->modify(['modify' => $modify, 'setValuesToDefaults' => $reset]);
    }

    /**
     * Without $afterId rows get the positions they occupy now in the given order (full sort = all ids),
     * with $afterId rows are moved after this row (0 — to the beginning)
     */
    public function mcpReorder(array $ids, ?int $afterId): array
    {
        if (!$this->mcpIsWrite() || !$this->Table->isUserCanAction('reorder')) {
            throw new errorException($this->translate('You are not allowed to sort in this table'));
        }
        if (empty($this->Table->getTableRow()['with_order_field'])) {
            throw new errorException('Table ' . $this->Table->getTableRow()['name'] . ' has no manual order (n-field)');
        }
        $this->Table->checkIsUserCanViewIds('web', array_merge($ids, $afterId ? [$afterId] : []));
        if ($afterId === 0) {
            /* core ignores addAfter = 0: put rows after the first row, then move the first row after them */
            $firstId = $this->Table->getByParams(['field' => 'id', 'where' => [['field' => 'id', 'operator' => '!=', 'value' => $ids]],
                'order' => [['field' => 'n', 'ad' => 'asc']]], 'field');
            if (!$firstId) {
                return $this->modify(['reorder' => $ids]);
            }
            $this->modify(['reorder' => $ids, 'addAfter' => (int)$firstId]);
            return $this->modify(['reorder' => [(int)$firstId], 'addAfter' => end($ids)]);
        }
        return $this->modify(['reorder' => $ids] + (is_null($afterId) ? [] : ['addAfter' => $afterId]));
    }

    /**
     * Data of the right-click panel of a cell: formatted cell, full value, panel formats (p-sections),
     * select previews and log of manual changes
     */
    public function mcpCell(string $fieldName, ?int $id, int $logLimit): array
    {
        $this->Table->reCalculateFilters('web', false, false, ['params' => $this->getPermittedFilters($this->post['filters'] ?? [])]);
        $fields = $this->Table->getVisibleFields('web');
        if (!($field = $fields[$fieldName] ?? null)) {
            throw new errorException($this->translate('Access to the field is denied'));
        }
        $isColumn = $field['category'] === 'column';
        if ($isColumn) {
            if (!$id) {
                throw new errorException('Field ' . $fieldName . ' is a column: parameter "id" (row id) is required');
            }
            $this->Table->checkIsUserCanViewIds('web', [$id]);
            $item = $this->Table->getTbl()['rows'][$id];
            $mainField = $this->Table->getTableRow()['main_field'] ?? null;
            $names = array_values(array_unique(array_filter([$fieldName, $mainField && key_exists($mainField, $fields) ? $mainField : null])));
            $formatted = $this->mcpFormat(['rows' => [$item]], 'web', [$id], fieldNames: $names)['rows'][0];
            $cell = $formatted[$fieldName] ?? ['v' => null];
            $rowF = $formatted['f'] ?? [];
            $rowTitle = $mainField && $mainField !== $fieldName && isset($formatted[$mainField]) ? $formatted[$mainField] : null;
        } else {
            $item = $this->Table->getTbl()['params'];
            $cell = $this->mcpFormat(['params' => [$fieldName => $item[$fieldName] ?? ['v' => null]]], 'web',
                    [], fieldNames: [$fieldName])['params'][$fieldName] ?? ['v' => null];
        }

        $full = in_array($field['type'], ['text', 'comments', 'file', 'listRow'])
            ? $this->getValue(['fieldName' => $fieldName, 'rowId' => $id])['value']
            : null;

        $Field = Field::init($field, $this->Table);
        $panel = $Field->getPanelFormat($item, $this->Table->getTbl());

        $previews = null;
        $value = $item[$fieldName]['v'] ?? null;
        if ($field['type'] === 'select' && $value !== null && $value !== [] && $this->mcpWithPreview($field)) {
            $previews = $Field->getPreviewHtml(['v' => $value], $item, $this->Table->getTbl());
        }

        return [
            'field' => $field,
            'cell' => $cell,
            'rowF' => $rowF ?? [],
            'rowTitle' => $rowTitle ?? null,
            'full' => $full,
            'panel' => $panel,
            'previews' => $previews,
            'log' => $logLimit > 0 ? $this->mcpFieldLog($field, $isColumn ? $id : null, $logLimit) : false,
            'form' => $this->getTableClientForm([$fieldName]),
            'f' => $this->getTableFormat($isColumn ? [$id] : []),
        ];
    }

    protected function mcpWithPreview(array $field): bool
    {
        foreach ((array)($field['codeSelect'] ?? []) as $code) {
            if (is_string($code) && preg_match('/(selectRowListForSelect|selectListAssoc)\([^)]*(preview|previewscode)\s*:/i', $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Why the log of manual changes of the field is not available (the same rules as the "Log" button of the web panel, fieldsForClient logButton)
     * @return string|null null — the log is available
     */
    public function mcpLogUnavailable(array $field): ?string
    {
        return match (true) {
            $this->Table->getTableRow()['type'] === 'tmp' => 'tmp tables are not logged',
            $field['type'] === 'button' => 'buttons have no log',
            $field['category'] === 'filter' => 'filters have no log',
            !($field['logging'] ?? true) => 'logging is off for the field',
            !empty($field['logRoles']) && !array_intersect($field['logRoles'], $this->User->getRoles()) => 'the log of the field is not available for your roles (logRoles)',
            default => null,
        };
    }

    /**
     * Log of manual changes with the same access rules as the "Log" button of the web panel
     * @return array|string string — the log is not available for the field (reason)
     */
    protected function mcpFieldLog(array $field, ?int $id, int $limit): array|string
    {
        if ($reason = $this->mcpLogUnavailable($field)) {
            return $reason;
        }
        $logs = $this->Totum->totumActionsLogger()->getLogs(
            $this->Table->getTableRow()['id'],
            $this->Table->getCycle()?->getId(),
            $id,
            $field['name']
        );
        $UserV = $this->Totum->getNamedModel(UserV::class);
        $result = ['count' => count($logs), 'rows' => []];
        foreach (array_slice($logs, -$limit) as $log) {
            $log['user_modify'] = $UserV->getFio($log['user_modify']);
            $result['rows'][] = $log;
        }
        return $result;
    }

    /**
     * Button of linkToDataJson window: the code from the hash gets the JSON as $#value
     */
    public function mcpJsonWindowClick(): array
    {
        $this->linkJsonClick();
        return $this->getTableClientChangedData([]);
    }

    /**
     * Export / import buttons of web for the table: csv (role right csv / csv_edit), xlsx (services xlsx / xlsximport)
     */
    public function mcpExportInfo(): array
    {
        $tech = $this->Totum->getConfig()->isTechTable($this->Table->getTableRow()['name']);
        return [
            'csv' => $this->Table->isUserCanAction('csv'),
            'xlsx' => !$tech && $this->isTableServiceOn('xlsx'),
            'import' => ($this->mcpIsWrite() && $this->Table->isUserCanAction('csv_edit')) || (!$tech && $this->mcpIsWrite() && $this->isTableServiceOn('xlsximport')),
        ];
    }

    /**
     * CSV export like the button of web (csvExport: full — params, filters, rows, footers in the format of the csv import; rows — only rows)
     * @param array $ids rows in the order of web (all filtered rows)
     * @return string csv
     */
    public function mcpCsvExport(array $ids, array $fields, string $type): string
    {
        if (!$this->Table->isUserCanAction('csv')) {
            throw new errorException($this->translate('Csv download of this table is not allowed for your role.'));
        }
        $this->Table->reCalculateFilters('web', false, false, ['params' => $this->getPermittedFilters($this->post['filters'] ?? [])]);
        /* empty ids would mean "all loaded rows" for the core */
        return $this->Table->csvExport(null, json_encode($ids ?: [0]), $fields, $type)['csv'];
    }

    /**
     * Excel export like the button of web (excelExport: the data of the cells → ServiceXlsxGenerator → linkToFileDownload)
     * @param array $data rows of cells (the first one — titles)
     */
    public function mcpExcelExport(array $data, string $title): void
    {
        if ($this->Totum->getConfig()->isTechTable($this->Table->getTableRow()['name']) || !$this->isTableServiceOn('xlsx')) {
            throw new errorException('Excel export is not switched on for this table (service xlsx): use the csv format');
        }
        $this->unblockServices();
        $this->post['data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->post['title'] = $title;
        $this->excelExport();
    }

    /**
     * Comments not read by the user (web underlines the cell): the last ones after the viewed count, if the last comment is not his own
     */
    public function mcpUnreadComments(string $fieldName, ?int $id): int
    {
        $field = $this->Table->getVisibleFields('web')[$fieldName] ?? null;
        if (!$field || $field['type'] !== 'comments' || ($field['category'] === 'column' && !$id)) {
            return 0;
        }
        if ($id) {
            $this->Table->checkIsUserCanViewIds('web', [$id]);
        }
        $comments = ($id ? $this->Table->getTbl()['rows'][$id][$fieldName]['v'] ?? null : $this->Table->getTbl()['params'][$fieldName]['v'] ?? null);
        if (!is_array($comments) || !$comments || (string)(end($comments)[1] ?? '') === (string)$this->User->getId()) {
            return 0;
        }
        $viewed = Field::init($field, $this->Table)->getViewedForUsers([$this->User->getId()], $id)[0]['nums'] ?? 0;
        return max(0, count($comments) - (int)$viewed);
    }

    /**
     * Mark the comments of the cells as read like web after opening them (setCommentsViewed: nums = count of the comments)
     * @param array $ids rows; [] — params / footer field
     * @return array [row id | 'params' => [count, unread before]]
     */
    public function mcpMarkCommentsRead(string $fieldName, array $ids): array
    {
        $field = $this->Table->getVisibleFields('web')[$fieldName] ?? null;
        if (!$field) {
            throw new errorException($this->translate('Access to the field is denied'));
        }
        if ($field['type'] !== 'comments') {
            throw new errorException('Field ' . $fieldName . ' is not a comments field');
        }
        if (($field['category'] === 'column') !== (bool)$ids) {
            throw new errorException($field['category'] === 'column' ? 'Field ' . $fieldName . ' is a column: pass "ids" (rows)'
                : 'Field ' . $fieldName . ' is not a column: do not pass ids');
        }
        $Field = Field::init($field, $this->Table);
        $result = [];
        if ($ids) {
            $this->Table->checkIsUserCanViewIds('web', $ids);
        }
        foreach ($ids ?: [null] as $id) {
            $unread = $this->mcpUnreadComments($fieldName, $id);
            $comments = $id ? $this->Table->getTbl()['rows'][$id][$fieldName]['v'] ?? [] : $this->Table->getTbl()['params'][$fieldName]['v'] ?? [];
            $count = is_array($comments) ? count($comments) : 0;
            if ($count) {
                $Field->setViewed($count, $id);
            }
            $result[$id ?? 'params'] = [$count, $unread];
        }
        return $result;
    }

    /**
     * linkToFileUpload: the code from the hash gets the files [{name, filestring}] as $#input (post files: [{name, base64}] like the browser)
     */
    public function mcpFilesUpload(): array
    {
        $this->filesUpload();
        return $this->getTableClientChangedData([]);
    }

    /**
     * Files of a cell now — the files kept by a new value of a file field
     * @param int|string|null $item row id / hash of the insert row (i-…) / hash of the panel (e-…) / null — params
     */
    public function mcpCellFiles(string $fieldName, int|string|null $item): array
    {
        $value = $this->mcpItemRow($item)[$fieldName]['v'] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * Row of a write now: row / insert row / panel (with its not saved changes) / params
     * @param int|string|null $item row id / hash of the insert row (i-…) / hash of the panel (e-…) / null — params
     * @return array [field => ['v' => …]]
     */
    public function mcpItemRow(int|string|null $item): array
    {
        if (is_int($item)) {
            $this->Table->checkIsUserCanViewIds('web', [$item]);
            return $this->Table->getTbl()['rows'][$item] ?? [];
        }
        if (is_string($item) && str_starts_with($item, 'e-')) {
            return $this->getEditRow($item, ['id' => $this->mcpPanelId($item)], []);
        }
        if (is_string($item)) {
            $this->mcpCheckInsertHash($item);
            return $this->getInsertRow($item);
        }
        return $this->Table->getTbl()['params'] ?? [];
    }

    /**
     * Button of linkToInput / linkToInputSelect window: the code from the hash gets the value as $#input
     * (select: the core checks the value against the options of codeselect)
     */
    public function mcpInputClick(mixed $value): array
    {
        $data = json_decode((string)$this->Totum->getModel('_tmp_tables', true)->getField('tbl',
            ['table_name' => '_linkToInput', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null]), true);
        if (!$data) {
            throw new errorException($this->translate('The proposed input is outdated.') . ' The hash works once: press the opening button again');
        }
        if (($data['type'] ?? null) === 'select') {
            if (empty($data['multiple']) && is_array($value)) {
                throw new errorException('The select of the input takes one value (a key), not a list');
            }
        } elseif (!is_string($value)) {
            /* web sends the text of the input (or of the textarea ttmInput of an html form) */
            $value = is_scalar($value) ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $this->post['val'] = $value;
        $this->linkInputClick();
        return $this->getTableClientChangedData([]) + ['mcpInputClose' => !empty($data['close'])];
    }

    /**
     * Options of linkToInputSelect like the select of the web window: cut list (selectLength) and search by q
     */
    public function mcpInputSelectOptions(string $q): array
    {
        $this->post['search'] = ['q' => $q];
        return $this->linkInputClick();
    }

    /**
     * linkToEdit: data of the hash (table, field, id) + the linked table, field and row (current value)
     * @return array [data, aTable, field, item (row or params)]
     */
    public function mcpEditFieldData(string $hash): array
    {
        try {
            $data = TmpTables::init($this->Totum->getConfig())->getByHash(TmpTables::SERVICE_TABLES['linktoedit'], $this->User, $hash);
        } catch (errorException $e) {
            throw new errorException($e->getMessage() . ' Press the opening button again');
        }
        $Table = $this->Totum->getTable($data['table']['name'], $data['table']['extra'] ?? null);
        $field = $Table->getFields()[$data['table']['field']] ?? null;
        if (!$field) {
            throw new errorException('The field of the edit window is not found');
        }
        if (!empty($data['table']['id'])) {
            $Table->loadFilteredRows('inner', [$data['table']['id']]);
            $item = $Table->getTbl()['rows'][$data['table']['id']] ?? throw new errorException($this->translate('Row not found'));
        } else {
            $item = $Table->getTbl()['params'];
        }
        return [$data, $Table, $field, $item];
    }

    /**
     * Options of the select / tree of the edit window like web (saveLinkToEdit with search)
     */
    public function mcpEditFieldOptions(string $hash, string $q, string|array|null $parentId): array
    {
        $this->post['shash'] = $hash;
        $this->post['search'] = ['q' => $q, 'parentId' => $parentId];
        return $this->saveLinkToEdit();
    }

    /**
     * Save button of the edit window: the field of the linked table is changed via inner channel (the code gives the access, like web);
     * special: setValuesToDefaults (reset manual value) / setValuesToPinned (pin the calculated value)
     */
    public function mcpEditFieldSave(string $hash, mixed $value, ?string $special): array
    {
        $this->post['shash'] = $hash;
        $this->post['data'] = $value;
        unset($this->post['search']);
        if ($special) {
            $this->post['special'] = $special;
        }
        $this->saveLinkToEdit();
        return $this->getTableClientChangedData([]);
    }

    /**
     * Button of the panel of a cell (panelButton / panelButtons of the format); refresh of the button — web refreshes the table after it
     */
    public function mcpPanelButtonClick(): array
    {
        $buttons = json_decode((string)$this->Totum->getModel('_tmp_tables', true)->getField('tbl',
            ['table_name' => '_panelbuttons', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null]), true);
        if (!$buttons) {
            throw new errorException('The panel buttons are outdated: take a fresh hash with get_cell');
        }
        $button = array_values(array_filter($buttons, fn($b) => ($b['ind'] ?? null) === ($this->post['index'] ?? null)))[0]
            ?? throw new errorException('No button with this index in the panel: take hash and index from a fresh get_cell');
        $this->panelButtonsClick();
        return $this->getTableClientChangedData([]) + ['mcpRefresh' => $button['refresh'] ?? false];
    }

    /**
     * Button of linkToButtons window: the code of the button runs in the environment of the hash;
     * web closes the window after any button (the core keeps the hash until cleanup)
     */
    public function mcpButtonsClick(int $index): array
    {
        $data = json_decode((string)$this->Totum->getModel('_tmp_tables', true)->getField('tbl',
            ['table_name' => '_linkToButtons', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null]), true);
        if (!$data) {
            throw new errorException($this->translate('The choice is outdated.') . ' Press the opening button again');
        }
        $button = $data['buttons'][$index] ?? throw new errorException('No button with index ' . $index . ' in the window (indexes 0–' . (count($data['buttons'] ?? []) - 1) . ')');
        $this->post['index'] = $index;
        $this->linkButtonsClick();
        return $this->getTableClientChangedData([]) + ['mcpRefresh' => $button['refresh'] ?? $data['refresh'] ?? false, 'mcpClose' => !empty($data['close'])];
    }

    /**
     * Comments: the user can edit his last comment if it is the last one of the field (web sends {value, editLastComment: old text})
     * @param int|null $id row; null — params
     */
    public function mcpEditLastComment(string $fieldName, ?int $id, mixed $text): array
    {
        if (!is_string($text) || $text === '') {
            throw new errorException('edit_last of ' . $fieldName . ' must be a non-empty text');
        }
        if ($id) {
            $this->Table->checkIsUserCanViewIds('web', [$id]);
            $comments = $this->Table->getTbl()['rows'][$id][$fieldName]['v'] ?? [];
        } else {
            $comments = $this->Table->getTbl()['params'][$fieldName]['v'] ?? [];
        }
        $last = is_array($comments) && $comments ? end($comments) : null;
        if (!is_array($last) || (string)($last[1] ?? '') !== (string)$this->User->getId()) {
            throw new errorException('Field ' . $fieldName . ($id ? ' of row ' . $id : '') . ': only your own comment can be edited, and only if it is the last one of the field'
                . ' — add a new comment (text) instead');
        }
        return ['value' => $text, 'editLastComment' => $last[2] ?? ''];
    }

    /**
     * Web insert row: a row for adding with its own hash (i-…) in the storage of the user (TmpTables insert_row).
     * Every change is recalculated by the core like in web (checkInsertRow): code fields, selects depending on other fields;
     * button codes change it with set(hash: $#ih). Nothing is added to the table until add with this hash.
     * @param array $reset fields returned to calculated values (clearField of web)
     */
    public function mcpInsertRow(?string $hash, array $data = [], array $reset = []): array
    {
        $this->mcpCheckInsert();
        if (!$hash) {
            $hash = TmpTables::init($this->Totum->getConfig())->getNewHash(TmpTables::SERVICE_TABLES['insert_row'], $this->User, [], 'i');
        } else {
            $this->mcpCheckInsertHash($hash);
        }
        if ($data) {
            $before = $this->mcpFormatInsertRow($this->getInsertRow($hash));
            foreach (array_keys($data) as $name) {
                $name = explode('/', (string)$name)[0];
                if (!empty($before[$name]['f']['block'])) {
                    throw new errorException('Field ' . $name . ' of the insert row is blocked by format (#block)');
                }
            }
        }
        foreach ($reset as $name) {
            $this->getInsertRow($hash, [], [], $name);
        }
        return ['row' => $this->mcpFormatInsertRow($this->getInsertRow($hash, $data)), 'hash' => $hash, 'f' => $this->getTableFormat([])];
    }

    /**
     * Button of the insert row: only with buttonActiveOnInsert (web blocks the others) and not blocked by the format of the cell
     */
    public function mcpInsertRowClick(string $hash, string $fieldName, array $checkedIds = []): array
    {
        $this->mcpCheckInsert();
        $this->mcpCheckInsertHash($hash);
        $field = $this->Table->getVisibleFields('web')[$fieldName] ?? null;
        if (!$field || $field['category'] !== 'column' || $field['type'] !== 'button' || empty($field['buttonActiveOnInsert'])) {
            throw new errorException('Field ' . $fieldName . ' is not a button active in the insert row (buttonActiveOnInsert)');
        }
        if (!empty($this->mcpFormatInsertRow($this->getInsertRow($hash))[$fieldName]['f']['block'])) {
            throw new errorException('Field ' . $fieldName . ' of the insert row is blocked by format (#block)');
        }
        $this->post['data'] = json_encode(['item' => $hash, 'fieldName' => $fieldName] + ($checkedIds ? ['checked_ids' => $checkedIds] : []));
        return $this->click();
    }

    /**
     * Edit panel of a row (linkToPanel with id, panel of web): changes are collected in the storage of the hash (e-…, TmpTables edit_row)
     * and recalculated like in web (checkEditRow — without saving); mcpSavePanel saves them at once.
     * @param array $data {field: value}
     * @param array $reset fields returned to calculated values
     */
    public function mcpEditPanel(?string $hash, ?int $id, array $data = [], array $reset = []): array
    {
        if (!$this->mcpIsWrite() || !$this->Table->isUserCanAction('edit')) {
            throw new errorException($this->translate('Your access to this table is read-only. Contact administrator to make changes.')
                . ' Read the row with get_row');
        }
        if (!$hash) {
            if (!$id) {
                throw new errorException('Parameter "id" (row) is required to open a panel');
            }
            $this->Table->checkIsUserCanViewIds('web', [$id]);
            $hash = TmpTables::init($this->Totum->getConfig())->getNewHash(TmpTables::SERVICE_TABLES['edit_row'], $this->User, ['id' => $id], 'e');
        }
        $id = $this->mcpPanelId($hash, $id);
        $edit = [];
        foreach ($data as $name => $value) {
            /* checkEditRow takes only {v: …} values */
            $edit[$name] = ['v' => $value];
        }
        foreach ($reset as $name) {
            $edit[$name] = ['v' => null, 'h' => false];
        }
        if ($edit) {
            $before = $this->mcpFormatInsertRow($this->getEditRow($hash, ['id' => $id], []));
            if (!empty($before['f']['block'])) {
                throw new errorException('Row ' . $id . ' is blocked by format (#block): edit is not allowed');
            }
            foreach (array_keys($edit) as $name) {
                if (!empty($before[explode('/', (string)$name)[0]]['f']['block'])) {
                    throw new errorException('Field ' . $name . ' of row ' . $id . ' is blocked by format (#block)');
                }
            }
        }
        $raw = $this->getEditRow($hash, $edit + ['id' => $id], []);
        return ['row' => $this->mcpFormatInsertRow($raw), 'hash' => $hash, 'id' => $id, 'f' => $this->getTableFormat([$id]),
            'changed' => array_values(array_diff(array_keys(TmpTables::init($this->Totum->getConfig())
                ->getByHash(TmpTables::SERVICE_TABLES['edit_row'], $this->User, $hash)), ['id']))];
    }

    /**
     * Save button of the panel: all collected changes at once (saveEditRow, channel web)
     * @return array [changed data of the table, saved fields]
     */
    public function mcpSavePanel(string $hash): array
    {
        $id = $this->mcpPanelId($hash);
        $stored = TmpTables::init($this->Totum->getConfig())->getByHash(TmpTables::SERVICE_TABLES['edit_row'], $this->User, $hash);
        $fields = array_values(array_diff(array_keys($stored), ['id']));
        $this->mcpCheckBlocks('edit', [], [$id => $fields]);
        $this->post['data'] = json_encode(['id' => $id] + $stored, JSON_UNESCAPED_UNICODE);
        $result = $this->saveEditRow();
        TmpTables::init($this->Totum->getConfig())->deleteByHash(TmpTables::SERVICE_TABLES['edit_row'], $this->User, $hash);
        return [$result, $stored];
    }

    /**
     * Button of the panel: the code gets the unsaved values of the panel as #pnl.field (__edit_hash)
     */
    public function mcpPanelClick(string $hash, string $fieldName): array
    {
        $id = $this->mcpPanelId($hash);
        $this->post['data'] = json_encode(['item' => $id, 'fieldName' => $fieldName, 'hash' => $hash]);
        $this->post['hash'] = $hash;
        return $this->mcpClick();
    }

    /**
     * Row of the panel by its hash (the storage keeps the id)
     */
    public function mcpPanelId(string $hash, ?int $id = null): int
    {
        if (!str_starts_with($hash, 'e-')) {
            throw new errorException('panel_hash must be the hash of a panel (e-…) given by edit_panel');
        }
        try {
            $stored = TmpTables::init($this->Totum->getConfig())->getByHash(TmpTables::SERVICE_TABLES['edit_row'], $this->User, $hash);
        } catch (errorException $e) {
            throw new errorException($e->getMessage() . ' The panel ' . $hash . ' is not found (saved or expired): open it again with edit_panel');
        }
        $storedId = (int)($stored['id'] ?? 0);
        if (!$storedId || ($id && $id !== $storedId)) {
            throw new errorException('The panel ' . $hash . ' is of row ' . $storedId . ($id ? ', not ' . $id : ''));
        }
        return $storedId;
    }

    /**
     * Button of a select with a source table (selectTable): web opens a panel of the selected row of the source table
     * (or a new row there — plus) by selectTableAction (default linkToPanel)
     * @param int|string|null $item row id / insert row hash (i-…) / panel hash (e-…) / null — params
     */
    public function mcpSelectSource(string $fieldName, int|string|null $item, bool $isPlus): void
    {
        $this->post['field_name'] = $fieldName;
        $this->post['isPlus'] = $isPlus ? 'true' : 'false';
        if (is_int($item)) {
            $this->Table->checkIsUserCanViewIds('web', [$item]);
            $this->post['data'] = json_encode($this->Table->getTbl()['rows'][$item], JSON_UNESCAPED_UNICODE);
        } else {
            $this->post['data'] = $item ?? json_encode($this->Table->getTbl()['params'], JSON_UNESCAPED_UNICODE);
        }
        $this->selectSourceTableAction();
    }

    protected function mcpCheckInsert(): void
    {
        if (!$this->mcpIsWrite() || $this->User->isOneCycleTable($this->Table->getTableRow()) || !$this->Table->isUserCanAction('insert')) {
            throw new errorException($this->translate('You are not allowed to add to this table'));
        }
        $this->mcpCheckBlocks('add');
    }

    protected function mcpCheckInsertHash(string $hash): void
    {
        if (!str_starts_with($hash, 'i-')) {
            throw new errorException('insert_hash must be the hash of an insert row (i-…) given by insert_row');
        }
        try {
            TmpTables::init($this->Totum->getConfig())->getByHash(TmpTables::SERVICE_TABLES['insert_row'], $this->User, $hash);
        } catch (errorException $e) {
            throw new errorException($e->getMessage() . ' The insert row ' . $hash . ' is not found (saved or expired): open a new one with insert_row');
        }
    }

    /**
     * Values and formats for the answers in the section "views" of the calculation log, like web (SELECTS AND FORMATS):
     * the log of a call with debug.log c / a has no formatting of the answer
     */
    protected function mcpFormat(array $data, string $viewType, array $pageIds, ?array $fieldNames = null): array
    {
        $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS']);
        try {
            return $this->Table->getValuesAndFormatsForClient($data, $viewType, $pageIds, $fieldNames);
        } finally {
            $this->Table->calcLog($Log, 'result', 'done');
        }
    }

    protected function mcpFormatInsertRow(array $raw): array
    {
        $this->mcpResetSelectViewLists();
        return $this->mcpFormat(['rows' => [$raw]], 'edit', [])['rows'][0];
    }

    /**
     * Select caches the view list (titles of the values) in the Field object for the whole request (web formats once per request);
     * MCP formats before and after a change in one call — new values would be shown as #deleted
     */
    public function mcpResetSelectViewLists(): void
    {
        foreach ($this->Table->getFields() as $field) {
            /* tree has no cache of the view list */
            if ($field['type'] === 'select') {
                Field::init($field, $this->Table)->emptyCommonSelectViewList();
            }
        }
    }

    public function mcpClick(): array
    {
        $click = json_decode($this->post['data'] ?? '', true) ?? [];
        $field = $this->Table->getVisibleFields('web')[$click['fieldName'] ?? ''] ?? null;
        if (!$field) {
            throw new errorException($this->translate('Access to the field is denied'));
        }
        if ($field['type'] !== 'button' && empty($field['CodeActionOnClick'])) {
            throw new errorException('Field ' . $field['name'] . ' is not a button and has no click action');
        }
        if ($field['type'] === 'button') {
            if (!$this->mcpIsWrite() && empty($field['pressableOnOnlyRead'])) {
                throw new errorException($this->translate('Your access to this table is read-only. Contact administrator to make changes.'));
            }
            return $this->click();
        }
        $this->post['id'] = $click['item'] === 'params' ? 0 : $click['item'];
        $this->post['field'] = $field['name'];
        return $this->dblClick();
    }

    /**
     * Instead of web diff for the page: full rows that were changed/added by the action
     */
    protected function getTableClientChangedData($data, $force = false, $onlyFields = [])
    {
        if (method_exists($this->Table, 'withoutNotLoaded')) {
            $this->Table->withoutNotLoaded();
        }
        $this->Table->reCalculateFilters('web', false, false, ['params' => $this->getPermittedFilters($this->post['filters'] ?? [])]);

        $changeIds = $this->Table->getChangeIds();
        $deleted = array_keys($changeIds['deleted'] ?? []);
        $restored = array_keys($changeIds['restored'] ?? []);
        $added = array_keys($changeIds['added'] ?? []);
        $changedRows = array_diff_key($changeIds['changed'] ?? [], ['params' => true]);

        $ids = [...$added, ...array_keys(array_diff_key(($data['modify'] ?? []) + ($data['setValuesToDefaults'] ?? []), ['params' => true])),
            ...array_keys($changedRows), ...$restored];
        $ids = array_values(array_diff(array_unique(array_map('intval', $ids)), $deleted));

        $moreIds = array_slice($ids, static::MCP_MAX_CHANGED_ROWS);
        $ids = array_slice($ids, 0, static::MCP_MAX_CHANGED_ROWS);

        $loaded = $ids ? $this->Table->loadFilteredRows('web', $ids) : [];
        $rows = [];
        foreach ($loaded as $id) {
            if (!empty($this->Table->getTbl()['rows'][$id])) {
                $rows[] = $this->Table->getTbl()['rows'][$id];
            }
        }

        $paramsNames = array_merge(array_keys((array)($changeIds['changed']['params'] ?? [])), array_keys($data['modify']['params'] ?? []),
            array_keys($data['setValuesToDefaults']['params'] ?? []));
        $params = array_intersect_key($this->Table->getTbl()['params'] ?? [], array_flip($paramsNames));

        $this->mcpResetSelectViewLists();
        $formatted = $this->mcpFormat(['rows' => $rows, 'params' => $params], 'web', $loaded);

        return [
            'mcpChanges' => true,
            'rows' => $formatted['rows'] ?? [],
            'params' => $formatted['params'] ?? [],
            'f' => $this->getTableFormat($loaded),
            'added' => $added,
            'deleted' => $deleted,
            'restored' => $restored,
            'reordered' => array_keys($changeIds['reorderedIds'] ?? []) ?: !empty($changeIds['reordered']),
            'moreChangedIds' => $moreIds,
            'changedFields' => array_map(fn($fields) => is_array($fields) ? array_keys($fields) : [], $changedRows),
            'updated' => $this->Table->getSavedUpdated(),
        ];
    }
}

<?php

namespace totum\moduls\MCP;

use totum\common\errorException;
use totum\common\FormatParamsForSelectFromTable;
use totum\common\Totum;
use totum\models\Table;
use totum\tableTypes\aTable;

/**
 * Creator view (admin layer): the structure of Totum is the data of the system tables tree / tables / tables_fields,
 * changed with the usual tools like the panels of web. Here: get_field_settings, data_src of fields as {"setting": value},
 * checks of the system tables the core does not do, the creator section of get_table_schema
 */
class MCPCreator
{
    public const INSTRUCTIONS = <<<TXT
CREATOR VIEW (admin layer). The structure of Totum is data of system tables, changed with the same tools (insert_row + add_row, edit, edit_panel, delete_rows) like the panels of web:
- tree — the navigation tree: a row is a branch (type empty — folder, link, anchor), parent_id — the parent branch (empty — the top level), ord — order. Add a branch: insert_row {"table":"tree","data":{"title":…,"parent_id":<tree id>}} + add_row with insert_hash.
- tables — a row is a table, id of the row = id of the table: insert_row {"table":"tables","data":{"title":…,"type":"simple","tree_node_id":<tree id>}} + add_row. name is made from title (^[a-z][a-z0-9_]{2,30}$) and is not changed later. Types: simple, cycles (every row is a cycle), calcs (a table inside the cycles of a cycles table: tree_node_id = id of that cycles table — required), globcalcs (calculated, in the tree), tmp (temporary).
  The type is fixed after creation; a calcs table is not moved to another cycles table (only copied by the button of the panel of its name: get_cell of field name). Other settings of the table — edit of its row.
- tables_fields — a row is a field: table_id, category (simple/cycles: param, filter, column; tmp: param, column, footer; calcs/globcalcs: all four), title, name (made from title with the prefix of the category, fixed), ord, data_src — the settings of the field (the field panel of web; every type has its own settings).
  get_field_settings shows the settings of a type (with a field — its values); write data_src as {"setting": value} — only the changed settings, the others are kept (a new field takes the defaults of the panel); null switches a setting off.
  Add a field: insert_row {"table":"tables_fields","data":{"table_id":<table id>,"category":"column","title":…,"data_src":{"type":"number", …}}} + add_row with insert_hash.
- get_table_schema shows "## Creator view": the row of the table in tables and the rows (ids) of its fields in tables_fields.
- Deleting a row of tables / tables_fields deletes the table / the field WITH ITS DATA — ask the user before. System tables are not deleted.
TXT;

    protected ?MCPFieldSettings $Settings = null;

    public function __construct(protected Totum $Totum)
    {
    }

    public function fieldSettings(): MCPFieldSettings
    {
        return $this->Settings ??= new MCPFieldSettings($this->Totum);
    }

    public static function flatRow(array $row): array
    {
        return array_map(fn($cell) => is_array($cell) && key_exists('v', $cell) ? $cell['v'] : $cell, $row);
    }

    /* ======================== writes ======================== */

    /**
     * data_src of a write of tables_fields: {"setting": value} → data_src of the panel of web (merged with the current one)
     * @param array $row the row now (flat): row / insert row / panel; [] — new row
     */
    public function writeValues(aTable $Table, array $values, array $row): array
    {
        if ($Table->getTableRow()['name'] !== 'tables_fields' || !key_exists('data_src', $values)) {
            return $values;
        }
        $changes = is_string($values['data_src']) ? json_decode($values['data_src'], true) : $values['data_src'];
        if (!is_array($changes) || ($changes && array_is_list($changes))) {
            throw new errorException('data_src must be an object {"setting": value} — get_field_settings shows the settings');
        }
        $state = array_merge($row, array_diff_key($values, ['data_src' => 1]));
        if (empty($state['table_id'])) {
            throw new errorException('Set table_id of the field before (or with) data_src: the settings depend on the type of the table');
        }
        [$category, $tableType, $name] = $this->fieldContext($state);
        $values['data_src'] = $this->fieldSettings()->build(is_array($row['data_src'] ?? null) ? $row['data_src'] : [], $changes, $category, $tableType, $name);
        return $values;
    }

    /**
     * Checks of the system tables that the core does not do (selects without checkSelectValues, fixed settings)
     * @param array $changes values of the write
     * @param array $row the row now (flat): existing row / insert row; [] — new row
     * @param bool $isNew insert row / new row
     * @param bool $final the row is added now (add_row)
     */
    public function checkWrite(aTable $Table, array $changes, array $row, bool $isNew, bool $final): void
    {
        match ($Table->getTableRow()['name']) {
            'tables' => $this->checkTablesRow($changes, $row, $isNew, $final),
            'tables_fields' => $this->checkFieldsRow($changes, $row, $isNew, $final),
            default => null,
        };
    }

    protected function checkTablesRow(array $changes, array $row, bool $isNew, bool $final): void
    {
        $changed = fn(string $name) => key_exists($name, $changes) && (string)$changes[$name] !== (string)($row[$name] ?? '');
        if (!$isNew) {
            if ($changed('type')) {
                throw new errorException('The type of a table is fixed after creation (' . $row['type'] . '): create a new table of the needed type');
            }
            if ($row['type'] === 'calcs' && $changed('tree_node_id')) {
                throw new errorException('A calcs table belongs to its cycles table and is not moved to another one: copy it with the button of the panel of its name '
                    . '(duplicate to another cycles table: get_cell {"table":"tables","field":"name","id":' . ($row['id'] ?? '<id>') . '} → click_panel_button) or create a new table there');
            }
        }
        if (!$final && !key_exists('tree_node_id', $changes) && !key_exists('type', $changes)) {
            return;
        }
        $state = array_merge($row, $changes);
        $node = $state['tree_node_id'] ?? null;
        if ((string)($state['type'] ?? '') === 'calcs') {
            if ((string)$node === '') {
                if ($final) {
                    throw new errorException('A calcs table is created inside a cycles table: set tree_node_id = id of the cycles table (' . $this->cyclesTablesList() . ')');
                }
            } elseif (($this->Totum->getTableRow((int)$node)['type'] ?? null) !== 'cycles') {
                throw new errorException('tree_node_id of a calcs table is the id of its cycles table, ' . $node . ' is not a cycles table (' . $this->cyclesTablesList() . ')');
            }
        } elseif ((string)$node !== '' && !$this->Totum->getModel('tree')->executePrepared(true, ['id' => (int)$node, 'is_del' => false], 'id', null, '0,1')->fetch()) {
            throw new errorException('tree_node_id of a table is a branch of the navigation tree (a row of tree) or empty — ' . $node . ' is not found in tree');
        }
    }

    protected function checkFieldsRow(array $changes, array $row, bool $isNew, bool $final): void
    {
        if (!$isNew && key_exists('table_id', $changes) && (string)$changes['table_id'] !== (string)($row['table_id'] ?? '')) {
            throw new errorException('A field is not moved to another table: add a field there (insert_row of tables_fields) and delete this one if needed');
        }
        if (!$final && !key_exists('category', $changes) && !key_exists('table_id', $changes)) {
            return;
        }
        $state = array_merge($row, $changes);
        if (!empty($state['table_id']) && ($tableRow = $this->Totum->getTableRow((int)$state['table_id']))
            && ($allowed = MCPFieldSettings::CATEGORIES_BY_TABLE_TYPE[$tableRow['type']] ?? null)
            && !in_array((string)($state['category'] ?? 'column'), $allowed)) {
            throw new errorException('A table of type ' . $tableRow['type'] . ' has fields of categories ' . implode(', ', $allowed) . ', not ' . $state['category']);
        }
    }

    protected function cyclesTablesList(): string
    {
        $rows = Table::init($this->Totum->getConfig())->getAll(['type' => 'cycles', 'is_del' => false], 'id, name', 'id');
        return $rows ? 'cycles tables: ' . implode(', ', array_map(fn($r) => $r['id'] . ' ' . $r['name'], $rows)) : 'there are no cycles tables';
    }

    /**
     * @return array [category, table type, field name]
     */
    protected function fieldContext(array $row): array
    {
        $tableRow = !empty($row['table_id']) ? $this->Totum->getTableRow((int)$row['table_id']) : null;
        return [(string)($row['category'] ?? '') ?: 'column', $tableRow['type'] ?? null, (string)($row['name'] ?? '')];
    }

    /* ======================== get_field_settings ======================== */

    public function fieldSettingsAnswer(array $args): string
    {
        $Settings = $this->fieldSettings();
        $field = !empty($args['id']) || (!empty($args['table']) && !empty($args['field'])) ? $this->fieldRow($args) : null;
        $tableRow = $field
            ? $this->Totum->getTableRow((int)$field['table_id'])
            : (!empty($args['table']) ? ($this->Totum->getTableRow(is_int($args['table']) ? $args['table'] : (string)$args['table'])
                ?: throw new errorException('Table ' . $args['table'] . ' is not found')) : null);
        $category = (string)($field['category'] ?? $args['category'] ?? 'column');
        $tableType = $tableRow['type'] ?? null;
        $name = (string)($field['name'] ?? '');
        $dataSrc = is_array($field['data_src'] ?? null) ? $field['data_src'] : [];
        if (!empty($args['type']) || !$field) {
            $dataSrc = $Settings->build($dataSrc, !empty($args['type']) ? ['type' => (string)$args['type']] : [], $category, $tableType, $name);
        }
        $type = $dataSrc['type']['Val'];

        $out = '# Field settings · type ' . $type . ' · ' . $category . ($tableRow ? ' · table ' . $tableRow['name'] . ' (' . $tableType . ')' : '') . "\n";
        if ($field) {
            $out .= 'field: ' . $tableRow['name'] . '.' . $field['name'] . ' «' . MCPMarkdown::plain((string)$field['title']) . '» · row ' . $field['id'] . ' of tables_fields'
                . (!empty($field['version']) ? ' · version ' . $field['version'] : '') . "\n";
            if (!empty($args['type']) && $args['type'] !== ($field['data_src']['type']['Val'] ?? null)) {
                $out .= 'shown as after the change of the type to ' . $type . ' (not saved): the values of the settings common with the type '
                    . ($field['data_src']['type']['Val'] ?? '?') . " are kept, the others take the defaults\n";
            }
        } else {
            $out .= 'a new field: the defaults of the panel' . ($tableRow ? '' : ' (without table: settings for some table types only are not shown — pass table)') . "\n";
        }
        $out .= 'types: ' . implode(', ', array_map(fn($t, $title) => $t . ' (' . $title . ')', array_keys($Settings->types()), $Settings->types())) . "\n";
        $id = $field['id'] ?? '<id>';
        $out .= 'write: ' . ($field
                ? 'edit {"table":"tables_fields","rows":{"' . $id . '":{"data_src":{"<setting>": <value>}}}}'
                : 'insert_row {"table":"tables_fields","data":{"table_id":' . ($tableRow['id'] ?? '<table id>') . ',"category":"' . $category . '","title":"…","data_src":{"type":"' . $type . '", …}}} + add_row with insert_hash')
            . " — only the changed settings, the others are kept; null — switch off (checkbox — false); \"type\" — another type (another list of settings)\n";

        $rows = [];
        foreach ($Settings->states($dataSrc, $category, $tableType, $name) as $setting => [$def, $state, $val, $hiddenBy]) {
            $kind = $def['type'] . (!empty($def['multiple']) ? ' multiple' : '') . (!empty($def['required']) ? ' required' : '');
            $values = '';
            if ($def['type'] === 'select' && $setting !== 'type') {
                $values = implode('; ', array_map(fn($k, $t) => $k . ' — ' . MCPMarkdown::plain((string)$t), array_keys($Settings->values($setting)), $Settings->values($setting)));
            }
            $rows[] = [$setting, MCPMarkdown::esc(MCPMarkdown::plain((string)($def['title'] ?? ''))), $kind,
                $state === 'hidden' ? 'hidden (' . $hiddenBy . ')' : $state,
                MCPMarkdown::esc(MCPMarkdown::cut($this->settingValue($val), 200)),
                MCPMarkdown::esc(MCPMarkdown::cut($this->settingValue($def['default'] ?? null), 100)),
                $def['parent'] ?? '', MCPMarkdown::esc(MCPMarkdown::cut($values, 300))];
        }
        return $out . "\n" . MCPMarkdown::mdTable(['setting', 'title', 'kind', 'state', 'value', 'default', 'parent', 'values'], $rows)
            . "\nstate: on — works (the core takes it into the field); off — a switchable setting that is off (a value switches it on); "
            . "hidden — its parent is off: it does not work, set the parent first (checkbox true / a value)\n"
            . "kinds: checkbox — true/false; string, html; code — totum code (\"=: …\", several lines); integer, number; select — a key of values (multiple — array of keys); json — object/array\n";
    }

    /**
     * Row of tables_fields by id or by table + field name
     */
    protected function fieldRow(array $args): array
    {
        $Params = (new FormatParamsForSelectFromTable())
            ->field('id')->field('table_id')->field('name')->field('title')->field('category')->field('version')->field('data_src');
        if (!empty($args['id'])) {
            $Params->where('id', (int)$args['id']);
        } else {
            $tableRow = $this->Totum->getTableRow(is_int($args['table']) ? $args['table'] : (string)$args['table'])
                ?: throw new errorException('Table ' . $args['table'] . ' is not found');
            $Params->where('table_id', $tableRow['id'])->where('name', (string)$args['field']);
        }
        $rows = $this->Totum->getTable('tables_fields')->getByParams($Params->params(), 'rows');
        if (!$rows) {
            throw new errorException('Field is not found in tables_fields');
        }
        if (count($rows) > 1) {
            throw new errorException('The field has rows in several versions of the calcs table — pass id of the row: '
                . implode(', ', array_map(fn($r) => $r['id'] . ' (version ' . $r['version'] . ')', $rows)));
        }
        return $rows[0];
    }

    protected function settingValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => str_replace("\n", '⏎', (string)$value),
        };
    }

    /* ======================== reading ======================== */

    /**
     * get_value of data_src: the settings that work
     * @param array $row the row of tables_fields (flat)
     */
    public function describeFieldValue(mixed $dataSrc, array $row): string
    {
        if (!is_array($dataSrc)) {
            return MCPMarkdown::scalar($dataSrc) . "\n";
        }
        [$category, $tableType, $fieldName] = $this->fieldContext($row);
        $effective = MCPFieldSettings::effective($dataSrc);
        $order = array_flip(['type', ...array_keys($this->fieldSettings()->available((string)($effective['type'] ?? ''), $category, $tableType, $fieldName))]);
        uksort($effective, fn($a, $b) => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
        $lines = [];
        foreach ($effective as $name => $value) {
            $str = $this->settingValue(is_string($value) ? str_replace("\n", "\x00", $value) : $value);
            $lines[] = str_contains($str, "\x00") || mb_strlen($str) > 120
                ? '- ' . $name . ":\n~~~\n" . str_replace("\x00", "\n", $str) . "\n~~~"
                : '- ' . $name . ': ' . str_replace("\x00", '⏎', $str);
        }
        return 'type ' . ($dataSrc['type']['Val'] ?? '?') . ' · ' . $category . ' of ' . ($row['table_name'] ?? $row['table_id'] ?? '?') . ' (' . ($tableType ?? '?') . ")\n"
            . "settings that work (on in the panel of web):\n" . implode("\n", $lines) . "\n"
            . 'all settings of the type with their states: get_field_settings {"id": ' . ($row['id'] ?? '<id>') . '}; write: data_src {"setting": value} — only the changed ones' . "\n";
    }

    /**
     * get_table_schema: the row of the table in tables and its fields in tables_fields
     */
    public function schemaSection(aTable $Table): string
    {
        $tableRow = $Table->getTableRow();
        $id = $tableRow['id'];
        $isCalcs = $tableRow['type'] === 'calcs';
        if ($isCalcs) {
            $place = 'cycles table ' . ($this->Totum->getTableRow((int)$tableRow['tree_node_id'])['name'] ?? $tableRow['tree_node_id']);
        } elseif (!empty($tableRow['tree_node_id'])) {
            $title = $this->Totum->getConfig()->getSql()->getField('select title from tree__v where id = ' . (int)$tableRow['tree_node_id']);
            $place = 'tree branch ' . $tableRow['tree_node_id'] . ($title ? ' «' . MCPMarkdown::plain((string)$title) . '»' : '');
        } else {
            $place = 'out of the tree';
        }
        $codes = array_filter(Totum::TABLE_CODE_PARAMS, fn($name) => preg_match('/^\s*[a-z0-9]*=:\s*[^\s]+/mu', (string)($tableRow[$name] ?? '')));

        $out = "\n## Creator view\n"
            . 'table: row ' . $id . ' of tables · type ' . $tableRow['type'] . ' · ' . $place . ' · sort ' . ($tableRow['sort'] ?? '') . ' · actual ' . ($tableRow['actual'] ?? '')
            . ($isCalcs ? ' · version ' . ($tableRow['__version'] ?? '?') : '') . ' · codes of the table: ' . ($codes ? implode(', ', $codes) : 'none') . "\n"
            . '- settings of the table: get_row {"table":"tables","id":' . $id . '}; change — edit {"table":"tables","rows":{"' . $id . '":{…}}}'
            . ' (the type' . ($isCalcs ? ' and the cycles table are' : ' is') . " fixed)\n"
            . '- fields: rows of tables_fields — read_table {"table":"tables_fields","filters":{"f_table":"' . $id . '"'
            . ($isCalcs ? ',"fl_version":"' . ($tableRow['__version'] ?? '') . '"' : '') . '}}; settings of a field — get_field_settings {"table":"'
            . $tableRow['name'] . '","field":"<name>"}, change — edit of its data_src; a new field — insert_row of tables_fields with table_id ' . $id . "\n";

        $rows = [];
        foreach ($Table->getFields() as $name => $field) {
            $fieldCodes = [];
            foreach (['code' => 'code', 'codeAction' => 'action', 'codeSelect' => 'select', 'format' => 'format'] as $param => $title) {
                if (!empty($field[$param])) {
                    $triggers = $param === 'codeAction' ? array_keys(array_filter(['add' => 'CodeActionOnAdd', 'change' => 'CodeActionOnChange',
                        'delete' => 'CodeActionOnDelete', 'click' => 'CodeActionOnClick'], fn($flag) => !empty($field[$flag]))) : [];
                    $fieldCodes[] = $title . ($triggers ? ' (' . implode(', ', $triggers) . ')' : '');
                }
            }
            $rows[] = [$name, $field['id'] ?? '', $field['category'], $field['ord'] ?? '', $field['type'], implode(', ', $fieldCodes)];
        }
        return $out . MCPMarkdown::mdTable(['name', 'field id', 'category', 'ord', 'type', 'codes'], $rows);
    }
}

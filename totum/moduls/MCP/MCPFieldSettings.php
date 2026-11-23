<?php

namespace totum\moduls\MCP;

use totum\common\errorException;
use totum\common\Totum;

/**
 * Settings of a field (data_src of tables_fields) like the field panel of web (fieldParams of main.js):
 * the list of the settings of a type is fieldListParams[type] of the jsonFields of tables_fields.data_src,
 * a setting is not in the panel if its categories / names / tables do not match the field; every setting is {Val, isOn}:
 * checkbox and required settings are on when they are shown, others have a switcher; a setting with a parent
 * from the list is shown only when the parent is shown and on (checkbox checked / switcher on / required).
 * The core takes only the settings with isOn (fieldParamsResult::getDataFromDataSrc).
 */
class MCPFieldSettings
{
    public const SYSTEM_TYPES = ['fieldParams', 'fieldParamsResult'];
    /* categories of fields by table type like the select category of tables_fields (core does not check them) */
    public const CATEGORIES_BY_TABLE_TYPE = [
        'simple' => ['param', 'filter', 'column'],
        'cycles' => ['param', 'filter', 'column'],
        'tmp' => ['param', 'column', 'footer'],
        'globcalcs' => ['param', 'filter', 'column', 'footer'],
        'calcs' => ['param', 'filter', 'column', 'footer'],
    ];
    /* the core cannot have them in a filter (FieldParams::checkValByType) */
    public const NOT_FILTER_TYPES = ['link', 'text', 'comments', 'file', 'password', 'chart', 'unic'];
    public const CODE_KINDS = ['code', 'html'];

    protected array $settings;
    protected array $lists;
    protected array $valuesCache = [];

    public function __construct(protected Totum $Totum)
    {
        $json = $Totum->getTable('tables_fields')->getFields()['data_src']['jsonFields'] ?? [];
        $this->settings = $json['fieldSettings'] ?? [];
        $this->lists = $json['fieldListParams'] ?? [];
        if (!$this->settings || !$this->lists) {
            throw new errorException('Settings of the field panel are not found (jsonFields of tables_fields.data_src)');
        }
    }

    /**
     * @return array [type => title] in the order of the panel
     */
    public function types(): array
    {
        $values = $this->settings['type']['values'] ?? [];
        $order = $this->settings['type']['valuesOrder'] ?? array_keys($values);
        $types = [];
        foreach ($order as $type) {
            if (!in_array($type, static::SYSTEM_TYPES) && key_exists($type, $this->lists)) {
                $types[$type] = $values[$type] ?? $type;
            }
        }
        return $types;
    }

    /**
     * Settings of the kind code (code, codeAction, codeSelect, format, …) in the order of the panel settings
     * @return array [name => title]
     */
    public function codeSettings(): array
    {
        return array_map(fn($s) => (string)($s['title'] ?? ''), array_filter($this->settings, fn($s) => ($s['type'] ?? null) === 'code'));
    }

    public function checkType(string $type): void
    {
        if (!key_exists($type, $this->types())) {
            throw new errorException('Field type "' . $type . '" is not a type of the field panel. Types: ' . implode(', ', array_keys($this->types())));
        }
    }

    /**
     * Settings of the panel for the field in the order of the panel
     * @param string|null $tableType settings with "tables" are not shown without the type of the table (like web)
     * @return array [name => setting]
     */
    public function available(string $type, string $category, ?string $tableType, string $fieldName): array
    {
        $result = [];
        foreach ($this->lists[$type] ?? [] as $name) {
            if (($setting = $this->settings[$name] ?? null) && !$this->filteredOut($name, $setting, $type, $category, $tableType, $fieldName)) {
                $result[$name] = $setting;
            }
        }
        return $result;
    }

    /**
     * Why the setting is not in the panel of the field (null — it is there)
     */
    protected function filteredOut(string $name, array $setting, string $type, string $category, ?string $tableType, string $fieldName): ?string
    {
        if (!empty($setting['categories']) && !($name === 'codeAction' && $type === 'button') && !in_array($category, $setting['categories'])) {
            return 'only for categories ' . implode(', ', $setting['categories']);
        }
        if (!empty($setting['names']) && !in_array($fieldName, $setting['names'])) {
            return 'only for fields named ' . implode(', ', $setting['names']);
        }
        if (!empty($setting['tables']) && !in_array($tableType, $setting['tables'])) {
            return 'only for tables of types ' . implode(', ', $setting['tables']);
        }
        return null;
    }

    /**
     * data_src after the changes like the save of the panel of web
     * @param array $current data_src now ([] — new field)
     * @param array $changes {setting: value | null (switch off) | {"Val"?: …, "isOn"?: …}}
     * @return array data_src: type + the settings of the panel
     */
    public function build(array $current, array $changes, string $category, ?string $tableType, string $fieldName): array
    {
        $current = array_map(fn($v) => is_array($v) && key_exists('isOn', $v) ? $v + ['Val' => null] : ['isOn' => true, 'Val' => $v], $current);
        $type = key_exists('type', $changes) ? $changes['type'] : ($current['type']['Val'] ?? 'string');
        if (is_array($type) && key_exists('Val', $type)) {
            $type = $type['Val'];
        }
        if (!is_string($type)) {
            throw new errorException('Setting "type" must be a field type: ' . implode(', ', array_keys($this->types())));
        }
        $this->checkType($type);
        if ($category === 'filter' && in_array($type, static::NOT_FILTER_TYPES)) {
            throw new errorException('A filter cannot be of type ' . $type . ' (not for filters: ' . implode(', ', static::NOT_FILTER_TYPES) . ')');
        }
        $list = $this->available($type, $category, $tableType, $fieldName);
        $this->checkNames($changes, $list, $type, $category, $tableType, $fieldName);

        $data = [];
        foreach ($list as $name => $setting) {
            $data[$name] = $current[$name] ?? $this->defaultState($name, $setting, $type);
            if (key_exists($name, $changes)) {
                $data[$name] = $this->applyChange($name, $setting, $data[$name], $changes[$name]);
            }
        }
        $hidden = [];
        $visible = $this->resolveVisibility($type, $list, $data, $hidden);
        foreach ($list as $name => $setting) {
            if (!$visible[$name]) {
                $data[$name]['isOn'] = false;
            } elseif ($setting['type'] === 'checkbox' || !empty($setting['required'])) {
                $data[$name]['isOn'] = true;
            }
        }
        $switchedOn = array_filter(array_diff_key($changes, ['type' => 1]),
            fn($c) => $c !== null && $c !== false && !(is_array($c) && key_exists('isOn', $c) && !$c['isOn']));
        if ($wrong = array_intersect_key($hidden, $switchedOn)) {
            $lines = [];
            foreach ($wrong as $name => $parent) {
                $lines[] = '- ' . $name . ' works only when ' . $parent . ' is on — ' . (key_exists($parent, $list)
                        ? 'set ' . $parent . ' too (' . ($list[$parent]['type'] === 'checkbox' ? 'true' : 'a value') . ')'
                        : 'but ' . $parent . ' is not in the panel of this field (' . $this->filteredOut($parent, $this->settings[$parent], $type, $category, $tableType, $fieldName) . ')');
            }
            throw new errorException("Settings are hidden in the panel by their parents (they would not work), nothing is saved:\n" . implode("\n", $lines));
        }
        return ['type' => ['isOn' => true, 'Val' => $type]] + $data;
    }

    /**
     * State of every setting of the panel for get_field_settings
     * @return array [name => [setting, state (on/off/hidden), Val, parent hidden by]]
     */
    public function states(array $dataSrc, string $category, ?string $tableType, string $fieldName): array
    {
        $type = $dataSrc['type']['Val'] ?? 'string';
        $list = $this->available($type, $category, $tableType, $fieldName);
        $data = [];
        foreach ($list as $name => $setting) {
            $data[$name] = $dataSrc[$name] ?? $this->defaultState($name, $setting, $type);
        }
        $hidden = [];
        $visible = $this->resolveVisibility($type, $list, $data, $hidden);
        $result = [];
        foreach ($list as $name => $setting) {
            $on = $visible[$name] && ($setting['type'] === 'checkbox' || !empty($setting['required']) || !empty($data[$name]['isOn']));
            $result[$name] = [$setting, $visible[$name] ? ($on ? 'on' : 'off') : 'hidden', $data[$name]['Val'] ?? null,
                isset($hidden[$name]) ? $hidden[$name] . (key_exists($hidden[$name], $list) ? ' off' : ' not in the panel of this field') : null];
        }
        return $result;
    }

    /**
     * Settings that work (isOn) — what the core takes into the field
     */
    public static function effective(array $dataSrc): array
    {
        $result = [];
        foreach ($dataSrc as $name => $value) {
            if (is_array($value) && !empty($value['isOn'])) {
                $result[$name] = $value['Val'] ?? null;
            }
        }
        return $result;
    }

    /**
     * Short text of data_src for cells: type, number of the settings that work, codes
     */
    public static function summary(array $dataSrc): string
    {
        $effective = static::effective($dataSrc);
        $codes = array_keys(array_filter(array_intersect_key($effective, array_flip(Totum::FIELD_CODE_PARAMS)), fn($v) => is_string($v) && trim($v) !== ''));
        return ($effective['type'] ?? '?') . ' · ' . count($effective) . ' settings on' . ($codes ? ' · codes: ' . implode(', ', $codes) : '');
    }

    /**
     * Values of a select setting (roles, tables, chart types are filled like web)
     */
    public function values(string $name): array
    {
        if (key_exists($name, $this->valuesCache)) {
            return $this->valuesCache[$name];
        }
        if (in_array($name, Totum::FIELD_ROLES_PARAMS)) {
            $values = $this->Totum->getModel('roles')->getFieldIndexedById('title', ['is_del' => false], 'title->>\'v\'');
        } elseif ($name === 'selectTable') {
            $values = $this->Totum->getModel('tables')->getFieldIndexedByField(['is_del' => false, 'type' => ['globcalcs', 'simple']], 'name', 'title', 'title->>\'v\'');
        } elseif ($name === 'chartType' && $this->Totum->getTableRow('ttm__charts')) {
            $values = array_column($this->Totum->getModel('ttm__charts')->executePrepared(true, [], 'type, title')->fetchAll(), 'title', 'type');
        } elseif ($name === 'type') {
            $values = $this->types();
        } else {
            $values = $this->settings[$name]['values'] ?? [];
        }
        return $this->valuesCache[$name] = (array)$values;
    }

    protected function checkNames(array $changes, array $list, string $type, string $category, ?string $tableType, string $fieldName): void
    {
        $errors = [];
        foreach (array_keys(array_diff_key($changes, $list, ['type' => 1])) as $name) {
            if (!($setting = $this->settings[$name] ?? null)) {
                $errors[] = '- ' . $name . ': no such setting';
            } elseif (!in_array($name, $this->lists[$type] ?? [])) {
                $errors[] = '- ' . $name . ': not a setting of type ' . $type;
            } else {
                $errors[] = '- ' . $name . ': ' . $this->filteredOut($name, $setting, $type, $category, $tableType, $fieldName)
                    . ' (field: ' . $category . ', table type ' . ($tableType ?? 'unknown') . ')';
            }
        }
        if ($errors) {
            throw new errorException("Settings are not in the panel of this field, nothing is saved (get_field_settings shows the list):\n" . implode("\n", $errors));
        }
    }

    /**
     * A setting that is not in data_src yet: off with the default value; a checkbox without parent with default true is on (like web)
     */
    protected function defaultState(string $name, array $setting, string $type): array
    {
        $val = $setting['default'] ?? null;
        if ($setting['type'] === 'json' && is_string($val)) {
            $val = json_decode($val, true) ?? [];
        } elseif ($name === 'codeSelect' && $type === 'tree') {
            $val = "=: selectRowListForTree(table: ''; field: ''; order: '' asc; where:  '' = ; parent: ''; disabled:)";
        } elseif ($val === null) {
            $val = match ($setting['type']) {
                'checkbox' => false,
                'select' => empty($setting['multiple']) ? (array_key_first($this->orderedValues($name)) ?? null) : [],
                'integer', 'number' => null,
                'json' => [],
                default => '',
            };
        }
        return ['isOn' => empty($setting['parent']) && $setting['type'] === 'checkbox' && $val === true, 'Val' => $val];
    }

    protected function orderedValues(string $name): array
    {
        $values = $this->values($name);
        if ($order = $this->settings[$name]['valuesOrder'] ?? null) {
            $values = array_replace(array_flip(array_intersect($order, array_map('strval', array_keys($values)))), $values);
        }
        return $values;
    }

    /**
     * @param array $state {isOn, Val} now
     * @param mixed $change value / null / {"Val"?, "isOn"?}
     */
    protected function applyChange(string $name, array $setting, array $state, mixed $change): array
    {
        $isRaw = is_array($change) && !array_is_list($change) && key_exists('isOn', $change) && !array_diff(array_keys($change), ['Val', 'isOn']);
        if ($isRaw) {
            if (key_exists('Val', $change)) {
                $state['Val'] = $change['Val'] === null ? null : $this->normalize($name, $setting, $change['Val']);
            }
            $state['isOn'] = (bool)$change['isOn'];
            return $state;
        }
        if ($change === null) {
            if ($setting['type'] === 'checkbox') {
                $state['Val'] = false;
            } elseif (!empty($setting['required'])) {
                throw new errorException('Setting ' . $name . ' is required: it cannot be switched off, set a value');
            } else {
                $state['isOn'] = false;
            }
            return $state;
        }
        return ['isOn' => true, 'Val' => $this->normalize($name, $setting, $change)];
    }

    protected function normalize(string $name, array $setting, mixed $value): mixed
    {
        $error = fn(string $expected) => throw new errorException('Setting ' . $name . ' (' . $setting['type'] . '): ' . $expected . ', not '
            . json_encode($value, JSON_UNESCAPED_UNICODE));
        switch ($setting['type']) {
            case 'checkbox':
                if (is_bool($value)) {
                    return $value;
                }
                return match (is_scalar($value) ? strtolower((string)$value) : null) {
                    'true', '1' => true,
                    'false', '0', '' => false,
                    default => $error('true or false'),
                };
            case 'integer':
                return is_numeric($value) && (int)$value == $value ? (int)$value : $error('an integer');
            case 'number':
                return is_numeric($value) ? $value + 0 : $error('a number');
            case 'json':
                if (is_string($value)) {
                    $value = json_decode($value, true);
                }
                return is_array($value) ? $value : $error('a JSON object or array');
            case 'select':
                $values = $this->values($name);
                $check = function ($key) use ($values, $name, $error) {
                    if (!is_scalar($key) || is_bool($key)) {
                        $error('a key');
                    }
                    if ($values && !key_exists((string)$key, $values)) {
                        throw new errorException('Setting ' . $name . ': "' . $key . '" is not one of the values: '
                            . implode(', ', array_map(fn($k, $t) => $k . ' (' . MCPMarkdown::plain((string)$t) . ')', array_keys($values), $values)));
                    }
                    return (string)$key;
                };
                if (!empty($setting['multiple'])) {
                    return is_array($value) ? array_values(array_map($check, $value)) : [$check($value)];
                }
                return $check($value);
            default:
                return is_scalar($value) && !is_bool($value) ? (string)$value : $error('a string');
        }
    }

    /**
     * Shown settings: the parent (if it is in the list) is shown and on — checkbox checked, required, switcher on; cascade like the panel of web
     * @param array $hidden [name => parent that hides it]
     * @return array [name => bool]
     */
    protected function resolveVisibility(string $type, array $list, array $data, array &$hidden): array
    {
        $visible = [];
        $typeList = $this->lists[$type] ?? [];
        $check = function (string $name, array $path = []) use (&$check, &$visible, &$hidden, $list, $data, $typeList): bool {
            if (key_exists($name, $visible)) {
                return $visible[$name];
            }
            $parent = $list[$name]['parent'] ?? null;
            if (!$parent || !in_array($parent, $typeList) || in_array($parent, $path)) {
                return $visible[$name] = true;
            }
            /* web: the parent is of the type but not in the panel of the field (categories / tables / names) — the child is hidden */
            if (!key_exists($parent, $list)) {
                $hidden[$name] = $parent;
                return $visible[$name] = false;
            }
            $parentSetting = $list[$parent];
            $parentOn = $check($parent, [...$path, $name]) && match (true) {
                    $parentSetting['type'] === 'checkbox' => !empty($data[$parent]['Val']),
                    !empty($parentSetting['required']) => true,
                    default => !empty($data[$parent]['isOn']),
                };
            if (!$parentOn) {
                $hidden[$name] = $parent;
            }
            return $visible[$name] = $parentOn;
        };
        foreach (array_keys($list) as $name) {
            $check($name);
        }
        return $visible;
    }
}

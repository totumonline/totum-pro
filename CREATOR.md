# CREATOR.md — creator view of MCP (admin layer)

The creator view gives the agent the admin layer of Totum: the navigation tree, tables and fields. The structure of Totum is the data of
the system tables `tree`, `tables`, `tables_fields` — the agent changes it with the usual tools (`insert_row` + `add_row`, `edit`,
`edit_panel`, `delete_rows`) like the creator does it with the panels of web. The creator view adds instructions, the tool
`get_field_settings`, the section `## Creator view` of `get_table_schema` and the short form of the field settings (`data_src`).

## Connection
- stdio: `php bin/totum mcp --user=<login|email> --creator=true` (`--creator` without value = true; `--creator=false` or no option — the usual view).
- HTTP: header `TTM-Creator: true` in every request (no `X-` prefix — RFC 6648; values `true/1/yes/on`):
  ```json
  {"mcpServers": {"totum-creator": {"type": "http", "url": "https://<host>/MCP/",
    "headers": {"Authorization": "Basic <base64(login:password)>", "TTM-Creator": "true"}}}}
  ```
- The flag gives no rights: the user (`interface = agent`) must have the role Creator (role 1, `User::isCreator()`), otherwise
  stdio exits with `ERROR: Creator view is available only for users with the role Creator`, HTTP answers 403; it is checked again
  in every `tools/call` (`MCPServer::callTool` → `MCPAuth::checkCreator`).
- Without the flag nothing changes: a creator agent works with the rights of the creator as before, but has no creator tools/instructions.
- `initialize` → `instructions` = the usual instructions + `MCPCreator::INSTRUCTIONS`; `tools/list` has `get_field_settings` (tool with `'creator' => true`,
  `MCPTools::isToolOff`). Calling it without the flag → `Unknown tool: get_field_settings (a tool of the creator view: …)`.

## Structure (system tables)
| table | a row is | main fields |
|---|---|---|
| `tree` (id 4) | a branch of the navigation tree | `title`, `type` (empty — folder, `link`, `anchor`), `parent_id` (tree, empty — the top level), `ord`, `roles`, `default_table`, `link`, `icon` |
| `tables` (id 1) | a table, id of the row = id of the table | `title`, `name` (`sysTranslit(title)`, `^[a-z][a-z0-9_]{2,30}$`, not editable), `type`, `tree_node_id`, `sort`, roles, codes `default_action` / `row_format` / `table_format` / `on_duplicate`, … |
| `tables_fields` (id 2) | a field | `table_id`, `category`, `title`, `name` (from title with the prefix of the category: `h_` param, `fl_` filter, `f_` footer; not editable), `ord`, `data_src` (settings, type `fieldParams`), `version` (calcs tables, set by its code), `data` (calculated from data_src) |

The core does the DDL itself: a row added to `tables` creates the table (`Table::createTableAfterPrepared`), a row of `tables_fields` adds the column
(`TablesFields::insertPrepared`), deleting a row of `tables` drops the table with its fields and data (`Table::delete`; system tables are not deleted),
deleting a field drops its column.

### Tree branch
```json
{"name": "insert_row", "arguments": {"table": "tree", "data": {"title": "Sales", "parent_id": "37"}}}
{"name": "add_row", "arguments": {"table": "tree", "insert_hash": "i-…"}}
```

### Table
Web: the plus of a branch opens the insert panel of `tables` with `tree_node_id` = the branch.
```json
{"name": "insert_row", "arguments": {"table": "tables", "data": {"title": "Orders", "name": "orders", "type": "simple", "tree_node_id": "38"}}}
{"name": "add_row", "arguments": {"table": "tables", "insert_hash": "i-…"}}
```
Types: `simple`, `cycles` (every row is a cycle), `calcs` (a table inside the cycles of a cycles table), `globcalcs` (calculated, in the tree), `tmp`.
A calcs table (web: the plus of a cycles table in the tree, `type` and `tree_node_id` locked): `tree_node_id` = id of the cycles table — required:
```json
{"name": "add_row", "arguments": {"table": "tables", "data": {"title": "Plan", "name": "plan", "type": "calcs", "tree_node_id": "63"}}}
```
Settings of a table — `edit` / `edit_panel` of its row of `tables`.

### Field
Web: "Insert after" of the field menu opens the insert panel of `tables_fields` (table_id, category, ord preset) with the field panel in `data_src`.
```json
{"name": "insert_row", "arguments": {"table": "tables_fields", "data": {"table_id": 67, "category": "column", "title": "Amount",
  "data_src": {"type": "number", "dectimalPlaces": 2, "unitType": "$", "before": true}}}}
{"name": "insert_row", "arguments": {"table": "tables_fields", "insert_hash": "i-…", "data": {"data_src": {"required": true, "code": "=: 100"}}}}
{"name": "add_row", "arguments": {"table": "tables_fields", "insert_hash": "i-…"}}
{"name": "add_row", "arguments": {"table": "tables_fields", "after_id": 752, "data": {"title": "Country", "data_src": {"type": "select", "codeSelect": "=: selectRowListForSelect(table: 'country'; field: 'country_name')"}}}}
```
`after_id` of `tables_fields` = the field (row of tables_fields) after which the new one goes, like "Insert after" of web: `table_id` / `category` of that field
(must be the same), `ord` = its ord + 10, the core moves the next fields of the category (`tableData.afterField` → `TablesFields::setAfterField`;
the core reads `tableData` in every add of tables_fields — `MCPTools::fieldsInsertAfter` always sends it).
Fields of a calcs table: `version` is set by the code of the field (the default version); `read_table` of tables_fields — filters `f_table`, `fl_version`.

### Restrictions (MCPCreator::checkWrite, before the rights)
The selects of the system tables have no `checkSelectValues`, the core takes any value — MCP checks:
| write | error |
|---|---|
| `type` of an existing table | the type is fixed after creation (the field is not editable anyway) |
| `tree_node_id` of an existing calcs table | a calcs table is not moved between cycles tables — copy: the panel button of `name` (duplicate to another cycles table) |
| a calcs table without `tree_node_id` (on add) or with a not cycles table | `tree_node_id` = id of a cycles table (the list of cycles tables in the error) |
| `tree_node_id` of other types that is not a row of `tree` | a branch of the tree or empty |
| `table_id` of an existing field | a field is not moved to another table |
| `category` not of the table type | simple/cycles: param, filter, column; tmp: param, column, footer; calcs/globcalcs: all four (like the select `category` of tables_fields) |
| `data_src` of a filter with type link/text/comments/file/password/chart/unic | not for filters (the core checks it only on save) |

Deleting rows of `tables` / `tables_fields` deletes the data — the instructions tell the agent to ask the user before.

## Field settings (data_src)
`data_src` = `{setting: {"Val": …, "isOn": bool}}`; the core takes only `isOn` settings (`fieldParamsResult::getDataFromDataSrc` → `data`).
The panel of web (`fieldParams` of `http/js/main.js`) is described by `jsonFields` of the field `tables_fields.data_src` (data_src of row 4):
`fieldListParams[type]` — settings of the type in the order of the panel, `fieldSettings[name]` — `type` (checkbox, string, integer, number, select, code, html, json),
`title`, `default`, `required`, `parent`, `values`/`valuesOrder`, `multiple`, `categories`, `tables` (table types), `names` (field names).
`MCPFieldSettings` repeats the panel:
- a setting is not in the panel when `categories` (except `codeAction` of a button) / `names` / `tables` do not match the field (`tables` without the table type — not shown, like web);
- a setting not in data_src: `isOn` false, `Val` = default (a checkbox without parent with default true — on; tree: own default of `codeSelect`);
- checkbox and `required` settings are on when shown (`isOn` = visible), other settings have a switcher (`isOn`);
- a setting whose `parent` is of the type is shown only when the parent is shown and on (checkbox checked / required / switcher on); a parent that is of the type
  but not in the panel of the field hides it (e.g. `sectionTitle` of a column — `tableBreakBefore` is not for columns); hidden settings are saved with `isOn` false (cascade like the changes in the panel);
- saved: `type` + the settings of the panel; settings of other types are dropped (like the save of web after a change of the type).
Validation of the builder: rebuilding every existing data_src without changes keeps the effective settings except legacy data that web drops on save too
(settings of other types/categories, missing new checkboxes).

### Writing (MCPCreator::writeValues, from MCPTools::writeValues of edit / insert_row / add_row / edit_panel)
`data_src` is `{"setting": value}`: only the changed settings, merged with the current data_src of the row / insert row / panel (`MCPActionsTrait::mcpItemRow`);
a new row — the defaults of the panel. `table_id` must be known (the row or the same call): the settings depend on the table type.
- value — sets `Val` and switches on: checkbox `true/false`, integer, number, select — a key of `values` (multiple — array; roles / `selectTable` / `chartType` are filled
  like web), string / code / html — text, json — object/array (or a JSON string);
- `null` — switch off (checkbox — false; required settings — error);
- `{"Val": …, "isOn": bool}` — raw state (e.g. `{"isOn": true}` switches on with the kept value);
- `"type": "<type>"` — another type, the list of settings is rebuilt (common settings keep their values).
Errors (nothing is saved): unknown setting / not of the type / not for this category or table type; a setting set while its parent is off
(`prefix works only when currency is on — set currency too (true)`); wrong kind of a value / not one of the values.

### Reading
- `get_field_settings` (creator view): `{"table": …, "field": …}` or `{"id": <row of tables_fields>}` — the current settings; `{"table": …, "type": …, "category": …}` — the defaults of a new field;
  a field + `type` — the settings after the change of the type (not saved). Answer: types, how to write, table `| setting | title | kind | state | value | default | parent | values |`,
  state `on` / `off` / `hidden (<parent> off | not in the panel of this field)`.
- `get_value` of `data_src` — the settings that work in the order of the panel (multi-line codes in `~~~` blocks: totum codes contain ``` themselves).
- cells of `data_src` (insert row / panel cards) — `number · 25 settings on · codes: code` (`MCPFieldSettings::summary`); read_table rows show the text of web (`Settings field`).
- `get_table_schema` (creator view) — `## Creator view`: the row of the table in `tables` (type, branch / cycles table, sort, actual, version of calcs, codes of the table),
  how to read/change it and its fields, table `| name | field id | category | ord | type | codes |` (codes: code, action (add, change, delete, click), select, format).

## Implementation
- `MCPAuth::CREATOR_HEADER`, `isFlagOn`, `checkCreator`; `MCPServe` option `--creator`; `MCPController` header → 403; `MCPServer(…, $creator)`; `MCPTools(…, $creator)`.
- `MCPCreator` — instructions, `fieldSettingsAnswer`, `writeValues`, `checkWrite`, `describeFieldValue`, `schemaSection`. Field settings are converted and the restrictions are
  checked in every mode (not only creator view): they keep the system tables consistent; the view adds only tools, instructions and the schema section.
- `MCPFieldSettings` — `available`, `build`, `states`, `effective`, `summary`, `values`.
- `MCPActionsTrait::mcpItemRow` — the row of a write (row / insert row / panel / params); `mcpInitTableData` — a new calcs table has no data in a cycle until it is opened:
  `get_table_schema` calculates it like the opening of the page (before — `array_merge(): Argument #2 must be of type array`).
- `mcpResetSelectViewLists` — only `select` fields (tree has no cache of the view list; before — an error in insert rows of tables with tree fields, e.g. `tree`).

## Verification
- Lint: `for f in totum/moduls/MCP/*.php totum/commands/MCPServe.php; do php -l $f; done`
- `printf '{"jsonrpc":"2.0","id":1,"method":"tools/list"}\n' | php bin/totum mcp --user=agent --creator=true` — has `get_field_settings`; without the flag — not.
- Flow on test objects (delete them after): tree branch → table (insert_row + add_row) → calcs table in `tablica_ciklov` (63) → fields of several types
  (insert_row with data_src, add_row with after_id, a param button, a footer of the calcs table) → edit / edit_panel of data_src → errors (type, moving between cycles,
  tree_node_id, footer of a simple table, hidden setting) → `delete_rows` of tables / tree.
- Not creator user without changing roles: a harness with `MCPServer(…, creator: true)` and a user provider that clears `User::$roles` / `$isCreatorValue` by reflection.

# CREATOR.md — creator view of MCP (admin layer)

Field settings, table settings and functions in detail — the documentation [docs/DOCS.md](docs/DOCS.md) (server: `https://<host>/docs/DOCS.md`):
field settings `fields#fields-settings-<name>`, field types `fields#type-<type>`, table settings `tables#table-settings-<name>`, functions `functions#fn-<name>`;
over MCP — `read_docs {"setting": …}` / `{"field_type": …}` / `{"table_setting": …}` / `{"function": …}`, `search_docs {"q": …}`.

The creator view gives the agent the admin layer of Totum: the navigation tree, tables and fields. The structure of Totum is the data of
the system tables `tree`, `tables`, `tables_fields` — the agent changes it with the usual tools (`insert_row` + `add_row`, `edit`,
`edit_panel`, `delete_rows`) like the creator does it with the panels of web. The creator view adds instructions, the tool
`get_field_settings`, the section `## Creator view` of `get_table_schema` and the short form of the field settings (`data_src`).

## Before development — required reading
> **ATTENTION: the most common error is not using `~`.** Every time you write or change a Totum code, check the lines that are called
> more than once with the same value and fix them with `~` (except the lines that depend on the element / iteration of `listReplace` / `while`) —
> [docs/ARCHITECTURE.md#fix-lines](docs/ARCHITECTURE.md#fix-lines), MCP: `read_docs {"ref": "ARCHITECTURE#fix-lines"}`.

Before building tables or writing codes read:
1. [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) (server: `https://<host>/docs/ARCHITECTURE.md`, MCP: `read_docs {"ref": "ARCHITECTURE"}`) — architectural patterns of Totum codes
   (the same line is in the instructions of the creator view, `MCPCreator::INSTRUCTIONS`). The main ones:
   - **`~` on lines called several times with the same value** — extra important for performance. In `listReplace` and `while` do not fix lines
     that depend on the current element / iteration: `~` keeps the first value for all the following ones.
   - **Totals of the previous rows** (`listSum` of `selectList` of all the previous rows of the client / date) in simple tables and cycles tables —
     a CRASH of the system UNDER LOAD: the query is slower with every new row. Make a field with `codeOnlyInAdd`: the previous value by one `select` + the current one.
   - **As few queries to the database as possible** (they are the most expensive): several `select` of different fields of the same row —
     one `~row: selectRow(…; field: …; field: …)` and `$row[field]`; several `selectList` with the same `where` — one `selectRowList`.
   - **Short lists — inline `` json`["short","lists"]` ``**, not a separate line with `listCreate`.
   - **Strings**: `` str`…` `` is written right in the parameter of the function, not in a separate line;
     in `` str`…` `` a space is added by `++`, not inside the quotes (`` str`"Счёт №" ++ #order_num + $#nl` ``);
     a string with `"` inside — `strAdd` with the parts in single quotes (`strAdd(str: 'Заказ "'; str: #order_num; str: '"')`).
   - **Simple math** (add, subtract, divide) — `` math`…` `` right in the parameter of the function (`` field: 'sum' = math`#price * #quantity - #discount` ``).
   - **Dates and numbers (money) in texts for users** (messages, notifications, comments) — formatted with `dateFormat` / `numFormat`.
   - **Names of lines**: `dateAdd` — `dadd` (also with a negative number), `numFormat` — `nf`, `dateFormat` — `df`; several in one code — `dadd_***`, `nf_***`, `df_***`.
   - **Empty list** — `$list = $#lc`, not `$list = ""` (it is `true` also for a list with one empty element).
   - **After `set` into the current row** `#field` keeps the value of the start of the code — the new value is read by `select` / `@table.field[#id]`.
   - Examples of the docs may be written in other ways — the patterns of ARCHITECTURE.md have priority.
   - **Layout of `listReplace`**: the line of the source `list` has an additional indent — two tabs (important!), the lines of `action` — one tab, nested ones deeper:
     ```totum
     =: listReplace(list: $rows; action: "total" = $total; value: "row")
     		rows: selectRowList(table: 'order_items'; field: 'id'; field: 'price'; field: 'quantity')
     	total: $#row[price] * $#row[quantity]
     ```
   - **Several `action` of `listReplace` one after another**: the result of an `action` is passed to the next one through an intermediate `var`
     (`action_row: var(name: "action_row"; value: $var_action_row)`, `var_action_row: …`, the next `action` reads `$#action_row`).
2. The sections of the functions you use — `read_docs {"function": …}`, the settings — `read_docs {"setting": …}` / `{"table_setting": …}`, the syntax — `read_docs {"ref": "syntax"}`.
3. Before designing the tables of a system — the patterns of building systems `docs/ru/patterns/` (server: `https://<host>/docs/ru/patterns/patterns.md`, MCP: `read_docs {"ref": "patterns/patterns"}`;
   the same line is in `MCPCreator::INSTRUCTIONS`) — solutions of the working B2M platform with the real names of its tables and fields:
   - **Tab of a cycle** (`cycle-tabs`): data that cannot be a calcs table of the cycle — a simple table opened by a `tab_` button with a linked prefilter and a not linked flag `fl_tab` (creator only);
     its table format hides them (`fieldshide`), sets the title, blocks adding out of the tab.
   - **Tree anchors** (`tree-anchors`): an anchor opens the main table (it lies in a technical folder), the subtables have the branch of the anchor as their place in the tree.
   - **Stored fields** (`stored-fields`) and **recalculation of the changed rows** (`table-action-recalc`): summary fields of a cycles table row — `codeOnlyInAdd` + `notLoaded`, recalculated by
     `reCalculate(…; field: …; where: 'id' = $uniq)` from `default_action` of the source tables, `$uniq` — `listUniq` of the owners of `$#changes`; deleted rows — the action on delete with `#old`.
   - **Process variables** (`process-vars`): `procVar(name: …; default: …)` — one value for all the rows of a process (`default` is calculated only when the variable is not set); flags between codes.
   - **Background** (`background-daemons`): changes only set a flag-queue field, a daemon takes one row per run and drops the flag with `exclude`, the heavy calculation — the action of the flag on `false`.
   - **Autofill** (`insert-param`): `insert` = `#h_insert`, the list in the param `h_insert` — visible, given from outside, reset by `clear`, fixed after deleting rows.
   - **exec of a field** (`exec-field-code`): `exec(code: "field_name")` — in the called code no `$#nfv` / `$#nf` / `$#onfv` (they are of the caller), the fields by name.
   - **listReplace** (`listreplace-load`): `var` between actions, one `~selectRowList` + `listFilter` in the action instead of a query per element.
   - **Cache by design** (`cache-by-design`) and **indexes** (`indexes`): what can be calculated at writing — in the action into a field (big selects many times faster);
     indexes on the fields of `where` of big selects, not more than one per 7–8 columns, GIN for values inside lists (`ttm__custom_indexes`).
   - **Mass changes and triggers** (`action-triggers`): the root row changes the children by one `setList` with a `procVar` flag; one technical trigger field (`listCreate` of the watched fields) instead of actions in every field.
   - **Data of a cycle** (`cycles-data-flow`): one footer field → label / `select` in the cycles table; the cycles row is recalculated last in a cascade — trigger in the cycles table or data from the source.
   - **Trees** (`tree-view`): `treeViewCalc`, descendants in `tree_children`, ancestors by `where: 'tree_children' = id`, old and new parents, `#old` on delete, `roots` for big trees, no pagination for trees.
   - **Interface** (`interface-conventions`), **navigation and access** (`access-and-navigation`), **tracking and daemons** (`load-and-actuality`), **extending** (`extending`: `spec…`, `execSSH` + `stdin`, `ext…`).
4. Solved questions of users — `search_docs {"q": …, "section": "how-to"}` (pages `user-questions`, `knowledge-base`).

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
- `initialize` → `instructions` = the usual instructions + `MCPCreator::INSTRUCTIONS`; `tools/list` has `get_field_settings`, `exec_code`, `search_codes` (tools with `'creator' => true`,
  `MCPTools::isToolOff`). Calling them without the flag → `Unknown tool: get_field_settings (a tool of the creator view: …)`.

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

## Debug: the log, the rollback and the dry run (parameter `debug`, tool `exec_code`)
Every tool with `table` (creator view, not `get_field_settings`) has `debug`; without the creator view — `ERROR: debug is a parameter of the creator view`.
```json
{"name": "edit", "arguments": {"table": "orders", "rows": {"12": {"status": "done"}}, "debug": true}}
{"name": "click_button", "arguments": {"table": "orders", "field": "send", "id": 12, "debug": {"log": ["a"], "on_block": "skip"}}}
{"name": "exec_code", "arguments": {"table": "orders", "id": 12, "code": "=: emailSend(to: #email; title: \"Order\"; body: #name)"}}
{"name": "exec_code", "arguments": {"table": "orders", "code": "=: listSum(list: $l)\nl: selectList(table: 'orders'; field: 'sum'; where: 'client' = $#c)", "vars": {"c": 5}}}
```
`debug` = `true` (the defaults) or an object (`MCPLog`):
| key | default | meaning |
|---|---|---|
| `log` | `["c","a"]` | types of `CalculateLog` like the checkboxes of "Debugging" of web (cookie `pcTableLogs`): `c` codes, `a` action codes, `C` why the table is changed, `s` selects, `f` formats, `flds` calculation time of fields (only it), `all`; `[]` — no log |
| `commit` | `false` | `false` — the transaction is rolled back; `true` — saved if there is no error and no blocked external action is met |
| `block` | `"all"` | kinds of external actions that a rollback does not undo (`DryRun::KINDS`) that are not done |
| `allow` | `[]` | kinds done anyway (with `block: "all"`) |
| `on_block` | `"stop"` | `stop` — the run stops at the first blocked action (`DryRunStopException`), the answer shows it; `skip` — not done, the function returns `null`, the run goes on (not with `commit: true`) |
| `mocks` | `[]` | rules `[{"function" or "kind", "table"?, "field"?, "id"?, "match"?, "result"}]` or `{"<function or kind>": result}`: the action is NOT done, the function returns `result`, the run goes on. Any action (also not blocked), the first matching rule; `match` — a text in the details (uri, to, query…), case-insensitive. Not with `commit: true` |
| `help_mocks` | `true` | blocked actions take the `mock` lines of `<agent>` of the help of the field of the code (off with `commit: true`) |
| `log_limit` | 300 | lines of the log (max 2000) |

Kinds (the guard `Calculate::dryRunAllows` in the functions, before the external part):
| kind | functions |
|---|---|
| `email` | `emailSend`, `notificationSend` (eml) |
| `http` | `getFromScript` (not `request: true`), `getFromSoap` |
| `ssh` | `execSSH` |
| `exec` | `exec(ssh: true/test)`, `notificationSend` custom codes in a separate process — `bin/totum exec` commits in its own transaction |
| `db` | `proDbExecQuery` (external databases) |
| `imap` | `proImapGetEmail` (marks the email as read) |
| `services` | `serviceRequests`: xlsx / docx / pdf generators, xlsx parser, openai |
| `schema` | `schemaGzStringUpload` (psql), `schemaUpdate` (restart of GOM on end) |
| `gom` | `checkGomDaemonds` |
Not guarded (undone by the rollback): DB writes, files of file fields and GOM `tableUpdates` (`Sql::addOnCommit`), indexes (`proIndexAdd`, trigram — DDL in the transaction).
Not undone anyway: sequences of ids (rolled back adds leave gaps).

Answer:
```
**DEBUG: rolled back — nothing is saved** (debug.commit: true saves)   — or: saved / stopped at a blocked external action / ERROR: … + rolled back
<the usual answer of the tool>                                       — none when stopped
## Debug
external actions blocked: … · on block: stop · log: c, a
error path: …                                                         — getPathMess of the error
### Stopped at                                                         — function (kind) · table · field · id, details, how to continue
### External actions                                                   — journal: # | status (done / skipped / stopped / after stop) | kind | function | where | details
### Errors in the log (N)                                               — the deepest place of every error message with its path in the tree
### Log (N lines)                                                       — getLogsForJsTree as an indented list; !ERR — error nodes
### Fields calculation time (N)                                         — flds: getFieldLogs sorted by time
```
Flow «stop and ask»: the run stops at e.g. `emailSend` → the agent shows to / title / body to the user → yes: repeat with `"debug": {"allow": ["email"]}`
(still rolled back, but the email IS sent) or for real — `"debug": {"commit": true, "allow": ["email"]}` / without `debug`.
To test further without doing it — a mock: `"debug": {"mocks": [{"function": "emailSend", "result": true}]}`.

### Mocks
The result of a mock is what the function returns (not the raw answer of the service): `getFromScript` — the body (a string, or `{"headers", "body"}` with `header: "json"`),
`emailSend` — `true`, `serviceAskOpenai` with `answer: "json"` — the decoded content, `proDbExecQuery` — the count of rows, … Order of `DryRun::check`:
a stop before → stop; a rule of `mocks` → mocked; not blocked → done; a mock of the help of the field → mocked; stop / skip. Journal: `mocked (mock #N)` / `mocked (help of the field …)`, column `returned`.

Mocks in fields — `<agent>` parts of the help (hidden in web: `Totum::filterAudienceText`), one per line (`DryRun::mocksFromHelp`):
```
<agent>mock getFromScript "api.cbr.ru": {"rate": 92.5}</agent>
<agent>mock emailSend: true</agent>
<agent>mock http: {
  "status": "ok"}</agent>
```
`mock <function or kind>[ "<match>"]: <result>` — JSON (an object / list goes on the next lines until it is valid) or a text; the help is html: tags are dropped, `<p>` / `<br>` are new lines.
The field of the code — `Calculate::$varName` (codes of the field, its action / select / format; `exec(code: 'other_field')` keeps the field of the caller); codes of the table
(`default_action`, `row_format`…) and `exec_code` have no field help — mocks of the call only.
`get_table_schema` (creator view): column `external calls` of the fields — `getFromScript (mock)` / `(no mock)` (`DryRun::externalCalls`: function names in the parsed codes,
case-insensitive, `exec` — with `ssh:` only) and the line `external calls without a mock …`. The instructions of the creator view tell the agent to write realistic mocks
there when it creates / changes such a field.

Implementation:
- core: `totum\common\DryRun` (kinds, `check` → done / skipped / stop, journal; after a stop everything is blocked — a stop swallowed by a code still ends the run),
  `DryRunStopException extends criticalErrorException` (catch sections of code lines rethrow it, `tryCatch` too), `Totum::setDryRun/getDryRun`, `Calculate::dryRunAllows`.
  Without `setDryRun` (web, API, crons) the guards do nothing.
- MCP: `MCPLog` (options, `apply` — `setCalcsTypesLog` + `setDryRun` right after `new Totum`: types set after the first `getTable` do not log that table),
  `MCPTools::debugCall` (the answer is made before the rollback — `Sql::transactionRollBack` terminates the db connection; `isRolledBack` → `MCPServer` takes a clean config,
  `isFailed` → `isError`), `MCPActionsTrait::mcpFormat` — the formatting of the answers in the section views of the log (`SELECTS AND FORMATS`, like web),
  so `c` / `a` logs have no formatting of the answer.
- `exec_code`: `CalculateAction::execAction('exec_code', row, row, tbl, tbl, table, 'exec', vars)`, always with `debug` (default `true`); a row is loaded with `loadFilteredRows`.
- Windows / insert rows / panels opened in a rolled back call do not live (their hashes are rolled back too).

## Search in codes (search_codes)
A grep through all the codes of the schema (creator view, read-only, `MCPCodeSearch`): who uses a table / a field / a function, where the external calls are.
```json
{"name": "search_codes", "arguments": {"q": "'orders'"}}
{"name": "search_codes", "arguments": {"q": "status", "word": true, "table": "orders", "context": 2}}
{"name": "search_codes", "arguments": {"q": "(set|insert|delete)\\w*\\(table:\\s*'orders'", "regex": true, "settings": ["codeAction"]}}
{"name": "search_codes", "arguments": {"external": true}}
{"name": "search_codes", "arguments": {"q": "specMyFunc", "in": ["fields", "data"]}}
```
| arg | meaning |
|---|---|
| `q` | text, case and ё/е insensitive, matched by lines; `regex: true` — PCRE without delimiters; `word: true` — not a part of a longer name (`status` finds `#status`, `'status'`, not `status_id`) |
| `external` | only codes with external calls (`DryRun::externalCalls`: the functions of the kinds of debug, `exec` with `ssh:`); without `q` the lines of the calls are shown; fields have `external: <function> (mock / no mock)` — mocks in the help |
| `in` | scopes, default all: `fields` — code settings of data_src (the settings of kind `code` of the panel: `code`, `format`, `codeChart`, `codeAction`, `codeSelect`, `treeViewCode`, `selectTableAction`); `tables` — `Totum::TABLE_CODE_PARAMS` of the rows of `tables`; `data` — values of text fields with `textType` totum in rows (columns) and params of simple / cycles tables: `crons`, `notification_codes`, `ttm__custom_functions`, `ttm__remotes`, `ttm__daemons`, `ttm__openid`, `settings`… (not `tables` / `tables_fields` — they are the scopes above; tmp / calcs / globcalcs keep no rows of their own) |
| `table` | only the codes of this table: its fields, its table codes, its rows (data) |
| `settings` | only these codes (fields and tables, no data) |
| `off` | also switched-off codes of fields (`isOn` false, kept in data_src, do not work); without it the answer counts them: `also in N switched-off code(s)` |
| `context` | lines around a matching line (0–5) |
| `offset` / `limit` | paging by codes (default 30, max 200) |

Empty codes (`=:`, `= : `, `f1=:` without an expression) are skipped. Answer:
```
# Code search: «'crons'» · in fields, tables, data · 1 code(s), 3 matching line(s)
by table: ttm__ldap_settings 1
lines: «N:» — a matching line, «N-» — a line around it (context); the whole code — the call of «open»

## ttm__ldap_settings «Настройки LDAP» · simple · id 43
### ttm__ldap_settings.h_generate_cron «Сгенерировать cron» · param button · field id 530 · codeAction (click)
open: get_value {"table":"tables_fields","field":"data_src","id":530} · settings — get_field_settings {"id":530}
~~~
3: 	sel: select(table: 'crons'; field: 'id'; where: 'name' = "ttm__ldap_cron")
…
8: insert: insert(table: 'crons'; …)
~~~
```
- order: tables by name; inside a table — its table codes, fields (default version of calcs first, categories param → filter → column → footer, ord), data rows by id;
- a field: `table.field «title» · category type · field id N · setting (triggers of codeAction: add / change / delete / click; buttons — click)`, `version vN (default)` of calcs tables,
  `OFF` for switched-off codes; a table code: `table · table code default_action`; data: `crons row 5 «name» · data: code` (the title — main field or name / function_name / title / path_code / table_name);
- `open` — the tool call that shows the whole code; at most 20 matching lines per code, long lines (> 300) are cut around the match.
- Static text search: a code built at run time (`exec(code: $var)`, `exec(code: 'other_field')`, `$#ntn` — the current table) is not found by the name of its target —
  search the names of the fields / variables too; a call `specName(…)` of a custom function hides its external calls — they are in the data of `ttm__custom_functions`.

## Testing an unknown system
The agent did not write the system and does not know what a change does. Order: map the codes with `search_codes` / `get_table_schema`, then reproduce with `debug` (rolled back),
then — only by the decision of the user — for real. Never test by real writes "to see what happens": actions change other tables, send mail, call services.

### 1. Map: what runs and what it touches
- `get_table_schema {"table": "orders"}` — the `## Creator view` table: the codes of every field (`code`, `action (add, change, delete, click)`, `select`, `format`),
  the codes of the table (`default_action` runs after every change of the table, `row_format`…), `external calls`.
- Entry points of the table — what runs on the actions of the user:
  | action of the user | what runs | test with |
  |---|---|---|
  | open / read | `code` of the fields (calculated), formats | `read_table` + `debug: {"log": ["c"]}` |
  | edit of a field | its `codeAction` (change), the `code` of the dependent fields, `default_action` | `edit` + `debug` |
  | add a row | `code` of the fields (`codeOnlyInAdd`), `codeAction` (add), `default_action` | `add_row` with `data` + `debug` (not `insert_row` + `add_row`: an insert row opened in a rolled back call does not live) |
  | delete | `codeAction` (delete), `default_action` | `delete_rows` + `debug` |
  | button | `codeAction` of the button | `click_button` + `debug` (windows of the answer — `submit_*` with `debug` too) |
  | schedule | rows of `crons` | `exec_code {"table": "crons", "id": <cron>, "code": "=: exec(code: #code)"}` — like the scheduler (`SchemaCron`: the code in the context of the row) |
  | API (`/Remotes/`) | rows of `ttm__remotes` | `exec_code {"table": "ttm__remotes", "id": <row>, "code": <the code of the row — get_value of code>, "vars": {"get": {…}, "post": {…}, "input": "…", "method": "POST", "path": …, "headers": {…}}}` — the vars of `RemotesController::action` |
- Who else writes / reads the table: `search_codes {"q": "'orders'"}` (codes of other tables, crons, notifications, custom functions),
  writes only — `{"q": "(set|setList|insert|delete|clear|recal)\\w*\\(table:\\s*'orders'", "regex": true}`. A `set` / `insert` into the table runs ITS action codes too (chains: log `C`).
- Who uses a field — `search_codes {"q": "status", "word": true}` (`#status`, `'status'`, `#old.status`…) before changing its name, type or values.
- Who calls a code / a function: a user function `spec<Name>` (docs: `read_docs {"ref": "user-functions"}`) — `search_codes {"q": "spec<Name>"}`; `exec(code: 'field')` — `{"q": "'field'"}`; a notification — the name of its code in `notification_codes`.
- Read the codes found whole (`open` of the answer), the unknown functions — `read_docs {"function": …}`.

### 2. External actions: what leaves the system
`search_codes {"external": true}` (or with `table`) — all the calls of mail, http, ssh, services… in fields, table codes and data.
- Fields `(no mock)` — before the test write a realistic mock into `<agent>` of the help of the field (ask the user for a real answer or take one from a run with `allow` agreed with the user), see "Mocks";
  table codes, crons, custom functions have no help — `debug.mocks` of the call.
- The default of `debug` is the stop at the first external action: the answer shows what would be sent (to, uri, body) — show it to the user. `on_block: "skip"` — the list of all of them in one run.

### 3. Dry run: reproduce the action of the user
The same tool the user would use, with `debug` — the whole chain runs (codes, actions of other tables, `default_action`), then the transaction is rolled back:
```json
{"name": "edit", "arguments": {"table": "orders", "rows": {"12": {"status": "paid"}}, "debug": {"log": ["a", "C"]}}}
{"name": "click_button", "arguments": {"table": "orders", "field": "send", "id": 12, "debug": {"on_block": "skip"}}}
{"name": "add_row", "arguments": {"table": "orders", "data": {"client": 5, "sum": 100}, "debug": {"mocks": [{"function": "getFromScript", "match": "api.cbr.ru", "result": "{\"rate\": 92.5}"}]}}}
```
- Read: the answer of the tool (changed rows, `other changed tables`, side effects, opened windows), `## Debug` — `### Stopped at` / `### External actions`, `### Errors in the log`
  (the deepest place of every error with its path), `### Log`. Logs: `a` — action codes (what the actions did), `c` — codes of the fields, `C` — why a table is recalculated
  (chains between tables), `s` — selects, `flds` — slow fields; start with `["a"]` (actions) or the default `["c", "a"]`, keep the log short with `log_limit`.
- Test data: rows added / changed in debug are rolled back — no cleanup. Several steps (add a row, then change it) — one `exec_code`: the next call does not see the rolled back data.
  Named lines run only when they are referenced — the steps are the sections `a1=:`, `a2=:`… of an action code (run in order, the result — the last one; read_docs `specific-codes#code-action`):
  ```
  a1=: insert(table: 'orders'; field: 'client' = 5; field: 'sum' = 100)
  a2=: set(table: 'orders'; field: 'status' = "paid"; where: 'client' = 5)
  a3=: selectRowList(table: 'orders'; field: 'id'; field: 'status'; field: 'sum'; where: 'client' = 5)
  ```
  The log `a` shows every section with its value (`a1=: …`, `RECALC`, `ACTIONS` of the changed tables).
  The `set` / `insert` of a code run the action codes of the table like the edit of web (change / add).
- Check the results in the same run: the answer is made before the rollback, after it nothing is left — the result of `exec_code` is the place for the selects of the state after the action
  (`selectRow` / `selectRowList` of the changed tables), `changed tables:` — what was touched.
- Calcs tables — with `cycle`; tmp tables — with the `sess_hash` of the answer (inside one call).

### 4. A new or changed code
- Before saving: `exec_code` with the new text in the context of the table / row (`id`) — `#field` of the row, `$#…` vars. An action code — as is;
  a calculated code (`code` of a field) — its value is the result (`exec_code` runs it as an action code: the functions of formats — `setFormat`… — are not there:
  a format is checked after the save with `read_table` + `styles: true` / `debug: {"log": ["f"]}`).
- Save it (`edit` of `data_src` of tables_fields) — then repeat the dry run of step 3 with the real flow of the user. A save of structure with `debug` is rolled back too — the next calls do not see it.
- After a change: `search_codes` of the name of a changed field / table / variable — the other codes that use it.

### 5. For real
Only when the user agrees: `debug: {"commit": true}` (saved if nothing blocked was met; external actions still blocked unless `allow`), then `allow: [kind]` / without `debug`
for real external actions. Not undone by the rollback anyway: sequences of ids (gaps), see "Debug" — the guards and what is not guarded.

## Implementation
- `MCPAuth::CREATOR_HEADER`, `isFlagOn`, `checkCreator`; `MCPServe` option `--creator`; `MCPController` header → 403; `MCPServer(…, $creator)`; `MCPTools(…, $creator)`.
- `MCPCreator` — instructions, `fieldSettingsAnswer`, `writeValues`, `checkWrite`, `describeFieldValue`, `schemaSection`. Field settings are converted and the restrictions are
  checked in every mode (not only creator view): they keep the system tables consistent; the view adds only tools, instructions and the schema section.
- `MCPFieldSettings` — `available`, `build`, `states`, `effective`, `summary`, `values`, `codeSettings` (the settings of kind `code` of the panel).
- `MCPCodeSearch` — `search_codes`: the rows of `tables` / `tables_fields` / `calcstable_versions` are read by models (all the schema, the creator has all rights), the data — `getModel(<table>)`
  for every column with `textType` totum, params — from `header` of the row of `tables`; matched by lines in PHP (`pattern`: `\x01` delimiters, ё/е, word — `(?<![\p{L}\p{N}_])`).
  Sort keys of all the scopes have the same length (PHP compares arrays by count first). `get_field_settings` / `search_codes` are not blocked by `checkOneCycle` (structure, not the data of the cycle).
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
- Debug, several calls in one stdio process (the config after a rollback): `edit` with `debug: true` → `read_table` (the value is not changed);
  `exec_code` with `emailSend` (stop, the details), with `on_block: "skip"` (journal skipped, the code goes on), `tryCatch` around `emailSend` (still stopped),
  an error (`select` of a not existing table: error path + the error in the log), `commit: true` with a read-only code (saved), `log: ["flds"]` (the table of times),
  wrong `block` / `skip` + `commit` (errors), without `--creator` (`exec_code` unknown, `debug` — error). Unit: `new DryRun("all", ["email"])` — `check` done / stopped / after stop.
  Do not test `allow` of real kinds (the action is done).
- Mocks: `exec_code` with `getFromScript` + `mocks` with `match` (mocked, the result in the code), `{"email": true}` + a not mocked `getFromScript` (mocked, then stopped),
  `mocks` + `commit` / a rule without function and kind (errors). Help mocks without changing the structure: a harness that sets `help` / `codeAction` of a field of
  `aTable::$fields` by reflection (in memory), `execAction('<field>', …)` with `DryRun` — matching uri → mocked, other uri / `help_mocks` off → stop;
  `MCPCreator::schemaSection` — `external calls`. Unit: `DryRun::mocksFromHelp` (multi-line JSON, match, kind), `externalCalls`, `hasHelpMock`.
- search_codes: `{"q": "'crons'"}` (a field code with 3 lines), `{"external": true}` (fields with `(no mock)`, `ttm__custom_functions` row in data, `getFromScript` in a `format`),
  `word` (`status` ≠ `status_id`), `regex` (`table:\\s*'crons'`; `"("` — error), `table` of a calcs table (`version v1 (default)`), `in: ["data"]` (rows titled by name / function_name),
  `settings` + `off` (the count of switched-off codes), `context`, `offset` beyond the end, errors of `in` / `settings` / without `q` and `external`; without `--creator` — unknown tool.
  Unit (reflection, `newInstanceWithoutConstructor`): `pattern` + `matchLines` (ё/е, word, empty codes `=:` / `f1=: `), `linesText` (context, `…` gaps, a line > 300 cut around the match).
- The testing flow of "Testing an unknown system": `add_row` with `data` + `debug` (rolled back, no row after), `exec_code` with `a1=:` / `a2=:` / `a3=:` (the state of the last section),
  `exec_code` of a cron row `=: exec(code: #code)`, `setFormat` in `exec_code` — error (formats are not in action codes).

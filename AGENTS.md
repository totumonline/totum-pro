# AGENTS.md

Documents for agents — in the repo and on every server (the server links are relative symlinks `http/AGENTS.md`, `http/CREATOR.md`, `http/docs` committed to the repo):
| document | repo | server |
|---|---|---|
| this file: the project, working with Totum over MCP | `AGENTS.md` | `https://<host>/AGENTS.md` |
| creator view of MCP: tree, tables, fields (`--creator=true` / header `TTM-Creator: true`) | [CREATOR.md](CREATOR.md) | `https://<host>/CREATOR.md` |
| documentation of Totum: syntax, functions, fields, tables, interface | [docs/DOCS.md](docs/DOCS.md) | `https://<host>/docs/DOCS.md`, articles `https://<host>/docs/<lang>/totum/<page>.md` |
| architectural patterns of Totum codes — required reading before development | [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | `https://<host>/docs/ARCHITECTURE.md` |

Over MCP the documentation is read without internet requests: `search_docs` / `read_docs` (see "Documentation (search_docs, read_docs)").

## Project
Totum PRO (PHP 8.5, PostgreSQL). Modules: `totum/moduls/<Name>/<Name>Controller.php`, routed by first URL segment (`/Table/`, `/Json/`, `/MCP/`).
CLI: `php bin/totum <command>` (Symfony Console), schemas: `php bin/totum schemas-list` (multi install, option `-s <schema>`).

## MCP module (totum/moduls/MCP)
- Agent interface over ReadTableActions/WriteTableActions (channel `web`), only users with `interface = agent`.
- HTTP: `POST /MCP/` JSON-RPC, `Authorization: Basic base64(login:password)` in every request.
- stdio: `php bin/totum mcp --user=<login|email> [-s schema] [--creator=true]`.
- Creator view (`--creator=true` / HTTP header `TTM-Creator: true`, users with the role Creator only) — see [CREATOR.md](CREATOR.md).
  It also has the debug of calls: parameter `debug` (calculation log, rollback, dry run of external actions — `totum\common\DryRun`) and the tool `exec_code`,
  the search through all the codes `search_codes` (`MCPCodeSearch`) and the method of testing a system the agent does not know (CREATOR.md "Testing an unknown system").
- Output of tools is markdown only (see MCPMarkdown).

## Working with MCP

### Connection
User: `interface = agent`, `on_off = true`, rights are given by roles exactly like for a web user (field visibility and editing by showInWeb / webRoles / editRoles / addRoles).

**HTTP** — every JSON-RPC message is a separate POST to `/MCP/`, login/password in every request:
```bash
curl -sk -u agent:pass -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"list_tables","arguments":{}}}' \
  https://<host>/MCP/
```
MCP client config (Claude Code / Cursor etc.):
```json
{"mcpServers": {"totum": {"type": "http", "url": "https://<host>/MCP/",
  "headers": {"Authorization": "Basic <base64(login:password)>"}}}}
```

**stdio** — on the server, no password (shell access = full access), one JSON message per line:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"list_tables","arguments":{}}}' \
  | php bin/totum mcp --user=agent
```
```json
{"mcpServers": {"totum": {"command": "php", "args": ["/home/totum/totum-pro/bin/totum", "mcp", "--user=agent"]}}}
```
Protocol methods: `initialize`, `ping`, `tools/list`, `tools/call`. Below only `arguments` of `tools/call` are shown.

### Workflow
1. `list_tables` — which tables are available.
2. `get_table_schema` — which fields exist, their `name`, type and rights.
3. `read_table` — data (params, filters, page of rows, footers).
4. `edit` / `insert_row` + `add_row` / … — changes. Requests always use field `name`, not titles. New rows — through the insert row (see "Insert row").

### List of tables
```json
{"name": "list_tables", "arguments": {}}
{"name": "list_tables", "arguments": {"q": "order"}}
```
`q` — search by words in name/title. Answer: `| id | name | title | type | branch | access |`; access is `rw` (write) or `ro` (read-only).
Tables hidden from the tree (roles `tree_off`, table setting "hide from tree" — even for the creator) are not listed, like the tree of web:
they are opened only by buttons/links of other tables (`MCPTableResolver::hiddenFromTree` = `User::getTables()` − `getTreeTables()`, without the tables of the extra role "user manager").
The list has the line `not listed: N table(s) hidden from the tree …`; the header of such a table has the line `hidden from the tree: … do not open it directly`.
Direct access is not blocked (web does not block it either, and a window of a linkToTable may come without sess_hash/filters) — the agent is told not to do it.
A table is passed in the `table` argument by id or name. Types:
- `simple`, `globcalcs`, `cycles` — only `table`;
- `calcs` (table inside a cycle) — also `cycle`: id of the row in the cycles table (branch column shows `cycle of <cycles table>`); see "Cycles";
- `tmp` — the answer header contains `sess_hash`; pass it back to keep working with the same temporary table.

### Cycles
A cycles table (`cycles`): every row is a cycle; a cycle has its own copies of the calcs tables of the cycles table (tables with `tree_node_id` = the cycles table).
Web address has one more level: `/Table/{top}/{cycles table}/{cycle}/{calcs table}` — MCP: `{"table": "<calcs table>", "cycle": <row id>}`
(`MCPTableResolver`: cycle row must exist and not be deleted, cycle access by `cycles_access_type` / `creator_id` like `TableController::checkTableByUri`).
Test tables: `tablica_ciklov` (cycles), `tablica_vnutri_cikla_1`, `tablica_vnutri_cikla_2` (calcs), tab button `tab_country` (→ `country`).

`read_table` (first page) / `get_table_schema` of a cycles table — section `## Cycles` (`MCPMarkdown::cyclesSection`):
```
## Cycles
every row is a cycle with its own tables: tablica_vnutri_cikla_1 (…), tablica_vnutri_cikla_2 (…) — pass them with "cycle": <row id>
tabs of a cycle (tab_ buttons, web shows them as tabs inside the cycle): tab_country — click_button with "id": <row id>, it opens the table of the tab
open a cycle like web (double click on the row / button_to_cycle): click_button `{"table":"tablica_ciklov","field":"button_to_cycle","id":"<row id>"}` — …
add a cycle like the add button of web: add_row `{"table":"tablica_ciklov"}` without data — a cycles table has no insert row: …
```
`read_table` (first page) / `get_table_schema` of a calcs table — section `## Cycle` with the tabs of web inside the cycle (`MCPTableResolver::cycleTabs` = `TableController::setTreeData`):
tab_ buttons of the cycles table (visible, edit right of the cycles table or `pressableOnOnlyRead`, ord of the field) and calcs tables of the cycle in the tree of the user
(`Cycle::getViewTablesWithOrds` — order of the versions of the cycle), sorted by ord:
```
# Таблица внутри цикла 2 · tablica_vnutri_cikla_2 · id 65 · calcs · cycle 1 (tablica_ciklov)      — cycle 1 «name» if the cycles table has a main field
## Cycle 1 of tablica_ciklov — its tables and tabs (like the tabs of web inside the cycle)
| tab | title | kind | open with |
| tablica_vnutri_cikla_1 | Таблица внутри цикла 1 | table of the cycle | read_table `{"table":"tablica_vnutri_cikla_1","cycle":1}` |
| tablica_vnutri_cikla_2 (current) | … | table of the cycle | read_table `{…,"cycle":1}` |
| tab_country | tab_country | tab button → opens its table | click_button `{"table":"tablica_ciklov","field":"tab_country","id":1}` |
```
- tab_ buttons (`tab_…` button columns of a cycles table): web hides them as columns for not creators (`fieldsForClient`) and shows them as tabs inside the cycle;
  the click runs the button of the cycle row (`App.clickToCyclesTabButton` → `click` item = cycle). Its `linkToTable` of a not calcs table makes the link
  `/Table/{top}/{cycles table}/{cycle}/{table}?b=tab_…` (`CalculateAction::funcLinkToTable`; filters also as `efl`) — the table is the usual table, not of the cycle.
  The opened window says `the table is opened as the tab tab_country of cycle 1 (… no "cycle" arg)` and shows the `## Cycle` section (current — the tab). Schema mark `cycle-tab`.
- Opening a cycle: web double click on a row of the cycles table = `button_to_cycle` (or the first calcs table) — `click_button` of `button_to_cycle` gives the link to the first table → "Opened window".
- Adding a cycle: web has no insert row in cycles tables (`model.add({})`), `cyclesTable::addRow` creates the tables of the cycle (`Cycle::create`) and, channel web,
  opens its first table (`linkToTable(table: first; cycle: new id)`). MCP: `add_row` without data → `**Cycle added**`, `added: N`, the line that the answer goes into the cycle,
  and "Opened window" with the first table and its `## Cycle` section. The tables of the new cycle and `calcstable_cycle_version` are not listed in `other changed tables`.
  `insert_row` of a cycles table → `ERROR: A cycles table has no insert row (like web): add a cycle with add_row without data …`.
- Deleting a cycle (`delete_rows` of the cycles table) deletes the data of its tables and its versions (`Totum::deleteCycle`).

One cycle per user (roles setting `one_cycle_tables`, `User::isOneCycleTable`; cycles of the user — `creator_id` in connected users, `cyclesTable::getUserCyclesCount`),
like `TableController::checkTableByUri` (`MCPTableResolver::oneCycle`: cycle id / null — no cycle / false — usual table):
- `read_table` / `get_table_schema` of the cycles table = opening of the page of web (`MCPTools::oneCycleRedirect`): no cycle → it is created (`reCalculateFromOvers add`,
  `**your cycle N is created now**`); then instead of the cycles table the first table of the cycle available for the user is opened (`## Opened: the first table of the cycle`):
  ```
  # Таблица циклов · tablica_ciklov · id 63 · cycles · one cycle per user
  **your cycle 5 is created now** (…)                     (only when it was created)
  one cycle per user: like web the cycles table is not shown — its opening goes into your cycle 5. …
  ---
  ## Opened: the first table of the cycle
  # Таблица внутри цикла 1 · … · cycle 5 (tablica_ciklov)
  ## Cycle 5 of tablica_ciklov — …
  one cycle per user: this cycle is your workspace — the cycles table is not shown (like web, no way back to it)
  ```
- Other tools on the cycles table (`MCPTools::checkOneCycle`, before the tool) → `ERROR: Table … has one cycle per user: like web the cycles table is not worked with directly — …`;
  allowed only `click_button` of a `tab_` button with `id` = the cycle of the user (web `clickToCyclesTabButton`). Adding is blocked by the core too (`WriteTableActions::add`).
- Several cycles of the user in such a table → the usual cycles table; `## Cycles` says `adding cycles is not allowed`.
- `list_tables`: type `cycles (one cycle per user)`.
- Test without changing roles: a harness with `MCPServer` and a user provider that sets `User::$tables['oneCycle']` by reflection (in memory only), e.g. `[63]` for `tablica_ciklov`.
  The agent has no own cycles there (cycles 1, 2 are of the admin): the first `read_table` creates one — delete it after the test.

### Table structure
```json
{"name": "get_table_schema", "arguments": {"table": "orders"}}
```
By categories (`## Params` / `## Filters` / `## Columns` / `## Footers`): `| Title | name | type | rights | description |`.
Type: `select→statuses` (select from a table), `number(2) {N $} money(thousands " ") step 0.5` (decimal places, value pattern, money separators, rounding step), `date (Y-m-d)` / `datetime (Y-m-d H:i)`, `multiple`; for a filter — which column it filters.
After the categories — `## Marks` (legend of the marks in rights, see below) and `## Field types`: how to read/write the types present in the table (`MCPMarkdown::TYPE_HINTS`).

Marks in rights of `get_table_schema` (`MCPMarkdown::featureMarks` + edit marks of `checkGroupEdit`):
| mark | meaning | source |
|---|---|---|
| `one-row` / `multi-field` | batch edit marks, see "Changes" | editGroup / editGroupMultiColumns |
| `panel` | the cell has a context panel (right click in web) with p-sections — `get_cell`, buttons — `click_panel_button` | `formatInPanel` of fieldsForClient (format has `pN=`) |
| `context-panel` | the button opens the context panel of its cell after pressing — shown in the answer of `click_button` | `openContextPanel` |
| `checked-rows` | the code of the button uses `$#ids` — pass the rows checked with checkmarks in `checked_ids` | `codeAction` contains `$#ids` |
| `log` | the log of manual changes is available — `get_cell`; fields without the mark have no log | `logButton` of fieldsForClient (logging, logRoles, not button/filter), not tmp |
| `cycle-tab` | tab_ button of a cycles table — a tab inside the cycle (see "Cycles") | `MCPTableResolver::isCycleTabButton` |
| `field-params` | the format sets the type of the cells (`setFieldParams`) — see "Field params of a cell" | `format` of the field (virtual columns — of the base field) contains `setFieldParams` |

Field types (UI name → type in output):
| UI | type | output details | value for writing |
|---|---|---|---|
| String | `string` | `regexp /…/ "error text"` (schema, insert row card) | text matching the regexp |
| Number | `number` | `(decimals)`, `{N unit}`, `money(…)`, `step`, `regexp /…/` (schema) | number without unit/separators |
| Select | `select` | `→table`, `multiple` | key / array of keys |
| Select-tree | `tree` | `→table`, `multiple` | key / array of keys (`get_select_options` with `parent_id`) |
| Checkbox | `checkbox` | | `true` / `false` |
| Date | `date` | `date (Y-m-d)` / `datetime (Y-m-d H:i)` | `"Y-m-d"` / `"Y-m-d H:i"` |
| Button | `button` | `"TEXT"` (schema) | — (`click_button`) |
| Label | `<type of linked field> link→table.field` | the core gives a link the type of the linked field | — read-only |
| Text | `text` | `/html`, `/json` … (textType) | text |
| Comments | `comments` | | text of a new comment |
| PRO File | `file` | `secure`, `versioned`, `accept …` (schema), `multiple` | list of files (see "Files") |
| Unique | `unic` | `regexp /…/ "error text"` (schema, insert row card) | text unique in the table |
| Password | `password` | value is hidden | new password |
| Data | `listRow` | | JSON |
| PRO Chart | `chart` | | — read-only |

`read_table`, cards (params/footers/rows) show the short type (`fieldType($field, true)`): without value pattern, money, step, button text, regexp, accept.

### Table data
```json
{"name": "read_table", "arguments": {"table": "orders"}}
{"name": "read_table", "arguments": {"table": "orders", "fields": ["name", "status", "amount"], "limit": 100}}
{"name": "read_table", "arguments": {"table": "orders", "filters": {"f_status": "new"}}}
{"name": "read_table", "arguments": {"table": "orders", "ids": [101, 102]}}
{"name": "read_table", "arguments": {"table": "plan", "cycle": 15}}
```
- `fields` — output only these fields (any category: params, columns, footers). Always narrow wide tables.
- `filters` — filter values `{filter_name: value}` or the crypted filters string of an opened window (linkToTable). No state is kept: pass filters in **every** call, including writes.
- `ids` — only these rows.
- `recalculate` (default `true`) — recalculate changed data like opening the table in web.
- `deleted: true` — show deleted rows (for `restore_rows`).
- `styles: true` — also the visual formats of web (see "Styles" below); not needed for usual work with data.

### Styles (styles: true)
The usual answers show only the formats that change the work with data (`#block`, `#icon-…`, `#text`, `#comment`, `#placeholder`, row flags).
The visual formats of web — colors, bold, background, italic, decoration, progress, align, tab, … — are shown on request
(`MCPMarkdown::styleMark` / `stylesSection`, flag `withStyles`):
```json
{"name": "read_table", "arguments": {"table": "warehouse_movements", "styles": true}}
{"name": "get_row", "arguments": {"table": "warehouse_movements", "id": 12, "styles": true}}
```
```
| Количество #info #style(panelColor: ##lightgray) | …                — panelColor of the field (setting of the field)
| 5 #blockdelete #style(background: ##yellow; italic: true) | …   — setRowFormat of the row
| -2 #style(color: ##tomato) | 6 #style(bold: true) | …            — setFormat of the cell
## Styles
table format: interlace: "##aliceblue/" (…)
user colors (table ttm__colors; ##name — the light color, the dark one in the dark theme): ##tomato = #cf222e (dark theme #cf222e), ##nosuch = not in ttm__colors: web uses the HTML color nosuch
```
- `#style(…)` is written in the syntax of `setFormat`: `##name` and numbers as is, strings in quotes, `false` only for `showhand` / `expand` (others — default of web), arrays as JSON (`hide`).
- The keys of the usual marks are not repeated (`block`, `icon`, `text`, `textasvalue`, `comment`, `placeholder`; of rows — `block…`, `rowcomment`); `fieldParams` is internal.
- `## Styles` — `interlace` of setTableFormat and the legend of the `##` colors met in the answer: light / dark of `ttm__colors` (`TableController::getColorsArray`, like `App.Colors` of web);
  a name not in the table — web strips `##` and uses it as an HTML color.
- `get_cell` shows the styles always: `style (visual format of web): …`, `style of the row (setRowFormat; …)`, `panel color of the field …` and the legend line.
- Insert row / panel cards and answers to writes have no styles.

Texts of the table — like in web:
```
## Description            — table description (info button in web); only on the first page (offset 0, without ids)
## Table text             — setTableFormat tabletext + tablehtml (text above the table in web), on every page
## Rows 1–50 of 340
rows comment: …           — setTableFormat tablecomment (comment of the rows part in web)
```
HTML is converted to plain text (tags removed, `<br>`/`</p>` → new line, `<script>`/`<style>` dropped). Links are kept as markdown: `<a href="url">text</a>` → `[text](url)`; a link without text or with text equal to url → `url`; `#`/`javascript:` links → text only. Relative urls (`/Table/1/60`) stay relative.

Audience parts of table descriptions and field helps (`Totum::filterAudienceText`, applied in web, forms and MCP):
| tag | web | MCP |
|---|---|---|
| `<admin>…</admin>` | creator only | creator only, shown as `[admin] …` line |
| `<agent>…</agent>` | hidden for everybody (incl. creator) | shown as a plain line — instructions for agents |

`ReadTableActions::isAgentInterface()` is `false`, `MCPActionsTrait` overrides it with `true`. A help that consists only of `<agent>` parts has no info icon in web.

### Next page
Default `limit = 50`, max 500. The answer has a header `## Rows 1–50 of 340`. If there are more rows, the end of the answer contains `next page: offset=50`. Pass this offset:
```json
{"name": "read_table", "arguments": {"table": "orders", "offset": 50}}
{"name": "read_table", "arguments": {"table": "orders", "offset": 100, "limit": 50}}
```
Pass the same filters and `fields` while paging. Row order is the same as in web (table sorting + setRowsOrder format).

### One row and full values
```json
{"name": "get_row", "arguments": {"table": "orders", "id": 101}}
{"name": "get_value", "arguments": {"table": "orders", "field": "comment", "id": 101}}
{"name": "get_value", "arguments": {"table": "orders", "field": "notes"}}
```
`get_row` — a row as a card with uncut values. `get_value` — full text, all comments, list of files. A params field needs no `id`.

### One cell — like right click in web
```json
{"name": "get_cell", "arguments": {"table": "orders", "field": "comment", "id": 101}}
{"name": "get_cell", "arguments": {"table": "orders", "field": "total", "log": 5}}
{"name": "click_panel_button", "arguments": {"table": "orders", "hash": "ca0b47…", "index": "1_c9dd73…"}}
```
`get_cell` returns the same as the right-click panel: field title (with `{N unit}`), `name`, row id and row title (main field), type and rights,
manual / calculated value (`manual value ✋, calculated: …` / `manual value ✊ — equal to calculated`), errors, format marks, styles (see "Styles"), full (uncut) value (`### Value`),
field description (`### Field description`), panel sections of the field format (`### Panel`: text / html / img / buttons), select previews (`### Preview`)
and the log of manual changes (`### Log of manual changes`: when, who, action, value after change, entered (if different); `log` — how many last entries, default 20, `0` — no log section).
`id` is required for column fields, omit it for params/footers.
Panel buttons are listed as `button: Text → click_panel_button(hash "…", index "…") · refresh: true (re-read the table after it)` — pass these hash and index to `click_panel_button` (hash lives only for a while: take it from a fresh `get_cell`).

Log of manual changes — the same data and access rules as the "Log" button of the web panel (`getFieldLog`: visible field, `logRoles`; `MCPActionsTrait::mcpLogUnavailable`):
- entries → table (`### Log of manual changes (N of M)`); no entries → `no manual changes in the field`;
- not available → `not available for this field: <reason>` (tmp tables are not logged / buttons have no log / filters have no log / logging is off for the field / not available for your roles (logRoles)).
`get_table_schema` marks the fields with a log as `log`.

Panel buttons (`panelButton` / `panelButtons` of the format, `_panelbuttons` hash): `click_panel_button` = `panelButtonsClick` of web; the answer has the changes and
`refresh: … — web refreshes the table after this button: re-read it` (refresh of the button, `panelButtons(refresh:)` is inherited by its buttons).
A button with `openContextPanel` (mark `context-panel`): web runs its code and then opens the context panel of the cell (`contextmenu`) —
the answer of `click_button` has `## Opened context panel (openContextPanel of the button)` with the cell panel (`get_cell` without log) and its buttons:
```
**Pressed: button_context_panel**
---
## Opened context panel (openContextPanel of the button)
## Cell: button_context_panel · button_context_panel · row 29
### Panel
- button: Поднять ревизию → click_panel_button(hash "fd7a…", index "1_dc0c…") · refresh: true (re-read the table after it)
```

### Options for select / tree
```json
{"name": "get_select_options", "arguments": {"table": "orders", "field": "status", "id": 101}}
{"name": "get_select_options", "arguments": {"table": "orders", "field": "client", "q": "acme"}}
{"name": "get_select_options", "arguments": {"table": "orders", "field": "city", "row": {"region": "msk"}}}
{"name": "get_select_options", "arguments": {"table": "orders", "field": "category", "parent_id": "9"}}       (tree: children of the node)
{"name": "get_select_options", "arguments": {"table": "orders", "field": "category", "q": "^~: Spare parts"}}  (search mode, see below)
```
Answer: `| key | title | parent |`. Write the `key`. `id` — for an existing row, `row` — values of a new row (when the list depends on other fields), `insert_hash` / `panel_hash` — the current state of the insert row / edit panel (see "Insert row", "Edit panel"), `input_hash` / `edit_hash` (without `field`) — the select of an input / edit window (see "Input windows", "Edit windows"). `not all options are shown — refine q` means the list is cut.
Long lists are cut like in web (`selectLength`, default 50): the needed option is found with `q`.

Search modes of `q` — the same function as in web (`SearchTrait::getSearchFunction`; case and `ё`/`е`, `á`… insensitive, the prefix `mode:` + spaces are cut):
| q | finds `Azot (20 sch)` | meaning |
|---|---|---|
| `Az 20` = `~~: Az 20` | yes | default: every part (separated by spaces) is found somewhere in the title |
| `!: Az 20` = `!~~: Az 20` | no | inverted default: titles without **any** of the parts (`!: Az Kis` excludes both `Azot…` and `Kislorod…`) |
| `~: ot (20` | yes | the whole string with spaces as is is found in the title (`~: Az 20` — no) |
| `!~: ot (20` | no | inverted `~:` |
| `=: Azot (20 sch)` | yes | equal to the whole title (`=: Azot` — no) |
| `!=: Azot (20 sch)` | no | inverted `=:` |
| `^: Azot (20`, `^: Az sch` | yes | every part is the **beginning of a word** of the title (`^: Azot 20` — no: the word is `(20`; `^: zot` — no) |
| `!^: Azot` | no | titles where no word begins with a part |
| `^~: Azot (20` | yes | the title begins with the whole string (`^~: ot (20` — no) |
| `!^~: Azot (20` | no | inverted `^~:` |

How MCP applies `q`:
- select: the core searches only in long lists (> selectLength) and cuts the result; short lists web filters on the client side — MCP applies the same function to every list.
- tree: for `q` the core returns only the ids of the parents of the found nodes (web loads these branches and searches in them on the client side) —
  `MCPTools::treeSearch` loads the top level and these branches (`parent_id` as array = massload) and filters them by `q`: the whole tree is searched,
  the answer has the found nodes with `parent` = key and title of the parent.
- tree without `q`: a long tree gives only the top level (+ the selected value and its branch) with `(has children)` and the line
  `the tree is not loaded completely …` — children with `parent_id`, or `q` over the whole tree.

Select of `setFieldParams` (a Data field whose cell is a select by the format, mark `#fieldParams(select…)`, see "Field params of a cell"): `get_select_options` with `field` + `id`
(the core `getEditSelectFromTable` builds the Select from the params of the cell). A virtual column of a dynamic field (`dyn/key`) has no field in the core:
the options are the `values` of the params of the cell (`MCPTools::paramsValuesOptions`); its `codeSelect` → error.

### Field params of a cell (setFieldParams)
The cell format `setFieldParams(condition; params)` makes web show and edit a cell as a field of another type (string, number, checkbox, select…; docs `dynamic-fields#dynamic-field-params`),
also in the virtual columns of a dynamic field (`dn=:` of the table format, `$#nfd` — the key of the column). The params come in the format of the cell `f.fieldParams`
(select — also the titles `v_` of the value: `Field::addFormat`):
- `read_table` / `get_row` / cards: mark `#fieldParams(number(3))`, `#fieldParams(string, required)`, `#fieldParams(select multiple)` (`MCPMarkdown::fieldParamsType` = `fieldType` of the params);
  select / tree / checkbox values are shown like these types (`Alpha [a], Gamma [c]`, `true`).
- `get_table_schema`: mark `field-params` of the fields whose format has `setFieldParams` (virtual columns — by the base field).
- `get_cell`: `field params of the cell …: web shows and edits this cell as number(3) instead of listRow`, `params: {json}` (without `v_`) and how to write the value
  (select — the call of `get_select_options`). The value is kept in the field itself and written by its rules (Data): required / regexp / decimal places of the params are checked only by the browser.
- Core fix: `CalculateFormat::funcSetFieldParams` of a select in a virtual column (`dyn/key`) took the field `dyn/key` that does not exist (warnings) — now the base field.
Test table: `mcp_test_search` (`value` — Data with setFieldParams by `kind`, `dyn` — dynamic field with columns `dyn/n` number, `dyn/c` checkbox, `dyn/s` select).

### Changes
```json
{"name": "edit", "arguments": {"table": "orders",
  "rows": {"101": {"status": "done", "amount": 200}},
  "params": {"client": "ACME Ltd"},
  "reset": {"rows": {"101": ["discount"]}, "params": ["total"]},
  "updated": "1621071962"}}
{"name": "edit", "arguments": {"table": "orders", "rows": {"101": {"amount": 300}}, "dry_run": true}}
{"name": "add_row", "arguments": {"table": "orders", "insert_hash": "i-b233…"}}                             (correct way — see "Insert row")
{"name": "add_row", "arguments": {"table": "orders", "data": {"name": "Order 5", "status": "new"}}}         (direct insert — shortcut)
{"name": "add_row", "arguments": {"table": "orders", "data": {"name": "Order 5"}, "dry_run": true}}
{"name": "delete_rows",    "arguments": {"table": "orders", "ids": [101]}}
{"name": "restore_rows",   "arguments": {"table": "orders", "ids": [101]}}
{"name": "duplicate_rows", "arguments": {"table": "orders", "ids": [101], "after_id": 105}}
{"name": "duplicate_rows", "arguments": {"table": "orders", "ids": [101, 102], "replaces": {"101": {"article": "A-1"}, "102": {"article": "A-2"}}}}
{"name": "reorder_rows",   "arguments": {"table": "orders", "ordered_ids": [103, 101, 102]}}
{"name": "reorder_rows",   "arguments": {"table": "orders", "ids": [107, 108], "after_id": 101}}
{"name": "reorder_rows",   "arguments": {"table": "orders", "ids": [107], "after_id": 0}}
{"name": "recalc_rows",    "arguments": {"table": "orders", "ids": [101]}}
{"name": "click_button",   "arguments": {"table": "orders", "field": "send", "id": 101}}
{"name": "click_button",   "arguments": {"table": "orders", "field": "export_all", "checked_ids": [101, 102]}}
```
- `rows` — row columns `{"id": {field: value}}`, `params` — params and footer fields. Filters are not passed here — only in `filters`.
- Values: select — key (multiple — array of keys), date `Y-m-d` / datetime `Y-m-d H:i`, checkbox `true`/`false`, number — number, comments — text of a new comment, listRow — array.
- Comments (chat): a text adds a new comment; `{"edit_last": "new text"}` (in `edit` and `edit_panel`) edits the own last comment — like web, only if the last comment of the field
  is of the agent user (`MCPActionsTrait::mcpEditLastComment` → core `{value, editLastComment: old text}`, `Comments::modify` channel web, the comment gets the flag "edited").
  Otherwise `ERROR: … only your own comment can be edited, and only if it is the last one of the field`.
  Output: `(3, unread 1) 22.11 agent: text (edited)` in tables/cards; `get_value` / `get_cell` list all comments, the editable one is marked `#editable` with the hint how to edit it.
- `duplicate_rows` (rows checked in web → `ids`): copies are added after `after_id` (web: after the row of the menu). Unique (`unic`) columns need new values for the copies —
  the dialog "Fill in the values for unique fields" of web: `replaces` `{"source row id": {unic field: value}}` (`data` of the core `duplicate`), required for every visible unic column
  when the table has no duplicate code (`on_duplicate`); `replaces` with other fields → error. `MCPTools::checkDuplicateReplaces`.
- Writing to a field with code (`calc`) makes the value manual (✋). `reset` removes the manual value and returns the calculated one.
- `dry_run` — show the calculated result without saving (`edit` — only for one row).
- `updated` — the code from the header of the previous answer. If somebody changed the table since then — error, re-read the table.
- `reorder_rows` (only tables with manual order, header line `order: manual (n-field) · can change`):
  `ordered_ids` — these rows take the positions they occupy now, in the given order (pass all ids of the table for a full sort);
  `ids` + `after_id` — move rows after the row `after_id`; `after_id: 0` — to the beginning.
- `click_button`: a row button needs `id`, a params/footer button — no `id`. `checked_ids` are passed to the button code as `$#ids`.
  Checkmarks: in web rows are checked with checkmarks and `checked_ids` are sent with every button press (row, params, insert row) — MCP is stateless, the agent passes them in every call.
  Buttons whose code uses `$#ids` are marked `checked-rows` in `get_table_schema`; an error of such a button without `checked_ids` gets the hint
  `(the code of … works with the rows checked with checkmarks in web — $#ids: pass them in checked_ids)` (the code itself usually says "check rows" in the language of the schema).
  `uncheckAfterClick` — line `the checkmarks are cleared after this button …`.
  Buttons have rights `click` and are shown as `[BUTTON TEXT]`; `get_cell` / `get_table_schema` show the type `button "TEXT"`.
  The answer contains the rows changed by the button code (`changed fields — …`) and `## Side effects` (links, messages, dialogs).
  `other changed tables: …` — the action changed other tables too (e.g. a button in a window wrote to the source table): re-read them.
- One or several rows / fields in one `edit` — like the group edit of web (selected cells, `MCPTools::checkGroupEdit`), by field settings:
  | setting (UI) | field | several in one call | why |
  |---|---|---|---|
  | Batch Edit (`editGroup`) off | column | several **rows** → `ERROR: Batch edit is off for these fields … Nothing is saved: - amount (rows 101, 102)` | heavy for the server: one row per call, wait for the answer before the next one |
  | Batch Edit: With other columns selected (`editGroupMultiColumns`) off | any | several **fields** (rows and params of the call together) → saved + `warning: amount, note — changed together with other fields, …` | an action code of the field may change other fields — values set at the same time may be incorrect: better alone (one field per call) or in `edit_panel` |
  `get_table_schema` marks editable fields: `one-row` (editGroup off, column) and `multi-field` (editGroupMultiColumns on), legend in `## Marks`.
  Reset counts like a change. Web skips such cells in a group selection; `edit_panel` (several fields of one row) is allowed like the panel of web.
- `recalc_rows` — recalculation of the rows like "recalculate" in web (e.g. after a new code field was added: values of the old rows appear only after it).

### Insert row (insert_row)
The web insert row: a row for adding with its own hash `i-…` (TmpTables `_insert_row`, per user), not in the table until it is saved.
The core recalculates it after every change (`checkInsertRow`): code fields, selects depending on other fields; button codes change it with `set(…; hash: $#ih)`.
**The correct way to add a row is the insert row** (`insert_row` → `add_row` with `insert_hash`) — the way the user adds it in web:
the agent sees the row as it is calculated after every change, selects give only the options allowed for the current state of the row,
buttons of the insert row set the values that the user would set with them.
`add_row` with `data` (without `insert_hash`) is a direct insert — a shortcut: values go to the table without these steps,
an inadmissible value of one of the fields (a select key not allowed by the other fields, a value that a button of the insert row must set) can break the user system.
Use it only when it is known that the table has no such dependencies (tool descriptions say the same to the agent).
```json
{"name": "insert_row",         "arguments": {"table": "orders"}}
{"name": "get_select_options", "arguments": {"table": "orders", "field": "client", "insert_hash": "i-b233…", "q": "acme"}}
{"name": "insert_row",         "arguments": {"table": "orders", "insert_hash": "i-b233…", "data": {"name": "Order 6", "client": "12"}}}
{"name": "insert_row",         "arguments": {"table": "orders", "insert_hash": "i-b233…", "reset": ["priority"]}}
{"name": "click_button",       "arguments": {"table": "orders", "field": "start_revision", "insert_hash": "i-b233…"}}
{"name": "add_row",            "arguments": {"table": "orders", "insert_hash": "i-b233…", "data": {"amount": 200}}}
```
Answer of `insert_row` (and of `click_button` with `insert_hash` — after the button):
```
## Insert row (new) · insert_hash i-b233…
Not added to the table yet. … Pass `{"table":"orders","insert_hash":"i-b233…"}` to: insert_row / get_select_options / click_button / add_row
| Title | name | type | rights | value |
| Client | client | select | add | ACME Ltd [12] |
| Priority | priority | string | add calc | 2 |                  — calculated from client by code
| Start revision | start_revision | button | click | [+ REVISION] | — active in the insert row
| Show items | show_items | button | — | [SHOW] |                   — not active in the insert row
```
- Rights in the card: `add` — can be set with `insert_row` data / reset; `click` — button with `buttonActiveOnInsert` (field setting "active in the insert row"; web blocks the others). In tables/schema such buttons have the right `add-click`.
- `data` — like `add_row` data; only insertable fields (`add`), checked against `#block` of the cells of the current state of the row and the input format (regexp, see "Input format"). `reset` — return calculated values (web `clearField`).
- Fields that are not insertable (e.g. `revision`) are set only by button codes (`set` with `hash: $#ih` writes to the insert row via channel inner).
- `click_button` with `insert_hash` (instead of `id`): item = hash like in web, `$#ih` = hash, `#id` is empty. Only buttons with `buttonActiveOnInsert`, not `#block`.
- A window opened by an insert-row button (e.g. `choose_delivery` → linkToDataTable `delivery_types` with `h_insert_hash = $#ih`; its `f_save` writes `set(…; hash: #h_insert_hash)`)
  has the line `the window is opened from the insert row … see the result with insert_row {…}`: the window changes only the storage of the insert row,
  so its answers show no changes — re-read the insert row with `insert_row` after the window.
- `add_row` with `insert_hash` = save button of web (`add` by hash: values from the storage, required fields checked, `after_id` works). `data` — last changes before saving; `dry_run` is not used (insert_row shows the row).
  After saving the hash is deleted: `ERROR: … The insert row i-… is not found (saved or expired): open a new one with insert_row`.
- Rights: write access, `insert` action of the table, not `#blockadd`; the hash works only for the user that opened it. Not stored rows expire by TmpTables cleanup.
- `filters` of the table are applied to the insert row like in web (values of filters of columns without code are put into the row) — pass the same `filters` in every call.

### Edit panel (edit_panel, linkToPanel)
The panel of web is a view of one row where several fields are changed and saved at once. The changes are collected in the storage of the hash `e-…`
(TmpTables `_edit_row`, per user, keeps `id` + the changes as `{v}` / `{v: null, h: false}` for reset) and recalculated by the core after every change
without saving (`getEditRow` → `checkEditRow`, isCheck). The save button = `saveEditRow` (channel web: rights of fields, codes, logs like an edit of the row).
```json
{"name": "edit_panel",         "arguments": {"table": "orders", "id": 101}}
{"name": "edit_panel",         "arguments": {"table": "orders", "panel_hash": "e-ade6…", "data": {"amount": 777, "client": "12"}}}
{"name": "get_select_options", "arguments": {"table": "orders", "field": "client", "panel_hash": "e-ade6…", "q": "acme"}}
{"name": "click_button",       "arguments": {"table": "orders", "field": "start_revision", "panel_hash": "e-ade6…"}}
{"name": "edit_panel",         "arguments": {"table": "orders", "panel_hash": "e-ade6…", "reset": ["priority"], "save": true}}
```
```
## Panel (new) · row 101 · panel_hash e-ade6…
not saved changes: amount, client — values below are calculated with them. … Pass `{"table":"orders","panel_hash":"e-ade6…"}` to: edit_panel / get_select_options / click_button
the panel shows the fields: name, amount                    (only with fields: of linkToPanel)
| Title | name | type | rights | value |
| Amount {N $} | amount | number | edit | 777 |
| Priority | priority | string | calc | 2 |               — recalculated with the not saved client
```
- Rights in the card: `edit` — can be set with `edit_panel` data / reset (editable field), `calc`, `click` — buttons of the row, `required`. Data fields: editable, not `#block` (row / cell of the current state of the panel), input format (regexp).
- `click_button` with `panel_hash`: the button works with the saved row (like web), its code sees the not saved values of the panel as `#pnl.field` (`__edit_hash`). The answer: changes of the button + the panel after it (the not saved changes stay).
- `save: true` (with or without data/reset in the same call): all collected changes are saved at once, then the hash is deleted — `**The panel is closed**`; `Nothing to save` — no changes.
- Without write access the panel is not opened (`… read-only … Read the row with get_row`).

`linkToPanel(table; id | field: | bfield:; fields; titles; refresh)` — the code opens panels (`Totum::getPanelLinks`), MCP opens them at once after the answer (`MCPTools::panelWindow`):
```
## Side effects
- panel: /Table/37/60/ row 1 (see "Opened panel" below)            — or "new row"

---
## Opened panel: orders · row 1
Work with it like with the panel of web: pass `{"table":"orders"}` + the hash below.
refresh: true — …
## Panel (new) · row 1 · panel_hash e-edb4…                        (card as above)
```
- `id` (list — several panels) / `bfield` (rows by a field value) → edit panels; without id → the panel of a new row = the insert row of the table (`insert_row` / `add_row`), `field:` — its values (like linkToTable `field:`).
- `fields` — the panel shows only these fields (`the panel shows the fields: …`), `titles` — titles of fields in the card; `columns` / `fieldsettings` — layout of web, not used.

### Source of a select (select_source)
A select (or tree) with a source table (field setting `selectTable`, also `selectTableBaseField`, custom `selectTableAction`) has buttons near the select in web:
edit the selected row of the source table and `+` — add a new row there. Both open panels (default code `linkToPanel(table: selectTable; id: #field)`, `+` — `id: ""`).
```json
{"name": "select_source", "arguments": {"table": "orders", "field": "client", "id": 101}}
{"name": "select_source", "arguments": {"table": "orders", "field": "client", "insert_hash": "i-b233…", "plus": true}}
```
- The row of the select: `id` (saved row), `insert_hash` (insert row), `panel_hash` (edit panel) or nothing (params / footer select) — like web the code gets the current values of this row.
- The panel of the source table is in the answer ("Opened panel": edit panel of the selected row or the insert row of the source table) — work in it with the args of the source table.
- After `+` and `add_row` in the source table web puts the new key into the select automatically — the agent sets it itself (`added: N` → `edit` / `insert_row` / `edit_panel`).

### Windows with text, html, json (linkToDataText / linkToDataHtml / linkToDataJson)
The content of the window is in the answer of the action (`MCPMarkdown::textWindow` / `jsonWindow`):
```
## Side effects
- text: Title (window — see "Opened window" below)        — also html: … / json: …

---
## Opened window (text): Title          — linkToDataText: text as is
## Opened window (html): Title          — linkToDataHtml: html → plain text, links → [text](url)
## Opened window (json): Title          — linkToDataJson: ```json … ``` (pretty), "view only" without code
```
linkToDataJson with `code` has a button (buttontext, default OK) that runs the code with the edited JSON as `$#value`:
```
## Opened window (json): Delivery data
button [OK]: change the JSON if needed and press it with submit_json_window `{"table":"orders","hash":"t-1814…","json":"<JSON>"}` — …
refresh: true — the source table is refreshed after the window: re-read it when the work in the window is done.
```
```json
{"name": "click_button",       "arguments": {"table": "orders", "field": "edit_delivery_json", "id": 101}}
{"name": "submit_json_window", "arguments": {"table": "orders", "hash": "t-1814…", "json": ["type_1", "type_3"]}}
```
- `json` is the JSON value itself (array/object/…), not a string with JSON.
- The hash works once (`linkJsonClick` deletes it): a second press → error "Temporary table storage time has expired"; press the opening button again.
- The answer is like other writes: changed rows of the table, `other changed tables: …`, then `**The window is closed**`.
- Table args (`table`/`cycle`/`sess_hash`) are of the table where the window was opened; the code runs in the environment saved with the hash (`loadEnvirement`).

`refresh` of all windows (text/html/json/table and links): `true`, `"recalculate"`, `"reload"` mean the same for the agent — a line `refresh: … — … re-read it` (`MCPMarkdown::isRefresh`; the values differ only in web: refresh of data / reload of the page). `false`, `""`, `"false"`, `0` — no line.

### Buttons windows (linkToButtons)
`linkToButtons(title; html; buttons; refresh)` — a window with a title, html text and one or more buttons (`text`, `code` — code or name of a button field, `vars`, `refresh`, `icon`).
The core saves the params with a hash (`_tmp_tables` `_linkToButtons`, per user); web presses a button with `linkButtonsClick` (`hash`, `index`): the code runs in the environment
of the hash (`loadEnvirement`), then web closes the window (always), with `refresh` of the button (default — `refresh` of the window) refreshes the table, with `close` closes the iframe.
```
## Side effects
- buttons: Кнопки (window — see "Opened window" below)

---
## Opened window (buttons): Кнопки
Нажми на кнопку Москва                                             — html → plain text
| index | button | refresh |
| 0 | [Москва] | true — re-read the table after it |
| 1 | [Лондон] | true — re-read the table after it |
press one button with submit_buttons `{"table":"test_agents_table","hash":"5852…","index":"<index>"}` — … press only one button, do not use this hash again.
```
```json
{"name": "click_button",   "arguments": {"table": "test_agents_table", "field": "h_linktobuttons"}}
{"name": "submit_buttons", "arguments": {"table": "test_agents_table", "hash": "5852…", "index": 0}}
```
- Answer: like other writes (changed rows, `other changed tables: …`), `refresh: …` line of the button, `**The buttons window is closed**`; windows opened by the code of the button — "Opened window" sections.
- The core does not delete the hash after a button (it lives until TmpTables cleanup), MCP does not delete it either (like linkToEdit):
  web closes the window, so the answer says `**The buttons window is closed** … do not press its buttons again with this hash`. Expired hash → `ERROR: <The choice is outdated> Press the opening button again`.
- Table args are of the table where the window was opened. Opened from an insert-row button — the line `the window is opened from the insert row …`.

### Input windows (linkToInput / linkToInputSelect)
The code saves its params with a hash (`_tmp_tables` `_linkToInput`, per user); web shows a window with an input and sends the value to
`linkInputClick` (`hash`, `val`) — the code of the button runs with `$#input` (+ `var:` values) in the environment of the hash (`loadEnvirement`: table and row of the action).
The agent gets the window in the answer of the action and presses its button with `submit_input`:
```
## Side effects
- input: Enter amount (window — see "Opened window" below)

---
## Opened window (input): Enter amount
input: text input — value is a string                              — kinds: see the table below
current value: "100"                                               — value: of the code (html form — the text of its ttmInput)
button [Save]: press it with submit_input `{"table":"orders","hash":"c110…","value":"<value>"}` — the code of the button gets the value as $#input. The hash works once.
refresh: true — the source table is refreshed after the window: re-read it when the work in the window is done.
close: true — the window (iframe) where the input was opened is closed after the button.     (only with close: true)

### Options                                                         (linkToInputSelect only)
| key | title |
```
```json
{"name": "click_button",       "arguments": {"table": "orders", "field": "set_note", "id": 101}}
{"name": "submit_input",       "arguments": {"table": "orders", "hash": "c110…", "value": "text"}}
{"name": "get_select_options", "arguments": {"table": "orders", "input_hash": "cc45…", "q": "^: Ve"}}
{"name": "submit_input",       "arguments": {"table": "orders", "hash": "cc45…", "value": ["26", "38"]}}
```
| kind | params of the code | `value` of submit_input |
|---|---|---|
| `text input` (`type …` — e.g. password) | linkToInput, `value` — default, `html` — text above the input (shown as plain text) | string (number/bool → string) |
| `html form of the window with its own field ttmInput` | linkToInput `html` with `<textarea id="ttmInput">` (may be hidden): web disables the standard input and sends the text of this textarea, its JS form fills it | string in the format the form builds; an object/array is sent as its JSON text. The answer shows the initial text of ttmInput and the html source (`html` block, up to 5000 chars) to understand the form |
| `select, one value` | linkToInputSelect, `multiple: false` (`multiple: "force"` → one value + `selectAndSave`: web saves at once on choosing) | key |
| `select, multiple` | linkToInputSelect `multiple: true` | array of keys |
- Select: options of `codeselect` are in `### Options` (cut like web, `selectLength`); search — `get_select_options` with `input_hash` and `q` (search modes). The core checks the value (`checkSelectVal`): `ERROR: This value is not available for entry in field …` (the core, in the language of the schema); a list for one value — `ERROR: The select of the input takes one value…`.
- The hash works once (deleted after the code): `ERROR: The proposed input is outdated. The hash works once: press the opening button again`. An unused hash just stays (web closes the window without a request).
- Answer of `submit_input`: like other writes (changed rows, `other changed tables: …`), then `**The input window is closed**`; with `close: true` — also the line that the window (iframe) where the input was opened is closed.
- `refresh` (`true` / `"recalculate"` / `"reload"`) — the `refresh:` line (`MCPMarkdown::refreshLine`).
- Opened from an insert-row button — the line `the window is opened from the insert row …` (the code may change the insert row via `$#ih`).
- Table args of `submit_input` / `get_select_options` are of the table where the window was opened.

### Edit windows (linkToEdit)
`linkToEdit(table; field; id)` opens one field of a row (or of params without `id`) of a table — maybe another one — for editing. The code gives the access:
the field is saved via channel inner (`saveLinkToEdit` → `reCalculateFromOvers`), rights of the field (editRoles, editable) are not checked — like web.
The hash (`_linktoedit`) keeps table / field / id / extra (cycle or sess_hash); web saves with `shash` + `data` and gets the options of select/tree with `search`.
```
## Side effects
- edit: Edit amount (window — see "Opened window" below)

---
## Opened window (edit): Edit amount
| Title | name | type | where | current value |
| Amount {N $} | amount | number {N $} | table orders, row 101 | 200 |
value: number — number without unit and separators (see {N …} in title)                — TYPE_HINTS of the type
save with submit_edit `{"table":"orders","hash":"t-a692…","value":"<value>"}` — the code gives the access to the field (…)
the value is calculated: submit_edit with "special": "pin" (instead of value) fixes it as manual    — fields with code
refresh: true — …

### Options                                                         (select / tree only)
| key | title | parent |
```
```json
{"name": "submit_edit",        "arguments": {"table": "orders", "hash": "t-a692…", "value": 300}}
{"name": "submit_edit",        "arguments": {"table": "orders", "hash": "t-a692…", "special": "reset"}}
{"name": "get_select_options", "arguments": {"table": "orders", "edit_hash": "t-a692…", "q": "^: Ac"}}
```
- `value` — like `edit`: select — key (multiple — array), date `Y-m-d`, checkbox `true`/`false`, comments — text of a new comment, listRow — JSON, file — the list of files (see "Files"; the current files are listed in the window).
- Checks: the core checks the type (`ERROR: The value of the … field must be numeric`) and select values; MCP checks the input format (regexp) before saving like `edit`.
- `special` (fields with code, not `codeOnlyInAdd`, like the buttons of the web window): `reset` → `setValuesToDefaults` (manual → calculated, shown when the value is manual ✋/✊),
  `pin` → `setValuesToPinned` with the current value (calculated → fixed manual ✊, shown when the value is calculated). For a field without code — error.
- Select / tree: the options of the window are loaded at once (`### Options`, like web: `saveLinkToEdit` search); search and tree branches — `get_select_options` with `edit_hash` (`q` with search modes, `parent_id`).
- Answer of `submit_edit`: like other writes for the table of the action; if the field is in another table — `the field is in the table … — re-read it`; then `**The edit window is closed**`.
  The core does not delete the hash on saving (it lives until TmpTables cleanup), but web closes the window: press the opening button again for one more change.
- Table args of `submit_edit` / `get_select_options` are of the table where the window was opened; the table of the field is in the `where` column.
- In HTTP mode `saveLinkToEdit` calls `session_start()` (secure file access of linkToEdit) — the answer may contain a session cookie, it is not used by MCP.

### Windows with tables (linkToDataTable)
A button with `linkToDataTable` opens a window with a table (usually a tmp table). The agent gets it at once in the answer:
```
## Side effects
- table: Choose delivery type (window with a table — see "Opened window" below)

---
## Opened window: Choose delivery type
Work with it like in the web window: pass `{"table":"delivery_types","sess_hash":"91b5…"}` to read_table / edit / click_button / get_cell.
refresh: true — the source table is refreshed after the window: re-read it when the work in the window is done.

# … · delivery_types · id 62 · tmp · sess_hash 91b5…   (the table as read_table)
```
Workflow (example: choose delivery types for a row of `orders`):
```json
{"name": "click_button", "arguments": {"table": "orders", "field": "choose_delivery", "id": 101}}
{"name": "edit",         "arguments": {"table": "delivery_types", "sess_hash": "91b5…", "rows": {"1": {"checked": true}, "3": {"checked": true}}}}
{"name": "click_button", "arguments": {"table": "delivery_types", "sess_hash": "91b5…", "field": "f_save"}}
{"name": "get_cell",     "arguments": {"table": "orders", "field": "delivery", "id": 101}}
```
- Always pass the same `sess_hash` — without it a new empty tmp table is created.
- `refresh: true` of the opening button — the source table changes after the window: re-read it at the end.
- `**The window is closed after this button** (closeIframeAfterClick)` — the work with the window is finished, do not press the button again; open a new window if needed.
- `**The window is hidden after this button** (hideIframeAfterClick)` — the window is hidden in web.
- tmp tables are removed by the garbage collector; nothing has to be closed or deleted.
- Links to tables (`linkToTable`) are opened the same way — see below.

### Links to tables (linkToTable)
`MCPTableResolver::parseLink` parses `/Table/{top}/{table}` and `/Table/{top}/{cycles table}/{cycle}/{table}` (calcs → `cycle` arg), `sess_hash` (tmp),
`f`/`efl` — crypted filters from `filter:` of linkToTable, `a` — values from `field:` for the insert row.
```
## Side effects
- link (iframe): <title of linkToTable> → /Table/37/61?iframe=1 · crypted filters (table — see "Opened window" below)

---
## Opened window: <title of linkToTable>
Work with it like in the web window: pass `{"table":"clients","filters":"p5ESFXUq…"}` to read_table / edit / click_button / get_cell.
filters are set by the link (crypted string — the core decrypts it, the values are in ## Filters): pass the same "filters" string in every call to this table (filters are stateless).
the link opens the insert row with the values {"note":"test"} (field: of linkToTable) — like web: see "## Insert row" below, …   (only with field:)

# Clients · clients · id 61 · simple   (read_table with these filters: ## Filters shows the values set by the link)
…
## Insert row (new) · insert_hash i-8356…             (only with field: of linkToTable)
Not added to the table yet. … Pass `{"table":"clients","filters":"p5ESFXUq…","insert_hash":"i-8356…"}` to: …
| Title | name | type | rights | value |
| Priority | priority | string | add | 1 |      — from the filter of the link (column without code, like web)
| Note | note | string | add | test |           — from field: of the link
```
linkToTable with `field:` (query `a[field]=value`, `addVars` of web) — web opens the target table and at once its insert row with these values.
MCP does the same (`MCPTools::linkInsertRow`): opens the insert row (`insert_row` with `data` = the values) and adds its card after the table.
The agent continues with the args from the card (`table` + the same `filters` string + `insert_hash`) and saves the row with `add_row`; nothing is added until then.
- Like web (channel web), values of fields that are not visible/insertable columns for the user are not set: `not set (not insertable columns for you, web ignores them too): …`.
- Values of filters of the link are put into the insert row too (filter of a column without code — like web).
- An error of the insert row (format, rights, `#blockadd`) does not cancel the button: `The insert row of the link is not opened: …` after the table.
Crypted filters are never decrypted by MCP: they may contain values that must not be seen. Like `?f=` of the web page the string goes to the core as is
(`filters` arg is an object **or** this string → `MCPActionsTrait::deCryptFilters` → core `deCryptFilters` → `getPermittedFilters`: only editable filter fields),
the agent sees only what the web user sees — visible filter fields in `## Filters`.
- Crypt key: in web it is random per session; the HTTP agent has no session, so `MCPServer::setCryptKey` sets a stable key of the agent user
  (`hash_hmac(schema/user id, crypto.key file or crypt_solt)`) before every call — the string works in the next calls/requests.
- A wrong/foreign string → `ERROR: Crypted filters are not valid…` (web silently ignores it; the agent would get the table without the filters).
- A plain object replaces the string completely: crypted filters are lost — to keep them, pass the string.
- If a field cannot be changed (no rights, wrong category, field not found) — immediate error, nothing is saved.
- Blocks from formats are enforced by MCP on the server side: the Totum core does not check format blocks on write (the web UI only disables the controls; a server check exists only where it is coded explicitly). The whole request is rejected, nothing is saved:
  | action | blocked by |
  |---|---|
  | `edit` (also `reset`, `dry_run`) | cell `#block` (setFormat), row `#block` (setRowFormat), table `#block` (setTableFormat) |
  | `edit_panel` (data, reset, save) | the same: data — of the current state of the panel, save — of the saved row |
  | `add_row`, `insert_row` | table `#blockadd`; `insert_row` data — also cell `#block` of the insert row |
  | `delete_rows` | row / table `#blockdelete` |
  | `duplicate_rows` | row / table `#blockduplicate` |
  | `reorder_rows` | row / table `#blockorder` |
  | `click_button` | `#block` of the button cell (with `insert_hash` — of the insert row; button without `buttonActiveOnInsert` — error) |
  Error example: `ERROR: Field amount of row 101 is blocked by format (#block)`.
- Input format (field setting "Input validation format (regexp)" + `regexpErrorText`, types string / unic / number): the core checks it on the server
  (`checkValByType` → criticalException, transaction is rolled back), but the error has only `regexpErrorText` (`ERROR: Error text for user`) — without field and value.
  `regexpErrorText` is the user's error text set in the field settings (not a message of the core or MCP); without it the core uses
  `The value of %s field must match the format: %s`. In the examples "Error text for user" stands for this text of the field
  (field `article` with regexp `^[0-9]+$`).
  MCP checks the values before the action (`MCPTools::checkFormats`, same pattern: `/regexp/u`, number — without `u` and only numeric values, empty values are not checked)
  for `edit` (rows and params), `add_row`, `insert_row` and `add_row` with `insert_hash`, all mismatches at once, nothing is saved:
  ```
  ERROR: Values do not match the input format (regexp) of the fields, nothing is saved:
  - row 101, article "abc": must match /^[0-9]+$/ — Error text for user
  - row 102, article "0.5": must match /^[0-9]+$/ — Error text for user
  ```
  After `—` — `regexpErrorText` of the field as is (omitted if the field has none).
  The format is shown in `get_table_schema` and in the insert row card: `string regexp /^[0-9]+$/ "Error text for user"` — in quotes `regexpErrorText` (read_table / cards — short type without it).
  Values written by codes (e.g. `set` of a button) are checked by the core only.

### Unread comments (mark_comments_read)
Like web (`Comments`: `_comments_viewed.nums` — how many comments of the cell the user has read; unread = the last `n − nums`, if the last comment is not his own):
- tables / cards: `(3, unread 1) …` (`notViewed` of the web view);
- `get_value` / `get_cell` of a comments field: the unread comments get `#unread` (counted before: `MCPActionsTrait::mcpUnreadComments`; for get_cell — `notViewed` of the formatted cell)
  and the line that they are marked as read now — the core does it in `getFullValue` like web when the comments are opened;
- `mark_comments_read {table, field, ids}` (no ids for params / footers) = `setCommentsViewed` of web (nums = count of the comments) without reading:
  `- row 9: 2 comment(s), 2 were unread`.

### Export (export_table)
```json
{"name": "export_table", "arguments": {"table": "test_agents_table", "format": "csv", "type": "rows", "fields": ["city_name", "country"], "ids": [29, 9]}}
{"name": "export_table", "arguments": {"table": "test_agents_table", "format": "xlsx", "with_id": true}}
```
- Rows: like read_table with the `filters` of the call (all rows of the filters in the order of web, up to 100000; `ids` — only these), `fields` — default all visible.
- `csv` = "CSV-export" of web (`csvExport` of the core, role right `csv`): `type` `full` (default — params, filters, rows, footers in the format of the csv import) / `rows`;
  name `<title>.<updated dt>.csv`.
- `xlsx` = Excel export of web (`excelExport` → `ServiceXlsxGenerator` *NEW*; `unblockServices`): only when the service `xlsx` is on for the table (`ttm__services`),
  data — the first line titles (+ `id` with `with_id`), cells like the copy text of web (`MCPMarkdown::exportValue`: titles of selects, names of files, the last comment, full texts).
  Not tested end-to-end on ocho: the service has no tables there → `ERROR: Excel export is not switched on for this table (service xlsx): use the csv format`.
- The file comes as `files` interface data → "Downloaded files" (stdio — the folder of the agent, HTTP — a link).
- `get_table_schema`: `export: export_table — csv, xlsx`; with the csv_edit right / service xlsximport — `import: the csv / xlsx import of web is not supported by the agent interface`.

### Not in the agent interface (decided)
csv / xlsx import; kanban / panels view and the tree view of rows (the same data is in the table); versions of files, OnlyOffice, new file from a docx/xlsx template;
on-the-fly unique check (the core checks on save); favorites, theme, switching the user, help, AI proxy, special functions of the creator; print (`linkToPrint`, printTable).
Buttons moved by the table format to places of web (see the marks `[B] [E] [P] [D]`) are the usual params / footer buttons for the agent.
The panel of buttons of the user menu (`settings.h_user_settings_buttons`, `loadUserButtons`) is not in the agent interface.
Admin interface (tree, tables, fields, codes) — the creator view, see [CREATOR.md](CREATOR.md).

### Documentation (search_docs, read_docs) — `MCPDocs`
Both tools are in every mode (annotation `readOnlyHint`); they read the markdown of `docs/` on the server (see [docs/DOCS.md](docs/DOCS.md)), the agent needs no internet requests.
```json
{"name": "search_docs", "arguments": {"q": "только при добавлении"}}
{"name": "search_docs", "arguments": {"q": "selectList order", "section": "totum", "limit": 5}}
{"name": "read_docs", "arguments": {}}
{"name": "read_docs", "arguments": {"ref": "functions#fn-select"}}
{"name": "read_docs", "arguments": {"function": "listSum"}}
{"name": "read_docs", "arguments": {"setting": "codeOnlyInAdd"}}
{"name": "read_docs", "arguments": {"table_setting": "deleting"}}
{"name": "read_docs", "arguments": {"field_type": "select"}}
{"name": "read_docs", "arguments": {"ref": "syntax", "offset": 419}}
{"name": "read_docs", "arguments": {"ref": "ARCHITECTURE#fix-lines"}}
```
- Pages: `docs/<lang>/<section>/<page>.md` (sections `totum`, `training-course`, `how-to`), `lang` — argument / language of Totum (`Conf::getLang()`), `ru` if there are no docs of it.
- Root pages `MCPDocs::ROOT_PAGES` — `docs/<NAME>.md`, common for all languages (section `''`): `ARCHITECTURE` (architectural patterns of codes, required reading before development).
  ref `ARCHITECTURE` / `ARCHITECTURE#<anchor>` (case-insensitive); the index (`read_docs` without arguments) lists them in `## Root pages`; `search_docs` does not search them.
  A new root page — a file in `docs/` + its name in `ROOT_PAGES`.
- A section = a heading (anchor `<a name="…">` in it) with its text up to the next heading of the same or a higher level; headings in ``` / ~~~ blocks are not headings.
- ref: `page`, `page#anchor`, `section/page#anchor` (like `links.md`, `/page/#anchor` and `.md` / `ru/` prefix are accepted); a heading without anchor — `page#Title`; anchors are case-insensitive.
  Short forms: `function` → `functions#fn-<name>`, `setting` → `fields#fields-settings-<name>`, `field_type` → `fields#type-<type>`, `table_setting` → `tables#table-settings-<name>`.
- `search_docs`: words of 2+ letters, parts of words, case and ё/е insensitive; a section must have all the words (none — sections with some of them, with a note);
  score: word in the heading/anchor 10, in the own text of the section 1 per occurrence (max 5), the whole query equal to the title or to `fn-` / `fields-settings-` / `table-settings-` / `type-<q>` +100.
  Answer `| ref | section (path of headings) | text (fragment around the first word) |`, `more: N` above `limit` (default 10, max 30).
  Without `section` the reference (`totum`) goes first: the table has only its hits (`limit` is for them), the header counts by sections `(totum 9, training-course 41)`,
  every other section with hits is a block after it — `## more in training-course: 41 — search_docs {"q":…,"section":"training-course"}` with its best 3 (`MCPDocs::OTHER_SECTION_LIMIT`).
  No hits in the reference — the table is of all the sections, as with `section`.
- `read_docs`: numbered links `[text][N]` → `text (→ ref)` by `docs/links.md` (not in ``` / ~~~ blocks and `inline code`: `$list[0][1]` is a code); a text over 15000 chars is paged by lines (`next page: … "offset": N`),
  its first page lists the subsections (a whole page with one `#` title — its chapters); not found — similar pages / anchors (`selectLst` → `fn-selectList`).
  Without arguments — `DOCS.md` + the list of pages of every section (title = the first heading).
- Only files inside `docs/` (names `[A-Za-z0-9_-]`, `realpath` check). Parsed pages are cached in the process by mtime (stdio is long-living).
- Hints: `get_field_settings` → `read_docs` of settings / type / functions; `## Creator view` of `get_table_schema` → `table_setting`; instructions of the server and of the creator view.
- Links of the docs are consistent (2026-11-23): every `[N]` of the texts is defined in `links.md`, every target page/anchor exists, anchors are unique in a page,
  anchors `fields-settings-<name>` / `table-settings-<name>` are the names of the settings (data_src / fields of `tables`). Removed the links of the old docs
  (`/install…`, `/ubuntu/#fpm|#nginx`, technical functions `fn-dateWeekDay`, `fn-getFromSoap`, `fn-getTableSource`, `fn-setForm…Format`, `fn-schemaGzString…`, `fn-servicePdfGenerator`,
  `fn-serviceAskOpenai…` — not documented, better not to use them; `fn-setFieldParams` is documented since 2026-11-25); `interface#part-N` → the page / `sx-section-parameters`; `[24]` → `functions#part-9`.
  A broken ref is answered by `read_docs` with similar anchors.
- Check of the links after changes of the docs and the rules of `links.md` — section "Editing" of [docs/DOCS.md](docs/DOCS.md).

### Files (file fields, linkToFileUpload, linkToFileDownload) — `MCPFiles`
The answer of a tool goes only into the context of the model: bytes of files never go through it (except small inline files and pictures on request).
- **stdio** (the server is on the disk of the agent): the server saves / reads files by local paths (any absolute path: shell access = full access).
  Default folder of the agent: `totumTmpfiles/mcp/<schema>/<user id>/`.
- **HTTP**: links with a token for curl, without the password (the password is in the config of the MCP client, the model does not know it):
  `GET /MCP/file/<token>` — download (15 min, `_tmp_tables` `_mcp_file_links`: path/name/mime after the access check of the tool),
  `PUT|POST /MCP/upload/<token>` — upload (raw body `curl -T` or multipart `-F file=@…`; token of `upload_file`, 60 min, one file; `_mcp_uploads` → tmp file of the user).
  `MCPController` routes `/MCP/file|upload/…` before the Basic auth; `MCPServer`/`MCPTools` get `baseUrl` (null = stdio). nginx `client_max_body_size` limits uploads (413).
```json
{"name": "get_file",    "arguments": {"table": "test_agents_table", "field": "multifile", "id": 29, "save_to": "/tmp/files/"}}
{"name": "get_file",    "arguments": {"table": "test_agents_table", "field": "file_image", "id": 29, "image": "preview"}}
{"name": "upload_file", "arguments": {"name": "report.pdf"}}                                                   (HTTP only, not listed in stdio)
{"name": "edit",        "arguments": {"table": "test_agents_table", "rows": {"9": {"multifile": [{"file": "60_9_multifile_….txt"}, {"path": "/tmp/a.pdf"}]}}}}
{"name": "submit_file_upload", "arguments": {"table": "test_agents_table", "hash": "081e…", "files": [{"path": "/tmp/a.pdf"}]}}
```
- The answers say where the bytes are (`MCPFiles::deliverHint`, `upload_file` in 2 steps): HTTP — the agent runs the curl commands in its terminal on its machine,
  the file is saved where `-o` points (default — the current folder of its shell); upload — 1) `curl -T ./file <link>` → upload id (tmp storage of the server, not in a field),
  2) `{"upload": "<id>"}` in the value of the field / `files` of submit_file_upload — only then the file is in the field. stdio — paths on the disk of the server (= the disk of the agent).
- Reading: tables/cards show names; `get_value` / `get_cell` list `name · size · mime · file \`…\` · image` (`MCPMarkdown::filesList`). Schema type: `file secure versioned accept .pdf multiple`.
- `get_file` (field, id, `file` — one file of the cell, without it — all): access like web (`getValue`: visible field, row of the user — also for secure files,
  which web gives only via `checkIsSecureFileRequest`); the file must be in the cell. stdio → `saved to \`path\`` (`save_to` — file or folder ending with `/`; several files → folder),
  HTTP → `download: \`curl -sS -o 'name' 'https://host/MCP/file/<token>'\`` (+ public `/fls/…` for not secure fields; `save_to` → error).
  `image: "preview"` (`_thumb.jpg` of the core, 290 px) / `"full"` (≤ 5 MB) → MCP `image` content after the text; only images (jpg, png, gif, webp); by default no pictures.
- Writing (`edit`, `insert_row`, `add_row`, `edit_panel`, `submit_edit` — `MCPTools::fileValues` → `MCPFiles::fieldValue`): the value is the whole list like the form of web —
  `{"file": "<file>"}` keeps a file of the cell (`name` renames it; a file not in the cell → error), new files: `{"path"}` (stdio only), `{"upload"}` (id of upload_file, user-bound),
  `{"name", "text"}` / `{"name", "base64"}` (≤ 1 MB); `[]` — no files; an object instead of a list = one file. New files become tmp files of the user
  `{name, tmpfile}` (`File::createUserTmpFile`: like `tmpFileUpload` — image extension by mime, preview), the core copies them on commit and deletes the dropped files.
  Checks of the web form (the core does not do them): one file for a not multiple field, `accept` (`.ext`, `mime/type`, `type/*` — `MCPFiles::accepts`), size ≤ `Conf::$MaxFileSizeMb`.
  Raw `tmpfile` / file names of other cells are never passed to the core from the agent.
- `linkToFileUpload(title; code; limit; type; refresh)`: the core saves only the code (`_linkToFileUpload`); limit/type go to the browser —
  MCP keeps them (`_mcp_linktofileupload`) when the window is opened:
  ```
  ## Opened window (file upload): Загрузка файлов
  files: up to 2 · types: .pdf
  upload with submit_file_upload `{"table":"test_agents_table","hash":"081e…","files":[{"path":"/abs/path/file"}]}` — … The hash works once.
  refresh: true — …
  ```
  `submit_file_upload` checks the limit (`Upload limit exceeded`) and the type (`*` — any), sends `[{name, base64}]` to `filesUpload` (the code gets `$#input` = `[{name, filestring}]`),
  answer — changes + refresh line + `**The file upload window is closed**`; second use → `ERROR: The proposed input is outdated…`.
  Test: `h_linktofileupload` of `test_agents_table` writes the files to `fayl` of row 9 (Лондон), limit 2, `.pdf`.
- `linkToFileDownload` (interface data `files` with base64 strings): `## Downloaded files` — stdio: saved to the default folder (`name (1).ext` if it exists), HTTP: links.
- Test data: row 29 (Москва) has all file fields filled (`fayl` open `.pdf`, `secure_file`, `multifile` ×2, `file_image` png with preview).
- Cleanup (crons of the install, every 10 min): tmp files of uploads / HTTP downloads of linkToFileDownload (`tmpLoadedFiles/<schema>.<user>.…`) — `clean-tmp-dir`, 2 h by atime;
  tokens in `_tmp_tables` (`_mcp_…`) — `clean-schemas-tmp-tables`, 2 h after the last touch; folders of the agents `totumTmpfiles/mcp/` — `clean-tmp-dir`
  (`MCPFiles::cleanAgentsDir`): files older than 24 h by mtime (`AGENTS_DIR_TTL`), empty folders. Field files of `get_file` over HTTP are not copied (the link points to the storage).
- Versioned fields (versions, version comment, replace the last version): not supported yet — only the list and a plain replacement of files.

## Output reference
All service text of the output is in English. Table titles, field titles, data and error messages of the Totum core are in the language of the schema.

### Header
```
# Orders · orders · id 12 · simple · cycle 15 (projects) · sess_hash …
updated 2026-11-20 10:00:00 12345 · code 1621071962 · table: edit add delete restore duplicate reorder
order: manual (n-field) · can change: reorder_rows (…)
hidden from the tree: … do not open it directly           (only tables hidden from the tree)
```
- title · name · id · type; `cycle` — for calcs, `sess_hash` — for tmp;
- `updated … · code N` — when the table was changed; pass `code` as `updated` when writing;
- `table:` — what the agent is allowed to do: `edit add delete restore duplicate reorder`; `read-only` — read only; `#blockadd`, `#blockdelete`, `#block` … — restrictions from the table format;
- `order:` — `manual (n-field)` with `can change: reorder_rows` / `cannot change`; or `by field X` — sorted by field X; `descending` — reverse order.

### Sections
| heading | meaning |
|---|---|
| `## Params` | params (header fields) |
| `## Filters` | filters |
| `## Columns` | columns (schema) |
| `## Rows 1–50 of 340` | page of rows |
| `## Footers` | footers |
| `## Description` | table description (`get_table_schema`, first page of `read_table`) |
| `## Table text` | tabletext + tablehtml of the table format (`read_table`; in answers to writes — `(changed)` only) |
| `rows comment:` | tablecomment of the table format, line under `## Rows` (in answers to writes — `(changed)` only) |
| `## Cell: …` | one cell (`get_cell`) |
| `## Side effects` | side effects |

### Params, filters, footers — as a card
```
| Title | name | type | rights | value |
| Client #info | client | select→clients | edit | ACME Ltd [12] |
```

### Rows — 4-line header
```
| id         | Name     | Status #info | Amount #icon-usd |
| name       | name     | status       | amount           |
| type       | string   | select       | number(2)        |
| access     | edit add | edit add     | edit calc        |
| 101        | Order 1  | New [new]    | 200 ✋(150)      |
| 103 #block | Long tex… |             | 0 #block         |
```
Line 1 — field titles, line 2 (`name`) — names for requests, line 3 (`type`) — short field type, line 4 (`access`) — field rights, then data.

### Field rights
| mark | meaning |
|---|---|
| `edit` | can be changed (`edit`; in the panel card — `edit_panel`) |
| `add` | can be set when adding a row (`add_row`, `insert_row`) |
| `calc` | value is calculated by code; writing makes it manual |
| `calc-on-add` | code runs only when a row is added |
| `click` | button / click action (`click_button`); in the insert row card — button active in the insert row |
| `add-click` | button is active in the insert row too (`buttonActiveOnInsert`): `click_button` with `insert_hash` |
| `one-row` | (`get_table_schema`) batch edit is off: change the field in one row per call |
| `multi-field` | (`get_table_schema`) can be changed together with other fields in one call (others — better alone, warning) |
| `panel` | (`get_table_schema`) the cell has a context panel: `get_cell`, its buttons — `click_panel_button` |
| `context-panel` | (`get_table_schema`) the button opens the context panel of its cell after pressing |
| `checked-rows` | (`get_table_schema`) the button code uses the checked rows (`$#ids`): pass `checked_ids` |
| `log` | (`get_table_schema`) log of manual changes is available (`get_cell`) |
| `cycle-tab` | (`get_table_schema`) tab_ button of a cycles table: a tab inside the cycle, `click_button` with `id` = cycle |
| `field-params` | (`get_table_schema`) the format of the field sets the type of its cells (`setFieldParams`): see `#fieldParams(…)` of the cells, `get_cell` |
| `required` | required field |
| `—` | view only |

### Marks in titles
| mark | meaning |
|---|---|
| `{N pcs}` | how the value is shown in web: `N` — the raw value (write it as is, without unit and separators), unit after `N` or before it (`{$ N}`) |
| `{N ₽ / −: (N ₽)}` | money format: positive / negative variant (prefix/postfix with `**`); separators are listed in `get_table_schema` as `money(thousands " ", decimal ",")` |
| `#info` | the field has a description — see `get_table_schema` |
| `#icon-name` | fontawesome icon of the field |
| `#hide` | the field is hidden by the table format (fieldhide) |
| `#style(panelColor: ##lightgray)` | panel color of the field (setting panelColor; only with `styles: true`) |
| `[B]` `[E]` `[P]` `[D]` | a params / footer button that web shows for users (not creators) in another place — setTableFormat `buttons` (bar above the table), `extbuttons` (☰ menu), `printbuttons` (print menu), `dotbuttons` (⋯ menu at the bottom right), like the marks of the admin interface (`MCPMarkdown::buttonPlace`, legend in `## Marks` of get_table_schema); for the agent — a usual button: `click_button` without id |

### Marks in values
| mark | meaning |
|---|---|
| `New [new]` | select/tree title and key in brackets (key is shown if it differs from the title); write the key |
| `A [a], B [b]` | multiple select |
| `#deleted` | the selected value is missing in the options list |
| `200 ✋(150)` | manual value that differs from the calculated one (calculated in brackets); text fields show just `✋` |
| `150 ✊` | set manually, but equal to the calculated one |
| `!ERR text` | field calculation error |
| `#block` | cell is blocked by format — cannot be changed |
| `#icon-name` | icon from the cell format |
| `#text:"…"` | format `text`: web shows this text instead of the value (the real value is before the mark; often the value is empty and the text is a hint like "Needs to be added") |
| `#textasvalue:"…"` | format `text` with `textasvalue`: web shows the text and uses it as the cell value (copy, filters) |
| `#comment:"…"` | format `comment`: hint to the cell (in web — info icon), e.g. what to do with an empty cell |
| `#placeholder:"…"` | hint for an empty value |
| `#fieldParams(number(3))` | `setFieldParams` of the format: web shows and edits the cell as this type (string, number, checkbox, select…) — write a value of it; params — `get_cell` |
| `#style(color: ##tomato; bold: true)` | visual format of the cell (only with `styles: true`, see "Styles") |
| `…` | text is cut (field setting "visible text length") — full value via `get_cell` / `get_value` / `get_row` |
| `⏎` | new line inside a value |
| `\|` | escaped vertical bar |
| `[TEXT]` | button (button text) |
| `(3, unread 1) date name: text (edited)` | comments: count (unread for the user — web underlines the cell), the last one, `(edited)` — changed by its author |
| `true` / `false` | checkbox |
| `***` | password (value is not shown) |

### Marks in row id
| mark | meaning |
|---|---|
| `#block` | the row is blocked for changes |
| `#blockdelete` / `#blockduplicate` / `#blockorder` | cannot be deleted / duplicated / moved |
| `#hide` | the row is hidden by the table format (setRowsHide/setRowsShow) |
| `#comment:"…"` | row comment |
| `#style(background: ##yellow)` | visual format of the row — setRowFormat (only with `styles: true`) |

### Answer to a write
```
**Saved**                 — or Added / Deleted / Restored / Duplicated / Order saved / Recalculated / Pressed: field / Panel button pressed
updated … · code …
added: 105                — also deleted: … / restored: … / reordered: …
## Params / ## Footers    — changed params / footer fields
## Rows (changed/added)   — changed/added rows: up to 3 as cards, more — as a table
changed fields — 101: revision; 102: a, b   — which fields of which rows were changed by the action and recalculation
more changed rows: …      — when more than 50 rows changed
no changes in the table
## Table text (changed)   — tabletext/tablehtml changed by the action ("(empty)" — removed)
rows comment (changed): … — tablecomment changed by the action
```
Rows changed by the action itself and by recalculation are shown (up to 50 rows in full).
`**dry_run: not saved**` — dry run, nothing saved.

### Side effects
Code actions (buttons, changes) that would open something in the web interface:
```
## Side effects
- link (iframe): Table fields → /Table/1/2?f=…
- text: Title (window — see "Opened window" below)
- files: report.xlsx (download — see "Downloaded files" below)
- input: Enter amount (window — see "Opened window" below)
- buttons: Choose (window — see "Opened window" below)
- file upload: Title (window — see "Opened window" below)
- script (POST, target blank): Title → https://example.com/hook.php
  web sends a POST form to it from the browser of the user; to call it run: `curl -sS -X POST '…' --data-urlencode 'id=5' --data-urlencode 'data[a][0]=1'`
- link (blank): Title → https://host/An/60 — a web page for a person (form / anonymous table): give the link to the user, the agent does not work with it
- buffer (proLinkToBuffer): web copies this text to the clipboard of the user — the text:  (```text block, up to 10000 chars)
- print: web prints a document (linkToPrint / print of the table) in the browser of the user — printing is not supported by the agent interface, the document is not shown
```
Links that are not tables (`MCPMarkdown::sideEffects`, host — `baseUrl` of HTTP or `https://<getFullHostName>` of stdio, relative uri → absolute):
- `/Forms/…` and `/<getAnonymModul()>/…` without postData (linkToForm / linkToQuickForm / linkToAnonymTable with target) → link for the user;
- other (linkToScript): `script` with a ready curl command; postData → `-X POST` + `--data-urlencode` fields like the form of the browser (`name[key]`, booleans `true`/`false`);
  a page of this Totum → the note that the browser sends it with the session of the user (curl has none); `refresh` line.
`table` (linkToDataTable), `text` / `html` (linkToDataText / linkToDataHtml), `json` (linkToDataJson), `input` (linkToInput / linkToInputSelect), `edit` (linkToEdit), `buttons` (linkToButtons), `panel` (linkToPanel), `file upload` (linkToFileUpload) and `files` (linkToFileDownload) are opened for the agent — see "Windows with …", "Input windows", "Edit windows", "Buttons windows", "Edit panel", "Files". `print` (linkToPrint, printTable) is not supported — only the line.

### Notifications (get_notifications / notification_update)
Like the popups of web (`Actions::checkForNotifications`): table `notifications` (title, user_id, code → `notification_codes`, vars, active, active_dt_from, prioritet).
```json
{"name": "get_notifications",   "arguments": {}}
{"name": "notification_update", "arguments": {"ids": [13], "action": "deactivate"}}
{"name": "notification_update", "arguments": {"ids": [14], "action": "later", "num": 1, "unit": "hours"}}
{"name": "notification_update", "arguments": {"all": true, "action": "deactivate"}}
```
- `get_notifications` (`limit`, default 20): active notifications of the user whose `active_dt_from` has come, by `prioritet`, id; `postponed: N (the next one at …)`.
  The code of every notification runs like web (`execAction` with the row and `vars`); its interface data (text / html / table) is rendered by `openedWindows`
  under `## Notification <id>: <title> · since … · priority …` (tool flag `ownWindows`: `call()` does not add side effects / windows again; links / panels of the code are ignored like web).
- They stay until closed (web shows them again on every page): `notification_update` = the buttons of the popup — core `Actions::notificationUpdate` (Actions without a table):
  `deactivate` → `active = false`; `later` → `active_dt_from = now + num unit` (minutes / hours / days, default 5 minutes); `all` = `ALL_ACTIVE`. Not own / missing ids → error.
- Times are in the timezone of the schema (Totum sets it; plain php CLI is UTC).

### Search of the system (search / search_click) — `MCPSearch`
The search button of the top panel of web (`__header.php`: shown when `ttm__search_settings` has MeiliSearch `h_get_updates` or TRGM `h_use_trgm_search` on; docs `meilisearch`):
```json
{"name": "search",       "arguments": {"q": "monaco"}}
{"name": "search",       "arguments": {"q": "bridge", "catalogs": ["test"]}}
{"name": "search_click", "arguments": {"pk": "71-4"}}
{"name": "search_click", "arguments": {"pk": "71-2", "button": "info"}}
```
- `search` = `Actions::getSearchResults` (post `q`, `cats`; engine MeiliSearch first like the core): the tables of `ttm__search_settings` available for the user, rows he sees in web,
  at most `h_search_limit` (default 20). Answer: `# Search: "q" · TRGM · N result(s)`, the catalogs (`ttm__search_catalog`, `searchCatalog`), the table
  `| pk | table · row | catalog | title | text (**found**) | buttons |` — the markers of the engine → `**word**` (MeiliSearch text is html-escaped — decoded), text cut to 500.
  Unknown catalog → error with the list; the search off / no `ttm__search_settings` → error (web has no search button).
- `search_click` = `Actions::searchClick` (post `pk`, `button`): the code of the card (column `code` of the row of the table in `ttm__search_settings`) or of the button
  (`buttons` = `[{name, title, code}]`) runs in the context of the row of the result. The core does nothing silently on a wrong click — MCP checks before: pk `tableId-rowId`,
  the table in the access of the user and in the search, the row visible (`loadFilteredRows web`), the button exists, the code is not empty.
  Answer `# Search result 71-4 · table · row 4` + `**Opened: the card …**` / `**Pressed: button Info [info] of the card**`, changed tables, then side effects and opened windows like
  `click_button`; the windows of the code are submitted in the table of the result (`MCPSearch::tableArgs`). Creator view: `debug` (tool flag `'debug' => true` — the debug prop without `table`).
- Core fix: TRGM search with catalogs passed the parameters in the wrong order (catalogs instead of `q` in `WHERE`) — `Actions::getSearchResults`.
- Test: `mcp_test_search` (`ttm_search` = index/title/catalog `test`), row in `ttm__search_settings` (code `linkToPanel`, buttons `info` — linkToDataText, `open` — linkToTable), TRGM on.

### Errors
Text `ERROR: …` with `isError: true` — the action is not done, the transaction is rolled back completely. HTTP authorization errors — status 401.

## Verification
- Lint: `for f in totum/moduls/MCP/*.php totum/commands/MCPServe.php; do php -l $f; done`
- Class loading: `php -r 'require "vendor/autoload.php"; var_dump(class_exists("totum\\moduls\\MCP\\MCPTools"));'`
- HTTP smoke: `curl -sk -X POST -H "Host: <host>" -H 'Content-Type: application/json' -d '{}' https://127.0.0.1/MCP/` → 401
- Note: `Sql::transactionRollBack()` terminates the db backend — use a new config (`getClearConf()`) after a rollback.
- Note: stdio is a long-living process — process-wide static caches bound to Totum/Sql must be reset on every call (`MCPServer::freshConfig()`: `tmpTable::clearInstances()`). Otherwise a window call works through the Totum of the call that opened it: writes go over the old committed connection, `tableUpdates` to GOM are not sent (web gets no "table was changed" notice).
- Note: Select caches its view list (titles of values) in the Field object per request (web formats once per request). MCP formats before and after a change in one call → `MCPActionsTrait::mcpResetSelectViewLists()` before formatting (otherwise new values are shown as `#deleted`).
- Quick stdio call for tests: `printf '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"insert_row","arguments":{"table":"<table>"}}}\n' | php bin/totum mcp --user=agent`. The MCP server of the IDE session is a long-living stdio process: restart it after changes of the code.
- HTTP mode of files without the password of the agent: a harness with `new MCPServer($Conf, fn($C) => MCPAuth::byLogin($C, 'agent'), true, 'https://<host>')`
  (links / upload_file), then `curl -sk -H 'Host: <host>' https://127.0.0.1/MCP/file/<token>` / `curl -sk -T file … /MCP/upload/<token>`.
- GOM notifications can be checked with a debug subclass of `Conf` overriding `proGoModuleSocketSend` (log `method = tableUpdates`) and `getBaseDir()` (returns the totum dir, it is used for the socket path).

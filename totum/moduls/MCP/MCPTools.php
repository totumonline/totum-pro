<?php

namespace totum\moduls\MCP;

use GuzzleHttp\Psr7\ServerRequest;
use totum\common\calculates\CalculateAction;
use totum\common\criticalErrorException;
use totum\common\DryRunStopException;
use totum\common\errorException;
use totum\common\Model;
use totum\common\Totum;
use totum\common\User;
use totum\config\Conf;
use totum\fieldTypes\File;
use totum\models\Table;
use totum\moduls\Table\Actions;

/**
 * Registry of MCP tools and their handlers over MCPRead/MCPWriteTableActions
 */
class MCPTools
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 500;

    protected const TABLE_PROPS = [
        'table' => ['type' => 'string', 'description' => 'Table id or name'],
        'cycle' => ['type' => 'integer', 'description' => 'Cycle id — required for tables of type calcs'],
        'sess_hash' => ['type' => 'string', 'description' => 'For tmp tables: sess_hash from the previous answer'],
        'filters' => ['type' => ['object', 'string'],
            'description' => 'Filter values {filter_name: value} or the crypted filters string given by an opened window. Stateless — pass in every call'],
    ];
    protected const WRITE_PROPS = [
        'updated' => ['type' => 'string', 'description' => 'Optional optimistic lock: "code" of updated from the previous answer. Error if the table was changed since then'],
    ];
    protected const INSERT_HASH = ['type' => 'string', 'description' => 'Hash (i-…) of the insert row from insert_row'];
    protected const IDS = ['type' => 'array', 'items' => ['type' => 'integer']];
    protected const NAMES = ['type' => 'array', 'items' => ['type' => 'string']];
    protected const STYLES = ['type' => 'boolean', 'description' => 'Also the visual formats of web (not needed usually): #style(color: ##tomato; bold: true; …) '
        . 'of cells and rows (setFormat / setRowFormat), panelColor of fields in titles, section "## Styles" — interlace of the table and the user colors ##name (ttm__colors)'];
    /* values of file fields in writes */
    protected const FILE_VALUES = 'File fields — the list of files of the cell: {"file": "<file from get_value>"} keeps a file, '
        . 'new files — {"path": "/abs/path"} (stdio server: its disk), {"upload": "<id of upload_file>"} (HTTP server), '
        . '{"name": "a.txt", "text": "…"} / {"name": …, "base64": "…"} (small files); [] — no files.';

    protected MCPFiles $Files;
    protected MCPCreator $Creator;
    protected bool $rolledBack = false;
    protected bool $failed = false;
    /** @var array MCP content after the text of the answer (images of get_file) */
    protected array $content = [];

    /**
     * @param string|null $baseUrl https://host of the HTTP server; null — stdio
     * @param bool $creator creator view: tools with 'creator' => true, creator section of get_table_schema
     */
    public function __construct(protected ?string $baseUrl = null, protected bool $creator = false)
    {
    }

    public function extraContent(): array
    {
        return $this->content;
    }

    protected function tools(): array
    {
        return [
            'list_tables' => [
                'description' => 'List of tables available to the agent: id, name, title, type, branch, access (rw/ro).',
                'props' => ['q' => ['type' => 'string', 'description' => 'Search words in name/title']],
                'required' => [],
            ],
            'get_table_schema' => [
                'description' => 'Table structure: fields by category (params, filters, columns, footers) with name, type, rights (edit/add/calc/click/required) and description (#info).',
                'props' => static::TABLE_PROPS,
                'required' => ['table'],
            ],
            'read_table' => [
                'description' => 'Read table like opening it in web: description (first page), table text, params, filters, rows page (with rows comment), footers. Cells: "Title [key]" select key, ✋(x) manual differs from calculated x, ✊ manual equal to calculated, !ERR error, #block, …=cut text. '
                    . 'Tables with the tree view (header line "view: tree"): the rows in the order of the open tree and the section "## Tree" with the nodes; '
                    . 'not loaded branches (web loads them when they are opened) — branch.',
                'props' => static::TABLE_PROPS + [
                        'branch' => ['type' => ['string', 'array'], 'items' => ['type' => 'string'],
                            'description' => 'Tree view: open this node (key from "## Tree") like a click on a folder in web — its rows and subnodes; several — a list'],
                        'recursive' => ['type' => 'boolean', 'description' => 'Tree view with branch: with all the branches inside ("Open all" of the folder)'],
                        'ids' => static::IDS + ['description' => 'Only these row ids'],
                        'fields' => static::NAMES + ['description' => 'Only these fields (any category)'],
                        'offset' => ['type' => 'integer', 'description' => 'Rows offset, default 0'],
                        'limit' => ['type' => 'integer', 'description' => 'Rows on page, default ' . static::DEFAULT_LIMIT . ', max ' . static::MAX_LIMIT],
                        'recalculate' => ['type' => 'boolean', 'description' => 'Recalculate changed like web page opening, default true'],
                        'deleted' => ['type' => 'boolean', 'description' => 'Show deleted rows (for restore_rows)'],
                        'styles' => static::STYLES,
                    ],
                'required' => ['table'],
            ],
            'get_row' => [
                'description' => 'One row with full (not cut) values as a card.',
                'props' => static::TABLE_PROPS + ['id' => ['type' => 'integer'], 'fields' => static::NAMES, 'styles' => static::STYLES],
                'required' => ['table', 'id'],
            ],
            'get_value' => [
                'description' => 'Full value of one field: long text, all comments, list of files.',
                'props' => static::TABLE_PROPS + ['field' => ['type' => 'string'], 'id' => ['type' => 'integer', 'description' => 'Row id for column fields']],
                'required' => ['table', 'field'],
            ],
            'get_cell' => [
                'description' => 'Everything about one cell like the right-click panel in web: field title/type/rights, full (uncut) value, '
                    . 'manual/calculated value, format marks, style (visual format: color, bold, background…; ##name — user colors), field description, panel sections (text/html/img/buttons — click_panel_button), select previews, '
                    . 'log of manual changes (fields with the mark log in get_table_schema; without changes — a message).',
                'props' => static::TABLE_PROPS + [
                        'field' => ['type' => 'string'],
                        'id' => ['type' => 'integer', 'description' => 'Row id for column fields; omit for params/footers'],
                        'log' => ['type' => 'integer', 'description' => 'How many last log entries to show, default 20, 0 — no log'],
                    ],
                'required' => ['table', 'field'],
            ],
            'click_panel_button' => [
                'description' => 'Press a button from the panel of a cell (get_cell, section "Panel"; also the context panel opened by click_button): pass hash and index from there. '
                    . 'Side effects and opened windows — like click_button; refresh of the button — re-read the table after it.',
                'props' => static::TABLE_PROPS + [
                        'hash' => ['type' => 'string'],
                        'index' => ['type' => 'string'],
                    ],
                'required' => ['table', 'hash', 'index'],
            ],
            'submit_json_window' => [
                'description' => 'Press the button of a JSON window (linkToDataJson with code, see "Opened window (json)"): '
                    . 'pass table args and hash from there and the (edited) JSON — the code of the button gets it as $#value. The hash works once.',
                'props' => static::TABLE_PROPS + [
                        'hash' => ['type' => 'string'],
                        'json' => ['description' => 'JSON value (object/array/…), not a string with JSON'],
                    ],
                'required' => ['table', 'hash', 'json'],
            ],
            'submit_buttons' => [
                'description' => 'Press one button of a buttons window (linkToButtons, see "Opened window (buttons)"): pass table args and hash from there '
                    . 'and the index of the button. The code of the button runs; the window is closed after any button (like web) — do not press it again with this hash.',
                'props' => static::TABLE_PROPS + [
                        'hash' => ['type' => 'string'],
                        'index' => ['type' => 'integer', 'description' => 'Index of the button (column index of the window)'],
                    ],
                'required' => ['table', 'hash', 'index'],
            ],
            'submit_input' => [
                'description' => 'Press the button of an input window (linkToInput / linkToInputSelect, see "Opened window (input)"): '
                    . 'pass table args and hash from there and the value — the code of the button gets it as $#input. The hash works once.',
                'props' => static::TABLE_PROPS + [
                        'hash' => ['type' => 'string'],
                        'value' => ['description' => 'Text input / html form — string (the text of its field ttmInput, e.g. JSON); '
                            . 'select — key, multiple — array of keys'],
                    ],
                'required' => ['table', 'hash', 'value'],
            ],
            'submit_edit' => [
                'description' => 'Save the value of an edit window (linkToEdit, see "Opened window (edit)"): pass table args and hash from there and the value '
                    . '(like edit: select — key, multiple — array, date "Y-m-d", checkbox true/false, comments — text of a new comment, listRow — JSON). '
                    . 'special "reset" — return the calculated value, "pin" — fix the calculated value as manual (fields with code). ' . static::FILE_VALUES,
                'props' => static::TABLE_PROPS + [
                        'hash' => ['type' => 'string'],
                        'value' => ['description' => 'New value of the field'],
                        'special' => ['type' => 'string', 'enum' => ['reset', 'pin'], 'description' => 'Instead of value: reset / pin (fields with code)'],
                    ],
                'required' => ['table', 'hash'],
            ],
            'get_file' => [
                'description' => 'Files of a file field (the access is checked like web, also for secure files): one file — "file" from get_value, '
                    . 'without it — all files of the cell. HTTP server (you work on another machine): the answer has a curl command — run it in your terminal, '
                    . 'the file is saved on your machine where -o points (default — the current folder of your shell); the link works 15 min. '
                    . 'stdio server (the server is your machine): the file is copied on its disk (save_to — a file or a folder, default — the folder of the agent). '
                    . 'image "preview" / "full" — the picture itself in the answer: only when you need to see it.',
                'props' => static::TABLE_PROPS + [
                        'field' => ['type' => 'string'],
                        'id' => ['type' => 'integer', 'description' => 'Row id for column fields; omit for params/footers'],
                        'file' => ['type' => 'string', 'description' => '"file" of one file of the cell (get_value); without it — all files'],
                        'save_to' => ['type' => 'string', 'description' => 'stdio server only: absolute path of a file or a folder (ending with /)'],
                        'image' => ['type' => 'string', 'enum' => ['preview', 'full'], 'description' => 'An image file: show its preview (small) or the full picture'],
                    ],
                'required' => ['table', 'field'],
            ],
            'upload_file' => [
                'description' => 'Step 1 of sending a file of your machine to a file field: gives a curl command — run it in your terminal, '
                    . 'the file goes to the temporary storage of the server and gets an upload id. Step 2: pass {"upload": "<id>"} as a file of the value of the field '
                    . '(edit / insert_row / add_row / edit_panel / submit_edit) or in files of submit_file_upload — only then the file is in the field.',
                'props' => ['name' => ['type' => 'string', 'description' => 'File name with extension, e.g. "report.pdf"']],
                'required' => ['name'],
                'http' => true,
            ],
            'submit_file_upload' => [
                'description' => 'Upload files to a file upload window (linkToFileUpload, see "Opened window (file upload)"): pass table args and hash from there '
                    . 'and the files — the code of the button gets them as $#input. Limit and types of the window are checked. The hash works once.',
                'props' => static::TABLE_PROPS + [
                        'hash' => ['type' => 'string'],
                        'files' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Files: {"path"} (stdio), {"upload"} (upload_file), '
                            . '{"name", "text"|"base64"} (small)'],
                    ],
                'required' => ['table', 'hash', 'files'],
            ],
            'export_table' => [
                'description' => 'Export the table to a file like the buttons of web (with the filters of the call, rows in the order of web): '
                    . 'csv — "CSV-export" (type full: params, filters, rows, footers in the format of the csv import of web; rows — only rows; '
                    . 'needs the csv right of the role), xlsx — Excel export of web (rows of the visible columns as they are shown, the first line — titles; '
                    . 'only if the service xlsx is switched on for the table). The file is given like get_file (stdio — saved on the disk, HTTP — a curl link). '
                    . 'Import of csv / xlsx of web is not supported by the agent interface: change the data with edit / add_row.',
                'props' => static::TABLE_PROPS + [
                        'format' => ['type' => 'string', 'enum' => ['csv', 'xlsx']],
                        'type' => ['type' => 'string', 'enum' => ['full', 'rows'], 'description' => 'csv: full (default) or only rows'],
                        'fields' => static::NAMES + ['description' => 'Only these fields (default — all visible)'],
                        'ids' => static::IDS + ['description' => 'Only these rows (default — all rows of the filters)'],
                        'with_id' => ['type' => 'boolean', 'description' => 'xlsx: the first column is the row id'],
                    ],
                'required' => ['table', 'format'],
            ],
            'mark_comments_read' => [
                'description' => 'Mark the comments of comments fields as read by you, like web after opening them (web underlines the cells with unread comments; '
                    . 'tables show them as "(N, unread M)"). get_value / get_cell of a comments field show the unread comments (#unread) and mark them as read too.',
                'props' => static::TABLE_PROPS + [
                        'field' => ['type' => 'string'],
                        'ids' => static::IDS + ['description' => 'Rows for a column field; omit for params/footers'],
                    ],
                'required' => ['table', 'field'],
            ],
            'get_notifications' => [
                'description' => 'Notifications of your user like the popups of web: active notifications whose time has come (by priority) with their content — '
                    . 'the code of a notification runs like in web (text / html / a window with a table to work in). Tasks may come as notifications: '
                    . 'check them, and close a handled one with notification_update (deactivate) or postpone it (later) — otherwise it stays here.',
                'props' => ['limit' => ['type' => 'integer', 'description' => 'How many notifications to show, default 20, max 100']],
                'required' => [],
                'ownWindows' => true,
            ],
            'notification_update' => [
                'description' => 'Like the buttons of a notification popup of web: deactivate — close it (it is not shown any more), '
                    . 'later — postpone it (shown again after num minutes/hours/days, default 5 minutes).',
                'props' => [
                    'ids' => static::IDS + ['description' => 'Notification ids from get_notifications'],
                    'all' => ['type' => 'boolean', 'description' => 'All active notifications instead of ids'],
                    'action' => ['type' => 'string', 'enum' => ['deactivate', 'later']],
                    'num' => ['type' => 'integer', 'description' => 'later: how many units, default 5'],
                    'unit' => ['type' => 'string', 'enum' => ['minutes', 'hours', 'days'], 'description' => 'later: default minutes'],
                ],
                'required' => ['action'],
            ],
            'search' => [
                'description' => 'Search through the system like the search button of the top panel of web (set in ttm__search_settings: MeiliSearch or TRGM): '
                    . 'finds rows of the tables of the search by the words of their search index — the results are cards (title, text with the **found** words, catalog). '
                    . 'Like web, open a result with search_click: the code of the card runs (it opens the table / panel of the row or does an action); '
                    . 'cards may have extra buttons — search_click with button. Error if the search is switched off (web has no search button).',
                'props' => [
                    'q' => ['type' => 'string', 'description' => 'Words to search'],
                    'catalogs' => static::NAMES + ['description' => 'Only these catalogs (names from the answer, ttm__search_catalog)'],
                ],
                'required' => ['q'],
            ],
            'search_click' => [
                'description' => 'Click on a result of search like on its card in web: runs the code of the card (code of the table in ttm__search_settings) '
                    . 'in the context of the row of the result, or with button — the code of that button of the card. '
                    . 'Side effects and opened windows (the table / panel of the row…) — like click_button: continue in them with the args they give.',
                'props' => [
                    'pk' => ['type' => 'string', 'description' => 'pk of the result from search: "<table id>-<row id>"'],
                    'button' => ['type' => 'string', 'description' => 'name of a button of the card (column buttons of search); omit — the click on the card'],
                ],
                'required' => ['pk'],
                'debug' => true,
            ],
            'get_select_options' => [
                'description' => 'Options of select/tree field: key and title. Write the key. '
                    . 'Also a Data field whose cell is a select by setFieldParams (mark #fieldParams(select…)): field + id of the row.',
                'props' => static::TABLE_PROPS + [
                        'field' => ['type' => 'string'],
                        'id' => ['type' => 'integer', 'description' => 'Row id (column field of existing row)'],
                        'row' => ['type' => 'object', 'description' => 'Values of a new row {field: value} (column field when adding)'],
                        'insert_hash' => static::INSERT_HASH + ['description' => 'Options for the current state of the insert row (insert_row): set the fields with insert_row first'],
                        'input_hash' => ['type' => 'string', 'description' => 'Options of the select of an input window (linkToInputSelect): hash from "Opened window (input)", without field'],
                        'edit_hash' => ['type' => 'string', 'description' => 'Options of the select/tree of an edit window (linkToEdit): hash from "Opened window (edit)", without field'],
                        'panel_hash' => ['type' => 'string', 'description' => 'Options for the current state of the edit panel (edit_panel)'],
                        'q' => ['type' => 'string', 'description' => 'Search in titles (case and ё/е insensitive). Default: all parts separated by spaces '
                            . 'are found in the title ("Az 20" finds "Azot (20 sch)"). Modes by prefix: "~:" — the whole string with spaces as is; '
                            . '"=:" — equal to the whole title; "^:" — every part is the beginning of a word of the title; "^~:" — the title begins with the string; '
                            . '"!" before a mode inverts it ("!:" — titles without any of the parts, "!~:", "!=:", "!^:", "!^~:"); "~~:" — the default mode'],
                        'parent_id' => ['type' => 'string', 'description' => 'For tree: children of this key (without q)'],
                    ],
                'required' => ['table'],
            ],
            'edit' => [
                'description' => 'Change values. Values: select — key (multiple — array of keys), date "Y-m-d"/"Y-m-d H:i", checkbox true/false, '
                    . 'comments — text of new comment or {"edit_last": "text"} — edit your own last comment (if nobody has written after it). Setting a calc field makes it manual (✋); reset returns calculated value. '
                    . 'Like the group edit of web: a field marked one-row (get_table_schema) is changed in one row per call — wait for the answer before the next row '
                    . '(several rows — error: heavy for the server); several fields in one call are allowed, but fields without the mark multi-field are better changed alone '
                    . '(their action code may change other fields) — a warning in the answer. ' . static::FILE_VALUES,
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + [
                        'params' => ['type' => 'object', 'description' => 'Header/footer fields {field: value}'],
                        'rows' => ['type' => 'object', 'description' => 'Rows {"row_id": {field: value}}'],
                        'reset' => ['type' => 'object', 'description' => 'Reset manual values to calculated: {"params": [fields], "rows": {"row_id": [fields]}}'],
                        'dry_run' => ['type' => 'boolean', 'description' => 'Only for one row: show recalculated row without saving'],
                    ],
                'required' => ['table'],
            ],
            'insert_row' => [
                'description' => 'The correct way to add a row. Insert row like in web: a new row that is filled step by step and recalculated by the code after every change '
                    . '(values and select options depending on other fields, buttons active in the insert row). Without insert_hash — opens a new insert row '
                    . 'and returns its insert_hash; with insert_hash — shows/changes it. Buttons of the insert row: click_button with insert_hash; '
                    . 'options: get_select_options with insert_hash; save to the table: add_row with insert_hash. Nothing is added until add_row. '
                    . 'Tables with the tree view: it is the panel of a new row of web (into a folder — data {"tree": "<key>"}; the fields hidden in the panel are not set). ' . static::FILE_VALUES,
                'props' => static::TABLE_PROPS + [
                        'insert_hash' => static::INSERT_HASH,
                        'data' => ['type' => 'object', 'description' => 'Values to set {field: value}'],
                        'reset' => static::NAMES + ['description' => 'Fields to return to the calculated value'],
                    ],
                'required' => ['table'],
            ],
            'edit_panel' => [
                'description' => 'Edit panel of a row like in web (linkToPanel opens it, or open it yourself with id): several fields are changed '
                    . 'and recalculated by the code after every change without saving, then saved at once with save: true. '
                    . 'Without panel_hash and with id — opens a new panel and returns its panel_hash; with panel_hash — shows/changes/saves it. '
                    . 'Buttons of the row: click_button with panel_hash (their code sees the not saved values as #pnl.field); options: get_select_options with panel_hash. '
                    . static::FILE_VALUES,
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + [
                        'panel_hash' => ['type' => 'string', 'description' => 'Hash (e-…) of the panel'],
                        'id' => ['type' => 'integer', 'description' => 'Row id — to open a new panel'],
                        'data' => ['type' => 'object', 'description' => 'Values to set {field: value}'],
                        'reset' => static::NAMES + ['description' => 'Fields to return to the calculated value'],
                        'save' => ['type' => 'boolean', 'description' => 'Save all changes of the panel to the row (after data/reset of this call)'],
                    ],
                'required' => ['table'],
            ],
            'select_source' => [
                'description' => 'Source button of a select with a source table (field setting selectTable, like the buttons near the select in web): '
                    . 'opens the panel of the selected row of the source table to edit it, or with plus: true — the panel of a new row there. '
                    . 'The row is id, or insert_hash (insert row), or panel_hash (edit panel); params/footer select — none of them.',
                'props' => static::TABLE_PROPS + [
                        'field' => ['type' => 'string'],
                        'id' => ['type' => 'integer'],
                        'insert_hash' => static::INSERT_HASH,
                        'panel_hash' => ['type' => 'string', 'description' => 'Hash (e-…) of the edit panel'],
                        'plus' => ['type' => 'boolean', 'description' => 'Add a new row to the source table'],
                    ],
                'required' => ['table', 'field'],
            ],
            'add_row' => [
                'description' => 'Add a row. Correct way: with insert_hash — saves the insert row (insert_row) to the table (data — last changes before saving). '
                    . 'Without insert_hash — direct insert of data, a shortcut that skips the steps of the insert row (select options depending on other fields, '
                    . 'values set by its buttons): an inadmissible value can break the user system — use it only for tables without such dependencies. '
                    . 'dry_run (direct insert only) shows the row as it will be calculated without saving. '
                    . 'Cycles tables have no insert row (like web): add_row without data creates a cycle at once, the answer opens its first table. '
                    . 'Tables with the tree view: web adds rows only through the panel of a new row — add_row with data goes through the insert row (the panel) too. '
                    . static::FILE_VALUES,
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + [
                        'data' => ['type' => 'object', 'description' => '{field: value}'],
                        'insert_hash' => static::INSERT_HASH + ['description' => 'Save this insert row (from insert_row)'],
                        'after_id' => ['type' => 'integer', 'description' => 'Insert after this row (tables with order field)'],
                        'dry_run' => ['type' => 'boolean'],
                    ],
                'required' => ['table'],
            ],
            'delete_rows' => [
                'description' => 'Delete rows.',
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + ['ids' => static::IDS],
                'required' => ['table', 'ids'],
            ],
            'restore_rows' => [
                'description' => 'Restore deleted rows (see read_table deleted=true).',
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + ['ids' => static::IDS],
                'required' => ['table', 'ids'],
            ],
            'duplicate_rows' => [
                'description' => 'Duplicate rows (like the rows checked with checkmarks in web). The copies are added after after_id '
                    . '(web: after the row whose menu was used; without after_id — like adding). Unique (unic) fields need new values in replaces '
                    . '(the dialog "Fill in the values for unique fields" of web) unless the table has its own duplicate code.',
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + [
                        'ids' => static::IDS + ['description' => 'Rows to duplicate'],
                        'after_id' => ['type' => 'integer', 'description' => 'Add the copies after this row (tables with order field)'],
                        'replaces' => ['type' => 'object', 'description' => 'New values of unique fields for the copies: {"row_id": {field: value}} (row_id — the source row)'],
                    ],
                'required' => ['table', 'ids'],
            ],
            'reorder_rows' => [
                'description' => 'Change manual order of rows (tables with n-field, header line "order: manual"). '
                    . 'Either ordered_ids — these rows take the positions they occupy now in the given order (pass all ids of the table for a full sort), '
                    . 'or ids + after_id — move rows after the row after_id (0 — to the beginning).',
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + [
                        'ordered_ids' => static::IDS + ['description' => 'Rows in the new order'],
                        'ids' => static::IDS + ['description' => 'Rows to move (with after_id)'],
                        'after_id' => ['type' => 'integer', 'description' => 'Move ids after this row; 0 — to the beginning'],
                    ],
                'required' => ['table'],
            ],
            'recalc_rows' => [
                'description' => 'Recalculate rows.',
                'props' => static::TABLE_PROPS + static::WRITE_PROPS + ['ids' => static::IDS],
                'required' => ['table', 'ids'],
            ],
            'click_button' => [
                'description' => 'Press button field (or field with click action). Results of interface actions are shown in "Side effects". '
                    . 'Windows opened by the button are shown at once in "Opened window" sections: a table window is already read and gives the args '
                    . '(table, sess_hash, cycle, filters) to pass in the next calls to work in it (a link with field: values also opens its insert row — save it with add_row); '
                    . 'text/html/json windows show their content, '
                    . 'a json window with a button — submit_json_window, a buttons window — submit_buttons. '
                    . 'A button with the mark context-panel opens the context panel of its cell after pressing (shown in the answer, its buttons — click_panel_button). '
                    . 'Rows checked with checkmarks in web are checked_ids — web sends them with every button; buttons marked checked-rows need them. '
                    . 'Cells selected with the mouse in web are selected_cells — the code of the button gets them as $#slPro. '
                    . 'A field with an action on double click (mark dblclick-action) is run with click_button too, also when its cell is #block (web runs it exactly then). '
                    . 'Buttons of a blocked row / table (#block) are not pressed, like web.',
                'props' => static::TABLE_PROPS + [
                        'field' => ['type' => 'string'],
                        'id' => ['type' => 'integer', 'description' => 'Row id for column buttons; omit for header/footer buttons'],
                        'insert_hash' => static::INSERT_HASH + ['description' => 'Press the column button in the insert row (instead of id; buttons with right add-click)'],
                        'panel_hash' => ['type' => 'string', 'description' => 'Press the column button in the edit panel (instead of id): the code sees the not saved values as #pnl.field'],
                        'checked_ids' => static::IDS + ['description' => 'Rows checked with checkmarks (like in web), passed to the button code as $#ids'],
                        'selected_cells' => ['type' => 'object', 'additionalProperties' => static::IDS,
                            'description' => 'Cells of rows selected with the mouse in web: {"field": [row ids]} — the button code gets them as $#slPro ({"row id": [fields]})'],
                    ],
                'required' => ['table', 'field'],
            ],
            'get_field_settings' => [
                'description' => 'Creator: settings of a field like the field panel of web (data_src of the row of tables_fields): every setting of the type '
                    . 'for the category and the table type with its title, kind, default, values, parent (a setting is shown and works only when its parent is on) '
                    . 'and state. With table + field (or id of the row of tables_fields) — the current values of the field. '
                    . 'Write data_src with edit / insert_row / edit_panel of tables_fields as {"setting": value} — only the changed settings, '
                    . 'the others are kept (a new field — the defaults of the panel); null switches a setting off; "type" changes the type (the list of settings).',
                'props' => [
                    'table' => ['type' => 'string', 'description' => 'Table of the field (id or name): its type selects the settings; with field — the current values'],
                    'field' => ['type' => 'string', 'description' => 'Name of an existing field of the table'],
                    'id' => ['type' => 'integer', 'description' => 'Instead of table + field: id of the row of the field in tables_fields'],
                    'type' => ['type' => 'string', 'description' => 'Field type (string, number, select, tree, checkbox, date, button, link, text, comments, file, unic, password, listRow, chart); '
                        . 'with a field — the settings it would have after the change of the type'],
                    'category' => ['type' => 'string', 'enum' => ['param', 'filter', 'column', 'footer'], 'description' => 'Default column (a field — its category)'],
                ],
                'required' => [],
                'creator' => true,
                'noDebug' => true,
            ],
            'exec_code' => [
                'description' => 'Creator: execute an action code (like the code of a button: =: / named lines, set, insert, linkTo…, emailSend…) in the context of the table '
                    . '(and of the row id — #field are its values; vars — $#name). Always in debug mode (see the parameter debug): the calculation log, '
                    . 'external actions (mail, http, ssh…) blocked — stops at the first one, the transaction is ROLLED BACK unless debug.commit: true. '
                    . 'Answer: the result of the code, the changed tables, side effects and opened windows, the external actions, errors and the log.',
                'props' => static::TABLE_PROPS + [
                        'code' => ['type' => 'string', 'description' => 'Totum action code, e.g. "=: set(table: \'orders\'; field: \'status\' = \"done\"; where: \'id\' = #id)"'],
                        'id' => ['type' => 'integer', 'description' => 'Row of the table: the context of #field (like a button of the row); omit — the table without a row'],
                        'vars' => ['type' => 'object', 'description' => 'Variables of the code {name: value} — $#name'],
                    ],
                'required' => ['table', 'code'],
                'creator' => true,
            ],
            'search_codes' => [
                'description' => 'Creator: search through all the codes of the schema like grep — fields (code, codeAction, codeSelect, format… of data_src), '
                    . 'tables (default_action, row_format, table_format, on_duplicate) and data (rows/params of text fields with the totum code: crons, notification_codes, '
                    . 'ttm__custom_functions…). Answer: the codes by tables with the matching lines (line numbers) and how to open the whole code. '
                    . 'Who uses a table — q "\'orders\'"; a field — q "status" with word: true (#status, \'status\'); a custom function — q "specName"; '
                    . 'external actions (mail, http…) before a test — external: true (fields: mock / no mock in the help).',
                'props' => [
                    'q' => ['type' => 'string', 'description' => 'Text to find (case and ё/е insensitive, by lines); with regex: true — a PCRE without delimiters'],
                    'regex' => ['type' => 'boolean', 'description' => 'q is a regex, e.g. "emailSend|notificationSend", "table:\\\\s*\'orders\'"'],
                    'word' => ['type' => 'boolean', 'description' => 'q is not a part of a longer name: "status" finds #status, \'status\', not status_id'],
                    'external' => ['type' => 'boolean', 'description' => 'Only codes with external calls (emailSend, getFromScript, execSSH…; the kinds of debug); without q — the lines of the calls'],
                    'in' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => MCPCodeSearch::IN], 'description' => 'Scopes, default all: fields, tables (codes of tables), data (codes in rows of tables)'],
                    'table' => ['type' => 'string', 'description' => 'Only the codes of this table (id or name): its fields, its table codes, its rows'],
                    'settings' => static::NAMES + ['description' => 'Only these codes: code, codeAction, codeSelect, format, codeChart, treeViewCode, selectTableAction (fields); '
                            . 'default_action, row_format, table_format, on_duplicate (tables); without data'],
                    'off' => ['type' => 'boolean', 'description' => 'Also the switched-off codes of fields (kept in data_src, do not work)'],
                    'context' => ['type' => 'integer', 'description' => 'Lines around a matching line, default 0, max ' . MCPCodeSearch::MAX_CONTEXT],
                    'offset' => ['type' => 'integer', 'description' => 'Codes offset (next page), default 0'],
                    'limit' => ['type' => 'integer', 'description' => 'Codes on page, default ' . MCPCodeSearch::DEFAULT_LIMIT . ', max ' . MCPCodeSearch::MAX_LIMIT],
                ],
                'required' => [],
                'creator' => true,
                'noDebug' => true,
            ],
            'search_docs' => [
                'description' => 'Search in the documentation of Totum (code syntax, functions, field types and settings, table settings, interface, cycles, API, …): '
                    . 'sections that have all the words (parts of words work, case and ё/е insensitive; a function / setting name ranks first) with their ref and a fragment. '
                    . 'Read a found section with read_docs. Before writing a code — check the functions you use.',
                'props' => [
                    'q' => ['type' => 'string', 'description' => 'Words to search, e.g. "selectList order" or "только при добавлении"'],
                    'section' => ['type' => 'string', 'description' => 'Only this section: totum (reference), training-course, how-to, patterns (patterns of building systems with B2M examples); default — all: the reference first, the other sections — blocks "more in <section>" after it'],
                    'lang' => ['type' => 'string', 'description' => 'Language of the docs (default — the language of Totum, ru if there is no such docs)'],
                    'limit' => ['type' => 'integer', 'description' => 'How many sections, default ' . MCPDocs::DEFAULT_LIMIT . ', max ' . MCPDocs::MAX_LIMIT],
                ],
                'required' => ['q'],
                'readOnly' => true,
            ],
            'read_docs' => [
                'description' => 'Read the documentation of Totum: a section (from its heading to the next one of the same level) by ref "page#anchor" '
                    . '(from search_docs or links «text (→ ref)» in the texts), a whole page by "page", or by short forms: function (e.g. "selectList"), '
                    . 'setting (a field setting of data_src, e.g. "codeOnlyInAdd"), field_type (e.g. "select"), table_setting (a setting of the row of tables, e.g. "deleting"). '
                    . 'Architectural patterns of codes (read before development) — ref "ARCHITECTURE"; patterns of building systems (tabs, tree anchors, stored fields, background calculations…) — ref "patterns/patterns". '
                    . 'Without arguments — the index (DOCS.md and the list of pages). Long texts are paged: offset from "next page".',
                'props' => [
                    'ref' => ['type' => 'string', 'description' => '"page", "page#anchor" or "section/page#anchor", e.g. "functions#fn-select", "syntax", "how-to/some-page", "patterns/patterns"; root pages: "ARCHITECTURE"'],
                    'function' => ['type' => 'string', 'description' => 'Name of a function (functions#fn-<name>)'],
                    'setting' => ['type' => 'string', 'description' => 'Name of a field setting (fields#fields-settings-<name>)'],
                    'field_type' => ['type' => 'string', 'description' => 'Field type (fields#type-<type>)'],
                    'table_setting' => ['type' => 'string', 'description' => 'Name of a table setting (tables#table-settings-<name>)'],
                    'lang' => ['type' => 'string', 'description' => 'Language of the docs (default — the language of Totum, ru if there is no such docs)'],
                    'offset' => ['type' => 'integer', 'description' => 'Line of a long text to continue from ("next page")'],
                ],
                'required' => [],
                'readOnly' => true,
            ],
        ];
    }

    /* tools that do not change the data (MCP annotation readOnlyHint: clients may call them without a confirmation);
       read_table recalculates the changed like the opening of web, get_value / get_cell mark comments as read like web */
    protected const READ_ONLY_TOOLS = ['list_tables', 'get_table_schema', 'read_table', 'get_row', 'get_value', 'get_cell', 'get_select_options', 'get_file',
        'export_table', 'get_field_settings', 'search_codes', 'search'];

    protected function isToolOff(array $tool): bool
    {
        return (!empty($tool['http']) && $this->baseUrl === null) || (!empty($tool['creator']) && !$this->creator);
    }

    public function definitions(): array
    {
        $result = [];
        foreach ($this->tools() as $name => $tool) {
            if ($this->isToolOff($tool)) {
                continue;
            }
            if ($this->creator && (key_exists('table', $tool['props']) || !empty($tool['debug'])) && empty($tool['noDebug'])) {
                $tool['props']['debug'] = MCPLog::PROP;
            }
            $result[] = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => ['type' => 'object', 'properties' => $tool['props'] ?: new \stdClass()]
                    + ($tool['required'] ? ['required' => $tool['required']] : []),
            ] + (!empty($tool['readOnly']) || in_array($name, static::READ_ONLY_TOOLS) ? ['annotations' => ['readOnlyHint' => true]] : []);
        }
        return $result;
    }

    public function call(Conf $Config, User $User, string $name, array $args): string
    {
        $this->content = [];
        if (!key_exists($name, $this->tools()) || $this->isToolOff($this->tools()[$name])) {
            throw new errorException('Unknown tool: ' . $name . match (true) {
                    $name === 'upload_file' => ' (stdio server: pass new files as {"path": "/abs/path"})',
                    !empty($this->tools()[$name]['creator']) => ' (a tool of the creator view: connect with --creator=true / header ' . MCPAuth::CREATOR_HEADER . ': true)',
                    default => '',
                });
        }
        $this->rolledBack = $this->failed = false;
        if ($name === 'exec_code' && !key_exists('debug', $args)) {
            $args['debug'] = true;
        }
        if (key_exists('debug', $args) && !$this->creator) {
            throw new errorException('debug is a parameter of the creator view (connect with --creator=true / header ' . MCPAuth::CREATOR_HEADER . ': true)');
        }
        $Debug = key_exists('debug', $args) ? new MCPLog($args['debug']) : null;
        $this->Files = new MCPFiles($Config, $User, $this->baseUrl);
        $Totum = new Totum($Config, $User);
        $Debug?->apply($Totum);
        $this->Creator = new MCPCreator($Totum);
        $Totum->transactionStart();
        if (!$Debug) {
            $text = $this->runTool($Totum, $Config, $name, $args);
            $Totum->transactionCommit();
            return $text;
        }
        return $this->debugCall($Totum, $Config, $Debug, $name, $args);
    }

    /**
     * True — the last call was rolled back by debug (the db connection is closed: the server takes a clean config)
     */
    public function isRolledBack(): bool
    {
        return $this->rolledBack;
    }

    /**
     * True — the last call ended with an error answered by debug (with the log)
     */
    public function isFailed(): bool
    {
        return $this->failed;
    }

    /* the tool of the call (prepare: writes of a strict table need "updated") */
    protected string $toolName = '';
    /* writes that web checks against tableData.updated (strict tables: actual «strong»); edit_panel — with save */
    protected const STRICT_TOOLS = ['edit', 'add_row', 'delete_rows', 'restore_rows', 'duplicate_rows', 'reorder_rows', 'recalc_rows'];

    protected function runTool(Totum $Totum, Conf $Config, string $name, array $args): string
    {
        $this->toolName = $name;
        $this->checkOneCycle($Totum, $name, $args);
        $method = 'tool' . str_replace('_', '', ucwords($name, '_'));
        $text = $this->$method($Totum, $args);
        if (empty($this->tools()[$name]['ownWindows'])) {
            $links = $Totum->getInterfaceLinks() ?? [];
            $datas = $Totum->getInterfaceDatas() ?? [];
            $panels = $Totum->getPanelLinks();
            $text .= MCPMarkdown::sideEffects($links, $datas, $panels, $this->hostUrl($Config), $this->humanPages($Config));
            /* the code of a search card runs in the table of the result: its windows (buttons, input…) are submitted there */
            $tableArgs = $name === 'search_click' ? MCPSearch::tableArgs($Totum, $args) : array_intersect_key($args, array_flip(['table', 'cycle', 'sess_hash']));
            $text .= $this->openedWindows($Totum, $links, $datas, $tableArgs, $panels,
                $name === 'click_button' && is_string($args['insert_hash'] ?? null) ? $args['insert_hash'] : null);
        }
        return $text;
    }

    /**
     * debug: the call with the calculation log and the dry run; rolled back unless debug.commit and nothing blocked was met.
     * The answer is made before the rollback (the rollback closes the db connection)
     */
    protected function debugCall(Totum $Totum, Conf $Config, MCPLog $Debug, string $name, array $args): string
    {
        $text = '';
        $error = $path = null;
        try {
            $text = $this->runTool($Totum, $Config, $name, $args);
        } catch (DryRunStopException) {
        } catch (errorException|criticalErrorException $e) {
            $error = MCPMarkdown::plain($e->getMessage());
            $path = method_exists($e, 'getPathMess') ? $e->getPathMess() : null;
        }
        $save = $Debug->commit && $error === null && !$Debug->DryRun->getStop();
        $sections = $Debug->sections($Totum, $path);
        if ($save) {
            $Totum->transactionCommit();
        } else {
            $Totum->transactionRollback();
            $this->rolledBack = true;
        }
        $this->failed = $error !== null;
        return $Debug->status($save, $error) . $text . $sections;
    }

    /**
     * https://host of this Totum for relative links of the codes (stdio — the host of the schema)
     */
    protected function hostUrl(Conf $Config): string
    {
        return $this->baseUrl ?? 'https://' . $Config->getFullHostName();
    }

    /**
     * Paths of web pages for people (linkToForm / linkToQuickForm / linkToAnonymTable): the links are given to the user
     */
    protected function humanPages(Conf $Config): array
    {
        return ['/Forms/', '/' . $Config->getAnonymModul() . '/'];
    }

    /**
     * Windows of interface actions:
     * linkToDataText / linkToDataHtml / linkToDataJson — content of the window (json with code — button via submit_json_window);
     * linkToDataTable (window with a tmp/other table) — the agent gets the opened table at once
     * and continues with "table" + "sess_hash" like the web window
     * @param array $tableArgs table/cycle/sess_hash of the table of the action
     * @param string|null $insertHash the action is a button of the insert row: windows may change it (set with hash: $#ih)
     */
    protected function openedWindows(Totum $Totum, array $links, array $datas, array $tableArgs, array $panels = [], ?string $insertHash = null): string
    {
        $insertLine = $insertHash
            ? 'the window is opened from the insert row: its code may change the insert row — when the work in the window is done, '
            . 'see the result with insert_row `' . $this->json($tableArgs + ['insert_hash' => $insertHash]) . '` and save it with add_row.' . "\n"
            : '';
        $out = '';
        $windows = [];
        foreach ($datas as [$type, $data]) {
            switch ($type) {
                case 'table':
                    if (!empty($data['table_id'])) {
                        $windows[] = ['table_id' => $data['table_id'], 'sess_hash' => $data['sess_hash'] ?? null, 'title' => $data['title'] ?? '',
                            'refresh' => $data['refresh'] ?? false];
                    }
                    break;
                case 'text':
                    $out .= MCPMarkdown::textWindow($data);
                    break;
                case 'json':
                    $out .= MCPMarkdown::jsonWindow($data, $tableArgs) . $insertLine;
                    break;
                case 'input':
                    $out .= $this->inputWindow($Totum, $data, $tableArgs) . $insertLine;
                    break;
                case 'buttons':
                    $out .= MCPMarkdown::buttonsWindow($data, $tableArgs) . $insertLine;
                    break;
                case 'editField':
                    $out .= $this->editWindow($Totum, $data, $tableArgs);
                    break;
                case 'fileUpload':
                    $out .= $this->fileUploadWindow($data, $tableArgs) . $insertLine;
                    break;
                case 'files':
                    $out .= $this->downloadedFiles($data);
                    break;
            }
        }
        foreach ($links as $link) {
            if ($parsed = MCPTableResolver::parseLink($link['uri'] ?? '')) {
                $windows[] = $parsed + ['title' => $link['title'] ?? '', 'refresh' => $link['refresh'] ?? false];
            }
        }
        foreach ($windows as $window) {
            if (!($tableRow = $Totum->getTableRow($window['table_id']))) {
                continue;
            }
            $args = ['table' => $tableRow['name']]
                + ($tableRow['type'] === 'calcs' && !empty($window['cycle']) ? ['cycle' => $window['cycle']] : [])
                + (!empty($window['sess_hash']) ? ['sess_hash' => $window['sess_hash']] : [])
                + (!empty($window['filters']) ? ['filters' => $window['filters']] : []);
            $out .= "\n---\n## Opened window: " . MCPMarkdown::plain($window['title']) . "\n"
                . 'Work with it like in the web window: pass `' . $this->json($args) . '` to read_table / edit / click_button / get_cell.' . "\n"
                . (!empty($window['filters']) ? "filters are set by the link (crypted string — the core decrypts it, the values are in ## Filters): "
                    . "pass the same \"filters\" string in every call to this table (filters are stateless).\n" : '')
                . (!empty($window['add']) ? 'the link opens the insert row with the values ' . $this->json($window['add'])
                    . ' (field: of linkToTable) — like web: see "## Insert row" below, fill it and save with add_row.' . "\n" : '')
                . MCPMarkdown::refreshLine($window['refresh']) . $insertLine . $this->cycleTabWindow($Totum, $window, $tableRow);
            try {
                $out .= "\n" . $this->toolReadTable($Totum, $args);
            } catch (errorException|criticalErrorException $e) {
                $out .= 'The window table is not available: ' . MCPMarkdown::plain($e->getMessage()) . "\n";
                continue;
            }
            if (!empty($window['add'])) {
                try {
                    $out .= $this->linkInsertRow($Totum, $args, $window['add']);
                } catch (errorException|criticalErrorException $e) {
                    $out .= "\nThe insert row of the link is not opened: " . MCPMarkdown::plain($e->getMessage()) . "\n";
                }
            }
        }
        foreach ($panels as $panel) {
            $out .= $this->panelWindow($Totum, $panel, $insertLine);
        }
        return $out;
    }

    /**
     * Cycles table with one cycle per user: like web only the opening (read_table / get_table_schema → the cycle) and the tab_ buttons of the cycle
     */
    protected function checkOneCycle(Totum $Totum, string $tool, array $args): void
    {
        if (in_array($tool, ['list_tables', 'read_table', 'get_table_schema', 'get_field_settings', 'search_codes']) || empty($args['table'])
            || ($Totum->getConfig()->getTableRow(is_int($args['table']) ? $args['table'] : (string)$args['table'])['type'] ?? null) !== 'cycles') {
            return;
        }
        $Resolver = new MCPTableResolver($Totum, $args);
        if (($cycleId = $Resolver->oneCycle()) === false
            || ($cycleId && $tool === 'click_button' && str_starts_with((string)($args['field'] ?? ''), 'tab_') && (int)($args['id'] ?? 0) === $cycleId)) {
            return;
        }
        $name = $Resolver->getTable()->getTableRow()['name'];
        throw new errorException('Table ' . $name . ' has one cycle per user: like web the cycles table is not worked with directly — '
            . ($cycleId
                ? 'work in the tables of your cycle ' . $cycleId . ' (read_table `' . $this->json(['table' => $name]) . '` opens it and shows its tables); '
                . 'here only the tab_ buttons of the cycle are pressed: click_button with "id": ' . $cycleId
                : 'open it with read_table `' . $this->json(['table' => $name]) . '`: like web the opening creates your cycle and opens it'));
    }

    /**
     * Opening (read_table / get_table_schema) of a cycles table with one cycle per user — like the page of web:
     * no cycle → it is created (reCalculateFromOvers add), then the first table of the cycle available for the user is opened instead of the cycles table
     * @return string|null null — a usual table
     */
    protected function oneCycleRedirect(Totum $Totum, array $args, string $tool): ?string
    {
        if (($Totum->getConfig()->getTableRow(is_int($args['table'] ?? null) ? $args['table'] : (string)($args['table'] ?? ''))['type'] ?? null) !== 'cycles') {
            return null;
        }
        $Resolver = new MCPTableResolver($Totum, $args);
        if (($cycleId = $Resolver->oneCycle()) === false) {
            return null;
        }
        $Table = $Resolver->getTable();
        $tableRow = $Table->getTableRow();
        $out = '# ' . MCPMarkdown::plain($tableRow['title']) . ' · ' . $tableRow['name'] . ' · id ' . $tableRow['id'] . " · cycles · one cycle per user\n";
        if ($cycleId === null) {
            $Table->reCalculateFromOvers(['add' => [[]]]);
            $cycleId = (int)$Table->getUserCycleId();
            $out .= '**your cycle ' . $cycleId . ' is created now** (like web on opening: a table with one cycle per user creates it)' . "\n";
        }
        $out .= 'one cycle per user: like web the cycles table is not shown — its opening goes into your cycle ' . $cycleId
            . '. Work in the tables of the cycle ("cycle": ' . $cycleId . '); the cycles table takes only the tab_ buttons of the cycle (click_button with "id": ' . $cycleId . ").\n";
        $Cycle = $Totum->getCycle($cycleId, $tableRow['id']);
        foreach ($Cycle->getTableIds() as $tableId) {
            if ($Totum->getUser()->isTableInAccess($tableId)) {
                $first = ['table' => $Totum->getTableRow($tableId)['name'], 'cycle' => $cycleId];
                return $out . "\n---\n## Opened: the first table of the cycle\n"
                    . ($tool === 'read_table' ? $this->toolReadTable($Totum, $first) : $this->toolGetTableSchema($Totum, $first));
            }
        }
        return $out . "no tables of the cycle are available for you\n";
    }

    /**
     * A not calcs table opened by a tab_ button of a cycles table (/Table/{top}/{cycles table}/{cycle}/{table}?b=tab_…):
     * web shows it as a tab inside the cycle — the table itself has no cycle (it is the usual table, with the filters of the link)
     */
    protected function cycleTabWindow(Totum $Totum, array $window, array $tableRow): string
    {
        if (empty($window['cycle']) || empty($window['cycles_table']) || $tableRow['type'] === 'calcs' || !is_string($tab = $window['query']['b'] ?? null)) {
            return '';
        }
        try {
            $Cycle = $Totum->getCycle($window['cycle'], $window['cycles_table']);
            if (!$Cycle->loadRow()) {
                return '';
            }
            return 'the table is opened as the tab ' . $tab . ' of cycle ' . $window['cycle'] . ' (web shows it inside the cycle; the table itself is not a table of the cycle — no "cycle" arg)' . "\n"
                . MCPMarkdown::cycleSection($Totum, $Cycle, $tab);
        } catch (errorException|criticalErrorException) {
            return '';
        }
    }

    /**
     * linkToPanel (and the source buttons of selects): with id — the edit panel of the row (several ids — several panels),
     * without id — the panel of a new row = the insert row of the table (field: — its values)
     */
    protected function panelWindow(Totum $Totum, array $panel, string $insertLine): string
    {
        $parsed = MCPTableResolver::parseLink(rtrim($panel['uri'] ?? '', '/') . '/');
        if (!$parsed || !($tableRow = $Totum->getTableRow($parsed['table_id']))) {
            return '';
        }
        $args = ['table' => $tableRow['name']]
            + ($tableRow['type'] === 'calcs' && !empty($parsed['cycle']) ? ['cycle' => $parsed['cycle']] : [])
            + (!empty($parsed['sess_hash']) ? ['sess_hash' => $parsed['sess_hash']] : []);
        $id = !empty($panel['id']) ? (int)$panel['id'] : null;
        $fields = array_values(array_filter((array)($panel['fields'] ?? []), 'is_string'));
        $out = "\n---\n## Opened panel: " . $tableRow['name'] . ($id ? ' · row ' . $id : ' · new row') . "\n"
            . 'Work with it like with the panel of web: pass `' . $this->json($args) . '` + the hash below.' . "\n"
            . MCPMarkdown::refreshLine($panel['refresh'] ?? false) . $insertLine;
        try {
            if ($id) {
                return $out . $this->toolEditPanel($Totum, $args + ['id' => $id], true, $fields, (array)($panel['titles'] ?? []));
            }
            $values = [];
            foreach ((array)($panel['field'] ?? []) as $name => $value) {
                $values[$name] = is_array($value) && key_exists('v', $value) ? $value['v'] : $value;
            }
            return $out . 'the panel of a new row is the insert row of the table: fill it and save with add_row' . "\n"
                . $this->linkInsertRow($Totum, $args, $values);
        } catch (errorException|criticalErrorException $e) {
            return $out . 'The panel is not opened: ' . MCPMarkdown::plain($e->getMessage()) . "\n";
        }
    }

    /**
     * linkToInput / linkToInputSelect: the window with an input (text / html form with textarea ttmInput / select);
     * the select gets its options at once like the select of the web window
     */
    protected function inputWindow(Totum $Totum, array $data, array $tableArgs): string
    {
        $options = null;
        if (($data['type'] ?? null) === 'select' && !empty($data['hash'])) {
            try {
                $options = $this->inputSelectOptions($Totum, $tableArgs, (string)$data['hash'], '');
            } catch (errorException|criticalErrorException $e) {
                $options = 'The options are not available: ' . MCPMarkdown::plain($e->getMessage()) . "\n";
            }
        }
        return MCPMarkdown::inputWindow($data, $tableArgs, $options);
    }

    /**
     * linkToEdit: the field of a row/params of a table (maybe another one) in a window; select/tree get their options at once
     */
    protected function editWindow(Totum $Totum, array $data, array $tableArgs): string
    {
        $hash = (string)($data['hash'] ?? '');
        try {
            [, $Actions] = $this->prepare($Totum, $tableArgs, false);
            [$hashData, $Table, $field, $item] = $Actions->mcpEditFieldData($hash);
            $options = null;
            if (in_array($field['type'], ['select', 'tree'])) {
                $options = $this->editOptions($Totum, $tableArgs, $hash, '', null);
            }
        } catch (errorException|criticalErrorException $e) {
            return "\n---\n## Opened window (edit): " . MCPMarkdown::plain(MCPMarkdown::scalar($data['title'] ?? '')) . "\n"
                . 'The edit window is not available: ' . MCPMarkdown::plain($e->getMessage()) . "\n";
        }
        $Md = new MCPMarkdown($Table, ['fields' => [$field['name'] => $field]], true);
        return $Md->editWindow($data, $hashData['table'], $field, $data['value'] ?? ($item[$field['name']] ?? null), $tableArgs, $options);
    }

    /**
     * linkToFileUpload: web opens the file dialog of the browser (accept = type, multiple if limit > 1) and sends the files to filesUpload;
     * limit / type are not saved by the core — MCP keeps them for submit_file_upload
     */
    protected function fileUploadWindow(array $data, array $tableArgs): string
    {
        $hash = (string)($data['hash'] ?? '');
        $meta = ['limit' => max(1, (int)($data['limit'] ?? 1)), 'type' => is_string($data['type'] ?? null) ? trim($data['type']) : '*',
            'title' => MCPMarkdown::scalar($data['title'] ?? ''), 'refresh' => $data['refresh'] ?? false];
        $this->Files->rememberFileUpload($hash, $meta);
        return MCPMarkdown::fileUploadWindow($meta, $tableArgs + ['hash' => $hash], $this->Files->isLocal());
    }

    /**
     * linkToFileDownload: the browser saves the files — stdio: they are saved to the folder of the agent, HTTP: download links
     */
    protected function downloadedFiles(array $data): string
    {
        $lines = [];
        foreach ((array)($data['files'] ?? []) as $file) {
            $content = base64_decode((string)($file['string'] ?? ''));
            $name = MCPFiles::safeName((string)($file['name'] ?? 'file'));
            $lines[] = '- ' . MCPMarkdown::esc($name) . ' · ' . MCPFiles::size(strlen($content)) . (!empty($file['type']) ? ' · ' . $file['type'] : '')
                . ' → ' . $this->Files->deliverContent($name, $content, $file['type'] ?? null);
        }
        return "\n---\n## Downloaded files (linkToFileDownload — web saves them in the browser)\n"
            . ($lines ? $this->Files->deliverHint() . implode("\n", $lines) . "\n" : "no files\n");
    }

    protected function editOptions(Totum $Totum, array $tableArgs, string $hash, string $q, ?string $parentId): string
    {
        [, $Actions] = $this->prepare($Totum, $tableArgs, false);
        [, , $field] = $Actions->mcpEditFieldData($hash);
        $options = $this->options($Totum, fn($q, $parentId) => $Actions->mcpEditFieldOptions($hash, $q, $parentId), $field['type'] === 'tree', $q, $parentId);
        $args = $this->json($tableArgs + ['edit_hash' => $hash, 'q' => '…']);
        return ($options['sliced'] ? 'not all options are shown — search with get_select_options `' . $args . "`\n" : '')
            . ($options['notComplete'] ? 'the tree is not loaded completely: (has children) — children with parent_id = key or search with q: get_select_options `' . $args . "`\n" : '')
            . $options['table'];
    }

    /**
     * @return string markdown table of the options (+ a line when the list is cut)
     */
    protected function inputSelectOptions(Totum $Totum, array $tableArgs, string $hash, string $q): string
    {
        [, $Actions] = $this->prepare($Totum, $tableArgs, false, ['hash' => $hash]);
        $options = $this->options($Totum, fn($q) => $Actions->mcpInputSelectOptions($q), false, $q);
        return ($options['sliced'] ? 'not all options are shown — search with get_select_options `'
                . $this->json($tableArgs + ['input_hash' => $hash, 'q' => '…']) . "`\n" : '')
            . $options['table'];
    }

    /**
     * linkToTable with field: — web opens the target table with the insert row filled by these values (addVars → checkInsertRow).
     * Like web (channel web) the values of fields that are not insertable for the user are not set: they are listed in the answer
     */
    protected function linkInsertRow(Totum $Totum, array $args, array $add): string
    {
        $Table = (new MCPTableResolver($Totum, $args))->getTable();
        $data = [];
        $skipped = [];
        foreach ($add as $name => $value) {
            $field = $Table->getFields()[$name] ?? null;
            if ($field && $field['category'] === 'column' && $Table->isField('visible', 'web', $field) && $Table->isField('insertable', 'web', $field)) {
                $data[$name] = $value;
            } else {
                $skipped[] = $name;
            }
        }
        return $this->toolInsertRow($Totum, $args + ['data' => $data], true)
            . ($skipped ? 'not set (not insertable columns for you, web ignores them too): ' . implode(', ', $skipped) . "\n" : '');
    }

    /* ======================== helpers ======================== */

    /**
     * @return array [MCPTableResolver, MCPReadTableActions|MCPWriteTableActions]
     */
    /**
     * @param bool|null $forChanges the answer is changesAnswer (default — $forWrite): remember table texts before the action
     */
    protected function prepare(Totum $Totum, array $args, bool $forWrite, array $post = [], ?bool $forChanges = null): array
    {
        $Resolver = new MCPTableResolver($Totum, $args);
        /* object {filter: value} or crypted string from a link (opened window) — decrypted by the core */
        $post['filters'] = is_array($args['filters'] ?? null) || is_string($args['filters'] ?? null) ? $args['filters'] : [];
        if (!empty($args['deleted'])) {
            $post['restoreView'] = true;
        }
        $Actions = $Resolver->getActions($post, $forWrite);
        if ($forWrite) {
            $Actions->mcpCheckUpdated($args['updated'] ?? null,
                in_array($this->toolName, static::STRICT_TOOLS) || ($this->toolName === 'edit_panel' && !empty($args['save'])));
        }
        if ($forChanges ?? $forWrite) {
            $Actions->mcpRememberTableTexts();
            $Actions->mcpRememberFormats();
        }
        return [$Resolver, $Actions];
    }

    protected function markdown(MCPTableResolver $Resolver, MCPReadTableActions|MCPWriteTableActions $Actions, array $f = []): MCPMarkdown
    {
        return (new MCPMarkdown($Resolver->getTable(), $Actions->mcpSchema(), $Actions->mcpIsWrite()))->setTableFormat($f)
            ->withTreeView($Actions->mcpTreeViewType());
    }

    protected function ids(array $args, string $name, bool $required = false): array
    {
        $ids = array_values(array_filter(array_map('intval', (array)($args[$name] ?? []))));
        if ($required && !$ids) {
            throw new errorException('Parameter "' . $name . '" must be a non-empty list of row ids');
        }
        return $ids;
    }

    protected function names(array $args, string $name): ?array
    {
        $names = array_values(array_filter((array)($args[$name] ?? []), 'is_string'));
        return $names ?: null;
    }

    protected function json(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function visibleField(MCPTableResolver $Resolver, string $name): array
    {
        if (!($field = $Resolver->getTable()->getVisibleFields('web')[$name] ?? null)) {
            throw new errorException('Field ' . $name . ' is not found or access to it is denied');
        }
        return $field;
    }

    /**
     * The core silently ignores not permitted fields — the agent gets an explicit error instead
     */
    protected function checkFieldRight(MCPTableResolver $Resolver, string $name, array $categories, string $right): void
    {
        $Table = $Resolver->getTable();
        $field = $Table->getFields()[str_contains($name, '/') ? explode('/', $name)[0] : $name] ?? null;
        if (!$field || !$Table->isField('visible', 'web', $field)) {
            throw new errorException('Field ' . $name . ' is not found or access to it is denied');
        }
        if ($field['category'] === 'filter') {
            throw new errorException('Field ' . $name . ' is a filter: pass it in "filters"');
        }
        if (!in_array($field['category'], $categories)) {
            throw new errorException('Field ' . $name . ' has category ' . $field['category'] . ': ' . ($field['category'] === 'column' ? 'use "rows"' : 'use "params"'));
        }
        if (!$Table->isField($right, 'web', $field)) {
            throw new errorException('Field ' . $name . ' is not ' . $right . ' for you');
        }
    }

    /**
     * Input validation format (regexp of string/unic/number) like checkValByType of the core, but before the action and with all
     * mismatches at once: the core stops at the first one with regexpErrorText only (without field and value)
     * @param array $values [row label => [field => value]]
     */
    protected function checkFormats(MCPTableResolver|array $fields, array $values): void
    {
        $fields = is_array($fields) ? $fields : $fields->getTable()->getFields();
        $errors = [];
        foreach ($values as $label => $row) {
            foreach ($row as $name => $value) {
                $field = $fields[$name] ?? null;
                if (!$field || empty($field['regexp']) || !is_string($field['regexp']) || !in_array($field['type'], ['string', 'unic', 'number'])
                    || !is_scalar($value) || is_bool($value) || (string)$value === '' || ($field['type'] === 'number' && !is_numeric($value))) {
                    continue;
                }
                $pattern = '/' . str_replace('/', '\/', $field['regexp']) . '/' . ($field['type'] === 'number' ? '' : 'u');
                if (@preg_match($pattern, (string)$value) === 0) {
                    $errors[] = '- ' . $label . ', ' . $name . ' ' . json_encode((string)$value, JSON_UNESCAPED_UNICODE) . ': must match /' . $field['regexp'] . '/'
                        . (!empty($field['regexpErrorText']) ? ' — ' . MCPMarkdown::plain($field['regexpErrorText']) : '');
                }
            }
        }
        if ($errors) {
            throw new errorException("Values do not match the input format (regexp) of the fields, nothing is saved:\n" . implode("\n", $errors));
        }
    }

    /**
     * @param array $skipOthers tables not listed in "other changed tables" (e.g. the tables of a new cycle)
     */
    protected function changesAnswer(MCPTableResolver $Resolver, MCPReadTableActions|MCPWriteTableActions $Actions, mixed $result, string $title, array $skipOthers = []): string
    {
        if (is_string($result)) {
            throw new errorException($result);
        }
        $result = is_array($result) ? $result : [];
        $Md = $this->markdown($Resolver, $Actions, $result['f'] ?? []);
        $out = $Md->header() . "\n**" . $title . "**\n" . (!empty($result['mcpChanges']) ? $Md->changes($result) : "ok\n");
        if (($before = $Actions->mcpTableTextsBefore()) && isset($result['f'])) {
            $out .= $Md->tableFlagsChanges($before, $result['f']) . $Md->tableTextsChanges($before, MCPMarkdown::tableTexts($result['f']));
        }

        $Table = $Resolver->getTable();
        $others = array_diff($Table->getTotum()->getChangedTables(), [$Table->getTableRow()['name']], $skipOthers);
        if ($others) {
            $out .= "\nother changed tables: " . implode(', ', $others) . " — re-read them to see the result\n";
        }
        return $out;
    }

    /* ======================== read tools ======================== */

    protected function toolListTables(Totum $Totum, array $args): string
    {
        $User = $Totum->getUser();
        $access = $User->getTables();
        if (!$access) {
            return "# Tables\nno tables available\n";
        }
        /* like the tree of web: tables hidden from the tree are opened only by buttons/links of other tables */
        $hidden = MCPTableResolver::hiddenFromTree($Totum);
        $ids = array_keys(array_diff_key($access, $hidden));
        $tables = $ids ? Table::init($Totum->getConfig())->getAll(['id' => $ids, 'is_del' => false],
            'id, name, title, type, tree_node_id',
            '(sort->>\'v\')::numeric') : [];

        $words = preg_split('/\s+/', mb_strtolower(trim((string)($args['q'] ?? ''))), -1, PREG_SPLIT_NO_EMPTY);
        $tables = array_filter($tables, function ($t) use ($words) {
            $str = mb_strtolower($t['name'] . ' ' . $t['title']);
            foreach ($words as $w) {
                if (!str_contains($str, $w)) {
                    return false;
                }
            }
            return true;
        });

        $namesById = array_column($tables, 'name', 'id');
        $treeIds = array_unique(array_map('intval', array_column(array_filter($tables, fn($t) => $t['type'] !== 'calcs'), 'tree_node_id')));
        $branches = $treeIds
            ? array_column($Totum->getConfig()->getSql()->getAll('select id, title from tree__v where id in (' . implode(',', $treeIds) . ')'), 'title', 'id')
            : [];

        $rows = [];
        foreach ($tables as $t) {
            $branch = $t['type'] === 'calcs'
                ? 'cycle of ' . ($namesById[$t['tree_node_id']] ?? $Totum->getTableRow($t['tree_node_id'])['name'] ?? $t['tree_node_id'])
                : ($branches[$t['tree_node_id']] ?? '');
            $rows[] = [$t['id'], $t['name'], MCPMarkdown::esc($t['title']), $t['type'] . ($User->isOneCycleTable($t) ? ' (one cycle per user)' : ''),
                MCPMarkdown::esc($branch), $access[$t['id']] ? 'rw' : 'ro'];
        }
        return '# Tables (' . count($rows) . ")\n"
            . "calcs tables are opened with cycle = id of the row in the cycles table (a cycle = a row of its cycles table; read_table of the cycles table shows how to open and add cycles)\n"
            . ($hidden ? 'not listed: ' . count($hidden) . ' table(s) hidden from the tree — they are opened by buttons/links of other tables '
                . "(the answer gives the args of the window), do not open them directly\n" : '') . "\n"
            . MCPMarkdown::mdTable(['id', 'name', 'title', 'type', 'branch', 'access'], $rows);
    }

    protected function toolGetTableSchema(Totum $Totum, array $args): string
    {
        if ($redirect = $this->oneCycleRedirect($Totum, $args, 'get_table_schema')) {
            return $redirect;
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false);
        return $this->markdown($Resolver, $Actions, $Actions->mcpTableFormat())->schema($Actions->mcpExportInfo())
            . ($this->creator ? $this->Creator->schemaSection($Resolver->getTable()) : '');
    }

    protected function toolGetFieldSettings(Totum $Totum, array $args): string
    {
        return $this->Creator->fieldSettingsAnswer($args);
    }

    protected function toolSearchCodes(Totum $Totum, array $args): string
    {
        return (new MCPCodeSearch($Totum, $this->Creator->fieldSettings()))->search($args);
    }

    /**
     * An action code in the context of the table / row (like a button: CalculateAction::execAction), called in debug mode only (see call)
     */
    protected function toolExecCode(Totum $Totum, array $args): string
    {
        $code = is_string($args['code'] ?? null) ? trim($args['code']) : '';
        if ($code === '') {
            throw new errorException('code is required: an action code, e.g. "=: set(table: \'orders\'; field: \'status\' = \"done\"; where: \'id\' = #id)"');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true);
        $Table = $Resolver->getTable();
        $row = [];
        if ($id = (int)($args['id'] ?? 0)) {
            if (!in_array($id, $Table->loadFilteredRows('web', [$id]))) {
                throw new errorException('Row ' . $id . ' is not found in the table (or not visible with the filters)');
            }
            $row = $Table->getTbl()['rows'][$id];
        }
        $vars = is_array($args['vars'] ?? null) ? $args['vars'] : [];
        $result = (new CalculateAction($code))->execAction('exec_code', $row, $row, $Table->getTbl(), $Table->getTbl(), $Table, 'exec', $vars);

        $result = MCPMarkdown::scalar($result);
        return $this->markdown($Resolver, $Actions)->header() . "\n**Executed: exec_code**" . ($id ? ' · row ' . $id : '') . "\n"
            . 'result: ' . ($result === '' ? '(empty)' : "\n~~~\n" . MCPMarkdown::cut($result, 5000) . "\n~~~") . "\n"
            . 'changed tables: ' . (($changed = $Totum->getChangedTables()) ? implode(', ', $changed) : 'none') . "\n";
    }

    protected function toolSearchDocs(Totum $Totum, array $args): string
    {
        return (new MCPDocs($Totum->getConfig(), $args['lang'] ?? null))->search((string)($args['q'] ?? ''), isset($args['section']) ? (string)$args['section'] : null,
            (int)($args['limit'] ?? MCPDocs::DEFAULT_LIMIT));
    }

    protected function toolReadDocs(Totum $Totum, array $args): string
    {
        return (new MCPDocs($Totum->getConfig(), $args['lang'] ?? null))->read($args);
    }

    protected function toolReadTable(Totum $Totum, array $args): string
    {
        if ($redirect = $this->oneCycleRedirect($Totum, $args, 'read_table')) {
            return $redirect;
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false);
        $limit = min(max((int)($args['limit'] ?? static::DEFAULT_LIMIT), 1), static::MAX_LIMIT);
        $offset = max((int)($args['offset'] ?? 0), 0);
        $fields = $this->names($args, 'fields');

        if ($Actions->mcpTreeViewType() && !$this->ids($args, 'ids')) {
            return $this->readTree($Resolver, $Actions, $args, $offset, $limit, $fields);
        }
        if (key_exists('branch', $args) || !empty($args['recursive'])) {
            throw new errorException('"branch" / "recursive" are for tables with the tree view (header line "view: tree")'
                . ($Actions->mcpTreeViewType() ? ' without ids' : ''));
        }
        $data = $Actions->mcpReadTable($offset, $limit, $this->ids($args, 'ids'), (bool)($args['recalculate'] ?? true));
        $Md = (new MCPMarkdown($Resolver->getTable(), $data['form'], $Actions->mcpIsWrite()))->setTableFormat($data['f'])
            ->withStyles(!empty($args['styles']));

        $count = count($data['rows']);
        $out = $Md->header() . ($offset === 0 && !$this->ids($args, 'ids') ? $Md->description() . $Md->cycleInfo() : '') . $Md->tableText()
            . $Md->paramsSections($data['params'], $fields);
        $out .= "\n## Rows " . ($count ? ($data['offset'] + 1) . '–' . ($data['offset'] + $count) : '0') . ' of ' . $data['allCount']
            . (!empty($args['deleted']) ? ' (deleted)' : '') . "\n" . $Md->rowsComment();
        if ($Md->fieldsByCategory('column', $fields)) {
            $out .= $Md->rowsTable($data['rows'], $fields);
        }
        if ($data['offset'] + $count < $data['allCount']) {
            $out .= "\nnext page: offset=" . ($data['offset'] + $count) . "\n";
        }
        return $out . $Md->footerSection($data['params'], $fields) . (!empty($args['styles']) ? $Md->stylesSection() : '');
    }

    /**
     * Tree view: like the tree page of web — the nodes ("## Tree") and the rows of the loaded branches in the order of the open tree;
     * branch — the opening of a node (its rows and subnodes)
     */
    protected function readTree(MCPTableResolver $Resolver, MCPReadTableActions|MCPWriteTableActions $Actions, array $args, int $offset, int $limit, ?array $fields): string
    {
        $branches = key_exists('branch', $args) ? array_map(fn($b) => is_scalar($b) ? (string)$b : '', (array)$args['branch']) : [];
        if (key_exists('branch', $args) && !$branches) {
            throw new errorException('"branch" must be a key of a node of "## Tree" (or a list of keys)');
        }
        if (!empty($args['recursive']) && !$branches) {
            throw new errorException('"recursive" is for "branch": the node whose branches are opened');
        }
        $data = $Actions->mcpReadTree($branches, !empty($args['recursive']), $offset, $limit, (bool)($args['recalculate'] ?? true));
        $Md = (new MCPMarkdown($Resolver->getTable(), $data['form'], $Actions->mcpIsWrite()))->setTableFormat($data['f'])
            ->withStyles(!empty($args['styles']))->withTreeView($data['type']);
        $tableArgs = $this->windowArgs($args);
        $count = count($data['rows']);
        $out = $Md->header() . ($offset === 0 && !$branches ? $Md->description() . $Md->cycleInfo() : '') . $Md->tableText()
            . $Md->paramsSections($data['params'], $fields)
            . ($offset === 0 ? $Md->treeSection($data, $tableArgs) : '');
        $out .= "\n## Rows " . ($count ? ($offset + 1) . '–' . ($offset + $count) : '0') . ' of ' . $data['allCount']
            . ($branches ? ' of the branch ' . implode(', ', $branches) : ($data['paging'] ? ' (the paged tree: rows are read by folders)' : ' of the loaded branches'))
            . "\n" . $Md->rowsComment();
        if ($count && $Md->fieldsByCategory('column', $fields)) {
            $out .= $Md->rowsTable($data['rows'], $fields);
        }
        if ($offset + $count < $data['allCount']) {
            $out .= "\nnext page: offset=" . ($offset + $count) . ($branches ? ' (with the same branch)' : '') . "\n";
        }
        return $out . $Md->footerSection($data['params'], $fields) . (!empty($args['styles']) ? $Md->stylesSection() : '');
    }

    protected function toolGetRow(Totum $Totum, array $args): string
    {
        $fields = $this->names($args, 'fields');
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, ['id' => (string)(int)($args['id'] ?? 0), 'fields' => $this->json($fields ?? [])]);
        $res = $Actions->viewRow();
        $Md = $this->markdown($Resolver, $Actions, $res['f'] ?? [])->withStyles(!empty($args['styles']));
        return $Md->header() . $Md->rowCard($res['row'], $fields) . (!empty($args['styles']) ? $Md->stylesSection() : '');
    }

    protected function toolGetValue(Totum $Totum, array $args): string
    {
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false);
        $field = $this->visibleField($Resolver, (string)($args['field'] ?? ''));
        /* before getValue: the core marks the comments as read when they are opened */
        $unread = $field['type'] === 'comments' ? $Actions->mcpUnreadComments($field['name'], !empty($args['id']) ? (int)$args['id'] : null) : 0;
        $value = $Actions->getValue(['fieldName' => $field['name'], 'rowId' => $args['id'] ?? null])['value'];

        $out = '# ' . $Resolver->getTable()->getTableRow()['name'] . '.' . $field['name'] . (!empty($args['id']) ? ' · id ' . (int)$args['id'] : '')
            . ' · ' . $field['type'] . "\n\n";
        switch ($field['type']) {
            case 'comments':
                return $out . MCPMarkdown::commentsList((array)$value, $field['name'], $unread) . "\n";
            case 'file':
                return $out . MCPMarkdown::filesList((array)$value, $field) . "\n";
            case 'fieldParams':
                return $out . $this->Creator->describeFieldValue($value,
                        MCPCreator::flatRow(!empty($args['id']) ? $Resolver->getTable()->getTbl()['rows'][(int)$args['id']] ?? [] : []));
            default:
                return $out . MCPMarkdown::scalar($value) . "\n";
        }
    }

    /**
     * Files of a file field with the access of web (visible field, row of the user; secure files are given by the core only after this check)
     */
    protected function toolGetFile(Totum $Totum, array $args): string
    {
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false);
        $field = $this->visibleField($Resolver, (string)($args['field'] ?? ''));
        if ($field['type'] !== 'file') {
            throw new errorException('Field ' . $field['name'] . ' is not a file field');
        }
        $id = !empty($args['id']) ? (int)$args['id'] : null;
        if ($field['category'] === 'column' && !$id) {
            throw new errorException('Field ' . $field['name'] . ' is a column: parameter "id" (row id) is required');
        }
        $files = array_values(array_filter((array)$Actions->getValue(['fieldName' => $field['name'], 'rowId' => $id])['value'],
            fn($f) => is_array($f) && !empty($f['file']) && (!isset($args['file']) || $f['file'] === $args['file'])));
        $out = '# Files: ' . $Resolver->getTable()->getTableRow()['name'] . '.' . $field['name'] . ($id ? ' · row ' . $id : '') . "\n";
        if (!$files) {
            if (isset($args['file'])) {
                throw new errorException('The file ' . MCPMarkdown::scalar($args['file']) . ' is not in the cell: its files — get_value');
            }
            return $out . "no files in the cell\n";
        }
        if (!empty($args['image']) && count($files) > 1) {
            throw new errorException('"image" is for one file: pass "file" (get_value lists the files of the cell)');
        }
        if (empty($args['image'])) {
            $out .= $this->Files->deliverHint();
        }
        foreach ($files as $file) {
            $path = File::getFilePath($file['file'], $Totum->getConfig(), $field);
            $out .= '- ' . MCPMarkdown::esc($file['name'] ?? '') . ' · ' . MCPFiles::size($file['size'] ?? 0) . (!empty($file['mime']) ? ' · ' . $file['mime'] : '')
                . ' · file `' . $file['file'] . '` → ';
            if (!is_file($path)) {
                $out .= "the file does not exist on the disk\n";
            } elseif (!empty($args['image'])) {
                $out .= $this->imageContent($file, $path, (string)$args['image']);
            } else {
                $out .= $this->Files->deliver($path, (string)($file['name'] ?? $file['file']), $file['mime'] ?? null, $args['save_to'] ?? null, count($files) > 1) . "\n"
                    . (empty($field['secureFile']) && !$this->Files->isLocal() ? '  open file (anybody with the link): ' . $this->baseUrl . '/fls/' . $file['file'] . "\n" : '');
            }
        }
        return $out;
    }

    /**
     * The picture in the answer (MCP image content): the preview of the core (_thumb.jpg, 290 px) or the original
     */
    protected function imageContent(array $file, string $path, string $mode): string
    {
        if (!in_array($mode, ['preview', 'full'])) {
            throw new errorException('"image" must be "preview" or "full"');
        }
        if (!File::isImage($file['name'] ?? '')) {
            throw new errorException('The file ' . ($file['name'] ?? '') . ' is not an image (jpg, png, gif, webp): download it without "image"');
        }
        $src = $mode === 'preview' ? $path . '_thumb.jpg' : $path;
        if (!is_file($src)) {
            throw new errorException('The preview of the image is not found: use image "full"');
        }
        if (filesize($src) > MCPFiles::IMAGE_MAX) {
            throw new errorException('The image is bigger than ' . MCPFiles::size(MCPFiles::IMAGE_MAX) . ': use image "preview" or download it without "image"');
        }
        $this->content[] = ['type' => 'image', 'data' => base64_encode(file_get_contents($src)),
            'mimeType' => $mode === 'preview' ? 'image/jpeg' : (trim((string)shell_exec('file -b --mime-type ' . escapeshellarg($src))) ?: 'image/' . File::isImage($file['name']))];
        return ($mode === 'preview' ? 'the preview (290 px wide)' : 'the full picture') . " is in the answer below\n";
    }

    protected function toolUploadFile(Totum $Totum, array $args): string
    {
        if (!is_string($args['name'] ?? null) || trim($args['name']) === '') {
            throw new errorException('Parameter "name" (file name with extension) is required');
        }
        $name = MCPFiles::safeName(trim($args['name']));
        [$token, $url] = $this->Files->newUpload($name);
        $item = $this->json(['upload' => $token]);
        return '# Upload link: ' . $name . "\n"
            . "A file of your machine goes to a file field in 2 steps:\n\n"
            . "1. Send the file to the server — run in your terminal (on your machine):\n"
            . '   `curl -sS -T ' . escapeshellarg('./' . $name) . ' ' . escapeshellarg($url) . "`\n"
            . '   (`./' . $name . '` — replace with the path of the file on your machine). The answer is JSON `{"ok":true,"upload":"' . $token . '",…}`: '
            . "the file is in the temporary storage of the server, not in a field yet.\n"
            . "2. Put it into the field — pass `" . $item . "` as a file of the value of the field:\n"
            . '   edit `{"table":"…","rows":{"<row id>":{"<file field>":[' . $item . ']}}}` — the value is the whole list of the cell: '
            . 'to keep the files that are there now add them as {"file": "…"} (get_value lists them); '
            . 'the same item works in insert_row / add_row / edit_panel / submit_edit, and in "files" of submit_file_upload (a file upload window). '
            . '"name" next to "upload" renames the file. Only now the file is saved to the field (and accept / one file / size are checked).' . "\n\n"
            . 'The link works ' . (MCPFiles::UPLOAD_TTL / 60) . ' min, one file per link, up to ' . Conf::$MaxFileSizeMb . ' MB '
            . '(the web server may limit the size of a request too — HTTP 413). The upload id can be used until the link expires.' . "\n";
    }

    public const EXPORT_MAX_ROWS = 100000;

    /**
     * Export like the buttons of web: csv (csvExport of the core) / xlsx (excelExport: cells as web copies them → ServiceXlsxGenerator);
     * the file comes as linkToFileDownload ("Downloaded files")
     */
    protected function toolExportTable(Totum $Totum, array $args): string
    {
        $format = $args['format'] ?? null;
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            throw new errorException('Parameter "format" must be "csv" or "xlsx"');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false);
        $Table = $Resolver->getTable();
        $visible = $Table->getVisibleFields('web');
        $names = $this->names($args, 'fields');
        if ($names && ($wrong = array_diff($names, array_keys($visible)))) {
            throw new errorException('Fields are not found or access to them is denied: ' . implode(', ', $wrong));
        }
        $names ??= array_keys($visible);
        $data = $Actions->mcpReadTable(0, static::EXPORT_MAX_ROWS, $this->ids($args, 'ids'), true);
        if ($data['allCount'] > static::EXPORT_MAX_ROWS) {
            throw new errorException('More than ' . static::EXPORT_MAX_ROWS . ' rows: narrow them with filters or ids');
        }
        $ids = array_map('intval', array_column($data['rows'], 'id'));
        $title = MCPFiles::safeName(MCPMarkdown::plain($data['f']['tabletitle'] ?? $Table->getTableRow()['title']));
        $Md = (new MCPMarkdown($Table, $data['form'], $Actions->mcpIsWrite()))->setTableFormat($data['f']);

        if ($format === 'csv') {
            $type = ($args['type'] ?? 'full') === 'rows' ? 'rows' : 'full';
            $csv = $Actions->mcpCsvExport($ids, $names, $type);
            $updated = json_decode($Table->getSavedUpdated() ?? '', true)['dt'] ?? date('Y-m-d H:i');
            $Totum->addToInterfaceDatas('files', ['files' => [['name' => $title . '.' . substr((string)$updated, 0, 19) . '.csv', 'type' => 'text/csv',
                'string' => base64_encode($csv)]]]);
            return $Md->header() . "\n**Exported: csv " . $type . ' · ' . count($ids) . " row(s)**\n"
                . ($type === 'full' ? "full — params, filters, rows and footers in the format of the csv import of web (the import itself is not supported by the agent interface)\n" : '');
        }

        $columns = array_values(array_filter($Md->fieldsByCategory('column', $names), fn($f) => !in_array($f['type'], ['button', 'chart'])));
        if (!$columns) {
            throw new errorException('No columns to export');
        }
        $withId = !empty($args['with_id']);
        $rows = [array_merge($withId ? ['id'] : [], array_map(fn($f) => MCPMarkdown::plain($data['f']['fieldtitle'][$f['name']] ?? $f['title']), $columns))];
        foreach ($data['rows'] as $row) {
            $raw = $Table->getTbl()['rows'][$row['id']] ?? [];
            $rows[] = array_merge($withId ? [(string)$row['id']] : [],
                array_map(fn($f) => MCPMarkdown::exportValue($f, $row[$f['name']] ?? null, $raw[$f['name']]['v'] ?? null), $columns));
        }
        $Actions->mcpExcelExport($rows, $title);
        return $Md->header() . "\n**Exported: xlsx · " . count($ids) . ' row(s), ' . count($columns) . " column(s)**\n";
    }

    protected function toolMarkCommentsRead(Totum $Totum, array $args): string
    {
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false);
        $field = (string)($args['field'] ?? '');
        $result = $Actions->mcpMarkCommentsRead($field, $this->ids($args, 'ids'));
        $lines = [];
        foreach ($result as $item => [$count, $unread]) {
            $lines[] = '- ' . ($item === 'params' ? 'params' : 'row ' . $item) . ': ' . $count . ' comment(s), ' . ($unread ? $unread . ' were unread' : 'nothing was unread');
        }
        return '# ' . $Resolver->getTable()->getTableRow()['name'] . '.' . $field . "\n**Comments marked as read**\n" . implode("\n", $lines) . "\n";
    }

    /**
     * Notifications like checkForNotifications of web: active ones whose time has come, by priority; the code of every notification runs
     * like in web and its interface data (text / html / table) is the content of the popup
     */
    protected function toolGetNotifications(Totum $Totum, array $args): string
    {
        $limit = min(max((int)($args['limit'] ?? 20), 1), 100);
        $model = $Totum->getModel('notifications');
        $userId = $Totum->getUser()->getId();
        $rows = $model->getAllPrepared(['<=active_dt_from' => date('Y-m-d H:i:s'), 'user_id' => $userId, 'active' => 'true'], '*',
            '(prioritet->>\'v\')::int, id');
        $postponed = $model->getColumn('active_dt_from', ['>active_dt_from' => date('Y-m-d H:i:s'), 'user_id' => $userId, 'active' => 'true']);
        $out = '# Notifications (' . count($rows) . ' active' . (count($rows) > $limit ? ', shown ' . $limit : '') . ")\n";
        if ($postponed) {
            $dates = array_map(fn($d) => is_string($d) && ($j = json_decode($d, true)) && isset($j['v']) ? $j['v'] : $d, $postponed);
            sort($dates);
            $out .= 'postponed: ' . count($dates) . ' (the next one at ' . MCPMarkdown::scalar($dates[0]) . ")\n";
        }
        if (!$rows) {
            return $out . "no active notifications\n";
        }
        $Table = $Totum->getTable('notifications');
        $codes = $Totum->getModel('notification_codes');
        $ids = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            array_walk($row, function (&$v, $k) {
                if (!Model::isServiceField($k)) {
                    $v = json_decode($v, true);
                }
            });
            $ids[] = (int)$row['id'];
            $out .= "\n---\n## Notification " . $row['id'] . ': ' . MCPMarkdown::plain(MCPMarkdown::scalar($row['title']['v'] ?? ''))
                . ' · since ' . MCPMarkdown::scalar($row['active_dt_from']['v'] ?? '') . ' · priority ' . MCPMarkdown::scalar($row['prioritet']['v'] ?? '') . "\n";
            $before = count($Totum->getInterfaceDatas() ?? []);
            try {
                (new CalculateAction($codes->getField('code', ['name' => $row['code']['v'] ?? null])))
                    ->execAction('code', [], $row, [], $Table->getTbl(), $Table, 'exec', (array)($row['vars']['v'] ?? []));
                $content = $this->openedWindows($Totum, [], array_slice($Totum->getInterfaceDatas() ?? [], $before), []);
                $out .= $content !== '' ? preg_replace('/^\n---\n/', '', $content) : "(no content)\n";
            } catch (errorException|criticalErrorException $e) {
                $out .= 'The content is not available: ' . MCPMarkdown::plain($e->getMessage()) . "\n";
            }
        }
        $one = [$ids[0]];
        return $out . "\n---\nclose a handled notification: notification_update `" . $this->json(['ids' => $one, 'action' => 'deactivate'])
            . '`; postpone it: `' . $this->json(['ids' => $one, 'action' => 'later', 'num' => 1, 'unit' => 'hours']) . "`\n";
    }

    protected function toolSearch(Totum $Totum, array $args): string
    {
        return (new MCPSearch($Totum))->search($args);
    }

    protected function toolSearchClick(Totum $Totum, array $args): string
    {
        return (new MCPSearch($Totum))->click($args);
    }

    protected function toolNotificationUpdate(Totum $Totum, array $args): string
    {
        $action = $args['action'] ?? null;
        if (!in_array($action, ['deactivate', 'later'], true)) {
            throw new errorException('Parameter "action" must be "deactivate" or "later"');
        }
        $units = ['minutes' => 1, 'hours' => 2, 'days' => 3];
        $unit = $args['unit'] ?? 'minutes';
        if (!key_exists($unit, $units)) {
            throw new errorException('Parameter "unit" must be minutes, hours or days');
        }
        $num = max(1, (int)($args['num'] ?? 5));
        $model = $Totum->getModel('notifications');
        $userId = $Totum->getUser()->getId();
        if (!empty($args['all'])) {
            $ids = 'ALL_ACTIVE';
            $affected = $model->getColumn('id', ['<=active_dt_from' => date('Y-m-d H:i:s', time() - 2), 'user_id' => $userId, 'active' => 'true']);
        } else {
            $ids = $this->ids($args, 'ids', true);
            $affected = $model->getColumn('id', ['id' => $ids, 'user_id' => $userId]);
            if ($missing = array_diff($ids, array_map('intval', $affected))) {
                throw new errorException('Notifications are not found (or not yours): ' . implode(', ', $missing));
            }
        }
        $post = ['id' => $ids, 'type' => $action] + ($action === 'later' ? ['num' => (string)$num, 'item' => (string)$units[$unit]] : []);
        (new Actions((new ServerRequest('POST', '/MCP/'))->withParsedBody($post), '/Table/', null, $Totum))->notificationUpdate();
        $affected = array_map('intval', $affected);
        sort($affected);
        return $action === 'deactivate'
            ? '**Notifications closed**: ' . ($affected ? implode(', ', $affected) : 'none') . "\n"
            : '**Notifications postponed** until ' . date('Y-m-d H:i', strtotime('+' . $num . ' ' . $unit)) . ': ' . ($affected ? implode(', ', $affected) : 'none') . "\n";
    }

    protected function toolSubmitFileUpload(Totum $Totum, array $args): string
    {
        $hash = (string)($args['hash'] ?? '');
        $meta = $this->Files->fileUploadData($hash)
            ?? throw new errorException($Totum->getLangObj()->translate('The proposed input is outdated.') . ' The hash works once: press the opening button again');
        $items = is_array($args['files'] ?? null) ? array_values($args['files']) : [];
        if (!$items) {
            throw new errorException('Parameter "files" (list of files) is required');
        }
        if (count($items) > $meta['limit']) {
            throw new errorException($Totum->getLangObj()->translate('Upload limit exceeded') . ': up to ' . $meta['limit'] . ' file(s), nothing is uploaded');
        }
        $files = $this->Files->uploadFiles($items, $meta['type'] === '' ? '*' : $meta['type']);
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, ['hash' => $hash, 'files' => $this->json($files)], true);
        $out = $this->changesAnswer($Resolver, $Actions, $Actions->mcpFilesUpload(), 'Files uploaded: ' . implode(', ', array_column($files, 'name')));
        $this->Files->forgetFileUpload($hash);
        return $out . MCPMarkdown::refreshLine($meta['refresh'] ?? false)
            . "\n**The file upload window is closed**: the hash is used — press the opening button again to upload more.\n";
    }

    /**
     * @param bool $withoutHeader the context panel opened by a button (openContextPanel): the header is already in the answer
     */
    protected function toolGetCell(Totum $Totum, array $args, bool $withoutHeader = false): string
    {
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false);
        $id = !empty($args['id']) ? (int)$args['id'] : null;
        $data = $Actions->mcpCell((string)($args['field'] ?? ''), $id, max(0, (int)($args['log'] ?? 20)));
        $Md = (new MCPMarkdown($Resolver->getTable(), $data['form'], $Actions->mcpIsWrite()))->setTableFormat($data['f']);
        return ($withoutHeader ? '' : $Md->header()) . $Md->cellPanel($data, $id);
    }

    protected function toolClickPanelButton(Totum $Totum, array $args): string
    {
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, ['hash' => (string)($args['hash'] ?? ''), 'index' => (string)($args['index'] ?? '')], true);
        $result = $Actions->mcpPanelButtonClick();
        return $this->changesAnswer($Resolver, $Actions, $result, 'Panel button pressed') . MCPMarkdown::buttonRefreshLine($result['mcpRefresh']);
    }

    protected function toolSubmitButtons(Totum $Totum, array $args): string
    {
        if (!isset($args['index']) || !is_numeric($args['index'])) {
            throw new errorException('Parameter "index" (index of the button) is required');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, ['hash' => (string)($args['hash'] ?? '')], true);
        $result = $Actions->mcpButtonsClick((int)$args['index']);
        return $this->changesAnswer($Resolver, $Actions, $result, 'Button ' . (int)$args['index'] . ' of the buttons window pressed')
            . MCPMarkdown::buttonRefreshLine($result['mcpRefresh'])
            . "\n**The buttons window is closed** (like web after any button): do not press its buttons again with this hash — press the opening button again to choose once more.\n"
            . (!empty($result['mcpClose']) ? "close: true — the window (iframe) where the buttons were opened is closed too: the work in it is finished.\n" : '');
    }

    protected function toolSubmitJsonWindow(Totum $Totum, array $args): string
    {
        if (!key_exists('json', $args)) {
            throw new errorException('Parameter "json" is required');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, ['hash' => (string)($args['hash'] ?? ''), 'json' => $this->json($args['json'])], true);
        return $this->changesAnswer($Resolver, $Actions, $Actions->mcpJsonWindowClick(), 'JSON window button pressed')
            . "\n**The window is closed**: the hash is used — open the window again for one more change.\n";
    }

    protected function toolSubmitInput(Totum $Totum, array $args): string
    {
        if (!key_exists('value', $args)) {
            throw new errorException('Parameter "value" is required');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, ['hash' => (string)($args['hash'] ?? '')], true);
        $result = $Actions->mcpInputClick($args['value']);
        return $this->changesAnswer($Resolver, $Actions, $result, 'Input button pressed')
            . "\n**The input window is closed**: the hash is used — press the opening button again for one more input.\n"
            . (!empty($result['mcpInputClose']) ? "close: true — the window (iframe) where the input was opened is closed too: the work in it is finished.\n" : '');
    }

    protected function toolSubmitEdit(Totum $Totum, array $args): string
    {
        $special = match ($args['special'] ?? null) {
            null, '' => null,
            'reset' => 'setValuesToDefaults',
            'pin' => 'setValuesToPinned',
            default => throw new errorException('Parameter "special" must be "reset" or "pin"'),
        };
        if (!$special && !key_exists('value', $args)) {
            throw new errorException('Parameter "value" (or "special") is required');
        }
        $hash = (string)($args['hash'] ?? '');
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, [], true);
        [$data, $Table, $field, $item] = $Actions->mcpEditFieldData($hash);
        if ($special && (empty($field['code']) || !empty($field['codeOnlyInAdd']))) {
            throw new errorException('reset / pin are only for fields with code (calculated not only on adding)');
        }
        $value = match ($special) {
            null => $field['type'] === 'file'
                ? $this->Files->fieldValue($field, $args['value'], is_array($item[$field['name']]['v'] ?? null) ? $item[$field['name']]['v'] : [], 'edit window')
                : $args['value'],
            /* web pins the current value */
            'setValuesToPinned' => $item[$field['name']]['v'] ?? null,
            default => null,
        };
        if (!$special) {
            $this->checkFormats([$field['name'] => $field], [($data['table']['id'] ?? false ? 'row ' . $data['table']['id'] : 'params') => [$field['name'] => $value]]);
        }
        $result = $Actions->mcpEditFieldSave($hash, $value, $special);
        $out = $this->changesAnswer($Resolver, $Actions, $result,
            'Edit window saved: ' . $Table->getTableRow()['name'] . '.' . $field['name'] . (!empty($data['table']['id']) ? ' of row ' . $data['table']['id'] : '')
            . ($special ? ' (' . $args['special'] . ')' : ''));
        if ($Table->getTableRow()['name'] !== $Resolver->getTable()->getTableRow()['name'] && !str_contains($out, 'other changed tables')) {
            $out .= "\nthe field is in the table " . $Table->getTableRow()['name'] . " — re-read it to see the result\n";
        }
        return $out . "\n**The edit window is closed** (like web after saving).\n";
    }

    protected function toolGetSelectOptions(Totum $Totum, array $args): string
    {
        if (is_string($args['input_hash'] ?? null) && $args['input_hash'] !== '') {
            return '# Options of the input select' . "\n\n"
                . $this->inputSelectOptions($Totum, $this->windowArgs($args), $args['input_hash'], (string)($args['q'] ?? ''));
        }
        if (is_string($args['edit_hash'] ?? null) && $args['edit_hash'] !== '') {
            return '# Options of the edit window' . "\n\n"
                . $this->editOptions($Totum, $this->windowArgs($args), $args['edit_hash'], (string)($args['q'] ?? ''), $args['parent_id'] ?? null);
        }
        if (empty($args['field'])) {
            throw new errorException('Parameter "field" is required (or "input_hash" / "edit_hash" for the select of an input / edit window)');
        }
        $insertHash = $this->insertHash($args);
        $panelHash = $this->panelHash($args);
        [$Resolver, $Actions] = $this->prepare($Totum, $args, $insertHash || $panelHash, [], false);
        $field = $this->visibleField($Resolver, (string)($args['field'] ?? ''));
        /* a Data field with the select of setFieldParams in the format of the cell: the core takes the options from the params (getEditSelect) */
        $byParams = $field['type'] === 'listRow' && MCPMarkdown::hasFieldParams($Resolver->getTable(), $field);
        if (!in_array($field['type'], ['select', 'tree']) && !$byParams) {
            throw new errorException('Field ' . $field['name'] . ' is not of type select/tree');
        }
        if ($byParams && $field['category'] === 'column' && empty($args['id']) && !$insertHash && !$panelHash) {
            throw new errorException('Field ' . $field['name'] . ' is a select by setFieldParams of its cells: parameter "id" (row id) is required');
        }
        if ($byParams && str_contains($field['name'], '/')) {
            return $this->paramsValuesOptions($Totum, $Resolver, $Actions, $field, $args);
        }
        if ($panelHash) {
            if ($field['category'] !== 'column' || $insertHash || !empty($args['row'])) {
                throw new errorException('panel_hash is for column fields of the panel only (without insert_hash and row): set the values with edit_panel');
            }
            /* the core takes the row with the not saved changes of the panel by hash */
            $data = ['field' => $field['name'], 'item' => ['id' => $Actions->mcpPanelId($panelHash, !empty($args['id']) ? (int)$args['id'] : null)],
                'hash' => $panelHash];
        } elseif ($insertHash) {
            if ($field['category'] !== 'column' || !empty($args['id']) || !empty($args['row'])) {
                throw new errorException('insert_hash is for column fields of the insert row only (without id and row): set the values with insert_row');
            }
            /* the core takes the row from the storage of the insert row by hash; item values would be saved to it */
            $Actions->mcpInsertRow($insertHash);
            $data = ['field' => $field['name'], 'item' => [], 'hash' => $insertHash];
        } else {
            $item = is_array($args['row'] ?? null) ? $args['row'] : [];
            if (!empty($args['id'])) {
                $item['id'] = (int)$args['id'];
            }
            $data = ['field' => $field['name'], 'item' => $item];
        }
        try {
            $options = $this->options($Totum, fn($q, $parentId) => $Actions->getEditSelect($data, $q, $parentId), $field['type'] === 'tree',
                (string)($args['q'] ?? ''), $args['parent_id'] ?? null);
        } catch (errorException $e) {
            throw $byParams ? new errorException($e->getMessage() . ' (setFieldParams of this cell does not make it a select with values / codeSelect: '
                . 'see #fieldParams of the cell, write the value of its type)') : $e;
        }
        return '# Options ' . $Resolver->getTable()->getTableRow()['name'] . '.' . $field['name'] . ' (' . $options['count'] . ")\n"
            . ($options['sliced'] ? "not all options are shown — refine q\n" : '')
            . ($options['notComplete'] ? "the tree is not loaded completely: (has children) — get its children with parent_id = key, or search the whole tree with q\n" : '')
            . "\n" . $options['table'];
    }

    /**
     * A virtual column of a dynamic field (name/key) with the select of setFieldParams: the core has no field of this name to calculate
     * the options (getEditSelect) — they are the values of the params of the cell, like the select of web shows them
     */
    protected function paramsValuesOptions(Totum $Totum, MCPTableResolver $Resolver, MCPReadTableActions|MCPWriteTableActions $Actions, array $field, array $args): string
    {
        $id = !empty($args['id']) ? (int)$args['id'] : null;
        if ($this->insertHash($args) || $this->panelHash($args)) {
            throw new errorException('Field ' . $field['name'] . ' is a virtual column of a dynamic field: its options are given for a saved row — pass "id"');
        }
        $cell = $Actions->mcpCell($field['name'], $id, 0)['cell'];
        $params = MCPMarkdown::fieldParams(is_array($cell['f'] ?? null) ? $cell['f'] : []);
        if (($params['type'] ?? null) !== 'select') {
            throw new errorException('The cell ' . $field['name'] . ($id ? ' of row ' . $id : '') . ' is not a select by setFieldParams'
                . ($params ? ' (#fieldParams(' . $params['type'] . '))' : '') . ': write the value of its type');
        }
        if (!is_array($params['values'] ?? null) || !$params['values']) {
            throw new errorException('The select of the cell ' . $field['name'] . ' takes its options by codeSelect: the core calculates them only for real fields, '
                . 'not for a virtual column of a dynamic field — the current value is in get_cell');
        }
        $q = (string)($args['q'] ?? '');
        $search = $q !== '' ? $Totum->getLangObj()->getSearchFunction($q) : null;
        $rows = [];
        foreach ($params['values'] as $key => $value) {
            $title = MCPMarkdown::scalar(is_array($value) ? ($value['title'] ?? '') : $value);
            if (!$search || $search($title)) {
                $rows[] = [MCPMarkdown::esc($key), MCPMarkdown::esc($title) . (is_array($value) && !empty($value['disabled']) ? ' #disabled' : ''), ''];
            }
        }
        return '# Options ' . $Resolver->getTable()->getTableRow()['name'] . '.' . $field['name'] . ' (' . count($rows) . ")\n"
            . "values of setFieldParams of the cell\n\n" . MCPMarkdown::mdTable(['key', 'title', 'parent'], $rows);
    }

    /**
     * Options of a select / select-tree as the web select gets them (cropSelectListForWeb of the core)
     * @param callable $load fn(string $q, string|array|null $parentId): array — the answer of the core for the select
     * @return array{table: string, count: int, sliced: bool, notComplete: bool}
     */
    protected function options(Totum $Totum, callable $load, bool $isTree, string $q, ?string $parentId = null): array
    {
        $search = $q !== '' ? $Totum->getLangObj()->getSearchFunction($q) : null;
        $rows = [];
        $res = [];
        if ($isTree && $search) {
            $nodes = $this->treeSearch($load, $q);
            $titles = array_column($nodes, 'text', 'id');
            foreach ($nodes as $item) {
                if ($search((string)($item['text'] ?? ''))) {
                    $rows[] = $item;
                }
            }
        } else {
            $res = $load($q, $parentId);
            if (key_exists('indexed', $res)) {
                foreach ($res['list'] as $key) {
                    $v = $res['indexed'][$key] ?? [];
                    /* the core searches by q only in long lists (web filters short ones on the client side) */
                    if ($search && !$search((string)($v[0] ?? ''))) {
                        continue;
                    }
                    $rows[] = [MCPMarkdown::esc($key), MCPMarkdown::esc($v[0] ?? '') . (!empty(end($v)) ? ' #deleted' : ''), ''];
                }
            } else {
                $rows = array_values(array_filter($res['list'] ?? [], 'is_array'));
            }
        }
        if ($isTree) {
            $titles ??= array_column($rows, 'text', 'id');
            $rows = array_map(fn($item) => [MCPMarkdown::esc($item['id'] ?? ''), MCPMarkdown::esc($item['text'] ?? '')
                . (!empty($item['state']['disabled']) ? ' #disabled' : '') . (!empty($item['state']['deleted']) ? ' #deleted' : ''),
                (($parent = $item['parent'] ?? '#') === '#' ? '' : MCPMarkdown::esc($parent) . (isset($titles[$parent]) ? ' ' . MCPMarkdown::esc($titles[$parent]) : ''))
                . (!empty($item['children']) ? ' (has children)' : '')], $rows);
        }
        return [
            'table' => MCPMarkdown::mdTable(['key', 'title', 'parent'], $rows),
            'count' => count($rows),
            'sliced' => !empty($res['sliced']),
            'notComplete' => $isTree && !$search && (bool)array_filter($rows, fn($row) => str_ends_with($row[2], '(has children)')),
        ];
    }

    /**
     * Search in a select-tree like web: the core gives for q only the ids of the parents of the found nodes (web loads these branches
     * and searches in them on the client side) — the branches and the top level are loaded here, the caller filters them by q.
     * Parents of the found nodes are kept for the titles in the "parent" column.
     * @return array nodes {id, parent, text, state, children}
     */
    protected function treeSearch(callable $load, string $q): array
    {
        $nodes = [];
        foreach ($load('', null)['list'] ?? [] as $item) {
            $nodes[$item['id']] = $item;
        }
        if ($parents = $load($q, null)['list'] ?? []) {
            foreach ($load('', array_map('strval', $parents))['list'] ?? [] as $children) {
                foreach ($children as $item) {
                    $nodes[$item['id']] = $item;
                }
            }
        }
        return array_values($nodes);
    }

    /* ======================== write tools ======================== */

    protected function toolEdit(Totum $Totum, array $args): string
    {
        $modify = [];
        $reset = [];
        foreach ((array)($args['rows'] ?? []) as $id => $values) {
            if (!is_array($values) || !(int)$id) {
                throw new errorException('Parameter "rows" must be {"row_id": {field: value}}');
            }
            $modify[(int)$id] = $values;
        }
        if (!empty($args['params'])) {
            $modify['params'] = (array)$args['params'];
        }
        foreach ((array)($args['reset']['rows'] ?? []) as $id => $fields) {
            foreach ((array)$fields as $f) {
                $reset[(int)$id][$f] = true;
            }
        }
        foreach ((array)($args['reset']['params'] ?? []) as $f) {
            $reset['params'][$f] = true;
        }
        if (!$modify && !$reset) {
            throw new errorException('Nothing to change: set "params", "rows" or "reset"');
        }

        [$Resolver, $Actions] = $this->prepare($Totum, $args, true);
        foreach ($modify as $id => $values) {
            $this->checkSystemWrite($Resolver, $Actions, $values, $id === 'params' ? null : $id);
        }
        foreach ($modify + $reset as $id => $values) {
            foreach (array_keys($values) as $name) {
                $this->checkFieldRight($Resolver, (string)$name, $id === 'params' ? ['param', 'footer'] : ['column'], 'editable');
            }
        }
        foreach ($modify as $id => $values) {
            $modify[$id] = $this->editLastComments($Resolver, $Actions, $values, $id === 'params' ? null : $id);
            $modify[$id] = $this->writeValues($Resolver, $Actions, $modify[$id], $id === 'params' ? null : $id, $id === 'params' ? 'params' : 'row ' . $id);
        }
        $formatValues = [];
        foreach ($modify as $id => $values) {
            $formatValues[$id === 'params' ? 'params' : 'row ' . $id] = $values;
        }
        $this->checkFormats($Resolver, $formatValues);
        $cells = [];
        foreach ($modify + $reset as $id => $values) {
            $cells[$id] = array_keys(($modify[$id] ?? []) + ($reset[$id] ?? []));
        }
        $Actions->mcpCheckBlocks('edit', [], $cells);
        $warning = $this->checkGroupEdit($Resolver, $cells);

        if (!empty($args['dry_run'])) {
            return $this->editDryRun($Resolver, $Actions, $modify, $reset) . $warning;
        }

        return $this->changesAnswer($Resolver, $Actions, $Actions->mcpEdit($modify, $reset), 'Saved') . $warning;
    }

    /**
     * Values of a write like the forms of web:
     * file fields — kept files of the cell + new files of the agent as tmp files of the user {name, tmpfile} (path / upload / base64 / text);
     * one file / accept are checked like the form (the core does not);
     * data_src of tables_fields — {"setting": value} into the data_src of the field panel (MCPCreator::writeValues)
     * @param int|string|null|false $item cell of the current values: row id / insert hash / panel hash / null — params / false — a new row
     */
    protected function writeValues(MCPTableResolver $Resolver, MCPReadTableActions|MCPWriteTableActions $Actions, array $values,
                                   int|string|null|false $item, string $label): array
    {
        $fields = $Resolver->getTable()->getFields();
        foreach ($values as $name => $value) {
            if (($fields[$name]['type'] ?? null) === 'file') {
                $values[$name] = $this->Files->fieldValue($fields[$name], $value,
                    $item === false ? [] : $Actions->mcpCellFiles((string)$name, $item), $label);
            }
        }
        return $this->Creator->writeValues($Resolver->getTable(), $values,
            $item === false || !key_exists('data_src', $values) ? [] : MCPCreator::flatRow($Actions->mcpItemRow($item)));
    }

    /**
     * Checks of the system tables (tables, tables_fields) before the rights: fixed type, cycles table of a calcs table, tree branch, categories
     * @param int|string|null|false $item row id / insert hash / panel hash / null — params / false — a new row
     * @param bool $final the row is added now (add_row)
     */
    protected function checkSystemWrite(MCPTableResolver $Resolver, MCPReadTableActions|MCPWriteTableActions $Actions, array $values,
                                        int|string|null|false $item, bool $final = false): void
    {
        if ($item === null || !in_array($Resolver->getTable()->getTableRow()['name'], ['tables', 'tables_fields'])) {
            return;
        }
        $isNew = $item === false || (is_string($item) && str_starts_with($item, 'i-'));
        $this->Creator->checkWrite($Resolver->getTable(), $values, $item === false ? [] : MCPCreator::flatRow($Actions->mcpItemRow($item)), $isNew, $final);
    }

    /**
     * Comments: {"edit_last": "text"} — edit the own last comment like web ({value, editLastComment: old text} for the core)
     * @param int|null $id row; null — params
     */
    protected function editLastComments(MCPTableResolver $Resolver, MCPReadTableActions|MCPWriteTableActions $Actions, array $values, ?int $id): array
    {
        foreach ($values as $name => $value) {
            if (is_array($value) && key_exists('edit_last', $value)) {
                if (($Resolver->getTable()->getFields()[$name]['type'] ?? null) !== 'comments') {
                    throw new errorException('edit_last is only for comments fields, not for ' . $name);
                }
                $values[$name] = $Actions->mcpEditLastComment((string)$name, $id, $value['edit_last']);
            }
        }
        return $values;
    }

    /**
     * Like the group edit of web (selected cells):
     * a field without editGroup (batch edit) is changed in one row only — several rows are a heavy operation for the server: error;
     * a field without editGroupMultiColumns is changed together with other fields — its action code may change the other fields: warning
     * @param array $cells [row id | 'params' => [field names]]
     * @return string warning for the answer
     */
    protected function checkGroupEdit(MCPTableResolver $Resolver, array $cells): string
    {
        $fields = $Resolver->getTable()->getFields();
        $rowsByField = [];
        $names = [];
        foreach ($cells as $id => $cellNames) {
            foreach ($cellNames as $name) {
                $name = explode('/', (string)$name)[0];
                $names[$name] = true;
                if ($id !== 'params') {
                    $rowsByField[$name][$id] = $id;
                }
            }
        }
        $errors = [];
        foreach ($rowsByField as $name => $ids) {
            if (count($ids) > 1 && empty($fields[$name]['editGroup'])) {
                $errors[] = '- ' . $name . ' (rows ' . implode(', ', $ids) . ')';
            }
        }
        if ($errors) {
            throw new errorException("Batch edit is off for these fields (field setting \"Batch Edit\", editGroup): change them in one row per call "
                . "and wait for the answer before the next one — several rows at once are a heavy operation for the server. Nothing is saved:\n"
                . implode("\n", $errors));
        }
        if (count($names) < 2) {
            return '';
        }
        $alone = array_keys(array_filter($names, fn($_, $name) => empty($fields[$name]['editGroupMultiColumns']), ARRAY_FILTER_USE_BOTH));
        return $alone
            ? "\nwarning: " . implode(', ', $alone) . ' — changed together with other fields, but the field setting "Batch Edit: With other columns selected" '
            . '(editGroupMultiColumns) is off: an action code of such a field may change other fields, and values set at the same time may be incorrect. '
            . "Check the result; next time change such fields alone (one field per call) or in edit_panel.\n"
            : '';
    }

    protected function editDryRun(MCPTableResolver $Resolver, MCPWriteTableActions $Actions, array $modify, array $reset): string
    {
        $ids = array_keys(array_diff_key($modify + $reset, ['params' => 1]));
        if (count($ids) !== 1 || key_exists('params', $modify) || key_exists('params', $reset)) {
            throw new errorException('dry_run is supported for exactly one row without params');
        }
        $id = $ids[0];
        $data = ['id' => $id];
        foreach ($modify[$id] ?? [] as $f => $v) {
            $data[$f] = ['v' => $v];
        }
        foreach ($reset[$id] ?? [] as $f => $_) {
            $data[$f] = ['v' => null, 'h' => false];
        }
        $Actions->editPost('data', $this->json($data));
        $Actions->mcpResetSelectViewLists();
        $res = $Actions->checkEditRow();
        $Md = $this->markdown($Resolver, $Actions);
        return $Md->header() . "\n**dry_run: not saved**\n" . $Md->rowCard($res['row']);
    }

    /**
     * @param bool $withoutHeader the insert row of an opened window: the table header is already in the answer
     */
    protected function toolInsertRow(Totum $Totum, array $args, bool $withoutHeader = false): string
    {
        [$Resolver, $Actions, $res] = $this->openInsertRow($Totum, $args);
        $Md = $this->markdown($Resolver, $Actions, $res['f']);
        return ($withoutHeader ? '' : $Md->header())
            . $Md->insertRowCard($res['row'], $res['hash'], $this->windowArgs($args), !$this->insertHash($args), $res['panel'], $res['hidden']);
    }

    /**
     * Insert row: opens a new one (without insert_hash) or changes it with data / reset
     * @return array [MCPTableResolver, MCPWriteTableActions, mcpInsertRow result]
     */
    protected function openInsertRow(Totum $Totum, array $args): array
    {
        $data = is_array($args['data'] ?? null) ? $args['data'] : [];
        $reset = $this->names($args, 'reset') ?? [];
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, [], false);
        if ($Resolver->getTable()->getTableRow()['type'] === 'cycles') {
            throw new errorException('A cycles table has no insert row (like web): add a cycle with add_row without data — it is created at once '
                . 'and its first table is opened in the answer; then change its fields with edit');
        }
        $insertHash = $this->insertHash($args);
        $this->checkSystemWrite($Resolver, $Actions, $data, $insertHash ?? false);
        foreach ([...array_keys($data), ...$reset] as $name) {
            $this->checkFieldRight($Resolver, (string)$name, ['column'], 'insertable');
        }
        $this->checkFormats($Resolver, ['insert row' => $data]);
        $data = $this->writeValues($Resolver, $Actions, $data, $insertHash ?? false, 'insert row');
        return [$Resolver, $Actions, $Actions->mcpInsertRow($insertHash, $data, $reset)];
    }

    /**
     * Tree view: the button Add of the top level is hidden (field setting treeHideAddButton / table format hideadd) —
     * like web a row is added only into a folder (self — as a child of a row)
     */
    protected function checkTreeAdd(MCPTableResolver $Resolver, MCPWriteTableActions $Actions, mixed $tree): void
    {
        if (!($type = $Actions->mcpTreeViewType()) || ($tree !== null && $tree !== '' && $tree !== [])) {
            return;
        }
        $Table = $Resolver->getTable();
        if (!empty($Table->getFields()['tree']['treeHideAddButton']) || !empty($Actions->mcpTableFormat()['hideadd'])) {
            throw new errorException('Tree view: the button Add of the top level is hidden (' . (!empty($Table->getFields()['tree']['treeHideAddButton'])
                    ? 'field setting treeHideAddButton' : 'table format hideadd') . '): like web a row is added only into a '
                . ($type === 'self' ? 'row (as its child)' : 'folder') . ' — set the field tree (keys — "## Tree" of read_table)');
        }
    }

    protected function toolAddRow(Totum $Totum, array $args): string
    {
        $data = is_array($args['data'] ?? null) ? $args['data'] : [];
        if ($insertHash = $this->insertHash($args)) {
            return $this->addInsertRow($Totum, $args, $insertHash, $data);
        }
        if ((new MCPTableResolver($Totum, $args))->getTable()->getTableRow()['type'] !== 'cycles'
            && $this->prepare($Totum, $args, false, [], false)[1]->mcpTreeViewType()) {
            /* tree view: web adds rows only through the panel of a new row (insert row) */
            [$Resolver, $Actions, $res] = $this->openInsertRow($Totum, array_diff_key($args, ['data' => 1, 'dry_run' => 1]) + ['data' => $data]);
            if (!empty($args['dry_run'])) {
                $Md = $this->markdown($Resolver, $Actions, $res['f']);
                return $Md->header() . "\n**dry_run: not saved** (tree view: the row is added through the panel of a new row — its insert row below)\n"
                    . $Md->insertRowCard($res['row'], $res['hash'], $this->windowArgs($args), true, true, $res['hidden']);
            }
            return $this->addInsertRow($Totum, $args, $res['hash'], [])
                . "(tree view: like web the row is added through the panel of a new row — the insert row " . $res['hash'] . ")\n";
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, [
            /* empty data for add() means "new row with defaults" */
            'data' => $data || !empty($args['dry_run']) ? $this->json($data) : '',
            'insertAfter' => !empty($args['after_id']) ? (string)(int)$args['after_id'] : null,
            'fields' => '[]',
        ]);
        $this->checkSystemWrite($Resolver, $Actions, $data, false, empty($args['dry_run']));
        foreach (array_keys($data) as $name) {
            $this->checkFieldRight($Resolver, (string)$name, ['column'], 'insertable');
        }
        $prepared = $this->fieldsInsertAfter($Resolver, $Actions, $args, $data, false);
        $this->checkFormats($Resolver, ['new row' => $prepared]);
        if ($data !== ($prepared = $this->writeValues($Resolver, $Actions, $prepared, false, 'new row'))) {
            $Actions->editPost('data', $this->json($prepared));
        }
        $Actions->mcpCheckBlocks('add');
        if (!empty($args['dry_run'])) {
            $res = $Actions->checkInsertRow();
            $Md = $this->markdown($Resolver, $Actions);
            return $Md->header() . "\n**dry_run: not saved**\n" . $Md->rowCard($res['row'], null, 'new row');
        }
        if ($Resolver->getTable()->getTableRow()['type'] === 'cycles') {
            /* cyclesTable::addRow creates the tables of the cycle and (channel web) opens its first table with linkToTable */
            return $this->changesAnswer($Resolver, $Actions, $Actions->add(), 'Cycle added',
                    ['calcstable_cycle_version', ...array_keys(MCPTableResolver::cyclesTableTables($Totum, $Resolver->getTable()))])
                . "\nthe cycle is created with its tables; like web the answer goes into the cycle — its first table is opened below (with the tabs of the cycle).\n";
        }
        return $this->changesAnswer($Resolver, $Actions, $Actions->add(), 'Added');
    }

    /**
     * Saving of the insert row like the save button of web: add by hash — the values come from the storage of the insert row
     */
    protected function addInsertRow(Totum $Totum, array $args, string $insertHash, array $data): string
    {
        if (!empty($args['dry_run'])) {
            throw new errorException('dry_run is not used with insert_hash: insert_row shows the insert row as it is calculated');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, [
            'hash' => $insertHash,
            'data' => '',
            'insertAfter' => !empty($args['after_id']) ? (string)(int)$args['after_id'] : null,
            'fields' => '[]',
        ]);
        $this->checkSystemWrite($Resolver, $Actions, $data, $insertHash, true);
        foreach (array_keys($data) as $name) {
            $this->checkFieldRight($Resolver, (string)$name, ['column'], 'insertable');
        }
        $data = $this->fieldsInsertAfter($Resolver, $Actions, $args, $data, $insertHash);
        $this->checkFormats($Resolver, ['insert row' => $data]);
        $res = $Actions->mcpInsertRow($insertHash, $this->writeValues($Resolver, $Actions, $data, $insertHash, 'insert row'));
        $this->checkTreeAdd($Resolver, $Actions, $res['row']['tree']['v'] ?? null);
        return $this->changesAnswer($Resolver, $Actions, $Actions->add(), 'Added from the insert row')
            . "\n**The insert row is saved and closed**: its insert_hash is not valid any more — open a new one with insert_row for the next row.\n";
    }

    /**
     * Adding to tables_fields: the core reads tableData.afterField (web sends tableData with every add). The fields have no order of rows:
     * like "Insert after" of the field menu of web, after_id is the field (row of tables_fields) after which the new one goes —
     * ord of it + 10 (same table and category), the next fields of the category are moved by the core (TablesFields::setAfterField)
     * @param string|false $insertHash insert row / false — new row
     */
    protected function fieldsInsertAfter(MCPTableResolver $Resolver, MCPWriteTableActions $Actions, array $args, array $data, string|false $insertHash): array
    {
        if ($Resolver->getTable()->getTableRow()['name'] !== 'tables_fields') {
            return $data;
        }
        $tableData = [];
        if (!empty($args['after_id'])) {
            $after = MCPCreator::flatRow($Actions->mcpItemRow((int)$args['after_id']));
            if (!$after) {
                throw new errorException('after_id ' . (int)$args['after_id'] . ' is not a row of tables_fields');
            }
            $new = $data + ($insertHash ? MCPCreator::flatRow($Actions->mcpItemRow($insertHash)) : []);
            foreach (['table_id', 'category'] as $name) {
                if (isset($new[$name]) && (string)$new[$name] !== (string)$after[$name]) {
                    throw new errorException('after_id: the field ' . $after['name'] . ' has ' . $name . ' ' . $after[$name] . ', the new field — ' . $new[$name]
                        . ' (a field goes after a field of the same table and category)');
                }
                $data[$name] ??= $after[$name];
            }
            $tableData['afterField'] = (int)$after['ord'];
            $data['ord'] ??= (int)$after['ord'] + 10;
            $Actions->editPost('insertAfter', null);
        }
        $Actions->editPost('tableData', $tableData);
        return $data;
    }

    protected function toolDeleteRows(Totum $Totum, array $args): string
    {
        $ids = $this->ids($args, 'ids', true);
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, ['delete_ids' => $this->json($ids)]);
        $Actions->mcpCheckBlocks('delete', $ids);
        return $this->changesAnswer($Resolver, $Actions, $Actions->delete(), 'Deleted');
    }

    protected function toolRestoreRows(Totum $Totum, array $args): string
    {
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, ['restore_ids' => $this->json($this->ids($args, 'ids', true))]);
        return $this->changesAnswer($Resolver, $Actions, $Actions->restore(), 'Restored');
    }

    protected function toolDuplicateRows(Totum $Totum, array $args): string
    {
        $ids = $this->ids($args, 'ids', true);
        $replaces = [];
        foreach ((array)($args['replaces'] ?? []) as $id => $values) {
            if (!is_array($values) || !in_array((int)$id, $ids)) {
                throw new errorException('Parameter "replaces" must be {"row_id": {field: value}} for rows of ids');
            }
            $replaces[(int)$id] = $values;
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, [
            'duplicate_ids' => $this->json($ids),
            'data' => $this->json($replaces ?: new \stdClass()),
            'insertAfter' => !empty($args['after_id']) ? (string)(int)$args['after_id'] : null,
        ]);
        $this->checkDuplicateReplaces($Resolver, $ids, $replaces);
        $Actions->mcpCheckBlocks('duplicate', $ids);
        return $this->changesAnswer($Resolver, $Actions, $Actions->duplicate(), 'Duplicated');
    }

    /**
     * Like the dialog of web "Fill in the values for unique fields": without a duplicate code of the table (on_duplicate)
     * the copies need new values of the visible unic columns; replaces — only for them
     */
    protected function checkDuplicateReplaces(MCPTableResolver $Resolver, array $ids, array $replaces): void
    {
        $Table = $Resolver->getTable();
        $unic = array_keys(array_filter($Table->getVisibleFields('web', true)['column'] ?? [], fn($f) => $f['type'] === 'unic'));
        foreach ($replaces as $values) {
            if ($wrong = array_diff(array_keys($values), $unic)) {
                throw new errorException('replaces are only for unique (unic) columns' . ($unic ? ' (' . implode(', ', $unic) . ')' : '') . ', not for: ' . implode(', ', $wrong));
            }
        }
        if (!$unic || preg_match('/^\s*(a\d+)?=\s*:\s*[^\s]/', $Table->getTableRow()['on_duplicate'] ?? '')) {
            return;
        }
        $missing = [];
        foreach ($ids as $id) {
            if ($fields = array_diff($unic, array_keys($replaces[$id] ?? []))) {
                $missing[] = '- row ' . $id . ': ' . implode(', ', $fields);
            }
        }
        if ($missing) {
            throw new errorException("Fill in the values for unique fields of the copies (like the dialog of web): replaces {\"row_id\": {field: value}}. Nothing is duplicated:\n"
                . implode("\n", $missing));
        }
    }

    protected function toolReorderRows(Totum $Totum, array $args): string
    {
        $afterId = key_exists('after_id', $args) && $args['after_id'] !== null ? (int)$args['after_id'] : null;
        $ids = is_null($afterId) ? $this->ids($args, 'ordered_ids', true) : $this->ids($args, 'ids', true);
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true);
        $Actions->mcpCheckBlocks('reorder', $ids);
        return $this->changesAnswer($Resolver, $Actions, $Actions->mcpReorder($ids, $afterId), 'Order saved');
    }

    protected function toolRecalcRows(Totum $Totum, array $args): string
    {
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, ['refreash_ids' => $this->json($this->ids($args, 'ids', true))]);
        return $this->changesAnswer($Resolver, $Actions, $Actions->refresh_rows(), 'Recalculated');
    }

    protected function toolClickButton(Totum $Totum, array $args): string
    {
        $Resolver = new MCPTableResolver($Totum, $args);
        $field = $this->visibleField($Resolver, (string)($args['field'] ?? ''));
        if ($insertHash = $this->insertHash($args)) {
            return $this->clickInsertRowButton($Totum, $args, $field, $insertHash);
        }
        if ($panelHash = $this->panelHash($args)) {
            return $this->clickPanelButton($Totum, $args, $field, $panelHash);
        }
        if ($field['category'] === 'column' && empty($args['id'])) {
            throw new errorException('Field ' . $field['name'] . ' is a column: parameter "id" (row id) or "insert_hash" (insert row) is required');
        }
        $click = ['item' => $field['category'] === 'column' ? (int)$args['id'] : 'params', 'fieldName' => $field['name']];
        if ($checked = $this->ids($args, 'checked_ids')) {
            $click['checked_ids'] = $checked;
        }
        if ($selected = $this->selectedCells($Resolver, $args)) {
            $click['sl'] = $selected;
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, ['data' => $this->json($click)], true);
        $Actions->mcpCheckBlocks('click', [], [$click['item'] => [$field['name']]]);
        $out = $this->changesAnswer($Resolver, $Actions, $this->withCheckedHint($field, $checked, fn() => $Actions->mcpClick()), 'Pressed: ' . $field['name'])
            . $this->afterClickLines($field, $checked);
        if (!empty($field['openContextPanel'])) {
            /* web opens the context panel of the cell after the button (contextmenu) */
            $out .= "\n---\n## Opened context panel (openContextPanel of the button)\n"
                . 'its buttons — click_panel_button with hash and index below' . "\n"
                . $this->toolGetCell($Totum, $this->windowArgs($args) + ['field' => $field['name'], 'id' => $args['id'] ?? null, 'log' => 0], true);
        }
        return $out;
    }

    /**
     * Cells selected with the mouse in web (selectedCells.ids of the page, sent with every button as "sl"): {"field": [row ids]} — cells of rows only,
     * the core gives them to the code as $#slPro {"row id": [fields]} (checks the visibility of the rows and the fields)
     */
    protected function selectedCells(MCPTableResolver $Resolver, array $args): array
    {
        if (!key_exists('selected_cells', $args) || $args['selected_cells'] === [] || $args['selected_cells'] === null) {
            return [];
        }
        if (!is_array($args['selected_cells']) || array_is_list($args['selected_cells'])) {
            throw new errorException('"selected_cells" must be {"field": [row ids]} — the cells of rows selected with the mouse in web');
        }
        $fields = $Resolver->getTable()->getVisibleFields('web');
        $selected = [];
        foreach ($args['selected_cells'] as $name => $ids) {
            if (($fields[$name]['category'] ?? null) !== 'column') {
                throw new errorException('selected_cells: ' . $name . ' is not a visible column — web selects only the cells of rows');
            }
            if ($ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))))) {
                $selected[(string)$name] = $ids;
            }
        }
        return $selected;
    }

    /**
     * The code of a button with $#ids works with the rows checked with checkmarks in web: an error of such a code without checked_ids
     * gets a hint (the code usually asks to check rows in the language of the schema)
     */
    protected function withCheckedHint(array $field, array $checked, callable $click): mixed
    {
        try {
            return $click();
        } catch (errorException|criticalErrorException $e) {
            if ($checked || !$this->usesCheckedRows($field)) {
                throw $e;
            }
            throw new errorException($e->getMessage() . "\n(the code of " . $field['name'] . ' works with the rows checked with checkmarks in web — $#ids: '
                . 'pass them in checked_ids)');
        }
    }

    protected function usesCheckedRows(array $field): bool
    {
        return str_contains(json_encode($field['codeAction'] ?? '', JSON_UNESCAPED_UNICODE), '$#ids');
    }

    /**
     * Web behaviour after a button: closes / hides the window (iframe), clears the checkmarks
     */
    protected function afterClickLines(array $field, array $checked): string
    {
        $out = '';
        if ($checked && !empty($field['uncheckAfterClick'])) {
            $out .= "\nthe checkmarks are cleared after this button (uncheckAfterClick): do not reuse these checked_ids.\n";
        }
        if (!empty($field['closeIframeAfterClick'])) {
            $out .= "\n**The window is closed after this button** (closeIframeAfterClick): the work with this table is finished, do not press it again.\n";
        } elseif (!empty($field['hideIframeAfterClick'])) {
            $out .= "\n**The window is hidden after this button** (hideIframeAfterClick).\n";
        }
        return $out;
    }

    /**
     * Button of the insert row (item = hash like in web): the code works with the insert row via $#ih,
     * the answer shows the insert row after the button
     */
    protected function clickInsertRowButton(Totum $Totum, array $args, array $field, string $insertHash): string
    {
        if (!empty($args['id'])) {
            throw new errorException('Pass either "id" (row) or "insert_hash" (insert row)');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true);
        $checked = $this->ids($args, 'checked_ids');
        $out = $this->changesAnswer($Resolver, $Actions, $this->withCheckedHint($field, $checked, fn() => $Actions->mcpInsertRowClick($insertHash, $field['name'], $checked)),
            'Pressed in the insert row: ' . $field['name']) . $this->afterClickLines($field, $checked);
        $res = $Actions->mcpInsertRow($insertHash);
        return $out . $this->markdown($Resolver, $Actions, $res['f'])->insertRowCard($res['row'], $res['hash'], $this->windowArgs($args), false, $res['panel'], $res['hidden']);
    }

    protected function insertHash(array $args): ?string
    {
        return is_string($args['insert_hash'] ?? null) && $args['insert_hash'] !== '' ? $args['insert_hash'] : null;
    }

    protected function panelHash(array $args): ?string
    {
        return is_string($args['panel_hash'] ?? null) && $args['panel_hash'] !== '' ? $args['panel_hash'] : null;
    }

    /**
     * Button of the edit panel (item = row id + hash of the panel like in web)
     */
    protected function clickPanelButton(Totum $Totum, array $args, array $field, string $panelHash): string
    {
        if ($field['category'] !== 'column') {
            throw new errorException('panel_hash is for column buttons of the row of the panel');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, false, [], true);
        $id = $Actions->mcpPanelId($panelHash, !empty($args['id']) ? (int)$args['id'] : null);
        $Actions->mcpCheckBlocks('click', [], [$id => [$field['name']]]);
        $out = $this->changesAnswer($Resolver, $Actions, $Actions->mcpPanelClick($panelHash, $field['name']), 'Pressed in the panel: ' . $field['name']);
        if ($Actions->mcpIsWrite()) {
            $panel = $Actions->mcpEditPanel($panelHash, $id);
            $out .= $this->markdown($Resolver, $Actions, $panel['f'])->panelCard($panel, $this->windowArgs($args));
        }
        return $out;
    }

    /**
     * @param bool $withoutHeader the panel of an opened window: the header of the table is printed too (the table may differ)
     * @param array $fields / $titles fields and their titles of linkToPanel
     */
    protected function toolEditPanel(Totum $Totum, array $args, bool $fromLink = false, array $fields = [], array $titles = []): string
    {
        $data = is_array($args['data'] ?? null) ? $args['data'] : [];
        $reset = $this->names($args, 'reset') ?? [];
        $panelHash = $this->panelHash($args);
        $id = !empty($args['id']) ? (int)$args['id'] : null;
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, [], !empty($args['save']));
        if ($data && ($panelHash || $id)) {
            $this->checkSystemWrite($Resolver, $Actions, $data, $panelHash ?? $id);
        }
        foreach ([...array_keys($data), ...$reset] as $name) {
            $this->checkFieldRight($Resolver, (string)$name, ['column'], 'editable');
        }
        $this->checkFormats($Resolver, ['row ' . ($id ?? 'of the panel') => $data]);
        if (array_filter($data, fn($v) => is_array($v) && key_exists('edit_last', $v))) {
            $data = $this->editLastComments($Resolver, $Actions, $data, $panelHash ? $Actions->mcpPanelId($panelHash, $id) : $id);
        }
        $data = $this->writeValues($Resolver, $Actions, $data, $panelHash ?? $id ?? false, 'row ' . ($id ?? 'of the panel'));
        $panel = $Actions->mcpEditPanel($panelHash, $id, $data, $reset);
        $Md = $this->markdown($Resolver, $Actions, $panel['f']);
        if (empty($args['save'])) {
            return ($fromLink ? '' : $Md->header()) . $Md->panelCard($panel, $this->windowArgs($args), !$panelHash, $fields, $titles);
        }
        if (!$panel['changed']) {
            throw new errorException('Nothing to save: the panel has no changes');
        }
        [$result, $stored] = $Actions->mcpSavePanel($panel['hash']);
        return $this->changesAnswer($Resolver, $Actions, $result, 'Panel saved: row ' . $panel['id'] . ' (' . implode(', ', array_keys(array_diff_key($stored, ['id' => 1]))) . ')')
            . "\n**The panel is closed**: its panel_hash is not valid any more — open a new panel with edit_panel and id.\n";
    }

    protected function toolSelectSource(Totum $Totum, array $args): string
    {
        $Resolver = new MCPTableResolver($Totum, $args);
        $field = $this->visibleField($Resolver, (string)($args['field'] ?? ''));
        if (!in_array($field['type'], ['select', 'tree']) || empty($field['selectTable'])) {
            throw new errorException('Field ' . $field['name'] . ' has no source table (selectTable)');
        }
        $item = match (true) {
            (bool)$this->panelHash($args) => $this->panelHash($args),
            (bool)$this->insertHash($args) => $this->insertHash($args),
            !empty($args['id']) => (int)$args['id'],
            default => null,
        };
        if ($field['category'] === 'column' && is_null($item)) {
            throw new errorException('Field ' . $field['name'] . ' is a column: pass "id", "insert_hash" or "panel_hash"');
        }
        [$Resolver, $Actions] = $this->prepare($Totum, $args, true, [], true);
        if (is_string($item)) {
            str_starts_with($item, 'e-') ? $Actions->mcpPanelId($item) : $Actions->mcpInsertRow($item);
        }
        $Actions->mcpSelectSource($field['name'], $item, !empty($args['plus']));
        $Md = $this->markdown($Resolver, $Actions);
        return $Md->header() . "\n**Source of " . $field['name'] . ' opened' . (!empty($args['plus']) ? ' (new row)' : '') . "**\n"
            . (!empty($args['plus'])
                ? 'after adding the row to the source table (add_row in the panel below) set its key into ' . $field['name']
                . ' yourself (web does it automatically): edit / insert_row / edit_panel.' . "\n"
                : 'changes of the source row change the titles of the options of ' . $field['name'] . ".\n");
    }

    /**
     * Args of the table for the next calls (table/cycle/sess_hash/filters)
     */
    protected function windowArgs(array $args): array
    {
        return array_intersect_key($args, array_flip(['table', 'cycle', 'sess_hash', 'filters']));
    }
}

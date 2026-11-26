# DOCS.md — documentation of Totum

Markdown documentation of Totum. The same files are the source of the documentation site for people (the interface of Totum opens it),
so they are written for people — agents read them as they are.

- Repo: `docs/`. Server: `https://<host>/docs/DOCS.md`, articles — `https://<host>/docs/<lang>/<section>/<page>.md`
  (e.g. `https://<host>/docs/ru/totum/functions.md`; the folder `docs/` is published as `/docs/` by the relative symlink `http/docs` of the repo).
- MCP: `search_docs` (sections by words) and `read_docs` (a section by ref) read these files on the server — no internet requests needed.

## Structure
| path | content |
|---|---|
| `<lang>/totum/` | reference of Totum: code syntax, functions, field types and settings, table types and settings, interface, roles, cycles, API, services, installation |
| `<lang>/training-course/` | training course: lessons from simple to complex — questions and answers, texts of the videos (see "Pages of `<lang>/training-course/`") |
| `<lang>/how-to/` | how-to: masterclasses, external connections (step by step), knowledge base — questions of users with answers (see "Pages of `<lang>/how-to/`") |
| `<lang>/patterns/` | patterns of building systems: how tables, fields, settings and codes are put together in a big system (tabs of cycles, tree anchors, stored fields, cache by design, indexes, recalculation of the changed rows, mass changes and triggers, data of cycles, trees, process variables, background calculations, autofill, `exec`, load in `listReplace`, interface conventions, navigation and access, tracking of changes, extending) — with the examples of the working B2M platform (see "Pages of `<lang>/patterns/`") |
| `links.md` | numbered links of the texts, one list for all languages |

Languages: `ru` now; `en`, `es` later — with the same page names and anchors, so a link / anchor works in any language.
This file is common for all languages (in English); MCP takes the language of Totum, another one — argument `lang` of `search_docs` / `read_docs`
(`ru` if there are no docs in the language).

## First steps
An agent that connects for the first time:
1. **Working with data** (users' tasks): `user-guide` — what a user does in a table; `interface` — what is where on the page (placements: params, filters, rows, footers).
2. **Building** (creator): `tables` (table types, cycles) → `fields` (field types, settings) → `syntax` (how a code is written) →
   `calculation-sequence` (when what is calculated) → `specific-codes` (code / action / formats in practice) → `functions` by need.
3. Before writing a code — the architectural patterns [ARCHITECTURE.md](ARCHITECTURE.md) (MCP: `read_docs {"ref": "ARCHITECTURE"}`; `~`, short lists in `` json`…` ``, `` str`…` `` and `` math`…` `` right in the parameter, `++` in `` str`…` ``, `dateFormat` / `numFormat` in texts for users, names of lines `dadd` / `nf` / `df`, empty list — `$#lc`, layout of `listReplace`, `var` between actions; they have priority over the other ways of the examples),
   the section of every function used (`read_docs {"function": …}`), the comparisons of values — `comparisons`.
   Before designing tables of a system — the patterns of building systems `patterns/patterns` (MCP: `read_docs {"ref": "patterns/patterns"}`; the list of the patterns with links):
   what lives in a cycle and what in a simple table shown as a tab, which fields are stored (`codeOnlyInAdd`, `notLoaded`) and who recalculates them, what goes to the background.
4. **How it is done in practice** (step by step, with examples): the training course — `search_docs {"q": …, "section": "training-course"}`;
   integrations and solved questions of users — `how-to` (`search_docs` without `section` shows both after the reference).

## Pages of `<lang>/totum/`
Pages marked **long** must be read by sections, not whole.
Titles are given in English; a page has its title in its own language (the first heading `# …`).

### Code
| page | title | what is inside · anchors |
|---|---|---|
| `syntax` **long** | Syntax | the language of codes: where codes are used, `=:` and named lines, references `#field`, `#old.`, `#s.`, `#h.`, `#c.`, `#l.`, `$line`, `$#var`, `$$`, `@table.field`, list items and sections, calling functions, variables (code / global / process), order of calculation of lines and of math, data types and types of storage (one value / list / row), user colors `##name` (`sx-colors`), operators, `cond`, JSON literal `` json`…` `` with values from the code (`sx-json`), concatenation, text inclusion, QROW · `sx-…`, chapters `part-N` |
| `functions` **long** | Functions | every function: syntax, required / optional parameters, result · `fn-<name>`; its examples (a task, the code, the result) · `fn-<name>-examples`; chapters `part-N`: selects, dates, math, strings, lists, rows (associative arrays), logic (`if`, `while`, …), recalculation / insert / change (action functions), windows and scripts (`linkTo…`), formatting (`setFormat`, `setFieldParams`, panels), json, files, system, xml, PRO, Services |
| `comparisons` **long** | Comparison table | how `=`, `!=`, `<`, `>`, `==` compare values of different types: rules, simple values, lists, associative arrays (a reference table of cases) · `sx-compare-…` |
| `specific-codes` | Specifics of codes | code of a field: order of calculation, recalculation of rows, actions from a code, `linkToDataTable`, old values; action code: triggers, order of execution, special syntax, `linkTo…` windows, mail and scripts; formats: special syntax, panel functions, row / table formatting, weak and strong block |
| `calculation-sequence` | Calculation order and recalculation units | order of calculation of fields, units of recalculation, table types by units of recalculation |
| `function-extension` | Custom functions | own PHP functions for codes (`ext…`): placement, additional interface |
| `user-functions` | User functions (spec) | PRO functions in the Totum language from the table `ttm__custom_functions`, called as `spec<Name>(…)`: fields of the table, example, call (case, sections), parameters (`$#name`, required / multiple, not passed — `null`, names, defaults), context of the caller, code editor, checks · `user-functions-…` |
| `pro-databases` | Database access functions | PRO: functions for external databases (`proDb…`), connections of `ttm__external_databases` (`dsn` — a PDO DSN without the password) |

### Structure: tables, fields, relations
| page | title | what is inside · anchors |
|---|---|---|
| `tables` **long** | Table types and settings | table types (simple, cycles, calcs in a cycle, globcalcs, tmp), automatic links of tables in cycles, versions of calcs tables, `tab_` buttons of cycles, action codes in a cycles table; every table setting (the row of `tables`) · `type-…`, `table-settings-<name>` |
| `fields` **long** | Field types | every field type (string, number, select, tree, checkbox, date, button, link, text, comments, file, unic, password, listRow / data, chart) · `type-<type>`; every field setting of the field panel (data_src) · `fields-settings-<name>` |
| `system-tables` | System tables | structure of the system: tables list, table fields, tree, versions of calcs tables and of tables in cycles, settings, settings of users |
| `relational-associations` | Relational associations | selects: value and view, select code, individual select, `#` vs `select`, multiple selects, editing the source from a select, `linkToPanel`, another basefield (`bfield`), select-tree |
| `trees` | Trees | tables shown as a tree (`treeViewType`), formatting of the tree field, indent `tab:` / `#l.tree`, `roots` |
| `prefilter` | Prefilter | filters that limit the rows loaded from the database: what they are for, how they work |
| `data-field-search` | Search by keys in a Data field | `where` and prefilters by the keys of the `row` of a Data (`listRow`) field — `{"ttm__filter": "jsonb", …}`: one value, several keys (AND, `ttm__type: or`), lists inside a key, `ttm__or`, `ttm__interval` (numbers / strings, `sign`), `ttm__strict`, a `row` without `ttm__filter`, indexes · `dfs-…` |
| `duplicate` | Duplicating rows and cycles | duplicating rows and cycles, code on duplicate, `$#duplicatedId` |
| `dynamic-fields` | Dynamic fields | PRO: virtual columns made by a code (`dn=:`), naming; another field type in every row of one field — `setFieldParams` (`dynamic-field-params`) |
| `autofill-tables` | Autofill of tables | videos about filling tables automatically |
| `print-and-csv` **long** | Print and CSV | print templates; full CSV and CSV of rows: format of export / import |

### Interface and users
| page | title | what is inside |
|---|---|---|
| `user-guide` | User basics | work of a user in a table: scrolling, moving the pointer, editing, adding / deleting / duplicating / recalculating rows, copying, filters, sums and sorting, field sets, order of rows, navigation and user menu |
| `interface` | Interface and layout | general view, placements (params, filters, rows, footers), table settings access, navigation tree, view settings, hidden fields, editing field settings from the table, sections (assignment, control, parameters), PRO panels view, kanban, rotated table |
| `roles-and-users` | Roles and users | roles and users tables, role Creator, access to tables and in fields, priority of accesses, fields only for the creator, access to cycles, blocks by roles in codes, system users, password recovery and brute force protection, on/off, user managers, SUDO users, favorites |
| `notifications` | Notifications | notifications table and its mechanics, codes of notifications, adding, a notification as a tmp table, check time |
| `forms` | External forms | PRO quick forms for external users: settings of a form and its fields, parameters, hidden fields from JS |
| `anonymous-tables` | Anonymous tables | tmp tables opened without authorization (user `anonym`) |
| `pro-custom-docs` | Custom user documentation | own help links of the user menu (`ttm__user_documentation`) |
| `pro-css` | CSS customization | small cosmetic CSS of a schema (`h_custom_css`) |
| `custom-interfaces` | Custom interfaces | PRO own pages over Totum (`ttm__interfaces`, `interfacesSwitchedOn` of `Conf.php`): settings and paths of an interface, matching of addresses, authorization and `webuser`, PHP templates in `interfaces/<name>/`, the error template, data from the browser · `ci-…` |
| `user-colors` | User colors | PRO palette `ttm__colors` (light / dark color by name), `##name` in codes and settings, where web resolves it (formats, panelColor, sections, buttons, panels view), dark theme, MCP `styles` · `user-colors-…` |
| `superlang` | Superlang | PRO multilanguage interface: enabling, switching languages, translation keys in titles and codes |

### Automation and integrations
| page | title | what is inside |
|---|---|---|
| `crons` | Scheduled actions | crons table, start conditions, overlay control |
| `daemons` | Daemons | PRO processes with intervals under 1 minute (`ttm__daemons`): trigger code and interval, daemon code |
| `api` | API | REMOTES (calls of the system from outside and back), PRO JSON API, own API module, direct database access |
| `auth-links` | Auth Tokens | links with authorization tokens, tokens for passing from another application |
| `pro-ldap-ad` | LDAP AD | PRO authorization by LDAP / AD: roles mapping, order of authorization, stunnel |
| `openid` | OpenID (SSO) | PRO login through OpenID Connect providers (`ttm__openid`): fields, the flow of a login, `extra_options` (`$#state`; Google nonce, VK ID PKCE), `extra_headers` (headers / posts of the code exchange, `$#code_answer`), `check_code` (user data by `access_token`: Yandex ID, VK ID), users and `ttm__extparams`, `roles_code` (keeping manual roles) · `openid-…` |
| `two-fa` | 2FA | two-factor authorization: settings `h_pro_auth_…` of the table Settings, Conf.php |
| `list-unsubscribe` | List-unsubscribe | unsubscribe header / link of emails (`ttm__list_unsubscribe`) |
| `meilisearch` | MeiliSearch | PRO full-text search (`ttm__search_settings`): install, connecting to a schema, search data of a table, adding tables; search by trigrams of PostgreSQL `pg_trgm` without MeiliSearch (`h_use_trgm_search`, trigram indexes) · `s-trgm` |
| `only-office` | Only Office | PRO editing of files in OnlyOffice: server, possibilities, new file from a file field, versions, install and connection |
| `pro-versioned` | File versions | PRO versions of files of a file field: enabling, timer of a new version, rights, storage of big volumes |
| `srv_connection` | SERVICES Connection | installing the services and connecting Totum to them, logs |
| `srv_using_from_interface` | SERVICES Export, print, preview and import | services in the interface: xlsx export, pdf print, docx / xlsx preview, xlsx import |
| `srv_xlsx_or_docx_generator` | SERVICES XLSX and DOCX generation | generating xlsx from table data and templates, formulas in xlsx, pictures in templates, docx by template, many files, PDF, download and email of generated files |

### Administration
| page | title | what is inside |
|---|---|---|
| `ubuntu` | Installing Totum on Ubuntu 26.04 | installation script, PRO licenses (test license, network check, concurrent connections, users from the console), change of the domain, timezone |
| `update` | Update | updating Totum with the console utility, result, PRO kept fields |
| `export` | Updates and exports | moving schemas by backup, moving parts of solutions (exports): reserved names, creating and loading an export, matches, codes on loading, `bin/totum` for schemas, test → prod |
| `backup` | Backups | backups of the database and of files, restoring |
| `bin-totum` | Console utility bin/totum | commands of `bin/totum` |
| `debugging` | Debugging | global errors, enabling debug, PRO highlighting of fields, debug logs, finding the field with an error |
| `optimization` | Optimization | PHP-FPM, PostgreSQL settings, CPU and RAM load, indexes; PRO custom indexes `ttm__custom_indexes` (BTREE of several fields, GIN of lists) · `custom-indexes` |
| `profiler` | Load profiling | PRO profiling: activation, reading, parameters |
| `proxy` | Installing Totum PRO behind a proxy | installation behind a proxy server |

## Pages of `<lang>/training-course/`
The training course of the site (`/training-course`): a page is a part of the course, in the order of the course.
A lesson is a heading `## <a name="<lesson>">` (the anchor — the address of the lesson on the site `/<lesson>`) with:
- a line with the videos (VK Video, YouTube) and the example in the demo set of the course (`ttm-samples`, `course_sample_only_for_clean_systems`), if any;
- `### <lesson>-qa` Вопросы и ответы — questions of the lesson, `#### <lesson>-qN` a question with its answer (the main text of the lesson);
- `### <lesson>-text` Текст урока (по видео) — the text recognized from the video: what is done in the interface step by step (spoken language);
- `### <lesson>-ai` Дополнительные вопросы — checked answers made by AI by the text of the lesson, `#### <lesson>-aiN`.

All pages of the course except `training-course` are **long**: read a lesson or a question, not a page (`read_docs {"ref": "training-course/codes-base-level"}` lists the lessons).
Links `[text][N]` of the course lead to the reference (`links.md`).

| page | title | lessons |
|---|---|---|
| `training-course` | Training course | what the course is, the masterclasses, the installation, the examples of the course (`ttm-samples`) |
| `creating-simple-tables-and-fields` | Creating simple tables and fields | tables, sections and folders; deleting a table; adding fields, field settings, order, name and placement; number field settings; width; hiding fields, view without technical fields; quick filter; duplicating fields and tables; adding rows and rights to add / change |
| `codes-base-level` | Codes base level | calculation by a code, order of calculation and its error, unit of recalculation, header in simple and calcs tables, functions, `if` and `select`, `where` and `order`, one value and a list (`sum`, `min`, `max`, `count`), `math`, `str`, fixing of calculation, manual values, code only on adding, `$#nd`, `$#ndt`, `$#ntn`, dates, errors of calculation |
| `selects-and-links-between-tables` | Selects and links between tables | select field and how it works, select code, select depending on other fields, crossed-out values, single / multiple, another identifier instead of id, value of a select, context panel and sections of the dropdown, editing the source |
| `table-settings-basic-level` | Table settings basic level | sort field, reverse order, main field, pages, concurrent access, manual sorting of rows |
| `prefilters-basic-level` | Prefilters base level | prefilter field linked to a column, filtering by a select, manual selects in prefilters, interval prefilters, dates |
| `conditional-formatting-basic-level` | Conditional formatting basic level | bold, text and background color, several conditions, blocking and highlighting a row, blocking a field, `text` and `comment`, formatting of the table |
| `action-codes-base-level` | Action codes base level | setting another field on change, several actions in sequence, code of a button and log, adding / deleting rows, recalculating rows, set by values of a column |
| `using-pop-up-windows` | Using pop-up windows | `linkToTable` by a button, hiding header / footer, refresh of the source, closing the window, tmp tables, filling the rows through the header, background calculation in a tmp table, text input window, panel with buttons |
| `cycles-basic-level` | Cycles base level | cycles table and tables in a cycle, automatic links in a cycle, codes to tables of a cycle, label fields, recalculation of the cycles table, access to cycles |
| `roles-and-users-on-the-web` | Roles and users on the web | access to tables (change, view, tree), access to fields, hiding and restoring, duplicating, favorites and dashboards, managing users |
| `codes-advanced-level` | Codes advanced level | comparisons, a list in a list, switching off `where`, no id of the add row, quick lists, a row and its data, lists of associative arrays, name of a field / line by a code, list of lists, going through a list, filtering and sorting lists, manual values, tree level, `while` and `var` / `listReplace`, calling a line with variables, `cond`, value of the previous row, column recalculation, previous value with manual input, `row` and `rowList` |
| `action-codes-advanced-level` | Action codes advanced level | previous value in an action, strong blocks on the server, when the data of an action is taken, cross actions, nested tmp table returning data, `linkToInput` / `linkToButtons` from windows, relative `set`, inserting after a row by the order field, `listReplace` in actions, panels of other tables, file upload window, own form in `linkToInput`, actions on checked rows |
| `prefilters-advanced-level` | Prefilters advanced level | blocked and hidden prefilters, prefilters by a code, a prefilter as a temporary variable |
| `field-and-table-settings-advanced-level` | Field and table settings advanced level | required and regexp, lists and notloaded of a data field, group change, logging, description and hidden text, value as url, duplicating rows, panel, icons, restoring, field sets, date of actuality |
| `cycles-advanced-level` | Cycles advanced level | versions of tables in cycles and their parameters, duplicating a cycle, recalculating a cycle by a code, the open button with full recalculation, access sync between cycles tables, one cycle tables |
| `formatting-advanced-level` | Formatting advanced level | buttons, pictures and texts of the context panel, texts instead of the value, blocking add / delete by the number of rows, hiding fields by conditions, buttons in rows |
| `select-tree` | Select-Tree | select-tree field, tree view on a select-tree |
| `executing-scheduled-action-code` | Executing a scheduled action code | crons, sequence and overlay |
| `printing-and-emailing` | Printing and emailing | print by a template, nested templates, sending email, attachments |
| `notifications-course` | Notifications | notifications, a notification as a tmp table |
| `api-interface` | API interaction | calling a script by a button, calling another server from a code, receiving external requests, authorization in remotes |
| `flexibility-and-sections` | Adaptivity and Sections | sections, adaptivity |

The parts of the site course without texts are not here yet: charts, panels view and rotation, anonymous tables, external forms, update and backup, duplicating schemes, `bin/totum`.

## Pages of `<lang>/how-to/`
How-to of the site (`/workshops-1` and the others): a page is a group, an article is a heading `## <a name="<article>">` (the anchor — the address of the article on the site `/<article>`)
with a line of the videos, if any, and its steps `### <a name="<article>-N">`; an article of the knowledge base is one section: the source (a discussion on GitHub), **Вопрос**, **Ответ**.
`knowledge-base` is **long**: find an article with `search_docs {"q": …, "section": "how-to"}`.

| page | title | articles |
|---|---|---|
| `workshops` | Workshops | masterclasses 1–6: a small project from the beginning to the end (videos, the lessons of the course used, the sample files of `ttm-samples`) |
| `sync` | External connections | Slack through n8n.io, Telegram bot (webhook, remotes, buttons, secret phrase), Yandex translator (IAM token, request), email by IMAP into Totum (fetchmail), Mailcow as the SMTP server |
| `tableau` | Tableau and PowerBI | connecting Tableau to PostgreSQL (settings of PostgreSQL, drivers, sql to the tables of Totum — fields are `jsonb`), Let's Encrypt certificate of PostgreSQL for PowerBI |
| `amoCRM` | amoCRM | connecting Totum to amoCRM: the sample, the integration, remotes and hooks |
| `btx24` | Bitrix 24 | connecting Totum to Bitrix 24 |
| `knowledge-base` | Knowledge base | ~100 questions of users from GitHub discussions with answers: codes (lists, rows, selects, dates, `tryCatch`), tmp tables and windows, cycles (adding, tabs, access, first table), access by roles and to rows, prefilters, formats and panels, files (url, pdf preview, deleting, email), print templates, forms and registration, notifications, remotes and external API, LDAP, Meilisearch, performance and the connection to the database |
| `user-questions` | User questions | questions of users to the Totum assistant with checked answers (one section: **Вопрос**, **Ответ**, no source): `if` / empty values, comments in codes, lists and rows (JSON literal, nested JSON, `listReplace`, `listCut`, `rowAdd`, rounding, pairs, templates, `while` with `iterator`), selects (display, code of a select, multiselects, defaults), cycles (`linkToTable` with `cycle`, recalculation), `set` / `setList` / `insertList`, tmp tables, buttons (own add button, blocking, `$#ids`), dynamic columns, pagination, prefilters, formats and panels (`interlace`, hiding columns, multiline text, `panelHtml`, own JS), files (links, access, base64, xlsx/docx generator, Telegram), external API and SQL databases, sites over Totum, crons |

## Pages of `<lang>/patterns/`
Patterns of building systems on Totum (in Russian, for people and agents): a page is a pattern or a group of close patterns, its sections are
`## <a name="pt-…">` **Задача** (the problem) → **Решение** (tables, fields, settings, codes) → **Пример из B2M** (where and how it is done in the B2M platform, the real names of tables and fields) → **Ловушки** (traps).
They are about the structure of a system; the rules of writing codes are in [ARCHITECTURE.md](ARCHITECTURE.md) — read both. Pages are short: read a whole page.

| page | title | what is inside · anchors |
|---|---|---|
| `patterns` | Patterns of building systems | about the section, the list of the patterns, how B2M is built (items cycles table, data tables of items, stored fields, catalog, tree, tmp tables) · `patterns-…` |
| `cycle-tabs` | Tab in a cycle with hidden prefilters | a simple table shown as a `tab_` of a cycle: a prefilter linked to the column of the cycle, a not linked prefilter-flag `fl_tab` (creator only), `setTableFormat` — `fieldshide`, `tabletitle` by `#s.`, `blockadd` out of the tab; autosubstitution of the linked column; extra parameters of a tab by `var` · `pt-tabs-…` |
| `tree-anchors` | Table with subtables in the tree | an anchor (tree branch of type anchor) opens the main table, the subtables have the branch of the anchor as their place in the tree, the main table lies in a technical folder; `filters` of anchors · `pt-tree-…` |
| `stored-fields` | Stored calculations in a cycles table | summary fields of a row (data of other tables) with `codeOnlyInAdd` recalculated only by `reCalculate` with `field`, `notLoaded` (not read from the database for web in simple / cycles tables), creator only, no group edit; chains of stored fields and their action codes · `pt-stored-…` |
| `table-action-recalc` | Table action code: recalculation of the changed rows | `default_action` with `$#changes` (`added`, `changed`, `reorderedIds`) → owners by one `selectList` → `listUniq` → one `reCalculate`; reacting to certain fields; checks of the whole table; deleted rows — the action of the linked column on delete with `#old` and `procVar` once per owner · `pt-recalc-…` |
| `process-vars` | Process variables | `procVar` with `default` (lazy) — one value for all the rows of a process (formats, codes of many rows); flags between codes: no reaction during a save (flag with `$#nci`), deleting with the owner, manual vs automatic changes, the daemon flag · `pt-proc-…` |
| `background-daemons` | Heavy calculations in the background | a flag-queue field, changes only set the flag (`setList`), the daemon takes one row per run and drops the flag with `exclude`, the heavy calculation in the action of the flag on `false`, flags up the tree; `exclude` vs `silent`; one portion per run, errors of a daemon · `pt-bg-…` |
| `insert-param` | Autofill through h_insert | the list of the field `insert` in the param `h_insert`: visible, given from outside (`linkToDataTable` params), reset by `clear`, compared with `#c.h_insert`; rows of `selectRowList` as values (`#insert[key]`); fixing the list after deleting rows in the table action code · `pt-insert-…` |
| `exec-field-code` | Calling the code of another field by exec | `exec(code: "field_name")` runs the code of the same type of that field in the context of the caller (row, trigger sections); parameters by `var` + `var(default:)`; **no `$#nfv` / `$#nf` / `$#onfv` in the called code** — the fields by name · `pt-exec-…` |
| `listreplace-load` | Load in listReplace | the result of an `action` to the next one by `var`; one `~selectRowList` before `listReplace` + `listFilter` in the `action` instead of a query for every element · `pt-lr-…` |
| `cache-by-design` | Cache by design | what can be calculated at writing is calculated in the action and stored in a field (0.1 s more for a write — big selects many times faster): values for `where` (`all_categories`, `tree_children`), data of a linked row by one `selectRow` into a service field, totals pushed by the source, ready JSON / HTML / search index; ways of storing (code, `codeOnlyInAdd`, action `set`, background) · `pt-cache-…` |
| `indexes` | Indexes | where indexes are needed (simple / cycles tables, fields of `where` of big selects, cached fields, queue flags), usual / composite BTREE / GIN (`ttm__custom_indexes`), **not more than one index per 7–8 columns**, the logic for B2M tables (candidates, not set yet), building blocks writing, finding slow selects · `pt-idx-…` |
| `action-triggers` | Mass changes and a common trigger | the root row changes the child rows by one `setList` with a `procVar` flag — the children skip their reaction, the root does the common action once (`in_stock` of the base SKU); ping-pong of two tables; one technical field (`listCreate` of the watched fields) with the action on change instead of the action in every field; `control` + `$#changes` · `pt-trig-…` |
| `cycles-data-flow` | Data of a cycle in the cycles table | rows of a table in the cycle collected into one footer field, taken by the cycles table by a label field or `select`; the cascade trap — the row of the cycles table is recalculated last: the action by the trigger of the cycles table or data from the source; action on add of a cycles table · `pt-cycdata-…` |
| `tree-view` | Trees | `treeViewCalc` (root to end / end to root), all the descendants in the technical field `tree_children` (`selectTreeChildren`): a branch down by `listAdd`, ancestors up by `where: 'tree_children' = id`; recalculation of old and new parents (`#old`), `#old` on delete; big trees — `roots` by a prefilter, tree view and pagination do not work together · `pt-tree-calc-order`, `pt-tree-children`, `pt-tree-move`, `pt-tree-big` |
| `interface-conventions` | Interface conventions | wide context panel for texts, `angle-down` icon when the cell has a panel, panel buttons are out of the roles of editing (`$#nr` in the condition), colors: `##lightgray` not active by other conditions, `##tomato` + white — important not filled, `##yellowgreen` — switched on; button texts in CAPS (not in special placements), short buttons with an empty text and an icon, icons of tables, `tablecomment` with `@` for `blockadd`, pagination with `**`, escaping of data in `tablehtml` · `pt-ui-…` |
| `access-and-navigation` | Navigation and access | few top sections with the most used default table, the redirect table of a role, tmp tables as dashboards on the main page (HTML / JS in `tablehtml`), tmp tables hidden from the tree for users (not for the creator), checks of the buttons of tmp tables (opened directly with empty params), access to rows of a cycles table by prefilters ≠ access into the cycle (`cycles_access_type`), a button that sets prefilters by `linkToTable` (`fl_sku_search`) · `pt-nav-…` |
| `load-and-actuality` | Load: tracking of changes and daemons | lowered tracking for cycles tables with several users («without tracking with notices»), `off` for tables written by external systems, the header of tables without tracking; daemons with micro intervals can kill a weak server — cheap activation code, portions, `do_it_now`, a switch · `pt-load-…` |
| `extending` | Extending the system | user functions `spec…` for blocks repeated across the system, `execSSH` with `stdin` for global calculations (B2M: ImageMagick), `vars` for escaping, ext functions on PHP (`extSanitize`, `extTidy`, `extSitemap`, `extRedirects`) · `pt-ext-…` |

New pages of every section must be added to these tables (the index of `read_docs` lists the pages by themselves, but not what is inside).

## How to read
- Do not read whole pages: `functions`, `fields`, `tables`, `syntax` are long. Take a section by its anchor — the heading with `<a name="…">`
  and its text up to the next heading of the same level.
  - MCP: `read_docs {"function": "selectList"}`, `{"setting": "codeOnlyInAdd"}`, `{"field_type": "select"}`, `{"table_setting": "deleting"}`,
    `{"ref": "syntax#sx-data-types"}`; a group (`{"ref": "functions#part-6"}`) or a page (`{"ref": "syntax"}`) lists its subsections; `search_docs {"q": "…"}`.
  - Files: `grep -n 'name="fn-selectList"' <lang>/totum/functions.md`, read to the next heading of the same level.
- Numbered links `[text][N]`: the line `[N]: /page#anchor` of `links.md` → page `<lang>/totum/<page>.md`, anchor `<a name="anchor">`
  (`[Code][27]` → `[27]: /fields#fields-settings-code` → `<lang>/totum/fields.md`, `name="fields-settings-code"`; the text of a link is in the language of the page).
  `read_docs` shows them already resolved: `Code (→ fields#fields-settings-code)` — pass the ref to `read_docs`.
- Signatures of the built-in functions (parameters, required, repeatable) are also in `http/js/functions.js` (autocomplete of the code editor),
  user functions `spec…` — the table `ttm__custom_functions` (page `user-functions`).

## Editing
- The files are the site too: keep the anchors `<a name="…">` (one per heading, unique in the page) and the numbered links.
- A new link — a new number in `links.md` (shared by all languages); a link of a page of another section — `/<section>/<page>#anchor`.
  `links.md` must end with a new line: an appended line (`echo … >> links.md`) is glued to the last link otherwise — check `tail -c1 links.md` before appending.
  Do not reuse the numbers of removed links (an old text of the site may still have them).
- A new page — `<lang>/<section>/<page>.md`: the first line `# Title`, headings with anchors; the index of `read_docs` lists the pages by themselves.
- After changes check the links (from `docs/`; no output — everything is fine): every `[N]` of the texts (not in code: `$list[0][1]` is not a link) is defined, every target page / anchor exists
  in every language, no duplicate anchors in a page. A language that is not translated completely shows its missing pages / anchors as broken.
  ```bash
  python3 -c '
  import re,glob,os
  L={m[0]:m[1] for m in re.findall(r"^\[(\d+)\]:\s*(\S+)",open("links.md").read(),re.M)}
  for lang in sorted(d for d in os.listdir(".") if os.path.isdir(d)):
      A={}
      for f in glob.glob(lang+"/*/*.md"):
          n=re.findall(r"<a\s+name=\"([^\"]+)\"",open(f).read()); A[os.path.basename(f)[:-3]]=set(n)
          [print("duplicate anchor",f,x) for x in set(n) if n.count(x)>1]
          T=re.sub(r"`[^`\n]*`","",re.sub(r"^\s*(```|~~~).*?^\s*\1","",open(f).read(),flags=re.S|re.M))
          [print("undefined link",f,x) for x in set(re.findall(r"\]\[(\d+)\]",T)) if x not in L]
      for k,t in L.items():
          p,_,a=t.lstrip("/").replace("/#","#").partition("#"); p=p.strip("/").split("/")[-1]
          if not t.startswith("http") and (p not in A or a and a not in A[p]): print("broken",lang,k,t)'
  ```
  The anchors of the field and table settings (`fields-settings-<name>`, `table-settings-<name>`) must be the names of the settings exactly —
  `read_docs {"setting": …}` / `{"table_setting": …}` and `get_field_settings` look for them by these names.

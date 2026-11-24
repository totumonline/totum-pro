# DOCS.md — documentation of Totum

Markdown documentation of Totum. The same files are the source of the documentation site for people (the interface of Totum opens it),
so they are written for people — agents read them as they are.

- Repo: `docs/`. Server: `https://<host>/docs/DOCS.md`, articles — `https://<host>/docs/<lang>/<section>/<page>.md`
  (e.g. `https://<host>/docs/ru/totum/functions.md`; the folder `docs/` is published as `/docs/` by a symlink of the installer).
- MCP: `search_docs` (sections by words) and `read_docs` (a section by ref) read these files on the server — no internet requests needed.

## Structure
| path | content |
|---|---|
| `<lang>/totum/` | reference of Totum: code syntax, functions, field types and settings, table types and settings, interface, roles, cycles, API, services, installation |
| `<lang>/training-course/` | training course: lessons from simple to complex — questions and answers, texts of the videos (see "Pages of `<lang>/training-course/`") |
| `<lang>/how-to/` | how-to: masterclasses, external connections (step by step), knowledge base — questions of users with answers (see "Pages of `<lang>/how-to/`") |
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
4. **How it is done in practice** (step by step, with examples): the training course — `search_docs {"q": …, "section": "training-course"}`;
   integrations and solved questions of users — `how-to` (`search_docs` without `section` shows both after the reference).

## Pages of `<lang>/totum/`
Pages marked **long** must be read by sections, not whole.
Titles are given in English; a page has its title in its own language (the first heading `# …`).

### Code
| page | title | what is inside · anchors |
|---|---|---|
| `syntax` **long** | Syntax | the language of codes: where codes are used, `=:` and named lines, references `#field`, `#old.`, `#s.`, `#h.`, `#c.`, `#l.`, `$line`, `$#var`, `$$`, `@table.field`, list items and sections, calling functions, variables (code / global / process), order of calculation of lines and of math, data types and types of storage (one value / list / row), user colors `##name` (`sx-colors`), operators, `cond`, JSON literal `` json`…` `` with values from the code (`sx-json`), concatenation, text inclusion, QROW · `sx-…`, chapters `part-N` |
| `functions` **long** | Functions | every function: syntax, required / optional parameters, result · `fn-<name>`; its examples (a task, the code, the result) · `fn-<name>-examples`; chapters `part-N`: selects, dates, math, strings, lists, rows (associative arrays), logic (`if`, `while`, …), recalculation / insert / change (action functions), windows and scripts (`linkTo…`), formatting (`setFormat`, panels), json, files, system, xml, PRO, Services |
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
| `duplicate` | Duplicating rows and cycles | duplicating rows and cycles, code on duplicate, `$#duplicatedId` |
| `dynamic-fields` | Dynamic fields | PRO: virtual columns made by a code (`dn=:`), naming |
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
| `srv_xlsx_or_docx_generator` | SERVICES XLSX and DOCX generation | generating xlsx from table data and templates, pictures in templates, docx by template, many files, PDF, download and email of generated files |

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
| `free-license` | Free license | free PRO license for 2 users |

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
| `user-questions` | User questions | questions of users to the Totum assistant with checked answers (one section: **Вопрос**, **Ответ**, no source): `if` / empty values, lists (JSON literal, `listReplace`, rounding, pairs, templates), selects (display, code of a select, multiselects, defaults), cycles, `set` / `setList` / `insertList`, prefilters, formats and panels, files (links, access, Telegram), external API, crons |

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

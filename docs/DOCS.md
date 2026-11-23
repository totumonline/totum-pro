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
| `<lang>/training-course/` | training course (empty for now: filled by an agent from a Totum system) |
| `<lang>/how-to/` | how-to articles (empty for now: filled the same way) |
| `links.md` | numbered links of the texts, one list for all languages |

Languages: `ru` now; `en`, `es` later — with the same page names and anchors, so a link / anchor works in any language.
This file is common for all languages (in English); MCP takes the language of Totum, another one — argument `lang` of `search_docs` / `read_docs`
(`ru` if there are no docs in the language).

## First steps
An agent that connects for the first time:
1. **Working with data** (users' tasks): `user-guide` — what a user does in a table; `interface` — what is where on the page (placements: params, filters, rows, footers).
2. **Building** (creator): `tables` (table types, cycles) → `fields` (field types, settings) → `syntax` (how a code is written) →
   `calculation-sequence` (when what is calculated) → `specific-codes` (code / action / formats in practice) → `functions` by need.
3. Before writing a code — the section of every function used (`read_docs {"function": …}`), the comparisons of values — `comparisons`.

## Pages of `<lang>/totum/`
Pages marked **long** must be read by sections, not whole.
Titles are given in English; a page has its title in its own language (the first heading `# …`).

### Code
| page | title | what is inside · anchors |
|---|---|---|
| `syntax` **long** | Syntax | the language of codes: where codes are used, `=:` and named lines, references `#field`, `#old.`, `#s.`, `#h.`, `#c.`, `#l.`, `$line`, `$#var`, `$$`, `@table.field`, list items and sections, calling functions, variables (code / global / process), order of calculation of lines and of math, data types and types of storage (one value / list / row), user colors `##name` (`sx-colors`), operators, `cond`, concatenation, text inclusion, QROW · `sx-…`, chapters `part-N` |
| `functions` **long** | Functions | every function: syntax, required / optional parameters, result, examples · `fn-<name>`; chapters `part-N`: selects, dates, math, strings, lists, rows (associative arrays), logic (`if`, `while`, …), recalculation / insert / change (action functions), windows and scripts (`linkTo…`), formatting (`setFormat`, panels), json, files, system, xml, PRO, Services |
| `comparisons` **long** | Comparison table | how `=`, `!=`, `<`, `>`, `==` compare values of different types: rules, simple values, lists, associative arrays (a reference table of cases) · `sx-compare-…` |
| `specific-codes` | Specifics of codes | code of a field: order of calculation, recalculation of rows, actions from a code, `linkToDataTable`, old values; action code: triggers, order of execution, special syntax, `linkTo…` windows, mail and scripts; formats: special syntax, panel functions, row / table formatting, weak and strong block |
| `calculation-sequence` | Calculation order and recalculation units | order of calculation of fields, units of recalculation, table types by units of recalculation |
| `function-extension` | Custom functions | own PHP functions for codes (`ext…`): placement, additional interface |
| `user-functions` | User functions (spec) | PRO functions in the Totum language from the table `ttm__custom_functions`, called as `spec<Name>(…)`: fields of the table, example, call (case, sections), parameters (`$#name`, required / multiple, not passed — `null`, names, defaults), context of the caller, code editor, checks · `user-functions-…` |
| `pro-databases` | Database access functions | PRO: functions for external databases (`proDb…`) |

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
| `two-fa` | 2FA | two-factor authorization: settings `h_pro_auth_…` of the table Settings, Conf.php |
| `list-unsubscribe` | List-unsubscribe | unsubscribe header / link of emails (`ttm__list_unsubscribe`) |
| `meilisearch` | MeiliSearch | PRO full-text search: install, connecting to a schema, search data of a table, adding tables |
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
| `optimization` | Optimization | PHP-FPM, PostgreSQL settings, CPU and RAM load, indexes |
| `profiler` | Load profiling | PRO profiling: activation, reading, parameters |
| `proxy` | Installing Totum PRO behind a proxy | installation behind a proxy server |
| `free-license` | Free license | free PRO license for 2 users |

### Other sections
| section | what is inside |
|---|---|
| `<lang>/training-course/` | training course: building a system step by step (empty for now) |
| `<lang>/how-to/` | how-to articles: solutions of typical tasks with codes (empty for now) |
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
- After changes check the links (from `docs/`; no output — everything is fine): every `[N]` of the texts is defined, every target page / anchor exists
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
          [print("undefined link",f,x) for x in set(re.findall(r"\]\[(\d+)\]",open(f).read())) if x not in L]
      for k,t in L.items():
          p,_,a=t.lstrip("/").replace("/#","#").partition("#"); p=p.strip("/").split("/")[-1]
          if not t.startswith("http") and (p not in A or a and a not in A[p]): print("broken",lang,k,t)'
  ```
  The anchors of the field and table settings (`fields-settings-<name>`, `table-settings-<name>`) must be the names of the settings exactly —
  `read_docs {"setting": …}` / `{"table_setting": …}` and `get_field_settings` look for them by these names.

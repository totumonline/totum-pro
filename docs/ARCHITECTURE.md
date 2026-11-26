# ARCHITECTURE.md — architectural patterns of Totum codes

Patterns that a creator (a person or an agent) must follow when writing Totum codes and building tables.
Read it before development, together with the sections of the functions you use (`read_docs {"function": …}`).
The language of the codes is described in the documentation ([DOCS.md](DOCS.md), page `syntax`); this file is about how to write them well.

- Repo: `docs/ARCHITECTURE.md`. Server: `https://<host>/docs/ARCHITECTURE.md`. MCP: `read_docs {"ref": "ARCHITECTURE"}`, a pattern — `{"ref": "ARCHITECTURE#<anchor>"}`.
- A new pattern — a heading `## <a name="<anchor>"></a> …` (the anchor is unique in the file).
- **Priority.** Examples of the documentation, the training course and the how-to may be written in other ways (they are left as they are).
  When they differ from this file, **the patterns of this file have priority** — write new codes by them.
- One pattern — one section: the rule, why, an example, the traps. Examples use abstract names (`some_table`, `some_field`).
- This file is about writing codes. How a system is put together from tables, fields, settings and codes (tabs of cycles, tree anchors, stored fields and their recalculation,
  process variables, background calculations, autofill, `exec` of a field) — the section `patterns` of the docs with the examples of the B2M platform:
  `docs/ru/patterns/patterns.md`, MCP: `read_docs {"ref": "patterns/patterns"}`.

## <a name="fix-lines"></a> Fix lines with `~` when they are called more than once with the same value
> **ATTENTION: the most common error is not using `~`.** Every time you write or change a Totum code, check the lines that are called
> more than once with the same value and fix them with `~` (except the lines that depend on the element / iteration, see the trap below).

A line of a code is calculated every time it is referenced. If a line is used several times and its value does not change between the calls
(a select, a split of a string, a list built once), fix it with `~` — it is calculated once on the first call and the value is reused.
**This is extra important for performance**: an unfixed select used in several places (or inside an iteration) hits the database every time.

```totum
=: rowListCreate(field: "value" = $list; field: "title" = $list)
	~list: strSplit(str: #some_string; separator: ", ")
```

Trap — **`listReplace` and `while`**: a line that depends on the current element (`$#value_name`, `$#key_name`, the iterator of `while`, a `var` changed in the iteration)
must NOT be fixed — `~` keeps the value of the first iteration for all the following ones. Fix only what is the same for every iteration:

```totum
=: listReplace(list: json`[1, 2, 3]`; action: $item; value: "v")
	item: $#v + $count
		~count: listCount(list: $some_list)
```

Result `[4, 5, 6]` (`some_list` has 3 elements). With `~item: $#v * 10` the result is `[10, 10, 10]` — the first value for all elements.

## <a name="running-totals"></a> Totals of the previous rows — take the previous value with `select` and add the current one (code only on add)
> **ATTENTION: in simple tables and cycles tables a code of a row that selects ALL the previous rows is equal to a CRASH of the system UNDER LOAD** —
> the query becomes SLOWER with every new added row:

```totum
=: listSum(list: $before_list)
	before_list: selectList(table: $#ntn; field: 'amount'; where: 'client' = #client; where: 'paid' = true; where: 'date' < #date)
```

Every row reads all the previous rows of the client: the more rows, the slower every new row, and a recalculation of the table
(a change of the code, `recalculate`) reads the table again for every row — for N rows ~N²/2 values. `~` does not help: the select depends on the row.

Such totals are made by a field that is calculated **from the previous row**: one `select` takes the total of the previous row and the current value is added.
The field has the setting **«Execute the code only on add»** (`codeOnlyInAdd`) — the value is calculated once when the row is added and then it is stored:

```totum
=: $prev + $current
	prev: select(table: $#ntn; field: 'paid_total'; where: 'client' = #client; where: 'date' <= #date; where: 'id' != #id; order: 'date' desc; order: 'id' desc)
	current: if(condition: #paid = true; then: #amount; else: 0)
```

`paid_total` — the total of the paid amounts of the client including the current row (the first row of the client: `$prev` = null, the result = `$current`);
the total before the row (`before` of the example above) = `paid_total` − `current`.
- Trap: the stored totals of the following rows do not change by themselves. When a row is added with a past date, or `amount` / `paid` / `date` / `client`
  of a row is changed, or a row is deleted — recalculate `paid_total` of the following rows of the client (from that date, in the order of `date`, `id`)
  in the action code of the change: `recalculate` with `field: 'paid_total'` executes a code «only on add» again.

## <a name="fewer-queries"></a> As few queries to the database as possible — several fields of one row by one `selectRow` with `~`
**Queries to the database are the most expensive part of a code** — write codes so that they make as few of them as possible.
Several `select` to the same row that take different fields are several queries. Replace them with one `selectRow` with all the fields,
fix it with `~` and take the values from it by `$line[field]`:

```totum
=: str`$company[name] ++ "ИНН" ++ $company[inn] ++ "КПП" ++ $company[kpp]`
	~company: selectRow(table: 'companies'; field: 'name'; field: 'inn'; field: 'kpp'; where: 'id' = #company)
```

Not so — three queries to the same row:

```totum
=: str`$name ++ "ИНН" ++ $inn ++ "КПП" ++ $kpp`
	name: select(table: 'companies'; field: 'name'; where: 'id' = #company)
	inn: select(table: 'companies'; field: 'inn'; where: 'id' = #company)
	kpp: select(table: 'companies'; field: 'kpp'; where: 'id' = #company)
```

- Without `~` every `$company[…]` makes the query again — the gain is lost.
- The same for several rows: several `selectList` with the same `where` — one `selectRowList` with all the fields.

## <a name="short-lists"></a> Short lists — inline `` json`…` ``, not a separate line with `listCreate`
A short constant list or `row` is written right in the parameter with the JSON literal instead of a separate line `listCreate(item: …; item: …)`:

```totum
=: rowListCreate(field: "value" = json`["ph", "temp"]`; field: "title" = json`["pH", "Temperature"]`)
```

```totum
=: if(condition: #status = json`[1, 3, 5]`; then: true; else: false)
```

- Values of the code can be put in the place of values (not keys): `#field`, `$line`, `$#var`, simple math — `` json`[#main_unit, $#nu]` ``
  (page `syntax`, section `sx-json-values`).
- A function call cannot be written inside `` json`…` `` — calculate it in a separate line and put `$line`.
- `listCreate` stays for lists built from many calculated parts or for long lists.

## <a name="str-concatenation"></a> Strings: `str` right in the parameter, `++` instead of spaces inside quotes, `strAdd` with `'…'` for strings with `"`
`` str`…` `` is written **right in the parameter of the function** — not in a separate line that is only passed to the parameter:

```totum
=: set(table: 'some_table'; field: 'some_field' = str`"Заказ №" ++ #order_num`; where: 'id' = #id)
```

Not so:

```totum
=: set(table: 'some_table'; field: 'some_field' = $text; where: 'id' = #id)
	text: str`"Заказ №" ++ #order_num`
```

A separate line is needed only when the string is used in several places (then fix it with `~`, see above).

In the `` str`…` `` sugar join the parts with a space by `++`, do not put the space inside the quoted text (`"Счёт № " + …`).
`+` joins without a space:

```totum
=: str`"Счёт №" ++ #order_num + $#nl`
```

Result for `order_num` = 15: `Счёт № 15` and a new line.

A string with double quotes `"` inside cannot be written in `` str`…` `` — use `strAdd` and wrap such parts in single quotes `'…'`:

```totum
=: strAdd(str: 'Заказ "'; str: #order_num; str: '"')
```

Result: `Заказ "15"`.

## <a name="math-inline"></a> Simple math — `` math`…` `` right in the parameter
Simple math (add, subtract, multiply, divide) is written with `` math`…` `` **right in the parameter of the function** — not in a separate line.
Without `math` the operations of a line are done from left to right (`10 + 10 / 2` = 10); `math` keeps the mathematical order and brackets:

```totum
=: set(table: 'some_table'; field: 'some_sum' = math`#price * #quantity - #discount`; where: 'id' = #id)
```

```totum
=: if(condition: math`#a + #b / 2` > 15; then: true; else: false)
```

- A function call cannot be written inside `` math`…` `` — calculate it in a separate line and put `$line`.

## <a name="user-formats"></a> Dates and numbers for users — `dateFormat` and `numFormat`
When a date or a number (money, amounts, quantities) is put into a text for a user — a message, a notification, a comment, an email, a text of a window —
format it with `dateFormat` / `numFormat`. Do not output the raw values `2026-11-24` / `1234567.5`:

```totum
=: str`"Счёт от" ++ $df ++ "на сумму" ++ $nf`
	df: dateFormat(date: #date; format: "d.m.Y")
	nf: numFormat(num: #sum; decimals: 2; decsep: ","; thousandssep: " "; unittype: " ₽")
```

Result: `Счёт от 24.11.2026 на сумму 1 234 567,50 ₽`.
- Only the text for a person is formatted. Values that are written into fields, compared or passed to other functions stay raw
  (a formatted date / number is a string and is not compared as a date / number).

## <a name="line-names"></a> Names of lines of common functions
Lines with these functions have historical names — use them:

| function | line | several in one code |
|---|---|---|
| `dateAdd` | `dadd` | `dadd_***` |
| `numFormat` | `nf` | `nf_***` |
| `dateFormat` | `df` | `df_***` |

`***` — what the line is about: `dadd_start`, `nf_sum`, `df_created`. `dateAdd` is always `dadd`, also when it subtracts (a negative number):

```totum
=: select(table: 'some_table'; field: 'some_field'; where: 'date' >= $dadd_start; where: 'date' <= $dadd_end)
	dadd_start: dateAdd(date: $#nd; days: -7; format: "Y-m-d")
	dadd_end: dateAdd(date: $#nd; days: 7; format: "Y-m-d")
```

## <a name="empty-list"></a> Check of an empty list — compare with `$#lc`, not with `""`
A list is checked on empty by the comparison with the empty list `$#lc`: `$list = $#lc` / `$list != $#lc`.
The comparison `$list = ""` is a check of the **entry of an empty value into the list** (lists are compared by intersection, page `comparisons`):
it is `true` also for a list that is not empty but has one empty element.

```totum
=: if(condition: $list = $#lc; then: 0; else: $sum)
	~list: selectList(table: 'some_table'; field: 'some_field'; where: 'some_param' = #id)
	sum: listSum(list: $list)
```

Trap: for `` json`[1, "", 2]` `` the condition `$list = ""` is `true`, `$list = $#lc` is `false`.

## <a name="fresh-after-set"></a> After `set` into the current row — the new value by `select` / `@table…`, not by `#field`
`#field` is taken **at the start of the execution of the code** and does not change until its end. When an action code changes the current row
(`set`, `setList`… into `$#ntn` with `where: 'id' = #id`) and the following lines of the chain must use the new value
(of the changed field or of the fields recalculated after the change), read it from the table by `select` or its short form `@table.field[#id]` —
`#field` gives the cached start value:

```totum
=: listCreate(item: $set; item: #status; item: $new_status; item: @some_table.status[#id])
	set: set(table: $#ntn; field: 'status' = "done"; where: 'id' = #id)
	new_status: select(table: $#ntn; field: 'status'; where: 'id' = #id)
```

Result for `status` = `"new"`: `[null, "new", "done", "done"]` — `#status` is still the start value.
- Trap: do not fix with `~` a select line that is called both before and after the `set` — `~` keeps the value of the first call (the old one).

## <a name="listreplace-layout"></a> Layout of `listReplace`
The line that gives the source `list` has an **additional** indent — **two** tabs, the lines of `action` (and their nested lines) — one tab and deeper.
**This is important**: the reader sees at once what is iterated and what is done with every element. Do not write the source line with one tab like an `action`:

```totum
=: listReplace(list: $rows; action: "total" = $total; value: "row")
		rows: selectRowList(table: 'order_items'; field: 'id'; field: 'price'; field: 'quantity')
	total: $#row[price] * $#row[quantity]
```

Result: every row of `rows` gets the key `total` = `price` × `quantity`.

## <a name="listreplace-var"></a> Passing the result of one `action` of `listReplace` to the next one — `var`
When `listReplace` has several `action` executed one after another and the next one needs the result of the previous one,
the previous `action` writes its result into a variable with `var` (the name of the variable = the name of the line), the next one reads it as `$#name`.
The value of `var` is calculated in its own line `var_<name>`:

```totum
=: listReplace(list: json`[1, 2, 3]`; action: $action_row; action: $next_step; value: "v")
	action_row: var(name: "action_row"; value: $var_action_row)
		var_action_row: $#v * 10
	next_step: $#action_row + 1
```

Result `[11, 21, 31]`: the result of `listReplace` is the value of the last `action`.
- Do not fix these lines with `~` (see the trap above) — they change on every element.
- The names of variables must be unique in the code and must not cross the variables of `listReplace` / `while` (`value`, `key`, `iterator`).

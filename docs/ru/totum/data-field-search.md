# Поиск по ключам в поле Данные

## <a name="dfs-general"></a> Общее

В поле типа [Данные][38] обычно хранится `row` (JSON-объект). Найти строки по значениям его ключей можно в `where` функций выборки ([select][225], [selectList][226], [selectRowList][231] и других) и в [префильтре][48], привязанном к полю Данные.

Для этого в условие передается `row` специального вида:

```json
{
  "ttm__filter": "jsonb",
  "ttm__where": {
    "SHIRINA": "40"
  }
}
```

+ `ttm__filter` — всегда `"jsonb"`, включает поиск по ключам.
+ `ttm__where` — условия по ключам (см. ниже).
+ `ttm__strict` — строгое условие: `row`, который должен целиком входить в значение поля (см. «Строгое условие» ниже).
+ `ttm__type` — `"or"`: условия разных ключей объединяются через ИЛИ. По умолчанию — через И.

Должен быть хотя бы один из ключей `ttm__where` / `ttm__strict`.

+ Оператор `=` выбирает строки, подходящие под условие, `!=` — все остальные.
+ Работает в [простых таблицах][88] и [таблицах циклов][89] — их строки хранятся в базе данных. В [расчетных таблицах цикла][1] и [временных таблицах][34] такой поиск не работает.
+ Для префильтра: поле фильтра типа Данные привязывается к колонке Данные, а значение фильтра — такой же `row`. Например, его можно передать в `filter` функции [linkToTable][305].

Примеры ниже — на таблице, в поле Данные `data_field` которой лежат такие значения:

| id | data_field |
|---|---|
| 1 | `{"VES": "23", "FASAD": ["Каменноугольный", "Минвата"], "KORPUS": ["Такое вот значение", "Чугунный"], "VYSOTA": "50", "GLUBINA": "20", "SHIRINA": "80"}` |
| 2 | `{"VES": "30", "RUCHKI": "true", "SHIRINA": "40"}` |
| 3 | `{"VYSOTA": "50", "SHIRINA": "105"}` |
| 4 | `{"FASAD": ["Каменноугольный"], "KORPUS": ["Чугунный"], "VYSOTA": "60", "SHIRINA": "80"}` |
| 5 | `{"FASAD": "Каменноугольный", "KORPUS": ["Чугунный"], "VYSOTA": "60", "SHIRINA": "80"}` |

## <a name="dfs-value"></a> Одно значение

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"SHIRINA": "40"}}`)

// Результат: [2]
```

+ Число и строка с числом считаются одним значением: `"40"` и `40` найдут и `"SHIRINA": "40"`, и `"SHIRINA": 40`.
+ Значение может приходить из кода — на месте значения в [json``][701] можно писать `#field`, `$line`, `$#var`: `` json`{"ttm__filter": "jsonb", "ttm__where": {"SHIRINA": #h_width}}` ``.
+ Строки без этого ключа не выбираются.

## <a name="dfs-and"></a> Несколько ключей — И

Условия разных ключей объединяются через И:

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"VYSOTA": "60", "KORPUS": ["Чугунный"]}}`)

// Результат: [4, 5]
```

С `"ttm__type": "or"` — через ИЛИ:

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__type": "or", "ttm__where": {"VES": "30", "SHIRINA": "105"}}`)

// Результат: [2, 3]
```

## <a name="dfs-list"></a> Внутри ключа список

Если в ключе хранится список, условие тоже задается списком — строка подходит, если ее список содержит **все** перечисленные элементы:

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"FASAD": ["Каменноугольный"]}}`)

// Результат: [1, 4]
```

Форма условия должна совпадать с тем, как значение хранится в поле:

+ список `["Каменноугольный"]` не находит строку 5, где в `FASAD` строка `"Каменноугольный"`;
+ строка `"FASAD": "Каменноугольный"` находит только строку 5 — списки, где есть этот элемент, она не находит.

Если ключи условия задаются кодом (в [json``][701] ключи вычислять нельзя), `row` собирается через [rowCreateByLists][276]. Значение-список при этом оборачивается еще одним списком: в `values` по одному элементу на ключ, и элемент ключа `FASAD` — сам список:

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = $jsonb)
	jsonb: rowCreate(field: "ttm__filter" = "jsonb"; field: "ttm__where" = $search_row)
		search_row: rowCreateByLists(keys: json`["FASAD"]`; values: json`[$list_list]`)
			list_list: json`["Каменноугольный"]`

// Результат: [1, 4]
```

## <a name="dfs-or"></a> Одно из значений — ttm__or

`ttm__or` со списком значений: значение ключа равно одному из них (сравнение как строк, `30` и `"30"` — одно значение):

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"SHIRINA": {"ttm__or": ["40", 105]}}}`)

// Результат: [2, 3]
```

`ttm__or` со списком списков — для ключей, в которых хранится список: строка подходит, если ее список содержит все элементы хотя бы одного из списков:

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"FASAD": {"ttm__or": [["Минвата"], ["Чугунный"]]}}}`)

// Результат: [1]
```

## <a name="dfs-interval"></a> Интервал — ttm__interval

```json
"SHIRINA": {
  "ttm__interval": {
    "type": "numbers",
    "sign": "<=",
    "value": ["40", "80"]
  }
}
```

+ `value` — нижняя и верхняя границы. Нижняя всегда включается: `>=`.
+ `sign` — сравнение с верхней границей: по умолчанию `<` (`F1 <= значение < F2`), `"<="` — `F1 <= значение <= F2`.
+ `type` — `"numbers"` сравнивает как числа, любое другое значение (например, `"string"`) — как строки, по алфавиту.

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"SHIRINA": {"ttm__interval": {"type": "numbers", "value": ["40", "80"]}}}}`)

// Результат: [2] — 80 не входит

=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"SHIRINA": {"ttm__interval": {"sign": "<=", "type": "numbers", "value": ["40", "80"]}}}}`)

// Результат: [1, 2, 4, 5]

=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__where": {"SHIRINA": {"ttm__interval": {"sign": "<=", "type": "string", "value": ["1", "2"]}}}}`)

// Результат: [3] — как строки "105" лежит между "1" и "2"
```

> При `"type": "numbers"` во всех строках таблицы значение этого ключа должно быть числом (или ключа не должно быть). Если хотя бы в одной строке там текст или список, выборка завершится ошибкой `One of number fields of table … contains non-numeric data`.

`ttm__or` и `ttm__interval` работают только для ключей из латинских букв, цифр, `_` и `-`. Для других ключей (например, с кириллицей или пробелами) условие ищется как обычное значение и ничего не находит.

## <a name="dfs-strict"></a> Строгое условие — ttm__strict

`ttm__strict` — `row`, который должен целиком входить в значение поля. Значения сравниваются вместе с их типом: строка `"40"` не равна числу `40`.

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__strict": {"VES": "30", "SHIRINA": "40"}}`)

// Результат: [2]

=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"ttm__filter": "jsonb", "ttm__strict": {"SHIRINA": 40}}`)

// Результат: [] — в поле "40" строкой
```

`ttm__strict` можно передать вместе с `ttm__where` — условия объединяются так же, как ключи `ttm__where` (через И, с `"ttm__type": "or"` — через ИЛИ).

## <a name="dfs-prefilter"></a> В префильтре

Тот же `row` передается в значение фильтра, привязанного к полю Данные:

```totum
=: linkToTable(table: $#ntn; filter: 'fl_prefiltr' = json`{"ttm__filter": "jsonb", "ttm__where": {"PARAM_1": "R", "PARAM_2": ["D"]}}`)
```

Длинные условия удобно держать [текстовым включением][577] и разбирать [jsonExtract][319]:

~~~totum
=: linkToTable(table: $#ntn; filter: 'fl_prefiltr' = $jsonPrepared)
	jsonPrepared: jsonExtract(text: $json)

```json:json
{
  "ttm__filter": "jsonb",
  "ttm__where": {
    "PARAM_3": {
      "ttm__interval": {
        "sign": "<=",
        "type": "numbers",
        "value": ["10", "30"]
      }
    }
  }
}
```
~~~

## <a name="dfs-without-filter"></a> Без ttm__filter

Если передать в `where` поля Данные `row` без `ttm__filter`, ключи объединяются через **ИЛИ**, а `ttm__or`, `ttm__interval`, `ttm__strict` не работают:

```totum
=: selectList(table: $#ntn; field: 'id'; where: 'data_field' = json`{"VES": "30", "SHIRINA": "105"}`)

// Результат: [2, 3]
```

Список без `ttm__filter` ищет строки, в списке которых есть хотя бы один из элементов.

## <a name="dfs-indexes"></a> Индексы

Обычный индекс по полю из настройки таблицы [Индексы][115] для такого поиска не подходит: он ускоряет сравнение значения поля целиком, а не поиск внутри его `row`.

Для поиска по ключам нужен GIN-индекс по полю Данные — он создается в PRO в таблице [пользовательских индексов][685] (`ttm__custom_indexes`, тип `GIN`). Им ускоряются условия на значения, списки, `ttm__strict` и `ttm__or` со списком списков — они выполняются проверкой вхождения jsonb (`@>`). `ttm__interval` и `ttm__or` со списком значений сравнивают значение ключа — GIN-индекс на них не работает.

# Коды продвинутый уровень

## <a name="how-do-comparisons-work-in-codes"></a> Как работают сравнения в кодах?

### <a name="how-do-comparisons-work-in-codes-qa"></a> Вопросы и ответы

#### <a name="how-do-comparisons-work-in-codes-q1"></a> Где используются сравнения?

Например в параметрах `where` и `condition`.

#### <a name="how-do-comparisons-work-in-codes-q2"></a> Какое может быть сравнение?

+ `=` — равно или пересечение.

+ `!=` — не равно или отсутствие пересечения. Набирается как `!` + `=`.

+ `>` — больше.

+ `<` — меньше.

+ `>=` — больше или равно. Набирается как `>` + `=`.

+ `<=` — меньше или равно. Набирается как `<` + `=`.

+ `==` — полностью равно для списков, ассоциированных массивов и списков ассоциированных массивов. Набирается как `=` + `=`.

+ `!==` — полностью неравно для списков, ассоциированных массивов и списков ассоциированных массивов. Набирается как `!` + `=` + `=`.

#### <a name="how-do-comparisons-work-in-codes-q3"></a> Можно ли сравнивать число со строкой?

Да. Они будут сравниваться как числа в том случае если в числе, которое представлено в качестве строки есть только цифры без пробелов, а в качестве разделителя дроби используется `.`.

#### <a name="how-do-comparisons-work-in-codes-q4"></a> Как работает сравнение одного значения со списком?

`true` будет если в списке есть хотя бы один такой элемент.

#### <a name="how-do-comparisons-work-in-codes-q5"></a> Как работает сравнение списка со списком?

Сравнение по `=` работает по пересечению. Если у списков есть хотя бы один одинаковый элемент, то возвращается `true`.

#### <a name="how-do-comparisons-work-in-codes-q6"></a> Как работает отрицательное сравнение для списков?

`true` возвращается — во втором списке нет ни одного элемента из первого.

#### <a name="how-do-comparisons-work-in-codes-q7"></a> Важен ли порядок элементов при простом сравнении?

Нет. Если списки сравниваются по `=` то:

```totum

=: if(condition: $list1 = $list2; then: true; else: false)

list1: listCreate(item: 1; item: 2; item: 3)
list2: listCreate(item: 3; item: 1; item: 2)

// Результат true
```

#### <a name="how-do-comparisons-work-in-codes-q8"></a> Работают ли сравнения больше и меньше для списков?

Нет — относительные сравнения `больше`, `меньше` и прочие для списков не работают.

#### <a name="how-do-comparisons-work-in-codes-q9"></a> Как провести полное сравнение?

Полное сравнение это двойное равно `==`.

Полное сравнение вернет `true` если сравниваемые значения полностью одинаковы с учетом порядка элементов.

Для `row` порядок ключей неважен.

#### <a name="how-do-comparisons-work-in-codes-q10"></a> Что такое пустое значение?

Это отсутствие каких либо символов, чисел или букв или спецсимволов.

В тотум обозначается двумя идущими подряд двойными кавычками — `""`.

#### <a name="how-do-comparisons-work-in-codes-q11"></a> Ноль равен пустому значению?

Нет. `0` и `""` это неравне элементы.

#### <a name="how-do-comparisons-work-in-codes-q12"></a> Пустой список равен пустому значению?

Да — `""` равна `$#lc`.

#### <a name="how-do-comparisons-work-in-codes-q13"></a> Что вернет сравнение Списка с пустым значением внутри с пустым значением?

`""` равна списку с пустым значением `["",2,"A"]` тк это пересечение.

#### <a name="how-do-comparisons-work-in-codes-q14"></a> Как проверить, что перед нами именно пустой список?

Чтобы удостовериться, что перед нами список без единого элемента надо сравнивать с `$#lc`:

```totum

=: if(condition: $value = $#lc; then: true; else: false)

value: json`[]`

// Результат true

```

### <a name="how-do-comparisons-work-in-codes-ai"></a> Дополнительные вопросы

#### <a name="how-do-comparisons-work-in-codes-ai1"></a> Работают ли сравнения больше и меньше для списков?

Нет, относительные сравнения `больше`, `меньше` и прочие для списков не работают.

## <a name="how-do-I-check-if-one-list-enters-into-another"></a> Как проверить вхождение одного списка в другой?

### <a name="how-do-I-check-if-one-list-enters-into-another-qa"></a> Вопросы и ответы

#### <a name="how-do-I-check-if-one-list-enters-into-another-q1"></a> Пример: пример на вхождение

```totum

//Входят ли все значения list1 в list2

=: if(condition: $list1 == $cross; then: true; else: false)

	cross: listcross(list: $list1; list: $list2)

~list1: json`[1,2,4]`

list2: json`[1,3,2,5,4]`

```

Мы выполняем полное сравнение `list1` с остатком от пересечения с `list2`.

## <a name="how-to-turn-off-where"></a> Как по условиям выключить where

### <a name="how-to-turn-off-where-qa"></a> Вопросы и ответы

#### <a name="how-to-turn-off-where-q1"></a> Пример: пример использования ALL в where

Чтобы выключить `where` в него нужно передать в качестве значения сравнения `"*ALL*"`.

Например если `if` идет в `then` то учитываем `where`, а если в `else`, то берем все значения:

```totum

=: select(table: 'table'; field: 'field'; where: 'id' = $if)

if: if(condition: #value != 0; then: #value; else: "*ALL*")

```

## <a name="the-add-row-has-no-id"></a> У строки добавления нет id

### <a name="the-add-row-has-no-id-qa"></a> Вопросы и ответы

#### <a name="the-add-row-has-no-id-q1"></a> Пример: Пример случаев, в которых это может привести к разным результатам кодов при добавлении и последующего пересчета

Строка добавления еще не записана в базу данных — поэтому у нее нет `#id`. Это важно тк если вы в строке добавления используете код ссылающийся на `#id` вы можете столкнутся с непредвиденным для себя поведением:

Если поле вычисляется кодом и редактируется в строке добавления и **отключен** `код только при добавлении` — значение в добавленной строке встанет в зафиксированное, тк после добавления оно пересчитается и будет отличаться от рассчитанного при добавлении.

Если поле вычисляется кодом и редактируется в строке добавления и **включен** `код только при добавлении` — значение в добавленной строке будет добавлено с учетом того, что `#id` был равен `""`:

```totum

= : str`"NUM -" ++ #id`)

// Без код только при добавлении
// Результат в строке добавления: NUM -
// Результат после добавления: NUM - [hand]

// С кодом только при добавлении
// Результат в строке добавления: NUM -
// Результат после добавления: NUM -

```

Поэтому не рекомендуется использовать привязку к `id` для кодов в таблицах в которых пользователи могут добавлять строки вручную.

Когда вы добавляете строку через функцию `insert` это **также актуально** — если вы используете `код только при добавлении` то при его выполнении `id` будет пустым!.

Если все-таки нужна и строка добавления и `id` после добавления, то надо добавить действие на добавление строки:

+ Если включен `код только при добавлении`:

	```totum
	=: recalculate(table: $#ntn; where: 'id' = #id; field: $#nf)

	// Код-действия выполняется после создания id

	```

+ Если выключен `код только при добавлении` и поле `редактируемое при добавлении`:

	```totum

	=: clear(table: $#ntn; where: 'id' = #id; field: $#nf)

	// Код-действия выполняется после создания id

	```
Эти варианты не решают проблему если у вас на этом поле завязаны коды последующих полей.

> Поэтому лучшим вариантом будет сконструировать код без привязки к `id` добавляемой строки!

## <a name="how-to-create-lists-quickly"></a> Как быстро создавать списки

### <a name="how-to-create-lists-quickly-qa"></a> Вопросы и ответы

#### <a name="how-to-create-lists-quickly-q1"></a> С какого ключа начинаются списки?

Списки начинаются с `0`.

#### <a name="how-to-create-lists-quickly-q2"></a> Как получить первый элемент списка?

Если мы хотим взять первый элемент списка — `$list[0]`.

В `[]` указывается номер элемента для списков или ключ для row — `$row[key]`.

Если у вас `rowlist` и вам нужно взять значение из первой строки из колонки `title` — `$rowlist[0][title]`.

Если у вас наименования колонок в `row` или `rowlist` обозначены не по английски, то надо заключать их в кавычки — `$rowlist[0]["Представление"]`.

#### <a name="how-to-create-lists-quickly-q3"></a> Как создать список из нескольких одинарных значений?

1. Функцией [listCreate][255]:

```totum

=: listCreate(item: 1; item: 2; item: 3)

```

2. Быстрым сахаром `json`:

```totum

=: if(condition: #field_1 = json`[1,2,3]`; condition: #field_2 = json`["banana","apple"]`; then: true; else: false)

```

+ `[]` — обозначают список.

+ `,` — разделитель элементов.

+ `""` — строки обязательно заключать в кавычки.

Если вы ошиблись в формате, например поставили `;` вместо `,` то при расчете кода будет ошибка:

	Parsing error in [1:3]. Expected ',' or ']' while parsing array.

	Из которой нам понятно, что в списке ожидается , или закрывающая ], а у нас там что-то другое.

Чем хорош `json` — его можно использовать прямо внутри функции, но сложно читается если там много значений.

Пожалуйста, если у вас в этом небольшом списке больше 3-4 значений — делайте через `listCreate`.

#### <a name="how-to-create-lists-quickly-q4"></a> Работу какой функции заменяет сахар json``?

Функцию [listCreate][255].

#### <a name="how-to-create-lists-quickly-q5"></a> Можно ли использовать json внутри других функций?

Да.

## <a name="how-do-I-take-a-row-from-a-table-and-then-retrieve-data-from-it"></a> Как взять строку из таблицы и потом получить из нее данные?

### <a name="how-do-I-take-a-row-from-a-table-and-then-retrieve-data-from-it-qa"></a> Вопросы и ответы

#### <a name="how-do-I-take-a-row-from-a-table-and-then-retrieve-data-from-it-q1"></a> Как взять сразу несколько полей одним запросом из другой таблицы?

Давайте рассмотрим такой код:

```totum

=: if(condition: $select_1; condition: $select_2; condition: $select_3; then: 10; else: "")

select_1: select(table: 'table'; field: 'field_1'; where: 'id' = #num)

select_2: select(table: 'table'; field: 'field_2'; where: 'id' = #num)

select_3: select(table: 'table'; field: 'field_3'; where: 'id' = #num)

```

В этом коде у нас есть 3 обращения к одной и той же строке таблицы `table`, но к разным полям. Гораздо эффективнее будет взять сразу три поля одним обращением, запомнить их и использовать повторно. Итак делаем:

```totum

=: if(condition: $select[field_1]; condition: $select[field_2]; condition: $select[field_3]; then: 10; else: "")

~select: selectRow(table: 'table'; field: 'field_1'; field: 'field_2'; field: 'field_3'; where: 'id' = #num)

```

При помощи `selectRow` мы берем сразу несколько полей из строки.

`~` используем, что бы результат был запомнен при первом выполнении и последующие два `condition` повторно не ходили в `table`.

Результатом `selectRow` является `row` у которого есть 3 значения — `field_1`, `field_2` и `field_3`. Что бы взять их значения в `condition` мы используем `[]`.

#### <a name="how-do-I-take-a-row-from-a-table-and-then-retrieve-data-from-it-q2"></a> Можно ли в selectRow передать список полей кодом?

Да, есть параметр `fields`, куда мы можем передать автоматически сформированный список полей.

Например если мы хотим взять значения всех колонок, то мы можем их получить из таблицы `Состав таблиц`:

```totum

=: selectRow(table: 'table'; fields: $sel; where: 'id' = #num)

	sel: selectList(table: 'tables_fields'; field: 'name'; where: 'table_name' = "table"; where: 'category' = "column")

```

#### <a name="how-do-I-take-a-row-from-a-table-and-then-retrieve-data-from-it-q3"></a> Как обратиться к элементу строки, которую мы получили кодом?

Через `[]` — `$select[key]`.

В квадратных скобках могут быть использованы все стандартные обращения тотум:

```totum

e1: $select[$value]
	value: "field_1"

e2: $select[#value]

// Значение поля value — field_2

e3: $select[$#nf]

e4: $select[$$row]
	row: "key"
		key: "field_3"

~select: selectRow(table: 'table'; field: 'field_1'; field: 'field_2'; field: 'field_3'; where: 'id' = #num)

```

#### <a name="how-do-I-take-a-row-from-a-table-and-then-retrieve-data-from-it-q4"></a> Какова разница по нагрузке между несколькими select и selectRow

Происходит разное количество обращений к базе данных. Таким образом однократное обращение `selectRow` выигрывает по скорости.

#### <a name="how-do-I-take-a-row-from-a-table-and-then-retrieve-data-from-it-q5"></a> Будет ли выигрыш по скорости работы если забыть поставить ~ ?

Нет. Если не зафиксировать значение при первом обращении количество обращений к базе данных будет таким же как и при использовании нескольких `select`.

## <a name="what-are-associated-array-lists"></a> Что такое списки ассоциированных массивов?

### <a name="what-are-associated-array-lists-qa"></a> Вопросы и ответы

#### <a name="what-are-associated-array-lists-q1"></a> Что берет selectRowList и как выглядит результат его работы?

Если [selectRow][230] берет несколько колонок из **одной** строки, то [selectRowList][231] берет **несколько** строк и несколько колонок.

Несколько строк `list` `+` несколько колонок `row` `=` `rowlist`

id | field_1 | field_2
-- | ------- | ----
0 | 100 | a
1 | 200 | b
2 | 300 | c

Первым обращение идет к строке, а потом к колонке. Если мы хотим из этого `rowlist` взять значениее `200` то:

```totum

=: $rowlist[1][field_1]

rowlist:

```

#### <a name="what-are-associated-array-lists-q2"></a> Как обратиться к конкретной строке и что в этом случае придет как ответ?

Если обратиться только к строке `rowlist` то мы получим `row`:

id | field_1 | field_2
-- | ------- | ----
0 | 100 | a
1 | 200 | b
2 | 300 | c

```totum

=: if(condition: $row[field_1] = 200; then: true; else: false)

	row: $rowlist[1]

		rowlist: "..."

// Результат true

```

#### <a name="what-are-associated-array-lists-q3"></a> Как взять колонку из списка строк?

Если мы хотим получить колонку, то надо использовать `[[]]`.

Результатом такого обращения будет `list` из всех значений в колонке:

id | field_1 | field_2
-- | ------- | ----
0 | 100 | a
1 | 200 | b
2 | 300 | c

```totum

=: $rowlist[[field_2]]

		rowlist: "..."

// Результат ["a","b","c"]

```

## <a name="defining-a-field-name-by-code-defining-a-line-of-code-by-code"></a> Определение названия поля кодом, определение строки кода кодом?

### <a name="defining-a-field-name-by-code-defining-a-line-of-code-by-code-qa"></a> Вопросы и ответы

#### <a name="defining-a-field-name-by-code-defining-a-line-of-code-by-code-q1"></a> Можно ли в функциях определять таблицу или поле кодами?

Конечно:

```totum

=: select(table: $tableIf; field: 'field'; where: 'id' = #num)

tableIf: if(condition: #type = 1; then: 'table_1'; else: 'table_2')

```

#### <a name="defining-a-field-name-by-code-defining-a-line-of-code-by-code-q2"></a> Как использовать $$ для ветвления?

`$$` начинает выполнять строку кода, название которой определила другая строка.

Как это можно использовать для ветвления. Например в поле `type` содержатся разные значения селекта в зависимости от которх нам надо взять разные `select`:

```totum

=: $$type

		type: #type

	0: select(table: 'table_1'; field: 'field_1'; where: 'id' = #num)
	1: @table_2.field_2
	2: 0

```

## <a name="a-list-of-lists-and-turning-it-into-a-list"></a> Список списков и превращение его в список?

### <a name="a-list-of-lists-and-turning-it-into-a-list-qa"></a> Вопросы и ответы

#### <a name="a-list-of-lists-and-turning-it-into-a-list-q1"></a> Перечислите несколько способов получения списка списков

1. Возьмем `selectList` по полю Селект со `множественными` значениями.

2. Возьмем колонку из `$rowlist[[lists]]` в которой в каждой строке списки.

#### <a name="a-list-of-lists-and-turning-it-into-a-list-q2"></a> Как быстро превратить список списков в один список, соединив их последовательно?

Для этого используется [listTrain][329] — он склеивает списки друг за другом в один список.

id | field
---| ---
0  | `["a","b","c"]`
1  | `["d","e","f"]`
2  | `["h","j","c"]`

Например такой код по этой таблице:

```totum

=: listTrain(list: $sel)

	sel: selectList(table: $#ntn; field: 'field'; order: 'id' asc)

// Результат: ["a","b","c","d","e","f","h","j","c"]

```

#### <a name="a-list-of-lists-and-turning-it-into-a-list-q3"></a> Как превратить список в текст с переносами?

Есть похожая по названию на [listTrain][329] функция — [listJoin][262].

Она делает другое действие — превращает список в текст.

Например, если вам нужно список взятый через `selectList` текстом отправлять на печать, то можно это сделать при помощи [listJoin][262].

В параметр `str` можно передать клей — что-то чем будут склеиваться элементы списка. Если мы хотим, что бы в получившемся тексте каждый следующий элементы был с новой строки:

```totum

=: listJoin(list: $list; str: $#nl)

list: selectList(table: 'table'; field: 'field'; where: 'status' = "sold")

```

#### <a name="a-list-of-lists-and-turning-it-into-a-list-q4"></a> Как текст с разделителями превратить в список?

Есть обратная функция [strSplit][249], которая текст превращает в список, ориентируясь на `separator`.

Например у нас есть значение `23-4`, которое мы получили внутри цикла через:

```totum
=: str`$#nci + "-" + #id`

```

Те первая цифра у нас в этом значении — это номер цикла и нам нужно получить этот номер:

```totum

=: $split[0]

split: strSplit(str: #value; separator: "-")

```

А если нужен номер строки:

```totum

=: $split[1]

split: strSplit(str: #value; separator: "-")

```

## <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values"></a> Как последовательно перебрать список и дополнить его или перезаписать  значения?

### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-qa"></a> Вопросы и ответы

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q1"></a> Как перебрать список при помощи listReplace?

Достаточно часто есть задача в которой у вас есть `list` и вам нужно последовательно обработать каждый его элемент и получить новый `list` с обработаннми элеменами.

Для этого используем функцию [listReplace][486].

```totum

=: listReplace(list: $startlist; action: $action; value: "value")

		startlist: selectList(table: 'table'; field: 'num'; where: 'status' = 4)

	action: select(table: 'table_2'; field: 'price'; where: 'num' = $#value)

```

[listReplace][486] берет стартовый `list` и для каждого его элемента выполняет `action`.

Мы можем в `value` задать переменную, в которой будет хранится значение элемента `list`, который выполняется в текущий момент.

Те `action` будет вызван столько раз, сколько было элементов в списке.

В результате мы получим новый список с таким же количеством элементов, но с другими значениями — значениями, которые мы рассчитали в `action`.

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q2"></a> Код в action будет выполнен для каждой строки?

Да. Для каждого элемента списка.

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q3"></a> Как в коде вызванном в action узнать номер обрабатываемой строки?

У [listReplace][486] есть параметр `key` в котором можно указать имя переменной в которую будет передан номер обрабатываемой строки.

Если мы хотим вернуть `""` для первых 5 строк:

```totum

=: listReplace(list: $startlist; action: $action; value: "value"; key: "key")

		startlist: selectList(table: 'table'; field: 'num'; where: 'status' = 4)

	action: if(condition: $#key <= 4; then: ""; else: $sel)

	// помните, что нумерация списков начинаются с 0

		sel: select(table: 'table_2'; field: 'price'; where: 'num' = $#value)

```

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q4"></a> Как в коде вызваном в action использовать значение обрабатываемой строки?

У вас в параметре `value` должно быть задано имя переменной в которую будет передано значение. И к этой переменной можно обратиться как `$#value_name_param`.

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q5"></a> На что надо обратить внимание, если у нас в одном коде несколько listReplace?

Проверяйте, что у вас не пересекаются имена переменных.

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q6"></a> Можно ли добавить элемент с ключом, если мы обрабатываем список единичных значений?

Нет, `action` с ключом вида `action: "key" = $value` доступен для `rowlist`.

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q7"></a> Как добавить или заменить элемент с ключом, если мы обрабатываем rowList?

```totum

=: listReplace(list: $startlist; action: "price" = $action; value: "value")

		startlist: selectRowList(table: 'table'; field: 'num'; field: 'price'; where: 'status' = 4)

	action: $#value[price] * $kf
		kf: select(table: 'table_2'; field: 'kf'; where: 'num' = $#value[num])

```

Здесь мы берем `rowlist` и корректируем значения в колонке `price`. обратите внимание, что в value попдает значение строки списка `startlist`, а для `rowlist` значение строки это `row` — поэтому обращение в `$#value` должно дополнятся нужним ключом (колонкой) — `$#value[price]`.

Если в `action` мы задаем имеющийся в `rowlist` ключ, то он будет перезаписан. Если мы укажем ключ, которого нет в стартовом `rowlist`, то будет добавлена еще одна колонка с таким ключом.

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q8"></a> Как создать основу для перебора при помощи listNumberRange?

Иногда нам нужно создать список основу, по которому мы будем делать перебор. Для этого подходит [listNumberRange][273].

Например создадим список дней от 1 до 31:

```totum

=: listNumberRange(min: 1; max: 31; step: 1)

```

Если для дальнейшей обработки нужен не `list`, а `rowlist`:

```totum

=: rowListCreate(field: "range" = $range)

range: listNumberRange(min: 1; max: 31; step: 1)

```

#### <a name="how-do-I-sequentially-go-through-the-list-and-complete-it-or-overwrite-the-values-q9"></a> Как упростить если требуется произвести однотипное математическое действие над всеми элементами списка?

Если над всеми элементами списка надо произвести однотипную математическую операцию, то для этого есть более простая функция [listMath][271].

```totum
=: listMath(list: $list; operator: "*"; num: 3)
    list: listCreate(item: 1; item: 2; item: 11; item: 21)

// Результат: [ 3, 6, 33, 63]

````

## <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays"></a> Как отфильтровать и сортировать список или список ассоциированных массивов?

### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-qa"></a> Вопросы и ответы

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q1"></a> Как сортировать list?

Например, чтобы обеспечить последовательнй порядок значений в списке, сложенном из двух списков:

```totum

=: listSort(list: $list; type: "string"; direction: "asc")

	list: listAdd(list: $list_1; list: $list_2)

		list_1: selectList(table: 'table_1'; field: 'unical_number'; where: 'status' != "")
		list_2: selectList(table: 'table_2'; field: 'unical_number'; where: 'status' != "")

// Обратите внимание, что в type надо указать тип значений, а в direction направление сортировки.

```

Такой случай возможен, но избыточен:

```totum

=: listSort(list: $list; type: "number"; direction: "asc")

	list: selectList(table: 'table'; field: 'unical_number'; where: 'status' != "")

// Здесь сначала работает база данных, а потом web-сервер

```

Лучше так:

```totum

=: selectList(table: 'table'; field: 'unical_number'; where: 'status' != ""; order: 'unical_number' asc)

// Здесь работает только база данных

```

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q2"></a> Как сортировать rowlist?

В этом случае есть два дополнительных параметра. В `key` мы указываем что используем `item`, а в `item` — какой именно.

```totum

=: listSort(list: $rowlist; type: "number"; direction: "asc"; key: "item"; item: "price")

		rowlist: selectRowList(table: 'table'; field: 'num'; field: 'price'; where: 'status' = 4)

```

Но на самом деле можно указать наименоватие `item` прямо в параметре `key`:

```totum

=: listSort(list: $rowlist; type: "number"; direction: "asc"; key: "price")

		rowlist: selectRowList(table: 'table'; field: 'num'; field: 'price'; where: 'status' = 4)

```

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q3"></a> Как отфильтровать list?

Очень похожая на сортировку функция [listFilter][268].

```totum

=: listFilter(list: $list; key: "value" > 1000)

	list: selectList(table: 'table'; field: 'price')

```

В `key` для `list` можно выбрать, фильтровать по значениям — `value` или по ключам — `key`.

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q4"></a> Как отфильтровать rowlist?

Указываем в `key` колонку, по которой будем фильтровать:

```totum

=: listFilter(list: $rowlist; key: "price" > 1000)

		rowlist: selectRowList(table: 'table'; field: 'num'; field: 'price'; where: 'status' = 4)

```

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q5"></a> Можно ли отфильтровать список по нескольким условиям?

Это делается последовательно:

```totum

=: listFilter(list: $filter_1; key: "type" = 2)

	filter_1: listFilter(list: $rowlist; key: "price" > 1000)

		rowlist: selectRowList(table: 'table'; field: 'type'; field: 'price'; where: 'status' = 4)

```

Или используя множественный параметр `key` (этот вариант быстрее):

```totum

=: listFilter(list: $rowlist; key: "price" > 1000; key: "type" = 2)

		rowlist: selectRowList(table: 'table'; field: 'type'; field: 'price'; where: 'status' = 4)

```

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q6"></a> Сохраняются ли ключи list при его сортировке?

Нет. Получается новый `list` у которого ключи идут от `0`.

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q7"></a> Сохраняются ли ключи list при его фильтрации?

Нет. Получается новый `list` у которого ключи идут от `0`.

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q8"></a> В каких случаях важно использовать skip: true при работе с rowList?

Если у вас неоднородный rowlist и в какой-то из строк нет ключа по которому осуществляется фильтрация, то функция вернет ошибку.

`skip: true` сообщает, что ошибку возвращать не надо — надо исключить эту строку из интервала и продолжить дальше.

#### <a name="how-do-I-filter-and-sort-a-list-or-a-list-of-associated-arrays-q9"></a> Можно ли отсортировать или отфильтровать row?

Да. В этом случае сохраняются ключи. В случае `sort` они выстраиваются в нужном порядке, а в случае с `filter` остаются только те, которые соответсвуют условиям.

Если вы просто хотите удалить некоторые ключи из `row` и знаете их, то используйте [rowKeysRemove][282].

## <a name="getting-information-about-manual-values-tree-level-selections"></a> Получение информации о ручных значениях, уровне дерева, селектах

### <a name="getting-information-about-manual-values-tree-level-selections-qa"></a> Вопросы и ответы

#### <a name="getting-information-about-manual-values-tree-level-selections-q1"></a> Как определить зафиксировано ли значение в ячейке?

Возможно получить признак фиксирования значения для полей с кодами — `true` или `false` используя `#h.fieldname`.

#### <a name="getting-information-about-manual-values-tree-level-selections-q2"></a> Как взять расчетное значение, а не зафиксированное?

Для зафиксированных полей можно получить расчетное значение через `#c.fieldname`.

#### <a name="getting-information-about-manual-values-tree-level-selections-q3"></a> Как определить уровень для дерева?

Обращение `#l.name_field` может быть использовано для любого поля [селект-дерево][37] — возвращает уровень вложенности значения в поле.

## <a name="how-do-while-and-var-work-replacing-with-listReplace"></a> Как работает while и var? Замена на listReplace

### <a name="how-do-while-and-var-work-replacing-with-listReplace-qa"></a> Вопросы и ответы

#### <a name="how-do-while-and-var-work-replacing-with-listReplace-q1"></a> Как работает var?

[var][285] — это функция получения значения переменной, присваивания значения переменной и создания переменной со значением по умолчанию.

Возврат значения — всегда при вызове [var][285].

Присвоить значение переменной — нужно новое значение передать в параметр `value`:

```totum

=: var(name: "count"; value: $#lc)

// Присвоили count пустой список
// Тк возврат значения происходит всегда при вызове var, то результатом этого кода будет пустой список

```

Получить значение переменной в коде используя быструю переменную:

```totum

=: $#count

```

Но если такая переменная не была определена ранее — небыло ни одного действия записывающего в нее значение, то такой код ругнется на несуществующую переменную!

Что бы этого не произошло используется параметр `default`:

```totum

=: var(name: "count"; default: $#lc)

// Код попытается взять значение count, увидит, что переменной нет и вернет значение default

```

#### <a name="how-do-while-and-var-work-replacing-with-listReplace-q2"></a> В каких пределах работает переменная определенная в var?

Переменная доступна только в текущем коде при вычислении конкретной ячейки.

#### <a name="how-do-while-and-var-work-replacing-with-listReplace-q3"></a> Расскажите подробнее про while

[while][284] может выполнятся и в кодах, возвращая в качестве результата последнее действие.

Полный вид [while][284] такой:

```totum

=: while(preaction: ; condition: ; limit: ; action: ; postaction: ; iterator: )

```

+ `preaction` – действия, которые выполняются один раз до всех условий и последующих действий.

+ `condition` — условия.

+ `limit` — лимит выполняемых операций — сейчас расскажем подробнее.

+ `action` — действия, которые выполняются по кругу, пока `condition` выполняется в `true`.

+ `postaction` — действия, которые выполняются один раз, когда `condition` выполнился в `false` или закончился `limit`. Но только в том случае, если был выполнен хотя бы один `action`.

+ `iterator` — наименование переменной в которую передается номер итерации. Номера начинаются с `0`.

Вообще в totum эффективнее использовать [listReplace][486] для перебора списков. Рассмотрим такой пример:

```totum

=: while(preaction: $zero; limit: 30; action: $set; iterator: "iter")
	zero: var(name: "count"; value: $#lc)
set: var(name: "count"; value: $plus)
	plus: listAdd(list: $#count; item: $i)
		i: dateAdd(date: $#nd; days: $days)
			days: $#iter * -1

```

Мы здесь вычисляем список дат от текущей на 30 дней назад.

Как это работает: `while` начинает выполнятся и видит `preaction`, который надо выполнить до всех условий и действий.

В этом `preaction` мы создаем переменную с пустым списком.

Дальше есть `limit` равный 30 — те надо 30 раз повторить `action`. Номер итерации будет передаваться в переменную `"iter"` — это указано в параметре `iterator`.

Начинает выполнятся `action`, он вызывает переменную `count` на запись и записывает в нее дополненный `list` из листа, который в ней уже содержался и новой даты.

Дата вычисляется методом — текущая дата минус количество дней согласно номеру итератора. На первом цикле минус `0` дней, на втором минус `1` день и так `30` раз.

Когда сработает `limit` функция вернет последнее вполненное действие — это будет список из 30 значений с датами.

**Дополним этот пример таким условием** – остановится если мы в отсчете назад достигли первого числа месяца:

```totum

=: while(preaction: $zero; condition: $dateformat != 1; limit: 30; action: $set; iterator: "iter")
	zero: var(name: "count"; value: $#lc)
	dateformat: dateFormat(date: $i; format: "j")
	// j — это день месяца без ведущего 0
set: var(name: "count"; value: $plus)
	plus: listAdd(list: $#count; item: $i)
		i: dateAdd(date: $#nd; days: $days)
			days: $#iter * -1

```

`while` будет выполнять итерацию за итерацией пока не выйдет `limit` или пока `condition` не выполнится в `false`. Как только добавляемая в список дата будет иметь день равный `1` то цикл остановится.

Обратите внимание, что первый день месяца в итоговый результат не попадет. Что бы попал, нам надо усложнить условия в `condition`:

```totum

=: while(preaction: $zero; condition: $dateformat != $lastday; limit: 30; action: $set; iterator: "iter")
	zero: var(name: "count"; value: $#lc)
	dateformat: dateFormat(date: $i; format: "j")
	~lastdaynumber: dateFormat(date: $lastday; format: "j")
		lastday: dateAdd(date: $firstday; days: -1)
			firstday: dateFormat(date: $#nd; format: "Y-m-01")
	// j — это день месяца без ведущего 0
set: var(name: "count"; value: $plus)
	plus: listAdd(list: $#count; item: $i)
		i: dateAdd(date: $#nd; days: $days)
			days: $#iter * -1

```

Мы получаем номер последнего дня предыдущего месяца прибавив минус `1` день к первому дню текущего месяца. И тк нам нужно сделать это только один раз — фиксируем `~`.

Если поставить тильду в любое другое место этого кода (кроме `zero`) — он поломается!

#### <a name="how-do-while-and-var-work-replacing-with-listReplace-q4"></a> Какое значение возвращает while в кодах?

Значение последнего `action` или `postaction`, если они есть.

#### <a name="how-do-while-and-var-work-replacing-with-listReplace-q5"></a> Почему лучше все, что укладывается в listReplace надо делать через listReplace?

`listReplace` кратно проще в чтении связанного с ним кода.

Чего не может `listReplace` — это остановится по условию, он всегда будет идти до конца списка.

## <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it"></a> Как оптимизировать выполнение одного и того же кода с передачей в него переменных?

### <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it-qa"></a> Вопросы и ответы

#### <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it-q1"></a> Как переиспользовать части кода в рамках одного кода?

В кодах возможно вызвать строку, передав в нее и всем строкам дальше переменные. Это же является еще одним методом создания переменных и указания им стартовых значений.

Например сравним 2 значения взятые одним селектом:

```totum

=: if(condition: $sum{var: "num" = 1} = $sum{var: "num" = 2}; then: true; else: false)

sum: listSum(list: $select)
	select: selectList(table: 'table'; field: 'field'; where: 'num' = $#num)

```

Этот код в `condition` два раза вызывает один и тот-же `select`, но передает ему разные значения переменной и по результату это аналогично:

```totum

=: if(condition: $sum_1 = $sum_2; then: true; else: false)

sum_1: listSum(list: $select_1)
	select_1: select(table: 'table'; field: 'field'; where: 'num' = 1)

sum_2: listSum(list: $select_2)
	select_2: select(table: 'table'; field: 'field'; where: 'num' = 2)

```

В таком маленьком примере лучше не использовать `{}` тк они труднее читаются, но если переиспользуемая часть большая — это существенно упрощает код.

#### <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it-q2"></a> Что такое exec?

Функция [exec][286] позволяет выполнить код, содержащийся в другом поле или переданный в параметр `code` в виде текста.

#### <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it-q3"></a> В каких случаях эффективно использовать exec?

Если у вас несколько полей, выполняющих один и тот-же код с разными параметрами. Например у нас отчет за год, где каждое поле — это месяц.

В первом поле пишем:

```totum

=: listSum(list: $list)

	list: selectList(table: 'table'; field: 'sum'; where: 'date' >= $datestart; where: 'date' <= $dateend)

		datestart: var(name: "datestart"; default: "2021-01-01")
		dateend: var(name: "dateend"; default: "2021-01-31")
```

Обратите внимание — мы здесь берем `datestart` и `dateend` через `var`, тк в первом поле эти переменные не будут определены и нам нужны будут `default` значения.

В феврале пишем:

```totum

=: exec(code: "name_first_field"; var: "datestart" = "2021-02-01"; var: "dateend" = "2021-02-28")

```

Можно ли var передать один, а не несколько? Да, можно:

```totum

=: exec(code: "name_first_field"; var: "dates" = $row)

	row: rowCreate(field: "datestart" = "2021-02-01"; field: "dateend" = "2021-02-28")

```

Но тогда нам нужно модифицировать код в первой колонке:

```totum

=: listSum(list: $list)

	list: selectList(table: 'table'; field: 'sum'; where: 'date' >= $dates[datestart]; where: 'date' <= $dates[dateend])

		~datestart: var(name: "dates"; default: $row)
			row: rowCreate(field: "datestart" = "2021-01-01"; field: "dateend" = "2021-01-31")

```

#### <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it-q4"></a> Если мы запускаем exec из секции форматирования то как он будет выполнен?

`exec` выполняет код того типа, из которого вызывается.

#### <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it-q5"></a> Будет ли выполнен exec, если какой-то из параметров не будет передан?

Нет, не будет. Для этого мы делали `default`.

Еще вариант, если в каких-то случаях параметр нужен, а в каких-то нет, то передавать его пустым `""`, там где оне не нужен.

#### <a name="how-to-optimize-the-execution-of-the-same-code-with-variables-passed-to-it-q6"></a> Как можно хранить выполняемые по exec коды?

Хорошей практикой является иметь скрытое поле в таблице, где в соответсвующей секции будет лежать исполняемый по `exec` код.

Но вы можете создать специальную таблицу для хранения таких вынесенных кодов — однако лучше этого избегать.

## <a name="how-do-I-use-cond-for-cond"></a> Как использовать cond для condition?

### <a name="how-do-I-use-cond-for-cond-qa"></a> Вопросы и ответы

#### <a name="how-do-I-use-cond-for-cond-q1"></a> Как работают condition и как их можно оптимизировать по нагрузке?

`condition` во всех функциях, где они есть исполняются слева-направо. Следующий выполняется только если предыдущий выполнился в `true`.

Поэтому `condition` надо располагать по возрастанию предполагаемой нагрузки.

#### <a name="how-do-I-use-cond-for-cond-q2"></a> Как можно использовать сравнения для оптимизации нагрузки вычислений?

Если у вас большой код, который потребляет много ресурсов, то можно проверять изменились ли нужные для него вводные используя предыдущее значение.

Например, мы все отслеживаемые поля склеили через `listCreate` в техническое поле `control_field` и проверяем, изменилось ли оно, используя предыдущее значение поля:

```totum

=: if(condition: #old.control_field == #control_field; then: $#onfv; else: $calc)

calc: "..."

```

Если не изменилось, то не вычисляем `calc`, а берем предыдущее значение текущего поля.

#### <a name="how-do-I-use-cond-for-cond-q3"></a> Cond всегда возвращает?

Есть сахар `cond` — он всегда возвращает результат сравнения внутри себя — `true` или `false`.

В `cond` возможны условия `и`, `или` и группировки `()`:

+ `&&` — и.

+ `||` — или.

`Cond` можно использовать внутри функций, а внутри `condition` можно не ставить лишнего сравнения:

```totum
=: if(condition: cond`$line_1 != 0 || $line_2 != 0`; then: 100; else: 0)

line_1: 10
line_2: 0

// Результат = 100

```

Если словами, то в этом примере написано: если `line_1` неравно `0` или `line_2` неравно `0`, то `100`, иначе `0`.

#### <a name="how-do-I-use-cond-for-cond-q4"></a> Можно ли использовать cond внутри condition без дополнительного указания = true/false?

Да. `condition` помимо сравнений принимают `true` или `false`:

```totum
=: if(condition: $cond; then: 100; else: 0)

cond: if(condition: $line_1 != 0; then: true; else: false)

	line_1: 10

// Результат = 100

```

#### <a name="how-do-I-use-cond-for-cond-q5"></a> Как написать условие и?

+ `&&` — и.

#### <a name="how-do-I-use-cond-for-cond-q6"></a> Как написать условие или?

+ `||` — или.

#### <a name="how-do-I-use-cond-for-cond-q7"></a> Для чего используются скобки внутри cond?

Для группировки условий:

```totum
cond: cond`($A=1 && $B=1) || ($A=2 && $B=2)`

// Означает: A и B равны 1 или A и B равны 2

```

#### <a name="how-do-I-use-cond-for-cond-q8"></a> В какой момент заканчивается выполнение cond?

Когда все условия на одном уровне выполнились в `true`:

```totum
cond: cond`($A=1 && $B=1) || ($A=2 && $B=2)`

// Здесь выполнение закончится если A=1 и B=1, повторного обращения к A и B не будет

```

## <a name="how-do-I-use-the-value-of-the-previous-row-to-calculate-the-current-one"></a> Как использовать значение предыдущей строки для расчета текущей?

### <a name="how-do-I-use-the-value-of-the-previous-row-to-calculate-the-current-one-qa"></a> Вопросы и ответы

#### <a name="how-do-I-use-the-value-of-the-previous-row-to-calculate-the-current-one-q1"></a> Пример: пример с последовательной нумерацией

У Тотум есть последовательня уникальная сортировка строк — `id`, но иногда нужна дополнительная, например если в таблице включено не только добавление, но и удаление строк или случаются перезапуски/отмены транзакций.

`id` не переиспользуются, поэтому получаются пробелы.

Чаще всего это бывает нужно в расчетных таблицах внутри цикла — например для того, что бы передавать в бухгалтерию или на печать чеков последовательные номера.

Сооздаем поле номер и делаем в нем Код, который будет его вычислять:

```totum
= : if(condition: $sel = ""; then: 1; else: $calc)

	~sel: select(table: $#ntn; field: $#nf; where: 'id' < $id; order: 'id' desc)
		id: if(condition: #id = ""; then: "*ALL*"; else: #id)

calc: $sel + 1

```

В этом примере мы проверяем существование предыдущего номера и еще мы проверяем `id` на `строку добавления`. Если это `строка добавления`, то мы берем последнюю строку по `id` в таблице, если нет, то первую строку, у которой `id` меньше.

#### <a name="how-do-I-use-the-value-of-the-previous-row-to-calculate-the-current-one-q2"></a> Пример: пример с n-сортировкой

Если включен параметр `сортировать по полю порядок`, то тоже самое нужно сделать по `n` вместо `id`:

```totum
= : if(condition: $sel = ""; then: 1; else: $calc)

	~sel: select(table: $#ntn; field: $#nf; where: 'n' < $n; order: 'n' desc)
		n: if(condition: #n = ""; then: "*ALL*"; else: #n)

calc: $sel + 1

```

## <a name="why-is-there-a-need-for-a-column-by-column-recalculation-in-the-calculation-and-temporal-tables"></a> Зачем нужен поколоночный пересчет в расчетных и временных таблицах?

### <a name="why-is-there-a-need-for-a-column-by-column-recalculation-in-the-calculation-and-temporal-tables-qa"></a> Вопросы и ответы

#### <a name="why-is-there-a-need-for-a-column-by-column-recalculation-in-the-calculation-and-temporal-tables-q1"></a> В каких типах таблиц можно использовать в коде значения всей предыдущей колонки?

На самом деле во всех, но подходы разные.

**С табличной единицей пересчета (расчетные и временные)**

В расчетных и временных можно включить параметр [поколоночный пересчет][93] — он меняет порядок расчета полей.

+ **Отключен** — считаются строки слева направо.

+ **Включен** — считаются колонки сверху вниз (по возрастанию `id` или `n`).

Также меняется порядок расчета футера у полей [привязанных к колонкам][138]:

+ **Отключен** — поля считаются по возрастанию `sort` и все после строчной части.

+ **Включен** — сначала считаются поля привязанные к колонкам по возрастанию `sort` колонки, а потом по возрастанию `sort` полей привязанных к колонке сразу после окончания расчета всех полей в строчной части колонки (не дожидаясь пока вся строчная часть посчитается!).

Зачем это может быть нужно. Например: нам нужно во второй колонке строчной части посчитать долю значения из первой колонки.

Код такой:

```totum
=: #first_column_value / #summ_first_column

```

`#summ_first_column` мы считаем в футере первой колонки как:

```totum
=: listSum(list: $list)

list: selectList(table: $#ntn; field: 'first_column_value')

```

Это будет работать только если включен [поколоночный пересчет][93], тк `#summ_first_column` должен быть посчитан до того, как начнется расчет полей строчной части второй колонки!

Если [поколоночный пересчет][93] отключен, то вот так в коде второй колонки сделать нельзя:

```totum
=: #first_column_value / $summ

summ: listSum(list: $list)

list: selectList(table: $#ntn; field: 'first_column_value')

```

Тк строки считаются сверху вниз и в момент, когда будет браться `selectList`, строк находящихся ниже еще нет!

**Со строчной единицей пересчета (простые и циклов)**

Для таблиц со строчной единицей пересчета когда вы берете `selectList` — это ограничение не действует тк строки в таблице существуют.

Но есть особенности связанные с текущей строкой. Если эта строка добавления, то ее значения еще не записаны в базу данных и в `selectList` не попадут. Если она уже существует и пересчитывается, то попадут старые значения.

Поэтому текущая строка из `select` исключается, а ее значение плюсуется или добавляется в список отдельным действием:

```totum
=: #first_column_value / $summ

summ: listSum(list: $list) + #first_column_value

list: selectList(table: $#ntn; field: 'first_column_value'; where: 'id' != #id)

// Это будет работать и для строки добавления тк != #id который получается != "" в этом случает тоже дает нужный результат

```

#### <a name="why-is-there-a-need-for-a-column-by-column-recalculation-in-the-calculation-and-temporal-tables-q2"></a> Какой параметр должен быть включен в расчетных и временных, для того, что бы получить корректные значения?

[Поколоночный пересчет][93]

#### <a name="why-is-there-a-need-for-a-column-by-column-recalculation-in-the-calculation-and-temporal-tables-q3"></a> Что надо учитывать при выполнении такого расчета в строке добавления?

**С табличной единицей пересчета (расчетные и временные)**

Когда включен [Поколоночный пересчет][93], значение из строки добавления будет включено в `select` автоматически, несмотря на то, что строка еще не сохранена в базу данных.

**Со строчной единицей пересчета (простые и циклов)**

Этого не происходит, поэтому значение текущей строки исключается из `select` и добавляется отдельно:

```totum
=: #first_column_value / $summ

summ: listSum(list: $list) + #first_column_value

list: selectList(table: $#ntn; field: 'first_column_value'; where: 'id' != #id)

```

#### <a name="why-is-there-a-need-for-a-column-by-column-recalculation-in-the-calculation-and-temporal-tables-q4"></a> Как меняется порядок обработки полей футера при переключении расчетной и временной на поколоночный режим рассчета?

Также меняется порядок расчета футера у полей [привязанных к колонкам][138]:

+ **Отключен** — поля считаются по возрастанию `sort` и все после строчной части.

+ **Включен** — сначала считаются поля привязанные к колонкам по возрастанию `sort` колонки, а потом по возрастанию `sort` полей привязанных к колонке сразу после окончания расчета всех полей в строчной части колонки (не дожидаясь пока вся строчная часть посчитается!).

## <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code"></a> Как использовать предыдущее значение в кодах и комбинировать ручной ввод и код?

### <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code-qa"></a> Вопросы и ответы

#### <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code-q1"></a> Как обратиться к предыдущему значению поля?

Не путать со значением предыдущей строки!

Предыдущее значение поля можно получить через быструю переменную `$#onfv` (old now field value) и через пристаку `old` при обращении к значению поля — `#old.field_name`.

#### <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code-q2"></a> Возможно ли обращение к предыдущему значению поля из кода?

Да, в кодах тоже можно получить предыдущее значение поля. Где еще — в кодах действия.

#### <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code-q3"></a> Пример: пример как можно оставить значение поля без изменения по условиям

Задача. При выполнении каких-то условий оставить значение поля без изменения, а если условия не выполняются — рассчитать:

```totum

=: if(condition: #type = 1; then: $#onfv; else: $calc)

calc: #value_1 * #value_2

```

#### <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code-q4"></a> Пример: пример, изменение значения поля чекбокс на противоположное при пересчете

```totum
= : if(condition: $#onfv = true; then: false; else: true)

```

Например для того, чтобы отслеживать любое изменение в строке. Изменение любого поля приведет к пересчету строки, а пересчет строки приведет к изменению чекбокса.

Таким образом можно повесить код действия отслеживающий любое изменение.

#### <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code-q5"></a> Как в поле, рассчитываемом кодом, вводить значения вручную и избежать фиксации значения?

Достаточно часто бывает, что поле рассчитываемое кодом на основе другого поля может быть изменено и это изменение должно вести к изменению основного поля и не должно приводить к фиксированию значения текущего:

Например код:

```totum
=: select(table: 'table'; field: 'field'; where: 'id' = #num)

```

Код действия на изменение этого поля:

```totum

=: set(table: 'table'; field: 'field' = $#nfv; where: 'id' = #num)

```

Но это приводит к `pin` текущего поля и появлению руки.

Руку можно скрыть исключительно косметически в форматировании:

```totum

f1=: setFormat(showhand: false)

```

Но это не приведет к откреплению значения в поле. Для того, что бы работало корректно надо вторым действием сделать `clear`:

```totum

a1=: set(table: 'table'; field: 'field' = $#nfv; where: 'id' = #num)

a2=: clear(table: $#ntn; field: $#nf; where: 'id' = #id)

// Не забудьте указать where по текущему id

```

#### <a name="how-do-I-use-the-previous-value-in-codes-and-combine-manual-input-and-code-q6"></a> Как убрать индикацию ручного значения?

Руку можно скрыть исключительно косметически в форматировании:

```totum

f1=: setFormat(showhand: false)

```

## <a name="operations-on-row-and-rowList"></a> Операции над row и rowList

### <a name="operations-on-row-and-rowList-qa"></a> Вопросы и ответы

#### <a name="operations-on-row-and-rowList-q1"></a> Какая функция используется для сложения по строкам двух rowList?

Если нужно строки одного `rowList` сложить со строками другого `rowList`, то они складываются как и `list` при помощи [listAdd][264]:

```totum

=: listAdd(list: $row1; list: $row2)

row1: selectRowList(table: 'table_1'; field: 'field_1'; field: 'field_2')

row2: selectRowList(table: 'table_2'; field: 'field_1'; field: 'field_2')

```

Часто это требуется в кодах селектов:

```totum

=: listAdd(list: $row1; list: $row2)

row1: selectRowListForSelect(table: 'table_1'; field: 'title'; bfield: 'uiid')

row2: selectRowListForSelect(table: 'table_2'; field: 'title'; bfield: 'uiid')

```

#### <a name="operations-on-row-and-rowList-q2"></a> Какая функция используется для сложения по колонкам двух rowList?

[rowListAdd][278] — используется, когда вам нужно добавить колонку со значениями в существующий `rowList`.

Колонка со значениями передается в виде `list` с нужным вам порядком элементов.

Именно в этом порядке они будут добавлены в `rowList`, поэтому задавайте сортировки. Если сортировок нет, то от раза к разу результат будет отличаться:

```totum
=: rowListAdd(rowlist: $rowList; field: "field_3" = $list)

rowList: selectRowList(table: 'table_1'; field: 'field_1'; field: 'field_2'; order: 'field_1' asc)

list: selectList(table: 'table_2'; field: 'field_3'; order: 'field_1' asc)

```

Если нужно добавить не одну колонку:

```totum
=: rowListAdd(rowlist: $rowList_1; rowlist: $rowList_2)

rowList_1: selectRowList(table: 'table_1'; field: 'field_1'; field: 'field_2'; order: 'field_1' asc)

rowList_2: selectRowList(table: 'table_2'; field: 'field_3'; field: 'field_4'; order: 'field_1' asc)

```

#### <a name="operations-on-row-and-rowList-q3"></a> Как из row получить список значений?

Если у вас есть `row`, нужен `list` из значений, которые в нем содержаться, то это делает [rowValues][280]:

Например возьмем значения полей в `list` из 5 полей в текущей строке:

```totum

=: rowValues(row: $row)

row: selectRow(table: $#ntn; field: 'field_1'; field: 'field_2'; field: 'field_3'; field: 'field_4'; field: 'field_5'; where: 'id' = #id)

```

#### <a name="operations-on-row-and-rowList-q4"></a> Как из row получить список ключей?

Есть аналогичная функция [rowKeys][279], которая берет названия ключей из `row`.

Обычно она используется совместно с [listFilter][268], который может фильтровать и `row` (те оставить только те колонки, которые отвечают условию):

Получим названия всех полей в которых значение больше `10`:

```totum

=: rowKeys(row: $filter)

filter: listFilter(list: $row; key: "value" > 10)

row: selectRow(table: $#ntn; field: 'field_1'; field: 'field_2'; field: 'field_3'; field: 'field_4'; field: 'field_5'; field: 'field_6'; field: 'field_7'; field: 'field_8'; field: 'field_9'; field: 'field_10'; field: 'field_11'; field: 'field_12'; where: 'id' = #id)

```

Усложним эту задачу: например это месяца и нам надо получить номера месяцев в которых значение больше 10.

```totum

=: listReplace(list: $keys; action: $new_value; value: "value")

 		new_value:  strReplace(str: $#value; from: "field_"; to: "")
		// Чтобы получить номера мы заменяем field_ на пустоту в каждом элементе списка

keys: rowKeys(row: $filter)
// Здесь получится list из строковых имен полей

filter: listFilter(list: $row; key: "value" > 10)

row: selectRow(table: $#ntn; field: 'field_1'; field: 'field_2'; field: 'field_3'; field: 'field_4'; field: 'field_5'; field: 'field_6'; field: 'field_7'; field: 'field_8'; field: 'field_9'; field: 'field_10'; field: 'field_11'; field: 'field_12'; where: 'id' = #id)

```

#### <a name="operations-on-row-and-rowList-q5"></a> Как из row и rowList удалить колонку?

Эту задачу выполняет [rowKeysRemove][282]. Передаете ей `row` или `rowList` и имена удаляемых колонок.

Если вы передаете `rowList`, то нужно будет указать `recursive: true`!

#### <a name="operations-on-row-and-rowList-q6"></a> Как переименовать ключ колонки в row или rowList?

Задача с переименованием колонок решается при помощи [rowKeysReplace][282].

Если вы передаете `rowList`, а не `row`, то нужно будет указать `recursive: true`!

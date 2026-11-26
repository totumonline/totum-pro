# Нагрузка в listReplace

[listReplace][486] выполняет свои `action` для каждого элемента списка. Все, что написано в `action`, умножается на число элементов: запрос в `action` — это столько запросов, сколько элементов, повторный расчет одного и того же значения в двух `action` — двойная работа на каждом элементе. Два приема ниже убирают и то, и другое.

## <a name="pt-lr-var"></a> Результат предыдущего action — следующему через var

### <a name="pt-lr-var-task"></a> Задача

Несколько `action` выполняются для элемента по очереди, и следующему нужен результат предыдущего. Если следующий `action` просто сошлется на ту же строку кода, она посчитается для элемента второй раз (а `~` здесь нельзя — строка зависит от элемента).

### <a name="pt-lr-var-solution"></a> Решение

Предыдущий `action` записывает результат в переменную через [var][285] — значение считается в отдельной строке `var_<имя>`, — следующий `action` читает готовое значение как `$#<имя>`:

```totum
=: listReplace(list: json`[1, 2, 3]`; action: $action_row; action: $next_step; value: "v")
	action_row: var(name: "action_row"; value: $var_action_row)
		var_action_row: $#v * 10
	next_step: $#action_row + 1
```

Результат: `[11, 21, 31]` — результат `listReplace` равен значению последнего `action`. Это же правило есть в [ARCHITECTURE.md](../../ARCHITECTURE.md#listreplace-var).

`action` выполняются в том порядке, в котором записаны в `listReplace`: переменная должна быть записана в `action` раньше, чем ее читают.

### <a name="pt-lr-var-b2m"></a> Пример из B2M

Поле `features_data` таблицы `b2m___items` собирает характеристики товара. Для каждой строки характеристики три `action` идут цепочкой — каждый следующий берет результат предыдущего из переменной:

```totum
double: listReplace(list: $select_list; action: "double" = $double_check; action: "value" = $value; action: "shown_value" = $shown_value; key: "key"; value: "raw_data")

		~select_list: selectRowList(table: 'b2m___product_features'; field: 'feature' as "feature"; field: 'feature_value' as "value"; field: 'unit'; field: 'type'; where: 'product' = #id; where: 'sku' = $#lc; order: 'n' asc)
	double_check: if(condition: $d_filter != $#lc; then: true; else: false)
		d_filter: var(name: "double"; value: $d_filter_process)
			d_filter_process: listFilter(list: $select_list; key: "key" < $#key; key: "feature" = $#raw_data[feature])
	value: var(name: "var_value"; value: $if_value)
		if_value: if(condition: $#double = $#lc; then: $add_ahead; else: "")
			…
	shown_value: if(condition: $is_it_list = false; then: $single; else: $list)
		is_it_list: listCheck(list: $#var_value)
			…
```

+ `double` — есть ли эта характеристика в строках **до** текущей (повтор); результат поиска записан в переменную `double`.

+ `value` — читает `$#double` и не ищет повторы заново; свое значение записывает в `var_value`.

+ `shown_value` — строит текст для витрины из `$#var_value`, не пересчитывая `value`.

## <a name="pt-lr-rowlist"></a> Один rowList на весь список вместо запроса на элемент

### <a name="pt-lr-rowlist-task"></a> Задача

Каждому элементу нужны данные из таблицы: другие строки той же группы, настройки по ключу элемента. Запрос в `action` — `select` с `where` по элементу — выполняется для каждого элемента: 200 элементов — 200 запросов к базе, а запросы — самая дорогая часть кода.

### <a name="pt-lr-rowlist-solution"></a> Решение

+ Одним [selectRowList][231] (или `selectList`) до `listReplace` взять **все** строки, которые могут понадобиться элементам, и зафиксировать `~` — строка не зависит от элемента.

+ В `action` выбирать нужное из этого списка [listFilter][268] по ключу элемента — в памяти, без запросов.

```totum
=: listReplace(list: $items; action: "settings" = $settings; value: "item")
		items: selectRowList(table: 'some_items'; field: 'id'; field: 'group')
	settings: listFilter(list: $all_settings; key: "group" = $#item[group])
		~all_settings: selectRowList(table: 'some_settings'; field: 'group'; field: 'param'; field: 'value')
```

Не так — запрос на каждый элемент:

```totum
=: listReplace(list: $items; action: "settings" = $settings; value: "item")
		items: selectRowList(table: 'some_items'; field: 'id'; field: 'group')
	settings: selectRowList(table: 'some_settings'; field: 'param'; field: 'value'; where: 'group' = $#item[group])
```

### <a name="pt-lr-rowlist-b2m"></a> Пример из B2M

+ `features_data` (`b2m___items`): строки характеристик товара берутся одним `~select_list`, а поиск повторов характеристики до и после текущей строки — `listFilter` по этому же списку (`key: "key" < $#key`, `key: "key" > $#key`), без запросов в `action`.

+ `filters_data` (`b2m___catalog`): настройки всех характеристик, участвующих в фильтрах, выбираются один раз, а для каждой характеристики категории `action` берет свою строку `listFilter`:

	```totum
	~feature_active_in_filter: selectRowList(table: 'b2m___features'; field: 'feature_key'; field: 'use_in_filter'; field: 'quick_access'; field: 'sort_in_filter'; field: 'description'; field: 'type'; field: 'unit'; field: 'feature_title'; where: 'use_in_filter' != "")
	```

	```totum
	filter_type: while(action: $var_create; postaction: $#proto_row[use_in_filter])
		var_create: var(name: "proto_row"; value: $filter[0])
			filter: listFilter(list: $feature_active_in_filter; key: "feature_key" = $#proto_data[filter])
	```

	Найденная строка настроек сразу кладется в переменную `proto_row` — остальные `action` (`quick_access`, `sort`, `description`, `unit`, …) читают `$#proto_row[…]`, не фильтруя список заново. Здесь работают оба приема страницы.

## <a name="pt-lr-traps"></a> Ловушки

+ `~` ставится на строку общего списка (`~all_settings`, `~select_list`), но **не** на строки, которые зависят от элемента: `~` запомнит значение первого элемента для всех ([ARCHITECTURE.md](../../ARCHITECTURE.md#fix-lines)).

+ Общий список ограничивайте `where` тем, что действительно нужно элементам (товар, группа, активные), — не тяните в память всю таблицу.

+ Имена переменных `var` должны быть уникальными в коде и не совпадать с переменными самого `listReplace` (`value`, `key`).

+ Строка источника `list` в `listReplace` пишется с двумя табами, строки `action` — с одним ([оформление listReplace](../../ARCHITECTURE.md#listreplace-layout)).

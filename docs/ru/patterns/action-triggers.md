# Массовые изменения и общий триггер

## <a name="pt-trig-root"></a> Изменение многих строк от корневой

### <a name="pt-trig-root-task"></a> Задача

Строки связаны «корень — подчиненные»: базовая строка товара и его SKU, заказ и его позиции. Значение, заданное в корне, должно разойтись по всем подчиненным. Если подчиненные строки сами реагируют на изменение этого поля (пишут итог владельцу, пересчитывают зависимые таблицы), то при массовом изменении их код выполнится на **каждую** строку: 200 SKU — 200 одинаковых записей в товар.

### <a name="pt-trig-root-solution"></a> Решение

+ Корень меняет подчиненные **одним** `setList` — не построчно.

+ Перед этим ставит [флаг процесса][721]: «изменение идет от корня».

+ Код действия подчиненных строк по этому флагу пропускает свою реакцию.

+ Общее действие (запись итога владельцу) корень выполняет сам — один раз.

+ Чтобы подчиненные не раздавали значение дальше, ветка «раздать всем» выполняется только в корне — условием по признаку корня.

### <a name="pt-trig-root-b2m"></a> Пример из B2M

В `b2m___sku` у товара есть базовая строка (`base = true`) и строки SKU. Код действия поля `in_stock` (наличие):

```totum
a1=: if(condition: #base = true; then: $set_while; else: "")

	set_while: while(action: $from_main; action: $set)

		from_main: procVar(name: "from_main"; value: true)

		set: setList(table: $#ntn; field: $#nf = $#nfv; where: 'product' = #product; where: 'base' = false; where: 'obsolete' = false; log: true)

a2=: if(condition: #base = true; then: $set_items)

	set_items: set(table: 'b2m___items'; field: 'in_stock' = $#nfv; where: 'id' = #product)

a3=: if(condition: $get_from_main = false; condition: #base = false; condition: $#nfv = "Y"; then: $set_items; else: $check_o)

		~get_from_main: procVar(name: "from_main"; default: false)
	…
```

+ `a1` — только в базовой строке: флаг `from_main` и один `setList` по всем актуальным SKU товара. Код действия `in_stock` при этом выполнится у каждого SKU, но:

+ `a3` — реакция отдельного SKU (написать наличие товару) — при флаге `from_main` пропускается;

+ `a2` — наличие товара записывает базовая строка, один раз.

Так же из базовой строки раздаются `stock_info`, `tracking_type`, `minus_limit`, `set_stock`, `exclude`: ветка `setList` стоит под условием `#base = true`, поэтому подчиненные строки, получив значение, дальше его не раздают.

Тот же прием разводит две таблицы, которые меняют друг друга: изменение остатка в `b2m___sku` ставит флаг `from_sku`, а движение по складу `b2m___stock` — `from_stock`; каждая сторона не отвечает на изменение, которое пришло от другой.

## <a name="pt-trig-control"></a> Общий триггер для нескольких полей

### <a name="pt-trig-control-task"></a> Задача

Одно действие должно запускаться при изменении **любого** из нескольких полей строки. Если написать его в код действия каждого поля, получится несколько копий, а при изменении двух полей за раз действие выполнится дважды.

### <a name="pt-trig-control-solution"></a> Решение

Техническое поле собирает значения отслеживаемых полей своим [кодом][27] — например, [listCreate][255] из них, — а [код действия][28] на изменение вешается только на него. Поле пересчитывается вместе со строкой: изменилось любое из отслеживаемых полей — изменилось и оно, и действие выполняется один раз.

+ Поле ставится по сортировке **после** отслеживаемых полей — иначе при расчете строки они еще не посчитаны ([порядок расчета][494]).

+ Поле техническое: [видно ролям][178] — только создателю, `notLoaded`, текст вместо значения форматированием.

### <a name="pt-trig-control-b2m"></a> Пример из B2M

`changing_control` в `b2m___product_options` (опции товара) следит за четырьмя модификаторами опции:

```totum
=: if(condition: #sku = true; then: $calc; else: $#lc)

calc: listCreate(item: #sku_mod; item: #title_mod; item: #price_mod_type; item: #price_mod)
```

Код действия на изменение один раз обновляет данные всех SKU с этой опцией и пересчитывает показ в каталоге:

```totum
a1= : if(condition: $#onfv != $#lc; condition: $#nfv != $#lc; then: $set)

set: setListExtended(table: 'b2m___sku'; field: 'keys_data' = $replace[[keys_data]]; where: 'id' = $replace[[id]])
	…

a2=: exec(code: "show_in_catalog")
```

Условие `$#onfv != $#lc` пропускает первое заполнение поля при добавлении строки.

Вариант того же приема — чекбокс `control` в `b2m___catalog`: он становится `true`, если изменилось хотя бы одно из полей, влияющих на меню (название, положение в дереве, URL, картинка, активность, порядок, описание; код сокращен):

```totum
=: if(condition: #id = ""; then: false; else: $switch)

switch: if(condition: $check = true; condition: $#onfv = false; then: true; else: false)

check: if(condition: cond`#category_name != #old.category_name || #tree != #old.tree || #update_url = true || #image_raw != #old.image_raw || #active != #old.active || #n_field != #old.n_field`; then: true; else: false)
```

А [код действия таблицы][489] ловит его в `$#changes` и один раз помечает меню для перегенерации — сколько бы строк и полей ни изменилось:

```totum
a1=: if(condition: $control != $#lc; then: $set)

control: rowKeys(row: $list_filter)
	list_filter: listFilter(list: $#changes[changed]; key: "value" = "control")

	set: set(table: $#ntn; field: 'h_html_alert_menu' = true)
```

## <a name="pt-trig-traps"></a> Ловушки

+ Флаг «от корня» ставится **до** `setList` — в той же цепочке, иначе коды подчиненных строк его не увидят.

+ Флаг живет до конца процесса: если в том же процессе дальше идет обычное изменение подчиненной строки, оно тоже будет пропущено. Ставьте флаг на узкий участок или сбрасывайте его `procVar(…; value: false)` после `setList`.

+ Техническое поле-триггер с кодом пересчитывается при каждом пересчете строки — держите его код дешевым: только поля своей строки, без запросов.

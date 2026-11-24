# Коды действий продвинутый уровень

## <a name="how-can-I-get-the-previous-value-of-a-field-and-use-it-in-the-action-code"></a> Как получить предыдущее значение поля и использовать его в коде-действия?

### <a name="how-can-I-get-the-previous-value-of-a-field-and-use-it-in-the-action-code-qa"></a> Вопросы и ответы

#### <a name="how-can-I-get-the-previous-value-of-a-field-and-use-it-in-the-action-code-q1"></a> Как ведут себя текущее и предыдущее значение при добавлении и удалении строки?

Предыдущее значение можно взять переменной `$#onfv` и через обращение `#old.field_name`.

+ При **добавлении** строки —  предыдущее значение пустое! Тк строки небыло.

+ При **удалении** строки —  текущее значение пустое! Тк строки больше нет.

Поэтому, если вы хотите выполнить действие поп триггеру удаление и использовать значение из поля удаляемой строки, то нужно взять предыдущее значение (то, которое было перед удалением).

Код действия по триггеру удаление:

```totum

=: recalculate(table: 'table'; where: 'connect_field' = $#onfv)

```

#### <a name="how-can-I-get-the-previous-value-of-a-field-and-use-it-in-the-action-code-q2"></a> Как можно разрешить действие только при определенном переходе одного значения поля в другое?

Например у вас есть статусы `[1,2,3,4]` и вам нужно выполнить действие, только если статус меняется с `3` на `4`:

Код действия:

```totum

=: if(condition: $#onfv = 3; condition: $#nfv = 4; then: $action)

action: set(table: 'table'; field: 'field' = "complete"; where: 'num' = $#nci)

```

#### <a name="how-can-I-get-the-previous-value-of-a-field-and-use-it-in-the-action-code-q3"></a> Какое значение мы должны использовать если хотим выполнить действие с использованием данных удаляемой строки?

Предыдущее значение!

```totum

=: recalculate(table: 'table'; where: 'connect_field' = $#onfv)

```

## <a name="strong-blocking-of-actions-by-conditions-on-the-server"></a> Сильные блокировки действий по условиям на сервере

### <a name="strong-blocking-of-actions-by-conditions-on-the-server-qa"></a> Вопросы и ответы

#### <a name="strong-blocking-of-actions-by-conditions-on-the-server-q1"></a> Что является слабой блокировкой?

Любые блокировки через [setFormat][315] в форматировании (во всех возможнх форматированиях)!

А также ограничения в выборке селектов. Если значения селекта нет в выпадающем списке — это не значит, что его нельзя ввести вскрыв фронтенд.

#### <a name="strong-blocking-of-actions-by-conditions-on-the-server-q2"></a> Что является сильной блокировкой?

Блокировки по ролям в настройках полей и таблиц и в кодах действий!

#### <a name="strong-blocking-of-actions-by-conditions-on-the-server-q3"></a> Каким образом остановить выполнение любой цепочки действий при невыполнении контрольного условия?

Для этого надо выполнить функцию [errorException][287] в любом месте кода действия.

При ее исполнении цепочка дейстий будет остановлена и полностью **отменена!**

#### <a name="strong-blocking-of-actions-by-conditions-on-the-server-q4"></a> Как проверить роль или пользователя перед совершением действия?

Быстрой переменной `$#nr` (now roles) — она возвращает список ролей, которые назначены пользователю.

И если вы хотите гарантировано отклонить любое действие, если роль не соответсвует указаной то:

```totum
=: if(condition: $#nr != $appr; then: $err)

appr: json`[1,2,5]`

err: errorException(text: "Только Создатель, Менеджер и Босс могут изменять статус!")

```

#### <a name="strong-blocking-of-actions-by-conditions-on-the-server-q5"></a> Как сделать кнопку удаления строки и проверить условия для удаления?

Например в строке мы делаем кнопку «Удалить» которая будет удалять текущую строку:

```totum
=: delete(table: $#ntn; where: 'id' = #id)

```

А проверку мы хотим сделать не в кнопке, а в каком-то другом поле. И мы там делаем код действия по триггеру удаление:

```totum
=: if(condition: $#onfv = 3; then: $err)

err: errorException(text: "Нельзя удалить строку со статусом Согласовано")

```

И здесь важный момент — тк используется триггер на удаление, то нам нужно брать предыдущее значение поля — `$#onfv`.

#### <a name="strong-blocking-of-actions-by-conditions-on-the-server-q6"></a> Использование кода действия таблицы для сильной блокировки любого действия в таблице?

Иногда нужно блокировать любое действие с таблицей, если не выполняется какое-либо условие. Часто используется для таблиц внутри циклов.

Например, когда заказ закрыт — то ничего в нем менять нельзя.

Что бы не писать блокирующий код действия для каждого поля есть [код действия таблицы][489] — это код действия, который выполняется один раз при любом изменени таблицы перед всеми остальными кодами действий в этой таблице.

И вы в нем можете написать:

```totum
=: if(condition: #status = 3; condition: $#nr != 1; then: $block)

block: errorException(text: "Нельзя изменять Заказ в статусе Отгружено!")

```

В этом примере мы разблокируем изменение для Создателя!

## <a name="at-what-point-is-the-data-for-the-action-codes-taken"></a> В какой момент берутся данные для кодов действий?

### <a name="at-what-point-is-the-data-for-the-action-codes-taken-qa"></a> Вопросы и ответы

#### <a name="at-what-point-is-the-data-for-the-action-codes-taken-q1"></a> В какой момент берутся #?

Все `#` берутся в момент **начала!** выполнения кода.

#### <a name="at-what-point-is-the-data-for-the-action-codes-taken-q2"></a> В какой момент берутся select?

Все `select` берутся в тот момент, **когда они исполняются!** Это принципиальное отличие от `#`.

#### <a name="at-what-point-is-the-data-for-the-action-codes-taken-q3"></a> В какой момент берутся @?

`@` это сахар для `select` — аналогично в момент их исполнения.

#### <a name="at-what-point-is-the-data-for-the-action-codes-taken-q4"></a> Пример: пример в котором неправильное понимание последовательности приводит к ошибке

Предположим у нас три поля `field_1`, `field_2` и `field_3`. Мы делаем код действия в `field_2`:

```totum

a1= : set(table: $#ntn; field: 'field_1' = 25; where: 'id' = #id)

a2=: set(table: $#ntn; field: 'field_3' = #field_1; where: 'id' = #id)

```

Те первым действием он изменяет значение в `field_1`, а вторым берет значение из `field_1` и ставит его в `field_3`.

В результате выполнения этого кода в `field_3` будет поставлено значение из `field_1` до изменения. Предположим оно там было равно `11`.

Несмотря на то, что мы назначаем `'field_1' = 25` в `field_3` будет `11`, тк его значение через `#` будет взято **до начала** исполнения кода действия!

Модифицируем, что бы работало:

```totum

a1= : set(table: $#ntn; field: 'field_1' = 25; where: 'id' = #id)

a2=: set(table: $#ntn; field: 'field_3' = $select; where: 'id' = #id)

select: select(table: $#ntn; field: 'field_1'; where: 'id' = #id)

```

Этот код будет работать, тк вызов `select` производится после изменения `field_1`.

## <a name="cross-performance-of-actions"></a> Перекрестное выполнение действий

### <a name="cross-performance-of-actions-qa"></a> Вопросы и ответы

#### <a name="cross-performance-of-actions-q1"></a> Пример: пример мешки и килограммы

Например, есть 2 взаимно зависящих друг от друга поля поля. Значение во втором должно быть всегда в 2 раза больше чем в первом и наоборот.

Делаем код действия при изменении в **первом** поле:

```totum

=: set(table: $#ntn; field: 'field_2' = math`$#nfv * 2`; where: 'id' = #id)

```

Делаем код действия при изменении во **втором** поле:

```totum

=: set(table: $#ntn; field: 'field_1' = math`$#nfv / 2`; where: 'id' = #id)

```

Что произойдет при изменеии в первом поле — во второе будет поставлено новое значение и там сработает триггер на изменение и рассчитав значение для первого поля код действия его туда установит.

Если при этом изменения в первом поле не произошло — вся цепочка кодов остановится.

**Будьте внимательны** с такими взаимо-зависимыми встречными кодами действий — если они не приходят в равновесие — сервер зависнет в бесконечном цикле, пока не сработает таймаут.

#### <a name="cross-performance-of-actions-q2"></a> Особенность такого расчета для строки добавления?

В строке добавления нет действий — поэтому в ней этот прием не работает. Вы можете оставить изменяемым какое-то ключевое поле, а остальные рассчитывать `кодом только при добавлении`, заблокировав их именно при добавлении.

В этом случае можно перенести добавление во временную таблицу, а строку добавления отключить. Пример смотрите здесь: [https://youtu.be/Bn1P_qY3Ruo?t=1048](https://youtu.be/Bn1P_qY3Ruo?t=1048)

## <a name="opening-a-sub-temporal-table-and-returning-data-to-the-parent-table"></a> Открытие вложенной временной таблицы и возврат данных в родительскую

### <a name="opening-a-sub-temporal-table-and-returning-data-to-the-parent-table-qa"></a> Вопросы и ответы

#### <a name="opening-a-sub-temporal-table-and-returning-data-to-the-parent-table-q1"></a> Пример: пример с вложенной временной таблицей

Это классная задача, которая решается при помощи параметра `hide: true` функции [linkToDataTable][308]. Если этого параметра нет, то [linkToDataTable][308] открывает окно, а если есть `hide: true` — то окно не открывается, а функция вернет назад `hash` созданной временной таблицы.

**Те получается так:**

1. Вызывается временная таблица, которая должна заполниться по `insert` — [подробнее про автозаполнение][87].

2. `h_insert` взывает еще одну временную с параметром `hide: true`.

	Несмотря на то, что [linkToDataTable][308] является функцией действия ее можно [выполнить из кода][558].

3. Вложенная временная делает нужное вычисление.

4. `h_insert` получив `hash` вложенной временной забирает из нее данные.

Все это упаковывается в код `h_insert` примерно так:

```totum
=: selectList(table: 'tmp_table_2'; hash: $linkto; field: 'id')

linkto: linkToDataTable(table: 'tmp_table_2'; params: $row; hide: true)

row: rowCreate()

```

#### <a name="opening-a-sub-temporal-table-and-returning-data-to-the-parent-table-q2"></a> Пример: пример открыть вложенную временную кнопкой и действием вернуть данные в первую

Вы можете вызвать временную таблицу и передать в нее хэш временной таблицы, который получается через выполняемую только во временной таблице переменную `$#nth`.

Используя эту переменную в функциях типа [set][294] в параметре `hash` можно передать значение именно в ту версию временной таблицы из которой была открыта вторая.

**Порядок реализации такой:**

1. Кнопкой в первой временной открываем вторую временную таблицу.

	```totum
	=: linkToDataTable(table: 'tmp_table_2'; title: "Complex data"; params: $row; refresh: true)
		row: rowCreate(field: "hash" = $#nth)

	```

2. Вносим в нее данные.

3. Кнопкой настраиваем возврат данных из второй временной таблицы в первую.

	```totum
	=: set(table: 'tmp_table_1'; hash: #hash; field: 'data' = #data)

	```

#### <a name="opening-a-sub-temporal-table-and-returning-data-to-the-parent-table-q3"></a> Пример: пример для строки добавления

В строке добавления нет триггеров, поэтому код-действия можно выполнить только из `Кнопки`.

По умолчанию кнопки в строке добавления заблокированы, но их можно разблокировать параметром [Включить кнопку в строке добавления][536].

Вы можете вызвать временную таблицу и передать в нее хэш строки добавления, который получается через выполняемую только в коде переменную `$#ih`.

Используя эту переменную в функциях типа [set][294] в параметре `hash` можно передать значение именно в строку добавления открытую у пользователя.

**Порядок реализации такой:**

1. Кнопкой в строке добавления открываем временную таблицу.

	```totum
	=: linkToDataTable(table: 'tmp_table'; title: "Complex data"; params: $row; refresh: true)
		row: rowCreate(field: "insert_hash" = $#ih)

	```

2. Вносим в нее комплексные данные.

3. Кнопкой настраиваем возврат данных из временной таблицы в строку добавления.

	```totum
	=: set(table: 'table'; hash: #insert_hash; field: 'data' = #data)

	```

## <a name="features-of-calling-linkToInput-and-linkToButtons-from-popups"></a> Особенности вызова linkToInput и linkToButtons из всплывающих окон

### <a name="features-of-calling-linkToInput-and-linkToButtons-from-popups-qa"></a> Вопросы и ответы

#### <a name="features-of-calling-linkToInput-and-linkToButtons-from-popups-q1"></a> Зачем нужен параметр close: true при открытии linkToInput?

[linkToInput][459] и [linkToButtons][446] имеют специальный параметр `close: true`, который необходимо использовать вместо [закрыть после выполнения][192] у кнопки, которая их вызывает.

Если параметр передан как `true`, то после выполнения действия из `code` будет закрыто не только окошко функции, но и родительское всплывающее окно из которого оно было вызвано.

#### <a name="features-of-calling-linkToInput-and-linkToButtons-from-popups-q2"></a> Почему нельзя использовать Закрыть окно после выполнения у кнопки таблицы источника?

Потому что открывающееся окошко [linkToInput][459] и [linkToButtons][446] привязано к окну источнику и если его закрыть, то работать не будет.

## <a name="relative-changes-to-lists-and-numbers-via-set"></a> Относительные изменения списков и чисел через set

### <a name="relative-changes-to-lists-and-numbers-via-set-qa"></a> Вопросы и ответы

#### <a name="relative-changes-to-lists-and-numbers-via-set-q1"></a> Пример: пример с изменением остатка на складе

[set][294], [setList][295] и [setListExtended][302] могут установить не конечное значение в поле, а изменить относительно имеющегося.

Для этого вместо `=` используется `+` или `-`.

Например при добавлении операции по складу мы можем в таблице `Склад` менять остаток позиции:

```totum

=: set(table: 'warenhouse'; field: 'count' + $#nfv)

```

#### <a name="relative-changes-to-lists-and-numbers-via-set-q2"></a> Пример: пример с добавлением значений в список

Аналогично [set][294], [setList][295] и [setListExtended][302] могут **добавить** или **удалить** значение если их целевое поле является [селектом][36].

Для этого также вместо `=` используется `+` или `-`.

Например, добавим основания `45`, `56` и `17` в `list`:

```totum

=: set(table: 'table'; field: 'list' + json`["45","56","17"]`)

```

Что бы это работало у целевого поля должен быть включен параметр [множественные значения][195]!

#### <a name="relative-changes-to-lists-and-numbers-via-set-q3"></a> Как понять, какие значения были добавлены или удалены при изменении списка?

Например список меняется вручную и вам нужно совершить какое-то действие над добавленными значениями. Для этого есть способ через [listMinus][265] с `old` и `текущим` значением:

```totum

=: linkToDataText(title: "Показать добавленные значения"; text: $join)

	join: listJoin(list: $list; str: $#nl)

		list: listMinus(list: $#nfv; list: $#onfv)

```

Те мы из нового списка в поле вычитаем старый список и остаются только новые добавленные значения. `join` мы используем для ппоказа в `linkToDataText`.

Если вам нужно получить удаленные из списка значения, то `$#nfv` и `$#onfv` надо поменять местами — тогда останутся только те значения, которые были до изменения, а после их уже нет.

#### <a name="relative-changes-to-lists-and-numbers-via-set-q4"></a> Как автоматически добавить запись в поле с типом Комментарий?

В поле [комментарии][131] можно добавить отдельное сообщение кодом.

Добавить запись от текущего пользователя:

```totum

example1: set(table: 'test'; field: 'comment' = "Тестовый комментарий"; where:  'id' = 3)

```

Исторически получилось, что в [комментарии][131] добавление сообщения использует оператор `=`.

## <a name="inserting-rows-after-a-certain-row-when-sorting-by-order-field"></a> Вставка строк после определенной строки при сортировке по полю порядок

### <a name="inserting-rows-after-a-certain-row-when-sorting-by-order-field-qa"></a> Вопросы и ответы

#### <a name="inserting-rows-after-a-certain-row-when-sorting-by-order-field-q1"></a> Для чего нужен параметр показывать по месту сортировки?

Когда для таблицы включен параметр [сортировать по полю порядок][97] у пользователей есть возможность вручную изменять порядок следования строк.

Также это дает возможность сразу показывать строки по месту сортировки при их добавлении.

Еще одним бонусом является возможность вставить строки кодом поле определенной строки.

#### <a name="inserting-rows-after-a-certain-row-when-sorting-by-order-field-q2"></a> Как использовать n и after для вставки в нужное место?

Для того, что бы поместить новые строки после определенной имеющейся в таблице строки — надо получить `id` этой строки.

Потом в [insert][292] или [insertList][293] используется параметр `after` в которй передается `id ` после которого необходимо произвести вставку:

```totum

=: insert(table: 'table'; field: 'data' = #data; after: $select)

select: select(table: 'table'; field: 'id'; where: 'summ' >= 100; order: 'summ' asc)

```

Добавим строки после наименьшего значения суммы `summ >= 100`.

#### <a name="inserting-rows-after-a-certain-row-when-sorting-by-order-field-q3"></a> С какими сложностями можно столкнуться при постоянной вставке между имеющимися строками?

Дело в том, что при вставке между двумя имеющимися значениями при включенном `n` происходит его дробление. Несмотря на то, что `n` имеет очень большую разрядность — этот ресурс ограничен!

#### <a name="inserting-rows-after-a-certain-row-when-sorting-by-order-field-q4"></a> Какое техническое действие необходимо запроектировать в таких случаях?

Если у вас имеется таблица, в которую постоянно идет вставка между строк — необходимо поставить на крон выполнение [normalizeN][323].

При нормализации `n` не отрабатывает единица пересчета. Те не происходит пересчета строк. Если поля в строках ссылаются на `#n` они не будут пересчитаны!

## <a name="using-listReplace-to-perform-actions"></a> Использование listReplace для выполнения действий

### <a name="using-listReplace-to-perform-actions-qa"></a> Вопросы и ответы

#### <a name="using-listReplace-to-perform-actions-q1"></a> Пример: пример веерного выполнения действий с проверкой условий

[listReplace][486] можно использовать не только в `кодах`, но и в `кодах действий` для выполнения действий.

В этом случае на каждой итерации он будет выполнять указанное действие. Конечно, на каждой итерации вы можете выполнять проверку и не выполнять ничего если проверка не пройдена.

Например, будем плюсовать в поле `field` `+1` если значение списка четное (делится на 2 без остатка):

```totum

=: listReplace(list: $list; action: $if; value: "val")

		list: listNumberRange(min: 0; max: 10; step: 1)

	if: if(condition: math`$round - $division` = 0; then: $action)

			division: $#val / 2
			round: round(num: $division; type: "up"; step: 1; dectimal: 0)

		action: set(table: 'table'; field: 'field' + 1)

```

## <a name="using-the-panels-of-other-tables"></a> Использование панелей других таблиц

### <a name="using-the-panels-of-other-tables-qa"></a> Вопросы и ответы

#### <a name="using-the-panels-of-other-tables-q1"></a> Как открыть панель в другой таблице?

Для этого используется функция [linkToPanel][310].

#### <a name="using-the-panels-of-other-tables-q2"></a> Как открыть панель передав данные в поля?

Используйте параметр `field: 'field_name' = $value`.

#### <a name="using-the-panels-of-other-tables-q3"></a> Как скрыть некоторые поля в панели?

Это можно задать в секции форматирования этих полей.

Используется [setFormat][315] с параметром `hide`:

```totum

f1=: setFormat(hide: "panel" = true)

```

Есть 2 варианта скрытия в панелях:

+ `panel` — в этом случае поле участвует в распределении полей по правой и левой колонке и его можно показать передав по условиям `hide: "panel" = false`

+ `extpanel` — в этом случае поле скрывается без возможности показа по условиям и исключается из расчета распределения полей по колонкам!

Помимо этих двух, есть еще параметры `hide` не относящиеся к панелям:

+ `"mobile" = true` — скрывеет поле в мобильном интерфейсе.

+ `"form" = true` — скрывает поле в формах.

+ `"table" = true` — снимает галочку видимости в глазе.

#### <a name="using-the-panels-of-other-tables-q4"></a> Какие два типа скрытия возможны для полей при открытии linkToPanel?

Есть 2 варианта скрытия в панелях:

+ `panel` — в этом случае поле участвует в распределении полей по правой и левой колонке и его можно показать передав по условиям `hide: "panel" = false`

+ `extpanel` — в этом случае поле скрывается без возможности показа по условиям и исключается из расчета распределения полей по колонкам!

#### <a name="using-the-panels-of-other-tables-q5"></a> Как открыть панель из панели?

Точно так же как и из таблицы. Помните, что если вам нужно, что бы таблица или панель из которой вы открываете вложенную панель обновились — то вам нужно указать `refresh: true`.

## <a name="how-do-I-open-the-file-download-window"></a> Как отрыть окно загрузки файлов?

### <a name="how-do-I-open-the-file-download-window-qa"></a> Вопросы и ответы

#### <a name="how-do-I-open-the-file-download-window-q1"></a> Пример: пример загрузки файла при помощи linkToFileUpload

Все делается при помощи [linkToFileUpload][542]

```totum
= : linkToFileUpload(title: "Select file"; code: $code; limit: 1; type: "*"; refresh: true)

code: "=: set(table: $#ntn; field: 'h_file' = $#input)"

```

Вся структура выбранных файлов передается в переменную `$#input`.

Также если включем параметр множественные в поле файл, то можно использовать `+` — `field: 'h_file' + $#input`

## <a name="using-an-arbitrary-form-in-linkToInput"></a> Использование произвольной формы в linkToInput

### <a name="using-an-arbitrary-form-in-linkToInput-qa"></a> Вопросы и ответы

#### <a name="using-an-arbitrary-form-in-linkToInput-q1"></a> Пример: пример использования html-формы

В [linkToInput][459] есть возможность засунуть собственную произвольную форму. Что же будет передано в `$#input` — содержимое скрытого поля `textarea` с `id="ttmInput"`:

```totum

=: linkToInput(title: "Тест"; html: "<div id='testInputForm'><div style='display: grid; grid-template-columns: 1fr 1fr'><select name='lang' class='form-control'><option>Eng</option><option>Ru</option></select><select name='type' class='form-control'><option>Одинарная</option><option>Множественная</option></select></div><textarea  class='form-control' name='title' placeholder='Введите название здесь'></textarea></div><textarea id='ttmInput' style='display:none'></textarea><script>function testInputForm(){let val={}; $('#testInputForm').find('select,textarea').each((k, v)=>{ val[v.name] = v.value }); $('#ttmInput').val(JSON.stringify(val))}; testInputForm(); $('#testInputForm').on('change keyup', 'select,textarea', testInputForm) </script>" ; code: $code; refresh: true; button: "Сохранить")

code: "=: set(table: $#ntn; field: 'h_testpole' = $#input)"

```

## <a name="how-do-I-perform-actions-on-the-lines-highlighted-with-checkmarks"></a> Как выполнить действия над строками выделенными галочками?

### <a name="how-do-I-perform-actions-on-the-lines-highlighted-with-checkmarks-qa"></a> Вопросы и ответы

#### <a name="how-do-I-perform-actions-on-the-lines-highlighted-with-checkmarks-q1"></a> Пример: пример с $#ids

```totum

= : if(condition: $#ids = $#lc; then: $text; else: $action)

	text: linkToDataText(title: "Предупреждение"; text: "Не выбрано ни одной строки")

action: setList(table: $#ntn; field: 'status' = 3; where: 'id' = $#ids; log: true)

```

Мы сначала проверяем выделено ли хоть что-то и потом уже совершаем действие.

Работает для кода действия кнопок. `id` выделенный строк передаются в переменную `$#ids` в виде `list`, поэтому в этом примере мы сравниваем `$#ids` с пустым списком.

Строго говоря именно здесь можно сравнить и с `""` тк не бывает пустых `id`. Но мы за правильное однообразие!

#### <a name="how-do-I-perform-actions-on-the-lines-highlighted-with-checkmarks-q2"></a> Зачем нужен параметр Снимать галочки после выполнения?

Мы рекомендуем ставить [снимать галочки после выполнения][191], тк пользователь может совершить следующее действие, забыв, что у него остались выделенные строки после первого действия.

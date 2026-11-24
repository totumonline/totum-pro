# Форматирование продвинутый уровень

## <a name="additional-buttons-in-the-context-bar"></a> Дополнительные кнопки в контекстной панели

### <a name="additional-buttons-in-the-context-bar-qa"></a> Вопросы и ответы

#### <a name="additional-buttons-in-the-context-bar-q1"></a> Каким образом вывести кнопку в панель по правой кнопке мыши?

[Описание panel-функций в документации][559]

Для этого в коде форматирования надо использовать префикс `p1=:` вместо стандартного `f1=:`.

С этим префиксом работают не все функции форматирования, а только `panel-функции`:

+ [panelButtons][461] — размещает на панели дополнительные кнопки.

+ [panelImg][462] — размещает на панели изображение.

+ [panelHtml][463] — отображает на панели html.

```totum
p0=: panelHtml(html: $strpanel)
	strpanel: str`"Физически на Складе<b>" ++ $flat ++ "</b>из которых" ++ $block ++ "в резерве."`
		flat: select(table: 'goods'; field: 'count'; where: 'id' = #name)
		block: select(table: 'goods'; field: 'block'; where: 'id' = #name)

p1=: panelButtons(condition: $block != 0; button: $b1; refresh: true)
	b1: rowCreate(field: "text" = "ПОСМОТРЕТЬ РЕЗЕРВЫ"; field: "code" = "show_block")

p2=: panelButtons(button: $b2; refresh: true)
	b2: rowCreate(field: "text" = "ПОСМОТРЕТЬ ДВИЖЕНИЕ"; field: "code" = "show_move")

```

#### <a name="additional-buttons-in-the-context-bar-q2"></a> panel-функции могут быть вызваны из секции f?

Нет, только из `p`.

#### <a name="additional-buttons-in-the-context-bar-q3"></a> Секция f и p имеют сквозную нумерацию?

Нет у них собственные раздельные нумерации.

Хорошим оформлением кода является ставить `p` секцию после того как закончились все `f` — не смешивая.

#### <a name="additional-buttons-in-the-context-bar-q4"></a> Можно ли ограничить показ кнопки по условиям?

Конечно, для этого в [panelButtons][461] и прочих `panel-функциях` есть параметр `condition`.

#### <a name="additional-buttons-in-the-context-bar-q5"></a> Вызывает ли срабатывание кнопки refresh в текущей таблице?

Нет — если кнопка выполняет действие в текущей таблице, то появится уведомление о том, что таблица была изменена.

Что бы автоматическа сделать `refresh` таблице необходимо передать `refresh: true` в `panelButtons`.

#### <a name="additional-buttons-in-the-context-bar-q6"></a> Какого вида необходимо передать row, чтобы сформировать кнопку?

Обязательные ключи это `text` и `code`:

```totum

=: rowCreate(field: "text" = "ПОСМОТРЕТЬ РЕЗЕРВЫ"; field: "code" = "show_block")

```

#### <a name="additional-buttons-in-the-context-bar-q7"></a> Каким образом можно передать код для выполнения в “code”?

В `code` передается строка. Например:

```totum

=: rowCreate(field: "text" = "ПО УМОЛЧАНИЮ"; field: "code" = $code)

code: "=: set(table: $#ntn; field: 'field_name' = 100)"

```

Также в качестве `code` можно передать `name` поля в текущей таблице из которого будет взят код-действия.

```totum

=: rowCreate(field: "text" = "ПО УМОЛЧАНИЮ"; field: "code" = "field_name")

```

И третий способ, когда вы хотите передать многострочный код и иметь к нему доступ в этом же окне:

```totum

=: rowCreate(field: "text" = "ПО УМОЛЧАНИЮ"; field: "code" = $code)

```code:totum

=: set(table: $#ntn; field: 'field_name' = $default)

default: 100

```

Этот блок при сохранении сохраняется как текст.

Если указано `:totum` — то будет активирована подсветка totum-кода.

Названия строк и ссылок на строки `$default` исключены из подсветки текущего кода и никак не пересекаются с именами вне выделенной области!
```

#### <a name="additional-buttons-in-the-context-bar-q8"></a> Какие переменные окружения передаются в выполняемый код?

`$#ntn`, `$#nci`, `$#nth`, `#id` (и прочие строчные поля).

Если вам нужно передать в код дополнительные переменные, то надо передать `row` в параметр `vars`.

## <a name="additional-pictures-and-texts-in-the-right-click-panel"></a> Дополнительные картинки и тексты в панели по правой кнопке мыши

### <a name="additional-pictures-and-texts-in-the-right-click-panel-qa"></a> Вопросы и ответы

#### <a name="additional-pictures-and-texts-in-the-right-click-panel-q1"></a> Пример: пример показа текста по правой кнопке мыши

+ [panelHtml][463] — отображает на панели html.

```totum
p1=: panelHtml(html: $strpanel)
	strpanel: str`"Физически на Складе<b>" ++ $flat ++ "</b>из которых" ++ $block ++ "в резерве."`
		flat: select(table: 'goods'; field: 'count'; where: 'id' = #name)
		block: select(table: 'goods'; field: 'block'; where: 'id' = #name)

```

#### <a name="additional-pictures-and-texts-in-the-right-click-panel-q2"></a> Пример: пример показа картинки в панели по правой кнопке мыши

+ [panelImg][462] — размещает на панели изображение.

```totum

p1=: panelImg(img: #file[0][file])

```

## <a name="using-texts-to-replace-the-field-value"></a> Использование текстов замещающих значение поля

### <a name="using-texts-to-replace-the-field-value-qa"></a> Вопросы и ответы

#### <a name="using-texts-to-replace-the-field-value-q1"></a> Можно ли текст взять как значение поля кодом?

Нет. Параметр `text` функции [setFormat][315] изменяет только то, как значение отображается в поле и не изменяет само значение.

#### <a name="using-texts-to-replace-the-field-value-q2"></a> Как можно форматировать числа используя текст?

Все отрицательные представим в виде `(1 000,00)`:

```totum

f1=: setFormat(condition: $#nfv < 0; text: $text)
	text: strAdd(str: "("; str: $numform; str: ")")
		numform: numFormat(num: $mod; decsep: ","; thousandssep: " ")
			mod: modul(num: $#nfv)

```

#### <a name="using-texts-to-replace-the-field-value-q3"></a> Как можно форматировать числа параметрами префикса, постфикса и раделителей?

Для полей типа `Число` есть параметры определяющие форматирование:

+ [Денежный формат][152] — форматирует число в денежном представлении.

Все последующие дочерние к `Денежному` и перекрывают его если заполнены:

+ [Префикс][526]

+ [Разделитель тысячных][525]

+ [Разделитель десятичных][524]

+ [Постфикс][523]

Для этих параметров можно указать разные значения для положительных и отрицательный чисел используя разделитель `**` — слева от разделителя для положительны, справа для отрицательнх.

#### <a name="using-texts-to-replace-the-field-value-q4"></a> Пример: пример использования текста для расчета информационной динамической информации

Иногда бвает нужно в поле футера показывать сумму с учетом префильтра.

Если просто сделать:

```totum

=: listSum(list: $list)

list: selectList(table: $#ntn; field: 'field')

```

То сумма никак не будет менятся в зависимости отвыбранного префильтра. Но записать в `where` ссылку на префильтр в коде будет кардинально неправильно!

Поэто му Код остается пустым, а все пишется в форматирование:

```totum

f1=: setFormat(text: $sum)

sum: listSum(list: $list)

list: selectList(table: $#ntn; field: 'field'; where: 'connect' = #fl_connect)

```

## <a name="block-adding-or-deleting-by-the-number-of-rows-in-the-table"></a> Блокировка добавления или удаления по количеству строк в таблице

### <a name="block-adding-or-deleting-by-the-number-of-rows-in-the-table-qa"></a> Вопросы и ответы

#### <a name="block-adding-or-deleting-by-the-number-of-rows-in-the-table-q1"></a> Пример: пример блокировки добавления и удаления строк по количеству строк и сумме

Например, нам нужно запретить добавлять в таблицу больше чем 5 строк. В форматирование таблицы пишем:

```totum

f1=: setTableFormat(condition: $count > 5; blockadd: true)

count: listCount(list: $list)

list: selectList(table: $#ntn; field: 'id')

```

#### <a name="block-adding-or-deleting-by-the-number-of-rows-in-the-table-q2"></a> Являются ли эти блокировки сильными или слабыми?

Это слабая блокировка. Если вы хотите установить сильную — это делается через проверку в `коде действия таблицы`.

## <a name="hiding-fields-by-conditions"></a> Скрытие полей по условиям

### <a name="hiding-fields-by-conditions-qa"></a> Вопросы и ответы

#### <a name="hiding-fields-by-conditions-q1"></a> Пример: пример скрытие полей в панелях и мобильном интерфейсе

Скрытие в панели с возможностью показать по условию:

```totum

f1=: setFormat(hide: "panel" = true)

f2=: setFormat(condition: #type = 1; hide: "panel" = false)

````

Скрытие в панели без возможности показа и с исключением из распределения по колонкам:

```totum

f1=: setFormat(hide: "extpanel" = true)

````

Скрытие в мобильном интерфейсе:

```totum

f1=: setFormat(hide: "mobile" = true)

f2=: setFormat(condition: #type = 1; hide: "mobile" = false)

````

#### <a name="hiding-fields-by-conditions-q2"></a> Пример: пример скрытие колонок строчной части по условиям

Скрытие поля в web идет немного по другому пути — оно выполняется через форматирование таблицы и параметр `fieldhide` функции `setTableFormat`:

```totum

f1=: setTableFormat(fieldhide: 'field' = true)

f2=: setTableFormat(condition: #h_type = 1; fieldhide: 'field' = false)

```

#### <a name="hiding-fields-by-conditions-q3"></a> Является ли такое скрытие сильным или слабым?

Такое скрытие слабое — пользователь может открыть настройки полей и показать их.

## <a name="displaying-buttons-in-the-line-part"></a> Отображение кнопок в строчной части

### <a name="displaying-buttons-in-the-line-part-qa"></a> Вопросы и ответы

#### <a name="displaying-buttons-in-the-line-part-q1"></a> Пример: пример использования buttons для setTableFormat

Для этого вы создаете кнопку или несколько кнопок в хедере или футере и передаете списком их `name` в параметр `buttons` функции `setTableFormat` в форматировании таблицы:

```totum

f1=: setTableFormat(buttons: json`["field_1","field_2"]`)

```

Они будут перенесены к кнопкам строчной части только в интерфейсе пользователей! Создатель будет видеть их там же, где их плейсмент и `sort`.

Порядок их у пользователей будет после стандартной кнопки `Добавить` в порядке возрастания `sort`.

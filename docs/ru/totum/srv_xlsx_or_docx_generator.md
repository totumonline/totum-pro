# SERVICES Генерация XLSX и DOCX документов
- - -
[![youtube](https://totum.totum.online/fls/365_608_file.png)](https://www.youtube.com/watch?v=r5Bkw-nEa_U)

**[Видео на YouTube ⟹](https://www.youtube.com/watch?v=r5Bkw-nEa_U)**

**[Видео на VK ⟹](https://vk.ru/video873495087_456239105)**
- - -

## <a name="srv-xlsx-new"></a> Как сгенерировать xlsx на основе данных таблицы и записать его в поле типа Файл

Для генерации `xlsx` используется функция `serviceXlsxGenerator`. У нее два обязательных параметра:

+ **template** – в него необходимо передать имя файла на сервере с `xlsx` шаблоном или указание на то, что необходимо использовать чистый шаблон.

	+ `*NEW*` — использовать чистый шаблон.
	
	+ `#file_field[0][file]` — получить имя файла не сервере на основе поля Файл.

+ **data** — массив для замены.

Когда используется `*NEW*` в `data` надо передать `rowList`, полученный любым способом.

+ **titles** — если вам нужно добавить заголовки колонок в генерируемый файл при использовании `*NEW*` в шаблоне — передайте `list` с заголовками в этот параметр.

	Пример этой операции:

```totum
= : set(table: $#ntn; field: 'result' = $list; where: 'id' = #id; log: true)

	list: listCreate(item: $from_services)
	//file: rowCreate(field: "name" = "some_name.xlsx"; field: "filestring" = $from_services)

from_services: serviceXlsxGenerator(template: "*NEW*"; data: #data; answertype: "filerow"; name: "some_name"; titles: json`["Title A","Title B","Title C"]`)
```

Что бы сделать `set` в поле Файл необходимо передать `rowList` (даже если у поля Файл выключен параметр `multi`). В этом `rowList` обязательны две колонки — `name` и `file` или `filestring`. Сервисы не могут возвращать `file` тк это имя на сервере Тотум, а при ответе от Сервиса файл в тотуме еще не создан, поэтому используется ключ `filestring` (содержимое файла).

```totum
= : set(table: $#ntn; field: 'result' = $list; where: 'id' = #id; log: true)

	list: listCreate(item: $file)
		file: rowCreate(field: "name" = "some_name.xlsx"; field: "filestring" = $from_services)

from_services: serviceXlsxGenerator(template: "*NEW*"; data: #data_row_list)
```

Если не задан параметр `answertype` то функция сервиса по умолчанию возвращает содержимое файла — `filestring`. однако мы можем указать в `answertype` другие значения:

+ `filerow` — в этом случае вернется готовый `row` с ключами `name` и `filestring`.

	Для того, что бы задать контролируемый `name` надо добавить соответвующий параметр (смотри первый пример на этой странице). Он может быть задан с расширением или без. Если расширение не будет задано, то тотум подставит на основании логики функции.


## <a name="srv-xlsx-template"></a> Как сгенерировать xlsx на основе шаблона

Для того, что бы сделать замену по шаблону, необходимо в шаблоне проставить переменные для замены вида — `${variable_name}`.

+ Переменные должны быть обязательно переданы, иначе они будут отображены как текст.

+ Если вы хотите скрыть переменную — то передайте в качестве ее значения пустоту — `""`.

Переменные можно заменить по одной, а можно добавить несколько строк. В это случае вам нужно взять первую переменную в строке и в нее передать `rowList`, содержащий переменные для замены:

```totum
= : set(table: $#ntn; field: 'result' = $list; where: 'id' = #id; log: true)

list: listCreate(item: $from_services)

from_services: serviceXlsxGenerator(template: #template[0][file]; data: $data; answertype: "filerow"; name: "some_name")

data: rowCreate(field: "title" = "Some title"; field: "value" = "Some value"; field: "field_1" = $rowList)

rowList: rowListCreate(field: "field_1" = json`[1,2,3]`; field: "field_2" = json`["A","B","C"]`; field: "field_3" = json`["A1","B2","C3"]`; field: "field_4" = "")
```

## <a name="srv-xlsx-formulas"></a> Как вставить в xlsx формулу

Генератор работает на PhpSpreadsheet: значение переменной, которое начинается со знака `=`, записывается в ячейку как формула.

+ Имена функций — только на английском: `СУММ` → `SUM`, `СРЗНАЧ` → `AVERAGE`.
+ Номера строк диапазона считаются в коде: если вставляется несколько строк, итоговая формула под ними должна заканчиваться номером последней вставленной строки. Формула собирается через `` str`…` ``.

Пример: в пятой строке шаблона — переменные `${name}` и `${cost}` (`${cost}` в колонке `G`), под ней итог `${total}`. В `${name}` передается `rowList` — строки вставляются с 5-й, сумма по колонке `G` считается с 5-й по последнюю вставленную:

```totum
=: set(table: $#ntn; field: 'generated_file' = json`[{"name": "report.xlsx", "filestring": $filestring}]`; where: 'id' = #id)

filestring: serviceXlsxGenerator(template: #h_xlsx_template[0][file]; data: $data)
	data: rowCreate(field: "name" = $rows; field: "total" = $total)
		rows: selectRowList(table: 'orders'; field: 'name'; field: 'cost'; order: 'id' asc)
		total: str`"=SUM(G5:G" + $last_row + ")"`
			last_row: math`4 + $count`
				count: listCount(list: $rows)
```

## <a name="srv-images"></a> Как добавить картинки, вместо одной из переменных

В качестве подмены переменной можно передать картинку, которая будет имплементирована в документ.

Для этого в переменную надо передать массив вида:

```totum

data: rowCreate(field: "title" = "Some title"; field: "image" = $imagerow)
	imagerow: rowCreate(field: "type" = "image"; field: "height" = 100; field: "width" = 100; field: "imagetype" = "png"; field: "filestring" = $base64)
		base64: strBaseEncode(str: $getcontent)
			getcontent: fileGetContent(file: #h_image[0][file])
```

Состав этого `row`:

+ `type` — значение `image`

+ `height` и/или `width` — значение размеров в пикселях.

+ `imagetype` — тип картинки (обязательно корректный!). Автоматически можно получить из люча `ext` поля Файл `#image[0][ext]`.

+ `filestring` — бинарник картинки упакованный в `base64`

	+ Бинарник получается функцией `fileGetContent(file: #h_image[0][file])`

	+ `base64` функцией `strBaseEncode(str: )`
	
Если вы передаете картинки для заполнения нескольких строк, то у вас должен быть `rowList` в каждой строке которого, лежит информация о своей картинке:

```totum
= : set(table: $#ntn; field: 'result' = $list; where: 'id' = #id; log: true)

list: listCreate(item: $from_services)

from_services: serviceXlsxGenerator(template: #template[0][file]; data: $data; answertype: "filerow"; name: "some_name")

data: rowCreate(field: "title" = "Some title"; field: "value" = "Some value"; field: "field_1" = $rowList)

rowList: rowListCreate(field: "field_1" = json`[1,2,3]`; field: "field_2" = json`["A","B","C"]`; field: "field_3" = json`["A1","B2","C3"]`; field: "field_4" = $imagerowlist)

imagerowlist: listReplace(list: $list_of_files; action: $imagerow; value: "image_var")
			list_of_files: selectList(table: $#ntn; field: 'image'; order: 'id' asc)
	imagerow: rowCreate(field: "type" = "image"; field: "height" = 100; field: "imagetype" = $#image_var[0][ext]; field: "filestring" = $base64)
		base64: strBaseEncode(str: $getcontent)
			getcontent: fileGetContent(file: $#image_var[0][file])
```

## <a name="srv-docx-template"></a> Как сгенерировать docx по шаблону

- - -
[![youtube](https://totum.totum.online/fls/365_608_file.png)](https://www.youtube.com/watch?v=74Y1iwqOS4w)

**[Видео на YouTube ⟹](https://www.youtube.com/watch?v=74Y1iwqOS4w)**

**[Видео на VK ⟹](https://vk.ru/video873495087_456239106)**
- - -

Переменные в шаблоне обозначаются аналогичным шаблонам `xlsx` образом.

Для того, что бы сделать замену по шаблону, необходимо в шаблоне проставить переменные для замены вида — `${variable_name}`.

+ Переменные должны быть обязательно переданы, иначе они будут отображены как текст.

+ Если вы хотите скрыть переменную — то передайте в качестве ее значения пустоту — `""`.

Для генерации используется функция `serviceXlsxGenerator` — работает аналогично `xlsx`.

Также можно сделать добавление нескольких строк в таблицу, вставленную в `docx` документ.


## <a name="srv-multi-files"></a> Как сгенерировать несколько файлов по одному шаблону с разными данными

1. Используется `answertype: "filerowlist"` — в этом случае `serviceDocxGenerator` и `serviceXlsxGenerator` вернут `rowList` с ключами `name` и `filestring`.

2. Что бы использовать разные данные для одного шаблона в `data` необходимо передать `rowlist` — в котором каждая строка будет содержать данные для отдельного файла.

3. Для того, что бы передать корректные имена созданным файлам — необходимо в `name` передать список из имен файлов.

> На самом деле `template` тоже может быть списком из разных шаблонов — подстановка будет идти 1 к 1 с порядком в `data`.

> Множественная генерация — для удобства. К серверу запросы уйдут отдельно на каждую строку в `data`. 

```totum
= : set(table: $#ntn; field: 'result' = $from_services; where: 'id' = #id; log: true)

from_services: serviceDocxGenerator(template: #template[0][file]; data: $data_list; answertype: "filerowlist"; name: $list_names; pdf: true)

data_list: listCreate(item: $data_1; item: $data_2)

	data_1: rowCreate(field: "var_1" = "Some title"; field: "var_2" = "Some value"; field: "field_1" = $rowList_1)
		rowList_1: rowListCreate(field: "field_1" = json`[1,2,3]`; field: "field_2" = json`["A","B","C"]`; field: "field_3" = json`["A1","B2","C3"]`)

	data_2: rowCreate(field: "var_1" = "Some title 2"; field: "var_2" = "Some value 2"; field: "field_1" = $rowList_2)
		rowList_2: rowListCreate(field: "field_1" = json`[4,5,6]`; field: "field_2" = json`["D","F","Y"]`; field: "field_3" = json`["D1","F2","Y3"]`)
		
list_names: listCreate(item: "some_name_1"; item: "some_name_2")
```

## <a name="srv-pdf"></a> Как сгенерировать файлы сразу в PDF

Включить параметр `pdf: true` в функции `serviceDocxGenerator` или `serviceXlsxGenerator`.

В этом случае, помимо генерации документа по шаблону, итоговый файл будет преобразован в `.pdf`.


## <a name="srv-download"></a> Как отправить сгенерированные файлы на скачивание в браузере

- - -
[![youtube](https://totum.totum.online/fls/365_608_file.png)](https://www.youtube.com/watch?v=tSPHkVz5NMI)

**[Видео на YouTube ⟹](https://www.youtube.com/watch?v=tSPHkVz5NMI)**

**[Видео на VK ⟹](https://vk.ru/video873495087_456239107)**
- - -

Отправкой файлов на скачивание в браущере выполняет функция `linkToFileDownload`.

Ее особенностью является то, что помимо ключей `name` и `file/filestring` необходимо передать `mime-type` в ключ `type`.

Хороший список `mime-type` вы можете найти на странице документации для [mozilla](https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types/Common_types).

Если использовать тип возврата `filerowlist`:

```totum
= : linkToFileDownload(files: $add_mimetype)

	add_mimetype: listReplace(list: $from_services; action: "type" = "application/vnd.openxmlformats-officedocument.wordprocessingml.document")

	// for row when you use answertype: "filerow"
	row: rowAdd(row: $from_services; field: "type" = "application/vnd.openxmlformats-officedocument.wordprocessingml.document")
	
from_services: serviceDocxGenerator(template: #template[0][file]; data: $data_list; answertype: "filerowlist"; name: "some name")

data_list: listCreate(item: $datarow)
	datarow: "SOME DATA ROW FOR YOUR FILE"

```

Если использовать тип возврата `filerow`:

```totum
= : linkToFileDownload(files: $row)

	row: rowAdd(row: $from_services; field: "type" = "application/vnd.openxmlformats-officedocument.wordprocessingml.document")
	
from_services: serviceDocxGenerator(template: #template[0][file]; data: $data; answertype: "filerow"; name: "some name")

data: "SOME DATA ROW FOR YOUR FILE"

```

## <a name="srv-email"></a> Как отправить сгенерированные файлы по email

```totum
= : emailSend(to: "totum@totum.online"; title: "some title"; body: "some body"; files: $from_services)

from_services: serviceDocxGenerator(template: #template[0][file]; data: $data_list; answertype: "filerowlist"; name: "some name")

data_list: listCreate(item: $datarow)
	datarow: "SOME DATA ROW FOR YOUR FILE"
```
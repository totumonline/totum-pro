# MeiliSearch

---
[![youtube](https://totum.totum.online/fls/365_608_file.png)](https://youtu.be/QyqxL-NU7jc)

**[Видео на YouTube ⟹](https://youtu.be/QyqxL-NU7jc)**

**[Видео на VK ⟹](https://vk.ru/video873495087_456239109)**

---

Общий поиск по таблицам (кнопка поиска в верхней панели) работает на одном из двух движков, оба настраиваются в таблице `ttm__search_settings`:

+ **MeiliSearch** — отдельный сервер полнотекстового поиска, в который Totum передает данные строк (разделы ниже).
+ **[Поиск по триграммам (TRGM)][686]** — поиск средствами PostgreSQL прямо по данным таблиц, без отдельного сервера.

## <a name="install-meili"></a> Устанавливаем meilisearch

> До версии V7 meilisearch устанавливался скриптом установки — смотрите наличие папки `/home/totum/meilifiles`.

Далее инструкция установки на тот-же сервер, на который установлен Totum:

От пользователя totum:

```
su totum
```

Создаем папку:

```
cd /home/totum && mkdir meilisearch && cd ./meilisearch
```

Скачиваем бинарник:

```
curl -L https://install.meilisearch.com | sh
```

Генерируем файл ключа:

```
printf "MEILI_MASTERKEY=%s\n" "$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c24)" > /home/totum/meilisearch/meili_masterkey
```

Создаем файл сервиса (одна многострочная команда):

```
cat << 'EOF' > /home/totum/meilisearch/meilisearch.service
[Unit]
Description=Meilisearch
After=systemd-user-sessions.service

[Service]
Type=simple
EnvironmentFile=/home/totum/meilisearch/meili_masterkey
WorkingDirectory=/home/totum/meilisearch
ExecStart=/home/totum/meilisearch/meilisearch --no-analytics --db-path ./meilifiles --env production --master-key $MEILI_MASTERKEY
User=totum
Group=totum
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF
```

Переключаемся в root:

```
exit
```

Подключаем сервис:

```
ln -s /home/totum/meilisearch/meilisearch.service /etc/systemd/system/meilisearch.service
```
```
systemctl daemon-reload
```
```
systemctl enable meilisearch.service
```
```
systemctl start meilisearch.service
```
```
service meilisearch status
```


## <a name="s-base-settings"></a> Подключаем базу поиска к схеме Totum

<img src="https://totum.totum.online/fls/365_586_file.png"/>

Заполняем `h_host` — `http://localhost:7700` (можно кликнуть правой кнопкой мыши и нажать `По умолчанию`).

Если хост на удаленном сервере, то должен быть введен адрес удаленного сервера.

В `h_key` записываем ключ, созданный на этапе установки, с которым запускается база поиска.

Ключ в файле `meili_masterkey`:

```
cat /home/totum/meilisearch/meili_masterkey
```

В `index_name` записываем схему в базе поиска — хорошей практикой будет делать ее аналогичной схеме Totum.

> Схема в базу поиска добавляется в момет сохранения значения в поле — постарайтесь делать это один раз.

Проверить добавление схемы можно нажав на `Connect code`

`Search limit` по умолчанию 20. Если вам нужно выводить другое максимальное количество вариантов поиска — введите число.

> Не рекомендуем ставить большое число, так как они все равно не будут помещаться на экран.

Задать настройки поиска — заполните `settings` следующими параметрами (это можно сделать по правой кнопке мыши на поле):

```json
{
  "displayedAttributes": [
    "pk",
    "index",
    "catalog",
    "title",
    "text"
  ],
  "searchableAttributes": [
    "title",
    "index"
  ],
  "attributesForFaceting": [
    "catalog",
    "table"
  ]
}
```

`Show settings` — показывает текущие настройки индекса (схемы) поиска.

`Index it` — позволяет вручную передать строки в индекс поиска, указав `id_таблицы` + `/` + `id-строк` через запятую.

+ Если строки в индексе отсутствовали — они будут добавлены.

+ Если присутствовали, то они будут обновлены.

> Таблица при этом обязательно должна быть добавлена в таблицы поиска и иметь техническое поле `ttm_search` в строчной части с правильным форматом данных!

`Delete from index` — позволяет вручную удалить строки из индекса аналогично `Index it`.

> Если передать `table_id/*ALL*`, то будет удален индекс поиска по всей таблице.

`Search` — поиск, результат которого показывается в `json`.

> За показ кнопки поиска в интерфейсе отвечает поле `h_get_updates`. Оно рассчитывается кодом на основе `h_host`, `h_key`, `h_index_name`, `h_settings`, но вы можете установить значение вручную. Например отключить, пока не введены настройки таблиц.

## <a name="s-table-settings"></a> Настраиваем данные поиска в целевой таблице

Создаем поле тип [данные][38] с `name` = `ttm_search` (обязательно!) в целевой таблице. Поиск будет обновляться при добавлении (строки), изменении и удалении (строки) этого поля. В базу поиска передается содержимое этого поля, которое должно соответствовать формату:

```
{
  "index": "data for search",
  "title": "title for this index",
  "catalog": "catalog name from ttm__search_catalog"
}
```

![base](https://totum.totum.online/fls/365_587_file.png)

**Например:**

```totum
= : rowCreate(field: "index" = str`#field_1 ++ #field_3`; field: "title" = #field_3; field: "catalog" = "video")
```

В параметр каталог передается `name` каталога, который надо создать в таблице `ttm__search_catalog`. 

Для простой таблицы не забудьте пересчитать строки, чтобы данные в `ttm_search` обновились и после этого проверьте структуру данных в паре строк.

## <a name="s-table-add"></a> Добавляем таблицы в поиск

![base](https://totum.totum.online/fls/365_588_file.png)

После этого необходимо добавить таблицу в строчную часть в `ttm__search_settings` и заполнить параметры `code` и `buttons`.

+ `code` — код действия, который будет выполнен при клике на плашку результата поиска. Он выполняется из строки таблицы, в которой содержится результат поиска и получает соответствующие данные окружения.

+ `buttons` — расширение возможных действий при помощи добавления кнопок в плашку результата поиска.

Чтобы проиндексировать (добавить в базу поиска данные) данные добавленной таблицы, нажмите `Index table`.

## <a name="s-trgm"></a> Поиск по триграммам (TRGM)

PRO. Поиск без MeiliSearch — средствами расширения PostgreSQL `pg_trgm` (поиск по сходству триграмм — устойчив к опечаткам и неполным словам). Ищет прямо в таблицах Totum по полю `ttm_search`: отдельный сервер не нужен, данные никуда не передаются, изменения строк попадают в поиск сразу, без индексации.

### <a name="s-trgm-install"></a> Расширение pg_trgm

Скрипт установки создает расширение в базе `totum` и задает порог сходства. Если система установлена раньше и расширения нет — выполните от пользователя `postgres`:

```
psql -d totum -c "CREATE EXTENSION IF NOT EXISTS pg_trgm SCHEMA public;"
psql -c "ALTER DATABASE totum SET pg_trgm.word_similarity_threshold = 0.3;"
```

`pg_trgm.word_similarity_threshold` — порог сходства от `0` до `1` (по умолчанию в PostgreSQL `0.6`): чем меньше, тем больше нечетких совпадений попадает в выдачу.

### <a name="s-trgm-settings"></a> Включение

В хедере `ttm__search_settings` (раздел «Поиск по триграммам») включите `Использовать поиск TRGM` (`h_use_trgm_search`).

+ Движки взаимоисключающие: включение TRGM выключает MeiliSearch (`h_get_updates`), и наоборот. Настройки MeiliSearch (`h_host`, `h_key`, `h_index_name`, `h_settings`) для TRGM не нужны.
+ `Лимит выдачи` (`h_search_limit`) работает так же — по умолчанию 20 результатов.
+ Кнопка поиска появляется в верхней панели, когда включен любой из движков.

### <a name="s-trgm-tables"></a> Таблицы поиска

Таблица готовится так же, как для MeiliSearch:

+ в таблице есть поле типа [Данные][38] `ttm_search` с `index`, `title`, `catalog` — см. [Настраиваем данные поиска в целевой таблице][687];
+ таблица добавлена строкой в `ttm__search_settings`, заполнены `code` и `buttons` — см. [Добавляем таблицы в поиск][688].

Кнопки индексации MeiliSearch (`Индексировать таблицу`, `Проиндексировать`, `Удалить из индекса`, `Очистить удаленные`) для TRGM не нужны.

Для скорости в строке таблицы нажмите `Создать триграм-индекс` — в базе будет создан GIN-индекс `trigram_<таблица>_ttm_idx` по `lower(ttm_search->'v'->>'index')` и отмечено `Триграм-индекс`. Без индекса поиск работает, но перебирает все строки таблицы — на больших таблицах медленно. `Удалить триграм-индекс` удаляет индекс. Строку таблицы с активным индексом удалить нельзя — сначала удалите индекс.

> Триграм-индекс создается только для [простых таблиц][88] и [таблиц циклов][89]. Его же можно создать и удалить кодом действия: `proTrigramIndexAdd(table: 'name')`, `proTrigramIndexDelete(table: 'name')`.

### <a name="s-trgm-search"></a> Как ищет

+ Поиск идет только по `index` из `ttm_search` (без учета регистра). `title` — заголовок плашки результата, по нему не ищется.
+ Строка попадает в выдачу, если запрос похож на какую-либо часть `index` (сходство слов `word_similarity` не ниже порога `pg_trgm.word_similarity_threshold`). Результаты всех таблиц сортируются вместе — сначала самые похожие.
+ Фильтр по каталогам работает по `catalog`.
+ Ищется только в таблицах, доступных пользователю, и только по строкам, которые он видит в web (с учетом ролей и [префильтров][48]).
+ Слова запроса подсвечиваются в тексте `index` результата.

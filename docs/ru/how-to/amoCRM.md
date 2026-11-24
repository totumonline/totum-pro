# amoCRM

## <a name="totum-amo"></a> Подключаем Тотум к amoCRM

видео: [VK Video](https://vk.ru/video873495087_456239103) · [YouTube](https://www.youtube.com/watch?v=XgFHB8Fyq7U)

### <a name="totum-amo-1"></a> Устанавливаем Семпл для Тотум

Сначала нам нужно установить семпл для Totum.

Скачайте файл по этой ссылке: [https://raw.githubusercontent.com/totumonline/ttm-samples/main/samples/ttm_amo.json.gz.ttm](https://raw.githubusercontent.com/totumonline/ttm-samples/main/samples/ttm_amo.json.gz.ttm)

Загрузите его в вашу схему. Инструкция по импорту: [https://youtu.be/UA7j28Ht5u4](https://youtu.be/UA7j28Ht5u4)

### <a name="totum-amo-2"></a> Добавляем интеграцию в amo

Далее идем в amo в раздел amoМаркет, нажимаем `...` и `Создать интеграцию` и выбираем `Внешняя интеграция`.

Заполняем:

+ Название;
+ Описание;
+ Предоставить доступ — `Доступ к данным аккаунта`
+ Ссылка для перенаправления — `https://HOST/Remotes/amo_keys` (замените `HOST` на ваш)
+ `Сохранить`

Переходим в `Установленные` и открываем эту интеграцию.

Переходим в `Ключи и доступы`.

Копируем в Тотум в таблицу `ttm_amo_access`

+ `Секретный ключ` — `h_client_secret`
+ `ID интеграции` — `h_client_id`
+ `Код авторизации` — `h_code`

В Тотум заполняем `h_amoHost` — `https://YOU_AMO_HOST.amocrm.ru` (замените `YOU_AMO_HOST` на ваш хост в `amo`).

Жмем `Получить ключ`.

Подключаем обновление ключа в кронах, переходим в `Системные таблицы` — `Основные` — `Настройки и крон` — `Кроны` и включаем крон `ttm_amo_cron`.

### <a name="totum-amo-3"></a> Настраиваем Remotes и хуки в amo

Дальше включаем `Remotes`, переходим в `Системные таблицы` — `Основные` — `API` — `Remotes`.

Заменяем `_hash` на придуманный вами, например `jfhYgsjGld`:

+ `amo_leads_add_hash` — `amo_leads_add_jfhYgsjGld`
+ `amo_leads_edit_hash` — `amo_leads_edit_jfhYgsjGld`
+ `amo_leads_del_hash` — `amo_leads_del_jfhYgsjGld`

Создаем пользователя `amo` с типом `API`.

В `Remotes` выбираем этого пользователя во всех трех ремонтах в поле  `remotes_user`.

Идем в `amo` в `amoМаркет` и нажимаем `WEB HOOKS` и добавляем три хука:

+ `https://TOTUM_HOST/Remotes/amo_leads_add_YOU_HASH` — `Сделка добавлена`
+ `https://TOTUM_HOST/Remotes/amo_leads_edit_YOU_HASH` — `Сделка изменена`
+ `https://TOTUM_HOST/Remotes/amo_leads_del_YOU_HASH` — `Сделка удалена`

### <a name="totum-amo-4"></a> Проверяем и донастраиваем

В `amo` добавляем новую сделку — она должна добавится в Тотум.

Идем в таблицу `ttm_amo_statuses` — жмем `Get amo statuses`.

Идем в таблицу `ttm_amo_users` — жмем `Get amo users`.

Теперь нам нужно добавить два поля добавить в `Сделку` в `amo` два поля:

+ Totum link — Тип `Ссылка` — `Только из API`
+ Additional field — Тип `Текст` — `Только из API`

Копируем `id` этих полей и вставляем их в код в Тотум в `ttm_amo_cycle_orders` — `ttm_amo_order` — `f_amo` — `Код`

+ `field_1_id` — `id Totum link`
+ `field_2_id` — `id Additional field`
+ **БЕЗ ЭТОГО ИЗМЕНЕИЕ ДАННЫХ В ТОТУМ НЕ БУДЕТ ВОЗВРАЩАТСЯ В AMO**

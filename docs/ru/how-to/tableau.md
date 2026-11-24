# Tableau и PowerBI

## <a name="connect_tableau"></a> Подключение Tableau к PostgreSQL

видео: [VK Video](https://vk.ru/video873495087_456239102) · [YouTube](https://www.youtube.com/watch?v=8FlmKaYf_Pw)

### <a name="connect_tableau-1"></a> Настраиваем PostgreSQL для Ubuntu

Смотрим путь расположения конфигурационного файла:

```
su - postgres -c "psql -c 'SHOW config_file;'"
```

Открываем файл в nano по полученному пути, например:

```
nano /etc/postgresql/12/main/postgresql.conf
```

Ищем секцию `CONNECTIONS AND AUTHENTICATION`, убираем комментарий у `listen_addresses` и ставим `'*'`:

```
#------------------------------------------------------------------------------
# CONNECTIONS AND AUTHENTICATION
#------------------------------------------------------------------------------

# - Connection Settings -

listen_addresses = '*'                  # what IP address(es) to listen on;
```

Открываем по тому же пути файл `pg_hba.conf`, например:

```
nano /etc/postgresql/12/main/pg_hba.conf
```

Добавляем строку в секцию `IPv4 local connections`:

```
host    all             all             0.0.0.0/0               md5
```

Смотрим список служб:

```
service --status-all
```

Перезапускаем `postrges` — название службы надо проверить по списку из предыдущей команды:

```
service postgresql restart
```

Проверяем, что рестартовал корректно:

```
service postgresql status
```

Можно проверить подключение с другого сервера на котором установлен `psql`, замените `ubuntu.totum.online` на хост вашего сервера с бд и `totum` на вашего пользователя `postgresql` если он отличается:

```
psql -h ubuntu.totum.online -U totum
```

### <a name="connect_tableau-2"></a> Драйвера PostgreSQL для Tableau

[https://www.tableau.com/support/drivers](https://www.tableau.com/support/drivers)

Data Source — PostgreSQL

Operating System — ...

Bit Version — ...

### <a name="connect_tableau-3"></a> Особенности sql запросов к Totum

Запрос идет к `SCHEMA.TABLE`

Если в `SHEMA-NAME` есть нестандартные символы включая дефис, то ставить в кавычки  `"SHEMA-NAME".table`

Поля в Тотум это `jsonb`, поэтому запрос к ним `FIELD_NAME->>'v'`

Для сравнения по числовым полям (для дробных разделитель `.`):

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2" from totum.tableau where (second_field->>'v')::NUMERIC > 20
```

Для сравнения по нечисловым полям (по чекбоксу сравнение идет как по строковому полю с `'true'` и `'false'`):

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2" from totum.tableau where first_field->>'v' > 'B'
```

Если мы хотим выбрать строки по нескольким значениям поля, то можно использовать такую запись:

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2" from totum.tableau where first_field->>'v' in ('B','F')
```

Если мы хотим выбрать строки исключая несколько значений поля, то можно использовать такую запись:

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2" from totum.tableau where first_field->>'v' not in ('B','F')
```

Сортировка по числовым полям:

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2" from totum.tableau where first_field->>'v' > 'B' order by (second_field->>'v')::NUMERIC asc
```

Сортировка по нечисловым полям:

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2" from totum.tableau where first_field->>'v' > 'B' order by first_field->>'v' asc
```

Что бы выбрать where по пустому полю (там может быть `null` или пустая строка `""`, для тотума это одно и тоже, но для sql это разные вещи поэтому используйте оператор `or`):

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2", list_field->>'v' as list from totum.tableau

where (first_field->>'v' is NULL or first_field->>'v' = '') order by (second_field->>'v')::NUMERIC desc
```

Что бы выбрать из полей типа Список по включению значения этого списка:

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2", list_field->>'v' as list from totum.tableau
where list_field->'v' @> '10'::jsonb order by (second_field->>'v')::NUMERIC desc
```

Строки и числа хранящиеся в тотум в таких списках для бд различаются, что бы гарантировано искать по значению его надо дублировать через `or` как число и как строку:

```
select first_field->>'v' as "field 1", second_field->>'v' as "field 2", list_field->>'v' as list from totum.tableau
where (list_field->'v' @> '10'::jsonb or list_field->'v' @> '"10"'::jsonb) order by (second_field->>'v')::NUMERIC desc
```

Если мы хотим выбрать значения изнутри json хранящегося в поле по ключам:

```
select row_field->'v'->>'key_1' as "key 1", row_field->'v'->>'key_2' as "key 2" from totum.tableau where first_field->>'v' in ('B','F')
```

Для таблиц с режимом удаления Скрывать, что бы выбрать активные строки надо указать `where is_del = false`

## <a name="letsencript_for_powerbi"></a> Подключение Let's Encrypt сертификата к PostgreSQL для подключения PowerBI

### <a name="letsencript_for_powerbi-1"></a> Базово подключение из PowerBi к PostgreSQL завершается с ошибкой проверки SSL, можно ли отключить SSL?

![one does not simply](https://totum.totum.online/fls/365_404_file.jpg)

Чтобы отключить SSL:

1. Попытаться подключится выбрав PostgreSQL и введя хост сервера и БД.

2. Вводим пользователя и пароль

3. Выпадает ошибка: При чтении данных от поставщика произошла ошибка: 'Удаленный сертификат недействителен согласно результатам проверки подлинности.

4. Идем: Файл — Параметры и настройки — Настройки источника данных

5. Выбираем наш хост — Править разрешения — Снимаем галочку Шифрование

Но это небезопасно так как данные передаются открытым способом!

### <a name="letsencript_for_powerbi-2"></a> Почему не работает стандартный самоподписанный сертификат?

Потому что Windows – это Windows, и он не может проверить ваш самоподписанный сертфикат.

### <a name="letsencript_for_powerbi-3"></a> Продолжаем отсюда если у вас уже установлен сертификат LE

Эта инструкция предполагает, что вы выполняли действия описанные [здесь][680] или устанавливани при помощи скрипта Автоустановки.

Создаем `Renewal Hook`:

Смотрим путь расположения конфигурационного файла:

```
su - postgres -c "psql -c 'SHOW config_file;'"
```

Открываем файл в nano по полученному пути, например:

```
nano /etc/postgresql/12/main/postgresql.conf
```

Смотрим путь к `data_directory` в `postgresql.conf` — это пункт `data_directory`

```
#------------------------------------------------------------------------------
# FILE LOCATIONS
#------------------------------------------------------------------------------

# The default values of these variables are driven from the -D command-line
# option or PGDATA environment variable, represented here as ConfigDir.

data_directory = '/var/lib/postgresql/12/main'          # use data in another directory
                                                        # (change requires restart)
hba_file = '/etc/postgresql/12/main/pg_hba.conf'        # host-based authentication file
                                                        # (change requires restart)
ident_file = '/etc/postgresql/12/main/pg_ident.conf'    # ident configuration file
                                                        # (change requires restart)
```

Создаем файл:

````
nano /etc/letsencrypt/renewal-hooks/deploy/postgresql.deploy
````

`example.com` — заменить на ваш домен.

`/var/lib/pgsql/data` — заменить на путь к `data_directory` вашего `postgresql`.

```
#!/bin/bash
umask 0177

export DOMAIN=example.com
export DATA_DIR=/var/lib/pgsql/data

cp /etc/letsencrypt/live/$DOMAIN/fullchain.pem $DATA_DIR/server.crt
cp /etc/letsencrypt/live/$DOMAIN/privkey.pem   $DATA_DIR/server.key
chown postgres:postgres  $DATA_DIR/server.crt $DATA_DIR/server.key

service postgresql restart

```

Делаем скрипт исполняемым:

```
chmod +x /etc/letsencrypt/renewal-hooks/deploy/postgresql.deploy
```

Выполним его:

```
/etc/letsencrypt/renewal-hooks/deploy/postgresql.deploy
```

Редактируем кофиг, комментируем старые `ssl_cert_file` и `ssl_key_file` и добавляем новые пути:

```
nano /etc/postgresql/12/main/postgresql.conf
```

```
# вид секции SSL после изменения

# - SSL -

ssl = on
ssl_cert_file = 'server.crt'
ssl_key_file = 'server.key'

#ssl_cert_file = '/etc/ssl/certs/ssl-cert-snakeoil.pem'
#ssl_key_file = '/etc/ssl/private/ssl-cert-snakeoil.key'

#ssl_ca_file = ''
#ssl_crl_file = ''
#ssl_ciphers = 'HIGH:MEDIUM:+3DES:!aNULL' # allowed SSL ciphers
#ssl_prefer_server_ciphers = on
#ssl_ecdh_curve = 'prime256v1'
#ssl_min_protocol_version = 'TLSv1'
#ssl_max_protocol_version = ''
#ssl_dh_params_file = ''
#ssl_passphrase_command = ''
#ssl_passphrase_command_supports_reload = off
```

Перезапускаем PostgreSQL:

```
service postgresql restart
```

Подключаем перевыпуск сертификата (не делаем этого если перевыпуск уже подключен):

```
crontab -e
```

Вам нужно заменить `42` в этой строке на другое число в диапазоне между `0` и `59`:

```
42 */12 * * * certbot renew --quiet --allow-subset-of-names
```

### <a name="letsencript_for_powerbi-4"></a> Есть еще непонятные моменты

Now, you have a proper PostgreSQL connector, your database is using SSL so everyone should be happy. So, you publish your report on PowerBI Service and hit refresh.

And then it fails miserably.

[https://dataintoresults.com/post/powerbi-and-postgresql-ssl-lets-encrypt-and-gateway/](https://dataintoresults.com/post/powerbi-and-postgresql-ssl-lets-encrypt-and-gateway/)

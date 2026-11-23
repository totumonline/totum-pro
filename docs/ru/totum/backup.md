# Бэкапы

## <a name="database"></a> Бэкапирование базы данных

> Конечным исполнителем всех операций являются `pg_dump` и `psql`. Если вы создали бэкап на одном сервере, а восстанавливаете на другом — убедитесь что вы идете с повышением версии PostgreSQL командой `psql -V`. При понижении версии бэкап скорее всего развернут не будет.


### <a name="bin-totum-create-backup"></a> Консольной утилитой bin/totum


Перейдите в папку totum:

```
cd /home/totum/totum-pro
```

Выполните:

```
bin/totum schema-backup --schema="SCHEMA_NAME" prod_dump.sql
```

`SCHEMA_NAME` = по умолчанию `totum`

#### Можно не переносить логи и автоматически сжимать в gz:

```
bin/totum schema-backup --schema="SCHEMA_NAME" --no-logs --gz file_dump.sql
```
		
#### Можно не переносить некоторые таблицы:

```
bin/totum schema-backup --schema="SCHEMA_NAME" --no-content="table_name_1,table_name_2" file_dump.sql
```


![Имя схемы](https://totum.totum.online/fls/365_358_file.png)


### <a name="manual-create-backup"></a> Вручную через pg_dump

Создаем дамп. Выполняем из корневой папки Тотум. Замените `USER` и `PASSWORD` на соответствующие вашей БД.

`SCHEMA_NAME` — имя схемы. Посмотреть можно в любой таблице в Тотум в верхнем правом углу.

```
pg_dump -O -x --dbname=postgresql://USER:PASSWORD@LOCALHOST:5432/DB_NAME --schema="SCHEMA_NAME" > db_backup_name.sql
```
	
+ `-O` — означает без owner, те этот дамп можно будет восстановить на другом хосте.

+ `-x` — без привелегий.
	
+ `db_backup_name.sql` — вы можете создать папку `backups` в корневой папке тотума и указать полный или относительный путь до нее вместе с название файла копии.

> `bin/totum schema-backup` выполняет команду `pg_dump` исключая лог внешних синхронизаций и временные таблицы!

Если необходимо выгрузить без логов: 

```
pg_dump -O -x --dbname=postgresql://USER:PASSWORD@LOCALHOST:5432/DB_NAME --schema="SCHEMA_NAME" --no-tablespaces --exclude-table-data='_tmp_tables' --exclude-table-data='_bfl' | grep -v '^--' > db_backup_name.sql
```

### <a name="cron"></a> Бэкапирование по расписанию

Вы можете поставить создание дампа в `cron` в самом Тотум, использовав функцию [execSSH][464].

**Пример кода Totum создающего копии в корневой папке тотума:**

`PATH_TO_TOTUM_FOLDER` — замените на путь к папке Тотум. Посмотреть можно выполнив `pwd` в консоли сервера. 

Вторым действием удаляет 7-дневный файл. Ставить в крон на исполнение 1 раз в день.

Замените `SCHEMA_NAME` на имя схемы (по умолчанию `totum`):

```totum
= : while(action: $a1; action: $a2)

a1: execSSH(ssh: "cd ~/totum-pro && bin/totum schema-backup --schema='SCHEMA_NAME' --gz '%schema%-%Y%-%m%-%d%.sql'")
	
a2: execSSH(ssh: str`"cd ~/totum-pro && rm SCHEMA_NAME-" + $dm + ".sql.gz"`)

		dm: dateAdd(date: $#nd; days: -7; format: "Y-m-d")
```

Если при выполнении Тотум выдасть предупреждение о блокировке [execSSH][464] — снимите в Conf.php в корневой папке установки комментарий со строки `/* protected $execSSHOn = true;*/` должно быть -> `protected $execSSHOn = true;`

## <a name="files"></a> Бэкапирование файлов

Замените `HOST` на ваш Хост:

Файлы расположены `/home/totum/totum-pro/http/fls/HOST` и `/home/totum/totum-pro/totumTmpfiles/HOST`.

А для защищенных файлов `/home/totum/totum-pro/secureFiles/HOST`.

Вам нужно бэкапировать эти папки любым способом.


## <a name="restore"></a> Восстановление бэкапа базы данных

### <a name="bin-totum-restore-backup"></a> Восстановление через консольную утилиту bin/totum

При выполнении этой операции из корневой папки тотум схема БД полностью заменяется на схему из `prod_dump.sql` (замените `schema_name` на вашу схему):

#### Если заменить cуществующую

```
bin/totum schema-replace --with-active-crons file_dump.sql schema_name
```

#### Если создать новую

```
bin/totum schema-replace --with-active-crons file_dump.sql schema_name host.zone
```
		
По умолчанию кроны при загрузке отключаются — `--with-active-crons` отключает это правило


### <a name="manual-restore-backup"></a> Восстановление вручную через PSQL

Для восстановления заходим в psql и папки в которой лежит файл с бэкапом. Замените `USER` и `PASSWORD` на соответствующие вашей БД.

	psql --dbname=postgresql://user:password@localhost:5432/db_name

Переименовываем текущую схему в резерв:

	ALTER SCHEMA имя_схемы RENAME TO имя_резерв;

Заливаем восстановление:

	\i имя_файла_бэкапа

Проверяете восстановление и если ок, то удаляете переименованную старую схему

	DROP SCHEMA имя_резерв CASCADE;
	
Как посмотреть список всех схем в базе:

	select schema_name from information_schema.schemata;

Выходим

	\q
	
	# enter
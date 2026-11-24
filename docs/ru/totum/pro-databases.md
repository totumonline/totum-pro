# Функции доступа к базам данных

## <a name="general"></a> Общее

Для доступа к внешним SQL-базам данных из Totum необходимо заполнить доступы к базам в таблице `ttm__external_databases`.

Для сохранения и чтения паролей используется функция [strEncrypt][594], для ее работы потребуется создать файл `Crypto.key` в корне установки Totum с ключем шифрования.

![](https://totum.totum.online/fls/365_595_file.png)

+ `name` — уникальное имя коннекта к базе.

+ `type` — тип PDO.

	Для `MySQL` необходимо установить модуль `PHP`: `apt -y install php8.5-mysql`

+ <a name="dsn"></a>`dsn` — строка подключения в формате [DSN PDO](https://www.php.net/manual/ru/pdo.construct.php) **без пароля**. Если `dsn` заполнен, Totum подключается по нему, а `type`, `host`, `port` и `database_name` не используются. Логин и пароль все равно берутся из `username` и `user_pass` (пароль в `dsn` не пишется — он хранился бы открытым текстом), опции — из `options`.

	Нужен, когда подключению требуются параметры, которых нет в отдельных полях: `sslmode`, `charset`, unix-сокет, имя сервиса и т.п. Примеры:

	```
	pgsql:host=db.example.com;port=5432;dbname=shop;sslmode=require
	mysql:host=10.0.0.5;port=3306;dbname=shop;charset=utf8mb4
	mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=shop
	```

	Если `dsn` пуст — строка собирается из полей: `<type>:host=<host>;port=<port>;dbname=<database_name>`.

+ `host` — хост БД к которой необходимо подключиться. Для удаленной базы необходимо обеспечить доступ по порту, а также разрешить дистанционный коннект для хоста, пользователя и базы.

+ `port` — порт коннекта.

+ `database_name` — имя базы.

+ `username` — имя пользователя.

+ `user_pass` — пароль. Устанавливается через `set_pass`.

+ `options` — опции PDO для коннекта.

## <a name="db-functions"></a> Функции, которые используются для подключения к сторонним базам

+ [proDbConnect][599] — подключение.

+ [proDbDisconnect][600] — отключение.

+ [proDbExecQuery][601] — исполнение `query` без возврата данных.

+ [proDbSelect][602] — исполнение `query` с возвратом `row`.

+ [proDbSelectList][603] — исполнение `query` с возвратом `rowList`.
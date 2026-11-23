# Обновление

## <a name="bin-totum"></a> Консольной утилитой

> Выполняйте обновление от пользователя созданного для работы Totum. Если `bin/totum git-update` или `git pull` будет выполнен от `root`, то вы столкнетесь с ошибкой доступа и ваш сервер будет в нерабочем состоянии!

> **ЕЩЕ РАЗ ЭКСТРА-ВАЖНО!!!** ОБНОВЛЕНИЕ ВЫПОЛНЯЕТСЯ ТОЛЬКО ОТ ПОЛЬЗОВАТЕЛЯ ОТ КОТОРОГО РАБОТАЕТ TOTUM И ТОЛЬКО ИЗ ПАПКИ УСТАНОВКИ TOTUM!!!

> ЕСЛИ ВЫ ЗАШЛИ ОТ ROOT ИЛИ SUDO-ПОЛЬЗОВАТЕЛЯ ТО ВАМ НЕОБХОДИМО СНАЧАЛА ПЕРЕКЛЮЧИТСЯ В ПОЛЬЗОВАТЕЛЯ TOTUM: `su totum`

> ПОТОМ ПЕРЕЙТИ В ПАКУ УСТАНОВКИ: `cd ~/totum-pro`

> И ТОЛЬКО ПОТОМ ВЫПОЛНИТЬ КОМАНДУ ОБНОВЛЕНИЯ

Выполните из корневой папки Totum:

```
bin/totum git-update
```

Если вы увидели сообщение:

```
fatal: detected dubious ownership in repository at '/home/totum/totum-pro'
To add an exception for this directory, call:

        git config --global --add safe.directory /home/totum/totum-pro
```

Это значит вы оперируете от ошибочного пользователя!
	
> Внимание! Иногда, когда на сервер GitHub идет множество запросов из подсети в которой находится ваш сервер вас попросят ввести свои авторизационные данные

```
*** Please tell me who you are.

Run

  git config --global user.email "you@example.com"
  git config --global user.name "Your Name"

to set your account's default identity.
Omit --global to set the identity only in this repository.
```

В этом случае вам нужно будет ввести свои учетные данные на GitHub (замените `USERNAME_IN_GITHUB` и `EMAIL_IN_GITHUB` на ваши):

```
git config --local user.name USERNAME_IN_GITHUB
```
```
git config --local user.email EMAIL_IN_GITHUB
```

Проверить:

```
git config --list --local
```

## <a name="result"></a> Результат выполнения

Пример успешного результата выполнения команды:

	totum@totum:~/totum$ bin/totum git-update
	remote: Enumerating objects: 28, done.
	remote: Counting objects: 100% (28/28), done.
	remote: Compressing objects: 100% (2/2), done.
	remote: Total 16 (delta 14), reused 16 (delta 14), pack-reused 0
	Unpacking objects: 100% (16/16), done.
	From https://github.com/totumonline/totum-pro
	 * branch            master     -> FETCH_HEAD
	 080411e..171aec7  master     -> origin/master
	Updating 080411e..171aec7
	Fast-forward
	 http/html.html                           |  2 +-
	 http/js/main.js                          |  2 +-
	 totum/moduls/Table/ReadTableActions.php  | 41 +++++++++++++++++++++++++++++++----------
	 totum/templates/html.html                |  2 +-
	 totum/templates/page_template.php        |  2 +-
	 totum/templates/page_template_simple.php |  2 +-
	 6 files changed, 36 insertions(+), 15 deletions(-)
	Loading composer repositories with package information
	Installing dependencies from lock file
	Nothing to install or update
	Generating autoload files
	6 packages you are using are looking for funding.
	Use the `composer fund` command to find out more!
	update totum with source totum_ru from sys_update
	Set fields of table tables_fields
	Set fields of table tables
	Set settings in table tables for "tables" and "tables_fields"
	     Update settings table "ttm__remotes"
		 Update settings table "ttm__tmp_change_tree_links"
		 Update settings table "auth_log"
		 Update settings table "calcstable_versions"
		 Update settings table "calcstable_cycle_version"
		 Update settings table "calc_fields_log"
		 Update settings table "ttm__charts"
		 Update settings table "tree"
		 Update settings table "ttm__load_updates"
		 Update settings table "ttm__change_field_name"
		 Update settings table "ttm__change_fields_list"
		 Update settings table "table_categories"
		 Update settings table "notification_codes"
		 Update settings table "crons"
		 Update settings table "settings"
		 Update settings table "notifications"
		 Update settings table "ttm__updates"
		 Update settings table "users"
		 Update settings table "ttm__users_online"
		 Update settings table "roles"
		 Update settings table "ttm__update_create"
		 Update settings table "ttm__update_ids"
		 Update settings table "tables_fields"
		 Update settings table "tables"
		 Update settings table "log_structure"
		 Update settings table "ttm__manage_notifications"
		 Update settings table "print_templates"
		 Update settings table "panels_view_settings"
	Add and modify fields for 30 tables
	Add tree links and anchors
	Load data to tables and exec codes from schema
	    exec code: =: if(condition: $#type="install"; then: $set)...
		exec code: =: if(condition: $#type="install"; then: $set)...
		update @ttm__updates.h_matches/totum_ru
	Set default tables and sort for new tree branches
	totum@totum:~/totum$
	
## <a name="held-fields"></a>PRO Удерживаемые поля

В момент обновления Тотум — происходит перезапись настроек полей данными из обновления. Если вы для своих задач модифицировали системное поле и вам необходимо, что бы оно не перезаписалось при обновлении — то его необходимо отметить как удерживаемое.

Для этого надо заполнить поле `h_held_fields` таблицы [Настройки][359]. В ключах используется name таблиц а содержимое ключей — списки из name удерживаемых полей.

```
{
  "settings": [
    "h_user_settings_buttons"
  ]
}
```

В момент обновления в Консоли будет показана таблица с полями, которые были в загружаемом обновлении, но небыли обновлены из-за удержания.
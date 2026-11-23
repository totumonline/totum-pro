# Консольная утилита bin/totum

> Если Totum не установлен, то единственной доступной командой будет `install`.

Список команд можно посмотреть `bin/totum list` — выполняется из корневой папки totum.

**Available commands:**

+ `clean-schema-tmp-tables` — Clean tmp_tables in schemas. For single install. Set in crontab one time in 10 minutes.

+ `clean-schemas-tmp-tables` — Clean tmp_tables in schemas. For multi install. Set in crontab one time in 10 minutes.

+ `clean-tmp-dir` — Clean tmp dir. Set in crontab one time in 10 minutes.

+ `git-update` — Update from git origin master && composer && schema(s)-update.

+ `help` — Displays help for a command.

+ `list` — Lists commands.

+ `schema-cron` — Execute exact totum code of table crons.

+ `schema-crons` — Execute totum codes of table crons for single install. Set in crontab every minute.

+ `schemas-crons` — Execute totum codes of table crons for multi install. Set in crontab every minute.

+ `schema-passwd` — Change password for schema user.

+ `schema-update` — Update schema.

+ `schemas-update` — Update schemas for multi install.

+ `schemas-list` — List of active schemas.

+ `schema-backup` — Backup schema to file.

+ `schema-add` — Add new schema.

+ `schema-duplicate` — Duplicate schema. You need install with psql and pg_dump in it. Change Conf.php if you installed totum without its.

+ `schema-replace` — Replace or add schema.

+ `set-hidden-host` — Add/set hidden host.

+ `vacuum` — Vacuum all database or table in schema.

+ `exec` — Technical function for run Totum-code from CLI.

+ `schema-user-unblock` — Unblock user autorisation.
  
Чтобы посмотреть документацию по конкретной команде выполните `bin/totum COMMAND -h`.
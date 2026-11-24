# Внешние подключения

## <a name="n8nslack"></a> Отправляем сообщение в Slack через n8n.io

видео: [VK Video](https://vk.ru/video873495087_456239100) · [YouTube](https://www.youtube.com/watch?v=XBkMrjRCMi4)

### <a name="n8nslack-1"></a> Вступление

Slack в этой инструкции как пример — n8n позволяет создавать интеграции с большим количеством SaaS приложений. Его основное преимущество в том, что они прочитали инструкции этих приложений и предоставляют уже подготовленные действия и инструкции по подключениям.

### <a name="n8nslack-2"></a> Создаем webhook в n8n

Для этой инструкции вам потребуется схема Totum, аккаунт Slack и аккаунт n8n.io (или его инстанс, установленный на собственный сервер).

В `n8n` в новом `workflow` жмем `+` и добавляем `webhook`.

![](https://totum.totum.online/fls/365_565_file.png)

### <a name="n8nslack-3"></a> Создаем вызов в Totum

В Totum создаем кнопку и делаем вызов функцией `getFromScript`:

```
= : getFromScript(uri: "https://totum.app.n8n.cloud/webhook-test/2bd51c53-0175-405d-8cc3-1d122d19e379"; post: "param_1" = "ferrum"; post: "param_2" = "water")
```

+ `param_1`, `param_2`, а также их значения вы указываете сами.
+ По умолчанию будет выполнен POST в формате `x-www-form-urlencoded`

### <a name="n8nslack-4"></a> Проверяем работу hook

В `n8n` нажимаем `Listen for test event ` и выполняем кнопку из Тотум.

![](https://totum.totum.online/fls/365_566_file.png)

### <a name="n8nslack-5"></a> Настраиваем интеграцию со Slack

В `n8n` добавляем следующий шаг `Slack` указываем канал и формируем сообщение.

![](https://totum.totum.online/fls/365_567_file.png)

![](https://totum.totum.online/fls/365_568_file.png)

### <a name="n8nslack-6"></a> Создаем приложение в Slack

Идем в [https://api.slack.com](https://api.slack.com), переходим в менеджмент приложений (вверху справа) и добавляем приложение.

![](https://totum.totum.online/fls/365_569_file.png)

Говорим `From scratch`.

![](https://totum.totum.online/fls/365_570_file.png)

Указываем название и выбираем рабочую область.

![](https://totum.totum.online/fls/365_571_file.png)

Идем в раздел `Permissions`.

![](https://totum.totum.online/fls/365_572_file.png)

Листаем в раздел `Scopes` — `Bot token scopes` и добавляем одно или несколько разрешений.

![](https://totum.totum.online/fls/365_573_file.png)

Листаем обратно наверх и устанавливаем в рабочую область `Install to Workspace`.

![](https://totum.totum.online/fls/365_574_file.png)

Копируем токен и добавляем его в `n8n`.

![](https://totum.totum.online/fls/365_575_file.png)

![](https://totum.totum.online/fls/365_576_file.png)

### <a name="n8nslack-7"></a> Добавляем приложение в канал

В рабочей области `Slack` добавляем созданного бота в канал.

![](https://totum.totum.online/fls/365_577_file.png)

### <a name="n8nslack-8"></a> Включаем workflow

Включаем процесс в `n8n` и **изменяем адрес обращение в Тотум с тестового на рабочий.**

![](https://totum.totum.online/fls/365_578_file.png)

## <a name="telegram-bot"></a> Создаем и подключаем Telegram бота

видео: [VK Video](https://vk.ru/video873495087_456239101) · [YouTube](https://www.youtube.com/watch?v=sbu5sfLBceo)

### <a name="telegram-bot-1"></a> Создаем Telegram бота

Ищем `@BotFather`.

Говорим `/newbot`.

Вводим `title` и `username`.

Получаем `token`.

Можем добавить картинку новому боту сказав ботфазеру `/setuserpic` и загрузив картинку.

### <a name="telegram-bot-2"></a> Вызываем бот из Totum

Создаем в Тотум кнопку в которой делаем тестовый вызов `telegram-api`:

```totum
= : linkToDataJson(title: "Answer"; data: $extr)

extr: jsonExtract(text: $getfs)

getfs: getFromScript(uri: str`"https://api.telegram.org/bot" + $token + "/" + $command`)

token: "YOUR_BOT_TOKEN"
command: "getMe"
```

Выполняем и видим ответ с данными нашего бота — если нет, то смотрим где ошиблись.

### <a name="telegram-bot-3"></a> Добавляем Remout в Totum

Далее создаем `Remout` в Totum, что бы получать входящие сообщения от нашего бота.

Идем `Системные таблицы` — `Основные` — `API` — `Remotes`.

Добавляем `Remout` с `name`: `telegram_bot_incoming_HASH` (замените `HASH` на вашу случайную последовательность 6-10 символов ) и выбираем `remote_user`.

Добавим простую таблицу для тестирования: `incoming_from_bot` с полем `incoming_message` тип Данные в строчной части.

В код ремоута запишем добавление строки в эту таблицу, когда приходит `hook`:

```totum
=: insert(table: 'incoming_from_bot'; field: 'incoming_message' = $extract)

extract: jsonExtract(text: $#input)
```

### <a name="telegram-bot-4"></a> Регистрируем адрес Remout в боте

Регистрируем наш `hook` в боте (замените `HOST` на ваш хост):

```totum
= : linkToDataJson(title: "Answer"; data: $extr)

extr: jsonExtract(text: $getfs)

getfs: getFromScript(uri: str`"https://api.telegram.org/bot" + $token + "/" + $command`; post: "url" = "https://HOST/Remotes/telegram_bot_incoming"; post: "secret_token" = $secret_token)

token: "YOUR_BOT_TOKEN"
command: "setWebhook"

secret_token: "goldenBrown"
```

+ в `url` передается `https://HOST/Remotes/telegram_bot_incoming` (замените `HOST` на ваш хост)

+ в `secret_token` произвольная фраза. Можно использовать `A-Z`, `a-z`, `0-9`, `_` и `-`. Этой фразой бот будет подписываться, когда будет отправлять сообщение на ваш Тотум.

Выполняем и можем проверить установленный адрес хука:

```totum
= : linkToDataJson(title: "Answer"; data: $extr)

extr: jsonExtract(text: $getfs)

getfs: getFromScript(uri: str`"https://api.telegram.org/bot" + $token + "/" + $command`)

token: "YOUR_BOT_TOKEN"
command: "getWebhookInfo"
```

### <a name="telegram-bot-5"></a> Подключаемся к боту

Теперь мы ищем нашего бота в `telegram` по `@your_bot_username` и отправляем ему сообщение и смотрим пришедшие данные в таблице `incoming_from_bot`.

Нас интересует `#message[chat][id]` — это `id`, который нам нужен, что бы отправить обратное сообщение.

### <a name="telegram-bot-6"></a> Бот отправляет сообщение

Теперь попробуем отправить сообщение пользователю:

```totum
= : linkToDataJson(title: "Answer"; data: $extr)

extr: jsonExtract(text: $getfs)

getfs: getFromScript(uri: str`"https://api.telegram.org/bot" + $token + "/" + $command`; post: "chat_id" = "169521867"; post: "text" = $text)

token: "YOUR_BOT_TOKEN"
command: "sendMessage"

text: "Some great text!"
```

Если пришло, значит все ок и можем вешать отправку нужной нам нотификации на триггер.

### <a name="telegram-bot-7"></a> Отправляем в сообщении Кнопки

[https://github.com/totumonline/totum-ru-issues-and-discussions/discussions/218](https://github.com/totumonline/totum-ru-issues-and-discussions/discussions/218)

### <a name="telegram-bot-8"></a> Заключение

Это самый простой бот отправляющий нотификации, но API телеграма позволяет огромное количество штук [https://core.telegram.org/bots/api#available-methods](https://core.telegram.org/bots/api#available-methods).

Теперь, когда вы знаете общий метод взаимодействия с ботом вы можете программировать свой.

### <a name="telegram-bot-9"></a> Проверка секретной фразы при вызове webhook

Что бы проверять секретную фразу при срабатывании `webhook` модифицируйте код в `Remotes`:

```totum
=: if(condition: $#headers["X-Telegram-Bot-Api-Secret-Token"][0] = "goldenBrown"; then: $insert)

insert: insert(table: 'incoming_from_bot'; field: 'incoming_message' = $extract)

extract: jsonExtract(text: $#input)
```

Обратите внимание, что секретный ключ приходит в заголовке `X-Telegram-Bot-Api-Secret-Token` (название которого содержит дефисы `-`) в котором лежит `list` и ключ в первом элементе этого `list` и что бы по структуре Тотум мог его взять этот ключ **обязательно** должен быть указан **в кавычках!**

## <a name="yandex-translator"></a> Подключаемся к Yandex-переводчику

### <a name="yandex-translator-1"></a> Предварительные настройки консоли Яндекс

Регистрируетесь в `console.yandex.ru`.

Идете в `AI API`.

Создаете платежный аккаунт.

Привязываете карту и переходите на платную версию.

Пополняете баланс (в начале 2023 г. 100К символов переводчика стоят ~50 р.).

Подробнее про тарифы здесь: [https://cloud.yandex.ru/prices](https://cloud.yandex.ru/prices)

Получаете `oAuth-token`. Идете сюда [https://cloud.yandex.ru/docs/iam/operations/iam-token/create](https://cloud.yandex.ru/docs/iam/operations/iam-token/create), переходите на вкладку API и переходите по ссылке в пункте 2. Разрешаете доступ к `cloud` от вашего аккаунта Яндекс и видите `token` — сохраняете его.

### <a name="yandex-translator-2"></a> Получаем IAM-token и настраиваем его обновление

Создаем поле тип `Данные` для хранения `IAM-token`.

Получаем `IAM-token` и сохраняем его в созданное поле (подставьте свой `oAuth` токен полученный ранее):

```totum
// код-действия кнопки

= : set(table: $#ntn; field: 'h_iamtoken' = $extract)

extract: jsonExtract(text: $iam)

	iam: getFromScript(uri: "https://iam.api.cloud.yandex.net/iam/v1/tokens"; posts: $json)
		json: jsonCreate(data: $row)
			row: rowCreate(field: "yandexPassportOauthToken" = "YOUR_OAUTH_TOKEN_HERE")

```

Нажатие кнопки надо поставить на крон каждые 6 часов через `execButton`.

### <a name="yandex-translator-3"></a> Получаем идентификатор каталога и назначаем роль

**Идентификатор каталога:**

1. Выберите каталог на [стартовой странице](https://console.cloud.yandex.ru/cloud) консоли управления. На этой странице отображается информация о всех каталогах в выбранном облаке. Если необходимо, [переключитесь на другое облако](https://cloud.yandex.ru/docs/resource-manager/operations/cloud/switch-cloud).

2. Получить идентификатор каталога можно из URL страницы каталога в консоли управления:

   ```
   https://console.cloud.yandex.ru/folders/b1gd129pp9ha0vnvf5g7
   ```

   Где `b1gd129pp9ha0vnvf5g7` — это идентификатор каталога.

![](https://totum.totum.online/fls/365_605_file.png)

**Назначаем роль:**

1. Выбираем каталог

2. Переходим на вкладку Права доступа

3. Жмем Назначить роли

4. Жмем выбрать пользователя

5. Выбираем того пользователя от которого мы выпускали токен

6. Жмем добавить роль и выбираем `ai.translate.user`

7. Сохранить

![](https://totum.totum.online/fls/365_606_file.png)

### <a name="yandex-translator-4"></a> Формируем запрос на перевод и записываем результат куда-нибудь

Формируем запрос на перевод и записываем результат куда-нибудь (замените `YOUR_FOLDER_ID`):

```totum
= : set(table: $#ntn; field: 'translation' = $extract[translations][0][text]; where: 'id' = #id)

	extract: jsonExtract(text: $get)

get: getFromScript(uri: "https://translate.api.cloud.yandex.net/translate/v2/translate"; headers: $headers; posts: $json)
headers: listCreate(item: $header_1; item: $header_2)

	header_1: "Content-Type: application/json"
	header_2: str`"Authorization: Bearer" ++ #h_iamtoken[iamToken]`

	json: jsonCreate(data: $data)
		data: rowCreate(field: "folderId" = "YOUR_FOLDER_ID"; field: "texts" = $list; field: "sourceLanguageCode" = "en"; field: "targetLanguageCode" = "ru")

			list: listCreate(item: $text_1)
				text_1: #something_to_translate
```

За один запрос можно передать несколько текстов — для этого надо в строку `list` передать список из текстов.

В ответе в ключе `translations` будет содержаться список переводов в том же порядке в котором вы их передали.

## <a name="imap_to_totum"></a> Получаем email по IMAP в Totum

### <a name="imap_to_totum-1"></a> Инструкция

От `root` устанавливаем нужный софт:

```
apt install fetchmail procmail uudeview
```

Переключаемся в пользователя `totum`:

```
su totum
```

Создаем файл конфигурации `fetchmailrc`:

```
nano ~/.fetchmailrc
```

```
poll imap.host.com protocol IMAP port 993
  user "email@host.com" password "some_pass_here" is "totum" here
  mda "/usr/bin/procmail"
  ssl
```

Порт и параметры настроек смотрите у вашего IMAP-сервера. Если у него нет SSL, то спросите у GPT как его настраивать.

Устанавливаем права на `fetchmailrc`:

```
chmod 700 ~/.fetchmailrc
```

Создаем конфиг `procmail`:

```
nano ~/.procmailrc
```

```
MAILDIR=$HOME/Mail
DEFAULT=$MAILDIR/mbox
LOGFILE=$MAILDIR/procmail.log

:0
* ^From.*
| $MAILDIR/extract.sh
```

Устанавливаем права на `procmailrc`:

```
chmod 600 ~/.procmailrc
```

Создаем папку `Mail` и внутри `data`:

```
mkdir -p ~/Mail/data && cd ~/Mail
```

Письма будут лежать в `data`!

Создаем скрипт обработки писем (ближе к конце найдите `MY_KEY` и замените на рандомный набор символов и сохраните его, а также замените `YOUR_HOST` на хост вашего totum или на `ip` и если на `ip` то и протокол измените с `https` на `http`):

```
nano extract.sh
```

```
#!/bin/bash

EMAIL=$(cat)
DATE=`date +%s`
RANDOM_NUM=$(echo "$RANDOM" | head -c 3)
UNIQ_NAME="email_${DATE}_${RANDOM_NUM}"
DIR="$HOME/Mail/data/$UNIQ_NAME"
JSON="$DIR/$UNIQ_NAME.json"

mkdir -p $DIR

echo "$EMAIL" > "$HOME/Mail/data/$UNIQ_NAME.mail"

echo "$EMAIL" | uudeview -i +a +o -t -p $DIR -

decode_and_concatenate() {
  local input_string="$1"
  local result=""

  for part in $input_string; do
    decoded_part=$(echo $part | awk -F '[?]' '{print $4}' | openssl enc -base64 -d -A)
    result+="$decoded_part"
  done

  echo "$result"
}

FROM=$(echo "$EMAIL" | formail -c -x From: | awk -F'[<>]' '{print $2}')

FROM_NAME=$(echo "$EMAIL" | formail -c -x From: | awk -F'<|>' '{print $1}' | sed 's/^[ \t]*//;s/[ \t]*$//' | while read -r name_line; do
    if [[ $name_line == =?* ]]; then
      name_clear=$(echo "$name_line" | sed 's/  / /g')
      decode_and_concatenate "$name_clear"
    else
      echo "$name_line"
    fi
  done | tr -d '\n' | base64 -w 0)

TO=$(echo "$EMAIL" | formail -x To: | sed 's/^[ \t]*//;s/[ \t]*$//;s/"//g' | sed 's/.*<\([^>]*\)>.*/\1/')

SUBJECT=$(echo "$EMAIL" | formail -c -x Subject | sed 's/^[ \t]*//;s/[ \t]*$//' | while read -r sub_line; do
    if [[ $sub_line == =?* ]]; then
      sub_clear=$(echo "$sub_line" | sed 's/  / /g')
      decode_and_concatenate "$sub_clear"
    else
      echo "$sub_line"
    fi
  done | tr -d '\n' | base64 -w 0)

if [ -f "$DIR/UNKNOWN.001" ]; then
  BODY=$(base64 -w 0 "$DIR/UNKNOWN.001")
else
  if formail -c -x Content-Type: < "$DIR/0001.txt" | grep -qv "multipart"; then
    BODY=$(formail -I "" < "$DIR/0001.txt" | base64 -w 0)
  else
    BODY=$(base64 -w 0 "$DIR/0001.txt")
  fi
fi

FILES=()

for FILE_PATH in "$DIR"/*; do
    FILE_NAME=$(basename "$FILE_PATH")
    FILE_NAME_BASE=$(echo "$FILE_NAME" | tr -d '\n' | base64 -w 0)
    if [[ ! "$FILE_NAME" =~ ^UNKNOWN\.[0-9]+$ ]] && [[ ! "$FILE_NAME" =~ ^000[0-9]+\.txt$ ]]; then
        FILE_CONTENT=$(base64 -w 0 "$FILE_PATH")
        FILES+=("{\"name\":\"$FILE_NAME_BASE\",\"filestringbase64\":\"$FILE_CONTENT\"}")
    fi
done

FILES_JSON=$(printf ",%s" "${FILES[@]}")
FILES_JSON="[${FILES_JSON:1}]"

echo "{\"email_id\":\"$UNIQ_NAME\",\"from\":\"$FROM\",\"from_name\":\"$FROM_NAME\",\"to\":\"$TO\",\"subject\":\"$SUBJECT\",\"body\":\"$BODY\",\"files\":$FILES_JSON}" > $JSON

KEY="my_key"

curl -d @"$JSON" -H "Content-Type: application/json" -H "Authorization: $KEY" https://YOUR_HOST/Remotes/get_email

rm -r $DIR
```

Делаем скрипт исполняемым:

```
chmod +x extract.sh
```

Идем в Totum в `ttm__remotes` и создаем ремоут `get_email` (назначаете пользователя и добавляете код, заменив `MY_KEY` на сохраненный ранее ключ):

```
=: if(condition: $#headers[Authorization][0] = "MY_KEY"; then: $insert)

insert: insert(table: 'email_table'; field: 'raw' = $#input; field: 'email_id' = $email_data[email_id]; field: 'email_data' = $email_data_decode; field: 'email_files' = $email_files_decode; log: true)

email_data_decode: rowAdd(row: $email_data; field: "from_name" = $from_name_decode; field: "subject" = $subject_decode; field: "body" = $body_decode)
	from_name_decode: strBaseDecode(str: $email_data[from_name])
	subject_decode: strBaseDecode(str: $email_data[subject])
	body_decode: strBaseDecode(str: $email_data[body])

		~email_data: rowKeysRemove(row: $extract; key: "files")

email_files_decode: listReplace(list: $extract[files]; action: "name" = $name; value: "value")
	name: strBaseDecode(str: $#value[name])

~extract: jsonExtract(text: $#input)
```

Создаем таблицу в Totum c name=`email_table` и добавляем поля:

- `email_id` — {строка}
- `raw` — {строка}
- `email_data` — {данные}
- `email_files` — {файл} + [множественный] + [хеши на конце файла] или [защищенный файл]

Таблице ставим [pagination] и по желанию [обратный порядок]

Проверяете сначала из консоли сервера:

```
fetchmail
```

Лог записывается в:

```
tail -100 ~/Mail/procmail.log
```

Добавляем крон (можно из системы или из totum). Если из totum, то должен быть включен execSSH! Период проверки выбираете по желанию.

Из Тотум в кроны добавляем проверку и очистку технической папки от писем старше 3 дней и ротирование лога:

```
= : execSSH(ssh: "fetchmail -s && find ~/Mail/data/ -type f -mtime +3 -exec rm {} \;) && tail -n 1000 ~/Mail/procmail.log > ~/Mail/procmail.log.tmp && mv ~/Mail/procmail.log.tmp ~/Mail/procmail.log
```

Включение execSSH (только в PRO, как и поля типа Файл):

```
nano /home/totum/totum-mit/Conf.php
```

`Ctrl` + ` W` набираем `'inner'`, `enter` и заменяем `'inner'` на `true` (`true` без кавычек!)

ИЛИ! Из системы от пользователя totum:

```
su totum
```
```
crontab -e
```
```
* * * * * fetchmail -s && find ~/Mail/data/ -type f -mtime +3 -exec rm {} \; && tail -n 1000 ~/Mail/procmail.log > ~/Mail/procmail.log.tmp && mv ~/Mail/procmail.log.tmp ~/Mail/procmail.log
```
> Помните, что последняя строка `crontab` всегда должна оставаться пустой

Не рекомендуется подключаться к основному email, который находится на входе от внешнего мира. Используйте правила сортировки и пересылки на вашем email-сервере и пересылайте проходящие по условиям письма на технических email. И с него уже забирайте данные в базу!

## <a name="mailcow"></a> Устанавливаем Mailcow как SMTP-сервер для Totum

### Устанавливаем mailcow

[Mailcow на GitHub](https://github.com/mailcow/mailcow-dockerized)

> Перед арендой виртуалки удостоверьтесь, что хостер разрешает исходящие соединения по 25 и 495 порту. Есть хостеры у которых невозможно развернуть SMTP-сервер. У остальных стандартной практикой является разблокировка порта по запросу в техподдержку!

> Эта инструкция написана и проверена для Ubuntu 24.04

Создаем сервер не менее 2С х 4G х 20G SSD (mailcow будет потреблять 75% этого объема по памяти и диску).

Смотрим что занимает 25 порт:

```
ss -tulnp | grep :25
```

Если вывод не пустой то запоминаем PID процесса.

Смотрим что за процесс (подставляем запомненный PID):

```
ps -p PID -o pid,ppid,user,stat,cmd
```

Смотрим список сервисов и пытаемся понять, что нужно отключить на основе вывода предыдущей команды:

```
systemctl list-units --type=service --state=running
```

Если вы видите что типа:

```
exim4.service # в этом случае название сервиса exim4

postfix@-.service # в этом случае название сервиса postfix
```

Отключаем (например postfix, вы подставляете то, что вы определили как занимающее 25 порт):

```
systemctl disable postfix
```

Проверяете:

```
systemctl is-enabled postfix
```

Проверяем повторно 25 порт:

```
ss -tulnp | grep :25
```

Вывод должен быть пустым!

Адресуем на сервер `smtp.YOUR_HOST` домен.

Устанавливаем `ufw` и разрешаем `22`, `80` и `443` порты:

```
apt install ufw
```

```
ufw default deny incoming && ufw default allow outgoing && ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp
```

Устанавливаем `docker` и `docker-compose`

Предварительно смотрим [номер релиза](https://github.com/docker/compose/releases) и заменяем `2.29.2` на номер последнего релиза:

```
apt update && apt -y install software-properties-common git nano htop && apt update && apt -y install ca-certificates curl gnupg lsb-release && curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null && apt-get update && apt-get -y install docker-ce docker-ce-cli containerd.io && curl -L "https://github.com/docker/compose/releases/download/v2.29.2/docker-compose-linux-x86_64" -o /usr/local/bin/docker-compose && chmod +x /usr/local/bin/docker-compose && apt install docker-compose-plugin
```

Устанавливаем в `opt`:

```
cd /opt && git clone https://github.com/mailcow/mailcow-dockerized && cd mailcow-dockerized
```

Генерируем конфиг:

```
./generate_config.sh
```

Указываем домен без протокола, вводим `timezone` в формате `Europe/London` и выбираем `stable` вариант (цифра 1).

Скачиваем контейнеры:

```
docker compose pull
```

Запускаем:

```
docker compose up -d
```

Ждем пока все создалось и запустилось и останавиваем:

```
docker compose down
```

#### Отключаем `ipv6`:

> Эта инструкция предполагает, что будем эксплуатировать по стандартному `ipv4`

```
nano /opt/mailcow-dockerized/docker-compose.yml
```

Ищем:  `Ctrl` + `W` — `enable_ipv6` — `enter`

Изменяем на `false`  и комментируем строку сети `ipv6`:

```
networks:
  mailcow-network:
    [...]
    enable_ipv6: false # <<< set to false
    ipam:
      driver: default
      config:
        - subnet: ${IPV4_NETWORK:-172.22.1}.0/24
#       - subnet: ${IPV6_NETWORK:-fd4d:6169:6c63:6f77::/64} # <<< comment out with #
    [...]
```

Создаем дополнительный файл настроек (тк это `yml` то пробелы супер-важны! их нельзя просто так добавлять или удалять):

```
nano /opt/mailcow-dockerized/docker-compose.override.yml
```

```
services:

    ipv6nat-mailcow:
      image: bash:latest
      restart: "no"
      entrypoint: ["echo", "ipv6nat disabled in compose.override.yml"]
```

Отключаем `IPv6` в `unbound-mailcow`:

```
nano /opt/mailcow-dockerized/data/conf/unbound/unbound.conf
```

Ищем: `Ctrl` + `W` — `do-ip6` — `enter`

Ставим `no`:

```
server:
  [...]
  do-ip6: no # here put no
  [...]
```

Отключаем `IPv6` в `postfix-mailcow`:

```
nano /opt/mailcow-dockerized/data/conf/postfix/extra.cf
```

Добавляем строки:

```
smtp_address_preference = ipv4
inet_protocols = ipv4
```

Корректируем конфиг `Nginx`, `Dovecot` и `php-fpm`:

```
sed -i '/::/d' /opt/mailcow-dockerized/data/conf/nginx/listen_* && sed -i '/::/d' /opt/mailcow-dockerized/data/conf/nginx/templates/listen* && sed -i '/::/d' /opt/mailcow-dockerized/data/conf/nginx/dynmaps.conf && sed -i 's/,\[::\]//g' /opt/mailcow-dockerized/data/conf/dovecot/dovecot.conf && sed -i 's/\[::\]://g' /opt/mailcow-dockerized/data/conf/phpfpm/php-fpm.d/pools.conf
```

Стартуем (по прежнему должны находится в папке `cd /opt/mailcow-dockerized`):

```
docker compose up -d
```

Смотрим лог получения SSL-сертификата:

```
docker compose logs --tail=200 -f acme-mailcow
```

Должны увидеть какие-то сообщение о получении сертификата нужно подождать пару минут!

---

[Оригинальная инструкция](https://docs.mailcow.email/post_installation/firststeps-disable_ipv6/)

[Оригинальная инструкция по сертификату](https://docs.mailcow.email/post_installation/firststeps-ssl/#lets-encrypt-out-of-the-box)

---

#### Очистка логов docker

Создаем в папке `/opt/mailcow-dockerized` скрипт `clear_docker_logs.sh`:

```
nano /opt/mailcow-dockerized/clear_docker_logs.sh
```

```
#!/bin/bash

containers=(
  mailcowdockerized-olefy-mailcow-1
  mailcowdockerized-solr-mailcow-1
  mailcowdockerized-sogo-mailcow-1
  mailcowdockerized-memcached-mailcow-1
  mailcowdockerized-redis-mailcow-1
  mailcowdockerized-unbound-mailcow-1
  mailcowdockerized-watchdog-mailcow-1
  mailcowdockerized-dockerapi-mailcow-1
  mailcowdockerized-php-fpm-mailcow-1
  mailcowdockerized-clamd-mailcow-1
  mailcowdockerized-mysql-mailcow-1
  mailcowdockerized-dovecot-mailcow-1
  mailcowdockerized-postfix-mailcow-1
  mailcowdockerized-nginx-mailcow-1
  mailcowdockerized-acme-mailcow-1
  mailcowdockerized-netfilter-mailcow-1
  mailcowdockerized-rspamd-mailcow-1
  mailcowdockerized-ofelia-mailcow-1
  mailcowdockerized-ipv6nat-mailcow-1
)

for container in "${containers[@]}"; do
  sudo sh -c 'echo "" > $(docker inspect --format="{{.LogPath}}" '"$container"')'
done
```

Делаем исполняемым:

```
chmod +x /opt/mailcow-dockerized/clear_docker_logs.sh
```

Ставим на `cron`:

```
crontab -e
```

```
0 3 * * 0 /opt/mailcow-dockerized/clear_docker_logs.sh
```

#### После запуска

Идем в админ-панель по указанному при конфигурации домену: `https://HOST`

Стандартный логин `admin`, пароль `moohoo`.

Меняем пароль админа в `system` — `configuration`

#### Добавляем домен и ящик:

Для добавления нового почтового домена, переходим в в раздел `Configuration` — `Mail Setup` и нажимаем на зеленую кнопку `+ Add Domain`.

— домен = `YOUR_EMAIL_DOMAIN`

— псевдонимов = `0`

— почтовых ящиков = `1`

— селектор = `tcdkim`

— `Add domain and restart SOGo`

**Настраиваем DNS:**

Напротив домена нажимаем DNS и видим таблицу настроек. Ожидаемые значения и значения, полученные сканером.

Настраиваем на вашем DNS-сервере по рекомендациям.

`PTR`-запись важна для почтовых серверов. Через нее владелец  ip-пула как-бы подтверждает, что знает про ваш домен. Иногда ее изменение доступно через настройки виртуального сервера (например у [https://vultr.com](https://www.vultr.com/?ref=9084282)), но иногда нужно писать в поддержку хостера ([https://netangels.ru](https://netangels.ru/?p_ref=u83347)).

Значение `SPF` (замените `YOUR_SMTP_IP` на `ip` вашего сервера):

```
v=spf1 ip4:YOUR_SMTP_IP ~all
```

Значение `DMARC` записи:

```
v=DMARC1; p=reject;
```

**Добавляем ящик на соседней вкладке:**

— `no-reply` (стандарт) или `totum-noreply` (здесь рекомендуется вместо `totum` использовать ваше осмысленное название)

— выбираем созданный ранее домен

— создаем пароль

— отключаем `POP`, `IMAP` и `Sieve`

— Сохраняем

#### Настраиваем отклонение всех входящих писем (только отправка)

Идем в раздел `Email` — `Configeration` — `Filters`

Коментируем `#` все строки в `Global prefilter` и добавляем в конец:

```
require ["reject"];

# Reject all incoming messages
reject "This SMTP-server does not accept incoming messages!";

```

Нажимаем `Validate` и потом `Save changes`.

Таким образом сервер не будет засоряться входящими спам-сообщениями.

#### Настраиваем `Conf.php` в Totum

[Настройки для emailSend](/functions#fn-emailSend-settings)

В `totum` параметры `smtp` надо заполнить в таблице `settings` в поле `custom_smtp_setings_for_schema`:

```
{
  "host": "ssl://smtp.gmail.com",
  "port": 465,
  "login": "totum@totum.online",
  "password": "password_here"
}
```

По умолчанию письма отправляются от адреса `no-reply@HOST`, но если вы создали другой ящик, или почтовый домен не совпадает с доменом установки `Totum` то надо указать полный адрес ящика в таблице `settings` в поле `default_email`.

**Если вы хотите задать для всех сехм на сервере жесткий `smtp` то:**

Заполняем в `Conf.php` параметры SMTP для всех схем:

```
nano /home/totum/totum-mit/Conf.php
```

Переключаем `Conf.php` на использование `SMTP`, закоментировав `WithPhpMailerTrait` и разкомментировав `WithPhpMailerSmtpTrait` — это отключает `custom_smtp_setings_for_schema` в таблице `settings`.

Разкомментируйте параметры SMTP и заполните вашими данными:

```
 protected $SmtpData = [
                'host' => 'ssl://smtp.ttmapp.ru',
                'port' => 465,
                'login' => 'no-reply@alliance.ttmapp.ru',
                'pass' => 'CFbhNe3exyi2hdUfDq',
            ];
```

#### Проверка письма на доставляемость

Используйте [https://www.mail-tester.com/](https://www.mail-tester.com/) для проверки письма на доставляемость.

Также рекомендуем включить [PRO List-unsubscribe][629]

#### Лимит

Если необходимо увеличить/уменьшить максимальный размер письма (по умолчанию 100 Mb в `main.cf`):

```
nano /opt/mailcow-dockerized/data/conf/postfix/extra.cf
```

Добавляем параметр:

```
message_size_limit = LIMIT_IN_BYTES
```

Потом необходимо рестартовать `postfix`:

```
cd /opt/mailcow-dockerized && docker-compose restart postfix-mailcow
```

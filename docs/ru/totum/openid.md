# OpenID (SSO)

PRO. Вход в Totum через внешний сервер авторизации по протоколу OpenID Connect (Keycloak, Яндекс ID, VK ID, Google, Microsoft Entra ID и другие) — единый вход (SSO).

Totum работает только в режиме `authorization code`: пользователь авторизуется на стороне провайдера, провайдер возвращает его в Totum с одноразовым кодом, Totum обменивает код на токен прямым запросом к провайдеру и по данным токена находит или создает пользователя.

Провайдеры настраиваются в таблице `ttm__openid` (дерево: Системные таблицы → PRO Разное → OpenID): одна строка — один провайдер. Провайдеров может быть несколько — на странице входа будет кнопка для каждого.

## <a name="openid-fields"></a> Поля таблицы ttm__openid

| Поле | name | Назначение |
|---|---|---|
| Name провайдера OpenID | `name` | внутреннее имя провайдера в Totum. Записывается пользователю в `ttm__extparams` — видно, через какого провайдера он вошел |
| Заголовок кнопки | `button_title` | надпись на кнопке на странице входа |
| Картинка на кнопке | `button_img` | картинка кнопки (берется первый файл) |
| URL авторизации | `auth_uri` | адрес страницы авторизации провайдера (`authorization_endpoint`), на который перенаправляется пользователь |
| ID клиента | `client_id` | идентификатор приложения у провайдера. Клиент в OpenID — это приложение (Totum), а не пользователь |
| URI перенаправления | `redirect_uri` | адрес Totum, на который провайдер вернет пользователя. При добавлении строки заполняется как `https://<хост>/Auth/OpenID`; через контекстную панель поля можно вернуть стандартное значение. Этот же адрес нужно зарегистрировать у провайдера |
| Список scopes | `scope` | какие данные о пользователе запрашиваются у провайдера. По умолчанию `openid` и `email`. Названия зависят от провайдера: например, Google — `openid`, `email`, `profile`; Яндекс ID — `login:email`; VK ID — `email`. Email обязателен — по нему определяется пользователь; если провайдер не кладет его в `id_token`, его получают в `check_code` |
| Код генерации дополнительных опций | `extra_options` | код, который возвращает дополнительные GET-параметры для перехода на `auth_uri` — см. [ниже][689] |
| Эндпойнт получения токена | `token_endpoint` | адрес обмена кода на токен (`token_endpoint` провайдера) |
| Дополнительные заголовки | `extra_headers` | код, который возвращает дополнительные заголовки и POST-параметры запроса обмена кода на токен — см. [ниже][690] |
| Secret клиента OpenID | `client_secret` | секрет приложения, выданный провайдером. Поле Пароль в режиме `Crypto.key`: хранится зашифрованным, для работы нужен файл `Crypto.key` в корне установки |
| Ключ для FIO | `fio_key` | ключ в данных токена, из которого берется ФИО нового пользователя (например `name`) |
| Код определения ролей | `roles_code` | код, который возвращает [список][14] id ролей пользователя — см. [ниже][691] |
| Комментарий при отклонении | `rejection_comment` | если заполнен — новые пользователи через этого провайдера не создаются, вместо этого показывается этот текст. Войти смогут только пользователи, которые уже есть в Totum |
| Код проверки | `check_code` | код проверки и замены данных токена — см. [ниже][692] |
| Код-действия при регистрации | `new_user_action` | код действия, который выполняется один раз — при создании нового пользователя через OpenID |
| Успешный редирект | `success_redirect` | адрес, на который пользователь попадает после входа. Если пуст — `/` |
| Редирект при ошибке | `error_redirect` | адрес, на который пользователь перенаправляется при ошибке; текст ошибки передается в GET-параметре `ERROR`. Если пуст — показывается страница ошибки Totum |
| Статус | `status` | провайдер включен: показывается кнопка на странице входа и принимается вход через него |

## <a name="openid-flow"></a> Как проходит вход

1. На странице входа пользователь нажимает кнопку провайдера.
2. Totum генерирует случайный `state`, запоминает его в сессии и перенаправляет пользователя на `auth_uri` с GET-параметрами:
	+ `response_type=code`;
	+ `state` — проверяется при возврате, чтобы чужой ответ нельзя было подставить в сессию;
	+ `client_id`, `redirect_uri`, `scope` (через пробел) — из полей строки;
	+ параметры из `extra_options`.
3. Пользователь авторизуется у провайдера, провайдер возвращает его на `redirect_uri` с `code` и `state`, например:
	```
	GET https://totum.example.com/Auth/OpenID?state=…&session_state=…&iss=https%3A%2F%2Fkeycloak.example.com%2Frealms%2Ftotum&code=45e9cefe-…
	```
	Если `state` не совпадает с сохраненным в сессии — вход прерывается («Session was closed»).
4. Totum делает POST-запрос на `token_endpoint` (`Content-Type: application/x-www-form-urlencoded`) с параметрами `grant_type=authorization_code`, `client_id`, `code`, `redirect_uri`, `client_secret` (расшифрованный) и дополнительными заголовками / параметрами из `extra_headers`:
	```
	curl -X POST https://keycloak.example.com/realms/totum/protocol/openid-connect/token \
	  -H "Content-Type: application/x-www-form-urlencoded" \
	  -d "grant_type=authorization_code" \
	  -d "client_id=totum-online" \
	  -d "client_secret=…" \
	  -d "code=…" \
	  -d "redirect_uri=https://totum.example.com/Auth/OpenID"
	```
5. Провайдер отвечает json с `access_token`, `id_token` и др. Если в ответе есть `error` — вход прерывается с этой ошибкой. Totum берет `id_token` — JWT — и разбирает его вторую часть (payload) в [ассоциативный массив][30]:
	```json
	{
	  "sub": "8534fd3c-c1b9-46ad-ad01-70597c39a6e7",
	  "email": "user@example.com",
	  "name": "User Vano P",
	  "preferred_username": "user",
	  "given_name": "User Vano",
	  "family_name": "P",
	  "email_verified": true
	}
	```
	Подпись JWT не проверяется: токен получен не от браузера, а напрямую от `token_endpoint` по защищенному соединению.

	Не все провайдеры возвращают `id_token` с email: Яндекс ID отдает только `access_token` (`id_token` нет — данные токена пустые), в `id_token` VK ID нет email. Тогда данные пользователя запрашивают у провайдера по `access_token` в `check_code` и возвращают оттуда — см. [ниже][692].
6. Выполняется `check_code` — может отклонить вход или заменить данные токена.
7. По `email` из токена Totum ищет пользователя и создает или обновляет его — см. [Пользователи][693]. Если `email` в данных нет — вход не выполняется.
8. Подтверждение от провайдера равнозначно правильному паролю: если включена [2FA][634], пользователь переходит к вводу второго фактора, иначе — входит и перенаправляется на `success_redirect`.

## <a name="openid-extra-options"></a> extra_options — дополнительные параметры перехода к провайдеру

Код должен вернуть [ассоциативный массив][30] — его ключи добавляются к GET-параметрам перехода на `auth_uri`. Нужен для параметров, которые требует провайдер: `nonce`, `prompt`, `login_hint`, `acr_values`, `code_challenge` и т.п.

+ Код получает переменную `$#state` — значение `state`, сгенерированное для этого входа. Его можно использовать, чтобы связать с этим входом свои данные (например, `code_verifier` для PKCE) и получить их обратно в `extra_headers` по `$#code_answer[state]`.
+ Стандартные параметры (`response_type`, `state`, `client_id`, `redirect_uri`, `scope`) кодом не переопределяются — добавляются только новые ключи.

Если дополнительных параметров нет — поле оставляют пустым.

Google: случайный `nonce` и `prompt=login`:

```totum
=: rowCreate(field: "nonce" = $nonce; field: "prompt" = "login")

	nonce: strRandom(length: 10; numbers: "true"; letters: "true"; symbols: "false")
```

`prompt=login` заставляет провайдера каждый раз запрашивать пароль, даже если пользователь уже авторизован у него.

VK ID требует PKCE: в переходе передается `code_challenge` — base64url (без `=`) от SHA-256 случайной строки `code_verifier`, а сам `code_verifier` передается потом при обмене кода на токен (в `extra_headers`). Поэтому код сохраняет `code_verifier` вместе с `$#state` в свою таблицу (здесь `openid_pkce` с полями `state`, `code_verifier` и `date` — дата-время создания строки):

```totum
=: rowCreate(field: "code_challenge_method" = "S256"; field: "code_challenge" = $b64url)

	b64url: extBase64Url(str: $hash; hex: true)
		hash: extSHA256(str: $while_insert)
			while_insert: while(action: $insert; action: $code_verifier)
				insert: insert(table: 'openid_pkce'; field: 'state' = $#state; field: 'code_verifier' = $code_verifier)
					~code_verifier: strRandom(length: 64; numbers: "true"; letters: "true"; symbols: "false")
```

+ `~code_verifier` рассчитывается один раз — в таблицу записывается и хешируется одна и та же строка; [while][284] выполняет `insert` и возвращает результат последнего действия — `code_verifier`.
+ `extSHA256` и `extBase64Url` — не встроенные функции: в Totum нет SHA-256 и base64url, их подключают [функциями на PHP][505] (`CalculateExtensions.php`). Здесь `extSHA256` возвращает SHA-256 строки в hex, `extBase64Url` с `hex: true` переводит hex в байты и кодирует их в base64url без `=`.

## <a name="openid-extra-headers"></a> extra_headers — дополнительные заголовки и параметры обмена кода на токен

Код выполняется перед POST-запросом на `token_endpoint` и должен вернуть [ассоциативный массив][30] с ключами:

+ `headers` — [список][14] строк заголовков вида `"Имя: значение"`. Добавляются к запросу; `Content-Type: application/x-www-form-urlencoded` добавляется всегда.
+ `posts` — [ассоциативный массив][30] POST-параметров. Добавляются к стандартным (`grant_type`, `client_id`, `code`, `redirect_uri`, `client_secret`); параметр с тем же именем **заменяет** стандартный.

Оба ключа необязательные.

Код получает переменную `$#code_answer` — [ассоциативный массив][30] всех GET-параметров, с которыми провайдер вернул пользователя на `redirect_uri` (`code`, `state`, `session_state`, `iss` и любые параметры провайдера). Так можно передать в обмен кода параметры, которые провайдер присылает при возврате.

Например, VK ID возвращает пользователя с `device_id` и требует передать при обмене кода `device_id`, `state` и `code_verifier`, сохраненный в [extra_options][689] по `state`:

```totum
=: rowCreate(field: "posts" = $posts)

posts: rowCreate(field: "code_verifier" = $code_verifier; field: "device_id" = $#code_answer[device_id]; field: "state" = $#code_answer[state])

	code_verifier: select(table: 'openid_pkce'; field: 'code_verifier'; where: 'state' = $#code_answer[state]; where: 'date' >= $dadd)
		dadd: dateAdd(date: $#ndt; minutes: -10; format: "Y-m-d H:i")
```

Условие по `date` ограничивает время жизни `code_verifier` десятью минутами. Заголовки добавляются так же — ключом `headers`, например `field: "headers" = listCreate(item: "Accept: application/json")`.

Если провайдер требует передавать секрет не в теле запроса, а заголовком `Authorization: Basic …` (`client_secret_basic`), верните его в `headers`, а `client_secret` — пустым в `posts`.

## <a name="openid-check-code"></a> check_code — проверка данных токена

Необязательный код, который выполняется после получения токена. Получает переменные:

+ `$#id_token` — разобранные данные `id_token` ([ассоциативный массив][30]);
+ `$#raw` — полный текст ответа `token_endpoint` (json-строка: `access_token`, `refresh_token`, `id_token`, …).

Результат кода:

+ [ассоциативный массив][30] с ключом `error` — вход отклоняется, пользователю показывается текст `error` (или он передается в `error_redirect`);
+ [ассоциативный массив][30] с ключом `id_token` (ассоциативный массив) — дальше используются эти данные вместо данных токена. Так можно, например, подставить `email` из другого ключа или дополнить данные запросом к провайдеру по `access_token`;
+ другой непустой [ассоциативный массив][30] — вход отклоняется с ошибкой `check_code error`;
+ пусто — вход продолжается с исходными данными токена.

Google: email должен быть передан и подтвержден:

```totum
=: if(condition: $#id_token[email] = ""; then: $err; else: $verified)

	err: rowCreate(field: "error" = "Невозможно авторизоваться, необходимо, чтобы был передан email!")

	verified: if(condition: $#id_token[email_verified] != true; then: $err_verif; else: $id_token)

		err_verif: rowCreate(field: "error" = "Ваш email в системе верификации не подтвержден — вход невозможен!")

		id_token: rowCreate(field: "id_token" = $#id_token)
```

Если провайдер не возвращает email в `id_token`, код запрашивает данные пользователя по `access_token` из `$#raw` и собирает из них `id_token`. В нем должны быть `email`, `sub`, `sid` (записываются в `ttm__extparams`) и ключ из `fio_key`. Яндекс ID (`fio_key` = `name`):

```totum
=catch: if(condition: $user_extract[default_email] = ""; then: $err_email; else: $verified)

	err_email: rowCreate(field: "error" = "Невозможно авторизоваться, необходимо, чтобы был передан email!")

	verified: rowCreate(field: "id_token" = $row_create)

	row_create: rowCreate(field: "sid" = $user_extract[id]; field: "sub" = $user_extract[client_id]; field: "email" = $user_extract[default_email]; field: "name" = $user_extract[login]; field: "raw" = $raw; field: "raw_user_data" = $user_extract)

	~raw: jsonExtract(text: $#raw)

	~user_extract: jsonExtract(text: $get_user_data)

		get_user_data: getFromScript(uri: "https://login.yandex.ru/info"; headers: $headers; gets: $gets; timeout: 10)
			headers: listCreate(item: str`"Authorization: OAuth" ++ $raw[access_token]`)
			gets: rowCreate(field: "format" = "json")

catch: rowCreate(field: "error" = "Не удалось получить данные пользователя")
```

+ `=catch:` — [tryCatch][288]: если запрос к провайдеру упал, вход отклоняется с текстом `catch`.
+ `~raw`, `~user_extract` рассчитываются один раз — запрос к провайдеру выполняется один раз, хотя к результату обращаются несколько строк.
+ `raw`, `raw_user_data` необязательны — так полные ответы провайдера сохранятся в `ttm__extparams` пользователя.

VK ID — то же, но `id_token` у него есть (без email): `sub` берется из него, данные пользователя — POST-запросом с `client_id` из ключа `app` токена, ответ — в ключе `user` (`user_id`, `email`, `first_name`):

```totum
	~user_extract: $get_extract[user]
		get_extract: jsonExtract(text: $get_user_data)

		~get_user_data: getFromScript(uri: "https://id.vk.ru/oauth2/user_info"; method: "POST"; post: "client_id" = $client_id; post: "access_token" = $raw[access_token]; timeout: 10)
			client_id: $#id_token[app]
```

с `row_create: rowCreate(field: "sid" = $user_extract[user_id]; field: "sub" = $#id_token[sub]; field: "email" = $user_extract[email]; field: "name" = $user_extract[first_name]; …)` и проверкой `$user_extract[email] = ""`.

## <a name="openid-users"></a> Пользователи

Пользователь Totum определяется по `email` из токена (без учета регистра) — `email` в OpenID уникален.

+ **Пользователь с таким email есть** — входит под своей учетной записью. Ему устанавливается тип авторизации `ttm__auth_type = OpenID`, обновляются `ttm__extparams` и роли. Пароль не сбрасывается — войти по логину и паролю он тоже сможет.
+ **Пользователя нет** — создается новый (если не заполнен `rejection_comment`):
	+ `fio` — из ключа `fio_key` данных токена;
	+ `email` — из токена; `login` пустой;
	+ `ttm__auth_type = OpenID`, роли — из `roles_code`;
	+ затем выполняется `new_user_action` — код действия в контексте таблицы `users` с переменной `$#userId` (id созданного пользователя). Выполняется только один раз — при создании.

В `ttm__extparams` пользователя при каждом входе записывается:

+ `id_token` — данные токена (после `check_code`);
+ `name` — `name` провайдера из `ttm__openid`;
+ `sid`, `sub` — одноименные значения из токена;
+ `id_token_hint` — исходный `id_token` (JWT), если провайдер его вернул, — пригодится, например, для выхода у провайдера (`end_session_endpoint?id_token_hint=…`).

Если настроено несколько провайдеров и пользователь входит то через один, то через другой, `ttm__extparams` перезаписывается данными последнего.

> Восстановление пароля по email для пользователей с типом авторизации (`ttm__auth_type`) запрещено, если в таблице Настройки не включено `h_allow_restore_with_different_type`.

## <a name="openid-roles"></a> roles_code — роли

Код выполняется при каждом входе через OpenID и должен вернуть [список][14] id ролей. Этот список **записывается в роли пользователя при каждом входе** — роли, выданные вручную в Totum, при следующем входе будут заменены. Получает переменную `$#id_token` — данные токена ([ассоциативный массив][30]).

Логику назначения ролей вы пишете сами. Чтобы роли, выданные в Totum вручную, не сбрасывались, код возвращает текущие роли пользователя, а новому пользователю — роль по умолчанию (здесь `3`):

```totum
=: if(condition: $exist = ""; then: listCreate(item: "3"); else: $exist)

	exist: select(table: 'users'; field: 'roles'; where: 'email' = $#id_token[email])
```

## <a name="openid-limits"></a> Ограничения

+ Выход из Totum не завершает сессию у провайдера, а выход у провайдера (например, разлогин в Keycloak) не завершает сессию в Totum — Totum об этом не уведомляется.
+ Вход через другого провайдера никак не затрагивает данные предыдущего, кроме перезаписи `ttm__extparams`.

# Взаимодействие по API

## <a name="calling-a-third-party-script-from-button-code"></a> Вызов стороннего скрипта из кода по кнопке

видео: [YouTube](https://www.youtube.com/watch?v=vE5Car9h7LM)

### <a name="calling-a-third-party-script-from-button-code-qa"></a> Вопросы и ответы

#### <a name="calling-a-third-party-script-from-button-code-q1"></a> Чем отличается getFromScript от linkToScript?

Есть две функции, которые из Totum могут вызвать сторонний сервер. На самом деле их больше, но нас интересуют эти две:

+ [linkToScript][306] — открывает окно!

+ [getFromScript][312] — выполняет действие в фоне!

#### <a name="calling-a-third-party-script-from-button-code-q2"></a> Как выполнить POST или GET запрос при помощи getFromScript?

Например, нам нужно вызвать сторонний сервер, передать ему параметры запроса и сделать что-то с полученным ответом. Например запишем ответ в поле `h_data`:

Передаем POST-параметры (они не видны в адресной строке):

```totum
=: set(table: $#ntn; field: 'h_data' = $script)

// Вариант, когда мы post-параметры вносим вручную

script: getFromScript(uri: "https://example.host/api/uri/"; post: "action" = "select"; post: "key" = "oIpOyDHca3tSQZIz3ZjY"; post: "params" = $params; timeout: 30)
	params: jsonCreate(data: $paramsJsonData)
		paramsJsonData: rowCreate(field: "user" = #user; field: "type" = "new")

```

```totum
=: set(table: $#ntn; field: 'h_data' = $script)

// Вариант, когда мы post-параметры передаем как row

script: getFromScript(uri: "https://example.host/api/uri/"; posts: $data; timeout: 30)

data: rowCreate(field: "action" = "select"; field: "key" = "oIpOyDHca3tSQZIz3ZjYRZGqQd0yvqXYwQ"; field: "params" = $params)
	params: jsonCreate(data: $paramsJsonData)
		paramsJsonData: rowCreate(field: "user" = #user; field: "type" = "new")

```

Передаем GET-параметры (они видны в адресной строке):

```totum
=: set(table: $#ntn; field: 'h_data' = $script)

// Вариант, когда мы post-параметры передаем как row

script: getFromScript(uri: "https://example.host/api/uri/"; gets: $data; timeout: 30)

data: rowCreate(field: "action" = "select"; field: "key" = "oIpOyDHca3tSQZIz3ZjYRZGqQd0yvqXYwQ"; field: "params" = $params)
	params: jsonCreate(data: $paramsJsonData)
		paramsJsonData: rowCreate(field: "user" = #user; field: "type" = "new")

```

#### <a name="calling-a-third-party-script-from-button-code-q3"></a> Зачем нужен параметр timeout?

**Важно:** при вполнении такого запроса к стороннему серверу если не задать параметр `timeout` процесс Тотума будет ждать ответ бесконечно и не отключится по внутреннему таймауту.

Если `timeout` задан то по его истечении при отсутвии ответа стороннего сервера произойдет срабатывание исключения (exception), аналогично функции [errorException][287].

#### <a name="calling-a-third-party-script-from-button-code-q4"></a> Может ли getFromScript быть вызван из кода, а не только из кода-действия?

Да, [getFromScript][312] является одной из функций исключений. Но с этим необходимо быть очень осторожным, тк зависание ответа стороннего сервера приведет к зависанию таблицы!

## <a name="calling-a-third-party-server-from-code"></a> Вызов стороннего сервера из кода

### <a name="calling-a-third-party-server-from-code-qa"></a> Вопросы и ответы

#### <a name="calling-a-third-party-server-from-code-q1"></a> Из каких секций кода можно вызвать linkToScript или getFromScript?

Это функции кодов-действий, но [getFromScript][312] относится к исключениям, которые могут выполняться в коде.

#### <a name="calling-a-third-party-server-from-code-q2"></a> Что произойдет, если при вызове сторонний сервер не отвечает?

Таймаут процесса Totum на это не влияет, он бесконечно будет ждать сторонний сервер, пока тот не вернет код ошибки.

Чтобы избежать жестких зависаний — задавайте параметр `timeout`.

#### <a name="calling-a-third-party-server-from-code-q3"></a> Можно ли getFromScript выполнить кроном?

Конечно!

#### <a name="calling-a-third-party-server-from-code-q4"></a> Можно ли увидеть ошибку, если код действия выполняется кроном и сторонний сервер не ответил?

Да, для это надо код крона обернуть в [tryCatch][288] и в секцию `catch` написать действие, которое нужно совершить если код крона завершился с ошибкой — например отправить админу нотификацию:

```totum

=send_admin: set(table: 'table'; field: 'h_data' = $script)

	// Запрос без параметров

	script: getFromScript(uri: "https://example.host/api/uri/"; timeout: 30)

send_admin: insert(table: 'notifications'; field: 'code' = "admin_text"; field: 'active_dt_from' = $#ndt; field: 'title' = "Ошибка крона"; field: 'user_id' = 1; field: 'vars' = json`["text":$#error]`; field: 'active' = true)

```

В этом примере использован синтаксический вызов [tryCatch][288] — текст ошибки в этом случае может быть получен из переменной `$#error`.

Этот пример - для понимания как можно обработать ошибки крона, если вам нужно другое поведение. Сейчас в Totum уже реализованы в случае ошибки крона автоматическая отправка письма на email, указанный в Conf.php, и нотификации пользователю admin.

#### <a name="calling-a-third-party-server-from-code-q5"></a> Если в процессе выполнения кода обращение к вызову стороннего сервера идет несколько раз, то как сделать кэширование в рамках выполнения кода?

Используется стандартное фиксирование строки тильдой — `~`.

#### <a name="calling-a-third-party-server-from-code-q6"></a> Пример: пример организации отправки смс

Достаточно часто прямой вызов стороннего сервера в процессе выполнения каких-то действий внутри Тотум является плохой практикой, тк задержка ответа стороннего сервера будет тормозить действие в системе.

В этом случае делается асинхронный механизм отправки. Например, если нам по условиям нужно отправить клиенту СМС, то:

1. Делаем таблицу с СМС.

2. Каждая строчка в этой таблице будет отдельным СМС.

3. Когда выполняется действие мы записывает строку в эту таблицу с текстом и телефоном получателя.

4. Код вызова стороннего сервера ставим в `чекбокс` на изменение.

5. Ставим галочки кроном раз в 1-5 минут.

Таким образом если сервер API СМСок или Телеграм-бота отрубится, то действия внутри Тотум будет продолжать происходить без тормозов.

## <a name="receiving-external-requests-to-totum"></a> Прием внешних запросов к тотум

### <a name="receiving-external-requests-to-totum-qa"></a> Вопросы и ответы

#### <a name="receiving-external-requests-to-totum-q1"></a> Как отправить запрос к Тотум по пути?

Например у вас есть сторонний сервер, которому нужно передать параметры и получить ответ от Тотума.

Лучшим решением для этого являются [remotes][436] — втроенные api-микросервисы на Totum.

Самый простой вариант — создаем строку в таблице `Системные таблицы` —> `Основные` —> `API` —> `Remotes`.

1. Задаем `name` + `remotes_user` + `code`:

	```totum
	=: selectRowList(table: 'users'; field: 'login'; field: 'fio')

	```

2. В `return` ставим вариант `json` и включаем `on_off`.

3. Теперь если мы в браузере откроем `https://HOST.TOTUM/Remotes/NAME_ACTION` — то увидим список всех пользователей системы в `json`-формате.

#### <a name="receiving-external-requests-to-totum-q2"></a> Можно ли написать обработчик и сборщик информации на тотум-коде?

Да, код в `code` в `remotes` пишется именно на `totum-code`.

#### <a name="receiving-external-requests-to-totum-q3"></a> Каким образом можно передать данные в таком запросе и как их получить в тотум-коде?

В запросе к Тотуму могут быть переданы `GET` или `POST` параметры.

Внутри кода в `code` параметры запроса могут быть получены через переменные:

+ `$#get` — `row` из переменных, переданных в GET.

+ `$#post` — `row` из переменных, переданных в POST при использовании `application/x-www-form-urlencoded` или `multipart/form-data` в заголовке `Content-Type` запроса `HTTP`.

+ `$#input` — строка или `null` — необработанные данные из тела запроса при передаче `POST` в `raw-data`.

+ `$#headers` — row из заголовков HTTP-запроса.

Обратите внимание, что если параметры передаются размеченными в GET или POST (не в `raw-data`), то они придут в код Тотума уже в формате Тотума.

Те если у вас запрос к ремоуту содержит `POST` с параметрами `key = zzGhdy` и `name = Alexey`, то что бы найти такую запись в таблице `table` по `key`:

```totum

=: select(table: 'table'; field: 'summ'; where: 'key' = $#post[key])

```

Если же запрос пришел в `raw-data` и вы получили его при помощи `$#input` то это будет просто строка, которую вам нужно будет разобрать на части.

#### <a name="receiving-external-requests-to-totum-q4"></a> Какие есть варианты кодирования ответа?

+ `success/error` — возвращается `success` либо `error` если в процессе выполнения были ошибки. Текст ошибки не выводится.

+ `json` — ответ скрипта переводится в JSON. Если возникла ошибка, возвращается `{"error":"Текст ошибки"}`.

+ `строка` — ответ скрипта возвращается в тело ответа без обработки.

+ `headers + body` — в ответе скрипта ожидается `row` с ключами `headers` — отправляются в качестве HTTP-заголовков ответа и `body` — выводится в тело ответа.

#### <a name="receiving-external-requests-to-totum-q5"></a> Можно ли протестировать remote не отправляя запрос?

Да, в таблице `remotes` есть 4 поля: `get`, `post`, `input`, `data` и кнопка `check_remote`.

`get`, `post`, `input` и `data` — соответсвуют переменным. Про `data` расскажем чуть позже.

При исполнении кода по кнопке `check_remote` он выполнится от того пользователя под которм нажимаетсякнопка, а не под пользователем `remotes_user`.

#### <a name="receiving-external-requests-to-totum-q6"></a> Можно ли выполнить действие в тотум при получении такого запроса?

Конечно. `Code` — это код-действия. Например если мы хотим создать строку и записать в поле `data` пришедший `POST`:

```totum

=: insert(table: 'table'; field: 'data' = $#post)

```

#### <a name="receiving-external-requests-to-totum-q7"></a> От какого пользователя в этом случае будет сделана запись об изменении при включенных логах?

От пользователя указанного в `remotes_user`.

## <a name="authorization-in-remouts"></a> Авторизация в remotes

### <a name="authorization-in-remouts-qa"></a> Вопросы и ответы

#### <a name="authorization-in-remouts-q1"></a> Пример: пример с токеном

Когда вы сделали `remote`, то любой, кто знает путь может к нему обратится и выполнить запрос или действие. Но достаточно часто это требуется ограничивать только доверенными скриптами.

Первый и самый простой способ — это токен.

При `POST` запросе вы одним из параметров передаете `"token" = "TOKEN_VALUE"` и проверяете его в коде `remote`:

```totum

=: if(condition: $#post[token] = "TOKEN_VALUE"; then: $select)

select: select(table: 'table'; field: 'summ'; where: 'key' = $#post[key])

```

В этом случае если сторонний сервер в `POST` не передаст `token` или передаст неверный `token`, то он не получит ответа.

#### <a name="authorization-in-remouts-q2"></a> Пример: пример с API пользователями

Второй подход чуть сложнее, но имеет дополнительное преимущество.

Дело в том, что при базовой настройке если у вас несколько разных ремоутов, то обращение к ним идет по разным `url`.

Использование `API-user` позволяет отправлять запрос на один адрес с указанием к какому ремоуту он относится в теле запроса.

1. Что бы это работало нам нужно создать пользователя с признаком `API`.

2. Этот API-пользователь должен быть выбран в поле `api_user` в `remotes`.

3. Обращение идет в `POST` оформленному как `raw-data` по одному адресу  `http(s)://domain.zone/Json/`.

В тело запроса передается `JSON` содержащий секцию авторизации и секцию `remotes`:

```json
{
"auth": {
    "login": "json",
    "password": "1111"
  },
"remotes": [
    {"name":"remote1", "data": {"var1": 1, "var2": [1,2,3]}},
    {"name":"remote2", "data": {"var1": 2, "var2": [3,2,5]}}
  ]
}

```

Таким образом может быть вызвано несколько ремоутов одновременно. Они будут выполнены в порядке в котором они переданы в запросе.

Когда вызов ремоута идет через `API-JSON` интерфейс — то переменные, которые передаются в ремоут можно получить в переменной `$#data`!

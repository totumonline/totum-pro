# List-unsubscribe

Настраивается в таблице `ttm__list_unsubscribe`:

![](https://totum.totum.online/fls/365_626_file.png)

+ **h_header** — добавляет заголовок с `list-unsubscribe` в отправляемые письма, содержищий шифрованную ссылку, переход по которой сразу добавит `email` получателя в блокированные в этой таблице.

+ **h_link** — добавляет ссылку `unsubscribe` в `body` письма. Перейдя по этой ссылке пользователь увидит кнопку `unsubscribe`, нажатие на которую приведет к попаданию его `email` в список блокированных.

+ **h_enable** — проверять ли `email` в списке блокированных в текущей таблице при отправке письма. Если активно, то письмо на блокированный адрес отправлено не будет.

> Наличие заголовка и ссылки уменьшает процент попадания письма в Спам, но в некоторых случаях наличие именно заголовка `list-unsubscribe` может приводить к попаданию письма в папку Рассылки/Промоакции или подобные.

Эти же настройки могут быть определены через `Conf.php` (в том случае, когда и настройки SMTP заданы через Conf) — тогда в этой таблице они перестанут действовать.

Для включения через `Conf.php` открываем его:

```
nano /home/totum/totum-pro/Conf.php
```

Добавляем в блок `protected $SmtpData` параметры `enablelistunsubscribe` и `blockhiddencopy`:

```
protected $SmtpData = [
'host' => 'YOUR_SMTP_HOST_HERE',
'port' => 'YOUR_SMTP_PORT_HERE',
'login' => 'YOUR_SMTP_LOGIN_HERE',
'pass' => 'YOUR_SMTP_PASS_HERE',

'enablelistunsubscribe' => true,
'blockhiddencopy' => true
];
```

+ **enablelistunsubscribe** — включает для всех схем `h_header` и `h_link`.

+ **blockhiddencopy** — блокирует для всех схем параметр `hiddencopy` функции [emailSend][314].
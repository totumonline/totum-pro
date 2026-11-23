# Superlang

## <a name="sl-enabling"></a> Подключение

Подключаем superlang в `Conf.php`:

```
nano /home/totum/totum-pro/Conf.php
```

Добавляем после строки с основным языком `const LANG = "en";`:

```
const isSuperlang = true; // - добавить в Conf.php
```

Обязательно после этого выполнить и корневой папки установки:

```
bin/totum schemas-update
```

В таблице settings в поле `h_pro_langs` указать список из доступный языков:

```
[
  "en",
  "es"
]
```

Доступные варианты: `en`, `ru`, `es`, `de`.

> Пока что не работает для [анонимных таблиц][503] и [форм][504].


## <a name="sl-change-lang"></a> Переключение между языками

Для Создателя доступен технический Селектор, который имеет нейстральный режим в котором видны ключи перевода: 

<img src="https://totum.totum.online/fls/365_703_file.png" style="max-width: 400px">

Для пользователя переключение между языками происходит из пользовательского меню:

<img src="https://totum.totum.online/fls/365_704_file.png" style="max-width: 310px">

Возможно предустановить язык для пользователя и заблокировать его изменение в таблице users:

<img src="https://totum.totum.online/fls/365_705_file.png" style="max-width: 650px">

+ **ttm__langs** — указывается двухбуквенный код из достпных языков системы. Если пустой, то используется язык по умолчанию.

+ **ttm__block_lang** — блокирует изменение языка для пользователя.

## <a name="sl-translation-keys"></a> Ключи перевода

### Формат ключа

В любом месте системы может быть указан ключ перевода в формате `{[key_name]}` и в таблице `ttm__langs` указываются соответсвия для всех используемых языков.

<img src="https://totum.totum.online/fls/365_681_file.png" style="max-width: 600px">

### Таблица соответствия ключей

![](https://totum.totum.online/fls/365_706_file.png)

### Прямое указание перевода без ключей

Также перевод ключа может быть задан непосредственно в поле или в коде, без использования таблицы ключей в формате 

```
{[en:'Text in english';ru:'Текст на русском']}
```

<img src="https://totum.totum.online/fls/365_707_file.png" style="max-width: 600px">

### Ключи и прямое указание в кодах

```totum
f1=: setFormat(condition: $#nfv = ""; text: "{[en:'Empty';es:'Está vacío']}")

f2=: setFormat(condition: $#nfv = 3; comment: "{[some_key_from_table]}")
```
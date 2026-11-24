# Выполнения кода действия по расписанию

## <a name="general-cron"></a> Общее

### <a name="general-cron-qa"></a> Вопросы и ответы

#### <a name="general-cron-q1"></a> Какие условия должны быть выполнены, что бы выполнился код по расписанию?

Подробно [про кроны в документации][430].

Должен быть заполнен `code` и включен `status` в таблице `Системные таблицы` —> `Основные` —> `Настройки и крон` —> `Кроны`.

#### <a name="general-cron-q2"></a> Код из какого поля выполняется по расписанию?

Из поля `code`.

#### <a name="general-cron-q3"></a> С какой периодичностью будет запускаться задача, если все временные поля пустые?

1 раз в минуту.

#### <a name="general-cron-q4"></a> С какой периодичностью и в какой момент будет запускаться задача если в поле час указано 0, а все остальные поля пустые?

С 0 часов до 1 часа каждую минуту.

#### <a name="general-cron-q5"></a> Что должно быть указано во временных полях, что бы запустить задачу один раз в день в 11 часов вечера?

Минуты = 0, Часы = 23

#### <a name="general-cron-q6"></a> Как выполнить код по расписанию вручную?

По кнопке `do_it_now`.

#### <a name="general-cron-q7"></a> Какие функции нельзя использовать в кодах по расписанию?

Не используйте функции `linkTo...` тк они открывают окна!

#### <a name="general-cron-q8"></a> В чем проблема с linkToScript и что нужно использовать вместо нее?

[linkToScript][306] открывает окно и не работает в кронах. Вместо  используется [getFromScript][312].

#### <a name="general-cron-q9"></a> Как проверить, работает ли код по расписанию?

Поставьте в `code` и включите крон в строке, в которой все временные интервалы пустые:

```totum

=: set(table: $#ntn; field: 'status' = false; where: 'id' = #id)

```
При работающих кронах в течении минуты галочка активности должна быть снята.

## <a name="sequencing-and-superimposition-of-neglected-crowns"></a> Последовательность и наложение запущенных кронов

### <a name="sequencing-and-superimposition-of-neglected-crowns-qa"></a> Вопросы и ответы

#### <a name="sequencing-and-superimposition-of-neglected-crowns-q1"></a> От какого пользователя запускается задача по расписанию?

От пользователя `cron`.

#### <a name="sequencing-and-superimposition-of-neglected-crowns-q2"></a> Если два крона запускаются в одно и тоже время, то они будут запущены последовательно на одном ядре процессора или одновременно на разных (при их наличии)?

На разных — в разных процессах.

#### <a name="sequencing-and-superimposition-of-neglected-crowns-q3"></a> Если в одно и тоже время запускается больше задач, чем доступно процессорных ядер, то в каком порядке они будут выполнены?

В произвольном порядке. Не надо расчитывать на порядок выполнения кронов в одном тайм-слоте.

#### <a name="sequencing-and-superimposition-of-neglected-crowns-q4"></a> Таймаут на исполнение у кронов такой же, как и у остальных скриптов?

Да. Устанавливается в `Conf.php` в корневой папке установки.

#### <a name="sequencing-and-superimposition-of-neglected-crowns-q5"></a> Если крон выпоняется дольше, чем интервал запуска, то к чему это приведет?

Крон следующего интервала наложится на предыдущий.

#### <a name="sequencing-and-superimposition-of-neglected-crowns-q6"></a> Пример: Пример использования глобальных переменных

Для того, чтобы исключить наложение крона на незавершенный предыдущий, используется комбинация глобальных переменных и функции [globVar][544] и [tryCatch][288].

Общий подход такой:

1. Когда крон стартует, он записывает в `глобальную переменную` контрольное значение.

2. Когда завершается — снимает это контрольное значение из переменной.

	+ Если завершается с ошибкой — то тоже снимает.

3. Когда стартует следующий по тайм-слоту крон — он проверяет переменную. Если там есть блокирующее значение, то завершается. Если переменная пустая, то записывает контрольное значение и начинает выполняться:

```totum

=catch: if(condition: $var0 = 0; condition: $control[value] != true; then: $start; else: $control_time)

	var0: var(name: "inner"; value: 0)

	~control: globVar(name: "contol_crone"; block: 10; date: true)

	control_time: if(condition: $dateadd < $#ndt; then: $start)
		dateadd: dateAdd(date: $df; minutes: 10; format: "Y-m-d H:i")
			df: strPart(str: $control[date]; length: 16)

start: while(preaction: $set_control; preaction: $var1; action: "...."; postaction: $clear_control)

	set_control: globVar(name: "contol_crone"; value: true)

	var1: var(name: "inner"; value: 1)

	clear_control: globVar(name: "contol_crone"; value: false)

catch: if(condition: $#inner = 1; then: $clearGlob)
	clearGlob: globVar(name: "contol_crone"; value: false)

```

Запись [globVar][544] идет вне транзакционной модели, поэтому для того, чтобы переменная не зависла при **ошибке** мы ее ставим в `false` при помощи [tryCatch][288]!

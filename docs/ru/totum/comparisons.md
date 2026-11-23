# Таблица сравнений

## <a name="part-4"></a>Правила сравнения

### <a name="sx-compare-1"></a>Простые сравнения

[Числа][5] сравниваются как числа. [Строки][6] как строки.

При сравнении чисел `12` `>` `2`.

При сравнении строк `12` `<` `2`. 

[Строка][6] с [числом][5] сравнивается как:

+ Если строка состоит из числа (разделитель дроби `.`, без пробелов) то как числа.

+ Во всех остальных случаях как строки.

[Булевы][8] `true/false` обрабатываются как строки.

[Пустая строка][5] равна отсутствию значения [null][5].

Число `0` не равно [пустой строке][5] и не равно пустому значению [null][5].

### <a name="sx-compare-2"></a>Сравнения списков

По условию `=` списки сравниваются по **пересечению**.

**Сравнение одинарного значения и списка производится по вхождению значения в список.**

Если вы хотите сравнить список полностью с учетом **порядка элементов** используйте `==`.

Списки не сравниваются по условиям `>` и `<`.

Списки внутри списка сравниваются как `==` (полностью равны).

[Пустая строка][5] равна `=` [пустому списку][5] `$#lc` и списку с пустой строкой `[1,"",2,3]`.

[Пустая строка][5] не полностью равна `==` пустому списку `$#lc`.

#### Включает

Если вы хотите получить `true/false` на вопрос включает ли список определенные значения, то это делается через полное сравнение с [listCross][263]:

```totum
example: if(condition: $list_1 == $list_cross; then: true; else: false)

list_cross: listCross(list: $list_1; list: $list_2)

list_1: listCreate(item: "1"; item: "3")

list_2: listCreate(item: "1"; item: "2"; item: "3"; item: "4")
```

### <a name="sx-compare-3"></a>Сравнения массивов

Ассоциированные массивы сравниваются по пересечению значений ключей.

Если надо сравнить все значения всех ключей **без учета порядка** используется `==`.

Массивы сравниваются с [отдельными значениями][245] всегда с результатом `false`.

Массивы не сравниваются `>` и `<`.

Списки внутри значений ключей сравниваются как `==` — те по полному соответсвию с учетом порядка.

Пустой массив и [пустой список][5] идентичны `$#lc`.

Массив с упорядоченными числовыми ключами от 0 с шагом 1 является [списком][5]!

### <a name="sx-compare-4"></a>Сравнения списков массивов

Списки массивов сравниваются как [списки][346] по пересечению.

Порядок ключей в массивах не имеет значения, но количество и ключи в массивах должны совпадать.

## <a name="part-1"></a>Простые сравнения

### <a name="sx-compare-5"></a>Две пустые строки

```totum
example1_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: ""
right_eq: ""

// Результат: true
```
```totum
example1_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: ""
right_noteq: ""

// Результат: false
```
```totum
example1_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: ""
right_eq2: ""

// Результат: true
```
```totum
example1_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: ""
right_noteq2: ""

// Результат: false
```
```totum
example1_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: ""
right_gt: ""

// Результат: false
```
```totum
example1_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: ""
right_gte: ""

// Результат: true
```
```totum
example1_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: ""
right_lg: ""
```
// Результат: false

```totum
example1_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: ""
right_lge: ""

// Результат: true
```

### <a name="sx-compare-6"></a>Пустая строка и 0

```totum
example4_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: ""
right_eq: 0

// Результат: false
```
```totum
example4_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: ""
right_noteq: 0

// Результат: true
```
```totum
example4_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: ""
right_eq2: 0

// Результат: false
```
```totum
example4_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: ""
right_noteq2: 0

// Результат: true
```
```totum
example4_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: ""
right_gt: 0

// Результат: false
```
```totum
example4_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: ""
right_gte: 0

// Результат: false
```
```totum
example4_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: ""
right_lg: 0

// Результат: true
```
```totum
example4_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: ""
right_lge: 0

// Результат: true
```

### <a name="sx-compare-7"></a>Ноль и null

```totum
example6_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: 0
right_eq: $null_eq
null_eq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example6_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: 0
right_noteq: $null_noteq
null_noteq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example6_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: 0
right_eq2: $null_eq2
null_eq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example6_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: 0
right_noteq2: $null_noteq2
null_noteq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example6_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: 0
right_gt: $null_gt
null_gt: if(condition: true = false; then: "")

// Результат: true
```
```totum
example6_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: 0
right_gte: $null_gte
null_gte: if(condition: true = false; then: "")

// Результат: true
```
```totum
example6_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: 0
right_lg: $null_lg
null_lg: if(condition: true = false; then: "")

// Результат: false
```
```totum
example6_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: 0
right_lge: $null_lge
null_lge: if(condition: true = false; then: "")

// Результат: false
```

### <a name="sx-compare-8"></a>Пустая строка и null

```totum
example5_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: ""
right_eq: $null_eq
null_eq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example5_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: ""
right_noteq: $null_noteq
null_noteq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example5_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: ""
right_eq2: $null_eq2
null_eq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example5_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: ""
right_noteq2: $null_noteq2
null_noteq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example5_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: ""
right_gt: $null_gt
null_gt: if(condition: true = false; then: "")

// Результат: false
```
```totum
example5_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: ""
right_gte: $null_gte
null_gte: if(condition: true = false; then: "")

// Результат: true
```
```totum
example5_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: ""
right_lg: $null_lg
null_lg: if(condition: true = false; then: "")

// Результат: false
```
```totum
example5_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: ""
right_lge: $null_lge
null_lge: if(condition: true = false; then: "")

// Результат: true
```

### <a name="sx-compare-9"></a>Числа как числа

```totum
example51_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: 1
right_eq: 3

// Результат: false
```
```totum
example51_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: 1
right_noteq: 3

// Результат: true
```
```totum
example51_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: 1
right_eq2: 3

// Результат: false
```
```totum
example51_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: 1
right_noteq2: 3

// Результат: true
```
```totum
example51_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: 1
right_gt: 3

// Результат: false
```
```totum
example51_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: 1
right_gte: 3

// Результат: false
```
```totum
example51_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: 1
right_lg: 3

// Результат: true
```
```totum
example51_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: 1
right_lge: 3

// Результат: true
```

### <a name="sx-compare-10"></a>Два одинаковых числа

> Две одинаковые строки аналогично.

```totum
example2_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: 0
right_eq: 0

// Результат: true
```
```totum
example2_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: 0
right_noteq: 0

// Результат: false
```
```totum
example2_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: 0
right_eq2: 0

// Результат: true
```
```totum
example2_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: 0
right_noteq2: 0

// Результат: false
```
```totum
example2_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: 0
right_gt: 0

// Результат: false
```
```totum
example2_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: 0
right_gte: 0

// Результат: true
```
```totum
example2_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: 0
right_lg: 0

// Результат: false
```
```totum
example2_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: 0
right_lge: 0

// Результат: true
```

### <a name="sx-compare-11"></a>Одинаковые число и число как строка

```totum
example3_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: 0
right_eq: "0"

// Результат: true
```
```totum
example3_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: 0
right_noteq: "0"

// Результат: false
```
```totum
example3_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: 0
right_eq2: "0"

// Результат: true
```
```totum
example3_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: 0
right_noteq2: "0"

// Результат: false
```
```totum
example3_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: 0
right_gt: "0"

// Результат: false
```
```totum
example3_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: 0
right_gte: "0"

// Результат: true
```
```totum
example3_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: 0
right_lg: "0"

// Результат: false
```
```totum
example3_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: 0
right_lge: "0"

// Результат: true
```

### <a name="sx-compare-12"></a>Число как строка и другое число

```totum
example7_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: "0"
right_eq: 121212

// Результат: false
```
```totum
example7_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: "0"
right_noteq: 121212

// Результат: true
```
```totum
example7_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: "0"
right_eq2: 121212

// Результат: false
```
```totum
example7_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: "0"
right_noteq2: 121212

// Результат: true
```
```totum
example7_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: "0"
right_gt: 121212

// Результат: false
```
```totum
example7_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: "0"
right_gte: 121212

// Результат: false
```
```totum
example7_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: "0"
right_lg: 121212

// Результат: true
```
```totum
example7_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: "0"
right_lge: 121212

// Результат: true
```

### <a name="sx-compare-13"></a>Строки только из цифр и точки

```totum
example52_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: "100"
right_eq: "3.8"

// Результат: false
```
```totum
example52_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: "100"
right_noteq: "3.8"

// Результат: true
```
```totum
example52_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: "100"
right_eq2: "3.8"

// Результат: false
```
```totum
example52_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: "100"
right_noteq2: "3.8"

// Результат: true
```
```totum
example52_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: "100"
right_gt: "3.8"

// Результат: true
```
```totum
example52_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: "100"
right_gte: "3.8"

// Результат: true
```
```totum
example52_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: "100"
right_lg: "3.8"

// Результат: false
```
```totum
example52_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: "100"
right_lge: "3.8"

// Результат: false
```

### <a name="sx-compare-14"></a>Строка цифры-буквы с числом как строка

> Сравнение с числом аналогично.

```totum
example53_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: "100f"
right_eq: "3.8"

// Результат: false
```
```totum
example53_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: "100f"
right_noteq: "3.8"

// Результат: true
```
```totum
example53_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: "100f"
right_eq2: "3.8"

// Результат: false
```
```totum
example53_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: "100f"
right_noteq2: "3.8"

// Результат: true
```
```totum
example53_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: "100f"
right_gt: "3.8"

// Результат: false
```
```totum
example53_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: "100f"
right_gte: "3.8"

// Результат: false
```
```totum
example53_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: "100f"
right_lg: "3.8"

// Результат: true
```
```totum
example53_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: "100f"
right_lge: "3.8"

// Результат: true
```

### <a name="sx-compare-15"></a>Булево и булево

> Для `false` аналогично!

```totum
example17_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: true
right_eq: true

// Результат: true
```
```totum
example17_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: true
right_noteq: true

// Результат: false
```
```totum
example17_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: true
right_eq2: true

// Результат: true
```
```totum
example17_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: true
right_noteq2: true

// Результат: false
```
```totum
example17_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: true
right_gt: true

// Результат: false
```
```totum
example17_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: true
right_gte: true

// Результат: true
```
```totum
example17_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: true
right_lg: true

// Результат: false
```
```totum
example17_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: true
right_lge: true

// Результат: true
```

### <a name="sx-compare-35"></a>Булево и строка true

> Для `false` аналогично!

```totum
example18_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: true
right_eq: "true"

// Результат: true
```
```totum
example18_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: true
right_noteq: "true"

// Результат: false
```
```totum
example18_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: true
right_eq2: "true"

// Результат: true
```
```totum
example18_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: true
right_noteq2: "true"

// Результат: false
```
```totum
example18_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: true
right_gt: "true"

// Результат: false
```
```totum
example18_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: true
right_gte: "true"

// Результат: true
```
```totum
example18_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: true
right_lg: "true"

// Результат: false
```
```totum
example18_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: true
right_lge: "true"

// Результат: true
```

### <a name="sx-compare-16"></a>Булево и число 1 как строка

> Для `false` сравнение со строкой 0 аналогично!

```totum
example19_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: true
right_eq: "1"

// Результат: false
```
```totum
example19_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: true
right_noteq: "1"

// Результат: true
```
```totum
example19_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: true
right_eq2: "1"

// Результат: false
```
```totum
example19_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: true
right_noteq2: "1"

// Результат: true
```
```totum
example19_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: true
right_gt: "1"

// Результат: true
```
```totum
example19_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: true
right_gte: "1"

// Результат: true
```
```totum
example19_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: true
right_lg: "1"

// Результат: false
```
```totum
example19_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: true
right_lge: "1"

// Результат: false
```

## <a name="part-2"></a>Списки

### <a name="sx-compare-17"></a>Пустой список и пустой список

```totum
example50_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: $#lc
right_eq: $#lc

// Результат: true
```
```totum
example50_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: $#lc
right_noteq: $#lc

// Результат: false
```
```totum
example50_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: $#lc
right_eq2: $#lc

// Результат: true
```
```totum
example50_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: $#lc
right_noteq2: $#lc

// Результат: false
```
```totum
example50_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: $#lc
right_gt: $#lc

// Результат: —
```
```totum
example50_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: $#lc
right_gte: $#lc

// Результат: —
```
```totum
example50_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: $#lc
right_lg: $#lc

// Результат: —
```
```totum
example50_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: $#lc
right_lge: $#lc

// Результат: —
```

### <a name="sx-compare-18"></a>Пустой список и пустая строка

```totum
example42_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: $#lc
right_eq: ""

// Результат: true
```
```totum
example42_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: $#lc
right_noteq: ""

// Результат: false
```
```totum
example42_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: $#lc
right_eq2: ""

// Результат: false
```
```totum
example42_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: $#lc
right_noteq2: ""

// Результат: true
```
```totum
example42_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: $#lc
right_gt: ""

// Результат: —
```
```totum
example42_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: $#lc
right_gte: ""

// Результат: —
```
```totum
example42_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: $#lc
right_lg: ""

// Результат: —
```
```totum
example42_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: $#lc
right_lge: ""

// Результат: —
```

### <a name="sx-compare-19"></a>Список без null и null

```totum
example61_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listcreate(item: 0; item: 1; item: 2; item: 3)
right_eq: $null_eq
null_eq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example61_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listcreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: $null_noteq
null_noteq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example61_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listcreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: $null_eq2
null_eq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example61_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listcreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: $null_noteq2
null_noteq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example61_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listcreate(item: 0; item: 1; item: 2; item: 3)
right_gt: $null_gt
null_gt: if(condition: true = false; then: "")

// Результат: —
```
```totum
example61_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listcreate(item: 0; item: 1; item: 2; item: 3)
right_gte: $null_gte
null_gte: if(condition: true = false; then: "")

// Результат: —
```
```totum
example61_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listcreate(item: 0; item: 1; item: 2; item: 3)
right_lg: $null_lg
null_lg: if(condition: true = false; then: "")

// Результат: —
```
```totum
example61_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listcreate(item: 0; item: 1; item: 2; item: 3)
right_lge: $null_lge
null_lge: if(condition: true = false; then: "")

// Результат: —
```

### <a name="sx-compare-20"></a>Список с null и null

```totum
example16_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: $null_eq; item: 1; item: 2; item: 3)
right_eq: $null_eq
null_eq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example16_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: $null; item: 1; item: 2; item: 3)
right_noteq: $null_noteq_0
null_noteq_0: if(condition: true = false; then: "")


// Результат: false
```
```totum
example16_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: $null; item: 1; item: 2; item: 3)
right_eq2: $null_noteq_1
null_noteq_1: if(condition: true = false; then: "")


// Результат: false
```
```totum
example16_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: $null_noteq2; item: 1; item: 2; item: 3)
right_noteq2: $null_noteq_2
null_noteq_2: if(condition: true = false; then: "")


// Результат: true
```
```totum
example16_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: $null_gt; item: 1; item: 2; item: 3)
right_gt: $null_gt
null_gt: if(condition: true = false; then: "")


// Результат: —
```
```totum
example16_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: $null_gte; item: 1; item: 2; item: 3)
right_gte: $null_gte
null_gte: if(condition: true = false; then: "")


// Результат: —
```
```totum
example16_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: $null_lg; item: 1; item: 2; item: 3)
right_lg: $null_lg
null_lg: if(condition: true = false; then: "")


// Результат: —
```
```totum
example16_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: $null_lge; item: 1; item: 2; item: 3)
right_lge: $null_lge
null_lge: if(condition: true = false; then: "")


// Результат: —
```

### <a name="sx-compare-21"></a>Список с null и 0

```totum
example39_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: $null_eq; item: 1; item: 2; item: 3)
right_eq: 0
null_eq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example39_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: $null_noteq; item: 1; item: 2; item: 3)
right_noteq: 0
null_noteq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example39_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: $null_eq2; item: 1; item: 2; item: 3)
right_eq2: 0
null_eq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example39_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: $null_noteq2; item: 1; item: 2; item: 3)
right_noteq2: 0
null_noteq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example39_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: $null_gt; item: 1; item: 2; item: 3)
right_gt: 0
null_gt: if(condition: true = false; then: "")

// Результат: —
```
```totum
example39_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: $null_gte; item: 1; item: 2; item: 3)
right_gte: 0
null_gte: if(condition: true = false; then: "")

// Результат: —
```
```totum
example39_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: $null_lg; item: 1; item: 2; item: 3)
right_lg: 0
null_lg: if(condition: true = false; then: "")

// Результат: —
```
```totum
example39_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: $null_lge; item: 1; item: 2; item: 3)
right_lge: 0
null_lge: if(condition: true = false; then: "")

// Результат: —
```

### <a name="sx-compare-22"></a>Список с 0 и пустая строка

```totum
example10_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq: ""

// Результат: false
```
```totum
example10_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: ""

// Результат: true
```
```totum
example10_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: ""

// Результат: false
```
```totum
example10_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: ""

// Результат: true
```
```totum
example10_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gt: ""

// Результат: —
```
```totum
example10_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gte: ""

// Результат: —
```
```totum
example10_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lg: ""

// Результат: —
```
```totum
example10_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lge: ""

// Результат: —
```

### <a name="sx-compare-23"></a>Список с 0 и null

```totum
example40_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq: $null_eq
null_eq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example40_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: $null_noteq
null_noteq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example40_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: $null_eq2
null_eq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example40_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: $null_noteq2
null_noteq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example40_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gt: $null_gt
null_gt: if(condition: true = false; then: "")

// Результат: —
```
```totum
example40_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gte: $null_gte
null_gte: if(condition: true = false; then: "")

// Результат: —
```
```totum
example40_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lg: $null_lg
null_lg: if(condition: true = false; then: "")

// Результат: —
```
```totum
example40_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lge: $null_lge
null_lge: if(condition: true = false; then: "")

// Результат: —
```

### <a name="sx-compare-24"></a>Список с false и пустая строка

```totum
example26_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: false; item: true; item: true; item: true)
right_eq: ""

// Результат: false
```
```totum
example26_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: false; item: true; item: true; item: true)
right_noteq: ""

// Результат: true
```
```totum
example26_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: false; item: true; item: true; item: true)
right_eq2: ""

// Результат: false
```
```totum
example26_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: false; item: true; item: true; item: true)
right_noteq2: ""

// Результат: true
```
```totum
example26_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: false; item: true; item: true; item: true)
right_gt: ""

// Результат: —
```
```totum
example26_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: false; item: true; item: true; item: true)
right_gte: ""

// Результат: —
```
```totum
example26_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: false; item: true; item: true; item: true)
right_lg: ""

// Результат: —
```
```totum
example26_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: false; item: true; item: true; item: true)
right_lge: ""

// Результат: —
```

### <a name="sx-compare-25"></a>Список с false и null

```totum
example41_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: false; item: 1; item: 2; item: 3)
right_eq: $null_eq
null_eq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example41_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: false; item: 1; item: 2; item: 3)
right_noteq: $null_noteq
null_noteq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example41_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: false; item: 1; item: 2; item: 3)
right_eq2: $null_eq2
null_eq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example41_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: false; item: 1; item: 2; item: 3)
right_noteq2: $null_noteq2
null_noteq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example41_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: false; item: 1; item: 2; item: 3)
right_gt: $null_gt
null_gt: if(condition: true = false; then: "")

// Результат: —
```
```totum
example41_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: false; item: 1; item: 2; item: 3)
right_gte: $null_gte
null_gte: if(condition: true = false; then: "")

// Результат: —
```
```totum
example41_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: false; item: 1; item: 2; item: 3)
right_lg: $null_lg
null_lg: if(condition: true = false; then: "")

// Результат: —
```
```totum
example41_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: false; item: 1; item: 2; item: 3)
right_lge: $null_lge
null_lge: if(condition: true = false; then: "")

// Результат: —
```

### <a name="sx-compare-26"></a>Список с false и 0

```totum
example38_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: false; item: 1; item: 2; item: 3)
right_eq: 0

// Результат: false
```
```totum
example38_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: false; item: 1; item: 2; item: 3)
right_noteq: 0

// Результат: true
```
```totum
example38_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: false; item: 1; item: 2; item: 3)
right_eq2: 0

// Результат: false
```
```totum
example38_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: false; item: 1; item: 2; item: 3)
right_noteq2: 0

// Результат: true
```
```totum
example_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: false; item: 1; item: 2; item: 3)
right_gt: 0

// Результат: —
```
```totum
example38_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: false; item: 1; item: 2; item: 3)
right_gte: 0

// Результат: —
```
```totum
example38_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: false; item: 1; item: 2; item: 3)
right_lg: 0

// Результат: —
```
```totum
example38_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: false; item: 1; item: 2; item: 3)
right_lge: 0

// Результат: —
```

### <a name="sx-compare-27"></a>Два одинаковых списка

```totum
example28_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: true
```
```totum
example28_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: false
```
```totum
example28_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: true
```
```totum
example28_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: false
```
```totum
example28_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gt: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: —
```
```totum
example28_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gte: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: —
```
```totum
example28_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lg: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: —
```
```totum
example28_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lge: listCreate(item: 0; item: 1; item: 2; item: 3)

// Результат: —
```

### <a name="sx-compare-28"></a>Два списка с одинаковыми элементами

```totum
example43_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: true
```
```totum
example43_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: false
```
```totum
example43_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: false
```
```totum
example43_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: true
```
```totum
example43_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gt: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: —
```
```totum
example43_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gte: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: —
```
```totum
example43_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lg: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: —
```
```totum
example43_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lge: listCreate(item: 1; item: 2; item: 0; item: 3)

// Результат: —
```

### <a name="sx-compare-29"></a>Список из чисел и число как строка


```totum
example15_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq: "0"

// Результат: true
```
```totum
example15_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: "0"

// Результат: false
```
```totum
example15_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: "0"

// Результат: false
```
```totum
example15_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: "0"

// Результат: true
```
```totum
example15_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gt: "0"

// Результат: —
```
```totum
example15_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gte: "0"

// Результат: —
```
```totum
example15_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lg: "0"

// Результат: —
```
```totum
example15_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lge: "0"

// Результат: —
```

### <a name="sx-compare-30"></a>Пересекающиеся списки

```totum
example12_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq: listCreate(item: 1)

// Результат: true
```
```totum
example12_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: listCreate(item: 1)

// Результат: false
```
```totum
example12_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: listCreate(item: 1)

// Результат: false
```
```totum
example12_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: listCreate(item: 1)

// Результат: true
```
```totum
example12_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gt: listCreate(item: 1)

// Результат: —
```
```totum
example12_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gte: listCreate(item: 1)

// Результат: —
```
```totum
example12_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lg: listCreate(item: 1)

// Результат: —
```
```totum
example12_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lge: listCreate(item: 1)

// Результат: —
```

### <a name="sx-compare-31"></a>Список и булевых и булево

```totum
example22_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: false; item: false; item: true; item: true)
right_eq: true

// Результат: true
```
```totum
example22_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: false; item: false; item: true; item: true)
right_noteq: true

// Результат: false
```
```totum
example22_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: false; item: false; item: true; item: true)
right_eq2: true

// Результат: false
```
```totum
example22_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: false; item: false; item: true; item: true)
right_noteq2: true

// Результат: true
```
```totum
example22_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: false; item: false; item: true; item: true)
right_gt: true

// Результат: —
```
```totum
example22_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: false; item: false; item: true; item: true)
right_gte: true

// Результат: —
```
```totum
example22_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: false; item: false; item: true; item: true)
right_lg: true

// Результат: —
```
```totum
example22_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: false; item: false; item: true; item: true)
right_lge: true

// Результат: —
```

### <a name="sx-compare-32"></a>Два списка различающихся 0 и null

```totum
example47_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq: listCreate(item: $null_eq; item: 1; item: 2; item: 3)
null_eq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example47_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq: listCreate(item: $null_noteq; item: 1; item: 2; item: 3)
null_noteq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example47_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_eq2: listCreate(item: $null_eq2; item: 1; item: 2; item: 3)
null_eq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example47_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: 0; item: 1; item: 2; item: 3)
right_noteq2: listCreate(item: $null_noteq2; item: 1; item: 2; item: 3)
null_noteq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example47_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gt: listCreate(item: $null_gt; item: 1; item: 2; item: 3)
null_gt: if(condition: true = false; then: "")

// Результат: —
```
```totum
example47_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: 0; item: 1; item: 2; item: 3)
right_gte: listCreate(item: $null_gte; item: 1; item: 2; item: 3)
null_gte: if(condition: true = false; then: "")

// Результат: —
```
```totum
example47_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lg: listCreate(item: $null_lg; item: 1; item: 2; item: 3)
null_lg: if(condition: true = false; then: "")

// Результат: —
```
```totum
example47_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: 0; item: 1; item: 2; item: 3)
right_lge: listCreate(item: $null_lge; item: 1; item: 2; item: 3)
null_lge: if(condition: true = false; then: "")

// Результат: —
```

### <a name="sx-compare-33"></a>Два списка различающихся пустой строкой и null

```totum
example48_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: listCreate(item: ""; item: 1; item: 2; item: 3)
right_eq: listCreate(item: $null_eq; item: 1; item: 2; item: 3)
null_eq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example48_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: listCreate(item: ""; item: 1; item: 2; item: 3)
right_noteq: listCreate(item: $null_noteq; item: 1; item: 2; item: 3)
null_noteq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example48_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: listCreate(item: ""; item: 1; item: 2; item: 3)
right_eq2: listCreate(item: $null_eq2; item: 1; item: 2; item: 3)
null_eq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example48_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: listCreate(item: ""; item: 1; item: 2; item: 3)
right_noteq2: listCreate(item: $null_noteq2; item: 1; item: 2; item: 3)
null_noteq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example48_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: listCreate(item: ""; item: 1; item: 2; item: 3)
right_gt: listCreate(item: $null_gt; item: 1; item: 2; item: 3)
null_gt: if(condition: true = false; then: "")

// Результат: —
```
```totum
example48_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: listCreate(item: ""; item: 1; item: 2; item: 3)
right_gte: listCreate(item: $null_gte; item: 1; item: 2; item: 3)
null_gte: if(condition: true = false; then: "")

// Результат: —
```
```totum
example48_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: listCreate(item: ""; item: 1; item: 2; item: 3)
right_lg: listCreate(item: $null_lg; item: 1; item: 2; item: 3)
null_lg: if(condition: true = false; then: "")

// Результат: —
```
```totum
example48_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: listCreate(item: ""; item: 1; item: 2; item: 3)
right_lge: listCreate(item: $null_lge; item: 1; item: 2; item: 3)
null_lge: if(condition: true = false; then: "")

// Результат: —
```

## <a name="part-3"></a>Ассоциированные массивы

### <a name="sx-compare-34"></a>Массив c null и пустой список

```totum
example54_eq: if(condition: $left_eq = $right_eq; then: true; else: false)

left_eq: rowCreate(field: "tiraj" = 2; field: "mat" = $null_eq)
right_eq: $#lc
null_eq: if(condition: true = false; then: "")

// Результат: false
```
```totum
example54_noteq: if(condition: $left_noteq != $right_noteq; then: true; else: false)

left_noteq: rowCreate(field: "tiraj" = 2; field: "mat" = $null_noteq)
right_noteq: $#lc
null_noteq: if(condition: true = false; then: "")

// Результат: true
```
```totum
example54_eq2: if(condition: $left_eq2 == $right_eq2; then: true; else: false)

left_eq2: rowCreate(field: "tiraj" = 2; field: "mat" = $null_eq2)
right_eq2: $#lc
null_eq2: if(condition: true = false; then: "")

// Результат: false
```
```totum
example54_noteq2: if(condition: $left_noteq2 !== $right_noteq2; then: true; else: false)

left_noteq2: rowCreate(field: "tiraj" = 2; field: "mat" = $null_noteq2)
right_noteq2: $#lc
null_noteq2: if(condition: true = false; then: "")

// Результат: true
```
```totum
example54_gt: if(condition: $left_gt > $right_gt; then: true; else: false)

left_gt: rowCreate(field: "tiraj" = 2; field: "mat" = $null_gt)
right_gt: $#lc
null_gt: if(condition: true = false; then: "")

// Результат: —
```
```totum
example54_gte: if(condition: $left_gte >= $right_gte; then: true; else: false)

left_gte: rowCreate(field: "tiraj" = 2; field: "mat" = $null_gte)
right_gte: $#lc
null_gte: if(condition: true = false; then: "")

// Результат: —
```
```totum
example54_lg: if(condition: $left_lg < $right_lg; then: true; else: false)

left_lg: rowCreate(field: "tiraj" = 2; field: "mat" = $null_lg)
right_lg: $#lc
null_lg: if(condition: true = false; then: "")

// Результат: —
```
```totum
example54_lge: if(condition: $left_lge <= $right_lge; then: true; else: false)

left_lge: rowCreate(field: "tiraj" = 2; field: "mat" = $nul_lge)
right_lge: $#lc
null_lge: if(condition: true = false; then: "")

// Результат: —
```


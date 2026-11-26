# Код действия таблицы: пересчет затронутых строк

## <a name="pt-recalc-task"></a> Задача

Строки таблицы-источника (картинки, характеристики, SKU) принадлежат строкам другой таблицы (товарам). Когда источник меняется, у владельцев надо обновить [хранимые поля][711] — но только у тех владельцев, чьи строки изменились, и **по одному разу на владельца**, даже если за одно действие изменились десятки строк источника (загрузили 5 картинок, сменили порядок, отредактировали группу строк).

Код действия на каждом поле источника для этого не годится: он срабатывает на каждую строку и каждое поле — один товар пересчитается столько раз, сколько у него изменилось ячеек.

## <a name="pt-recalc-solution"></a> Решение

[Код действия таблицы][489] (`default_action`) выполняется один раз после любого изменения таблицы и получает в [$#changes][11] все, что изменилось: `added`, `deleted`, `restored`, `changed` (`row`: id строки → список измененных полей), `reorderedIds`. Из него собирается список id владельцев, [listUniq][260] оставляет каждого по одному разу, и один [reCalculate][289] пересчитывает их всех:

```totum
a1=: reCalculate(table: 'b2m___items'; field: 'product_images'; field: 'all_images'; where: 'id' = $uniq)

~uniq: listUniq(list: $list_products)

	list_products: selectList(table: $#ntn; field: 'product'; where: 'id' = $list_id)

	list_id: listAdd(list: $#changes[added]; list: $#changes[reorderedIds]; list: $row_keys)
		row_keys: rowKeys(row: $#changes[changed])
```

+ `list_id` — id строк источника, которые добавились, изменились или поменяли порядок.

+ `list_products` — один запрос: владельцы этих строк (колонка `product`).

+ `uniq` — каждый владелец один раз; `~`, потому что строка используется в нескольких действиях кода.

+ `where: 'id' = $uniq` — пересчитываются только эти строки, а не вся таблица.

`reorderedIds` включают туда, где у владельца важен порядок строк источника: картинки товара собираются по `n`, перестановка картинок меняет витрину.

Если владелец — цикл, тем же списком пересчитывают таблицы цикла:

```totum
a2=: reCalculate(table: 'b2m___item'; cycle: $uniq)
```

## <a name="pt-recalc-b2m"></a> Пример из B2M

Один и тот же код действия таблицы стоит во всех таблицах данных товаров — различаются только поля, которые надо пересчитать:

| Таблица | `reCalculate` в `b2m___items` |
|---|---|
| `b2m___product_images` | `product_images`, `all_images` |
| `b2m___product_features` | `features_data`, `features_search`, `features_list`, `show_in_catalog` + пересчет циклов `b2m___item` |
| `b2m___sku` | `catalog_prices`, `price_from`, `search_price`, `discount`, `max_price`, `min_price` |
| `b2m___product_options` | `full_options` |
| `b2m___product_documents` | `files_data` |
| `b2m___reviews` | `reviews`, `questions`, `score`, `reviews_count` |

`$#changes[changed]` используют и чтобы реагировать только на изменение определенных полей. В `b2m___brands` пересчет таблицы первых букв брендов запускается, только если менялось поле `first_letter` или строки удалялись:

```totum
a2=: if(condition: cond`$letters != $#lc || $#changes[deleted] != $#lc`; then: $letters_recalc)

letters: rowKeys(row: $letters_filter)
	letters_filter: listFilter(list: $#changes[changed]; key: "value" = "first_letter")

	letters_recalc: reCalculate(table: 'b2m___uniq_brand_letters')
```

Код действия таблицы годится и для проверок по всей таблице сразу — например, запрет удаления строк поставки, которая уже записана на склад (`b2m___goods_receipt`):

```totum
=: if(condition: #h_status = 2; condition: $#changes[deleted] != $#lc; then: $err)

	err: errorException(text: "Нельзя удалить строки из поставки которая уже записана на склад")
```

## <a name="pt-recalc-deleted"></a> Удаленные строки

Удаленные строки `select` не находит — по `$#changes[deleted]` владельцев не получить. Пересчет владельца при удалении делают в [коде действия][28] колонки привязки на удаление — там есть `#old.product`. Чтобы при удалении многих строк одного владельца не пересчитывать его на каждую строку, используется [процессная переменная][713]:

```totum
dl4=: if(condition: $get_product_id != #old.product; then: $recalc_images_while)
		get_product_id: procVar(name: "img_product_id"; default: "")
	recalc_images_while: while(action: $recalc_product_images; action: $set_product_id)
		recalc_product_images: reCalculate(table: 'b2m___items'; field: 'product_images'; field: 'all_images'; where: 'id' = #old.product)
		set_product_id: procVar(name: "img_product_id"; value: #old.product)
```

Так сделано в `b2m___product_images` (колонка `product`, код действия при удалении; код сокращен).

## <a name="pt-recalc-traps"></a> Ловушки

+ Код действия таблицы срабатывает на **любое** изменение таблицы, триггеров у него нет — держите его легким: сначала дешевые проверки по `$#changes`, тяжелые действия — только по условию.

+ Если в действии изменился хедер, в `$#changes[changed]` есть ключ `params` — `rowKeys` вернет его вместе с id строк. В `where: 'id' = …` он ничего не найдет, но не используйте этот список там, где лишнее значение важно.

+ Без `listUniq` владелец попадает в список столько раз, сколько у него изменилось строк; `reCalculate` с `where` по списку пересчитает его один раз, но пересчет циклов и другие действия по списку — нет.

+ Если код действия таблицы меняет свою же таблицу, ограничивайте это условием по `$#changes` — так сделано в `b2m___set_price_levels`: запись в хедер идет только когда в изменении есть удаленные строки, а не при каждом изменении таблицы.

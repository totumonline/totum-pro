# Автозаполнение таблиц

<div style="padding-top: 20px; padding-bottom: 40px">

<iframe class="medium large" src="https://www.youtube.com/embed/OUihzx5Ai_c" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen width="640" height="360"></iframe>

<iframe class="small" src="https://www.youtube.com/embed/OUihzx5Ai_c" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen width="320" height="180"></iframe>

</div>


Строки таблиц с `табличной` единицей пересчета можно заполнять автоматически по условиям.

Для этого первым по `sort` полем строчной части ставится поле с [name][354] — `insert`.

Это поле должно содержать любой [список][14]. Будут созданы строки в количестве значений списка. В каждую строку в поле `insert` будет подставлено значение из списка. Значения будут размещены в том порядке, в котором они находятся в списке.

Список обязательно должен содержать только уникальные значения, не содержащие [null][5] или ["" (пустую строку)][5].

Если по результатам пересчета список в `insert` изменился и:

+ Добавились новые значения — строки будут добавлены в конец таблицы.

+ Удалились значения:
	
	+ В удаляемой строке отсутствовали изменения — строка будет удалена.
	+ В удаляемой строке были изменения — строка останется, но будет помечена зачеркиванием.
		
+ Изменились значения — в этом случае изменение отрабатывается как совокупность удаления-добавления.
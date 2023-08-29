//! moment.js locale configuration
//! locale : Spanish [es]
//! author : Julio Napurí : https://github.com/julionc
!function(e,a){"object"==typeof exports&&"undefined"!=typeof module&&"function"==typeof require?a(require("../moment")):"function"==typeof define&&define.amd?define(["../moment"],a):a(e.moment)}(this,(function(e){"use strict";
//! moment.js locale configuration
var a="ene._feb._mar._abr._may._jun._jul._ago._sep._oct._nov._dic.".split("_"),o="ene_feb_mar_abr_may_jun_jul_ago_sep_oct_nov_dic".split("_"),i=[/^ene/i,/^feb/i,/^mar/i,/^abr/i,/^may/i,/^jun/i,/^jul/i,/^ago/i,/^sep/i,/^oct/i,/^nov/i,/^dic/i],t=/^(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|ene\.?|feb\.?|mar\.?|abr\.?|may\.?|jun\.?|jul\.?|ago\.?|sep\.?|oct\.?|nov\.?|dic\.?)/i;return e.defineLocale("es",{months:"enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre".split("_"),monthsShort:function(e,i){return e?/-MMM-/.test(i)?o[e.month()]:a[e.month()]:a},monthsRegex:t,monthsShortRegex:t,monthsStrictRegex:/^(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/i,monthsShortStrictRegex:/^(ene\.?|feb\.?|mar\.?|abr\.?|may\.?|jun\.?|jul\.?|ago\.?|sep\.?|oct\.?|nov\.?|dic\.?)/i,monthsParse:i,longMonthsParse:i,shortMonthsParse:i,weekdays:"domingo_lunes_martes_miércoles_jueves_viernes_sábado".split("_"),weekdaysShort:"dom._lun._mar._mié._jue._vie._sáb.".split("_"),weekdaysMin:"do_lu_ma_mi_ju_vi_sá".split("_"),weekdaysParseExact:!0,longDateFormat:{LT:"H:mm",LTS:"H:mm:ss",L:"DD/MM/YYYY",LL:"D [de] MMMM [de] YYYY",LLL:"D [de] MMMM [de] YYYY H:mm",LLLL:"dddd, D [de] MMMM [de] YYYY H:mm"},calendar:{sameDay:function(){return"[hoy a la"+(1!==this.hours()?"s":"")+"] LT"},nextDay:function(){return"[mañana a la"+(1!==this.hours()?"s":"")+"] LT"},nextWeek:function(){return"dddd [a la"+(1!==this.hours()?"s":"")+"] LT"},lastDay:function(){return"[ayer a la"+(1!==this.hours()?"s":"")+"] LT"},lastWeek:function(){return"[el] dddd [pasado a la"+(1!==this.hours()?"s":"")+"] LT"},sameElse:"L"},relativeTime:{future:"en %s",past:"hace %s",s:"unos segundos",ss:"%d segundos",m:"un minuto",mm:"%d minutos",h:"una hora",hh:"%d horas",d:"un día",dd:"%d días",w:"una semana",ww:"%d semanas",M:"un mes",MM:"%d meses",y:"un año",yy:"%d años"},dayOfMonthOrdinalParse:/\d{1,2}º/,ordinal:"%dº",week:{dow:1,doy:4},invalidDate:"Fecha inválida"})})),
/*!
 * Bootstrap-select v1.12.4 (http://silviomoreto.github.io/bootstrap-select)
 *
 * Copyright 2013-2017 bootstrap-select
 * Licensed under MIT (https://github.com/silviomoreto/bootstrap-select/blob/master/LICENSE)
 */
function(e,a){"function"==typeof define&&define.amd?define(["jquery"],(function(e){return a(e)})):"object"==typeof module&&module.exports?module.exports=a(require("jquery")):a(e.jQuery)}(this,(function(e){e.fn.selectpicker.defaults={noneSelectedText:"No hay selección",noneResultsText:"No hay resultados {0}",countSelectedText:"Seleccionados {0} de {1}",maxOptionsText:["Límite alcanzado ({n} {var} max)","Límite del grupo alcanzado({n} {var} max)",["elementos","element"]],multipleSeparator:", ",selectAllText:"Seleccionar Todos",deselectAllText:"Desmarcar Todos"}})),
/*!
* TOTUM LOCALIZATION
* */
App.langs=App.langs||{},App.langs.es={locale:"es-ES",localeDatetimepicker:"es",dateFormat:"DD/MM/YY",dateTimeFormat:"DD/MM/YY HH:mm",timeDateFormatNoYear:"HH:mm DD/MM",filtersExtenders:App.commonFiltersExtenders,search_prepare_function:function(e,a){let o={};return Object.keys(o).forEach(i=>{e=e.toLowerCase().replace(i,o[i]),a&&(a=a.toLowerCase().replace(i,o[i]))}),[e,a]},css:{table:'.pcTable-container .loading-row td {background: url("/imgs/loading_en.png") repeat #fff;}'},modelMethods:{edit:"Editar",checkInsertRow:"Agregar previamente",duplicate:"Duplicar",refresh_rows:"Recalcular filas",loadPage:"Cargar página",getTableData:"Cargar información de la tabla",refresh:"Actualizar datos de la tabla",checkEditRow:"Cálculo previo del panel",saveEditRow:"Guardar panel",save:"Cambiar campo",click:"Presionar botón",selectSourceTableAction:"Llamar al panel",add:"Agregar fila",getEditSelect:"Cargar selección de edición",delete:"Eliminar"},translates:{"Creator-tableEditButtons-default_action":"Acción predeterminada","Creator-tableEditButtons-on_duplicate":"En duplicado","Creator-tableEditButtons-row_format":"Formato de fila","Creator-tableEditButtons-table_format":"Formato de tabla","Load context data":"Cargar datos de contexto adicionales","Close context data":"<b>Cerrar</b> datos de contexto adicionales","Open context data":"<b>Abrir</b> datos de contexto adicionales","Element preview is empty":"La vista previa del elemento está vacía","PATH-TO-DOCUMENTATION":"https://docs.totum.online/","Email for cron notifications":"Correo electrónico para notificaciones de cron",Password:"Contraseña",Login:"Inicio de sesión","Create a user with full access":"Crear un usuario con acceso completo","PostgreSql console utilities":"Utilidades de consola PostgreSql","With console utilities":"Con utilidades de consola","Without console utilities":"Sin utilidades de consola","Database name":"Nombre de la base de datos","Database host":"Host de la base de datos","Setup string":"Cadena de configuración","Row <b>id %s %s</b> is blocked":"La fila <b>id %s %s</b> está bloqueada","Database PostgreSQL":"Base de datos PostgreSQL","Deploy only in the new":"Desplegar solo en lo nuevo","Use the existing":"Usar el existente",Schema:"Esquema","Schema (not public)":"Esquema (no público)","Single installation":"Instalación única","Multiple installation":"Instalación múltiple","The value is not found":"El valor no se encuentra","Edit totumCode in %s":"Editar código Totum en %s","Edit totumCode in value of %s":"Editar código Totum en valor de %s","Recalculate all table rows after changing the field type":"Recalcule todas las filas de la tabla después de cambiar el tipo de campo","Default printing":"Impresión por defecto",Forms:"Formularios","Add form":"Agregar formulario","On type change all field setting will be reset to default. If you want to save this changes — save field and change it's type after that":"Si cambia el tipo, todos los ajustes del campo se restablecerán a sus valores por defecto. Si desea conservar estos cambios, guarde el campo y cambie su tipo después.","On type change all field setting will be reset to saved. If you want to save this changes — save field and change it's type after that":"Si cambia el tipo, todos los ajustes del campo se restablecerán a los guardados. Si desea conservar estos cambios, guarde el campo y cambie su tipo después.","RowList of page/table rows":"RowList de filas de página/tabla",Attention:"Atención","Show columns extra info":"Mostrar información adicional de las columnas","Hide columns extra info":"Ocultar información adicional de las columnas",Edited:"Editado","There is no any active trigger.":"No hay ningún disparador activo.","Your last comment editing":"Edición de su último comentario",Cancel:"Cancelar",Add:"Agregar","Add a branch":"Agregar una rama","Add a row":"Agregar una fila",Save:"Guardar",Load:"Cargar",Open:"Abrir","Open all":"Abrir todo",Close:"Cerrar","Close all":"Cerrar todo","Close the panel":"Cerrar el panel",Apply:"Aplicar","By default":"Por defecto","Show all":"Mostrar todo","Disable code":"Desactivar código","Code disabling":"Desactivación de código",Disable:"Desactivar",Refresh:"Actualizar",Tab:"Pestaña","Create a set":"Crear un conjunto","Hide admin. fields":"Ocultar campos de administrador","Save the fields set":"Guardar el conjunto de campos","Set title":"Establecer título","Upload limit exceeded":"Se ha excedido el límite de carga","In a new tab":"En una nueva pestaña","Expand All":"Expandir todo","Scheme of calculation":"Esquema de cálculo","Select user":"Seleccionar usuario","Select values":"Seleccionar valores",Select:"Seleccionar",Loading:"Cargando","%s elements":"%s elementos","%s el.":"%s el.","Change warning":"Cambiar advertencia","Default sets":"Conjuntos predeterminados",Sets:"Conjuntos","Save as default set":"Guardar como conjunto predeterminado","Click hear to unlock":"Haga clic aquí para desbloquear","Apply to selected":"Aplicar a la selección","Fix the selected":"Fijar la selección","Reset manuals":"Reiniciar manuales","Reset manual":"Reiniciar manualmente","Change in source table":"Cambiar en tabla de origen","Add to source table":"Agregar a tabla de origen","Viewing table settings":"Viendo configuración de tabla","Editing table settings":"Editando configuración de tabla","Viewing table field":"Viendo campo de tabla","Editing table field":"Editando campo de tabla","Viewing <b>%s</b> from table <b>%s</b>":"Viendo <b>%s</b> de la tabla <b>%s</b>","Editing <b>%s</b> from table <b>%s</b>":"Editando <b>%s</b> de la tabla <b>%s</b>","Adding table":"Añadiendo tabla","Adding field":"Añadiendo campo","Adding row to table":"Añadiendo fila a la tabla","Error in %s field":"Error en el campo %s","You can't put the Settings field type in linkToEdit":"No puedes poner el tipo de campo Configuración en linkToEdit",Done:"Hecho","Comments of field":"Comentarios del campo","Editing in the form":"Edición en el formulario","Add comment":"Agregar comentario",Manually:"Manualmente","Action not executed":"Acción no ejecutada","Manually changing the json field":"Cambio manual del campo json","Manually changing the json":"Cambio manual del json","JSON format error":"Error de formato JSON","Fill in by the default settings":"Rellenar con la configuración por defecto","Edit list/json":"Editar lista/json",Order:"Orden",Format:"Formatear",FormatShort:"Formato",Copy:"Copiar","Field <b>%s</b> text":"Texto del campo <b>%s</b>","Field settings":"Configuración de campo","Edit text":"Editar texto",Edit:"Editar",View:"Ver","Adding to the table is forbidden":"No está permitido agregar a la tabla","The field must be entered":"Debe ingresar el campo","The field %s must be entered":"Debe ingresar el campo %s",'Value fails regexp validation: "%s"':'El valor no pasa la validación regex: "%s"',"Change the password":"Cambiar la contraseña","New password":"Nueva contraseña",Selected:"Seleccionado","The data is incomplete. Use the search!":"Los datos están incompletos. ¡Usa la búsqueda!",'Filled "%s" field  error: %s':'Error de campo "%s": %s',"Failed to load data":"No se pudo cargar los datos","Required to save the item for file binding":"Es necesario guardar el elemento para vincular el archivo","Adding file":"Añadir archivo","Adding files":"Añadir archivos","Drag and drop the file here":"Arrastra y suelta el archivo aquí","There must be a number":"Debe haber un número",ApplyShort:"Aplicar",InvertShort:"Invertir",CancelShort:"Cancelar","Field structure error":"Error de estructura del campo","Field %s structure error":"Error de estructura del campo %s","Field <b>%s</b> parameters":"Parámetros del campo <b>%s</b>",Editor:"Editor","ERR!":"¡ERR!",Error:"Error","The field accepts only one file":"El campo solo acepta un archivo","Checking the file with the server":"Comprobando el archivo con el servidor","The file is too large":"El archivo es demasiado grande",Empty:"Vacío","Files form <b>%s</b>":"Formulario de archivos <b>%s</b>","Edit field":"Editar campo","The JSON field content":"Contenido del campo JSON","Choose the field":"Seleccionar el campo","Remove from the filter":"Eliminar del filtro","Add to the filter":"Agregar al filtro",Simple:"Simple","Calculated in the cycle":"Calculado en el ciclo",Calculated:"Calculado",Temporary:"Temporal",Cycles:"Ciclos",Code:"Código","Action code":"Código de acción",ActionShort:"Acc.",SelectShort:"Sel.",Formating:"Formateo",Selects:"Selecciones","Fields calculation time":"Tiempo de cálculo de campos","Send password to email":"Enviar contraseña por correo electrónico","Register and send password to email":"Registrarse y enviar contraseña por correo electrónico",Registration:"Registro","Service is optimized for browsers Chrome, Safari, Yandex, FireFox latest versions":"El servicio está optimizado para los navegadores Chrome, Safari, Yandex, FireFox últimas versiones","I still want to see it":"Aún quiero verlo","Apply and close":"Aplicar y cerrar","Shelve all":"Pausar todo",Shelve:"Pausar",__clock_shelve_panel:'<span class="clocks-na">En</span> <input type="number" step="1" value="10" class="form-control"/> <select class="form-control"><option  selected value="1">minutos</option><option value="2">horas</option><option value="3">días</option></select>',"Calculated value":"Valor calculado","Same as calculated":"Igual al calculado","Show logs":"Mostrar registros",Debugging:"Depuración","Without highlightning":"Sin resaltado","With code":"Con código","With code only on adding":"Solo con código al agregar","With action code":"Con código de acción","With action code on add":"Con código de acción al agregar","With action code on change":"Con código de acción al cambiar","With action code on delete":"Con código de acción al eliminar","With action code on click":"Con código de acción al hacer clic","With format code":"Con código de formato",Log:"Registro","Calculate log":"Registro de cálculo","Log of field manual changes":"Registro de cambios manuales de campo","Log is empty":"El registro está vacío. Habilita el registro y recarga la página","Operation execution error":"Error al ejecutar la operación","No server connection":"Sin conexión al servidor",export:"exportar",import:"importar",Full:"Completo","Only rows":"Solo filas",Copied:"Copiado","Edit table settings":"Editar configuración de tabla","Open Tables":"Abrir lista de tablas","Open Tables Fields":"Abrir campos de tablas","Creating tables versions":"Creando versiones de tablas","Changing versions of cycle tables":"Cambiando las versiones de las tablas de ciclo",Restore:"Restaurar",Restoring:"Restauración",Editing:"Edición","Normal mode":"Modo normal"," / Version %s / Cycle %s":"/ Versión %s / Ciclo %s","Add field":"Agregar campo","%s from %s":"%s de %s",Reset:"Restablecer","Comment of the table rows part":"Comentario de la parte de filas de la tabla","Read only":"Solo lectura",Filters:"Filtros",Parameters:"Parámetros","Rows part":"Parte de filas","with id":"con ID","Column footers":"Pies de página de las columnas","Out of column footers":"Fuera de los pies de página de las columnas",Logout:"Cerrar sesión",Print:"Imprimir"," from ":" desde ",Header:"Encabezado",Columns:"Columnas",Footer:"Pie de página",Prefilter:"Prefiltro","Hidden by default":"Oculto por defecto","Fields visibility":"Visibilidad del campo","On adding":"Al agregar","On changing":"Al cambiar","On deleting":"Al eliminar","On click":"Al hacer clic","Adding and editing is disallowed":"La adición y la edición están desactivadas","Adding is disallowed":"La adición está desactivada","Editing is disallowed":"La edición está desactivada","Field %s":"Campo %s",Change:"Cambiar",Duplicate:"Duplicar","Insert after":"Insertar después",Section:"Sección","Change NAME":"Cambiar NAME",Delete:"Eliminar",Deleting:"Eliminando",Hide:"Ocultar",Hiding:"Ocultando","Open the panel":"Abrir el panel",Recalculate:"Recalcular","Recalculate cycle":"Recalcular ciclo",Show:"Mostrar","Field width":"Ancho del campo",Pin:"Anclar",Unpin:"Desanclar","Sort A-Z":"Ordenar de la A a la Z","Sort Z-A":"Ordenar de la Z a la A","Table is empty":"La tabla está vacía","Page is empty":"La página está vacía","Text field editing":"Edición de campo de texto",Documentaion:"Documentación","Delete field %s from table %s?":"¿Eliminar campo %s de la tabla %s?","Deleting field %s from table %s?":"¿Eliminar campo %s de la tabla %s?","Fill in the values for unique fields":"Ingrese los valores para los campos únicos",Operation:"Operación",Value:"Valor","Math operations":"Operaciones matemáticas",Summ:"Suma","Number of numbers":"Número de números",Average:"Promedio",Max:"Máximo",Min:"Mínimo","Non-numeric elements":"Elementos no numéricos","Calculated only by visible rows":"Calculado solo por filas visibles","By current page":"Por página actual","Wait, the table is loading":"Espere, la tabla se está cargando","Add row":"Agregar fila","Field % not found":"Campo %s no encontrado","Section deleting":"Eliminación de sección","Section editing":"Edición de sección","empty list":"lista vacía",date:"fecha","date-time":"fecha-hora","date-time with secongs":"fecha-hora con segundos","user id":"id de usuario","user roles ids":"ids de roles de usuario","table id":"id de tabla","table NAME":"nombre de tabla NAME","temporary table HASH":"HASH de tabla temporal","adding row HASH":"HASH de fila agregada","calcuated table cycle id":"id del ciclo de tabla calculado","field NAME":"nombre del campo NAME","new line":"Nueva línea",tab:"Tabulación","action code action type":"código de acción tipo de acción","the ids of the checked fields":"los ids de los campos marcados","current field value (for selections/actions/formats)":"valor actual del campo (para selecciones/acciones/formatos)","past value of the current field":"valor anterior del campo actual","current host-name":"nombre de host actual","duplicated row id":"id de fila duplicada","Csv-loading question":"Pregunta de carga de archivo CSV","Check matching the structure of the loaded file to the sequence of fields":"Compruebe si la estructura del archivo cargado coincide con la secuencia de campos",Running:"Ejecutándose",Deleted:"Eliminado",Blocked:"Bloqueado","Surely to change?":"¿Seguro que desea cambiar?","Surely to recalculate %s rows?":"¿Seguro que desea recalcular %s filas?","Surely to duplicate %s rows?":"¿Seguro que desea duplicar %s filas?","Surely to recalculate %s cycles?":"¿Seguro que desea recalcular %s ciclos?","Surely to hide %s rows?":"¿Seguro que desea ocultar %s filas?","Surely to delete %s rows?":"¿Seguro que desea eliminar %s filas?","Surely to hide the row?":"¿Seguro que desea ocultar la fila?","Surely to delete the row?":"¿Seguro que desea eliminar la fila?","Surely to restore the row %s?":"¿Seguro que desea restaurar la fila %s?","Surely to restore %s rows?":"¿Seguro que desea restaurar %s filas?","Hiding %s rows":"Ocultando %s filas","Deleting %s rows":"Eliminando %s filas","Hiding the row %s":"Ocultando la fila %s","Deleting the row %s":"Eliminando la fila %s",Recalculating:"Recalculando",Duplicating:"Duplicando",Confirmation:"Confirmación",Reload:"Recargar",All:"Todo","Without hand":"Sin mano","With hand all":"Con mano todo","With hand equals calc":"Con mano igual al cálculo","With hand different":"Con mano diferente","Filtering by current page":"Filtrado por página actual","No rows are selected by the filtering conditions":"No hay filas seleccionadas por las condiciones de filtrado","To operate the order field, reload the table":"Para operar con el campo de orden, recarga la tabla","Rows restore mode. Sorting disabled":"Modo de restauración de filas. La ordenación está desactivada","It is possible to sort only within a category":"Es posible ordenar solo dentro de una categoría","You cannot move the row %s":"No puedes mover la fila %s","The unchecked row should be selected as the anchor for the move":"La fila no marcada debe ser seleccionada como ancla para el movimiento","No data":"Sin datos","Only nested rows can be moved":"Solo se pueden mover filas anidadas","You can only move within one branch":"Solo puedes mover dentro de una rama","Attention, please - this is a temporary table":"Atención, por favor - esta es una tabla temporal","The table was changed by the user <b>%s</b> at <b>%s</b>":"La tabla fue modificada por el usuario <b>%s</b> en <b>%s</b>",treeAddTable:"Agregar tabla",treeAddFolder:"Agregar carpeta/enlace","Tree search":"Búsqueda en árbol","isCreatorSelector-NotCreatorView":"Desactivar capa de administrador","isCreatorSelector-CommonView":"Desactivar vista especial","isCreatorSelector-MobileView":"Cambiar a vista de escritorio","Dbstring is incorrect":"La cadena de base de datos es incorrecta","Create config and upload scheme":"Crear configuración y cargar esquema","Recalculate +":"Recalcular +","Recalculate cycle +":"Recalcular ciclo +","Available in PRO":"Disponible en PRO","In the fields marked with a checkmark, their Code on Addition will be executed when recalculating":"En los campos marcados con una marca de verificación, se ejecutará su Código de adición al volver a calcular","The field accept only following types: %s":"Este campo solo acepta los siguientes tipos: %s",mobileToDesctopWarning:"Este tipo de visualización solo está diseñado para PC con pantallas pequeñas. No lo habilites si tienes un dispositivo móvil, como un teléfono o una tableta.",mobileToDesctopUserWarning:"Hemos detectado el tipo de página automáticamente.  Si nos hemos equivocado, puedes cambiar la vista móvil/escritorio manualmente.  ¡Tienes que estar seguro de la acción que estás realizando!  Si cambias a la vista de escritorio en un dispositivo móvil, ¡la página será defectuosa!","Dark mode":"Modo oscuro","This option works only in PRO.":"Esta opción solo funciona en PRO.","If you enable it and you have files in this field, they stay on the server, but you cannot access them from totum.":"Si lo habilitas y tienes archivos en este campo, permanecerán en el servidor, pero no podrás acceder a ellos desde totum.","This option can be enabled only. You will not be able to turn it off.":"Esta opción solo se puede habilitar. No podrás desactivarla.",Page:"Página",Orientation:"Orientación",Portrate:"Retrato",Landscape:"Paisaje","Excel export":"Exportar a Excel","Copy selected":"Copiar seleccionado","Copy with names":"Copiar con nombres","Excel export with names":"Exportar a Excel con nombres","Xlsx export":"Exportar a Excel",Export:"Exportar","Create PDF":"Crear PDF",Download:"Descargar","CSV-export":"Exportar CSV","CSV-import":"Importar CSV","Adding version":"Añadir versión","Last version will be removed":"Se eliminará la última versión","File %s verions":"Fichero %s verions","versions(%s)":"versiones (%s)","Leave a comment":"Deja un comentario","Last version will be replaced":"Se sobrescribirá la última versión","No, create new version":"No, cree una nueva versión","Rewrite last version by this file":"Sustituya la última versión por este archivo","Rewrite or create?":"¿Sustituir o crear una nueva versión?","File for version must be same type as main one: %s":"El archivo para la versión debe ser del mismo tipo que el archivo principal: %s","Date formats":"Formatos de fecha","Number dectimal delimiter":"Número delimitador dectimal","Excel import":"Importar de Excel","You are in mobile mode - switch to desktop mode to enable the admin layer.":"Estás en modo móvil - cambia a modo escritorio para habilitar la capa de administración.","The panel view is disabled due to exceeding the number of rows in the displayed table":"La vista de panel está desactivada debido a que se ha superado el número de filas de la tabla mostrada.","Surely to recalculate %s cycles with selected fields?":"¿Seguramente recalcular %s ciclos con los campos seleccionados?","Surely to recalculate %s rows with selected fields?":"¿Seguramente recalcular %s filas con los campos seleccionados?","The data is incomplete.":"Los datos están incompletos.",Expand:"Expandir",Compress:"Comprimir","Shared editing":"Edición compartida","Single editing":"Edición de",Viewing:"Ver","Save and close":"Guardar y cerrar","bugFinder-warning":"Las pruebas se realizan en modo no guardado y seguro para la base de datos, pero pueden invocar servicios de terceros y enviar correos electrónicos si esas funciones están implicadas en la cadena de código de la página que se está abriendo.","Enter the path to the table":"Introduzca la ruta a la tabla",Stop:"Stop",Start:"Start","Current user":"Usuario actual","By user":"Usuario","By address":"URL","Time limit, sec.":"Límite, seg.","Main table":"Tabla principal","First pagination page":"Primera página de paginación"}};
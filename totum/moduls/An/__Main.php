<?php

use totum\moduls\Table\TableController;

$isCreatorView = $isCreatorView ?? false;

if (empty($tableConfig)) {
    if (empty($error)) {
        if (!empty($html)) {
            echo '<div id="main-page">' . $html . '</div>';
        }
    }
    return;
}

if ($isCreatorView) {
    $specFuncs = json_encode(TableController::getSpecFunctionsArray($this->Totum), JSON_UNESCAPED_UNICODE);
}
$colors = json_encode(TableController::getColorsArray($this->Totum), JSON_UNESCAPED_UNICODE);

?>

<div id="table"></div>
<script>
    var TableModel = App.models.table(window.location.href, {'updated': <?=($tableConfig['updated'])?><?=($tableConfig['tableRow']['sess_hash'] ?? null) ? ', sess_hash: "' . $tableConfig['tableRow']['sess_hash'] . '"' : ''?>})
</script>
<script>
    let TableConfig = <?=json_encode($tableConfig, JSON_UNESCAPED_UNICODE);?>;

    TableConfig.model = TableModel;
    $(function () {
        App.Colors = <?=$colors?>;
        new App.pcTableMain($('#table'), TableConfig);
    })

    <?php
    if ($isCreatorView){?>
    $(function () {
        App.SpecFuncs = <?=$specFuncs?>;
    })

    <?php }  ?>
</script>

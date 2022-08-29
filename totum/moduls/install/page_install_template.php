<?php

use totum\common\Lang\RU;

?>
<!DOCTYPE html>
<html>
<head lang="ru">
    <link rel="stylesheet"
          type="text/css"
          href="/css/libs.css">
    <script src="/js/libs.js"></script>
    <script>App = {}</script>

    <script src="/js/main.js"></script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/main.css">

    <link rel="shortcut icon" type="image/png" href="/fls/6_favicon.png"/>
    <title>Totum</title>
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1">
    <style>
        body {
            overflow: auto;
        }
    </style>
</head>
<body id="pk">
<noscript>
    <?= $this->translate('To work with the system you need to enable JavaScript in your browser settings') ?>
</noscript>
<div id="big_loading" style="display: none;"><i class="fa fa-cog fa-spin fa-3x"></i></div>

<?php
if (!empty($error)) {
    echo '<div class="panel panel-danger" id="error"><div class="panel-body">' . $error . '</div></div>';
} ?>
<?php
include static::$contentTemplate; ?>

</body>
</html>
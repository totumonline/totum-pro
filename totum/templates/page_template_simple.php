<!DOCTYPE html>
<head lang="ru">
    <script>App = {}</script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/libs.css?v=3710809">
    <script src="/js/libs.js?v=63416c5"></script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/main.css?v=71b98a5">
    <script src="/js/main.js?v=d699a51"></script>
    <script src="/js/i18n/<?= $this->Config->getLang() ?>.js?6"></script>
    <script>App.lang = App.langs["<?= $this->Config->getLang() ?>"]</script>

    <link rel="shortcut icon" type="image/png" href="/fls/6_favicon.png"/>


    <?php
    include dirname(__FILE__) . DIRECTORY_SEPARATOR . '__titles_descriptions.php';
    ?>
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1">

    <?=$this->Config->getThemesCss()?>
</head>
<body id="pk"
      class="lock">
<noscript>
    <?=$this->translate('To work with the system you need to enable JavaScript in your browser settings')?>
</noscript>
<div id="big_loading" style="display: none;"><i class="fa fa-cog fa-spin fa-3x"></i></div>
<script>
    App.fullScreenProcesses.showCog();
</script>
<div class="page_content tree-minifyed">
    <div id="notifies"></div>
    <?php
    if (!empty($error)) {
        echo '<div class="panel panel-danger"><div class="panel-body">' . $error . '</div></div>';
    } ?>
    <?php include static::$contentTemplate; ?>
</div>
<script>
    $(function(){
        App.fullScreenProcesses.hideCog();
    })
</script>
</body>
</html>
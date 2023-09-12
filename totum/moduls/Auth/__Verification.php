<?php

use totum\config\Conf;

?>


<div id="auth_form"
     class="center-block"
     style="">
    <style>
        #top_line, .Tree {
            display: none;
        }

        .page_content {
            margin-left: 0px;
        }

        input.error {
            border-color: red;
        }

        body {
            background: url(/imgs/mailttm.png) no-repeat center center fixed;
            background-size: cover;
        }
    </style>

    <div style="text-align: center; font-size: 30px; padding-bottom: 2vh;padding-top: 2vh;"
         class="login-brand"><?= $schema_name ?> </div>

    <div class="center-block">

        <form method="post"
              id='form'>
            <div><?= $code_sent ?? '' ?></div>
            <?php
            if (isset($seconds)) {
                ?>
                <div class="form-group"><label><?= $this->translate('Secret code') ?>:</label><input type="text"
                                                                                                     name="secret"
                                                                                                     value=""
                                                                                                     class="form-control"
                    /></div>
                <div class="form-group"><input type="submit"
                                               name="login"
                                               value="<?= $this->translate('Log in') ?>"
                                               style="width: auto; padding: 0px 22px;margin-top:4px;"
                                               id="login"
                                               class="form-control"/>

                </div>
                <div class="form-group">
                    <button type="submit"
                            name="resend"
                            value="ok"
                            disabled
                            style="width: auto; padding: 0px 22px;margin-top:4px;"
                            id="resend"
                            class="form-control"><?= $this->translate('You can resend a secret via <span></span> sec') ?>
                    </button>

                </div>
                <?php
            } ?>
        </form>

    </div>

</div>
<script>
    if (App.theme.isDark()) {
        $('body').css('backgroundImage', 'url(/imgs/mailttm_dark.png)');
    }
    let time = <?=$seconds ?? 0?>;
    let resend = $('#resend');
    if (time > 0) {
        const intervalFunc = () => {
            time--;
            if (time) {
                resend.find('span').text(time)
            } else {
                clearInterval(interval);
                resend.html('<?=$this->translate('Resend secret')?> <i class="fa fa-refresh"></i>').prop('disabled', '')
            }
        };
        intervalFunc();
        let interval = setInterval(intervalFunc, 1000)
    } else {
        resend.html('<?=$this->translate('Resend secret')?> <i class="fa fa-refresh"></i>').prop('disabled', '')
    }
</script>
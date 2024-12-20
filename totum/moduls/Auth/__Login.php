<?php

use totum\config\Conf;

?>


<div id="auth_form"
     class="center-block"
     style="display: none;">
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
        <div id="auth_text" style="padding-bottom:10px;"></div>
        <form method="post"
              id='form'>
            <div class="form-group"><label><?= $this->translate('Login/Email') ?>:</label><input type="text"
                                                                                                 name="login"
                                                                                                 value=""
                                                                                                 class="form-control"
                /></div>
            <div class="form-group"><label><?= $this->translate('Password') ?>:</label><input type="password"
                                                                                              name="pass"
                                                                                              class="form-control"/>
            </div>
            <?php
            if ($this->Config->getLDAPSettings('h_ldap_on', null) && $this->Config->getLDAPSettings('h_domain_selector', null) && $this->Config->getLDAPSettings('h_domains_settings', null)) {
                ?>
                <div class="form-group"><label><?= $this->translate('Authorization type') ?>:</label>
                    <select name="type" class="form-control selectpicker" id="auth_type_select">
                        <option value="">Totum</option>
                        <?php
                        if ($this->Config->getLDAPSettings('h_domain_selector', null) === 'ldap') {
                            echo '<option value="1">LDAP</option>';
                        } else {
                            foreach ($this->Config->getLDAPSettings('h_domains_settings', null) as $domain => $_) {
                                echo '<option value="' . $domain . '">' . $domain . '</option>';
                            }
                        } ?>
                    </select>
                    <script>
                        let storageName = "ttmAuthTypeSelect";
                        let select = $('#auth_type_select');
                        if (localStorage.getItem(storageName)) {
                            select.val(localStorage.getItem(storageName))
                        }
                        select.on("change", () => {
                            localStorage.setItem(storageName, select.val())
                        })

                    </script>
                </div>
                <?php
            } ?>

            <div class="form-group"><input type="submit"
                                           value="<?= $this->translate('Log in') ?>"
                                           style="width: auto; padding: 0px 22px;margin-top:4px;"
                                           id="login"
                                           class="form-control"/>

            </div>
        </form>
        <?php
        if ($with_pass_recover) { ?>
            <button
                    style=";margin-top:4px;"
                    id="recover"
                    class="form-control btn btn-default"><?= $this->translate('Send new password to email') ?>
            </button>
            <?php
        } ?>
    </div>
</div>

<script>
    $(function () {

        try {
            LOGINJS();
        } catch (e) {
            $('body').html('<div style="width: 600px; margin: auto; padding-top: 50px; font-size: 16px; text-align: center;" id="comeinBlock">' +
                '<img src="/imgs/start.png" alt="">' +
                '<div style="padding-bottom: 10px;"><?=$this->translate('Service is optimized for desktop browsers Chrome, Safari, Yandex latest versions. It seems that your version of the browser is not supported. Error - for developers: ')?>' + e.toString() + '</div>' +
                '</div>');
        }
    })
</script>
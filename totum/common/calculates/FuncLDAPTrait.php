<?php

namespace totum\common\calculates;

use totum\common\errorException;

trait FuncLDAPTrait
{

    protected function funcPROCheckLDAPConnection($params)
    {
        if (!extension_loaded("ldap")) {
            die('LDAP extension php not enabled');
        }

        $bindFormat = $this->Table->getTotum()->getConfig()->getLDAPSettings('h_bind_format');
        if (empty($bindFormat)) {
            throw new errorException($this->translate('Set the binding format in the LDAP settings table'));
        }
        $connection = $this->Table->getTotum()->getConfig()->getLDAPSettings('connection');

        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['basedn', 'filter']);

    }

}
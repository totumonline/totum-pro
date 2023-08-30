<?php

namespace totum\common\calculates;

use totum\common\errorException;

trait FuncLDAPTrait
{

    protected function funcPROLDAPgetUsers($params)
    {



        $params = $this->getParamsArray($params, [], [], []);
        $this->__checkNotEmptyParams($params, ['basedn', 'filter', 'domain']);

        $connection = $this->Table->getTotum()->getConfig()->getLDAPSettings('connection', $params['domain']);

        if ($params['user'] ?? null) {
            $login = match ($this->Table->getTotum()->getConfig()->getLDAPSettings('h_bind_format', $params['domain'])) {
                'at' => $params['user'] . '@' . $params['domain'],
                'dn' => $params['user'],
                default => throw new \Exception('Не поддерживаемый формат бинда ')
            };
            $r = @ldap_bind($connection, $login, $params['pass']);
            if (!$r) {
                throw new errorException(ldap_error($connection));
            }
        }
        $Conf = $this->Table->getTotum()->getConfig();
        $r = @ldap_search($connection, $params['basedn'], $params['filter'][0],
            [$Conf->getLDAPSettings('h_login_param', $params['domain']), $Conf->getLDAPSettings('h_fio_param', $params['domain']), $Conf->getLDAPSettings('h_email_param', $params['domain'])]
        );

        if (!$r) {
            throw new errorException(ldap_error($connection));
        }
        $users = FuncLDAPTrait::cleanLDAPResult(ldap_get_entries($connection, $r));

        $procUsers = [];
        foreach ($users as $user) {
            $procUser = [];
            foreach ($user as $k => $v) {
                $procUser[mb_strtolower($k, 'utf8')] = $v;
            }
            $procUsers[] = $procUser;
        }
        $users = $procUsers;

        if ($params['withgroups'] ?? false) {
            $groupsSetting = $Conf->getLDAPSettings('h_get_groups', $params['domain']);
            if ($groupsSetting) {
                $groupParam = $Conf->getLDAPSettings('h_group_param', $params['domain']);
                foreach ($users as &$user) {

                    foreach ($groupsSetting as $groupParamName => $groupFilter) {
                        $groupFilter = str_replace('{USER_DN}', $user['dn'], $groupFilter);
                        $r = @ldap_search($connection,
                            $params['basedn'],
                            $groupFilter);
                        if (!$r) {
                            throw new errorException(ldap_error($connection));
                        }

                        $user[$groupParamName] = FuncLDAPTrait::cleanLDAPResult(ldap_get_entries($connection, $r),
                            $groupParam);
                    }

                }
            }
            unset($user);
        }
        return $users;
    }

    static function cleanLDAPResult(array $data, $onlyParam = null)
    {
        unset($data['count']);
        $result = [];
        foreach ($data as $_v) {
            if ($onlyParam) {
                if (!key_exists($onlyParam, $_v)) {
                    foreach ($_v as $k => $_pv) {
                        if (mb_strtolower($k, 'utf8') === mb_strtolower($onlyParam, 'utf8')) {
                            $result[] = is_array($_pv) ? $_pv[0] : $_pv;
                            break;
                        }
                    }
                } else {
                    $result[] = is_array($_v[$onlyParam]) ? $_v[$onlyParam][0] : $_v[$onlyParam];
                }
                continue;
            }
            $row = [];
            unset($_v['count']);
            foreach ($_v as $k => $v) {
                if (!is_numeric($k)) {
                    $row[$k] = mb_convert_encoding(is_array($v) ? $v[0] : $v, 'UTF-8', 'UTF-8');
                }
            }
            $result[] = $row;
        }
        return $result;
    }

}
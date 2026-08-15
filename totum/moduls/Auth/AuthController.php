<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 19.10.16
 * Time: 12:54
 */

namespace totum\moduls\Auth;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateAction;
use totum\common\controllers\interfaceController;
use totum\common\Auth;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\FormatParamsForSelectFromTable;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\OnlyOfficeConnector;
use totum\common\Totum;
use totum\common\User;
use totum\config\Conf;
use totum\tableTypes\aTable;

class AuthController extends interfaceController
{
    public function action(ServerRequestInterface $request)
    {
        die('Path is not available');
    }

    public function actionToken(ServerRequestInterface $request)
    {

        $this->Config->setSessionCookieParams();
        session_start();

        list($_, $_, $_, $token) = explode('/', $request->getUri()->getPath() . '/');
        $data = null;
        if (method_exists($this->Config, 'singleAuthToken')) {
            $data = $this->Config->singleAuthToken($token);
        }

        $this->Totum = new Totum($this->Config, Auth::serviceUserStart($this->Config));

        if (empty($data) && $data !== false) {
            $tokenTable = $this->Totum->getTable('ttm__auth_tokens');
            if ($data = $tokenTable->getByParams((new FormatParamsForSelectFromTable())
                ->field('auth_user')->field('multiple')->field('target')->field('id')
                ->where('token', $token)
                ->where('disabled', false)
                ->where('expire', date('Y-m-d H:i:s'), '>')->params(), 'row')) {
                $data['user'] = $data['auth_user'];
            }
        }

        if (!$data) {
            die($this->translate('Token is not exists or is expired'));
        }
        if (empty($_SESSION['userId'])) {
            $user = Auth::getUserById($this->Config, $data['user']);
            if (in_array(1, $user->getRoles())) {
                die($this->translate('This user have Creator role. He cannot be authorized by a token'));
            }
            if (in_array($user->login, ['service', 'cron', 'anonym'])) {
                die($this->translate('This is a service user. He cannot be authorized by a token'));
            }
            if ($user->interface != 'web') {
                die($this->translate('This is not web user. He cannot be authorized by a token'));
            }

            if (!empty($tokenTable)) {
                $modify = [
                    'last_used_at' => date('Y-m-d H:i')
                ];
                if (!$data['multiple']) {
                    $modify['disabled'] = true;
                }
                $tokenTable->reCalculateFromOvers([
                    'modify' => [$data['id'] => $modify]
                ]);
            }

            $this->Config->getSql()->insert(
                'auth_log',
                [
                    'datetime' => json_encode(['v' => date_create()->format('Y-m-d H:i')])
                    , 'user_ip' => json_encode(['v' => ($_SERVER['REMOTE_ADDR'] ?? null)])
                    , 'login' => json_encode(['v' => $user->login])
                    , 'status' => json_encode(['v' => strval(Auth::$AuthStatuses['TOKEN_AUTH'])])
                ],
                false
            );

        } else {
            $user = Auth::getUserById($this->Config, $_SESSION['userId']);
        }

        $this->Totum = new Totum($this->Config, $user);
        $link = '/';
        if ($data['target']['t'] ?? false) {
            $targetTableRow = $this->Totum->getTableRow($data['target']['t']);
            $link = '/Table/';
            if ($targetTableRow['type'] === 'calcs') {

                $targetTable = $this->Totum->getTable($targetTableRow, $data['target']['c'] ?? 0);
                $tree_node_id = $this->Totum->getTableRow($targetTable->getTableRow()['tree_node_id'])['tree_node_id'];
                $link .= $tree_node_id . '/' . $targetTable->getTableRow()['tree_node_id'] . '/' . $data['target']['c'] . '/' . $targetTable->getTableRow()['id'] . '/';
            } else {
                $targetTable = $this->Totum->getTable($targetTableRow);
                $tree_node_id = $targetTable->getTableRow()['tree_node_id'];
                $link .= $tree_node_id . '/';
                $link .= $targetTable->getTableRow()['id'] . '/';
            }


            if ($data['target']['f'] ?? false) {
                $cripted = Crypt::getCrypted(json_encode($data['target']['f'], JSON_UNESCAPED_UNICODE));
                $q_params['f'] = $cripted;
                $link .= '?' . http_build_query($q_params, '', '&', PHP_QUERY_RFC1738);
            }

        }

        Auth::webInterfaceSetAuth($user->id);
        $this->location($link);
        die;

    }

    public function actionVerification(ServerRequestInterface $request)
    {
        $this->Config->setSessionCookieParams();
        session_start();
        if (!empty($_SESSION['userId'])) {
            $this->location();
            die;
        }
        if (empty($_SESSION['auth_data'])) {
            $this->location('/Auth/Login' . ($_GET['from'] ? '?from=' . urlencode($_GET['from']) : ''));
            die;
        }

        $post = $request->getParsedBody();

        $auth_user = function () {
            $user = Auth::getUserById($this->Config, $_SESSION['auth_data']['id']);
            Auth::isBlockedUserIfTimesOff($_SESSION['auth_data']['login'], null, $this->Config, 'write', Auth::$AuthStatuses['OK']);
            Auth::webInterfaceSetAuth($user->getId());

            $baseDir = $this->Config->getBaseDir();

            if (in_array(1, $user->getRoles())) {
                $schema = is_callable([$this->Config, 'setHostSchema']) ? '"' . $this->Config->getSchema() . '"' : '';
                shell_exec("cd {$baseDir} && bin/totum check-service-notifications {$schema} &");
            }

            unset($_SESSION['auth_data']);

            $this->location($_GET['from'] && $_GET['from'] !== '/' ? $_GET['from'] : $user->getUserStartPath(),
                !key_exists('from', $_GET));
        };

        if (in_array($_SESSION['auth_data']['login'], $this->Config->loginsWithoutTwoFactorAuth)) {
            $auth_user();
        }

        if (Auth::isUserBlocked($_SESSION['auth_data']['login'], $this->Config)) {
            $this->location('/Auth/Login' . ($_GET['from'] ? '?from=' . urlencode($_GET['from']) : ''));
        }


        if (!empty($post['login']) && !empty($_SESSION['auth_data']['secret'])) {
            if ($_SESSION['auth_data']['secret']['code'] != $post['secret'] ?? null) {
                $error = $this->translate('Wrong secret code');
                $_SESSION['auth_secret_anti_bruteforce'] = $_SESSION['auth_secret_anti_bruteforce'] ?? 0;
                $_SESSION['auth_secret_anti_bruteforce']++;

            } elseif ($_SESSION['auth_data']['secret']['time'] < (time() - $this->Config->getSettings('h_pro_auth_live_time') * 60)) {
                $error = $this->translate('Secret code expired');
                if (Auth::isBlockedUserIfTimesOff($_SESSION['auth_data']['login'], Auth::$AuthStatuses['WRONG_PASSWORD'], $this->Config) === Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION']) {
                    $this->location('/Auth/Login' . ($_GET['from'] ? '?from=' . urlencode($_GET['from']) : ''));
                }
            } else {
                $auth_user();
            }
        }
        if (empty($_SESSION['auth_data']['secret'])
            || (!empty($post['resend']) && $_SESSION['auth_data']['secret']['time'] < (time() - $this->Config->getSettings('h_pro_auth_resend_time')))
        ) {
            $secretStatus = Auth::isBlockedUserIfTimesOff($_SESSION['auth_data']['login'], Auth::$AuthStatuses['SECRET_SENT'], $this->Config, 'check');
            if ($secretStatus === Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION']) {
                Auth::isBlockedUserIfTimesOff($_SESSION['auth_data']['login'], Auth::$AuthStatuses['SECRET_SENT'], $this->Config, 'write', $secretStatus);
                $this->location('/Auth/Login' . ($_GET['from'] ? '?from=' . urlencode($_GET['from']) : ''));
            }

            $Totum = new Totum($this->Config, Auth::serviceUserStart($this->Config));
            $Calculate = new CalculateAction($this->Config->getSettings('h_pro_auth_secret'));
            $settingsTable = $Totum->getTable('settings');
            $code = $Calculate->execAction('CODE', $settingsTable->getTbl()['params'], $settingsTable->getTbl()['params'], $settingsTable->getTbl(), $settingsTable->getTbl(), $settingsTable, 'exec', [
                'userId' => $_SESSION['auth_data']['id']
            ]);
            if (!$code || is_array($code)) {
                die('Settings error.');
            }

            try {

                $codeInstruction = (new CalculateAction($this->Config->getSettings('h_pro_auth_message')))->execAction('CODE', $settingsTable->getTbl()['params'], $settingsTable->getTbl()['params'], $settingsTable->getTbl(), $settingsTable->getTbl(), $settingsTable, 'exec', [
                    'secret' => $code,
                    'userId' => $_SESSION['auth_data']['id']
                ]);
                if (!is_string($codeInstruction)) {
                    $codeInstruction = null;
                }

                $_SESSION['auth_data']['secret']['code'] = (string)$code;
                $_SESSION['auth_data']['secret']['time'] = time();

                $this->__addAnswerVar('code_sent', $codeInstruction ?: $this->translate('New secret code was sent'), false);
                Auth::isBlockedUserIfTimesOff($_SESSION['auth_data']['login'], Auth::$AuthStatuses['SECRET_SENT'], $this->Config, 'write', $secretStatus);

            } catch (\Exception $exception) {
                $error = $exception->getMessage();
            }

        } elseif (!empty($post['resend'])) {
            $error = $this->translate('You can\'t resend the secret yet.');
        }

        if ($error ?? null) {
            $this->__addAnswerVar('error', $error, true);
        }
        $this->__addAnswerVar('schema_name', $this->Config->getSettings('totum_name'), true);
        if (!empty($_SESSION['auth_data']['secret']['time'])) {
            $this->__addAnswerVar('seconds', ($_SESSION['auth_data']['secret']['time'] + $this->Config->getSettings('h_pro_auth_resend_time')) - time());
        }
    }

    public function actionLogin(ServerRequestInterface $request)
    {
        $post = $request->getParsedBody();

        $this->Config->setSessionCookieParams();
        session_start();
        if (!empty($_SESSION['userId'])) {
            if($this->Config->isInterfacesSwitchedOn()){
                $User = Auth::getUserById($this->Config, $_SESSION['userId']);
                if($User->login !== 'webuser'){
                    $this->location();
                    die;
                }

            }else{
                $this->location();
                die;
            }
        }
        $Totum = new Totum($this->Config);

        $this->__addAnswerVar('with_pass_recover', $this->Config->getSettings('with_pass_recover'));
        $this->__addAnswerVar('schema_name', $this->Config->getSettings('totum_name'), true);

        if (!empty($post)) {
            $SendLetter = function ($email, $login, $pass) {
                $template = $this->Config->getModel('print_templates')->get(['name' => 'main_email'], 'styles, html');

                $template["body"] = preg_replace_callback(
                    '/{(domain|domen|login|pass)}/',
                    function ($match) use ($pass, $email, $login) {
                        switch ($match[1]) {
                            case 'domen':
                            case 'domain':
                                return $this->Config->getFullHostName();
                            case 'pass':
                                return $pass;
                            case 'login':
                                return empty($login) ? $email : $login;
                        }
                        return null;
                    },
                    $template["html"]
                );

                $this->Config->sendMail(
                    $email,
                    $this->translate('Credentials in %s', $_SERVER['HTTP_HOST']),
                    '<style>' . $template["styles"] . '</style>' . $template["body"] . '',
                );
            };


            $getNewPass = function () {
                $letters = 'abdfjhijklmnqrstuvwxz';
                return $letters[mt_rand(0, strlen($letters) - 1)] . $letters[mt_rand(
                        0,
                        strlen($letters) - 1
                    )] . str_pad(mt_rand(1, 9999), 4, 0);
            };

            if (empty($post['login']) || is_array($post['login'])) {
                return ['error' => $this->translate('Fill in the Login/Email field')];
            }


            if (empty($post['recover'])) {
                if (empty($post['pass'])) {
                    return ['error' => $this->translate('Fill in the Password field')];
                }

                switch ($this->passwordCheckingAndProtectionWithLDAP($post,
                    $userRow) ?? Auth::passwordCheckingAndProtection($post['login'],
                    $post['pass'],
                    $userRow,
                    $this->Config,
                    'web')) {
                    case Auth::$AuthStatuses['OK']:
                        if ($this->Config->getSettings('h_pro_auth_on_off')) {

                            $_SESSION['auth_data'] = ['id' => $userRow['id'], 'login' => mb_strtolower($post['login'])];
                            $this->location('/Auth/Verification' . ($_GET['from'] ? '?from=' . urlencode($_GET['from']) : ''));

                        } else {

                            Auth::webInterfaceSetAuth($userRow['id']);

                            $baseDir = $this->Config->getBaseDir();

                            if (in_array(1, $userRow['roles'])) {
                                $schema = is_callable([$this->Config, 'setHostSchema']) ? '"' . $this->Config->getSchema() . '"' : '';
                                shell_exec("cd {$baseDir} && bin/totum check-service-notifications {$schema} > /dev/null 2>&1 &");
                            }

                        $this->location($_GET['from'] && $_GET['from'] !== '/'  && $_GET['from'] !== '/totum'? $_GET['from'] : Auth::getUserById($this->Config,
                                $userRow['id'])->getUserStartPath(),
                                !key_exists('from', $_GET));
                        }
                        break;
                    case Auth::$AuthStatuses['LDAP_LOAD_CRASH']:
                        return ['error' => $this->translate('User is switched off or does not have access rights')];
                    case Auth::$AuthStatuses['WRONG_PASSWORD']:
                        return ['error' => $this->translate('Password is not correct')];
                    case Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION']:
                        return ['error' => $this->translate('Due to exceeding the number of password attempts, your IP is blocked')];
                }
            } else {
                if (!$Totum->getConfig()->getSettings('with_pass_recover')) {
                    return ['error' => $this->translate('Password recovery via email is disabled for this database. Contact the solution administrator.')];
                }

                if ($userRow = $userRow ?? Auth::getUserRowWithServiceRestriction($post['login'], $this->Config)) {

                    if ($userRow['ttm__auth_type']) {
                        return ['error' => $this->translate('Password recovering is not possible for users with special auth types')];
                    }

                    $email = $userRow['email'];
                    if (empty($email)) {
                        return ['error' => $this->translate('Email for this login is not set')];
                    }
                    $User = Auth::serviceUserStart($this->Config);
                    $Totum = new Totum($this->Config, $User);
                    $pass = $getNewPass();

                    try {
                        $Totum->getTable('users')->actionSet(
                            ['pass' => $pass],
                            [['field' => 'id', 'operator' => '=', 'value' => $userRow['id']]]
                        );
                        Auth::webInterfaceRemoveAuth();

                        $login = $userRow['login'];
                        $email = $userRow['email'];
                        $SendLetter($email, $login, $pass);

                        return ['error' => $this->translate('An email with a new password has been sent to your Email. Check your inbox in a couple of minutes.')];
                    } catch (Exception $e) {
                        Auth::webInterfaceRemoveAuth();

                        return ['error' => $this->translate('Letter has not been sent: %s', $e->getMessage())];
                    }
                } else {
                    return ['error' => $this->translate('The user with the specified Login/Email was not found')];
                }
            }
        }
    }

    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestAction = substr($requestUri, strlen($this->modulePath));
        $action = explode('/', $requestAction, 2)[0] ?? 'Main';

        try {
            $this->__run($action, $request);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->__addAnswerVar('error', $message);
        }
        $this->output($action);
    }

    public function actionLogout()
    {

        $OnlyOffice = OnlyOfficeConnector::init($this->Config);
        if ($OnlyOffice->isSwithedOn()) {
            session_start();
            if ($_SESSION['userId'] ?? null) {
                $OnlyOffice->dropLogoutUser($_SESSION['userId']);
            }
        }
        Auth::webInterfaceRemoveAuth();
        $this->location();
        die;
    }

    protected function passwordCheckingAndProtectionWithLDAP(array $post, &$userRow): ?int
    {
        /*LDAP Off*/
        if (!$this->Config->getLDAPSettings('h_ldap_on', null)) {
            return null;
        }
        /*It's TOTUM auth*/
        if ($this->Config->getLDAPSettings('h_domain_selector', null) && empty($post['type'])) {
            return null;
        }

        $Config = $this->Config;
        $ip = ($_SERVER['REMOTE_ADDR'] ?? null);
        $now_date = date_create();

        if (($block_time = $Config->getSettings('h_time')) && ($error_count = (int)$Config->getSettings('error_count'))) {
            $BlockDate = date_create()->modify('-' . $block_time . 'minutes');
            $block_date = $BlockDate->format('Y-m-d H:i');
        }

        if ($block_time && $Config->getModel('auth_log')->get(['user_ip' => $ip, 'login' => $post['login'], 'datetime->>\'v\'>=\'' . $block_date . '\'', 'status' => 2])) {
            return Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION'];
        }

        $getWrongStatus = function () use ($post, $error_count, $block_time, $block_date, $ip, $Config) {
            if (!$block_time || !$error_count) {
                return Auth::$AuthStatuses['WRONG_PASSWORD'];
            }
            $statuses = $Config->getModel('auth_log')->getAll(
                ['user_ip' => $ip, 'login' => $post['login'], 'datetime->>\'v\'>=\'' . $block_date . '\''],
                'status',
                'id desc'
            );
            $count = 0;
            foreach ($statuses as $st) {
                if ($st["status"] != 1) {
                    break;
                } else {
                    $count++;
                }
            }

            if ($count >= $error_count) {
                return Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION'];
            } else {
                return Auth::$AuthStatuses['WRONG_PASSWORD'];
            }
        };


        $checkLDAPBind = function ($loginIn, $password, $domain, &$userRow) {
            $connection = $this->Config->getLDAPSettings('connection', $domain);

            $getDnForUser = function () use ($domain, $loginIn, $userRow) {
                if ($userRow) {
                    return $userRow['ttm__extparams']['dn'];
                }
                $where = new \stdClass();
                $where->whereStr = 'ttm__auth_type->>\'v\' = \'LDAP\' AND is_del = false AND interface->>\'v\' = \'web\' AND ttm__extparams->\'v\'->>\'logindomain\' = ?';
                $where->params = [$loginIn . '@' . $domain];
                $params = $this->Config->getModel('users')->getPrepared($where, 'ttm__extparams->\'v\'->>\'dn\' as dn');
                if ($params) {
                    return $params['dn'];
                }
                return null;
            };


            $login = match ($this->Config->getLDAPSettings('h_bind_format', $domain)) {
                'at' => $loginIn . '@' . $domain,
                'dn' => $getDnForUser(),
                default => throw new \Exception('Не поддерживаемый формат бинда ')
            };

            if (!$login) {
                return Auth::$AuthStatuses['WRONG_PASSWORD'];
            }

            $r = @ldap_bind($connection, $login, $password);
            if (!$r) {
                return Auth::$AuthStatuses['WRONG_PASSWORD'];
            }

            /*Update User params*/
            $Totum = $this->Totum ?? new Totum($this->Config,
                Auth::loadAuthUserByLogin($this->Config, 'service', false));
            $LDAPSettingsTable = $Totum->getTable('ttm__ldap_settings');
            $CalcAction = new CalculateAction("=: exec(code: 'h_import_users'; var: 'onLogin' = $#loginJson)");
            $userRow = $CalcAction->execAction('CODE', [],
                [],
                $LDAPSettingsTable->getTbl(),
                $LDAPSettingsTable->getTbl(),
                $LDAPSettingsTable,
                'exec',
                ['loginJson' => ['login' => $loginIn, 'domain' => $domain]]
            );

            if (!$userRow || !$userRow['on_off']) {
                return Auth::$AuthStatuses['LDAP_LOAD_CRASH'];
            }
            return Auth::$AuthStatuses['OK'];
        };

        if (str_contains($post['login'], '@')) {
            /*check is email with switched off users - he may be switched on by LDAP*/
            if ($userRow = Auth::getUserRowWithServiceRestriction($post['login'], $this->Config, 'web', '*ALL*')) {
                if ($userRow['ttm__auth_type'] === 'LDAP') {
                    $status = $checkLDAPBind($userRow['ttm__extparams']['login'],
                        $post['pass'],
                        $userRow['ttm__extparams']['domain'],
                        $userRow);
                } /*if this totum user was switched off*/
                elseif (!$userRow['is_del']) {
                    $status = Auth::$AuthStatuses['WRONG_PASSWORD'];
                } else {
                    /*This is totum-user check it common way*/
                    return null;
                }
            } else {
                list($login, $domain) = explode('@', $post['login']);
                $status = $checkLDAPBind($login,
                    $post['pass'],
                    $domain,
                    $userRow);
            }

        } else {
            if (!$this->Config->getLDAPSettings('h_domain_selector', null)) {
                if ($userRow = Auth::getUserRowWithServiceRestriction($post['login'], $this->Config, 'web')) {
                    /*This is totum-user check it common way*/
                    return null;
                } else {
                    if (!is_array($this->Config->getLDAPSettings('h_domains_settings', null)) || count($this->Config->getLDAPSettings('h_domains_settings', null)) != 1) {
                        /*If domain number is not 1 then check only totum-user here*/
                        return Auth::$AuthStatuses['WRONG_PASSWORD'];
                    } else {
                        $status = $checkLDAPBind($post['login'],
                            $post['pass'],
                            array_key_first($this->Config->getLDAPSettings('h_domains_settings', null)),
                            $userRow);
                    }
                }
            } elseif (empty($post['type'])) {
                /*Its totum-user. Check it common way*/
                return null;
            } else {
                if ($post['type'] === "1") {
                    if ($this->Config->getLDAPSettings('h_domain_selector', null) === "ldap") {
                        if (count($this->Config->getLDAPSettings('h_domains_settings', null)) != 1) {
                            die("wrong ldap settings");
                        }
                        $post['type'] = array_key_first($this->Config->getLDAPSettings('h_domains_settings', null));
                    } else {
                        die("wrong ldap settings");
                    }
                }

                $status = $checkLDAPBind($post['login'],
                    $post['pass'],
                    $post['type'],
                    $userRow);
            }
        }

        if (is_null($status ?? null)) {
            die('logic error');
        }

        if ($status !== Auth::$AuthStatuses['OK']) {
            if ($status === Auth::$AuthStatuses['LDAP_LOAD_CRASH']) {
                return Auth::$AuthStatuses['LDAP_LOAD_CRASH'];
            }
            $status = $getWrongStatus();
        }
        if ($status !== Auth::$AuthStatuses['OK'] || !$Config->getSettings('h_pro_auth_on_off')) {
            $Config->getSql()->insert(
                'auth_log',
                [
                    'datetime' => json_encode(['v' => $now_date->format('Y-m-d H:i')])
                    , 'user_ip' => json_encode(['v' => $ip])
                    , 'login' => json_encode(['v' => $post['login']])
                    , 'status' => json_encode(['v' => strval($status)])
                ],
                false
            );
        }
        return $status;

    }

    public function actionOpenId(ServerRequestInterface $request)
    {
        $this->Config->setSessionCookieParams();
        session_start();

        if (!empty($_SESSION['userId'])) {
            $this->location();
            die;
        }
        if (!empty($request->getParsedBody()['method'])) {
            $this->isAjax = true;

            return match ($request->getParsedBody()['method']) {
                'getOpenIdRedirectData' => $this->getOpenIdData($request->getParsedBody()['id'] ?? null),
                default => ['error' => $this->translate('Method [[%s]] in this module is not defined.', $request->getParsedBody()['method'])]
            };
        }

        if (!empty($_GET['state'])) {
            $OpedIdSessionData = $_SESSION['openIdData'] ?? [];
            if ($_GET['state'] != ($OpedIdSessionData['state'] ?? false)) {
                static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
                return ['error' => 'Session was closed. Return to authorizaion page.'];
            }
            return $this->getOpenIdData($OpedIdSessionData['id'], 'auth', $_GET['code'] ?? '');
        }


        var_dump('test');
        die;
    }

    protected function getOpenIdData(string|null $openIdId, string $type = 'redirest', string $code = null): array
    {
        $Totum = new Totum($this->Config, Auth::serviceUserStart($this->Config));
        $Table = $Totum->getTable('ttm__openid');
        if ($openIdId &&
            $openIdIdData = $Table->getByParams((new FormatParamsForSelectFromTable())
                ->where('status', true)
                ->where('id', $openIdId)->field('*ALL*')
                ->params(), 'row')) {

            switch ($type) {
                case 'redirest':
                    $extraOptions = [];
                    if (trim($openIdIdData['extra_options']) != '') {
                        $extraOptions = (new CalculateAction($openIdIdData['extra_options']))
                            ->execAction('CODE', [], [], $Table->getTbl(), $Table->getTbl(), $Table, 'exec');
                    }


                    $_SESSION['openIdData'] = ['state' => md5(random_bytes(10)), 'id' => $openIdId];

                    return ['uri' => $openIdIdData['auth_uri'] . '?' . http_build_query(
                            ['response_type' => 'code',
                                'state' => $_SESSION['openIdData']['state'],
                                'client_id' => $openIdIdData['client_id'],
                                'redirect_uri' => $openIdIdData['redirect_uri'],
                                'scope' => implode(' ', $openIdIdData['scope'])] + $extraOptions
                        )];

                case 'auth':


                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $openIdIdData['token_endpoint']);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->Config->isCheckSsl() ? 2 : 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->Config->isCheckSsl());


                    if (trim($openIdIdData['extra_headers']) != '') {
                        $extraHeaders = (new CalculateAction($openIdIdData['extra_headers']))
                            ->execAction('CODE', [], [], $Table->getTbl(), $Table->getTbl(), $Table, 'exec');
                        if (is_array($extraHeaders)) {
                            curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($extraHeaders,['Content-Type: application/x-www-form-urlencoded']));
                        }else {
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                        }
                    }else {
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
                    }



                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                        'grant_type' => 'authorization_code',
                        'client_id' => $openIdIdData['client_id'],
                        'code' => $code,
                        'redirect_uri' => $openIdIdData['redirect_uri'],
                        'client_secret' => Crypt::getDeCrypted($openIdIdData['client_secret'], $this->Config->getCryptKeyFileContent())

                    ]));
                    $result = curl_exec($ch);
                    if ($error = curl_error($ch)) {
                        curl_close($ch);
                        return ['error' => $error];
                    }
                    curl_close($ch);

                    $resultData = json_decode($result, true);

                    if ($resultData['error'] ?? '') {
                        static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
                        return ['error' => $resultData['error']];
                    }

                    $split = explode('.', $resultData['id_token']);
                    $data = json_decode(base64_decode($split[1]), true);

                    if (trim($openIdIdData['check_code']) != '') {
                        $_res = (new CalculateAction($openIdIdData['check_code']))
                            ->execAction('CODE', [], [], $Table->getTbl(),
                                $Table->getTbl(), $Table, 'exec', ['id_token' => $data]);

                        if ($_res && is_array($_res)) {
                            if (!empty($_res['error'])) {
                                $this->answerVars['error'] = $_res['error'];
                                static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
                                return [];
                            } elseif (!empty($openIdIdData['id_token']) && is_array($openIdIdData['id_token'])) {
                                $data = $openIdIdData['id_token'];
                            } else {
                                $this->answerVars['error'] = 'check_code error';
                                static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
                                return [];
                            }
                        }

                        unset($_res);
                    }

                    $data['id_token_hint'] = $resultData['id_token'];

                    if (!empty($data['email'])) {

                        $auth = function ($id) use ($data) {
                            if ($this->Config->getSettings('h_pro_auth_on_off')) {
                                $_SESSION['auth_data'] = ['id' => $id, 'login' => $data['email']];
                                $this->location('/Auth/Verification');
                            } else {
                                Auth::isBlockedUserIfTimesOff($data['email'], null, $this->Config, 'write', Auth::$AuthStatuses['OK']);
                                Auth::webInterfaceSetAuth($id);
                                $this->location('/');
                            }
                            die;
                        };


                        try {
                            if ($User = Auth::loadAuthUserByEmail($this->Config, $data['email'], true)) {
                                $this->addOrUpdateOpenIdUser($data, $openIdIdData, $Table, $User->getId());
                                $auth($User->getId());
                            }
                        } catch (\Exception $e) {
                            if (preg_match('/^GOMODULE: User with email ' . $data['email'] . ' is not found$/', $e->getMessage())) {
                                if (!empty($openIdIdData['rejection_comment'])){
                                    $this->answerVars['error'] = $openIdIdData['rejection_comment'];
                                    static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
                                    return [];
                                }

                                $id = $this->addOrUpdateOpenIdUser($data, $openIdIdData, $Table);
                                $auth($id);
                            }

                            $this->answerVars['error'] = $e->getMessage();
                            static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
                            return [];
                        }


                    }

                    break;
            }
        }

        return ['error' => 'OpenId was not found'];
    }

    protected function addOrUpdateOpenIdUser(array $data, $openIdIdData, aTable $Table, int|null $userId = null)
    {
        $Totum = new Totum($this->Config, Auth::serviceUserStart($this->Config));
        $Totum->transactionStart();
        $Users = $Totum->getTable('users');
        $RolesCode = new CalculateAction($openIdIdData['roles_code']);
        $roles = $RolesCode->execAction('CODE', [], [], $Table->getTbl(), $Table->getTbl(), $Table, 'exec', [
            'id_token' => $data
        ]);
        if (!$userId) {
            $params['add'] = [[
                'fio' => $data[$openIdIdData['fio_key']],
                'ttm__extparams' => [
                    'id_token' => $data,
                    'name' => $openIdIdData['name'],
                    'sid' => $data['sid'] ?? '',
                    'sub' => $data['sub'],
                    'id_token_hint' => $data['id_token_hint']
                ],
                'ttm__auth_type' => 'OpenID',
                'roles' => $roles,
                'email' => $data['email']
            ]
            ];
        } else {
            $params['modify'] = [$userId => [
                'ttm__extparams' => [
                    'id_token' => $data,
                    'name' => $openIdIdData['name'],
                    'sid' => $data['sid'],
                    'sub' => $data['sub'],
                    'id_token_hint' => $data['id_token_hint']
                ],

                'ttm__auth_type' => 'OpenID',
                'roles' => $roles
            ]
            ];
        }


        $Users->reCalculateFromOvers(
            $params
        );
        if (!$userId) {
            if (!empty($Users->getChangeIds()['added'])) {
                $userId = array_keys($Users->getChangeIds()['added'])[0];
                if($openIdIdData['new_user_action']){
                        static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
                            $Users = $Totum->getTable('users');
                            $CA = new CalculateAction($openIdIdData['new_user_action']);
                            $CA->execAction('CODE', [], [], $Users->getTbl(), $Users->getTbl(), $Users, 'exec', ['userId' => $userId]);

                }

            } else {
                $this->answerVars['error'] = 'Strange error: user not inserted';
                static::$contentTemplate = $this->folder . '/__RedirectWithError.php';
            }
        }
        $Totum->transactionCommit();

        return $userId;
    }


}

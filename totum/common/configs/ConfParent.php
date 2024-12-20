<?php
/** @noinspection PhpMissingReturnTypeInspection */

/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 04.07.2018
 * Time: 11:37
 */

namespace totum\common\configs;

use totum\common\calculates\CalculateAction;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Lang\LangInterface;
use totum\common\logs\Log;
use totum\common\Services\Services;
use totum\common\Services\ServicesVarsInterface;
use totum\common\sql\Sql;
use totum\common\sql\SqlException;
use totum\common\Totum;
use totum\common\User;
use totum\fieldTypes\File;

abstract class ConfParent
{
    use TablesModelsTrait;
    use ProfilerTrait;

    /* Переменные настройки */


    protected $ajaxTimeout = 50;
    public static $CalcLogs;
    protected $tmpDirPath = 'totumTmpfiles/tmpLoadedFiles/';
    protected $tmpTableChangesDirPath = 'totumTmpfiles/tmpTableChangesDirPath/';
    protected $logsDir = 'myLogs/';

    public static $MaxFileSizeMb = 40000;
    public static $timeLimit = 30;


    protected $execSSHOn = false;
    protected $checkSSl = false;

    const isSuperlang = false;

    static string $GlobProfilerVarName = 'PRO_PROFILER';

    const LANG = '';

    /* Переменные работы конфига */
    protected static $handlersRegistered = false;
    /**
     * @var Log
     */
    protected static $logPhp;

    protected $CalculateExtensions;

    /**
     * @var string production|development
     */
    protected $env;
    public const ENV_LEVELS = ['production' => 'production', 'development' => 'development'];


    protected $dbConnectData;
    protected string $proGoModuleServiceName = 'totum-gom';

    private $settingsCache;
    protected $settingsLDAPCache;
    protected $hostName;
    /**
     * @var string
     */
    protected $schemaName;


    /**
     * microtime of start script (this config part)
     *
     * @var float
     */
    protected $mktimeStart;
    /**
     * @var string
     */
    protected $baseDir;
    protected $procVars = [];
    protected $Lang;

    public $loginsWithoutTwoFactorAuth = [];
    protected bool $isLangFixed = false;
    protected ?array $langJsonTranslates = null;
    protected bool|string $userLangCreatorMode = false;
    /**
     * @var false|resource|null
     */
    protected $proGoModuleSocket;
    /**
     * @var array|mixed|null
     */
    protected mixed $langLangsJsonTranslates;
    protected $checkSSLservices = true;

    public function __construct($env = self::ENV_LEVELS['production'])
    {
        $this->mktimeStart = microtime(true);
        set_time_limit(static::$timeLimit);
        $this->logLevels =
            $env === self::ENV_LEVELS['production'] ? ['critical', 'emergency']
                : ['error', 'debug', 'alert', 'critical', 'emergency', 'info', 'notice', 'warning'];

        $this->baseDir = $this->getBaseDir();
        $this->tmpDirPath = $this->baseDir . $this->tmpDirPath;
        $this->tmpTableChangesDirPath = $this->baseDir . $this->tmpTableChangesDirPath;
        $this->logsDir = $this->baseDir . $this->logsDir;
        $this->env = $env;

        if (empty(static::LANG)) {
            throw new \Exception('Language is not defined in constant LANG in Conf.php');
        }
        if (!class_exists('totum\\common\\Lang\\' . strtoupper(static::LANG))) {
            throw new \Exception('Specified ' . static::LANG . ' language is not supported');
        }
        $this->Lang = new ('totum\\common\\Lang\\' . strtoupper(static::LANG))();

    }

    public function isCheckSsl(): bool
    {
        return $this->checkSSl;
    }
    public function isCheckSslServices(): bool
    {
        return $this->checkSSLservices;
    }

    public function getDefaultSender()
    {
        return $this->getSettings('default_email') ?? 'no-reply@' . $this->getFullHostName();
    }

    public function setSessionCookieParams()
    {
        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    public function getBaseDir()
    {
        return dirname((new \ReflectionClass(get_called_class()))->getFileName()) . DIRECTORY_SEPARATOR;
    }

    public function setLogIniAndHandlers()
    {
        $this->setLogIni();
        $this->registerHandlers();
    }

    public function getClearConf()
    {
        if (!empty($GLOBALS[static::$GlobProfilerVarName]) && is_a($GLOBALS[static::$GlobProfilerVarName] ?? false, Profiler::class)) {
            $GLOBALS[static::$GlobProfilerVarName]->increaseRestarts();
        }

        return new static($this->env);
    }

    public function cronErrorActions($cronRow, $User, $exception)
    {
        $errTitle = $this->translate('Cron error');

        try {
            $Totum = new Totum($this, $User);
            $Table = $Totum->getTable('settings');


            $Cacl = new CalculateAction('=: insert(table: "notifications"; field: \'user_id\'=1; field: \'active\'=true; field: \'title\'="' . $errTitle . '"; field: \'code\'="admin_text"; field: "vars"=$#vars)');
            $Cacl->execAction(
                'kod',
                $cronRow,
                $cronRow,
                $Table->getTbl(),
                $Table->getTbl(),
                $Table,
                'exec',
                ['vars' => ['text' => $errTitle . ': <b>' . ($cronRow['descr'] ?? $cronRow['id']) . '</b>:<br>' . $exception->getMessage()]]
            );
        } catch (\Exception) {
        }

        $this->sendMail(
            static::adminEmail,
            $errTitle . ' ' . $this->getSchema() . ' ' . ($cronRow['descr'] ?? $cronRow['id']),
            $exception->getMessage()
        );
    }

    public function getTemplatesDir()
    {
        return dirname(__FILE__) . '/../../templates';
    }

    public function getTmpDir()
    {
        if (!is_dir($this->tmpDirPath)) {
            mkdir($this->tmpDirPath, 0777, true);
        }
        return $this->tmpDirPath;
    }

    public function getTmpTableChangesDir()
    {
        if (!is_dir($this->tmpTableChangesDirPath)) {
            mkdir($this->tmpTableChangesDirPath, 0777, true);
        }
        return $this->tmpTableChangesDirPath;
    }

    public function getSchema($force = true)
    {
        if ($force && empty($this->schemaName)) {
            errorException::criticalException($this->translate('The schema is not connected.'), $this);
        }
        return $this->schemaName;
    }

    public function getFullHostName()
    {
        return $this->hostName;
    }

    public function getMainHostName()
    {
        return $this->hostName;
    }

    public function getFilesDir()
    {
        return $this->baseDir . 'http/fls/';
    }

    public function getCryptKeyFileContent()
    {
        $fName = $this->getBaseDir() . 'Crypto.key';
        if (!file_exists($fName)) {
            throw new errorException($this->translate('Crypto.key file not exists'));
        }
        return file_get_contents($fName);
    }


    public function getCryptSolt()
    {
        return $this->getSettings('crypt_solt');
    }

    /**
     * @return bool
     */
    public function isExecSSHOn(bool|string $type): bool
    {
        return match ($type) {
            true => $this->execSSHOn === true,
            'inner' => $this->execSSHOn === true || $this->execSSHOn === 'inner',
            default => false
        };
    }

    public function getSecureFilesDir(): string
    {
        $dir = $this->baseDir . 'secureFiles/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * @param User|string $User - User or "NOT_TRANSLATE"
     * @return void
     */
    public function setUserData(User|string|array $User)
    {
        if (is_object($User) && is_a($GLOBALS[static::$GlobProfilerVarName] ?? false, Profiler::class)) {
            $GLOBALS[static::$GlobProfilerVarName]->setUserId($User->getId());
        }

        if ($User === 'NOT_V_TRANSLATE') {
            if ($this->userLangCreatorMode !== 'NOT_TRANSLATE') {
                $this->userLangCreatorMode = 'NOT_V_TRANSLATE';
            }
        }elseif ($User === 'NOT_TRANSLATE') {
            $this->userLangCreatorMode = 'NOT_TRANSLATE';
        } elseif (static::isSuperlang) {
            if (is_array($User)) {
                if ($User['lang'] ?? null) {
                    $newLang = new ('totum\\common\\Lang\\' . strtoupper($User['lang']))();
                    if ($this->Lang::class !== $newLang::class) {
                        $this->langLangsJsonTranslates = null;
                        $this->langJsonTranslates = null;
                        $this->Lang = $newLang;
                    }
                }
                $this->userLangCreatorMode = false;
                return;
            } elseif (!$this->isLangFixed && $User->ttm__langs) {
                $newLang = new ('totum\\common\\Lang\\' . strtoupper($User->ttm__langs))();
                if ($this->Lang::class !== $newLang::class) {
                    $this->langLangsJsonTranslates = null;
                    $this->langJsonTranslates = null;
                    $this->Lang = $newLang;
                }
            }
            $this->userLangCreatorMode = $User->isCreator();
        }
    }

    public function getProGoModuleServiceName(): string
    {
        return $this->proGoModuleServiceName;
    }


    /********************* MAIL SECTION **************/

    protected function mailBodyAttachments($body, $attachmentsIn = [])
    {
        $attachments = [];
        foreach ($attachmentsIn as $k => $v) {
            $filestring = null;
            $fileName = null;
            if (is_array($v)) {
                $fileName = $v['name'] ?? throw new errorException($this->translate('Not correct row in files list'));

                if (!empty($v['file'])) {
                    $v = $v['file'];
                } elseif (!empty($v['filestring'])) {
                    $filestring = $v['filestring'];
                } else {
                    throw new errorException($this->translate('Not correct row in files list'));
                }
            }

            $filestring = $filestring ?? File::getContent($v, $this);
            if (!$fileName) {
                if (!preg_match('/.+\.[a-zA-Z0-9]+$/', $k)) {
                    $fileName = preg_replace('`([^/]+\.[^/]+)$`', '$1', $v);
                } else {
                    $fileName = $k;
                }
            }
            $attachments[$fileName] = $filestring;
        }

        $body = preg_replace_callback(
            '~src\s*=\s*([\'"]?)(?:http(?:s?)://' . $this->getFullHostName() . ')?/fls/(.*?)\1~',
            function ($matches) use (&$attachments) {
                if (!empty($matches[2]) && $file = File::getContent($matches[2], $this)) {
                    $md5 = md5($matches[2]) . '.' . preg_replace('/.*\.([a-zA-Z]{2,5})$/', '$1', $matches[2]);
                    $attachments[$md5] = $file;
                    return 'src="cid:' . $md5 . '"';
                }
                return null;
            },
            $body
        );

        return [$body, $attachments];
    }

    /**
     * Override this function by traits or directly for send emails from AuthController or Totum-code
     *
     *
     * @param $to
     * @param $title
     * @param $body
     * @param array $attachments
     * @param null $from
     * @throws errorException
     */
    public function sendMail(array|string $to, $title, $body, $attachments = [], $from = null)
    {
        throw new errorException($this->translate('Settings for sending mail are not set.'));
    }

    /********************* ANONYM SECTION **************/

    protected const ANONYM_ALIAS = 'An';

    public function getAnonymHost($type)
    {
        if ($hiddenHosts = $this->getHiddenHosts()) {
            foreach (static::getSchemas() as $host => $schema) {
                if (key_exists($host,
                        $hiddenHosts) && ($this->getSchema() === $schema) && ($hiddenHosts[$host][$type] ?? false)) {
                    return $host;
                }
            }
        }
        return $this->getFullHostName();
    }

    /**
     * @return string
     */
    public function getAnonymModul()
    {
        return static::ANONYM_ALIAS;
    }

    /********************* HANDLERS SECTION **************/

    protected function registerHandlers()
    {
        if (!static::$handlersRegistered) {
            register_shutdown_function([$this, 'shutdownHandler']);

            /*Для записи нотификаций от php в лог*/
            static::$logPhp = $this->getLogger('php');
            set_error_handler([$this, 'errorHandler']);

            static::$handlersRegistered = true;
        }
    }

    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $this->getLogger('php')->error($errfile . ':' . $errline . ' ' . $errstr);
    }

    public function shutdownHandler()
    {
        $error = error_get_last();

        if ($error !== null) {
            $errno = $error['type'];
            $errfile = $error['file'];
            $errline = $error['line'];
            $errstr = $error['message'];


            $errorStr = $errstr;
            if ($errno === E_ERROR) {
                if (empty($_POST['ajax'])) {
                    echo $errorStr;
                }

                static::errorHandler($errno, $errorStr, $errfile, $errline);
                static::$logPhp?->error($errfile . ':' . $errline . ' ' . $errstr);
                if (static::$CalcLogs) {
                    $this->getLogger('sql')->error(static::$CalcLogs);
                }
            }

            if (!empty($_POST['ajax'])) {
                echo json_encode(['error' => $errorStr], JSON_UNESCAPED_UNICODE);
            }
        }
    }


    /**
     * @param $uri
     * @return array|string[]
     */
    public function getActivationData($uri)
    {
        $split = explode('/', substr($uri, 1), 2);
        if (!preg_match('/^[a-z0-9_]+$/i', $split[0])) {
            $split[0] = '';
            $split[1] = $uri;
        }
        if ($split[0] === $this->getAnonymModul()) {
            $split[0] = 'An';
        } elseif ($split[0] === 'An') {
            die($this->translate('Error accessing the anonymous tables module.'));
        }

        return [$split[0], $split[1] ?? ''];
    }


    /********************* LOGGERS SECTION **************/

    /**
     * @var array
     */
    protected $Loggers = [];
    /**
     * @var string[]
     */
    protected $logLevels;

    /**
     * @param string $type
     * @param null|array $levels
     * @param null $templateCallback
     * @param null $fileName
     * @return Log
     */
    public function getLogger(string $type, $levels = null, $templateCallback = null, $fileName = null)
    {
        if (key_exists($type, $this->Loggers)) {
            return $this->Loggers[$type];
        }

        if (!$levels) {
            $levels = $this->logLevels;
        }

        $dir = $this->logsDir;
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $this->Loggers[$type] = new Log(
            $fileName ?? $dir . $type . '_' . $this->getSchema(false) . '.log',
            $this->getLangObj(),
            $levels,
            $templateCallback
        );

        return $this->Loggers[$type];
    }


    public function getCalculateExtensionFunction($funcName)
    {
        $this->getObjectWithExtFunctions();
        if (method_exists($this->CalculateExtensions, $funcName) || (property_exists($this->CalculateExtensions,
                    $funcName) && is_callable($this->CalculateExtensions->$funcName))) {
            return $this->CalculateExtensions->$funcName;
        }
        throw new errorException($this->translate('Function [[%s]] is not found.', $funcName));
    }

    public function getExtFunctionsTemplates()
    {
        $this->getObjectWithExtFunctions();
        return $this->CalculateExtensions->jsTemplates ?? '[]';
    }

    public function getObjectWithExtFunctions()
    {
        ;
        if (!$this->CalculateExtensions) {
            if (file_exists($fName = dirname((new \ReflectionClass($this))->getFileName()) . '/CalculateExtensions.php')) {
                include($fName);
            }
            $this->CalculateExtensions = $CalculateExtensions ?? new \stdClass();
        }
        return $this->CalculateExtensions;
    }

    public function getLang()
    {
        $array = explode('\\', $this->Lang::class);
        return strtolower(end($array));
    }

    public function getServicesVarObject(): ServicesVarsInterface
    {
        return Services::init($this);
    }

    protected function setLogIni()
    {
        ini_set('log_errors', 1);
        switch ($this->env) {
            case 'production':
                ini_set('display_errors', 0);
                ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);
                break;
            default:
                ini_set('display_errors', 1);
                ini_set('error_reporting', E_ALL);
        }
    }

    /********************* DATABASE SECTION **************/

    abstract public static function getSchemas();

    public function getSshPostgreConnect($type)
    {
        $db = $this->getDb(false);
        if (empty($db[$type])) {
            errorException::criticalException($this->translate('The path to ssh script %s is not set.', $type), $this);
        }
        $pathPsql = $db[$type];
        $dbConnect = sprintf(
            'postgresql://%s:%s@%s/%s',
            $db['username'],
            urlencode($db['password']),
            $db['host'],
            $db['dbname']
        );

        return "$pathPsql --dbname=\"$dbConnect\"";
    }

    public function getDb($withSchema = true)
    {
        $db = $this->dbConnectData ?? static::db;
        if ($withSchema) {
            $db['schema'] = $db['schema'] ?? $this->getSchema();
        }
        return $db;
    }

    /**
     * @var Sql
     */
    protected $Sql;

    public function getSql($mainInstance = true, $withSchema = true, $Logger = null)
    {
        $getSql = function () use ($withSchema, $Logger) {
            return new Sql($this->getDb($withSchema),
                $Logger ?? $this->getLogger('sql'),
                $withSchema,
                $this->getLangObj(),
                (static::$timeLimit + 5) * 1000
            );
        };
        if ($mainInstance) {
            return $this->Sql ?? $this->Sql = $getSql();
        } else {
            return $getSql();
        }
    }

    public function getLDAPSettings(string $name, string|null $domain)
    {
        if (!$this->settingsLDAPCache) {

            $settings = json_decode(
                $this->getTableRow('ttm__ldap_settings')['header'],
                true
            );
            $this->settingsLDAPCache = [];
            foreach ($settings as $s_key => $s_value) {
                if (is_array($s_value) && key_exists('v', $s_value)) {
                    $this->settingsLDAPCache[$s_key] = $s_value['v'];
                }
            }
        }
        switch ($name) {
            case 'connection':
                if (empty($this->settingsLDAPCache['connection'][$domain])) {

                    if (!extension_loaded("ldap")) {
                        die('LDAP extension php not enabled');
                    }

                    $host = $this->getLDAPSettings('h_host', $domain);
                    if (empty($host)) {
                        throw new errorException($this->translate('Set the host in the LDAP settings table'));
                    }

                    $port = $this->getLDAPSettings('h_port', $domain);

                    if (empty($port)) {
                        throw new errorException($this->translate('Set the port in the LDAP settings table'));
                    }
                    $this->settingsLDAPCache['connection'][$domain] = ldap_connect($host, $port);

                    $settings = $this->getLDAPSettings('h_version', $domain);
                    $settings['LDAP_OPT_PROTOCOL_VERSION'] = (int)($settings['LDAP_OPT_PROTOCOL_VERSION'] ?? 3);
                    foreach ($settings as $_name => $val) {
                        if (str_starts_with($_name, 'G_')) {
                            $_name = substr($_name, 2);
                            ldap_set_option(null, constant($_name), $val);
                        } else {
                            ldap_set_option($this->settingsLDAPCache['connection'][$domain], constant($_name), $val);
                        }
                    }

                    if ($this->getLDAPSettings('h_ssl', $domain)) {
                        if (($cert = $this->getLDAPSettings('h_cert_file', $domain)) && is_array($cert)) {
                            foreach ($cert as $file) {
                                if (($file['name'] ?? null) && $file['ext'] ?? null) {
                                    $filePath = File::getFilePath($file['name'], $this);
                                    switch ($file['ext']) {
                                        case 'crt':
                                            ldap_set_option(null, LDAP_OPT_X_TLS_CERTFILE, $filePath);
                                            break;
                                        case 'key':
                                            ldap_set_option(null, LDAP_OPT_X_TLS_KEYFILE, $filePath);
                                            break;
                                    }
                                }
                            }
                        }
                        ldap_start_tls($this->settingsLDAPCache['connection'][$domain]);
                    }
                }

                return $this->settingsLDAPCache['connection'][$domain];
                break;
            case 'h_bind_format':
                if (empty($this->settingsLDAPCache['h_domains_settings'][$domain]['bind_format'] ?? $this->settingsLDAPCache['h_bind_format'])) {
                    throw new errorException($this->translate('Set the binding format in the LDAP settings table'));
                }
                break;
        }

        if ($domain && str_starts_with($name, 'h_') && is_array($this->settingsLDAPCache['h_domains_settings'][$domain] ?? null)) {
            $cropName = substr($name, 2);
            if (key_exists($cropName, $this->settingsLDAPCache['h_domains_settings'][$domain])) {
                return $this->settingsLDAPCache['h_domains_settings'][$domain][$cropName];
            }
        }

        return $this->settingsLDAPCache[$name] ?? null;
    }

    /**
     * Load and Cache settings from table "settings"
     *
     * @param null $name
     * @return array
     */
    public function getSettings($name = null)
    {
        if (!$this->settingsCache) {
            $settings = json_decode(
                $this->getTableRow('settings')['header'],
                true
            );
            $this->settingsCache = [];
            foreach ($settings as $s_key => $s_value) {
                if (is_array($s_value) && key_exists('v', $s_value)) {
                    $this->settingsCache[$s_key] = $s_value['v'];
                } else {
                    $this->settingsCache[$s_key] = $s_value;
                }
            }
            if (empty($this->settingsCache['totum_name'])) {
                $this->settingsCache['totum_name'] = $this->getSchema();
            }
        }


        if ($name) {
            return $this->settingsCache[$name] ?? null;
        }
        return $this->settingsCache;
    }

    public function globVar($name, $params = [])
    {
        static $sql = null;
        static $prepareInsertOrUpdate = null;
        static $prepareSelect = null;
        static $prepareSelectDefault = null;
        static $prepareSelectBlockFalse = null;

        if (empty($sql)) {
            $sql = $this->getSql(false);
        }

        $getPrepareSelect = function () use (&$prepareSelect, $sql) {
            if (!$prepareSelect) {
                $prepareSelect = $sql->getPrepared('select value, dt from _globvars where name = ?');
            }
            return $prepareSelect;
        };
        $getPrepareSelectBlocked = function ($interval) use ($sql): \PDOStatement {
            return $sql->getPrepared('WITH time AS(
    select now() + interval \'' . $interval . '\' as times
)
UPDATE _globvars SET blocked=
    CASE
        WHEN (blocked is null OR blocked<=now()) THEN (SELECT times FROM time)
        ELSE blocked
        END
WHERE name = :name
RETURNING value, dt, blocked, blocked=(SELECT times FROM time) as was_blocked');
        };
        $getPrepareSelectDefault = function () use (&$prepareSelectDefault, $sql) {
            if (!$prepareSelectDefault) {
                $prepareSelectDefault = $sql->getPrepared('INSERT INTO _globvars (name, value) 
VALUES (?,?)
ON CONFLICT (name) DO UPDATE 
  SET name = excluded.name RETURNING value, dt');
            }
            return $prepareSelectDefault;
        };
        $getPrepareSelectBlockedFalse = function () use (&$prepareSelectBlockFalse, $sql) {
            if (!$prepareSelectBlockFalse) {
                $prepareSelectBlockFalse = $sql->getPrepared('INSERT INTO _globvars (name) 
VALUES (?)
ON CONFLICT (name) DO UPDATE 
  SET blocked = NULL RETURNING value, dt');
            }
            return $prepareSelectBlockFalse;
        };
        $getPrepareInsertOrUpdate = function () use ($sql, &$prepareInsertOrUpdate) {
            if (!$prepareInsertOrUpdate) {
                $prepareInsertOrUpdate = $sql->getPrepared('INSERT INTO _globvars (name, value) 
VALUES (?,?)
ON CONFLICT (name) DO UPDATE 
  SET value = excluded.value, 
      blocked = null,
      dt = (\'now\'::text)::timestamp without time zone RETURNING value, dt');
            }
            return $prepareInsertOrUpdate;
        };


        $returnData = function ($prepare) use($params) {
            if ($data = $prepare->fetch()) {
                if ($params['date'] ?? false) {
                    list($date, $tail) = explode('.', $data['dt']);
                    return ['date' => $date, 'secpart'=>$tail, 'value' => json_decode($data['value'], true)['v']];
                } else {
                    return json_decode($data['value'], true)['v'];
                }
            } else {
                return null;
            }
        };

        try {

            if (key_exists('value', $params)) {
                $getPrepareInsertOrUpdate()->execute([$name, json_encode(
                    ['v' => $params['value']],
                    JSON_UNESCAPED_UNICODE
                )]);
                return $returnData($prepareInsertOrUpdate);
            } elseif (key_exists('default', $params)) {
                $getPrepareSelectDefault()->execute([$name, json_encode(
                    ['v' => $params['default']],
                    JSON_UNESCAPED_UNICODE
                )]);

                return $returnData($prepareSelectDefault);

            } elseif (key_exists('block', $params)) {
                if (!$params['block']) {
                    $getPrepareSelectBlockedFalse()->execute([$name]);
                    return $returnData($prepareSelectBlockFalse);
                } else {
                    while (true) {
                        $prepareSelectBlocked = $getPrepareSelectBlocked((float)$params['block'] . ' second');
                        $prepareSelectBlocked->execute(['name' => $name]);

                        if ($data = $prepareSelectBlocked->fetch()) {
                            if ($data['was_blocked']) {
                                if ($params['date'] ?? false) {
                                    return ['date' => $data['dt'], 'value' => json_decode($data['value'], true)['v']];
                                } else {
                                    return json_decode($data['value'], true)['v'];
                                }
                            }
                        } else {
                            return null;
                        }
                    }
                }
            } else {
                $getPrepareSelect()->execute([$name]);

                return $returnData($prepareSelect);
            }
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '42P01') {
                $sql->exec(
                    <<<SQL
create table "_globvars"
(
    name     text                                                      not null,
    value     jsonb,
    blocked     timestamp,
    dt        timestamp default ('now'::text)::timestamp without time zone not null
)
SQL
                );
                $sql->exec('create UNIQUE INDEX _globvars_name_index on _globvars (name)');
                return $this->globVar($name, $params);
            } else {
                throw new SqlException($exception->getMessage());
            }
        }
    }

    public function procVar($name = null, $params = [])
    {
        if (empty($name)) {
            return array_keys($this->procVars ?? []);
        }

        if (key_exists('value', $params)) {
            $this->procVars[$name] = $params['value'];
        } elseif (key_exists('default', $params)) {
            if (!key_exists($name, $this->procVars)) {
                $this->procVars[$name] = $params['default'];
            }
        }

        return $this->procVars[$name] ?? null;
    }

    public function getLangObj(): LangInterface
    {
        return $this->Lang;
    }

    public function superTranslate(mixed $data, $isCreator = false): mixed
    {
        if (
            //Мультиленг выключен
            !static::isSuperlang
            ) {
            return $data;
        }

        $sys_translate = function ($data) use (&$sys_translate) {
            if (is_array($data)) {
                $res = [];
                foreach ($data as $k => $v) {
                    $res[$sys_translate($k)] = $sys_translate($v);
                }
                return $res;
            } elseif (is_string($data)) {
                return preg_replace_callback("~\{\{[/a-zA-Z0-9,?'!_\-]+\}\}~",
                    function ($template) {
                        $this->langJsonTranslates = $this->langJsonTranslates ?? $this->loadUserTranslates('main');
                        return $this->langJsonTranslates[$template[0]] ?? $template[0];
                    },
                    $data
                );
            }
            return $data;
        };
        $data = $sys_translate($data);

        if ($this->userLangCreatorMode === 'NOT_TRANSLATE' || $this->userLangCreatorMode=== true) {
            return $data;
        }

        $lang_translate = function ($data, $inV = false) use (&$lang_translate, $isCreator) {
            if (is_array($data)) {
                $vt = [];
                foreach ($data as $k => $_v) {
                    if ($this->userLangCreatorMode === 'NOT_V_TRANSLATE' && $k === 'v') {
                        $vt[$k] = $_v;
                    } else {
                        $vt[$lang_translate($k)] =
                            $lang_translate($_v, ($inV || $k === 'v'));
                        if ($isCreator && !$inV && $k === 'v' && $vt['v'] !== $_v) {
                            $vt['t'] = $_v;
                        }
                    }
                }
                return $vt;
            } elseif (is_string($data)) {

                if (!$this->userLangCreatorMode || $this->userLangCreatorMode === 'NOT_V_TRANSLATE') {
                    $data = preg_replace_callback("~\{\[([/a-zA-Z0-9,?'!_\-]+)\]\}~",
                        function ($template) use ($data) {
                            $this->langLangsJsonTranslates = $this->langLangsJsonTranslates ?? $this->loadUserTranslates('langs');
                            return $this->langLangsJsonTranslates[$template[1]] ?? $template[0];
                        },
                        $data);
                    $data = preg_replace_callback("~\{\[(.+)\]\}~",
                        function ($template) use ($data) {
                            if (preg_match_all('/((?<lg>[a-z]{2})\s*:
\s*(["\'])
(?<tr>.*)
\3\s*
(;|$)
)+/xDU', $template[1], $matches, PREG_SET_ORDER)) {
                                $langs = [];
                                foreach ($matches as $match) {
                                    $langs[$match['lg']] = $match['tr'];
                                }
                                return $langs[$this->getLang()] ?? $langs[strtolower(static::LANG)] ?? $template[0];
                            }
                            return $template[0];
                        },
                        $data);
                }
                return $data;
            } else return $data;
        };

       return $lang_translate($data);

    }

    public function getTotumFooter()
    {
        $genTime = round(microtime(true) - $this->mktimeStart, 4);
        $mb = memory_get_peak_usage(true) / 1024 / 1024;
        if ($mb < 1) {
            $mb = '< 1 ';
        } else {
            $mb = round($mb, 2);
        }
        $memory_limit = ini_get('memory_limit');
        $SchemaName = $this->getSchema();
        $version = Totum::VERSION;

        return $this->translate('Page processing time: %s sec.<br/>
    RAM: %sM. of %s.<br/>
    Sql Schema: %s, V %s<br/>',
            [$genTime, $mb, $memory_limit, $SchemaName, $version]);
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->getLangObj()->translate($str, $vars);
    }

    public function getHiddenHosts(): array
    {
        return [];
    }

    public function setHiddenHostSettings(array $Settings)
    {
        if (!empty($Settings['lang'])) {
            if (!class_exists('totum\\common\\Lang\\' . strtoupper($Settings['lang']))) {
                throw new \Exception('Specified ' . $Settings['lang'] . ' language is not supported');
            }
            $newLang = new ('totum\\common\\Lang\\' . strtoupper($Settings['lang']))();
            if ($this->Lang::class !== $newLang::class) {
                $this->langLangsJsonTranslates = null;
                $this->langJsonTranslates = null;
                $this->Lang = $newLang;
            }
            $this->isLangFixed = true;
        }
    }

    protected array $techTables = ['ttm__prepared_data_import'];

    public function isTechTable(string $name)
    {
        return in_array($name, $this->techTables);
    }

    public function getThemesCss()
    {
        if ($css = trim($this->getSettings('h_custom_css') ?? '')) {
            return '<style>' . preg_replace('~<\s*/\s*style\s*~', '', $css) . '</style>';
        }
    }

    /**
     * @param string $type main|langs
     * @return array|mixed|void
     * @throws SqlException
     */
    protected function loadUserTranslates(string $type)
    {
        switch ($type) {
            case 'main':
                return json_decode(file_get_contents($this->getBaseDir() . 'totum/moduls/install/' . $this->getLang() . '.json'), true);
            case 'langs':
                $langField = 'lang_' . $this->getLang();
                $mainLangField = 'lang_' . strtolower(static::LANG);
                $fields = $langField;
                if ($langField !== $mainLangField) {
                    $fields .= ', ' . $mainLangField;
                }
                $fields .= ', template';
                $st = $this->Sql->getPDO()->prepare('select ' . $fields . ' from ttm__langs');
                $st->execute();
                $data = [];
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $data[json_decode($row['template'])->v] = json_decode($row[$langField])->v ?? json_decode($row[$mainLangField])->v ?? $row['template'];
                }
                return $data;
        }
    }

    function proGoModuleSocketSend(array $data, $close = false, $reCheckSchemaForce = false)
    {

        if (!$this->proGoModuleSocket || $reCheckSchemaForce) {
            $this->proGoModuleSocket = @stream_socket_client("unix://" . $this->getBaseDir() . "/socket", $errno, $errstr, 30);
            if (!$this->proGoModuleSocket) {
                errorException::criticalException("GOMODULE: " . $errstr, $this);
            }
            $schemaData = ['schema' => $this->getSchema(true)];
            if ($reCheckSchemaForce) {
                $schemaData['check'] = true;
            }
            if (($data["method"] ?? '') == "license") {
                $schemaData += $data;
                return $this->proGoModuleSocketSend($schemaData);
            }
            $this->proGoModuleSocketSend($schemaData);
        }
        if (!$data) {
            if ($close) {
                fclose($this->proGoModuleSocket);
                $this->proGoModuleSocket = null;
            }
            return;
        }


        $s = fwrite($this->proGoModuleSocket, json_encode($data, JSON_UNESCAPED_UNICODE));

        $result = stream_get_line($this->proGoModuleSocket, 0, "\r\n");

        if ($close) {
            fclose($this->proGoModuleSocket);
            $this->proGoModuleSocket = null;
        }
        try {
            $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
            if (key_exists('error', $result)) {
                errorException::criticalException("GOMODULE: " . $result['error'], $this);
            }
            return $result;
        } catch (criticalErrorException $e) {
            throw $e;
        } catch (\Exception $e) {
            errorException::criticalException('GOMODULE error: ' . $e->getMessage() . ':`' . $result . '`', $this);
        }

    }

}

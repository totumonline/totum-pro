<?php

namespace totum\common;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use totum\common\configs\ConfParent;
use totum\common\sql\Sql;
use totum\fieldTypes\File;

class OnlyOfficeConnector
{
    static $tableName = '_onlyofficedata';

    static protected OnlyOfficeConnector $self;

    static public function init(ConfParent $Config)
    {
        return $self ?? ($self = new static($Config));
    }

    protected function __construct(protected ConfParent $Config)
    {
    }


    public function isSwithedOn(): bool
    {
        return !!($this->getSettings()['host'] ?? false);
    }

    public function getConfig(Totum $Totum, bool $fileHttpPath, string $ext, string $title, string $fileName, array $tableData, bool $isShared = true, bool $isReadonly = false)
    {
        $tableData['users'] = [$Totum->getUser()->id];

        $configUrlViaKey = $fileHttpPath === false;
        $tableData['readOnly'] = $isReadonly;

        $config = [
            "document" => [
                "fileType" => $ext,
                "key" => $key = $this->getKey($fileName, $tableData, isConfigUrlViaKey: $configUrlViaKey, isShared: $isShared),
                "title" => $title,
                "url" => $configUrlViaKey ? 'https://' . $Totum->getConfig()->getMainHostName() . '/Table/?OnlyOfficeAction=getFile&key=' . $key : 'https://' . $this->Config->getMainHostName() . '/fls/' . $fileName,
                'permissions' => [
                    'edit' => !$isReadonly
                ]

            ],
            "documentType" => $this->getDocumentType($ext),
            "editorConfig" => [
                "callbackUrl" => 'https://' . $this->Config->getMainHostName() . $_SERVER['REQUEST_URI'] . '&OnlyOfficeAction=saveFile',
                'customization' => [
                    'forcesave' => false
                ],
                'user' => [
                    'group' => 'Group1',
                    'id' => (string)$Totum->getUser()->id,
                    'name' => $Totum->getUser()->fio
                ],

            ]
        ];
        $config['token'] = JWT::encode($config, $this->getSettings('token'), 'HS256');
        return ['config' => $config, 'script_src' => $this->getSettings('host') . '/web-apps/apps/api/documents/api.js'];
    }

    public function parseToken($token)
    {
        return JWT::decode($token, new Key($this->getSettings('token'), 'HS256'));
    }


    public function userSesstionClosed()
    {

    }

    public
    function dropLogoutUser(int $userId)
    {
        foreach ($this->getFileKeysByUser($userId) as $key) {
            $this->closeKey($key, $userId);
        }
    }

    /**
     * @throws errorException
     */
    protected
    function getDocumentType($type): string
    {
        return match ('.' . $type) {
            '.djvu', '.doc', '.docm', '.docx', '.docxf', '.dot', '.dotm', '.dotx', '.epub', '.fb2', '.fodt', '.htm', '.html', '.mht', '.mhtml', '.odt', '.oform', '.ott', '.oxps', '.pdf', '.rtf', '.stw', '.sxw', '.txt', '.wps', '.wpt', '.xml', '.xps' => 'word',
            '.csv', '.et', '.ett', '.fods', '.ods', '.ots', '.sxc', '.xls', '.xlsb', '.xlsm', '.xlsx', '.xlt', '.xltm', '.xltx' => 'cell',
            '.dps', '.dpt', '.fodp', '.odp', '.otp', '.pot', '.potm', '.potx', '.pps', '.ppsm', '.ppsx', '.ppt', '.pptm', '.pptx', '.sxi' => 'slide',
            default => throw new errorException('File type is not correct')
        };
    }

    protected
    function getSettings($key = null)
    {
        if ($key) {
            return $this->Config->getSettings('h_pro_olny_office')[$key] ?? false;
        }
        return $this->Config->getSettings('h_pro_olny_office');
    }

    public function checkOnlyOfficeIp($ip)
    {
        return (!$this->getSettings('ip') || $this->getSettings('ip') === $ip);
    }

    protected
    function getKey($fileName, $tableData, &$isConfigUrlViaKey, $isShared = true): string
    {
        $isConfigUrlViaKey = true;
        $tableData['file'] = $fileName;
        $tableData['shared'] = $isShared;
        $tableData['download_request'] = date('Y-m-d H:i:s');

        $tableData['md5'] = $this->getFileHashByFileName($fileName, ($tableData['isTmp'] ?? false ? 'tmp' : null));

        if (($data = $this->query('select * from ' . static::$tableName . ' where data->>\'file\'=? order by dt desc', [$fileName]))) {

            foreach ($data as $i => $_f) {
                $_fData = json_decode($_f['data'], true);
                if ($_fData['md5'] !== $tableData['md5']) {
                    $this->removeKey($_f['key']);
                    foreach ($_fData['users'] as $user) {
                        $this->sendCommand([
                            "c" => "drop",
                            "key" => $_f['key'],
                            "users" => [(string)$user]
                        ]);
                    }
                    unset($data[$i]);
                }
            }


            foreach ($data as $_f) {
                $_fData = json_decode($_f['data'], true);
                if ($_fData['readOnly'] ?? false) {
                    continue;
                }
                $userConnected = in_array($tableData['users'][0], $_fData['users']);
                if ($isShared && $_fData['shared']) {
                    if (!$userConnected) {
                        $this->query('update ' . static::$tableName . ' set data = data || jsonb_build_object(\'users\', array_to_json(array_append(ARRAY(SELECT jsonb_array_elements_text(data->\'users\'))::int[], ?))::jsonb);', [$tableData['users'][0]], true);
                    }

                    $this->updateKeyData($_f['key'], 'download_request', $tableData['download_request']);

                    if ($_fData['added'] ?? false) {
                        $isConfigUrlViaKey = true;
                    }
                    return $_f['key'];
                }
                if (!$isShared && !$_fData['shared'] && $userConnected) {
                    if ($_fData['added'] ?? false) {
                        $isConfigUrlViaKey = true;
                    }
                    $this->updateKeyData($_f['key'], 'download_request', $tableData['download_request']);
                    return $_f['key'];
                }
            }
        }

        $rowCount = 0;
        while (!$rowCount) {
            $key = bin2hex(random_bytes(10));
            $rowCount = $this->query('insert into ' . static::$tableName . ' (key, data) values (?,?) on conflict do nothing', [$key, json_encode($tableData)],
                isExec: true
            );
        }
        return $key;
    }

    protected
    function getSql(): Sql
    {
        static $PDO;

        return $PDO ?? ($PDO = $this->Config->getSql(false));
    }

    protected
    function query($query, $params, $isExec = false, $afterError = false)
    {
        try {

            $prepare = $this->getSql()->getPrepared($query);
            $prepare->execute($params);
            if ($isExec) {
                return $prepare->rowCount();
            }
            return $prepare->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $exception) {
            if ($afterError || $exception->getCode() != '42P01') {
                throw new criticalErrorException($exception->getMessage());
            }

            $this->getSql()->exec('CREATE TABLE ' . static::$tableName . '(
  dt timestamp NOT NULL default NOW()::timestamp,
  key text,
  data jsonb
)');
            $this->getSql()->exec('create unique index ' . static::$tableName . '_key_uindex
    on ' . static::$tableName . ' (key)');

            $this->query($query, $params, $isExec, true);
        }

    }

    public function getByKey($key, $dataKey = null): mixed
    {
        $data = $this->query('select ' . ($dataKey ? "data->'$dataKey' as data" : 'data') . ' from ' . static::$tableName . ' where key=?', [$key]);
        if (!$data) {
            throw new errorException($this->Config->getLangObj()->translate('File key is not exists or is expired'));
        }
        return json_decode($data[0]['data'], true);
    }

    public function removeKey($key)
    {
        $date = date_create();
        $date->modify('-1day');
        $this->query('delete from ' . static::$tableName . ' where key=? AND (data->>\'onSaving\' is null OR data->>\'onSaving\'!=\'true\' OR dt<?) ', [$key, $date->format('Y-m-d H:i:s')], true);
    }

    public function getFileFromDocumentsServer($url)
    {
        return file_get_contents($url, true, stream_context_create([
            'http' => [
                'header' => "User-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                'method' => 'GET'
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]));
    }

    public function callForceSave(string $fileKey, $user, bool $removeLastVersion)
    {
        $this->updateKeyData($fileKey, 'onSaving', true);
        if ($removeLastVersion) {
            $this->updateKeyData($fileKey, 'remove_last_version', $removeLastVersion);
        }

        $result = $this->sendCommand([
            'c' => 'forcesave',
            'key' => $fileKey,
            'userdata' => $user
        ]);

        return $result;
    }

    public function setSaved($fileKey, $extraKey = null, $extraValue = null)
    {
        if ($extraKey) {
            $this->updateKeyDatas($fileKey, 'onSaving', false, $extraKey, $extraValue);
        } else {
            $this->updateKeyData($fileKey, 'onSaving', false);
        }
    }

    protected function updateKeyData($fileKey, $dataKey, $value)
    {
        if (is_bool($value)) {
            $valueType = 'bool';
            $value = $value ? 'true' : 'false';
        } else $valueType = 'text';


        $tableName = static::$tableName;

        $this->query(<<<SQL
update $tableName set data = data || jsonb_build_object(?::text, ?::$valueType) where key=?
SQL
            , [$dataKey, $value, $fileKey], true);
    }

    protected function updateKeyDatas($fileKey, $dataKey, $value, $dataKey2, $value2)
    {
        if (is_bool($value)) {
            $valueType = 'bool';
            $value = $value ? 'true' : 'false';
        } else $valueType = 'text';

        if (is_bool($value2)) {
            $valueType2 = 'bool';
            $value2 = $value2 ? 'true' : 'false';
        } else $valueType2 = 'text';

        $tableName = static::$tableName;
        $this->query(<<<SQL
update $tableName set data = data || jsonb_build_object(?::text, ?::$valueType) || jsonb_build_object(?::text, ?::$valueType2) where key=?
SQL
            , [$dataKey, $value, $dataKey2, $value2, $fileKey], true);
    }

    public function closeKey(string $fileKey, int $userId, $onlyOnServer = false)
    {
        if (!$onlyOnServer) {
            $tableName = static::$tableName;
            if (!$this->query(<<<SQL
delete from $tableName where key=? AND (data->>'shared'='false' OR data->>'users'='[$userId]')
SQL
                , [$fileKey], true)) {
                $this->query('update ' . static::$tableName . ' set data = data || jsonb_build_object(\'users\', array_to_json(array_remove(ARRAY(SELECT jsonb_array_elements_text(data->\'users\'))::int[], ?))::jsonb) where key=?', [$userId, $fileKey]);
            }
        }
        $this->sendCommand([
            "c" => "drop",
            "key" => $fileKey,
            "users" => [(string)$userId]
        ]);

    }

    protected function getFileKeysByUser(int $userId)
    {
        $keys = [];
        foreach ($this->query('select key from ' . static::$tableName . ' where data->\'users\' @> ' . "'[$userId]'::jsonb", []) as $row) {
            $keys[] = $row['key'];
        }
        return $keys;
    }

    protected function sendCommand(array $data)
    {
        $data = ['token' => JWT::encode($data, $this->getSettings('token'), 'HS256')];

        return json_decode(file_get_contents($this->getSettings('host') . '/coauthoring/CommandService.ashx', true, stream_context_create([
            'http' => [
                'header' => "Content-type: application/json\r\nConnection: Close\r\n\r\n",
                'method' => 'POST',
                'content' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ])), true);
    }

    public function getFilePathByKey(string $key)
    {
        $dataFromKey = $this->getByKey($key);
        if ($dataFromKey['download_request'] < date('Y-m-d H:i:s', time() - 120)) {
            throw new errorException('Expired download link');
        }
        if ($dataFromKey['isTmp'] ?? false) {
            $filePath = $this->Config->getTmpDir() . $dataFromKey['file'];
        } else {
            $filePath = File::getFilePath($dataFromKey['file'], $this->Config);
        }
        if (!file_exists($filePath)) {
            throw new errorException($this->Config->getLangObj()->translate('File [[%s]] is not found.', $dataFromKey['file']));
        }
        return $filePath;
    }

    protected function getFileHashByFileName($fileName, $fileType)
    {
        $filePath = match ($fileType) {
            'tmp' => $this->Config->getTmpDir() . $fileName,
            'secure' => File::getFilePath($fileName, $this->Config, true),
            default => File::getFilePath($fileName, $this->Config),
        };
        if (!is_file($filePath)) {
            return null;
        }
        return md5_file($filePath);
    }

    public function tableActionByToken(string $token, ServerRequestInterface &$request, &$User, null|LoggerInterface $logger = null)
    {
        $error = 0;

        $dataToken = $this->parseToken($token);
        if ($dataToken->status === 2 || $dataToken->status === 4) {
            $this->removeKey($dataToken->key);
            $logger?->debug('removeKey: ' . $dataToken->key);
        } else if ($dataToken->status === 6) {
            $User = Auth::loadAuthUser($this->Config, ($dataToken->userdata ?? $dataToken->users[0]), false);

            $dataFromKey = $this->getByKey($dataToken->key);

            $logger?->debug('SAVING $dataFromKey: ' . json_encode((array)$dataFromKey));

            if ($dataFromKey['readOnly']) {
                $error = 'readOnly';
            } elseif (in_array($User->getId(), $dataFromKey['users'])) {

                if ($dataFromKey['isTmp'] ?? false) {
                    if (is_file($fileName = $this->Config->getTmpDir() . $dataFromKey['file'])) {
                        file_put_contents($fileName, $this->getFileFromDocumentsServer($dataToken->url));
                        $this->setSaved($dataToken->key);
                        $error = 0;
                    } else {
                        $error = 'File not found';
                    }
                } else {
                    $request = $request->withParsedBody([
                        'method' => 'editFile',
                        'data' => [
                            'fieldName' => $dataFromKey['field'],
                            'fileName' => $dataFromKey['file'],
                            'filestring' => $this->getFileFromDocumentsServer($dataToken->url),
                            'remove_last_version' => $dataFromKey['remove_last_version'] ?? false,
                        ]
                    ]);
                    $error = null;

                    $this->onDescruct[] = (function () use ($dataFromKey, $dataToken) {
                        if ($dataFromKey['remove_last_version'] ?? false) {
                            $this->setSaved($dataToken->key, 'remove_last_version', false);
                        } else {
                            $this->setSaved($dataToken->key);
                        }
                    });
                }
            } else {
                $error = 'Wrong user';
            }
        }
        return $error;
    }

    protected $onDescruct = [];

    public function __destruct()
    {
        foreach ($this->onDescruct as $func) {
            $func();
        }

    }

    public function checkFileHashes(string $filepath, string $fileName)
    {
        $hash = md5_file($filepath);
        if (($data = $this->query('select * from ' . static::$tableName . ' where data->>\'file\'=? order by dt desc', [$fileName]))) {
            foreach ($data as $i => $_f) {
                $_fData = json_decode($_f['data'], true);
                if ($_fData['md5'] !== $hash) {
                    if ($_fData['onSaving'] ?? false) {

                        if ($_fData['remove_last_version'] ?? false) {
                            $this->updateKeyData($data['key'], 'remove_last_version', false);
                        }
                        $this->setSaved($data['key'], 'md5', $hash);

                    } else {
                        $this->removeKey($_f['key']);
                        foreach ($_fData['users'] as $user) {
                            $this->sendCommand([
                                "c" => "drop",
                                "key" => $_f['key'],
                                "users" => [(string)$user]
                            ]);
                        }
                    }
                }
            }
        }

    }

}
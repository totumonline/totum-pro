<?php

namespace totum\common;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use totum\common\configs\ConfParent;
use totum\common\sql\Sql;

class OnlyOfficeConnector
{
    static $tableName = '_onlyofficedata';

    public function __construct(protected ConfParent $Config)
    {
    }


    public function isSwithedOn(): bool
    {
        return !!($this->getSettings()['host'] ?? false);
    }

    public function getConfig(Totum $Totum, string|bool $fileHttpPath, string $ext, string $title, string $fileName, array $tableData, bool $isShared = true)
    {
        $tableData['users'] = [$Totum->getUser()->id];

        $config = [
            "document" => [
                "fileType" => $ext,
                "key" => $key = $this->getKey($fileName, $tableData, $isShared, isSecured: $fileHttpPath === false),
                "title" => $title,
                "url" => $fileHttpPath ?: 'https://' . $Totum->getConfig()->getMainHostName() . '/Table/?OnlyOfficeAction=getFile&key=' . $key],
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
            ],

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

    protected
    function getKey($fileName, $tableData, $isShared = true, $isSecured = false): string
    {
        $tableData['file'] = $fileName;
        $tableData['shared'] = $isShared;
        $tableData['download_request'] = date('Y-m-d H:i:s');
        if (($data = $this->query('select * from ' . static::$tableName . ' where data->>\'file\'=? order by dt desc', [$fileName]))) {
            foreach ($data as $_f) {
                $_fData = json_decode($_f['data'], true);
                $userConnected = in_array($tableData['users'][0], $_fData['users']);
                if ($isShared && $_fData['shared']) {
                    if (!$userConnected) {
                        $_fData['users'][] = $tableData['users'][0];
                        $_fData['download_request'] = $tableData['download_request'];
                        $this->query('update ' . static::$tableName . ' set data=? where key=?', [json_encode($_fData), $_f['key']], true);
                    } else {
                        $this->updateKeyData($_f['key'], 'download_request', $tableData['download_request']);
                    }
                    return $_f['key'];
                }
                if (!$isShared && !$_fData['shared'] && $userConnected) {
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
            throw new errorException('Expired file key');
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

    public function callForceSave(string $fileKey, $user)
    {
        $this->updateKeyData($fileKey, 'onSaving', true);

        $result = $this->sendCommand([
            'c' => 'forcesave',
            'key' => $fileKey,
            'userdata' => $user
        ]);

        return $result;
    }

    public function setSaved($fileKey)
    {
        $this->updateKeyData($fileKey, 'onSaving', false);
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

}
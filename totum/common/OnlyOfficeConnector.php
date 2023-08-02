<?php

namespace totum\common;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use totum\common\configs\ConfParent;

class OnlyOfficeConnector
{
    public function __construct(protected ConfParent $Config)
    {
    }


    public function isSwithedOn(): bool
    {
        return !!($this->getSettings()['host'] ?? false);
    }

    public function getConfig($Totum, $fileHttpPath, $id, $fieldName, $ext, $title, $fileName, $tableCode, $isShared = true)
    {
        $config = [
            "document" => [
                "fileType" => $ext,
                "key" => $fieldName . '.' . $tableCode . '.' . str_replace('/', '-', $fileName),
                "title" => $title,
                "url" => $fileHttpPath
            ],
            "documentType" => $this->getDocumentType($ext),
            "editorConfig" => [
                "callbackUrl" => 'https://' . $this->Config->getMainHostName() . $_SERVER['REQUEST_URI'] . '&OnlyOfficeAction=saveFile',
                'customization' => [
                    'forcesave' => false
                ],
                'user' => [
                    'group' => 'Group1',
                    'id' => $Totum->getUser()->id,
                    'name' => $Totum->getUser()->fio
                ],
                'coediting' => [
                    'mode' => 'strict',
                    'change' => false
                ]
            ],

        ];
        $config['token'] = JWT::encode($config, $this->getSettings('token'), 'HS256');
        return ['config' => $config, 'script_src' => $this->getSettings('host') . '/web-apps/apps/api/documents/api.js'];
    }

    public function parseToken($token)
    {
        return JWT::decode($token, new Key($this->getSettings('token'), 'HS256'));
    }


    public function dropLogoutUser($user)
    {
        foreach ($this->getFileKeysByUser($user) as $key) {
            $this->sendCommand(['c' => 'drop',
                'key' => $key,
                'users' => ['6d5a81d0']]);
        }
    }

    /**
     * @throws errorException
     */
    protected function getDocumentType($type): string
    {
        return match ('.' . $type) {
            '.djvu', '.doc', '.docm', '.docx', '.docxf', '.dot', '.dotm', '.dotx', '.epub', '.fb2', '.fodt', '.htm', '.html', '.mht', '.mhtml', '.odt', '.oform', '.ott', '.oxps', '.pdf', '.rtf', '.stw', '.sxw', '.txt', '.wps', '.wpt', '.xml', '.xps' => 'word',
            '.csv', '.et', '.ett', '.fods', '.ods', '.ots', '.sxc', '.xls', '.xlsb', '.xlsm', '.xlsx', '.xlt', '.xltm', '.xltx' => 'cell',
            '.dps', '.dpt', '.fodp', '.odp', '.otp', '.pot', '.potm', '.potx', '.pps', '.ppsm', '.ppsx', '.ppt', '.pptm', '.pptx', '.sxi' => 'slide',
            default => throw new errorException('File type is not correct')
        };
    }

    protected function getSettings($key = null)
    {
        if ($key) {
            return $this->Config->getSettings('h_pro_olny_office')[$key] ?? false;
        }
        return $this->Config->getSettings('h_pro_olny_office');
    }

    protected function getKey()
    {

    }

    protected function query()
    {

    }

}
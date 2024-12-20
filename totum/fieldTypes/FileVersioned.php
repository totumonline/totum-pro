<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.02.18
 * Time: 13:51
 */

namespace totum\fieldTypes;

use totum\common\Auth;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\OnlyOfficeConnector;
use totum\common\sql\Sql;
use totum\config\Conf;
use totum\models\CalcsTableCycleVersion;
use totum\tableTypes\aTable;

class FileVersioned extends File
{

    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);
        $this->data['removeVersionsRoles'] = $this->data['removeVersionsRoles'] ?? [];
    }

    public function getFullValue($val, $rowId = null)
    {
        $valArray = ['v' => $val];
        static::addViewValues('edit', $valArray, []);
        return $valArray['v'];
    }

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        switch ($viewType) {
            case 'web':
            case 'edit':
                if (!empty($valArray['e'])) {
                    $valArray['v'] = [];
                }
                $superUser = empty($this->data['removeVersionsRoles']) || in_array($this->table->getUser()->getId(),
                        $this->data['removeVersionsRoles']);
                if (is_array($valArray['v'])) {
                    foreach ($valArray['v'] as &$file) {
                        if ($this->table->getUser()->getId() === ($file['versions'][0]['user'] ?? false)) {
                            if (!empty($this->data['versionsTimer'])) {
                                if (($file['versions'][0]['dt'] ?? false)) {
                                    $versionDt = date_create($file['versions'][0]['dt']);
                                    $versionDt->modify('+' . (int)$this->data['versionsTimer'] . ' minutes');

                                    if ($versionDt->format('Y-m-d H:i') > date('Y-m-d H:i')) {
                                        $file['version_timer'] = true;
                                        $file['version_remove'] = true;
                                    }
                                }
                            } else {
                                $file['version_remove'] = true;
                            }

                        }
                        if ($superUser) {
                            $file['file_remove'] = true;
                        }
                        $file['versions_count'] = !empty($file['versions']) && count($file['versions']) >= 2 ? count($file['versions']) : 0;
                        $file['version_comment'] = $file['versions'][0]['comment'] ?? '';
                        unset($file['versions']);
                    }
                    unset($file);
                } else {
                    $valArray['v'] = [];
                }
                break;
            default:
                parent::addViewValues($viewType, $valArray, $row, $tbl);

        }
    }

    protected static function deleteFile($fullFileName)
    {
        parent::deleteFile($fullFileName);
        array_map("unlink", glob("{$fullFileName}__V*"));
    }

    public function modify($channel, $changeFlag, $newVal, $oldRow, $row = [], $oldTbl = [], $tbl = [], $isCheck = false)
    {
        if ($changeFlag == self::CHANGED_FLAGS['changed']) {
            $newValObj = new \stdClass();
            $newValObj->channel = $channel;
            $newValObj->value = $newVal;
            $newVal = $newValObj;
        }

        return parent::modify($channel,
            $changeFlag,
            $newVal,
            $oldRow,
            $row,
            $oldTbl,
            $tbl,
            $isCheck); // TODO: Change the autogenerated stub
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck, $row)
    {
        if (is_object($modifyVal) && ($modifyVal->channel ?? false)) {
            $channel = $modifyVal->channel;
            $modifyVal = $modifyVal->value;
        }

        if (!$isCheck) {
            $deletedFiles = [];
            $checkRemoveLastVersion = function (&$file) use ($channel) {
                if (!empty($file['remove_last_version'])) {
                    if ($channel !== 'inner') {
                        if (empty($this->data['removeVersionsRoles']) || in_array($this->table->getUser()->getId(),
                                $this->data['removeVersionsRoles'])) ;
                        elseif (($file['versions'][0]['user'] ?? 0) === $this->table->getUser()->getId()) {
                            if ($this->data['versionsTimer'] ?? false) {
                                if (($file['versions'][0]['dt'] ?? false)) {
                                    $versionDt = date_create($file['versions'][0]['dt']);
                                    $versionDt->modify('+' . (2 * (int)$this->data['versionsTimer']) . ' minutes');
                                    if ($versionDt->format('Y-m-d H:i') <= date('Y-m-d H:i')) {
                                        throw new errorException($this->translate('The time to delete/replace the last file version has expired'));
                                    }
                                } else {
                                    unset($file['remove_last_version']);
                                }
                            }
                        } else {
                            unset($file['remove_last_version']);
                        }
                    }
                }
            };
            $checkComment = function (&$file) use ($channel) {
                if (key_exists('comment', $file)) {
                    if ($channel !== 'inner' && ($file['versions'][0]['user'] ?? 0) !== $this->table->getUser()->getId() && !empty($this->data['removeVersionsRoles']) && !in_array($this->table->getUser()->getId(),
                            $this->data['removeVersionsRoles'])) {
                        unset($file['comment']);
                    } else {
                        $file['comment'] = trim($file['comment']);
                    }
                }
            };
            if (is_object($modifyVal)) {

                if ($modifyVal->sign !== '+') {
                    throw new errorException($this->translate('Operation [[%s]] over files is not supported.',
                        $modifyVal->sign));
                };
                if (empty($oldVal) || !is_array($oldVal)) {
                    $oldVal = array();
                }
                if (empty($this->data['multiple'])) {
                    if ($oldVal) {
                        if ((empty($newVal->val['file']) || $oldVal[0]['file'] === $modifyVal->val['file'])) {
                            if (key_exists('filestring', $modifyVal->val)) {
                                $oldVal[0]['filestring'] = $modifyVal->val['filestring'];
                            } elseif (key_exists('filestringbase64', $modifyVal->val)) {
                                $oldVal[0]['filestringbase64'] = $modifyVal->val['filestringbase64'];
                            }
                            if ($modifyVal->val['remove_last_version'] ?? false) {
                                $oldVal[0]['remove_last_version'] = true;
                                $checkRemoveLastVersion($oldVal[0]);
                            }
                            if (key_exists('comment', $modifyVal->val)) {
                                $oldVal[0]['comment'] = $modifyVal->val['comment'];
                                $checkComment($oldVal[0]);
                            }
                            $modifyVal = $oldVal;
                        }
                    } else {
                        $modifyVal = $modifyVal->val;
                        $checkRemoveLastVersion($modifyVal);
                        $checkComment($modifyVal);
                        $modifyVal = [$modifyVal];
                    }
                } else {
                    if (key_exists('file', $modifyVal->val)) {
                        $found = false;
                        foreach ($oldVal as &$file) {
                            if ($file['file'] === ($modifyVal->val['file'] ?? false)) {
                                $found = true;
                                if (key_exists('filestring', $modifyVal->val)) {
                                    $file['filestring'] = $modifyVal->val['filestring'];
                                } elseif (key_exists('filestringbase64', $modifyVal->val)) {
                                    $file['filestringbase64'] = $modifyVal->val['filestringbase64'];
                                }
                                if ($modifyVal->val['remove_last_version'] ?? false) {
                                    $file['remove_last_version'] = true;
                                    $checkRemoveLastVersion($file);
                                }
                                if (key_exists('comment', $modifyVal->val)) {
                                    $file['comment'] = $modifyVal->val['comment'];
                                    $checkComment($file);
                                }
                            }
                        }
                        unset($file);
                        if (!$found) {
                            $modifyVal = array_merge($oldVal, [$modifyVal->val]);
                        } else {
                            $modifyVal = $oldVal;
                        }
                    } else {
                        $modifyVal = array_merge($oldVal, [$modifyVal->val]);
                    }
                }

            } elseif (!empty($oldVal) && is_array($oldVal)) {
                foreach ($modifyVal as &$file) {
                    unset($file['versions']);
                }
                unset($file);

                foreach ($oldVal as $fOld) {
                    if (is_array($fOld)) {
                        foreach ($modifyVal as &$file) {
                            if ($fOld['file'] === ($file['file'] ?? null)) {
                                $file['versions'] = $fOld['versions'] ?? [];
                                $checkRemoveLastVersion($file);
                                $checkComment($file);
                                unset($file);
                                continue 2;
                            }
                        }
                        unset($file);
                        if (str_starts_with(preg_replace('~^.*?([^/]+$)~',
                            '$1',
                            $fOld['file'] ?? ''),
                            $this->_getFprefix($row['id'] ?? null))) {
                            $deletedFiles[] = $fOld;
                        }
                    }
                }
            }
            foreach ($modifyVal as $i => $file) {
                if (($file['remove_last_version'] ?? false) && count($file['versions'] ?? []) < 2 && !(key_exists('filestring',
                            $file) || key_exists('filestringbase64', $file) || key_exists('tmpfile', $file))) {
                    unset($modifyVal[$i]);
                    if (str_starts_with($file['file'] ?? '',
                        $this->_getFprefix($row['id'] ?? null))) {
                        $deletedFiles[] = $file;
                    }
                }
            }

            if ($deletedFiles) {
                static::deleteFilesOnCommit($deletedFiles,
                    $this->table->getTotum()->getConfig(),
                    $this->data);
            }
        } else {
            $files = [];
            foreach ($oldVal as $val) {
                $files[$val['file']] = $val;
            }
            foreach ($modifyVal as &$file) {
                $file = array_intersect_key($file, array_flip(["name", 'file', 'tmpfile', 'size', 'ext', 'tmpfileName', 'tmpfileSize', 'remove_last_version', 'comment']));
                if ($file['file']) {
                    $file['versions'] = $files[$file['file']]['versions'];
                }
            }
            unset($file);
        }
        return $modifyVal;
    }


    protected
    function checkValByType(&$val, $row, $isCheck = false)
    {
        if (is_null($val) || $val === '' || $val === []) {
            return [];
        }

        if (!is_array($val)) {
            throw new criticalErrorException($this->translate('The data format is not correct for the File field.'));
        }


        $createTmpFile = function ($fileString, &$file) {
            $ftmpname = tempnam(
                $this->table->getTotum()->getConfig()->getTmpDir(),
                $this->table->getTotum()->getConfig()->getSchema() . '.' . $this->table->getUser()->getId() . '.'
            );
            file_put_contents($ftmpname, $fileString);

            if (!empty($file['gz'])) {
                `gzip $ftmpname`;
                $ftmpname .= '.gz';
                unset($file['gz']);
                $file['name'] .= '.gz';
            }
            $file['tmpfile'] = preg_replace('`^.*/([^/]+)$`', '$1', $ftmpname);

            static::checkAndCreateThumb($ftmpname,
                $file['name'] ?? $file['file'] ?? throw new criticalErrorException($this->translate('The data format is not correct for the File field.')));
        };

        /*Добавление через filestring и filestringbase64 */
        foreach ($val as &$file) {
            if (!empty($file['filestring'])) {
                $createTmpFile($file['filestring'], $file);
                unset($file['filestring']);
            } elseif (!empty($file['filestringbase64'])) {
                $createTmpFile(base64_decode($file['filestringbase64']), $file);
                unset($file['filestringbase64']);
            }
        }
        unset($file);

        /*----------------*/

        if (!$isCheck && ($this->data['category'] !== 'column' || ($row['id'] ?? null))) {
            $fPrefix = $this->_getFprefix($row['id'] ?? null);

            $folder = '';
            if (!empty($this->data['customFileFolder'])) {
                $folder = $this->data['customFileFolder'] . '/';

                if (!empty($this->data['fileIdDivider'])) {
                    $id = $this->table->getCycle()?->getId() ?? $row['id'] ?? 0;

                    $folder_id = ($id - ($id % $this->data['fileIdDivider'])) / $this->data['fileIdDivider'];
                    $folder_id = str_pad($folder_id, 7, "0", STR_PAD_LEFT);
                    $folder .= $folder_id . '/';
                    unset($folder_id);
                }
            }

            if ($folder) {
                if (!is_dir($dir = $this->table->getTotum()->getConfig()->getSecureFilesDir() . $folder)) {
                    mkdir($dir, 0755, true);
                }
            }

            $funcGetFname = function ($ext) use ($fPrefix, $folder) {
                $fnum = 0;

                do {
                    $unlinked = false;

                    $fname = static::getFilePath(
                        $folder
                        . $fPrefix
                        . ($fnum ? '_' . $fnum : '') //Номер
                        . (!empty($this->data['nameWithHash']) ? '_' . md5(microtime(1) . $this->data['name']) : '') //хэш
                        . '.' . $ext,
                        $this->table->getTotum()->getConfig(),
                        $this->data
                    );

                    if (!$this->data['multiple'] && $this->table->getTableRow()['type'] !== 'tmp') {
                        break;
                    }

                    ++$fnum;

                    if (is_file($fname) &&
                        (
                            (filesize($fname) === 0 && filemtime($fname) < time() - 10 * 60)
                            || ($this->table->getTableRow()['type'] === 'tmp' && filemtime($fname) < time() - 24 * 60 * 60)
                        )

                    ) {
                        if (unlink($fname)) {
                            $fnum--;
                        }
                        $unlinked = true;
                    }
                } while ($unlinked || (!@fopen($fname, 'x') && $fnum < 1030));


                if ($fnum === 1030) {
                    die($this->translate('File name search error.'));
                }
                return $fname;
            };

            $vals = [];
            foreach ($val as $file) {
                $fl = [];
                if (!is_array($file) || !array_key_exists('name', $file)) {
                    throw new criticalErrorException($this->translate('The data format is not correct for the File field.'));
                }

                $file['ext'] = preg_replace('/^.*\.([a-z0-9]{1,10})$/', '$1', strtolower($file['name']));

                if (empty($file['ext'])) {
                    throw new criticalErrorException($this->translate('The file must have an extension.'));
                }
                if (in_array(
                    $file['ext'],
                    ['php', 'phtml']
                )) {
                    throw new criticalErrorException($this->translate('Restricted to add executable files to the server.'));
                }

                if ($file['ext'] === 'jpeg') {
                    $file['ext'] = 'jpg';
                }

                if (!empty($file['file']) && empty($file['versions'])) {
                    $file['versions'] = [[
                        'file' => $file['file'],
                        'size' => $file['size'],
                        'user' => '',
                        'dt' => '',
                    ]];
                }

                if (!empty($file['tmpfile'])) {
                    if (!is_file($ftmpname = $this->table->getTotum()->getConfig()->getTmpDir() . $file['tmpfile'])) {
                        die('{"error":"Temporary file not found"}');
                    }

                    /*Добавляем версию к сущесвующему файлу*/
                    if (!empty($file['file'])) {
                        $fname = static::getFilePath($file['file'],
                            $this->table->getTotum()->getConfig(),
                            $this->data);

                        if (empty($file['remove_last_version'])) {
                            $vNumber = (int)preg_replace('/^.*__V(\d+).*$/',
                                    '$1',
                                    $file['versions'][1]['file'] ?? '') + 1;
                            $fVersionName = $file['file'] . '__V' . str_pad($vNumber,
                                    4,
                                    '0',
                                    STR_PAD_LEFT) . '.' . $file['ext'];
                            $file['versions'][0]['file'] = $fVersionName;

                            $fVersionName = static::getFilePath($fVersionName,
                                $this->table->getTotum()->getConfig(),
                                $this->data);
                        } else {
                            array_splice($file['versions'], 0, 1);
                        }

                    } else {
                        $fname = $funcGetFname($file['ext']);
                        $file['versions'] = [];
                    }

                    array_unshift($file['versions'], [
                        'file' => $folder ? preg_replace('~.*?/(' . preg_quote($folder, '~') . '[^/]+$)~',
                            '$1',
                            $fname) : preg_replace('/^.*\/([^\/]+)$/', '$1', $fname),
                        'size' => filesize($ftmpname),
                        'user' => $this->table->getUser()->getId(),
                        'dt' => date('Y-m-d H:i'),
                    ]);


                    static::$transactionCommits[$fname] = $ftmpname;

                    $this->table->getTotum()->getConfig()->getSql()->addOnCommit(function () use ($ftmpname, $fname, &$fVersionName, &$fl) {
                        if ($fVersionName) {
                            if (!rename($fname, $fVersionName)) {
                                die(json_encode(['error' => $this->translate('Failed to move file to version.')]));
                            }
                            if (is_file($fname . '_thumb.jpg')) {
                                rename($fname . '_thumb.jpg', $fVersionName . '_thumb.jpg');
                            } elseif (is_file($fname . File::DOC_PREVIEW_POSTFIX)) {
                                rename($fname . File::DOC_PREVIEW_POSTFIX, $fVersionName . File::DOC_PREVIEW_POSTFIX);
                            }
                        }
                        if (!copy($ftmpname, $fname)) {
                            die(json_encode(['error' => $this->translate('Failed to copy a temporary file.')]));
                        }
                        $OnlyOfficeConnector = OnlyOfficeConnector::init($this->table->getTotum()->getConfig());
                        if ($OnlyOfficeConnector->isSwithedOn()) {
                            $OnlyOfficeConnector->checkFileHashes($fname, $fl['file']);
                        }

                        if (is_file($ftmpname . '_thumb.jpg')) {
                            if (!copy($ftmpname . '_thumb.jpg', $fname . '_thumb.jpg')) {
                                die(json_encode(['error' => $this->translate('Failed to copy preview.')],
                                    JSON_UNESCAPED_UNICODE));
                            }
                        } elseif (is_file($fname . File::DOC_PREVIEW_POSTFIX)) {
                            unlink($fname . File::DOC_PREVIEW_POSTFIX);
                        }
                        unset(static::$transactionCommits[$fname]);
                    });


                    $fl['size'] = filesize($ftmpname);
                    $fl['ext'] = $file['ext'];
                    $fl['file'] = $folder ? preg_replace('~.*?/(' . preg_quote($folder, '~') . '[^/]+$)~',
                        '$1',
                        $fname) : preg_replace('/^.*\/([^\/]+)$/', '$1', $fname);
                } elseif (!empty($file['file'])) {

                    $filepath = static::getFilePath($file['file'],
                        $this->table->getTotum()->getConfig(),
                        $this->data);

                    if (!empty($file['remove_last_version'])) {
                        array_shift($file['versions']);
                        $oldFile = $file['versions'][0]['file'];
                        $oldFile = static::$transactionCommits[$filepath] = static::getFilePath($oldFile,
                            $this->table->getTotum()->getConfig(),
                            $this->data);
                        $file['versions'][0]['file'] = $file['file'];
                        $file['size'] = $file['versions'][0]['size'];

                        $this->table->getTotum()->getConfig()->getSql()->addOnCommit(function () use ($oldFile, $filepath, &$fl) {
                            if (!copy($oldFile, $filepath)) {
                                die(json_encode(['error' => $this->translate('Failed to copy a temporary file.')]));
                            }
                            unlink($oldFile);
                            $OnlyOfficeConnector = OnlyOfficeConnector::init($this->table->getTotum()->getConfig());
                            if ($OnlyOfficeConnector->isSwithedOn()) {
                                $OnlyOfficeConnector->checkFileHashes($filepath, $fl['file']);
                            }

                            if (is_file($oldFile . File::DOC_PREVIEW_POSTFIX)) {
                                copy($oldFile . File::DOC_PREVIEW_POSTFIX, $filepath . File::DOC_PREVIEW_POSTFIX);
                                unlink($oldFile . File::DOC_PREVIEW_POSTFIX);
                            }
                            unset(static::$transactionCommits[$filepath]);
                        });
                    }


                    $fl['file'] = $file['file'];

                    if (key_exists($filepath, static::$transactionCommits)) ; elseif (!is_file($filepath)) {
                        if ($isCheck) {
                            throw new errorException($this->translate('Field [[%s]] is not found.', $file['name']));
                        }
                        $file['size'] = 0;
                        $fl['e'] = 'Файл не найден';
                    } else {
                        if (!str_starts_with(preg_replace('~^.*?([^/]+$)~', '$1', $file['file'] ?? ''),
                                $fPrefix) && !empty($this->data['fileDuplicateOnCopy'])) {
                            $fname = $funcGetFname($file['ext']);

                            $otherfname = static::getFilePath($file['file'],
                                $this->table->getTotum()->getConfig(),
                                $this->data);

                            static::$transactionCommits[$fname] = $otherfname;

                            $file['versions'] = [];

                            $this->table->getTotum()->getConfig()->getSql()->addOnCommit(function () use ($otherfname, $fname) {
                                if (!copy($otherfname, $fname)) {
                                    die(json_encode(['error' => $this->translate('Error copying a file to the storage folder.')],
                                        JSON_UNESCAPED_UNICODE));
                                }
                                if (is_file($otherfname . '_thumb.jpg')) {
                                    copy($otherfname . '_thumb.jpg', $fname . '_thumb.jpg');
                                } elseif (is_file($fname . File::DOC_PREVIEW_POSTFIX)) {
                                    unlink($fname . File::DOC_PREVIEW_POSTFIX);
                                }
                                unset(static::$transactionCommits[$fname]);
                            });
                            $fl['file'] = $folder ? preg_replace('~.*?/(' . preg_quote($folder, '~') . '[^/]+$)~',
                                '$1',
                                $fname) : preg_replace('/^.*\/([^\/]+)$/', '$1', $fname);
                        }
                        if (!$file['size']) {
                            $file['size'] = filesize($filepath);
                        }
                    }

                    $fl['size'] = $file['size'];
                    $fl['ext'] = $file['ext'];
                }

                if (key_exists('comment', $file)) {
                    $file['versions'][0]['comment'] = $file['comment'];
                }

                $fl['versions'] = $file['versions'];
                $fl['name'] = $file['name'];
                $vals[] = $fl;
            }
            $val = $vals;
        }
    }

}

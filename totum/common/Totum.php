<?php

namespace totum\common;

use totum\common\calculates\CalculateAction;
use totum\common\configs\TablesModelsTrait;
use totum\common\Lang\RU;
use totum\common\logs\ActionsLog;
use totum\common\logs\CalculateLog;
use totum\common\logs\OutersLog;
use totum\common\sql\Sql;
use totum\config\Conf;
use totum\models\traits\WithTotumTrait;
use totum\models\CalcsTableCycleVersion;
use totum\models\NonProjectCalcs;
use totum\models\Table;
use totum\models\TablesFields;
use totum\tableTypes\aTable;
use totum\tableTypes\cyclesTable;
use totum\tableTypes\globcalcsTable;
use totum\tableTypes\RealTables;
use totum\tableTypes\simpleTable;
use totum\tableTypes\tmpTable;

/**
 * Class Totum
 * @package totum\common
 */
class Totum
{
    public const VERSION = '6.14.61.0-8.3';


    public const TABLE_CODE_PARAMS = ['row_format', 'table_format', 'on_duplicate', 'default_action'];
    public const FIELD_ROLES_PARAMS = ['addRoles', 'logRoles', 'webRoles', 'xmlRoles', 'editRoles', 'xmlEditRoles', 'removeVersionsRoles'];
    public const FIELD_CODE_PARAMS = ['code', 'codeSelect', 'codeAction', 'format'];
    public const TABLE_ROLES_PARAMS = [
        'csv_edit_roles',
        'csv_roles',
        'delete_roles',
        'duplicate_roles',
        'edit_roles',
        'insert_roles',
        'order_roles',
        'read_roles',
        'tree_off_roles'];

    protected $interfaceData = [];
    /**
     * @var Conf
     */
    private $Config;
    private TotumMessenger $Messenger;
    /**
     * @var User
     */
    private $User;
    private $tablesInstances = [];
    protected $fieldsCache = [];
    protected $changedTables = [];
    protected $totumLogger;
    protected $cacheCycles = [];
    /**
     * @var array
     */
    protected $interfaceLinks = [];
    /**
     * @var array
     */
    protected $panelLinks = [];
    /**
     * @var OutersLog
     */
    protected $outersLogger;
    /**
     * @var CalculateLog
     */
    protected $CalculateLog;
    protected $fieldObjectsCachesVar;
    protected array $orderFieldCodeErrors = [];
    protected array $creatorWarnings = [];
    protected mixed $tablesUpdated;


    /**
     * Totum constructor.
     * @param Conf $Config
     * @param User|null $User $User
     */
    public function __construct(Conf $Config, User $User = null)
    {
        $this->Config = $Config;
        $this->User = $User;
        $this->CalculateLog = new CalculateLog();
        if ($User) {
            $this->Config->setUserData($User);
        }
    }

    public static function getTableClass($tableRow)
    {
        $table = '\\totum\\tableTypes\\' . $tableRow['type'] . 'Table';
        return $table;
    }

    public static function isRealTable($tableRow)
    {
        return is_subclass_of(static::getTableClass($tableRow), RealTables::class);
    }

    public function addOrderFieldCodeError(aTable $Table, string $nameVar)
    {
        $this->orderFieldCodeErrors[$Table->getTableRow()['name']][$nameVar] = 1;
    }

    public function addCreatorWarnings($warningText)
    {
        $this->creatorWarnings[$warningText] = 1;
    }

    public function getCreatorWarnings()
    {
        return $this->creatorWarnings;
    }


    public function getMessenger()
    {
        return $this->Messenger = $this->Messenger ?? new TotumMessenger();
    }

    /**
     * @return array
     */
    public function getOrderFieldCodeErrors(): array
    {
        return $this->orderFieldCodeErrors;
    }

    public function getInterfaceDatas()
    {
        return $this->interfaceData;
    }

    public function getInterfaceLinks()
    {
        return $this->interfaceLinks;
    }

    /**
     * TODO выделить в дочерний объект
     *
     * @param string $type data|table|json|diagramm|notify
     * @param $data
     * @param bool $refresh
     * @param array $elseData
     */
    public function addToInterfaceDatas(string $type, $data, $refresh = false, $elseData = [])
    {
        $data['refresh'] = $data['refresh'] ?? $refresh;
        $data['elseData'] = $elseData;
        $this->interfaceData[] = [$type, $data];
    }

    /**
     * @param $table
     * @param null|array $data
     * @param null|array $dataList
     * @param null|int $after
     * @throws errorException
     */
    public function actionInsert($table, $data = null, $dataList = null, $after = null)
    {
        $this->getTable($this->getTableRow($table))->actionInsert($data, $dataList, $after);
    }

    /**
     * @param array|int|string $where
     * @param bool $force
     * @return array|null
     */
    public function getTableRow($where, $force = false)
    {
        if (is_array($where) && key_exists('name', $where) && key_exists('id', $where)) {
            return $where;
        }
        return $this->Config->getTableRow($where, $force);
    }

    /**
     * @param string|int|array $table
     * @return bool
     */
    public function tableExists($table)
    {
        return !!$this->Config->getTableRow($table);
    }

    /**
     * @param string $tableName
     */
    public function tableChanged(string $tableName)
    {
        $this->changedTables[$tableName] = true;
        if (count($this->changedTables) === 1) {
            $this->Config->getSql()->addOnCommit(function () {
                $this->searchIndexUpdate();
            });
        }
    }

    /**
     * @return bool
     */
    public function isAnyChages()
    {
        return !!$this->changedTables;
    }

    protected function searchIndexUpdate()
    {
        $updates = [];
        $deletes = [];

        $searchTables = null;


        foreach ($this->changedTables as $name => $_) {
            $TableRow = $this->getTableRow($name);
            if ($TableRow['type'] != 'tmp' && $TableRow['type'] != 'calcs') {
                $Table = $this->getTable($TableRow);
                if (key_exists('ttm_search', $Table->getFields()) && $this->getTable('ttm__search_settings')->getByParams(['field' => 'h_get_updates'])) {
                    $searchTables = $searchTables ?? $this->getTable('ttm__search_settings')->getByParams(
                        ['field' => 'table_id'],
                        'list'
                    );
                    $tableId = $TableRow['id'];
                    if (in_array($tableId, $searchTables)) {
                        $pkCreate = function ($id) use ($tableId) {
                            return $tableId . '-' . $id;
                        };

                        $changedIds = $Table->getChangeIds();
                        foreach (['restored',
                                     'added',
                                     'changed'] as $operation) {
                            foreach (array_keys($changedIds[$operation]) as $id) {
                                if (empty($Table->getTbl()['rows'][$id]['ttm_search']['v'])) {
                                    $deletes[] = $pkCreate($id);
                                } else {
                                    if (!is_array($Table->getTbl()['rows'][$id]['ttm_search']['v'])) {
                                        errorException::criticalException(
                                            $this->translate(
                                                'Check that the ttm__search field type in table %s is data',
                                                $Table->getTableRow()['name']
                                            ),
                                            $this
                                        );
                                    }
                                    $updates[] = array_merge(
                                        $Table->getTbl()['rows'][$id]['ttm_search']['v'],
                                        ['pk' => $pkCreate($id), 'table' => (string)$tableId]
                                    );
                                }
                            }
                        }

                        foreach (array_keys($changedIds['deleted']) as $id) {
                            $deletes[] = $pkCreate($id);
                        }
                    }
                }
            }
        }
        if ($updates || $deletes) {
            $SearchTable = $this->getTable('ttm__search_settings');
            $Calc = new CalculateAction('=: exec(code: \'h_connect_code\'; var: "posts" = $#posts; var: "path"= str`"/indexes/"+#h_index_name+"/"+$#path`)');
            if ($updates) {
                $Calc->execAction(
                    'KOD',
                    $SearchTable->getTbl()['params'],
                    $SearchTable->getTbl()['params'],
                    $SearchTable->getTbl(),
                    $SearchTable->getTbl(),
                    $SearchTable,
                    'exec',
                    [
                        'posts' => json_encode($updates, JSON_UNESCAPED_UNICODE),
                        'path' => 'documents'
                    ]
                );
            }
            if ($deletes) {
                $Calc->execAction(
                    'KOD',
                    $SearchTable->getTbl()['params'],
                    $SearchTable->getTbl()['params'],
                    $SearchTable->getTbl(),
                    $SearchTable->getTbl(),
                    $SearchTable,
                    'exec',
                    [
                        'posts' => json_encode($deletes, JSON_UNESCAPED_UNICODE),
                        'path' => 'documents/delete-batch'
                    ]
                );
            }
        }
    }


    /**
     * @param array|int|string $table
     * @param null $extraData
     * @param bool $light - возможно, не используется
     * @return aTable
     * @throws errorException
     */
    public function getTable(array|int|string $table, $extraData = null, $light = false, $forceNew = false): aTable
    {
        if (is_array($table)) {
            $tableRow = $table;
        } else {
            $tableRow = $this->Config->getTableRow($table);
        }

        if (empty($tableRow)) {
            throw new errorException($this->translate('Table [[%s]] is not found.', $table));
        } elseif (empty($tableRow['type'])) {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            throw new errorException($this->translate('Table type is not defined.'));
        }
        if (is_array($tableRow['type'])) {
            debug_print_backtrace();
            die;
        }

        $cacheString = $tableRow['id'] . ';' . $extraData;

        if ($forceNew) {
            unset($this->tablesInstances[$cacheString]);
        }
        if ($tableRow['type'] === 'tmp' && empty($extraData)) {
            /** @var tmpTable $tableTmp */
            /** @var tmpTable $table */
            $tableTmp = tmpTable::init($this, $tableRow, $this->getCycle(0, 0), $light, $extraData);
            $cacheString = $tableRow['id'] . ';' . $tableTmp->getTableRow()['sess_hash'];
            $this->tablesInstances[$cacheString] = $tableTmp;
            $isNewTable = true;
        } elseif (($isNewTable = !key_exists($cacheString, $this->tablesInstances))) {
            switch ($tableRow['type']) {
                case 'globcalcs':
                    $this->tablesInstances[$cacheString] = globcalcsTable::init($this, $tableRow, $light);
                    break;
                case 'calcs':
                    $Cycle = $this->getCycle($extraData, $tableRow['tree_node_id']);
                    $this->tablesInstances[$cacheString] = $Cycle->getTable($tableRow, $light);
                    break;
                case 'tmp':
                    /** @var tmpTable $table */
                    $this->tablesInstances[$cacheString] = tmpTable::init(
                        $this,
                        $tableRow,
                        $this->getCycle(0, 0),
                        $light,
                        $extraData
                    );
                    break;
                case 'simple':
                    $this->tablesInstances[$cacheString] = simpleTable::init($this, $tableRow, $extraData, $light);
                    break;
                case 'cycles':
                    $this->tablesInstances[$cacheString] = cyclesTable::init($this, $tableRow, $extraData, $light);
                    break;
                default:
                    errorException::criticalException(
                        $this->translate(
                            'The [[%s]] table type is not connected to the system.',
                            $tableRow['type']
                        ),
                        $this
                    );
            }
        }

        if ($isNewTable) {
            $this->tablesInstances[$cacheString]->addCalculateLogInstance($this->CalculateLog->getChildInstance(['table' => $this->tablesInstances[$cacheString]]));
        }
        return $this->tablesInstances[$cacheString];
    }

    public function getCycle($id, $cyclesTableId)
    {
        $id = (int)$id;
        $cyclesTableId = (int)$cyclesTableId;
        $hashKey = $cyclesTableId . ':' . $id;

        if (!key_exists($hashKey, $this->cacheCycles)) {
            $this->cacheCycles[$hashKey] = new Cycle($id, $cyclesTableId, $this);
        }

        return $this->cacheCycles[$hashKey];
    }

    public function deleteCycle($id, $cyclesTableId)
    {
        $cycle = $this->getCycle($id, $cyclesTableId);
        $cycle->delete();

        $id = (int)$id;
        $cyclesTableId = (int)$cyclesTableId;
        $hashKey = $cyclesTableId . ':' . $id;

        unset($this->cacheCycles[$hashKey]);
    }

    public function getNamedModel(string $className, $isService = false): Model
    {
        return $this->getModel(Conf::getTableNameByModel($className), $isService);
    }

    public function getModel(string $tableName, $isService = false): Model
    {
        $m = $this->Config->getModel($tableName, null, $isService);

        if (key_exists(WithTotumTrait::class, class_uses($m))) {
            /** @var WithTotumTrait $m */
            $m->addTotum($this);
        }
        return $m;
    }

    public function getConfig(): Conf
    {
        return $this->Config;
    }

    public function transactionStart()
    {
        $this->Config->getSql()->transactionStart();
    }

    public function transactionCommit()
    {
        $this->Config->getSql()->transactionCommit();
    }

    /**
     * Задание типа собираемых расчетных логов
     *
     * @param array $types
     */
    public function setCalcsTypesLog(array $types)
    {
        $this->CalculateLog->setLogTypes($types);
    }

    public function addToInterfaceLink($uri, $target, $title = '', $postData = null, $width = null, $refresh = false, $elseData = [])
    {
        $this->interfaceLinks[] = ['uri' => $uri, 'target' => $target, 'title' => $title, 'postData' => $postData, 'width' => $width, 'refresh' => $refresh, 'elseData' => $elseData];
    }

    public function addLinkPanel($link, $id, $field, $refresh, $fields = [], $columns = null, $titles = [], $settings = [])
    {
        $this->panelLinks[] = ['uri' => $link, 'id' => $id, 'field' => $field, 'refresh' => $refresh, 'fields' => $fields, 'columns' => $columns, 'titles' => $titles, 'settings' => $settings];
    }

    /* Сюда можно будет поставить общую систему кешей */
    public function getFieldsCaches($tableId, $version)
    {
        $cache = $tableId . '/' . $version;
        if (key_exists($cache, $this->fieldsCache)) {
            return $this->fieldsCache[$cache];
        }
        return null;
    }

    public function setFieldsCaches($tableId, $version, array $fields)
    {
        $cache = $tableId . '/' . $version;
        $this->fieldsCache[$cache] = $fields;
    }

    public function getUser(): User
    {
        if (!$this->User) {
            errorException::criticalException($this->translate('Authorization lost.'), $this);
        }
        return $this->User;
    }

    public function totumActionsLogger()
    {
        if (!$this->totumLogger) {
            if (!$this->User) {
                errorException::criticalException($this->translate('Authorization lost.'), $this);
            }
            $this->totumLogger = new ActionsLog($this);
        }

        return $this->totumLogger;
    }

    /**
     * @return CalculateLog
     */
    public function getCalculateLog(): CalculateLog
    {
        return $this->CalculateLog;
    }

    /**
     * @return array
     */
    public function getPanelLinks(): array
    {
        return $this->panelLinks;
    }

    public function transactionRollback()
    {
        $this->Config->getSql()->transactionRollBack();
    }

    public function fieldObjectsCaches(string $staticName, \Closure $getField)
    {
        return $this->fieldObjectsCachesVar[$staticName] ?? $this->fieldObjectsCachesVar[$staticName] = $getField();
    }


    public function getOutersLogger()
    {
        return $this->outersLogger ?? $this->outersLogger = new OutersLog($this, $this->User->getId());
    }

    public function clearTables()
    {
        $this->tablesInstances = [];
        $this->fieldsCache = [];
    }

    public function getSpecialInterface()
    {
        return null;
    }

    public function getLangObj(): Lang\LangInterface
    {
        return $this->Config->getLangObj();
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->getLangObj()->translate($str, $vars);
    }


    protected array $hashes = [];

    function hashValue($type, $hash = null, $value = '*GET*')
    {
        if (!is_null($hash)) {
            $hash = (string)$hash;
        }

        if ($value === '*GET*') {
            return $this->hashes[$type][$hash] ?? null;
        }

        if (is_null($hash)) {
            do {
                $hash = "h" . substr((float)microtime(), 2, 5);
            } while (key_exists($hash, $this->hashes[$type] ?? []));
        }
        $this->hashes[$type][$hash] = $value;

        return $hash;
    }

    public function addTableUpdated(int|string $id, string $updated)
    {
        if (empty($this->tablesUpdated)) {
            $this->Config->getSql()->addOnCommit(function () {
                $this->Config->proGoModuleSocketSend([
                    'method' => 'tableUpdates',
                    'updates' => $this->tablesUpdated
                ]);
            });
        }
        if (is_int($id)) {
            $id = "$id/0";
        }
        $this->tablesUpdated[$id] = json_decode($updated, true);
        $this->tablesUpdated[$id]['code'] = "{$this->tablesUpdated[$id]['code']}";
    }

    protected array $onEnd = [];
    public function addOnEnd(\Closure $param)
    {
        $this->onEnd[]=$param;
    }

    /*Использовать только для "дернуть гом" и прочих неважных транзакционно штук*/
    public function __destruct()
    {
        foreach ($this->onEnd as $f) {
            $f();
        }
    }
}

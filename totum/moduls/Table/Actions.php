<?php


namespace totum\moduls\Table;

use JetBrains\PhpStorm\ArrayShape;
use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\calculates\CalculcateFormat;
use totum\common\errorException;
use totum\common\Field;
use totum\common\FormatParamsForSelectFromTable;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\Totum;
use totum\common\User;
use totum\fieldTypes\Select;
use totum\models\Table;
use totum\models\TablesFields;
use totum\models\Tree;
use totum\tableTypes\aTable;

class Actions
{
    /**
     * @var aTable
     */
    protected $Table;
    /**
     * @var Totum
     */
    protected $Totum;
    /**
     * @var User|null
     */
    protected $User;
    /**
     * @var array|object|null
     */
    protected $post;
    /**
     * @var ServerRequestInterface
     */
    protected $Request;

    protected $modulePath;
    protected $isServicesBlocked = true;

    public $withLog = true;
    protected array $Cookies = [];


    public function unblockServices()
    {
        $this->isServicesBlocked = false;
    }

    public function __construct(ServerRequestInterface $Request, string $modulePath, aTable $Table = null, Totum $Totum = null)
    {
        if ($this->Table = $Table) {
            $this->Totum = $this->Table->getTotum();
        } else {
            $this->Totum = $Totum;
        }
        $this->User = $this->Totum->getUser();
        $this->Request = $Request;
        $this->post = $Request->getParsedBody();

        $this->Cookies = $Request->getCookieParams();

        if (!empty($this->post['restoreView'])) {
            $this->Table->setRestoreView(true);
        }

        $this->modulePath = $modulePath;
    }

    public function reuser()
    {
        if (!Auth::isCanBeOnShadow($this->User)) {
            throw new errorException($this->translate('The function is not available to you.'));
        }
        $user = Auth::getUsersForShadow($this->Totum->getConfig(), $this->User, $this->post['userId']);
        if (!$user) {
            throw new errorException($this->translate('User not found'));
        }
        Auth::asUser($this->Totum->getConfig(), $user[0]['id'], $this->User);

        $this->Totum->addToInterfaceLink($this->Request->getParsedBody()['location'], 'self', 'reload');

        return ['ok' => 1];
    }

    #[ArrayShape(['default' => "bool", 'userLinks' => "array"])]
    public function getUserHelpLinks()
    {
        $DocsTable = $this->Totum->getTable('ttm__user_documentation');
        $tableName = '';
        if ($this->Table) {
            $tableName = $this->Table->getTableRow()['name'];
        }
        $roles = $this->User->getRoles();

        $links = [];
        foreach ($DocsTable->getByParams(
            (new FormatParamsForSelectFromTable)
                ->order('n')
                ->field('link')
                ->field('title')
                ->field('for_roles')
                ->field('for_tables')->params(),
            'rows') as $_link) {
            if ($_link['for_tables'] && !in_array($tableName, haystack: $_link['for_tables'])) {
                continue;
            }
            if ($_link['for_roles'] && !array_intersect($roles, $_link['for_roles'])) {
                continue;
            }
            $links[] = [$_link['title'], $_link['link']];

        }


        return ['default' => !($DocsTable->getTbl()['params']['h_turn_off_system_links']['v'] ?? false), 'userLinks' => $links];
    }

    public function seachUserTables()
    {
        $TreeModel = $this->Totum->getNamedModel(Tree::class);
        $q = mb_strtolower($this->post['q'], 'UTF-8');
        $words = preg_split('/\s+/', $q);

        $checkWords = function ($test) use ($words) {
            foreach ($words as $w) {
                if (!str_contains($test, $w)) {
                    return false;
                }
            }
            return true;
        };

        $branchesArray = [];

        foreach ($TreeModel->getBranchesByTables(
            null,
            array_keys($this->User->getTreeTables()),
            $this->User->getRoles()
        ) as $br) {
            if ((int)$br['id'] !== (int)($this->post['et'] ?? 0)) {
                array_push($branchesArray,
                    ...$TreeModel->getBranchesByTables(
                        $br['id'],
                        array_keys($this->User->getTreeTables()),
                        $this->User->getRoles()
                    ));
            }
        }
        $branchIds = array_column($branchesArray, 'id');
        $branchesCombine = array_combine($branchIds, array_column($branchesArray, 'top'));
        $tables = [];

        foreach ($this->Totum->getNamedModel(Table::class)->getAll(
            ['tree_node_id' => ($branchIds), 'id' => array_keys($this->User->getTreeTables())],
            'id, title, name, tree_node_id, type, icon',
            '(sort->>\'v\')::numeric'
        ) as $table) {
            if ($checkWords($table['name']) || $checkWords(mb_strtolower($table['title'], 'UTF-8'))) {
                $tables[] = ['id' => $table['id'], 'title' => $table['title'], 'top' => $branchesCombine[$table['tree_node_id']], 'icon' => $table['icon'] ?? null, 'type' => $table['type']];
            }
        }

        $tree = [];
        foreach ($branchesArray as $br) {
            if (in_array($br['type'], ['link', 'anchor']) && $checkWords(mb_strtolower($br['title'], 'UTF-8'))) {
                $tree[] = ['id' => $br['id'], 'title' => $br['title'], 'type' => $br['type'], 'href' => ($br['href'] ?? null), 'icon' => $br['icon']];
            }
        }
        return ['tables' => $tables, 'trees' => $tree];
    }

    public function getNotificationsTable()
    {
        $Calc = new CalculateAction('=: linkToDataTable(table: \'ttm__manage_notifications\'; title: "' . $this->translate('Notifications') . '"; width: 800; height: "80vh"; refresh: false; header: true; footer: true)');
        $Calc->execAction('KOD', [], [], [], [], $this->Totum->getTable('tables'), 'exec');
    }

    public function searchCatalog()
    {
        $TableSearch = $this->Totum->getTable('ttm__search_catalog');
        $catalog = $TableSearch->getByParams(['field' => ['name', 'title'], 'order' => [['field' => 'n', 'ad' => 'asc']]],
            'rows');
        return ['catalog' => $catalog];
    }

    public function searchClick()
    {
        if ($this->post['pk'] ?? '') {
            $data = explode('-', $this->post['pk']);
            if (count($data) === 2) {
                if (key_exists($data[0], $this->User->getTables())) {
                    $TableSearch = $this->Totum->getTable('ttm__search_settings');
                    if ($codesRow = $TableSearch->getByParams(['where' => [
                        ['field' => 'table_id', 'operator' => '=', 'value' => $data[0]]
                    ], 'field' => ['buttons', 'code']],
                        'row')) {

                        $Table = $this->Totum->getTable($data[0]);
                        if ($Table->loadFilteredRows('web', [$data[1]])) {
                            if ($this->post['button'] ?? false) {
                                foreach ($codesRow['buttons'] as $btn) {
                                    if ($btn['name'] === $this->post['button']) {
                                        $code = $btn['code'];
                                        break;
                                    }
                                }
                                if (empty($code)) {
                                    throw new errorException($this->translate('The code for the specified button is not found. Try again.'));
                                }
                            } else {
                                $code = $codesRow['code'];
                            }

                            $Calc = new CalculateAction($code);
                            $Calc->execAction('KOD',
                                $Table->getTbl()['rows'][$data[1]],
                                $Table->getTbl()['rows'][$data[1]],
                                $Table->getTbl(),
                                $Table->getTbl(),
                                $Table,
                                'exec',
                            );
                        }
                    }
                }
            }
        }
        return ['ok' => 1];
    }

    public function getSearchResults()
    {


        $Table = $this->Totum->getTable('ttm__search_settings');


        $facetFilters = [];

        $settings = $Table->getByParams(['field' => ['table_id', 'buttons']], 'rows');
        $tables_buttons = [];
        $tables = [];
        $column_delete = function (&$list) {
            unset($list['code']);
        };
        array_walk($settings,
            function ($row) use (&$tables_buttons, &$tables, $column_delete) {
                $tables_buttons[$row['table_id']] = $row['buttons'] && array_walk($row['buttons'],
                    $column_delete) ? $row['buttons'] : [];
                $tables[] = $row['table_id'];
            });

        $tables_cleared = array_intersect($tables, array_keys($this->User->getTables()));
        if ($tables_cleared != $tables) {
            foreach ($tables_cleared as $table) {
                $facetFilters[] = 'table = ' . $table;
            }
            if (empty($facetFilters)) {
                return ['hits' => []];
            }
        }

        if (!empty($this->post['cats'])) {
            $catsFilters = [];
            foreach ($this->post['cats'] as $name) {
                $catsFilters[] = 'catalog = ' . $name;
            }
            if ($facetFilters) {
                $facetFilters = [$facetFilters, $catsFilters];
            } else {
                $facetFilters = [$catsFilters];
            }
        } elseif ($facetFilters) {
            $facetFilters = [$facetFilters];
        }

        $Calc = new CalculateAction('=: exec(code: \'h_connect_code\'; var: "posts" = $#posts; var: "path"= str`"/indexes/"+#h_index_name+"/search"`)');
        $posts = [
            "q" => $this->post['q'] ?? '',
            "attributesToHighlight" => ["index", "title"],
            "highlightPreTag" => "-highlightPreTag-",
            "highlightPostTag" => "-highlightPostTag-",
        ];
        if ($facetFilters) {
            $posts["filter"] = $facetFilters;
        }


        $tables = [];
        $getTable = function ($tableId) use (&$tables) {
            if (!key_exists($tableId, $tables)) {
                $tables[$tableId] = $this->Totum->getTable($tableId);
                $tables[$tableId]->reCalculateFilters('web');
                $params = $tables[$tableId]->filtersParamsForLoadRows('web', [], [], true);
                if (empty($params)) {
                    $tables[$tableId] = false;
                }
            }
            return $tables[$tableId];
        };


        $i = -1;
        $limit = $Table->getTbl()['params']['h_search_limit']['v'];
        if (empty($limit)) {
            $limit = 20;
        }
        $offset = 0;
        $hits = [];
        do {
            $i++;
            $removed = false;
            $posts['offset'] = $offset;
            $posts['limit'] = $limit - count($hits);
            $resIn = $Calc->execAction('KOD',
                $Table->getTbl()['params'],
                $Table->getTbl()['params'],
                $Table->getTbl(),
                $Table->getTbl(),
                $Table,
                'exec',
                [
                    'posts' => json_encode(
                        $posts,
                        JSON_UNESCAPED_UNICODE)
                ]);

            $res = json_decode($resIn, true);

            if (($res['code'] ?? false)) {
                throw new errorException($res['message']);
            }

            foreach ($res['hits'] as $k => $_h) {
                $offset++;
                list($tableId, $rowId) = explode('-', $_h['pk']);
                if ($_Table = $getTable($tableId)) {
                    try {
                        $_Table->checkIsUserCanViewIds('web', [$rowId], isCritical: false);
                    } catch (\Exception $exception) {
                        $removed = true;
                        continue;
                    }
                }

                foreach ($_h['_formatted'] as &$match) {
                    $match = htmlspecialchars($match);
                    $match = str_replace('-highlightPreTag-', '<span class="marker">', $match);
                    $match = str_replace('-highlightPostTag-', '</span>', $match);
                }
                unset($match);

                if (key_exists($tableId, $tables_buttons)) {
                    $_h['buttons'] = $tables_buttons[$tableId];
                }
                $hits[] = $_h;
            }
            unset($_h);
        } while ($removed);

        return ['hits' => array_values($hits)];
    }

    public function loadUserButtons()
    {
        $result = null;
        $Table = $this->Totum->getTable('settings');
        $fieldData = $Table->getFields()['h_user_settings_buttons'] ?? null;

        if ($fieldData) {
            $clc = new CalculcateFormat($fieldData['format']);

            $result = $clc->getPanelFormat(
                'h_user_settings_buttons',
                $Table->getTbl()['params'],
                $Table->getTbl(),
                $Table
            );
        }
        return ['panelFormats' => $result];
    }

    /**
     * Клик по кнопке в панельке поля
     *
     * @throws errorException
     */
    public function userButtonsClick()
    {
        $model = $this->Totum->getModel('_tmp_tables', true);
        $key = ['table_name' => '_panelbuttons', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null];
        if ($data = $model->getField('tbl', $key)) {
            $data = json_decode($data, true);
            foreach ($data as $row) {
                if ($row['ind'] === ($this->post['index'] ?? null)) {
                    $Table = $this->Totum->getTable('settings');
                    if (is_string($row['code']) && key_exists($row['code'], $Table->getFields())) {
                        $row['code'] = $Table->getFields()[$row['code']]['codeAction'];
                    }
                    $CA = new CalculateAction($row['code']);
                    $item = $Table->getTbl()['params'];


                    $CA->execAction(
                        $row['field'],
                        [],
                        $item,
                        [],
                        $Table->getTbl(),
                        $Table,
                        'exec',
                        $row['vars'] ?? []
                    );
                    break;
                }
            }
        } else {
            throw new errorException($this->translate('The choice is outdated.'));
        }
        return ['ok' => 1];
    }

    public function notificationUpdate()
    {
        if (!empty($ids = $this->post['id'])) {

            $model = $this->Totum->getModel('notifications');
            if ($ids === 'ALL_ACTIVE') {
                $rows = $model->getAllPrepared(['<=active_dt_from' => date('Y-m-d H:i:s', time() - 2),
                    'user_id' => $this->User->getId(),
                    'active' => 'true']);
            } else {
                $rows = $model->getAll(['id' => $ids, 'user_id' => $this->User->getId()]);
            }
            if ($rows) {
                $upd = [];
                switch ($this->post['type']) {
                    case 'deactivate':
                        $upd = ['active' => false];
                        break;
                    case 'later':

                        $date = date_create();
                        if (empty($this->post['num']) || empty($this->post['item'])) {
                            $date->modify('+5 minutes');
                        } else {
                            $items = [1 => 'minutes', 'hours', 'days'];
                            $date->modify('+' . $this->post['num'] . ' ' . ($items[$this->post['item']] ?? 'minutes'));
                        }

                        $upd = ['active_dt_from' => $date->format('Y-m-d H:i')];
                        break;
                }

                $md = [];
                foreach ($rows as $row) {
                    $md[$row['id']] = $upd;
                }

                $this->Totum->getTable('notifications')->reCalculateFromOvers(['modify' => $md]);
            }
        }
        return ['ok' => 1];
    }

    /**
     * Клик по linkToInout
     *
     *
     * @throws errorException
     */
    public function linkInputClick()
    {
        $model = $this->Totum->getModel('_tmp_tables', true);
        $key = ['table_name' => '_linkToInput', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null];
        if ($data = $model->getField('tbl', $key)) {
            $data = json_decode($data, true);

            list($Table, $row) = $this->loadEnvirement($data);

            if (key_exists('type', $data) && $data['type'] === 'select') {

                /** @var Select $Field */
                $Field = Field::init([
                    'type' => 'select',
                    'name' => '_linktoinputselect',
                    'category' => key_exists('id', $row) ? 'column' : 'param',
                    'codeSelect' => ($data['codeselect'] ?? null),
                    'checkSelectValues' => true,
                    'title' => $data['title'],
                    'multiple' => $data['multiple'] ?? false,
                ], $Table);

                /*Запрос preview*/
                if (key_exists('preview', $this->post)) {
                    if (key_exists('val', $this->post)) {
                        return ['previews' => $Field->getPreviewHtml(['v' => $this->post['val']],
                            $row,
                            $this->Table->getTbl())];
                    }
                }/*Запрос селекта*/
                elseif (key_exists('search', $this->post)) {
                    $val = ['v' => $this->post['search']['checkedVals'] ?? (empty($data['multiple']) ? [] : null)];
                    $list = $Field->calculateSelectList($val,
                        $row,
                        $Table->getTbl(),
                        $data['vars'] ?? []);

                    return $Field->cropSelectListForWeb($list, $val['v'], $this->post['search']['q']);

                } /*Проверка результата*/
                else {
                    $Field->checkSelectVal('web',
                        $this->post['val'],
                        $row,
                        $Table->getTbl(),
                        [],
                        ($data['vars'] ?? []));
                }

            }

            if ($Table->getFields()[$data['code']] ?? false) {
                $CA = new CalculateAction($this->Table->getFields()[$data['code']]['codeAction']);
            } else {
                $CA = new CalculateAction($data['code']);
            }

            $CA->execAction(
                'CODE',
                [],
                $row,
                $Table->getTbl(),
                $Table->getTbl(),
                $Table,
                'exec',
                ($data['vars'] ?? []) + ['input' => $this->post['val']]
            );

            $model->delete($key);
        } else {
            throw new errorException($this->translate('The proposed input is outdated.'));
        }
        return ['ok' => 1];
    }

    public function checkForNotifications()
    {
        /*TODO FOR MY TEST SERVER */
        if ($_SERVER['HTTP_HOST'] === 'localhost:8080') {
            die('test');
        }

        $actived = $this->post['activeIds'] ?? [];
        $model = $this->Totum->getModel('notifications');
        $codes = $this->Totum->getModel('notification_codes');
        $getNotification = function () use ($actived, $model, $codes) {
            if (!$actived) {
                $actived = [0];
            }
            $result = [];

            if ($row = $model->getPrepared(
                ['!id' => $actived,
                    '<=active_dt_from' => date('Y-m-d H:i:s'),
                    'user_id' => $this->User->getId(),
                    'active' => 'true'],
                '*',
                '(prioritet->>\'v\')::int, id'
            )) {
                array_walk(
                    $row,
                    function (&$v, $k) {
                        if (!Model::isServiceField($k)) {
                            $v = json_decode($v, true);
                        }
                    }
                );
                $kod = $codes->getField(
                    'code',
                    ['name' => $row['code']['v']]
                );
                $calc = new CalculateAction($kod);
                $table = $this->Totum->getTable('notifications');
                $calc->execAction(
                    'code',
                    [],
                    $row,
                    [],
                    $table->getTbl(),
                    $table,
                    'exec',
                    $row['vars']['v']
                );

                $result['notification_id'] = $row['id'];
            }
            if ($actived) {
                $result['deactivated'] = [];
                if ($ids = ($model->getColumn(
                        'id',
                        ['id' => $actived, 'user_id' => $this->User->getId(), 'active' => 'false']
                    ) ?? [])) {
                    $result['deactivated'] = array_merge($result['deactivated'], $ids);
                }
                if ($ids = ($model->getColumn(
                        'id',
                        ['id' => $actived, 'user_id' => $this->User->getId(), 'active' => 'true', '>active_dt_from' => date('Y-m-d H:i')]
                    ) ?? [])) {
                    $result['deactivated'] = array_merge($result['deactivated'], $ids);
                }
                if (empty($result['deactivated'])) {
                    unset($result['deactivated']);
                }
            }
            return $result;
        };

        if (!empty($this->post['periodicity']) && ($this->post['periodicity'] > 1)) {
            $i = 0;

            $count = ceil(20 / $this->post['periodicity']);

            do {
                echo "\n";
                flush();

                if (connection_status() !== CONNECTION_NORMAL) {
                    die;
                }
                if ($result = $getNotification()) {
                    break;
                }

                sleep($this->post['periodicity']);
            } while (($i++) < $count);
        } else {
            $result = $getNotification();
        }
        echo json_encode($result + ['notifications' => array_map(
                function ($n) {
                    $n[0] = 'notification';
                    return $n;
                },
                $this->Totum->getInterfaceDatas()
            )]);
        die;
    }

    protected function loadEnvirement(array $data): array
    {
        $Table = $this->Totum->getTable($data['env']['table'], $data['env']['extra'] ?? null);

        $row = [];
        if (key_exists('id', $data['env'])) {
            if ($Table->loadFilteredRows('inner', [$data['env']['id']])) {
                $row = $Table->getTbl()['rows'][$data['env']['id']];
            }
        }
        return [$Table, $row];
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->Totum->getLangObj()->translate($str, $vars);
    }
}

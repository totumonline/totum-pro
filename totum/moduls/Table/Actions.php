<?php


namespace totum\moduls\Table;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\calculates\CalculcateFormat;
use totum\common\errorException;
use totum\common\Model;
use totum\common\Totum;
use totum\common\User;
use totum\models\TablesFields;
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

        if (!empty($this->post['restoreView'])) {
            $this->Table->setRestoreView(true);
        }

        $this->modulePath = $modulePath;
    }

    public function reuser()
    {
        if (!Auth::isCanBeOnShadow($this->User)) {
            throw new errorException('Функция вам недоступна');
        }
        $user = Auth::getUsersForShadow($this->Totum->getConfig(), $this->User, $this->post['userId']);
        if (!$user) {
            throw new errorException('Пользователь не найден');
        }
        Auth::asUser($this->Totum->getConfig(), $user[0]['id'], $this->User);

        $this->Totum->addToInterfaceLink($this->Request->getParsedBody()['location'], 'self', 'reload');

        return ['ok' => 1];
    }

    public function getNotificationsTable()
    {
        $Calc = new CalculateAction('=: linkToDataTable(table: \'ttm__manage_notifications\'; title: "Нотификации"; width: 800; height: "80vh"; refresh: false; header: true; footer: true)');
        $Calc->execAction('KOD', [], [], [], [], $this->Totum->getTable('tables'), 'exec');
    }

    public function searchClick(){
        if($this->post['pk'] ?? ''){
            $data=explode('-', $this->post['pk']);
            if(count($data)===2){
                if(key_exists($data[0], $this->User->getTables())){
                    $TableSearch = $this->Totum->getTable('ttm__search_settings');
                    if($code=$TableSearch->getByParams(['where'=>[
                        ['field'=>'table_id', 'operator'=>'=', 'value'=>$data[0]]
                    ], 'field'=>'code'], 'field')){

                        $Table=$this->Totum->getTable($data[0]);
                        if($Table->loadFilteredRows('web', [$data[1]])){
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
        return ['ok'=>1];
    }
    public function getSearchResults()
    {
        $Table = $this->Totum->getTable('ttm__search_settings');
        $Calc = new CalculateAction('=: exec(code: \'h_connect_code\'; var: "posts" = $#posts; var: "path"= str`"/indexes/"+#h_index_name+"/search"`)');
        $res = $Calc->execAction('KOD',
            $Table->getTbl()['params'],
            $Table->getTbl()['params'],
            $Table->getTbl(),
            $Table->getTbl(),
            $Table,
            'exec',
            [
                'posts' => json_encode(
                    [
                        "q" => $this->post['q'] ?? '',
                        "matches" => true
                    ],
                    JSON_UNESCAPED_UNICODE)
            ]);

        $res = json_decode($res, true);
        foreach ($res['hits'] as &$_h) {
            foreach ($_h['_matchesInfo'] as $field => &$matches) {
                $val = $_h[$field];
                foreach ($matches as &$match) {
                    $match['start'] = mb_strlen(substr($val, 0, $match['start']));
                    $match['length'] = mb_strlen(substr($val, $match['start'], $match['length']));
                    unset($match);
                }
                unset($matches);
            }
        }
        unset($_h);
        return $res;
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
            throw new errorException('Предложенный выбор устарел.');
        }
        return ['ok' => 1];
    }

    public function notificationUpdate()
    {
        if (!empty($this->post['id'])) {
            if ($rows = $this->Totum->getModel('notifications')->getAll(['id' => $this->post['id'], 'user_id' => $this->User->getId()])) {
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

            if (key_exists('cycle_id', $data['env'])) {
                $Table = $this->Totum->getTable($data['env']['table'], $data['env']['cycle_id']);
            } elseif (key_exists('hash', $data['env'])) {
                $Table = $this->Totum->getTable($data['env']['table'], $data['env']['hash']);
            } else {
                $Table = $this->Totum->getTable($data['env']['table']);
            }

            $row = [];
            if (key_exists('id', $data['env'])) {
                if ($Table->loadFilteredRows('inner', [$data['env']['id']])) {
                    $row = $Table->getTbl()['rows'][$data['env']['id']];
                }
            }


            if ($this->Table->getFields()[$data['code']] ?? false) {
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
            throw new errorException('Предложенный ввод устарел.');
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
}

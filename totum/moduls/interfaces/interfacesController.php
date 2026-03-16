<?php

namespace totum\moduls\interfaces;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\calculates\CalculateAction;
use totum\common\controllers\interfaceController;
use totum\common\controllers\WithAuthTrait;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Auth;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\logs\CalculateLog;
use totum\common\Model;
use totum\common\OnlyOfficeConnector;
use totum\common\Services\ServicesConnector;
use totum\common\WithPathMessTrait;
use totum\common\sql\SqlException;
use totum\common\tableSaveOrDeadLockException;
use totum\common\Totum;
use totum\config\Conf;
use totum\models\UserV;


class interfacesController extends interfaceController
{
    use WithAuthTrait;

    protected array|null $interface;
    protected $totumTries;

    public function __construct(Conf $Config, $totumPrefix = '')
    {
        $this->Config = $Config;
        parent::__construct($Config, $totumPrefix);
    }

    protected function __run($operation, ServerRequestInterface $request)
    {
        $this->User = Auth::webInterfaceSessionStart($this->Config);

        if ($this->interface = $this->Config->getInterfaceData()) {
            if (!$this->User) {
                if ($this->interface['auth']){
                    $this->location($this->interface['interface']['auth_path'] ?: '/');
                }
                if ($this->interface['interface']['webuser']){
                    $this->User = Auth::loadAuthUserByLogin($this->Config, 'webuser', false);
                }
            }
            static::$pageTemplate = $this->Config->getBaseDir() . 'interfaces/' . $this->interface['interface']['name'] . '/' . $this->interface['template'];
        }else{
            $this->location('/');
        }
        if (!$this->User) {
            $this->__UnauthorizedAnswer($request);
        }

    }

    public function doIt(ServerRequestInterface $request, bool $output)
    {

        try {
            try {
                $this->__run('', $request);
                $this->Totum = new Totum($this->Config, $this->User);
                $this->Totum->transactionStart();
                $this->outputHtmlTemplate();
                $this->Totum->transactionCommit();
            } catch (tableSaveOrDeadLockException $exception) {
                $this->Totum?->transactionRollback();
                if (++$this->totumTries < 5) {
                    $this->Config = $this->Config->getClearConf();
                    $this->answerVars = [];
                    $this->doIt($request, false);
                } else {
                    throw new \Exception($this->translate('Conflicts of access to the table error'));
                }
            }
        } catch (\Exception $e) {
            $this->Totum?->transactionRollback();
            $message = $e->getMessage();
            if ($this->User && $this->User->isCreator() && method_exists($e, 'getPathMess') && $e->getPathMess()) {
                $message .= '<br/>' . $e->getPathMess();
            }
            $this->__addAnswerVar('error', $message);

            var_dump($message);
            static::$pageTemplate = $this->Config->getBaseDir() .
                'interfaces/' . $this->interface['interface']['name'] . '/error';

            $this->outputHtmlTemplate();
            $this->Totum->transactionRollback();
        }
    }
    public function getSearchResults($post)
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

        if (!empty($post['cats'])) {
            $catsFilters = [];
            foreach ($post['cats'] as $name) {
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
            "q" => $post['q'] ?? '',
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
}

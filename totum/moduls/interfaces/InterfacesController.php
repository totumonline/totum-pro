<?php

namespace totum\config\totum\moduls\interfaces;

use Psr\Http\Message\ServerRequestInterface;
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

class InterfacesController extends interfaceController
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
            if (!$this->User && $this->interface['interface']['webuser']) {
                $this->User = Auth::loadAuthUserByLogin($this->Config, 'webuser', false);
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
        $this->Totum = new Totum($this->Config, $this->User);

        try {
            try {
                $this->outputHtmlTemplate();
            } catch (tableSaveOrDeadLockException $exception) {
                if (++$this->totumTries < 5) {
                    $this->Config = $this->Config->getClearConf();
                    $this->answerVars = [];
                    $this->doIt($request, false);
                } else {
                    throw new \Exception($this->translate('Conflicts of access to the table error'));
                }
            }
        } catch (\Exception $e) {

            $message = $e->getMessage();
            if ($this->User && $this->User->isCreator() && method_exists($e, 'getPathMess') && $e->getPathMess()) {
                $message .= '<br/>' . $e->getPathMess();
            }
            $this->__addAnswerVar('error', $message);

            static::$pageTemplate = $this->Config->getBaseDir() .
                'interfaces/' . $this->interface['interface']['name'] . '/error';

            $this->outputHtmlTemplate();
        }
    }
}

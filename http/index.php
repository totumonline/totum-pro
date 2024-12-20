<?php

use GuzzleHttp\Psr7\ServerRequest;
use totum\config\Conf;

$GLOBALS['mktimeStart'] = microtime(true);

ignore_user_abort(false);

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists(Conf::class)) {
    die('NOT INSTALLED');
} else {
    $Config = new Conf();
    if (is_callable([$Config, 'setHostSchema'])) {
        $Config->setHostSchema($_SERVER['HTTP_HOST']);
    }

    if (preg_match('/^\/ServicesAnswer(\/|\?|$)/', $_SERVER['REQUEST_URI'])) {
        \totum\common\Services\ServicesConnector::init($Config)->setAnswer(ServerRequest::fromGlobals());
        die('true');
    } elseif (str_starts_with($_SERVER['REQUEST_URI'], '/go-licenser')) {
        if (str_starts_with($_SERVER['REQUEST_URI'], '/go-licenser-test')){
            die($_SERVER['HTTP_HOST'].'/'.$Config->getSchema().'/test');
        }
        try {
            $data = $Config->proGoModuleSocketSend(['method' => 'license', 'host' => $_SERVER['HTTP_HOST']]);
            die($_SERVER['HTTP_HOST'].'/'.$Config->getSchema().'/'.$data['str']);
        }catch (\Exception $e){
            die($e->getMessage());
        }
    }

    list($module, $lastPath) = $Config->getActivationData($_SERVER['REQUEST_URI']);
}


if (empty($module)) {
    $module = 'Table';
    $lastPath = '';
}
$controllerClass = 'totum\\moduls\\' . $module . '\\' . $module . 'Controller';
if (class_exists($controllerClass)) {
    if ($Config && !empty($Config->getHiddenHosts()[$Config->getFullHostName()]) && empty($Config->getHiddenHosts()[$Config->getFullHostName()][$module])) {
        die($Config->getLangObj()->translate('The module is not available for this host.'));
    }

    $Config->profilingStart(preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']) . '/' . (($_POST['method'] ?? null) ?: 'table'),
        function () {
            $data = [];
            if (!empty($_POST) && !empty($_POST['data'])) {
                if ($_data = @json_decode($_POST['data'], true)) {
                    if ($_POST['method'] === 'edit') {
                        $data['id_value'] = array_key_first($_data);
                        if (is_array($_data[$data['id_value']] ?? false)) {
                            $data['field_name'] = array_key_first($_data[$data['id_value']]);
                        }
                    } else {
                        if (key_exists('id', $_data)) {
                            $data['id_value'] = $_data['id'];
                        } elseif (key_exists('item', $_data) && !is_array($_data['item'])) {
                            $data['id_value'] = $_data['item'];
                        }
                        if (key_exists('fieldName', $_data)) {
                            $data['field_name'] = $_data['fieldName'];
                        } elseif (key_exists('fieldname', $_data)) {
                            $data['field_name'] = $_data['fieldname'];
                        }
                    }
                }
            }
            return $data;
        }
    );

    /*
     * @var Controller $Controller
     * */
    $Controller = new $controllerClass($Config);

    $request = ServerRequest::fromGlobals();
    $response = $Controller->doIt($request, true);


    //$Config->getSql()->transactionRollBack();

} else {
    if ($Config) {
        $Lang = $Config->getLangObj();
    } else {
        $Lang = (new \totum\common\Lang\EN());
    }
    echo $Lang->translate('Not found: %s', [htmlspecialchars($controllerClass)]);
}
die;
?>
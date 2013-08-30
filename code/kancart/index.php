<?php

error_reporting(0);
ini_set('display_errors', false);
define('IN_KANCART', true);
define('API_VERSION', '1.1');

define('KANCART_ROOT', str_replace('\\', '/', dirname(__FILE__)));
chdir('..') || set_include_path(dirname(KANCART_ROOT));
require_once 'includes/application_top.php';
require_once KANCART_ROOT . '/KancartHelper.php';

kc_include_once(KANCART_ROOT . '/ErrorHandler.php');
kc_include_once(KANCART_ROOT . '/Logger.php');
kc_include_once(KANCART_ROOT . '/configure.php');
kc_include_once(KANCART_ROOT . '/Exceptions.php');
kc_include_once(KANCART_ROOT . '/ActionFactory.php');
kc_include_once(KANCART_ROOT . '/ServiceFactory.php');
kc_include_once(KANCART_ROOT . '/actions/BaseAction.php');
kc_include_once(KANCART_ROOT . '/actions/UserAuthorizedAction.php');
kc_include_once(KANCART_ROOT . '/util/CryptoUtil.php');
kc_include_once(KANCART_ROOT . '/KancartResult.php');
kc_include_once(KANCART_ROOT . '/common-functions.php');

try {
    $actionInstance = ActionFactory::factory(isset($_REQUEST['method']) ? $_REQUEST['method'] : '');
    $actionInstance->init();
    if ($actionInstance->validate()) {
        $actionInstance->execute();
    }
    $result = $actionInstance->getResult();
    die(json_encode($result->returnResult()));
} catch (EmptyMethodException $e) {
    die('KanCart OpenAPI v' . API_VERSION . ' is installed on Oscommerce v' . CART_VERSION . '. osCommerce Plugin v' . KANCART_PLUGIN_VERSION);
} catch (Exception $e) {
    die(json_encode(array('result' => KancartResult::STATUS_FAIL, 'code' => KancartResult::ERROR_UNKNOWN_ERROR, 'info' => $e->getMessage() . ',' . $e->getTraceAsString())));
}
?>

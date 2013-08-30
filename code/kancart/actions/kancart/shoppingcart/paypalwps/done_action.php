<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalwps_done_action extends BaseAction {

    public function execute() {
        global $order_id;

        $paypalWpsService = ServiceFactory::factory('PaypalWps');
        $order = $paypalWpsService->done();
        $order->info['order_id'] = $order_id;
        $tx = max($_REQUEST['tx'], $_REQUEST['txn_id']);
        $orderService = ServiceFactory::factory('Order');
        $info = $orderService->getPaymentOrderInfo($order, $tx);
        $this->setSuccess($info);
    }

}

?>

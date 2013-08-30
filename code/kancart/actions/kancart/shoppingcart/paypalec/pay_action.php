<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_pay_action extends BaseAction {

    public function execute() {
        global $order, $return_url, $ppe_payerid, $cancel_url, $order_id;
        $expressCheckoutService = ServiceFactory::factory('PaypalExpressCheckout');
        if (empty($ppe_payerid)) {
            $paypal_express = new kancart_paypal_express($return_url, $cancel_url);
            $expressCheckoutService->returnFromPaypal($paypal_express);
        }
        $result = $expressCheckoutService->pay();
        empty($order->info['order_id']) && $order->info['order_id'] = $order_id;
        if ($result === true) {
            $orderService = ServiceFactory::factory('Order');
            $info = $orderService->getPaymentOrderInfo($order);
            $this->setSuccess($info);
        } else {
            is_array($result) && $result = join('<br>', $result);
            $this->setError('', $result);
        }
    }

}

?>

<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_checkout_action extends UserAuthorizedAction {

    public function execute() {
        $payment = trim($_REQUEST['payment_method_id']);
        switch ($payment) {
            case 'paypalwpp':
                $this->paypalwpp();
                break;
            case 'paypal':
                $this->paypal();
                break;
            default:
                $this->payorder($payment);
                break;
        }
    }

    public function paypalwpp() { //paypal express checkout
        global $return_url, $cancel_url;

        $expressCheckout = ServiceFactory::factory('PaypalExpressCheckout');
        $paypal_express = new kancart_paypal_express($_REQUEST['return_url'], $_REQUEST['cancel_url']);
        $result = $expressCheckout->startExpressCheckout($paypal_express, true);
        if (is_array($result) && isset($result['token']) && $result['token']) {
            $return_url = $_REQUEST['return_url'];
            $cancel_url = $_REQUEST['cancel_url'];
            tep_session_register('return_url');
            tep_session_register('cancel_url');
            $this->setSuccess($result);
        } else {
            is_array($result) && $result = join('<br>', $result);
            $this->setError('', $result);
        }
    }

    public function paypal() { //Website Payments Standard     
        $checkoutService = ServiceFactory::factory('Checkout');
        $params = $checkoutService->placeOrder();
        if (!is_array($params)) {
            $this->setError('', $params);
        } else {
            $this->setSuccess($params);
        }
    }

    public function payorder($method) {
        if (empty($method)) {
            $this->setError('', 'Error: payment_method_id is empty.');
        } else {
            $paypal = ServiceFactory::factory('KancartPayment');
            list($result, $order, $message) = $paypal->placeOrder($method);
            if ($result === true) {
                $orderService = ServiceFactory::factory('Order');
                $info = $orderService->getPaymentOrderInfo($order);             
                $this->setSuccess($info);
            } else {
                is_array($message) && $message = join('<br>', $message);
                $this->setError('', $message);
            }
        }
    }

}

?>
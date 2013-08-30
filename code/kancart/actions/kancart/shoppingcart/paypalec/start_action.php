<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_paypalec_start_action extends BaseAction {

    public function execute() {
        $expressCheckout = ServiceFactory::factory('PaypalExpressCheckout');
        $paypal_express = new kancart_paypal_express($_REQUEST['return_url'], $_REQUEST['cancel_url']);
        $result = $expressCheckout->startExpressCheckout($paypal_express);
        if (is_array($result) && isset($result['token']) && !empty($result['token'])) {
            $this->setSuccess($result);
        } else {
            is_array($result) && $result = join('<br>', $result);
            $this->setError('', $result);
        }
    }

}

?>

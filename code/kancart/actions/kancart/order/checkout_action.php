<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_order_checkout_action extends UserAuthorizedAction {

    public function execute() {
        global $ecs, $db;
        $pay_code = 'paypal';
        /* 判断是否启用 */
        $sql = "SELECT COUNT(*) FROM " . $ecs->table('payment') . " WHERE pay_code = '$pay_code' AND enabled = 1";
        if ($db->getOne($sql) == 0) {
            $msg = $_LANG['pay_disabled'];
            $this->setError('', $msg);
            return;
        } else {
            $plugin_file = ROOT_PATH . 'includes/modules/payment/' . $pay_code . '.php';
            /* 检查插件文件是否存在，如果存在则验证支付是否成功，否则则返回失败信息 */
            if (file_exists($plugin_file)) {
                /* 根据支付方式代码创建支付类的对象并调用其响应操作方法 */
                include_once($plugin_file);
                $orderId = $this->getParam('order_id');
                $paymentInfo = PaymentService::singleton()->getPaymentByCode($this->getParam('payment_method_id'));
                $paymentConfig = unserialize($paymentInfo['pay_config']);
                $orderDetail = get_order_detail($orderId, $_SESSION['user_id']);
                $paypalParams = $this->buildOrderCheckoutPaypalParams($orderDetail, $paymentConfig, $this->getParam('return_url'), $this->getParam('cancel_url'));
                $paypalRedirectUrl = '';
                if (defined('MODULE_PAYMENT_PAYPAL_STANDARD_GATEWAY_SERVER') && MODULE_PAYMENT_PAYPAL_STANDARD_GATEWAY_SERVER == 'sandbox') {
                    $paypalRedirectUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
                } else {
                    $paypalRedirectUrl = 'https://www.paypal.com/cgi-bin/webscr';
                }
                $this->setSuccess(array(
                    'paypal_redirect_url' => $paypalRedirectUrl,
                    'paypal_params' => $paypalParams));
            } else {
                $msg = $_LANG['pay_not_exist'];
                $this->setError('', $msg);
            }
        }
    }

    private function getPaypalNotifyUrl() {
        $root = str_replace('\\', '/', dirname(dirname(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))))));
        if (substr($root, -1) != '/') {
            $root .= '/';
        }
        return $GLOBALS['ecs']->get_domain() . $root . 'respond.php?code=paypal';
    }

    private function buildOrderCheckoutPaypalParams($orderDetail, $payment, $returnUrl, $cancelUrl) {
        $data_order_id = $orderDetail['order_id'];
        $data_order_id = get_paylog_id($data_order_id, PAY_ORDER);
        $data_amount = $orderDetail['total_fee'];
        $currency_code = '';
        $data_pay_account = '';
        foreach ($payment as $eachPayment) {
            if ($eachPayment['name'] == 'paypal_account') {
                $data_pay_account = $eachPayment['value'];
            }
            if ($eachPayment['name'] == 'paypal_currency') {
                $currency_code = $eachPayment['value'];
            }
        }

        $data_notify_url = $this->getPaypalNotifyUrl();
        $params = array();
        $params['cmd'] = '_xclick';
        $params['business'] = $data_pay_account;
        $params['item_name'] = $orderDetail['order_sn'];
        $params['amount'] = $data_amount;
        $params['currency_code'] = $currency_code;
        $params['return'] = $returnUrl;
        $params['invoice'] = $data_order_id;
        $params['charset'] = 'utf-8';
        $params['no_shipping'] = '1';
        $params['no_note'] = '';
        $params['notify_url'] = $data_notify_url;
        $params['rm'] = '2';
        $params['cancel_return'] = $cancelUrl;
        $params['bn'] = 'PP-BuyNowBF:btn_buynowCC_LG.gif:NonHosted';
        return $params;
    }

}

?>

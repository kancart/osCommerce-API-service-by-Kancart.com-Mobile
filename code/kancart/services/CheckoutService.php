<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * Checkout utility
 * @package services
 * @author hujs
 */
class CheckoutService {

    public function __construct() {
        $this->cart = &$_SESSION['cart'];
        $this->freeShipping = false;
        $this->isVirtual = $this->cart->get_content_type() == 'virtual';
    }

    /**
     * get checkout detail information
     * 
     */
    public function detail() {
        global $guest_uname;

        $detail = array();
        $detail['billing_address'] = $this->getBillingAddress();
        $detail['shipping_address'] = $this->getShippingAddress();
        $detail['review_orders'] = array($this->getReviewOrder());
        if (tep_count_shipping_modules() < 1) {
            $detail['need_select_shipping_method'] = false;
        } elseif (!$this->freeShipping && !$detail['review_orders'][0]['selected_shipping_method_id']) {
            $detail['need_select_shipping_method'] = true;
        }
        $detail['price_infos'] = $this->getPriceInfos();
        $detail['payment_methods'] = $this->getPaymentMethods();
        $detail['is_virtual'] = $this->isVirtual;
        $detail['need_billing_address'] = defined('NEED_BILLING_ADDRESS') ? NEED_BILLING_ADDRESS : FALSE;
        $detail['need_shipping_address'] = !$this->isVirtual && !ServiceFactory::factory('Store')->checkAddressIntegrity($detail['shipping_address']);

        if (tep_session_is_registered('guest_uname')) {
            $detail['uname'] = $guest_uname;
            tep_session_unregister('guest_uname');
        }

        return $detail;
    }

    public function getReviewOrder() {
        $order = array();
        $order['cart_items'] = ServiceFactory::factory('ShoppingCart')->getProducts();
        $order['shipping_methods'] = $this->getOrderShippingMethods();
        $selectedShippingMethod = $this->selectShippingMethod($order['shipping_methods']);
        $order['selected_shipping_method_id'] = $this->freeShipping ? 0 : $selectedShippingMethod;
        return $order;
    }

    public function buildWpsRedirectParams($paypalStandard, $returnUrl = '', $cancelUrl = '') {
        global $customer_id, $order, $sendto, $currency, $cart_PayPal_Standard_ID;
        $process_button_string = '';
        $parameters = array(
            'cmd' => '_xclick',
            'item_name' => STORE_NAME,
            'shipping' => $paypalStandard->format_raw($order->info['shipping_cost']),
            'tax' => $paypalStandard->format_raw($order->info['tax']),
            'business' => MODULE_PAYMENT_PAYPAL_STANDARD_ID,
            'amount' => $paypalStandard->format_raw($order->info['total'] - $order->info['shipping_cost'] - $order->info['tax']),
            'currency_code' => $currency,
            'invoice' => substr($cart_PayPal_Standard_ID, strpos($cart_PayPal_Standard_ID, '-') + 1),
            'custom' => $customer_id,
            'no_note' => '1',
            'notify_url' => tep_href_link('ext/modules/payment/paypal/standard_ipn.php', '', 'SSL', false, false),
            'return' => $returnUrl,
            'cancel_return' => $cancelUrl,
            'bn' => 'osCommerce22_Default_ST',
            'paymentaction' => ((MODULE_PAYMENT_PAYPAL_STANDARD_TRANSACTION_METHOD == 'Sale') ? 'sale' : 'authorization'));

        if (is_numeric($sendto) && ($sendto > 0)) {
            $parameters['address_override'] = '1';
            $parameters['first_name'] = $order->delivery['firstname'];
            $parameters['last_name'] = $order->delivery['lastname'];
            $parameters['address1'] = $order->delivery['street_address'];
            $parameters['city'] = $order->delivery['city'];
            $parameters['state'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
            $parameters['zip'] = $order->delivery['postcode'];
            $parameters['country'] = $order->delivery['country']['iso_code_2'];
        } else {
            $parameters['no_shipping'] = '1';
            $parameters['first_name'] = $order->billing['firstname'];
            $parameters['last_name'] = $order->billing['lastname'];
            $parameters['address1'] = $order->billing['street_address'];
            $parameters['city'] = $order->billing['city'];
            $parameters['state'] = tep_get_zone_code($order->billing['country']['id'], $order->billing['zone_id'], $order->billing['state']);
            $parameters['zip'] = $order->billing['postcode'];
            $parameters['country'] = $order->billing['country']['iso_code_2'];
        }

        if (tep_not_null(MODULE_PAYMENT_PAYPAL_STANDARD_PAGE_STYLE)) {
            $parameters['page_style'] = MODULE_PAYMENT_PAYPAL_STANDARD_PAGE_STYLE;
        }

        if (MODULE_PAYMENT_PAYPAL_STANDARD_EWP_STATUS == 'True') {
            $parameters['cert_id'] = MODULE_PAYMENT_PAYPAL_STANDARD_EWP_CERT_ID;

            $random_string = rand(100000, 999999) . '-' . $customer_id . '-';

            $data = '';
            reset($parameters);
            while (list($key, $value) = each($parameters)) {
                $data .= $key . '=' . $value . "\n";
            }

            $fp = fopen(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'data.txt', 'w');
            fwrite($fp, $data);
            fclose($fp);

            unset($data);

            if (function_exists('openssl_pkcs7_sign') && function_exists('openssl_pkcs7_encrypt')) {
                openssl_pkcs7_sign(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'data.txt', MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt', file_get_contents(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_PUBLIC_KEY), file_get_contents(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_PRIVATE_KEY), array('From' => MODULE_PAYMENT_PAYPAL_STANDARD_ID), PKCS7_BINARY);

                unlink(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'data.txt');

                // remove headers from the signature
                $signed = file_get_contents(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt');
                $signed = explode("\n\n", $signed);
                $signed = base64_decode($signed[1]);

                $fp = fopen(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt', 'w');
                fwrite($fp, $signed);
                fclose($fp);

                unset($signed);

                openssl_pkcs7_encrypt(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt', MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'encrypted.txt', file_get_contents(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_PAYPAL_KEY), array('From' => MODULE_PAYMENT_PAYPAL_STANDARD_ID), PKCS7_BINARY);

                unlink(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt');

                // remove headers from the encrypted result
                $data = file_get_contents(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'encrypted.txt');
                $data = explode("\n\n", $data);
                $data = '-----BEGIN PKCS7-----' . "\n" . $data[1] . "\n" . '-----END PKCS7-----';

                unlink(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'encrypted.txt');
            } else {
                exec(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_OPENSSL . ' smime -sign -in ' . MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'data.txt -signer ' . MODULE_PAYMENT_PAYPAL_STANDARD_EWP_PUBLIC_KEY . ' -inkey ' . MODULE_PAYMENT_PAYPAL_STANDARD_EWP_PRIVATE_KEY . ' -outform der -nodetach -binary > ' . MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt');
                unlink(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'data.txt');

                exec(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_OPENSSL . ' smime -encrypt -des3 -binary -outform pem ' . MODULE_PAYMENT_PAYPAL_STANDARD_EWP_PAYPAL_KEY . ' < ' . MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt > ' . MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'encrypted.txt');
                unlink(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'signed.txt');

                $fh = fopen(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'encrypted.txt', 'rb');
                $data = fread($fh, filesize(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'encrypted.txt'));
                fclose($fh);

                unlink(MODULE_PAYMENT_PAYPAL_STANDARD_EWP_WORKING_DIRECTORY . '/' . $random_string . 'encrypted.txt');
            }

            $process_button_string = tep_draw_hidden_field('cmd', '_s-xclick') .
                    tep_draw_hidden_field('encrypted', $data);

            unset($data);
        } else {
            reset($parameters);
            return $parameters;
        }
        return $parameters;
    }

    private function getSendToAddressId() {
        global $customer_default_address_id, $customer_id, $sendto;
        if (empty($sendto) || !tep_session_is_registered('sendto')) {
            $sendto = $customer_default_address_id;
            tep_session_register('sendto');
        } else {
            // verify the selected shipping address
            if ((is_array($sendto) && empty($sendto)) || is_numeric($sendto)) {
                $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int) $customer_id . "' and address_book_id = '" . (int) $sendto . "'");
                $check_address = tep_db_fetch_array($check_address_query);
                if ($check_address['total'] != '1') {
                    $sendto = $customer_default_address_id;
                    if (tep_session_is_registered('shipping'))
                        tep_session_unregister('shipping');
                }
            }
        }
        return $sendto;
    }

    public function getShippingAddress() {
        $sendTo = $this->getSendToAddressId();
        if ($sendTo) {
            $userService = ServiceFactory::factory('User');
            return $userService->getAddress($sendTo);
        }
        return array();
    }

    public function getBillingAddress() {
        $billto = $this->getBillToAddressId();
        if ($billto) {
            $userService = ServiceFactory::factory('User');
            return $userService->getAddress($billto);
        }
        return array();
    }

    public function getBillToAddressId() {
        global $billto, $customer_default_address_id, $customer_id;
        // if no billing destination address was selected, use the customers own address as default
        if (empty($billto) || !tep_session_is_registered('billto')) {
            $billto = $customer_default_address_id;
            tep_session_register('billto');
        } else {
            // verify the selected billing address
            if ((is_array($billto) && empty($billto)) || is_numeric($billto)) {
                $check_address_query = tep_db_query("select count(*) as total from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int) $customer_id . "' and address_book_id = '" . (int) $billto . "'");
                $check_address = tep_db_fetch_array($check_address_query);
                if ($check_address['total'] != '1') {
                    $billto = $customer_default_address_id;
                    if (tep_session_is_registered('payment'))
                        tep_session_unregister('payment');
                }
            }
        }
        return $billto;
    }

    public function getPaymentMethods() {

        $paypalwpp = array();
        $paypal = array();
        if (defined('MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS') && MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS == 'True') {
            $paypalwpp['pm_id'] = 'paypalwpp';
            $paypalwpp['pm_title'] = '';
            $paypalwpp['pm_code'] = 'paypal_express';
            $paypalwpp['pm_description'] = '';
            $paypalwpp['img_url'] = '';
        }
        if (defined('MODULE_PAYMENT_PAYPAL_STANDARD_STATUS') && MODULE_PAYMENT_PAYPAL_STANDARD_STATUS == 'True') {
            $paypal['pm_id'] = 'paypal';
            $paypal['pm_title'] = '';
            $paypal['pm_code'] = 'paypal_standard';
            $paypal['pm_description'] = '';
            $paypal['img_url'] = '';
        }

        if ($paypalwpp) {
            $paymentMethods[] = $paypalwpp;
        }

        if ($paypal) {
            $paymentMethods[] = $paypal;
        }
        return $paymentMethods;
    }

    public function getOrderShippingMethods() {
        global $shipping, $currency, $order;
        if ($this->isVirtual) { //is virtual cart or not
            return array();
        }
        $shipping_modules = $this->getShippingModules();
        $freeShipping = $this->getFreeShippingMethod($order); //is free shipping or not
        if (!is_null($freeShipping)) {
            return $freeShipping;
        }
        // get all available shipping quotes
        $quotes = $shipping_modules->quote();
        if (!$shipping || $shipping['id'] == 'free_free') {
            $shipping = $shipping_modules->cheapest();
        }
        $availableShippingMethods = array();
        for ($i = 0, $n = sizeof($quotes); $i < $n; $i++) {
            for ($j = 0, $n2 = sizeof($quotes[$i]['methods']); $j < $n2; $j++) {
                $shippingMethod = array();
                $shippingMethod['sm_id'] = $quotes[$i]['id'] . '_' . $quotes[$i]['methods'][$j]['id'];
                $shippingMethod['title'] = "{$quotes[$i]['module']}({$quotes[$i]['methods'][$j]['title']})";
                $shippingMethod['price'] = currency_price_value(tep_add_tax($quotes[$i]['methods'][$j]['cost'], (isset($quotes[$i]['tax']) ? $quotes[$i]['tax'] : 0)));
                $shippingMethod['currency'] = $currency;
                $shippingMethod['description'] = '';
                $availableShippingMethods[] = $shippingMethod;
            }
        }
        return $availableShippingMethods;
    }

    public function getShippingModules() {
        global $cartID, $cart, $total_weight, $total_count, $free_shipping, $order, $shipping_modules;

        if (!class_exists('order')) {
            include(DIR_WS_CLASSES . 'order.php');
        }
        $order = new order;
        // register a random ID in the session to check throughout the checkout procedure
        // against alterations in the shopping cart contents
        if (!tep_session_is_registered('cartID'))
            tep_session_register('cartID');
        $cartID = $cart->cartID;
        $total_weight = $cart->show_weight();
        $total_count = $cart->count_contents();
        include_once (DIR_WS_CLASSES . 'shipping.php');
        if (!$shipping_modules) {
            $shipping_modules = new shipping;
        }
        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true')) {
            $pass = false;
            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
                case 'national':
                    if ($order->delivery['country_id'] == STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;
                case 'international':
                    if ($order->delivery['country_id'] != STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;
                case 'both':
                    $pass = true;
                    break;
            }
            $free_shipping = false;
            if (($pass == true) && ($order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) {
                $free_shipping = true;
            }
        } else {
            $free_shipping = false;
        }
        if (!tep_session_is_registered('shipping')) {
            tep_session_register('shipping');
        }
        return $this->shippingModules = $shipping_modules;
    }

    /**
     * get free shipping method,if not return null
     * @param type $order
     * @return string
     * @author hujs
     */
    public function getFreeShippingMethod($order) {
        global $currency, $language, $shipping;

        if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true')) {
            $pass = false;

            switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
                case 'national':
                    if ($order->delivery['country_id'] == STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;
                case 'international':
                    if ($order->delivery['country_id'] != STORE_COUNTRY) {
                        $pass = true;
                    }
                    break;
                case 'both':
                    $pass = true;
                    break;
            }
        }

        $freeShipping = ($pass == true) && ($order->info['subtotal'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER); //free shipping or not
        $this->freeShipping = $freeShipping;
        if ($freeShipping) {
            include_once(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_shipping.php');
            $shipping = array(
                'id' => 'free_free',
                'title' => FREE_SHIPPING_TITLE,
                'cost' => 0
            );
            $shippingMethod = array();
            $shippingMethod['sm_id'] = 'free_free';
            $shippingMethod['title'] = FREE_SHIPPING_TITLE;
            $shippingMethod['price'] = 0;
            $shippingMethod['currency'] = $currency;
            $shippingMethod['description'] = sprintf(FREE_SHIPPING_DESCRIPTION, MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER);

            return array($shippingMethod);
        }

        return null;
    }

    public function selectShippingMethod($shipping_methods) {
        global $shipping;
        if (is_array($shipping)) {
            return $shipping['id'];
        } else if (is_string($shipping) && $shipping) {
            return $shipping;
        }
        reset($shipping_methods);
        $current = current($shipping_methods);
        if ($current) {
            $this->updateShippingMethod($current['sm_id']);
            return $current['sm_id'];
        } else {
            return '';
        }
    }

    public function getPriceInfos() {
        global $currency, $order_total_modules, $order;
        if (!class_exists('order')) {
            require(DIR_WS_CLASSES . 'order.php');
        }
        $order = new order;
        if (!class_exists('order_total')) {
            require(DIR_WS_CLASSES . 'order_total.php');
        }
        if (!$order_total_modules) {
            $order_total_modules = new order_total;
        }
        $totals = $order_total_modules->process();
        $priceInfo = array();
        $position = 0;
        foreach ($totals as $total) {
            $priceInfo[] = array(
                'title' => $total['title'],
                'type' => $total['code'] == 'ot_total' ? 'total' : $total['code'],
                'price' => currency_price_value($total['value']),
                'currency' => $currency,
                'position' => $position++
            );
        }
        return $priceInfo;
    }

    public function addAddress($address) {
        global $sendto;
        $result = ServiceFactory::factory('User')->addAddress($address);
        if (is_numeric($result)) {
            $sendto = $result;
        }
        return $result;
    }

    public function updateAddress($addressBookId, $address = array()) {
        global $sendto;
        if ($addressBookId) {
            $sendto = $addressBookId;
            if ($address) {
                $address['address_book_id'] = $addressBookId;
                $userService = ServiceFactory::factory('User');
                $userService->updateAddress($address);
            }
        }
    }

    // update actions
    public function updateShippingMethod($shippingMethod) {
        global $free_shipping, $shipping, $order;
        $quote = array();
        $shipping_modules = $this->getShippingModules();
        if ($shippingMethod) {
            $shipping = $shippingMethod;
            list($module, $method) = explode('_', $shippingMethod);
            global $$module;
            if (is_object($$module) || ($shipping == 'free_free')) {
                if ($shipping == 'free_free') {
                    $quote[0]['methods'][0]['title'] = FREE_SHIPPING_TITLE;
                    $quote[0]['methods'][0]['cost'] = '0';
                } else {
                    $quote = $shipping_modules->quote($method, $module);
                }
                if (isset($quote['error'])) {
                    tep_session_unregister('shipping');
                } else {
                    if ((isset($quote[0]['methods'][0]['title'])) && (isset($quote[0]['methods'][0]['cost']))) {
                        $shipping =
                                array(
                                    'id' => $shipping,
                                    'title' => (($free_shipping == true) ? $quote[0]['methods'][0]['title'] : $quote[0]['module'] . ' (' . $quote[0]['methods'][0]['title'] . ')'),
                                    'cost' => $quote[0]['methods'][0]['cost']
                        );
                        if ($order) {
                            if (class_exists('order')) {
                                // refresh the order
                                $order = new $order;
                            }
                        }
                    }
                }
            } else {
                tep_session_unregister('shipping');
            }
        }
    }

    public function placeOrder() {
        global $payment, $order, $order_total_modules, $cartID, $cart;

        $returnUrl = $_REQUEST['return_url'];
        $cancelUrl = $_REQUEST['cancel_url'];
        if (!class_exists('order')) {
            require(DIR_WS_CLASSES . 'order.php');
        }
        $order = new order;

        // Stock Check
        $any_out_of_stock = false;
        $error = array();
        if (STOCK_CHECK == 'true') {
            for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                if (($result = tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty']))) {
                    $any_out_of_stock = true;
                    $error[] = $result;
                }
            }
            // Out of Stock
            if ((STOCK_ALLOW_CHECKOUT != 'true') && ($any_out_of_stock == true)) {
                return join('<br>', $error);
            }
        }

        $payment = 'paypal_standard';
        $paymentService = ServiceFactory::factory('KancartPayment');
        if (!class_exists('shipping')) {
            require(DIR_WS_CLASSES . 'shipping.php');
        }
        $shipping_modules = new shipping();
        global $order_total_modules;
        require(DIR_WS_CLASSES . 'order_total.php');
        $order_total_modules = new order_total;
        $order_total_modules->process();
        // load the selected payment module
        if (!class_exists('payment')) {
            require(DIR_WS_CLASSES . 'payment.php');
        }
        $paymentModules = new payment($payment);
        if (!tep_session_is_registered('payment')) {
            tep_session_register('payment');
        }
        //      $paymentModules->selection(); //Delete the payment failed orders
        $paymentModules->update_status();
        if (is_array($paymentModules->modules)) {
            if (empty($cart->cartID)) {
                $cartID = $cart->cartID = $cart->generate_cart_id();
            }

            empty($cartID) && $cartID = $cart->cartID;
            tep_session_is_registered('cartID') || tep_session_register('cartID');
            $paymentModules->pre_confirmation_check();
        }
        global $$payment;
        $form_action_url = $$payment->form_action_url;
        $paymentModules->confirmation();
        $paypalParams = ServiceFactory::factory('Checkout')->buildWpsRedirectParams($GLOBALS[$paymentModules->selected_module], $returnUrl, $cancelUrl);
        return array(
            'paypal_redirect_url' => $form_action_url,
            'paypal_params' => $paypalParams
        );
    }

}

?>

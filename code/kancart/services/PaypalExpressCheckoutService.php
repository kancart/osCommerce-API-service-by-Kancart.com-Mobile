<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

require_once('includes/modules/payment/paypal_express.php');

class kancart_paypal_express extends paypal_express {

    var $returlUrl, $cancelUrl;

    function kancart_paypal_express($returnUrl, $cancelUrl) {
        global $language;

        $this->paypal_express();
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;

        include_once(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PROCESS);
    }

    function before_process() {
        global $customer_id, $order, $sendto, $ppe_token, $ppe_payerid, $comments, $response_array;
        if (empty($comments)) {
            if (isset($_REQUEST['custom_kc_comments']) && tep_not_null($_REQUEST['custom_kc_comments'])) {
                $comments = tep_db_prepare_input($_REQUEST['custom_kc_comments']);
                $order->info['comments'] = $comments;
            }
        }
        $params = array('TOKEN' => $ppe_token,
            'PAYERID' => $ppe_payerid,
            'AMT' => $this->format_raw($order->info['total']),
            'CURRENCYCODE' => $order->info['currency']);

        if (is_numeric($sendto) && ($sendto > 0)) {
            $params['SHIPTONAME'] = $order->delivery['firstname'] . ' ' . $order->delivery['lastname'];
            $params['SHIPTOSTREET'] = $order->delivery['street_address'];
            $params['SHIPTOCITY'] = $order->delivery['city'];
            $params['SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
            $params['SHIPTOCOUNTRYCODE'] = $order->delivery['country']['iso_code_2'];
            $params['SHIPTOZIP'] = $order->delivery['postcode'];
        }

        $response_array = $this->doExpressCheckoutPayment($params);

        if (($response_array['ACK'] != 'Success') && ($response_array['ACK'] != 'SuccessWithWarning')) {
            return $response_array;
        }
        return TRUE;
    }

    function setExpressCheckout($parameters) {
        if (MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER == 'Live') {
            $api_url = 'https://api-3t.paypal.com/nvp';
        } else {
            $api_url = 'https://api-3t.sandbox.paypal.com/nvp';
        }

        $params = array(
            'VERSION' => $this->api_version,
            'METHOD' => 'SetExpressCheckout',
            'PAYMENTACTION' => ((MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_METHOD == 'Sale') || (!tep_not_null(MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME)) ? 'Sale' : 'Authorization'),
            'RETURNURL' => $this->returnUrl,
            'CANCELURL' => $this->cancelUrl
        );

        if (tep_not_null(MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME)) {
            $params['USER'] = MODULE_PAYMENT_PAYPAL_EXPRESS_API_USERNAME;
            $params['PWD'] = MODULE_PAYMENT_PAYPAL_EXPRESS_API_PASSWORD;
            $params['SIGNATURE'] = MODULE_PAYMENT_PAYPAL_EXPRESS_API_SIGNATURE;
        } else {
            $params['SUBJECT'] = MODULE_PAYMENT_PAYPAL_EXPRESS_SELLER_ACCOUNT;
        }

        if (MODULE_PAYMENT_PAYPAL_EXPRESS_ACCOUNT_OPTIONAL == 'True') {
            $params['SOLUTIONTYPE'] = 'Sole';
        }

        if (is_array($parameters) && !empty($parameters)) {
            $params = array_merge($params, $parameters);
        }

        $post_string = '';

        foreach ($params as $key => $value) {
            $post_string .= $key . '=' . urlencode(utf8_encode(trim($value))) . '&';
        }

        $post_string = substr($post_string, 0, -1);

        $response = $this->sendTransactionToGateway($api_url, $post_string);
        $response_array = array();
        parse_str($response, $response_array);

        if (($response_array['ACK'] != 'Success') && ($response_array['ACK'] != 'SuccessWithWarning')) {
            if (method_exists($this, 'sendDebugEmail')) {
                $this->sendDebugEmail();
            }
        }

        return $response_array;
    }

}

class PaypalExpressCheckoutService {

    public function startExpressCheckout($paypal_express, $commit = false) {
        global $order;
        if (MODULE_PAYMENT_PAYPAL_EXPRESS_TRANSACTION_SERVER == 'Live') {
            $paypal_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout-mobile';
        } else {
            $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout-mobile';
        }

        if (!class_exists('order')) {
            include(DIR_WS_CLASSES . 'order.php');
        }
        $order = new order;

        $params = array('CURRENCYCODE' => $order->info['currency']);

        // A billing address is required for digital orders so we use the shipping address PayPal provides
        //      if ($order->content_type == 'virtual') {
        //        $params['NOSHIPPING'] = '1';
        //      }

        $line_item_no = 0;
        $items_total = 0;
        $tax_total = 0;

        foreach ($order->products as $product) {
            $params['L_NAME' . $line_item_no] = $product['name'];
            $params['L_AMT' . $line_item_no] = $paypal_express->format_raw($product['final_price']);
            $params['L_NUMBER' . $line_item_no] = $product['id'];
            $params['L_QTY' . $line_item_no] = $product['qty'];

            $product_tax = tep_calculate_tax($product['final_price'], $product['tax']);

            $params['L_TAXAMT' . $line_item_no] = $paypal_express->format_raw($product_tax);
            $tax_total += $paypal_express->format_raw($product_tax) * $product['qty'];

            $items_total += $paypal_express->format_raw($product['final_price']) * $product['qty'];

            $line_item_no++;
        }

        $params['ITEMAMT'] = $items_total;
        $params['TAXAMT'] = $tax_total;

        if (tep_not_null($order->delivery['firstname'])) {
            $params['ADDROVERRIDE'] = '1';
            $params['SHIPTONAME'] = $order->delivery['firstname'] . ' ' . $order->delivery['lastname'];
            $params['SHIPTOSTREET'] = $order->delivery['street_address'];
            $params['SHIPTOCITY'] = $order->delivery['city'];
            $params['SHIPTOSTATE'] = tep_get_zone_code($order->delivery['country']['id'], $order->delivery['zone_id'], $order->delivery['state']);
            $params['SHIPTOCOUNTRYCODE'] = $order->delivery['country']['iso_code_2'];
            $params['SHIPTOZIP'] = $order->delivery['postcode'];
        }
        $params['AMT'] = $paypal_express->format_raw($params['ITEMAMT'] + $params['TAXAMT'], '', 1);
        $params['MAXAMT'] = $paypal_express->format_raw($params['AMT'] + 100, '', 1); // safely pad higher for dynamic shipping rates (eg, USPS express)
        $response_array = $paypal_express->setExpressCheckout($params);
        if (($response_array['ACK'] == 'Success') || ($response_array['ACK'] == 'SuccessWithWarning')) {
            return array(
                'token' => $response_array['TOKEN'],
                'paypal_redirect_url' => $paypal_url . '&useraction=' . ($commit ? 'commit' : 'continue') . '&token=' . $response_array['TOKEN']
            );
        }

        return "{$response_array['L_SHORTMESSAGE0']} => {$response_array['L_LONGMESSAGE0']}<br>";
    }

    private function forceLogin($response_array) {
        global $customer_id, $customer_first_name, $customer_default_address_id, $sessiontoken;
        $email_address = tep_db_prepare_input($response_array['EMAIL']);
        $telephone = tep_db_prepare_input($response_array['SHIPTOPHONENUM']);
        // check whether user email already exists
        $check_query = tep_db_query("select * from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($email_address) . "' limit 1");
        if (tep_db_num_rows($check_query)) {
            $check = tep_db_fetch_array($check_query);
            $customer_id = $check['customers_id'];
            $customers_firstname = $check['customers_firstname'];
            $customer_default_address_id = $check['customers_default_address_id'];
        } else {
            global $guest_uname;
            // if user email not exists
            $customers_firstname = tep_db_prepare_input($response_array['FIRSTNAME']);
            $customers_lastname = tep_db_prepare_input($response_array['LASTNAME']);
            $customer_password = tep_create_random_value(max(ENTRY_PASSWORD_MIN_LENGTH, 8));

            $sql_data_array = array(
                'customers_firstname' => $customers_firstname,
                'customers_lastname' => $customers_lastname,
                'customers_email_address' => $email_address,
                'customers_telephone' => $telephone,
                'customers_fax' => '',
                'customers_newsletter' => '0',
                'customers_password' => tep_encrypt_password($customer_password));

            if (empty($telephone) && isset($response_array['PHONENUM']) && tep_not_null($response_array['PHONENUM'])) {
                $customers_telephone = tep_db_prepare_input($response_array['PHONENUM']);
                $sql_data_array['customers_telephone'] = $customers_telephone;
            }
            // create customer account
            tep_db_perform(TABLE_CUSTOMERS, $sql_data_array);

            $customer_id = tep_db_insert_id();
            $guest_uname = $email_address;
            tep_session_register('guest_uname');

            tep_db_query("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int) $customer_id . "', '0', now())");

            // build the message content
            $name = $customers_firstname . ' ' . $customers_lastname;
            $email_text = sprintf(EMAIL_GREET_NONE, $customers_firstname) . EMAIL_WELCOME . sprintf(MODULE_PAYMENT_PAYPAL_EXPRESS_EMAIL_PASSWORD, $email_address, $customer_password) . EMAIL_TEXT . EMAIL_CONTACT . EMAIL_WARNING;
            tep_mail($name, $email_address, EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }

        if (SESSION_RECREATE == 'True') {
            tep_session_recreate();
        }

        $customer_first_name = $customers_firstname;
        tep_session_register('customer_id');
        tep_session_register('customer_first_name');
        // reset session token
        $sessiontoken = md5(tep_rand() . tep_rand() . tep_rand() . tep_rand());
    }

    private function extractShippingInfo($response_array) {
        global $customer_default_address_id, $customer_id, $sendto, $ship_country_id, $ship_zone_id;
        // check if paypal shipping address exists in the address book
        $ship_firstname = tep_db_prepare_input(substr($response_array['SHIPTONAME'], 0, strpos($response_array['SHIPTONAME'], ' ')));
        $ship_lastname = tep_db_prepare_input(substr($response_array['SHIPTONAME'], strpos($response_array['SHIPTONAME'], ' ') + 1));
        $ship_address = tep_db_prepare_input($response_array['SHIPTOSTREET']);
        $ship_city = tep_db_prepare_input($response_array['SHIPTOCITY']);
        $ship_zone = tep_db_prepare_input($response_array['SHIPTOSTATE']);
        $ship_zone_id = 0;
        $ship_postcode = tep_db_prepare_input($response_array['SHIPTOZIP']);
        $ship_country = tep_db_prepare_input($response_array['SHIPTOCOUNTRYCODE']);
        $ship_country_id = 0;
        $ship_address_format_id = 1;

        $country_query = tep_db_query("select countries_id, address_format_id from " . TABLE_COUNTRIES . " where countries_iso_code_2 = '" . tep_db_input($ship_country) . "' limit 1");
        if (tep_db_num_rows($country_query)) {
            $country = tep_db_fetch_array($country_query);
            $ship_country_id = $country['countries_id'];
            $ship_address_format_id = $country['address_format_id'];
        }

        if ($ship_country_id > 0) {
            $zone_query = tep_db_query("select zone_id from " . TABLE_ZONES . " where zone_country_id = '" . (int) $ship_country_id . "' and (zone_name = '" . tep_db_input($ship_zone) . "' or zone_code = '" . tep_db_input($ship_zone) . "') limit 1");
            if (tep_db_num_rows($zone_query)) {
                $zone = tep_db_fetch_array($zone_query);
                $ship_zone_id = $zone['zone_id'];
            }
        }

        $check_query = tep_db_query("select address_book_id from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int) $customer_id . "' and entry_firstname = '" . tep_db_input($ship_firstname) . "' and entry_lastname = '" . tep_db_input($ship_lastname) . "' and entry_street_address = '" . tep_db_input($ship_address) . "' and entry_postcode = '" . tep_db_input($ship_postcode) . "' and entry_city = '" . tep_db_input($ship_city) . "' and (entry_state = '" . tep_db_input($ship_zone) . "' or entry_zone_id = '" . (int) $ship_zone_id . "') and entry_country_id = '" . (int) $ship_country_id . "' limit 1");
        if (tep_db_num_rows($check_query)) {
            $check = tep_db_fetch_array($check_query);
            $sendto = $check['address_book_id'];
        } else {
            // insert an address to the address book
            $sql_data_array = array(
                'customers_id' => $customer_id,
                'entry_firstname' => $ship_firstname,
                'entry_lastname' => $ship_lastname,
                'entry_street_address' => $ship_address,
                'entry_postcode' => $ship_postcode,
                'entry_city' => $ship_city,
                'entry_country_id' => $ship_country_id);

            if (ACCOUNT_STATE == 'true') {
                if ($ship_zone_id > 0) {
                    $sql_data_array['entry_zone_id'] = $ship_zone_id;
                    $sql_data_array['entry_state'] = '';
                } else {
                    $sql_data_array['entry_zone_id'] = '0';
                    $sql_data_array['entry_state'] = $ship_zone;
                }
            }

            tep_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

            $address_id = tep_db_insert_id();

            $sendto = $address_id;

            if ($customer_default_address_id < 1) {
                tep_db_query("update " . TABLE_CUSTOMERS . " set customers_default_address_id = '" . (int) $address_id . "' where customers_id = '" . (int) $customer_id . "'");
                $customer_default_address_id = $address_id;
            }
        }
    }

    private function updatePaymentStatus($paypal_express, $response_array) {
        global $payment, $ppe_token, $ppe_payerid, $ppe_payerstatus, $ppe_addressstatus;
        if (!tep_session_is_registered('payment')) {
            tep_session_register('payment');
        }
        $payment = $paypal_express->code;

        if (!tep_session_is_registered('ppe_token')) {
            tep_session_register('ppe_token');
        }
        $ppe_token = $response_array['TOKEN'];

        if (!tep_session_is_registered('ppe_payerid')) {
            tep_session_register('ppe_payerid');
        }
        $ppe_payerid = $response_array['PAYERID'];

        if (!tep_session_is_registered('ppe_payerstatus')) {
            tep_session_register('ppe_payerstatus');
        }
        $ppe_payerstatus = $response_array['PAYERSTATUS'];

        if (!tep_session_is_registered('ppe_addressstatus')) {
            tep_session_register('ppe_addressstatus');
        }
        $ppe_addressstatus = $response_array['ADDRESSSTATUS'];
    }

    public function returnFromPaypal($paypal_express) {
        global $customer_id, $shipping, $customer_country_id, $customer_zone_id, $billto, $sendto, $cart, $ship_country_id, $ship_zone_id, $ppe_token;
        // if there is nothing in the customers cart, redirect them to the shopping cart page
        $response_array = $paypal_express->getExpressCheckoutDetails($_REQUEST['token']);

        if (($response_array['ACK'] == 'Success') || ($response_array['ACK'] == 'SuccessWithWarning')) {
            $force_login = false;
            $ppe_token = $_REQUEST['token'];
            if(!tep_session_is_registered('ppe_token')) {
                tep_session_register('ppe_token');
            }
            // check if e-mail address exists in database and login or create customer account
            if (!tep_session_is_registered('customer_id') || !$customer_id) {
                $force_login = true;
                $this->forceLogin($response_array);
            }
            $this->extractShippingInfo($response_array);
            if ($force_login == true) {
                $customer_country_id = $ship_country_id;
                $customer_zone_id = $ship_zone_id;
                tep_session_register('customer_default_address_id');
                tep_session_register('customer_country_id');
                tep_session_register('customer_zone_id');
                $billto = $sendto;
            }
            if ($cart->get_content_type() == 'virtual') {
                if (!tep_session_is_registered('shipping')) {
                    tep_session_register('shipping');
                }
                $shipping = false;
                $sendto = false;
            }
            $this->updatePaymentStatus($paypal_express, $response_array);
        }
    }

    function pay() {
        global $customer_id, $currencies, $order, $languages_id, $sendto, $billto, $payment, $cart, $insert_id, $language;
        require(DIR_WS_CLASSES . 'payment.php');
        if (!class_exists('order')) {
            require(DIR_WS_CLASSES . 'order.php');
        }
        $order = new order;

        require(DIR_WS_CLASSES . 'order_total.php');
        $order_total_modules = new order_total;

        $order_totals = $order_total_modules->process();
        $payment_modules = new kancart_paypal_express('', '');
        $payment_modules->update_status();
        // load the before_process function from the payment modules
        $result = $payment_modules->before_process();
        if ($result !== TRUE) {
            return $result;
        }

        $sql_data_array = array(
            'customers_id' => $customer_id,
            'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
            'customers_company' => $order->customer['company'],
            'customers_street_address' => $order->customer['street_address'],
            'customers_suburb' => $order->customer['suburb'],
            'customers_city' => $order->customer['city'],
            'customers_postcode' => $order->customer['postcode'],
            'customers_state' => $order->customer['state'],
            'customers_country' => $order->customer['country']['title'],
            'customers_telephone' => $order->customer['telephone'],
            'customers_email_address' => $order->customer['email_address'],
            'customers_address_format_id' => $order->customer['format_id'],
            'delivery_name' => trim($order->delivery['firstname'] . ' ' . $order->delivery['lastname']),
            'delivery_company' => $order->delivery['company'],
            'delivery_street_address' => $order->delivery['street_address'],
            'delivery_suburb' => $order->delivery['suburb'],
            'delivery_city' => $order->delivery['city'],
            'delivery_postcode' => $order->delivery['postcode'],
            'delivery_state' => $order->delivery['state'],
            'delivery_country' => $order->delivery['country']['title'],
            'delivery_address_format_id' => $order->delivery['format_id'],
            'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
            'billing_company' => $order->billing['company'],
            'billing_street_address' => $order->billing['street_address'],
            'billing_suburb' => $order->billing['suburb'],
            'billing_city' => $order->billing['city'],
            'billing_postcode' => $order->billing['postcode'],
            'billing_state' => $order->billing['state'],
            'billing_country' => $order->billing['country']['title'],
            'billing_address_format_id' => $order->billing['format_id'],
            'payment_method' => $order->info['payment_method'],
            'cc_type' => $order->info['cc_type'],
            'cc_owner' => $order->info['cc_owner'],
            'cc_number' => $order->info['cc_number'],
            'cc_expires' => $order->info['cc_expires'],
            'date_purchased' => 'now()',
            'orders_status' => $order->info['order_status'],
            'currency' => $order->info['currency'],
            'currency_value' => $order->info['currency_value']);
        tep_db_perform(TABLE_ORDERS, $sql_data_array);
        $insert_id = tep_db_insert_id();
        $order->info['order_id'] = $insert_id;
        for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
            $sql_data_array = array('orders_id' => $insert_id,
                'title' => $order_totals[$i]['title'],
                'text' => $order_totals[$i]['text'],
                'value' => $order_totals[$i]['value'],
                'class' => $order_totals[$i]['code'],
                'sort_order' => $order_totals[$i]['sort_order']);
            tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
        }

        $customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
        $sql_data_array = array(
            'orders_id' => $insert_id,
            'orders_status_id' => $order->info['order_status'],
            'date_added' => 'now()',
            'customer_notified' => $customer_notification,
            'comments' => $order->info['comments']);
        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        // initialized for the email confirmation
        $products_ordered = '';

        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            // Stock Update - Joao Correia
            if (STOCK_LIMITED == 'true') {
                if (DOWNLOAD_ENABLED == 'true') {
                    $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename 
                            FROM " . TABLE_PRODUCTS . " p
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                             ON p.products_id=pa.products_id
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                             ON pa.products_attributes_id=pad.products_attributes_id
                            WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
                    // Will work with only one option for downloadable products
                    // otherwise, we have to build the query dynamically with a loop
                    $products_attributes = $order->products[$i]['attributes'];
                    if (is_array($products_attributes)) {
                        $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                    }
                    $stock_query = tep_db_query($stock_query_raw);
                } else {
                    $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                }
                if (tep_db_num_rows($stock_query) > 0) {
                    $stock_values = tep_db_fetch_array($stock_query);
                    // do not decrement quantities if products_attributes_filename exists
                    if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
                        $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
                    } else {
                        $stock_left = $stock_values['products_quantity'];
                    }
                    tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false')) {
                        tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    }
                }
            }

            // Update products_ordered (for bestsellers list)
            tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

            $sql_data_array = array('orders_id' => $insert_id,
                'products_id' => tep_get_prid($order->products[$i]['id']),
                'products_model' => $order->products[$i]['model'],
                'products_name' => $order->products[$i]['name'],
                'products_price' => $order->products[$i]['price'],
                'final_price' => $order->products[$i]['final_price'],
                'products_tax' => $order->products[$i]['tax'],
                'products_quantity' => $order->products[$i]['qty']);
            tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
            $order_products_id = tep_db_insert_id();

            //------insert customer choosen option to order--------
            $attributes_exist = '0';
            $products_ordered_attributes = '';
            if (isset($order->products[$i]['attributes'])) {
                $attributes_exist = '1';
                for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                    if (DOWNLOAD_ENABLED == 'true') {
                        $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename 
                               from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa 
                               left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                on pa.products_attributes_id=pad.products_attributes_id
                               where pa.products_id = '" . $order->products[$i]['id'] . "' 
                                and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' 
                                and pa.options_id = popt.products_options_id 
                                and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' 
                                and pa.options_values_id = poval.products_options_values_id 
                                and popt.language_id = '" . $languages_id . "' 
                                and poval.language_id = '" . $languages_id . "'";
                        $attributes = tep_db_query($attributes_query);
                    } else {
                        $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
                    }
                    $attributes_values = tep_db_fetch_array($attributes);

                    $sql_data_array = array('orders_id' => $insert_id,
                        'orders_products_id' => $order_products_id,
                        'products_options' => $attributes_values['products_options_name'],
                        'products_options_values' => $attributes_values['products_options_values_name'],
                        'options_values_price' => $attributes_values['options_values_price'],
                        'price_prefix' => $attributes_values['price_prefix']);
                    tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

                    if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                        $sql_data_array = array('orders_id' => $insert_id,
                            'orders_products_id' => $order_products_id,
                            'orders_products_filename' => $attributes_values['products_attributes_filename'],
                            'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                            'download_count' => $attributes_values['products_attributes_maxcount']);
                        tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                    }
                    $products_ordered_attributes .= "\n\t" . $attributes_values['products_options_name'] . ' ' . $attributes_values['products_options_values_name'];
                }
            }
            //------insert customer choosen option eof ----
            $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
        }

        include_once (DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PROCESS);

        // lets start with the email confirmation
        $email_order = STORE_NAME . "\n" .
                EMAIL_SEPARATOR . "\n" .
                EMAIL_TEXT_ORDER_NUMBER . ' ' . $insert_id . "\n" .
                EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $insert_id, 'SSL', false) . "\n" .
                EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";
        if ($order->info['comments']) {
            $email_order .= tep_db_output($order->info['comments']) . "\n\n";
        }
        $email_order .= EMAIL_TEXT_PRODUCTS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                $products_ordered .
                EMAIL_SEPARATOR . "\n";

        for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
            $email_order .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
        }

        if ($order->content_type != 'virtual') {
            $email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" .
                    EMAIL_SEPARATOR . "\n" .
                    tep_address_label($customer_id, $sendto, 0, '', "\n") . "\n";
        }

        $email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                tep_address_label($customer_id, $billto, 0, '', "\n") . "\n\n";
        if (is_object($$payment)) {
            $email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" .
                    EMAIL_SEPARATOR . "\n";
            $payment_class = $$payment;
            $email_order .= $order->info['payment_method'] . "\n\n";
            if (isset($payment_class->email_footer)) {
                $email_order .= $payment_class->email_footer . "\n\n";
            }
        }
        tep_mail($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

        // send emails to other people
        if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
            tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }

        // load the after_process function from the payment modules
        $payment_modules->after_process();

        $cart->reset(true);

        // unregister session variables used during checkout
        tep_session_unregister('ppe_token');
        tep_session_unregister('sendto');
        tep_session_unregister('billto');
        tep_session_unregister('shipping');
        tep_session_unregister('payment');
        tep_session_unregister('comments');
        tep_session_unregister('return_url');
        tep_session_unregister('cancel_url');
        return true;
    }

}

?>

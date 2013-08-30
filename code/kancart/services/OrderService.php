<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * Order Service, Utility
 * @package services 
 */
class OrderService {

    /**
     * get user orders information
     * @param type $userId
     * @return type 
     * @author hujs
     */
    public function getOrderInfos(array $parameter) {
        $orderInfos = array();

        $userId = $parameter['customer_id'];
        $pageNo = $parameter['page_no'];
        $pageSize = $parameter['page_size'];

        $orders = $this->getOrderList($userId, $pageNo, $pageSize);
        foreach ($orders as $order) {
            $orderItem = array();

            $this->initOrderDetail($orderItem, $order);
            $orderItem['price_infos'] = $this->getPriceInfos($orderItem, $order);
            $orderItem['order_items'] = $this->getOrderItems($order);
            $orderItem['order_status'] = $this->getOrderHistory($order['orders_id']);

            $orderInfos[] = $orderItem;
        }

        return array('total_results' => $this->getUserOrderCounts($userId), 'orders' => $orderInfos);
    }

    /**
     * get order detail information
     * @param type $userId
     * @param type $orderId
     * @return type
     * @author hujs
     */
    public function getOneOrderInfoById($userId, $orderId) {
        $orderItem = array();

        $order = $this->getOneOrder($userId, $orderId);
        $this->initOrderDetail($orderItem, $order);
        $orderItem['price_infos'] = $this->getPriceInfos($orderItem, $order);
        $orderItem['order_items'] = $this->getOrderItems($order);
        $orderItem['order_status'] = $this->getOrderHistory($orderId);
        $orderItem['shipping_address'] = $this->getShippingAddress($order);
        $orderItem['billing_address'] = $this->getBillingAddress($order);

        return $orderItem;
    }

    public function getPaymentOrderInfo($order, $tx = '') {
        $orderItem = array();
        $orderId = false;

        if ($order) {
            $orderItem['display_id'] = $orderId = $order->info['order_id'];
            $orderItem['shipping_address'] = $this->getPaymentAddress($order->delivery);
            $orderItem['price_infos'] = $this->getPaymentPriceInfos($order);
            $orderItem['order_items'] = $this->getPaymentOrderItems($order);

            $total = tep_round($order->info['total'] * $order->info['currency_value'], 2);
            $currency = $order->info['currency'];
        } else {
            global $currency;
            $total = 0;
        }

        return array(
            'transaction_id' => $tx,
            'payment_total' => $total,
            'currency' => $currency,
            'order_id' => $orderId,
            'orders' => sizeof($orderItem) ? array($orderItem) : false
        );
    }

    public function getPaymentAddress($address) {
        $addr = array(
            'city' => $address['city'],
            'country_id' => $address['country_id'],
            'zone_id' => $address['zone_id'], //1
            'zone_name' => '', //2
            'state' => $address['state'], //3
            'address1' => $address['address1'],
            'address2' => $address['suburb'],
        );

        return $addr;
    }

    public function getPaymentPriceInfos($order) {
        $info = array();

        $prices = array('total', 'shipping', 'tax', 'insurance', 'returnfree');
        $query = tep_db_query("select * from " . TABLE_ORDERS_TOTAL . " where orders_id = " . (int) $order->info['order_id']);
        while ($row = tep_db_fetch_array($query)) {
            $title = strtolower(substr($row['title'], 0, -1));
            strpos($title, ' ') > 0 && $title = 'shipping';
            if (in_array($title, $prices)) {
                $info[] = array(
                    'type' => $title,
                    'home_currency_price' => $row['value'] * $order->info['currency_value']
                );
            }
        }

        return $info;
    }

    public function getPaymentOrderItems($order) {
        $items = array();
        foreach ($order->products as $product) {
            $items[] = array(
                'order_item_key' => $product['id'],
                'item_title' => $product['name'],
                'category_name' => '',
                'home_currency_price' => $product['final_price'] * $order->info['currency_value'],
                'qty' => $product['qty']
            );
        }

        return $items;
    }

    private function getCountryIdByName($countryName) {
        $countryQuery = tep_db_query("select countries_id from " . TABLE_COUNTRIES . " where countries_name = '" . $countryName . "'");
        $row = tep_db_fetch_array($countryQuery);
        return $row ? $row['countries_id'] : '';
    }

    /**
     * 
     * @param type $orderItem
     * @param type $item
     * @author hujs
     */
    public function initOrderDetail(&$orderItem, $item) {
        $payMethod = array(
            'pm_id' => '',
            'title' => $item['payment_method'],
            'description' => ''
        );

        $orderItem = array(
            'order_id' => $item['orders_id'],
            'display_id' => $item['orders_id'], //show id
            'uname' => $item['customers_name'],
            'currency' => $item['currency'],
            'shipping_address' => array(),
            'billing_address' => array(),
            'payment_method' => $payMethod,
            'shipping_insurance' => '',
            'coupon' => '',
            'order_status' => array(),
            'last_status_id' => $item['orders_status'],
            'order_tax' => '',
            'order_date_start' => '',
            'order_date_finish' => isset($item['orders_date_finished']) ? $item['orders_date_finished'] : '',
            'order_date_purchased' => $item['date_purchased']);
    }

    /**
     * get order ship address
     * @param array $order
     * @return array
     * @author hujs
     */
    private function getShippingAddress(array $order) {

        $address = array('address_book_id' => '',
            'address_type' => 'ship',
            'lastname' => $order['delivery_name'],
            'firstname' => '',
            'telephone' => $order['customers_telephone'],
            'postcode' => $order['delivery_postcode'],
            'city' => $order['delivery_city'],
            'state' => $order['delivery_state'],
            'address1' => $order['delivery_street_address'],
            'address2' => $order['delivery_suburb'],
            'country_id' => $this->getCountryIdByName($order['delivery_country']),
            'country_code' => '',
            'country_name' => $order['delivery_country'],
            'company' => $order['delivery_company']);

        return $address;
    }

    /**
     * get order bill address
     * @param array $order
     * @return array
     * @author hujs
     */
    private function getBillingAddress(array $order) {

        $address = array('address_book_id' => '',
            'address_type' => 'bill',
            'lastname' => $order['customers_name'],
            'firstname' => '',
            'telephone' => $order['customers_telephone'],
            'mobile' => '',
            'gender' => '',
            'postcode' => $order['billing_postcode'],
            'city' => $order['billing_city'],
            'state' => $order['billing_state'],
            'address1' => $order['billing_street_address'],
            'address2' => $order['billing_suburb'],
            'country_id' => $this->getCountryIdByName($order['billing_country']),
            'country_code' => '',
            'country_name' => $order['billing_country'],
            'company' => $order['billing_company']);

        return $address;
    }

    /**
     * get order price information
     * @global type $currencies
     * @param array $order
     * @author hujs
     */
    public function getPriceInfos(&$orderItem, array $order) {
        $info = array();
        $postion = 1;
        $orderCurrency = $order['currency'];

        $shipingMethod = array('pm_id' => '', 'title' => '', 'description' => '', 'price' => 0);

        $orderQuery = tep_db_query("select * from " . TABLE_ORDERS_TOTAL . " where orders_id = " . (int) $order['orders_id']);
        while ($row = tep_db_fetch_array($orderQuery)) {
            $title = $row['title'];
            $info[] = array(
                'title' => $title,
                'type' => $row['class'],
                'price' => $row['value'] * $order['currency_value'],
                'currency' => $orderCurrency,
                'position' => $postion++);

            if ($row['class'] == 'ot_shipping') {
                $shipingMethod['title'] = substr($title, 0, strlen($title) - 1);
                $shipingMethod['price'] = $row['value'] * $order['currency_value'];
            }
        }
        $orderItem['shipping_method'] = $shipingMethod;

        return $info;
    }

    /**
     * get order items
     * @param array $order
     * @return array
     * @author hujs
     */
    public function getOrderItems(array $order) {
        $items = array();
        $orderId = $order['orders_id'];
        $productsQuery = tep_db_query("select o.*, p.products_image
                                      from orders_products o
                                      LEFT JOIN products p on(o.products_id = p.products_id)
                                      where o.orders_id = " . (int) $orderId);

        $urlPath = getFullSiteUrl('images/');
        $productIds = array(); //productId=>key
        $num = 0;
        while ($row = tep_db_fetch_array($productsQuery)) {
            $productId = $row['products_id'];
            $items[] = array('order_item_id' => '',
                'item_id' => $productId,
                'display_id' => $productId,
                'order_item_key' => '',
                'display_attributes' => '',
                'attributes' => '',
                'item_title' => $row['products_name'],
                'thumbnail_pic_url' => $urlPath . $row['products_image'],
                'qty' => $row['products_quantity'],
                'price' => $row['products_price'] * $order['currency_value'],
                'final_price' => $row['final_price'] * $order['currency_value'],
                'item_tax' => $row['products_tax'],
                'shipping_method' => '',
                'post_free' => false,
                'virtual_flag' => false);
            $productIds[$productId] = $num++;
        }

        $attributesQuery = tep_db_query("select att.products_options, att.products_options_values, pr.products_id " .
                "from " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " att " .
                "LEFT JOIN " . TABLE_ORDERS_PRODUCTS . " pr on(att.orders_products_id = pr.orders_products_id) " .
                "where att.orders_id = " . (int) $orderId);

        while ($attributes = tep_db_fetch_array($attributesQuery)) {
            $productId = $attributes['products_id'];
            $key = $productIds[$productId];
            $items[$key]['display_attributes'].=((empty($items[$key]['display_attributes'])) ? '  - ' : '<br>  - ') .
                    $attributes['products_options'] . ' ' . $attributes['products_options_values'];
        }

        return $items;
    }

    /**
     * get order history information by id
     * @param type $orderId
     * @return type
     * @author hujs
     */
    public function getOrderHistory($orderId) {
        $info = array();
        $postion = 1;
        global $languages_id;
        $historyQuery = tep_db_query("select * from " . TABLE_ORDERS_STATUS . " st LEFT JOIN " . TABLE_ORDERS_STATUS_HISTORY . " h on(st.orders_status_id = h.orders_status_id) 
                                        where h.orders_id = " . (int) $orderId . " and st.public_flag = 1 and st.language_id = $languages_id");
        while ($row = tep_db_fetch_array($historyQuery)) {
            $info[] = array('status_id' => $row['orders_status_id'],
                'status_name' => $row['orders_status_name'],
                'display_text' => $row['orders_status_name'],
                'language_id' => $row['language_id'],
                'date_added' => $row['date_added'],
                'comments' => $row['comments'],
                'position' => $postion++);
        }
        tep_db_free_result($historyQuery);

        return $info;
    }

    /**
     * get orders information
     * @param type $userId
     * @return array
     * @author hujs
     */
    public function getOrderList($userId, $pageNo, $pageSize) {
        global $languages_id;
        $orders = array();

        $start = ($pageNo - 1) * $pageSize;
        $orderQuery = tep_db_query("select o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.orders_status, o.orders_date_finished, o.currency, o.currency_value " .
                "from " . TABLE_ORDERS . " o " .
                "left join " . TABLE_ORDERS_STATUS . " s on o.orders_status = s.orders_status_id " .
                "left join " . TABLE_ORDERS_TOTAL . " ot on  o.orders_id = ot.orders_id " .
                "where o.customers_id = " . (int) $userId . " and ot.class = 'ot_total' " .
                "and s.language_id = '" . (int) $languages_id . "' and s.public_flag = '1' " .
                "ORDER BY o.orders_id DESC " .
                "limit " . (int) $start . "," . (int) $pageSize);

        while ($row = tep_db_fetch_array($orderQuery)) {
            $orders[] = $row;
        }

        return $orders;
    }

    /**
     * get one order information by order id 
     * @param type $userId
     * @param type $orderId
     * @return type
     */
    public function getOneOrder($userId, $orderId) {

        $orderQuery = tep_db_query("select * from " . TABLE_ORDERS . " " .
                "where customers_id = " . (int) $userId . " and orders_id = " . (int) $orderId);
        return tep_db_fetch_array($orderQuery);
    }

    /**
     * get user order count
     * @param type $userId
     * @return int
     * @author hujs
     */
    public function getUserOrderCounts($userId) {
        global $languages_id;

        $orders_total = tep_count_customer_orders($userId);
        if ($orders_total > 0) {
            $history_query_raw = "select o.orders_id, o.orders_number, o.date_purchased, o.delivery_name, o.billing_name, o.tracking_number, o.tracking_type, ot.text as order_total, s.orders_status_name from " . TABLE_ORDERS . " o, " . TABLE_ORDERS_TOTAL . " ot, " . TABLE_ORDERS_STATUS . " s where o.customers_id = '" . (int) $userId . "' and o.orders_id = ot.orders_id and ot.class = 'ot_total' and o.orders_status = s.orders_status_id and s.language_id = '" . (int) $languages_id . "' and s.public_flag = '1' order by orders_id DESC";
            $history_split = new splitPageResults($history_query_raw, MAX_DISPLAY_ORDER_HISTORY);

            return (int) $history_split->number_of_rows;
        }

        return 0;
    }

}

?>

<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * ShoppingCart Service,Utility
 * @package services 
 * @author hujs
 */
class ShoppingCartService {

    private $hasError = false;
    private $updateId = true;

    public function __construct() {
        $this->cart = &$_SESSION['cart'];
    }

    /**
     * Get Cart detailed information
     * @author hujs
     */
    public function get() {
        global $language;

        $result = array();
        $this->initShoppingCartGetReslut($result);
        if ($this->cart) {
            $result['cart_items'] = $this->getProducts();
            $result['price_infos'] = $this->getPriceInfo();
            $result['cart_items_count'] = $this->cart->count_contents();
            $result['is_virtual'] = $this->cart->content_type == 'virtual';  //get_content_type()
            if (defined('MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS') && MODULE_PAYMENT_PAYPAL_EXPRESS_STATUS == 'True') {
                $result['payment_methods'] = array('paypal_ec');
            }

            if ($this->hasError && $this->updateId === TRUE) {
                include_once (DIR_WS_LANGUAGES . $language . '/' . FILENAME_SHOPPING_CART);
                if (STOCK_ALLOW_CHECKOUT == 'true') {
                    $result['messages'] = array(str_replace(STOCK_MARK_PRODUCT_OUT_OF_STOCK, '***', OUT_OF_STOCK_CAN_CHECKOUT));
                } else {
                    $result['messages'] = array(str_replace(STOCK_MARK_PRODUCT_OUT_OF_STOCK, '***', OUT_OF_STOCK_CAN_CHECKOUT));
                    $result['valid_to_checkout'] = false;
                }
            }
        }

        return $result;
    }

    /**
     * get products information
     * @param type $cart
     * @return array
     * @author hujs
     */
    public function getProducts() {
        global $currency, $languages_id;

        $products = $this->cart->get_products();
        for ($i = 0, $n = sizeof($products); $i < $n; $i++) {
            // Push all attributes information in an array
            if (isset($products[$i]['attributes']) && is_array($products[$i]['attributes'])) {
                while (list($option, $value) = each($products[$i]['attributes'])) {
                    $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix
                                      from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                      where pa.products_id = '" . (int) $products[$i]['id'] . "'
                                       and pa.options_id = '" . (int) $option . "'
                                       and pa.options_id = popt.products_options_id
                                       and pa.options_values_id = '" . (int) $value . "'
                                       and pa.options_values_id = poval.products_options_values_id
                                       and popt.language_id = '" . (int) $languages_id . "'
                                       and poval.language_id = '" . (int) $languages_id . "'");
                    $attributes_values = tep_db_fetch_array($attributes);

                    $products[$i][$option]['products_options_name'] = $attributes_values['products_options_name'];
                    $products[$i][$option]['options_values_id'] = $value;
                    $products[$i][$option]['products_options_values_name'] = $attributes_values['products_options_values_name'];
                    $products[$i][$option]['options_values_price'] = $attributes_values['options_values_price'];
                    $products[$i][$option]['price_prefix'] = $attributes_values['price_prefix'];
                }
            }
        }

        $items = array();
        foreach ($products as $product) {
            $productId = intval($product['id']);
            $out_of_stock = false;
            if (isset($product['attributes']) && is_array($product['attributes'])) {
                reset($product['attributes']);
                while (list($option, $value) = each($product['attributes'])) {
                    $attr .= ' - ' . $product[$option]['products_options_name'] . ':' . $product[$option]['products_options_values_name'] . '<br/>';
                }
            }

            if (STOCK_CHECK == 'true') {
                $stock_check = tep_check_stock($productId, $product['quantity']);
                if (tep_not_null($stock_check)) {
                    $out_of_stock = true;
                    $this->hasError = true;
                    if ($this->updateId == $productId) {
                        $this->updateId = true;
                    }
                }
            }

            $item = array(
                'cart_item_id' => $product['id'],
                'item_id' => $productId,
                'out_of_stock' => $out_of_stock,
                'item_title' => $product['name'],
                'item_url' => getFullSiteUrl('product_info.php?products_id=' . $productId),
                'currency' => $currency,
                'item_price' => currency_price_value($product['final_price'], tep_get_tax_rate($product['tax_class_id'])),
                'qty' => $product['quantity'],
                'display_attributes' => $attr,
                'thumbnail_pic_url' => getFullSiteUrl(DIR_WS_IMAGES . $product['image']),
                'short_description' => $product['short_description'],
                'post_free' => false,
            );

            $items[] = $item;
        }

        return $items;
    }

    /**
     * initialization cart information
     * @param type $result
     * @author hujs
     */
    public function initShoppingCartGetReslut(&$result) {
        $result['cart_items_count'] = 0;
        $result['cart_items'] = array();
        $result['messages'] = array();
        $result['price_infos'] = array();
        $result['payment_methods'] = array();
        $result['valid_to_checkout'] = true;
        $result['is_virtual'] = false;
    }

    /**
     * get price information
     * @return int
     * @author hujs
     */
    private function getPriceInfo() {
        global $currency, $language;

        $price_info = array();
               
        include(DIR_WS_LANGUAGES . $language . '/' . FILENAME_SHOPPING_CART);
        $pos = 0;

        $price_info[] = array(
            'title' => rtrim(SUB_TITLE_SUB_TOTAL, ':'),
            'type' => 'total',
            'price' => currency_price_value($this->cart->show_total()),
            'currency' => $currency,
            'position' => ++$pos
        );

        return $price_info;
    }

    /**
     * add goods into cart
     * @param type $goods
     * @return type 
     * @author hujs
     */
    public function add($products) {
        $this->clearShipping();
        $this->updateId = $products['products_id'];
        $qty = $this->cart->get_quantity(tep_get_uprid($products['products_id'], $products['attr'])) + $products['qty'];
        return $this->cart->add_cart($products['products_id'], $qty, $products['attr'], true);
    }

    public function clearShipping() {
        if (tep_session_is_registered('shipping')) {
            tep_session_unregister('shipping');
        }
    }

    /**
     * update product's quantity and attributes
     * in cart by product id
     * @param type $arr
     * @return type 
     * @author hujs
     */
    public function update($productsId, $quantity = '', $attributes = '') {
        if (empty($attributes)) {
            $contents = $this->cart->contents;
            $attributes = $contents[$productsId]['attributes'];
        }
        $this->clearShipping();
        $this->updateId = intval($productsId);
        return $this->cart->update_quantity($productsId, $quantity, $attributes);
    }

    /**
     * remove goods from cart by product id
     * @access  public
     * @param   integer $id
     * @return  void
     * @author hujs
     */
    public function dropCartGoods($productId) {
        $this->clearShipping();
        $this->cart->remove($productId);
    }

}

?>

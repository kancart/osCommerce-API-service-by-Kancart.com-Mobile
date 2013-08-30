<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_checkout_detail_action extends UserAuthorizedAction {

    public function validate() {  //EC also can checkout
        if ($this->getParam('payment_method_id') == 'paypalwpp') {
            $actionInstance = ActionFactory::factory('KanCart.ShoppingCart.PayPalEC.Detail');
            $actionInstance->execute();
            return true;
        } else {
            return parent::validate();
        }
    }

    public function execute() {
        global $language;
        if ((STOCK_ALLOW_CHECKOUT != 'true')) {
            include_once (DIR_WS_LANGUAGES . $language . '/' . FILENAME_SHOPPING_CART);
            include_once(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PROCESS);
            require_once(DIR_WS_CLASSES . 'order.php');
            $order = new order;
            // Stock Check
            if (STOCK_CHECK == 'true') {
                for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                    if (tep_check_stock($order->products[$i]['id'], $order->products[$i]['qty'])) {
                        $this->setSuccess(array(
                            'redirect_to_page' => 'shopping_cart',
                            'messages' => array(OUT_OF_STOCK_CANT_CHECKOUT)
                        ));
                        return;
                    }
                }

                if (sizeof($order->products) < 1) {
                    $this->setSuccess(array(
                        'redirect_to_page' => 'shopping_cart',
                        'messages' => array('Shopping Cart is empty.')
                    ));
                    return;
                }
            }
        }

        $this->setSuccess(ServiceFactory::factory('Checkout')->detail());
    }

}

?>

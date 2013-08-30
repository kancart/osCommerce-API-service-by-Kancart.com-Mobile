<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_update_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $cartItemId = $this->getParam('cart_item_id');
        $qty = $this->getParam('qty');
        $validateInfo = array();
        if (!isset($cartItemId)) {
            $validateInfo[] = 'Cart item id is not specified .';
        }
        if (!isset($qty) || !is_numeric($qty) || $qty <= 0) {
            $validateInfo[] = 'Qty is not valid.';
        }
        if ($validateInfo) {
            $this->setError(KancartResult::ERROR_CART_INPUT_PARAMETER, $validateInfo);
            return false;
        }
        return true;
    }

    public function execute() {

        $cartItemId = $this->getParam('cart_item_id');
        $qty = $this->getParam('qty');

        $cartService = ServiceFactory::factory('ShoppingCart');
        if ($qty > 0) {
            $cartService->update($cartItemId, $qty);
            $result = $cartService->get();
            $this->setSuccess($result);
        } else {
            $this->setError();
        }
    }

}

?>

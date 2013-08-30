<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_shoppingcart_add_action extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return false;
        }
        $itemId = $this->getParam('item_id');

        if (!isset($itemId)) {
            $errMesg = 'Item id is not specified .';
        }
        $qty = $this->getParam('qty');

        if (!is_numeric($qty) || intval($qty) <= 0) {
            $errMesg = 'Incorrect number of product.';
        }

        if ($errMesg) {
            $this->setError(KancartResult::ERROR_CART_INPUT_PARAMETER, $errMesg);
            return false;
        }
        return true;
    }

    public function execute() {

        $itemId = $this->getParam('item_id');
        $qty = $this->getParam('qty');
        $attributes = $this->getParam('attributes');
        $spec = array();
        if ($attributes) {
            $attributes = json_decode(stripslashes(urldecode($attributes)));
            foreach ($attributes as $attribute) {
                $optionId = $attribute->attribute_id;
                $spec[$optionId] = $attribute->value;
            }
        }

        $cartService = ServiceFactory::factory('ShoppingCart');
        $cartService->add(array('products_id' => $itemId, 'qty' => $qty, 'attr' => $spec));
        $result = $cartService->get();
        $this->setSuccess($result);
    }

}

?>

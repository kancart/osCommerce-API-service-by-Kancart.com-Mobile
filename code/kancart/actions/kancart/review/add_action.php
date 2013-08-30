<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_review_add_action extends UserAuthorizedAction {

    public function validate() {
        if (parent::validate()) {
            $content = $this->getParam('content');
            if (!isset($content) || $content == '') {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER);
                return false;
            }
            $item_id = $this->getParam('item_id');
            if (!isset($item_id) || $item_id == '') {
                $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER);
                return false;
            }
        }
        return true;
    }

    public function execute() {

        $itemId = $this->getParam('item_id');
        $rating = max(0, intval($this->getParam('rating')));

        $content = is_null($this->getParam('content')) ? '' : tep_db_prepare_input($this->getParam('content'));
        $reviewService = ServiceFactory::factory('Review');
        $error = array();
        if (strlen($content) < REVIEW_TEXT_MIN_LENGTH) {
            $error[] = JS_REVIEW_TEXT;
        }

        if (($rating < 1) || ($rating > 5)) {
            $error[] = JS_REVIEW_RATING;
        }

        if (sizeof($error)) {
            $this->setError('', $error);
        } else {
            if ($reviewService->addReview($itemId, $content, $rating)) {
                $this->setSuccess();
            } else {
                $this->setError('', array(array('add review to this product failed.')));
            }
        }
    }

}

?>

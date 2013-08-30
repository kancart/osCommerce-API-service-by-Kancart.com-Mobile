<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class UserAuthorizedAction extends BaseAction {

    public function validate() {
        if (!parent::validate()) {
            return;
        }
        if (tep_session_is_registered('customer_id')) {
            return true;
        }
        $this->setError(KancartResult::ERROR_SYSTEM_INVALID_SESSION_KEY);
        return false;
    }

}

?>

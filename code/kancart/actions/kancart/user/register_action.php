<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_user_register_action extends BaseAction {

    public function execute() {
        $userService = ServiceFactory::factory('User');
        $username = is_null($this->getParam('email')) ? '' : trim($this->getParam('email'));
        $phone = is_null($this->getParam('telephone')) ? '' : trim($this->getParam('telephone'));
        $enCryptedPassword = is_null($this->getParam('pwd')) ? '' : trim($this->getParam('pwd'));
        $password = CryptoUtil::Crypto($enCryptedPassword, 'AES-256', KANCART_APP_SECRET, false);
        if (empty($username)) {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, 'User name is empty.');
            return;
        }
        if (empty($password)) {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, 'Password name is empty.');
            return;
        }
        $firstname = is_null($this->getParam('firstname')) ? '' : trim($this->getParam('firstname'));
        $lastname = is_null($this->getParam('lastname')) ? '' : trim($this->getParam('lastname'));
        $regisetInfo = array(
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $username,
            'password' => $password,
            'telephone' => $phone
        );
        if (!$userService->register($regisetInfo)) {
            $this->setError(KancartResult::ERROR_USER_INVALID_USER_DATA, $msg);
            return;
        }
        // succed registering
        $this->setSuccess();
    }

}

?>

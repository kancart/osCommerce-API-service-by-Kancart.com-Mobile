<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class kancart_user_login_action extends BaseAction {

    public function execute() {
        $userService = ServiceFactory::factory('User');
        $username = is_null($this->getParam('uname')) ? '' : trim($this->getParam('uname'));
        if (empty($username)) {
            $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'User name is empty.');
            return;
        }
        $encryptedPassword = is_null($this->getParam('pwd')) ? '' : trim($this->getParam('pwd'));
        $password = CryptoUtil::Crypto($encryptedPassword, 'AES-256', KANCART_APP_SECRET, false);
        if (!$password) {
            $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, 'Password is empty.');
            return;
        }
        $loginInfo = array(
            'email' => $username,
            'password' => $password
        );
        $login = $userService->login($loginInfo);
        if (is_string($login)) {
            $this->setError(KancartResult::ERROR_USER_INPUT_PARAMETER, $login);
            return;
        }
        $info = array('sessionkey' => md5($username . uniqid(mt_rand(), true)));
        $this->setSuccess($info);
    }

}

?>

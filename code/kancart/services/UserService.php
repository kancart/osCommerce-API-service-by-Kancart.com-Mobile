<?php

global $language;
include_once (DIR_WS_LANGUAGES . $language . '/create_account.php');
include_once (DIR_WS_LANGUAGES . $language . '/contact_us.php');

class UserService {

    private $addressFieldsMap = array(
        'address_book_id' => 'address_book_id',
        'entry_firstname' => 'firstname',
        'entry_lastname' => 'lastname',
        'entry_street_address' => 'address1',
        'entry_city' => 'city',
        'entry_zone_id' => 'zone_id',
        'entry_state' => 'state',
        'entry_country_id' => 'country_id',
        'entry_zone_id' => 'zone_id',
        'entry_postcode' => 'postcode',
        'entry_company' => 'company',
        'entry_gender' => 'gender',
    );

    /**
     * translate the osCommerce's address to kancart's address format
     * @param type $address
     * @return type
     */
    public function translateAddress($address) {
        global $customer_id;
        static $telephone;
        if (!$telephone) {
            $query = tep_db_query("select * from " . TABLE_CUSTOMERS . " where customers_id = '" . $customer_id . "'");
            $userInfo = tep_db_fetch_array($query);
            $telephone = $userInfo['customers_telephone'];
        }
        $translatedAddress = array();
        foreach ($this->addressFieldsMap as $key => $value) {
            $translatedAddress[$value] = $address[$key];
        }
        if ($address['entry_state']) {
            $translatedAddress['state'] = $address['entry_state'];
        }
        if (!isset($translatedAddress['telephone'])) {
            $translatedAddress['telephone'] = $telephone;
        }
        return $translatedAddress;
    }

    /**
     * Get user's addresses
     * @global type $customer_id
     * @return type
     */
    public function getAddresses() {
        global $customer_id;
        $addresses = array();
        $addresses_query = tep_db_query(
                "select *
                 from " . TABLE_ADDRESS_BOOK .
                " where customers_id = '" . (int) $customer_id . "' 
                 order by entry_firstname, entry_lastname"
        );
        while ($addresse = tep_db_fetch_array($addresses_query)) {
            $addresses[] = $this->translateAddress($addresse);
        }
        return $addresses;
    }

    /**
     * check wheter the email has already existed in the database
     * @param type $email
     * @return boolean
     */
    public function checkEmailExists($email) {
        if ($email) {
            $checkEmailQuery = tep_db_query("select count(*) as total from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($email) . "'");
            $checkEmail = tep_db_fetch_array($checkEmailQuery);
            if ($checkEmail['total'] > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * User register
     * @global type $cart
     * @param type $registerInfo
     * @return boolean
     */
    public function register($registerInfo) {
        global $cart;
        $customerBasicInfo = array(
            'customers_firstname' => $registerInfo['firstname'],
            'customers_lastname' => $registerInfo['lastname'],
            'customers_email_address' => $registerInfo['email'],
            'customers_telephone' => $registerInfo['telephone'],
            'customers_fax' => '',
            'customers_newsletter' => '',
            'customers_password' => tep_encrypt_password($registerInfo['password']));

        tep_db_perform(TABLE_CUSTOMERS, $customerBasicInfo);
        $customerId = tep_db_insert_id();

        $country = defined('STORE_COUNTRY') ? STORE_COUNTRY : false;
        if (empty($country)) {
            $query = tep_db_query("select countries_id from " . TABLE_COUNTRIES . " limit 1");
            $country = tep_db_fetch_array($query);
        }

        $customerAddressInfo = array(
            'customers_id' => $customerId,
            'entry_firstname' => $registerInfo['firstname'],
            'entry_lastname' => $registerInfo['lastname'],
            'entry_street_address' => '',
            'entry_postcode' => '',
            'entry_city' => '',
            'entry_country_id' => $country['countries_id']);
        tep_db_perform(TABLE_ADDRESS_BOOK, $customerAddressInfo);
        $addressId = tep_db_insert_id();
        tep_db_query("update " . TABLE_CUSTOMERS . " set customers_default_address_id = '" . (int) $addressId . "' where customers_id = '" . (int) $customerId . "'");
        tep_db_query("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int) $customerId . "', '0', now())");

        if (SESSION_RECREATE == 'True') {
            tep_session_recreate();
        }
        $GLOBALS['customer_first_name'] = $registerInfo['firstname'];
        $GLOBALS['customer_default_address_id'] = $addressId;
        $GLOBALS['customer_id'] = $customerId;
        tep_session_register('customer_id');
        tep_session_register('customer_first_name');
        tep_session_register('customer_default_address_id');

// reset session token
        $GLOBALS[sessiontoken] = md5(tep_rand() . tep_rand() . tep_rand() . tep_rand());

// restore cart contents
        $cart->restore_contents();
        // send email,need?
// build the message content

        $lastname = $registerInfo['lastname'];
        $name = $registerInfo['firstname'] . ' ' . $lastname;
        if (ACCOUNT_GENDER == 'true') {
            $gender = isset($registerInfo['gender']) ? $registerInfo['gender'] : '';
            if ($gender == 'm') {
                $email_text = sprintf(EMAIL_GREET_MR, $lastname);
            } else {
                $email_text = sprintf(EMAIL_GREET_MS, $lastname);
            }
        } else {
            $email_text = sprintf(EMAIL_GREET_NONE, $registerInfo['firstname']);
        }

        $email_text .= EMAIL_WELCOME . EMAIL_TEXT . EMAIL_CONTACT . EMAIL_WARNING;
        tep_mail($name, $registerInfo['email'], EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

        return true;
    }

    /**
     * User login
     * @global type $cart
     * @param type $loginInfo
     * @return type
     */
    public function login($loginInfo) {
        global $cart, $customer_id, $customer_default_address_id, $customer_first_name, $customer_country_id, $customer_zone_id;
        $emailAddress = tep_db_prepare_input($loginInfo['email']);
        $password = tep_db_prepare_input($loginInfo['password']);
        $error = false;
        $errorMessage = '';
        // Check if email exists
        $checkCustomerQuery = tep_db_query("select customers_id, customers_firstname, customers_password, customers_email_address, customers_default_address_id from " . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($emailAddress) . "'");
        if (!tep_db_num_rows($checkCustomerQuery)) {
            $error = true;
            $errorMessage = 'Email is not registered .';
        } else {
            $checkCustomer = tep_db_fetch_array($checkCustomerQuery);
            // Check that password is good
            if (!tep_validate_password($password, $checkCustomer['customers_password'])) {
                $error = true;
                $errorMessage = 'Password is not correct.';
            } else {
                if (SESSION_RECREATE == 'True') {
                    tep_session_recreate();
                }

                // migrate old hashed password to new phpass password
                if (function_exists('tep_password_type') && tep_password_type($checkCustomer['customers_password']) != 'phpass') {
                    tep_db_query("update " . TABLE_CUSTOMERS . " set customers_password = '" . tep_encrypt_password($password) . "' where customers_id = '" . (int) $checkCustomer['customers_id'] . "'");
                }

                $checkCountryQuery = tep_db_query("select entry_country_id, entry_zone_id from " . TABLE_ADDRESS_BOOK . " where customers_id = '" . (int) $checkCustomer['customers_id'] . "' and address_book_id = '" . (int) $checkCustomer['customers_default_address_id'] . "'");
                $checkCountry = tep_db_fetch_array($checkCountryQuery);

                $customer_id = $checkCustomer['customers_id'];
                $customer_default_address_id = $checkCustomer['customers_default_address_id'];
                $customer_first_name = $checkCustomer['customers_firstname'];
                $customer_country_id = $checkCountry['entry_country_id'];
                $customer_zone_id = $checkCountry['entry_zone_id'];
                tep_session_register('customer_id');
                tep_session_register('customer_default_address_id');
                tep_session_register('customer_first_name');
                tep_session_register('customer_country_id');
                tep_session_register('customer_zone_id');

                tep_db_query("update " . TABLE_CUSTOMERS_INFO . " set customers_info_date_of_last_logon = now(), customers_info_number_of_logons = customers_info_number_of_logons+1 where customers_info_id = '" . (int) $customer_id . "'");
                // reset session token
                $GLOBALS[sessiontoken] = md5(tep_rand() . tep_rand() . tep_rand() . tep_rand());
                // restore cart contents
                $cart->restore_contents();
            }
        }
        return $error ? $errorMessage : true;
    }

    private function prepareSqlDataArrayForAddress($addressInfo) {
        $result = array();
        $gender = tep_db_prepare_input($addressInfo['gender']);
        $firstname = tep_db_prepare_input($addressInfo['firstname']);
        $lastname = tep_db_prepare_input($addressInfo['lastname']);
        $address_1 = tep_db_prepare_input($addressInfo['address1']);
        $address_2 = tep_db_prepare_input($addressInfo['address2']);
        $postcode = tep_db_prepare_input($addressInfo['postcode']);
        $telephone = tep_db_prepare_input($addressInfo['telephone']);
        $city = tep_db_prepare_input($addressInfo['city']);
        $country = tep_db_prepare_input($addressInfo['country_id']);
        $state = tep_db_prepare_input($addressInfo['state']);
        $errorMessage = array();
        $zone_id = 0;
        if (isset($addressInfo['zone_id']) && !empty($addressInfo['zone_id'])) {
            $zone_id = tep_db_prepare_input($addressInfo['zone_id']);
        }
        if (strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
            $error = true;
            $errorMessage[] = ENTRY_FIRST_NAME_ERROR;
        }

        if (strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
            $error = true;
            $errorMessage[] = ENTRY_LAST_NAME_ERROR;
        }

        if (strlen($address_1) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
            $error = true;
            $errorMessage[] = ENTRY_STREET_ADDRESS_ERROR;
        }

        if (strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
            $error = true;
            $errorMessage[] = ENTRY_POSTCODE_MIN_LENGTH;
        }

        if (strlen($city) < ENTRY_CITY_MIN_LENGTH) {
            $error = true;
            $errorMessage[] = ENTRY_CITY_ERROR;
        }

        if (!is_numeric($country)) {
            $error = true;
            $errorMessage[] = ENTRY_COUNTRY_ERROR;
        }
        if ($error == false) {
            $sqlDataArray = array(
                'entry_firstname' => $firstname,
                'entry_lastname' => $lastname,
                'entry_street_address' => $address_1,
                'entry_suburb' => $address_2,
                'entry_postcode' => $postcode,
                'entry_city' => $city,
                'entry_country_id' => (int) $country,
                'entry_gender' => $gender,
                'entry_zone_id' => (int) $zone_id);
            if ($zone_id > 0) {
                $sqlDataArray['entry_state'] = '';
            } else {
                $sqlDataArray['entry_zone_id'] = '0';
                $sqlDataArray['entry_state'] = $state;
            }
            $result['result'] = true;
            $result['info'] = $sqlDataArray;
            return $result;
        }
        $result['result'] = false;
        $result['info'] = $errorMessage;
        return $result;
    }

    public function getAddress($sendto) {
        global $customer_id;
        if (is_array($sendto) && !empty($sendto)) {
            return array('firstname' => $sendto['firstname'],
                'lastname' => $sendto['lastname'],
                'company' => $sendto['company'],
                'address1' => $sendto['street_address'],
                'address2' => $sendto['suburb'],
                'postcode' => $sendto['postcode'],
                'city' => $sendto['city'],
                'zone_id' => $sendto['zone_id'],
                'zone_name' => $sendto['zone_name'],
                'country_id' => $sendto['country_id'],
                'country_name' => $sendto['country_name'],
                'state' => $sendto['zone_name']);
        } elseif (is_numeric($sendto) && !empty($sendto)) {
            $addrQuerySql = tep_db_query(
                    " select *
                  from " . TABLE_ADDRESS_BOOK .
                    " where customers_id = '" . (int) $customer_id . "' 
                  and address_book_id = " . intval($sendto) . "
                  order by entry_firstname, entry_lastname"
            );
            $row = tep_db_fetch_array($addrQuerySql);
            if ($row) {
                return $this->translateAddress($row);
            }
        }
        return array();
    }

    /**
     * add an address entry to address book
     * @param type $addressInfo
     */
    public function addAddress($addressInfo) {
        global $customer_id;
        $errorMessages = array();
        if (tep_count_customer_address_book_entries() < MAX_ADDRESS_BOOK_ENTRIES) {
            $result = $this->prepareSqlDataArrayForAddress($addressInfo);
            if ($result['result']) {
                $sqlDataArray = $result['info'];
                $sqlDataArray['customers_id'] = (int) $customer_id;
                tep_db_perform(TABLE_ADDRESS_BOOK, $sqlDataArray);
                $addressId = tep_db_insert_id();
                return $addressId;
            }
            $errorMessages = $result['info'];
        }
        $errorMessages[] = 'Address book\'s entry is more than ' . MAX_ADDRESS_BOOK_ENTRIES;
        return $errorMessages;
    }

    /**
     * delete the an entry from address book
     * @global type $customer_default_address_id
     * @global type $customer_id
     * @param type $addressBookId
     * @return type
     */
    public function deleteAddress($addressBookId) {
        global $customer_default_address_id, $customer_id;
        $errorMessages = array();
        if ($addressBookId == $customer_default_address_id) {
            $errorMessages[] = WARNING_PRIMARY_ADDRESS_DELETION;
        } else {
            tep_db_query("delete from " . TABLE_ADDRESS_BOOK . " where address_book_id = '" . (int) $addressBookId . "' and customers_id = '" . (int) $customer_id . "'");
        }
        return count($errorMessages) > 0 ? $errorMessages : true;
    }

    /**
     * update user's address
     * @global type $customer_id
     * @param type $addressInfo
     * @return boolean|string
     */
    public function updateAddress($addressInfo) {
        global $customer_id;
        $result = $this->prepareSqlDataArrayForAddress($addressInfo);
        if ($result['result']) {
            $sqlDataArray = $result['info'];
            $addressBookId = $addressInfo['address_book_id'];
            $check_query = tep_db_query("select address_book_id from " . TABLE_ADDRESS_BOOK . " where address_book_id = '" . (int) $addressBookId . "' and customers_id = '" . (int) $customer_id . "' limit 1");
            if (tep_db_num_rows($check_query) == 1) {
                tep_db_perform(TABLE_ADDRESS_BOOK, $sqlDataArray, 'update', "address_book_id = '" . (int) $addressBookId . "' and customers_id ='" . (int) $customer_id . "'");
                return true;
            }
            $errorMessage[] = 'Address book id shoud be specified.';
        }
        $errorMessage = $result['info'];
        return $errorMessage;
    }

}

?>

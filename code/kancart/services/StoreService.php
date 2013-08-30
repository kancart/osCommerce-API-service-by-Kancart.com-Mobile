<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class StoreService {

    public function getStoreInfo() {
        $storeInfo = array();
        $storeInfo['general'] = $this->getGeneralInfo();
        $storeInfo['currencies'] = $this->getCurrencies();
        $storeInfo['countries'] = $this->getCountries();
        $storeInfo['zones'] = $this->getZones();
        $storeInfo['languages'] = $this->getLanguages();
        $storeInfo['register_fields'] = $this->getRegisterFields();
        $storeInfo['address_fields'] = $this->getAddressFields();
        $storeInfo['category_sort_options'] = $this->getCategorySortOptions();
        $storeInfo['search_sort_options'] = $this->getSearchSortOptions();
        return $storeInfo;
    }

    /**
     * get Languages
     * @global type $languages
     * @return string
     * @author hujs
     */
    public function getLanguages() {
        $info = array();
        $position = 0;
        $languages_query = tep_db_query("select languages_id, name, code, image, directory from " . TABLE_LANGUAGES . " order by sort_order");
        while ($languages = tep_db_fetch_array($languages_query)) {
            $info[] = array(
                'language_id' => $languages['languages_id'],
                'default' => $languages['code'] == DEFAULT_LANGUAGE,
                'language_code' => $languages['code'],
                'language_name' => $languages['name'],
                'language_text' => $languages['directory'],
                'position' => $position++,
            );
        }
        return $info;
    }

    public function getCountries() {
        $shopCountries = array();
        $countries = tep_db_query("select * from " . TABLE_COUNTRIES . " order by countries_name");
        while ($row = tep_db_fetch_array($countries)) {
            $shopCountries[] = array(
                'country_id' => $row['countries_id'],
                'country_name' => $row['countries_name'],
                'country_iso_code_2' => $row['countries_iso_code_2'],
                'country_iso_code_3' => $row['countries_iso_code_3']
            );
        }
        return $shopCountries;
    }

    public function getZones() {
        $shopZones = array();
        $query = tep_db_query("select * from " . TABLE_ZONES);
        while ($row = tep_db_fetch_array($query)) {
            $shopZones[] = array(
                'zone_id' => $row['zone_id'],
                'country_id' => $row['zone_country_id'],
                'zone_name' => $row['zone_name'],
                'zone_code' => $row['zone_code']
            );
        }
        return $shopZones;
    }

    public function getCurrencies() {
        global $currencies, $currency;
        $shopCurencies = array();
        if ($currencies) {
            foreach ($currencies->currencies as $code => $currencyEntry) {
                $shopCurencies[] = array(
                    'currency_code' => $code,
                    'default' => $code == $currency,
                    'currency_symbol' => $currencyEntry['symbol_left'] ? $currencyEntry['symbol_left'] : $currencyEntry['symbol_right'],
                    'currency_symbol_right' => $currencyEntry['symbol_right'] ? true : false,
                    'decimal_symbol' => $currencyEntry['decimal_point'],
                    'group_symbol' => $currencyEntry['thousands_point'],
                    'decimal_places' => $currencyEntry['decimal_places'],
                    'description' => $currencyEntry['title'],
                );
            }
        }
        return $shopCurencies;
    }

    public function getGeneralInfo() {
        return array(
            'cart_type' => 'oscommerce',
            'cart_version' => CART_VERSION,
            'plugin_version' => KANCART_PLUGIN_VERSION,
            'support_kancart_payment' => true,
            'login_by_mail' => true
        );
    }

    public function getRegisterFields() {
        $registerFields = array(
            array('type' => 'firstname', 'required' => true),
            array('type' => 'lastname', 'required' => true),
            array('type' => 'email', 'required' => true),
            array('type' => 'pwd', 'required' => true),
            array('type' => 'telephone', 'required' => true)
        );
        return $registerFields;
    }

    public function getAddressFields() {
        $addressFields = array(
            array('type' => 'firstname', 'required' => true),
            array('type' => 'lastname', 'required' => true),
            array('type' => 'gender', 'required' => true),
            array('type' => 'country', 'required' => true),
            array('type' => 'zone', 'required' => true),
            array('type' => 'city', 'required' => true),
            array('type' => 'address1', 'required' => true),
            array('type' => 'address2', 'required' => false),
            array('type' => 'postcode', 'required' => true),
        );
        return $addressFields;
    }

    /**
     * Verify the address is complete
     * @param type $address
     * @return boolean
     */
    public function checkAddressIntegrity($address) {
        if (empty($address) || !is_array($address)) {
            return false;
        }

        $addressFields = $this->getAddressFields();
        foreach ($addressFields as $field) {
            if ($field['required'] === true) {
                $name = $field['type'];
                if ($name == 'country') {
                    if (!isset($address['country_id']) || empty($address['country_id'])) {
                        return false;
                    }
                } elseif ($name == 'zone') {
                    if (isset($address['zone_id']) && intval($address['zone_id'])) {
                        continue;
                    } elseif (isset($address['state']) && $address['state']) {
                        continue;
                    } elseif (isset($address['zone_name']) && $address['zone_name']) {
                        continue;
                    } else {
                        return false;
                    }
                } elseif ($name == 'city') {
                    if (isset($address['city_id']) && intval($address['city_id'])) {
                        continue;
                    } elseif (isset($address['city']) && $address['city']) {
                        continue;
                    } else {
                        return false;
                    }
                } elseif (!isset($address[$name]) || empty($address[$name])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getCategorySortOptions() {
        global $language;
        require_once(DIR_WS_LANGUAGES . $language . '/' . FILENAME_DEFAULT);
        $define_list = array(
            'PRODUCT_LIST_MODEL' => PRODUCT_LIST_MODEL,
            'PRODUCT_LIST_NAME' => PRODUCT_LIST_NAME,
            'PRODUCT_LIST_MANUFACTURER' => PRODUCT_LIST_MANUFACTURER,
            'PRODUCT_LIST_PRICE' => PRODUCT_LIST_PRICE,
            'PRODUCT_LIST_QUANTITY' => PRODUCT_LIST_QUANTITY,
            'PRODUCT_LIST_WEIGHT' => PRODUCT_LIST_WEIGHT);

        asort($define_list);
        $column_list = array();
        reset($define_list);
        while (list($key, $value) = each($define_list)) {
            if (defined($key) && $value > 0)
                $column_list[] = $key;
        }
        $categorySortOptions = array();
        for ($col = 0, $n = sizeof($column_list); $col < $n; $col++) {
            switch ($column_list[$col]) {
                case 'PRODUCT_LIST_MODEL': {
                        $categorySortOptions[] = array(
                            array(
                                'title' => defined('TABLE_HEADING_MODEL') ? TABLE_HEADING_MODEL : 'Model',
                                'code' => 'p.products_model:asc',
                                'arrow_type' => 'asc'
                            ),
                            array(
                                'title' => defined('TABLE_HEADING_MODEL') ? TABLE_HEADING_MODEL : 'Model',
                                'code' => 'p.products_model:desc',
                                'arrow_type' => 'desc'
                            )
                        );
                    }
                    break;
                case 'PRODUCT_LIST_NAME': {
                        $categorySortOptions[] = array(array(
                                'title' => defined('TABLE_HEADING_PRODUCTS') ? TABLE_HEADING_PRODUCTS : 'Product Name',
                                'code' => 'pd.products_name:asc',
                                'arrow_type' => 'asc'
                            ),
                            array(
                                'title' => defined('TABLE_HEADING_PRODUCTS') ? TABLE_HEADING_PRODUCTS : 'Product Name',
                                'code' => 'pd.products_name:desc',
                                'arrow_type' => 'desc'
                                ));
                    }
                    break;
                case 'PRODUCT_LIST_MANUFACTURER': {
                        $categorySortOptions[] = array(
                            array(
                                'title' => defined('TABLE_HEADING_MANUFACTURER') ? TABLE_HEADING_MANUFACTURER : 'Manufacturer',
                                'code' => 'm.manufacturers_name:asc',
                                'arrow_type' => 'asc'
                            ),
                            array(
                                'title' => defined('TABLE_HEADING_MANUFACTURER') ? TABLE_HEADING_MANUFACTURER : 'Manufacturer',
                                'code' => 'm.manufacturers_name:desc',
                                'arrow_type' => 'desc'
                            )
                        );
                    }
                    break;
                case 'PRODUCT_LIST_PRICE': {
                        $categorySortOptions[] = array(
                            array(
                                'title' => defined('TABLE_HEADING_PRICE') ? TABLE_HEADING_PRICE : 'Price',
                                'code' => 'final_price:asc',
                                'arrow_type' => 'asc'
                            ),
                            array(
                                'title' => defined('TABLE_HEADING_PRICE') ? TABLE_HEADING_PRICE : 'Price',
                                'code' => 'final_price:desc',
                                'arrow_type' => 'desc'
                            )
                        );
                    }
                    break;
                case 'PRODUCT_LIST_QUANTITY': {
                        $categorySortOptions[] = array(
                            array(
                                'title' => defined('TABLE_HEADING_QUANTITY') ? TABLE_HEADING_QUANTITY : 'Quantity',
                                'code' => 'p.products_quantity:asc',
                                'arrow_type' => 'asc'
                            ),
                            array(
                                'title' => defined('TABLE_HEADING_QUANTITY') ? TABLE_HEADING_QUANTITY : 'Quantity',
                                'code' => 'p.products_quantity:desc',
                                'arrow_type' => 'desc'
                            )
                        );
                    }
                    break;
                case 'PRODUCT_LIST_WEIGHT': {
                        $categorySortOptions[] = array(
                            array(
                                'title' => defined('TABLE_HEADING_WEIGHT') ? TABLE_HEADING_WEIGHT : 'Weight',
                                'code' => 'p.products_weight:asc',
                                'arrow_type' => 'asc'
                            ),
                            array(
                                'title' => defined('TABLE_HEADING_WEIGHT') ? TABLE_HEADING_WEIGHT : 'Weight',
                                'code' => 'p.products_weight:desc',
                                'arrow_type' => 'desc'
                            )
                        );
                    }
                    break;
            }
        }
        return $categorySortOptions;
    }

    public function getSearchSortOptions() {
        return $this->getCategorySortOptions();
    }

}

?>

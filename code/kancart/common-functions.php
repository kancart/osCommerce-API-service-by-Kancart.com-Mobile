<?php

init();

function getDomain() {
    $protocol = (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off')) ? 'https://' : 'http://';
    /* 域名或IP地址 */
    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
    } else {
        /* 端口 */
        if (isset($_SERVER['SERVER_PORT'])) {
            $port = ':' . $_SERVER['SERVER_PORT'];

            if ((':80' == $port && 'http://' == $protocol) || (':443' == $port && 'https://' == $protocol)) {
                $port = '';
            }
        } else {
            $port = '';
        }

        if (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'] . $port;
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $host = $_SERVER['SERVER_ADDR'] . $port;
        }
    }

    return $protocol . $host;
}

function getFullSiteUrl($path) {
    $root = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
    if (substr($root, -1) != '/') {
        $root .= '/';
    }
    return getDomain() . $root . $path;
}

/**
 * used to initializing the store
 * @global type $currency
 * @global type $currencies
 */
function init() {

    tep_db_query("SET NAMES 'utf8'");
    tep_db_query("SET CHARACTER SET utf8");
    tep_db_query("SET CHARACTER_SET_CONNECTION=utf8");

    if (function_exists('tep_get_version')) {
        define('CART_VERSION', tep_get_version());
    } elseif (defined('PROJECT_VERSION')) {
        if (preg_match('/\d+\.\d+/', PROJECT_VERSION, $version)) {
            define('CART_VERSION', current($version));
        } else {
            define('CART_VERSION', 'unknow');
        }
    } else {
        define('CART_VERSION', 'unknow');
    }
    
    define('SEARCH_INCLUDED_DESC', CART_VERSION > '2.2');

    init_currency();
    init_language();
}

function init_currency() {
    global $currency, $currencies;
    if (!tep_session_is_registered('currency') || isset($_REQUEST['currency']) || ( (USE_DEFAULT_LANGUAGE_CURRENCY == 'true') && (LANGUAGE_CURRENCY != $currency) )) {
        if (!tep_session_is_registered('currency'))
            tep_session_register('currency');

        if (isset($_REQUEST['currency']) && $currencies->is_set($_REQUEST['currency'])) {
            $currency = $_REQUEST['currency'];
        } else {
            $currency = ((USE_DEFAULT_LANGUAGE_CURRENCY == 'true') && $currencies->is_set(LANGUAGE_CURRENCY)) ? LANGUAGE_CURRENCY : DEFAULT_CURRENCY;
        }
    }
}

function init_language() {
    global $languages_id, $language;
    $requestLanguageId = intval($_REQUEST['language']);
    if ($requestLanguageId > 0 && $requestLanguageId != $languages_id) {
        if (!tep_session_is_registered('language')) {
            tep_session_register('language');
            tep_session_register('languages_id');
        }
        include_once(DIR_WS_CLASSES . 'language.php');
        $lng = new language();
        foreach ($lng->catalog_languages as $langCode => $lang) {
            if ($lang['id'] == $requestLanguageId) {
                $lng->set_language($langCode);
                break;
            }
        }
        $language = $lng->language['directory'];
        $languages_id = $lng->language['id'];
    }
}

function table_has_field($table, $field) {
    $query = tep_db_query('DESC ' . $table);
    while ($row = mysql_fetch_array($query)) {
        if ($row['Field'] == $field) {
            return true;
        }
    }
    
    return false;
}

function currency_price_value($price, $products_tax_class_id = false, $quantity = 1) {
    global $currency, $currencies;
    $currency_type = $currency;
    if ($products_tax_class_id) {
        $price = $currencies->calculate_price($price, $products_tax_class_id, $quantity);
    }
    $rate = $currencies->currencies[$currency_type]['value'];
    return tep_round($price * $rate, $currencies->currencies[$currency_type]['decimal_places']);
}

function defaultValueIfEmpty($var, $defaultValue) {
    if (!isset($var) || empty($var)) {
        return $defaultValue;
    }
    return $var;
}

function prepare_address() {
    $address = array(
        'gender' => isset($_REQUEST['gender']) ? trim($_REQUEST['gender']) : '',
        'lastname' => isset($_REQUEST['lastname']) ? trim($_REQUEST['lastname']) : '',
        'firstname' => isset($_REQUEST['firstname']) ? trim($_REQUEST['firstname']) : '',
        'country_id' => isset($_REQUEST['country_id']) ? intval($_REQUEST['country_id']) : 0,
        'zone_id' => isset($_REQUEST['zone_id']) ? intval($_REQUEST['zone_id']) : 0,
        'city' => isset($_REQUEST['city']) ? trim($_REQUEST['city']) : '',
        'address1' => isset($_REQUEST['address1']) ? trim($_REQUEST['address1']) : '',
        'address2' => isset($_REQUEST['address2']) ? trim($_REQUEST['address2']) : '',
        'postcode' => isset($_REQUEST['postcode']) ? trim($_REQUEST['postcode']) : '',
        'telephone' => isset($_REQUEST['telephone']) ? trim($_REQUEST['telephone']) : ''
    );
    $address['state'] = '';
    if (empty($address['zone_id'])) {
        $address['state'] = isset($_REQUEST['state']) ? trim($_REQUEST['state']) : '';
    }
    if (isset($_REQUEST['address_book_id']) && intval($_REQUEST['address_book_id']) > 0) {
        $address['address_book_id'] = intval($_REQUEST['address_book_id']);
    }
    return $address;
}

?>

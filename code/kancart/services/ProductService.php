<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class ProductService {

    private function setDefaultFilterIfNeed(&$filter) {
        if (!$filter['page_size']) {
            $filter['page_size'] = 20;
        }
        if (!$filter['page_no']) {
            $filter['page_no'] = 1;
        }
        if (!$filter['sort_by']) {
            $filter['sort_by'] = 'p.products_id';
        }
        if (!$filter['sort_order']) {
            $filter['sort_order'] = 'desc';
        }
    }

    /**
     * Get the products,filter is specified by the $filter parameter
     * 
     * @param array $filter array
     * @return array
     */
    public function getProducts($filter) {
        $this->setDefaultFilterIfNeed($filter);
        $products = array('total_results' => 0, 'items' => array());
        if (isset($filter['item_ids'])) {
            // get by item ids
            $products = $this->getSpecifiedProducts($filter);
        } else if (isset($filter['is_specials']) && intval($filter['is_specials'])) {
            // get specials products
            $products = $this->getSpecialProducts($filter);
        } else if (isset($filter['query'])) {
            // get by query
            $products = $this->getProductsByQuery($filter);
        } else {
            // get by category
            $products = $this->getProductsByCategory($filter, $filter['cid']);
        }
        return $products;
    }

    /**
     * the category id is specified in the $filter array
     * @param type $filter
     */
    public function getProductsByCategory($filter) {
        $products = array();
        $categoryService = ServiceFactory::factory('Category');
        list($items, $total) = $categoryService->getCategoryProducts($filter['cid'], $filter['sort_by'], $filter['sort_order'], $filter['page_no'], $filter['page_size'], $filter['include_subcat']);
        $proudctTranslator = ServiceFactory::factory('ProductTranslator');
        foreach ($items as $product) {
            $proudctTranslator->clear();
            $proudctTranslator->setProduct($product);
            $proudctTranslator->getItemBaseInfo();
            $proudctTranslator->getItemPrices();
            $products['items'][] = $proudctTranslator->getTranslatedItem();
        }
        $products ['total_results'] = $total;

        return $products;
    }

    /**
     * get product by name
     * @global type $languages_id
     * @param type $filter
     * @return int
     * @author hujs
     */
    public function getProductsByQuery($filter) {
        global $languages_id;
        if (is_null($filter['query'])) {
            return array('total_results' => 0, 'items' => array());
        }

        $search_keywords = '';
        $productName = trim($filter['query']);
        $keywords = tep_db_prepare_input($productName);
        if (tep_not_null($keywords)) {
            tep_parse_search_string($keywords, $search_keywords);
        }

        $pageNo = ($filter['page_no'] - 1 < 0 ? 0 : $filter['page_no'] - 1) * $filter['page_size'];
        $proudctTranslator = ServiceFactory::factory('ProductTranslator');

        if (isset($search_keywords) && (sizeof($search_keywords) > 0)) {
            $where_str .= " and (";
            for ($i = 0, $n = sizeof($search_keywords); $i < $n; $i++) {
                switch ($search_keywords[$i]) {
                    case '(':
                    case ')':
                    case 'and':
                    case 'or':
                        $where_str .= " " . $search_keywords[$i] . " ";
                        break;
                    default:
                        $keyword = tep_db_prepare_input($search_keywords[$i]);
                        $productName = tep_db_input($keyword);
                        $where_str .= "(pd.products_name like '%$productName%' or p.products_model like '%$productName%' or m.manufacturers_name like '%$productName%'" .
                                (SEARCH_INCLUDED_DESC ? " or pd.products_description like '%$productName%')" : ")");
                        break;
                }
            }
            $where_str .= " )";
        }
        $this->getQueryTotalResults($where_str);
        $sql = "SELECT
            p.*,
            pd.*,
            m.*,
            IF (
                s.STATUS,
                s.specials_new_products_price,
                NULL
            ) AS specials_new_products_price,
            IF (
               s.STATUS,
               s.specials_new_products_price,
               p.products_price
            ) AS final_price
            FROM
                " . TABLE_PRODUCTS . " p
            LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id and pd.language_id=$languages_id 
            LEFT JOIN " . TABLE_MANUFACTURERS . " m ON p.manufacturers_id = m.manufacturers_id
            LEFT JOIN " . TABLE_SPECIALS . " s ON p.products_id = s.products_id
            WHERE p.products_status = '1' $where_str
            AND p.products_id in (SELECT distinct products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . ")
            ORDER BY {$filter['sort_by']} {$filter['sort_order']}
            LIMIT $pageNo,{$filter['page_size']}";

        $query = tep_db_query($sql);
        $products['items'] = array();
        while ($row = tep_db_fetch_array($query)) {
            $proudctTranslator->clear();
            $proudctTranslator->setProduct($row);
            $proudctTranslator->getItemBaseInfo();
            $proudctTranslator->getItemPrices();
            $products['items'][] = $proudctTranslator->getTranslatedItem();
        }
        $products ['total_results'] = $this->getQueryTotalResults($where_str);

        return $products;
    }

    public function getQueryTotalResults($where_str) {
        global $languages_id;
        $sql = "SELECT
             COUNT(*) as total
             FROM
                " . TABLE_PRODUCTS . " p
             LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id and pd.language_id=$languages_id 
             LEFT JOIN " . TABLE_MANUFACTURERS . " m ON p.manufacturers_id = m.manufacturers_id
             WHERE p.products_status = '1' $where_str
             AND p.products_id in (SELECT distinct products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . ")";
        $query = tep_db_query($sql);
        $row = tep_db_fetch_array($query);
        return $row['total'];
    }

    /**
     * get special products
     * @global type $languages_id
     * @return type
     * @author hujs
     */
    public function getSpecialProducts($filter) {
        global $languages_id;
        $pageNo = ($filter['page_no'] - 1 < 0 ? 0 : $filter['page_no'] - 1) * $filter['page_size'];

        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $sql = "SELECT
            p.*,
            pd.*,
            IF (
                s.STATUS,
                s.specials_new_products_price,
                NULL
            ) AS specials_new_products_price,
            IF (
               s.STATUS,
               s.specials_new_products_price,
               p.products_price
            ) AS final_price
            FROM
                products_description pd,
                products p
            RIGHT JOIN specials s ON p.products_id = s.products_id
            WHERE
                p.products_status = '1'
            AND s.status = '1'
            AND pd.language_id = $languages_id
            AND pd.products_id = p.products_id
            ORDER BY {$filter['sort_by']} {$filter['sort_order']}
            LIMIT $pageNo,{$filter['page_size']}";

        $query = tep_db_query($sql);
        $items = array();
        while ($row = tep_db_fetch_array($query)) {
            $productTranslator->clear();
            $productTranslator->setProduct($row);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
        }
        $returnResult['total_results'] = $this->getSpecialProductsCount();
        $returnResult['items'] = $items;
        return $returnResult;
    }

    public function getSpecialProductsCount() {
        global $languages_id;

        $sql = "SELECT
            COUNT(*) as total
            FROM
                products_description pd,
                products p
            RIGHT JOIN specials s ON p.products_id = s.products_id
            WHERE
                p.products_status = '1'
            AND s.status = '1'
            AND pd.language_id = $languages_id
            AND pd.products_id = p.products_id";

        $query = tep_db_query($sql);
        $row = tep_db_fetch_array($query);

        return $row['total'];
    }

    public function getSpecifiedProducts($filter) {
        global $languages_id;

        $productIds = $filter['item_ids'];
        if (!is_array($productIds)) {
            $productIds = explode(',', $productIds);
        }

        if (!sizeof($productIds)) {
            return array('total_results' => 0, 'items' => array());
        }

        $total = count($productIds);
        $start = max($filter['page_no'] - 1, 0) * $filter['page_size'];
        $itemIds = array_splice($productIds, $start, $filter['page_size']);
        if (empty($itemIds)) {
            return array('total_results' => 0, 'items' => array());
        }
        $productTranslator = ServiceFactory::factory('ProductTranslator');
        $sql = "SELECT
            p.*,
            pd.*,
            IF (
                s.STATUS,
                s.specials_new_products_price,
                NULL
            ) AS specials_new_products_price,
            IF (
               s.STATUS,
               s.specials_new_products_price,
               p.products_price
            ) AS final_price
            FROM
                products_description pd,
                products p
            LEFT JOIN specials s ON p.products_id = s.products_id
            WHERE
                p.products_status = '1'
            AND pd.language_id = $languages_id
            AND pd.products_id = p.products_id
            AND p.products_id in (" . join(',', $itemIds) . ')';
        $query = tep_db_query($sql);
        $items = array();
        while ($row = tep_db_fetch_array($query)) {
            $productTranslator->clear();
            $productTranslator->setProduct($row);
            $productTranslator->getItemBaseInfo();
            $productTranslator->getItemPrices();
            $items[] = $productTranslator->getTranslatedItem();
        }
        $returnResult['total_results'] = $total;
        $returnResult['items'] = $items;
        return $returnResult;
    }

    /**
     * Get one product info
     * @param integer $goods_id

      商品id
     * @return array
     */
    public function getProduct($productId) {
        global $languages_id;
        $sql = "SELECT
            p.*,
            pd.*,
            IF (
                s.STATUS,
                s.specials_new_products_price,
                NULL
            ) AS specials_new_products_price,
            IF (
               s.STATUS,
               s.specials_new_products_price,
               p.products_price
            ) AS final_price
            FROM
                products_description pd,
                products p
            LEFT JOIN specials s ON p.products_id = s.products_id
            WHERE
                p.products_status = '1'
            AND pd.language_id = $languages_id
            AND pd.products_id = p.products_id
            AND p.products_id = $productId";
        $query = tep_db_query($sql);
        $row = tep_db_fetch_array($query);
        if ($row) {
            $productTranslator = ServiceFactory::factory('ProductTranslator');
            $productTranslator->setProduct($row);
            return $productTranslator->getFullItemInfo();
        }
        return array();
    }

}

?>
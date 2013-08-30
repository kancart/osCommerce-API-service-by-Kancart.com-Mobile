<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class CategoryService {

    public function getAllCategories() {
        global $languages_id;
        $sql = 'SELECT
                    c.*, cd.categories_name
                FROM categories AS c
                LEFT JOIN categories_description AS cd ON c.categories_id = cd.categories_id
                WHERE cd.language_id = ' . intval($languages_id) . ' order by c.sort_order,cd.categories_name';
        $query = tep_db_query($sql);
        $pos = 0;
        $categories = array();
        $parent = array();
        while ($row = tep_db_fetch_array($query)) {
            $cid = $row['categories_id'];
            $row['parent_id'] != 0 && $parent[$row['parent_id']] = true;
            $categories[$cid] = array(
                'cid' => $cid,
                'parent_cid' => $row['parent_id'] == 0 ? '-1' : $row['parent_id'],
                'name' => $row['categories_name'],
                'is_parent' => false,
                'count' => 0,
                'position' => $pos++
            );
        }

        $this->getProductQuantity($categories);
        $this->getProductTotal($categories, $parent);

        return array_values($categories);
    }

    /**
     * Calculation category include sub categroy product counts
     * @auth hujs
     * @staticvar array $children
     * @param type $cats
     * @return boolean
     */
    private function getProductTotal(&$cats, $pids) {
        if (!($count = sizeof($pids))) {//depth=1
            return;
        }

        $parents = array();
        $newPids = array();
        foreach ($cats as $key => &$cat) {
            if (isset($pids[$key])) {
                $cat['is_parent'] = true;
                $parents[$key] = &$cat;
                $newPids[$cat['parent_cid']] = true;
            } elseif ($cat['parent_cid'] != -1) {
                $cats[$cat['parent_cid']]['count'] += intval($cat['count']);
            }
        }
        $pcount = sizeof($newPids);

        while ($pcount > 1 && $count != $pcount) { //one parent or only children
            $count = $pcount;
            $pids = array();
            foreach ($parents as $key => &$parent) {
                if (!isset($newPids[$key])) {
                    if ($parent['parent_cid'] != -1) {
                        $parents[$parent['parent_cid']]['count'] += intval($parent['count']);
                    }
                    unset($parents[$key]);
                } else {
                    $pids[$parent['parent_cid']] = true;
                }
            }
            $pcount = sizeof($pids);
            $newPids = $pids;
        }
    }

 /**
  * get every category product count not include sub children
  * @param type $categories
  */
    private function getProductQuantity(&$categories) {
        $productCountSql = "SELECT
            count(*) AS `count`,
            c.categories_id AS cid
            FROM  " . TABLE_PRODUCTS_TO_CATEGORIES . " c
            LEFT JOIN " . TABLE_PRODUCTS . " p ON(p.products_id = c.products_id)
            WHERE p.products_status = '1'    
            GROUP BY c.categories_id";
        $query = tep_db_query($productCountSql);

        while ($row = tep_db_fetch_array($query)) {
            if (isset($categories[$row['cid']])) {
                $categories[$row['cid']]['count'] = $row['count'];
            }
        }
    }

    public function getCategoryProducts($cid, $sort_by, $sort_order, $page_no, $page_size, 
            $include_sub = true, $include_active = true, $no_repeat = false) {
        global $languages_id;

        $cid = max($cid, 0);
        if ($cid == 0) {
            $where = '';
        } else {
            $cids = array();
            if ($include_sub) {
                tep_get_subcategories($cids, $cid);
            }
            array_push($cids, $cid);
            $idsSql = join(',', $cids);
            $where = "WHERE categories_id in($idsSql)";
        }

        if ($include_active) {
            $active = 'p.products_status = \'1\' AND ';
        } else {
            $active = '';
        }

        $start = max($page_no - 1, 0) * $page_size;
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
            WHERE $active p.products_id in (SELECT products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " $where)
            ORDER BY $sort_by $sort_order
            LIMIT $start, $page_size";

        $query = tep_db_query($sql);
        $products = array();
        while ($row = tep_db_fetch_array($query)) {
            $products[] = $row;
        }
        tep_db_free_result($query);
        if ($no_repeat) {
            $total = $this->getCategoryProductsTotal($where, $active);
        } else {
            $total = tep_count_products_in_category($cid, !$include_active);
        }

        return array($products, $total);
    }

    /**
     * not include the same product
     * @global type $languages_id
     * @param type $where
     * @return Integer
     */
    private function getCategoryProductsTotal($where, $active) {
        global $languages_id;

        $productCountSql = "SELECT
            count(*) as `count`
            FROM
                " . TABLE_PRODUCTS . " p
            LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd ON p.products_id = pd.products_id and pd.language_id=$languages_id 
            WHERE $active p.products_id in (SELECT products_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " $where)";
        $query = tep_db_query($productCountSql);
        $row = tep_db_fetch_array($query);

        return (int) $row['count'];
    }

}

?>

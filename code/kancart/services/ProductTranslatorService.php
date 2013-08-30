<?php

class ProductTranslatorService {

    private $product;
    private $item = array();

    const OPTION_TYPE_SELECT = 'select';
    const OPTION_TYPE_CHECKBOX = 'select';
    const OPTION_TYPE_MULTIPLE_SELECT = 'multiselect';
    const OPTION_TYPE_TEXT = 'text';

    public function getTranslatedItem() {
        return $this->item;
    }

    public function getItemBaseInfo() {

        $this->item['item_id'] = $this->product['products_id'];
        $this->item['item_title'] = $this->product['products_name'];
        $this->item['item_url'] = getFullSiteUrl('product_info.php?products_id=' . $this->product['products_id']);
        //$this->item['cid'] = $this->product->getCategoryIds();
        $this->item['qty'] = $this->product['products_quantity'];
        $this->item['thumbnail_pic_url'] = getFullSiteUrl(DIR_WS_IMAGES . $this->product['products_image']);
        $this->item['is_virtual'] = false;
        $this->item['allow_add_to_cart'] = !$this->hasAttributes($this->product['products_id']);
        $this->item['item_type'] = 'simple';
        $this->item['item_status'] = $this->product['status'] == '0' ? 'outofstock' : 'instock';
        $reviewService = ServiceFactory::factory('Review');
        $this->item['rating_count'] = $reviewService->getReviewsCount($this->product['products_id']);
        $this->item['rating_score'] = $reviewService->getAvgRatingScore($this->product['products_id']);
        return $this->item;
    }

    /**
     * whether the product has attributes
     * @param type $productId
     * @return boolean
     * @author hujs
     */
    public function hasAttributes($productId) {
        return tep_has_product_attributes($productId);
    }

    public function getItemPrices() {
        global $currency, $currencies;
        $prices = array();
        $productPrice = currency_price_value($currencies->calculate_price($this->product['final_price'], tep_get_tax_rate($this->product['products_tax_class_id']))) + 0;
        $prices['currency'] = $currency;
        $prices['base_price'] = array('price' => $productPrice);
        $prices['tier_prices'] = array();
        $displayPrices = array();
        $displayPrices[] = array(
            'title' => 'Price',
            'price' => $productPrice,
            'style' => 'normal'
        );
        if ($this->product['products_price'] > $this->product['final_price']) {
            $displayPrices[] = array(
                'title' => '',
                'price' => currency_price_value($this->product['products_price'], tep_get_tax_rate($this->product['products_tax_class_id'])) + 0,
                'style' => 'line-through'
            );
            $this->item['discount'] = round(100 - ($this->product['final_price'] * 100) / $this->product['products_price']);
        }

        $prices['display_prices'] = $displayPrices;
        $this->item['prices'] = $prices;
        return $prices;
    }

    public function getItemAttributes() {
        global $languages_id;
        $productId = $this->product['products_id'];
        $attrSql = "select distinct patrib.*, popt.*,pov.products_options_values_name
                    from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_ATTRIBUTES . " patrib, " . TABLE_PRODUCTS_OPTIONS_VALUES . " pov
                    where patrib.products_id='" . (int) $productId . "' 
                    and   patrib.options_id = popt.products_options_id 
                    and   patrib.options_values_id = pov.products_options_values_id
                    and   popt.language_id = " . (int) $languages_id;
        $attrQuery = tep_db_query($attrSql);
        $attributeCollection = array();
        while ($row = tep_db_fetch_array($attrQuery)) {
            $attributeCollection[] = $row;
        }
        $this->item['attributes'] = $this->extractAttributes($attributeCollection);
        return $this->item['attributes'];
    }

    private function extractAttributes($attributeCollection) {
        global $currencies;
        $attributes = array();
        $optionIds = array();
        foreach ($attributeCollection as $attr) {
            $price = $attr['price_prefix'] == '-' ? 0 - $attr['options_values_price'] : $attr['options_values_price'];
            if (!in_array($attr['options_id'], $optionIds)) {
                $optionIds[] = $attr['options_id'];
                $attributes[] = array(
                    'attribute_id' => $attr['options_id'],
                    'title' => $attr['products_options_name'],
                    'input' => 'select',
                    'options' => array(
                        array(
                            'attribute_id' => $attr['options_id'],
                            'option_id' => $attr['options_values_id'],
                            'title' => $attr['products_options_values_name'],
                            'price' => currency_price_value($currencies->calculate_price($price, tep_get_tax_rate($this->product['products_tax_class_id']))
                            )
                    ))
                );
            } else {
                //find the attribute
                foreach ($attributes as &$attribute) {
                    if ($attribute['attribute_id'] == $attr['options_id']) {
                        $attribute['options'][] = array(
                            'attribute_id' => $attr['options_id'],
                            'option_id' => $attr['options_values_id'],
                            'title' => $attr['products_options_values_name'],
                            'price' => currency_price_value($currencies->calculate_price($price, tep_get_tax_rate($this->product['products_tax_class_id'])))
                        );
                        break;
                    }
                }
            }
        }
        return $attributes;
    }

    public function getRecommededItems() {
        $this->item['recommended_items'] = array();
    }

    public function getRelatedItems() {
        $this->item['related_items'] = array();
    }

    public function getItemImgs() {
        $this->item['short_description'] = $this->product['products_description'];
        $this->item['detail_description'] = $this->product['products_description'];
        $imgs = array();

        if (defined('TABLE_PRODUCTS_IMAGES')) {
            $productId = intval($this->product['products_id']);
            $sql = 'SELECT * FROM ' . TABLE_PRODUCTS_IMAGES . ' WHERE products_id = ' . $productId;
            $query = tep_db_query($sql);
            $pos = 0;
            while ($row = tep_db_fetch_array($query)) {
                $imgs[] = array(
                    'id' => $row['id'],
                    'img_url' => getFullSiteUrl(DIR_WS_IMAGES . $row['image']),
                    'position' => $pos++
                );
            }
        }
        if (sizeof($imgs) < 1) {
            $imgs[] = array(
                'id' => '1',
                'img_url' => getFullSiteUrl(DIR_WS_IMAGES . $this->product['products_image']),
                'position' => $pos
            );
        }
        $this->item['item_imgs'] = $imgs;
        return $imgs;
    }

    public function clear() {
        $this->product = array();
        $this->item = array();
    }

    public function setProduct($product) {
        $this->product = $product;
    }

    public function getFullItemInfo() {
        $this->getItemBaseInfo();
        $this->getItemPrices();
        $this->getItemAttributes();
        $this->getItemImgs();
        $this->getRecommededItems();
        $this->getRelatedItems();
        return $this->getTranslatedItem();
    }

}

?>

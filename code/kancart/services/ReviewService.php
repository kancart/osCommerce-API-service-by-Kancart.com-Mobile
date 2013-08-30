<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

define('HAS_REVIEW_STATUS', table_has_field(TABLE_REVIEWS, 'reviews_status'));

class ReviewService {

    public function getAvgRatingScore($productId) {
        $sql = ' SELECT sum(r.reviews_rating)/count(*) as avg_rating_score FROM ' . TABLE_REVIEWS . ' r' .
                ' WHERE  r.products_id = ' . $productId . (HAS_REVIEW_STATUS ? ' AND r.reviews_status = 1 ' : '');
        $query = tep_db_query($sql);
        $row = tep_db_fetch_array($query);
        if ($row) {
            return intval($row['avg_rating_score']);
        }
        return 0;
    }

    public function getReviewsCount($productId) {
        global $languages_id;

        $reviewsStatus = HAS_REVIEW_STATUS ? ' AND r.reviews_status = 1 ' : '';
        $sql = "SELECT
                count(*) as count
                FROM reviews r,reviews_description rd
                WHERE r.reviews_id = rd.reviews_id $reviewsStatus
                AND rd.languages_id = $languages_id
                AND r.products_id = $productId";
        $query = tep_db_query($sql);
        $row = tep_db_fetch_array($query);
        if ($row) {
            return $row['count'];
        }
        return 0;
    }

    /**
     * add a review
     * @param type $itemId
     * @param type $content
     * @param type $rating
     */
    public function addReview($itemId, $content, $rating) {
        global $languages_id;

        $customer_id = $_SESSION['customer_id'];

        $orderQuery = tep_db_query("SELECT * FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int) $customer_id);
        $userInfo = tep_db_fetch_array($orderQuery);

        tep_db_query("insert into " . TABLE_REVIEWS .
                " (products_id, customers_id, customers_name, reviews_rating, date_added) values ('" . (int) $itemId . "', '" . (int) $customer_id . "', '" .
                tep_db_input($userInfo['customers_firstname']) . ' ' . tep_db_input($userInfo['customers_lastname']) . "', '" . tep_db_input($rating) . "', now())");

        $insert_id = tep_db_insert_id();

        $result = tep_db_query("insert into " . TABLE_REVIEWS_DESCRIPTION .
                " (reviews_id, languages_id, reviews_text) values ('" . (int) $insert_id . "', '" . (int) $languages_id . "', '" . tep_db_input($content) . "')");

        return $result;
    }

    public function getReviews($productId, $pageNo = 0, $pageSize = 10) {
        global $languages_id;
        $reviews = array();
        $reviewsStatus = HAS_REVIEW_STATUS ? ' AND r.reviews_status = 1 ' : '';
        $sql = "SELECT
                    r.*,rd.reviews_text
                FROM reviews r,reviews_description rd
                WHERE r.reviews_id = rd.reviews_id $reviewsStatus
                AND rd.languages_id = $languages_id
                AND r.products_id = $productId
                limit $pageNo,$pageSize";
        $query = tep_db_query($sql);
        while ($row = tep_db_fetch_array($query)) {
            $reviews[] = array(
                'uname' => $row['customers_name'],
                'item_id' => $productId,
                'rate_score' => $row['reviews_rating'],
                'rate_content' => $row['reviews_text'],
                'rate_date' => $row['date_added']
            );
        }
        return $reviews;
    }

}

?>

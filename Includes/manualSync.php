<?php
set_time_limit(50000000);

use WC_Product;
// $path = $_SERVER['DOCUMENT_ROOT'];
$path = "../../../..";

include_once $path . '/wp-config.php';
include_once $path . '/wp-includes/wp-db.php';
include_once $path . '/wp-includes/pluggable.php';

global $wpdb;

$baseUrl = "https://api.edara.io/v2.0/";
$edara_accsess_token = "Bearer 4cG1qpKdeIIWqwSk6vCzM91MVnGGEoz0p5JzSLwQROzVjMtzIBPMIJg5ftK5L7mY";

$url = $baseUrl . "stockItems?limit=10000000&offset=0";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$headers = array(
    "Accept: application/json",
    "Authorization:".$edara_accsess_token."",
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
$edaraResult = json_decode($resp, true);

if ($edaraResult != null) {
    if ($edaraResult['status_code'] == 200) {
        foreach ($edaraResult['result'] as $edaraProduct) {
            //check if this product exsists or not
            $checkIfExists = $wpdb->get_var("SELECT wp_product FROM ".$wpdb->prefix."edara_products WHERE edara_product = '".$edaraProduct['id']."'");
            if ($checkIfExists == NULL) {

                $date = date('Y-m-d H:i:s');

                $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM ".$wpdb->prefix."postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $edaraProduct['sku'] ) );

                if ($product_id) {
                    $charset_collate = $wpdb->get_charset_collate();
                    // Check that the table does not already exist before continuing
                    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_products (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    wp_product varchar(255),
                    edara_product varchar(255),
                    status varchar(255),
                    PRIMARY KEY (id)
                    ) $charset_collate;";
                    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                    dbDelta( $sql );
                    $is_error = empty( $wpdb->last_error );
                    if ($is_error) {
                        $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_product' => $product_id,
                        'edara_product' => $edaraProduct['id'],
                        'status' => "linked"
                        ));
                    }
                }else{

                    if ($sale_price == "sale_price") {
                        $price = $edaraProduct['price'];
                    }else if ($sale_price == "dealer_price") {
                        $price = $edaraProduct['dealer_price'];
                    }else{
                        $price = $edaraProduct['supper_dealer_price'];
                    }
                    $code = $edaraProduct['code'];

                    $productw = new WC_Product();
                    $productw->set_name($edaraProduct['description']);
                    // $product->set_title($product['description']);
                    $productw->set_status( 'publish' );
                    $productw->set_catalog_visibility( 'visible' );
                    $productw->set_price( $price );
                    $productw->set_regular_price( $price );
                    $productw->set_sold_individually( true );
                    $productw->set_downloadable( false );
                    $productw->set_virtual( false );
                    $productw->set_sku($edaraProduct['sku']);
                    $productw->save();
                    
                    $savedProduct = $wpdb->update($wpdb->prefix.'posts', array(
                        'menu_order' => $code
                    ), array('ID'=>$productw->id));
                    $is_error = empty( $wpdb->last_error );
                    if (!$is_error) {
                        var_dump($wpdb->last_error);die();
                    }
                    // $my_post = array(
                    //   'to_ping'    => $product['code'],
                    //   'ID'  => $productw->id
                    // );

                    // // Insert the post into the database
                    // wp_insert_post( $my_post );
                    // $savedProduct = $wpdb->insert($wpdb->prefix.'posts', array(
                    //   'post_title' => $product['description'],
                    //   'post_name' => $product['description'],
                    //   'post_content' => $product['description'],
                    //   'post_excerpt' => $product['description'],
                    //   'to_ping' => $product['code'],
                    //   'pinged' => $product['description'],
                    //   'post_content_filtered' => $product['description'],
                    //   'post_name' => $product['description'],
                    //   'post_content' => $product['description'],
                    //   'post_type' => 'product',

                    //   'post_date' => $date,
                    //   'post_date_gmt' => $date,
                    //   'post_modified' => $date,
                    //   'post_modified_gmt' => $date
                    // ));
                    // $lastid = $wpdb->insert_id;

                    // if ($lastid) {
                    $lastid = $productw->id;
                    if ($lastid) {


                        // if ($sale_price == "sale_price") {
                        //     $price = $product['price'];
                        // }else if ($sale_price == "dealer_price") {
                        //     $price = $product['dealer_price'];
                        // }else{
                        //     $price = $product['supper_dealer_price'];
                        // }
                        // $savedLocup = $wpdb->insert($wpdb->prefix.'wc_product_meta_lookup', array(
                        //     'product_id' => $lastid,
                        //     'sku' => $product['sku'],
                        //     'downloadable' => 1,
                        //     'min_price' => $price,
                        //     'max_price' => $price,
                        //     'onsale' => 1,
                        //     'stock_quantity' => 1,
                        //     'stock_status' => 'instock',
                        //     'rating_count' => 0,
                        //     'average_rating' => 0.00,
                        //     'total_sales' => 0,
                        //     'tax_status' => 'taxable',
                        //     'tax_class' => ''
                        // ));
                        // $savedMeta = $wpdb->insert($wpdb->prefix.'postmeta', array(
                        //   'post_id' => $lastid,
                        //   'meta_key' => '_regular_price',
                        //   'meta_value' => $price
                        // ));

                        // $savedMeta2 = $wpdb->insert($wpdb->prefix.'postmeta', array(
                        //   'post_id' => $lastid,
                        //   'meta_key' => '_sale_price',
                        //   'meta_value' => $price
                        // ));

                        $charset_collate = $wpdb->get_charset_collate();
                        // Check that the table does not already exist before continuing
                        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_products (
                        id bigint(50) NOT NULL AUTO_INCREMENT,
                        wp_product varchar(255),
                        edara_product varchar(255),
                        status varchar(255),
                        PRIMARY KEY (id)
                        ) $charset_collate;";
                        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                        dbDelta( $sql );
                        $is_error = empty( $wpdb->last_error );
                        if ($is_error) {
                            update_post_meta( $lastid, '_sku', $edaraProduct['sku'] );
                            $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                            'wp_product' => $lastid,
                            'edara_product' => $edaraProduct['id'],
                            'status' => "linked"
                            ));
                        }
                    }
                }
            }else{
                $date = date('Y-m-d H:i:s');
                $savedProduct = $wpdb->update($wpdb->prefix.'posts', array(
                        'post_title' => $edaraProduct['description'],
                        'post_name' => $edaraProduct['description'],
                        'post_content' => $edaraProduct['description'],
                        'post_excerpt' => $edaraProduct['description'],
                        'menu_order' => $edaraProduct['code'],
                        'pinged' => $edaraProduct['description'],
                        'post_content_filtered' => $edaraProduct['description'],
                        'post_name' => $edaraProduct['description'],
                        'post_content' => $edaraProduct['description'],
                        'post_type' => 'product',

                        'post_date' => $date,
                        'post_date_gmt' => $date,
                        'post_modified' => $date,
                        'post_modified_gmt' => $date
                    ), array('ID'=>$checkIfExists));
                if ($savedProduct) {
                    if ($sale_price == "sale_price") {
                        $price = $edaraProduct['price'];
                    }else if ($sale_price == "dealer_price") {
                        $price = $edaraProduct['dealer_price'];
                    }else{
                        $price = $edaraProduct['supper_dealer_price'];
                    }

                    $savedLocup = $wpdb->update($wpdb->prefix.'wc_product_meta_lookup', array(
                            // 'product_id' => $checkIfExists,
                            'sku' => $edaraProduct['sku'],
                            'downloadable' => 1,
                            'min_price' => $price,
                            'max_price' => $price,
                            'onsale' => 1,
                            'stock_quantity' => 1,
                            'stock_status' => 'instock',
                            'rating_count' => 0,
                            'average_rating' => 0.00,
                            'total_sales' => 0,
                            'tax_status' => 'taxable',
                            'tax_class' => ''
                        ),array('product_id'=>$checkIfExists));
                    $savedMeta = $wpdb->update($wpdb->prefix.'postmeta', array(
                            'meta_value' => $price
                    ), array('post_id'=>$checkIfExists,'meta_key'=>'_regular_price'));
                    if (!$savedMeta) {
                        $savedMeta = $wpdb->insert($wpdb->prefix.'postmeta', array(
                            'post_id' => $checkIfExists,
                            'meta_key' => '_regular_price',
                            'meta_value' => $price + 1
                        ));
                    }
                    // $savedMeta2 = $wpdb->update($wpdb->prefix.'postmeta', array(
                    //   'meta_value' => $price
                    // ), array('post_id'=>$checkIfExists,'meta_key'=>'_sale_price'));
                    // if (!$savedMeta2) {
                    //     $savedMeta2 = $wpdb->insert($wpdb->prefix.'postmeta', array(
                    //       'post_id' => $checkIfExists,
                    //       'meta_key' => '_sale_price',
                    //       'meta_value' => $price
                    //     ));
                    // }

                    // Check that the table does not already exist before continuing
                    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_products (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    wp_product varchar(255),
                    edara_product varchar(255),
                    status varchar(255),
                    PRIMARY KEY (id)
                    ) $charset_collate;";
                    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                    dbDelta( $sql );
                    $is_error = empty( $wpdb->last_error );
                    if ($is_error) {
                        update_post_meta( $checkIfExists, '_sku', $edaraProduct['sku'] );
                        $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_product' => $checkIfExists,
                        'edara_product' => $edaraProduct['id'],
                        'status' => "linked"
                        ));
                    }

                }
            }
        }
    }

}
?>
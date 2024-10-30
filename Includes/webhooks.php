<?php
use WC_Product;
use WC_Product_Factory;
// $path = $_SERVER['DOCUMENT_ROOT'];
// $path = "../../../..";
 
// include_once $path . '/wp-config.php';
// include_once $path . '/wp-includes/wp-db.php';
// include_once $path . '/wp-includes/pluggable.php';

$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
require_once( $path . 'wp-load.php' );

//----------------------------
global $wpdb;
global $path;
//--------------------------

$json = file_get_contents('php://input');
$data = json_decode($json, true);

file_put_contents(ABSPATH . "log_webhooks_v2.log",print_r($data,true),FILE_APPEND);

switch($data['event_type']){

    case 'After_Add':
        if ($data['entity_type'] != 'StockItem') {
            break;
        }
    
        $product = json_decode($json, true);
        $product = $product['data'];
        $products_selection = $wpdb->get_var("SELECT products_selection FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
        $sale_price = $wpdb->get_var("SELECT sale_price FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
        $date = date('Y-m-d H:i:s');
    
        if ($sale_price == "sale_price") {
            $price = $product['price'];
        } else if ($sale_price == "dealer_price") {
            $price = $product['dealer_price'];
        } else {
            $price = $product['supper_dealer_price'];
        }
    
        // Check if SKU already exists
        if (wc_get_product_id_by_sku($product['sku'])) {
            // SKU already exists, so skip creating the product
            break;
        }
    
        // Start a transaction to prevent race conditions
        $wpdb->query('START TRANSACTION');
    
        try {
            // Check again within the transaction
            if (wc_get_product_id_by_sku($product['sku'])) {
                // SKU already exists, so skip creating the product
                $wpdb->query('ROLLBACK');
                break;
            }
    
            $productw = new WC_Product();
            $productw->set_name($product['description']);
            $productw->set_status('draft');
            $productw->set_catalog_visibility('visible');
            $productw->set_price($price);
            $productw->set_regular_price($price);
            $productw->set_sku($product['sku']);
            $productw->set_sold_individually(false);
            $productw->set_downloadable(false);
            $productw->set_virtual(false);
            $productw->save();
    
            $lastid = $productw->get_id();
            if ($lastid) {
                $savedProduct = $wpdb->update($wpdb->prefix . 'posts', array(
                    'menu_order' => $product['code']
                ), array('ID' => $lastid));
                $is_error = empty($wpdb->last_error);
                if (!$is_error) {
                    throw new Exception($wpdb->last_error);
                }
    
                // set the default character set and collation for the table
                $charset_collate = $wpdb->get_charset_collate();
                // Check that the table does not already exist before continuing
                $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_products (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    wp_product varchar(255),
                    edara_product varchar(255),
                    status varchar(255),
                    PRIMARY KEY (id)
                ) $charset_collate;";
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($sql);
                $is_error = empty($wpdb->last_error);
                if ($is_error) {
                    update_post_meta($lastid, '_sku', $product['sku']);
                    $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                        'wp_product' => $lastid,
                        'edara_product' => $product['id'],
                        'status' => "linked"
                    ));
                }
            } else {
                // set the default character set and collation for the table
                $charset_collate = $wpdb->get_charset_collate();
                // Check that the table does not already exist before continuing
                $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_products (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    wp_product varchar(255),
                    edara_product varchar(255),
                    status varchar(255),
                    PRIMARY KEY (id)
                ) $charset_collate;";
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($sql);
                $is_error = empty($wpdb->last_error);
                if ($is_error) {
                    $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $post_id);
                    if ($dataSelected != NULL) {
                        $row = $wpdb->update($wpdb->prefix . 'edara_products', array(
                            'wp_product' => $lastid,
                            'edara_product' => $product['id'],
                            'status' => $wpdb->last_error
                        ), array('wp_product' => $lastid));
                    } else {
                        $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                            'wp_product' => $lastid,
                            'edara_product' => $product['id'],
                            'status' => $wpdb->last_error
                        ));
                    }
                }
            }
    
            // Commit the transaction
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $wpdb->query('ROLLBACK');
            error_log("Error: " . $e->getMessage());
        }
        break;
        
    case 'After_Update':
        if($data['entity_type'] != 'StockItem'){
            break;
        }
        
        $product = json_decode($json,true);
        $product = $product['data'];

        $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");
        if ($sale_price == "sale_price") {
            $price = $product['price'];
        }else if ($sale_price == "dealer_price") {
            $price = $product['dealer_price'];
        }else{
            $price = $product['supper_dealer_price'];
        }

        $date = date('Y-m-d H:i:s');

        $products_table = $wpdb->base_prefix.'edara_products';
        $queryProducts = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $products_table ) );

        file_put_contents(ABSPATH . "log_webhooks.log",print_r($queryProducts,true),FILE_APPEND);

        if ($products_table == $wpdb->get_var( $queryProducts )) {
            $productExsists = $wpdb->get_var("SELECT wp_product FROM ".$wpdb->prefix."edara_products WHERE edara_product = ".$product['id']);
            
            file_put_contents(ABSPATH . "log_webhooks.log","WooCommerce id = " . $productExsists,FILE_APPEND);

            if ($productExsists) {
                $productExsistsID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE edara_product = ".$product['id']);
                // $productss = new WC_Product( $productExsists );
                $savedProduct = $wpdb->update($wpdb->prefix.'posts', array(
                'post_title' => $product['description'], 
                'menu_order' => $product['code'],
                'post_date' => $date,
                'post_date_gmt' => $date,
                'post_modified' => $date,
                'post_modified_gmt' => $date
                ), array('ID' => $productExsists));
                if ($savedProduct) {
                    file_put_contents(ABSPATH . "log_webhooks.log","Updating " . $productExsists . " price to " . $price,FILE_APPEND);
                    
                    $regularPrice = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."postmeta WHERE post_id = '$productExsists' AND meta_key = '_regular_price'");
                    $currentPrice = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."postmeta WHERE post_id = '$productExsists' AND meta_key = '_price'");
                    
                    file_put_contents(ABSPATH . "testt.log",$productExsists . " " . $regularPrice,FILE_APPEND);

                    if(!$regularPrice || empty($regularPrice)){
                        $result = $wpdb->insert($wpdb->prefix.'postmeta', array(
                            'post_id' => $productExsists,
                            'meta_key' => "_regular_price",
                            'meta_value' => $price
                        ));
                    }

                    if(!$currentPrice || empty($currentPrice)){
                        $result = $wpdb->insert($wpdb->prefix.'postmeta', array(
                            'post_id' => $productExsists,
                            'meta_key' => "_price",
                            'meta_value' => $price
                        ));
                    }

                    if($regularPrice == $currentPrice){
                        $savedMeta2 = $wpdb->update($wpdb->prefix.'postmeta', array(
                        'meta_value' => $price
                        ), array('post_id' => $productExsists,'meta_key' => '_price'));
                    }
                    
                    $savedMeta = $wpdb->update($wpdb->prefix.'postmeta', array(
                    'meta_value' => $price
                    ), array('post_id' => $productExsists,'meta_key' => '_regular_price'));

                    if (isset($product['sku'])) {
                    update_post_meta( $productExsists, '_sku', $product['sku'] );
                    }
                    
                    // set the default character set and collation for the table
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
                    $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                        'status' => "linked"
                    ), array('wp_product' => $productExsists,'edara_product' => $product['id']));
                    }
                }else{
                    // set the default character set and collation for the table
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
                    $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$productExsists."");
                    if ($dataSelected != NULL)  {
                        $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                        'status' => $wpdb->last_error
                        ), array('wp_product' => $productExsists,'edara_product' => $product['id']));
                    }else{
                        $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                            'wp_product' => $productExsists,
                            'edara_product' => $product['id'],
                            'status' => $wpdb->last_error
                        ));
                    }
                    
                    }
                }
            }else{
                file_put_contents(ABSPATH . "log_webhooks.log", " Adding product on update ",FILE_APPEND);

                $product = json_decode($json,true);
                $product = $product['data'];
                $products_selection = $wpdb->get_var("SELECT products_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");
                $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");
                $date = date('Y-m-d H:i:s');

                if ($sale_price == "sale_price") {
                    $price = $product['price'];
                }else if ($sale_price == "dealer_price") {
                    $price = $product['dealer_price'];
                }else{
                    $price = $product['supper_dealer_price'];
                }

                $productw = new WC_Product();
                $productw->set_name($product['description']);
                // $product->set_title($product['description']);
                $productw->set_status('draft');
                $productw->set_catalog_visibility( 'visible' );
                $productw->set_price( $price );
                $productw->set_regular_price( $price );
                $productw->set_sku($product['sku']);
                $productw->set_sold_individually(false);
                $productw->set_downloadable( false );
                $productw->set_virtual( false );      
                $productw->save();

                $lastid = $productw->id;
                if ($lastid) {
                    $savedProduct = $wpdb->update($wpdb->prefix.'posts', array(
                    'menu_order' => $product['code']
                    ), array('ID'=>$productw->id));
                    $is_error = empty( $wpdb->last_error );
                    if (!$is_error) {
                        var_dump($wpdb->last_error);die();
                    }

                    // set the default character set and collation for the table
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
                    update_post_meta( $lastid, '_sku', $product['sku'] );
                    $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_product' => $lastid,
                        'edara_product' => $product['id'],
                        'status' => "linked"
                    ));
                    }
                }else{
                    // set the default character set and collation for the table
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
                    $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                    if ($dataSelected != NULL)  {
                        $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                        'wp_product' => $lastid,
                        'edara_product' => $product['id'],
                        'status' => $wpdb->last_error
                        ), array('wp_product' => $lastid));
                    }else{
                        $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                            'wp_product' => $lastid,
                            'edara_product' => $product['id'],
                            'status' => $wpdb->last_error
                        ));
                    }

                    }
                }
            }
        }
        break;
        case 'Balance_Changed':
        case 'Reserved_Balance_Changed':
        case 'Balance_Decreased':
        case 'Balance_Increased':
            $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");
            if ($sale_price == "sale_price") {
                $price = $data['data']['price'];
            }else if ($sale_price == "dealer_price") {
                $price = $data['data']['dealer_price'];
            }else{
                $price = $data['data']['supper_dealer_price'];
            }

            $date = date('Y-m-d H:i:s');

            $products_table = $wpdb->base_prefix.'edara_products';
            $queryProducts = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $products_table ) );
            if ($products_table == $wpdb->get_var( $queryProducts )) {
                $productExsists = $wpdb->get_var("SELECT wp_product FROM ".$wpdb->prefix."edara_products WHERE edara_product = ".$data['data']['id']);
                
                file_put_contents(ABSPATH . "log_webhooks.log","WooCommerce id = " . $productExsists,FILE_APPEND);
                
                if ($productExsists) {
                    $productExsistsID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE edara_product = ".$data['data']['id']);
                    // Set up WooCommerce product object
                        $savedProduct = wc_get_product( $productExsists );
                        
                        // Make changes to stock quantity and save
                        $savedProduct->set_manage_stock( true );
                        $savedProduct->set_stock_quantity( $data['message_attributes']['TotalBalance'] - $data['message_attributes']['TotalReservedBalance']);
                        $savedProduct->save();
                        
                    if ($savedProduct) {
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
                        $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                            'status' => "linked"
                        ), array('wp_product' => $productExsists,'edara_product' => $data['data']['id']));
                        }
                    }else{
                    // set the default character set and collation for the table
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
                        $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$productExsists."");
                        if ($dataSelected != NULL)  {
                            $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                            'status' => $wpdb->last_error
                            ), array('wp_product' => $productExsists,'edara_product' => $data['data']['id']));
                        }else{
                            $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                'wp_product' => $productExsists,
                                'edara_product' => $data['data']['id'],
                                'status' => $wpdb->last_error
                            ));
                        }
                        
                        }
                    }
                }
            }

            break;
}

?>
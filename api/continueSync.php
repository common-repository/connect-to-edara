<?php
// var_dump($_POST);die();
declare(strict_types=1);
// $path = $_SERVER['DOCUMENT_ROOT'];
$path = "../../../..";

include_once $path . '/wp-config.php';
include_once $path . '/wp-includes/wp-db.php';
include_once $path . '/wp-includes/pluggable.php';

global $wpdb;

$edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");
$baseUrl = "https://api.edara.io/v2.0/";

if(isset($_POST['type'])){
    switch($_POST['type']){
        case '1':
            //Products

            $result = $wpdb->update($wpdb->prefix.'edara_config', array(
                'is_installing' => 1
            ), array('id'=>1));

            $products_selection = $wpdb->get_var("SELECT products_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");

            $currentProducts = $wpdb->get_var("SELECT COUNT(id) as count FROM ".$wpdb->prefix."edara_products");

            if($products_selection == 'no'){

            }elseif($products_selection == 'wp_to_edara'){
                //Check wpml
                $tableName = $wpdb->prefix."icl_translations";
                $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
                if($wpdb->get_var($query) == $tableName){
                    $products = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."posts INNER JOIN ".$wpdb->prefix."icl_translations ON ".$wpdb->prefix."posts.ID=".$wpdb->prefix."icl_translations.element_id AND ".$wpdb->prefix."icl_translations.source_language_code IS NULL AND ".$wpdb->prefix."posts.post_type = 'product' LIMIT ".$currentProducts.",1000000");
                    // $count = $wpdb->get_var( "SELECT COUNT(id) AS count FROM ".$wpdb->prefix."posts INNER JOIN ".$wpdb->prefix."icl_translations ON ".$wpdb->prefix."posts.ID=".$wpdb->prefix."icl_translations.element_id AND ".$wpdb->prefix."icl_translations.source_language_code IS NULL AND ".$wpdb->prefix."posts.post_type = 'product' ORDER BY ID DESC LIMIT 0,100");
                }else{
                    $products = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."posts WHERE post_type = 'product' LIMIT ".$currentProducts.", 1000000");
                    // $count = $wpdb->get_var( "SELECT COUNT(id) AS count FROM ".$wpdb->prefix."posts WHERE post_type = 'product'");
                }

                $getProductsUrl = $baseUrl . "stockItems?offset=0&limit=1000000";
                $curl2 = curl_init();
                curl_setopt_array($curl2, array(
                    CURLOPT_URL => $getProductsUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        'Authorization:'.$edara_accsess_token
                    ),
                ));

                $response = curl_exec($curl2);

                curl_close($curl2);
                // echo $response;
                $responseJson = json_decode($response,TRUE);

                if($responseJson['status_code'] == '200'){
                    $url = $baseUrl . "stockItems";
                    foreach ($products as $product) {
                        $postmeta = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."postmeta WHERE post_id = ".$product->ID." AND meta_key = '_regular_price'");
                        $price = count($postmeta) > 0 ? (double)$postmeta[0]->meta_value : 0;
                        //--------------- check if exsist link it --------------
                        $skuProduct = get_post_meta( $product->ID, '_sku', true );

                        $flag = 0;
                        $edaraId = 0;
                        foreach($responseJson['result'] as $responseProduct){
                            if($skuProduct == $responseProduct['sku']){
                                $flag = 1;
                                $edaraId = $responseProduct['id'];
                            }
                        }

                        // $ckeckUrl = $baseUrl . "stockItems/Find?sku=".$skuProduct;
                        // $curl = curl_init($ckeckUrl);
                        // curl_setopt($curl, CURLOPT_URL, $ckeckUrl);
                        // curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                        // $headers = array(
                        // "Accept: application/json",
                        // "Authorization:".$edara_accsess_token."",
                        // );
                        // curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                        // //for debug only!
                        // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                        // curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

                        // $resp = curl_exec($curl);
                        // curl_close($curl);
                        // $result = json_decode($resp, true);

                        if ($flag == 1) {
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
                                'wp_product' => $product->ID,
                                'edara_product' => $edaraId,
                                'status' => "linked"
                            ));
                            }
                        } else{
                            if ($sale_price == "sale_price") {
                                $data = array('description' => $product->post_title, 'sku' =>$skuProduct,'price' => $price);
                            }else if ($sale_price == "dealer_price") {
                                $data = array('description' => $product->post_title, 'sku' =>$skuProduct,'dealer_price' => $price);
                            }else{
                                $data = array('description' => $product->post_title, 'sku' =>$skuProduct,'supper_dealer_price' => $price);
                            }
                            $options = array(
                                'http' => array(
                                    'header'  => "Authorization:".$edara_accsess_token."",
                                    'method'  => 'POST',
                                    'content' => http_build_query($data),
                                )
                            );
                            $context  = stream_context_create($options);
                            $result = file_get_contents($url, false, $context);
                            $result = json_decode($result, true);
                            if ($result['status_code'] == 200) {
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
                                    'wp_product' => $product->ID,
                                    'edara_product' => $result['result'],
                                    'status' => "linked"
                                ));
                                }
                            }else{
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
                                $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$product->ID."");
                                    if ($dataSelected != NULL)  {
                                        $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                                        'wp_product' => $product->ID,
                                        'edara_product' => "0",
                                        'status' => $result['error_message']
                                    ), array('wp_product' => $product->ID));
                                    }else{
                                        $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                            'wp_product' => $product->ID,
                                            'edara_product' => 0,
                                            'status' => $result['error_message']
                                        ));
                                    }
                                }
                            }
                        }
                    
                        //-----------------------------------------------------
                    }
                }
            }else{
                //Write the rule
                // $wp_rewrite->set_permalink_structure('/%post_id%/');

                //Set the option
                // update_option( "rewrite_rules", FALSE );

                //Flush the rules and tell it to write htaccess
                // $wp_rewrite->flush_rules( true );

                $url = $baseUrl . "stockItems?limit=10000000&offset=".$currentProducts;

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

                                $product_id = wc_get_product_id_by_sku($edaraProduct['sku']);

                                if ($product_id && $product_id > 0) {
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
                        
                                    $lastid = $productw->id;
                                    if ($lastid) {
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
                                    'menu_order' => $edaraProduct['code'],
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
            }
            break;
        case '2':
            // $customers = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."users WHERE ID <> 1");
            $customers = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."wc_customer_lookup");

            $getCustomersUrl = $baseUrl . "customers?Offset=0&limit=1000000";
            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => $getCustomersUrl,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'GET',
              CURLOPT_HTTPHEADER => array(
                'Authorization: '.$edara_accsess_token
              ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            // echo $response;
            $responseJson = json_decode($response,TRUE);

            $url = $baseUrl . "customers";

            if ($responseJson['status_code'] == '200') {
              foreach ($customers as $customer) {
                $flag = 0;
                $edaraId = 0;
                foreach ($responseJson['result'] as $responseCustomer) {
                  if ($customer->email == $responseCustomer['email']) {
                    $edaraId = $responseCustomer['id'];
                    $flag = 1;
                  }
                }

                $message = $flag . " " . $customer->email;
                file_put_contents(ABSPATH . "initial_setup.log",print_r($message,true),FILE_APPEND);

                if ($flag == 1) {
                    $charset_collate = $wpdb->get_charset_collate();
                    $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_customers (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    wp_customer varchar(255),
                    edara_customer varchar(255),
                    status varchar(255),
                    PRIMARY KEY (id)
                    ) $charset_collate;";
                    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                    dbDelta( $sql );
                    $is_error = empty( $wpdb->last_error );
                    if ($is_error) {
                        $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                            'wp_customer' => $customer->customer_id,
                            'edara_customer' => $edaraId,
                            'status' => "linked"
                        ));
                    }else{
                        var_dump($wpdb->last_error);die();
                    }
                } else{
                    $id = $customer->customer_id;
                    $name = $customer->first_name . " " . $customer->last_name;
                    $email = $customer->email;

                    $data = array('name' => $name, 'email' =>$email,'payment_type' => 'Credit');

                    $options = array(
                        'http' => array(
                            'header'  => "Authorization:".$edara_accsess_token."",
                            'method'  => 'POST',
                            'content' => http_build_query($data),
                        )
                    );
                    $context  = stream_context_create($options);
                    $result = file_get_contents($url, false, $context);
                    $result = json_decode($result, true);
                    if ($result['status_code'] == 200) {
                        $charset_collate = $wpdb->get_charset_collate();
                        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_customers (
                        id bigint(50) NOT NULL AUTO_INCREMENT,
                        wp_customer varchar(255),
                        edara_customer varchar(255),
                        status varchar(255),
                        PRIMARY KEY (id)
                        ) $charset_collate;";
                        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                        dbDelta( $sql );
                        $is_error = empty( $wpdb->last_error );
                        if ($is_error) {
                            $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                                'wp_customer' => $customer->customer_id,
                                'edara_customer' => $result['result'],
                                'status' => "linked"
                            ));
                        }else{
                            var_dump($wpdb->last_error);die();
                        }
                    }else{
                        $charset_collate = $wpdb->get_charset_collate();
                        // Check that the table does not already exist before continuing
                        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_customers (
                        id bigint(50) NOT NULL AUTO_INCREMENT,
                        wp_customer varchar(255),
                        edara_customer varchar(255),
                        status varchar(255),
                        PRIMARY KEY (id)
                        ) $charset_collate;";
                        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                        dbDelta( $sql );
                        $is_error = empty( $wpdb->last_error );
                        if ($is_error) {
                            $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$customer->ID."");
                            if ($dataSelected != NULL)  {
                                $row = $wpdb->update($wpdb->prefix.'edara_customers', array(
                                'wp_customer' => $customer->customer_id,
                                'edara_customer' => "0",
                                'status' => $result['error_message']
                              ), array('wp_customer' => $customer->customer_id));
                            }else{
                            $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                                'wp_customer' => $customer->customer_id,
                                'edara_customer' => 0,
                                'status' => $result['error_message']
                              ));
                            }
                        }
                    }
                }
              }
            }
            break;
    }
}

?>
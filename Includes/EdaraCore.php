<?php
declare(strict_types=1);

namespace Edara\Includes;

use Automattic\WooCommerce\Admin\Overrides\Order;

class EdaraCore
{
    public function init(): void
    {
        (new EdaraEndpoint())->addHooks();
        $this->addHooks();
    }

    private function addHooks()
    {
        add_action( 'wp_after_insert_post', array( __CLASS__, 'insert_new_product' ),10,3);   
        add_action( 'user_register', array( __CLASS__, 'my_user_register' ) );
        add_action( 'profile_update', array( __CLASS__, 'my_user_update' ), 10, 2 );
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'my_orders_hooks' ) );
        add_action( 'wp_insert_post', array( __CLASS__, 'my_orders_hooks_admin' ), 10, 1 );
        add_action( 'sbr_hooks_save_post', array( __CLASS__, 'my_update_order_one_request' ), 10, 3 );
        add_action( 'woocommerce_delete_order', array( __CLASS__, 'trashOrder' ) );
        add_action( 'woocommerce_trash_order', array( __CLASS__, 'trashOrder' ) );
        add_action('add_meta_boxes', [$this, 'addEdaraQuickLinks']);
        add_action('add_meta_boxes', [$this, 'addEdaraProductQuickLinks']);
        add_filter('manage_edit-shop_order_columns', [$this, 'registerNewOrderColInHeader']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'addOrderAdditionalInfoContent']);
        add_filter('woocommerce_product_data_tabs', [$this, 'hideInventoryTabInProductPage'], 10, 1);
        add_filter('woocommerce_inventory_settings', [$this, 'addAdditionalOptionForInventorySettings'], 10, 1);
        add_filter('woocommerce_get_availability_text', [$this, 'changeProductAvailabilityText'], 10, 2);

        if ( ! wp_next_scheduled( 'edara_check_product_discount_due_dates' ) ) {
            wp_schedule_event( time(), 'daily', 'edara_check_product_discount_due_dates' );
        }

        if ( ! wp_next_scheduled( 'every_six_hours_checker' ) ) {
            wp_schedule_event( time(), 'every_six_hours_edara_checker', 'every_six_hours_checker' );
        }

        if ( ! wp_get_scheduled_event( 'woocommerce_cancel_unpaid_orders' ) ) {
            $held_duration = get_option( 'woocommerce_hold_stock_minutes', '60' );

            if ( '' !== $held_duration ) {
                wp_schedule_single_event( time() + ( absint( $held_duration ) * 60 ), 'woocommerce_cancel_unpaid_orders' );
            }
        }

    }

    public static function insert_new_product($post, $update, $post_before){
        global $wpdb;

        $baseUrl = EdaraCore::getBaseUrlStatic();

        $is_installing = $wpdb->get_var("SELECT is_installing FROM ".$wpdb->prefix."edara_config WHERE id = 1");
        if($is_installing == 1){
            return;
        }

        $post = get_post($post);
        if(!$post) {
            return;
        }
    
        $postId = $post->ID;

        if ($post->post_type == "shop_order" || $post->post_type == "shop_order_placehold") {
            $order_status = $wpdb->get_var("SELECT status FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $postId);
            
            if ($order_status == '404') {
                return;
            }
        
            wp_schedule_single_event(time() + 10, 'sbr_hooks_save_post', array($postId, $post, $update));
            return;
        }

        EdaraCore::logFunctionStatic("insert_new_product " . print_r($post,true) . " ");

        if($post->post_status != 'publish') {
            if($post->post_status == 'publish-from-edara'){
                $result = $wpdb->update($wpdb->prefix.'posts', array(
                    'post_status' => 'publish'
                  ), array('ID'=>$postId));
            }
            EdaraCore::logGeneralStatic("SKIPPING ");
            return 0;
        }

        if($post->post_type == 'product_variation') {
            EdaraCore::logGeneralStatic("SKIPPING ");
            return 0;
        }

        $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");

        $productObj = wc_get_product($postId);

        if(empty($productObj)){
            return;
        }
        
        if($productObj->exists() == 0){
            return;
        }

        if($productObj->get_sku()){
            $productSku = $productObj->get_sku();

            $countSkus = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_sku' AND meta_value = '$productSku'");

            if($countSkus > 1){
                //Check wpml
                $tableName = $wpdb->prefix."icl_translations";
                $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
                if($wpdb->get_var($query) == $tableName){
                    $checkSourceLang = $wpdb->get_var("SELECT source_language_code FROM ".$wpdb->base_prefix."icl_translations WHERE element_id = ".$postId);
                    if($checkSourceLang){
                        EdaraCore::logGeneralStatic("SKIPPING .... translation found");

                        return;
                    }
                }
            }
        }

        if($productObj->get_type() == 'variable'){
            EdaraCore::logGeneralStatic("Product is variable");
            $childsIds = $wpdb->get_results("SELECT ID FROM ".$wpdb->base_prefix."posts WHERE post_parent = ".$postId);
            foreach($childsIds as $childId){
                $checkExisted = $wpdb->get_var("SELECT wp_product FROM ".$wpdb->base_prefix."edara_products WHERE wp_product = ".$childId->ID);
                EdaraCore::logGeneralStatic("Child " . print_r($childId,true));
                if($checkExisted != $childId){
                    $childObj = wc_get_product($childId);

                    if($childObj == null || $childObj == false){
                        continue;
                    }

                    $title = $childObj->get_name();
                    $skuProduct = $childObj->get_sku();
                    $price = $childObj->get_regular_price();

                    EdaraCore::logGeneralStatic("Title = ".$title.", price = ".$price.", sku = ".$skuProduct);

                    $productExsistsID = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$childId->ID);
                    if($productExsistsID > 0){
                        $getCodeUrl = $baseUrl . "stockItems/".$productExsistsID;
                        $productCode = "0";
                        $options = array(
                            'http' => array(
                                'header'  => "Authorization:".$edara_accsess_token."",
                                'method'  => 'GET',
                            )
                        );
                        $context  = stream_context_create($options);
                        $result = file_get_contents($getCodeUrl, false, $context);

                        EdaraCore::logRequestStatic("Get product by id ",$getCodeUrl,$options,$result);

                        $result = json_decode($result, true);

                        $edara_other_lang_description = "";
                        $edara_code = "";
                        $edara_sku = "";
                        $edara_purchase_price = 0.0;
                        $edara_dealer_price = 0.0;
                        $edara_supper_dealer_price = 0.0;
                        $edara_minimum_price = 0.0;
                        $edara_part_number = "";
                        $edara_tax_rate = 0.0;
                        $edara_classification_id = null;
                        $edara_brand_id = 0;
                        $edara_warranty = 0.0;
                        $edara_dynamic_properties_info = array();
                        $edara_unitofmeasure_chain_id = null;
                        $edara_default_unitofmeasure_id = null;
                        $edara_unitofmeasures_info = array();
                        $edara_sales_price_discount = 0.0;
                        $edara_sales_price_discount_type = 0;
                        $edara_sales_price_discount_is_limited = false;
                        $edara_sales_price_discount_date_from = null;
                        $edara_sales_price_discount_date_to = null;
                        $edara_dealer_price_discount = 0.0;
                        $edara_dealer_price_discount_type = 0;
                        $edara_dealer_price_discount_is_limited = false;
                        $edara_dealer_price_discount_date_from = null;
                        $edara_dealer_price_discount_date_to = null;
                        $edara_super_dealer_price_discount = 0.0;
                        $edara_super_dealer_price_discount_type = 0;
                        $edara_super_dealer_price_discount_is_limited = false;
                        $edara_super_dealer_price_discount_date_from = null;
                        $edara_super_dealer_price_discount_date_to = null;
                        $edara_weight = 0.0;
                        $edara_data_sheet = "";
                        $edara_note = "";

                        if($result['status_code'] == '200'){
                            $edara_other_lang_description = $result['result']['other_lang_description'];
                            $edara_code = $result['result']['code'];
                            $edara_sku = $result['result']['sku'];
                            $edara_purchase_price = $result['result']['purchase_price'];
                            $edara_dealer_price = $result['result']['dealer_price'];
                            $edara_supper_dealer_price = $result['result']['supper_dealer_price'];
                            $edara_minimum_price = $result['result']['minimum_price'];
                            $edara_part_number = $result['result']['part_number'];
                            $edara_tax_rate = $result['result']['tax_rate'];
                            $edara_classification_id = $result['result']['classification_id'];
                            $edara_brand_id = $result['result']['brand_id'];
                            $edara_warranty = $result['result']['warranty'];
                            $edara_dynamic_properties_info = $result['result']['dynamic_properties_info'];
                            $edara_unitofmeasure_chain_id = $result['result']['unitofmeasure_chain_id'];
                            $edara_default_unitofmeasure_id = $result['result']['default_unitofmeasure_id'];
                            $edara_unitofmeasures_info = $result['result']['unitofmeasures_info'];
                            $edara_sales_price_discount = $result['result']['sales_price_discount'];
                            $edara_sales_price_discount_type = $result['result']['sales_price_discount_type'];
                            $edara_sales_price_discount_is_limited = $result['result']['sales_price_discount_is_limited'];
                            $edara_sales_price_discount_date_from = $result['result']['sales_price_discount_date_from'];
                            $edara_sales_price_discount_date_to = $result['result']['sales_price_discount_date_to'];
                            $edara_dealer_price_discount = $result['result']['dealer_price_discount'];
                            $edara_dealer_price_discount_type = $result['result']['dealer_price_discount_type'];
                            $edara_dealer_price_discount_is_limited = $result['result']['dealer_price_discount_is_limited'];
                            $edara_dealer_price_discount_date_from = $result['result']['dealer_price_discount_date_from'];
                            $edara_dealer_price_discount_date_to = $result['result']['dealer_price_discount_date_to'];
                            $edara_super_dealer_price_discount = $result['result']['super_dealer_price_discount'];
                            $edara_super_dealer_price_discount_type = $result['result']['super_dealer_price_discount_type'];
                            $edara_super_dealer_price_discount_is_limited = $result['result']['super_dealer_price_discount_is_limited'];
                            $edara_super_dealer_price_discount_date_from = $result['result']['super_dealer_price_discount_date_from'];
                            $edara_super_dealer_price_discount_date_to = $result['result']['super_dealer_price_discount_date_to'];
                            $edara_weight = $result['result']['weight'];
                            $edara_data_sheet = $result['result']['data_sheet'];
                            $edara_note = $result['result']['note'];
                        }else{
                            return;
                        }

                        $url = $baseUrl . "stockItems";
                        $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");

                        $data = array(
                            'id' => $productExsistsID,
                            'description' => $title,
                            'other_lang_description' => $edara_other_lang_description,
                            'code' => $edara_code,
                            'sku' => $edara_sku,
                            'price' => $price,
                            'purchase_price' => $edara_purchase_price,
                            'dealer_price' => $edara_dealer_price,
                            'supper_dealer_price' => $edara_supper_dealer_price,
                            'minimum_price' => $edara_minimum_price,
                            'part_number' => $edara_part_number,
                            'tax_rate' => $edara_tax_rate,
                            'classification_id' => $edara_classification_id,
                            'brand_id' => $edara_brand_id,
                            'warranty' => $edara_warranty,
                            'dynamic_properties_info' => $edara_dynamic_properties_info,
                            'unitofmeasure_chain_id' => $edara_unitofmeasure_chain_id,
                            'default_unitofmeasure_id' => $edara_default_unitofmeasure_id,
                            'sales_price_discount' => $edara_sales_price_discount,
                            'sales_price_discount_type' => $edara_sales_price_discount_type,
                            'sales_price_discount_is_limited' => $edara_sales_price_discount_is_limited,
                            'sales_price_discount_date_from' => $edara_sales_price_discount_date_from,
                            'sales_price_discount_date_to' => $edara_sales_price_discount_date_to,
                            'dealer_price_discount' => $edara_dealer_price_discount,
                            'dealer_price_discount_type' => $edara_dealer_price_discount_type,
                            'dealer_price_discount_is_limited' => $edara_dealer_price_discount_is_limited,
                            'dealer_price_discount_date_from' => $edara_dealer_price_discount_date_from,
                            'dealer_price_discount_date_to' => $edara_dealer_price_discount_date_to,
                            'super_dealer_price_discount' => $edara_super_dealer_price_discount,
                            'super_dealer_price_discount_type' => $edara_super_dealer_price_discount_type,
                            'super_dealer_price_discount_is_limited' => $edara_super_dealer_price_discount_is_limited,
                            'super_dealer_price_discount_date_from' => $edara_super_dealer_price_discount_date_from,
                            'super_dealer_price_discount_date_to' => $edara_super_dealer_price_discount_date_to,
                            'weight' => $edara_weight,
                            'data_sheet' => $edara_data_sheet,
                            'note' => $edara_note
                        );

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'PUT',
                        CURLOPT_POSTFIELDS =>json_encode($data),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: '.$edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                        ));

                        $result = curl_exec($curl);

                        curl_close($curl);

                        $result = json_decode($result, true);
            
                        EdaraCore::logRequestStatic("update product child ",$url,json_encode($data),$result);

                        if ($result['status_code'] == 200) {
                            $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'status' => "linked"
                                ), array('id'=>$childId->ID));
                        }
                        continue;
                    }

                    $url = $baseUrl . "stockItems";
                    $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");
                    if ($sale_price == "sale_price") {
                        $data = array('description' => $title, 'sku' =>$skuProduct,'price' => $price);
                    }else if ($sale_price == "dealer_price") {
                        $data = array('description' => $title, 'sku' =>$skuProduct,'dealer_price' => $price);
                    }else{
                        $data = array('description' => $title, 'sku' =>$skuProduct,'supper_dealer_price' => $price);
                    }

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS =>json_encode($data),
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: '.$edara_accsess_token,
                        'Content-Type: application/json'
                    ),
                    ));

                    $result = curl_exec($curl);

                    curl_close($curl);

                    $result = json_decode($result, true);
        
                    EdaraCore::logRequestStatic("insert product child ",$url,json_encode($data),$result);

                    if ($result['status_code'] == 200) {
                        $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                'wp_product' => $childId->ID,
                                'edara_product' => $result['result'],
                                'status' => "linked"
                        ));
                    }else{
                        $post_id = $postId;
                        $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                        if ($dataSelected != NULL)  {
                            $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                            'wp_product' => $childId->ID,
                            'edara_product' => "0",
                            'status' => $result['error_message']
                        ), array('wp_product' => $childId->ID));
                        }else{
                            $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                'wp_product' => $childId->ID,
                                'edara_product' => $result['result'],
                                'status' => "linked"
                            ));
                        }
                    }
                }
            }
            return 0;
        }else{
            EdaraCore::logGeneralStatic("Product is not variable");
        }

        $products_selection = $wpdb->get_var("SELECT products_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");

        EdaraCore::logGeneralStatic($products_selection);

        if (true) {
            $products_table = $wpdb->base_prefix.'edara_products';
            $queryProducts = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $products_table ) );
            if ($products_table == $wpdb->get_var( $queryProducts )) {
                $productExsists = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$postId);
                if ($productExsists) {
                    $productExsistsID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$postId);
                    if ($productExsists == 0) {
                        $productObj = wc_get_product($postId);
                        EdaraCore::logGeneralStatic("Product type: " . $productObj->get_type());
                        // update status
                        $skuProduct = get_post_meta( $postId, '_sku', true );
                        $price = get_post_meta( $postId, '_regular_price', true );

                        $edaraExsistsID = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$postId);
                        if($edaraExsistsID > 0){
                            $url = $baseUrl . "stockItems";
                            $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");
                            if ($sale_price == "sale_price") {
                                $data = array('id' => $edaraExsistsID ,'description' => $title,'price' => $price);
                            }else if ($sale_price == "dealer_price") {
                                $data = array('id' => $edaraExsistsID ,'description' => $title,'dealer_price' => $price);
                            }else{
                                $data = array('id' => $edaraExsistsID ,'description' => $title,'supper_dealer_price' => $price);
                            }

                            $curl = curl_init();

                            curl_setopt_array($curl, array(
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'PUT',
                            CURLOPT_POSTFIELDS =>json_encode($data),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: '.$edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                            ));

                            $result = curl_exec($curl);

                            curl_close($curl);
                            
                            $result = json_decode($result, true);
                
                            EdaraCore::logRequestStatic("update product ",$url,json_encode($data),$result);

                            if ($result['status_code'] == 200) {
                                $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                                    'status' => "linked"
                                    ), array('id'=>$postId));
                            }
                            return;
                        }
                        
                        $url = $baseUrl . "stockItems";
                        $data = array('description' => $post->post_title, 'sku' =>$skuProduct,'price' => $price);

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS =>json_encode($data),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: '.$edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                        ));

                        $result = curl_exec($curl);

                        curl_close($curl);

                        $result = json_decode($result, true);

                        EdaraCore::logRequestStatic("Post stock item ",$url,json_encode($data),$result);

                        if ($result['status_code'] == 200) {
                              $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'edara_product' => $result['result'],
                                'status' => "linked"
                              ), array('id'=>$productExsistsID));
                        }else{
                            $post_id = $postId;
                            $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                            if ($dataSelected != NULL)  {
                                $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'wp_product' => $post_id,
                                'edara_product' => "0",
                                'status' => $result['error_message']
                              ), array('wp_product' => $post_id));
                            }else{
                                // $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                //     'wp_product' => $post_id,
                                //     'edara_product' => 0,
                                //     'status' => $result['error_message']
                                //   ));
                            }
                        }
                    }else{

                        $product = $wpdb->get_var( "SELECT menu_order FROM ".$wpdb->prefix."posts WHERE ID = ".$_POST['post_ID']." AND post_type = 'product'");


                        $edaraExsistsID = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$postId);

                        //Get stockitem by id
                        $url = $baseUrl . "stockItems/" . $edaraExsistsID;

                        $options = array(
                            'http' => array(
                                'header'  => "Authorization:".$edara_accsess_token."",
                                'method'  => 'GET',
                            )
                        );

                        $context  = stream_context_create($options);
                        $result = file_get_contents($url, false, $context);

                        EdaraCore::logRequestStatic("Get product by id ",$url,$options,$result);

                        $result = json_decode($result, true);

                        $edara_other_lang_description = "";
                        $edara_code = "";
                        $edara_sku = "";
                        $edara_purchase_price = 0.0;
                        $edara_dealer_price = 0.0;
                        $edara_supper_dealer_price = 0.0;
                        $edara_minimum_price = 0.0;
                        $edara_part_number = "";
                        $edara_tax_rate = 0.0;
                        $edara_classification_id = null;
                        $edara_brand_id = 0;
                        $edara_warranty = 0.0;
                        $edara_dynamic_properties_info = array();
                        $edara_unitofmeasure_chain_id = null;
                        $edara_default_unitofmeasure_id = null;
                        $edara_unitofmeasures_info = array();
                        $edara_sales_price_discount = 0.0;
                        $edara_sales_price_discount_type = 0;
                        $edara_sales_price_discount_is_limited = false;
                        $edara_sales_price_discount_date_from = null;
                        $edara_sales_price_discount_date_to = null;
                        $edara_dealer_price_discount = 0.0;
                        $edara_dealer_price_discount_type = 0;
                        $edara_dealer_price_discount_is_limited = false;
                        $edara_dealer_price_discount_date_from = null;
                        $edara_dealer_price_discount_date_to = null;
                        $edara_super_dealer_price_discount = 0.0;
                        $edara_super_dealer_price_discount_type = 0;
                        $edara_super_dealer_price_discount_is_limited = false;
                        $edara_super_dealer_price_discount_date_from = null;
                        $edara_super_dealer_price_discount_date_to = null;
                        $edara_weight = 0.0;
                        $edara_data_sheet = "";
                        $edara_note = "";

                        if ($result['status_code'] == 200) {
                            $edara_other_lang_description = $result['result']['other_lang_description'];
                            $edara_code = $result['result']['code'];
                            $edara_sku = $result['result']['sku'];
                            $edara_purchase_price = $result['result']['purchase_price'];
                            $edara_dealer_price = $result['result']['dealer_price'];
                            $edara_supper_dealer_price = $result['result']['supper_dealer_price'];
                            $edara_minimum_price = $result['result']['minimum_price'];
                            $edara_part_number = $result['result']['part_number'];
                            $edara_tax_rate = $result['result']['tax_rate'];
                            $edara_classification_id = $result['result']['classification_id'];
                            $edara_brand_id = $result['result']['brand_id'];
                            $edara_warranty = $result['result']['warranty'];
                            $edara_dynamic_properties_info = $result['result']['dynamic_properties_info'];
                            $edara_unitofmeasure_chain_id = $result['result']['unitofmeasure_chain_id'];
                            $edara_default_unitofmeasure_id = $result['result']['default_unitofmeasure_id'];
                            $edara_unitofmeasures_info = $result['result']['unitofmeasures_info'];
                            $edara_sales_price_discount = $result['result']['sales_price_discount'];
                            $edara_sales_price_discount_type = $result['result']['sales_price_discount_type'];
                            $edara_sales_price_discount_is_limited = $result['result']['sales_price_discount_is_limited'];
                            $edara_sales_price_discount_date_from = $result['result']['sales_price_discount_date_from'];
                            $edara_sales_price_discount_date_to = $result['result']['sales_price_discount_date_to'];
                            $edara_dealer_price_discount = $result['result']['dealer_price_discount'];
                            $edara_dealer_price_discount_type = $result['result']['dealer_price_discount_type'];
                            $edara_dealer_price_discount_is_limited = $result['result']['dealer_price_discount_is_limited'];
                            $edara_dealer_price_discount_date_from = $result['result']['dealer_price_discount_date_from'];
                            $edara_dealer_price_discount_date_to = $result['result']['dealer_price_discount_date_to'];
                            $edara_super_dealer_price_discount = $result['result']['super_dealer_price_discount'];
                            $edara_super_dealer_price_discount_type = $result['result']['super_dealer_price_discount_type'];
                            $edara_super_dealer_price_discount_is_limited = $result['result']['super_dealer_price_discount_is_limited'];
                            $edara_super_dealer_price_discount_date_from = $result['result']['super_dealer_price_discount_date_from'];
                            $edara_super_dealer_price_discount_date_to = $result['result']['super_dealer_price_discount_date_to'];
                            $edara_weight = $result['result']['weight'];
                            $edara_data_sheet = $result['result']['data_sheet'];
                            $edara_note = $result['result']['note'];
                        }else{
                            return;
                        }

                        $url = $baseUrl . "stockItems";
                        $skuProduct = get_post_meta( $postId, '_sku', true );
                        $price = get_post_meta( $postId, '_regular_price', true );

                        $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");

                        $data = array(
                            'id' => $edaraExsistsID,
                            'description' => $post->post_title,
                            'other_lang_description' => $edara_other_lang_description,
                            'code' => $edara_code,
                            'sku' => $edara_sku,
                            'price' => $price,
                            'purchase_price' => $edara_purchase_price,
                            'dealer_price' => $edara_dealer_price,
                            'supper_dealer_price' => $edara_supper_dealer_price,
                            'minimum_price' => $edara_minimum_price,
                            'part_number' => $edara_part_number,
                            'tax_rate' => $edara_tax_rate,
                            'classification_id' => $edara_classification_id,
                            'brand_id' => $edara_brand_id,
                            'warranty' => $edara_warranty,
                            'dynamic_properties_info' => $edara_dynamic_properties_info,
                            'unitofmeasure_chain_id' => $edara_unitofmeasure_chain_id,
                            'default_unitofmeasure_id' => $edara_default_unitofmeasure_id,
                            'sales_price_discount' => $edara_sales_price_discount,
                            'sales_price_discount_type' => $edara_sales_price_discount_type,
                            'sales_price_discount_is_limited' => $edara_sales_price_discount_is_limited,
                            'sales_price_discount_date_from' => $edara_sales_price_discount_date_from,
                            'sales_price_discount_date_to' => $edara_sales_price_discount_date_to,
                            'dealer_price_discount' => $edara_dealer_price_discount,
                            'dealer_price_discount_type' => $edara_dealer_price_discount_type,
                            'dealer_price_discount_is_limited' => $edara_dealer_price_discount_is_limited,
                            'dealer_price_discount_date_from' => $edara_dealer_price_discount_date_from,
                            'dealer_price_discount_date_to' => $edara_dealer_price_discount_date_to,
                            'super_dealer_price_discount' => $edara_super_dealer_price_discount,
                            'super_dealer_price_discount_type' => $edara_super_dealer_price_discount_type,
                            'super_dealer_price_discount_is_limited' => $edara_super_dealer_price_discount_is_limited,
                            'super_dealer_price_discount_date_from' => $edara_super_dealer_price_discount_date_from,
                            'super_dealer_price_discount_date_to' => $edara_super_dealer_price_discount_date_to,
                            'weight' => $edara_weight,
                            'data_sheet' => $edara_data_sheet,
                            'note' => $edara_note
                        );

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'PUT',
                        CURLOPT_POSTFIELDS =>json_encode($data),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: '.$edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                        ));

                        $result = curl_exec($curl);

                        curl_close($curl);

                        EdaraCore::logRequestStatic("Update product by id ",$url,json_encode($data),$result);

                        $result = json_decode($result, true);

                        if ($result['status_code'] == 200) {
                              $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'status' => "linked"
                              ), array('id'=>$productExsistsID));
                        }else{
                            $post_id = $postId;
                            $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                            if ($dataSelected != NULL)  {
                                $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'wp_product' => $post_id,
                                'status' => $result['error_message']
                              ), array('wp_product' => $post_id));
                            }else{
                                // $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                //     'wp_product' => $post_id,
                                //     'status' => $result['error_message']
                                //   ));
                            }
                        }
                    }
                }else{
                    $productObj = wc_get_product($postId);

                    $variableId = $wpdb->get_var("SELECT term_id FROM ".$wpdb->base_prefix."terms WHERE name = 'variable'");

                    $sql = "SELECT object_id FROM ".$wpdb->base_prefix."term_relationships WHERE object_id = '".$_POST['post_ID']."' AND term_taxonomy_id = '".$variableId."'";
                    $isVariable = $wpdb->get_var($sql);
                    
                    EdaraCore::logGeneralStatic($sql);

                    if($postId == $isVariable){
                        EdaraCore::logGeneralStatic("Product is variable");
                    }else{
                        EdaraCore::logGeneralStatic("Product is not variable");
                    }

                    $url = $baseUrl . "stockItems";
                    $skuProduct = get_post_meta( $postId, '_sku', true );
                    $price = get_post_meta( $postId, '_regular_price', true );
                    
                    $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");

                    if ($sale_price == "sale_price") {
                        $data = array('description' => $post->post_title, 'sku' =>$skuProduct,'price' => $price);
                    }else if ($sale_price == "dealer_price") {
                        $data = array('description' => $post->post_title, 'sku' =>$skuProduct,'dealer_price' => $price);
                    }else{
                        $data = array('description' => $post->post_title, 'sku' =>$skuProduct,'supper_dealer_price' => $price);
                    }

                    if (isset($post->post_title)) {

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS =>json_encode($data),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: '.$edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                        ));

                        $result = curl_exec($curl);

                        curl_close($curl);

                        $result = json_decode($result, true);

                        EdaraCore::logRequestStatic("Update product by code ",$url,json_encode($data),$result);

                        // var_dump($result['status_code']);die();
                        if ($result['status_code'] == 200) {
                            $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                    'wp_product' => $postId,
                                    'edara_product' => $result['result'],
                                    'status' => "linked"
                            ));
                        }else{
                            $post_id = $postId;
                            $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                            if ($dataSelected != NULL)  {
                                $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'wp_product' => $post_id,
                                'edara_product' => "0",
                                'status' => $result['error_message']
                            ), array('wp_product' => $post_id));
                            }else{
                                // $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                //     'wp_product' => $post_id,
                                //     'edara_product' => $result['result'],
                                //     'status' => "linked"
                                // ));
                            }
                        }
                    }
                }

            }

        }

        return true;
    }

    public static function my_product_insert_delay($postId = null,$price = null,$sku = null,$title = null){
        EdaraCore::logFunctionStatic("my_product_insert delay" . $postId . " ");

        $baseUrl = EdaraCore::getBaseUrlStatic();

        global $wpdb;
        $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");

        $variableId = $wpdb->get_var("SELECT term_id FROM ".$wpdb->base_prefix."terms WHERE name = 'variable'");

        $sql = "SELECT object_id FROM ".$wpdb->base_prefix."term_relationships WHERE object_id = '".$postId."' AND term_taxonomy_id = '".$variableId."'";
        $isVariable = $wpdb->get_var($sql);
        
        EdaraCore::logGeneralStatic($sql);

        if($postId == $isVariable){
            EdaraCore::logGeneralStatic("Product is variable");
            $childsIds = $wpdb->get_results("SELECT ID FROM ".$wpdb->base_prefix."posts WHERE post_parent = ".$postId);
            foreach($childsIds as $childId){
                $checkExisted = $wpdb->get_var("SELECT wp_product FROM ".$wpdb->base_prefix."edara_products WHERE wp_product = ".$childId->ID);
                if($checkExisted != $childId){
                    $childObj = wc_get_product($childId);
                    // EdaraCore::logGeneralStatic(print_r($childObj,true));

                    $title = $childObj->get_name();
                    // $skuProduct = get_post_meta( $postId, '_sku', true );
                    // $price = get_post_meta( $postId, '_regular_price', true );
                    $skuProduct = $childObj->get_sku();
                    $price = $childObj->get_regular_price();

                    EdaraCore::logGeneralStatic("Title = ".$title.", price = ".$price.", sku = ".$skuProduct);

                    $url = $baseUrl . "stockItems";
                    $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");
                    if ($sale_price == "sale_price") {
                        $data = array('description' => $title, 'sku' =>$skuProduct,'price' => $price);
                    }else if ($sale_price == "dealer_price") {
                        $data = array('description' => $title, 'sku' =>$skuProduct,'dealer_price' => $price);
                    }else{
                        $data = array('description' => $title, 'sku' =>$skuProduct,'supper_dealer_price' => $price);
                    }

                    // $options = array(
                    //     'http' => array(
                    //         'header'  => "Authorization:".$edara_accsess_token."",
                    //         'method'  => 'POST',
                    //         'content' => http_build_query($data),
                    //     )
                    // );
                    // $context  = stream_context_create($options);
                    // $result = file_get_contents($url, false, $context);

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS =>json_encode($data),
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: '.$edara_accsess_token,
                        'Content-Type: application/json'
                    ),
                    ));

                    $result = curl_exec($curl);

                    curl_close($curl);
                    
                    $result = json_decode($result, true);
        
                    EdaraCore::logRequestStatic("insert product child ",$url,json_encode($data),$result);

                    if ($result['status_code'] == 200) {
                        $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                'wp_product' => $childId->ID,
                                'edara_product' => $result['result'],
                                'status' => "linked"
                        ));
                    }else{
                        $post_id = $postId;
                        $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                        if ($dataSelected != NULL)  {
                            $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                            'wp_product' => $childId->ID,
                            'edara_product' => "0",
                            'status' => $result['error_message']
                        ), array('wp_product' => $$childId->ID));
                        }else{
                            // $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                            //     'wp_product' => $childId->ID,
                            //     'edara_product' => $result['result'],
                            //     'status' => "linked"
                            // ));
                        }
                    }
                }
            }
            return 0;
        }else{
            EdaraCore::logGeneralStatic("Product is not variable");
        }

        $url = $baseUrl . "stockItems";
        $price = $price != "" ? (double)$price : 0;
        $skuProduct = get_post_meta( $postId, '_sku', true );
        if ($skuProduct == "") {
            $skuProduct = $sku;
        }
        $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");

        if ($sale_price == "sale_price") {
            $data = array('description' => $title, 'sku' =>$skuProduct,'price' => $price);
        }else if ($sale_price == "dealer_price") {
            $data = array('description' => $title, 'sku' =>$skuProduct,'dealer_price' => $price);
        }else{
            $data = array('description' => $title, 'sku' =>$skuProduct,'supper_dealer_price' => $price);
        }

        // $data = array('description' => $_POST['post_title'], 'sku' =>$skuProduct,'price' => $price);

        // $edara_accsess_token = "5ivhDvWwFzgB9nljbwKajWC7BDelZPcZ0odfIfW4hzs=";
        if (isset($title)) {
            // $options = array(
            //     'http' => array(
            //         'header'  => "Authorization:".$edara_accsess_token."",
            //         'method'  => 'POST',
            //         'content' => http_build_query($data),
            //     )
            // );
            // $context  = stream_context_create($options);
            // $result = file_get_contents($url, false, $context);

            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Authorization: '.$edara_accsess_token,
                'Content-Type: application/json'
            ),
            ));

            $result = curl_exec($curl);

            curl_close($curl);
            
            $result = json_decode($result, true);

            EdaraCore::logRequestStatic("Insert new product ",$url,json_encode($data),$result);

            // var_dump($result['status_code']);die();
            if ($result['status_code'] == 200) {
                $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_product' => $postId,
                        'edara_product' => $result['result'],
                        'status' => "linked"
                ));
            }else{
                $post_id = $postId;
                $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                if ($dataSelected != NULL)  {
                    $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                    'wp_product' => $post_id,
                    'edara_product' => "0",
                    'status' => $result['error_message']
                ), array('wp_product' => $post_id));
                }else{
                    // $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                    //     'wp_product' => $post_id,
                    //     'edara_product' => $result['result'],
                    //     'status' => "linked"
                    // ));
                }
            }
        }
    }

    public static function my_product_insert($post_id,$post,$update) {
        EdaraCore::logFunctionStatic("my_product_insert " . $post_id . " " . $post->post_status);
        // EdaraCore::logFunctionStatic(print_r($post,true));

        $baseUrl = EdaraCore::getBaseUrlStatic();

        if(!isset($_POST['post_ID'])){
            return 0;
        }

        EdaraCore::logGeneralStatic("Revision = " . wp_is_post_revision( $post_id) . " Autosave = " . wp_is_post_autosave( $post_id ) . " Update = " . $update);

        if($post->post_status != 'publish') {
            EdaraCore::logGeneralStatic("SKIPPING ");
            return 0;
         }

        global $wpdb;

        $products_selection = $wpdb->get_var("SELECT products_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");
        $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");

        EdaraCore::logGeneralStatic($products_selection);

        if (true) {
            $products_table = $wpdb->base_prefix.'edara_products';
            $queryProducts = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $products_table ) );
            if ($products_table == $wpdb->get_var( $queryProducts )) {
                $productExsists = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$_POST['post_ID']);
                if ($productExsists) {
                    $productExsistsID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$_POST['post_ID']);
                    if ($productExsists == 0) {
                        $productObj = wc_get_product($_POST['post_ID']);
                        EdaraCore::logGeneralStatic("Product type: " . $productObj->get_type());
                        // update status
                        $skuProduct = get_post_meta( $_POST['post_ID'], '_sku', true );
                        $url = $baseUrl . "stockItems";
                        $price = $_POST['_regular_price'] != "" ? (double)$_POST['_regular_price'] : 0;
                        $data = array('description' => $_POST['post_title'], 'sku' =>$skuProduct,'price' => $price);

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS =>json_encode($data),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: '.$edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                        ));

                        $result = curl_exec($curl);

                        curl_close($curl);

                        $result = json_decode($result, true);
                        // var_dump($result['status_code']);die();

                        EdaraCore::logRequestStatic("Post stock item ",$url,json_encode($data),$result);

                        if ($result['status_code'] == 200) {
                              $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'edara_product' => $result['result'],
                                'status' => "linked"
                              ), array('id'=>$productExsistsID));
                        }else{
                            $post_id = $_POST['post_ID'];
                            $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                            if ($dataSelected != NULL)  {
                                $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'wp_product' => $post_id,
                                'edara_product' => "0",
                                'status' => $result['error_message']
                              ), array('wp_product' => $post_id));
                            }else{
                                // $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                //     'wp_product' => $post_id,
                                //     'edara_product' => 0,
                                //     'status' => $result['error_message']
                                //   ));
                            }
                        }
                    }else{
                        // $edara_accsess_token = "5ivhDvWwFzgB9nljbwKajWC7BDelZPcZ0odfIfW4hzs=";
                        $product = $wpdb->get_var( "SELECT menu_order FROM ".$wpdb->prefix."posts WHERE ID = ".$_POST['post_ID']." AND post_type = 'product'");
                        //var_dump($product);die();
                        $url = $baseUrl . "stockItems/UpdateByCode/".$product;
                        $price = $_POST['_regular_price'] != "" ? (double)$_POST['_regular_price'] : 0;
                        $skuProduct = get_post_meta( $_POST['post_ID'], '_sku', true );

                        $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");

                        if ($sale_price == "sale_price") {
                            $data = array('description' => $_POST['post_title'], 'code' =>$product,'price' => $price);
                        }else if ($sale_price == "dealer_price") {
                            $data = array('description' => $_POST['post_title'], 'code' =>$product,'dealer_price' => $price);
                        }else{
                            $data = array('description' => $_POST['post_title'], 'code' =>$product,'supper_dealer_price' => $price);
                        }

                        // $data = array('description' => $_POST['post_title'], 'sku' =>$skuProduct,'price' => $price,'code' => $product);

                        // $options = array(
                        //     'http' => array(
                        //         'header'  => "Authorization:".$edara_accsess_token."",
                        //         'method'  => 'PUT',
                        //         'content' => http_build_query($data),
                        //     )
                        // );
                        // $context  = stream_context_create($options);
                        // $result = file_get_contents($url, false, $context);

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'PUT',
                        CURLOPT_POSTFIELDS =>json_encode($data),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: '.$edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                        ));

                        $result = curl_exec($curl);

                        curl_close($curl);
                        
                        $result = json_decode($result, true);

                        EdaraCore::logRequestStatic("Update product by code ",$url,json_encode($data),$result);

                        if ($result['status_code'] == 200) {
                              $result = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'status' => "linked"
                              ), array('id'=>$productExsistsID));
                        }else{
                            $post_id = $_POST['post_ID'];
                            $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                            if ($dataSelected != NULL)  {
                                $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'wp_product' => $post_id,
                                'edara_product' => "0",
                                'status' => $result['error_message']
                              ), array('wp_product' => $post_id));
                            }else{
                                $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                    'wp_product' => $post_id,
                                    'edara_product' => 0,
                                    'status' => $result['error_message']
                                  ));
                            }
                        }
                    }
                }else{
                    $productObj = wc_get_product($_POST['post_ID']);
                    // if(is_a( $productObj, 'WC_Product_Variable' )){

                    wp_schedule_single_event( time()+10 , 'sbr_insert_product_delay', array($_POST['post_ID'],$_POST['_regular_price'],$_POST['_sku'],$_POST['post_title']));                    
                    return 0;

                    $variableId = $wpdb->get_var("SELECT term_id FROM ".$wpdb->base_prefix."terms WHERE name = 'variable'");

                    $sql = "SELECT object_id FROM ".$wpdb->base_prefix."term_relationships WHERE object_id = '".$_POST['post_ID']."' AND term_taxonomy_id = '".$variableId."'";
                    $isVariable = $wpdb->get_var($sql);
                    
                    EdaraCore::logGeneralStatic($sql);

                    if($_POST['post_ID'] == $isVariable){
                        EdaraCore::logGeneralStatic("Product is variable");
                    }else{
                        EdaraCore::logGeneralStatic("Product is not variable");
                    }

                    $url = $baseUrl . "stockItems";
                    $price = $_POST['_regular_price'] != "" ? (double)$_POST['_regular_price'] : 0;
                    $skuProduct = get_post_meta( $_POST['post_ID'], '_sku', true );
                    if ($skuProduct == "") {
                        $skuProduct = $_POST['_sku'];
                    }
                    $sale_price = $wpdb->get_var("SELECT sale_price FROM ".$wpdb->prefix."edara_config WHERE id = 1");

                    if ($sale_price == "sale_price") {
                        $data = array('description' => $_POST['post_title'], 'sku' =>$skuProduct,'price' => $price);
                    }else if ($sale_price == "dealer_price") {
                        $data = array('description' => $_POST['post_title'], 'sku' =>$skuProduct,'dealer_price' => $price);
                    }else{
                        $data = array('description' => $_POST['post_title'], 'sku' =>$skuProduct,'supper_dealer_price' => $price);
                    }

                    // $data = array('description' => $_POST['post_title'], 'sku' =>$skuProduct,'price' => $price);

                    // $edara_accsess_token = "5ivhDvWwFzgB9nljbwKajWC7BDelZPcZ0odfIfW4hzs=";
                    if (isset($_POST['post_title'])) {
                    //   $options = array(
                    //       'http' => array(
                    //           'header'  => "Authorization:".$edara_accsess_token."",
                    //           'method'  => 'POST',
                    //           'content' => http_build_query($data),
                    //       )
                    //   );
                    //   $context  = stream_context_create($options);
                    //   $result = file_get_contents($url, false, $context);

                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                        CURLOPT_URL => $url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS =>json_encode($data),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: '.$edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                        ));

                        $result = curl_exec($curl);

                        curl_close($curl);

                        $result = json_decode($result, true);

                        EdaraCore::logRequestStatic("Update product by code ",$url,json_encode($data),$result);

                        // var_dump($result['status_code']);die();
                        if ($result['status_code'] == 200) {
                                $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                    'wp_product' => $post_id,
                                    'edara_product' => $result['result'],
                                    'status' => "linked"
                                ));
                        }else{
                            $post_id = $_POST['post_ID'];
                            $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$post_id."");
                            if ($dataSelected != NULL)  {
                                $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                                'wp_product' => $post_id,
                                'edara_product' => "0",
                                'status' => $result['error_message']
                                ), array('wp_product' => $post_id));
                            }else{
                                // $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                                //     'wp_product' => $post_id,
                                //     'edara_product' => $result['result'],
                                //     'status' => "linked"
                                //     ));
                            }
                        }
                        }
                }

            }

        }

        return true;
    }

    public static function my_user_register( $user_id ) {
        $baseUrl = EdaraCore::getBaseUrlStatic();

        EdaraCore::logFunctionStatic("my_user_register");
        global $wpdb;

        $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");
        $customers_table = $wpdb->base_prefix.'edara_customers';
        $queryCustomers = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $customers_table ) );
        if ($customers_table == $wpdb->get_var( $queryCustomers )) {
            $customerExsists = $wpdb->get_var("SELECT edara_customer FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id);
            if ($customerExsists) {
                $customerExsistsID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id);

                $customers_selection = $wpdb->get_var("SELECT customers_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");
                $url = $baseUrl . "customers";

                $customer_data = $wpdb->get_var( "SELECT * FROM ".$wpdb->prefix."users WHERE ID = ".$user_id."");

                $data = array('name' => $_POST['user_login'], 'email' =>$_POST['email'],'payment_type' => 'Credit');

                $curl = curl_init();

                curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Authorization: '.$edara_accsess_token,
                    'Content-Type: application/json'
                ),
                ));

                $result = curl_exec($curl);

                curl_close($curl);
                
                $result = json_decode($result, true);
                if ($result['status_code'] == 200) {
                    $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => $result['result'],
                        'status' => "linked"
                    ));     
                }else{
                    $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id."");
                    if ($dataSelected != NULL)  {
                        $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => "0",
                        'status' => $result['error_message']
                        ), array('wp_customer' => $user_id));
                    }else{
                    $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => 0,
                        'status' => $result['error_message']
                        ));
                    }
                }
            }else{
                $customerExsistsID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id);

                $customers_selection = $wpdb->get_var("SELECT customers_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");
                $url = $baseUrl . "customers";

                $customer_data = $wpdb->get_var( "SELECT * FROM ".$wpdb->prefix."users WHERE ID = ".$user_id."");

                $data = array('name' => $_POST['user_login'], 'email' =>$_POST['email'],'payment_type' => 'Credit');

                $curl = curl_init();

                curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Authorization: '.$edara_accsess_token,
                    'Content-Type: application/json'
                ),
                ));

                $result = curl_exec($curl);

                curl_close($curl);
                
                $result = json_decode($result, true);
                if ($result['status_code'] == 200) {
                      $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => $result['result'],
                        'status' => "linked"
                      ));
                }else{
                    $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id."");
                    if ($dataSelected != NULL)  {
                        $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => "0",
                        'status' => $result['error_message']
                      ), array('wp_customer' => $user_id));
                    }else{
                    $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => 0,
                        'status' => $result['error_message']
                      ));
                    }
                }
            }
        }

        return true;
    }

    public static function my_user_update( $user_id, $oldData ) {
        $baseUrl = EdaraCore::getBaseUrlStatic();

        EdaraCore::logFunctionStatic("my_user_update");
        EdaraCore::logFunctionStatic(print_r($oldData,true));
        global $wpdb;
        
        $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");

        $customers_table = $wpdb->base_prefix.'edara_customers';
        $queryCustomers = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $customers_table ) );
        if ($customers_table == $wpdb->get_var( $queryCustomers )) {
            $customerExsists = $wpdb->get_var("SELECT edara_customer FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id);
            if ($customerExsists) {
                $customerExsistsID = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id);
                if ($customerExsists == 0) {
                    
                    $url = $baseUrl . "customers";
                    $nameC = isset($_POST['nickname']) ? $_POST['nickname'] : $_POST['user_login'];
                    $data = array('name' => $nameC, 'email' =>$_POST['email'],'mobile' => $_POST['billing_phone'],'payment_type' => 'Credit');

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS =>json_encode($data),
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: '.$edara_accsess_token,
                        'Content-Type: application/json'
                    ),
                    ));

                    $result = curl_exec($curl);

                    curl_close($curl);

                    $result = json_decode($result, true);

                    EdaraCore::logRequestStatic("Post customer exists == 0 ",$url,json_encode($data),$result);

                    if ($result['status_code'] == 200) {
                        $result = $wpdb->update($wpdb->prefix.'edara_customers', array(
                            'wp_customer' => $user_id,
                            'edara_customer' => $customerExsists,
                            'status' => "linked"
                          ), array('wp_customer' => $user_id));
                    }else{
                        $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id."");
                        if ($dataSelected != NULL)  {
                            $row = $wpdb->update($wpdb->prefix.'edara_customers', array(
                                'wp_customer' => $user_id,
                                'edara_customer' => "0",
                                'status' => $result['error_message']
                            ), array('wp_customer' => $user_id));
                        }else{
                            $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                                'wp_customer' => $user_id,
                                'edara_customer' => 0,
                                'status' => $result['error_message']
                            ));
                        }
                    }

                }else{
                    $url = $baseUrl . "customers";
                    $nameC = isset($_POST['nickname']) ? $_POST['nickname'] : $_POST['user_login'];
                    $data = array('id' => $customerExsists,'name' => $nameC, 'email' =>$_POST['email'],'mobile' => $_POST['billing_phone'],'payment_type' => 'Credit');

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'PUT',
                    CURLOPT_POSTFIELDS =>json_encode($data),
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: '.$edara_accsess_token,
                        'Content-Type: application/json'
                    ),
                    ));

                    $result = curl_exec($curl);

                    curl_close($curl);

                    $result = json_decode($result, true);

                    EdaraCore::logRequestStatic("Put customer exists != 0 ",$url,json_encode($data),$result);

                    if ($result['status_code'] == 200) {
                        $result = $wpdb->update($wpdb->prefix.'edara_customers', array(
                            'status' => "linked"
                        ), array('wp_customer' => $user_id));
                    }else{
                        $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id."");
                        if ($dataSelected != NULL)  {
                            $row = $wpdb->update($wpdb->prefix.'edara_customers', array(
                            'wp_customer' => $user_id,
                            'edara_customer' => "0",
                            'status' => $result['error_message']
                            ), array('wp_customer' => $user_id));
                        }else{
                        $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                            'wp_customer' => $user_id,
                            'edara_customer' => 0,
                            'status' => $result['error_message']
                            ));
                        }
                    }
                }
            }else{
                $url = $baseUrl . "customers";
                $nameC = isset($_POST['nickname']) ? $_POST['nickname'] : $_POST['user_login'];
                $data = array('name' => $nameC, 'email' =>$_POST['email'],'mobile' => $_POST['billing_phone'],'payment_type' => 'Credit');
                
                $curl = curl_init();

                curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>json_encode($data),
                CURLOPT_HTTPHEADER => array(
                    'Authorization: '.$edara_accsess_token,
                    'Content-Type: application/json'
                ),
                ));

                $result = curl_exec($curl);

                curl_close($curl);
                
                $result = json_decode($result, true);

                EdaraCore::logRequestStatic("Post customer if not exists ",$url,json_encode($data),$result);

                if ($result['status_code'] == 200) {
                    $result = $wpdb->update($wpdb->prefix.'edara_customers', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => $result['result'],
                        'status' => "linked"
                    ), array('wp_customer' => $user_id));  
                }else{
                    $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$user_id."");
                    if ($dataSelected != NULL)  {
                        $row = $wpdb->update($wpdb->prefix.'edara_customers', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => "0",
                        'status' => $result['error_message']
                        ), array('wp_customer' => $user_id));
                    }else{
                    $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                        'wp_customer' => $user_id,
                        'edara_customer' => 0,
                        'status' => $result['error_message']
                        ));
                    }
                }
            }
        }

        return true;
    }

    public static function my_orders_hooks_admin($order_id) {
        EdaraCore::logFunctionStatic("my_orders_hooks_admin");
    
        // Ensure the action is only performed for specific post types
        $post_type = get_post_type($order_id);
        if (!did_action('woocommerce_checkout_order_processed') && ($post_type == 'shop_order' || $post_type == 'shop_order_placehold')) {
            $order = wc_get_order($order_id);
    
            // Check if the order object is valid
            if ($order) {
                $user_id = $order->get_user_id();
    
                // Check if the order has a user associated with it
                if ($user_id) {
                    $user_meta = get_user_meta($user_id);
    
                    // Proceed if user meta is found
                    if ($user_meta) {
                        wp_schedule_single_event(time() + 1, 'woocommerce_thankyou', array($order_id));
                        // my_orders_hooks($order_id); // Uncomment this if needed
                    }
                } else {
                    EdaraCore::logFunctionStatic("Order has no associated user (guest checkout).");
                }
            } else {
                EdaraCore::logFunctionStatic("Invalid order ID: " . $order_id);
            }
        }
    }

    public static function my_orders_hooks($order_id) {
        $baseUrl = EdaraCore::getBaseUrlStatic();
        global $wpdb;
        
        // Logging the function call
        EdaraCore::logFunctionStatic("my_orders_hooks " . $order_id);
    
        // General log message
        EdaraCore::logGeneralStatic("Redirecting to update_one_request");
    
        // Schedule a single event to run after 1 second
        wp_schedule_single_event(time() + 1, 'sbr_hooks_save_post', array($order_id, get_post($order_id), get_post($order_id)));
    
        // Return immediately, preventing any code below this from executing
        return;
    }

    public static function trashOrder($order_id){
      EdaraCore::logFunctionStatic("Trash order " . $order_id);
    }

    public static function my_update_order_one_request_delay($order_id, $post, $update){

        wp_schedule_single_event( time()+10 , 'sbr_hooks_save_post', array($order_id,$post,$update));
    }
    
    public static function my_update_order_one_request($order_id, $post, $update) {

        $baseUrl = EdaraCore::getBaseUrlStatic();
    
        EdaraCore::logFunctionStatic("my_update_order_one_request");

        EdaraCore::logGeneralStatic(print_r($post, true));
    
        if ("shop_order" == $post->post_type) {

            global $wpdb;
            $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
    
            $orders_table = $wpdb->base_prefix . 'edara_orders';
            $queryOrders = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($orders_table));
    
            EdaraCore::logGeneralStatic("Access token= " . $edara_accsess_token . " , Order id= " . $order_id);
    
            if ($orders_table == $wpdb->get_var($queryOrders)) {
                $order = wc_get_order($order_id);
                $orders_selection = $wpdb->get_var("SELECT orders_selection FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
                $from_date = $wpdb->get_var("SELECT from_date FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
    
                $order_code = $wpdb->get_var("SELECT edara_order FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                $order_status = $wpdb->get_var("SELECT status FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
    
                EdaraCore::logGeneralStatic("Sql= " . "SELECT edara_order FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                EdaraCore::logGeneralStatic("Order code= " . $order_code);
                EdaraCore::logGeneralStatic("Order status= " . $order_status);

                if ($order_status == '404') {
                    EdaraCore::logGeneralStatic("Order deleted from edara");
                    return;
                }
    
                $url = $baseUrl . "salesOrders/UpdateByCode/" . $order_code;
    
                $availableOrdersStatus = $wpdb->get_var("SELECT orders_status FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
    
                $confirmedStatus = 0;
                if ($availableOrdersStatus) {
                    $orderStatus = $order->get_status();
    
                    $arr = str_replace('\\', '', $availableOrdersStatus);
                    foreach (json_decode($arr) as $st) {
                        if ($st == $orderStatus || $st == 'any') {
                            $confirmedStatus = 1;
                        }
                    }
                } else {
                    $confirmedStatus = 1;
                }
    
                if ($confirmedStatus == 0) {
                    EdaraCore::logGeneralStatic("Not confirmed status");
                    return;
                }
    
                $orderData = $order->get_data();
    
                $countryCode = $orderData['billing']['country'];
                $stateCode = $orderData['billing']['state'];
                $streetName = $orderData['billing']['address_1'] . ", " . $orderData['billing']['city'];
                $stateName = WC()->countries->get_states($countryCode)[$stateCode];
                $countryName = WC()->countries->countries[$order->get_shipping_country()];
                $countryId = 0;
                $cityId = 0;
    
                $first_order_note = '';
                $order_notes = wc_get_order_notes(array('order_id' => $order_id));
                if (!empty($order_notes)) {
                    $first_order_note = $order_notes[0]->content;
                }
    
                $awb = "";
                foreach ($orderData['meta_data'] as $metaData) {
                    $obj = $metaData->get_data();
                    if ($obj['key'] == 'AWB') {
                        $awb = $obj['value'];
                    }
                }
    
                if ($stateName && $countryName) {
                    $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_cities (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    city_name varchar(255),
                    country_id int(11),
                    city_id int(11),
                    PRIMARY KEY (id)
                    ) $charset_collate;";
                    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                    dbDelta($sql);
    
                    $countryIdTry = $wpdb->get_var("SELECT country_id FROM " . $wpdb->prefix . "edara_cities WHERE city_name = '" . $stateName . "'");
                    $cityIdTry = $wpdb->get_var("SELECT city_id FROM " . $wpdb->prefix . "edara_cities WHERE city_name = '" . $stateName . "'");
    
                    if ($countryIdTry && $cityIdTry) {
                        $countryId = $countryIdTry;
                        $cityId = $cityIdTry;
                    } else {
                        $findCityUrl = $baseUrl . "cities/FindByName/" . $stateName;
                        $options = array(
                            'http' => array(
                                'header'  => "Authorization:" . $edara_accsess_token . "",
                                'method'  => 'GET',
                            )
                        );
                        $context  = stream_context_create($options);
                        $cityResult = file_get_contents($findCityUrl, false, $context);
    
                        $cityResult = json_decode($cityResult, true);
                        EdaraCore::logRequestStatic("Find city by name", $findCityUrl, json_encode($options), $cityResult);
                        if ($cityResult['status_code'] == 200) {
                            $countryId = $cityResult['result']['country_id'];
                            $cityId = $cityResult['result']['id'];
    
                            $insertionResult = $wpdb->insert($wpdb->prefix . 'edara_cities', array(
                                'city_name' => $stateName,
                                'country_id' => $countryId,
                                'city_id' => $cityId
                            ));
                        } else {
                            $findCountryUrl = $baseUrl . "countries/FindByName/" . $countryName;
                            $options = array(
                                'http' => array(
                                    'header'  => "Authorization:" . $edara_accsess_token . "",
                                    'method'  => 'GET',
                                )
                            );
                            $context  = stream_context_create($options);
                            $countryResult = file_get_contents($findCountryUrl, false, $context);
    
                            $countryResult = json_decode($countryResult, true);
                            EdaraCore::logRequestStatic("Find country by name", $findCountryUrl, json_encode($options), $countryResult);
                            if ($countryResult['status_code'] == 200) {
                                $countryId = $countryResult['result']['id'];
    
                                $addCityUrl = $baseUrl . "cities";
                                $cityData = array('name' => $stateName, 'country_id' => $countryId);
    
                                $curl = curl_init();
    
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => $addCityUrl,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode($cityData),
                                    CURLOPT_HTTPHEADER => array(
                                        'Authorization: ' . $edara_accsess_token,
                                        'Content-Type: application/json'
                                    ),
                                ));
    
                                $addCityResult = curl_exec($curl);
    
                                curl_close($curl);
    
                                $addCityResult = json_decode($addCityResult, true);
    
                                EdaraCore::logRequestStatic("Post city when not exist", $addCityUrl, json_encode($cityData), $addCityResult);
    
                                if ($addCityResult['status_code'] == 200) {
                                    $cityId = $addCityResult['result'];
                                }
                            } else {
                                $addCountryUrl = $baseUrl . "countries";
                                $countryData = array('name' => $countryName);
    
                                $curl = curl_init();
    
                                curl_setopt_array($curl, array(
                                    CURLOPT_URL => $addCountryUrl,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_ENCODING => '',
                                    CURLOPT_MAXREDIRS => 10,
                                    CURLOPT_TIMEOUT => 0,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                    CURLOPT_POSTFIELDS => json_encode($countryData),
                                    CURLOPT_HTTPHEADER => array(
                                        'Authorization: ' . $edara_accsess_token,
                                        'Content-Type: application/json'
                                    ),
                                ));
    
                                $addCountryResult = curl_exec($curl);
    
                                curl_close($curl);
    
                                $addCountryResult = json_decode($addCountryResult, true);
    
                                EdaraCore::logRequestStatic("Post country when not exist", $addCountryUrl, json_encode($countryData), $addCountryResult);
    
                                if ($addCountryResult['status_code'] == 200) {
                                    $countryId = $addCountryResult['result'];
    
                                    $addCityUrl = $baseUrl . "cities";
                                    $cityData = array('name' => $stateName, 'country_id' => $countryId);
    
                                    $curl = curl_init();
    
                                    curl_setopt_array($curl, array(
                                        CURLOPT_URL => $addCityUrl,
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_ENCODING => '',
                                        CURLOPT_MAXREDIRS => 10,
                                        CURLOPT_TIMEOUT => 0,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                        CURLOPT_CUSTOMREQUEST => 'POST',
                                        CURLOPT_POSTFIELDS => json_encode($cityData),
                                        CURLOPT_HTTPHEADER => array(
                                            'Authorization: ' . $edara_accsess_token,
                                            'Content-Type: application/json'
                                        ),
                                    ));
    
                                    $addCityResult = curl_exec($curl);
    
                                    curl_close($curl);
    
                                    $addCityResult = json_decode($addCityResult, true);
    
                                    EdaraCore::logRequestStatic("Post city when not exist", $addCityUrl, json_encode($cityData), $addCityResult);
    
                                    if ($addCityResult['status_code'] == 200) {
                                        $cityId = $addCityResult['result'];
                                    }
                                }
                            }
                        }
                    }
                }
                EdaraCore::logGeneralStatic("Order data state => " . $stateName);
    
                // Check taxes
                $orderTaxRate = 0;
                $orderTaxTotal = 0;
                $edaraTaxId = 0;
    
                foreach ($order->get_items('tax') as $taxItem) {
                    $orderTaxRate = $taxItem->get_data()['rate_percent'];
                    $orderTaxTotal = $taxItem->get_data()['tax_total'];
                }
    
                if ($orderTaxRate) {
                    $edaraTaxIdTry = $wpdb->get_var("SELECT edara_id FROM " . $wpdb->prefix . "edara_taxes WHERE percent = '" . $orderTaxRate . "'");
    
                    if ($edaraTaxIdTry) {
                        $edaraTaxId = $edaraTaxIdTry;
                    }
                }
    
                // Check currency
                $edaraCurrencyId = 0;
                $currencyCode = $order->get_currency();
                $currencyIdTry = $wpdb->get_var("SELECT edara_id FROM " . $wpdb->prefix . "edara_currencies WHERE code = '" . $currencyCode . "'");
                if ($currencyIdTry) {
                    $edaraCurrencyId = $currencyIdTry;
                }
    
                $orderMeta = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "postmeta WHERE meta_key = '_customer_user' AND post_id =" . $order->get_id());
    
                $orderItemIDs = $wpdb->get_results("SELECT order_item_id FROM " . $wpdb->prefix . "woocommerce_order_items WHERE order_item_type = 'line_item' AND order_id = '" . $order->get_id() . "'");
    
                $customerMetaID = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "postmeta WHERE meta_key = '_customer_user' AND post_id = '" . $order->get_id() . "'");
    
                $customerID = $wpdb->get_var("SELECT edara_customer FROM " . $wpdb->prefix . "edara_customers WHERE wp_customer = " . $customerMetaID);
    
                $customerBillingEmail = $order->get_billing_email();
    
                $customersMapKey = "email";
                $customersMapKeyCheck = $wpdb->get_var("SELECT customers_key FROM " . $wpdb->prefix . "edara_config WHERE id = '1'");
                if ($customersMapKeyCheck) {
                    $customersMapKey = $customersMapKeyCheck;
                }
    
                if ($customersMapKey == "email") {
                    if (empty($customerBillingEmail)) {
                        EdaraCore::logGeneralStatic("Email empty");
                        return;
                    }
                } else {
                    if (empty($orderData['billing']['phone'])) {
                        EdaraCore::logGeneralStatic("Phone empty");
                        return;
                    }
                }
    
                $findCustomerUrl = $baseUrl . "customers/FindByEmail/" . $customerBillingEmail;
                if ($customersMapKey == "phone") {
                    $findCustomerUrl = $baseUrl . "customers/FindByMobile/" . $orderData['billing']['phone'];
                }
                $options = array(
                    'http' => array(
                        'header'  => "Authorization:" . $edara_accsess_token . "",
                        'method'  => 'GET',
                    )
                );
                $context  = stream_context_create($options);
                $result = file_get_contents($findCustomerUrl, false, $context);
    
                $result = json_decode($result, true);
    
                EdaraCore::logRequestStatic("Find customer by billing email ", $findCustomerUrl, $options, $result);
    
                if ($result['status_code'] == 200) {
                    $customerID = $result['result']['id'];
                } else {
                    $customer_data_user_login = $orderData['billing']['first_name'] . " " . $orderData['billing']['last_name'];
                    $customer_data_user_email = $orderData['billing']['email'];
                    $userPhone = $orderData['billing']['phone'];
    
                    if ($countryId && $cityId) {
                        $addressesArray = array();
                        $firstAddressArray = array();
    
                        $firstAddressArray['country_id'] = $countryId;
                        $firstAddressArray['city_id'] = $cityId;
                        $firstAddressArray['street'] = $streetName;
    
                        $addressesArray[] = $firstAddressArray;
                        $customerData = array('name' => $customer_data_user_login, 'email' => $customer_data_user_email, 'payment_type' => 'Credit', 'mobile' => $userPhone, 'customer_addresses' => $addressesArray);
                    } else {
                        $customerData = array('name' => $customer_data_user_login, 'email' => $customer_data_user_email, 'payment_type' => 'Credit', 'mobile' => $userPhone);
                    }
    
                    $customerUrl = $baseUrl . "customers";
    
                    $curl = curl_init();
    
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $customerUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => json_encode($customerData),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: ' . $edara_accsess_token,
                            'Content-Type: application/json'
                        ),
                    ));
    
                    $customerResult = curl_exec($curl);
    
                    curl_close($curl);
    
                    $customerResult = json_decode($customerResult, true);
                    EdaraCore::logRequestStatic("Post if customerId equals null", $customerUrl, json_encode($customerData), $customerResult);
                    if ($customerResult['status_code'] == 200) {
                        $customerID = $customerResult['result'];
                    } else {
                        return;
                    }
                }
    
                $total = 0;
                $saleOrderLine = [];
    
                if (count($orderItemIDs) == 0) {
                    EdaraCore::logGeneralStatic("No orderItemIDs ");
                    return;
                }
    
                if ($order_code == '0') {
                    EdaraCore::logGeneralStatic("Order code is zero ");
                    return;
                }
    
                foreach ($orderItemIDs as $orderItemID) {
                    $sub_total = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_line_total' AND order_item_id = '" . $orderItemID->order_item_id . "'");
    
                    $quantity = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_qty' AND order_item_id = '" . $orderItemID->order_item_id . "'");
    
                    $productMetaID = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND order_item_id = '" . $orderItemID->order_item_id . "'");
                    $productObj = wc_get_product($productMetaID);
                    $productSku = $productObj->get_sku();
    
                    $total_tax = 0;
                    // Check if product prices include tax
                    $prices_include_tax = wc_prices_include_tax();
                    $itemPrice = $sub_total / $quantity;
    
                    if ($prices_include_tax) {
                        $subtotal = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_line_subtotal' AND order_item_id = '" . $orderItemID->order_item_id . "'");
                        $subtotal_tax = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_line_subtotal_tax' AND order_item_id = '" . $orderItemID->order_item_id . "'");
                        $per_item_price_excl_tax = $subtotal / $quantity;
                        $per_item_tax_amount = $subtotal_tax / $quantity;
                        $itemPrice = $per_item_price_excl_tax + $per_item_tax_amount;
                    }
    
                    $productID = $wpdb->get_var("SELECT edara_product FROM " . $wpdb->prefix . "edara_products WHERE wp_product = '" . $productMetaID . "'");
    
                    EdaraCore::logGeneralStatic("WooId = " . $productMetaID . " And edaraId = " . $productID);
    
                    if (empty($productID) || $productID == NULL) {
                        $tableName = $wpdb->prefix . "icl_translations";
                        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
                        if ($wpdb->get_var($query) == $tableName) {
                            $englishId = $wpdb->get_var("SELECT trans2.element_id FROM wp_icl_translations AS trans1 INNER JOIN wp_icl_translations AS trans2 ON trans2.trid = trans1.trid WHERE trans1.element_id = " . $productMetaID . " AND trans2.source_language_code IS NULL");
                            $productID = $wpdb->get_var("SELECT edara_product FROM " . $wpdb->prefix . "edara_products WHERE wp_product = '" . $englishId . "'");
                            EdaraCore::logGeneralStatic("English id = " . $englishId . " And edaraId = " . $productID);
                        }
                    }
    
                    if (empty($productID) || $productID == NULL) {
                        $variationId = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_variation_id' AND order_item_id = '" . $orderItemID->order_item_id . "'");
                        if ($variationId != '0') {
                            $productID = $wpdb->get_var("SELECT edara_product FROM " . $wpdb->prefix . "edara_products WHERE wp_product = '" . $variationId . "'");
                        }
                        EdaraCore::logGeneralStatic("variation id = " . $variationId . " And new edaraId = " . $productID);
    
                        if (empty($productID) || $productID == NULL) {
                            $tableName = $wpdb->prefix . "icl_translations";
                            $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
                            if ($wpdb->get_var($query) == $tableName) {
                                $englishId = $wpdb->get_var("SELECT trans2.element_id FROM wp_icl_translations AS trans1 INNER JOIN wp_icl_translations AS trans2 ON trans2.trid = trans1.trid WHERE trans1.element_id = " . $productMetaID . " AND trans2.source_language_code IS NULL");
                                $productID = $wpdb->get_var("SELECT edara_product FROM " . $wpdb->prefix . "edara_products WHERE wp_product = '" . $englishId . "'");
                                EdaraCore::logGeneralStatic("English id = " . $englishId . " And edaraId = " . $productID);
                            }
                        }
                    }
    
                    // New logic to check if products exist in Edara and set their status to "linked"
                    if (empty($productID) || $productID == NULL) {
                        $findProductUrl = $baseUrl . "stockItems/Find?sku=" . urlencode($productSku);
                    
                        $ch = curl_init($findProductUrl);
                        $headers = array(
                            "Content-Type: application/json",
                            "Authorization:" . $edara_accsess_token . "",
                        );
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $findProductResult = curl_exec($ch);
                        curl_close($ch);
                        $findProductResult = json_decode($findProductResult, true);
                    
                        if ($findProductResult['status_code'] == 200) {
                            $productID = $findProductResult['result']['id'];
                            $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $productMetaID);
                            if ($dataSelected != NULL) {
                                $wpdb->update($wpdb->prefix . 'edara_products', array(
                                    'status' => "linked",
                                    'edara_product' => $productID
                                ), array('wp_product' => $productMetaID));
                            } else {
                                $wpdb->insert($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $productMetaID,
                                    'edara_product' => $productID,
                                    'status' => "linked"
                                ));
                            }
                        } else {
                            return;
                        }
                    }
    
                    if ($productID) {
                        if ($edaraTaxId) {
                            array_push($saleOrderLine, array('quantity' => $quantity, 'price' => $itemPrice, 'stock_item_id' => $productID, 'tax_id' => $edaraTaxId));
                        } else {
                            array_push($saleOrderLine, array('quantity' => $quantity, 'price' => $itemPrice, 'stock_item_id' => $productID));
                        }
                        $total += (double)$sub_total;
                        $total_tax += (double)$subtotal_tax;
                    } else {
                        echo "No edara product found for SKU: " . $productSku;
                        return;
                    }
                }
    
                // Handle shipping
                $shipping_total = $order->get_shipping_total();
                $shipping_tax = $order->get_shipping_tax();
                $serviceId = $wpdb->get_var("SELECT service_item FROM " . $wpdb->prefix . "edara_config WHERE id = '1'");
    
                if ($serviceId != NULL && $shipping_total != '0') {
                    $total += (double)$shipping_total;
                    if ($edaraTaxId && $shipping_tax != '0') {
                        $total += (double)$shipping_tax;
                        array_push($saleOrderLine, array('quantity' => 1, 'price' => (double)$shipping_total + (double)$shipping_tax, 'service_item_id' => $serviceId, 'tax_id' => $edaraTaxId));
                    } else {
                        array_push($saleOrderLine, array('quantity' => 1, 'price' => (double)$shipping_total, 'service_item_id' => $serviceId));
                    }
                }
    
                $saleOrderInstallmentsLine = [];
    
                if (count($saleOrderLine) > 0) {
                    $total_with_tax = $total + $total_tax;  // Calculate total including tax
                    array_push($saleOrderInstallmentsLine, array('due_date' => date("y-M-d H:i:s"), 'amount' => $total_with_tax, 'days_limit' => 0));
                }
    
                $warehouseID = $wpdb->get_var("SELECT warehouses_selection FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
                $storeID = $wpdb->get_var("SELECT stores_selection FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
                if ($warehouseID == -1) {
                    $warehouseID = null;
                }
    
                if ($storeID == -1) {
                    $storeID = null;
                }
    
                if (count($saleOrderLine) >= 0 && $customerID != NULL) {
                    $orderDate = $order->get_date_created();
    
                    $orderStatus = $order->get_status();
    
                    if ($orderStatus == 'processing') {
                        $orderStatus = 'pending';
                    }
    
                    if ($edaraTaxId) {
                        $total += $orderTaxTotal;
                        $data = array(
                            'paper_number' => $order->get_data()['number'],
                            'customer_id' => $customerID,
                            'order_status' => $orderStatus,
                            'document_date' => $orderDate->date("Y-m-d H:i:s"),
                            'sub_total' => $total,
                            'total_item_discounts' => 0.0,
                            'taxable' => true,
                            'tax' => $orderTaxTotal,
                            'warehouse_id' => $warehouseID,
                            'salesstore_id' => $storeID,
                            'salesOrder_details' => $saleOrderLine,
                            'salesOrder_installments' => $saleOrderInstallmentsLine,
                            'notes' => $first_order_note,
                        );
                    } else {
                        $data = array(
                            'paper_number' => $order->get_data()['number'],
                            'customer_id' => $customerID,
                            'order_status' => $orderStatus,
                            'document_date' => $orderDate->date("Y-m-d H:i:s"),
                            'sub_total' => $total,
                            'total_item_discounts' => 0.0,
                            'taxable' => true,
                            'tax' => 0,
                            'warehouse_id' => $warehouseID,
                            'salesstore_id' => $storeID,
                            'salesOrder_details' => $saleOrderLine,
                            'salesOrder_installments' => $saleOrderInstallmentsLine,
                            'notes' => $first_order_note,
                        );
                    }
    
                    if ($edaraCurrencyId) {
                        $data['currency_id'] = $edaraCurrencyId;
                    }
    
                    EdaraCore::logGeneralStatic("Order code = " . $order_code);
    
                    if ($order_code == null || empty($order_code)) {
                        $orderDate = new \DateTime($orderDate->date("Y-m-d H:i:s"));
                        $setupDate = $wpdb->get_var("SELECT setup_date FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
    
                        if ($setupDate == null) {
                            EdaraCore::logGeneralStatic("No setup date");
                            return;
                        }
                        $setupDate = new \DateTime($setupDate);
    
                        if ($orderDate < $setupDate) {
                            EdaraCore::logGeneralStatic("Order before setup");
                            return;
                        }
    
                        $newOrderUrl = $baseUrl . "salesOrders";
    
                        $ch = curl_init($newOrderUrl);
                        $payload = json_encode($data);
                        $headers = array(
                            "Content-Type: application/json",
                            "Authorization:" . $edara_accsess_token . "",
                        );
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $result = json_decode($result, true);
                        EdaraCore::logRequestStatic("Post new order while update", $url, $payload, $result);
                    } else {
                        $ch = curl_init($url);
                        $payload = json_encode($data);
                        $headers = array(
                            "Content-Type: application/json",
                            "Authorization:" . $edara_accsess_token . "",
                        );
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $result = curl_exec($ch);
                        curl_close($ch);
    
                        $result = json_decode($result, true);
    
                        EdaraCore::logRequestStatic("Update order by code ", $url, $payload, $result);
                    }
    
                    if ($result['status_code'] == 200) {
                        $dataSelected = $wpdb->get_var("SELECT edara_order FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                        EdaraCore::logGeneralStatic("Data selected: " . $dataSelected);
                        if ($dataSelected) {
                            if ($dataSelected == 0) {
                                $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                    'status' => 'linked'
                                ), array('wp_order' => $order_id));
                                EdaraCore::logGeneralStatic("update row " . '1');
                            } else {
                                $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                    'status' => "linked"
                                ), array('wp_order' => $order_id));
                                EdaraCore::logGeneralStatic("update row " . '2');
                            }
                        } else {
                            $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                'wp_order' => $order_id,
                                'edara_order' => $result['result'],
                                'status' => "linked"
                            ));
                        }

                        // Send order details to Google Form after setting status to 'linked'
                        self::sendOrderDetailsToGoogleForm($order, "linked");
    
                        if ($order->get_status() == 'cancelled') {
                            $cancelUrl = $baseUrl . "salesOrders/CancelByCode/" . $order_code;
    
                            $ch = curl_init($cancelUrl);
                            $headers = array(
                                "Content-Type: application/json",
                                "Authorization:" . $edara_accsess_token . "",
                            );
                            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                            curl_setopt($ch, CURLOPT_POSTFIELDS, "");
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            $result = curl_exec($ch);
                            curl_close($ch);
    
                            $result = json_decode($result, true);
    
                            EdaraCore::logRequestStatic("Cancel order by code ", $cancelUrl, "", $result);
                        }
                    } elseif ($result['status_code'] == 500 && $result['error_message'] == "the specified Documents_Warehouse not exist." || $result['status_code'] == 400 && $result['error_message'] == "Bad Request. DupplicatedCode: Exception of type 'Edara.EdaraBusinessSuite.CommonBusinessLogicLayer.BusinessLogicException' was thrown.") {
                        $data = array('paper_number' => $order->get_data()['number'], 'customer_id' => $customerID, 'order_status' => $order->get_status(), 'document_date' => $orderDate->date("Y-m-d H:i:s"), 'sub_total' => $total, 'total_item_discounts' => 0.0, 'taxable' => true, 'tax' => 0, 'salesstore_id' => $warehouseID, 'salesOrder_details' => $saleOrderLine);
    
                        $curl = curl_init();
    
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'PUT',
                            CURLOPT_POSTFIELDS => json_encode($data),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: ' . $edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                        ));
    
                        $result = curl_exec($curl);
    
                        curl_close($curl);
    
                        $result = json_decode($result, true);
    
                        EdaraCore::logRequestStatic("Update order by code if error ", $url, json_encode($data), $result);
    
                        if ($result['status_code'] == 200) {
                            $dataSelected = $wpdb->get_var("SELECT edara_order FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                            if ($dataSelected) {
                                if ($dataSelected == 0) {
                                    $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                        'status' => $result['error_message']
                                    ), array('wp_order' => $order_id));
                                    EdaraCore::logGeneralStatic("update row " . '3');
                                } else {
                                    $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                        'status' => "linked"
                                    ), array('wp_order' => $order_id));
                                    EdaraCore::logGeneralStatic("update row " . '4');
                                }
                            } else {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                    'wp_order' => $order_id,
                                    'edara_order' => $result['result'],
                                    'status' => "linked"
                                ));
                            }

                            // Send order details to Google Form after setting status to 'linked'
                            self::sendOrderDetailsToGoogleForm($order, "linked");

                        } else {
                            $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                            if ($dataSelected != NULL) {
                                $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                    'wp_order' => $order_id,
                                    'status' => $result['error_message']
                                ), array('wp_order' => $order_id));
                            } else {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                    'wp_order' => $order_id,
                                    'edara_order' => 0,
                                    'status' => $result['error_message']
                                ));
                            }
                        }
                    } elseif ($result['status_code'] == 500) {
                        $data = array('customer_id' => $customerID, 'order_status' => $order->get_status(), 'document_date' => $orderDate->date("Y-m-d H:i:s"), 'sub_total' => $total, 'total_item_discounts' => 0.0, 'taxable' => true, 'tax' => 0, 'salesstore_id' => $warehouseID, 'salesOrder_details' => $saleOrderLine);
    
                        $curl = curl_init();
    
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'PUT',
                            CURLOPT_POSTFIELDS => json_encode($data),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: ' . $edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                        ));
    
                        $result = curl_exec($curl);
    
                        curl_close($curl);
    
                        $result = json_decode($result, true);
    
                        EdaraCore::logRequestStatic("Else ", $url, json_encode($data), $result);
    
                        if ($result['status_code'] == 200) {
                            $charset_collate = $wpdb->get_charset_collate();
                            $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_orders (
                            id bigint(50) NOT NULL AUTO_INCREMENT,
                            wp_order varchar(255),
                            edara_order varchar(255),
                            status varchar(255),
                            PRIMARY KEY (id)
                            ) $charset_collate;";
                            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                            dbDelta($sql);
                            $is_error = empty($wpdb->last_error);
                            if ($is_error) {
                                $dataSelected = $wpdb->get_var("SELECT edara_order FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                                if ($dataSelected) {
                                    if ($dataSelected == 0) {
                                        $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                            'status' => $result['error_message']
                                        ), array('wp_order' => $order_id));
                                        EdaraCore::logGeneralStatic("update row " . '5');
                                    } else {
                                        $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                            'status' => "linked"
                                        ), array('wp_order' => $order_id));
                                        EdaraCore::logGeneralStatic("update row " . '6');
                                    }
                                } else {
                                    $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                        'wp_order' => $order_id,
                                        'edara_order' => $result['result'],
                                        'status' => "linked"
                                    ));
                                }
                            }

                            // Send order details to Google Form after setting status to 'linked'
                            self::sendOrderDetailsToGoogleForm($order, "linked");

                        } else {
                            $charset_collate = $wpdb->get_charset_collate();
                            $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_orders (
                            id bigint(50) NOT NULL AUTO_INCREMENT,
                            wp_order varchar(255),
                            edara_order varchar(255),
                            status varchar(255),
                            PRIMARY KEY (id)
                            ) $charset_collate;";
                            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                            dbDelta($sql);
                            $is_error = empty($wpdb->last_error);
                            if ($is_error) {
                                $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                                if ($dataSelected != NULL) {
                                    $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                        'wp_order' => $order_id,
                                        'status' => $result['error_message']
                                    ), array('wp_order' => $order_id));
                                    EdaraCore::logGeneralStatic("update row " . '7');
                                } else {
                                    $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                        'wp_order' => $order_id,
                                        'edara_order' => 0,
                                        'status' => $result['error_message']
                                    ));
                                }
                            }
                        }
                    } else {
                        if ($result['status_code'] == 404) {
                            $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                'status' => "404"
                            ), array('wp_order' => $order_id));
                            EdaraCore::logGeneralStatic("Error order id not found");
                            return;
                        }
    
                        $charset_collate = $wpdb->get_charset_collate();
                        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_orders (
                        id bigint(50) NOT NULL AUTO_INCREMENT,
                        wp_order varchar(255),
                        edara_order varchar(255),
                        status varchar(255),
                        PRIMARY KEY (id)
                        ) $charset_collate;";
                        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                        dbDelta($sql);
                        $is_error = empty($wpdb->last_error);
                        if ($is_error) {
                            $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id);
                            if ($dataSelected != NULL) {
                                $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                    'wp_order' => $order_id,
                                    'status' => 'linked'
                                ), array('wp_order' => $order_id));
                                EdaraCore::logGeneralStatic("update row " . '8');
                                
                                // Send order details to Google Form after setting status to 'linked'
                                self::sendOrderDetailsToGoogleForm($order, "linked");

                            } else {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                    'wp_order' => $order_id,
                                    'edara_order' => 0,
                                    'status' => $result['error_message']
                                ));
                            }
                        }
                    }
                }
            }
    
            return true;
        }
    
        if ("product_variation" == $post->post_type) {
            if (!empty($post->post_excerpt)) {
                global $wpdb;
                $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
                $wpId = $post->ID;
                EdaraCore::logGeneralStatic("WpId = " . $wpId);
                $productExsists = $wpdb->get_var("SELECT edara_product FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $wpId);
                EdaraCore::logGeneralStatic("productExsists = " . $productExsists);
                if ($productExsists || $productExsists == '0') {
                    $productExsistsID = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $wpId);
                    EdaraCore::logGeneralStatic("productExsistsId = " . $productExsistsID);
                    if ($productExsistsID == 0) {
                        $skuProduct = get_post_meta($wpId, '_sku', true);
                        $price = get_post_meta($wpId, '_price', true);
                        $url = $baseUrl . "stockItems";
                        $data = array('description' => $post->post_title, 'sku' => $skuProduct, 'price' => $price, 'external_id' => $wpId);
    
                        $curl = curl_init();
    
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => json_encode($data),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: ' . $edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                        ));
    
                        $result = curl_exec($curl);
    
                        curl_close($curl);
    
                        $result = json_decode($result, true);
    
                        EdaraCore::logRequestStatic("Post stock item ", $url, json_encode($data), $result);
    
                        if ($result['status_code'] == 200) {
                            $result = $wpdb->update($wpdb->prefix . 'edara_products', array(
                                'edara_product' => $result['result'],
                                'status' => "linked"
                            ), array('id' => $productExsistsID));
                        } else {
                            $post_id = $wpId;
                            $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $post_id . "");
                            if ($dataSelected != NULL) {
                                $row = $wpdb->update($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $post_id,
                                    'status' => $result['error_message']
                                ), array('wp_product' => $post_id));
                            } else {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $post_id,
                                    'status' => $result['error_message']
                                ));
                            }
                        }
                    } else {
                        $getCodeUrl = $baseUrl . "stockItems/" . $productExsists;
                        $productCode = "0";
                        $options = array(
                            'http' => array(
                                'header'  => "Authorization:" . $edara_accsess_token . "",
                                'method'  => 'GET',
                            )
                        );
                        $context  = stream_context_create($options);
                        $result = file_get_contents($getCodeUrl, false, $context);
    
                        EdaraCore::logRequestStatic("Get product by id ", $getCodeUrl, $options, $result);
    
                        $result = json_decode($result, true);
    
                        $edara_other_lang_description = "";
                        $edara_code = "";
                        $edara_sku = "";
                        $edara_purchase_price = 0.0;
                        $edara_dealer_price = 0.0;
                        $edara_supper_dealer_price = 0.0;
                        $edara_minimum_price = 0.0;
                        $edara_part_number = "";
                        $edara_tax_rate = 0.0;
                        $edara_classification_id = null;
                        $edara_brand_id = 0;
                        $edara_warranty = 0.0;
                        $edara_dynamic_properties_info = array();
                        $edara_unitofmeasure_chain_id = null;
                        $edara_default_unitofmeasure_id = null;
                        $edara_unitofmeasures_info = array();
                        $edara_sales_price_discount = 0.0;
                        $edara_sales_price_discount_type = 0;
                        $edara_sales_price_discount_is_limited = false;
                        $edara_sales_price_discount_date_from = null;
                        $edara_sales_price_discount_date_to = null;
                        $edara_dealer_price_discount = 0.0;
                        $edara_dealer_price_discount_type = 0;
                        $edara_dealer_price_discount_is_limited = false;
                        $edara_dealer_price_discount_date_from = null;
                        $edara_dealer_price_discount_date_to = null;
                        $edara_super_dealer_price_discount = 0.0;
                        $edara_super_dealer_price_discount_type = 0;
                        $edara_super_dealer_price_discount_is_limited = false;
                        $edara_super_dealer_price_discount_date_from = null;
                        $edara_super_dealer_price_discount_date_to = null;
                        $edara_weight = 0.0;
                        $edara_data_sheet = "";
                        $edara_note = "";
    
                        if ($result['status_code'] == '200') {
                            $edara_other_lang_description = $result['result']['other_lang_description'];
                            $edara_code = $result['result']['code'];
                            $edara_sku = $result['result']['sku'];
                            $edara_purchase_price = $result['result']['purchase_price'];
                            $edara_dealer_price = $result['result']['dealer_price'];
                            $edara_supper_dealer_price = $result['result']['supper_dealer_price'];
                            $edara_minimum_price = $result['result']['minimum_price'];
                            $edara_part_number = $result['result']['part_number'];
                            $edara_tax_rate = $result['result']['tax_rate'];
                            $edara_classification_id = $result['result']['classification_id'];
                            $edara_brand_id = $result['result']['brand_id'];
                            $edara_warranty = $result['result']['warranty'];
                            $edara_dynamic_properties_info = $result['result']['dynamic_properties_info'];
                            $edara_unitofmeasure_chain_id = $result['result']['unitofmeasure_chain_id'];
                            $edara_default_unitofmeasure_id = $result['result']['default_unitofmeasure_id'];
                            $edara_unitofmeasures_info = $result['result']['unitofmeasures_info'];
                            $edara_sales_price_discount = $result['result']['sales_price_discount'];
                            $edara_sales_price_discount_type = $result['result']['sales_price_discount_type'];
                            $edara_sales_price_discount_is_limited = $result['result']['sales_price_discount_is_limited'];
                            $edara_sales_price_discount_date_from = $result['result']['sales_price_discount_date_from'];
                            $edara_sales_price_discount_date_to = $result['result']['sales_price_discount_date_to'];
                            $edara_dealer_price_discount = $result['result']['dealer_price_discount'];
                            $edara_dealer_price_discount_type = $result['result']['dealer_price_discount_type'];
                            $edara_dealer_price_discount_is_limited = $result['result']['dealer_price_discount_is_limited'];
                            $edara_dealer_price_discount_date_from = $result['result']['dealer_price_discount_date_from'];
                            $edara_dealer_price_discount_date_to = $result['result']['dealer_price_discount_date_to'];
                            $edara_super_dealer_price_discount = $result['result']['super_dealer_price_discount'];
                            $edara_super_dealer_price_discount_type = $result['result']['super_dealer_price_discount_type'];
                            $edara_super_dealer_price_discount_is_limited = $result['result']['super_dealer_price_discount_is_limited'];
                            $edara_super_dealer_price_discount_date_from = $result['result']['super_dealer_price_discount_date_from'];
                            $edara_super_dealer_price_discount_date_to = $result['result']['super_dealer_price_discount_date_to'];
                            $edara_weight = $result['result']['weight'];
                            $edara_data_sheet = $result['result']['data_sheet'];
                            $edara_note = $result['result']['note'];
                        } else {
                            return;
                        }
    
                        $product = $wpdb->get_var("SELECT menu_order FROM " . $wpdb->prefix . "posts WHERE ID = " . $wpId . " AND post_type = 'product_variation'");
                        $url = $baseUrl . "stockItems";
                        $skuProduct = get_post_meta($wpId, '_sku', true);
                        $price = get_post_meta($wpId, '_regular_price', true);
    
                        $sale_price = $wpdb->get_var("SELECT sale_price FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
    
                        $data = array(
                            'id' => $productExsists,
                            'description' => $post->post_title,
                            'other_lang_description' => $edara_other_lang_description,
                            'code' => $edara_code,
                            'sku' => $edara_sku,
                            'price' => $price,
                            'purchase_price' => $edara_purchase_price,
                            'dealer_price' => $edara_dealer_price,
                            'supper_dealer_price' => $edara_supper_dealer_price,
                            'minimum_price' => $edara_minimum_price,
                            'part_number' => $edara_part_number,
                            'tax_rate' => $edara_tax_rate,
                            'classification_id' => $edara_classification_id,
                            'brand_id' => $edara_brand_id,
                            'warranty' => $edara_warranty,
                            'dynamic_properties_info' => $edara_dynamic_properties_info,
                            'unitofmeasure_chain_id' => $edara_unitofmeasure_chain_id,
                            'default_unitofmeasure_id' => $edara_default_unitofmeasure_id,
                            'sales_price_discount' => $edara_sales_price_discount,
                            'sales_price_discount_type' => $edara_sales_price_discount_type,
                            'sales_price_discount_is_limited' => $edara_sales_price_discount_is_limited,
                            'sales_price_discount_date_from' => $edara_sales_price_discount_date_from,
                            'sales_price_discount_date_to' => $edara_sales_price_discount_date_to,
                            'dealer_price_discount' => $edara_dealer_price_discount,
                            'dealer_price_discount_type' => $edara_dealer_price_discount_type,
                            'dealer_price_discount_is_limited' => $edara_dealer_price_discount_is_limited,
                            'dealer_price_discount_date_from' => $edara_dealer_price_discount_date_from,
                            'dealer_price_discount_date_to' => $edara_dealer_price_discount_date_to,
                            'super_dealer_price_discount' => $edara_super_dealer_price_discount,
                            'super_dealer_price_discount_type' => $edara_super_dealer_price_discount_type,
                            'super_dealer_price_discount_is_limited' => $edara_super_dealer_price_discount_is_limited,
                            'super_dealer_price_discount_date_from' => $edara_super_dealer_price_discount_date_from,
                            'super_dealer_price_discount_date_to' => $edara_super_dealer_price_discount_date_to,
                            'weight' => $edara_weight,
                            'data_sheet' => $edara_data_sheet,
                            'note' => $edara_note
                        );
    
                        $curl = curl_init();
    
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'PUT',
                            CURLOPT_POSTFIELDS => json_encode($data),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: ' . $edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                        ));
    
                        $result = curl_exec($curl);
    
                        curl_close($curl);
    
                        EdaraCore::logRequestStatic("Update product by id ", $url, json_encode($data), $result);
    
                        $result = json_decode($result, true);
    
                        if ($result['status_code'] == 200) {
                            $result = $wpdb->update($wpdb->prefix . 'edara_products', array(
                                'status' => "linked"
                            ), array('id' => $productExsistsID));
                        } else {
                            $post_id = $wpId;
                            $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $post_id . "");
                            if ($dataSelected != NULL) {
                                $row = $wpdb->update($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $post_id,
                                    'status' => $result['error_message']
                                ), array('wp_product' => $post_id));
                            } else {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $post_id,
                                    'status' => $result['error_message']
                                ));
                            }
                        }
                    }
                } else {
                    $url = $baseUrl . "stockItems";
                    $skuProduct = get_post_meta($wpId, '_sku', true);
                    $price = get_post_meta($wpId, '_regular_price', true);
    
                    $sale_price = $wpdb->get_var("SELECT sale_price FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
    
                    if ($sale_price == "sale_price") {
                        $data = array('description' => $post->post_title, 'sku' => $skuProduct, 'price' => $price, 'external_id' => $wpId);
                    } else if ($sale_price == "dealer_price") {
                        $data = array('description' => $post->post_title, 'sku' => $skuProduct, 'dealer_price' => $price, 'external_id' => $wpId);
                    } else {
                        $data = array('description' => $post->post_title, 'sku' => $skuProduct, 'supper_dealer_price' => $price, 'external_id' => $wpId);
                    }
    
                    if ($post->post_title) {
                        $post_id = $wpId;
    
                        $curl = curl_init();
    
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => json_encode($data),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: ' . $edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                        ));
    
                        $result = curl_exec($curl);
    
                        curl_close($curl);
    
                        $result = json_decode($result, true);
    
                        EdaraCore::logRequestStatic("Update product by code ", $url, json_encode($data), $result);
    
                        if ($result['status_code'] == 200) {
                            $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                                'wp_product' => $post_id,
                                'edara_product' => $result['result'],
                                'status' => "linked"
                            ));
                            EdaraCore::logGeneralStatic($post_id . " inserted into database");
                        } else {
                            $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $post_id . "");
                            if ($dataSelected != NULL) {
                                $row = $wpdb->update($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $post_id,
                                    'edara_product' => "0",
                                    'status' => $result['error_message']
                                ), array('wp_product' => $post_id));
                            } else {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $post_id,
                                    'edara_product' => $result['result'],
                                    'status' => "linked"
                                ));
                            }
                        }
                    }
                }
            }
        }
    }
    
    public function addEdaraQuickLinks(string $postType): void
    {
        $this->logFunction("addEdaraQuickLinks");
        // Escape of it's not a shop order
        if ('shop_order' !== $postType) {
            return;
        }

        add_meta_box('Edara-quick-links', 'Quick Links', function (\WP_Post $order) use ($postType) {
            $orderId = 0;
            if (!method_exists( $order, 'get_id' ) ) {
                $orderId = $order->ID;
            } else {
                $orderId = $order->get_id();
            }
            $externalCodeId = get_post_meta($orderId, 'external_code_id', true);
            $externalIssueOfferingCode = get_post_meta($orderId, 'external_issue_offering_code', true);
            $externalSalesReturnCode = get_post_meta($orderId, 'external_sales_return_code', true);
            // phpcs:disable
            $url = edara_url() . "/Sales/SalesOrder.aspx?Code={$externalCodeId}";
            $IOURL = edara_url() . "/Warehouse/IssueOfferings.aspx?Code={$externalIssueOfferingCode}";
            if ($externalCodeId) {
                $orderService = new OrderService();
                $orders = $orderService->findOrdersByExternalId($externalCodeId);
                echo "SO Code: ($externalCodeId) ";
                echo ("<a target='_blank' href='{$url}' style='color:darkgreen'>View</a> <br \>");
                // Display duplicated SO
                if (count($orders) > 1) {
                    echo "<span style='color: red'><strong>Duplicated SO</strong></span>";
                }
            }

            if ($externalIssueOfferingCode) {
                echo "IO Code: ($externalIssueOfferingCode) ";
                echo("<a target='_blank' href='{$IOURL}' style='color:darkgreen'>View</a> <br \>");
            }

            if ($externalSalesReturnCode) {
                echo "SR Code: ($externalSalesReturnCode) <br \>";
            }
            // phpcs:enable
        }, $postType, 'side', 'high');
    }

    public function addEdaraProductQuickLinks(string $postType): void
    {
        $this->logFunction("addEdaraProductQuickLinks");
        // Escape of it's not a shop order
        if ('product' !== $postType) {
            return;
        }

        add_meta_box('Edara-product-quick-links', 'Quick Links', function (\WP_Post $product) use ($postType) {
            $externalId = get_post_meta($product->ID, 'external_id', true);
            $externalBundleId = get_post_meta($product->ID, 'external_bundle_id', true);

            // phpcs:disable
            $url = edara_url() . "/Warehouse/stockitems.aspx?stockItemId={$externalId}";
            if ($externalId) {
                echo "Product Id: ($externalId) ";
                echo ("<a target='_blank' href='{$url}' style='color:darkgreen'>View</a> <br \>");
            }

            if ($externalBundleId) {
                $url = edara_url() . "/Sales/SalesBundles.aspx?Id={$externalBundleId}";
                echo "Bundle Id: ($externalBundleId) ";
                echo ("<a target='_blank' href='{$url}' style='color:darkgreen'>View</a> <br \>");
            }
            // phpcs:enable
        }, $postType, 'side', 'high');
    }

    public function registerNewCronSchedules(array $schedules):array
    {
        $this->logFunction("registerNewCronSchedules");
        $schedules['every_six_hours_edara_checker'] = [
            'interval' => 3600 * 6,
            'display' => __('Every 6 Hours #1', 'Edara Integration'),
        ];
        return $schedules;
    }

    public function registerNewOrderStatus(array $orderStatuses):array
    {
        $this->logFunction("registerNewOrderStatus");
        $orderStatuses['wc-ask_for_return'] = 'Ask for return';
        $this->logGeneral("shit " . print_r($orderStatus));

        global $post;
        // Ignore of the current is not an order type
        if (!$post || 'shop_order' !== $post->post_type) {
            return $orderStatuses;
        }

        // Display the pending status for the first time
        if ($post && 'shop_order' === $post->post_type && 'auto-draft' === $post->post_status) {
            return ['wc-pending' => $orderStatuses['wc-pending']];
        }
        // phpcs:ignore
        $output[$post->post_status] = array_get($orderStatuses, $post->post_status, array_get($orderStatuses, 'wc-' . $post->post_status));

        foreach ((new OrderService())->retrieveAvailableStatus($post->post_status) as $one) {
            $output[$one] = $orderStatuses[$one];
        }

        return $output;
    }

    public function validateOrderTransitionForMyAccount(array $actions, Order $order):array
    {
        $this->logFunction("validateOrderTransitionForMyAccount");
        // Display cancel for pending and processing status only
        if (in_array($order->get_status(), ['pending', 'processing'], true)) {
            return $actions;
        }

        // Else remove cancel action
        if (isset($actions['cancel'])) {
            unset($actions['cancel']);
        }

        return $actions;
    }

    public function registerNewOrderColInHeader( $columns ) {
        $this->logFunction("registerNewOrderColInHeader");
        $new_columns = array();
        foreach ( $columns as $column_name => $column_info ) {
            if ( 'order_date' === $column_name ) {
                $new_columns['additional_info'] = 'Additional info';
            }
            $new_columns[ $column_name ] = $column_info;
        }

        return $new_columns;
    }

    public function addOrderAdditionalInfoContent(string $column)
    {
        $this->logFunction("addOrderAdditionalInfoContent");
        global $post;

        if ('additional_info' === $column) {
            if ($externalCodeId = get_post_meta($post->ID, 'external_code_id', true)) {
                $url = edara_url() . "/Sales/SalesOrder.aspx?Code={$externalCodeId}";
                echo ("<a target='_blank' href='{$url}' style='color:darkgreen'>#$externalCodeId</a>");
            }

            if ($priceNotMatched = get_post_meta($post->ID, 'price_is_not_matched', true)) {
                echo ' <span style="color: white;background-color: red">#Price not matched</span>';
            }
        }
    }

    public function hideInventoryTabInProductPage($tabs)
    {
        $this->logFunction("hideInventoryTabInProductPage");
        if (current_user_can('manage_inventory_tab')) {
            unset($tabs['inventory']);
        }

        return ($tabs);
    }

    public function addAdditionalOptionForInventorySettings($settings)
    {
        $this->logFunction("addAdditionalOptionForInventorySettings");
        foreach ($settings as &$setting) {
            if ('woocommerce_stock_format' === $setting['id']) {
                $setting['options']['show_when_less_than_20'] = 'Always show quantity remaining in stock less than 20 "20 in stock"';
                break;
            }
        }

        return $settings;
    }

    public function changeProductAvailabilityText($availability, \WC_Product $product)
    {
        $this->logFunction("changeProductAvailabilityText");
        if ( $product->managing_stock() ) {
            $display      = __( 'In stock', 'woocommerce' );
            $stock_amount = $product->get_stock_quantity();

            switch ( get_option( 'woocommerce_stock_format' ) ) {
                case 'low_amount':
                    if ( $stock_amount <= get_option( 'woocommerce_notify_low_stock_amount' ) ) {
                        /* translators: %s: stock amount */
                        $display = sprintf( __( 'Only %s left in stock', 'woocommerce' ), wc_format_stock_quantity_for_display( $stock_amount, $product ) );
                    }
                    break;
                case '':
                    /* translators: %s: stock amount */
                    $display = sprintf( __( '%s in stock', 'woocommerce' ), wc_format_stock_quantity_for_display( $stock_amount, $product ) );
                    break;
                case 'show_when_less_than_20':
                    if ($product->get_stock_quantity() <= 20 ) {
                        $display = sprintf( __( '%s in stock', 'woocommerce' ), wc_format_stock_quantity_for_display( $stock_amount, $product ) );
                    }
                    break;
            }

            if ( $product->backorders_allowed() && $product->backorders_require_notification() ) {
                $display .= ' ' . __( '(can be backordered)', 'woocommerce' );
            }
        }

        return $display;
    }

    public function logFunction($functionName)
    {
        $message = "Function " . $functionName . ": " . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents(ABSPATH . "log_system.log",print_r($message,true),FILE_APPEND);

    }

    public function logRequest($requestType,$url,$requestParams,$response)
    {
        file_put_contents(ABSPATH . "log_system.log",$requestType . " Request",FILE_APPEND);
        file_put_contents(ABSPATH . "log_system.log","URL: " . $url,FILE_APPEND);
        file_put_contents(ABSPATH . "log_system.log","Params: " . print_r($requestParams,true),FILE_APPEND);
        file_put_contents(ABSPATH . "log_system.log",print_r($response,true),FILE_APPEND);

    }

    public function logGeneral($message)
    {
        $message = "General log " . $message . ": " . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents(ABSPATH . "log_system.log",print_r($message,true),FILE_APPEND);

    }

    public static function logFunctionStatic($functionName){
        $message = "Function " . $functionName . ": " . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents(ABSPATH . "log_system.log",print_r($message,true),FILE_APPEND);

    }

    public static function logRequestStatic($requestType,$url,$requestParams,$response)
    {
        file_put_contents(ABSPATH . "log_system.log",$requestType . " Request",FILE_APPEND);
        file_put_contents(ABSPATH . "log_system.log","URL: " . $url,FILE_APPEND);
        file_put_contents(ABSPATH . "log_system.log","Params: " . print_r($requestParams,true),FILE_APPEND);
        file_put_contents(ABSPATH . "log_system.log","Response: " . print_r($response,true),FILE_APPEND);

    }

    public static function logGeneralStatic($message)
    {
        $message = "General log " . $message . ": " . date("Y-m-d H:i:s") . PHP_EOL;
        file_put_contents(ABSPATH . "log_system.log",print_r($message,true),FILE_APPEND);

    }

    public static function sendOrderDetailsToGoogleForm($order, $sync_status) {
        // Google Form URL
        $google_form_url = 'https://docs.google.com/forms/d/e/1FAIpQLScz6lkbBQD0YE2l6nZNhUBbYFl8rLirO8XUts4AL6eOV0EsGQ/formResponse';
    
        // Retrieve data from the database
        global $wpdb;
        $edara_domain = $wpdb->get_var("SELECT edara_domain FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
        $edara_email = $wpdb->get_var("SELECT edara_email FROM " . $wpdb->prefix . "edara_config WHERE id = 1");
        $website_url = get_site_url(); // Get the website URL
        $order_id = $order->get_id(); // Get the order ID
        $order_total = $order->get_total(); // Get the total order amount
        $order_status = $order->get_status(); // Get the order status
        $order_date = $order->get_date_created(); // Get the order creation date
    
        // Prepare data to be sent
        $data = array(
            'entry.1620534668' => $edara_domain,
            'entry.1546867902' => $edara_email,
            'entry.161224006'  => $website_url,
            'entry.887880441'  => $order_id,
            'entry.1295938289' => $order_total,
            'entry.396311978'  => $order_status,
            'entry.564156645_year'  => $order_date->date('Y'),
            'entry.564156645_month' => $order_date->date('m'),
            'entry.564156645_day'   => $order_date->date('d'),
            'entry.1681967357' => $sync_status // New field for sync status
        );
    
        // Send POST request to Google Form
        $response = wp_remote_post($google_form_url, array(
            'method'    => 'POST',
            'body'      => $data,
            'headers'   => array(),
            'sslverify' => false
        ));
    
        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            EdaraCore::logGeneralStatic("Failed to send order data to Google Form: $error_message");
        } else {
            EdaraCore::logGeneralStatic("Successfully sent order data to Google Form for Order ID: $order_id");
        }
    }

    public function getBaseUrl(){
        return "https://api.edara.io/v2.0/";
    }

    public static function getBaseUrlStatic(){
        return "https://api.edara.io/v2.0/";
    }
}
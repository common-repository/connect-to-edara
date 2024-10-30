<?php
// var_dump($_POST);die();
declare(strict_types=1);
// $path = $_SERVER['DOCUMENT_ROOT'];
$path = "../../../..";

include_once $path . '/wp-config.php';
include_once $path . '/wp-includes/wp-db.php';
include_once $path . '/wp-includes/pluggable.php';

global $wpdb;

// $edara_accsess_token = "5ivhDvWwFzgB9nljbwKajWC7BDelZPcZ0odfIfW4hzs=";
$edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");
$id = $_POST['id'];
$type = $_POST['type'];

if ($id && $type) {

    if ($type == "product") {
        $response = "";
        $products = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."posts WHERE post_type = 'product' AND ID = ".intval($id)."");
        foreach ($products as $product) {
            $postmeta = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."postmeta WHERE post_id = ".$product->ID." AND meta_key = '_regular_price'");
            $price = count($postmeta) > 0 ? (double)$postmeta[0]->meta_value : 0;
            $skuProduct = get_post_meta($product->ID, '_sku', true);

            // Check if SKU exists in Edara
            $findProductUrl = "https://api.edara.io/v2.0/stockItems/Find?sku=" . urlencode($skuProduct);
            $ch = curl_init($findProductUrl);
            $headers = array(
                "Content-Type: application/json",
                "Authorization:".$edara_accsess_token."",
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $findProductResult = curl_exec($ch);
            curl_close($ch);
            $findProductResult = json_decode($findProductResult, true);
            error_log("Find product by sku: " . json_encode($findProductResult));

            if ($findProductResult['status_code'] == 200) {
                $productID = $findProductResult['result']['id'];

                $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$product->ID."");
                if ($dataSelected != NULL) {
                    $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                        'status' => "linked",
                        'edara_product' => $productID
                    ), array('wp_product' => $id));
                } else {
                    $insertionResult = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_product' => $id,
                        'edara_product' => $productID,
                        'status' => "linked"
                    ));
                }
                error_log("Found id by sku: " . $productID);
                $response = "linked";
            } else {
                $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$product->ID."");
                if ($dataSelected != NULL) {
                    $response = $findProductResult['error_message'];
                    $row = $wpdb->update($wpdb->prefix.'edara_products', array(
                        'status' => $findProductResult['error_message']
                    ), array('wp_product' => $product->ID));
                } else {
                    $response = $findProductResult['error_message'];
                    $result = $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_product' => $id,
                        'edara_product' => 0,
                        'status' => $findProductResult['error_message']
                    ));
                }
            }
        }
        echo $response;
    }

    
    if ($type == "customer") {
        $customers = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."wc_customer_lookup WHERE customer_id =".$id."");
        $url = "https://api.edara.io/v2.0/customers";
        $response = "";
        foreach ($customers as $customer) {

            $email = $customer->email;
            $findCustomerUrl = "https://api.edara.io/v2.0/customers/FindByEmail/" . $email;
            $options = array(
                'http' => array(
                    'header'  => "Authorization:".$edara_accsess_token."",
                    'method'  => 'GET',
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($findCustomerUrl, false, $context);

            $result = json_decode($result, true);
            if($result['status_code'] == 200){
                $edaraId = $result['result']['id'];

                $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                    'wp_customer' => $id,
                    'edara_customer' => $edaraId,
                    'status' => 'linked'
                  ));

                $response = "linked";
            }else{
                $name = $customer->first_name . " " . $customer->last_email;
                $data = array('name' => $name, 'email' =>$customer->email,'payment_type' => 'credit');

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
                        $response = "linked";
                        $result = $wpdb->update($wpdb->prefix.'edara_customers', array(
                            'status' => "linked"
                        ), array('wp_customer' => $id));
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
                        $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_customers WHERE wp_customer = ".$id."");
                        if ($dataSelected != NULL)  {
                            $response = $result['error_message'];
                            $row = $wpdb->update($wpdb->prefix.'edara_customers', array(
                            'status' => $result['error_message']
                        ), array('wp_customer' => $id));
                        }else{
                            $response = $result['error_message'];
                        $result = $wpdb->insert($wpdb->prefix.'edara_customers', array(
                            'wp_customer' => $id,
                            'edara_customer' => 0,
                            'status' => $result['error_message']
                        ));
                        }
                    }
                }
            }
        }
        echo $response;
    }

    if ($type == "order") {

        $response = "";
        global $wpdb;
        $warehouses_selection = $wpdb->get_var("SELECT warehouses_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");
    
        $url = "https://api.edara.io/v2.0/salesOrders";
        $order = wc_get_order(intval($id));
    
        $orderMeta = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_customer_user' AND post_id =".$order->get_id());
        $orderItemIDs = $wpdb->get_results("SELECT order_item_id FROM ".$wpdb->prefix."woocommerce_order_items WHERE order_item_type = 'line_item' AND order_id = '".$order->get_id()."'");
        $customerMetaID = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_customer_user' AND post_id = '".$order->get_id()."'");
    
        $availableOrdersStatus = $wpdb->get_var("SELECT orders_status FROM ".$wpdb->prefix."edara_config WHERE id = 1");
    
        $confirmedStatus = 0;
        if($availableOrdersStatus){
            $orderStatus = $order->get_status();
            $arr =  str_replace('\\','',$availableOrdersStatus);
            foreach(json_decode($arr) as $st){
                if($st == $orderStatus || $st == 'any'){
                    $confirmedStatus = 1;
                }
            }
        }else{
            $confirmedStatus = 1;
        }
    
        if($confirmedStatus == 0){
            echo ("Can't resync " . $order->get_status() . " orders");
            return;
        }
    
        // New logic to check if products exist in Edara and set their status to "linked"
        foreach ($orderItemIDs as $orderItemID) {
            $productMetaID = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND order_item_id = '".$orderItemID->order_item_id."'");
            $skuProduct = get_post_meta($productMetaID, '_sku', true);
            $findProductUrl = "https://api.edara.io/v2.0/stockItems/Find?sku=" . urlencode($skuProduct);
            
            $ch = curl_init($findProductUrl);
            $headers = array(
                "Content-Type: application/json",
                "Authorization:".$edara_accsess_token."",
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $findProductResult = curl_exec($ch);
            curl_close($ch);
            $findProductResult = json_decode($findProductResult, true);
    
            if ($findProductResult['status_code'] == 200) {
                $productID = $findProductResult['result']['id'];
                $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_products WHERE wp_product = ".$productMetaID);
                if ($dataSelected != NULL) {
                    $wpdb->update($wpdb->prefix.'edara_products', array(
                        'status' => "linked",
                        'edara_product' => $productID
                    ), array('wp_product' => $productMetaID));
                } else {
                    $wpdb->insert($wpdb->prefix.'edara_products', array(
                        'wp_product' => $productMetaID,
                        'edara_product' => $productID,
                        'status' => "linked"
                    ));
                }
            } else {
                echo "Product SKU not found in Edara: " . $skuProduct;
                return;
            }
        }
    
        $orderData = $order->get_data();
        $countryCode = $orderData['billing']['country'];
        $stateCode = $orderData['billing']['state'];
        $streetName = $orderData['billing']['address_1'] . ", " . $orderData['billing']['city'];
        $stateName = WC()->countries->get_states($countryCode)[$stateCode] ?? '';
        $countryName = WC()->countries->countries[$order->get_shipping_country()] ?? '';
        $countryId = 0;
        $cityId = 0;
    
        if($stateName && $countryName){
            $countryIdTry = $wpdb->get_var("SELECT country_id FROM ".$wpdb->prefix."edara_cities WHERE city_name = '".$stateName."'");
            $cityIdTry = $wpdb->get_var("SELECT city_id FROM ".$wpdb->prefix."edara_cities WHERE city_name = '".$stateName."'");
            if($countryIdTry && $cityIdTry){
                $countryId = $countryIdTry;
                $cityId = $cityIdTry;
            }else{
                $findCityUrl = "https://api.edara.io/v2.0/cities/FindByName/" . $stateName;
                $options = array(
                    'http' => array(
                        'header'  => "Authorization:".$edara_accsess_token."",
                        'method'  => 'GET',
                    )
                );
                $context  = stream_context_create($options);
                $cityResult = file_get_contents($findCityUrl, false, $context);
                $cityResult = json_decode($cityResult, true);
                logRequest("Find city by name",$findCityUrl,json_encode($options),$cityResult);
                if($cityResult['status_code'] == 200){
                    $countryId = $cityResult['result']['country_id'];
                    $cityId = $cityResult['result']['id'];
                    $wpdb->insert($wpdb->prefix.'edara_cities', array(
                        'city_name' => $stateName,
                        'country_id' => $countryId,
                        'city_id' => $cityId
                    ));
                }else{
                    $findCountryUrl = $baseUrl . "countries/FindByName/" . $countryName;
                    $options = array(
                        'http' => array(
                            'header'  => "Authorization:".$edara_accsess_token."",
                            'method'  => 'GET',
                        )
                    );
                    $context  = stream_context_create($options);
                    $countryResult = file_get_contents($findCountryUrl, false, $context);
                    $countryResult = json_decode($countryResult, true);
                    EdaraCore::logRequestStatic("Find country by name",$findCountryUrl,json_encode($options),$countryResult);
                    if($countryResult['status_code'] == 200){
                        $countryId = $countryResult['result']['id'];
                        $addCityUrl = $baseUrl . "cities";
                        $cityData = array('name' => $stateName, 'country_id' =>$countryId);
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
                            CURLOPT_POSTFIELDS =>json_encode($cityData),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: '.$edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                        ));
                        $addCityResult = curl_exec($curl);
                        curl_close($curl);
                        $addCityResult = json_decode($addCityResult, true);
                        EdaraCore::logRequestStatic("Post city when not exist",$addCityUrl,json_encode($cityData),$addCityResult);
                        if($addCityResult['status_code'] == 200){
                            $cityId = $addCityResult['result'];
                        }
                    }else{
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
                            CURLOPT_POSTFIELDS =>json_encode($countryData),
                            CURLOPT_HTTPHEADER => array(
                                'Authorization: '.$edara_accsess_token,
                                'Content-Type: application/json'
                            ),
                        ));
                        $addCountryResult = curl_exec($curl);
                        curl_close($curl);
                        $addCountryResult = json_decode($addCountryResult, true);
                        EdaraCore::logRequestStatic("Post city when not exist",$addCountryUrl,json_encode($countryData),$addCountryResult);
                        if($addCountryResult['status_code'] == 200){
                            $countryId = $addCountryResult['result'];
                            $addCityUrl = $baseUrl . "cities";
                            $cityData = array('name' => $stateName, 'country_id' =>$countryId);
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
                                CURLOPT_POSTFIELDS =>json_encode($cityData),
                                CURLOPT_HTTPHEADER => array(
                                    'Authorization: '.$edara_accsess_token,
                                    'Content-Type: application/json'
                                ),
                            ));
                            $addCityResult = curl_exec($curl);
                            curl_close($curl);
                            $addCityResult = json_decode($addCityResult, true);
                            EdaraCore::logRequestStatic("Post city when not exist",$addCityUrl,json_encode($cityData),$addCityResult);
                            if($addCityResult['status_code'] == 200){
                                $cityId = $addCityResult['result'];
                            }
                        }
                    }
                }
            }
        }
        logGeneral("Order data state => " . $stateName);
    
        $customerID = NULL;
        if(!$customerID){
            $customerBillingEmail = $order->get_billing_email();
            $customersMapKey = "email";
            $customersMapKeyCheck = $wpdb->get_var("SELECT customers_key FROM ".$wpdb->prefix."edara_config WHERE id = '1'");
            if($customersMapKeyCheck){
                $customersMapKey = $customersMapKeyCheck;
            }
            $findCustomerUrl = "https://api.edara.io/v2.0/customers/FindByEmail/" . $customerBillingEmail;
            if($customersMapKey == "phone"){
                $orderDataTemp = $order->get_data();
                $findCustomerUrl = "https://api.edara.io/v2.0/customers/FindByMobile/" . $orderDataTemp['billing']['phone'];
            }
            $options = array(
                'http' => array(
                    'header'  => "Authorization:".$edara_accsess_token."",
                    'method'  => 'GET',
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($findCustomerUrl, false, $context);
            $result = json_decode($result, true);
            logRequest("Find customer by billing email ",$findCustomerUrl,$options,$result);
            if($result['status_code'] == 200){
                $customerID = $result['result']['id'];
                $orderData = $order->get_data();
                $userName = $orderData['billing']['first_name'] . " " . $orderData['billing']['last_name'];
                $userEmail = $orderData['billing']['email'];
                $userPhone = $orderData['billing']['phone'];
                $edara_id = $result['result']['id'];
                $edara_name = $userName;
                $edara_code = $result['result']['code'];
                $edara_relatedaccountcode = $result['result']['relatedaccountcode'];
                $edara_phone = $result['result']['phone'];
                $edara_mobile = $userPhone;
                $edara_email = $userEmail;
                $edara_payment_type = $result['result']['payment_type'];
                $edara_credit_limit = $result['result']['credit_limit'];
                $edara_balance = $result['result']['balance'];
                $edara_shipping_term = $result['result']['shipping_term'];
                $edara_insurance = $result['result']['insurance'];
                $edara_has_discount = $result['result']['has_discount'];
                $edara_discount_from = $result['result']['discount_from'];
                $edara_discount_to = $result['result']['discount_to'];
                $edara_customer_type = $result['result']['customer_type'];
                $edara_pricing_type = $result['result']['pricing_type'];
                $edara_payment_max_due_days = $result['result']['payment_max_due_days'];
                $edara_related_account_id = $result['result']['related_account_id'];
                $edara_related_account_parent_id = $result['result']['related_account_parent_id'];
                $edara_tax_registeration_id = $result['result']['tax_registeration_id'];
                $edara_external_id = $result['result']['external_id'] ?? ''; // Check if the key exists
                $edara_customer_addresses = $result['result']['customer_addresses'];
                if($countryId && $cityId){
                    $firstAddressArray = array();
                    $firstAddressArray['country_id'] = $countryId;
                    $firstAddressArray['city_id'] = $cityId;
                    $firstAddressArray['street'] = $streetName;
                    $edara_customer_addresses[] = $firstAddressArray;
                }
                $updateCustomerUrl = "https://api.edara.io/v2.0/customers";
                $updateCustomerData = array(
                    'id' => $edara_id,
                    'name' => $edara_name,
                    'code' => $edara_code,
                    'relatedaccountcode' => $edara_relatedaccountcode,
                    'phone' => $edara_phone,
                    'mobile' => $edara_mobile,
                    'email' => $edara_email,
                    'payment_type' => $edara_payment_type,
                    'credit_limit' => $edara_credit_limit,
                    'balance' => $edara_balance,
                    'shipping_term' => $edara_shipping_term,
                    'insurance' => $edara_insurance,
                    'has_discount' => $edara_has_discount,
                    'discount_from' => $edara_discount_from,
                    'discount_to' => $edara_discount_to,
                    'customer_type' => $edara_customer_type,
                    'pricing_type' => $edara_pricing_type,
                    'payment_max_due_days' => $edara_payment_max_due_days,
                    'related_account_id' => $edara_related_account_id,
                    'related_account_parent_id' => $edara_related_account_parent_id,
                    'tax_registeration_id' => $edara_tax_registeration_id,
                    'external_id' => $edara_external_id,
                    'customer_addresses' => $edara_customer_addresses
                );
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $updateCustomerUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'PUT',
                    CURLOPT_POSTFIELDS =>json_encode($updateCustomerData),
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: '.$edara_accsess_token,
                        'Content-Type: application/json'
                    ),
                ));
                $updateResult = curl_exec($curl);
                curl_close($curl);
                logRequest("Update customer ",$updateCustomerUrl,json_encode($updateCustomerData),$updateResult);
            }else{
                $response = $result['error_message'];
                $orderData = $order->get_data();
                $userName = $orderData['billing']['first_name'] . " " . $orderData['billing']['last_name'];
                $userEmail = $orderData['billing']['email'];
                $userPhone = $orderData['billing']['phone'];
                $postCustomerUrl =  "https://api.edara.io/v2.0/customers";
                if($countryId && $cityId){
                    $addressesArray = array();
                    $firstAddressArray = array();
                    $firstAddressArray['country_id'] = $countryId;
                    $firstAddressArray['city_id'] = $cityId;
                    $firstAddressArray['street'] = $streetName;
                    $addressesArray[] = $firstAddressArray;
                    $data = array('name' => $userName, 'email' =>$userEmail,'payment_type' => 'Credit','mobile' => $userPhone,'customer_addresses'=>$addressesArray);
                }else{
                    $data = array('name' => $userName, 'email' =>$userEmail,'payment_type' => 'Credit','mobile' => $userPhone);
                }
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $postCustomerUrl,
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
                logRequest("Post if customerId equals null",$postCustomerUrl,$data,$result);
                if ($result['status_code'] == 200) {
                    $customerID = $result['result'];
                }else{
                    $response = "Error in posting customer";
                }
            }
        }
    
        //Check taxes
        $orderTaxRate = 0;
        $orderTaxTotal = 0;
        $edaraTaxId = 0;
        foreach($order->get_items('tax') as $taxItem){
            $orderTaxRate = $taxItem->get_data()['rate_percent'];
            $orderTaxTotal = $taxItem->get_data()['tax_total'];
        }
        if($orderTaxRate){
            $edaraTaxIdTry = $wpdb->get_var("SELECT edara_id FROM ".$wpdb->prefix."edara_taxes WHERE percent = '".$orderTaxRate."'");
            if($edaraTaxIdTry){
                $edaraTaxId = $edaraTaxIdTry;
            }
        }
    
        $total = 0;
        $saleOrderLine = [];
        foreach ($orderItemIDs as $orderItemID) {
            $sub_total = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_line_total' AND order_item_id = '".$orderItemID->order_item_id."'");
            $quantity = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_qty' AND order_item_id = '".$orderItemID->order_item_id."'");
            $productMetaID = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND order_item_id = '".$orderItemID->order_item_id."'");
            $itemPrice = $sub_total / $quantity;
            $productID = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = '".$productMetaID."'");
    
            if (empty($productID) || $productID == NULL) {
                $tableName = $wpdb->prefix."icl_translations";
                $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
                if($wpdb->get_var($query) == $tableName){
                    $englishId = $wpdb->get_var("SELECT trans2.element_id FROM wp_icl_translations AS trans1 INNER JOIN wp_icl_translations AS trans2 ON trans2.trid = trans1.trid WHERE trans1.element_id = ".$productMetaID." AND trans2.source_language_code IS NULL");
                    $productID = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = '".$englishId."'");
                    logGeneral("English id = " . $englishId . " And edaraId = " . $productID);
                }
            }
    
            if (empty($productID) || $productID == NULL) {
                $variationId = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_variation_id' AND order_item_id = '".$orderItemID->order_item_id."'");
                if($variationId != '0'){
                    $productID = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = '".$variationId."'");
                }
                logGeneral("variation id = " . $variationId . " And new edaraId = " . $productID);
    
                if (empty($productID) || $productID == NULL) {
                    $tableName = $wpdb->prefix."icl_translations";
                    $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
                    if($wpdb->get_var($query) == $tableName){
                        $englishId = $wpdb->get_var("SELECT trans2.element_id FROM wp_icl_translations AS trans1 INNER JOIN wp_icl_translations AS trans2 ON trans2.trid = trans1.trid WHERE trans1.element_id = ".$productMetaID." AND trans2.source_language_code IS NULL");
                        $productID = $wpdb->get_var("SELECT edara_product FROM ".$wpdb->prefix."edara_products WHERE wp_product = '".$englishId."'");
                        logGeneral("English id = " . $englishId . " And new edaraId = " . $productID);
                    }
                }
            }
    
            $total_tax = 0;
            // Check if product prices include tax
            $prices_include_tax = wc_prices_include_tax();
    
            if ($productID) {
                // Calculate tax-inclusive price per item if prices include tax
                if ($prices_include_tax) {
                    $subtotal = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_line_subtotal' AND order_item_id = '".$orderItemID->order_item_id."'");
                    $subtotal_tax = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_line_subtotal_tax' AND order_item_id = '".$orderItemID->order_item_id."'");
                    $per_item_price_excl_tax = $subtotal / $quantity;
                    $per_item_tax_amount = $subtotal_tax / $quantity;
                    $itemPrice = $per_item_price_excl_tax + $per_item_tax_amount;
                }
    
                if($edaraTaxId){
                    array_push($saleOrderLine, array('quantity' => $quantity,'price' => $itemPrice,'stock_item_id' => $productID,'tax_id' => $edaraTaxId));
                }else{
                    array_push($saleOrderLine, array('quantity' => $quantity,'price' => $itemPrice,'stock_item_id' => $productID));
                }
                $total += (double)$sub_total;
                $total_tax += isset($subtotal_tax) ? (double)$subtotal_tax : 0; // Ensure $subtotal_tax is defined
            }else {
                echo "No edara product found";
                return;
            }
        }
    
        $awb = "";
        foreach($orderData['meta_data'] as $metaData){
            $obj = $metaData->get_data();
            if($obj['key'] == 'AWB'){
                $awb = $obj['value'];
            }
        }
    
        // Get the first order note
        $first_order_note = "";
        $order_notes = wc_get_order_notes(array('order_id' => $order->get_id()));
        if (!empty($order_notes)) {
            $first_order_note = $order_notes[0]->content;
        }
    
        // Handle shipping
        $shipping_total = $order->get_shipping_total();
        $shipping_tax = $order->get_shipping_tax();
        $serviceId = $wpdb->get_var("SELECT service_item FROM ".$wpdb->prefix."edara_config WHERE id = '1'");
    
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
            array_push($saleOrderInstallmentsLine, array('due_date' => date("y-M-d H:i:s"),'amount' => $total_with_tax,'days_limit' => 0));
        }
    
        if (count($saleOrderLine) >= 0 && $customerID != NULL) {
            $orderDate = $order->get_date_created();
            
            $warehouseID = $wpdb->get_var("SELECT warehouses_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");
            $storeID = $wpdb->get_var("SELECT stores_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");
            if($warehouseID == -1){
                $warehouseID = null;
            }
            if($storeID == -1){
                $storeID = null;
            }
    
            $orderStatus = $order->get_status();
            if($orderStatus == 'processing'){
                $orderStatus = 'pending';
            }
    
            $data = array('paper_number' => $order->get_data()['number'],'customer_id' => $customerID, 'order_status' =>$orderStatus,'document_date' => $orderDate->date("Y-m-d H:i:s"),'sub_total' => $total,'total_item_discounts' => 0.0, 'taxable' => true, 'tax' => 0, 'warehouse_id' => $warehouseID, 'salesstore_id' => $storeID, 'salesOrder_details' => $saleOrderLine, 'salesOrder_installments' => $saleOrderInstallmentsLine,'notes' => $first_order_note);
    
            $ch = curl_init($url);
            $payload = json_encode($data);
            $headers = array(
                "Content-Type: application/json",
                "Authorization:".$edara_accsess_token."",
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($result, true);
            logRequest("Post ",$url,$payload,$result);
            if ($result['status_code'] == 200) {
                $charset_collate = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_orders (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    wp_order varchar(255),
                    edara_order varchar(255),
                    status varchar(255),
                    PRIMARY KEY (id)
                ) $charset_collate;";
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($sql);
    
                $row = $wpdb->update($wpdb->prefix.'edara_orders', array(
                    'edara_order' => $result['result'],
                    'status' => 'linked'
                ), array('wp_order' => $id));
    
                $is_error = empty($wpdb->last_error);
                if ($is_error) {
                    $dataSelected = $wpdb->get_var("SELECT edara_order FROM ".$wpdb->prefix."edara_orders WHERE wp_order = ".$id."");
                    if ($dataSelected) {
                        if ($dataSelected == "0") {
                            $row = $wpdb->update($wpdb->prefix.'edara_orders', array(
                                'edara_order' => $result['result'],
                                'wp_order' => $id,
                                'status' => $result['error_message']
                            ), array('wp_order' => $id));
                            $response = $result['error_message'];
                        } else {
                            $row = $wpdb->update($wpdb->prefix.'edara_orders', array(
                                'status' => "linked"
                            ), array('wp_order' => $id));
                            $response = "linked";
                        }
                    } else {
                        $result = $wpdb->insert($wpdb->prefix.'edara_orders', array(
                            'wp_order' => $id,
                            'edara_order' => $result['result'],
                            'status' => "linked"
                        ));
                        $response = "linked";
                    }
                }
            } else {
                $response = $result['error_message'];
                $charset_collate = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_orders (
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
                    $dataSelected = $wpdb->get_var("SELECT id FROM ".$wpdb->prefix."edara_orders WHERE wp_order = ".$id."");
                    if ($dataSelected != NULL)  {
                        $row = $wpdb->update($wpdb->prefix.'edara_orders', array(
                            'wp_order' => $id,
                            'edara_order' => "0",
                            'status' => $result['error_message']
                        ), array('wp_order' => $id));
                        $response = $result['error_message'];
                    } else {
                        $result = $wpdb->insert($wpdb->prefix.'edara_orders', array(
                            'wp_order' => $id,
                            'edara_order' => 0,
                            'status' => $result['error_message']
                        ));
                        echo "<script>console.log('Debug Objects: " . print_r($response) . "' );</script>";
                        $response = $result['error_message'];
                    }
                }
            }
        }
    
        echo $response;
    }
    
}

function logRequest($requestType,$url,$requestParams,$response)
{
  file_put_contents(ABSPATH . "log_sync.log",$requestType . " Request",FILE_APPEND);
  file_put_contents(ABSPATH . "log_sync.log","URL: " . $url,FILE_APPEND);
  file_put_contents(ABSPATH . "log_sync.log","Params: " . print_r($requestParams,true),FILE_APPEND);
  file_put_contents(ABSPATH . "log_sync.log","Response: " . print_r($response,true),FILE_APPEND);
}

function logGeneral($message)
{
  file_put_contents(ABSPATH . "log_sync.log","Log General: " . $message,FILE_APPEND);
}
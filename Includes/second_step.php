<?php
// var_dump($_POST);die();
// $path = $_SERVER['DOCUMENT_ROOT'];
$path = "../../../..";
  
include_once $path . '/wp-config.php';
include_once $path . '/wp-includes/wp-db.php';
include_once $path . '/wp-includes/pluggable.php';

global $wpdb;

$edara_accsess_token = $_POST['edara_accsess_token'];
$edara_option = $_POST['edara_option'];

if ($edara_option != null) {
    if ($edara_option == "sync_products_from_edara") {
        $url = "https://api.edara.io/v2.0/stockItems?limit=10000000&offset=0";

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
           "Accept: application/json",
           "Authorization: Bearer ".$edara_accsess_token."",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($resp, true);
        
        if ($result != null) {
            if ($result['status_code'] == 200) {
                foreach ($result['result'] as $product) {
                    //check if this product exsists or not
                    $checkIfExists = $wpdb->get_var("SELECT product_id FROM ".$wpdb->prefix."wc_product_meta_lookup WHERE sku = '".$product['id']."'");
                    if ($checkIfExists == NULL) {
                        // INSERT INTO wp_edaraposts (post_title, post_name, post_content, post_excerpt, to_ping, pinged, post_content_filtered, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt) VALUES ('Cardinal', 'Tom B. Erichsen', 'Tom B. Erichsen', 'Tom B. Erichsen', 'Tom B. Erichsen', 'Tom B. Erichsen', 'Tom B. Erichsen', 'product', NOW(), NOW(), NOW(), NOW());

                        // INSERT INTO `wp_edarapostmeta` (post_id, meta_key ,meta_value) VALUES (20,'_regular_price','45'),(20,'_sale_price','40');

                        // INSERT INTO `wp_edarawc_product_meta_lookup` (product_id, sku, downloadable, min_price, max_price, onsale, stock_quantity, stock_status, rating_count, average_rating, total_sales, tax_status, tax_class) VALUES (20,'',0,40.0000,40.0000,1,NULL,'instock',0,0.00,0,'taxable','');

                        $date = date('Y-m-d H:i:s');

                        $savedProduct = $wpdb->insert($wpdb->prefix.'posts', array(
                              'post_title' => $product['description'], 
                              'post_name' => $product['description'],
                              'post_content' => $product['description'],
                              'post_excerpt' => $product['description'], 
                              'to_ping' => $product['code'],
                              'pinged' => $product['description'],
                              'post_content_filtered' => $product['description'], 
                              'post_name' => $product['description'],
                              'post_content' => $product['description'],
                              'post_type' => 'product',

                              'post_date' => $date,
                              'post_date_gmt' => $date,
                              'post_modified' => $date,
                              'post_modified_gmt' => $date
                            ));
                        $lastid = $wpdb->insert_id;
                        if ($lastid) {
                            $savedMeta = $wpdb->insert($wpdb->prefix.'postmeta', array(
                              'post_id' => $lastid, 
                              'meta_key' => '_regular_price',
                              'meta_value' => $product['price']
                            ));

                            $savedMeta2 = $wpdb->insert($wpdb->prefix.'postmeta', array(
                              'post_id' => $lastid, 
                              'meta_key' => '_sale_price',
                              'meta_value' => $product['price']
                            ));

                            $savedLocup = $wpdb->insert($wpdb->prefix.'wc_product_meta_lookup', array(
                                'product_id' => $lastid,
                                'sku' => $product['id'],
                                'downloadable' => 1,
                                'min_price' => $product['minimum_price'],
                                'max_price' => $product['purchase_price'],
                                'onsale' => 1,
                                'stock_quantity' => 1,
                                'stock_status' => 'instock',
                                'rating_count' => 0,
                                'average_rating' => 0.00,
                                'total_sales' => 0,
                                'tax_status' => 'taxable',
                                'tax_class' => '' 
                            ));
                        }
                    }else{
                        $date = date('Y-m-d H:i:s');
                        $savedProduct = $wpdb->update($wpdb->prefix.'posts', array(
                              'post_title' => $product['description'], 
                              'post_name' => $product['description'],
                              'post_content' => $product['description'],
                              'post_excerpt' => $product['description'], 
                              'to_ping' => $product['code'],
                              'pinged' => $product['description'],
                              'post_content_filtered' => $product['description'], 
                              'post_name' => $product['description'],
                              'post_content' => $product['description'],
                              'post_type' => 'product',

                              'post_date' => $date,
                              'post_date_gmt' => $date,
                              'post_modified' => $date,
                              'post_modified_gmt' => $date
                            ), array('ID'=>$checkIfExists));
                        if ($savedProduct) {
                            $savedMeta = $wpdb->update($wpdb->prefix.'postmeta', array(
                                  'meta_value' => $product['price']
                            ), array('post_id'=>$checkIfExists,'meta_key'=>'_regular_price'));
                            if (!$savedMeta) {
                                $savedMeta = $wpdb->insert($wpdb->prefix.'postmeta', array(
                                  'post_id' => $checkIfExists, 
                                  'meta_key' => '_regular_price',
                                  'meta_value' => $product['price']
                                ));
                            }
                            $savedMeta2 = $wpdb->update($wpdb->prefix.'postmeta', array(
                              'meta_value' => $product['price']
                            ), array('post_id'=>$checkIfExists,'meta_key'=>'_sale_price'));
                            if (!$savedMeta2) {
                                $savedMeta2 = $wpdb->insert($wpdb->prefix.'postmeta', array(
                                  'post_id' => $checkIfExists, 
                                  'meta_key' => '_sale_price',
                                  'meta_value' => $product['price']
                                ));
                            }
                            $savedLocup = $wpdb->update($wpdb->prefix.'wc_product_meta_lookup', array(
                                'product_id' => $product['id'],
                                'downloadable' => 1,
                                'min_price' => $product['minimum_price'],
                                'max_price' => $product['purchase_price'],
                                'onsale' => 1,
                                'stock_quantity' => 1,
                                'stock_status' => 'instock',
                                'rating_count' => 0,
                                'average_rating' => 0.00,
                                'total_sales' => 0,
                                'tax_status' => 'taxable',
                                'tax_class' => '' 
                            ), array('product_id'=>$checkIfExists));
                        }
                    }
                }
                header("Content-Type: text/json; charset=utf8");
                echo json_encode(array("success" => true,"message" => count($result['result'])." Synced Products"));
            }else{
                header("Content-Type: text/json; charset=utf8");
                echo json_encode(array("success" => false,"error" => $result['error_message']));
            }
            
        }else{
            header("Content-Type: text/json; charset=utf8");
            echo json_encode(array("success" => false,"error" => "Invalid Token"));
        }
    }elseif ($edara_option == "sync_products_to_edara") {

        $products = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."posts WHERE post_type = 'product'");

        $url = "https://api.edara.io/v2.0/stockItems";
        foreach ($products as $product) {
            $postmeta = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."postmeta WHERE post_id = ".$product->ID." AND meta_key = '_sale_price'");
            $price = count($postmeta) > 0 ? (double)$postmeta[0]->meta_value : 0;
            $data = array('description' => $product->post_title, 'sku' =>$product->ID,'price' => (double)$postmeta[0]->meta_value);

            $options = array(
                'http' => array(
                    'header'  => "Authorization:Bearer ".$edara_accsess_token."",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $result = json_decode($result, true);
            if ($result['status_code'] == 400) {
                $url = "https://api.edara.io/v2.0/stockItems/UpdateByCode/".$product->to_ping;
                $data = array('description' => $product->post_title, 'sku' =>$product->ID,'price' => (double)$postmeta[0]->meta_value,'code' => $product->to_ping);
                $options = array(
                    'http' => array(
                        'header'  => "Authorization:Bearer ".$edara_accsess_token."",
                        'method'  => 'PUT',
                        'content' => http_build_query($data),
                    )
                );
                $context  = stream_context_create($options);
                $result = file_get_contents($url, false, $context);
                $result = json_decode($result, true);
            }
        }
        header("Content-Type: text/json; charset=utf8");
        echo json_encode(array("success" => true,"message" => count($products)." Synced Products"));
    }elseif ($edara_option == "sync_Customers_from_edara") {
        $url = "https://api.edara.io/v2.0/customers?limit=10000000&offset=0";

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
           "Accept: application/json",
           "Authorization: Bearer ".$edara_accsess_token."",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($resp, true);
        if ($result != null) {
            if ($result['status_code'] == 200) {
                foreach ($result['result'] as $customer) {
                    //check if this customer exsists or not
                    $checkIfExists = $wpdb->get_var("SELECT user_login FROM ".$wpdb->prefix."users WHERE user_email = '".$customer['email']."'");
                    if ($checkIfExists == NULL) {

                        $savedCustomer = $wpdb->insert($wpdb->prefix.'users', array(
                              'user_login' => $customer['name'], 
                              'user_pass' => $customer['name'],
                              'user_nicename' => $customer['name'],
                              'user_email' => $customer['email'], 
                              'user_registered' => date('Y-m-d H:i:s'),
                              'user_status' => 0,
                              'display_name' => $customer['name']
                            ));
                        
                        $lastid = $wpdb->insert_id;
                        // var_dump($lastid,$customer['name'],$customer['email'],$savedCustomer);die();
                        if ($lastid) {
                            $savedMeta = $wpdb->insert($wpdb->prefix.'usermeta', array(
                                  'meta_value' => $customer['name'],
                                  'meta_key' => "nickname",
                                  'user_id' => $lastid
                            ));

                            $savedMeta = $wpdb->insert($wpdb->prefix.'usermeta', array(
                                  'meta_value' => $customer['name'],
                                  'meta_key' => "first_name",
                                  'user_id' => $lastid
                            ));

                            $savedMeta = $wpdb->insert($wpdb->prefix.'usermeta', array(
                                  'meta_value' => $customer['name'],
                                  'meta_key' => "last_name",
                                  'user_id' => $lastid
                            ));

                            $savedMeta = $wpdb->insert($wpdb->prefix.'usermeta', array(
                                  'meta_value' => $customer['name'],
                                  'meta_key' => "description",
                                  'user_id' => $lastid
                            ));

                            $savedMeta = $wpdb->insert($wpdb->prefix.'usermeta', array(
                                  'meta_value' => $customer['id'],
                                  'meta_key' => "edara_id",
                                  'user_id' => $lastid
                            ));
                        }
                    }else{
                        $savedCustomer = $wpdb->update($wpdb->prefix.'users', array(
                              'user_login' => $customer['name'], 
                              'user_pass' => $customer['name'],
                              'user_nicename' => $customer['name'],
                              'user_email' => $customer['email'], 
                              'user_registered' => date('Y-m-d H:i:s'),
                              'user_status' => 0,
                              'display_name' => $customer['name']
                            ), array('user_login'=>$checkIfExists));
                        // header("Content-Type: text/json; charset=utf8");
                        // echo json_encode(array("success" => true,"message" => count($result['result'])." Synced Updated Customers"));
                    }
                }
                header("Content-Type: text/json; charset=utf8");
                echo json_encode(array("success" => true,"message" => count($result['result'])." Synced Customers"));
            }else{
                header("Content-Type: text/json; charset=utf8");
                echo json_encode(array("success" => false,"error" => $result['error_message']));
            }
            
        }else{
            header("Content-Type: text/json; charset=utf8");
            echo json_encode(array("success" => false,"error" => "Invalid Token"));
        }
    }elseif ($edara_option == "sync_Customers_to_edara") {
        $customers = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."users WHERE ID <> 1");
        $url = "https://api.edara.io/v2.0/customers";
        foreach ($customers as $customer) {
            $data = array('name' => $customer->user_login, 'email' =>$customer->user_email,'payment_type' => 'Credit');

            $options = array(
                'http' => array(
                    'header'  => "Authorization:Bearer ".$edara_accsess_token."",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            $result = json_decode($result, true);

            // if ($result['status_code'] == 409) {
            //     $customerMeta = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."usermeta WHERE user_id =".$customer->ID." AND meta_key = 'edara_id'");
            //     if ($customerMeta) {
            //         $url = "https://api.edara.io/v2.0/customers/".$customerMeta[0]->meta_value;
            //         $data = array('name' => $customer->user_login);
            //         $options = array(
            //             'http' => array(
            //                 'header'  => "Authorization:Bearer ".$edara_accsess_token."",
            //                 'method'  => 'PUT',
            //                 'content' => http_build_query($data),
            //             )
            //         );
            //         $context  = stream_context_create($options);
            //         $result = file_get_contents($url, false, $context);
            //         $result = json_decode($result, true);
            //         var_dump($result);die();
            //     }
                
            // }

        }
        header("Content-Type: text/json; charset=utf8");
        echo json_encode(array("success" => true,"message" => count($customers)." Synced Customers"));
    }elseif ($edara_option == "sync_orders_from_edara") {
        
        $url = "https://api.edara.io/v2.0/salesOrders?myorders=false&limit=10000000&offset=0";

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
           "Accept: application/json",
           "Authorization: Bearer ".$edara_accsess_token."",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($resp, true);
        if ($result != null) {
            if ($result['status_code'] == 200) {
                foreach ($result['result'] as $order) {
                    //check if this order exsists or not
                    $checkIfExists = $wpdb->get_var("SELECT order_id FROM ".$wpdb->prefix."woocommerce_order_items WHERE order_item_type = 'line_item' AND order_item_name = '".$order['document_code']."'");
                    
                    if ($checkIfExists == NULL) {

                        $date = date('Y-m-d H:i:s');

                        $savedOrder = $wpdb->insert($wpdb->prefix.'posts', array(
                              'post_status' => 'wc-'.$order['order_status'],
                              'post_title' => $order['document_code'], 
                              'post_name' => $order['document_code'],
                              'post_content' => $order['document_code'],
                              'post_excerpt' => $order['document_code'], 
                              'pinged' => $order['document_code'],
                              'post_content_filtered' => $order['document_code'], 
                              'post_name' => $order['document_code'],
                              'post_content' => $order['document_code'],
                              'post_type' => 'shop_order',

                              'post_date' => $order['document_date'],
                              'post_date_gmt' => $date,
                              'post_modified' => $date,
                              'post_modified_gmt' => $date
                            ));

                        $savedOrderId = $wpdb->insert_id;

                        $customerID = $wpdb->get_var("SELECT user_id FROM ".$wpdb->prefix."usermeta WHERE meta_key = 'edara_id' AND meta_value = '".$order['customer_id']."'");

                        $savedOrderCustomer = $wpdb->insert($wpdb->prefix.'postmeta', array(
                              'meta_key' => "_customer_user",
                              'meta_value' => $customerID, 
                              'post_id' => $savedOrderId
                            ));
                        foreach ($order['salesOrder_details'] as $saleOrder) {
                            $savedOrderLine = $wpdb->insert($wpdb->prefix.'woocommerce_order_items', array(
                              'order_item_name' => $order['document_code'],
                              'order_item_type' => "line_item", 
                              'order_id' => $savedOrderId
                            ));
                            $savedOrderLineId = $wpdb->insert_id;

                            $productID = $wpdb->get_var("SELECT product_id FROM ".$wpdb->prefix."wc_product_meta_lookup WHERE sku = '".$saleOrder['stock_item_id']."'");
                            // var_dump($savedOrderId,$savedOrderLineId,$productID);die();
                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_product_id", 
                              'meta_value' => $productID
                            ));
                            
                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_variation_id", 
                              'meta_value' => 0
                            ));

                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_qty", 
                              'meta_value' => $saleOrder['quantity']
                            ));

                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_line_subtotal", 
                              'meta_value' => $saleOrder['price']
                            ));

                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_tax_class", 
                              'meta_value' => null
                            ));

                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_line_subtotal", 
                              'meta_value' => $saleOrder['price']
                            ));
                            
                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_line_subtotal_tax", 
                              'meta_value' => 0
                            ));

                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_line_total", 
                              'meta_value' => $saleOrder['price']
                            ));
                            
                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_line_tax", 
                              'meta_value' => 0
                            ));

                            $savedOrderLineMeta = $wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
                              'order_item_id' => $savedOrderLineId,
                              'meta_key' => "_line_tax_data", 
                              'meta_value' => 'a:2:{s:5:"total";a:0:{}s:8:"subtotal";a:0:{}}'
                            ));
                        }
                        
                        header("Content-Type: text/json; charset=utf8");
                        echo json_encode(array("success" => true,"message" => count($result['result'])." Synced Products"));
                        
                    }else{
                        $date = date('Y-m-d H:i:s');
                        $savedProduct = $wpdb->update($wpdb->prefix.'posts', array(
                              'post_status' => 'wc-'.$order['order_status'],
                              'post_title' => $order['document_code'], 
                              'post_name' => $order['document_code'],
                              'post_content' => $order['document_code'],
                              'post_excerpt' => $order['document_code'], 
                              'pinged' => $order['document_code'],
                              'post_content_filtered' => $order['document_code'], 
                              'post_name' => $order['document_code'],
                              'post_content' => $order['document_code'],
                              'post_type' => 'shop_order',

                              'post_date' => $order['shipping_date'],
                              'post_date_gmt' => $date,
                              'post_modified' => $date,
                              'post_modified_gmt' => $date
                            ), array('id'=>$checkIfExists));
                        header("Content-Type: text/json; charset=utf8");
                        echo json_encode(array("success" => true,"message" => count($result['result'])." Synced Products"));
                    }
                }
                header("Content-Type: text/json; charset=utf8");
                echo json_encode(array("success" => true,"message" => count($result['result'])." Synced Customers"));
            }else{
                header("Content-Type: text/json; charset=utf8");
                echo json_encode(array("success" => false,"error" => $result['error_message']));
            }
            
        }else{
            header("Content-Type: text/json; charset=utf8");
            echo json_encode(array("success" => false,"error" => "Invalid Token"));
        }
    }elseif ($edara_option == "sync_orders_to_edara") {

        $orders = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."posts WHERE post_type = 'shop_order' AND post_status <> 'trash' AND post_status <> 'auto-draft'");
        $url = "https://api.edara.io/v2.0/salesOrders";
        foreach ($orders as $order) {
            $orderMeta = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_customer_user' AND post_id =".$order->ID);

            $orderItemIDs = $wpdb->get_results("SELECT order_item_id FROM ".$wpdb->prefix."woocommerce_order_items WHERE order_item_type = 'line_item' AND order_id = '".$order->ID."'");

            $customerMetaID = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_customer_user' AND post_id = '".$order->ID."'");

            $customerID = $wpdb->get_var("SELECT edara_id FROM ".$wpdb->prefix."usermeta WHERE meta_key = 'edara_id' AND meta_value = '".$customerMetaID."'");

            $total = 0;
            $saleOrderLine = [];
            foreach ($orderItemIDs as $orderItemID) {
                $sub_total = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_line_subtotal' AND order_item_id = '".$orderItemID->order_item_id."'");

                $quantity = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_qty' AND order_item_id = '".$orderItemID->order_item_id."'");

                $productMetaID = $wpdb->get_var("SELECT meta_value FROM ".$wpdb->prefix."woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND order_item_id = '".$orderItemID->order_item_id."'");

                $productID = $wpdb->get_var("SELECT sku FROM ".$wpdb->prefix."wc_product_meta_lookup WHERE product_id = '".$productMetaID."'");
                if ($productID) {
                    array_push($saleOrderLine, array('quantity' => $quantity,'price' => $sub_total,'stock_item_id' => $productID));
                    $total+=(double)$sub_total;
                }
            }
            if (count($saleOrderLine) >= 0 || $customerID != NULL) {
                $data = array('customer_id' => $customerID, 'order_status' =>$order->post_status,'document_date' => $order->post_date,'sub_total' => $total,'total_item_discounts' => 0.0, 'taxable' => true, 'tax' => 0, 'warehouse_id' => 1, 'salesOrder_details' => $saleOrderLine);

                $ch = curl_init( $url );
                # Setup request to send json via POST.
                $payload = json_encode($data);
                var_dump($payload);
                $headers = array(
                   "Content-Type: application/json",
                   "Authorization: Bearer ".$edara_accsess_token."",
                );
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
                // curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                # Return response instead of printing.
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                # Send request.
                $result = curl_exec($ch);
                curl_close($ch);
            }
            

        }
        header("Content-Type: text/json; charset=utf8");
        echo json_encode(array("success" => true,"message" => count($products)." Synced Orders"));
    }
    
}else{
    header("Content-Type: text/json; charset=utf8");
    echo json_encode(array("success" => false,"error" => "Please Select option"));
}

?>	
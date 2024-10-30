<?php
set_time_limit(50000000);

use WC_Product;
// $path = $_SERVER['DOCUMENT_ROOT'];
$path = "../../../..";

include_once $path . '/wp-config.php';
include_once $path . '/wp-includes/wp-db.php';
include_once $path . '/wp-includes/pluggable.php';

global $wpdb;
global $wp_rewrite;

$baseUrl = "https://api.edara.io/v2.0/";

$table = $wpdb->prefix."edara_config";
$wpdb->query('Drop TABLE '.$table.'');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_products');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_customers');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_orders');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_taxes');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_currencies');

$edara_accsess_token = $_POST["edara_accsess_token"];//"5ivhDvWwFzgB9nljbwKajWC7BDelZPcZ0odfIfW4hzs=";
$edara_domain = $_POST['edara_domain'];
$edara_email = $_POST['edara_email'];
$products_selection = $_POST['products_selection'];
$customers_selection = $_POST['customers_selection'];
$orders_selection = $_POST['orders_selection'];
$from_date = $_POST['from_date'];
$warehouses_selection = $_POST['warehouses_selection'];
$stores_selection = $_POST['stores_selection'];
$services_selection = $_POST['services_selection'];
$sale_price = $_POST['sale_price'];
$customer_key_selection = $_POST['customers_key'];

$ordersStatusString = $_POST['orders_status'];

// set the default character set and collation for the table
$charset_collate = $wpdb->get_charset_collate();
// Check that the table does not already exist before continuing
$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_config (
id bigint(50) NOT NULL AUTO_INCREMENT,
edara_email varchar(255),
edara_domain varchar(255),
products_selection varchar(255),
customers_selection varchar(255),
orders_selection varchar(255),
from_date varchar(255),
warehouses_selection varchar(255),
service_item varchar(255),
sale_price varchar(255),
edara_accsess_token varchar(255),
orders_status varchar(255),
is_installing INT,
customers_key varchar(255),
setup_date DATETIME,
stores_selection varchar(255),
PRIMARY KEY (id)
) $charset_collate;";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );
$is_error = empty( $wpdb->last_error );

$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_customers (
id bigint(50) NOT NULL AUTO_INCREMENT,
wp_customer varchar(255),
edara_customer varchar(255),
status varchar(255),
PRIMARY KEY (id)
) $charset_collate;";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_products (
id bigint(50) NOT NULL AUTO_INCREMENT,
wp_product varchar(255),
edara_product varchar(255),
status varchar(255),
PRIMARY KEY (id)
) $charset_collate;";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_orders (
id bigint(50) NOT NULL AUTO_INCREMENT,
wp_order varchar(255),
edara_order varchar(255),
status varchar(255),
PRIMARY KEY (id)
) $charset_collate;";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_taxes (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255),
    percent double,
    edara_id int(11),
    PRIMARY KEY (id)
    ) $charset_collate;";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );

$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix."edara_currencies (
    id int(11) NOT NULL AUTO_INCREMENT,
    code varchar(128),
    edara_id int(11),
    PRIMARY KEY (id)
    ) $charset_collate;";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );

$setupDate = date('Y-m-d H:i:s');

$isInstalling = 1;
if($products_selection == 'no'){
    $isInstalling = 0;
}

if ($is_error) {
    // WHERE edara_email = ".$edara_email." AND edara_domain = ".$edara_domain."
    $rows = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."edara_config");

    if (count($rows) == 0) {
        if ($orders_selection == "all_orders") {
            $result = $wpdb->insert($wpdb->prefix.'edara_config', array(
              'edara_email' => $edara_email,
              'edara_domain' => $edara_domain,
              'products_selection' => $products_selection,
              'customers_selection' => $customers_selection,
              'orders_selection' => $orders_selection,
              'from_date' => $from_date,
              'warehouses_selection' => $warehouses_selection,
              'stores_selection' => $stores_selection,
              'service_item' => $services_selection,
              'sale_price' => $sale_price,
              'edara_accsess_token' => $edara_accsess_token,
              'orders_status' => $ordersStatusString,
              'is_installing' => $isInstalling,
              'customers_key' => $customer_key_selection,
              'setup_date' => $setupDate
            ));
            $is_error = empty( $wpdb->last_error );
            if (!$is_error) {
                var_dump($wpdb->last_error);die();
            }
        } else {
            $result = $wpdb->insert($wpdb->prefix.'edara_config', array(
              'edara_email' => $edara_email,
              'edara_domain' => $edara_domain,
              'products_selection' => $products_selection,
              'customers_selection' => $customers_selection,
              'orders_selection' => $orders_selection,
              'warehouses_selection' => $warehouses_selection,
              'stores_selection' => $stores_selection,
              'service_item' => $services_selection,
              'sale_price' => $sale_price,
              'edara_accsess_token' => $edara_accsess_token,
              'orders_status' => $ordersStatusString,
              'is_installing' => $isInstalling,
              'customers_key' => $customer_key_selection,
              'setup_date' => $setupDate
            ));
            $is_error = empty( $wpdb->last_error );
            if (!$is_error) {
                var_dump($wpdb->last_error);die();
            }
        }

        // Function to check if domain is already registered
        function checkIfDomainRegistered($baseUrl, $edara_domain, $websiteDomain, $edara_accsess_token) {
            $url = $baseUrl . "webhooks/FindByListenerDomain?listenerDomain=" . $websiteDomain;

            $ch = curl_init($url);
            $headers = array(
                "EdaraDomain: " . $edara_domain,
                "Authorization: " . $edara_accsess_token
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($result, true);
            return $result;
        }

        // Function to register webhook
        function registerWebhook($baseUrl, $edara_domain, $edara_accsess_token, $webhookBody) {
            $webhookUrl = $baseUrl . "webhooks";

            $ch = curl_init($webhookUrl);
            $headers = array(
                "Content-Type: application/json",
                "EdaraDomain: " . $edara_domain,
                "Authorization: " . $edara_accsess_token
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookBody));

            $webhookResult = curl_exec($ch);
            curl_close($ch);

            $webhookResult = json_decode($webhookResult, true);
            return $webhookResult;
        }

        // Function to update webhook
        function updateWebhook($baseUrl, $edara_domain, $edara_accsess_token, $webhookBody) {
            $webhookUrl = $baseUrl . "webhooks";

            $ch = curl_init($webhookUrl);
            $headers = array(
                "Content-Type: application/json",
                "EdaraDomain: " . $edara_domain,
                "Authorization: " . $edara_accsess_token
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookBody));

            $webhookResult = curl_exec($ch);
            curl_close($ch);

            $webhookResult = json_decode($webhookResult, true);
            return $webhookResult;
        }

        // Main Code
        $websiteDomain = site_url();
        $baseUrl = "https://api.edara.io/v2.0/";

        $webhookBody = array(
            "events" => array(
                array(
                    "observer_filters" => null,
                    "name" => "StockItem_After_Add",
                    "selected" => true,
                    "id" => 21
                ),
                array(
                    "observer_filters" => null,
                    "name" => "StockItem_After_Update",
                    "selected" => true,
                    "id" => 22
                ),
                array(
                    "observer_filters" => null,
                    "name" => "StockItem_After_Delete",
                    "selected" => true,
                    "id" => 23
                ),
                array(
                    "observer_filters" => array(),
                    "name" => "StockItem_Balance_Changed",
                    "selected" => true,
                    "id" => 24
                ),
                array(
                    "observer_filters" => array(),
                    "name" => "StockItem_Reserved_Balance_Changed",
                    "selected" => true,
                    "id" => 52
                ),
                array(
                    "observer_filters" => array(),
                    "name" => "StockItem_Balance_Increased",
                    "selected" => true,
                    "id" => 53
                ),
                array(
                    "observer_filters" => array(),
                    "name" => "StockItem_Balance_Decreased",
                    "selected" => true,
                    "id" => 54
                ),
                array(
                    "observer_filters" => null,
                    "name" => "WorkOrder_After_Add",
                    "selected" => true,
                    "id" => 28
                )
            ),
            "authorization_token" => "",
            "name" => "WooWebhooks",
            "url" => $websiteDomain . "/wp-content/plugins/connect-to-edara/Includes/webhooks.php",
            "id" => null,
            "message_type" => "Default"
        );

        $domainCheckResult = checkIfDomainRegistered($baseUrl, $edara_domain, $websiteDomain, $edara_accsess_token);

        if (isset($domainCheckResult['result']['id'])) {
            // Domain found, update the webhook
            $webhookBody['id'] = $domainCheckResult['result']['id'];
            $response = updateWebhook($baseUrl, $edara_domain, $edara_accsess_token, $webhookBody);
        } else {
            // Domain not found, register the webhook
            $response = registerWebhook($baseUrl, $edara_domain, $edara_accsess_token, $webhookBody);
        }

        if (isset($response['id'])) {
            echo "Webhook created or updated successfully with ID: " . $response['id'];
        } elseif (isset($response['status_code']) && $response['status_code'] == 200) {
            echo "Webhook created or updated successfully.";
        } else {
            echo "Error creating or updating webhook: " . (isset($response['error_message']) ? $response['error_message'] : 'Unknown error');
        }

        $getTaxesUrl = $baseUrl . "Taxes";
        $curl2 = curl_init();
        curl_setopt_array($curl2, array(
            CURLOPT_URL => $getTaxesUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization:' . $edara_accsess_token
            ),
        ));

        $response = curl_exec($curl2);

        curl_close($curl2);
        // echo $response;
        $responseJson = json_decode($response, TRUE);

        if ($responseJson['status_code'] == 200) {
            foreach ($responseJson['result'] as $taxObj) {
                $result = $wpdb->insert($wpdb->prefix . 'edara_taxes', array(
                    'name' => $taxObj['name'],
                    'percent' => $taxObj['rate'],
                    'edara_id' => $taxObj['id']
                ));
            }
        }

        $getCurrenciesUrl = $baseUrl . "currencies?offset=0&limit=1000";
        $curl2 = curl_init();
        curl_setopt_array($curl2, array(
            CURLOPT_URL => $getCurrenciesUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization:' . $edara_accsess_token
            ),
        ));

        $response = curl_exec($curl2);

        curl_close($curl2);
        // echo $response;
        $responseJson = json_decode($response, TRUE);

        if ($responseJson['status_code'] == 200) {
            foreach ($responseJson['result'] as $currencyObj) {
                $result = $wpdb->insert($wpdb->prefix . 'edara_currencies', array(
                    'code' => $currencyObj['international_code'],
                    'edara_id' => $currencyObj['id']
                ));
            }
        }

        return true;

        if ($products_selection == "wp_to_edara") {
            $products = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "posts WHERE post_type = 'product'");

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
                    'Authorization:' . $edara_accsess_token
                ),
            ));

            $response = curl_exec($curl2);

            curl_close($curl2);
            // echo $response;
            $responseJson = json_decode($response, TRUE);

            if ($responseJson['status_code'] == '200') {
                $url = $baseUrl . "stockItems";
                foreach ($products as $product) {
                    $postmeta = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "postmeta WHERE post_id = " . $product->ID . " AND meta_key = '_regular_price'");
                    $price = count($postmeta) > 0 ? (double)$postmeta[0]->meta_value : 0;
                    //--------------- check if exsist link it --------------
                    $skuProduct = get_post_meta($product->ID, '_sku', true);

                    $flag = 0;
                    $edaraId = 0;
                    foreach ($responseJson['result'] as $responseProduct) {
                        if ($skuProduct == $responseProduct['sku']) {
                            $flag = 1;
                            $edaraId = $responseProduct['id'];
                        }
                    }

                    if ($flag == 1) {
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
                            $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                                'wp_product' => $product->ID,
                                'edara_product' => $edaraId,
                                'status' => "linked"
                            ));
                        }
                    } else {
                        if ($sale_price == "sale_price") {
                            $data = array('description' => $product->post_title, 'sku' => $skuProduct, 'price' => $price);
                        } else if ($sale_price == "dealer_price") {
                            $data = array('description' => $product->post_title, 'sku' => $skuProduct, 'dealer_price' => $price);
                        } else {
                            $data = array('description' => $product->post_title, 'sku' => $skuProduct, 'supper_dealer_price' => $price);
                        }
                        $options = array(
                            'http' => array(
                                'header' => "Authorization:" . $edara_accsess_token . "",
                                'method' => 'POST',
                                'content' => http_build_query($data),
                            )
                        );
                        $context = stream_context_create($options);
                        $result = file_get_contents($url, false, $context);
                        $result = json_decode($result, true);
                        if ($result['status_code'] == 200) {
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
                                $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
                                    'wp_product' => $product->ID,
                                    'edara_product' => $result['result'],
                                    'status' => "linked"
                                ));
                            }
                        } else {
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
                                $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_products WHERE wp_product = " . $product->ID . "");
                                if ($dataSelected != NULL) {
                                    $row = $wpdb->update($wpdb->prefix . 'edara_products', array(
                                        'wp_product' => $product->ID,
                                        'edara_product' => "0",
                                        'status' => $result['error_message']
                                    ), array('wp_product' => $product->ID));
                                } else {
                                    $result = $wpdb->insert($wpdb->prefix . 'edara_products', array(
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
        } else if ($products_selection == "edara_to_wp") {
            $url = $baseUrl . "stockItems?limit=10000000&offset=0";
        
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
            $headers = array(
                "Accept: application/json",
                "Authorization:" . $edara_accsess_token . "",
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            //for debug only!
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
            $resp = curl_exec($curl);
            curl_close($curl);
            $edaraResult = json_decode($resp, true);
        
            if ($edaraResult != null && $edaraResult['status_code'] == 200) {
                $charset_collate = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}edara_products (
                    id bigint(50) NOT NULL AUTO_INCREMENT,
                    wp_product varchar(255),
                    edara_product varchar(255),
                    status varchar(255),
                    PRIMARY KEY (id)
                ) $charset_collate;";
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta($sql);
        
                foreach ($edaraResult['result'] as $edaraProduct) {
                    // Start a transaction
                    $wpdb->query('START TRANSACTION');
        
                    try {
                        // Lock the tables for writing
                        $wpdb->query("LOCK TABLES {$wpdb->prefix}edara_products WRITE, {$wpdb->prefix}postmeta WRITE, {$wpdb->prefix}posts WRITE");
        
                        // Check if this product exists in edara_products
                        $checkIfExists = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT wp_product FROM {$wpdb->prefix}edara_products WHERE edara_product = %s",
                                $edaraProduct['id']
                            )
                        );
        
                        if ($checkIfExists == NULL) {
                            // Check if a product with the same SKU exists in WooCommerce
                            $product_id = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key='_sku' AND meta_value=%s LIMIT 1",
                                    $edaraProduct['sku']
                                )
                            );
        
                            if ($product_id) {
                                // Product with the same SKU exists, link it to Edara
                                $result = $wpdb->insert(
                                    $wpdb->prefix . 'edara_products',
                                    array(
                                        'wp_product' => $product_id,
                                        'edara_product' => $edaraProduct['id'],
                                        'status' => "linked"
                                    )
                                );
                            } else {
                                // Create a new product
                                if ($sale_price == "sale_price") {
                                    $price = $edaraProduct['price'];
                                } else if ($sale_price == "dealer_price") {
                                    $price = $edaraProduct['dealer_price'];
                                } else {
                                    $price = $edaraProduct['supper_dealer_price'];
                                }
                                $code = $edaraProduct['code'];
        
                                $productw = new WC_Product();
                                $productw->set_name($edaraProduct['description']);
                                $productw->set_status('publish');
                                $productw->set_catalog_visibility('visible');
                                $productw->set_price($price);
                                $productw->set_regular_price($price);
                                $productw->set_sold_individually(true);
                                $productw->set_downloadable(false);
                                $productw->set_virtual(false);
                                $productw->set_sku($edaraProduct['sku']);
                                $productw->save();
        
                                $lastid = $productw->get_id();
                                if ($lastid) {
                                    $wpdb->update(
                                        $wpdb->prefix . 'posts',
                                        array('menu_order' => $code),
                                        array('ID' => $lastid)
                                    );
        
                                    update_post_meta($lastid, '_sku', $edaraProduct['sku']);
                                    $result = $wpdb->insert(
                                        $wpdb->prefix . 'edara_products',
                                        array(
                                            'wp_product' => $lastid,
                                            'edara_product' => $edaraProduct['id'],
                                            'status' => "linked"
                                        )
                                    );
                                }
                            }
                        } else {
                            // Update existing product details if necessary
                            $date = current_time('mysql');
                            $wpdb->update(
                                $wpdb->prefix . 'posts',
                                array(
                                    'post_title' => $edaraProduct['description'],
                                    'post_name' => $edaraProduct['description'],
                                    'post_content' => $edaraProduct['description'],
                                    'post_excerpt' => $edaraProduct['description'],
                                    'menu_order' => $edaraProduct['code'],
                                    'pinged' => $edaraProduct['description'],
                                    'post_content_filtered' => $edaraProduct['description'],
                                    'post_modified' => $date,
                                    'post_modified_gmt' => get_gmt_from_date($date)
                                ),
                                array('ID' => $checkIfExists)
                            );
        
                            if ($sale_price == "sale_price") {
                                $price = $edaraProduct['price'];
                            } else if ($sale_price == "dealer_price") {
                                $price = $edaraProduct['dealer_price'];
                            } else {
                                $price = $edaraProduct['supper_dealer_price'];
                            }
        
                            $wpdb->update(
                                $wpdb->prefix . 'postmeta',
                                array('meta_value' => $price),
                                array('post_id' => $checkIfExists, 'meta_key' => '_regular_price')
                            );
        
                            update_post_meta($checkIfExists, '_sku', $edaraProduct['sku']);
                        }
        
                        // Commit the transaction
                        $wpdb->query('COMMIT');
                    } catch (Exception $e) {
                        // Rollback the transaction in case of error
                        $wpdb->query('ROLLBACK');
                        error_log('Error processing Edara product: ' . $e->getMessage());
                    } finally {
                        // Unlock the tables
                        $wpdb->query("UNLOCK TABLES");
                    }
                }
            }
        }
        

        $result = $wpdb->update($wpdb->prefix . 'edara_config', array(
            'is_installing' => 0
        ), array('id' => 1));

        $message = $customers_selection;
        file_put_contents(ABSPATH . "initial_setup.log", print_r($message, true), FILE_APPEND);

        if ($customers_selection == "all_customers") {
            // $customers = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."users WHERE ID <> 1");
            $customers = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "wc_customer_lookup");

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
                    'Authorization: ' . $edara_accsess_token
                ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            // echo $response;
            $responseJson = json_decode($response, TRUE);

            file_put_contents(ABSPATH . "initial_setup.log", print_r($edara_accsess_token, true), FILE_APPEND);
            file_put_contents(ABSPATH . "initial_setup.log", print_r($getCustomersUrl, true), FILE_APPEND);
            file_put_contents(ABSPATH . "initial_setup.log", print_r($responseJson, true), FILE_APPEND);

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
                    file_put_contents(ABSPATH . "initial_setup.log", print_r($message, true), FILE_APPEND);

                    if ($flag == 1) {
                        $charset_collate = $wpdb->get_charset_collate();
                        $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_customers (
                        id bigint(50) NOT NULL AUTO_INCREMENT,
                        wp_customer varchar(255),
                        edara_customer varchar(255),
                        status varchar(255),
                        PRIMARY KEY (id)
                        ) $charset_collate;";
                        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                        dbDelta($sql);
                        $is_error = empty($wpdb->last_error);
                        if ($is_error) {
                            $result = $wpdb->insert($wpdb->prefix . 'edara_customers', array(
                                'wp_customer' => $customer->customer_id,
                                'edara_customer' => $edaraId,
                                'status' => "linked"
                            ));
                        } else {
                            var_dump($wpdb->last_error);
                            die();
                        }
                    } else {
                        $id = $customer->customer_id;
                        $name = $customer->first_name . " " . $customer->last_name;
                        $email = $customer->email;

                        $data = array('name' => $name, 'email' => $email, 'payment_type' => 'Credit');

                        $options = array(
                            'http' => array(
                                'header' => "Authorization:" . $edara_accsess_token . "",
                                'method' => 'POST',
                                'content' => http_build_query($data),
                            )
                        );
                        $context = stream_context_create($options);
                        $result = file_get_contents($url, false, $context);
                        $result = json_decode($result, true);
                        if ($result['status_code'] == 200) {
                            $charset_collate = $wpdb->get_charset_collate();
                            $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_customers (
                            id bigint(50) NOT NULL AUTO_INCREMENT,
                            wp_customer varchar(255),
                            edara_customer varchar(255),
                            status varchar(255),
                            PRIMARY KEY (id)
                            ) $charset_collate;";
                            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                            dbDelta($sql);
                            $is_error = empty($wpdb->last_error);
                            if ($is_error) {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_customers', array(
                                    'wp_customer' => $customer->customer_id,
                                    'edara_customer' => $result['result'],
                                    'status' => "linked"
                                ));
                            } else {
                                var_dump($wpdb->last_error);
                                die();
                            }
                        } else {
                            $charset_collate = $wpdb->get_charset_collate();
                            // Check that the table does not already exist before continuing
                            $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "edara_customers (
                            id bigint(50) NOT NULL AUTO_INCREMENT,
                            wp_customer varchar(255),
                            edara_customer varchar(255),
                            status varchar(255),
                            PRIMARY KEY (id)
                            ) $charset_collate;";
                            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                            dbDelta($sql);
                            $is_error = empty($wpdb->last_error);
                            if ($is_error) {
                                $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_customers WHERE wp_customer = " . $customer->ID . "");
                                if ($dataSelected != NULL) {
                                    $row = $wpdb->update($wpdb->prefix . 'edara_customers', array(
                                        'wp_customer' => $customer->customer_id,
                                        'edara_customer' => "0",
                                        'status' => $result['error_message']
                                    ), array('wp_customer' => $customer->customer_id));
                                } else {
                                    $result = $wpdb->insert($wpdb->prefix . 'edara_customers', array(
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
        }
        if ($orders_selection == "all_orders") {
            $url = $baseUrl . "salesOrders";
            $date = strtotime($from_date);
            $from_date = date('Y-m-d 00:00:00', $date);
            $orders = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "posts WHERE post_type = 'shop_order' AND post_status <> 'trash' AND post_status <> 'auto-draft' AND post_date >= '" . $from_date . "'");

            foreach ($orders as $order) {
                $orderMeta = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "postmeta WHERE meta_key = '_customer_user' AND post_id =" . $order->ID);

                $orderItemIDs = $wpdb->get_results("SELECT order_item_id FROM " . $wpdb->prefix . "woocommerce_order_items WHERE order_item_type = 'line_item' AND order_id = '" . $order->ID . "'");

                $customerMetaID = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "postmeta WHERE meta_key = '_customer_user' AND post_id = '" . $order->ID . "'");

                // $customerID = $customerMetaID;
                $customerID = $wpdb->get_var("SELECT edara_customer FROM " . $wpdb->prefix . "edara_customers WHERE wp_customer = " . $customerMetaID);

                $total = 0;
                $saleOrderLine = [];
                foreach ($orderItemIDs as $orderItemID) {
                    $sub_total = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_line_total' AND order_item_id = '" . $orderItemID->order_item_id . "'");

                    $quantity = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_qty' AND order_item_id = '" . $orderItemID->order_item_id . "'");

                    $productMetaID = $wpdb->get_var("SELECT meta_value FROM " . $wpdb->prefix . "woocommerce_order_itemmeta WHERE meta_key = '_product_id' AND order_item_id = '" . $orderItemID->order_item_id . "'");

                    $productID = $wpdb->get_var("SELECT edara_product FROM " . $wpdb->prefix . "edara_products WHERE wp_product = '" . $productMetaID . "'");
                    // var_dump($orderItemID->order_item_id,(double)$sub_total,$quantity,$productMetaID,$productID);die();
                    if ($productID) {
                        array_push($saleOrderLine, array('quantity' => $quantity, 'price' => $sub_total, 'stock_item_id' => $productID));
                        $total += (double)$sub_total;
                    }
                }
                // var_dump($customerID,$total,$saleOrderLine);die();

                if (count($saleOrderLine) >= 0 || $customerID != NULL) {
                    $orderOb = new WC_Order((int)$order->ID);
                    $data = array('customer_id' => $customerID, 'order_status' => $order->post_status, 'document_date' => $order->post_date, 'sub_total' => $orderOb->get_total(), 'total_item_discounts' => 0.0, 'taxable' => true, 'tax' => 0, 'warehouse_id' => $warehouses_selection, 'salesOrder_details' => $saleOrderLine);

                    $ch = curl_init($url);
                    # Setup request to send json via POST.
                    $payload = json_encode($data);
                    // var_dump($payload);
                    $headers = array(
                        "Content-Type: application/json",
                        "Authorization:" . $edara_accsess_token . "",
                    );
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    // curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                    # Return response instead of printing.
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    # Send request.
                    $result = curl_exec($ch);
                    curl_close($ch);
                    $result = json_decode($result, true);

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
                            $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                'wp_order' => $order->ID,
                                'edara_order' => $result['result'],
                                'status' => "linked"
                            ));
                        }
                    } else if ($result['status_code'] == 500 && $result['error_message'] == "the specified Documents_Warehouse not exist." || $result['status_code'] == 400 && $result['error_message'] == "Bad Request. DupplicatedCode: Exception of type 'Edara.EdaraBusinessSuite.CommonBusinessLogicLayer.BusinessLogicException' was thrown.") {
                        $data = array('paper_number' => $order->ID, 'customer_id' => $customerID, 'order_status' => $order->post_status, 'document_date' => $order->post_date, 'sub_total' => $total, 'total_item_discounts' => 0.0, 'taxable' => true, 'tax' => 0, 'salesstore_id' => $warehouses_selection, 'salesOrder_details' => $saleOrderLine);
                        $order_id = $order->ID;
                        $ch = curl_init($url);
                        # Setup request to send json via POST.
                        $payload = json_encode($data);
                        //var_dump($data);die();
                        $headers = array(
                            "Content-Type: application/json",
                            "Authorization:" . $edara_accsess_token . "",
                        );
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                        // curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                        # Return response instead of printing.
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        # Send request.
                        $result = curl_exec($ch);
                        curl_close($ch);
                        $result = json_decode($result, true);
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
                                $dataSelected = $wpdb->get_var("SELECT edara_order FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id . "");
                                if ($dataSelected) {
                                    if ($dataSelected == 0) {
                                        $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                            'edara_order' => $result['result'],
                                            'status' => $result['error_message']
                                        ), array('wp_order' => $order_id));
                                    } else {
                                        $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                            'status' => "linked"
                                        ), array('wp_order' => $order_id));
                                    }
                                } else {
                                    $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                        'wp_order' => $order_id,
                                        'edara_order' => $result['result'],
                                        'status' => "linked"
                                    ));
                                }
                            }
                        } else {
                            $charset_collate = $wpdb->get_charset_collate();
                            // Check that the table does not already exist before continuing
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
                                $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order_id . "");
                                if ($dataSelected != NULL) {
                                    $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                        'wp_order' => $order_id,
                                        'edara_order' => "0",
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
                        }
                    } else {
                        $charset_collate = $wpdb->get_charset_collate();
                        // Check that the table does not already exist before continuing
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
                            $dataSelected = $wpdb->get_var("SELECT id FROM " . $wpdb->prefix . "edara_orders WHERE wp_order = " . $order->ID . "");
                            if ($dataSelected != NULL) {
                                $row = $wpdb->update($wpdb->prefix . 'edara_orders', array(
                                    'wp_order' => $order->ID,
                                    'edara_order' => "0",
                                    'status' => $result['error_message']
                                ), array('wp_order' => $order->ID));
                            } else {
                                $result = $wpdb->insert($wpdb->prefix . 'edara_orders', array(
                                    'wp_order' => $order->ID,
                                    'edara_order' => 0,
                                    'status' => $result['error_message']
                                ));
                            }
                        }
                    }
                }
            }
        }
        if ($orders_selection == "new_orders") {
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
        }
        return true;
    } else {
        if ($orders_selection == "all_orders") {
            $result = $wpdb->update($wpdb->prefix . 'edara_config', array(
                'edara_email' => $edara_email,
                'edara_domain' => $edara_domain,
                'products_selection' => $products_selection,
                'customers_selection' => $customers_selection,
                'orders_selection' => $orders_selection,
                'from_date' => $from_date,
                'warehouses_selection' => $warehouses_selection,
                'service_item' => $services_selection,
                'sale_price' => $sale_price,
                'edara_accsess_token' => $edara_accsess_token,
                'is_installing' => 0
            ), array('id' => 1));
        } else {
            $result = $wpdb->update($wpdb->prefix . 'edara_config', array(
                'edara_email' => $edara_email,
                'edara_domain' => $edara_domain,
                'products_selection' => $products_selection,
                'customers_selection' => $customers_selection,
                'orders_selection' => $orders_selection,
                'warehouses_selection' => $warehouses_selection,
                'service_item' => $services_selection,
                'sale_price' => $sale_price,
                'edara_accsess_token' => $edara_accsess_token,
                'is_installing' => 0
            ), array('id' => 1));
        }
    }

    return true;
} else {
    var_dump($wpdb->last_error);
    die();
}
?>

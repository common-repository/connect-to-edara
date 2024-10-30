<?php
// $path = $_SERVER['DOCUMENT_ROOT'];
// $path = "../../../..";

// include_once $path . '/wp-config.php';
// include_once $path . '/wp-includes/wp-db.php';
// include_once $path . '/wp-includes/pluggable.php';

$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
require_once( $path . 'wp-load.php' );

global $wpdb;

if (isset($_POST['tab'])) {
  $page = isset($_POST['page']) ? $_POST['page']-1 : 0;

  $offset = $page * 100;

  switch ($_POST['tab']) {
    case 'Products':
      $response = array();
      $productsArray = array();

      //Check wpml
      $tableName = $wpdb->prefix."icl_translations";
      $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName));
      if($wpdb->get_var($query) == $tableName){
        $products = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."posts INNER JOIN ".$wpdb->prefix."icl_translations ON ".$wpdb->prefix."posts.ID=".$wpdb->prefix."icl_translations.element_id AND ".$wpdb->prefix."icl_translations.source_language_code IS NULL AND ".$wpdb->prefix."posts.post_type = 'product' ORDER BY ID DESC LIMIT ".$offset.",100");
        $count = $wpdb->get_var( "SELECT COUNT(id) AS count FROM ".$wpdb->prefix."posts INNER JOIN ".$wpdb->prefix."icl_translations ON ".$wpdb->prefix."posts.ID=".$wpdb->prefix."icl_translations.element_id AND ".$wpdb->prefix."icl_translations.source_language_code IS NULL AND ".$wpdb->prefix."posts.post_type = 'product' ORDER BY ID DESC LIMIT 0,100");
      }else{
        $products = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."posts WHERE post_type = 'product' ORDER BY id DESC LIMIT ".$offset.",100");
        $count = $wpdb->get_var( "SELECT COUNT(id) AS count FROM ".$wpdb->prefix."posts WHERE post_type = 'product'");
      }

      foreach($products as $value){
        $product = wc_get_product((int)$value->ID);

        $tempArray = array();

        $edaraProductId = NULL;
        $edaraStatus = NULL;

        $edara_product = $wpdb->get_row( "SELECT * FROM ".$wpdb->prefix."edara_products WHERE wp_product = " . $value->ID);
        if($edara_product){
            $edaraProductId = $edara_product->edara_product;
            $edaraStatus = $edara_product->status;
        }else{
            $englishId = apply_filters( 'wpml_object_id', $value->ID , 'product', FALSE, 'en' );
            $edara_product = $wpdb->get_row( "SELECT * FROM ".$wpdb->prefix."edara_products WHERE wp_product = " . $value->ID);
            if($edara_product){
                $edaraProductId = $edara_product->edara_product;
                $edaraStatus = $edara_product->status;
            }
        }

        $tempArray['id'] = $value->ID;
        // $tempArray['edara_id'] = $value->edara_product;
        $tempArray['edara_id'] = $edaraProductId;
        $tempArray['wp_id'] = $value->ID;
        $tempArray['name'] = $product->get_name();
        $tempArray['sku'] = get_post_meta( $value->ID, '_sku', true );
        // $tempArray['status'] = $value->status;
        $tempArray['status'] = $edaraStatus;

        $productsArray[] = $tempArray;
      }

      $response['current_page'] = $page + 1;
      $response['last_page'] = ceil($count/100);
      $response['products'] = $productsArray;
      echo json_encode($response);

    //   $products = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."edara_products ORDER BY id DESC LIMIT ".$offset.",100");
    //   $count = $wpdb->get_var( "SELECT COUNT(id) AS count FROM ".$wpdb->prefix."edara_products");
    //   foreach ($products as $value) {
    //     if ($value->edara_product == NULL) {
    //       continue;
    //     }
    //     $product = wc_get_product((int)$value->wp_product);

    //     if(!$product){
    //       continue;
    //     }

    //     $tempArray = array();

    //     $tempArray['id'] = $value->id;
    //     $tempArray['edara_id'] = $value->edara_product;
    //     $tempArray['wp_id'] = $value->wp_product;
    //     $tempArray['name'] = $product->get_name();
    //     $tempArray['sku'] = get_post_meta( $value->wp_product, '_sku', true );
    //     $tempArray['status'] = $value->status;

    //     $productsArray[] = $tempArray;

    //   }

    //   $response['current_page'] = $page + 1;
    //   $response['last_page'] = ceil($count/100);
    //   $response['products'] = $productsArray;
    //   echo json_encode($response);
      break;

    case 'Customers':

      $response = array();
      
      $customersArray = array();
  
      $customers = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "wc_customer_lookup ORDER BY customer_id DESC LIMIT " . $offset . ",100");
  
      $count = $wpdb->get_var("SELECT COUNT(customer_id) AS count FROM " . $wpdb->prefix . "wc_customer_lookup");
  
      foreach ($customers as $value) {
          $username = $value->first_name . " " . $value->last_name;
          $email = $value->email;
  
          $edaraCustomerId = NULL;
          $edaraStatus = NULL;
  
          $edara_customer = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "edara_customers WHERE wp_customer = " . $value->customer_id);
  
          if ($edara_customer) {
              $edaraCustomerId = $edara_customer->edara_customer;
              $edaraStatus = $edara_customer->status;
          }
  
          // Initialize billing phone
          $billingPhone = "";
  
          // Fetch billing phone from latest order associated with customer email if the email is not empty
          if (!empty($email)) {
              $billingPhone = $wpdb->get_var($wpdb->prepare(
                  "SELECT phone 
                    FROM {$wpdb->prefix}wc_order_addresses 
                    WHERE email = %s 
                    AND address_type = 'billing'
                    ORDER BY order_id DESC 
                    LIMIT 1", 
                  $email
              ));
          }
  
          $tempArray = array();
          $tempArray['id'] = $value->customer_id;
          $tempArray['username'] = $username;
          $tempArray['billing_phone'] = $billingPhone;
          $tempArray['email'] = $email;
          $tempArray['edara_customer_id'] = $edaraCustomerId;
          $tempArray['wp_customer_id'] = $value->customer_id;
          $tempArray['status'] = $edaraStatus;
  
          $customersArray[] = $tempArray;
      }
  
      $response['current_page'] = $page + 1;
      $response['last_page'] = ceil($count / 100);
      $response['customers'] = $customersArray;
  
      echo json_encode($response);

    break;

    case 'Orders':
      $response = array();
      $ordersArray = array();
      $Orders = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_order_items GROUP BY order_id ORDER BY order_item_id DESC LIMIT ".$offset.",100");
      $count = $wpdb->get_var( "SELECT COUNT(DISTINCT order_id) AS count FROM ".$wpdb->prefix."woocommerce_order_items");

      foreach ($Orders as $value) {
        $edara_order = $wpdb->get_var( "SELECT edara_order FROM ".$wpdb->prefix."edara_orders WHERE wp_order = " . $value->order_id);
        $edaraStatus = $wpdb->get_var( "SELECT status FROM ".$wpdb->prefix."edara_orders WHERE wp_order = " . $value->order_id);
         if ($edara_order == NULL) {
           // continue;
         }
         if ($value->order_id == NULL) {
           continue;
         }
         try {
           $order = new WC_Order( (int)$value->order_id );
         } catch (\Exception $e) {
           continue;
         }

         $orderData = $order->get_user();
         $date = $order->get_date_created();

         $tempArray = array();

         $tempArray['id'] = $value->order_id;
         $tempArray['order_number'] = $order->get_order_number();
         $tempArray['date'] = $date ? $date->date("Y-m-d H:i:s") : "";
         $tempArray['payment_method'] = $order->get_payment_method();
         $tempArray['name'] = $order->get_data()['billing']['first_name'] . " " . $order->get_data()['billing']['last_name'];
         $tempArray['formated_order'] = $order->get_formatted_order_total();
         $tempArray['edara_order_id'] = $edara_order;
         $tempArray['status'] = $edaraStatus;

         $ordersArray[] = $tempArray;
      }

      $response['current_page'] = $page + 1;
      $response['last_page'] = ceil($count/100);
      $response['orders'] = $ordersArray;

      echo json_encode($response);
      break;
    case 'Dasboard':
      $edara_accsess_token = $wpdb->get_var("SELECT edara_accsess_token FROM ".$wpdb->prefix."edara_config WHERE id = 1");

      $isInstalling = 0;
      $allProducts = 0;
      $currentProducts = 0;
      $allCustomers = 0;
      $currentCustomers = 0;

      $baseUrl = "https://api.edara.io/v2.0/";

      $products_selection = $wpdb->get_var("SELECT products_selection FROM ".$wpdb->prefix."edara_config WHERE id = 1");

      if($products_selection == 'wp_to_edara'){
        $allProducts = $wpdb->get_var("SELECT COUNT(ID) as count FROM ".$wpdb->prefix."posts WHERE post_type = 'product'");
      }else{
        $getProductsUrl = $baseUrl . "stockItems?offset=0&limit=1";
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

        $allProducts = $responseJson['total_count'];
      }

      $currentProducts = $wpdb->get_var("SELECT COUNT(id) as count FROM ".$wpdb->prefix."edara_products");

      $getCustomersUrl = $baseUrl . "customers?Offset=0&limit=1";
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

      // $allCustomers = $responseJson['total_count'];
      $allCustomers = $wpdb->get_var("SELECT COUNT(customer_id) as count FROM ".$wpdb->prefix."wc_customer_lookup");
      $currentCustomers = $wpdb->get_var("SELECT COUNT(id) as count FROM ".$wpdb->prefix."edara_customers");

      $isInstalling = $wpdb->get_var("SELECT is_installing FROM ".$wpdb->prefix."edara_config WHERE id = 1");

      if($products_selection == 'no'){
        $allProducts = 1;
        $currentProducts = 1;
      }

      $responseArray = array();
      $responseArray['allProducts'] = $allProducts;
      $responseArray['currentProducts'] = $currentProducts;
      $responseArray['allCustomers'] = $allCustomers;
      $responseArray['currentCustomers'] = $currentCustomers;
      $responseArray['isInstalling'] = $isInstalling;

      echo json_encode($responseArray);

      break;
    default:
      // code...
      break;
  }
}

?>
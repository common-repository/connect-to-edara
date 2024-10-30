<?php

$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
require_once( $path . 'wp-load.php' );

global $wpdb;

      $base_url = home_url();
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->base_prefix.'edara_config';
      $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );

      if ( $wpdb->get_var( $query ) == $table_name ) {
         $dataArr = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."edara_config");
         if (count($dataArr) > 0) {
            $wpdb->delete( $wpdb->prefix."edara_products", array( 'wp_product' => NULL) );
         ?>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
      <link rel="stylesheet" href="<?php echo plugins_url('../assets/main_styles.css', __FILE__); ?>">
      
      <body>
         <div class="tab">
            <button class="tablinks" onclick="openCity(event, 'Dasboard','1')" id="defaultOpen">Dasboard</button>
            <button class="tablinks" onclick="openCity(event, 'Products','1')">Products</button>
            <button class="tablinks" onclick="openCity(event, 'Customers','1')">Customers</button>
            <button class="tablinks" onclick="openCity(event, 'Orders','1')">Orders</button>
            <button class="tablinks" onclick="logout()" style="float:right;">Logout</button>
         </div>

         <div id="Dasboard" class="tabcontent">
            <span onclick="this.parentElement.style.display='none'" class="topright">&times</span>
            <h3>Dasboard</h3>
            <p></p>
         </div>

         <div id="Products" class="tabcontent">
            <span onclick="this.parentElement.style.display='none'" class="topright">&times</span>
            <h3>Products</h3>
            <table id="productsTable">
               <tr style="position: sticky;top: 0px;background: #eee;">
                  <th>ID</th>
                  <th>Product</th>
                  <th>SKU</th>
                  <th>Product Id in Edara</th>
                  <th>Status</th>
                  <th>Sync</th>
               </tr>
            <?php
               $products = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."edara_products ORDER BY id DESC LIMIT 0");
               foreach ($products as $value) {
                  if ($value->edara_product == NULL) {
                  break;
                  }
                  $product = wc_get_product( (int)$value->wp_product );
                  ?>
                  <tr>
                  <td><?=$value->id?></td>
                  <?php
                  if (!is_bool($product)) {
                     echo "<td>".$product->get_name()."</td>";
                  }else{
                  echo "<td>".$value->id."</td>";
                  }
                  ?>
                  <td><?=get_post_meta( $value->wp_product, '_sku', true )?></td>
                  <td><?=$value->edara_product?></td>
                  <td id="product<?=$value->wp_product?>"><?=$value->status?></td>
                  <td>
                  <?php if($value->status != 'linked') { ?>
                  <button onclick="ReSync('<?=$value->wp_product?>', 'product',this)">ReSync</button>
                  <?php }?>

                  </td>
                  </tr>
               <?php }
            ?>
            </table>
            <div id="productsPagesNav" style="margin-top: 10px;text-align: center;"></div>
         </div>

      <div id="Customers" class="tabcontent">
        <span onclick="this.parentElement.style.display='none'" class="topright">&times</span>
        <h3>Customers</h3>

        <table id="customersTable">
           <tr style="position: sticky;top: 0px;background: #eee;">
             <th>ID</th>
             <th>Customer</th>
             <th>Phone</th>
             <th>Email</th>
             <th>Customer Id in Edara</th>
             <th>Status</th>
             <th>Sync</th>
           </tr>
        <?php
         $customers = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."edara_customers ORDER BY id DESC LIMIT 0");
         foreach ($customers as $value) {
            if ($value->edara_customer == NULL) {
              break;
            }
            $customer = new WC_Customer( (int)$value->wp_customer );
            $username     = $customer->get_username();
            ?>
            <tr>
             <td><?=$value->id?></td>
             <td><?=$username?></td>
             <td><?=$customer->get_billing_phone()?></td>
             <td><?=$customer->get_email()?></td>
             <td><?=$value->edara_customer?></td>
             <td id="customer<?=$value->wp_customer?>"><?=$value->status?></td>
             <td>
              <?php if($value->status != 'linked') { ?>
              <button onclick="ReSync('<?=$value->wp_customer?>', 'customer',this)">ReSync</button>
            <?php }?>

              </td>
            </tr>
         <?php }
        ?>
      </table>
      <div id="customersPagesNav" style="margin-top: 10px;text-align: center;">

      </div>
      </div>

      <div id="Orders" class="tabcontent">
        <span onclick="this.parentElement.style.display='none'" class="topright">&times</span>
        <h3>Orders</h3>
        <table id="ordersTable">
           <tr style="position: sticky;top: 0px;background: #eee;">
             <th>ID</th>

             <th>Order Number</th>
             <th>Date</th>
             <th>Payment Status</th>
             <th>Customer Name</th>
             <th>Order Total</th>

             <th>Order Id in Edara</th>
             <th>Status</th>
             <th>Sync</th>
           </tr>
        <?php
         // $Orders = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."edara_orders");
         $Orders = $wpdb->get_results( "SELECT * FROM ".$wpdb->prefix."woocommerce_order_items GROUP BY order_id ORDER BY order_item_id DESC LIMIT 0");
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
            ?>
            <tr>
             <td><?=$value->order_id?></td>

             <td><?=$order->get_order_number()?></td>
             <td><?=$date->date("Y-m-d H:i:s")?></td>
             <td><?=$order->get_payment_method()?></td>
             <!--<td><?=$orderData->user_nicename?></td>-->
             <td><?php print_r($order->get_data()['billing']['first_name'] . " " . $order->get_data()['billing']['last_name']) ?></td>
             <td><?=$order->get_formatted_order_total()?></td>

             <td><?=$edara_order?></td>
             <td id="order<?=$value->order_id?>"><?=$edaraStatus?></td>
             <td>
              <?php if($edaraStatus != 'linked') { ?>
              <button onclick="ReSync('<?=$value->order_id?>', 'order',this)">ReSync</button>
            <?php }?>

              </td>
            </tr>
         <?php }
        ?>
      </table>
      <div id="ordersPagesNav" style="margin-top: 10px;text-align: center;">

      </div>
      </div>
      <script src='https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.2.1.js'></script>
      <script>

         var site_base_url = '<?php echo $base_url; ?>';

         var plugin_url = '<?php echo plugins_url('/', dirname(__FILE__)); ?>';

         function logout() {
           window.location.href = plugin_url + "/Includes/logout.php";
         }

         function ReSync(id,type,button){
            button.innerHTML = '<i class="fa fa-refresh fa-spin"></i>';
            button.disabled = true;

            $.ajax({
               type: 'post',
               url: plugin_url + '/Includes/reSync.php',
               data: {
                  'type': type,
                  'id': id
               },
               success: function (message) {
                  document.getElementById(type+id).innerHTML = message;
                  button.remove();
                  // alert('form was submitted');
               }
            });
         }

         function openCity(evt, cityName,page) {
           var i, tabcontent, tablinks;
           tabcontent = document.getElementsByClassName("tabcontent");
           for (i = 0; i < tabcontent.length; i++) {
             tabcontent[i].style.display = "none";
           }
           tablinks = document.getElementsByClassName("tablinks");
           for (i = 0; i < tablinks.length; i++) {
             tablinks[i].className = tablinks[i].className.replace(" active", "");
           }
           document.getElementById(cityName).style.display = "block";
           evt.currentTarget.className += " active";

           $.ajax({
             type: 'post',
             url: plugin_url + '/api/loadTab.php',
             data: {
                  'tab': cityName,
                  'page': page
              },
             success: function (message) {
               // console.log(message);
              var dataJson = JSON.parse(message);
              var html = '';
              switch (cityName) {
                case 'Products':
                  var table = document.getElementById("productsTable");
                  for(var i = 1;i<table.rows.length;){
                      table.deleteRow(i);
                  }
                  dataJson['products'].forEach(element => {
                    html += '<tr><td>'
                         + element['id']
                         + '</td><td>'
                         + element['name']
                         + '</td><td>'
                         + element['sku']
                         + '</td><td>'
                         + element['edara_id']
                         + '</td><td id="product'+element['wp_id']+'">'
                         + element['status'];

                    if (element['status'] != 'linked') {
                      var reSyncProductOnClick = "ReSync('"+element['id']+"','product',this)";

                      html += '</td><td>'
                           + '<button onclick='+reSyncProductOnClick+'>ReSync</button>'
                           + '</td></tr>';
                    }else {
                        html += '</td><td></td></tr>';
                    }
                  });
                  $('#productsTable').append(html);

                  var pagesHtml = '';
                  for (var i = 1; i <= dataJson['last_page']; i++) {

                    var onClick = "openCity(event,'"+cityName+"','"+i+"')";
                    var disabled = "";
                    if (i == page) {
                      disabled = "disabled";
                    }
                    pagesHtml += '<button style="margin-right:2px" type="button" '+disabled+' onclick="'+onClick+'" name="button">'+i+'</button>';
                  }
                  $('#productsPagesNav').empty();
                  $('#productsPagesNav').append(pagesHtml);
                  break;
                case 'Customers':
                  var table = document.getElementById("customersTable");
                  for(var i = 1;i<table.rows.length;){
                      table.deleteRow(i);
                  }
                  dataJson['customers'].forEach(element => {
                    html += '<tr><td>'
                         + element['id']
                         + '</td><td>'
                         + element['username']
                         + '</td><td>'
                         + element['billing_phone']
                         + '</td><td>'
                         + element['email']
                         + '</td><td>'
                         + element['edara_customer_id']
                         + '</td><td id="customer'+element['wp_customer_id']+'">'
                         + element['status'];

                    if (element['status'] != 'linked') {
                      var reSyncCustomerOnClick = "ReSync('"+element['id']+"','customer',this)";

                      html += '</td><td>'
                           + '<button onclick='+reSyncCustomerOnClick+'>ReSync</button>'
                           + '</td></tr>';
                    }else {
                        html += '</td><td></td></tr>';
                    }
                  });
                  $('#customersTable').append(html);

                  var pagesHtml = '';
                  for (var i = 1; i <= dataJson['last_page']; i++) {

                    var onClick = "openCity(event,'"+cityName+"','"+i+"')";
                    var disabled = "";
                    if (i == page) {
                      disabled = "disabled";
                    }
                    pagesHtml += '<button style="margin-right:2px" type="button" '+disabled+' onclick="'+onClick+'" name="button">'+i+'</button>';
                  }
                  $('#customersPagesNav').empty();
                  $('#customersPagesNav').append(pagesHtml);
                  break;
                case 'Orders':
                  var table = document.getElementById("ordersTable");
                  for(var i = 1;i<table.rows.length;){
                      table.deleteRow(i);
                  }
                  dataJson['orders'].forEach(element => {
                    html += '<tr><td>'
                         + element['id']
                         + '</td><td>'
                         + element['order_number']
                         + '</td><td>'
                         + element['date']
                         + '</td><td>'
                         + element['payment_method']
                         + '</td><td>'
                         + element['name']
                         + '</td><td>'
                         + element['formated_order']
                         + '</td><td>'
                         + element['edara_order_id']
                         + '</td><td id="order'+element['id']+'">'
                         + element['status'];

                    if (element['status'] != 'linked') {
                      var reSyncOrderOnClick = "ReSync('"+element['id']+"','order',this)";

                      html += '</td><td>'
                           + '<button onclick='+reSyncOrderOnClick+'>ReSync</button>'
                           + '</td></tr>';
                    }else {
                        html += '</td><td></td></tr>';
                    }
                  });
                  $('#ordersTable').append(html);

                  var pagesHtml = '';
                  for (var i = 1; i <= dataJson['last_page']; i++) {

                    var onClick = "openCity(event,'"+cityName+"','"+i+"')";
                    var disabled = "";
                    if (i == page) {
                      disabled = "disabled";
                    }
                    pagesHtml += '<button style="margin-right:2px" type="button" '+disabled+' onclick="'+onClick+'" name="button">'+i+'</button>';
                  }
                  $('#ordersPagesNav').empty();
                  $('#ordersPagesNav').append(pagesHtml);
                  break;
               case 'Dasboard':
                  var html = "";
                  html += '<span onclick="this.parentElement.style.display=none" class="topright">&times</span>';
                  html += "<h3>Dashboard</h3>";
                  
                  var allProducts = dataJson['allProducts'];
                  var currentProducts = dataJson['currentProducts'];
                  var allCustomers = dataJson['allCustomers'];
                  var currentCustomers = dataJson['currentCustomers'];

                  var productsPercent = (currentProducts / allProducts) * 100;
                  var customersPercent = (currentCustomers / allCustomers) * 100;

                  if(productsPercent < 100){
                     html += '<div><div style="border: 1px solid #999;padding: 10px;margin-bottom: 10px;"><h2 style="margin-top: 5px;margin-bottom: 5px;font-size: 17px;">Importing stockitems from edara</h2><div style="color: #999;font-size: 13px;">Imported <span id="currentProductsSpan">'+currentProducts+'</span>/<span id="allProductsSpan">'+allProducts+'</span></div>';
                     html += '<div style="width: 100%;background-color: #e0e0e0;padding: 3px;border-radius: 3px;box-shadow: inset 0 1px 3px rgba(0, 0, 0, .2);margin-top: 20px;"><span id="productsBarSpan" style="display: block;height: 10px;background-color: #659cef;border-radius: 3px;width: '+productsPercent+'%;"></span></div>';
                     html += '<button id="continueSyncProductsButton" onclick="continueSync(this,1);">Sync</button>'
                     html += '</div></div>';
                  }

                  if(customersPercent < 100){
                     html += '<div><div style="border: 1px solid #999;padding: 10px;"><h2 style="margin-top: 5px;margin-bottom: 5px;font-size: 17px;">Importing customers from edara</h2><div style="color: #999;font-size: 13px;">Imported <span id="currentCustomersSpan">'+currentCustomers+'</span>/<span id="allCustomersSpan">'+allCustomers+'</span></div>';
                     html += '<div style="width: 100%;background-color: #e0e0e0;padding: 3px;border-radius: 3px;box-shadow: inset 0 1px 3px rgba(0, 0, 0, .2);margin-top: 20px;"><span id="customersBarSpan" style="display: block;height: 10px;background-color: #659cef;border-radius: 3px;width: '+customersPercent+'%;"></span></div>';
                     html += '<button id="continueSyncCustomersButton" onclick="continueSync(this,2);">Sync</button>'
                     html += '</div></div>';
                  }
                  
                  $('#Dasboard').empty();
                  $('#Dasboard').append(html);

                  if(dataJson['isInstalling'] == '1'){
                     document.getElementById("continueSyncProductsButton").click();
                  }

                  break;
              }
             }
           });
         }

         function continueSync(button,type){
            button.innerHTML = '<i class="fa fa-refresh fa-spin"></i>';
            button.disabled = true;

            $.ajax({
               type: 'post',
               url: plugin_url + '/api/continueSync.php',
               data: {
                     'type': type
                  },
                  beforeSend: function(){

                  },
                  complete: function(){

                  },
                  error: function (xhr, status, error) {
                     finishSetup();

                     console.log(xhr);
                     console.log(status);
                     console.log(error);
                  },
               success: function (message) {
                  finishSetup();
               }
            });
         }

         function finishSetup(){
            $.ajax({
               type: 'post',
               url: plugin_url + '/api/finishSetup.php',
               data: {
                     'type': 1
                  },
                  beforeSend: function(){

                  },
                  complete: function(){

                  },
                  error: function (xhr, status, error) {
                     console.log(xhr);
                     console.log(status);
                     console.log(error);
                  },
               success: function (message) {
                  var dataJson = JSON.parse(message);
                  
                  var allProducts = document.getElementById("allProductsSpan").innerHTML;
                  var currentProducts = dataJson['currentProducts'];
                  var allCustomers = document.getElementById("allCustomersSpan").innerHTML;
                  var currentCustomers = dataJson['currentCustomers'];

                  document.getElementById("currentProductsSpan").innerHTML = currentProducts;
                  document.getElementById("currentCustomersSpan").innerHTML = currentCustomers;

                  var productsPercent = (currentProducts / allProducts) * 100;
                  var customersPercent = (currentCustomers / allCustomers) * 100;

                  if(productsPercent > 100){
                     productsPercent = 100;

                     document.getElementById("continueSyncProductsButton").innerHTML = "Completed";
                     document.getElementById("continueSyncProductsButton").disabled = true;
                  }else{
                     document.getElementById("continueSyncProductsButton").innerHTML = "Sync";
                     document.getElementById("continueSyncProductsButton").disabled = false;
                  }

                  if(customersPercent > 100){
                     customersPercent = 100;

                     document.getElementById("continueSyncCustomersButton").innerHTML = "Completed";
                     document.getElementById("continueSyncCustomersButton").disabled = true;
                  }else{
                     document.getElementById("continueSyncCustomersButton").innerHTML = "Sync";
                     document.getElementById("continueSyncCustomersButton").disabled = false;
                  }

                  document.getElementById("productsBarSpan").style.width = productsPercent + "%";
                  document.getElementById("customersBarSpan").style.width = customersPercent + "%";
               }
            });
         }

         // Get the element with id="defaultOpen" and click on it
         document.getElementById("defaultOpen").click();
         </script>

                  <?php
               return true;
               }

              }

      ?>
      <head>
         <style type="text/css">@font-face{font-family:'Material Icons';font-style:normal;font-weight:400;src:url(https://fonts.gstatic.com/s/materialicons/v118/flUhRq6tzZclQEJ-Vdg-IuiaDsNa.woff) format('woff');}.material-icons{font-family:'Material Icons';font-weight:normal;font-style:normal;font-size:24px;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;font-feature-settings:'liga';}@font-face{font-family:'Material Icons';font-style:normal;font-weight:400;src:url(https://fonts.gstatic.com/s/materialicons/v118/flUhRq6tzZclQEJ-Vdg-IuiaDsNcIhQ8tQ.woff2) format('woff2');}.material-icons{font-family:'Material Icons';font-weight:normal;font-style:normal;font-size:24px;line-height:1;letter-spacing:normal;text-transform:none;display:inline-block;white-space:nowrap;word-wrap:normal;direction:ltr;-webkit-font-feature-settings:'liga';-webkit-font-smoothing:antialiased;}</style>
         <meta charset="utf-8">
         <title>Edara-App</title>
         <base href="/">
         <meta name="viewport" content="width=device-width, initial-scale=1">
         <link rel="icon" type="image/x-icon" href="favicon.ico">
         <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
         <meta name="theme-color" content="#1976d2">
         <style media="screen">
         .LockOn {
               display: block;
               visibility: visible;
               position: absolute;
               z-index: 999;
               top: 0px;
               left: 0px;
               width: 105%;
               height: 105%;
               background-color:white;
               vertical-align:bottom;
               padding-top: 20%;
               filter: alpha(opacity=75);
               opacity: 0.75;
               font-size:large;
               color:blue;
               font-style:italic;
               font-weight:400;
               background-image: url('<?php echo plugins_url('../assets/Loading_Gif.gif', __FILE__); ?>');
               background-repeat: no-repeat;
               background-attachment: fixed;
               background-position: center;
            }
            #loading {
            position: absolute;
            display: block;
            left: 0;
            right: 0;
            margin-left: auto;
            margin-right: auto;
            z-index: 1;
            -webkit-transform: translate3d(0, -50%, 0);
            -moz-transform: translate3d(0, -50%, 0);
            -o-transform: translate3d(0, -50%, 0);
            -ms-transform: translate3d(0, -50%, 0);
            transform: translate3d(0, -50%, 0);
            text-align: center;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            top: 50%;
            }
         </style>
         <link rel="stylesheet" href="https://shopi-intg.edara.io/styles.dfa7fb8853673e5015b3.css">
         <link rel="stylesheet" href="<?php echo plugins_url('../assets/styles.css', __FILE__); ?>">
         <link rel="stylesheet" href="<?php echo plugins_url('../assets/main_styles.css', __FILE__); ?>">
         <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
         <script charset="utf-8" src="https://shopi-intg.edara.io/5.6709503baf30a766ed73.js"></script>
      </head>
      <body>
         <app-root _nghost-uef-c162="" ng-version="11.2.7">
            <!---->
            <div _ngcontent-uef-c162="" class="l-page-content">
               <div _ngcontent-uef-c162="" class="l-content">
                  <router-outlet _ngcontent-uef-c162=""></router-outlet>
                  <app-login _nghost-uef-c91="" class="ng-star-inserted">
                     <mat-horizontal-stepper _ngcontent-uef-c91="" aria-orientation="horizontal" role="tablist" linear="true" labelposition="bottom" class="mat-stepper-horizontal ng-tns-c72-0 mat-stepper-label-position-bottom">
                        <div class="mat-horizontal-stepper-header-container ng-tns-c72-0">
                           <mat-step-header role="tab" class="mat-step-header mat-focus-indicator mat-horizontal-stepper-header mat-primary ng-tns-c72-0 ng-star-inserted" tabindex="0" id="cdk-step-label-0-0" aria-posinset="1" aria-setsize="5" aria-controls="cdk-step-content-0-0" aria-selected="true">
                              <div matripple="" class="mat-ripple mat-step-header-ripple"></div>
                              <div id="iconnumber1" class="mat-step-icon mat-step-icon-state-number mat-step-icon-selected">
                                 <div class="mat-step-icon-content">
                                    <!----><span class="ng-star-inserted">1</span><!----><!----><!----><!---->
                                 </div>
                              </div>
                              <div class="mat-step-label mat-step-label-active mat-step-label-selected">
                                 <div class="mat-step-text-label ng-star-inserted">
                                    Get started<!---->
                                 </div>
                                 <!----><!----><!----><!---->
                              </div>
                           </mat-step-header>
                           <div class="mat-stepper-horizontal-line ng-tns-c72-0 ng-star-inserted"></div>
                           <!----><!---->
                           <mat-step-header role="tab" class="mat-step-header mat-focus-indicator mat-horizontal-stepper-header mat-primary ng-tns-c72-0 ng-star-inserted" tabindex="-1" id="cdk-step-label-0-1" aria-posinset="2" aria-setsize="5" aria-controls="cdk-step-content-0-1" aria-selected="false">
                              <div matripple="" class="mat-ripple mat-step-header-ripple"></div>
                              <div id="iconnumber2" class="mat-step-icon mat-step-icon-state-number">
                                 <div class="mat-step-icon-content">
                                    <!----><span class="ng-star-inserted">2</span><!----><!----><!----><!---->
                                 </div>
                              </div>
                              <div class="mat-step-label">
                                 <div class="mat-step-text-label ng-star-inserted">
                                    Account verification<!---->
                                 </div>
                                 <!----><!----><!----><!---->
                              </div>
                           </mat-step-header>
                           <div class="mat-stepper-horizontal-line ng-tns-c72-0 ng-star-inserted"></div>
                           <!----><!---->
                           <mat-step-header role="tab" class="mat-step-header mat-focus-indicator mat-horizontal-stepper-header mat-primary ng-tns-c72-0 ng-star-inserted" tabindex="-1" id="cdk-step-label-0-2" aria-posinset="3" aria-setsize="5" aria-controls="cdk-step-content-0-2" aria-selected="false">
                              <div matripple="" class="mat-ripple mat-step-header-ripple"></div>
                              <div id="iconnumber3" class="mat-step-icon mat-step-icon-state-number">
                                 <div class="mat-step-icon-content">
                                    <!----><span class="ng-star-inserted">3</span><!----><!----><!----><!---->
                                 </div>
                              </div>
                              <div class="mat-step-label">
                                 <div class="mat-step-text-label ng-star-inserted">
                                    Initial import<!---->
                                 </div>
                                 <!----><!----><!----><!---->
                              </div>
                           </mat-step-header>
                           <div class="mat-stepper-horizontal-line ng-tns-c72-0 ng-star-inserted"></div>
                           <!----><!---->
                           <mat-step-header role="tab" class="mat-step-header mat-focus-indicator mat-horizontal-stepper-header mat-primary ng-tns-c72-0 ng-star-inserted" tabindex="-1" id="cdk-step-label-0-3" aria-posinset="4" aria-setsize="5" aria-controls="cdk-step-content-0-3" aria-selected="false">
                              <div matripple="" class="mat-ripple mat-step-header-ripple"></div>
                              <div id="iconnumber4" class="mat-step-icon mat-step-icon-state-number">
                                 <div class="mat-step-icon-content">
                                    <!----><span class="ng-star-inserted">4</span><!----><!----><!----><!---->
                                 </div>
                              </div>
                              <div class="mat-step-label">
                                 <div class="mat-step-text-label ng-star-inserted">
                                    Inventory setup<!---->
                                 </div>
                                 <!----><!----><!----><!---->
                              </div>
                           </mat-step-header>
                           <div class="mat-stepper-horizontal-line ng-tns-c72-0 ng-star-inserted"></div>
                           <!----><!---->
                           <mat-step-header role="tab" class="mat-step-header mat-focus-indicator mat-horizontal-stepper-header mat-primary ng-tns-c72-0 ng-star-inserted" tabindex="-1" id="cdk-step-label-0-4" aria-posinset="5" aria-setsize="5" aria-controls="cdk-step-content-0-4" aria-selected="false">
                              <div matripple="" class="mat-ripple mat-step-header-ripple"></div>
                              <div id="iconnumber5" class="mat-step-icon mat-step-icon-state-number">
                                 <div class="mat-step-icon-content">
                                    <!----><span class="ng-star-inserted">5</span><!----><!----><!----><!---->
                                 </div>
                              </div>
                              <div class="mat-step-label">
                                 <div class="mat-step-text-label ng-star-inserted">
                                    Confirm<!---->
                                 </div>
                                 <!----><!----><!----><!---->
                              </div>
                           </mat-step-header>
                           <!----><!----><!---->
                        </div>
                        <div class="mat-horizontal-content-container ng-tns-c72-0">
                           <div role="tabpanel" class="mat-horizontal-stepper-content ng-trigger ng-trigger-stepTransition ng-tns-c72-0 ng-star-inserted" id="cdk-step-content-0-0" aria-labelledby="cdk-step-label-0-0" aria-expanded="true" style="transform: none; visibility: inherit;">
                              <!---->
                              <div _ngcontent-uef-c91="" class="stepper_container ng-star-inserted" style="">
                                 <form _ngcontent-uef-c91="" novalidate="" class="text-center ng-invalid ng-dirty ng-touched">
                                    <!---->
                                    <div _ngcontent-uef-c91="">
                                       <img _ngcontent-uef-c91="" src="<?php echo plugins_url('../assets/edara-logo.svg', __FILE__); ?>" alt="Edara Logo" class="edara-logo">
                                    </div>
                                    <div _ngcontent-uef-c91="" class="flex-display">
                                       <h2 _ngcontent-uef-c91="">Welcome to Edara connect</h2>
                                       <span _ngcontent-uef-c91="" class="emoji">☺️</span>
                                    </div>
                                    <p _ngcontent-uef-c91="" class="typo-title">First, enter your Edara account</p>
                                    <div _ngcontent-uef-c91="" class="url-form-container">
                                       <mat-icon _ngcontent-uef-c91="" role="img" id="language" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">language</mat-icon>
                                       <div _ngcontent-uef-c91="">
                                          <mat-form-field _ngcontent-uef-c91="" appearance="outline" class="mat-form-field ng-tns-c33-1 mat-primary mat-form-field-type-mat-input mat-form-field-appearance-outline mat-form-field-can-float ng-invalid ng-dirty mat-form-field-invalid ng-touched">
                                             <div class="mat-form-field-wrapper ng-tns-c33-1">
                                                <div class="mat-form-field-flex ng-tns-c33-1">
                                                   <div class="mat-form-field-outline ng-tns-c33-1 ng-star-inserted">
                                                      <div class="mat-form-field-outline-start ng-tns-c33-1"></div>
                                                      <div class="mat-form-field-outline-gap ng-tns-c33-1"></div>
                                                      <div class="mat-form-field-outline-end ng-tns-c33-1"></div>
                                                   </div>
                                                   <div class="mat-form-field-outline mat-form-field-outline-thick ng-tns-c33-1 ng-star-inserted" style="color:black;">
                                                      <div class="mat-form-field-outline-start ng-tns-c33-1"></div>
                                                      <div class="mat-form-field-outline-gap ng-tns-c33-1"></div>
                                                      <div class="mat-form-field-outline-end ng-tns-c33-1"></div>
                                                   </div>
                                                   <!----><!----><!---->
                                                   <div class="mat-form-field-infix ng-tns-c33-1">
                                                      <input type="url" _ngcontent-uef-c91="" matinput="" placeholder="Yourcompany.edara.io" formcontrolname="edaraURL" autocomplete="false" class="mat-input-element mat-form-field-autofill-control ng-tns-c33-1 ng-invalid cdk-text-field-autofill-monitored ng-dirty ng-touched" id="domaininput" data-placeholder="Yourcompany.edara.io" aria-invalid="false" required="true">
                                                      <input type="hidden" id="edara_accsess_token" name="edara_accsess_token" />
                                                      <span class="mat-form-field-label-wrapper ng-tns-c33-1">
                                                         <!---->
                                                      </span>
                                                   </div>
                                                   <!---->
                                                </div>
                                                <!---->
                                                <div class="mat-form-field-subscript-wrapper ng-tns-c33-1">
                                                   <div class="ng-tns-c33-1 ng-trigger ng-trigger-transitionMessages ng-star-inserted" style="opacity: 1; transform: translateY(0%);">
                                                      <mat-error _ngcontent-uef-c91="" role="alert" class="mat-error ng-star-inserted" id="domaininputerror" style="display: none;"> You have entered an invalid email address! </mat-error>
                                                      <!----><!----><!---->
                                                   </div>
                                                   <!----><!---->
                                                </div>
                                             </div>
                                          </mat-form-field>
                                          <button _ngcontent-uef-c91="" type="submit" mat-raised-button="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary" onclick="firstTabAction(event)">
                                             <span class="mat-button-wrapper">
                                                <mat-icon _ngcontent-uef-c91="" role="img" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">arrow_forward</mat-icon>
                                             </span>
                                             <span matripple="" class="mat-ripple mat-button-ripple"></span><span class="mat-button-focus-overlay"></span>
                                          </button>
                                          <script>
                                             function firstTabAction(e) {

                                                e = e || window.event;
                                                e.preventDefault();
                                                var mailformat = /^\w+([\.-]?\w+)*.edara.io+$/;
                                                if(document.getElementById("domaininput").value.match(mailformat))
                                                {
                                                document.getElementById("domaininputerror").style.display = "none";

                                                document.getElementById("cdk-step-content-0-0").setAttribute("aria-expanded", "false");
                                                document.getElementById("cdk-step-content-0-0").style.transform = "none";
                                                document.getElementById("cdk-step-content-0-0").style.visibility = "hidden";

                                                document.getElementById("cdk-step-content-0-1").setAttribute("aria-expanded", "true");
                                                document.getElementById("cdk-step-content-0-1").style.transform = "inherit";
                                                document.getElementById("cdk-step-content-0-1").style.visibility = "visible";

                                                document.getElementById("iconnumber1").classList.remove("mat-step-icon-selected");
                                                document.getElementById("iconnumber2").classList.add("mat-step-icon-selected");
                                                   return false;
                                                }
                                                else
                                                {
                                                // alert("You have entered an invalid email address!");
                                                document.getElementById("domaininputerror").style.display = "block";
                                                return false;
                                                }
                                             // document.getElementById("demo").innerHTML = "Hello World";
                                             }
                                          </script>
                                       </div>
                                    </div>
                                    <p _ngcontent-uef-c91="" class="no-margin">Don't have an account yet?</p>
                                    <a _ngcontent-uef-c91="" href="https://getedara.com/pricing.html" target="_blank"><strong _ngcontent-uef-c91="">Contact us</strong></a>
                                 </form>
                              </div>
                              <!---->
                           </div>
                           <div role="tabpanel" class="mat-horizontal-stepper-content ng-trigger ng-trigger-stepTransition ng-tns-c72-0 ng-star-inserted" id="cdk-step-content-0-1" aria-labelledby="cdk-step-label-0-1" aria-expanded="false" style="transform: translate3d(100%, 0px, 0px); visibility: hidden;">
                              <!---->
                              <div _ngcontent-uef-c91="" class="stepper_container ng-star-inserted" style="">
                                 <form _ngcontent-uef-c91="" novalidate="" class="text-center ng-untouched ng-pristine ng-invalid">
                                    <!---->
                                    <div _ngcontent-uef-c91="">
                                       <img _ngcontent-uef-c91="" src="<?php echo plugins_url('../assets/edara-logo.svg', __FILE__); ?>" alt="Edara Logo" class="edara-logo">
                                    </div>
                                    <p _ngcontent-uef-c91="" class="typo-title">Let's verify your Edara account</p>
                                    <div _ngcontent-uef-c91="" class="login-form-container">
                                       <mat-icon _ngcontent-uef-c91="" role="img" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">person</mat-icon>
                                       <mat-form-field _ngcontent-uef-c91="" appearance="outline" class="mat-form-field ng-tns-c33-2 mat-primary mat-form-field-type-mat-input mat-form-field-appearance-outline mat-form-field-can-float ng-untouched ng-pristine ng-invalid">
                                          <div class="mat-form-field-wrapper ng-tns-c33-2">
                                             <div class="mat-form-field-flex ng-tns-c33-2">
                                                <div class="mat-form-field-outline ng-tns-c33-2 ng-star-inserted">
                                                   <div class="mat-form-field-outline-start ng-tns-c33-2"></div>
                                                   <div class="mat-form-field-outline-gap ng-tns-c33-2"></div>
                                                   <div class="mat-form-field-outline-end ng-tns-c33-2"></div>
                                                </div>
                                                <div class="mat-form-field-outline mat-form-field-outline-thick ng-tns-c33-2 ng-star-inserted">
                                                   <div class="mat-form-field-outline-start ng-tns-c33-2"></div>
                                                   <div class="mat-form-field-outline-gap ng-tns-c33-2"></div>
                                                   <div class="mat-form-field-outline-end ng-tns-c33-2"></div>
                                                </div>
                                                <!----><!----><!---->
                                                <div class="mat-form-field-infix ng-tns-c33-2">
                                                   <input id="edara_email" _ngcontent-uef-c91="" matinput="" placeholder="Username" formcontrolname="username" autocomplete="false" class="mat-input-element mat-form-field-autofill-control ng-tns-c33-2 ng-untouched ng-pristine ng-invalid cdk-text-field-autofill-monitored" id="mat-input-1" data-placeholder="Username" aria-invalid="false" aria-required="false">
                                                   <span class="mat-form-field-label-wrapper ng-tns-c33-2">
                                                      <!---->
                                                   </span>
                                                </div>
                                                <!---->
                                             </div>
                                             <!---->
                                          </div>
                                       </mat-form-field>
                                    </div>
                                    <div _ngcontent-uef-c91="" class="login-form-container">
                                       <mat-icon _ngcontent-uef-c91="" role="img" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">vpn_key</mat-icon>
                                       <mat-form-field _ngcontent-uef-c91="" appearance="outline" class="mat-form-field ng-tns-c33-3 mat-primary mat-form-field-type-mat-input mat-form-field-appearance-outline mat-form-field-can-float ng-untouched ng-pristine ng-invalid">
                                          <div class="mat-form-field-wrapper ng-tns-c33-3">
                                             <div class="mat-form-field-flex ng-tns-c33-3">
                                                <div class="mat-form-field-outline ng-tns-c33-3 ng-star-inserted">
                                                   <div class="mat-form-field-outline-start ng-tns-c33-3"></div>
                                                   <div class="mat-form-field-outline-gap ng-tns-c33-3"></div>
                                                   <div class="mat-form-field-outline-end ng-tns-c33-3"></div>
                                                </div>
                                                <div class="mat-form-field-outline mat-form-field-outline-thick ng-tns-c33-3 ng-star-inserted">
                                                   <div class="mat-form-field-outline-start ng-tns-c33-3"></div>
                                                   <div class="mat-form-field-outline-gap ng-tns-c33-3"></div>
                                                   <div class="mat-form-field-outline-end ng-tns-c33-3"></div>
                                                </div>
                                                <!----><!----><!---->
                                                <div id="password" class="mat-form-field-infix ng-tns-c33-3">
                                                   <input id="edara_password" _ngcontent-uef-c91="" matinput="" type="password" placeholder="Password" formcontrolname="password" autocomplete="false" class="mat-input-element mat-form-field-autofill-control ng-tns-c33-3 ng-untouched ng-pristine ng-invalid cdk-text-field-autofill-monitored" id="mat-input-2" data-placeholder="Password" aria-invalid="false" aria-required="false">
                                                   <span class="mat-form-field-label-wrapper ng-tns-c33-3">
                                                      <!---->
                                                   </span>
                                                </div>
                                                <!---->
                                             </div>
                                             <!---->
                                             <div class="mat-form-field-subscript-wrapper ng-tns-c33-3">
                                                <!---->
                                                <div class="mat-form-field-hint-wrapper ng-tns-c33-3 ng-trigger ng-trigger-transitionMessages ng-star-inserted" style="opacity: 1; transform: translateY(0%);">
                                                   <!---->
                                                   <mat-error _ngcontent-uef-c91="" role="alert" class="mat-error ng-star-inserted" id="secondtaberror" style="display: none;"></mat-error>
                                                </div>
                                                <!---->
                                             </div>
                                          </div>
                                       </mat-form-field>
                                    </div>
                                    <button id="verifyAccountButton" onclick="secondTabAction(event)" _ngcontent-uef-c91="" type="submit" mat-raised-button="" color="primary" class="mat-focus-indicator login-btn mat-raised-button mat-button-base mat-primary">
                                       <span class="mat-button-wrapper"> Verify my account </span>
                                       <span matripple="" class="mat-ripple mat-button-ripple"></span>
                                       <span class="mat-button-focus-overlay"></span>
                                    </button>
                                    <script>
                                       var site_base_url = '<?php echo $base_url; ?>';

                                       var plugin_url = '<?php echo plugins_url('/', dirname(__FILE__)); ?>';

                                       function orderStatusAnyClick(){
                                          document.getElementById("orderStatusPending").checked = true;
                                          document.getElementById("orderStatusProcessing").checked = true;
                                          document.getElementById("orderStatusOnHold").checked = true;
                                          document.getElementById("orderStatusCompleted").checked = true;
                                          document.getElementById("orderStatusCanceled").checked = true;
                                          document.getElementById("orderStatusRefunded").checked = true;
                                          document.getElementById("orderStatusFaild").checked = true;
                                          document.getElementById("orderStatusDraft").checked = true;
                                       }

                                       function orderStatusChange(check){
                                          if(check.checked == false){
                                                document.getElementById("orderStatusAny").checked = false;
                                          }
                                       }

                                       function secondTabAction(e) {
                                          e = e || window.event;
                                          e.preventDefault();

                                          var button = document.getElementById("verifyAccountButton");
                                          var buttonText = button.querySelector(".mat-button-wrapper");
                                          buttonText.innerHTML = "Loading...";
                                          button.disabled = true;
                                          button.classList.add("mat-button-disabled");

                                          var edara_domain = document.getElementById("domaininput").value;
                                          var edara_email = document.getElementById("edara_email").value;
                                          var edara_password = document.getElementById("edara_password").value;

                                          $.ajax({
                                             type: 'post',
                                             url: plugin_url + '/Includes/data.php',
                                             data: {
                                                'edara_email': edara_email,
                                                'edara_password': edara_password,
                                                'edara_domain': edara_domain
                                             },
                                             success: function (message) {
                                                if (!message.success) {
                                                      var error_area = document.getElementById('secondtaberror');
                                                      error_area.style.display = 'block';
                                                      error_area.innerHTML = message.error;
                                                      resetButton();
                                                } else {
                                                      var edara_domain = document.getElementById("domaininput").value;
                                                      var token_1 = message.data.access_token;  // Access token
                                                      var tenant_name = message.data.tenant_name; // Tenant name

                                                      // Pass both token_1 and tenant_name to the next function
                                                      data1Call(edara_domain, token_1, tenant_name);
                                                }
                                             }
                                          });

                                          function data1Call(edara_domain, token_1, tenant_name) {
                                                $.ajax({
                                                   type: 'post',
                                                   url: plugin_url + '/Includes/data1.php',
                                                   data: {
                                                      "base_url" : edara_domain,
                                                      "token" : token_1,
                                                      "tenant_name": tenant_name
                                                   },
                                                   success: function (message_new) {
                                                      if(!message_new){
                                                            var error_area = document.getElementById('secondtaberror');
                                                            error_area.style.display = 'block';
                                                            error_area.innerHTML = "error in integration";
                                                            resetButton();
                                                      }else{
                                                            var edara_accsess_token = message_new;
                                                            data2Call(edara_accsess_token);
                                                      }
                                                   }
                                                });
                                          }

                                          function data2Call(edara_accsess_token) {
                                                $.ajax({
                                                   type: 'post',
                                                   url: plugin_url + '/Includes/data2.php',
                                                   data: {
                                                      'edara_accsess_token': edara_accsess_token
                                                   },
                                                   success: function (res) {
                                                      $("#warehouses_selection").html(res);
                                                   },
                                                   complete: function () {
                                                      dataStoresCall(edara_accsess_token);
                                                   }
                                                });
                                          }

                                          function dataStoresCall(edara_accsess_token) {
                                                $.ajax({
                                                   type: 'post',
                                                   url: plugin_url + '/Includes/data_stores.php',
                                                   data: {
                                                      'edara_accsess_token': edara_accsess_token
                                                   },
                                                   success: function (res) {
                                                      $("#stores_selection").html(res);
                                                   },
                                                   complete: function () {
                                                      dataGetServicesCall(edara_accsess_token);
                                                   }
                                                });
                                          }

                                          function dataGetServicesCall(edara_accsess_token) {
                                                $.ajax({
                                                   type: 'post',
                                                   url: plugin_url + '/Includes/data_get_services.php',
                                                   data: {
                                                      'edara_accsess_token': edara_accsess_token
                                                   },
                                                   success: function (res) {
                                                      $("#services_selection").html(res);
                                                   },
                                                   complete: function () {
                                                      resetButton();
                                                      proceedToNextStep(edara_accsess_token);
                                                   }
                                                });
                                          }

                                          function resetButton() {
                                                buttonText.innerHTML = "Verify my account";
                                                button.disabled = false;
                                          }

                                          function proceedToNextStep(edara_accsess_token) {
                                                document.getElementById("edara_accsess_token").value = edara_accsess_token;
                                                document.getElementById("secondtaberror").style.display = "none";

                                                document.getElementById("cdk-step-content-0-1").setAttribute("aria-expanded", "false");
                                                document.getElementById("cdk-step-content-0-1").style.transform = "none";
                                                document.getElementById("cdk-step-content-0-1").style.visibility = "hidden";

                                                document.getElementById("cdk-step-content-0-2").setAttribute("aria-expanded", "true");
                                                document.getElementById("cdk-step-content-0-2").style.transform = "inherit";
                                                document.getElementById("cdk-step-content-0-2").style.visibility = "visible";

                                                document.getElementById("iconnumber2").classList.remove("mat-step-icon-selected");
                                                document.getElementById("iconnumber3").classList.add("mat-step-icon-selected");
                                          }
                                       }
                                    </script>
                                 </form>
                              </div>
                              <!---->
                           </div>
                           <div role="tabpanel" class="mat-horizontal-stepper-content ng-trigger ng-trigger-stepTransition ng-tns-c72-0 ng-star-inserted" id="cdk-step-content-0-2" aria-labelledby="cdk-step-label-0-2" aria-expanded="false" style="transform: translate3d(100%, 0px, 0px); visibility: hidden;">
                              <!---->
                              <div _ngcontent-uef-c91="" class="stepper_container ng-star-inserted" style="">
                                 <form _ngcontent-uef-c91="" novalidate="" class="padding ng-untouched ng-pristine ng-valid">
                                    <div _ngcontent-uef-c91="" class="row">
                                       <div _ngcontent-uef-c91="" class="col-sm-7">
                                          <div _ngcontent-uef-c91="">

                                             <mat-radio-group _ngcontent-uef-c91="" formcontrolname="products" class="mat-radio-group ng-pristine ng-valid">
                                                <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">1. I will import my products</mat-label>
                                                <br/>
                                                <br/>
                                                <div>
                                                   <select name="products" id="products_selection" style="padding:5px;">
                                                   <option value="edara_to_wp">From Edara to Wordpress.</option>
                                                   <option value="wp_to_edara">From Wordpress to Edara.</option>
                                                   <option value="no">No Import</option>
                                                </select>
                                                </div>
                                             </mat-radio-group>
                                             <br/>
                                             <br/>
                                             <mat-radio-group _ngcontent-uef-c91="" required="" formcontrolname="customers" class="mat-radio-group ng-pristine ng-valid">
                                                <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">2. Do you want to import Wordpress customers to Edara?</mat-label><br/>
                                                <br/>
                                                <select name="customers" id="customers_selection" style="padding:5px;">
                                                   <option value="all_customers">Yes, import all customers.</option>
                                                   <option value="new_customers">No, only new customers.</option>
                                                </select>
                                             </mat-radio-group>
                                             <br/>
                                             <br/>
                                             <mat-radio-group _ngcontent-uef-c91="" role="radiogroup" required="" formcontrolname="orders" class="mat-radio-group ng-pristine ng-valid">
                                                <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">3. Edara will import from Wordpress</mat-label>
                                                <br/>
                                                <br/>
                                                <select onchange="seeOrderSelection(this);" name="orders" id="orders_selection" style="padding:5px;">
                                                   <!-- <option value="all_orders">All orders.</option>
                                                   <option value="unfulfilled_orders">Unfulfilled orders.</option> -->
                                                   <option value="new_orders">New orders.</option>
                                                </select>
                                                <div id="div_from_date" style="display: none;">
                                                   <label for="from_date">
                                                      Integration start date
                                                   </label>
                                                   <br/>
                                                   <input id="from_date" type="date" name="from_date" style="padding:5px;"></input>
                                                </div>

                                             </mat-radio-group>
                                             <br>
                                             <br>
                                             <mat-radio-group _ngcontent-uef-c91="" role="radiogroup" required="" formcontrolname="orders" class="mat-radio-group ng-pristine ng-valid">
                                                <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">4. Edara will import orders from WooCommerce with order status:</mat-label>
                                                <br>
                                                <br>
                                                <label>
                                                   <input type="radio" id="orderStatusAny" name="order_status_any" onchange="orderStatusAnyClick()"> Any
                                                </label>
                                                <br>
                                                <div style="display: grid;grid-template-columns: auto auto auto auto;">
                                                   <label>
                                                      <input type="checkbox" id="orderStatusPending" name="order_status_pending" onchange="orderStatusChange(this)"> Pending payment
                                                   </label>
                                                   <label>
                                                      <input type="checkbox" id="orderStatusProcessing" name="order_status_processing" onchange="orderStatusChange(this)"> Processing
                                                   </label>
                                                   <label>
                                                      <input type="checkbox" id="orderStatusOnHold" name="order_status_on_hold" onchange="orderStatusChange(this)"> On-hold
                                                   </label>
                                                   <label>
                                                      <input type="checkbox" id="orderStatusCompleted" name="order_status_completed" onchange="orderStatusChange(this)"> Completed
                                                   </label>
                                                   <label>
                                                      <input type="checkbox" id="orderStatusCanceled" name="order_status_canceled" onchange="orderStatusChange(this)"> Canceled
                                                   </label>
                                                   <label>
                                                      <input type="checkbox" id="orderStatusRefunded" name="order_status_refunded" onchange="orderStatusChange(this)"> Refunded
                                                   </label>
                                                   <label>
                                                      <input type="checkbox" id="orderStatusFaild" name="order_status_faild" onchange="orderStatusChange(this)"> Faild
                                                   </label>
                                                   <label>
                                                      <input type="checkbox" id="orderStatusDraft" name="order_status_draft" onchange="orderStatusChange(this)"> Draft
                                                   </label>
                                                </div>
                                                <script type="text/javascript">
                                                   function orderStatusAnyClick(){
                                                      document.getElementById("orderStatusPending").checked = true;
                                                      document.getElementById("orderStatusProcessing").checked = true;
                                                      document.getElementById("orderStatusOnHold").checked = true;
                                                      document.getElementById("orderStatusCompleted").checked = true;
                                                      document.getElementById("orderStatusCanceled").checked = true;
                                                      document.getElementById("orderStatusRefunded").checked = true;
                                                      document.getElementById("orderStatusFaild").checked = true;
                                                      document.getElementById("orderStatusDraft").checked = true;
                                                   }
                                                   function orderStatusChange(checkbox){
                                                      if(checkbox.checked == false){
                                                         document.getElementById("orderStatusAny").checked = false;
                                                      }
                                                   }
                                                </script>
                                             </mat-radio-group>
                                          </div>
                                       </div>
                                       <div _ngcontent-uef-c91="" class="col-sm-5 text-center">
                                          <div _ngcontent-uef-c91="" class="init-import__banner"><img _ngcontent-uef-c91="" src="https://cdn.shopify.com/app-store/listing_images/cf139f8bb3184f91ac45430d3532a6d2/icon/CL6ysqv0lu8CEAE=.png" alt=""></div>
                                       </div>
                                    </div>
                                    <div _ngcontent-uef-c91="" align="end" class="u-justify-between">
                                       <button onclick="prevous1TabAction(event)" _ngcontent-uef-c91="" mat-button="" class="mat-focus-indicator mat-button mat-button-base">
                                          <span class="mat-button-wrapper">
                                             <mat-icon _ngcontent-uef-c91="" role="img" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">keyboard_arrow_left</mat-icon>
                                             Back
                                          </span>
                                          <span matripple="" class="mat-ripple mat-button-ripple"></span><span class="mat-button-focus-overlay"></span>
                                       </button>
                                       <button onclick="thiredTabAction(event)" _ngcontent-uef-c91="" type="submit" mat-raised-button="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary"><span class="mat-button-wrapper">Next</span><span matripple="" class="mat-ripple mat-button-ripple"></span><span class="mat-button-focus-overlay"></span></button>
                                       <script type="text/javascript">
                                          function prevous1TabAction(e) {

                                                e = e || window.event;
                                                e.preventDefault();


                                                document.getElementById("cdk-step-content-0-2").setAttribute("aria-expanded", "false");
                                                document.getElementById("cdk-step-content-0-2").style.transform = "none";
                                                document.getElementById("cdk-step-content-0-2").style.visibility = "hidden";

                                                document.getElementById("cdk-step-content-0-1").setAttribute("aria-expanded", "true");
                                                document.getElementById("cdk-step-content-0-1").style.transform = "inherit";
                                                document.getElementById("cdk-step-content-0-1").style.visibility = "visible";

                                                document.getElementById("iconnumber3").classList.remove("mat-step-icon-selected");
                                                document.getElementById("iconnumber2").classList.add("mat-step-icon-selected");


                                             }
                                          function seeOrderSelection(options) {
                                                var div_from_date = document.getElementById('div_from_date');

                                                if (options.value == "all_orders"){
                                                   div_from_date.style.display = 'block';
                                                }else{
                                                   div_from_date.style.display = 'none';
                                                }

                                             }
                                          function thiredTabAction(e) {

                                                e = e || window.event;
                                                e.preventDefault();

                                                document.getElementById("cdk-step-content-0-2").setAttribute("aria-expanded", "false");
                                                document.getElementById("cdk-step-content-0-2").style.transform = "none";
                                                document.getElementById("cdk-step-content-0-2").style.visibility = "hidden";

                                                document.getElementById("cdk-step-content-0-3").setAttribute("aria-expanded", "true");
                                                document.getElementById("cdk-step-content-0-3").style.transform = "inherit";
                                                document.getElementById("cdk-step-content-0-3").style.visibility = "visible";

                                                document.getElementById("iconnumber3").classList.remove("mat-step-icon-selected");
                                                document.getElementById("iconnumber4").classList.add("mat-step-icon-selected");

                                                document.getElementById("selected_products_option").innerHTML = "Products " + $("select#products_selection option").filter(":selected").text();

                                                document.getElementById("selected_customers_option").innerHTML = "Customers " + $("select#customers_selection option").filter(":selected").text();

                                                var order_value = $("select#orders_selection option").filter(":selected").val();
                                                if (order_value == "all_orders") {
                                                   document.getElementById("selected_orders_option").innerHTML = "Orders " + $("select#orders_selection option").filter(":selected").text() + 'from ' + document.getElementById("from_date").value;
                                                }else{
                                                   document.getElementById("selected_orders_option").innerHTML = "Orders " + $("select#orders_selection option").filter(":selected").text()
                                                }

                                             }

                                             function ordersSelectChange(input) {
                                                if(input.value == 'store'){
                                                   document.getElementById("warehouses_selection").value = "-1";
                                                   document.getElementById("warehouses_selection").style.display = "none";
                                                   document.getElementById("stores_selection").style.display = "inline-block";
                                                }else{
                                                   document.getElementById("stores_selection").value = "-1";
                                                   document.getElementById("stores_selection").style.display = "none";
                                                   document.getElementById("warehouses_selection").style.display = "inline-block";
                                                }
                                             }
                                       </script>
                                    </div>
                                 </form>
                              </div>
                              <!---->
                           </div>
                           <div role="tabpanel" class="mat-horizontal-stepper-content ng-trigger ng-trigger-stepTransition ng-tns-c72-0 ng-star-inserted" id="cdk-step-content-0-3" aria-labelledby="cdk-step-label-0-3" aria-expanded="false" style="transform: translate3d(100%, 0px, 0px); visibility: hidden;">
                              <!---->
                              <div _ngcontent-uef-c91="" class="stepper_container ng-star-inserted" style="">
                                 <form _ngcontent-uef-c91="" novalidate="" class="padding ng-untouched ng-pristine ng-valid">
                                    <div _ngcontent-uef-c91="" class="row">
                                       <div _ngcontent-uef-c91="" class="col-sm-7">
                                          <div _ngcontent-uef-c91="">
                                             <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">1. Edara will receive Wordpress orders on:</mat-label>
                                             <div _ngcontent-uef-c91="" class="inventory-setup-control search-control">
                                                <app-auto-complete _ngcontent-uef-c91="" _nghost-uef-c82="">
                                                   <mat-form-field _ngcontent-uef-c82="" class="mat-form-field full-width ng-tns-c33-6 mat-primary mat-form-field-type-mat-input mat-form-field-appearance-outline mat-form-field-can-float ng-untouched ng-pristine ng-invalid">
                                                      <br/>
                                                      <br/>

                                                      <div>
                                                         <input type="radio" id="radioWarehouse" name="orders_select_type" value="warehouse" checked onchange="ordersSelectChange(this)">
                                                         <label for="radioWarehouse">Warehouse</label>
                                                         <input type="radio" id="radioStore" name="orders_select_type" value="store" onchange="ordersSelectChange(this)">
                                                         <label for="radioStore">Store</label>
                                                      </div>
                                                      <select name="warehouses" id="warehouses_selection" style="padding:5px;">
                                                         <option value="prompt">No warehouses found</option>
                                                      </select>
                                                      
                                                      <select name="warehouses" id="stores_selection" style="padding:5px;display:none;">
                                                         <option value="prompt">No stores found</option>
                                                      </select>

                                                   </mat-form-field>
                                                   <div id="newWH" style="display:none;">
                                                      <input id="newWHname" _ngcontent-uef-c91="" matinput="" type="text" placeholder="Warehouse Name" formcontrolname="newWHname" autocomplete="false" class="mat-input-element mat-form-field-autofill-control ng-tns-c33-3 ng-untouched ng-pristine ng-invalid cdk-text-field-autofill-monitored" id="mat-input-2" data-placeholder="Warehouse Name" aria-invalid="false" aria-required="false">
                                                      <div id="warehouseAddResponse" style="display:none;"></div>
                                                      <button onclick="addNewWarehouseForm(event)"  _ngcontent-uef-c91="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary"><span class="mat-button-wrapper">Add</span></button>
                                                   </div>
                                                   <div id="newStore" style="display:none;">
                                                      <input id="newStorename" _ngcontent-uef-c91="" matinput="" type="text" placeholder="Store Name" formcontrolname="newStore" autocomplete="false" class="mat-input-element mat-form-field-autofill-control ng-tns-c33-3 ng-untouched ng-pristine ng-invalid cdk-text-field-autofill-monitored" id="mat-input-2" data-placeholder="Store Name" aria-invalid="false" aria-required="false">
                                                      <div id="storeAddResponse" style="display:none;"></div>
                                                      <button onclick="addNewStoreForm(event)"  _ngcontent-uef-c91="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary"><span class="mat-button-wrapper">Add</span></button>
                                                   </div>
                                                </app-auto-complete>
                                                <button onclick="addNewWarehouse(event)" _ngcontent-uef-c91="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary"><span class="mat-button-wrapper" style="padding:5px;">+ Add new Warehouse</span></button>

                                                <button onclick="addNewStore(event)"  _ngcontent-uef-c91="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary"><span class="mat-button-wrapper" style="padding:5px;">+ Add new Store</span></button>
                                                <script type="text/javascript">

                                                   var site_base_url = '<?php echo $base_url; ?>';

                                                   var plugin_url = '<?php echo plugins_url('/', dirname(__FILE__)); ?>';

                                                   function addNewWarehouse(e){

                                                      e = e || window.event;
                                                      e.preventDefault();
                                                      if (document.getElementById("newWH").style.display == "none") {
                                                         document.getElementById("newWH").style.display = "block";
                                                      }else{
                                                         document.getElementById("newWH").style.display = "none";
                                                      }
                                                   }
                                                   function addNewStore(e){
                                                      e = e || window.event;
                                                      e.preventDefault();
                                                      if (document.getElementById("newStore").style.display == "none") {
                                                         document.getElementById("newStore").style.display = "block";
                                                      }else{
                                                         document.getElementById("newStore").style.display = "none";
                                                      }
                                                   }
                                                   function addNewWarehouseForm(e){
                                                      e = e || window.event;
                                                      e.preventDefault();

                                                      var newWHname = document.getElementById("newWHname").value;
                                                      var edara_accsess_token = document.getElementById("edara_accsess_token").value;
                                                      $.ajax({
                                                         type: 'post',
                                                         url: plugin_url + '/Includes/addWarehouse.php',
                                                         data: {
                                                            'newWHname': newWHname,
                                                            'edara_accsess_token':edara_accsess_token
                                                         },
                                                         success: function (message) {
                                                            if(!message.success){
                                                               var error_area = document.getElementById('warehouseAddResponse');
                                                               error_area.style.display = 'block';
                                                               error_area.innerHTML = message.error;
                                                            }else{
                                                               document.getElementById('warehouseAddResponse').style.display = 'block';

                                                               var option = document.createElement("option");
                                                               option.text = newWHname;
                                                               option.value = message.message;
                                                               var select = document.getElementById("warehouses_selection");
                                                               select.appendChild(option);

                                                               document.getElementById("warehouseAddResponse").innerHTML = "Warehouse with code " + message.message + " Added";
                                                            }
                                                         }
                                                      });

                                                   }
                                                   function addNewStoreForm(e){
                                                      e = e || window.event;
                                                      e.preventDefault();

                                                      var newStorename = document.getElementById("newStorename").value;
                                                      var edara_accsess_token = document.getElementById("edara_accsess_token").value;
                                                      $.ajax({
                                                         type: 'post',
                                                         url: plugin_url + '/Includes/addStore.php',
                                                         data: {
                                                            'newStorename': newStorename,
                                                            'edara_accsess_token': edara_accsess_token,
                                                         },
                                                         success: function (message) {
                                                            if(!message.success){
                                                               var error_area = document.getElementById('storeAddResponse');
                                                               error_area.style.display = 'block';
                                                               error_area.innerHTML = message.error;
                                                            }else{
                                                               document.getElementById('storeAddResponse').style.display = 'block';

                                                               var option = document.createElement("option");
                                                               option.text = newStorename;
                                                               option.value = message.message;
                                                               var select = document.getElementById("warehouses_selection");
                                                               select.appendChild(option);

                                                               document.getElementById("storeAddResponse").innerHTML = "Store " + message.message + " Added";


                                                            }

                                                         // alert('form was submitted');
                                                         }
                                                      });

                                                   }
                                                </script>
                                             </div>
                                          </div>
                                          <div _ngcontent-uef-c91="">
                                             <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">2. Products prices in Wordpress will sync with</mat-label>
                                             <div _ngcontent-uef-c91="" class="inventory-setup-control">
                                                <mat-form-field _ngcontent-uef-c91="" appearance="outline" class="mat-form-field ng-tns-c33-4 mat-primary mat-form-field-type-mat-select mat-form-field-appearance-outline mat-form-field-can-float ng-untouched ng-pristine ng-valid mat-form-field-should-float">
                                                   <div class="mat-form-field-wrapper ng-tns-c33-4">
                                                      <div class="mat-form-field-flex ng-tns-c33-4">
                                                         <div class="mat-form-field-outline ng-tns-c33-4 ng-star-inserted" style="opacity: 0">
                                                            <div class="mat-form-field-outline-start ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-gap ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-end ng-tns-c33-4"></div>
                                                         </div>
                                                         <div class="mat-form-field-outline mat-form-field-outline-thick ng-tns-c33-4 ng-star-inserted">
                                                            <div class="mat-form-field-outline-start ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-gap ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-end ng-tns-c33-4"></div>
                                                         </div>
                                                         <!----><!----><!---->
                                                         <div class="row">
                                                               <select name="sale_price" id="sale_price" style="padding:5px;">
                                                                  <option value="sale_price">Sale Price</option>
                                                                  <!-- <option value="dealer_price">Dealer price</option>
                                                                  <option value="super_dealer_price">Super dealer price</option> -->
                                                               </select>
                                                            <!-- <span class="mat-form-field-label-wrapper ng-tns-c33-4">
                                                            </span> -->
                                                         </div>
                                                         <!---->
                                                      </div>
                                                      <!---->
                                                      <div class="mat-form-field-subscript-wrapper ng-tns-c33-4">
                                                         <!---->
                                                         <div class="mat-form-field-hint-wrapper ng-tns-c33-4 ng-trigger ng-trigger-transitionMessages ng-star-inserted" style="opacity: 1; transform: translateY(0%);">
                                                            <!---->
                                                            <div class="mat-form-field-hint-spacer ng-tns-c33-4"></div>
                                                         </div>
                                                         <!---->
                                                      </div>
                                                   </div>
                                                </mat-form-field>
                                             </div>
                                          </div>
                                          <div _ngcontent-uef-c91="">
                                             <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">3. Select service item</mat-label>
                                             <div _ngcontent-uef-c91="" class="inventory-setup-control">
                                                <mat-form-field _ngcontent-uef-c91="" appearance="outline" class="mat-form-field ng-tns-c33-4 mat-primary mat-form-field-type-mat-select mat-form-field-appearance-outline mat-form-field-can-float ng-untouched ng-pristine ng-valid mat-form-field-should-float">
                                                   <div class="mat-form-field-wrapper ng-tns-c33-4">
                                                      <div class="mat-form-field-flex ng-tns-c33-4">
                                                         <div class="mat-form-field-outline ng-tns-c33-4 ng-star-inserted" style="opacity: 0">
                                                            <div class="mat-form-field-outline-start ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-gap ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-end ng-tns-c33-4"></div>
                                                         </div>
                                                         <div class="mat-form-field-outline mat-form-field-outline-thick ng-tns-c33-4 ng-star-inserted">
                                                            <div class="mat-form-field-outline-start ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-gap ng-tns-c33-4"></div>
                                                            <div class="mat-form-field-outline-end ng-tns-c33-4"></div>
                                                         </div>
                                                         <!----><!----><!---->
                                                         <div class="row">
                                                               <select name="services_selection" id="services_selection" style="padding:5px;">
                                                                  <option value="0">No service item</option>
                                                               </select>
                                                            <!-- <span class="mat-form-field-label-wrapper ng-tns-c33-4">
                                                            </span> -->
                                                         </div>
                                                         <!---->
                                                      </div>
                                                      <!---->
                                                      <div class="mat-form-field-subscript-wrapper ng-tns-c33-4">
                                                         <!---->
                                                         <div class="mat-form-field-hint-wrapper ng-tns-c33-4 ng-trigger ng-trigger-transitionMessages ng-star-inserted" style="opacity: 1; transform: translateY(0%);">
                                                            <!---->
                                                            <div class="mat-form-field-hint-spacer ng-tns-c33-4"></div>
                                                         </div>
                                                         <!---->
                                                      </div>
                                                   </div>
                                                </mat-form-field>
                                             </div>
                                          </div>
                                          <div _ngcontent-uef-c91="">
                                             <mat-label _ngcontent-uef-c91="" class="typo-title no-margin">4. Select customer primary key</mat-label>
                                             <div _ngcontent-uef-c91="" class="inventory-setup-control">
                                                <select name="customer_primary_key_selection" id="customer_primary_key_selection" style="padding:5px;">
                                                   <option value="email">Email</option>
                                                   <option value="phone">Phone</option>   
                                                </select>
                                             </div>
                                          </div>
                                       </div>
                                       <div _ngcontent-uef-c91="" class="col-sm-5 text-center">
                                          <div _ngcontent-uef-c91="" class="init-import__banner"><img _ngcontent-uef-c91="" src="https://cdn.shopify.com/app-store/listing_images/cf139f8bb3184f91ac45430d3532a6d2/icon/CL6ysqv0lu8CEAE=.png" alt=""></div>
                                       </div>
                                    </div>
                                    <div _ngcontent-uef-c91="" align="end" class="u-justify-between">
                                       <button onclick="prevous2TabAction(event)" _ngcontent-uef-c91="" mat-button="" matstepperprevious="" class="mat-focus-indicator mat-stepper-previous mat-button mat-button-base" type="button">
                                          <span class="mat-button-wrapper">
                                             <mat-icon _ngcontent-uef-c91="" role="img" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">keyboard_arrow_left</mat-icon>
                                             Back
                                          </span>
                                          <span matripple="" class="mat-ripple mat-button-ripple"></span><span class="mat-button-focus-overlay"></span>
                                       </button>
                                       <button onclick="forthTabAction(event)" _ngcontent-uef-c91="" type="button" mat-raised-button="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary"><span class="mat-button-wrapper">Next</span><span matripple="" class="mat-ripple mat-button-ripple"></span><span class="mat-button-focus-overlay"></span></button>
                                       <script type="text/javascript">
                                          function prevous2TabAction(e) {

                                                e = e || window.event;
                                                e.preventDefault();


                                                document.getElementById("cdk-step-content-0-3").setAttribute("aria-expanded", "false");
                                                document.getElementById("cdk-step-content-0-3").style.transform = "none";
                                                document.getElementById("cdk-step-content-0-3").style.visibility = "hidden";

                                                document.getElementById("cdk-step-content-0-2").setAttribute("aria-expanded", "true");
                                                document.getElementById("cdk-step-content-0-2").style.transform = "inherit";
                                                document.getElementById("cdk-step-content-0-2").style.visibility = "visible";

                                                document.getElementById("iconnumber4").classList.remove("mat-step-icon-selected");
                                                document.getElementById("iconnumber3").classList.add("mat-step-icon-selected");


                                             }

                                          function forthTabAction(e) {

                                                e = e || window.event;
                                                e.preventDefault();

                                                document.getElementById("cdk-step-content-0-3").setAttribute("aria-expanded", "false");
                                                document.getElementById("cdk-step-content-0-3").style.transform = "none";
                                                document.getElementById("cdk-step-content-0-3").style.visibility = "hidden";

                                                document.getElementById("cdk-step-content-0-4").setAttribute("aria-expanded", "true");
                                                document.getElementById("cdk-step-content-0-4").style.transform = "inherit";
                                                document.getElementById("cdk-step-content-0-4").style.visibility = "visible";

                                                document.getElementById("iconnumber4").classList.remove("mat-step-icon-selected");
                                                document.getElementById("iconnumber5").classList.add("mat-step-icon-selected");

                                                document.getElementById("selected_warehouses_option").innerHTML = "Edara will receive orders on " + $("select#warehouses_selection option").filter(":selected").text();
                                                document.getElementById("selected_sale_option").innerHTML = "Sale Price";
                                             }
                                       </script>
                                    </div>
                                 </form>
                              </div>
                              <!---->
                           </div>
                           <div role="tabpanel" class="mat-horizontal-stepper-content ng-trigger ng-trigger-stepTransition ng-tns-c72-0 ng-star-inserted" id="cdk-step-content-0-4" aria-labelledby="cdk-step-label-0-4" aria-expanded="false" style="transform: translate3d(100%, 0px, 0px); visibility: hidden;">
                              <!---->
                              <div id="coverScreen"  class="LockOn" style="display:none;">
                              </div>
                              <div _ngcontent-uef-c91="" class="stepper_container ng-star-inserted" style="">
                                 <!---->
                                 <div _ngcontent-uef-c91="" class="row">
                                    <div _ngcontent-uef-c91="" class="col-sm-8 text-center">
                                       <div _ngcontent-uef-c91="" class="page-header">
                                          <h2 _ngcontent-uef-c91="">
                                             Greate job so far
                                             <mat-icon _ngcontent-uef-c91="" role="img" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">thumb_up</mat-icon>
                                          </h2>
                                       </div>
                                       <label _ngcontent-uef-c91="" class="typo-title">Take a look at the setup summary</label>
                                       <div _ngcontent-uef-c91="" class="summary_content">
                                          <strong _ngcontent-uef-c91="">Data import</strong>
                                          <ul _ngcontent-uef-c91="">
                                             <li _ngcontent-uef-c91="" id="selected_products_option">jj</li>
                                             <li _ngcontent-uef-c91="" id="selected_customers_option">k</li>
                                             <li _ngcontent-uef-c91="" id="selected_orders_option">k</li>
                                          </ul>
                                       </div>
                                       <div _ngcontent-uef-c91="" class="summary_content">
                                          <strong _ngcontent-uef-c91="">Inventory setup</strong>
                                          <ul _ngcontent-uef-c91="">
                                             <li _ngcontent-uef-c91="" id="selected_warehouses_option">Edara will receive wordpress orders on .</li>
                                             <li _ngcontent-uef-c91="" id="selected_sale_option">Products prices in wordpress will sync with the Sales price in Edara.</li>
                                          </ul>
                                       </div>
                                    </div>
                                    <div _ngcontent-uef-c91="" class="col-sm-4 text-center">
                                       <div _ngcontent-uef-c91="" class="init-import__banner"><img _ngcontent-uef-c91="" src="https://cdn.shopify.com/app-store/listing_images/cf139f8bb3184f91ac45430d3532a6d2/icon/CL6ysqv0lu8CEAE=.png" alt=""></div>
                                    </div>
                                 </div>
                                 <div _ngcontent-uef-c91="" align="end" class="u-justify-between padding">
                                    <button onclick="prevous3TabAction(event)" _ngcontent-uef-c91="" mat-button="" matstepperprevious="" class="mat-focus-indicator mat-stepper-previous mat-button mat-button-base" type="button">
                                       <span class="mat-button-wrapper">
                                          <mat-icon _ngcontent-uef-c91="" role="img" class="mat-icon notranslate material-icons mat-icon-no-color" aria-hidden="true" data-mat-icon-type="font">keyboard_arrow_left</mat-icon>
                                          Back
                                       </span>
                                       <span matripple="" class="mat-ripple mat-button-ripple"></span><span class="mat-button-focus-overlay"></span>
                                    </button>
                                    <button onclick="submitResults(event)" _ngcontent-uef-c91="" type="button" mat-raised-button="" color="primary" class="mat-focus-indicator mat-raised-button mat-button-base mat-primary"><span class="mat-button-wrapper">Confirm</span><span matripple="" class="mat-ripple mat-button-ripple"></span><span class="mat-button-focus-overlay"></span></button>
                                    <script type="text/javascript">
                                       function prevous3TabAction(e) {

                                          e = e || window.event;
                                          e.preventDefault();

                                          var plugin_url = '<?php echo plugins_url('/', dirname(__FILE__)); ?>';


                                          document.getElementById("cdk-step-content-0-4").setAttribute("aria-expanded", "false");
                                          document.getElementById("cdk-step-content-0-4").style.transform = "none";
                                          document.getElementById("cdk-step-content-0-4").style.visibility = "hidden";

                                          document.getElementById("cdk-step-content-0-3").setAttribute("aria-expanded", "true");
                                          document.getElementById("cdk-step-content-0-3").style.transform = "inherit";
                                          document.getElementById("cdk-step-content-0-3").style.visibility = "visible";

                                          document.getElementById("iconnumber5").classList.remove("mat-step-icon-selected");
                                          document.getElementById("iconnumber4").classList.add("mat-step-icon-selected");


                                       }

                                       function submitResults(e){
                                          e = e || window.event;
                                          e.preventDefault();

                                          var edara_domain = document.getElementById("domaininput").value;

                                          var edara_email = document.getElementById("edara_email").value;

                                          var edara_password = document.getElementById("edara_password").value;

                                          var products_selection = $("select#products_selection option").filter(":selected").val();

                                          var customer_key_selection = $("select#customer_primary_key_selection option").filter(":selected").val();

                                          var customers_selection = $("select#customers_selection option").filter(":selected").val();

                                          var orders_selection = $("select#orders_selection option").filter(":selected").val();

                                          var sale_price =  $("select#sale_price option").filter(":selected").val();

                                          var edara_accsess_token = document.getElementById("edara_accsess_token").value;

                                          var ordersStatusArray = [];
                                          if(document.getElementById("orderStatusAny").checked){
                                             ordersStatusArray.push("any");
                                          }
                                          if(document.getElementById("orderStatusPending").checked){
                                             ordersStatusArray.push("pending");
                                          }
                                          if(document.getElementById("orderStatusProcessing").checked){
                                             ordersStatusArray.push("processing");
                                          }
                                          if(document.getElementById("orderStatusOnHold").checked){
                                             ordersStatusArray.push("on-hold");
                                          }
                                          if(document.getElementById("orderStatusCompleted").checked){
                                             ordersStatusArray.push("completed");
                                          }
                                          if(document.getElementById("orderStatusCanceled").checked){
                                             ordersStatusArray.push("cancelled");
                                          }
                                          if(document.getElementById("orderStatusRefunded").checked){
                                             ordersStatusArray.push("refunded");
                                          }
                                          if(document.getElementById("orderStatusFaild").checked){
                                             ordersStatusArray.push("failed");
                                          }
                                          if(document.getElementById("orderStatusDraft").checked){
                                             ordersStatusArray.push("checkout-draft");
                                          }

                                          var from_date = document.getElementById("from_date").value;
                                          // var newWHname = document.getElementById("newWHname").value;
                                          // var newStorename = document.getElementById("newStorename").value;

                                          var warehouses_selection = $("select#warehouses_selection option").filter(":selected").val();
                                          var stores_selection = $("select#stores_selection option").filter(":selected").val();
                                          var services_selection = $("select#services_selection option").filter(":selected").val();

                                          $.ajax({
                                             type: 'post',
                                             url: plugin_url + '/Includes/done.php',
                                             data: {
                                                   'edara_domain': edara_domain,
                                                   'edara_email': edara_email,
                                                   'edara_accsess_token': edara_accsess_token,
                                                   'products_selection': products_selection,
                                                   'customers_selection': customers_selection,
                                                   'orders_selection': orders_selection,
                                                   'from_date': from_date,
                                                   'warehouses_selection': warehouses_selection,
                                                   'stores_selection': stores_selection,
                                                   'services_selection': services_selection,
                                                   'sale_price': sale_price,
                                                   'orders_status': JSON.stringify(ordersStatusArray),
                                                   'customers_key': customer_key_selection
                                             },
                                             beforeSend: function(){
                                                $("#coverScreen").show();
                                             },
                                             complete: function(){
                                                $("#coverScreen").hide();
                                             },
                                             error: function (xhr, status, error) {
                                                   console.log(xhr);
                                                   console.log(status);
                                                   console.log(error);
                                                },
                                             success: function (message) {
                                                document.getElementById("cdk-step-content-0-4").setAttribute("aria-expanded", "false");
                                                document.getElementById("cdk-step-content-0-4").style.transform = "none";
                                                document.getElementById("cdk-step-content-0-4").style.visibility = "hidden";

                                                document.getElementById("cdk-step-content-0-5").setAttribute("aria-expanded", "true");
                                                document.getElementById("cdk-step-content-0-5").style.transform = "inherit";
                                                document.getElementById("cdk-step-content-0-5").style.visibility = "visible";
                                             }
                                          });


                                       }

                                    </script>
                                 </div>
                              </div>
                              <!---->
                           </div>
                           <div role="tabpanel" class="mat-horizontal-stepper-content ng-trigger ng-trigger-stepTransition ng-tns-c72-0 ng-star-inserted" id="cdk-step-content-0-5" aria-labelledby="cdk-step-label-0-5" aria-expanded="false" style="transform: translate3d(100%, 0px, 0px); visibility: hidden;">
                              <!---->
                              <div _ngcontent-uef-c91="" class="stepper_container ng-star-inserted" style="display: flex; flex-direction: column; align-items: center; justify-content: top; ">
                                 <div style="font-size: 24px; margin-bottom: 20px; margin-top: 25px;">
                                    Everything is Done, thank you
                                 </div>
                                 <button color="primary" class="mat-focus-indicator login-btn mat-raised-button mat-button-base mat-primary" onclick="goToDashboard()" id="">Go to Dashboard</button>
                              </div>
                              <script>
                                 function goToDashboard(){
                                    window.top.location.reload();
                                 }
                              </script>
                              <!---->
                           </div>
                           <!---->
                        </div>
                     </mat-horizontal-stepper>
                     <!----><!----><!---->
                  </app-login>
                  <!---->
               </div>
            </div>
            <!---->
         </app-root>
         <script src="https://shopi-intg.edara.io/runtime.0af26aa44dea9a6646ba.js" defer=""></script>
         <script src="https://shopi-intg.edara.io/main.789750c3f7b560abb112.js" defer=""></script>
         <div class="cdk-live-announcer-element cdk-visually-hidden" aria-atomic="true" aria-live="polite"></div>
         <script src='https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.2.1.js'></script>
      </body>
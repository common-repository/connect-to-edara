<?php
// var_dump($_POST);die();
declare(strict_types=1);
// $path = $_SERVER['DOCUMENT_ROOT'];
// $path = "../../../..";

$path = preg_replace( '/wp-content(?!.*wp-content).*/', '', __DIR__ );
require_once( $path . 'wp-load.php' );

// include_once $path . '/wp-config.php';
// include_once $path . '/wp-includes/wp-db.php';
// include_once $path . '/wp-includes/pluggable.php';

global $wpdb;

$result = $wpdb->update($wpdb->prefix.'edara_config', array(
    'is_installing' => 0
), array('id'=>1));

$currentProducts = $wpdb->get_var("SELECT COUNT(id) as count FROM ".$wpdb->prefix."edara_products");
$currentCustomers = $wpdb->get_var("SELECT COUNT(id) as count FROM ".$wpdb->prefix."edara_customers");

$responseArray = array();
$responseArray['currentProducts'] = $currentProducts;
$responseArray['currentCustomers'] = $currentCustomers;

echo json_encode($responseArray);

?>
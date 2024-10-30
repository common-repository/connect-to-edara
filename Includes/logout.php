<?php 
// var_dump($_POST);die();
// $path = $_SERVER['DOCUMENT_ROOT'];
$path = "../../../..";
 
include_once $path . '/wp-config.php';
include_once $path . '/wp-includes/wp-db.php';
include_once $path . '/wp-includes/pluggable.php';

global $wpdb;
$table = $wpdb->prefix."edara_config";
$wpdb->query('Drop TABLE '.$table.'');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_products');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_customers');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_orders');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_cities');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_currencies');
$wpdb->query('Drop TABLE '.$wpdb->prefix.'edara_taxes');

// Generate the correct admin URL
$redirect_url = admin_url('admin.php?page=edara_integration_dashboard');

// Use JavaScript to redirect to the correct URL
echo "<script>window.top.location.href = '{$redirect_url}';</script>";

exit();
?>
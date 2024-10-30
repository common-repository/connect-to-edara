<?php
// var_dump($_POST);die();
// $path = $_SERVER['DOCUMENT_ROOT'];
$path = "../../../..";
  
include_once $path . '/wp-config.php';
include_once $path . '/wp-includes/wp-db.php';
include_once $path . '/wp-includes/pluggable.php';

global $wpdb;

$edara_accsess_token = $_POST['edara_accsess_token'];
$newWHname = $_POST['newWHname'];

$url = "https://api.edara.io/v2.0/warehouses";
$data = array('description' => $newWHname);

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
header("Content-Type: text/json; charset=utf8");
if ($result['status_code'] == 200) {
    echo json_encode(array("success" => true,"message" => $result['result']));
}else{
    echo json_encode(array("success" => false,"error" => $result['error_message']));
}

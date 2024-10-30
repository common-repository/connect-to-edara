<?php

$edara_accsess_token = $_POST['edara_accsess_token'];
$token = $_POST['token'];

$url = "https://api.edara.io/v2.0/salesStores?limit=10000000&offset=0";

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
$result = json_decode($resp, true);

$responseArray = array();

if($result['status_code'] == 200){
  $responseArray = $result['result'];

  if (count($responseArray) > 0) {
    echo  "<option value='-1' disabled selected>Select store...</option>";

    foreach ($responseArray as $store) {
      echo "<option value='" . $store['id']. "'>" . $store['description'] . "</option>";
    }
  }else{
    echo  "<option value='-1'>No stores found</option>";
  }
}else{
  echo  "<option value='-1'>No stores found</option>";
}



?>

<?php


$edara_accsess_token = $_POST['edara_accsess_token'];
$url = "https://api.edara.io/v2.0/serviceItems?limit=10000000&offset=0";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$headers = array(
  "Accept: application/json",
  'method'  => 'GET',
  "Authorization:".$edara_accsess_token."",
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
$result = json_decode($resp, true);

if ($result['status_code'] == 200) {
  if (count($result['result']) > 0) {
     foreach ($result['result'] as $service) {
        echo "<option value='" . $service['id']. "'>" . $service['description'] . "</option>";
    }
  }else{
    echo  "<option value='no'>No service found</option>";
}

}else{
  echo  "<option value='no'>No service found</option>";
}



?>

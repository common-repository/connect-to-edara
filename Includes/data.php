<?php
// Collect data from POST request
$edara_email = $_POST['edara_email'];
$edara_password = $_POST['edara_password'];
$edara_domain = $_POST['edara_domain'];

// Prepare API request
$url = "https://api.edara.io/v2/tenants/ValidateUser";
$data = array('username' => $edara_email, 'password' => $edara_password, 'domain' => $edara_domain);

// Create HTTP context options
$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$result = json_decode($result, true);

header("Content-Type: text/json; charset=utf8");

// Check if the API call was successful
if ($result['status_code'] == 200) {
    $accessToken = $result['result']['access_token'];
    $tenantName = $result['result']['tenant']['tenant_name'];
    
    // Return success response with access token and tenant name
    echo json_encode(array(
        "success" => true,
        "data" => array(
            "access_token" => $accessToken,
            "tenant_name" => $tenantName
        )
    ));
} else {
    // Return error response
    echo json_encode(array("success" => false, "error" => $result['error_message']));
}
?>

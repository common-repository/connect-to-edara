<?php

$user_name = "WooConnector-" . $_POST['tenant_name'];
$password = time();
$edara_domain = $_POST['base_url'];
$token = $_POST['token'];

// Check if user already exists
$checkUrl = "https://{$edara_domain}/v1/integrations/users?username={$user_name}";
$ch = curl_init($checkUrl);
$headers = array(
    "Content-Type: application/json",
    "Authorization: " . $token,
);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);

curl_close($ch);
$result = json_decode($result, true);

if (isset($result['status_code']) && $result['status_code'] == 200 && isset($result['result']['access_token'])) {
    // User exists, return existing access token
    echo $result['result']['access_token'];
} else {
    // User does not exist, proceed to create user
    $url = "https://{$edara_domain}/v1/integrations";
    $roles = [
        "Edara.API.V2.Controllers.AccountingController.GetAllAccountsByAccountType",
        "Edara.API.V2.Controllers.WarehouseController.GetStockItemByCode",
        "Edara.API.V2.Controllers.WarehouseController.GetAllStockItems",
        "Edara.API.V2.Controllers.WarehouseController.GetStockItemByExternalId",
        "Edara.API.V2.Controllers.WarehouseController.UpdateStockItem",
        "Edara.API.V2.Controllers.WarehouseController.GetAllWarehouses",
        "Edara.API.V2.Controllers.WarehouseController.DeleteStockItemById",
        "Edara.API.V2.Controllers.WarehouseController.GetStockItemGlobalBalanceByID",
        "Edara.API.V2.Controllers.WarehouseController.GetStockItemById",
        "Edara.API.V2.Controllers.WarehouseController.AddStockItem",
        "Edara.API.V2.Controllers.WarehouseController.GetWorkOrderById",
        "Edara.API.V2.Controllers.WarehouseController.SearchStockItems",
        "Edara.API.V2.Controllers.SalesController.GetAllSalesPersons",
        "Edara.API.V2.Controllers.SalesController.UpdateSalesOrderByCode",
        "Edara.API.V2.Controllers.SalesController.GetCustomerByExternalId",
        "Edara.API.V2.Controllers.SalesController.GetAllSalesOrders",
        "Edara.API.V2.Controllers.SalesController.AddSalesOrder",
        "Edara.API.V2.Controllers.SalesController.AddCustomer",
        "Edara.API.V2.Controllers.SalesController.GetSalesOrderByExternalId",
        "Edara.API.V2.Controllers.SalesController.GetAllServiceItems",
        "Edara.API.V2.Controllers.SalesController.DeleteSalesOrderById",
        "Edara.API.V2.Controllers.SalesController.GetCustomerById",
        "Edara.API.V2.Controllers.SalesController.DeleteCustomerById",
        "Edara.API.V2.Controllers.SalesController.GetAllCustomers",
        "Edara.API.V2.Controllers.SalesController.UpdateCustomer",
        "Edara.API.V2.Controllers.SalesController.UpdateSalesOrder",
        "Edara.API.V2.Controllers.SalesController.DeactivateCustomerById",
        "Edara.API.V2.Controllers.SalesController.GetCustomerAddressesByCustomerId",
        "Edara.API.V2.Controllers.SalesController.CancelSalesOrderByCode",
        "Edara.API.V2.Controllers.SalesController.GetSalesOrderByCode",
        "Edara.API.V2.Controllers.SalesController.GetAllSalesStores",
        "Edara.API.V2.Controllers.CommonController.GetSettingById",
        "Edara.API.V2.Controllers.CommonController.GetCountryByName",
        "Edara.API.V2.Controllers.CommonController.AddCountry",
        "Edara.API.V2.Controllers.CommonController.AddCity",
        "Edara.API.V2.Controllers.CommonController.GetCityByName",
        "Edara.API.V2.Controllers.TenantsController.GetFirstTenant",
        "Edara.API.V2.Controllers.WarehouseController.AddWarehouse",
        "Edara.API.V2.Controllers.SalesController.AddSalesStore",
        "Edara.API.V2.Controllers.WarehouseController.UpdateStockItemExternalId",
        "Edara.API.V2.Controllers.WarehouseController.GetStockItemBySku",
        "Edara.API.V1.Controllers.SalesController.UpdateSalesOrderByCode",
        "Edara.API.V2.Controllers.SalesController.GetCustomerByEmail",
        "Edara.API.V2.Controllers.SalesController.GetCustomerByMobile",
        "Edara.API.V2.Controllers.WarehouseController.UpdateStockItemByCode",
        "Edara.API.V2.Controllers.AccountingController.GetAllTaxes",
        "Edara.API.V2.Controllers.AccountingController.GetTaxByRate",
        "Edara.API.V2.Controllers.AccountingController.GetTaxByName",
        "Edara.API.V2.Controllers.AccountingController.GetCurrencyById",
        "Edara.API.V2.Controllers.AccountingController.GetCurrencyByCode",
        "Edara.API.V2.Controllers.AccountingController.GetAllCurrencies",
        "Edara.API.V2.Controllers.WebhookController.GetWebhookByListenerDomain",
        "Edara.API.V2.Controllers.WebhookController.AddNewWebhook",
        "Edara.API.V2.Controllers.WebhookController.UpdateWebhook",
        "Edara.API.V2.Controllers.WebhookController.DeleteWebhook",
    ];
    $data = array('user_name' => $user_name, 'email' => $user_name, 'password' => $password, 'roles' => $roles);

    $ch = curl_init($url);
    $payload = json_encode($data);
    $headers = array(
        "Content-Type: application/json",
        "Authorization: " . $token,
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);

    curl_close($ch);
    $result = json_decode($result, true);

    if ($result['status_code'] == 200) {
        $fileData = file('../index.php');
        $newData = array();
        $lookFor = 'EDARA_BEARER_TOKEN';
        $newText = 'const EDARA_BEARER_TOKEN = "' . substr($result['result']['access_token'], 7) . '";' . PHP_EOL;
        foreach ($fileData as $fileRow) {
            if (strstr($fileRow, $lookFor) !== false) {
                $fileRow = $newText;
            }
            $newData[] = $fileRow;
        }
        file_put_contents('../index.php', $newData);
        echo $result['result']['access_token'];
    } else {
        echo json_encode(array("success" => false, "error" => $result['error_message']));
    }
}

?>
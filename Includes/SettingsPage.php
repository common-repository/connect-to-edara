<?php
defined('ABSPATH') || exit;

$success_message = ''; // Initialize the success message variable

// Function to fetch data from the API
function fetch_edara_data($url, $token) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $headers = array(
        "Accept: application/json",
        "Authorization: " . $token,
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($resp, true);

    if ($result && isset($result['status_code']) && $result['status_code'] == 200) {
        return $result['result'];
    } else {
        return [];
    }
}

// Get the Edara access token from the database
global $wpdb;
$edara_access_token = $wpdb->get_var("SELECT edara_accsess_token FROM " . $wpdb->prefix . "edara_config WHERE id = 1");

// Fetch warehouses, sales stores, and service items
$warehouses = fetch_edara_data("https://api.edara.io/v2.0/warehouses?limit=10000000&offset=0", $edara_access_token);
$sales_stores = fetch_edara_data("https://api.edara.io/v2.0/salesStores?limit=10000000&offset=0", $edara_access_token);
$service_items = fetch_edara_data("https://api.edara.io/v2.0/serviceItems?limit=10000000&offset=0", $edara_access_token);

// Retrieve the saved values from the edara_config table
$edara_config = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "edara_config WHERE id = 1", ARRAY_A);

// Remove backslashes from the JSON string
$cleaned_order_status = stripslashes($edara_config['orders_status']);

// Decode the order status options
$order_status_options = json_decode($cleaned_order_status ?? '[]', true);

$products_import_option = $edara_config['products_selection'] ?? '';
$customer_import_option = $edara_config['customers_selection'] ?? '';
$order_import_option = $edara_config['orders_selection'] ?? '';
$receive_orders_option = ($edara_config['warehouses_selection'] != "-1") ? 'warehouse' : 'store';
$warehouse_store_option = ($receive_orders_option == 'warehouse') ? $edara_config['warehouses_selection'] : $edara_config['stores_selection'];
$product_price_option = $edara_config['sale_price'] ?? '';
$service_item_option = $edara_config['service_item'] ?? '';
$customer_primary_key_option = $edara_config['customers_key'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edara_save_settings'])) {
    // Collect the updated values from the form
    $updated_order_status = isset($_POST['edara_order_status']) ? $_POST['edara_order_status'] : [];
    $updated_order_import = sanitize_text_field($_POST['edara_order_import']);
    $updated_receive_orders = sanitize_text_field($_POST['edara_receive_orders']);
    $updated_warehouse_store = sanitize_text_field($_POST['edara_warehouse_store']);
    $updated_product_price = sanitize_text_field($_POST['edara_product_price']);
    $updated_service_item = sanitize_text_field($_POST['edara_service_item']);
    $updated_customer_primary_key = sanitize_text_field($_POST['edara_customer_primary_key']);

    // Convert the order status array to a JSON string
    $order_status_json = json_encode($updated_order_status);

    // Manually escape the double quotes
    $escaped_order_status_json = addslashes($order_status_json);

    // Update the database with the new values
    $wpdb->update(
        $wpdb->prefix . 'edara_config',
        array(
            'orders_status' => $escaped_order_status_json,
            'orders_selection' => $updated_order_import,
            'warehouses_selection' => ($updated_receive_orders === 'warehouse') ? $updated_warehouse_store : '-1',
            'stores_selection' => ($updated_receive_orders === 'store') ? $updated_warehouse_store : '-1',
            'sale_price' => $updated_product_price,
            'service_item' => $updated_service_item,
            'customers_key' => $updated_customer_primary_key,
        ),
        array('id' => 1)
    );

    // Set the success message
    $success_message = 'Your changes have been saved successfully.';

    // Reload the page to reflect the changes and show success message
    wp_redirect(add_query_arg(array('message' => 'success'), $_SERVER['REQUEST_URI']));
    exit;
}

// Show success message after page reload
if (isset($_GET['message']) && $_GET['message'] === 'success') {
    $success_message = 'Your changes have been saved successfully.';
}
?>

<div class="wrap">
    <h1>
        <?php esc_html_e('Integration Settings', 'edara-connect'); ?>
        <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/edara-logo.svg'; ?>" alt="Edara Logo" style="float:right; height: 20px;">
    </h1>
    <p><?php esc_html_e('Manage your integration settings here.', 'edara-connect'); ?></p>

    <!-- Display the success message if available -->
    <?php if (!empty($success_message)) : ?>
        <div id="message" class="updated notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <table class="form-table">
            <!-- Products Import Field -->
            <tr valign="top">
                <th scope="row">
                    <label for="edara_products_import">Products Import</label>
                </th>
                <td>
                    <select name="edara_products_import" id="edara_products_import" class="wc-enhanced-select" style="width: 300px;" disabled>
                        <option value="edara_to_wp" <?php selected($products_import_option, 'edara_to_wp'); ?>>From Edara to Wordpress</option>
                        <option value="wp_to_edara" <?php selected($products_import_option, 'wp_to_edara'); ?>>From Wordpress to Edara</option>
                        <option value="no" <?php selected($products_import_option, 'no'); ?>>No import</option>
                    </select>
                </td>
            </tr>

            <!-- Customer Import Field -->
            <tr valign="top">
                <th scope="row">
                    <label for="edara_customer_import">Customer Import</label>
                </th>
                <td>
                    <select name="edara_customer_import" id="edara_customer_import" class="wc-enhanced-select" style="width: 300px;" disabled>
                        <option value="all_customers" <?php selected($customer_import_option, 'all_customers'); ?>>All Customers</option>
                        <option value="new_customers" <?php selected($customer_import_option, 'new_customers'); ?>>New Customers</option>
                    </select>
                </td>
            </tr>

            <!-- Order Import Field -->
            <tr valign="top">
                <th scope="row">
                    <label for="edara_order_import">Order Import</label>
                </th>
                <td>
                    <select name="edara_order_import" id="edara_order_import" class="wc-enhanced-select" style="width: 300px;" disabled>
                        <option value="new_orders" <?php selected($order_import_option, 'new_orders'); ?>>New Orders</option>
                    </select>
                </td>
            </tr>

            <!-- Order Status Field -->
            <tr valign="top">
                <th scope="row">Order Status</th>
                <td>
                    <label><input type="checkbox" name="edara_order_status[]" value="any" <?php checked(in_array('any', $order_status_options)); ?> class="order-status-checkbox" id="any-checkbox"> Any</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="pending" <?php checked(in_array('pending', $order_status_options)); ?> class="order-status-checkbox"> Pending payment</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="processing" <?php checked(in_array('processing', $order_status_options)); ?> class="order-status-checkbox"> Processing</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="on-hold" <?php checked(in_array('on-hold', $order_status_options)); ?> class="order-status-checkbox"> On-hold</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="completed" <?php checked(in_array('completed', $order_status_options)); ?> class="order-status-checkbox"> Completed</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="cancelled" <?php checked(in_array('cancelled', $order_status_options)); ?> class="order-status-checkbox"> Canceled</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="refunded" <?php checked(in_array('refunded', $order_status_options)); ?> class="order-status-checkbox"> Refunded</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="failed" <?php checked(in_array('failed', $order_status_options)); ?> class="order-status-checkbox"> Failed</label><br>
                    <label><input type="checkbox" name="edara_order_status[]" value="checkout-draft" <?php checked(in_array('checkout-draft', $order_status_options)); ?> class="order-status-checkbox"> Draft</label>
                </td>
            </tr>

            <!-- Receive Orders and Warehouse/Store Fields -->
            <tr valign="top">
                <th scope="row">
                    <label for="edara_receive_orders">Receive Orders On</label>
                </th>
                <td>
                    <div style="display: flex; align-items: center;">
                        <select name="edara_receive_orders" id="edara_receive_orders" class="wc-enhanced-select" style="width: 300px; margin-right: 10px;">
                            <option value="warehouse" <?php selected($receive_orders_option, 'warehouse'); ?>>Warehouse</option>
                            <option value="store" <?php selected($receive_orders_option, 'store'); ?>>Store</option>
                        </select>

                        <select name="edara_warehouse_store" id="edara_warehouse_store" class="wc-enhanced-select" style="width: 300px;">
                            <?php if ($receive_orders_option == 'warehouse'): ?>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo esc_attr($warehouse['id']); ?>" <?php selected($warehouse_store_option, $warehouse['id']); ?>>
                                        <?php echo esc_html($warehouse['description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif ($receive_orders_option == 'store'): ?>
                                <?php foreach ($sales_stores as $store): ?>
                                    <option value="<?php echo esc_attr($store['id']); ?>" <?php selected($warehouse_store_option, $store['id']); ?>>
                                        <?php echo esc_html($store['description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </td>
            </tr>

            <!-- Product Price Field -->
            <tr valign="top">
                <th scope="row">
                    <label for="edara_product_price">Product Price</label>
                </th>
                <td>
                    <select name="edara_product_price" id="edara_product_price" class="wc-enhanced-select" style="width: 300px;">
                        <option value="sale_price" <?php selected($product_price_option, 'sale_price'); ?>>Sale Price</option>
                    </select>
                </td>
            </tr>

            <!-- Service Item Field -->
            <tr valign="top">
                <th scope="row">
                    <label for="edara_service_item">Service Item</label>
                </th>
                <td>
                    <select name="edara_service_item" id="edara_service_item" class="wc-enhanced-select" style="width: 300px;">
                        <?php foreach ($service_items as $service_item): ?>
                            <option value="<?php echo esc_attr($service_item['id']); ?>" <?php selected($service_item_option, $service_item['id']); ?>><?php echo esc_html($service_item['description']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Customer Primary Key Field -->
            <tr valign="top">
                <th scope="row">
                    <label for="edara_customer_primary_key">Customer Primary Key</label>
                </th>
                <td>
                    <select name="edara_customer_primary_key" id="edara_customer_primary_key" class="wc-enhanced-select" style="width: 300px;">
                        <option value="email" <?php selected($customer_primary_key_option, 'email'); ?>>Email</option>
                        <option value="phone" <?php selected($customer_primary_key_option, 'phone'); ?>>Phone</option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="edara_save_settings" id="submit" class="button button-primary" value="Save Changes" />
        </p>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const anyCheckbox = document.getElementById('any-checkbox');
    const otherCheckboxes = document.querySelectorAll('.order-status-checkbox:not(#any-checkbox)');
    const receiveOrdersDropdown = document.getElementById('edara_receive_orders');
    const warehouseStoreDropdown = document.getElementById('edara_warehouse_store');

    // If "Any" is checked, select all checkboxes
    anyCheckbox.addEventListener('change', function() {
        if (this.checked) {
            otherCheckboxes.forEach(checkbox => checkbox.checked = true);
        }
    });

    // If any other checkbox is unchecked, uncheck "Any"
    otherCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                anyCheckbox.checked = false;
            }
        });
    });

    function updateWarehouseStoreOptions() {
        const selectedOption = receiveOrdersDropdown.value;
        let options = '';

        if (selectedOption === 'warehouse') {
            <?php foreach ($warehouses as $warehouse): ?>
                options += '<option value="<?php echo esc_attr($warehouse['id']); ?>" <?php if($warehouse_store_option == $warehouse['id']) echo "selected"; ?>><?php echo esc_html($warehouse['description']); ?></option>';
            <?php endforeach; ?>
        } else if (selectedOption === 'store') {
            <?php foreach ($sales_stores as $store): ?>
                options += '<option value="<?php echo esc_attr($store['id']); ?>" <?php if($warehouse_store_option == $store['id']) echo "selected"; ?>><?php echo esc_html($store['description']); ?></option>';
            <?php endforeach; ?>
        }

        warehouseStoreDropdown.innerHTML = options;
        warehouseStoreDropdown.value = '<?php echo esc_attr($warehouse_store_option); ?>';
    }

    receiveOrdersDropdown.addEventListener('change', updateWarehouseStoreOptions);
    updateWarehouseStoreOptions(); // Initial load
});
</script>

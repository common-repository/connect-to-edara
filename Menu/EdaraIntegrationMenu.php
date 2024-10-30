<?php
declare(strict_types=1);

namespace Edara\Menu;

use Edara\Includes\ProductTaxRate;
use Views\ProductsTable;

class EdaraIntegrationMenu {

    public function init() {
        add_action('admin_menu', [$this, 'EdaraAdminMenu']);
    }

    public function Dashboard() {
        $plugin_dir_url = plugin_dir_url(__DIR__); // Use __DIR__ to get the directory of the current file
        $iframe_src = $plugin_dir_url . 'Includes/EdaraIntegration.php'; // Adjust the path to the Includes folder
        echo '<iframe id="edara_iframe" src="' . esc_url($iframe_src) . '" height="900px" width="100%" title="description"></iframe>';
    }

    public function enqueue_wc_admin_styles() {
        wp_enqueue_style('woocommerce_admin_styles');
    }

    public function SettingsPage() {
        // Check if the settings page exists
        $file_path = plugin_dir_path(__FILE__) . '../Includes/SettingsPage.php';
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo 'Settings page not found: ' . esc_html($file_path);
        }
    }

    public function EdaraAdminMenu() {
        // Add the main menu item for the Dashboard (this will also add it as the first submenu)
        add_menu_page(
            'Edara Integration Dashboard',
            'Edara Integration',
            'manage_options',
            'edara_integration_dashboard',
            [$this, 'Dashboard'],
            'data:image/svg+xml;base64,' . base64_encode(file_get_contents(plugin_dir_path(__FILE__) . '/../assets/edara-favicon.svg')),
            null
        );
    
        // Rename the first submenu to Dashboard (this is automatically created by add_menu_page)
        add_submenu_page(
            'edara_integration_dashboard', // Parent slug
            'Dashboard',                    // Page title
            'Dashboard',                    // Menu title
            'manage_options',               // Capability
            'edara_integration_dashboard',  // Menu slug
            [$this, 'Dashboard'],           // Callback function
            null                            // Position
        );
    
        // Check if the 'edara_config' table exists before adding the Settings submenu
        global $wpdb;
        $table_name = $wpdb->prefix . 'edara_config';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            // Add a submenu for the Settings page
            add_submenu_page(
                'edara_integration_dashboard', // Parent menu slug
                'Integration Settings',        // Page title
                'Settings',                    // Menu title
                'manage_options',              // Capability
                'edara_integration_settings',  // Menu slug
                [$this, 'SettingsPage'],       // Callback function
                null                           // Position
            );
        }
    
        // Enqueue WooCommerce styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_wc_admin_styles']);
    }
}
?>
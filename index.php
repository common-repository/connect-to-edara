<?php
declare( strict_types=1 );
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://edraksoftware.com/
 * @since             1.0.0
 * @package           Edara-ERP-Integration
 *
 * @wordpress-plugin
 * Plugin Name:       Connect to edara
 * Plugin URI:        https://edraksoftware.com/
 * Description:       Control your business with Edara ERP.
 * Version:           14.24091.0
 * Author:            Edrak Software
 * Author URI:        https://edraksoftware.com/
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// phpcs:ignore
const ENDPOINT_AUTHORIZATION_TOKEN = 'E3hAOC0YlE3hAOC0YlFh0VtIf9Pr5mH0GjN32vq6yFh0VtIf9Pr5mH0GjN32vq6y';
const EDARA_DATE_TIME_FORMAT = 'Y-M-d H:i:s';
const EDARA_INTEGRATION_PLUGIN_IS_PRODUCTION = true;
const EDARA_MAIN_WAREHOUSE_ID = 2;
const EDARA_PARENT_CLASSIFICATION_ID = EDARA_INTEGRATION_PLUGIN_IS_PRODUCTION ? 109 : 112;
const EDARA_DEFAULT_CLASSIFICATION = EDARA_INTEGRATION_PLUGIN_IS_PRODUCTION ? 111 : 132;
// Edara authorization token goes here
const EDARA_BEARER_TOKEN = "J/jp5p4cQINccgYC3GjcspQpoRL5T/KI9obXK0WxYXV1EziZCCu584sh7rRsZvORcPRG+dIJGWDCO8eHrtEI+Q==";
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
const EDARA_INTEGRATION_VERSION = '1.0.0';

require dirname(__FILE__) . '/vendor/autoload.php';

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once dirname(__FILE__) . '/Helpers.php';
require_once dirname(__FILE__) . '/Includes/EdaraEndpoint.php';
require_once dirname(__FILE__) . '/Includes/EdaraCore.php';
require_once dirname(__FILE__) . '/Menu/EdaraIntegrationMenu.php';

(new \Edara\Includes\EdaraCore())->init();
(new \Edara\Menu\EdaraIntegrationMenu())->init();

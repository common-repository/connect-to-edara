<?php
declare(strict_types=1);
  
namespace Edara\Includes;

class EdaraEndpoint
{

    /**
     * Register hooks
     */
    public function addHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRestEndpoint']);
    }

    /**
     * Register Rest endpoint to receive calls from Edara Observers
     */
    public function registerRestEndpoint(): void
    {
        register_rest_route(
            'edara-integration',
            'callback',
            [
                'methods' => 'POST',
                'callback' => [$this, 'handler'],
                'permission_callback' => [$this, 'authorize'],
            ]
        );
    }

    /**
     * Handler of Edara observer requests
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function handler(\WP_REST_Request $request): array
    {
        $action = $request->get_param('type');
        do_action("edara_{$action}_callback", json_decode($request->get_body(), true));

        return [$action];
    }

    /**
     * Check if the authorize to access the end point
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function authorize(\WP_REST_Request $request): bool
    {
        # If edara integration is not on production always allow any one
        if (!EDARA_INTEGRATION_PLUGIN_IS_PRODUCTION) {
            return true;
        }

        $consumerKey = wc_api_hash(sanitize_text_field($request->get_param('consumer_key')));
        $consumerSecret = $request->get_param('consumer_secret');

        global $wpdb;
        $output = $wpdb->get_row(
            $wpdb->prepare(
                "
			SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
			FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE consumer_key = %s and consumer_secret = '{$consumerSecret}'
		", $consumerKey)
        );

        return $output ? true : false;
    }
}

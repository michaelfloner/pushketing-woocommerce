<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Pushketing Integration
 *
 * @class   WC_Pushketing
 * @extends WC_Integration
 */
class WC_Pushketing extends WC_Integration {

    /**
     * endpoint api for pushketing tag.
     */
    const END_POINT = 'https://pushketing.online/api/tag';

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id                    = 'pushketing';
		$this->method_title          = __( 'Pushketing', 'woocommerce_pushketing-integration' );
        $this->method_desc = __('Pushketing is a msg from fb');
		$this->dismissed_info_banner = get_option( 'woocommerce_dismissed_info_banner' );

		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();

		if ( is_admin() ) {
			include_once( 'pushketing-admin.php' );
			WC_Pushketing_Admin::get_instance( $this->dismissed_info_banner, $this->ga_id );
		}

		// Admin Options
		add_filter( 'woocommerce_tracker_data', array( $this, 'track_options' ) );
		add_action( 'woocommerce_update_options_integration_pushketing', array( $this, 'process_admin_options') );
      add_action( 'woocommerce_update_options_integration_pushketing', array( $this, 'show_options_info') );

		// Tracking code
		add_action( 'wp_head', array( $this, 'tracking_code_display' ), 999999 );

        // Event tracking code
        add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), 10, 6 );
        add_action( 'woocommerce_cart_item_removed', array( $this, 'remove_from_cart' ) );
        add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );

	}

	/**
	 * Loads all of our options for this plugin
	 * @return array An array of options that can be passed to other classes
	 */
	public function init_options() {
		$options = array(
			'pushketing_id',
			'pushketing_tracking_enabled'
		);

		$constructor = array();
		foreach ( $options as $option ) {
			$constructor[ $option ] = $this->$option = $this->get_option( $option );
		}

		return $constructor;
	}
	

	/**
	 * Integration tab.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
            'pushketing_id' => array(
                'title'       => __( 'Pushketing ID', 'woocommerce_pushketing-integration'),
                'description' => __( 'Contact pushketing TEAM and get application ID <code>xxxxxxxxxxxxx</code>', 'woocommerce_pushketing-integration' ),
                'type'        => 'text',
                'placeholder' => 'xxxxxxxxxxxxx',
                'default'     => get_option( 'woocommerce_pushketing_id' ) // Backwards compat
            ),
            'pushketing_tracking_enabled' => array(
                'title'         => __( 'Tracking Options', 'woocommerce_pushketing-integration' ),
                'label'         => __( 'Enable Tracking', 'woocommerce_pushketing-integration' ),
                'description'   =>  __( 'Enable pushketing tracking.', 'woocommerce_pushketing-integration' ),
                'type'          => 'checkbox',
                'checkboxgroup' => 'start',
                'default'       => get_option( 'woocommerce_pushketing_tracking_enabled' ) ? get_option( 'woocommerce_pushketing_tracking_enabled' ) : 'no'  // Backwards compat
            )
		);
	}

    /**
     * Some notice help.
     */
    function show_options_info() {
        $this->method_description .= "<div class='notice notice-info'><p>" . __( 'Save and tracking start.', 'woocommerce_pushketing-integration' ) . "</p></div>";
    }

    /**
     * Hooks into woocommerce_tracker_data and tracks some of the analytic settings (just enabled|disabled status)
     * only if you have opted into WooCommerce tracking
     * http://www.woothemes.com/woocommerce/usage-tracking/
     */
    function track_options( $data ) {
        $data['wc-pushketing'] = [
            'pushketing_tracking_enabled'   		=> $this->pushketing_tracking_enabled
        ];
        return $data;
    }


    /**
     * Tracking.
     */
    public function tracking_code_display() {
        global $wp;

        if ( $this->disable_tracking( 'all' ) ) {
            return;
        }

        // Check if is order received page and stop when the products and not tracked
        if ( is_order_received_page() && 'yes' === $this->push_ecommerce_tracking_enabled ) {
            $order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;
            if (0 < $order_id && 1 != get_post_meta($order_id, '_push_tracked', true)) {
                $this->get_ecommerce_tracking_code($order_id);
            }
        }

    }


    /**
     * Push tracking order.
     *
     * @param int $order_id
     */
    protected function get_ecommerce_tracking_code( $order_id ) {
        // Get the order and output tracking code
        $order = new WC_Order( $order_id );
        $productsIds = [];

        if ($order->get_items()) {
            foreach ($order->getItems() as $item) {
                $productsIds[] = $item->get_sku() ? $item->get_sku : $item->get_id();
            }
        }

        $this->postTag('wo_finalize_order', $productsIds, $_COOKIE['user']);
    }

    /**
     * Check if tracking is disabled
     *
     * @param string $type The setting to check
     *
     * @return bool True if tracking for a certain setting is disabled
     */
    private function disable_tracking( $type ) {
        if ( is_admin() || current_user_can( 'manage_options' ) || ( ! $this->pushketing_id ) || 'no' === $type || apply_filters( 'woocommerce_pushketing_tracking_enabled', false, $type ) ) {
            return true;
        }
    }

    /**
     * Tracking add to cart.
     *
     * @return void
     */
    public function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_dat) {
        if ( $this->disable_tracking( $this->pushketing_tracking_enabled ) ) {
            return;
        }

        $this->postTag('wo_add_to_cart', [$product_id],  $_COOKIE['user']);
    }

    /**
     * Tracking remove from cart.
     */
    public function remove_from_cart($cart_item_key, $cart) {

        $productId = $cart->cart_contents[ $cart_item_key ]['product_id'];

        $this->postTag('wo_remove_from_cart', [$productId],  $_COOKIE['user']);
    }


    /**
     * Tracking detail view.
     */
    public function product_detail() {
        global $product;
        $productId = $product->get_sku() ? $product->get_sku() : $product->get_id();
        $this->postTag('wo_view_detail', [$productId],  $_COOKIE['user']);
    }
    /**
     * Client POST API request sending tag (keyword=value) about specific customer.
     *
     * @param $keyword
     * @param $value
     * @param $customer
     *
     */
    public function postTag($keyword, $value, $customer) {
        $tag = array(
            'keyword' => $keyword,
            'value' => $value
        );
        $request = array(
            'timestamp' => time(),
            'token' => $this->pushketing_id,
            'subscriber_id' => $customer,
            'tag' => array($tag)
        );
        $ch = curl_init(self::END_POINT);
        $payload = json_encode($request);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers = array(
            'Content-Type: application/json'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}

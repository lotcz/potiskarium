<?php
/**
 * Plugin Name: ComGate Gateway
 * Description: Simple ComGate payment gateway for WooCommerce
 * Version: 0.0.1
 * Author: Karel
 * Text Domain: karel-comgate-gateway
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

if (!defined( 'ABSPATH')) {
	exit;
}

// Declare compatibility with WooCommerce features
add_action('before_woocommerce_init', function() {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
});

function karel_comgate_plugin_add_gateway($gateways) {
	require_once __DIR__ . '/includes/WC_Gateway_Comgate_Simple.php';
	$gateways[] = 'WC_Gateway_Comgate_Simple';
	return $gateways;
}

function karel_comgate_plugin_init() {
	if (!class_exists('WC_Payment_Gateway')) {
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p><strong>ComGate Payment Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
		} );
		return;
	}

	add_filter('woocommerce_payment_gateways', 'karel_comgate_plugin_add_gateway');
}

add_action('plugins_loaded', 'karel_comgate_plugin_init', 11);

function karel_comgate_plugin_activate_block() {
	if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		require_once __DIR__ . '/includes/Comgate_Gateway_Blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function($registry) {
				$registry->register(new Comgate_Gateway_Blocks());
			}
		);
	}
}

add_action('woocommerce_blocks_loaded', 'karel_comgate_plugin_activate_block');

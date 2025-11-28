<?php
/**
 * Plugin Name: ComGate Gateway
 * Description: Simple ComGate payment gateway for WooCommerce
 * Version: 0.0.1
 * Author: Karel
 * Text Domain: karel-comgate-gateway
 */

if (!defined( 'ABSPATH')) {
	exit;
}

function wc_comgate_init() {
	if (!class_exists('WC_Payment_Gateway')) {
		add_action( 'admin_notices', function() {
			echo '<div class="error"><p><strong>ComGate Payment Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
		} );
		return;
	}

	function wc_add_comgate_simple_gateway($gateways) {
		require_once __DIR__ . '/includes/WC_Gateway_Comgate_Simple.php';
		$gateways[] = 'WC_Gateway_Comgate_Simple';
		return $gateways;
	}

	add_filter('woocommerce_payment_gateways', 'wc_add_comgate_simple_gateway');

	wp_register_script(
		'comgate_blocks',
		plugins_url( 'includes/blocks.js', __FILE__ ),
		[
			'wp-element',
			'wp-i18n',
			'wc-blocks-registry',
			'wc-settings',
		],
		'1.0',
		true
	);

	wp_script_add_data('comgate_blocks', 'type', 'module');

	if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		require_once __DIR__ . '/includes/Comgate_Gateway_Blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function($registry) {
				$registry->register(new Comgate_Gateway_Blocks);
			}
		);
	}
}

add_action('plugins_loaded', 'wc_comgate_init', 11);

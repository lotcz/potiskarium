<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Comgate_Gateway_Blocks extends AbstractPaymentMethodType {

	protected $name = 'karel_comgate_plugin_payment';

	private $gateway;

	private function is_enabled() {
		return !empty($this->settings['enabled']) && $this->settings['enabled'] === 'yes';
	}

	private function is_gateway_available() {
		return !empty($this->gateway) && $this->gateway->is_available();
	}

	public function is_active() {
		return $this->is_enabled() && $this->is_gateway_available();
	}

	public function initialize() {
		$this->settings = get_option('woocommerce_karel_comgate_plugin_payment_settings', []);
		$gateways = WC()->payment_gateways->payment_gateways();
		$this->gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : null;
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'karel_comgate_plugin_blocks',
			plugins_url( '../build/payment-gateway-block/block.js', __FILE__ ),
			[
				'wp-element',
				'wp-i18n',
				'wc-blocks-registry',
				'wc-settings',
			],
			'2.1',
			true
		);

		wp_script_add_data('karel_comgate_plugin_blocks', 'type', 'module');

		return ['karel_comgate_plugin_blocks'];
	}

	public function get_payment_method_data() {
		return array(
			'title' => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports' => array_filter($this->gateway->supports, array($this->gateway, 'supports')),
			'test_mode' => $this->get_setting('test_mode') === 'yes',
		);
	}
}

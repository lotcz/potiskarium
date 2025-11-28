<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Comgate_Gateway_Blocks extends AbstractPaymentMethodType {

	protected $name = 'karel_comgate_plugin_payment';

	public function is_enabled() {
		return !empty($this->settings['enabled']) && $this->settings['enabled'] === 'yes';
	}

	public function is_active() {
		return $this->is_enabled();
	}

	public function initialize() {
		$this->settings = get_option('woocommerce_karel_comgate_plugin_payment_settings', []);
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'karel_comgate_plugin_blocks',
			plugins_url( '/blocks.js', __FILE__ ),
			[
				'wp-element',
				'wp-i18n',
				'wc-blocks-registry',
				'wc-settings',
			],
			'1.4',
			true
		);

		wp_script_add_data('karel_comgate_plugin_blocks', 'type', 'module');

		return ['karel_comgate_plugin_blocks'];
	}

	public function get_payment_method_data() {
		return [
			'title' => 'ComGate – Platba kartou',
			'enabled' => $this->is_enabled(),
		];
	}
}

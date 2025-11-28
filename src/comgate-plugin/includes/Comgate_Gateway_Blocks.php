<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Comgate_Gateway_Blocks extends AbstractPaymentMethodType {

	protected $name = 'comgate_simple';

	public function is_enabled() {
		return $this->settings['enabled'] === 'yes';
	}

	public function is_active() {
		return $this->is_enabled();
	}

	public function initialize() {
		$this->settings = get_option('woocommerce_comgate_simple_settings', []);
	}

	public function get_payment_method_script_handles() {
		return ['comgate_blocks'];
	}

	public function get_payment_method_data() {
		return [
			'title' => 'ComGate – Platba kartou',
			'enabled' => $this->is_enabled(),
		];
	}
}

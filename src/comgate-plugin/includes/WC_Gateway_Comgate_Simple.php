<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_Gateway_Comgate_Simple extends WC_Payment_Gateway {

	public $merchant_id;

	public $secret;

	public $test_mode;

	public $api_url;

	public $redirect_url;

	public function __construct() {
		$this->id                 = 'karel_comgate_plugin_payment';
		$this->icon               = ''; // URL to an icon
		$this->has_fields         = false;
		$this->method_title       = __( 'ComGate Gateway', 'karel' );
		$this->method_description = __( 'Simple ComGate gateway.', 'karel' );

		// This is required for redirect gateways
		$this->order_button_text = __('Proceed to ComGate', 'woocommerce');

		$this->init_form_fields();
		$this->init_settings();

		// user settings
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->merchant_id  = $this->get_option( 'merchant_id' );
		$this->secret       = $this->get_option( 'secret' );
		$this->test_mode    = 'yes' === $this->get_option( 'test_mode' );

		$this->enabled      = $this->get_option( 'enabled' );

		$this->api_url = 'https://payments.comgate.cz/v1.0/create';

		// mark that this gateway supports basic features
		$this->supports = array( 'products' );

		// hooks
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action('woocommerce_api_' . $this->id, array( $this, 'check_comgate_response' ) );
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

		// Add REST API support for Blocks
		add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_payment_for_blocks'), 10, 2);

		// TEST MODE
		if ($this->test_mode) {
			add_filter( 'query_vars', function( $vars ) {
				$vars[] = 'comgate_mock_page';
				return $vars;
			});

			add_action( 'template_redirect', function () {
				if ( get_query_var( 'comgate_mock_page' ) ) {
					comgate_show_mock_payment_page();
					exit;
				}
			});

			$this->api_url = get_rest_url(null, 'comgate-mock-api');
		}
	}

	public function get_redirect_url($orderId, $transId) {
		return $this->test_mode ?
			get_site_url(null, 'comgate-mock-page') . '?trans_id=' . $transId . '&order_id=' . $orderId
			: 'https://payments.comgate.cz/client/instructions/index?id=' . $transId;
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'wc-comgate-simple' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable ComGate (Simple) Gateway', 'wc-comgate-simple' ),
				'default' => 'yes'
			),
			'title' => array(
				'title'       => __( 'Title', 'wc-comgate-simple' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'wc-comgate-simple' ),
				'default'     => __( 'Card payment (ComGate)', 'wc-comgate-simple' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'wc-comgate-simple' ),
				'type'        => 'textarea',
				'default'     => __( 'You will be redirected to a secure payment gateway to complete the payment.', 'wc-comgate-simple' ),
			),
			'merchant_id' => array(
				'title'       => __( 'Merchant ID', 'wc-comgate-simple' ),
				'type'        => 'text',
			),
			'secret' => array(
				'title'       => __( 'Secret / API key', 'wc-comgate-simple' ),
				'type'        => 'text',
			),
			'test_mode' => array(
				'title'       => __( 'Test mode', 'wc-comgate-simple' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable test mode', 'wc-comgate-simple' ),
				'default'     => 'yes',
			)
		);
	}

	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Process payment for WooCommerce Blocks
	 */
	public function process_payment_for_blocks($context, $result) {
		if ($context->payment_method !== $this->id) {
			return;
		}

		// Process the payment
		$order = $context->order;
		$payment_result = $this->process_payment($order->get_id());

		if ($payment_result['result'] === 'success') {
			$result->set_status('success');
			$result->set_redirect_url($payment_result['redirect']);
		} else {
			$result->set_status('failure');
			if (isset($payment_result['messages'])) {
				$result->set_payment_details(array(
					'errorMessage' => $payment_result['messages']
				));
			}
		}
	}

	/**
	 * Process the payment
	 */
	public function process_payment($order_id) {
		$order = wc_get_order($order_id);

		if (!$order) {
			return array(
				'result' => 'failure',
				'messages' => 'Invalid order'
			);
		}

		// Prepare payment data
		$payment_data = array(
			'merchant' => $this->merchant_id,
			'secret' => $this->secret,
			'price' => round($order->get_total() * 100), // Amount in cents (lowest denomination)
			'curr' => $order->get_currency(),
			'label' => 'Order #' . $order->get_order_number(),
			'refId' => (string)$order_id,
			'method' => 'ALL', // Allow all payment methods
			'prepareOnly' => 'true',
			'lang' => $this->get_language(),
			'country' => $order->get_billing_country(),
			'email' => $order->get_billing_email(),
			'returnUrl' => $this->get_return_url($order),
			'notifyUrl' => WC()->api_request_url('wc_gateway_comgate'),
		);

		// Log for debugging
		if ($this->test_mode) {
			error_log('ComGate Payment Data: ' . print_r($payment_data, true));
		}

		// Create payment
		$response = $this->create_payment($payment_data);

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			$order->add_order_note('ComGate payment error: ' . $error_message);

			return array(
				'result' => 'failure',
				'messages' => $error_message
			);
		}

		if (isset($response['code']) && $response['code'] === '0' && isset($response['transId'])) {
			// Store transaction ID
			$order->update_meta_data('_comgate_transaction_id', $response['transId']);
			$order->save();

			// Mark as pending
			$order->update_status('pending', __('Awaiting ComGate payment', 'woocommerce'));

			// Empty cart
			if (function_exists('WC')) {
				WC()->cart->empty_cart();
			}

			// Redirect to ComGate payment page
			$payment_url = $this->get_redirect_url($order_id, $response['transId']);

			if ($this->test_mode) {
				error_log('ComGate Redirect URL: ' . $payment_url);
			}

			return array(
				'result' => 'success',
				'redirect' => $payment_url
			);
		}

		$error_message = isset($response['message']) ? $response['message'] : 'Unknown error occurred';
		$order->add_order_note('ComGate payment failed: ' . $error_message);

		return array(
			'result' => 'failure',
			'messages' => $error_message
		);
	}

	/**
	 * Create payment via ComGate API
	 */
	private function create_payment($data) {
		$response = wp_remote_post($this->api_url, array(
			'method' => 'POST',
			'timeout' => 45,
			'body' => $data,
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded'
			),
			'sslverify' => !$this->test_mode // Verify SSL in production
		));

		if (is_wp_error($response)) {
			if ($this->test_mode) {
				error_log('ComGate API Error: ' . $response->get_error_message());
			}
			return $response;
		}

		$body = wp_remote_retrieve_body($response);
		$http_code = wp_remote_retrieve_response_code($response);

		if ($this->test_mode) {
			error_log('ComGate API Response Code: ' . $http_code);
			error_log('ComGate API Response Body: ' . $body);
		}

		// Parse response (ComGate returns key=value pairs)
		$parsed = array();
		$lines = explode("\n", $body);
		foreach ($lines as $line) {
			$parts = explode('=', $line, 2);
			if (count($parts) === 2) {
				$parsed[trim($parts[0])] = trim($parts[1]);
			}
		}

		return $parsed;
	}

	/**
	 * Check ComGate IPN response
	 */
	public function check_comgate_response() {
		// Get POST data
		$transaction_id = isset($_POST['transId']) ? sanitize_text_field($_POST['transId']) : '';
		$ref_id = isset($_POST['refId']) ? sanitize_text_field($_POST['refId']) : '';
		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

		if ($this->test_mode) {
			error_log('ComGate IPN received: ' . print_r($_POST, true));
		}

		if (!$transaction_id || !$ref_id) {
			status_header(400);
			die('Invalid ComGate response');
		}

		$order = wc_get_order($ref_id);

		if (!$order) {
			status_header(404);
			die('Order not found');
		}

		// Verify the transaction ID matches
		$stored_transaction_id = $order->get_meta('_comgate_transaction_id');
		if ($stored_transaction_id !== $transaction_id) {
			status_header(400);
			die('Transaction ID mismatch');
		}

		// Update order based on status
		switch ($status) {
			case 'PAID':
				if (!$order->is_paid()) {
					$order->payment_complete($transaction_id);
					$order->add_order_note('ComGate payment completed. Transaction ID: ' . $transaction_id);
				}
				break;

			case 'CANCELLED':
				$order->update_status('cancelled', 'Payment cancelled by customer via ComGate.');
				break;

			case 'PENDING':
				$order->update_status('on-hold', 'Payment is pending in ComGate.');
				break;

			default:
				$order->add_order_note('ComGate payment status: ' . $status);
				break;
		}

		status_header(200);
		echo 'OK';
		exit;
	}

	/**
	 * Output for the order received page
	 */
	public function thankyou_page($order_id) {
		$order = wc_get_order($order_id);

		if (!$order) {
			return;
		}

		$transaction_id = $order->get_meta('_comgate_transaction_id');

		if ($transaction_id) {
			echo '<div class="woocommerce-order-details">';
			echo '<h2 class="woocommerce-order-details__title">' . __('Payment Details', 'woocommerce') . '</h2>';
			echo '<p><strong>' . __('Transaction ID:', 'woocommerce') . '</strong> ' . esc_html($transaction_id) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Get language code for ComGate
	 */
	private function get_language() {
		$locale = get_locale();
		$lang = substr($locale, 0, 2);

		// ComGate supported languages: cs, en, sk, pl, de, hu, ru, hr, ro, bg, sl
		$supported = array('cs', 'en', 'sk', 'pl', 'de', 'hu', 'ru', 'hr', 'ro', 'bg', 'sl');

		return in_array($lang, $supported) ? $lang : 'en';
	}

}

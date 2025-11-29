<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_Gateway_Comgate_Simple extends WC_Payment_Gateway {

	public $merchant_id;

	public $secret;

	public $test_mode;

	/**
	 * External system will notify this internal endpoint
	 */
	public $notify_url;

	public function __construct() {
		$this->id = 'karel_comgate_plugin_payment';
		$this->icon = ''; // URL to an icon
		$this->has_fields = false;
		$this->method_title = __('ComGate Gateway', 'karel');
		$this->method_description = __('Simple ComGate gateway.', 'karel');
		$this->order_button_text = __('Proceed to ComGate', 'karel'); // This is required for redirect gateways
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->merchant_id = $this->get_option('merchant_id');
		$this->secret = $this->get_option('secret');
		$this->test_mode = 'yes' === $this->get_option('test_mode');
		$this->enabled = $this->get_option('enabled');
		$this->supports = array('products');

		$this->notify_url = rest_url('comgate-plugin-notify');

		$this->init_form_fields();

		// hooks
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_api_' . $this->id, array( $this, 'check_comgate_response'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

		// Add REST API support for Blocks
		add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_payment_for_blocks'), 10, 2);

		// TEST MODE
		if ($this->test_mode) {
			add_filter('query_vars', function( $vars ) {
				$vars[] = 'comgate_mock_page';
				return $vars;
			});

			add_action('template_redirect', function () {
				if ( get_query_var('comgate_mock_page')) {
					comgate_show_mock_payment_page();
					exit;
				}
			});

			add_action('rest_api_init', function () {
				register_rest_route('comgate-mock-api/v1', 'create', [
					'methods'  => 'POST',
					'callback' => array($this, 'handle_mock_api_request'),
					'permission_callback' => '__return_true'
				]);
			});
		}
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __('Enable/Disable', 'karel'),
				'type'    => 'checkbox',
				'label'   => __('Enable ComGate (Simple) Gateway', 'karel'),
				'default' => 'yes'
			),
			'title' => array(
				'title'       => __('Title', 'karel'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'karel'),
				'default'     => __('Card payment (ComGate)', 'karel'),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __('Description', 'karel'),
				'type'        => 'textarea',
				'default'     => __('You will be redirected to a secure payment gateway to complete the payment.', 'karel'),
			),
			'merchant_id' => array(
				'title'       => __('Merchant ID', 'karel'),
				'type'        => 'text',
			),
			'secret' => array(
				'title'       => __('Secret / API key', 'karel'),
				'type'        => 'text',
			),
			'test_mode' => array(
				'title'       => __('Test mode', 'karel'),
				'type'        => 'checkbox',
				'label'       => __('Enable test mode', 'karel'),
				'default'     => 'yes',
			)
		);
	}

	public function admin_options() {
		echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
		echo wp_kses_post(wpautop( $this->get_method_description()));
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
		echo "<div><strong>Notify URL:</strong> $this->notify_url</div>";
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

		$return_url = $this->get_return_url($order);

		// Prepare payment data
		$payment_data = array(
			'text' => 0,
			'price' => round($order->get_total() * 100), // Amount in cents (lowest denomination)
			'curr' => $order->get_currency(),
			'label' => 'Objednávka č. ' . $order->get_order_number(),
			'refId' => (string)$order_id,
			'method' => 'ALL', // Allow all payment methods
			'email' => $order->get_billing_email(),
			'lang' => $this->get_language(),
			'country' => $order->get_billing_country(),
			'url_paid' => $return_url,
			'url_cancelled' => $return_url,
			'url_pending' => $return_url
		);

		$api_url = $this->test_mode ? rest_url('comgate-mock-api/v1/create') : 'https://payments.comgate.cz/v2.0/payment.json';

		// Log for debugging
		if ($this->test_mode) {
			error_log('ComGate Payment URL: ' . $api_url);
			error_log('ComGate Payment Data: ' . print_r($payment_data, true));
		}

		// Create payment
		$response = wp_remote_post(
			$api_url,
			array(
				'method' => 'POST',
				'timeout' => 45,
				'body' => wp_json_encode($payment_data),
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'Authorization' => 'Bearer ' . $this->secret
				),
				'sslverify' => !$this->test_mode
			)
		);

		if (is_wp_error($response)) {
			$error_message = 'ComGate API error: ' . $response->get_error_message();
			error_log($error_message);
			return array(
				'result' => 'failure',
				'messages' => $error_message
			);
		}

		$error_messages = [];

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$error_message = "Invalid JSON returned by API: $body";
			error_log($error_message);
			return array(
				'result' => 'failure',
				'messages' => $error_message
			);
		}

		if (!isset($data['code'])) {
			$error_messages[] = 'API returned no code!';
		}

		$code = $data['code'];
		if ($code > 0) {
			$error_messages[] = "API returned invalid code: $code";
		}

		if (empty($data['transId'])) {
			$error_messages[] = 'API returned no transaction id!';
		}

		if (empty($data['redirect'])) {
			$error_messages[] = 'API returned no redirect URL!';
		}

		if (!empty($error_messages)) {
			if (!empty($data['message'])) {
				$error_messages[] = 'ComGate message:' . $data['message'];
			}
			$error_message = join(" ", $error_messages);
			error_log($error_message);
			$order->add_order_note('ComGate API error: ' . $error_message);
			return array(
				'result' => 'failure',
				'messages' => $error_message
			);
		}

		// Payment created

		$order->update_meta_data('_comgate_transaction_id', $data['transId']);
		$order->save();
		$order->update_status('pending', __('Awaiting ComGate payment', 'woocommerce'));

		// Empty cart
		if (function_exists('WC')) {
			WC()->cart->empty_cart();
		}

		// Redirect to ComGate payment page
		$payment_url = $data['redirect'];

		if ($this->test_mode) {
			error_log('ComGate Redirect URL: ' . $payment_url);
		}

		return array(
			'result' => 'success',
			'redirect' => $payment_url
		);
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

	public function handle_mock_api_request(WP_REST_Request $request) {
		$orderId = $request->get_param('refId');
		$return_url = $request->get_param('url_paid');
		$result = [
			'code' => 0,
			'message' => 'OK',
			'redirect' => site_url('comgate-mock-page') . '?order_id=' . $orderId . '&return_url=' . $return_url,
			'transId' => '123456',
		];

    	return new WP_REST_Response($result, 200);
	}
}

<?php

namespace src\includes;
use WC_Order;
use WC_Payment_Gateway;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
	exit;
}

class WC_Gateway_Comgate_Simple extends WC_Payment_Gateway {

	private $debug_mode = true;

	private $use_mock_page;

	private $test_payments;

	private $merchant_id;

	private $secret;

	private $authorization_header;

	/**
	 * External system will notify this internal endpoint
	 */
	private $notify_url;

	public function __construct() {
		$this->id = 'karel_comgate_plugin_payment';
		$this->icon = ''; // URL to an icon
		$this->has_fields = false;
		$this->method_title = __('ComGate Gateway', 'comgate-plugin');
		$this->method_description = __('Simple ComGate gateway by Karel.', 'comgate-plugin');
		$this->order_button_text = __('Proceed to payment', 'comgate-plugin');
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->merchant_id = $this->get_option('merchant_id');
		$this->secret = $this->get_option('secret');
		$this->authorization_header = 'Basic ' . base64_encode($this->merchant_id . ':' . $this->secret);
		$this->use_mock_page = 'yes' === $this->get_option('use_mock_page');
		$this->test_payments = 'yes' === $this->get_option('test_payments');
		$this->enabled = $this->get_option('enabled');
		$this->supports = array('products');
		$this->notify_url = rest_url('karel-comgate/v2/notify');

		$this->init_form_fields();

		add_action('rest_api_init', function () {
			register_rest_route('karel-comgate/v2', 'notify', [
				'methods' => 'POST',
				'callback' => array($this, 'handle_comgate_notification'),
				'permission_callback' => '__return_true'
			]);
		});

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
		add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_payment_for_blocks'), 10, 2);

		// MOCK PAGE
		if ($this->use_mock_page) {
			add_filter('query_vars', function ($vars) {
				$vars[] = 'comgate_mock_page';
				return $vars;
			});

			add_action('template_redirect', function () {
				if (get_query_var('comgate_mock_page')) {
					comgate_show_mock_payment_page();
					exit;
				}
			});

			add_action('rest_api_init', function () {
				register_rest_route('comgate-mock-api/v2', 'create', [
					'methods' => 'POST',
					'callback' => array($this, 'handle_mock_api_request_create'),
					'permission_callback' => '__return_true'
				]);
				register_rest_route('comgate-mock-api/v2', 'status', [
					'methods' => 'GET',
					'callback' => array($this, 'handle_mock_api_request_status'),
					'permission_callback' => '__return_true'
				]);
				register_rest_route('comgate-mock-api/v2', 'setstate', [
					'methods' => 'POST',
					'callback' => array($this, 'handle_mock_api_request_setstate'),
					'permission_callback' => '__return_true'
				]);
			});
		}
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'karel'),
				'type' => 'checkbox',
				'label' => __('Enable ComGate Gateway', 'karel'),
				'default' => 'yes'
			),
			'use_mock_page' => array(
				'title' => __('Use mock payment page', 'karel'),
				'type' => 'checkbox',
				'label' => __('Enable mock payment page', 'karel'),
				'description' => __('Instead of redirecting to ComGate, you will be redirected to testing page.', 'karel'),
				'default' => 'yes',
			),
			'test_payments' => array(
				'title' => __('Test payments', 'karel'),
				'type' => 'checkbox',
				'label' => __('Send test payments', 'karel'),
				'description' => __('Payments will be sent to ComGate, but marked as test payments.', 'karel'),
				'default' => 'yes',
			),
			'title' => array(
				'title' => __('Title', 'karel'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'karel'),
				'default' => __('Online payment', 'karel'),
			),
			'description' => array(
				'title' => __('Description', 'karel'),
				'type' => 'textarea',
				'default' => __('You will be redirected to a secure payment gateway to complete the payment.', 'karel'),
			),
			'merchant_id' => array(
				'title' => __('Merchant ID', 'karel'),
				'type' => 'text',
			),
			'secret' => array(
				'title' => __('Secret / API key', 'karel'),
				'type' => 'text',
			)
		);
	}

	public function admin_options() {
		?>
			<h2><?php echo esc_html($this->get_method_title()) ?></h2>
			<?php echo wp_kses_post(wpautop($this->get_method_description())) ?>
			<table class="form-table">
				<?php echo $this->generate_settings_html()?>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="notify-url">Notify URL</label>
					</th>
					<td class="forminp">
						<fieldset>
							<legend class="screen-reader-text"><span>Notify URL</span></legend>
							<div><?php echo $this->notify_url ?></div>
							<p class="description">This should be configured in ComGate administration as push notification endpoint.</p>
						</fieldset>
					</td>
				</tr>
			</table>

		<?php
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
	 * Check order status using ComGate /status endpoint and update it
	 */
	public function check_order_status(WC_Order $order): void {
		$transId = $order->get_meta('_comgate_transaction_id');

		if (empty($transId)) {
			error_log("Check order status: ComGate transaction id not set on order!");
			return;
		}

		$api_url = $this->use_mock_page ? rest_url('comgate-mock-api/v2/status') : 'https://payments.comgate.cz/v2.0/status';
		$api_url = add_query_arg(array('transId' => $transId), $api_url);

		if ($this->debug_mode) {
			error_log('Check order status URL: ' . $api_url);
		}

		// Check status
		$response = wp_remote_get(
			$api_url,
			array(
				'method' => 'GET',
				'timeout' => 45,
				'headers' => array(
					'Accept' => 'application/json',
					'Authorization' => $this->authorization_header
				),
				'sslverify' => !$this->use_mock_page
			)
		);

		if (is_wp_error($response)) {
			$error_message = 'ComGate API error: ' . $response->get_error_message();
			error_log($error_message);
			return;
		}

		$error_messages = [];

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$error_message = "Invalid JSON returned by ComGate API: $body";
			error_log($error_message);
			return;
		}

		if (!isset($data['code'])) {
			$error_messages[] = 'API returned no code!';
		}

		$code = $data['code'];
		if ($code > 0) {
			$error_messages[] = "API returned invalid code: $code";
		}

		if (empty($data['refId'])) {
			$error_messages[] = 'API returned no reference id!';
		} else {
			if (intval($data['refId']) !== $order->get_id()) {
				$error_messages[] = "API returned invalid reference id! Expected (Order ID): {$order->get_id()} Received: {$data['refId']}";
			}
		}

		if (empty($data['transId'])) {
			$error_messages[] = 'API returned no transaction id!';
		} else {
			if ($data['transId'] !== $transId) {
				$error_messages[] = "API returned invalid transaction id! Expected: $transId Received: {$data['transId']}";
			}
		}

		if (empty($data['status'])) {
			$error_messages[] = 'API returned no status!';
		}

		if (!empty($error_messages)) {
			if (!empty($data['message'])) {
				$error_messages[] = 'ComGate API message:' . $data['message'];
			}
			$error_message = join("\r\n", $error_messages);
			$error_message = "Check order status error(s):\r\n$error_message";
			error_log($error_message);
			$order->add_order_note(wpautop($error_message));
			return;
		}

		// Status checked

		$originalStatus = $order->get_meta('_comgate_transaction_status');

		if ($originalStatus === $data['status']) {
			if ($this->debug_mode) {
				error_log("ComGate transaction status is already set to '{$data['status']}'");
			}
			return;
		}

		$order->update_meta_data('_comgate_transaction_status', $data['status']);

		switch ($data['status']) {
			case 'PENDING':
				$order->update_status('pending', __('Awaiting ComGate payment', 'woocommerce'));
				break;
			case 'CANCELLED':
				$order->update_status('pending', __('ComGate payment cancelled', 'woocommerce'));
				break;
			case 'AUTHORIZED':
				$order->update_status('pending', __('ComGate payment authorized, but not paid', 'woocommerce'));
				break;
			case 'PAID':
				if (!$order->is_paid()) {
					$order->payment_complete($transId);
					$order->add_order_note('ComGate payment completed.');
				}
				break;
		}

		$order->save();
	}

	/**
	 * Process the payment - create payment in ComGate and redirect to payment page
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
			'test' => $this->test_payments,
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

		$api_url = $this->use_mock_page ? rest_url('comgate-mock-api/v2/create') : 'https://payments.comgate.cz/v2.0/payment.json';

		if ($this->debug_mode) {
			$data_log = print_r($payment_data, true);
			error_log("ComGate Payment:\r\nURL: $api_url\r\nData: $data_log");
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
					'Authorization' => $this->authorization_header
				),
				'sslverify' => !$this->use_mock_page
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
			$error_message = join("\r\n", $error_messages);
			$error_message = "ComGate API error: $error_message";
			error_log($error_message);
			$order->add_order_note(wpautop($error_message));
			return array(
				'result' => 'failure',
				'messages' => $error_message
			);
		}

		// Payment created

		$order->update_meta_data('_comgate_transaction_id', $data['transId']);
		$order->update_meta_data('_comgate_transaction_status', 'created');
		$order->update_status('pending', __('Awaiting ComGate payment', 'woocommerce'));
		$order->save();

		// Empty cart
		if (function_exists('WC')) {
			WC()->cart->empty_cart();
		}

		$payment_url = $data['redirect'];

		if ($this->debug_mode) {
			error_log('ComGate Redirect URL: ' . $payment_url);
		}

		return array(
			'result' => 'success',
			'redirect' => $payment_url
		);
	}

	/**
	 * Process ComGate notification call
	 */
	public function handle_comgate_notification(WP_REST_Request $request) {
		if ($this->debug_mode) {
			error_log('ComGate notification received: ' . print_r($request->get_body(), true));
		}

		$transaction_id = $request->get_param('transId');
		$ref_id = $request->get_param('refId');

		if (!$transaction_id || !$ref_id) {
			return new WP_REST_Response('Invalid ComGate response', 400);
		}

		$order = wc_get_order($ref_id);

		if (!$order) {
			return new WP_REST_Response('Order not found', 404);
		}

		// Verify the transaction ID matches
		$stored_transaction_id = $order->get_meta('_comgate_transaction_id');
		if ($stored_transaction_id !== $transaction_id) {
			return new WP_REST_Response('Transaction ID mismatch', 400);
		}

		$this->check_order_status($order);

		return new WP_REST_Response('OK', 200);
	}

	/**
	 * Output for the order received page
	 */
	public function thankyou_page($order_id) {
		$order = wc_get_order($order_id);

		if (!$order) {
			return;
		}

		if (!$order->is_paid()) {
			$this->check_order_status($order);
		}

		$transaction_id = $order->get_meta('_comgate_transaction_id');
		$transaction_status = $order->get_meta('_comgate_transaction_status');

		if ($transaction_id) {
			?>
			<div class="woocommerce-order-details">
				<h2 class="woocommerce-order-details__title"><?php echo __('Payment Details', 'comgate-plugin') ?></h2>
				<p><strong><?php echo __('Transaction ID:', 'comgate-plugin') ?></strong> <?php echo esc_html($transaction_id) ?></p>
				<p><strong><?php echo __('Transaction Status:', 'comgate-plugin') ?></strong> <?php echo __($transaction_status, 'comgate-plugin') ?></p>
				<?php
				if ($this->use_mock_page) {
					$transaction_mock_status = $order->get_meta('_comgate_transaction_mock_status');
					echo '<p><strong>' . __('Transaction Mock Status:', 'comgate-plugin') . '</strong> ' . esc_html($transaction_mock_status) . '</p>';
				}
				?>
			</div>
			<?php
		}
	}

	public function handle_mock_api_request_create(WP_REST_Request $request) {
		$orderId = $request->get_param('refId');
		$return_url = $request->get_param('url_paid');
		$result = [
			'code' => 0,
			'message' => 'OK',
			'redirect' => site_url('comgate-mock-page') . '?order_id=' . $orderId . '&return_url=' . urlencode($return_url),
			'transId' => $orderId,
		];

		return new WP_REST_Response($result, 200);
	}

	public function handle_mock_api_request_status() {
		$orderId = isset($_GET['transId']) ? $_GET['transId'] : '';
		$order = wc_get_order($orderId);
		if (empty($order)) {
			return new WP_REST_Response(null, 404);
		}

		$result = [
			'code' => 0,
			'status' => $order->get_meta('_comgate_transaction_mock_status'),
			'refId' => $orderId,
			'transId' => $orderId
		];

		return new WP_REST_Response($result, 200);
	}

	public function handle_mock_api_request_setstate(WP_REST_Request $request) {
		$orderId = $request->get_param('transId');

		$order = wc_get_order($orderId);
		if (empty($order)) {
			return new WP_REST_Response(array('message' => "Order $orderId not found"), 404);
		}

		$state = $request->get_param('state');
		$order->update_meta_data('_comgate_transaction_mock_status', $state);
		$order->save();

		return new WP_REST_Response(null, 200);
	}

}

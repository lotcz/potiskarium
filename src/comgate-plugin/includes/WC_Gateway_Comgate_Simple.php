<?php

if (!defined('ABSPATH')) {
	exit;
}

class WC_Gateway_Comgate_Simple extends WC_Payment_Gateway {

	public $merchant_id;

	public $secret;

	public $test_mode;

	public $create_url;

	public $verify_url;

	public function __construct() {
		$this->id                 = 'karel_comgate_plugin_payment';
		$this->icon               = ''; // URL to an icon
		$this->has_fields         = false;
		$this->method_title       = __( 'ComGate Gateway', 'karel' );
		$this->method_description = __( 'Simple ComGate gateway.', 'karel' );

		$this->init_form_fields();
		$this->init_settings();

		// user settings
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->merchant_id  = $this->get_option( 'merchant_id' );
		$this->secret       = $this->get_option( 'secret' );
		$this->test_mode    = 'yes' === $this->get_option( 'test_mode' );
		$this->create_url   = $this->get_option( 'create_url' );
		$this->verify_url   = $this->get_option( 'verify_url' );

		// expose enabled flag so blocks can see it
		$this->enabled      = $this->get_option( 'enabled' );

		// hooks
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_callback' ) );

		// register REST endpoints for blocks to call
		add_action( 'rest_api_init', array( $this, 'register_rest_endpoints' ) );

		// mark that this gateway supports basic features
		$this->supports = array( 'products' );
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
			),
			'create_url' => array(
				'title'       => __( 'Create payment URL', 'wc-comgate-simple' ),
				'type'        => 'text',
				'default'     => 'https://payments.comgate.cz/v1.0/create',
			),
			'verify_url' => array(
				'title'       => __( 'Verify payment URL', 'wc-comgate-simple' ),
				'type'        => 'text',
				'default'     => 'https://payments.comgate.cz/v1.0/status',
			),
		);
	}

	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	public function register_rest_endpoints() {
		register_rest_route( 'wc-comgate/v1', '/create', array(
			'methods' => 'POST',
			'callback' => array( $this, 'rest_create_payment' ),
			'permission_callback' => function() { return true; },
		) );
	}

	/**
	 * REST create — called from Blocks JS to create payment session and return redirect
	 */
	public function rest_create_payment( $request ) {
		$params = $request->get_json_params();

		if ( empty( $params['orderId'] ) ) {
			return new WP_Error( 'no_order', 'Missing orderId', array( 'status' => 400 ) );
		}

		$order = wc_get_order( intval( $params['orderId'] ) );
		if ( ! $order ) return new WP_Error( 'no_order', 'Order not found', array( 'status' => 404 ) );

		// build payload same as process_payment
		$payload = array(
			'merchant'    => $this->merchant_id,
			'secret'      => $this->secret,
			'price'       => intval( round( $order->get_total() * 100 ) ),
			'curr'        => $order->get_currency(),
			'label'       => sprintf( 'Order %s', $order->get_order_number() ),
			'refId'       => $order->get_id(),
			'method'      => 'CARD',
			'prepareOnly' => false,
			'test'        => $this->test_mode ? true : false,
			'returnUrl'   => $this->get_return_url( $order ),
		);

		$response = $this->remote_post( $this->create_url, $payload );

		if ( ! $response || empty( $response['code'] ) ) {
			return new WP_Error( 'gateway_error', 'Payment gateway error', array( 'status' => 500 ) );
		}

		if ( isset( $response['transId'] ) ) {
			$order->update_meta_data( '_comgate_trans_id', sanitize_text_field( $response['transId'] ) );
			$order->save();
		}

		if ( isset( $response['redirect'] ) ) {
			return rest_ensure_response( array( 'result' => 'success', 'redirect' => $response['redirect'] ) );
		}

		return new WP_Error( 'gateway_no_redirect', 'Gateway did not return redirect URL', array( 'status' => 500 ) );
	}

	public function process_payment( $order_id ) {
		// keep the original redirect flow for non-block checkout
		$order = wc_get_order( $order_id );

		$payload = array(
			'merchant'    => $this->merchant_id,
			'secret'      => $this->secret,
			'price'       => intval( round( $order->get_total() * 100 ) ),
			'curr'        => $order->get_currency(),
			'label'       => sprintf( 'Order %s', $order->get_order_number() ),
			'refId'       => $order->get_id(),
			'method'      => 'CARD',
			'prepareOnly' => false,
			'test'        => $this->test_mode ? true : false,
			'returnUrl'   => $this->get_return_url( $order ),
		);

		$response = $this->remote_post( $this->create_url, $payload );

		if ( ! $response || empty( $response['code'] ) ) {
			wc_add_notice( __( 'Payment gateway error. Please try again.', 'wc-comgate-simple' ), 'error' );
			return array('result' => 'fail');
		}

		if ( isset( $response['redirect'] ) ) {
			if ( isset( $response['transId'] ) ) {
				$order->update_meta_data( '_comgate_trans_id', sanitize_text_field( $response['transId'] ) );
				$order->save();
			}
			$order->update_status( 'on-hold', __( 'Awaiting payment via ComGate', 'wc-comgate-simple' ) );
			return array('result' => 'success', 'redirect' => esc_url_raw( $response['redirect'] ) );
		}

		wc_add_notice( __( 'Payment gateway returned an error.', 'wc-comgate-simple' ), 'error' );
		return array('result' => 'fail');
	}

	public function handle_callback() {
		$raw = file_get_contents( 'php://input' );
		$json = json_decode( $raw, true );
		$data = is_array( $json ) ? $json : $_POST;

		$transId = isset( $data['transId'] ) ? sanitize_text_field( $data['transId'] ) : ( isset( $data['trans_id'] ) ? sanitize_text_field( $data['trans_id'] ) : '' );
		$refId   = isset( $data['refId'] ) ? sanitize_text_field( $data['refId'] ) : ( isset( $data['ref_id'] ) ? sanitize_text_field( $data['ref_id'] ) : '' );
		$status  = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';

		if ( empty( $transId ) && empty( $refId ) ) {
			status_header( 400 ); echo 'No transaction identifier provided.'; exit;
		}

		$order = false;
		if ( $refId ) $order = wc_get_order( intval( $refId ) );

		if ( ! $order && $transId ) {
			$orders = wc_get_orders( array('limit' => 1, 'meta_key' => '_comgate_trans_id', 'meta_value' => $transId) );
			if ( ! empty( $orders ) ) $order = $orders[0];
		}

		if ( ! $order ) { status_header( 404 ); echo 'Order not found.'; exit; }

		$verified_status = $this->verify_remote_status( $transId, $order );

		if ( $verified_status === 'PAID' || ( $status && strtoupper( $status ) === 'PAID' ) ) {
			$order->payment_complete( $transId );
			$order->add_order_note( sprintf( __( 'Payment completed via ComGate. TransId: %s', 'wc-comgate-simple' ), $transId ) );
			status_header( 200 ); echo 'OK'; exit;
		}

		if ( $verified_status === 'CANCELED' || ( $status && strtoupper( $status ) === 'CANCELED' ) ) {
			$order->update_status( 'cancelled', __( 'Payment cancelled at gateway', 'wc-comgate-simple' ) );
			status_header( 200 ); echo 'CANCELLED'; exit;
		}

		status_header( 200 ); echo 'IGNORED'; exit;
	}

	protected function remote_post( $url, $payload ) {
		$args = array('timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ));
		$resp = wp_remote_post( $url, $args );
		if ( is_wp_error( $resp ) ) return false;
		$body = wp_remote_retrieve_body( $resp ); if ( empty( $body ) ) return false;
		$json = json_decode( $body, true ); if ( json_last_error() !== JSON_ERROR_NONE ) return false;
		return $json;
	}

	protected function verify_remote_status( $transId, $order ) {
		if ( empty( $transId ) ) return false;
		$payload = array('merchant' => $this->merchant_id, 'secret' => $this->secret, 'transId' => $transId, 'test' => $this->test_mode ? true : false);
		$resp = $this->remote_post( $this->verify_url, $payload );
		if ( ! $resp ) return false;
		if ( isset( $resp['status'] ) ) return strtoupper( $resp['status'] );
		return false;
	}
}

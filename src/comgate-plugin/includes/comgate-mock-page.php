<?php
/**
 * ComGate Mock Payment Page
 * simulates ComGate payment flow in test mode
 */
function comgate_show_mock_payment_page() {
	$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
	$return_url = isset($_GET['return_url']) ? sanitize_text_field($_GET['return_url']) : '';

	if (!$order_id) {
		wp_die('Missing order ID.');
	}

	if (!$return_url) {
		wp_die('Missing return URL.');
	}

	$order = wc_get_order($order_id);
	if (!$order) {
		wp_die('Order not found');
	}

	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo('charset'); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Mock ComGate Payment Gateway - Test Mode</title>
		<style>
			* { margin: 0; padding: 0; box-sizing: border-box; }
			body {
				font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 20px;
			}
			.container {
				background: white;
				border-radius: 12px;
				box-shadow: 0 20px 60px rgba(0,0,0,0.3);
				max-width: 500px;
				width: 100%;
				overflow: hidden;
			}
			.header {
				background: #2c3e50;
				color: white;
				padding: 25px;
				text-align: center;
			}
			.header h1 {
				font-size: 24px;
				margin-bottom: 5px;
			}
			.badge {
				display: inline-block;
				background: #f39c12;
				color: white;
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: bold;
				margin-top: 8px;
			}
			.content {
				padding: 30px;
			}
			.order-info {
				background: #f8f9fa;
				border-radius: 8px;
				padding: 20px;
				margin-bottom: 25px;
			}
			.order-info h2 {
				font-size: 18px;
				color: #2c3e50;
				margin-bottom: 15px;
			}
			.info-row {
				display: flex;
				justify-content: space-between;
				padding: 8px 0;
				border-bottom: 1px solid #e0e0e0;
			}
			.info-row:last-child {
				border-bottom: none;
			}
			.info-label {
				color: #666;
				font-size: 14px;
			}
			.info-value {
				font-weight: 600;
				color: #2c3e50;
			}
			.amount {
				font-size: 32px;
				text-align: center;
				color: #27ae60;
				font-weight: bold;
				margin: 20px 0;
			}
			.actions {
				display: flex;
				gap: 10px;
				margin-top: 25px;
			}
			.btn {
				flex: 1;
				padding: 15px;
				border: none;
				border-radius: 8px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
				text-decoration: none;
				text-align: center;
				display: block;
			}
			.btn-success {
				background: #27ae60;
				color: white;
			}
			.btn-success:hover {
				background: #229954;
				transform: translateY(-2px);
				box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
			}
			.btn-danger {
				background: #e74c3c;
				color: white;
			}
			.btn-danger:hover {
				background: #c0392b;
				transform: translateY(-2px);
				box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
			}
			.btn-secondary {
				background: #95a5a6;
				color: white;
			}
			.btn-secondary:hover {
				background: #7f8c8d;
			}
			.note {
				background: #fff3cd;
				border: 1px solid #ffc107;
				border-radius: 6px;
				padding: 12px;
				margin-top: 20px;
				font-size: 13px;
				color: #856404;
			}
			.processing {
				display: none;
				text-align: center;
				padding: 20px;
			}
			.spinner {
				border: 3px solid #f3f3f3;
				border-top: 3px solid #3498db;
				border-radius: 50%;
				width: 40px;
				height: 40px;
				animation: spin 1s linear infinite;
				margin: 0 auto 15px;
			}
			@keyframes spin {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}
		</style>
	</head>
	<body>
	<div class="container">
		<div class="header">
			<h1>🔒 Mock Payment Gateway</h1>
			<span class="badge">TEST MODE</span>
		</div>

		<div class="content">
			<div class="order-info">
				<h2>Payment Details</h2>
				<div class="info-row">
					<span class="info-label">Order Number:</span>
					<span class="info-value">#<?php echo $order->get_order_number(); ?></span>
				</div>
				<div class="info-row">
					<span class="info-label">Customer:</span>
					<span class="info-value"><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></span>
				</div>
				<div class="info-row">
					<span class="info-label">Email:</span>
					<span class="info-value"><?php echo esc_html($order->get_billing_email()); ?></span>
				</div>
			</div>

			<div class="amount">
				<?php echo $order->get_currency(); ?> <?php echo $order->get_total(); ?>
			</div>

			<div class="note">
				⚠️ <strong>This is a test payment page.</strong> No real payment will be processed.
				Choose an action below to simulate the payment flow.
			</div>

			<div id="actions-form" class="actions">
				<button class="btn btn-success" onclick="processPayment('PAID')">
					✓ Pay Now
				</button>
				<button class="btn btn-danger" onclick="processPayment('CANCELLED')">
					✕ Cancel
				</button>
				<button class="btn btn-secondary" onclick="processPayment('PENDING')">
					⏸ Set Pending
				</button>
			</div>

			<div id="processing" class="processing">
				<div class="spinner"></div>
				<p>Processing payment...</p>
			</div>
		</div>
	</div>

	<script>
		function processPayment(status) {
			document.getElementById('actions-form').style.display = 'none';
			document.getElementById('processing').style.display = 'block';

			// Simulate processing delay
			setTimeout(function() {
				const form = document.createElement('form');
				form.method = 'GET';
				form.action = '<?php echo $return_url ?>';

				const fields = {
					'refId': '<?php echo esc_js($order_id); ?>',
					'status': status
				};

				for (const key in fields) {
					const input = document.createElement('input');
					input.type = 'hidden';
					input.name = key;
					input.value = fields[key];
					form.appendChild(input);
				}

				document.body.appendChild(form);
				form.submit();
			}, 1500);
		}
	</script>
	</body>
	</html>
	<?php
}



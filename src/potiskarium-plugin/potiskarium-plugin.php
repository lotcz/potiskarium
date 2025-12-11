<?php
/**
 * Plugin Name: Potiskarium Plugin
 * Description: Allows image uploads to certain product types and lets user generate AI preview
 * Version: 1.2.0
 * Author: Karel
 * Text Domain: potiskarium-plugin
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

if (!defined( 'ABSPATH')) {
	exit;
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

/*
 *	CATEGORY WITH CUSTOM IMAGE
 */

if (!defined('POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META')) {
	define('POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META', '_potiskarium_allow_custom_print');
}

function potiskarium_plugin_category_form_uploads_field(mixed $term) {
	$enabled = empty($term) || is_string($term) ? 0 : get_term_meta( $term->term_id, POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META, true );
	?>

	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="<?php echo POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META?>">Custom Print</label>
		</th>
		<td>
			<label>
				<input type="checkbox"
					name="<?php echo POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META?>"
					id="<?php echo POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META?>"
					value="1"
					<?php checked( $enabled, '1' ); ?> />

				Allow custom print uploads for products in this category
			</label>
		</td>
	</tr>

	<?php
}

add_action('product_cat_edit_form_fields', 'potiskarium_plugin_category_form_uploads_field');
add_action('product_cat_add_form_fields', 'potiskarium_plugin_category_form_uploads_field');

function potiskarium_plugin_category_form_saved($term_id) {
	$value = isset($_POST[POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META]) ? '1' : '0';
	update_term_meta($term_id, POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META, $value);
}

add_action('edited_product_cat', 'potiskarium_plugin_category_form_saved');
add_action('created_product_cat', 'potiskarium_plugin_category_form_saved');

function product_supports_custom_print($product_id): bool {
	$terms = get_the_terms($product_id, 'product_cat');
	if (!$terms || is_wp_error($terms)) return false;

	foreach ($terms as $term) {
		$enabled = get_term_meta($term->term_id, POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META, true );
		if ( $enabled === '1' ) {
			return true;
		}
	}

	return false;
}

/*
 * PRODUCTS WITH PRINT IMAGE
 */

add_filter('woocommerce_product_data_tabs', function ($tabs) {
	global $post;

	if ($post && product_supports_custom_print($post->ID)) {
		$tabs['potiskarium_custom_tab'] = [
			'label'    => __('Potiskarium', 'potiskarium-plugin'),
			'target'   => 'potiskarium_custom_tab_data',
			'class'    => [],
			'priority' => 50,
		];
	}

	return $tabs;
});

add_action(
	'woocommerce_product_data_panels',
	function () {
		global $post;

		// Condition: only show textarea for mugs
		if (!product_supports_custom_print($post->ID)) {
			return; // Do not render the panel at all
		}
		?>

		<div id="potiskarium_custom_tab_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_textarea_input([
					'id'          => '_potiskarium_product_prompt',
					'label'       => __('AI Prompt', 'my-plugin'),
					'description' => __('Enter custom text used later by the plugin.'),
					'desc_tip'    => true,
				]);
				?>
			</div>
		</div>

	<?php
	}
);

add_action('woocommerce_admin_process_product_object', function ($product) {
	if (isset($_POST['_potiskarium_product_prompt'])) {
		$product->update_meta_data(
			'_potiskarium_product_prompt',
			wp_kses_post(wp_unslash($_POST['_potiskarium_product_prompt']))
		);
	}
});

function potiskarium_get_product_prompt($product_id) {
	return get_post_meta($product_id, '_potiskarium_product_prompt', true);
}


if (!defined('POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA')) {
	define('POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA', 'potiskarium_uploaded_custom_item_data');
}

add_action('woocommerce_before_add_to_cart_button', function() {
	global $product;
	if (!product_supports_custom_print($product->get_id())) return;
	?>
	<div class="custom-upload-wrapper">
    	<label for="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>">Obrázek pro vlastní potisk:</label>
		<input
			type="hidden"
			name="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>"
			id="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>"
		>
		<div class="custom-upload-preview">
			<button
				class="potiskarium-designer-btn wp-element-button"
				type="button"
				data-product_id="<?php echo $product->get_id()?>"
			>
				Nahrát vlastní obrázek...
			</button>
		</div>
	</div>
	<?php
});

add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id) {
	if (!product_supports_custom_print($product_id)) {
		return $passed;
	}
	if (empty($_POST[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA]) ) {
		wc_add_notice('Nahrajte obrázek pro potisk.', 'error');
		return false;
	}
	return $passed;
}, 10, 2 );

add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id) {
	if (!product_supports_custom_print($product_id ) ) {
		return $cart_item_data;
	}
	if (isset($_POST[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA])) {
		$cart_item_data[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA] = $_POST[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA];
	} else {
		wc_add_notice('Je třeba nahrát obrázek pro potisk!', 'error');
	}
	return $cart_item_data;
}, 10, 2 );

add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
	if (isset($cart_item[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA])) {
		$orig = $cart_item[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA];
		$json = str_replace("\\\"", "\"", $orig);
		$params = empty($orig) ? [] : json_decode($json, true);
		$params['item_key'] = $cart_item['key'];
		$item_data[] = [
			'name'  => 'Vlastní potisk',
			'value' => json_encode($params)
		];
	}
	return $item_data;
}, 10, 2 );

add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values) {
	if (isset($values[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA]) ) {
		$item->add_meta_data(POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA, $values[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA]);
	}
}, 10, 3 );

add_filter('woocommerce_order_item_display_meta_key', function ($display_key, $meta, $item) {
	if ($meta->key === POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA) {
		return 'Vlastní obrázek';
	}
	return $display_key;
}, 10, 3);

add_filter('woocommerce_order_item_display_meta_value', function($value, $meta) {
	if ($meta->key === POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA) {
		$json = str_replace("\\\"", "\"", $meta->value);
		$params = json_decode($json, true);
		return "<div class=\"potiskarium-design-preview\"><div class=\"custom-image-wrapper\"><img src=\"{$params['custom_image']}\"></div></div>";
	}
	return $value;
}, 10, 2 );

/* update cart item data */
add_action(
	'woocommerce_blocks_loaded',
	function() {
		woocommerce_store_api_register_update_callback(
			[
				'namespace' => 'potiskarium-plugin',
				'callback'  => function($data) {
					$key = $data['key'];
					$data = $data['data'];

					if (!$key || !isset(WC()->cart->cart_contents[$key])) {
						return new WP_Error('invalid_key', 'Invalid cart item key:' . $key, ['status' => 400]);
					}

					WC()->cart->cart_contents[$key][POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA] = json_encode($data);

					// Recalculate totals
					WC()->cart->calculate_totals();
					WC()->cart->set_session();

					return $data;
				}
			]
		);
	}
);

/*
 * CUSTOM UPLOAD
 */

add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'potiskarium-plugin/v1',
			'/image',
			[
				'methods' => 'POST',
				'callback' => 'potiskarium_handle_upload',
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'potiskarium-plugin/v1',
			'/preview',
			[
				'methods' => 'POST',
				'callback' => 'potiskarium_handle_preview',
				'permission_callback' => '__return_true',
			]
		);
	}
);

function potiskarium_handle_upload(WP_REST_Request $request) {

	// Check Nonce
	$nonce = $request->get_header('Nonce');
	if (!$nonce) {
		$nonce = $request->get_param('nonce');
	}

	if (!$nonce) {
		return new WP_Error(
			'missing_nonce',
			'Missing nonce.',
			['status' => 403]
		);
	}
/*
	if (!wp_verify_nonce($nonce, 'potiskarium_upload_nonce')) {
		return new WP_Error(
			'invalid_nonce',
			'Invalid or expired nonce.',
			['status' => 403]
		);
	}
*/
	// 2. File validation
	if (empty($_FILES['file'])) {
		return new WP_Error('no_file', 'No file uploaded.', ['status' => 400]);
	}

	$file = $_FILES['file'];

	$allowed_types = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/svg'
	];

	if (!in_array($file['type'], $allowed_types)) {
		return new WP_Error('invalid_type', 'Invalid file type.', ['status' => 400]);
	}

	if ($file['size'] > 20 * 1024 * 1024) {
		return new WP_Error('file_too_large', 'File too large.', ['status' => 400]);
	}

	// 3. Upload handling
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$uploaded = wp_handle_upload($file, ['test_form' => false]);

	if (isset($uploaded['error'])) {
		return new WP_Error('upload_error', $uploaded['error'], ['status' => 500]);
	}

	// 4. Insert to Media Library
	$attachment_id = wp_insert_attachment([
		'post_mime_type' => $uploaded['type'],
		'post_title' => sanitize_file_name($file['name']),
		'post_status' => 'inherit'
	], $uploaded['file']);

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata(
		$attachment_id,
		wp_generate_attachment_metadata($attachment_id, $uploaded['file'])
	);

	return [
		'success' => true,
		'id' => $attachment_id,
		'url' => wp_get_attachment_url($attachment_id)
	];
}

/*
 * MODIFY ADD TO CART BUTTON
 */

add_filter('woocommerce_product_add_to_cart_url', 'custom_loop_add_to_cart_url', 10, 2);
function custom_loop_add_to_cart_url($url, $product) {
	if (product_supports_custom_print($product->get_id())) {
		return get_permalink($product->get_id());
	}
	return $url;
}

/**
 * Add custom data attributes to products in the loop
 */
add_filter('woocommerce_loop_add_to_cart_args', 'custom_loop_add_to_cart_args', 10, 2);
function custom_loop_add_to_cart_args($args, $product) {
	if (product_supports_custom_print($product->get_id())) {
		$args['class'] = $args['class'] . ' potiskarium-product-add-to-cart';
		$args['attributes']['data-product_detail_url'] = get_permalink($product->get_id());
	}
	return $args;
}

/*
 * DESIGNER AND PREVIEW
 */

add_action('admin_enqueue_scripts', function() {
	wp_enqueue_style(
		'potiskarium-designer-style-admin',
		plugin_dir_url(__FILE__) . 'potiskarium-preview-admin.css',
		[],
		filemtime(plugin_dir_path(__FILE__) . 'potiskarium-preview-admin.css')
	);
});

add_action(
	'wp_enqueue_scripts',
	function() {
		wp_enqueue_style(
			'potiskarium-designer-style',
			plugin_dir_url( __FILE__ ) . 'potiskarium-designer.css',
			[],
			filemtime(plugin_dir_path(__FILE__) . 'potiskarium-designer.css')
		);

		wp_enqueue_script(
			'potiskarium-designer-script',
			plugin_dir_url( __FILE__ ) . 'potiskarium-designer.js',
			[ 'wc-blocks-checkout' ],
			filemtime(plugin_dir_path(__FILE__) . 'potiskarium-designer.js'),
			true
		);

		wp_localize_script(
			'potiskarium-designer-script',
			'PotiskariumDesigner',
			[
				'uploadRestUrl' => rest_url('potiskarium-plugin/v1/image'),
				'previewRestUrl' => rest_url('potiskarium-plugin/v1/preview'),
				'uploadNonce' => wp_create_nonce('potiskarium_upload_nonce')
			]
		);

		wp_enqueue_script(
			'potiskarium-preview-jscript',
			plugin_dir_url( __FILE__ ) . 'potiskarium-preview.js',
			[ 'wc-blocks-checkout' ],
			filemtime(plugin_dir_path(__FILE__) . 'potiskarium-preview.js'),
			true
		);

	}
);

/*
 * AI PREVIEW
 */

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Potiskarium AI Preview Settings',
			'Potiskarium AI',
			'manage_options',
			'potiskarium-settings',
			'potiskarium_settings_page'
		);
	}
);

add_action(
	'admin_init',
	function () {

		// Register an option
		register_setting('potiskarium_settings_group', 'potiskarium_api_key');

		// Add a section
		add_settings_section(
			'potiskarium_main_section',
			'Main Settings',
			function () {
				echo '<p>Configure my plugin behavior.</p>';
			},
			'potiskarium-settings'
		);

		// Add a field
		add_settings_field(
			'potiskarium_api_key',
			'API Key',
			function () {
				$value = get_option('potiskarium_api_key', '');
				echo '<input type="text" name="potiskarium_api_key" value="' . esc_attr($value) . '" class="regular-text">';
			},
			'potiskarium-settings',
			'potiskarium_main_section'
		);
	}
);

function potiskarium_settings_page() {
	?>
	<div class="wrap">
		<h1>Potiskarium AI Settings</h1>

		<form method="post" action="options.php">
			<?php
			settings_fields('potiskarium_settings_group');
			do_settings_sections('potiskarium-settings');
			submit_button();
			?>
		</form>
	</div>
	<?php
}

add_filter(
	'plugin_action_links_potiskarium-plugin/potiskarium-plugin.php',
	function ($links) {
		$settings_link = '<a href="options-general.php?page=potiskarium-settings">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}
);

function potiskarium_handle_preview(WP_REST_Request $request) {
	$customImage = $request->get_param('customImage');
	if (empty($customImage)) {
		return new WP_REST_Response("No custom image specified!", 400);
	}

	$productId = $request->get_param('productId');
	if (empty($productId)) {
		return new WP_REST_Response("No product ID specified!", 400);
	}

	$prompt = potiskarium_get_product_prompt($productId);

	$apiKey = get_option('potiskarium_api_key');
	if (empty($apiKey)) {
		return new WP_REST_Response("No API Key configured!", 400);
	}

	require_once __DIR__ . '/includes/PreviewOpenAi.php';
	$previewApi = new PreviewOpenAi($apiKey);

	try {
		$preview_result = $previewApi->generatePreview($customImage, $prompt);
		return new WP_REST_Response(['url' => esc_url($preview_result)], 200);
	} catch (\Exception $e) {
		$message = 'API Error: ' . $e->getMessage();
		error_log($message);
		return new WP_REST_Response($message, 500);
	}
}

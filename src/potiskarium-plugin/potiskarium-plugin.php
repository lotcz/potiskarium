<?php
/**
 * Plugin Name: Potiskarium Plugin
 * Description: Allows image uploads to certain product types and lets user generate AI preview
 * Version: 1.0.0
 * Author: Karel
 * Text Domain: potiskarium-plugin
 * Requires at least: 6.0
 * Requires PHP: 8.4
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

if (!defined('POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA')) {
	define('POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA', 'potiskarium_uploaded_custom_item_data');
}

add_action('woocommerce_before_add_to_cart_button', function() {
	global $product;
	if (!product_supports_custom_print($product->get_id())) return;
	?>
	<div class="custom-upload-wrapper">
    	<label for="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>">Obrázek pro vlastní potisk:</label>
		<input type="hidden" name="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>" id="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>" />
		<div class="custom-upload-preview">
			<button class="potiskarium-designer-btn wp-element-button" type="button">Nahrát vlastní obrázek</button>
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

/*
 * UPDATE CART ITEM
 */

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
				}
			]
		);
	}
);

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
				'uploadRestUrl' => rest_url('wp/v2/media'),
				'updateRestUrl' => rest_url('potiskarium-plugin/v1/update-cart-item'),
				'nonce' => wp_create_nonce('wp_rest')
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



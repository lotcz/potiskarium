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
	<p class="custom-upload-wrapper">
    	<label for="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>">Nahrajte obrázek pro potisk:</label>
		<input type="hidden" name="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>" id="<?php echo POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA?>" />
		<button class="potiskarium-designer-btn wp-element-button" type="button">Designer</button>
	</p>
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
		wc_add_notice('No design data posted', 'error');
	}
	return $cart_item_data;
}, 10, 2 );

add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values) {
	if (isset($values[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA]) ) {
		$item->add_meta_data(POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA, $values[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA]);
	}
}, 10, 3 );

add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
	if (isset($cart_item[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA])) {
		$item_data[] = [
			'name'  => POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA,
			'value' => $cart_item[POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA]
		];
	}
	return $item_data;
}, 10, 2 );

add_filter('woocommerce_order_item_get_formatted_meta_data', function($formatted_meta, $item) {
	foreach ($formatted_meta as $meta) {
		if ($meta->key === POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA) {
			$meta->display_value = $meta->value;
		}
	}
	return $formatted_meta;
}, 10, 2 );

add_filter('woocommerce_order_item_display_meta_value', function($value, $meta) {
	if ($meta->key === POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA) {
		return $meta->value;
	}
	return $value;
}, 10, 2 );

/*
 * DESIGNER
 */

add_action(
	'wp_enqueue_scripts',
	function() {
		wp_enqueue_style(
			'potiskarium-designer-cssstyle',
			plugin_dir_url( __FILE__ ) . 'potiskarium-designer.css',
			[],
			filemtime(plugin_dir_path(__FILE__) . 'potiskarium-designer.css')
		);

		wp_enqueue_script(
			'potiskarium-designer-jscript',
			plugin_dir_url( __FILE__ ) . 'potiskarium-designer.js',
			[ 'wc-blocks-checkout' ],
			filemtime(plugin_dir_path(__FILE__) . 'potiskarium-designer.js')
		);

		wp_localize_script(
			'potiskarium-designer-jscript',
			'PotiskariumDesigner',
			[
				'uploadRestUrl' => rest_url('wp/v2/media'),
				'nonce'   => wp_create_nonce('wp_rest'),
			]
		);
	}
);



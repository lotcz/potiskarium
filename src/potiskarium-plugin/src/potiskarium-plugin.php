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

if (!defined('POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META')) {
	define('POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META', '_potiskarium_allow_custom_print');
}

if (!defined('POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA')) {
	define('POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA', 'potiskarium_uploaded_custom_print_image');
}

if (!defined('POTISKARIUM_PLUGIN_PREVIEW_FILE_DATA')) {
	define('POTISKARIUM_PLUGIN_PREVIEW_FILE_DATA', 'potiskarium_preview_custom_print_image');
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

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

add_action('woocommerce_before_add_to_cart_button', function() {
	global $product;
	if (!product_supports_custom_print($product->get_id())) return;
	?>
	<p class="custom-upload-wrapper">
    	<label for="custom_image">Nahrajte obrázek pro potisk:</label>
		<input type="file" name="custom_image" id="custom_image" accept="image/*" />
	</p>
	<?php
});

add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id ) {

	if ( ! product_supports_custom_print( $product_id ) ) {
		return $passed;
	}

	if ( empty($_FILES['custom_image']['size']) ) {
		wc_add_notice('Nahrajte obrázek pro potisk.', 'error');
		return false;
	}

	return $passed;

}, 10, 2 );

add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {

	if ( ! product_supports_custom_print( $product_id ) ) {
		return $cart_item_data;
	}

	if ( isset($_FILES['custom_image']) && $_FILES['custom_image']['size'] > 0 ) {

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploaded = wp_handle_upload(
			$_FILES['custom_image'],
			[ 'test_form' => false ]
		);

		if (isset( $uploaded['error'])) {
			wc_add_notice($uploaded['error'], 'error');
		} else {
			$cart_item_data[POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA] = $uploaded['url'];
		}
	}

	return $cart_item_data;

}, 10, 2 );

add_filter( 'woocommerce_get_item_data', function( $item_data, $cart_item ) {

	if ( isset($cart_item[POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA]) ) {

		$item_data[] = [
			'name'  => 'Obrázek k potisku',
			'value' => $cart_item[POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA]
		];

		//error_log(print_r($item_data, true));
	}

	return $item_data;

}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values ) {

	if ( isset($values[POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA]) ) {
		$item->add_meta_data(POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA, $values[POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA] );
	}

}, 10, 3 );

add_filter( 'woocommerce_order_item_get_formatted_meta_data', function( $formatted_meta, $item ) {

	foreach ( $formatted_meta as $meta ) {

		if ( $meta->key === POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA) {
			$meta->display_value = '<img src="' . esc_url($meta->value) . '" style="max-width:150px;border:1px solid #ddd;" />';
		}
	}

	return $formatted_meta;

}, 10, 2 );

add_filter( 'woocommerce_order_item_display_meta_value', function( $value, $meta ) {

	if ( $meta->key === POTISKARIUM_PLUGIN_UPLOADED_FILE_DATA ) {
		return '<img src="' . esc_url( $meta->value ) . '" style="max-width:150px;border:1px solid #ddd;" />';
	}

	return $value;

}, 10, 2 );

/**
 * Enqueue block cart script for custom print image
 */
add_action( 'wp_enqueue_scripts', function() {

	if ( class_exists('Automattic\WooCommerce\Blocks\Assets') ) {

		wp_enqueue_script(
			'custom-print-cart-block',
			plugin_dir_url(__FILE__) . 'custom-print-cart-block/custom-print-cart-image.js',
			[ 'wc-blocks-checkout', 'wc-settings' ],
			filemtime( plugin_dir_path(__FILE__) . 'custom-print-cart-block/custom-print-cart-image.js' ),
			true
		);
	}

});

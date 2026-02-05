<?php
/**
 * Plugin Name: Potiskarium Plugin
 * Description: Allows image uploads to certain product types and lets user generate AI preview
 * Version: 2.0.0
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
 * GLOBAL PLUGIN SETTINGS
 */

function potiskarium_get_mm_url() {
	return get_option('potiskarium_mm_url', '');
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Potiskarium Settings',
			'Potiskarium',
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
		register_setting('potiskarium_settings_group', 'potiskarium_mm_url');

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
			'MerchMaster URL',
			function () {
				$value = potiskarium_get_mm_url();
				echo '<input type="text" name="potiskarium_mm_url" value="' . esc_attr($value) . '" class="regular-text">';
			},
			'potiskarium-settings',
			'potiskarium_main_section'
		);
	}
);

function potiskarium_settings_page() {
	?>
	<div class="wrap">
		<h1>Potiskarium Settings</h1>

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

/*
 * TRANSLATIONS
 * For cancel-order-request plugin
*/

add_action(
	'plugins_loaded',
	function () {
		load_textdomain(
			'cancel-order-request-woocommerce',
			plugin_dir_path(__FILE__) . '/languages/cancel-order-request/cancel-order-request-cs_CZ.mo'
		);
	}
);

/*
 * CATEGORY WITH CUSTOM PRINT OPTION
 * Mark some categories as eligible for custom prints and set up MerchMaster URL.
 */

if (!defined('POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META')) {
	define('POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META', '_potiskarium_allow_custom_print');
}

/**
 * Render custom fields on product category form in admin
 */
function potiskarium_plugin_category_form_uploads_field(mixed $term) {
	$enabled = empty($term) || is_string($term) ? 0 : get_term_meta( $term->term_id, POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META, true );

	?>

	<tr class="form-field potiskarium-allow-custom-print">
		<th scope="row"><label for="<?php echo POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META?>">Custom print</label></th>
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

/**
 * Register custom admin form for product category
 */
add_action('product_cat_edit_form_fields', 'potiskarium_plugin_category_form_uploads_field');

/**
 * Process custom fields added to product category
 */
function potiskarium_plugin_category_form_saved($term_id) {
	$value = isset($_POST[POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META]) ? '1' : '0';
	update_term_meta($term_id, POTISKARIUM_PLUGIN_ALLOW_UPLOADS_META, $value);

}

/**
 * Register product category form processing for custom fields
 */
add_action('edited_product_cat', 'potiskarium_plugin_category_form_saved');

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
 * PRODUCTS WITH CUSTOM PRINT
 * Set up properties for products eligible for custom print
 */

/*
 * Add tab to product page in admin
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

/*
 * Render form tab to product page in admin
 */
add_action(
	'woocommerce_product_data_panels',
	function () {
		global $post;

		if (!product_supports_custom_print($post->ID)) {
			return;
		}
		?>

		<div id="potiskarium_custom_tab_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input([
					'id'          => '_potiskarium_mm_product_id',
					'label'       => __('Merch Master product ID', 'potiskarium-plugin'),
					'description' => __('Enter product ID as in Merch Master.'),
					'desc_tip'    => true,
				]);
				?>
			</div>
		</div>

	<?php
	}
);

/**
 * Save custom product fields
 */
add_action('woocommerce_admin_process_product_object', function ($product) {
	if (isset($_POST['_potiskarium_mm_product_id'])) {
		$product->update_meta_data(
			'_potiskarium_mm_product_id',
			wp_kses_post(wp_unslash($_POST['_potiskarium_mm_product_id']))
		);
	}
});

function potiskarium_get_mm_product_id($wc_product_id) {
	return get_post_meta($wc_product_id, '_potiskarium_mm_product_id', true);
}

if (!defined('POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA')) {
	define('POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA', 'potiskarium_uploaded_custom_item_data');
}

/**
 * Show button for custom print designer on product detail if product is a custom print
 */
add_action('woocommerce_before_add_to_cart_button', function() {
	global $product;

	if (!product_supports_custom_print($product->get_id())) return;

	$mm_url = potiskarium_get_mm_url();
	if (empty($mm_url)) return;

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
				data-wc_product_id="<?php echo $product->get_id()?>"
				data-mm_product_id="<?php echo potiskarium_get_mm_product_id($product->get_id())?>"
				data-mm_url="<?php echo $mm_url?>"
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
		$params['mm_url'] = potiskarium_get_mm_url();

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

/**
 * Render button to open designer on order
 */
add_filter('woocommerce_order_item_display_meta_value', function($value, $meta) {
	if ($meta->key === POTISKARIUM_PLUGIN_CUSTOM_ITEM_DATA) {
		$json = str_replace("\\\"", "\"", $meta->value);
		$params = json_decode($json, false);
		$isAdmin = current_user_can('manage_options');

		return "<button 
			class=\"potiskarium-designer-btn button button-primary wp-element-button\"
			data-design_uuid=\"{$params->design_uuid}\"
			data-read_only=\"1\"
			data-admin=\"{$isAdmin}\"
		>Náhled...</button>";

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

function potiskarium_designer_scripts() {
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
			'mmUrl' => potiskarium_get_mm_url()
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

add_action('wp_enqueue_scripts', 'potiskarium_designer_scripts');

add_action('admin_enqueue_scripts', function() {
	wp_enqueue_style(
		'potiskarium-designer-style-admin',
		plugin_dir_url(__FILE__) . 'potiskarium-preview-admin.css',
		[],
		filemtime(plugin_dir_path(__FILE__) . 'potiskarium-preview-admin.css')
	);
	potiskarium_designer_scripts();
});


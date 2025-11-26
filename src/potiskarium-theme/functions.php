<?php

// Enable title support (WordPress will handle <title>)
add_theme_support( 'title-tag' );

// Remove extra stuff from wp_head
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'rest_output_link_wp_head', 10);
remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
remove_action('wp_head', 'wp_shortlink_wp_head', 10);
remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'wp_generator');

// Optional: Remove resource hints (preconnect/prefetch)
remove_action('wp_head', 'wp_resource_hints', 2);

// Enqueue your CSS/JS manually
function potiskarium_theme_scripts() {
	wp_enqueue_style('potiskarium-style', get_stylesheet_directory_uri() . '/style.css');
	//wp_enqueue_script('my-script', get_template_directory_uri() . '/js/script.js', [], false, true);
}
add_action('wp_enqueue_scripts', 'potiskarium_theme_scripts');

/* custom blocks */

/**
 * Registers a custom block category for your theme's blocks.
 *
 * @param array $categories Array of block categories.
 * @return array Filtered array of block categories.
 */
function my_custom_block_categories($categories) {
	$new_category = array(
		'slug'  => 'potiskarium-theme-blocks',
		'title' => 'Potiskarium',
		'icon'  => 'star-filled',
	);

	return array_merge(array($new_category), $categories);
}

add_filter( 'block_categories_all', 'my_custom_block_categories' );

add_action('init', function () {
	register_block_type(__DIR__ . '/build/logo-block');
});

add_action('enqueue_block_editor_assets', function() {
	wp_localize_script(
		'potiskarium-theme-logo-block-editor-script',  // Format: {namespace}-{block-name}-editor-script
		'PotiskariumThemeData',
		array(
			'themeUri' => get_stylesheet_directory_uri()
		)
	);
});


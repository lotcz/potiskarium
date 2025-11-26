<?php

add_theme_support( 'title-tag' );

function my_theme_scripts() {
	wp_enqueue_style('potiskarium-style', get_template_directory_uri() . '/style.css');
}

add_action('wp_enqueue_scripts', 'my_theme_scripts');

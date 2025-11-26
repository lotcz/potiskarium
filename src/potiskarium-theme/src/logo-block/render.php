<?php
	$variant =(!isset($attributes['variant'])) ? "white" : $attributes['variant'];
	$url = esc_url(get_theme_file_uri("/img/$variant.svg"));
?>

<img
	src="<?php echo $url ?>"
	alt="Potiskarium"
	style="max-width: 200px;"
>

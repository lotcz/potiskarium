<?php

	$tree = CategoryTree::getInstance();

	$hideEmpty = $attributes['hideEmpty'];
	$hideDefault = $attributes['hideDefault'];
	$showDefaultLast = $attributes['showDefaultLast'];
	$defaultId = ($hideDefault || $showDefaultLast) ? get_option('default_product_cat') : null;

	$renderMenuItem = function($item) {
		$category = $item->category;
		$thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
		if (empty($thumbnail_id)) return;
		$img_url = wp_get_attachment_image_url($thumbnail_id,'woocommerce_single');
		?>
			<a
				class="product-category-item"
				draggable="false"
				href="<?php echo esc_url(get_term_link($category)) ?>"
				style="background-image:url('<?php echo $img_url ?>')"
			>
				<div class="product-category-label" >
					<h3><?php echo esc_html($category->name) ?></h3>
					<?php /*echo esc_html($category->description) */?>
				</div>
			</a>
		<?php
	}

?>

<div class="product-categories-slider">
	<div class="slider-left-arrow"><div class="arrow-image"></div></div>
	<div class="product-categories-slider-view">
		<div class="product-categories-slider-content">
			<?php
				foreach ($tree->children as $item) {
					if ($hideEmpty && $item->isEmpty()) continue;
					$category = $item->category;
					if ($hideDefault && $category->term_id == $defaultId) continue;
					if ($showDefaultLast && $category->term_id == $defaultId) {
						$defaultCatTree = $item;
						continue;
					}
					$renderMenuItem($item);
				}

				if (isset($defaultCatTree)) {
					$renderMenuItem($defaultCatTree);
				}
			?>
		</div>
	</div>
	<div class="slider-right-arrow"><div class="arrow-image"></div></div>
</div>

<?php


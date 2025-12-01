<?php

	$tree = CategoryTree::getInstance();

	$showProductCounts = $attributes['showProductCounts'];
	$hideEmpty = $attributes['hideEmpty'];
	$hideDefault = $attributes['hideDefault'];
	$showDefaultLast = $attributes['showDefaultLast'];
	$defaultId = ($hideDefault || $showDefaultLast) ? get_option('default_product_cat') : null;

	$renderItemFunction = function($tree, $hideEmpty, $showCounts) {
		$category = $tree->category;
		?>
		<li class="collapsible-category-item">
			<a href="<?php echo esc_url(get_term_link($category)) ?>"><?php echo esc_html($category->name) ?></a>
			<?php collapsible_menu_render_children($tree, $hideEmpty, $showCounts); ?>
		</li>
		<?php
	}
?>

<div class="collapsible-categories-menu">
	<?php
	if (!$tree->isEmpty()) {
		?>
		<ul class="collapsible-categories-list">
			<?php
				$defaultCatTree = null;

				foreach ($tree->children as $subtree) {
					if ($hideEmpty && $subtree->isEmpty()) continue;
					$category = $subtree->category;
					if ($hideDefault && $category->term_id == $defaultId) continue;
					if ($showDefaultLast && $category->term_id == $defaultId) {
						$defaultCatTree = $subtree;
						continue;
					}
					$renderItemFunction($subtree, $hideEmpty, $showProductCounts);
				}

				if (isset($defaultCatTree)) {
					$renderItemFunction($defaultCatTree, $hideEmpty, $showProductCounts);
				}
			?>
		</ul>
		<?php
	}
	?>
</div>

<?php


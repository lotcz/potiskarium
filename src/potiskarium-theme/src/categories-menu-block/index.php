<?php

	$tree = CategoryTree::getInstance();

	$loadHierarchy = $attributes['loadHierarchy'];
	$hideEmpty = $attributes['hideEmpty'];
	$hideDefault = $attributes['hideDefault'];
	$showDefaultLast = $attributes['showDefaultLast'];
	$defaultId = ($hideDefault || $showDefaultLast) ? get_option('default_product_cat') : null;

	$renderMenuItem = function($tree, $hideEmpty, $loadHierarchy) {
		$category = $tree->category;
		?>
			<li class="product-category-item">
				<a href="<?php echo esc_url(get_term_link($category)) ?>"><?php echo esc_html($category->name) ?></a>
				<?php
					if ($loadHierarchy) {
						$subcategories = $tree->children;
						if (!empty($subcategories)) {
							?>
							<ul class="product-categories-submenu">
								<?php
								foreach ($subcategories as $subcategoryTree) {
									if ($hideEmpty && $subcategoryTree->isEmpty()) continue;
									$subcategory = $subcategoryTree->category;
									?>
									<li class="product-category-subitem">
										<a href="<?php echo esc_url(get_term_link($subcategory)) ?>"><?php echo esc_html($subcategory->name) ?></a>
									</li>
									<?php
								}
								?>
							</ul>
							<?php
						}
					}
				?>
			</li>
		<?php
	}

?>

<div class="product-categories-menu">
	<ul class="product-categories-list">
		<?php
			foreach ($tree->children as $subtree) {
				if ($hideEmpty && $subtree->isEmpty()) continue;
				$category = $subtree->category;
				if ($hideDefault && $category->term_id == $defaultId) continue;
				if ($showDefaultLast && $category->term_id == $defaultId) {
					$defaultCatTree = $subtree;
					continue;
				}
				$renderMenuItem($subtree, $hideEmpty, $loadHierarchy);
			}

			if (isset($defaultCatTree)) {
				$renderMenuItem($defaultCatTree, $hideEmpty, $loadHierarchy);
			}
		?>
	</ul>
</div>

<?php


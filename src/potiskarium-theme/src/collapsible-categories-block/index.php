<?php

	$tree = CategoryTree::getInstance();

	$showProductCounts = $attributes['showProductCounts'];
	$hideEmpty = $attributes['hideEmpty'];
	$startExpanded = $attributes['startExpanded'];
	$hideDefault = $attributes['hideDefault'];
	$showDefaultLast = $attributes['showDefaultLast'];
	$defaultId = ($hideDefault || $showDefaultLast) ? get_option('default_product_cat') : null;

	$renderItemFunction = function($tree, $hideEmpty, $showCounts, $startExpanded) {
		$category = $tree->category;
		$expanded = ($startExpanded || $tree->containsActive() || $tree->isActive());
		?>
		<li class="collapsible-category-item <?php echo $tree->isActive() ? 'active' : '' ?> <?php echo $expanded ? 'expanded' : '' ?>">
			<a href="<?php echo esc_url(get_term_link($category)) ?>">
				<div>
					<?php
						if ($tree->hasChildren() && ($tree->containsNonEmptyChild() || !$hideEmpty)) {
							?>
							<div class="collapsible-category-item-checkbox-label"></div>
							<?php
						}
					?>
					<div><?php echo esc_html($category->name)?></div>
				</div>
				<?php
					if ($showCounts) {
						?>
						<div class="collapsible-category-count"><?php echo $category->count ?></div>
						<?php
					}
				?>
			</a>
			<?php collapsible_menu_render_children($tree, $hideEmpty, $showCounts, $startExpanded); ?>
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
					$renderItemFunction($subtree, $hideEmpty, $showProductCounts, $startExpanded);
				}

				if (isset($defaultCatTree)) {
					$renderItemFunction($defaultCatTree, $hideEmpty, $showProductCounts, $startExpanded);
				}
			?>
		</ul>
		<?php
	}
	?>
</div>

<?php


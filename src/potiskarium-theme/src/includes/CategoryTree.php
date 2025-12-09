<?php

class CategoryTree {

	static ?CategoryTree $instance = null;

	public static function getInstance(): CategoryTree {
		if (self::$instance === null) {
			self::$instance = new CategoryTree();
		}
		return self::$instance;
	}

	public mixed $category;

	public array $children = [];

	private ?int $activeCategoryId;

	private bool $containsActive = false;

	private bool $containsNonEmptyChild = false;

	public function __construct(mixed $parent = null, ?array $all = null, ?int $activeCategoryId = null) {
		$parentId = empty($parent) ? 0 : $parent->term_id;
		$this->category = $parent;
		$this->activeCategoryId = $activeCategoryId;

		if ($this->activeCategoryId === null) {
			if (is_product_category()) {
				$current = get_queried_object();
				$this->activeCategoryId = $current->term_id;
			} else {
				$this->activeCategoryId = 0;
			}
		}

		if ($all === null) {
			$all = get_terms(array(
				'taxonomy' => 'product_cat',
				'orderby' => 'name',
				'order' => 'ASC',
				'hide_empty' => false
			));
		}

		$childrenCategories = array_filter($all, fn ($subcategory) => $subcategory->parent == $parentId);

		foreach ($childrenCategories as $category) {
			$subtree = new CategoryTree($category, $all, $this->activeCategoryId);
			$this->children[] = $subtree;
			if ($subtree->isActive() || $subtree->containsActive()) {
				$this->containsActive = true;
			}
			if (!$subtree->isEmpty()) {
				$this->containsNonEmptyChild = true;
			}
		}

	}

	public function isEmpty(): bool {
		return (!$this->containsNonEmptyChild) && (empty($this->category) || $this->category->count === 0);
	}

	public function isActive(): bool {
		return isset($this->category) && $this->category->term_id === $this->activeCategoryId;
	}

	public function hasChildren(): bool {
		return !empty($this->children);
	}

	public function containsActive(): bool {
		return $this->containsActive;
	}

	public function containsNonEmptyChild(): bool {
		return $this->containsNonEmptyChild;
	}

}

function collapsible_menu_render_children(CategoryTree $tree, bool $hideEmpty, bool $showCounts, bool $startExpanded) {
	if (empty($tree->children)) return;
	?>
	<ul class="collapsible-subcategories-list">
		<?php
		foreach ($tree->children as $subtree) {
			$category = $subtree->category;
			$expanded = ($startExpanded || $subtree->containsActive() || $subtree->isActive());
			if ($hideEmpty && $subtree->isEmpty()) continue;
			?>
			<li class="collapsible-subcategory-item <?php echo $subtree->isActive() ? 'active' : '' ?> <?php echo $expanded ? 'expanded' : '' ?>">
				<a href="<?php echo esc_url(get_term_link($category)) ?>">
					<div>
						<?php
							if (!empty($subtree->children)) {
								?>
								<div class="collapsible-category-item-checkbox-label"></div>
								<?php
							}
						?>
						<div><?php echo esc_html($category->name) ?></div>
					</div>
					<?php
					if ($showCounts) {
						?>
						<div class="collapsible-category-count"><?php echo $category->count ?></div>
						<?php
					}
					?>
				</a>
				<?php collapsible_menu_render_children($subtree, $hideEmpty, $showCounts, $startExpanded); ?>
			</li>
			<?php
		}
		?>
	</ul>
	<?php
}

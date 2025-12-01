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

	public array $children;

	public function __construct(mixed $parent = null, ?array $all = null) {
		$parentId = empty($parent) ? 0 : $parent->term_id;
		$this->category = $parent;

		if ($all === null) {
			$all = get_terms(array(
				'taxonomy' => 'product_cat',
				'orderby' => 'name',
				'order' => 'ASC',
				'hide_empty' => false
			));
		}

		$this->children = array_map(
			fn ($category) => new CategoryTree($category, $all),
			array_filter($all, fn ($subcategory) => $subcategory->parent == $parentId)
		);
	}

	public function isEmpty(): bool {
		return empty($this->children) && (empty($this->category) || $this->category->count === 0);
	}

}

function collapsible_menu_render_children(CategoryTree $tree, bool $hideEmpty, bool $showCounts) {
	if (empty($tree->children)) return;
	?>
	<ul class="collapsible-subcategories-list">
		<?php
		foreach ($tree->children as $subtree) {
			$category = $subtree->category;
			if ($hideEmpty && $subtree->isEmpty()) continue;
			?>
			<li class="collapsible-subcategory-item">
				<a href="<?php echo esc_url(get_term_link($category)) ?>"><?php echo esc_html($category->name) ?></a>
				<?php collapsible_menu_render_children($subtree, $hideEmpty, $showCounts); ?>
			</li>
			<?php
		}
		?>
	</ul>
	<?php
}

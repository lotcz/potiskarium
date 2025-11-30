<?php

$categories = get_terms(array(
	'taxonomy'   => 'product_cat',
	'orderby'    => 'name',
	'order'      => 'ASC',
	'hide_empty' => true
));

if (empty($categories)) return ;

?>

<div class="product-categories-menu">
	<ul class="product-categories-list">
		<?php
			foreach ($categories as $category) {
				$link = get_term_link($category);
				?>
					<li class="product-category-item">
						<a href="<?php echo esc_url($link) ?>"><?php echo esc_html($category->name) ?></a>
					</li>
				<?php
			}
		?>
	</ul>
</div>

<?php

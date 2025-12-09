<div class="potiskarium-dropdown-menu">
	<div class="potiskarium-dropdown-menu-button">
		<label
			class="dropdown-menu-checkbox-label"
			for="dropdown_menu_button"
		></label>
	</div>
	<input
		id="dropdown_menu_button"
		name="dropdown_menu_button"
		type="checkbox"
		class="dropdown-menu-checkbox"
	>
	<div class="potiskarium-dropdown-menu-content">
		<div class="potiskarium-dropdown-menu-header">
			<label
				class="dropdown-menu-checkbox-label-close"
				for="dropdown_menu_button"
			></label>
		</div>
		<?php
			include __DIR__ . '/../collapsible-categories-block/index.php';
		?>
	</div>
</div>

<?php


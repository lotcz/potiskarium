document.addEventListener(
	"DOMContentLoaded",
	() => {
		const collapsibleItems = document.querySelectorAll(":where(.collapsible-category-item, .collapsible-subcategory-item)");
		collapsibleItems.forEach(
			(collapsibleItem) => {
				const input = collapsibleItem.querySelector("input.collapsible-category-item-checkbox");
				if (input) {
					input.addEventListener(
						"change",
						(e) => {
							const checked = e.target.checked;
							collapsibleItem.classList.toggle("expanded", checked);
						}
					);
				}
			}
		);
	}
)

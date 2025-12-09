document.addEventListener(
	"DOMContentLoaded",
	() => {
		const collapsibleItems = document.querySelectorAll(":where(.collapsible-category-item, .collapsible-subcategory-item)");
		collapsibleItems.forEach(
			(collapsibleItem) => {
				const input = collapsibleItem.querySelector(".collapsible-category-item-checkbox-label");
				if (input && input.dataset.initialized !== 'true') {
					input.addEventListener(
						"click",
						(e) => {
							collapsibleItem.classList.toggle("expanded");
							e.preventDefault();
							e.stopPropagation();
						}
					);
					input.dataset.initialized = 'true';
				}
			}
		);
	}
)

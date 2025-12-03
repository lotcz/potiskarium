if (window.wc.blocksCheckout) {
	const {registerCheckoutFilters} = window.wc.blocksCheckout;

	const modifyItemName = (defaultValue, extensions, args) => {
		setTimeout(() => potiskarium_preview_init(), 100);
		return defaultValue;
	};

	registerCheckoutFilters('hack-extension', {
		itemName: modifyItemName,
	});
}

function potiskarium_preview_show(params, element) {
	element.innerHTML = '';

	const wrapper = document.createElement('div');
	wrapper.classList.add('potiskarium-design-preview');
	element.appendChild(wrapper);

	const customImageWrapper = document.createElement('div');
	customImageWrapper.classList.add('custom-image-wrapper');
	wrapper.appendChild(customImageWrapper);

	const img = document.createElement('img');
	if (params.custom_image) {
		img.setAttribute('src', params.custom_image);
	}
	customImageWrapper.appendChild(img);

	const btn = document.createElement('button');
	btn.setAttribute('type', 'button');
	btn.classList.add('wp-element-button');
	btn.innerText = 'Upravit';
	btn.addEventListener(
		'click',
		async () => {
			potiskarium_designer_show(params)
		}
	);
	wrapper.appendChild(btn);
}

function potiskarium_preview_init() {
	const hiddenInput = document.getElementById("potiskarium_uploaded_custom_item_data");
	if (hiddenInput) {
		const data = hiddenInput.value;
		if (data.startsWith('{')) {
			const json = decodeURIComponent(data).replace(/\\"/g, '"');
			const params = JSON.parse(json);
			const element = document.querySelector('.custom-upload-preview');
			if (element) {
				potiskarium_preview_show(params, element);
			}
		}
	}
	document
		.querySelectorAll(".wc-block-components-product-details__value")
		.forEach(
			(element) => {
				const data = element.innerText;
				if (data.startsWith('{')) {
					const json = decodeURIComponent(data).replace(/\\"/g, '"');
					const params = JSON.parse(json);
					potiskarium_preview_show(params, element);
				}
			}
		);
}

document.addEventListener("DOMContentLoaded", () => potiskarium_preview_init());

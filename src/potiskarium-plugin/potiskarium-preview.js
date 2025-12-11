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
	img.addEventListener('click', async () => potiskarium_designer_show(params));

	const previewImageWrapper = document.createElement('div');
	previewImageWrapper.classList.add('preview-image-wrapper');
	wrapper.appendChild(previewImageWrapper);

	const pimg = document.createElement('img');
	if (params.preview_image) {
		pimg.setAttribute('src', params.preview_image);
	}
	previewImageWrapper.appendChild(pimg);
	pimg.addEventListener('click', async () => potiskarium_designer_show(params));

	const btn = document.createElement('button');
	btn.setAttribute('type', 'button');
	btn.classList.add('wp-element-button');
	btn.innerText = 'Upravit...';
	btn.addEventListener('click', async () => potiskarium_designer_show(params));
	wrapper.appendChild(btn);
}

function potiskarium_preview_init() {

	// product detail form
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

	// cart items
	document.querySelectorAll(".wc-block-components-product-details__value")
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

	// add to cart button
	document.querySelectorAll(".potiskarium-product-add-to-cart")
		.forEach(
			(element) => {
				const url = element.dataset.product_detail_url;
				if (url) {
					const button = document.createElement('button');
					button.innerText = 'Zvolit vlastní potisk...';
					button.classList.add('wp-element-button');
					button.addEventListener(
						'click',
						(e) => {
							e.stopPropagation();
							e.preventDefault();
							document.location.href = url;
						}
					);
					element.replaceWith(button);
				}
			}
		);

}

document.addEventListener("DOMContentLoaded", () => potiskarium_preview_init());

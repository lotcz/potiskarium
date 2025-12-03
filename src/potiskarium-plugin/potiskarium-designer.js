if (window.wc.blocksCheckout) {
	const {registerCheckoutFilters} = window.wc.blocksCheckout;

	function hack_it() {
		console.log('Hack it');
		const elements = document.querySelectorAll('.wc-block-components-product-details__value');
		console.log(elements.length);

		elements.forEach(element => {
			const value = element.innerText;
			console.log(value);
			if (value && value.length > 0 && value.startsWith('http')) {
				element.innerHTML = `<img src="${value}" style="max-width:100px">`;
			}
		});
	}

	const modifyItemName = (defaultValue, extensions, args) => {
		setTimeout(() => hack_it(), 1000);
		return defaultValue;
	};

	registerCheckoutFilters('hack-extension', {
		itemName: modifyItemName,
	});
}

function potiskarium_designer_get() {
	return document.getElementById('potiskarium_designer');
}

function potiskarium_designer_hide() {
	const existing = potiskarium_designer_get();
	if (existing) existing.remove();
}

async function potiskarium_designer_save() {
	const designer = potiskarium_designer_get();
	const params = {
		custom_image: designer.dataset.custom_image,
		preview_image: designer.dataset.preview_image
	}
	const value = JSON.stringify(params);
	const input = document.getElementById('potiskarium_uploaded_custom_item_data');
	if (input) {
		input.value = value;
	} else {
		const response = await fetch(
			'/wp-json/wc/store/cart/update-item',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					key: '???',
					potiskarium_uploaded_custom_item_data: value
				})
			}
		);

		const result = await response.json();
		console.log('Cart updated', result);
	}
}

function potiskarium_designer_show(params) {
	potiskarium_designer_hide();

	const designer = document.createElement('div');
	designer.setAttribute('id', 'potiskarium_designer');
	designer.classList.add('designer-overlay');
	designer.addEventListener('click', () => potiskarium_designer_hide());
	document.body.appendChild(designer);

	const wrapper = document.createElement('div');
	wrapper.classList.add('designer-wrapper');
	wrapper.addEventListener(
		'click',
		(e) => {
			e.preventDefault();
			e.stopPropagation();
		}
	);
	designer.appendChild(wrapper);

	/* heading */

	const heading = document.createElement('div');
	heading.classList.add('designer-heading');
	wrapper.appendChild(heading);

	const title = document.createElement('h2');
	title.innerText = 'Designer';
	heading.appendChild(title);

	/* body */

	const body = document.createElement('div');
	body.classList.add('designer-body');
	wrapper.appendChild(body);

	const form = document.createElement('form');
	body.appendChild(form);

	const fileInput = document.createElement('input');
	fileInput.setAttribute('type', 'file');
	fileInput.setAttribute('accept', 'image/*');
	fileInput.setAttribute('name', 'image');
	form.appendChild(fileInput);

	/* footer */

	const footer = document.createElement('div');
	footer.classList.add('designer-footer');
	wrapper.appendChild(footer);

	const confirmBtn = document.createElement('button');
	confirmBtn.classList.add('button');
	confirmBtn.classList.add('wp-element-button');
	confirmBtn.innerText = 'Uložit';
	confirmBtn.addEventListener(
		'click',
		async () => {

			await potiskarium_designer_save();
			potiskarium_designer_hide();
		}
	);
	footer.appendChild(confirmBtn);
}

function potiskarium_designer_init() {
	document.querySelectorAll(".potiskarium-designer-btn").forEach(
		(btn) => {
			btn.addEventListener(
				"click",
				() => potiskarium_designer_show(
					{
						param: 'test'
					}
				)
			);
		}
	);
}

document.addEventListener("DOMContentLoaded", () => potiskarium_designer_init());

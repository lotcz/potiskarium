function potiskarium_designer_get() {
	return document.getElementById('potiskarium_designer');
}

function potiskarium_designer_hide() {
	const existing = potiskarium_designer_get();
	if (existing) existing.remove();
}

async function potiskarium_designer_create_preview(customImage, productId) {
	const param = {
		customImage: customImage,
		productId: productId,
	};

	const res = await fetch(PotiskariumDesigner.previewRestUrl, {
		method: 'POST',
		credentials: "same-origin",
		headers: {
			'Content-Type': 'application/json',
			'Nonce': PotiskariumDesigner.uploadNonce
		},
		body: JSON.stringify(param)
	});

	if (!res.ok) {
		const err = await res.text();
		throw new Error('Preview failed: ' + err);
	}

	return await res.json();
}

async function potiskarium_designer_upload_file(file) {
	const formData = new FormData();
	formData.append('file', file);
	formData.append('title', 'Custom uploaded image');
	formData.append('alt_text', 'Custom uploaded image');

	const res = await fetch(PotiskariumDesigner.uploadRestUrl, {
		method: 'POST',
		credentials: "same-origin",
		headers: {
			'Nonce': PotiskariumDesigner.uploadNonce,
			'Content-Disposition': `attachment; filename="${file.name}"`,
		},
		body: formData
	});

	if (!res.ok) {
		const err = await res.text();
		throw new Error('Upload failed: ' + err);
	}

	return await res.json();
}

async function potiskarium_designer_save() {
	const designer = potiskarium_designer_get();
	const params = {
		custom_image: designer.dataset.custom_image,
		preview_image: designer.dataset.preview_image,
		product_id: designer.dataset.product_id,
		item_key: designer.dataset.item_key
	}
	const value = JSON.stringify(params);

	const item_key = designer.dataset.item_key;
	if (item_key) {
		const { extensionCartUpdate } = wc.blocksCheckout;
		const { processErrorResponse } = wc.wcBlocksData;

		extensionCartUpdate( {
			namespace: 'potiskarium-plugin',
			data: {
				key: item_key,
				data: params
			},
		} ).then( () => {
			// Cart has been updated.
		} ).catch( ( error ) => {
			// Handle error.
			processErrorResponse(error);
		} );
	} else {
		const input = document.getElementById('potiskarium_uploaded_custom_item_data');
		if (input) {
			input.value = value;
			potiskarium_preview_init();
		}
	}
}

function potiskarium_designer_set_custom_file(url) {
	const designer = potiskarium_designer_get();
	if (!designer) return;

	const customImageWrapper = designer.querySelector('.designer-custom-image-wrapper');
	customImageWrapper.innerHTML = '';

	if (url === null) {
		const loading = document.createElement('div');
		loading.classList.add('loading');
		loading.innerText = 'Nahrávám...';
		customImageWrapper.appendChild(loading);
	} else if (url.length > 0) {
		const img = document.createElement('img');
		customImageWrapper.appendChild(img);
		img.setAttribute('src', url);
	}

	designer.dataset.custom_image = url;
}

function potiskarium_designer_set_preview_image(url) {
	const designer = potiskarium_designer_get();
	if (!designer) return;

	const previewImageWrapper = designer.querySelector('.designer-preview-image-wrapper');
	previewImageWrapper.innerHTML = '';

	if (url === null) {
		const loading = document.createElement('div');
		loading.classList.add('loading');
		loading.innerText = 'Generuji náhled...';
		previewImageWrapper.appendChild(loading);
	} else if (url.length > 0 && url.startsWith('http')) {
		const img = document.createElement('img');
		previewImageWrapper.appendChild(img);
		img.setAttribute('src', url);
	} else {
		const err = document.createElement('div');
		err.classList.add('error');
		err.innerText = 'Jejda, něco se pokazilo. Ani umělá inteligence není dokonalá...';
		previewImageWrapper.appendChild(err);
	}

	designer.dataset.preview_image = url;
}

function potiskarium_designer_show(params) {
	potiskarium_designer_hide();

	const designer = document.createElement('div');
	designer.setAttribute('id', 'potiskarium_designer');
	designer.classList.add('designer-overlay');
	designer.dataset.custom_image = params.custom_image;
	designer.addEventListener('click', () => potiskarium_designer_hide());
	document.body.appendChild(designer);

	const wrapper = document.createElement('div');
	wrapper.classList.add('designer-wrapper');
	wrapper.addEventListener('click', (e) => e.stopPropagation());
	designer.appendChild(wrapper);

	/* HEADING */

	const heading = document.createElement('div');
	heading.classList.add('designer-heading');
	wrapper.appendChild(heading);

	const title = document.createElement('h2');
	title.innerText = 'Vlastní potisk';
	heading.appendChild(title);

	/* BODY */

	const body = document.createElement('div');
	body.classList.add('designer-body');
	wrapper.appendChild(body);

	/* custom image */

	const customImage = document.createElement('div');
	customImage.classList.add('designer-custom-image');
	body.appendChild(customImage);

	const customImageHeader = document.createElement('h3');
	customImageHeader.innerText = 'Váš obrázek';
	customImage.appendChild(customImageHeader);

	const customImageWrapper = document.createElement('div');
	customImageWrapper.classList.add('designer-custom-image-wrapper');
	customImage.appendChild(customImageWrapper);

	const fileInput = document.createElement('input');
	fileInput.setAttribute('type', 'file');
	fileInput.setAttribute('accept', 'image/*');
	fileInput.setAttribute('name', 'image');
	fileInput.addEventListener(
		'change',
		async (e) => {
			const file = e.target.files[0];
			if (!file) return;

			try {
				potiskarium_designer_set_custom_file(null);
				const uploaded = await potiskarium_designer_upload_file(file);
				potiskarium_designer_set_custom_file(uploaded.url);

				potiskarium_designer_set_preview_image(null);
				const generated = await potiskarium_designer_create_preview(uploaded.url, params.product_id);
				potiskarium_designer_set_preview_image(generated.url);
			} catch (err) {
				console.error(err);
				potiskarium_designer_set_preview_image(err);
			}
		}
	);
	customImage.appendChild(fileInput);

	/* AI preview */

	const previewImage = document.createElement('div');
	previewImage.classList.add('designer-preview-image');
	body.appendChild(previewImage);

	const previewImageHeader = document.createElement('h3');
	previewImageHeader.innerText = 'Náhled';
	previewImage.appendChild(previewImageHeader);

	const previewImageWrapper = document.createElement('div');
	previewImageWrapper.classList.add('designer-preview-image-wrapper');
	previewImage.appendChild(previewImageWrapper);

	const imgPreview = document.createElement('img');
	if (params.preview_image) {
		imgPreview.setAttribute('src', params.preview_image);
	}
	previewImageWrapper.appendChild(imgPreview);

	/* FOOTER */

	const footer = document.createElement('div');
	footer.classList.add('designer-footer');
	wrapper.appendChild(footer);

	const closeBtn = document.createElement('a');
	closeBtn.innerText = 'Zavřít';
	closeBtn.setAttribute('href', '#');
	closeBtn.addEventListener(
		'click',
		async (e) => {
			potiskarium_designer_hide();
			e.preventDefault();
		}
	);
	footer.appendChild(closeBtn);

	const confirmBtn = document.createElement('button');
	confirmBtn.classList.add('button');
	confirmBtn.classList.add('wp-element-button');
	confirmBtn.innerText = 'Uložit';
	confirmBtn.addEventListener(
		'click',
		async () => {
			await potiskarium_designer_save();
			potiskarium_preview_init();
			potiskarium_designer_hide();
		}
	);
	footer.appendChild(confirmBtn);

	if (params.custom_image) {
		potiskarium_designer_set_custom_file(params.custom_image ? params.custom_image : '');
	}
	if (params.preview_image) {
		potiskarium_designer_set_preview_image(params.preview_image ? params.preview_image : '');
	} else if (params.custom_image) {
		potiskarium_designer_create_preview(params.custom_image, params.product_id)
			.then((result) => potiskarium_designer_set_preview_image(result.url));
	}
	if (params.item_key) {
		designer.dataset.item_key = params.item_key;
	}
	if (params.product_id) {
		designer.dataset.product_id = params.product_id;
	}
}

function potiskarium_designer_init() {
	document.querySelectorAll(".potiskarium-designer-btn").forEach(
		(btn) => {
			const params = {
				product_id: btn.dataset.product_id
			};
			btn.addEventListener(
				"click",
				() => potiskarium_designer_show(params)
			);
		}
	);
}

document.addEventListener("DOMContentLoaded", () => potiskarium_designer_init());

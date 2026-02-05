function potiskarium_designer_get() {
	return document.getElementById('potiskarium_designer');
}

function potiskarium_designer_hide() {
	window.removeEventListener('message', potiskarium_designer_finished);
	const existing = potiskarium_designer_get();
	if (existing) existing.remove();
}

async function potiskarium_designer_save(uuid) {
	const designer = potiskarium_designer_get();
	const params = {
		design_uuid: uuid,
		wc_product_id: designer.dataset.wc_product_id,
		mm_product_id: designer.dataset.mm_product_id,
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
			potiskarium_designer_hide();
		} ).catch( ( error ) => {
			// Handle error.
			processErrorResponse(error);
		} );
	} else {
		const input = document.getElementById('potiskarium_uploaded_custom_item_data');
		if (input) {
			input.value = value;
			potiskarium_preview_init();
			potiskarium_designer_hide();
		}
	}
}

async function potiskarium_designer_finished(event) {
	const designer = potiskarium_designer_get();
	if (event.origin !== designer.dataset.mm_url) {
		console.error('Invalid origin url:', event.origin, ', expected:', designer.dataset.mm_url);
		return;
	}

	if (event.data.type === 'DESIGN_CANCEL') {
		potiskarium_designer_hide();
	}

	if (event.data.type === 'DESIGN_SAVE') {
		const payload = event.data.payload;
		const uuid = payload.uuid;
		potiskarium_designer_save(uuid);
	}
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
	wrapper.classList.add('designer-window');
	wrapper.addEventListener('click', (e) => e.stopPropagation());
	designer.appendChild(wrapper);

	const header = document.createElement('div');
	header.classList.add('designer-header');
	wrapper.appendChild(header);

	const h = document.createElement('h1');
	h.innerHTML = 'Vlastní design';
	header.appendChild(h);

	const close = document.createElement('div');
	close.classList.add('designer-close');
	close.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg>';
	close.addEventListener('click', potiskarium_designer_hide);
	header.appendChild(close);

	/* IFRAME */

	const iframe = document.createElement('iframe');
	let mm_url = params.mm_url;
	if (PotiskariumDesigner && !mm_url) {
		mm_url = PotiskariumDesigner.mmUrl;
	}
	if (mm_url) {
		designer.dataset.mm_url = mm_url;
		if (params.design_uuid) {
			mm_url += `/designer/${params.design_uuid}`;
		} else if (params.mm_product_id) {
			mm_url += `/designer/add/${params.mm_product_id}`;
		} else {
			wrapper.innerText = 'No UUID or product id!';
		}
		mm_url += `?parent_origin=${window.location.origin}`;
		if (params.read_only) {
			mm_url += '&read_only=1';
		}
		if (params.admin) {
			mm_url += '&admin=1';
		}
		iframe.setAttribute('src', mm_url);
	} else {
		wrapper.innerText = 'No Merch Master URL!';
	}

	wrapper.appendChild(iframe);

	if (params.item_key) {
		designer.dataset.item_key = params.item_key;
	}
	if (params.wc_product_id) {
		designer.dataset.wc_product_id = params.wc_product_id;
	}

	window.addEventListener('message', potiskarium_designer_finished);
}

function potiskarium_designer_init() {
	document.querySelectorAll(".potiskarium-designer-btn").forEach(
		(btn) => {
			const params = {
				wc_product_id: btn.dataset.wc_product_id,
				mm_product_id: btn.dataset.mm_product_id,
				design_uuid: btn.dataset.design_uuid,
				mm_url: btn.dataset.mm_url,
				read_only: btn.dataset.read_only,
				admin: btn.dataset.admin
			};
			btn.addEventListener(
				"click",
				(e) => {
					e.preventDefault();
					e.stopPropagation();
					potiskarium_designer_show(params);
				}
			);
		}
	);
}

document.addEventListener("DOMContentLoaded", () => potiskarium_designer_init());

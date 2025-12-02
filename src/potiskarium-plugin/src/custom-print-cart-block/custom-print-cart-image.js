const { registerCheckoutFilters } = window.wc.blocksCheckout;

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

const modifyItemName = ( defaultValue, extensions, args ) => {
	setTimeout(() => hack_it(), 1000);
	return defaultValue;
};

registerCheckoutFilters( 'hack-extension', {
	itemName: modifyItemName,
} );

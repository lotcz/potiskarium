const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { createElement } = wp.element;

const Content = createElement(
	'div',
	null,
	'Platba kartou přes ComGate.'
);

const Edit = createElement(
	'div',
	null,
	'Platba kartou přes ComGate.'
);

registerPaymentMethod({
	name: 'comgate_simple',
	label: 'ComGate – Platba kartou',
	ariaLabel: 'ComGate – Platba kartou',

	canMakePayment: () => true,

	// MUST BE: React element (NOT a function)
	content: Content,
	edit: Edit,
});

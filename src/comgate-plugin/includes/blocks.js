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
	name: 'karel_comgate_plugin_payment',
	label: 'ComGate – Platba kartou',
	ariaLabel: 'ComGate – Platba kartou',

	canMakePayment: () => true,

	content: Content,
	edit: Edit,
	supports: {
		features: ['products']
	}
});

// Use the global WCBlocks object (provided by WooCommerce)
const { registerPaymentMethod } = wc.wcBlocksRegistry;

	const ComgatePayment = () => (
		<div>
			<p>Pay securely with Comgate card payment.</p>
			{/* Optional: add instructions, logo, etc. */}
		</div>
	);

	registerPaymentMethod({
		name: 'karel_comgate_plugin_payment',
		label: 'ComGate Gateway',
		ariaLabel: 'ComGate Gateway',
		content: <ComgatePayment />,
		edit: <ComgatePayment />,
		canMakePayment: () => true
	});


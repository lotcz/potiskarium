const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;
const { decodeEntities } = wp.htmlEntities;
const { useState } = wp.element;

// Get settings from PHP
const settings = getSetting('karel_comgate_plugin_payment_data', {});
const defaultLabel = decodeEntities(settings.title) || 'ComGate Gateway';
const description = decodeEntities(settings.description) || 'Pay securely with ComGate card payment.';

const ComgatePayment = ({ eventRegistration, emitResponse }) => {
	const [isProcessing, setIsProcessing] = useState(false);
	const { onPaymentSetup } = eventRegistration;

	// Register payment processing
	useState(() => {
		const unsubscribe = onPaymentSetup(async () => {
			setIsProcessing(true);

			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						payment_method: 'karel_comgate_plugin_payment'
					}
				}
			};
		});

		return unsubscribe;
	}, [onPaymentSetup, emitResponse]);

	return (
		<div className="comgate-payment-method">
			<p>{description}</p>
			{isProcessing && (
				<div className="comgate-processing">
					<span>Preparing payment...</span>
				</div>
			)}
			<div className="comgate-payment-icons">
				<span className="payment-icon">💳 Visa</span>
				<span className="payment-icon">💳 Mastercard</span>
				<span className="payment-icon">💳 Maestro</span>
			</div>
		</div>
	);
};

registerPaymentMethod({
	name: 'karel_comgate_plugin_payment',
	label: defaultLabel,
	ariaLabel: defaultLabel,
	content: <ComgatePayment />,
	edit: <ComgatePayment />,
	canMakePayment: () => true,
	supports: {
		features: settings.supports || ['products']
	}
});

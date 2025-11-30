const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { getSetting } = wc.wcSettings;
const { decodeEntities } = wp.htmlEntities;
const { useState } = wp.element;

// Get settings from PHP
const settings = getSetting('karel_comgate_plugin_payment_data', {});
const defaultLabel = decodeEntities(settings.title) || 'Online Payment';
const description = decodeEntities(settings.description) || 'You will be redirected to a secure payment gateway to complete the payment.';

const imagesUrl = KarelComgatePluginData.pluginUrl + 'img/';

const ComgatePayment = ({ eventRegistration, emitResponse }) => {
	const [isProcessing, setIsProcessing] = useState(false);
	const { onPaymentSetup } = eventRegistration;

	// Register payment processing
	useState(
		() => onPaymentSetup(
			async () => {
				setIsProcessing(true);

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							payment_method: 'karel_comgate_plugin_payment'
						}
					}
				};
			}
		),
		[onPaymentSetup, emitResponse]
	);

	return (
		<div>
			<div style={{display: 'flex', gap: '1rem', alignItems: 'center'}}>
				<img src={imagesUrl + 'visa.png'} alt="VISA"/>
				<img src={imagesUrl + 'maestro.png'} alt="Maestro"/>
				<img src={imagesUrl + 'mastercard.png'} alt="Mastercard"/>
				<img src={imagesUrl + 'apple-pay.png'} alt="Apple Pay"/>
				<img src={imagesUrl + 'google-pay.png'} alt="Google Pay"/>
			</div>
			<p>{description}</p>
			{isProcessing && (
				<div className="comgate-processing">
					<span>Preparing payment...</span>
				</div>
			)}
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

const { registerPaymentMethod } = wc.wcBlocksRegistry;
const { createElement, useState, useEffect } = wp.element;
const { __ } = wp.i18n;

const settings = wc.wcSettings.getSetting('karel_comgate_plugin_blocks_data', {});

// Label component (must be a function that returns an element)
const Label = (props) => {
	const { PaymentMethodLabel } = props.components;
	return createElement(PaymentMethodLabel, { text: 'Platba kartou' });
};

// Content component with card fields (this is a function component)
const Content = (props) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;

	const [cardNumber, setCardNumber] = useState('');
	const [expiry, setExpiry] = useState('');
	const [cvv, setCvv] = useState('');
	const [errors, setErrors] = useState({});

	useEffect(() => {
		const unsubscribe = onPaymentSetup(() => {
			const newErrors = {};

			// Validate card number
			if (!cardNumber || cardNumber.replace(/\s/g, '').length < 13) {
				newErrors.cardNumber = __('Zadejte platné číslo karty', 'karel-comgate-plugin');
			}

			// Validate expiry
			if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
				newErrors.expiry = __('Zadejte datum expirace ve formátu MM/RR', 'karel-comgate-plugin');
			}

			// Validate CVV
			if (!cvv || cvv.length < 3) {
				newErrors.cvv = __('Zadejte platný CVV kód', 'karel-comgate-plugin');
			}

			if (Object.keys(newErrors).length > 0) {
				setErrors(newErrors);
				return {
					type: emitResponse.responseTypes.ERROR,
					message: __('Zkontrolujte prosím platební údaje', 'karel-comgate-plugin'),
				};
			}

			// Return success with payment data
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						karel_comgate_card_number: cardNumber.replace(/\s/g, ''),
						karel_comgate_expiry: expiry,
						karel_comgate_cvv: cvv,
					},
				},
			};
		});

		return unsubscribe;
	}, [onPaymentSetup, cardNumber, expiry, cvv, emitResponse.responseTypes]);

	const inputStyle = {
		width: '100%',
		padding: '0.75rem',
		border: '1px solid #ddd',
		borderRadius: '4px',
		fontSize: '1rem',
	};

	const errorInputStyle = {
		width: '100%',
		padding: '0.75rem',
		border: '1px solid #dc3232',
		borderRadius: '4px',
		fontSize: '1rem',
	};

	return createElement(
		'div',
		{ className: 'wc-block-components-payment-method-content' },

		// Description
		createElement(
			'div',
			{ style: { marginBottom: '1rem' } },
			'Platba kartou přes ComGate.'
		),

		// Card Number Field
		createElement(
			'div',
			{ style: { marginBottom: '1rem' } },
			createElement(
				'label',
				{
					htmlFor: 'comgate-card-number',
					style: { display: 'block', marginBottom: '0.5rem', fontWeight: '600' }
				},
				'Číslo karty',
				createElement('span', { style: { color: '#dc3232' } }, ' *')
			),
			createElement('input', {
				type: 'text',
				id: 'comgate-card-number',
				value: cardNumber,
				onChange: (e) => {
					let value = e.target.value.replace(/\s/g, '');
					value = value.replace(/(\d{4})/g, '$1 ').trim();
					setCardNumber(value);
					setErrors(prev => ({ ...prev, cardNumber: undefined }));
				},
				placeholder: '1234 5678 9012 3456',
				maxLength: 19,
				style: errors.cardNumber ? errorInputStyle : inputStyle,
			}),
			errors.cardNumber && createElement(
				'div',
				{ style: { color: '#dc3232', fontSize: '0.875rem', marginTop: '0.25rem' } },
				errors.cardNumber
			)
		),

		// Expiry and CVV Row
		createElement(
			'div',
			{ style: { display: 'flex', gap: '1rem' } },

			// Expiry Field
			createElement(
				'div',
				{ style: { flex: 1 } },
				createElement(
					'label',
					{
						htmlFor: 'comgate-expiry',
						style: { display: 'block', marginBottom: '0.5rem', fontWeight: '600' }
					},
					'Expirace',
					createElement('span', { style: { color: '#dc3232' } }, ' *')
				),
				createElement('input', {
					type: 'text',
					id: 'comgate-expiry',
					value: expiry,
					onChange: (e) => {
						let value = e.target.value.replace(/\D/g, '');
						if (value.length >= 2) {
							value = value.slice(0, 2) + '/' + value.slice(2, 4);
						}
						setExpiry(value);
						setErrors(prev => ({ ...prev, expiry: undefined }));
					},
					placeholder: 'MM/RR',
					maxLength: 5,
					style: errors.expiry ? errorInputStyle : inputStyle,
				}),
				errors.expiry && createElement(
					'div',
					{ style: { color: '#dc3232', fontSize: '0.875rem', marginTop: '0.25rem' } },
					errors.expiry
				)
			),

			// CVV Field
			createElement(
				'div',
				{ style: { flex: 1 } },
				createElement(
					'label',
					{
						htmlFor: 'comgate-cvv',
						style: { display: 'block', marginBottom: '0.5rem', fontWeight: '600' }
					},
					'CVV',
					createElement('span', { style: { color: '#dc3232' } }, ' *')
				),
				createElement('input', {
					type: 'text',
					id: 'comgate-cvv',
					value: cvv,
					onChange: (e) => {
						const value = e.target.value.replace(/\D/g, '');
						setCvv(value);
						setErrors(prev => ({ ...prev, cvv: undefined }));
					},
					placeholder: '123',
					maxLength: 4,
					style: errors.cvv ? errorInputStyle : inputStyle,
				}),
				errors.cvv && createElement(
					'div',
					{ style: { color: '#dc3232', fontSize: '0.875rem', marginTop: '0.25rem' } },
					errors.cvv
				)
			)
		)
	);
};

// Edit component for the block editor (function component)
const Edit = (props) => {
	return createElement(
		'div',
		{ style: { padding: '1rem', backgroundColor: '#f0f0f0', borderRadius: '4px' } },
		createElement('p', { style: { margin: 0 } }, 'Platba kartou přes ComGate - náhled editoru')
	);
};

registerPaymentMethod({
	name: 'karel_comgate_plugin_payment',
	label: Label,
	content: Content,
	edit: Edit,
	ariaLabel: 'Platba kartou přes ComGate',
	canMakePayment: () => true,
	supports: {
		features: settings.supports?.features || ['products'],
	},
});

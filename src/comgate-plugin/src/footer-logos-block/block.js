import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function comgateFooterLogosUrl(variant) {
	const IMAGES_BASE_URI = window.KarelComgatePluginData ? window.KarelComgatePluginData.pluginUrl : '';
	if (!variant) {
		variant = 'light';
	}

	return IMAGES_BASE_URI + '/img/footer-logos-' + variant + '.png';
}

registerBlockType('comgate-plugin/footer-logos-block', {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { variant } = attributes;

		const onVariantChange = ( newVariant ) => {
			setAttributes( { variant: newVariant } );
		};

		return (
			<>
				<InspectorControls>
					<div style={{ margin: '1em' }}>
						<SelectControl
							label={ __( 'Variant') }
							value={ variant }
							options={ [
								{ label: 'Light Background', value: "light" },
								{ label: 'Dark Background', value: "dark" },
							] }
							onChange={ onVariantChange }
						/>
					</div>
				</InspectorControls>

				<div {...blockProps }>
					<img
						style={{ maxWidth: '100%' }}
						src={comgateFooterLogosUrl(variant)}
						alt="Comgate"
					/>
				</div>
			</>
		);
	},
	save: ({ attributes }) => {
		const blockProps = useBlockProps.save();
		const { variant } = attributes;

		return <div {...blockProps}>
			<img
				style={{ maxWidth: '100%' }}
				src={comgateFooterLogosUrl(variant)}
				alt="Comgate"
			/>
		</div>;
	},
} );

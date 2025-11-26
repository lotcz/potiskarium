import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function potiskariumLogoUrl(variant) {
	const THEME_BASE_URI = (typeof PotiskariumThemeData !== 'undefined') ? PotiskariumThemeData.themeUri : '';

	if (!variant) {
		variant = 'logo-white';
	}
	return THEME_BASE_URI + '/img/' + variant + '.svg';
}

registerBlockType( 'potiskarium-theme/logo-block', {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { variant } = attributes;

		const onVariantChange = ( newVariant ) => {
			setAttributes( { variant: newVariant } );
		};

		return (
			<>
				<InspectorControls>
					<div style={{ marginTop: '1em' }}>
						<SelectControl
							label={ __( 'Image Variant', 'simple-image-block' ) }
							value={ variant }
							options={ [
								{ label: 'White', value: "logo-white" },
								{ label: 'Purple', value: "logo" },
							] }
							onChange={ onVariantChange }
						/>
					</div>
				</InspectorControls>

				<div { ...blockProps }>
					<img src={potiskariumLogoUrl(variant)} alt="potiskarium-logo" />
				</div>
			</>
		);
	},
	save: ({ attributes }) => {
		const blockProps = useBlockProps.save();
		const { variant } = attributes;

		return <div { ...blockProps }>
			<img src={potiskariumLogoUrl(variant)} alt="Potiskarium" />
		</div>;
	},
} );

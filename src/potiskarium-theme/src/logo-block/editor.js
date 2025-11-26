import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function potiskariumLogoUrl(variant) {
	const THEME_BASE_URI = (typeof PotiskariumThemeData !== 'undefined') ? PotiskariumThemeData.themeUri : '';
	if (!variant) {
		variant = 'logo-white';
	}

	return THEME_BASE_URI + '/img/' + variant + '.svg';
}

function potiskariumLogoStyle(maxHeight) {
	if (!maxHeight) {
		maxHeight = 200;
	}

	return {maxHeight: maxHeight};
}

registerBlockType( 'potiskarium-theme/logo-block', {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { variant, maxHeight } = attributes;

		const onVariantChange = ( newVariant ) => {
			setAttributes( { variant: newVariant } );
		};

		const onMaxHeightChange = ( h ) => {
			setAttributes( { maxHeight: h } );
		};

		return (
			<>
				<InspectorControls>
					<div style={{ margin: '1em' }}>
						<SelectControl
							label={ __( 'Image Variant') }
							value={ variant }
							options={ [
								{ label: 'White', value: "logo-white" },
								{ label: 'Purple', value: "logo" },
							] }
							onChange={ onVariantChange }
						/>
						<UnitControl
							label={ __( 'Max. height') }
							value={ maxHeight }
							required={false}
							onChange={ onMaxHeightChange }
							units={[
								{ value: 'px', label: 'px' }
							]}
						/>
					</div>
				</InspectorControls>

				<div { ...blockProps }>
					<img
						src={potiskariumLogoUrl(variant)}
						alt="potiskarium-logo"
						style={potiskariumLogoStyle(maxHeight)}
					/>
				</div>
			</>
		);
	},
	save: ({ attributes }) => {
		const blockProps = useBlockProps.save();
		const { variant, maxHeight } = attributes;

		return <div { ...blockProps }>
			<img
				src={potiskariumLogoUrl(variant)}
				alt="Potiskarium"
				style={potiskariumLogoStyle(maxHeight)}
			/>
		</div>;
	},
} );

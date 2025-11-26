import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'potiskarium-theme/logo-block', {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { variant } = attributes;

		const onVariantChange = ( newVariant ) => {
			setAttributes( { variant: newVariant } );
		};

		return (
			<div { ...blockProps }>
				{/* 2. Variant Control (in Inspector Controls, which appear in the sidebar) */}
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
			</div>
		);
	},
	save: () => {
		// Dynamic blocks should return null for the save function
		return null;
	},
} );

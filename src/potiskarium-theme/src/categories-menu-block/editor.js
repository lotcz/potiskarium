import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType( 'potiskarium-theme/categories-menu-block', {
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
								{ label: 'Horizontal', value: "horizontal" },
								{ label: 'Vertical', value: "vertical" },
							] }
							onChange={ onVariantChange }
						/>
					</div>
				</InspectorControls>

				<div {...blockProps }>
					<div className="product-categories-menu">
						<ul className="product-categories-list">
							<li	className="product-categories-list-item"><a href="#">Woocommerce</a></li>
							<li	className="product-categories-list-item"><a href="#">Categories</a></li>
							<li	className="product-categories-list-item"><a href="#">Will</a></li>
							<li	className="product-categories-list-item"><a href="#">Show</a></li>
							<li	className="product-categories-list-item"><a href="#">Here</a></li>
						</ul>
					</div>
				</div>
			</>
		);
	},
	save: () => null,
} );

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType( 'potiskarium-theme/categories-slider-block', {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { hideEmpty, hideDefault, showDefaultLast } = attributes;

		return (
			<>
				<InspectorControls>
					<div style={{ margin: '1em' }}>
						<ToggleControl
							label={ __( 'Hide Empty') }
							checked={ hideEmpty }
							onChange={ (value) => setAttributes({hideEmpty: value }) }
						/>
						<ToggleControl
							label={ __( 'Hide Default Category') }
							checked={ hideDefault }
							onChange={ (value) => setAttributes({hideDefault: value }) }
						/>
						<ToggleControl
							disabled={ hideDefault }
							label={ __( 'Show Default Category Last') }
							checked={ showDefaultLast }
							onChange={ (value) => setAttributes({showDefaultLast: value }) }
						/>
					</div>
				</InspectorControls>

				<div {...blockProps}>
					<ServerSideRender block="potiskarium-theme/categories-slider-block" attributes={ attributes } />
				</div>
			</>
		);
	},
	save: () => null,
} );

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

registerBlockType('potiskarium-theme/collapsible-categories-block', {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { hideEmpty, hideDefault, showDefaultLast, showProductCounts, startExpanded } = attributes;

		return (
			<>
				<InspectorControls>
					<div style={{ margin: '1em' }}>
						<ToggleControl
							label={ __( 'Show Product Counts') }
							checked={ showProductCounts }
							onChange={ (value) => setAttributes({showProductCounts: value }) }
						/>
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
						<ToggleControl
							label={ __( 'Start With All Items Expanded') }
							checked={ startExpanded }
							onChange={ (value) => setAttributes({startExpanded: value }) }
						/>
					</div>
				</InspectorControls>

				<div {...blockProps}>
					<ServerSideRender block="potiskarium-theme/collapsible-categories-block" attributes={ attributes } />
				</div>
			</>
		);
	},
	save: () => null,
} );

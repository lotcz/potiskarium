import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
	const {  } = attributes;
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<button>Designer</button>
		</div>
	);
}

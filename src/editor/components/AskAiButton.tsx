import { __ } from '@wordpress/i18n';

interface AskAiButtonProps {
	onAsk: () => void;
}

export function AskAiButton( { onAsk }: AskAiButtonProps ): JSX.Element {
	return (
		<button type="button" className="rms-ask-ai" onClick={ onAsk }>
			{ __( 'Ask AI about this page', 'rankmyseo' ) }
		</button>
	);
}

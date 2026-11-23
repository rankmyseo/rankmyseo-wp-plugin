import { __ } from '@wordpress/i18n';
import {
	DESCRIPTION_FONT,
	DESKTOP_DESCRIPTION_PX,
	DESKTOP_TITLE_PX,
	MOBILE_DESCRIPTION_PX,
	MOBILE_TITLE_PX,
	TITLE_FONT,
	truncateToPixels,
} from '../metrics';

interface SerpPreviewProps {
	viewport: 'desktop' | 'mobile';
	title: string;
	description: string;
	url: string;
}

export function SerpPreview( {
	viewport,
	title,
	description,
	url,
}: SerpPreviewProps ): JSX.Element {
	const titlePx = viewport === 'mobile' ? MOBILE_TITLE_PX : DESKTOP_TITLE_PX;
	const descriptionPx =
		viewport === 'mobile' ? MOBILE_DESCRIPTION_PX : DESKTOP_DESCRIPTION_PX;
	const shownTitle = truncateToPixels(
		title || __( 'Untitled page', 'rankmyseo' ),
		TITLE_FONT,
		titlePx
	);
	const shownDescription = truncateToPixels(
		description || __( 'No description yet.', 'rankmyseo' ),
		DESCRIPTION_FONT,
		descriptionPx
	);

	return (
		<article
			className={ `rms-serp is-${ viewport }` }
			aria-label={
				viewport === 'mobile'
					? __( 'Mobile Google preview', 'rankmyseo' )
					: __( 'Desktop Google preview', 'rankmyseo' )
			}
		>
			<p className="rms-serp-label">
				{ viewport === 'mobile'
					? __( 'Mobile', 'rankmyseo' )
					: __( 'Desktop', 'rankmyseo' ) }
			</p>
			<p className="rms-serp-url">{ url }</p>
			<h3 className="rms-serp-title">{ shownTitle }</h3>
			<p className="rms-serp-description">{ shownDescription }</p>
		</article>
	);
}

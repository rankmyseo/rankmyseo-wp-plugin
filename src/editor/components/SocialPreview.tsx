import { __ } from '@wordpress/i18n';

interface SocialPreviewProps {
	network: 'facebook' | 'x';
	title: string;
	description: string;
	image: string;
	url: string;
}

export function SocialPreview( {
	network,
	title,
	description,
	image,
	url,
}: SocialPreviewProps ): JSX.Element {
	const label =
		network === 'facebook'
			? __( 'Facebook preview', 'rankmyseo' )
			: __( 'X preview', 'rankmyseo' );

	return (
		<article
			className={ `rms-social is-${ network }` }
			aria-label={ label }
		>
			<p className="rms-social-label">{ label }</p>
			{ image ? (
				<img className="rms-social-image" src={ image } alt="" />
			) : (
				<div className="rms-social-image is-empty">
					{ __( 'No image yet', 'rankmyseo' ) }
				</div>
			) }
			<p className="rms-social-url">{ url }</p>
			<h3 className="rms-social-title">
				{ title || __( 'Shared page', 'rankmyseo' ) }
			</h3>
			<p className="rms-social-description">
				{ description ||
					__( 'No social description yet.', 'rankmyseo' ) }
			</p>
		</article>
	);
}

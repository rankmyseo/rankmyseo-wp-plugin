import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockEditProps, BreadcrumbAttributes } from '../types';

export function BreadcrumbEdit( {
	attributes,
	setAttributes,
}: BlockEditProps< BreadcrumbAttributes > ): JSX.Element {
	const separator = attributes.separator || '/';
	const crumbs = [
		attributes.showHome !== false ? __( 'Home', 'rankmyseo' ) : null,
		__( 'Section', 'rankmyseo' ),
		attributes.showCurrent !== false
			? __( 'Current page', 'rankmyseo' )
			: null,
	].filter( ( crumb ): crumb is string => crumb !== null );

	return (
		<div className="rankmyseo-block rankmyseo-block-breadcrumbs">
			<nav aria-label={ __( 'Breadcrumb', 'rankmyseo' ) }>
				<ol className="rankmyseo-breadcrumbs__list">
					{ crumbs.map( ( crumb, index ) => (
						<li key={ crumb }>
							{ index > 0 && (
								<span aria-hidden="true">{ separator }</span>
							) }
							{ crumb }
						</li>
					) ) }
				</ol>
			</nav>
			<div className="rankmyseo-block__controls">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Separator', 'rankmyseo' ) }
					value={ attributes.separator }
					onChange={ ( value ) =>
						setAttributes( { separator: value } )
					}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show home', 'rankmyseo' ) }
					checked={ attributes.showHome !== false }
					onChange={ ( value ) =>
						setAttributes( { showHome: value } )
					}
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show current page', 'rankmyseo' ) }
					checked={ attributes.showCurrent !== false }
					onChange={ ( value ) =>
						setAttributes( { showCurrent: value } )
					}
				/>
			</div>
		</div>
	);
}

import { __ } from '@wordpress/i18n';
import { StatusBadge } from '../../shared/components/StatePrimitives';
import type { BuilderIntegrationStatus } from '../../shared/types';

export function builderStateLabel( item: BuilderIntegrationStatus ): string {
	if ( item.active ) {
		return __( 'Active', 'rankmyseo' );
	}
	if ( item.detected ) {
		return __( 'Detected', 'rankmyseo' );
	}
	return __( 'Not detected', 'rankmyseo' );
}

export function BuilderStatusList( {
	elementor,
	divi,
}: {
	elementor: BuilderIntegrationStatus;
	divi: BuilderIntegrationStatus;
} ): JSX.Element {
	const items = [ elementor, divi ];
	return (
		<>
			{ items.map( ( item ) => {
				let tone: 'up' | 'warn' | 'flat' = 'flat';
				if ( item.active ) {
					tone = 'up';
				} else if ( item.detected ) {
					tone = 'warn';
				}
				return (
					<div key={ item.id }>
						<dt>{ item.label }</dt>
						<dd>
							<StatusBadge tone={ tone }>
								{ builderStateLabel( item ) }
							</StatusBadge>
							{ item.version ? (
								<span className="rms-mono">{ ` ${ item.version }` }</span>
							) : null }
						</dd>
					</div>
				);
			} ) }
		</>
	);
}

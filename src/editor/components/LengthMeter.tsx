import { __ } from '@wordpress/i18n';
import { meterStatus, type MeterStatus } from '../metrics';

interface LengthMeterProps {
	label: string;
	length: number;
	min: number;
	max: number;
	pixelWidth: number;
	pixelMax: number;
}

const STATUS_LABEL: Record< MeterStatus, string > = {
	empty: __( 'Add a title or description', 'rankmyseo' ),
	short: __( 'A little short', 'rankmyseo' ),
	good: __( 'Good length', 'rankmyseo' ),
	long: __( 'Too long for Google', 'rankmyseo' ),
};

export function LengthMeter( {
	label,
	length,
	min,
	max,
	pixelWidth,
	pixelMax,
}: LengthMeterProps ): JSX.Element {
	const status = meterStatus( length, min, max, pixelWidth, pixelMax );
	const width = Math.min(
		100,
		Math.round( ( pixelWidth / pixelMax ) * 100 )
	);

	return (
		<div className={ `rms-meter is-${ status }` }>
			<div
				className="rms-meter-track"
				role="meter"
				aria-label={ label }
				aria-valuemin={ 0 }
				aria-valuemax={ pixelMax }
				aria-valuenow={ Math.min( pixelWidth, pixelMax ) }
				aria-valuetext={ STATUS_LABEL[ status ] }
			>
				<span style={ { width: `${ width }%` } } />
			</div>
			<p className="rms-meter-caption">
				{ length } { __( 'characters', 'rankmyseo' ) }
				<span aria-hidden="true"> · </span>
				{ pixelWidth }px
				<span className="rms-meter-status">
					{ STATUS_LABEL[ status ] }
				</span>
			</p>
		</div>
	);
}

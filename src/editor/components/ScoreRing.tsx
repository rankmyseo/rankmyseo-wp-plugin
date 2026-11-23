import { bandLabel, type ScoreBand } from '../../analyzer';

const STROKE: Record< ScoreBand, string > = {
	good: '#1f9d64',
	needs_work: '#b45309',
	poor: '#e24b4a',
	no_keyword: '#6b7280',
};

export function ScoreRing( {
	score,
	band,
}: {
	score: number;
	band: ScoreBand;
} ): JSX.Element {
	const label = bandLabel( band );
	const radius = 18;
	const circumference = 2 * Math.PI * radius;
	const dash =
		( Math.max( 0, Math.min( 100, score ) ) / 100 ) * circumference;

	return (
		<div
			className={ `rms-score-ring is-${ band.replace( '_', '-' ) }` }
			role="img"
			aria-label={ `${ score }, ${ label }` }
		>
			<svg viewBox="0 0 44 44" aria-hidden="true">
				<circle
					cx="22"
					cy="22"
					r={ radius }
					fill="none"
					stroke="#e4e0db"
					strokeWidth="4"
				/>
				<circle
					cx="22"
					cy="22"
					r={ radius }
					fill="none"
					stroke={ STROKE[ band ] }
					strokeWidth="4"
					strokeDasharray={ `${ dash } ${ circumference }` }
					strokeLinecap="round"
				/>
			</svg>
			<strong className="rms-tabular">{ score }</strong>
			<span className="rms-sr-only">{ label }</span>
		</div>
	);
}

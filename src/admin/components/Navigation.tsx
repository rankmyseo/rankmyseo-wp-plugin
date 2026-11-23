import { __ } from '@wordpress/i18n';
import type { NavPageId } from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

interface NavItem {
	id: NavPageId;
	label: string;
	simpleLabel: string;
	showInSimple: boolean;
}

const NAV_ITEMS: NavItem[] = [
	{
		id: 'dashboard',
		label: __( 'Dashboard', 'rankmyseo' ),
		simpleLabel: __( 'Home', 'rankmyseo' ),
		showInSimple: true,
	},
	{
		id: 'meta',
		label: __( 'Titles & Meta', 'rankmyseo' ),
		simpleLabel: __( 'Search Previews', 'rankmyseo' ),
		showInSimple: true,
	},
	{
		id: 'schema',
		label: __( 'Schema', 'rankmyseo' ),
		simpleLabel: __( 'Site Details', 'rankmyseo' ),
		showInSimple: false,
	},
	{
		id: 'sitemaps',
		label: __( 'Sitemaps', 'rankmyseo' ),
		simpleLabel: __( 'Sitemaps', 'rankmyseo' ),
		showInSimple: false,
	},
	{
		id: 'redirects',
		label: __( 'Redirects & 404s', 'rankmyseo' ),
		simpleLabel: __( 'Broken Links', 'rankmyseo' ),
		showInSimple: true,
	},
	{
		id: 'import',
		label: __( 'Import', 'rankmyseo' ),
		simpleLabel: __( 'Import', 'rankmyseo' ),
		showInSimple: true,
	},
	{
		id: 'cloud',
		label: __( 'RankMySEO Cloud', 'rankmyseo' ),
		simpleLabel: __( 'Cloud', 'rankmyseo' ),
		showInSimple: false,
	},
	{
		id: 'ai-queue',
		label: __( 'AI Queue', 'rankmyseo' ),
		simpleLabel: __( 'AI Queue', 'rankmyseo' ),
		showInSimple: false,
	},
	{
		id: 'settings',
		label: __( 'Settings', 'rankmyseo' ),
		simpleLabel: __( 'Settings', 'rankmyseo' ),
		showInSimple: true,
	},
];

export function Navigation(): JSX.Element {
	const { activePage, navigateTo, uiMode } = useAdmin();

	const visibleItems =
		uiMode === 'simple'
			? NAV_ITEMS.filter(
					( item ) => item.showInSimple || item.id === activePage
			  )
			: NAV_ITEMS;

	return (
		<nav
			className="rms-nav"
			aria-label={ __( 'RankMySEO sections', 'rankmyseo' ) }
		>
			<ul className="rms-nav-list">
				{ visibleItems.map( ( item ) => {
					const isActive = item.id === activePage;
					return (
						<li key={ item.id } className="rms-nav-item">
							<button
								type="button"
								className={ `rms-nav-tab${
									isActive ? ' is-active' : ''
								}` }
								aria-current={ isActive ? 'page' : undefined }
								onClick={ () => navigateTo( item.id ) }
							>
								{ uiMode === 'simple'
									? item.simpleLabel
									: item.label }
							</button>
						</li>
					);
				} ) }
			</ul>
		</nav>
	);
}

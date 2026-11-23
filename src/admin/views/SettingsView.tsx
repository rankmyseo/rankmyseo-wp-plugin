import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent } from 'react';
import {
	SkeletonCard,
	StatusBadge,
} from '../../shared/components/StatePrimitives';
import type { SiteType, UiMode } from '../../shared/types';
import { useAdmin } from '../context/AdminContext';

const SEPARATOR_OPTIONS = [
	{ label: '| (Pipe)', value: '|' },
	{ label: '- (Hyphen)', value: '-' },
	{ label: '· (Middle dot)', value: '·' },
	{ label: '— (Em dash)', value: '—' },
	{ label: '/ (Slash)', value: '/' },
	{ label: '• (Bullet)', value: '•' },
];

export function SettingsView(): JSX.Element {
	const {
		uiMode,
		settings,
		modules,
		status,
		loading,
		saving,
		saveSettings,
		toggleModule,
		reopenWizard,
	} = useAdmin();

	const [ siteName, setSiteName ] = useState< string >( '' );
	const [ siteType, setSiteType ] = useState< SiteType >( 'organization' );
	const [ titleSeparator, setTitleSeparator ] = useState< string >( '|' );
	const [ defaultUiMode, setDefaultUiMode ] = useState< UiMode >( 'simple' );
	const [ keepData, setKeepData ] = useState< boolean >( true );
	const [ telemetryOptIn, setTelemetryOptIn ] = useState< boolean >( false );

	useEffect( () => {
		if ( ! settings ) {
			return;
		}
		setSiteName( settings.site_name );
		setSiteType( settings.site_type );
		setTitleSeparator( settings.title_separator );
		setDefaultUiMode( settings.default_ui_mode );
		setKeepData( settings.keep_data_on_uninstall );
		setTelemetryOptIn( settings.telemetry_opt_in );
	}, [ settings ] );

	if ( loading && ! settings ) {
		return (
			<div className="rms-stack">
				<SkeletonCard lines={ 4 } />
				<SkeletonCard lines={ 3 } />
			</div>
		);
	}

	const handleSubmit = async ( event: FormEvent ): Promise< void > => {
		event.preventDefault();
		await saveSettings(
			{
				site_name: siteName,
				site_type: siteType,
				title_separator: titleSeparator,
				default_ui_mode: defaultUiMode,
				keep_data_on_uninstall: keepData,
				telemetry_opt_in: telemetryOptIn,
			},
			__( 'Settings saved.', 'rankmyseo' )
		);
	};

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<h1 className="rms-page-title">
						{ __( 'Settings', 'rankmyseo' ) }
					</h1>
					<p className="rms-muted">
						{ uiMode === 'simple'
							? __(
									'Manage your site basics, active SEO tools, and privacy preferences.',
									'rankmyseo'
							  )
							: __(
									'Configure global plugin defaults, module toggles, data lifecycle, and system diagnostics.',
									'rankmyseo'
							  ) }
					</p>
				</div>
				<Button variant="secondary" onClick={ reopenWizard }>
					{ __( 'Re-run Setup Wizard', 'rankmyseo' ) }
				</Button>
			</div>

			<form className="rms-card rms-form-card" onSubmit={ handleSubmit }>
				<h2 className="rms-section-title">
					{ __( 'General Site Settings', 'rankmyseo' ) }
				</h2>

				<div className="rms-form-grid">
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Site or brand name', 'rankmyseo' ) }
						value={ siteName }
						onChange={ setSiteName }
						help={ __(
							'Used in default search titles and site structured data.',
							'rankmyseo'
						) }
					/>

					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Site represents', 'rankmyseo' ) }
						value={ siteType }
						options={ [
							{
								label: __(
									'Business or Organization',
									'rankmyseo'
								),
								value: 'organization',
							},
							{
								label: __( 'Person', 'rankmyseo' ),
								value: 'person',
							},
						] }
						onChange={ ( val ) =>
							setSiteType(
								val === 'person' ? 'person' : 'organization'
							)
						}
					/>

					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Title separator', 'rankmyseo' ) }
						value={ titleSeparator }
						options={ SEPARATOR_OPTIONS }
						onChange={ setTitleSeparator }
					/>

					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Default mode for new users',
							'rankmyseo'
						) }
						value={ defaultUiMode }
						options={ [
							{
								label: __(
									'Simple (Guided plain-English view)',
									'rankmyseo'
								),
								value: 'simple',
							},
							{
								label: __(
									'Advanced (Full granular controls)',
									'rankmyseo'
								),
								value: 'advanced',
							},
						] }
						help={ __(
							'Each WordPress user can switch modes anytime from the top bar.',
							'rankmyseo'
						) }
						onChange={ ( val ) =>
							setDefaultUiMode(
								val === 'advanced' ? 'advanced' : 'simple'
							)
						}
					/>
				</div>

				<div className="rms-toggle-stack">
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Keep SEO data if plugin is uninstalled',
							'rankmyseo'
						) }
						help={ __(
							'On by default. Turn this off to delete RankMySEO tables, options, transients, scheduled tasks, and saved SEO fields when the plugin is deleted.',
							'rankmyseo'
						) }
						checked={ keepData }
						onChange={ setKeepData }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Share anonymous plugin diagnostics (optional)',
							'rankmyseo'
						) }
						help={ __(
							'Off by default. Helps improve compatibility by sharing only WordPress and PHP version totals.',
							'rankmyseo'
						) }
						checked={ telemetryOptIn }
						onChange={ setTelemetryOptIn }
					/>
				</div>

				<div className="rms-form-footer">
					<Button
						type="submit"
						variant="primary"
						className="rms-btn-primary"
						isBusy={ saving }
						disabled={ saving }
					>
						{ __( 'Save Settings', 'rankmyseo' ) }
					</Button>
				</div>
			</form>

			<section className="rms-section">
				<div className="rms-section-header">
					<h2 className="rms-section-title">
						{ __( 'SEO Modules', 'rankmyseo' ) }
					</h2>
					<span className="rms-muted">
						{ __(
							'Turn individual features on or off anytime. Changes save immediately.',
							'rankmyseo'
						) }
					</span>
				</div>

				<div className="rms-module-grid">
					{ modules.map( ( mod ) => {
						let badgeTone: 'accent' | 'up' | 'flat' = 'flat';
						let badgeLabel = __( 'Off', 'rankmyseo' );
						if ( mod.requires_cloud ) {
							badgeTone = 'accent';
							badgeLabel = __( 'Cloud optional', 'rankmyseo' );
						} else if ( mod.enabled ) {
							badgeTone = 'up';
							badgeLabel = __( 'Active', 'rankmyseo' );
						}
						return (
							<article
								key={ mod.id }
								className="rms-card rms-module-card"
							>
								<div className="rms-module-card-top">
									<h3 className="rms-card-title">
										{ mod.name }
									</h3>
									<StatusBadge tone={ badgeTone }>
										{ badgeLabel }
									</StatusBadge>
								</div>
								<p className="rms-module-desc">
									{ uiMode === 'simple'
										? mod.simple_description
										: mod.description }
								</p>
								<div className="rms-module-card-footer">
									<ToggleControl
										__nextHasNoMarginBottom
										label={
											mod.enabled
												? __( 'Enabled', 'rankmyseo' )
												: __( 'Disabled', 'rankmyseo' )
										}
										checked={ mod.enabled }
										disabled={ saving }
										onChange={ ( next ) => {
											void toggleModule( mod.id, next );
										} }
									/>
								</div>
							</article>
						);
					} ) }
				</div>
			</section>

			{ uiMode === 'advanced' && status && (
				<section className="rms-card rms-diagnostics-card">
					<h2 className="rms-section-title">
						{ __( 'System Health & Diagnostics', 'rankmyseo' ) }
					</h2>
					<dl className="rms-kv-grid">
						<div>
							<dt>{ __( 'Plugin Version', 'rankmyseo' ) }</dt>
							<dd className="rms-mono">
								{ status.plugin_version }
							</dd>
						</div>
						<div>
							<dt>{ __( 'WordPress Version', 'rankmyseo' ) }</dt>
							<dd className="rms-mono">
								{ status.wp_version || '6.4+' }
							</dd>
						</div>
						<div>
							<dt>{ __( 'PHP Version', 'rankmyseo' ) }</dt>
							<dd className="rms-mono">{ status.php_version }</dd>
						</div>
						<div>
							<dt>{ __( 'Schema Version', 'rankmyseo' ) }</dt>
							<dd className="rms-mono">
								{ `${ status.database.installed_version } / ${ status.database.target_version }` }
							</dd>
						</div>
						<div>
							<dt>{ __( 'Capabilities', 'rankmyseo' ) }</dt>
							<dd className="rms-mono">
								rankmyseo_manage, rankmyseo_approve_ai
							</dd>
						</div>
						<div>
							<dt>{ __( 'Custom Tables', 'rankmyseo' ) }</dt>
							<dd className="rms-mono">
								{ Object.keys( status.database.tables ).join(
									', '
								) }
							</dd>
						</div>
					</dl>
				</section>
			) }
		</div>
	);
}

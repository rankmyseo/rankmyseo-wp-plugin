import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent } from 'react';
import {
	ErrorBanner,
	SkeletonCard,
	StatusBadge,
} from '../../../shared/components/StatePrimitives';
import { useAdmin } from '../../context/AdminContext';
import { fetchWooSettings, saveWooSettings } from './api';
import { hasCustomAdvancedRules, type WooSettings } from './types';

const FEE_OPTIONS = [
	{ label: __( 'Free returns', 'rankmyseo' ), value: 'FreeReturn' },
	{
		label: __( 'The customer pays', 'rankmyseo' ),
		value: 'ReturnFeesCustomerResponsibility',
	},
	{
		label: __( 'The customer pays return shipping', 'rankmyseo' ),
		value: 'ReturnShippingFees',
	},
	{
		label: __( 'Original shipping is not refunded', 'rankmyseo' ),
		value: 'OriginalShippingFees',
	},
];

export function StoreView(): JSX.Element {
	const { uiMode, modules, saving, toggleModule } = useAdmin();
	const descriptor = modules.find(
		( module ) => module.id === 'woocommerce'
	);
	const moduleEnabled = descriptor?.enabled ?? true;
	const [ settings, setSettings ] = useState< WooSettings | null >( null );
	const [ active, setActive ] = useState< boolean >( true );
	const [ loading, setLoading ] = useState< boolean >( true );
	const [ localSaving, setLocalSaving ] = useState< boolean >( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ saved, setSaved ] = useState< boolean >( false );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const response = await fetchWooSettings();
			setSettings( response.settings );
			setActive( response.woocommerceActive );
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __( 'Could not load store settings.', 'rankmyseo' )
			);
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		void load();
	}, [ load ] );

	const update = ( patch: Partial< WooSettings > ): void => {
		setSaved( false );
		setSettings( ( current ) =>
			current ? { ...current, ...patch } : current
		);
	};

	const save = async ( event?: FormEvent ): Promise< void > => {
		event?.preventDefault();
		if ( ! settings ) {
			return;
		}
		setLocalSaving( true );
		setError( null );
		try {
			const response = await saveWooSettings( settings );
			setSettings( response.settings );
			setActive( response.woocommerceActive );
			setSaved( true );
		} catch ( err ) {
			setError(
				err instanceof Error
					? err.message
					: __( 'Could not save store settings.', 'rankmyseo' )
			);
		} finally {
			setLocalSaving( false );
		}
	};

	if ( loading && ! settings ) {
		return (
			<div className="rms-stack">
				<SkeletonCard lines={ 4 } />
			</div>
		);
	}

	if ( ! settings ) {
		return (
			<ErrorBanner
				message={
					error || __( 'Could not load store settings.', 'rankmyseo' )
				}
				onRetry={ () => {
					void load();
				} }
			/>
		);
	}

	const advanced = uiMode === 'advanced';
	const simpleFees = FEE_OPTIONS.filter( ( option ) =>
		[ 'FreeReturn', 'ReturnFeesCustomerResponsibility' ].includes(
			option.value
		)
	);

	return (
		<div className="rms-stack">
			<div className="rms-page-header">
				<div>
					<div className="rms-title-row">
						<h1 className="rms-page-title">
							{ advanced
								? __( 'Store', 'rankmyseo' )
								: __( 'Your shop in search', 'rankmyseo' ) }
						</h1>
						<StatusBadge tone={ moduleEnabled ? 'up' : 'flat' }>
							{ moduleEnabled
								? __( 'Module enabled', 'rankmyseo' )
								: __( 'Module disabled', 'rankmyseo' ) }
						</StatusBadge>
					</div>
					<p className="rms-muted">
						{ advanced
							? __(
									'Product offers, catalog URLs, and return and shipping details. Everything stays on this site.',
									'rankmyseo'
							  )
							: __(
									'Choose how products, prices, and sold-out items show up. These details stay on this site.',
									'rankmyseo'
							  ) }
					</p>
				</div>
				<ToggleControl
					__nextHasNoMarginBottom
					label={
						moduleEnabled
							? __( 'Enabled', 'rankmyseo' )
							: __( 'Disabled', 'rankmyseo' )
					}
					checked={ moduleEnabled }
					disabled={ saving }
					onChange={ ( next ) => {
						void toggleModule( 'woocommerce', next );
					} }
				/>
			</div>

			{ ! active && (
				<p className="rms-note">
					{ __(
						'WooCommerce is not active. These choices are saved and start working when WooCommerce is on.',
						'rankmyseo'
					) }
				</p>
			) }
			{ error && (
				<ErrorBanner
					message={ error }
					onRetry={ () => {
						void load();
					} }
				/>
			) }
			{ ! advanced && hasCustomAdvancedRules( settings ) && (
				<p className="rms-note">
					{ __(
						'Custom advanced rules active — switch to Advanced to edit',
						'rankmyseo'
					) }
				</p>
			) }

			<form
				className="rms-card rms-stack"
				onSubmit={ ( event ) => void save( event ) }
			>
				<div className="rms-toggle-stack">
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							advanced
								? __(
										'List one offer for each product option',
										'rankmyseo'
								  )
								: __(
										'Show each product option in search',
										'rankmyseo'
								  )
						}
						checked={ settings.variation_offers }
						onChange={ ( next ) =>
							update( { variation_offers: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							advanced
								? __(
										'Add product price and stock to social tags',
										'rankmyseo'
								  )
								: __(
										'Add price and stock when a product is shared',
										'rankmyseo'
								  )
						}
						checked={ settings.product_open_graph }
						onChange={ ( next ) =>
							update( { product_open_graph: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Hide sold-out products from search',
							'rankmyseo'
						) }
						checked={ settings.noindex_out_of_stock }
						onChange={ ( next ) =>
							update( { noindex_out_of_stock: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							advanced
								? __(
										'Canonicalize filtered and sorted shop URLs',
										'rankmyseo'
								  )
								: __(
										'Send filtered shop links to the main shop page',
										'rankmyseo'
								  )
						}
						checked={ settings.canonicalize_shop_filters }
						onChange={ ( next ) =>
							update( { canonicalize_shop_filters: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							advanced
								? __(
										'Noindex filtered and sorted shop URLs',
										'rankmyseo'
								  )
								: __(
										'Keep sorted and filtered shop links out of search',
										'rankmyseo'
								  )
						}
						checked={ settings.noindex_shop_filters }
						onChange={ ( next ) =>
							update( { noindex_shop_filters: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							advanced
								? __(
										'Remove the /product/ permalink base',
										'rankmyseo'
								  )
								: __( 'Use shorter product links', 'rankmyseo' )
						}
						checked={ settings.remove_product_base }
						onChange={ ( next ) =>
							update( { remove_product_base: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={
							advanced
								? __(
										'Remove the /product-category/ permalink base',
										'rankmyseo'
								  )
								: __(
										'Use shorter category links',
										'rankmyseo'
								  )
						}
						checked={ settings.remove_category_base }
						onChange={ ( next ) =>
							update( { remove_category_base: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Add your return policy', 'rankmyseo' ) }
						checked={ settings.return_policy_enabled }
						onChange={ ( next ) =>
							update( { return_policy_enabled: next } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Add shipping details', 'rankmyseo' ) }
						checked={ settings.shipping_enabled }
						onChange={ ( next ) =>
							update( { shipping_enabled: next } )
						}
					/>
				</div>

				{ advanced && (
					<p className="rms-muted">
						{ __(
							'Filtered and sorted shop links use orderby, min_price, max_price, rating_filter, filter_*, and query_type_* parameters. Other query parameters stay in the address.',
							'rankmyseo'
						) }
					</p>
				) }

				{ settings.return_policy_enabled && (
					<div className="rms-form-grid">
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Return country', 'rankmyseo' ) }
							help={ __(
								'Two letters, such as US.',
								'rankmyseo'
							) }
							value={ settings.return_country }
							onChange={ ( next ) =>
								update( { return_country: next.toUpperCase() } )
							}
						/>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="number"
							label={ __( 'Return window in days', 'rankmyseo' ) }
							value={ String( settings.return_days ) }
							onChange={ ( next ) =>
								update( {
									return_days:
										Number.parseInt( next, 10 ) || 0,
								} )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Who pays for the return',
								'rankmyseo'
							) }
							value={ settings.return_fees }
							options={ advanced ? FEE_OPTIONS : simpleFees }
							onChange={ ( next ) =>
								update( { return_fees: next } )
							}
						/>
						{ advanced && (
							<>
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Return window', 'rankmyseo' ) }
									value={ settings.return_category }
									options={
										[
											{
												label: __(
													'A set number of days',
													'rankmyseo'
												),
												value: 'MerchantReturnFiniteReturnWindow',
											},
											{
												label: __(
													'No time limit',
													'rankmyseo'
												),
												value: 'MerchantReturnUnlimitedWindow',
											},
											{
												label: __(
													'Returns are not accepted',
													'rankmyseo'
												),
												value: 'MerchantReturnNotPermitted',
											},
										] as Array< {
											label: string;
											value: string;
										} >
									}
									onChange={ ( next ) =>
										update( { return_category: next } )
									}
								/>
								<SelectControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'How shoppers send an item back',
										'rankmyseo'
									) }
									value={ settings.return_method }
									options={
										[
											{
												label: __(
													'By mail',
													'rankmyseo'
												),
												value: 'ReturnByMail',
											},
											{
												label: __(
													'In store',
													'rankmyseo'
												),
												value: 'ReturnInStore',
											},
											{
												label: __(
													'At a drop-off point',
													'rankmyseo'
												),
												value: 'ReturnAtKiosk',
											},
										] as Array< {
											label: string;
											value: string;
										} >
									}
									onChange={ ( next ) =>
										update( { return_method: next } )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Return policy link',
										'rankmyseo'
									) }
									value={ settings.return_policy_url }
									onChange={ ( next ) =>
										update( { return_policy_url: next } )
									}
								/>
							</>
						) }
					</div>
				) }

				{ settings.shipping_enabled && (
					<div className="rms-form-grid">
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Shipping country', 'rankmyseo' ) }
							help={ __(
								'Two letters, such as US.',
								'rankmyseo'
							) }
							value={ settings.shipping_country }
							onChange={ ( next ) =>
								update( {
									shipping_country: next.toUpperCase(),
								} )
							}
						/>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Shipping price', 'rankmyseo' ) }
							help={ __(
								'Use 0 for free shipping.',
								'rankmyseo'
							) }
							value={ settings.shipping_rate }
							onChange={ ( next ) =>
								update( { shipping_rate: next } )
							}
						/>
						{ advanced && (
							<>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Shipping currency',
										'rankmyseo'
									) }
									help={ __(
										'Three letters. Leave blank to use the shop currency.',
										'rankmyseo'
									) }
									value={ settings.shipping_currency }
									onChange={ ( next ) =>
										update( {
											shipping_currency:
												next.toUpperCase(),
										} )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									type="number"
									label={ __(
										'Handling time, minimum days',
										'rankmyseo'
									) }
									value={ String(
										settings.shipping_handling_min
									) }
									onChange={ ( next ) =>
										update( {
											shipping_handling_min:
												Number.parseInt( next, 10 ) ||
												0,
										} )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									type="number"
									label={ __(
										'Handling time, maximum days',
										'rankmyseo'
									) }
									value={ String(
										settings.shipping_handling_max
									) }
									onChange={ ( next ) =>
										update( {
											shipping_handling_max:
												Number.parseInt( next, 10 ) ||
												0,
										} )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									type="number"
									label={ __(
										'Transit time, minimum days',
										'rankmyseo'
									) }
									value={ String(
										settings.shipping_transit_min
									) }
									onChange={ ( next ) =>
										update( {
											shipping_transit_min:
												Number.parseInt( next, 10 ) ||
												0,
										} )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									type="number"
									label={ __(
										'Transit time, maximum days',
										'rankmyseo'
									) }
									value={ String(
										settings.shipping_transit_max
									) }
									onChange={ ( next ) =>
										update( {
											shipping_transit_max:
												Number.parseInt( next, 10 ) ||
												0,
										} )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Previous product link base',
										'rankmyseo'
									) }
									value={ settings.legacy_product_base }
									onChange={ ( next ) =>
										update( { legacy_product_base: next } )
									}
								/>
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __(
										'Previous category link base',
										'rankmyseo'
									) }
									value={ settings.legacy_category_base }
									onChange={ ( next ) =>
										update( { legacy_category_base: next } )
									}
								/>
							</>
						) }
					</div>
				) }

				<Button
					variant="primary"
					type="submit"
					className="rms-btn-primary"
					isBusy={ localSaving }
					disabled={ localSaving }
				>
					{ __( 'Save store settings', 'rankmyseo' ) }
				</Button>
				{ saved && (
					<p className="rms-note" role="status">
						{ __( 'Store settings saved.', 'rankmyseo' ) }
					</p>
				) }
			</form>
		</div>
	);
}

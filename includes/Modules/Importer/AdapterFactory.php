<?php
/**
 * Builds the adapter for a requested source.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Importer;

/**
 * Creates Yoast, Rank Math, and All in One SEO adapters.
 */
final class AdapterFactory {
	/**
	 * Source reader.
	 *
	 * @var SourceReader
	 */
	private SourceReader $reader;

	/**
	 * Destination store, used for the redirect hash setting.
	 *
	 * @var SiteStore
	 */
	private SiteStore $store;

	/**
	 * Constructor.
	 *
	 * @param SourceReader $reader Source data.
	 * @param SiteStore    $store  Destination store.
	 */
	public function __construct( SourceReader $reader, SiteStore $store ) {
		$this->reader = $reader;
		$this->store  = $store;
	}

	/**
	 * Every source the importer knows about.
	 *
	 * @return array<int, SourceAdapter>
	 */
	public function all(): array {
		$translator = new TemplateTranslator();
		$paths      = PathHash::from_store( $this->store );

		return [
			new YoastAdapter( $this->reader, $translator, $paths ),
			new RankMathAdapter( $this->reader, $translator, $paths ),
			new AioseoAdapter( $this->reader, $translator, $paths ),
		];
	}

	/**
	 * One source by ID.
	 *
	 * @param string $source yoast, rankmath, or aioseo.
	 * @return SourceAdapter|null
	 */
	public function match( string $source ): ?SourceAdapter {
		foreach ( $this->all() as $adapter ) {
			if ( $adapter->id() === $source ) {
				return $adapter;
			}
		}

		return null;
	}
}

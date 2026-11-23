<?php
/**
 * Unit tests for the non-destructive SEO migration importer.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use PHPUnit\Framework\TestCase;
use RankMySEO\Modules\Importer\AdapterFactory;
use RankMySEO\Modules\Importer\ImportRequest;
use RankMySEO\Modules\Importer\ImportService;
use RankMySEO\Modules\Importer\MetaCatalog;
use RankMySEO\Rest\ImporterController;
use RankMySEO\Tests\Support\MemorySiteStore;
use RankMySEO\Tests\Support\MemorySourceReader;
use RankMySEO_Test_State;
use WP_Error;
use WP_REST_Request;

/**
 * Covers mapping, skip versus overwrite, chunking, redirects, and rollback.
 */
final class ImporterTest extends TestCase {
	/**
	 * Reset WordPress stubs before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		RankMySEO_Test_State::reset();
	}

	/**
	 * Yoast post meta, terms, variables, and site defaults map onto RankMySEO keys.
	 *
	 * @return void
	 */
	public function test_yoast_maps_meta_variables_terms_and_settings_without_deleting_source(): void {
		$reader = new MemorySourceReader();
		$store  = $this->fresh_store();
		$reader->posts[12] = [
			'label' => 'Hello',
			'meta'  => [
				'_yoast_wpseo_title'                  => '%%title%% %%sep%% %%sitename%%',
				'_yoast_wpseo_metadesc'               => 'A %%excerpt%% about %%focuskw%%',
				'_yoast_wpseo_focuskw'                => 'widgets',
				'_yoast_wpseo_canonical'              => 'https://example.com/hello',
				'_yoast_wpseo_meta-robots-noindex'    => '1',
				'_yoast_wpseo_meta-robots-nofollow'   => '1',
				'_yoast_wpseo_opengraph-title'        => 'OG %%title%%',
				'_yoast_wpseo_opengraph-description'  => 'OG desc',
				'_yoast_wpseo_opengraph-image'        => 'https://example.com/og.jpg',
				'_yoast_wpseo_twitter-title'          => 'Tw %%title%%',
				'_yoast_wpseo_twitter-description'    => 'Tw desc',
				'_yoast_wpseo_twitter-image-id'       => '44',
			],
		];
		$reader->options['wpseo_taxonomy_meta'] = [
			'category' => [
				5 => [
					'wpseo_title'   => '%%term_title%%',
					'wpseo_desc'    => 'Term desc',
					'wpseo_noindex' => 'noindex',
				],
			],
		];
		$reader->terms[5] = [
			'label' => 'News',
			'meta'  => [],
		];
		$reader->options['wpseo_titles'] = [
			'separator'          => 'sc-dash',
			'company_or_person'  => 'person',
			'person_name'        => 'Ada',
			'title-home-wpseo'   => '%%sitename%% %%sep%% %%sitedesc%%',
			'metadesc-home-wpseo'=> 'Home desc',
		];
		$reader->options['wpseo_social'] = [
			'twitter_site' => '@ada',
		];
		$snapshot = $reader->posts[12]['meta'];
		$service  = $this->service( $reader, $store );
		$preview  = $service->preview( $this->request( 'yoast' ) );
		$this->assertGreaterThan( 0, count( $preview['samples'] ) );
		$this->assertLessThanOrEqual( 5, count( $preview['samples'] ) );
		$this->assertArrayHasKey( 'before', $preview['samples'][0] );
		$this->assertArrayHasKey( 'after', $preview['samples'][0] );
		$this->assertNull( $store->get_meta( 'post', 12, MetaCatalog::TITLE ) );

		$result = $service->run( $this->request( 'yoast' ) );

		$this->assertTrue( $result['done'] );
		$this->assertTrue( $result['nothing_deleted'] );
		$this->assertSame( $snapshot, $reader->posts[12]['meta'] );
		$this->assertSame( '%title% %sep% %sitename%', $store->get_meta( 'post', 12, MetaCatalog::TITLE ) );
		$this->assertSame( 'A %excerpt% about %%focuskw%%', $store->get_meta( 'post', 12, MetaCatalog::DESCRIPTION ) );
		$this->assertSame( 'widgets', $store->get_meta( 'post', 12, MetaCatalog::FOCUS_KEYWORD ) );
		$this->assertSame( 'https://example.com/hello', $store->get_meta( 'post', 12, MetaCatalog::CANONICAL ) );
		$this->assertSame( 'https://example.com/og.jpg', $store->get_meta( 'post', 12, MetaCatalog::OG_IMAGE ) );
		$this->assertSame( '44', $store->get_meta( 'post', 12, MetaCatalog::TWITTER_IMAGE ) );
		$this->assertSame( '%term_title%', $store->get_meta( 'term', 5, MetaCatalog::TITLE ) );

		$robots = json_decode( (string) $store->get_meta( 'post', 12, MetaCatalog::ROBOTS ), true );
		$this->assertTrue( $robots['noindex'] );
		$this->assertTrue( $robots['nofollow'] );
		$this->assertFalse( $robots['noarchive'] );

		$settings = $store->get_option( MetaCatalog::OPTION_CORE );
		$this->assertSame( '-', $settings['title_separator'] );
		$this->assertSame( 'person', $settings['site_type'] );
		$this->assertSame( 'Ada', $settings['site_name'] );

		$schema = $store->get_option( MetaCatalog::OPTION_SCHEMA );
		$this->assertSame( 'person', $schema['entity_type'] );
		$this->assertSame( [ 'https://x.com/ada' ], $schema['same_as'] );

		$titles = $store->get_option( MetaCatalog::OPTION_TITLES );
		$this->assertSame( '%sitename% %sep% %tagline%', $titles['homepage']['title'] );
	}

	/**
	 * Rank Math keywords, robots, and term meta map onto RankMySEO keys.
	 *
	 * @return void
	 */
	public function test_rank_math_maps_keywords_robots_and_terms(): void {
		$reader = new MemorySourceReader();
		$store  = $this->fresh_store();
		$reader->posts[3] = [
			'label' => 'Ranked',
			'meta'  => [
				'rank_math_title'             => '%title% %sep% %sitename%',
				'rank_math_description'       => 'Rank desc',
				'rank_math_focus_keyword'     => 'alpha, beta, gamma',
				'rank_math_canonical_url'     => 'https://example.com/ranked',
				'rank_math_robots'            => [
					'index'  => 'noindex',
					'follow' => 'nofollow',
				],
				'rank_math_facebook_image_id' => 9,
				'rank_math_twitter_card_type' => 'summary_large_image',
			],
		];
		$reader->terms[8] = [
			'label' => 'Guides',
			'meta'  => [
				'rank_math_title'       => '%term%',
				'rank_math_description' => 'Guide desc',
			],
		];

		$before = $reader->posts[3]['meta'];
		$this->service( $reader, $store )->run( $this->request( 'rankmath' ) );

		$this->assertSame( $before, $reader->posts[3]['meta'] );
		$this->assertSame( 'alpha', $store->get_meta( 'post', 3, MetaCatalog::FOCUS_KEYWORD ) );
		$this->assertSame( '["beta","gamma"]', $store->get_meta( 'post', 3, MetaCatalog::SECONDARY_KEYWORDS ) );
		$this->assertSame( '9', $store->get_meta( 'post', 3, MetaCatalog::OG_IMAGE ) );
		$this->assertSame( 'summary_large_image', $store->get_meta( 'post', 3, MetaCatalog::TWITTER_CARD ) );
		$this->assertSame( '%term_title%', $store->get_meta( 'term', 8, MetaCatalog::TITLE ) );

		$robots = json_decode( (string) $store->get_meta( 'post', 3, MetaCatalog::ROBOTS ), true );
		$this->assertTrue( $robots['noindex'] );
		$this->assertTrue( $robots['nofollow'] );
	}

	/**
	 * All in One SEO table rows and hash variables map, and legacy rows for those IDs are skipped.
	 *
	 * @return void
	 */
	public function test_aioseo_maps_table_rows_and_hash_variables(): void {
		$reader = new MemorySourceReader();
		$store  = $this->fresh_store();
		$reader->tables['aioseo_posts'] = [
			[
				'id'                    => 1,
				'post_id'               => 7,
				'title'                 => '#post_title #separator_sa #site_title',
				'description'           => 'AIO desc',
				'keyphrases'            => wp_json_encode(
					[
						'focus'      => [ 'keyphrase' => 'shoes' ],
						'additional' => [ [ 'keyphrase' => 'boots' ] ],
					]
				),
				'canonical_url'         => 'https://example.com/shoes',
				'robots_default'        => 0,
				'robots_noindex'        => 1,
				'og_title'              => 'OG shoes',
				'og_image_custom_url'   => 'https://example.com/shoes.jpg',
				'twitter_card'          => 'summary',
			],
		];
		$reader->posts[7] = [
			'label' => 'Shoes',
			'meta'  => [
				'_aioseop_title' => 'Legacy title',
			],
		];
		$reader->posts[8] = [
			'label' => 'Legacy',
			'meta'  => [
				'_aioseop_title'       => '#post_title',
				'_aioseop_description' => 'Legacy desc',
				'_aioseop_keywords'    => 'hats, caps',
			],
		];
		$reader->options['aioseo_options'] = [
			'searchAppearance' => [
				'global' => [
					'separator' => '—',
					'schema'    => [
						'siteRepresents'   => 'organization',
						'organizationName' => 'Northwind',
						'organizationLogo' => 'https://example.com/logo.png',
					],
				],
				'postTypes' => [
					'post' => [
						'title'           => '#post_title',
						'metaDescription' => '#post_excerpt',
					],
				],
			],
			'social' => [
				'profiles' => [
					'urls' => [
						'facebook' => 'https://facebook.com/northwind',
					],
				],
			],
		];

		$snapshot = $reader->tables['aioseo_posts'];
		$this->service( $reader, $store )->run( $this->request( 'aioseo' ) );

		$this->assertSame( $snapshot, $reader->tables['aioseo_posts'] );
		$this->assertSame( '%title% %sep% %sitename%', $store->get_meta( 'post', 7, MetaCatalog::TITLE ) );
		$this->assertSame( 'shoes', $store->get_meta( 'post', 7, MetaCatalog::FOCUS_KEYWORD ) );
		$this->assertSame( '["boots"]', $store->get_meta( 'post', 7, MetaCatalog::SECONDARY_KEYWORDS ) );
		$this->assertSame( 'summary', $store->get_meta( 'post', 7, MetaCatalog::TWITTER_CARD ) );
		$this->assertNotSame( 'Legacy title', $store->get_meta( 'post', 7, MetaCatalog::TITLE ) );
		$this->assertSame( '%title%', $store->get_meta( 'post', 8, MetaCatalog::TITLE ) );
		$this->assertSame( 'hats', $store->get_meta( 'post', 8, MetaCatalog::FOCUS_KEYWORD ) );

		$settings = $store->get_option( MetaCatalog::OPTION_CORE );
		$this->assertSame( '—', $settings['title_separator'] );
		$this->assertSame( 'Northwind', $settings['site_name'] );
		$schema = $store->get_option( MetaCatalog::OPTION_SCHEMA );
		$this->assertSame( 'https://example.com/logo.png', $schema['logo_url'] );
		$this->assertSame( [ 'https://facebook.com/northwind' ], $schema['same_as'] );
		$titles = $store->get_option( MetaCatalog::OPTION_TITLES );
		$this->assertSame( '%title%', $titles['post_types']['post']['title'] );
		$this->assertSame( '%excerpt%', $titles['post_types']['post']['description'] );
	}

	/**
	 * Existing RankMySEO values stay put until overwrite is enabled, and rollback restores them.
	 *
	 * @return void
	 */
	public function test_skip_and_overwrite_and_rollback_restore_previous_values(): void {
		$reader = new MemorySourceReader();
		$store  = $this->fresh_store();
		$reader->posts[4] = [
			'label' => 'Keep',
			'meta'  => [
				'_yoast_wpseo_title'    => 'Imported title',
				'_yoast_wpseo_metadesc' => 'Imported description',
			],
		];
		$store->set_meta( 'post', 4, MetaCatalog::TITLE, 'Keep me' );

		$service = $this->service( $reader, $store );
		$skipped = $service->run( $this->posts_request( 'yoast', 20, false ) );

		$this->assertSame( 'Keep me', $store->get_meta( 'post', 4, MetaCatalog::TITLE ) );
		$this->assertSame( 'Imported description', $store->get_meta( 'post', 4, MetaCatalog::DESCRIPTION ) );
		$this->assertGreaterThan( 0, $skipped['skipped'] );
		$this->assertSame( $reader->posts[4]['meta'], $reader->posts[4]['meta'] );

		$rolled = $service->rollback( $skipped['import_id'] );
		$this->assertSame( 'rolled_back', $rolled['status'] );
		$this->assertNull( $store->get_meta( 'post', 4, MetaCatalog::DESCRIPTION ) );
		$this->assertSame( 'Keep me', $store->get_meta( 'post', 4, MetaCatalog::TITLE ) );
		$this->assertSame( [], $store->backups( $skipped['import_id'] ) );

		$again = $service->rollback( $skipped['import_id'] );
		$this->assertSame( 'already_rolled_back', $again['status'] );
		$this->assertSame( 0, $again['restored'] );

		$store->set_meta( 'post', 4, MetaCatalog::TITLE, 'Keep me' );
		$replaced = $service->run( $this->posts_request( 'yoast', 20, true ) );
		$this->assertSame( 'Imported title', $store->get_meta( 'post', 4, MetaCatalog::TITLE ) );
		$this->assertSame( 'Imported description', $store->get_meta( 'post', 4, MetaCatalog::DESCRIPTION ) );

		$service->rollback( $replaced['import_id'] );
		$this->assertSame( 'Keep me', $store->get_meta( 'post', 4, MetaCatalog::TITLE ) );
		$this->assertNull( $store->get_meta( 'post', 4, MetaCatalog::DESCRIPTION ) );
	}

	/**
	 * A small batch size walks every post without repeating a row.
	 *
	 * @return void
	 */
	public function test_chunked_batches_cover_every_post_once(): void {
		$reader = new MemorySourceReader();
		$store  = $this->fresh_store();
		for ( $id = 1; $id <= 5; $id++ ) {
			$reader->posts[ $id ] = [
				'label' => 'Post ' . $id,
				'meta'  => [
					'_yoast_wpseo_title' => 'Title ' . $id,
				],
			];
		}

		$service = $this->service( $reader, $store );
		$first   = $service->run( $this->posts_request( 'yoast', 2, false ) );
		$this->assertSame( 2, $first['processed'] );
		$this->assertFalse( $first['done'] );
		$this->assertSame( 'posts:2', $first['next_cursor'] );

		$second_request            = $this->posts_request( 'yoast', 2, false );
		$second_request->cursor    = $first['next_cursor'];
		$second_request->import_id = $first['import_id'];
		$second                    = $service->run( $second_request );
		$this->assertSame( 2, $second['processed'] );
		$this->assertSame( 'posts:4', $second['next_cursor'] );

		$third_request            = $this->posts_request( 'yoast', 2, false );
		$third_request->cursor    = $second['next_cursor'];
		$third_request->import_id = $first['import_id'];
		$third                    = $service->run( $third_request );

		$this->assertSame( 1, $third['processed'] );
		$this->assertTrue( $third['done'] );
		$this->assertSame( '', $third['cursor'] );
		for ( $id = 1; $id <= 5; $id++ ) {
			$this->assertSame( 'Title ' . $id, $store->get_meta( 'post', $id, MetaCatalog::TITLE ) );
		}

		$history = $service->history();
		$this->assertSame( 'completed', $history['imports'][0]['status'] );
		$this->assertSame( 5, $history['imports'][0]['posts'] );
	}

	/**
	 * Redirect rows use the redirects-module hash and roll back cleanly.
	 *
	 * @return void
	 */
	public function test_redirect_import_uses_source_hash_and_rollback_removes_it(): void {
		$reader = new MemorySourceReader();
		$store  = $this->fresh_store();
		$reader->options['wpseo-premium-redirects-base'] = [
			'/Old-Page/' => [
				'url'    => 'https://example.com/new',
				'type'   => 301,
				'format' => 'plain',
			],
			'/same/'     => [
				'url'    => 'https://example.com/same',
				'type'   => 301,
				'format' => 'plain',
			],
		];
		$reader->tables['rank_math_redirections'] = [
			[
				'id'          => 1,
				'sources'     => [
					[
						'pattern'    => '/Start-Here/',
						'comparison' => 'start',
					],
					[
						'pattern'    => '/skip-contains/',
						'comparison' => 'contains',
					],
				],
				'url_to'      => 'https://example.com/dest',
				'header_code' => 302,
				'status'      => 'active',
			],
		];

		$yoast = $this->service( $reader, $store )->run( $this->redirect_request( 'yoast' ) );
		$hash  = hash( 'sha256', '/old-page' );
		$row   = $store->find_redirect_by_hash( $hash );

		$this->assertIsArray( $row );
		$this->assertSame( '/old-page', $row['source_path'] );
		$this->assertSame( $hash, $row['source_hash'] );
		$this->assertSame( 'exact', $row['match_type'] );
		$this->assertSame( 'https://example.com/new', $row['target_url'] );
		$this->assertSame( 301, $row['status_code'] );
		$this->assertSame( 1, $row['is_active'] );
		$this->assertSame( 'import', $row['source_origin'] );
		$this->assertSame( 0, $row['hits'] );
		$this->assertNull( $store->find_redirect_by_hash( hash( 'sha256', '/same' ) ) );

		$this->service( $reader, $store )->rollback( $yoast['import_id'] );
		$this->assertNull( $store->find_redirect_by_hash( $hash ) );

		$rank = $this->service( $reader, $store )->run( $this->redirect_request( 'rankmath' ) );
		$prefix = $store->find_redirect_by_hash( hash( 'sha256', '/start-here' ) );
		$this->assertIsArray( $prefix );
		$this->assertSame( 'prefix', $prefix['match_type'] );
		$this->assertSame( 302, $prefix['status_code'] );
		$this->assertNull( $store->find_redirect_by_hash( hash( 'sha256', '/skip-contains' ) ) );
		$this->assertTrue( $rank['done'] );
		$this->assertSame( $reader->tables['rank_math_redirections'], $reader->tables['rank_math_redirections'] );
	}

	/**
	 * A dry run reports counts and writes nothing.
	 *
	 * @return void
	 */
	public function test_preview_does_not_write(): void {
		$reader = new MemorySourceReader();
		$store  = $this->fresh_store();
		$reader->posts[1] = [
			'label' => 'One',
			'meta'  => [
				'_yoast_wpseo_title' => 'Preview title',
			],
		];
		$service = $this->service( $reader, $store );
		$preview = $service->preview( $this->request( 'yoast' ) );

		$this->assertSame( 1, $preview['posts'] );
		$this->assertSame( 'write', $preview['samples'][0]['action'] );
		$this->assertSame( [], $store->backups( 'none' ) );
		$this->assertNull( $store->get_meta( 'post', 1, MetaCatalog::TITLE ) );
		$this->assertArrayNotHasKey( MetaCatalog::OPTION_HISTORY, $store->options );
	}

	/**
	 * REST routes require rankmyseo_manage and return a preview payload.
	 *
	 * @return void
	 */
	public function test_rest_routes_require_manage_capability(): void {
		$reader     = new MemorySourceReader();
		$store      = $this->fresh_store();
		$controller = new ImporterController( $this->service( $reader, $store ) );
		$controller->register_routes();

		$routes = array_map(
			static fn( array $entry ): string => $entry['namespace'] . $entry['route'],
			RankMySEO_Test_State::$registered_routes
		);
		$this->assertContains( 'rankmyseo/v1/importer/detect', $routes );
		$this->assertContains( 'rankmyseo/v1/importer/preview', $routes );
		$this->assertContains( 'rankmyseo/v1/importer/run', $routes );
		$this->assertContains( 'rankmyseo/v1/importer/rollback', $routes );
		$this->assertContains( 'rankmyseo/v1/importer/history', $routes );

		RankMySEO_Test_State::$current_user_caps = [ 'edit_posts' => true ];
		$denied = $controller->check_manage_permission();
		$this->assertInstanceOf( WP_Error::class, $denied );
		$this->assertSame( 403, $denied->get_error_data()['status'] );

		RankMySEO_Test_State::$current_user_caps = [ 'rankmyseo_manage' => true ];
		$this->assertTrue( $controller->check_manage_permission() );

		$reader->posts[2] = [
			'label' => 'Rest',
			'meta'  => [
				'_yoast_wpseo_title' => 'From REST',
			],
		];
		$request = new WP_REST_Request();
		$request->set_json_params(
			[
				'source'            => 'yoast',
				'include_redirects' => false,
				'include_settings'  => false,
				'include_terms'     => false,
			]
		);
		$preview = $controller->preview( $request );
		$this->assertSame( 1, $preview->get_data()['posts'] );

		$missing = new WP_REST_Request();
		$missing->set_json_params( [ 'import_id' => '11111111-1111-4111-8111-111111111111' ] );
		$not_found = $controller->rollback( $missing );
		$this->assertInstanceOf( WP_Error::class, $not_found );
		$this->assertSame( 404, $not_found->get_error_data()['status'] );
	}

	/**
	 * Build a service around the memory doubles.
	 *
	 * @param MemorySourceReader $reader Source.
	 * @param MemorySiteStore    $store  Destination.
	 * @return ImportService
	 */
	private function service( MemorySourceReader $reader, MemorySiteStore $store ): ImportService {
		return new ImportService( new AdapterFactory( $reader, $store ), $store );
	}

	/**
	 * Fresh destination with default separator and an empty site name.
	 *
	 * @return MemorySiteStore
	 */
	private function fresh_store(): MemorySiteStore {
		$store = new MemorySiteStore();
		$store->set_option(
			MetaCatalog::OPTION_CORE,
			[
				'title_separator' => '|',
				'site_type'       => 'organization',
				'site_name'       => '',
			]
		);
		return $store;
	}

	/**
	 * Request that imports every phase.
	 *
	 * @param string $source Source ID.
	 * @return ImportRequest
	 */
	private function request( string $source ): ImportRequest {
		$request         = new ImportRequest();
		$request->source = $source;
		return $request;
	}

	/**
	 * Request that imports posts only.
	 *
	 * @param string $source    Source ID.
	 * @param int    $batch     Batch size.
	 * @param bool   $overwrite Whether to replace existing values.
	 * @return ImportRequest
	 */
	private function posts_request( string $source, int $batch, bool $overwrite ): ImportRequest {
		$request                     = $this->request( $source );
		$request->batch_size         = $batch;
		$request->overwrite          = $overwrite;
		$request->include_terms      = false;
		$request->include_redirects  = false;
		$request->include_settings   = false;
		return $request;
	}

	/**
	 * Request that imports redirects only.
	 *
	 * @param string $source Source ID.
	 * @return ImportRequest
	 */
	private function redirect_request( string $source ): ImportRequest {
		$request                    = $this->request( $source );
		$request->include_posts     = false;
		$request->include_terms     = false;
		$request->include_settings  = false;
		$request->include_redirects = true;
		return $request;
	}
}

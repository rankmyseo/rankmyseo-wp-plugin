<?php
/**
 * Visible breadcrumb and FAQ blocks, plus the breadcrumb shortcode.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Registers front-end blocks that render in PHP and do not load a script.
 */
final class ContentBlocks {
	/**
	 * Breadcrumb block name.
	 */
	public const BREADCRUMB_BLOCK = 'rankmyseo/breadcrumbs';

	/**
	 * Register blocks, the shortcode, and FAQ sync.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_filter( 'block_categories_all', [ $this, 'categories' ], 10, 1 );
		add_shortcode( 'rankmyseo_breadcrumbs', [ $this, 'shortcode' ] );
		( new FaqContent() )->register();
	}

	/**
	 * Add the RankMySEO block category.
	 *
	 * @param mixed $categories Existing categories.
	 * @return array<int, mixed>
	 */
	public function categories( $categories ): array {
		$list = is_array( $categories ) ? $categories : [];
		foreach ( $list as $category ) {
			if ( is_array( $category ) && isset( $category['slug'] ) && 'rankmyseo' === $category['slug'] ) {
				return $list;
			}
		}

		$list[] = [
			'slug'  => 'rankmyseo',
			'title' => __( 'RankMySEO', 'rankmyseo' ),
			'icon'  => null,
		];

		return $list;
	}

	/**
	 * Register dynamic blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			self::BREADCRUMB_BLOCK,
			[
				'api_version'     => '3',
				'attributes'      => [
					'separator'   => [
						'type'    => 'string',
						'default' => '/',
					],
					'showHome'    => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showCurrent' => [
						'type'    => 'boolean',
						'default' => true,
					],
				],
				'render_callback' => [ self::class, 'render_breadcrumbs' ],
			]
		);

		register_block_type(
			FaqContent::BLOCK,
			[
				'api_version'     => '3',
				'attributes'      => [
					'questions' => [
						'type'    => 'array',
						'default' => [],
					],
					'layout'    => [
						'type'    => 'string',
						'default' => 'details',
					],
				],
				'render_callback' => [ self::class, 'render_faq' ],
			]
		);
	}

	/**
	 * Render the breadcrumb block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_breadcrumbs( array $attributes ): string {
		return ( new BreadcrumbRenderer() )->html(
			( new BreadcrumbTrail() )->for_request(),
			[
				'separator'    => isset( $attributes['separator'] ) ? (string) $attributes['separator'] : '/',
				'show_home'    => ! array_key_exists( 'showHome', $attributes ) || self::flag( $attributes['showHome'] ),
				'show_current' => ! array_key_exists( 'showCurrent', $attributes ) || self::flag( $attributes['showCurrent'] ),
			]
		);
	}

	/**
	 * Render the FAQ block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render_faq( array $attributes ): string {
		return FaqContent::render( $attributes );
	}

	/**
	 * `[rankmyseo_breadcrumbs]` shortcode.
	 *
	 * @param mixed $attributes Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $attributes ): string {
		$attributes = is_array( $attributes ) ? $attributes : [];

		return ( new BreadcrumbRenderer() )->html(
			( new BreadcrumbTrail() )->for_request(),
			[
				'separator'    => isset( $attributes['separator'] ) ? (string) $attributes['separator'] : '/',
				'show_home'    => ! array_key_exists( 'show_home', $attributes ) || self::flag( $attributes['show_home'] ),
				'show_current' => ! array_key_exists( 'show_current', $attributes ) || self::flag( $attributes['show_current'] ),
			]
		);
	}

	/**
	 * Coerce a block or shortcode flag.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function flag( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), [ '0', 'false', 'no', 'off', '' ], true );
		}

		return (bool) $value;
	}
}

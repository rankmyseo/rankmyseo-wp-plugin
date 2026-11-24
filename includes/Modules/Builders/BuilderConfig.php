<?php
/**
 * Editor config shared by the Elementor and Divi panels.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Builders;

use RankMySEO\Core\Settings;
use RankMySEO\Modules\Meta\MetaStore;
use RankMySEO\Modules\Meta\TemplateDefaults;
use RankMySEO\Modules\Meta\TitleSettings;
use WP_Post;

/**
 * Localizes the same SEO context the block editor already receives.
 */
final class BuilderConfig {
	/**
	 * Core settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Title settings.
	 *
	 * @var TitleSettings
	 */
	private TitleSettings $titles;

	/**
	 * Meta store.
	 *
	 * @var MetaStore
	 */
	private MetaStore $store;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Core settings.
	 * @param TitleSettings $titles   Title settings.
	 * @param MetaStore     $store    Meta store.
	 */
	public function __construct( Settings $settings, TitleSettings $titles, MetaStore $store ) {
		$this->settings = $settings;
		$this->titles   = $titles;
		$this->store    = $store;
	}

	/**
	 * Config consumed by the builder React panels.
	 *
	 * @param int $post_id Post being edited. Zero omits saved fields.
	 * @return array<string, mixed>
	 */
	public function for_editor( int $post_id = 0 ): array {
		$rest = function_exists( 'rest_url' )
			? (string) rest_url( 'rankmyseo/v1' )
			: home_url( 'wp-json/rankmyseo/v1' );

		$config = [
			'uiMode'    => $this->settings->get_user_ui_mode( get_current_user_id() ),
			'siteName'  => $this->titles->site_name(),
			'siteUrl'   => (string) home_url( '/' ),
			'separator' => $this->titles->separator(),
			'tagline'   => $this->titles->tagline(),
			'variables' => TemplateDefaults::variable_catalog(),
			'restRoot'  => $rest,
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'locale'    => function_exists( 'get_locale' ) ? (string) get_locale() : 'en',
			'postId'    => 0,
			'postType'  => 'post',
			'postTitle' => '',
			'permalink' => '',
			'fields'    => [],
		];

		if ( $post_id <= 0 ) {
			return $config;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $config;
		}

		$permalink = get_permalink( $post );

		$config['postId']    = (int) $post->ID;
		$config['postType']  = (string) $post->post_type;
		$config['postTitle'] = (string) $post->post_title;
		$config['permalink'] = is_string( $permalink ) ? $permalink : '';
		$config['fields']    = $this->store->read_post( (int) $post->ID );

		return $config;
	}
}

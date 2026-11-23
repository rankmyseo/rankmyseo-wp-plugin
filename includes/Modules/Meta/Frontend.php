<?php
/**
 * Front-end title, robots, canonical, and social tag output.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Meta;

/**
 * Hooks document title, wp_robots, and wp_head without duplicate tags.
 */
final class Frontend {
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
	 * Document composer.
	 *
	 * @var DocumentBuilder
	 */
	private DocumentBuilder $builder;

	/**
	 * Conflict guard.
	 *
	 * @var ConflictGuard
	 */
	private ConflictGuard $conflicts;

	/**
	 * Query reader.
	 *
	 * @var QueryReader
	 */
	private QueryReader $reader;

	/**
	 * Constructor.
	 *
	 * @param TitleSettings   $titles    Title settings.
	 * @param MetaStore       $store     Meta store.
	 * @param DocumentBuilder $builder   Document composer.
	 * @param ConflictGuard   $conflicts Conflict guard.
	 * @param QueryReader     $reader    Query reader.
	 */
	public function __construct(
		TitleSettings $titles,
		MetaStore $store,
		DocumentBuilder $builder,
		ConflictGuard $conflicts,
		QueryReader $reader
	) {
		$this->titles    = $titles;
		$this->store     = $store;
		$this->builder   = $builder;
		$this->conflicts = $conflicts;
		$this->reader    = $reader;
	}

	/**
	 * Register front-end hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp', [ $this, 'replace_canonical' ] );
		add_filter( 'pre_get_document_title', [ $this, 'filter_document_title' ], 20 );
		add_filter( 'document_title_parts', [ $this, 'filter_title_parts' ], 20 );
		add_filter( 'wp_robots', [ $this, 'filter_robots' ], 20 );
		add_action( 'wp_head', [ $this, 'render_head' ], 1 );
	}

	/**
	 * Remove WordPress' canonical when this module prints its own.
	 *
	 * @return void
	 */
	public function replace_canonical(): void {
		if ( ! $this->should_output() ) {
			return;
		}

		if ( '' !== $this->current_document()->canonical ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}

	/**
	 * Supply the document title.
	 *
	 * @param string $title Title already chosen by WordPress.
	 * @return string
	 */
	public function filter_document_title( string $title ): string {
		if ( ! $this->should_output() ) {
			return $title;
		}

		$resolved = $this->current_title();
		return '' !== $resolved ? $resolved : $title;
	}

	/**
	 * Fallback for themes that read document_title_parts directly.
	 *
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public function filter_title_parts( array $parts ): array {
		if ( ! $this->should_output() ) {
			return $parts;
		}

		$resolved = $this->current_title();
		if ( '' === $resolved ) {
			return $parts;
		}

		$parts['title'] = $resolved;
		unset( $parts['site'], $parts['tagline'], $parts['page'] );

		return $parts;
	}

	/**
	 * Add RankMySEO robots directives without clearing a site-wide noindex.
	 *
	 * @param array<string, bool|string> $robots Existing directives.
	 * @return array<string, bool|string>
	 */
	public function filter_robots( array $robots ): array {
		if ( ! $this->should_output() ) {
			return $robots;
		}

		$snapshot = $this->reader->current();
		$resolved = $this->builder->robots( $snapshot, $this->meta_for( $snapshot ), $this->titles->all() );
		$public   = '0' !== (string) get_option( 'blog_public', '1' );

		return Robots::apply( $robots, $resolved, $public );
	}

	/**
	 * Print description, canonical, OpenGraph, and Twitter tags.
	 *
	 * @return void
	 */
	public function render_head(): void {
		if ( ! $this->should_output() ) {
			return;
		}

		$html = $this->current_document()->render();
		if ( '' === $html ) {
			return;
		}

		// Tags are escaped inside HeadDocument::render().
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Whether this request should receive RankMySEO head output.
	 *
	 * @return bool
	 */
	public function should_output(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( is_feed() || is_robots() || is_trackback() ) {
			return false;
		}

		$allowed = apply_filters( 'rankmyseo/meta/output_head', ! $this->conflicts->should_pause_head() ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		return (bool) $allowed;
	}

	/**
	 * Current title.
	 *
	 * @return string
	 */
	private function current_title(): string {
		$snapshot = $this->reader->current();
		return $this->builder->title(
			$snapshot,
			$this->meta_for( $snapshot ),
			$this->titles->all(),
			$this->titles->separator(),
			$this->titles->site_name(),
			$this->titles->tagline()
		);
	}

	/**
	 * Current document.
	 *
	 * @return HeadDocument
	 */
	private function current_document(): HeadDocument {
		$snapshot = $this->reader->current();
		return $this->builder->head(
			$snapshot,
			$this->meta_for( $snapshot ),
			$this->titles->all(),
			$this->titles->separator(),
			$this->titles->site_name(),
			$this->titles->tagline()
		);
	}

	/**
	 * Meta for.
	 *
	 * @param QuerySnapshot $snapshot Current query.
	 * @return array<string, mixed>
	 */
	private function meta_for( QuerySnapshot $snapshot ): array {
		if ( in_array( $snapshot->kind, [ 'singular', 'front_page' ], true ) && $snapshot->object_id > 0 ) {
			return $this->store->read_post( $snapshot->object_id );
		}

		if ( 'tax' === $snapshot->kind && $snapshot->object_id > 0 ) {
			return $this->store->read_term( $snapshot->object_id );
		}

		return [ 'robots' => null ];
	}
}

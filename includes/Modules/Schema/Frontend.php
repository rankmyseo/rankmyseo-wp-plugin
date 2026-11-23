<?php
/**
 * Front-end JSON-LD output.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Schema;

/**
 * Prints one JSON-LD script tag in the document head.
 */
final class Frontend {
	/**
	 * Context factory.
	 *
	 * @var ContextFactory
	 */
	private ContextFactory $contexts;

	/**
	 * Graph builder.
	 *
	 * @var GraphBuilder
	 */
	private GraphBuilder $graph;

	/**
	 * Script renderer.
	 *
	 * @var ScriptRenderer
	 */
	private ScriptRenderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param ContextFactory $contexts Context factory.
	 * @param GraphBuilder   $graph    Graph builder.
	 * @param ScriptRenderer $renderer Script renderer.
	 */
	public function __construct( ContextFactory $contexts, GraphBuilder $graph, ScriptRenderer $renderer ) {
		$this->contexts = $contexts;
		$this->graph    = $graph;
		$this->renderer = $renderer;
	}

	/**
	 * Print the JSON-LD script for the current request.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( $this->should_skip() ) {
			return;
		}

		$html = $this->markup();
		if ( '' === $html ) {
			return;
		}

		echo "\n" . $html . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD is encoded with wp_json_encode and closing script tags inside values are escaped.
	}

	/**
	 * Render the JSON-LD script without printing it.
	 *
	 * @return string
	 */
	public function markup(): string {
		$document = $this->graph->build( $this->contexts->for_request() );
		return $this->renderer->render( $document );
	}

	/**
	 * Skip admin, feeds, AJAX, cron, REST, and 404 responses.
	 *
	 * @return bool
	 */
	private function should_skip(): bool {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}
		if ( function_exists( 'is_404' ) && is_404() ) {
			return true;
		}

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}

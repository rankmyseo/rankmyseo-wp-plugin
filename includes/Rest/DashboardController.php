<?php
/**
 * REST controller for the dual-mode dashboard summary.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use RankMySEO\Core\OnboardingStore;
use RankMySEO\Database\Migrator;
use RankMySEO\Modules\Chat\LocalAssistant;
use RankMySEO\Modules\Chat\SiteHealth;
use RankMySEO\Modules\Chat\SiteIndex;
use RankMySEO\Modules\Links\LinkIndexer;
use RankMySEO\Modules\ModuleManager;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes GET /rankmyseo/v1/dashboard/summary.
 */
final class DashboardController extends AbstractController {
	/**
	 * Local assistant, reused for the shared health signals.
	 *
	 * @var LocalAssistant
	 */
	private LocalAssistant $assistant;

	/**
	 * Site index for the short fix lists.
	 *
	 * @var SiteIndex
	 */
	private SiteIndex $index;

	/**
	 * Onboarding store.
	 *
	 * @var OnboardingStore
	 */
	private OnboardingStore $onboarding;

	/**
	 * Module manager.
	 *
	 * @var ModuleManager
	 */
	private ModuleManager $modules;

	/**
	 * Database migrator.
	 *
	 * @var Migrator
	 */
	private Migrator $migrator;

	/**
	 * Local link index.
	 *
	 * @var LinkIndexer|null
	 */
	private ?LinkIndexer $links;

	/**
	 * Constructor.
	 *
	 * @param LocalAssistant   $assistant  Local assistant.
	 * @param SiteIndex        $index      Site index.
	 * @param OnboardingStore  $onboarding Onboarding store.
	 * @param ModuleManager    $modules    Module manager.
	 * @param Migrator         $migrator   Database migrator.
	 * @param LinkIndexer|null $links     Optional link index.
	 */
	public function __construct(
		LocalAssistant $assistant,
		SiteIndex $index,
		OnboardingStore $onboarding,
		ModuleManager $modules,
		Migrator $migrator,
		?LinkIndexer $links = null
	) {
		$this->assistant  = $assistant;
		$this->index      = $index;
		$this->onboarding = $onboarding;
		$this->modules    = $modules;
		$this->migrator   = $migrator;
		$this->links      = $links;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/dashboard/summary',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_summary' ],
					'permission_callback' => [ $this, 'check_manage_permission' ],
				],
			]
		);
	}

	/**
	 * Handle GET /rankmyseo/v1/dashboard/summary.
	 *
	 * @return WP_REST_Response
	 */
	public function get_summary(): WP_REST_Response {
		$signals   = $this->assistant->signals();
		$health    = SiteHealth::summarize( $signals );
		$redirects = $this->index->redirect_counts();
		$tables    = $this->migrator->verify_tables();

		return $this->success_response(
			[
				'health'               => $health,
				'blog_public'          => $signals['blog_public'],
				'onboarding_completed' => $this->onboarding->is_completed(),
				'counts'               => [
					'missing_titles'       => $signals['missing_titles'],
					'missing_descriptions' => $signals['missing_descriptions'],
					'thin_posts'           => $signals['thin_posts'],
					'low_scores'           => $signals['low_scores'],
					'open_404s'            => $signals['open_404s'],
					'orphans'              => (int) ( $signals['orphans'] ?? 0 ),
					'cornerstone'          => (int) ( $signals['cornerstones'] ?? 0 ),
					'redirects_total'      => (int) $redirects['total'],
					'redirects_active'     => (int) $redirects['active'],
				],
				'orphans'              => $this->links instanceof LinkIndexer ? $this->links->list_orphans( 5 ) : [],
				'fixes'                => $this->build_fixes( $signals ),
				'modules'              => $this->modules->get_catalog(),
				'audit'                => [
					'tables_healthy'   => $signals['tables_healthy'],
					'tables'           => $tables,
					'sitemaps_enabled' => $signals['sitemaps_enabled'],
				],
				'cloud'                => [
					'connected' => false,
					'tier'      => 'local',
				],
			]
		);
	}

	/**
	 * Build the top plain-English fixes for Simple mode.
	 *
	 * @param array<string, mixed> $signals Local signals.
	 * @return list<array<string, mixed>>
	 */
	private function build_fixes( array $signals ): array {
		$fixes = [];

		if ( ! $signals['onboarding_completed'] ) {
			$fixes[] = $this->fix(
				'onboarding',
				__( 'Finish guided setup', 'rankmyseo' ),
				__( 'Save your site name, brand, and which SEO tools stay on. It takes a minute and stays on this site.', 'rankmyseo' ),
				0,
				__( 'What should I fix first?', 'rankmyseo' ),
				'dashboard'
			);
		}

		if ( ! $signals['blog_public'] ) {
			$fixes[] = $this->fix(
				'blog_public',
				__( 'Allow search engines to index this site', 'rankmyseo' ),
				__( 'WordPress is set to discourage search engines. Uncheck that under Settings → Reading when the site is ready.', 'rankmyseo' ),
				1,
				__( 'How is my site SEO?', 'rankmyseo' ),
				'settings'
			);
		}

		if ( $signals['missing_descriptions'] > 0 ) {
			$fixes[] = $this->fix(
				'missing_descriptions',
				sprintf(
					/* translators: %d: number of published posts or pages. */
					_n(
						'%d published item is missing a search description',
						'%d published items are missing a search description',
						(int) $signals['missing_descriptions'],
						'rankmyseo'
					),
					$signals['missing_descriptions']
				),
				__( 'A short description under the title helps searchers decide to click.', 'rankmyseo' ),
				$signals['missing_descriptions'],
				__( 'Find posts missing meta descriptions', 'rankmyseo' ),
				'meta'
			);
		}

		if ( $signals['missing_titles'] > 0 ) {
			$fixes[] = $this->fix(
				'missing_titles',
				sprintf(
					/* translators: %d: number of published posts or pages. */
					_n(
						'%d published item is missing an SEO title',
						'%d published items are missing an SEO title',
						(int) $signals['missing_titles'],
						'rankmyseo'
					),
					$signals['missing_titles']
				),
				__( 'A custom SEO title can lead with the topic instead of repeating only the page name.', 'rankmyseo' ),
				$signals['missing_titles'],
				__( 'Find posts missing SEO titles', 'rankmyseo' ),
				'meta'
			);
		}

		if ( $signals['thin_posts'] > 0 ) {
			$fixes[] = $this->fix(
				'thin_posts',
				sprintf(
					/* translators: %d: number of thin published posts or pages. */
					_n(
						'%d published item looks thin',
						'%d published items look thin',
						(int) $signals['thin_posts'],
						'rankmyseo'
					),
					$signals['thin_posts']
				),
				__( 'Pages with very little text give searchers less to land on. Add a section that answers their question.', 'rankmyseo' ),
				$signals['thin_posts'],
				__( 'Show thin posts', 'rankmyseo' ),
				'meta'
			);
		}

		if ( $signals['open_404s'] > 0 ) {
			$fixes[] = $this->fix(
				'open_404s',
				sprintf(
					/* translators: %d: number of unresolved broken links. */
					_n(
						'%d broken link needs a redirect',
						'%d broken links need a redirect',
						(int) $signals['open_404s'],
						'rankmyseo'
					),
					$signals['open_404s']
				),
				__( 'Visitors hitting these addresses get a missing page. Send them to a live page instead.', 'rankmyseo' ),
				$signals['open_404s'],
				__( 'Show recent unresolved 404s', 'rankmyseo' ),
				'redirects'
			);
		}

		$orphans = (int) ( $signals['orphans'] ?? 0 );
		if ( $orphans > 0 ) {
			$fixes[] = $this->fix(
				'orphans',
				sprintf(
					/* translators: %d: number of published posts or pages with no incoming links. */
					_n(
						'%d published page has no incoming links',
						'%d published pages have no incoming links',
						$orphans,
						'rankmyseo'
					),
					$orphans
				),
				__( 'Other pages on this site do not link to these. A link from a related page helps visitors find them.', 'rankmyseo' ),
				$orphans,
				__( 'Show orphan pages', 'rankmyseo' ),
				'dashboard'
			);
		}

		if ( $signals['low_scores'] > 0 ) {
			$fixes[] = $this->fix(
				'low_scores',
				sprintf(
					/* translators: %d: number of low-scoring published items. */
					_n(
						'%d published item has a low SEO score',
						'%d published items have a low SEO score',
						(int) $signals['low_scores'],
						'rankmyseo'
					),
					$signals['low_scores']
				),
				__( 'Scores under 60 usually mean the title, description, or content still needs a pass.', 'rankmyseo' ),
				$signals['low_scores'],
				__( 'Show low SEO score posts', 'rankmyseo' ),
				'meta'
			);
		}

		if ( empty( $fixes ) ) {
			$fixes[] = $this->fix(
				'clear',
				__( 'No urgent on-page gaps', 'rankmyseo' ),
				__( 'Local checks did not find missing descriptions, thin pages, or open broken links.', 'rankmyseo' ),
				0,
				__( 'How is my site SEO?', 'rankmyseo' ),
				'dashboard'
			);
		}

		return array_slice( $fixes, 0, 5 );
	}

	/**
	 * Shape one fix card.
	 *
	 * @param string $id     Stable id.
	 * @param string $title  Plain title.
	 * @param string $detail One-line impact.
	 * @param int    $count  Affected count.
	 * @param string $prompt Ask AI prompt.
	 * @param string $page   Admin screen id.
	 * @return array<string, mixed>
	 */
	private function fix( string $id, string $title, string $detail, int $count, string $prompt, string $page ): array {
		return [
			'id'     => $id,
			'title'  => $title,
			'detail' => $detail,
			'count'  => $count,
			'prompt' => $prompt,
			'page'   => $page,
		];
	}
}

<?php
/**
 * Deterministic offline SEO assistant.
 *
 * Matches plain-English questions to local site queries. It never calls an
 * external service and never invents rankings, traffic, or volumes.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Chat;

use RankMySEO\Core\OnboardingStore;
use RankMySEO\Core\Settings;
use RankMySEO\Database\Migrator;

/**
 * Intent router for POST /rankmyseo/v1/chat/local.
 */
final class LocalAssistant {
	/**
	 * How many affected items to return in one reply.
	 */
	private const ITEM_LIMIT = 8;

	/**
	 * Local site index.
	 *
	 * @var SiteIndex
	 */
	private SiteIndex $index;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Onboarding store.
	 *
	 * @var OnboardingStore
	 */
	private OnboardingStore $onboarding;

	/**
	 * Database migrator, used for table health only.
	 *
	 * @var Migrator
	 */
	private Migrator $migrator;

	/**
	 * Constructor.
	 *
	 * @param SiteIndex       $index      Local site index.
	 * @param Settings        $settings   Settings repository.
	 * @param OnboardingStore $onboarding Onboarding store.
	 * @param Migrator        $migrator   Database migrator.
	 */
	public function __construct( SiteIndex $index, Settings $settings, OnboardingStore $onboarding, Migrator $migrator ) {
		$this->index      = $index;
		$this->settings   = $settings;
		$this->onboarding = $onboarding;
		$this->migrator   = $migrator;
	}

	/**
	 * Answer one local question.
	 *
	 * @param string               $message User question.
	 * @param array<string, mixed> $context Optional screen and post context.
	 * @return array<string, mixed>
	 */
	public function respond( string $message, array $context = [] ): array {
		$post_id = isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0;
		$intent  = $this->detect_intent( $message, $post_id );

		switch ( $intent ) {
			case 'missing_descriptions':
				return $this->reply_missing_descriptions();
			case 'missing_titles':
				return $this->reply_missing_titles();
			case 'low_scores':
				return $this->reply_low_scores();
			case 'thin_posts':
				return $this->reply_thin_posts();
			case 'open_404s':
				return $this->reply_404s();
			case 'redirects':
				return $this->reply_redirects();
			case 'module_health':
				return $this->reply_module_health();
			case 'inspect_post':
				return $this->reply_inspect_post( $message, $post_id );
			case 'cloud_handoff':
				return $this->reply_cloud_handoff();
			default:
				return $this->reply_site_health();
		}
	}

	/**
	 * Map a plain-English question to a local intent id.
	 *
	 * @param string $message User question.
	 * @param int    $post_id Optional post context.
	 * @return string
	 */
	public function detect_intent( string $message, int $post_id = 0 ): string {
		$normalized = strtolower( trim( $message ) );

		$mentions_description = str_contains( $normalized, 'description' ) || str_contains( $normalized, 'meta desc' );
		$mentions_title       = 1 === preg_match( '/\b(seo titles?|search titles?|titles?)\b/', $normalized );

		if ( $mentions_description && ! $mentions_title ) {
			return 'missing_descriptions';
		}

		if ( $mentions_title && ! $mentions_description && 1 === preg_match( '/\b(missing|without|empty|find|show|no)\b/', $normalized ) ) {
			return 'missing_titles';
		}

		if (
			str_contains( $normalized, 'low score' )
			|| str_contains( $normalized, 'seo score' )
			|| str_contains( $normalized, 'scores under' )
			|| str_contains( $normalized, 'score below' )
			|| str_contains( $normalized, 'poor score' )
		) {
			return 'low_scores';
		}

		if (
			str_contains( $normalized, 'thin' )
			|| str_contains( $normalized, 'too short' )
			|| str_contains( $normalized, 'short post' )
			|| str_contains( $normalized, 'word count' )
		) {
			return 'thin_posts';
		}

		if ( str_contains( $normalized, '404' ) || str_contains( $normalized, 'broken' ) || str_contains( $normalized, 'not found' ) ) {
			return 'open_404s';
		}

		if ( str_contains( $normalized, 'redirect' ) ) {
			return 'redirects';
		}

		if (
			str_contains( $normalized, 'sitemap' )
			|| str_contains( $normalized, 'module' )
			|| str_contains( $normalized, 'which tools' )
			|| str_contains( $normalized, 'indexing' )
		) {
			return 'module_health';
		}

		if (
			str_contains( $normalized, 'rewrite' )
			|| str_contains( $normalized, 'traffic' )
			|| str_contains( $normalized, 'competitor' )
			|| str_contains( $normalized, 'search console' )
			|| str_contains( $normalized, 'rank higher' )
			|| str_contains( $normalized, 'rankings' )
		) {
			return 'cloud_handoff';
		}

		if ( $this->is_post_inspection( $normalized, $post_id ) ) {
			return 'inspect_post';
		}

		return 'site_health';
	}

	/**
	 * Reply listing posts missing meta descriptions.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_missing_descriptions(): array {
		$count = $this->index->count_missing_descriptions();
		$rows  = $this->index->list_missing_descriptions( self::ITEM_LIMIT );
		$tip   = __( 'Write a plain search description of about 120–155 characters that says what the page offers.', 'rankmyseo' );

		$explanation = 0 === $count
			? __( 'Every published post and page already has a search description.', 'rankmyseo' )
			: sprintf(
				/* translators: %d: number of published posts or pages. */
				__( 'I found %d published items with no search description. Searchers are more likely to click when that short summary is written by you.', 'rankmyseo' ),
				$count
			);

		return $this->envelope( 'missing_descriptions', $explanation, $this->with_tip( $rows, $tip ) );
	}

	/**
	 * Reply listing posts missing SEO titles.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_missing_titles(): array {
		$count = $this->index->count_missing_titles();
		$rows  = $this->index->list_missing_titles( self::ITEM_LIMIT );
		$tip   = __( 'Add an SEO title that leads with the topic and stays near 50–60 characters.', 'rankmyseo' );

		$explanation = 0 === $count
			? __( 'Every published post and page already has a custom SEO title.', 'rankmyseo' )
			: sprintf(
				/* translators: %d: number of published posts or pages. */
				__( 'I found %d published items with no custom SEO title. The page name is being used on its own.', 'rankmyseo' ),
				$count
			);

		return $this->envelope( 'missing_titles', $explanation, $this->with_tip( $rows, $tip ) );
	}

	/**
	 * Reply listing posts under the SEO score threshold.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_low_scores(): array {
		$threshold = SiteHealth::LOW_SCORE_THRESHOLD;
		$count     = $this->index->count_low_scores( $threshold );
		$rows      = $this->index->list_low_scores( $threshold, self::ITEM_LIMIT );
		$tip       = sprintf(
			/* translators: %d: SEO score threshold. */
			__( 'Open the item and improve the checks that pulled the SEO score under %d.', 'rankmyseo' ),
			$threshold
		);

		$explanation = 0 === $count
			? sprintf(
				/* translators: %d: SEO score threshold. */
				__( 'No published item has an SEO score under %d.', 'rankmyseo' ),
				$threshold
			)
			: sprintf(
				/* translators: 1: number of items, 2: SEO score threshold. */
				__( 'I found %1$d published items with an SEO score under %2$d.', 'rankmyseo' ),
				$count,
				$threshold
			);

		return $this->envelope( 'low_scores', $explanation, $this->with_tip( $rows, $tip ) );
	}

	/**
	 * Reply listing thin posts.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_thin_posts(): array {
		$min_words = SiteHealth::THIN_WORD_COUNT;
		$count     = $this->index->count_thin_posts( $min_words );
		$rows      = $this->index->list_thin_posts( $min_words, self::ITEM_LIMIT );
		$tip       = sprintf(
			/* translators: %d: suggested minimum word count. */
			__( 'Add useful detail until the page is at least about %d words, with clear subheadings.', 'rankmyseo' ),
			$min_words
		);

		$explanation = 0 === $count
			? sprintf(
				/* translators: %d: word-count threshold. */
				__( 'No published post or page is under about %d words.', 'rankmyseo' ),
				$min_words
			)
			: sprintf(
				/* translators: 1: number of items, 2: word-count threshold. */
				__( 'I found %1$d published items under about %2$d words. Thin pages are harder for searchers to trust.', 'rankmyseo' ),
				$count,
				$min_words
			);

		return $this->envelope( 'thin_posts', $explanation, $this->with_tip( $rows, $tip ) );
	}

	/**
	 * Reply listing recent unresolved 404s.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_404s(): array {
		$count = $this->index->count_open_404s();
		$rows  = $this->index->list_open_404s( self::ITEM_LIMIT );
		$tip   = __( 'Send visitors to a live page by creating a redirect for this broken link.', 'rankmyseo' );

		$explanation = 0 === $count
			? __( 'There are no unresolved broken links in the local 404 log.', 'rankmyseo' )
			: sprintf(
				/* translators: %d: number of open 404 rows. */
				__( 'I found %d unresolved broken links. The newest ones are listed below.', 'rankmyseo' ),
				$count
			);

		return $this->envelope( 'open_404s', $explanation, $this->with_tip( $rows, $tip ) );
	}

	/**
	 * Reply with redirect totals.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_redirects(): array {
		$counts = $this->index->redirect_counts();
		$total  = (int) $counts['total'];
		$active = (int) $counts['active'];

		$explanation = sprintf(
			/* translators: 1: total redirect rules, 2: active redirect rules. */
			__( 'This site has %1$d redirect rules, and %2$d of them are active. Redirects are stored locally and are not sent anywhere.', 'rankmyseo' ),
			$total,
			$active
		);

		$items = [
			[
				'id'        => 0,
				'title'     => sprintf(
					/* translators: %d: number of active redirects. */
					__( '%d active redirects', 'rankmyseo' ),
					$active
				),
				'slug'      => 'redirects',
				'type'      => 'redirects',
				'edit_link' => '',
				'page'      => 'redirects',
				'tip'       => __( 'Open Redirects to review rules, hit counts, and broken links.', 'rankmyseo' ),
			],
		];

		return $this->envelope( 'redirects', $explanation, $items );
	}

	/**
	 * Reply describing module and sitemap health.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_module_health(): array {
		$labels = [
			'meta'      => __( 'Search titles and social previews', 'rankmyseo' ),
			'schema'    => __( 'Structured data', 'rankmyseo' ),
			'sitemaps'  => __( 'XML sitemap', 'rankmyseo' ),
			'redirects' => __( 'Redirects and broken-link monitor', 'rankmyseo' ),
		];

		$items = [];
		foreach ( $labels as $module_id => $label ) {
			$enabled = $this->settings->is_module_enabled( $module_id );
			$items[] = [
				'id'        => 0,
				'title'     => $label,
				'slug'      => $module_id,
				'type'      => 'module',
				'edit_link' => '',
				'page'      => $this->module_page( $module_id ),
				'tip'       => $enabled
					? __( 'Turned on.', 'rankmyseo' )
					: __( 'Turned off. You can enable it from Settings.', 'rankmyseo' ),
			];
		}

		$redirects = $this->index->redirect_counts();
		$open_404s = $this->index->count_open_404s();
		$sitemap   = $this->settings->is_module_enabled( 'sitemaps' )
			? __( 'The sitemap tool is on.', 'rankmyseo' )
			: __( 'The sitemap tool is off, so WordPress keeps its own default sitemap behavior.', 'rankmyseo' );

		$explanation = sprintf(
			/* translators: 1: sitemap sentence, 2: active redirect count, 3: open 404 count. */
			__( 'Module check is local only. %1$s There are %2$d active redirects and %3$d open broken links.', 'rankmyseo' ),
			$sitemap,
			(int) $redirects['active'],
			$open_404s
		);

		if ( ! $this->tables_healthy() ) {
			$explanation .= ' ' . __( 'Some plugin tables are missing. Re-save Settings or reactivate the plugin to repair them.', 'rankmyseo' );
		}

		return $this->envelope( 'module_health', $explanation, $items );
	}

	/**
	 * Reply for a single post looked up by ID, title, or slug.
	 *
	 * @param string $message Original question.
	 * @param int    $post_id Context post ID, when the editor supplied one.
	 * @return array<string, mixed>
	 */
	private function reply_inspect_post( string $message, int $post_id ): array {
		$lookup = $this->extract_lookup( $message, $post_id );
		$rows   = [];

		if ( $lookup['id'] > 0 ) {
			$post = $this->index->get_post( $lookup['id'] );
			if ( null !== $post ) {
				$rows = [ $post ];
			}
		} elseif ( '' !== $lookup['text'] ) {
			$rows = $this->index->find_posts( $lookup['text'], 5 );
		}

		if ( empty( $rows ) ) {
			return $this->envelope(
				'inspect_post',
				__( 'I could not find a post or page with that id, title, or slug. Try “inspect post 42” or the exact title in quotes.', 'rankmyseo' ),
				[]
			);
		}

		$items = [];
		foreach ( $rows as $row ) {
			$items[] = $this->inspection_item( $row );
		}

		$first       = $items[0];
		$explanation = sprintf(
			/* translators: 1: item title, 2: word count. */
			__( 'Local inspection for “%1$s”. It is about %2$d words. Nothing was changed.', 'rankmyseo' ),
			(string) $first['title'],
			isset( $first['word_count'] ) ? (int) $first['word_count'] : 0
		);

		return $this->envelope( 'inspect_post', $explanation, $items );
	}

	/**
	 * Explain what stays local when a question needs live rankings or rewriting.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_cloud_handoff(): array {
		return $this->envelope(
			'cloud_handoff',
			__( 'I can check titles, descriptions, thin pages, SEO scores, broken links, redirects, and module status on this site. Live rankings, traffic changes, and rewritten copy need an optional RankMySEO Cloud connection. No request was sent.', 'rankmyseo' ),
			[],
			[
				'title'   => __( 'Connect RankMySEO Cloud', 'rankmyseo' ),
				'message' => __( 'Cloud is optional. Local checks keep working if you skip it.', 'rankmyseo' ),
			]
		);
	}

	/**
	 * Summarize the highest-value local gaps.
	 *
	 * @return array<string, mixed>
	 */
	private function reply_site_health(): array {
		$signals = $this->signals();
		$health  = SiteHealth::summarize( $signals );

		$items = [];
		if ( $signals['missing_descriptions'] > 0 ) {
			$items = array_merge( $items, $this->with_tip( $this->index->list_missing_descriptions( 3 ), __( 'Add a search description.', 'rankmyseo' ) ) );
		}
		if ( $signals['open_404s'] > 0 ) {
			$items = array_merge( $items, $this->with_tip( $this->index->list_open_404s( 3 ), __( 'Create a redirect for this broken link.', 'rankmyseo' ) ) );
		}

		$explanation = sprintf(
			/* translators: 1: score from 0 to 100, 2: plain summary. */
			__( 'Local site health is %1$d/100. %2$s', 'rankmyseo' ),
			$health['score'],
			$health['summary']
		);

		return $this->envelope( 'site_health', $explanation, $items );
	}

	/**
	 * Collect the signals used by the health score.
	 *
	 * @return array{
	 *     blog_public: bool,
	 *     onboarding_completed: bool,
	 *     missing_descriptions: int,
	 *     missing_titles: int,
	 *     thin_posts: int,
	 *     low_scores: int,
	 *     open_404s: int,
	 *     sitemaps_enabled: bool,
	 *     tables_healthy: bool
	 * }
	 */
	public function signals(): array {
		return [
			'blog_public'          => '0' !== (string) get_option( 'blog_public', '1' ),
			'onboarding_completed' => $this->onboarding->is_completed(),
			'missing_descriptions' => $this->index->count_missing_descriptions(),
			'missing_titles'       => $this->index->count_missing_titles(),
			'thin_posts'           => $this->index->count_thin_posts( SiteHealth::THIN_WORD_COUNT ),
			'low_scores'           => $this->index->count_low_scores( SiteHealth::LOW_SCORE_THRESHOLD ),
			'open_404s'            => $this->index->count_open_404s(),
			'sitemaps_enabled'     => $this->settings->is_module_enabled( 'sitemaps' ),
			'tables_healthy'       => $this->tables_healthy(),
		];
	}

	/**
	 * Whether every custom table is present.
	 *
	 * @return bool
	 */
	private function tables_healthy(): bool {
		foreach ( $this->migrator->verify_tables() as $table ) {
			if ( empty( $table['exists'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the question is asking to inspect one post.
	 *
	 * @param string $normalized Lowercase message.
	 * @param int    $post_id    Context post ID.
	 * @return bool
	 */
	private function is_post_inspection( string $normalized, int $post_id ): bool {
		if ( 1 === preg_match( '/(?:post|page|id)\s*#?\s*\d+/', $normalized ) || 1 === preg_match( '/#\d+/', $normalized ) ) {
			return true;
		}

		if ( 1 === preg_match( '/\bslug:[a-z0-9\-]+/', $normalized ) ) {
			return true;
		}

		if ( 1 === preg_match( '/["“][^"”]+["”]/u', $normalized ) && 1 === preg_match( '/\b(inspect|check|review|look at|page|post)\b/', $normalized ) ) {
			return true;
		}

		if ( 1 === preg_match( '/\b(inspect|this page|this post|current page)\b/', $normalized ) ) {
			return true;
		}

		return $post_id > 0 && 1 === preg_match( '/\b(this page|this post|current page)\b/', $normalized );
	}

	/**
	 * Pull a post ID or title/slug out of a question.
	 *
	 * @param string $message Original message.
	 * @param int    $post_id Context post ID.
	 * @return array{id: int, text: string}
	 */
	private function extract_lookup( string $message, int $post_id ): array {
		if ( 1 === preg_match( '/(?:post|page|id)\s*#?\s*(\d+)/i', $message, $matches ) ) {
			return [
				'id'   => (int) $matches[1],
				'text' => '',
			];
		}

		if ( 1 === preg_match( '/#(\d+)/', $message, $matches ) ) {
			return [
				'id'   => (int) $matches[1],
				'text' => '',
			];
		}

		if ( 1 === preg_match( '/\bslug:([a-z0-9\-]+)/i', $message, $matches ) ) {
			return [
				'id'   => 0,
				'text' => sanitize_title( $matches[1] ),
			];
		}

		if ( 1 === preg_match( '/["“](.+?)["”]/u', $message, $matches ) ) {
			return [
				'id'   => 0,
				'text' => trim( $matches[1] ),
			];
		}

		if ( $post_id > 0 && 1 === preg_match( '/\b(this page|this post|current page)\b/i', $message ) ) {
			return [
				'id'   => $post_id,
				'text' => '',
			];
		}

		return [
			'id'   => 0,
			'text' => '',
		];
	}

	/**
	 * Build one inspection card with concrete tips from stored fields.
	 *
	 * @param array<string, mixed> $row Post row from the index.
	 * @return array<string, mixed>
	 */
	private function inspection_item( array $row ): array {
		$tips        = [];
		$description = isset( $row['seo_description'] ) ? trim( (string) $row['seo_description'] ) : '';
		$seo_title   = isset( $row['seo_title'] ) ? trim( (string) $row['seo_title'] ) : '';
		$score       = isset( $row['seo_score'] ) && is_numeric( $row['seo_score'] ) ? (int) $row['seo_score'] : null;
		$words       = isset( $row['word_count'] ) ? (int) $row['word_count'] : 0;

		if ( '' === $seo_title ) {
			$tips[] = __( 'Add a custom SEO title.', 'rankmyseo' );
		}
		if ( '' === $description ) {
			$tips[] = __( 'Add a search description.', 'rankmyseo' );
		}
		if ( null !== $score && $score < SiteHealth::LOW_SCORE_THRESHOLD ) {
			$tips[] = sprintf(
				/* translators: %d: stored SEO score. */
				__( 'The stored SEO score is %d. Start with the title and description.', 'rankmyseo' ),
				$score
			);
		}
		if ( $words < SiteHealth::THIN_WORD_COUNT ) {
			$tips[] = sprintf(
				/* translators: %d: word count. */
				__( 'The page is about %d words. Add a section that answers the searcher’s question.', 'rankmyseo' ),
				$words
			);
		}
		if ( empty( $tips ) ) {
			$tips[] = __( 'The stored SEO fields look complete. Open the editor if you want to tune them.', 'rankmyseo' );
		}

		$row['tip'] = implode( ' ', $tips );

		return $this->public_item( $row );
	}

	/**
	 * Attach one shared tip to each row.
	 *
	 * @param list<array<string, mixed>> $rows Index rows.
	 * @param string                     $tip  Actionable tip.
	 * @return list<array<string, mixed>>
	 */
	private function with_tip( array $rows, string $tip ): array {
		$items = [];
		foreach ( $rows as $row ) {
			$row['tip'] = isset( $row['tip'] ) && '' !== (string) $row['tip'] ? (string) $row['tip'] : $tip;
			$items[]    = $this->public_item( $row );
		}

		return $items;
	}

	/**
	 * Reduce an index row to the public chat item shape.
	 *
	 * @param array<string, mixed> $row Index row.
	 * @return array<string, mixed>
	 */
	private function public_item( array $row ): array {
		return [
			'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'title'      => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '',
			'slug'       => isset( $row['slug'] ) ? sanitize_title( (string) $row['slug'] ) : '',
			'type'       => isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'post',
			'edit_link'  => isset( $row['edit_link'] ) ? esc_url_raw( (string) $row['edit_link'] ) : '',
			'page'       => isset( $row['page'] ) ? sanitize_key( (string) $row['page'] ) : '',
			'tip'        => isset( $row['tip'] ) ? sanitize_text_field( (string) $row['tip'] ) : '',
			'seo_score'  => isset( $row['seo_score'] ) && is_numeric( $row['seo_score'] ) ? (int) $row['seo_score'] : null,
			'word_count' => isset( $row['word_count'] ) ? (int) $row['word_count'] : null,
			'hits'       => isset( $row['hits'] ) ? (int) $row['hits'] : null,
		];
	}

	/**
	 * Wrap a reply in the stable local-chat contract.
	 *
	 * Operator slot stays disabled until a later PR connects Operator chat.
	 * action_cards stays empty so inline approval cards can be attached later
	 * without changing this offline path.
	 *
	 * @param string                     $intent      Intent id.
	 * @param string                     $explanation Plain-English explanation.
	 * @param list<array<string, mixed>> $items      Affected items.
	 * @param array<string, string>|null $handoff    Optional cloud handoff card.
	 * @return array<string, mixed>
	 */
	private function envelope( string $intent, string $explanation, array $items, ?array $handoff = null ): array {
		return [
			'tier'          => 'local',
			'offline'       => true,
			'intent'        => $intent,
			'explanation'   => $explanation,
			'items'         => $items,
			'follow_ups'    => $this->follow_ups( $intent ),
			'cloud_handoff' => $handoff,
			'action_cards'  => [],
			'operator_slot' => [
				'enabled' => false,
				'mode'    => 'local',
			],
		];
	}

	/**
	 * Suggest up to three next questions that are not the current intent.
	 *
	 * @param string $current Current intent id.
	 * @return list<array{id: string, label: string, prompt: string}>
	 */
	private function follow_ups( string $current ): array {
		$all = [
			'missing_descriptions' => [
				'id'     => 'missing_descriptions',
				'label'  => __( 'Missing descriptions', 'rankmyseo' ),
				'prompt' => __( 'Find posts missing meta descriptions', 'rankmyseo' ),
			],
			'missing_titles'       => [
				'id'     => 'missing_titles',
				'label'  => __( 'Missing SEO titles', 'rankmyseo' ),
				'prompt' => __( 'Find posts missing SEO titles', 'rankmyseo' ),
			],
			'low_scores'           => [
				'id'     => 'low_scores',
				'label'  => __( 'Low SEO scores', 'rankmyseo' ),
				'prompt' => __( 'Show low SEO score posts', 'rankmyseo' ),
			],
			'thin_posts'           => [
				'id'     => 'thin_posts',
				'label'  => __( 'Thin posts', 'rankmyseo' ),
				'prompt' => __( 'Show thin posts', 'rankmyseo' ),
			],
			'open_404s'            => [
				'id'     => 'open_404s',
				'label'  => __( 'Broken links', 'rankmyseo' ),
				'prompt' => __( 'Show recent unresolved 404s', 'rankmyseo' ),
			],
			'redirects'            => [
				'id'     => 'redirects',
				'label'  => __( 'Redirect counts', 'rankmyseo' ),
				'prompt' => __( 'How many redirects do I have?', 'rankmyseo' ),
			],
			'module_health'        => [
				'id'     => 'module_health',
				'label'  => __( 'Sitemap and modules', 'rankmyseo' ),
				'prompt' => __( 'Check sitemap and module health', 'rankmyseo' ),
			],
		];

		unset( $all[ $current ] );

		return array_slice( array_values( $all ), 0, 3 );
	}

	/**
	 * Map a module id to an admin screen id.
	 *
	 * @param string $module_id Module id.
	 * @return string
	 */
	private function module_page( string $module_id ): string {
		$map = [
			'meta'      => 'meta',
			'schema'    => 'schema',
			'sitemaps'  => 'sitemaps',
			'redirects' => 'redirects',
		];

		return $map[ $module_id ] ?? 'settings';
	}
}

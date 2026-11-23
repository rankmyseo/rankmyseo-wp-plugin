<?php
/**
 * Transient cache for RankMySEO Cloud insights.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

/**
 * Caches keyword, issue, and page-update reads for 15 minutes.
 */
final class InsightsCache {
	/**
	 * Transient key. Not an autoloaded option.
	 */
	public const TRANSIENT = 'rankmyseo_cloud_insights';

	/**
	 * Cache lifetime in seconds.
	 */
	public const TTL = 900;

	/**
	 * Credential vault.
	 *
	 * @var TokenVault
	 */
	private TokenVault $vault;

	/**
	 * HTTP client.
	 *
	 * @var CloudClient
	 */
	private CloudClient $client;

	/**
	 * Constructor.
	 *
	 * @param TokenVault  $vault  Credential vault.
	 * @param CloudClient $client HTTP client.
	 */
	public function __construct( TokenVault $vault, CloudClient $client ) {
		$this->vault  = $vault;
		$this->client = $client;
	}

	/**
	 * Return insights. Disconnected sites never trigger HTTP.
	 *
	 * @param bool $refresh Bypass the cache when true.
	 * @return array<string, mixed>
	 */
	public function get( bool $refresh = false ): array {
		if ( ! $this->vault->is_connected() ) {
			return $this->empty_payload( false );
		}

		if ( ! $refresh ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				$cached['stale']  = false;
				return $cached;
			}
		}

		$result = $this->client->get_insights();
		if ( ! $result->ok ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				$cached['cached']  = true;
				$cached['stale']   = true;
				$cached['message'] = $result->message;
				return $cached;
			}
			$empty            = $this->empty_payload( true );
			$empty['message'] = $result->message;
			return $empty;
		}

		$payload = $this->normalize( $result->data );
		set_transient( self::TRANSIENT, $payload, self::TTL );
		return $payload;
	}

	/**
	 * Map the cloud insights body into the admin REST shape.
	 *
	 * @param array<string, mixed> $data Decoded JSON.
	 * @return array<string, mixed>
	 */
	private function normalize( array $data ): array {
		$project = null;
		if ( isset( $data['project'] ) && is_array( $data['project'] ) ) {
			$project = array(
				'id'     => $this->line( $data['project']['id'] ?? '', 64 ),
				'name'   => $this->line( $data['project']['name'] ?? '', 180 ),
				'domain' => $this->line( $data['project']['domain'] ?? '', 253 ),
			);
		}

		$keywords = isset( $data['keywords'] ) && is_array( $data['keywords'] ) ? $data['keywords'] : array();
		$rows     = array();
		$raw_rows = isset( $keywords['rows'] ) && is_array( $keywords['rows'] ) ? $keywords['rows'] : array();
		foreach ( $raw_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$position = $row['position'] ?? null;
			$rows[]   = array(
				'keyword'  => $this->line( $row['keyword'] ?? '', 200 ),
				'position' => is_numeric( $position ) ? (int) $position : null,
				'url'      => $this->url( $row['url'] ?? '' ),
			);
		}

		$issues = array();
		if ( isset( $data['issues'] ) && is_array( $data['issues'] ) ) {
			foreach ( $data['issues'] as $issue ) {
				if ( ! is_array( $issue ) ) {
					continue;
				}
				$issues[] = array(
					'id'       => $this->line( $issue['id'] ?? '', 80 ),
					'category' => $this->line( $issue['category'] ?? '', 80 ),
					'count'    => isset( $issue['count'] ) ? (int) $issue['count'] : 0,
					'impact'   => $this->line( $issue['impact'] ?? '', 80 ),
				);
			}
		}

		$page_updates = array();
		if ( isset( $data['pageUpdates'] ) && is_array( $data['pageUpdates'] ) ) {
			foreach ( $data['pageUpdates'] as $update ) {
				if ( ! is_array( $update ) ) {
					continue;
				}
				$page_updates[] = array(
					'url'     => $this->url( $update['url'] ?? '' ),
					'action'  => $this->line( $update['action'] ?? '', 40 ),
					'summary' => $this->line( $update['summary'] ?? '', 400 ),
				);
			}
		}

		return array(
			'connected'    => true,
			'stale'        => false,
			'cached'       => false,
			'fetched_at'   => gmdate( 'c' ),
			'message'      => '',
			'project'      => $project,
			'keywords'     => array(
				'tracked' => isset( $keywords['tracked'] ) ? (int) $keywords['tracked'] : count( $rows ),
				'rows'    => $rows,
			),
			'issues'       => $issues,
			'page_updates' => $page_updates,
		);
	}

	/**
	 * Empty insights payload.
	 *
	 * @param bool $connected Whether a connection exists but the read failed.
	 * @return array<string, mixed>
	 */
	private function empty_payload( bool $connected ): array {
		return array(
			'connected'    => $connected,
			'stale'        => false,
			'cached'       => false,
			'fetched_at'   => '',
			'message'      => '',
			'project'      => null,
			'keywords'     => array(
				'tracked' => 0,
				'rows'    => array(),
			),
			'issues'       => array(),
			'page_updates' => array(),
		);
	}

	/**
	 * Sanitize a single display line.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string
	 */
	private function line( $value, int $max ): string {
		$text = sanitize_text_field( wp_strip_all_tags( is_scalar( $value ) ? (string) $value : '' ) );
		if ( strlen( $text ) > $max ) {
			$text = substr( $text, 0, $max );
		}
		return $text;
	}

	/**
	 * Sanitize an optional URL.
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	private function url( $value ): string {
		$url = esc_url_raw( is_scalar( $value ) ? (string) $value : '' );
		return is_string( $url ) ? $url : '';
	}
}

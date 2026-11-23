<?php
/**
 * WordPress HTTP transport for IndexNow.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Posts IndexNow payloads with wp_safe_remote_post.
 */
final class WpIndexNowTransport implements IndexNowTransport {
	/**
	 * Submit one IndexNow payload over HTTP.
	 *
	 * @param array<string, mixed> $payload IndexNow JSON body.
	 * @return array{ok: bool, code: int, message: string}
	 */
	public function submit( array $payload ): array {
		$body = wp_json_encode( $payload );
		if ( ! is_string( $body ) || '' === $body ) {
			return [
				'ok'      => false,
				'code'    => 0,
				'message' => __( 'Could not encode the IndexNow payload.', 'rankmyseo' ),
			];
		}

		$response = wp_safe_remote_post(
			SitemapSettings::INDEXNOW_ENDPOINT,
			[
				'timeout' => 10,
				'headers' => [
					'Content-Type' => 'application/json; charset=utf-8',
				],
				'body'    => $body,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'ok'      => false,
				'code'    => 0,
				'message' => $response->get_error_message(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$ok   = $code >= 200 && $code < 300;

		return [
			'ok'      => $ok,
			'code'    => $code,
			'message' => $ok
				? __( 'IndexNow accepted the update.', 'rankmyseo' )
				: sprintf(
					/* translators: %d: HTTP status code returned by IndexNow. */
					__( 'IndexNow returned HTTP %d.', 'rankmyseo' ),
					$code
				),
		];
	}
}

<?php
/**
 * Shared REST nonce check for RankMySEO controllers.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Rest;

use WP_Error;
use WP_REST_Request;

/**
 * Re-checks an X-WP-Nonce header when a client sends one.
 *
 * Cookie-authenticated requests are already rejected by WordPress core when the
 * nonce is missing. This check covers clients that attach a nonce explicitly.
 */
trait RestNonce {
	/**
	 * Return an error when a provided REST nonce is invalid.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_Error|null
	 */
	protected function invalid_nonce( WP_REST_Request $request ): ?WP_Error {
		if ( ! function_exists( 'wp_verify_nonce' ) || ! method_exists( $request, 'get_header' ) ) {
			return null;
		}

		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! is_string( $nonce ) || '' === $nonce ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
		}

		if ( ! is_string( $nonce ) || '' === $nonce ) {
			return null;
		}

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return $this->error_response(
				'rankmyseo_invalid_nonce',
				__( 'The security nonce is invalid. Reload the page and try again.', 'rankmyseo' ),
				403
			);
		}

		return null;
	}
}

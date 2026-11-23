<?php
/**
 * Verifies signed deliveries from RankMySEO.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\AiActions;

/**
 * Checks X-Webhook-Signature with hash_equals.
 *
 * The header is `t=<unix>,v1=<hex>` and the signed text is `{t}.{rawBody}`.
 */
final class WebhookVerifier {
	/**
	 * How far a timestamp may drift, in seconds.
	 */
	public const MAX_SKEW = 300;

	/**
	 * Verify a signature against the decrypted webhook secret.
	 *
	 * @param string   $secret Webhook secret.
	 * @param string   $header Signature header.
	 * @param string   $body   Raw request body.
	 * @param int|null $now    Current unix time. Null uses time().
	 * @return bool
	 */
	public function verify( string $secret, string $header, string $body, ?int $now = null ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}
		if ( 1 !== preg_match( '/(?:^|,)t=(\d+),v1=([a-f0-9]{64})(?:,|$)/i', $header, $match ) ) {
			return false;
		}
		$timestamp = (int) $match[1];
		$provided  = strtolower( $match[2] );
		$current   = null === $now ? time() : $now;
		if ( abs( $current - $timestamp ) > self::MAX_SKEW ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
		return hash_equals( $expected, $provided );
	}
}

<?php
/**
 * Transport contract for IndexNow submissions.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Sitemaps;

/**
 * Sends one IndexNow JSON payload. Implementations perform the HTTP call.
 */
interface IndexNowTransport {
	/**
	 * Submit a payload to IndexNow.
	 *
	 * @param array<string, mixed> $payload IndexNow JSON body.
	 * @return array{ok: bool, code: int, message: string}
	 */
	public function submit( array $payload ): array;
}

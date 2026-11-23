<?php
/**
 * Result of one RankMySEO Cloud HTTP call.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Cloud;

/**
 * Transport outcome for a cloud request. Secrets stay in $data on the server only.
 */
final class CloudResult {
	/**
	 * Whether the HTTP call succeeded (2xx).
	 *
	 * @var bool
	 */
	public bool $ok = false;

	/**
	 * Whether an outbound HTTP request was attempted.
	 *
	 * @var bool
	 */
	public bool $attempted = false;

	/**
	 * HTTP status code, or 0 when no request was sent.
	 *
	 * @var int
	 */
	public int $status = 0;

	/**
	 * Machine-readable error code. Empty on success.
	 *
	 * @var string
	 */
	public string $error = '';

	/**
	 * Calm message safe to show to an administrator.
	 *
	 * @var string
	 */
	public string $message = '';

	/**
	 * Seconds to wait before retrying. Set for HTTP 429.
	 *
	 * @var int
	 */
	public int $retry_after = 0;

	/**
	 * Decoded JSON body. May contain a one-time token; never send this to the browser.
	 *
	 * @var array<string, mixed>
	 */
	public array $data = [];

	/**
	 * Build a result that did not touch the network.
	 *
	 * @param string $error   Machine-readable error code.
	 * @param string $message Human-readable message.
	 * @return self
	 */
	public static function local( string $error, string $message ): self {
		$result          = new self();
		$result->error   = $error;
		$result->message = $message;
		return $result;
	}
}

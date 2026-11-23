<?php
/**
 * Validation error for redirect rules.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

/**
 * Thrown when a redirect rule fails validation.
 */
final class RedirectException extends \RuntimeException {
	/**
	 * Machine-readable error code.
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * Constructor.
	 *
	 * @param string $error_code Machine-readable error code.
	 * @param string $message    Human-readable message.
	 */
	public function __construct( string $error_code, string $message ) {
		$this->error_code = $error_code;
		parent::__construct( $message );
	}

	/**
	 * Return the machine-readable error code.
	 *
	 * @return string
	 */
	public function error_code(): string {
		return $this->error_code;
	}
}

<?php
/**
 * Detects self-redirects, circular loops, and multi-hop chains.
 *
 * @package RankMySEO
 */

declare(strict_types=1);

namespace RankMySEO\Modules\Redirects;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Messages are returned to the REST layer, not echoed.

/**
 * Pure loop and chain inspector over an exact-match path map.
 */
final class LoopGuard {
	/**
	 * Error code for a rule whose source and target are the same path.
	 */
	public const SELF = 'redirect_self';

	/**
	 * Error code for a circular chain.
	 */
	public const LOOP = 'redirect_loop';

	/**
	 * Warning code for a chain that is allowed but longer than one hop.
	 */
	public const CHAIN = 'redirect_chain';

	/**
	 * Maximum hops followed before a chain is treated as a loop.
	 */
	private const MAX_HOPS = 10;

	/**
	 * Inspect a proposed exact redirect against existing exact rules.
	 *
	 * @param string                $source_path Normalized source path.
	 * @param string|null           $target_path Normalized target path, or null for 410/451.
	 * @param array<string, string> $exact_map   Active source path => target path map.
	 * @return array{ok: bool, code: string, chain: list<string>}
	 */
	public function inspect( string $source_path, ?string $target_path, array $exact_map ): array {
		if ( null === $target_path || '' === $target_path ) {
			return [
				'ok'    => true,
				'code'  => '',
				'chain' => [ $source_path ],
			];
		}

		if ( $source_path === $target_path ) {
			return [
				'ok'    => false,
				'code'  => self::SELF,
				'chain' => [ $source_path, $target_path ],
			];
		}

		$chain  = [ $source_path, $target_path ];
		$cursor = $target_path;
		$hops   = 0;

		while ( isset( $exact_map[ $cursor ] ) && $hops < self::MAX_HOPS ) {
			$next = $exact_map[ $cursor ];
			if ( $next === $source_path || in_array( $next, $chain, true ) ) {
				$chain[] = $next;
				return [
					'ok'    => false,
					'code'  => self::LOOP,
					'chain' => $chain,
				];
			}

			$chain[] = $next;
			$cursor  = $next;
			++$hops;
		}

		if ( $hops >= self::MAX_HOPS ) {
			return [
				'ok'    => false,
				'code'  => self::LOOP,
				'chain' => $chain,
			];
		}

		if ( count( $chain ) > 2 ) {
			return [
				'ok'    => true,
				'code'  => self::CHAIN,
				'chain' => $chain,
			];
		}

		return [
			'ok'    => true,
			'code'  => '',
			'chain' => $chain,
		];
	}

	/**
	 * Walk an exact chain and return the final path.
	 *
	 * @param string                $target_path Starting target path.
	 * @param array<string, string> $exact_map   Active source path => target path map.
	 * @return string
	 * @throws RedirectException When the chain loops.
	 */
	public function final_target( string $target_path, array $exact_map ): string {
		$seen   = [ $target_path ];
		$cursor = $target_path;
		$hops   = 0;

		while ( isset( $exact_map[ $cursor ] ) && $hops < self::MAX_HOPS ) {
			$next = $exact_map[ $cursor ];
			if ( in_array( $next, $seen, true ) ) {
				throw new RedirectException(
					self::LOOP,
					__( 'This redirect chain loops, so it cannot be flattened.', 'rankmyseo' )
				);
			}
			$seen[] = $next;
			$cursor = $next;
			++$hops;
		}

		return $cursor;
	}
}

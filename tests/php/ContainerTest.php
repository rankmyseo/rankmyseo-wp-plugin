<?php
/**
 * Unit tests for RankMySEO\Container.
 *
 * @package RankMySEO\Tests
 */

declare(strict_types=1);

namespace RankMySEO\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RankMySEO\Container;
use stdClass;

final class ContainerTest extends TestCase {
	public function test_singleton_returns_same_instance(): void {
		$container = new Container();
		$calls     = 0;

		$container->singleton(
			'service',
			static function () use ( &$calls ): stdClass {
				++$calls;
				return new stdClass();
			}
		);

		$first  = $container->get( 'service' );
		$second = $container->get( 'service' );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $calls );
		$this->assertTrue( $container->has( 'service' ) );
	}

	public function test_bind_creates_fresh_instance_per_call(): void {
		$container = new Container();
		$container->bind( 'transient', static fn(): stdClass => new stdClass() );

		$first  = $container->get( 'transient' );
		$second = $container->get( 'transient' );

		$this->assertNotSame( $first, $second );
	}

	public function test_set_stores_explicit_instance_and_keys_lists_all(): void {
		$container = new Container();
		$obj       = new stdClass();

		$container->set( 'manual', $obj );
		$container->singleton( 'lazy', static fn(): stdClass => new stdClass() );

		$this->assertSame( $obj, $container->get( 'manual' ) );
		$this->assertSame( [ 'lazy', 'manual' ], $container->keys() );
	}

	public function test_get_throws_for_unknown_service(): void {
		$container = new Container();

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Service "missing" is not registered' );
		$container->get( 'missing' );
	}
}

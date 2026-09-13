<?php

namespace MediaWiki\Extension\MathSearch\Tests\Rest;

use MediaWiki\Extension\MathSearch\Rest\FormulaHash;
use MediaWiki\Extension\MathSearch\Rest\FormulaStore;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \MediaWiki\Extension\MathSearch\Rest\FormulaStore
 */
class FormulaStoreTest extends MediaWikiUnitTestCase {

	/** @param string|false $field what the single-row lookup returns */
	private function newStore( $field ): FormulaStore {
		$builder = $this->createMock( SelectQueryBuilder::class );
		foreach ( [ 'select', 'from', 'where', 'caller' ] as $method ) {
			$builder->method( $method )->willReturnSelf();
		}
		$builder->method( 'fetchField' )->willReturn( $field );

		$database = $this->createMock( IReadableDatabase::class );
		$database->method( 'newSelectQueryBuilder' )->willReturn( $builder );

		$provider = $this->createMock( IConnectionProvider::class );
		$provider->method( 'getReplicaDatabase' )->willReturn( $database );
		return new FormulaStore( $provider );
	}

	public function testResolvesTheStoredPreimage() {
		$store = $this->newStore( FormulaHash::preimage( 'E=mc^{2}' ) );
		$this->assertSame(
			[ 'q' => 'E=mc^{2}', 'type' => 'tex' ],
			$store->resolve( FormulaHash::fromTex( 'E=mc^{2}' ) )
		);
	}

	/** A malformed address is rejected before the database is asked. */
	public function testRejectsAMalformedAddress() {
		$provider = $this->createNoOpMock( IConnectionProvider::class );
		$this->assertNull( ( new FormulaStore( $provider ) )->resolve( 'nonsense' ) );
	}

	public function testReturnsNullForAnUnknownAddress() {
		$store = $this->newStore( false );
		$this->assertNull( $store->resolve( FormulaHash::fromTex( 'never stored' ) ) );
	}

	/** A row that is not the JSON preimage must not be handed on as a formula. */
	public function testReturnsNullForAnUnusableRow() {
		$this->assertNull(
			$this->newStore( 'not json' )->resolve( FormulaHash::fromTex( 'x' ) )
		);
		$this->assertNull(
			$this->newStore( '{"q":"x"}' )->resolve( FormulaHash::fromTex( 'x' ) )
		);
	}

	/** What is written is the preimage, keyed by the sha1 of exactly those bytes. */
	public function testRemembersThePreimageUnderItsOwnHash() {
		$written = [];
		$builder = $this->createMock( InsertQueryBuilder::class );
		foreach ( [ 'insertInto', 'ignore', 'caller' ] as $method ) {
			$builder->method( $method )->willReturnSelf();
		}
		$builder->method( 'row' )->willReturnCallback(
			static function ( array $row ) use ( &$written, $builder ) {
				$written = $row;
				return $builder;
			}
		);

		$database = $this->createMock( IDatabase::class );
		$database->method( 'newInsertQueryBuilder' )->willReturn( $builder );
		$provider = $this->createMock( IConnectionProvider::class );
		$provider->method( 'getPrimaryDatabase' )->willReturn( $database );

		$hash = ( new FormulaStore( $provider ) )->remember( 'E=mc^{2}' );

		$this->assertSame( FormulaHash::fromTex( 'E=mc^{2}' ), $hash );
		$this->assertSame( '{"q":"E=mc^{2}","type":"tex"}', $written['math_rest_input'] );
		$this->assertSame( $hash, $written['math_rest_hash'] );
		$this->assertSame( $hash, sha1( $written['math_rest_input'] ) );
	}
}

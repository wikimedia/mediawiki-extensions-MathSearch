<?php

namespace MediaWiki\Extension\MathSearch\StackExchange;

use MediaWikiUnitTestCase;

class FieldTest extends MediaWikiUnitTestCase {

// protected function setUp(): void {
//		parent::setUp();
//
//		// WikibaseRepo service getters should never access the database or do http requests
//		// https://phabricator.wikimedia.org/T243729
//		$this->disallowDBAccess();
//		$this->disallowHttpAccess();
//	}
//
//	private function disallowDBAccess() {
//		$this->setService(
//			'DBLoadBalancerFactory',
//			function() {
//				$lb = $this->createMock( ILoadBalancer::class );
//				$lb->expects( $this->never() )
//					->method( 'getConnection' );
//				$lb->expects( $this->never() )
//					->method( 'getConnectionRef' );
//				$lb->expects( $this->never() )
//					->method( 'getMaintenanceConnectionRef' );
//				$lb->expects( $this->any() )
//					->method( 'getLocalDomainID' )
//					->willReturn( 'banana' );
//
//				$lbFactory = $this->createMock( LBFactory::class );
//				$lbFactory->expects( $this->any() )
//					->method( 'getMainLB' )
//					->willReturn( $lb );
//
//				return $lbFactory;
//			}
//		);
//	}
//
//	private function disallowHttpAccess() {
//		$this->setService(
//			'HttpRequestFactory',
//			function() {
//				$factory = $this->createMock( HttpRequestFactory::class );
//				$factory->expects( $this->never() )
//					->method( 'create' );
//				$factory->expects( $this->never() )
//					->method( 'request' );
//				$factory->expects( $this->never() )
//					->method( 'get' );
//				$factory->expects( $this->never() )
//					->method( 'post' );
//				return $factory;
//			}
//		);
//	}

	/**
	 * @covers MediaWiki\Extension\MathSearch\StackExchange\Field::isKnown
	 */
	public function testIsKnown() {
		$f = new Field( 'unknown', '', '' );
		$this->assertFalse( $f->isKnown() );
		$f = new Field( 'Id', 'ignore', 'Posts.xml' );
		$this->assertTrue( $f->isKnown() );
	}

	/**
	 * @covers MediaWiki\Extension\MathSearch\StackExchange\Field::getContent
	 */
	public function testGetContent() {
		$f = new Field( 'ignore', 'content', 'ignore' );
		$this->assertSame( 'content', $f->getContent() );
	}

// /**
//	 * @covers \MathSearch\StackExchange\Field::getContent
//	 */
//	public function testGetSnak() {
//		$f = new Field( 'Id', '1', 'posts' );
//		$this->assertSame( 'wikibase-item', $f->getSnak()->getType());
//	}
}

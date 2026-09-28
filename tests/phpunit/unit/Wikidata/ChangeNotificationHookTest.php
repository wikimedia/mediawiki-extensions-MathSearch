<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit\Wikidata;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\MathSearch\Wikidata\ChangeNotificationHook;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWikiUnitTestCase;
use RuntimeException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Lib\Changes\EntityChange;
use Wikibase\Lib\Changes\EntityDiffChangedAspects;
use Wikibase\Repo\Hooks\WikibaseChangeNotificationHook;

/**
 * @covers \MediaWiki\Extension\MathSearch\Wikidata\ChangeNotificationHook
 */
class ChangeNotificationHookTest extends MediaWikiUnitTestCase {
	protected function setUp(): void {
		parent::setUp();
		if ( !interface_exists( WikibaseChangeNotificationHook::class ) ) {
			$this->markTestSkipped( 'Wikibase is required for this test' );
		}
	}

	/**
	 * @return IJobSpecification[]
	 */
	private function runHook(
		array $labelChanges, array $statementChanges, ?EntityId $id = null
	): array {
		$id ??= new ItemId( 'Q42' );
		$pushed = [];
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->method( 'lazyPush' )->willReturnCallback(
			static function ( $job ) use ( &$pushed ) {
				$pushed[] = $job;
			}
		);
		$change = $this->createMock( EntityChange::class );
		$change->method( 'getEntityId' )->willReturn( $id );
		$change->method( 'getObjectId' )->willReturn( $id->getSerialization() );
		$change->method( 'getMetadata' )->willReturn( [ 'user_text' => 'Alice' ] );
		$change->method( 'getCompactDiff' )->willReturn(
			new EntityDiffChangedAspects( $labelChanges, [], [], $statementChanges, [], [], false )
		);
		$hook = new ChangeNotificationHook(
			new HashConfig( [ 'MathSearchPropertyProfileType' => 'P1460' ] ),
			$jobQueueGroup
		);
		$hook->onWikibaseChangeNotification( $change );
		return $pushed;
	}

	public function testProfileTypeChange(): void {
		$jobs = $this->runHook( [], [ 'P1460' ] );
		$this->assertCount( 1, $jobs );
		$this->assertSame( 'CreateProfilePages', $jobs[0]->getType() );
		$this->assertArrayNotHasKey( 'variable_prefix', $jobs[0]->getParams() );
	}

	public function testEnglishLabelChange(): void {
		$jobs = $this->runHook( [ 'en' ], [] );
		$this->assertCount( 1, $jobs );
		$this->assertSame( [ 'Q42' ], $jobs[0]->getParams()['rows'] );
		$this->assertTrue( $jobs[0]->getParams()['variable_prefix'] );
	}

	public function testLabelAndProfileTypeChangeQueueOneJob(): void {
		$this->assertCount( 1, $this->runHook( [ 'en' ], [ 'P1460' ] ) );
	}

	public function testSwallowsExceptions(): void {
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->method( 'lazyPush' )->willThrowException( new RuntimeException( 'queue down' ) );
		$change = $this->createMock( EntityChange::class );
		$change->method( 'getEntityId' )->willReturn( new ItemId( 'Q42' ) );
		$change->method( 'getObjectId' )->willReturn( 'Q42' );
		$change->method( 'getMetadata' )->willReturn( [ 'user_text' => 'Alice' ] );
		$change->method( 'getCompactDiff' )->willReturn(
			new EntityDiffChangedAspects( [ 'en' ], [], [], [], [], [], false )
		);
		$hook = new ChangeNotificationHook(
			new HashConfig( [ 'MathSearchPropertyProfileType' => 'P1460' ] ),
			$jobQueueGroup
		);
		$hook->onWikibaseChangeNotification( $change );
		$this->addToAssertionCount( 1 );
	}

	public function testIgnoresNonItemEntities(): void {
		$lexemeId = $this->createMock( EntityId::class );
		$lexemeId->method( 'getSerialization' )->willReturn( 'L19' );
		$this->assertSame( [], $this->runHook( [ 'en' ], [ 'P1460' ], $lexemeId ) );
		$this->assertSame( [], $this->runHook( [ 'en' ], [], new NumericPropertyId( 'P31' ) ) );
	}

	public function testIgnoresOtherChanges(): void {
		$this->assertSame( [], $this->runHook( [ 'de' ], [ 'P31' ] ) );
	}
}

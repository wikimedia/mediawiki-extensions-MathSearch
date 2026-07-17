<?php

namespace MediaWiki\Extension\MathSearch\Tests\Unit\Wikidata;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\MathSearch\Wikidata\ProtectUpstreamEntitiesHook;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\Repo\Content\EntityContent;

/**
 * @covers \MediaWiki\Extension\MathSearch\Wikidata\ProtectUpstreamEntitiesHook
 */
class ProtectUpstreamEntitiesHookTest extends MediaWikiUnitTestCase {
	private function newHook( array $minimumEditableIds = [ 'P' => 0, 'Q' => 0 ] ): ProtectUpstreamEntitiesHook {
		return new ProtectUpstreamEntitiesHook( new HashConfig( [
			'MathSearchMinimumEditableIds' => $minimumEditableIds,
			'MathSearchWikidataUrl' => 'https://wikidata.org',
		] ) );
	}

	public function testAllowsEditableEntity(): void {
		$status = Status::newGood();
		$result = $this->newHook()->onEditFilterMergedContent(
			$this->createMock( IContextSource::class ),
			$this->newItemContentStub( 'Q10000000' ),
			$status,
			'',
			$this->createMock( User::class ),
			false
		);

		$this->assertTrue( $result );
		$this->assertTrue( $status->isOK() );
	}

	public function testAllowsEntityWhenProtectionDisabled(): void {
		$status = Status::newGood();
		$result = $this->newHook()->onEditFilterMergedContent(
			$this->createMock( IContextSource::class ),
			$this->newItemContentStub( 'Q42' ),
			$status,
			'',
			$this->createMock( User::class ),
			false
		);

		$this->assertTrue( $result );
		$this->assertTrue( $status->isOK() );
	}

	public function testBlocksProtectedItemWhenEnabled(): void {
		$hook = $this->newHook( [ 'Q' => 10000000 ] );
		$status = Status::newGood();
		$result = $hook->onEditFilterMergedContent(
			$this->createMock( IContextSource::class ),
			$this->newItemContentStub( 'Q42' ),
			$status,
			'',
			$this->createMock( User::class ),
			false
		);

		$this->assertTrue( $result );
		$this->assertFalse( $status->isOK() );
		$messages = $status->getMessages( 'error' );
		$this->assertCount( 1, $messages );
		$this->assertSame( 'mathsearch-protected-entity-edit', $messages[0]->getKey() );
		$this->assertSame(
			[ 'Item', 'Q42', 10000000, 'https://wikidata.org/wiki/Item:Q42' ],
			array_map( static fn ( $param ) => $param->getValue(), $messages[0]->getParams() )
		);
	}

	public function testBlocksProtectedPropertyWhenEnabled(): void {
		$hook = $this->newHook( [ 'P' => 1000000 ] );
		$status = Status::newGood();
		$result = $hook->onEditFilterMergedContent(
			$this->createMock( IContextSource::class ),
			$this->newPropertyContentStub( 'P42' ),
			$status,
			'',
			$this->createMock( User::class ),
			false
		);

		$this->assertTrue( $result );
		$this->assertFalse( $status->isOK() );
		$messages = $status->getMessages( 'error' );
		$this->assertCount( 1, $messages );
		$this->assertSame( 'mathsearch-protected-entity-edit', $messages[0]->getKey() );
		$this->assertSame(
			[ 'Property', 'P42', 1000000, 'https://wikidata.org/wiki/Property:P42' ],
			array_map( static fn ( $param ) => $param->getValue(), $messages[0]->getParams() )
		);
	}

	public function testIgnoresForwardCompatibleEntityTypes(): void {
		$status = Status::newGood();
		$result = $this->newHook( [ 'P' => 1000000, 'Q' => 10000000 ] )->onEditFilterMergedContent(
			$this->createMock( IContextSource::class ),
			$this->newEntityContentStub( $this->newEntityDocumentStub( 'L42' ) ),
			$status,
			'',
			$this->createMock( User::class ),
			false
		);

		$this->assertTrue( $result );
		$this->assertTrue( $status->isOK() );
	}

	private function newItemContentStub( string $entityId ): EntityContent {
		$item = new Item();
		$item->setId( new ItemId( $entityId ) );

		$content = $this->createMock( EntityContent::class );
		$content->method( 'isRedirect' )->willReturn( false );
		$content->method( 'getEntity' )->willReturn( $item );
		return $content;
	}

	private function newPropertyContentStub( string $entityId ): EntityContent {
		$property = Property::newFromType( 'string' );
		$property->setId( new NumericPropertyId( $entityId ) );

		$content = $this->createMock( EntityContent::class );
		$content->method( 'isRedirect' )->willReturn( false );
		$content->method( 'getEntity' )->willReturn( $property );
		return $content;
	}

	private function newEntityContentStub( EntityDocument $entity ): EntityContent {
		$content = $this->createMock( EntityContent::class );
		$content->method( 'isRedirect' )->willReturn( false );
		$content->method( 'getEntity' )->willReturn( $entity );
		return $content;
	}

	private function newEntityDocumentStub( string $serialization ): EntityDocument {
		$entity = $this->createMock( EntityDocument::class );
		$id = $this->createMock( EntityId::class );
		$id->method( 'getSerialization' )->willReturn( $serialization );
		$entity->method( 'getId' )->willReturn( $id );
		return $entity;
	}
}

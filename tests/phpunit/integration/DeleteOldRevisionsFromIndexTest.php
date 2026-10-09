<?php

namespace MediaWiki\Extension\MathSearch\Tests\Integration;

require_once dirname( __DIR__, 3 ) . '/maintenance/DeleteOldRevisionsFromIndex.php';

use DeleteOldRevisionsFromIndex;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \DeleteOldRevisionsFromIndex
 * @group Database
 */
class DeleteOldRevisionsFromIndexTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return DeleteOldRevisionsFromIndex::class;
	}

	public function testOnlyCurrentRevisionsStay() {
		$this->overrideConfigValues( [ 'MathValidModes' => [ 'source', 'native' ] ] );
		$this->editPage( 'A', '<math>x</math>' );
		$this->editPage( 'A', '<math>w</math>' );
		$current = $this->editPage( 'A', '<math>y</math>' )->getNewRevision()->getId();
		$other = $this->editPage( 'B', '<math>z</math>' )->getNewRevision()->getId();
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'mathindex' )
			->row( [
				'mathindex_revision_id' => $other + 1,
				'mathindex_anchor' => 'math.0',
				'mathindex_inputhash' => 'h',
			] )
			->caller( __METHOD__ )
			->execute();

		$this->maintenance->loadWithArgv( [ '--batch-size', '1' ] );
		$this->maintenance->execute();

		$this->newSelectQueryBuilder()
			->select( 'mathindex_revision_id' )
			->from( 'mathindex' )
			->orderBy( 'mathindex_revision_id' )
			->assertResultSet( [ [ $current ], [ $other ] ] );
		$this->expectOutputString( "3 revisions to delete from mathindex.\n" .
			"Deleted 1 of 3 revisions.\nDeleted 2 of 3 revisions.\nDeleted 3 of 3 revisions.\n" );
	}
}

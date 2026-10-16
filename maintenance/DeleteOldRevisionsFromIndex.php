<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @ingroup Maintenance
 */

use MediaWiki\Maintenance\Maintenance;
use Wikimedia\Rdbms\SelectQueryBuilder;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class DeleteOldRevisionsFromIndex extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Deletes the formulae of revisions that are not the current one of a page from mathindex.' );
		$this->setBatchSize( 1000 );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$dbw = $this->getPrimaryDB();
		$total = (int)$this->newOldRevisionsQuery()
			->select( 'COUNT(DISTINCT mathindex_revision_id)' )
			->caller( __METHOD__ )
			->fetchField();
		$this->output( "$total revisions to delete from mathindex.\n" );
		$deleted = 0;
		do {
			$revIds = $this->newOldRevisionsQuery()
				->select( 'mathindex_revision_id' )
				->distinct()
				->limit( $this->getBatchSize() )
				->caller( __METHOD__ )
				->fetchFieldValues();
			if ( $revIds ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'mathindex' )
					->where( [ 'mathindex_revision_id' => $revIds ] )
					->caller( __METHOD__ )
					->execute();
				$deleted += count( $revIds );
				$this->output( "Deleted $deleted of $total revisions.\n" );
				$this->waitForReplication();
			}
		} while ( count( $revIds ) === $this->getBatchSize() );
	}

	private function newOldRevisionsQuery(): SelectQueryBuilder {
		return $this->getPrimaryDB()->newSelectQueryBuilder()
			->from( 'mathindex' )
			// page_latest has no index
			->leftJoin( 'revision', null, 'rev_id = mathindex_revision_id' )
			->leftJoin( 'page', null, [ 'page_id = rev_page', 'page_latest = mathindex_revision_id' ] )
			->where( [ 'page_id' => null ] );
	}
}

$maintClass = DeleteOldRevisionsFromIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;

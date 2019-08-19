<?php
/**
 *
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

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class GenerateFeatureTable extends Maintenance {
	const RTI_CHUNK_SIZE = 100000;
	public $purge = false;
	/** @var \Wikimedia\Rdbms\IDatabase */
	public $dbw = null;

	/**
	 * @var \Wikimedia\Rdbms\IDatabase
	 */
	private $db;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Outputs page text to stdout' );
		$this->addOption(
			'purge', "If set all formulae are rendered again from strech. (Very time consuming!)",
			false, false, "f"
		);
		$this->addArg( 'min', "If set processing is started at the page with rank(pageID)>min", false );
		$this->addArg( 'max', "If set processing is stopped at the page with rank(pageID)<=max", false );
		$this->requireExtension( 'MathSearch' );
	}

	/**
	 * Populates the search index with content from all pages
	 *
	 * @param int $n
	 * @param int $cmax
	 */
	protected function populateSearchIndex( $n = 0, $cmax = - 1 ) {
		$res = $this->db->select( 'page', 'MAX(page_id) AS count' );
		$s = $this->db->fetchObject( $res );
		$count = $s->count;
		if ( $cmax > 0 && $count > $cmax ) {
			$count = $cmax;
		}
		# $this->output( "Rebuilding index fields for {$count} pages with option {$this->purge}...\n" );
		$fcount = 0;

		while ( $n < $count ) {
			if ( $n ) {
				$this->output( $n . " of $count \n" );
			}
			$end = $n + self::RTI_CHUNK_SIZE - 1;

			$res =
				$this->db->select( [ 'page', 'revision', 'text' ], [ 'page_id' ], [
						"page_id BETWEEN $n AND $end",
						'page_latest = rev_id',
						'rev_text_id = old_id'
				], __METHOD__ );
			$this->dbw->begin( __METHOD__ );
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			foreach ( $res as $s ) {
				// $revtext = Revision::getRevisionText( $s );
				$fcount += $this->doUpdate( $s->page_id );
			}
			$this->dbw->commit( __METHOD__ );
			$n += self::RTI_CHUNK_SIZE;
		}
		// $this->output( "Updated {$fcount} formulae!\n" );
	}

	/**
	 * @param $pid
	 *
	 * @return number
	 * @internal param unknown $pId
	 * @internal param unknown $pText
	 * @internal param string $pTitle
	 * @internal param string $purge
	 */
	private function doUpdate( $pid ) {
		// TODO: fix link id problem
		$res =
			$this->db->select( [ 'mathrevisionstat', 'mathvarstat' ], [
					'revstat_revid',
					'pagestat_featurename',
					'pagestat_featuretype',
					'revstat_featurecount',
					'varstat_id',
					'varstat_featurecount'
			], [
					'revstat_revid' => $pid,
					'pagestat_featurename = varstat_featurename',
					'pagestat_featuretype=varstat_featuretype'
			], __METHOD__ );
		foreach ( $res as $row ) {
			$this->output( $pid . ',' . $row->varstat_id . ',' . $row->pagestat_featurecount
						   /// $row->varstat_featurecount
						   .
						   "\n" );// .';'.$row->pagestat_featuretype.utf8_decode($row->pagestat_featurename)."\n");
		}
		return 0;
	}

	/**
	 *
	 */
	public function execute() {
		$this->dbw = wfGetDB( DB_MASTER );
		$this->purge = $this->getOption( "purge", false );
		$this->db = wfGetDB( DB_MASTER );
		$this->populateSearchIndex( $this->getArg( 0, 0 ), $this->getArg( 1, - 1 ) );
	}
}

$maintClass = "GenerateFeatureTable";
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

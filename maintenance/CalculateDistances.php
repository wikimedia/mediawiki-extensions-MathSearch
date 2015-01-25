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

require_once( __DIR__ . '/../../../maintenance/Maintenance.php' );

/**
 * Class CalculateDistances
 */
class CalculateDistances extends Maintenance {
	const RTI_CHUNK_SIZE = 100;
	/**@var DatabaseBase $dbw */
	public $dbw = null;

	/**
	 * @var DatabaseBase
	 */
	private $db;
	private $pagelist = array();

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Outputs page text to stdout';
		$this->addOption( 'page9', 'Ignore pages with only 9 equations or less.', false, false,
			'9' );
		$this->addArg( 'min', 'If set processing is started at the page with curid>min', false );
		$this->addArg( 'max', 'If set processing is stopped at the page with curid<=max', false );
	}

	/**
	 *
	 */
	public function execute() {
		$this->dbw = wfGetDB( DB_MASTER );
		$this->db = wfGetDB( DB_MASTER );
		$this->pagelist = array();
		$min = $this->getArg( 0, 0 );
		$max = $this->getArg( 1, PHP_INT_MAX );
		$conds = "pagestat_pageid >= $min";
		if ( $max < PHP_INT_MAX ) {
			$conds .= " AND pagestat_pageid <= $max";
		}
		if ( $this->getOption( 'page9', false ) ) {
			$res =
				$this->db->select( array( 'mathpage9', 'mathpagestat' ),
					array( 'page_id', 'pagestat_pageid' ),
					$conds . ' AND pagestat_pageid = page_id', __METHOD__, array( 'DISTINCT' ) );
		} else {
			$res =
				$this->db->select( 'mathpagestat', 'pagestat_pageid', $conds, __METHOD__,
					array( 'DISTINCT' ) );
		}
		foreach ( $res as $row ) {
			array_push( $this->pagelist, $row->pagestat_pageid );
		}
		$this->populateSearchIndex();
		$this->output( "Done.\n" );
	}

	/**
	 * Populates the search index with content from all pages
	 */
	protected function populateSearchIndex() {
		$n = 0;
		$count = sizeof( $this->pagelist );
		$this->output( "Rebuilding index fields for $count pages...\n" );
		while ( $n < $count ) {
			if ( $n ) {
				$this->output( $n . " of $count \n" );
			}
			$this->dbw->begin();
			for ( $j = 0; $j < self::RTI_CHUNK_SIZE; $j ++ ) {
				//TODO: USE PREPARED STATEMENTS
				$pid = $this->pagelist[$n];
				$sql =
					"INSERT IGNORE INTO mathpagesimilarity(pagesimilarity_A,pagesimilarity_B,pagesimilarity_Value)\n" .
					"SELECT DISTINCT $pid,`pagestat_pageid`,\n" .
					"CosProd( $pid,`pagestat_pageid`) FROM `mathpagestat` m ";
				if ( $this->getOption( 'page9', false ) ) {
					$sql .= " JOIN (SELECT page_id from mathpage9) as r WHERE m.pagestat_pageid=r.page_id AND ";
				} else {
					$sql .= " WHERE ";
				}
				$sql .= "m.pagestat_pageid < $pid ";
				echo "writing entries for page $pid...";
				$start = microtime( true );
				$this->dbw->query( $sql );
				echo 'done in ' . ( microtime( true ) - $start ) . "\n";
				$n ++;
			}
			$start = microtime( true );
			$this->dbw->commit();
			echo ' committed in ' . ( microtime( true ) - $start ) . "s\n\n";
		}
	}
}

$maintClass = 'CalculateDistances';
/** @noinspection PhpIncludeInspection */
require_once( RUN_MAINTENANCE_IF_MAIN );

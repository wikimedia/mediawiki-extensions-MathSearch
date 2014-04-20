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

require_once( dirname( __FILE__ ) . '/../../../maintenance/Maintenance.php' );

class CalculateDistances extends Maintenance {
	const RTI_CHUNK_SIZE = 1;
	var $dbw = null;

	/**
	 * @var DatabaseBase
	 */
	private $db;
	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Outputs page text to stdout';
		$this->addOption( 'limit', 'Only the pages with the most features are used. Default 2000', false, true, "l" );
		$this->addArg( 'min', "If set processing is started at the page with rank(pageID)>min", false );
		$this->addArg( 'max', "If set processing is stopped at the page with rank(pageID)<=max", false );
	}
	/**
	 * Populates the search index with content from all pages
	 */
	protected function populateSearchIndex( $n = 0, $cmax = -1 ) {
		$res = $this->db->select( 'page', 'MAX(page_id) AS count' );
		$s = $this->db->fetchObject( $res );
		$count = $s->count;
		if ( $cmax > 0 && $count > $cmax ) {
			$count = $cmax;
		}
		$this->output( "Rebuilding index fields for {$count} pages with option {$this->purge}...\n" );
		$fcount = 0;

		while ( $n < $count ) {
			if ( $n ) {
				$this->output( $n . " of $count \n" );
			}
			$end = $n + self::RTI_CHUNK_SIZE - 1;

			$res = $this->db->selectField( 'mathpagestat', 'pagestat_pageid', "pagestat_pageid=$n" );
			if ( $res ) {
				$this->dbw->begin();
				$fcount += self::doUpdate( $res, $this->dbw , $this->getOption( 'limit', 2000 ) );
			$start = microtime( true );
			$this->dbw->commit();
			echo " committed in " . ( microtime( true ) -$start ) . "s\n\n";
			}
			$n += self::RTI_CHUNK_SIZE;
		}
	}
	/**
	 * @param unknown $pId
	 * @param unknown $pText
	 * @param string $pTitle
	 * @param string $purge
	 * @return number
	 */
	private static function doUpdate( $pid  , $dbw, $limit ) {
		// TODO: fix link id problem
		$sql = "INSERT IGNORE INTO mathpagesimilarity(pagesimilarity_A,pagesimilarity_B,pagesimilarity_Value)\n"
				. "SELECT DISTINCT '.$pid.',`pagestat_pageid`,\n"
				. "CosProd('.$pid.',`pagestat_pageid`)\n"
				. "FROM `mathpagestat` m JOIN "
				. "(SELECT `pagestat_pageid` as pageid FROM `mathpagestat` GROUP BY `pagestat_pageid` "
				. "ORDER BY sum(`pagestat_featurecount`) DESC LIMIT $limit ) as r WHERE m.pagestat_pageid < $pid"
		 		. " AND m.pagestat_pageid=r.pageid";
		echo "writing entries for page $pid...";
		$start = microtime( true );
		$dbw->query( $sql );
		echo 'done in ' . ( microtime( true ) -$start ) . "\n";
		return 1;
	}
	/**
	 *
	 */
	public function execute() {
		$this->dbw = wfGetDB( DB_MASTER );
		$this->db = wfGetDB( DB_MASTER );
		$this->output( "Done.\n" );
		$this->populateSearchIndex( $this->getArg( 0, 0 ), $this->getArg( 1, -1 ) );
	}
}

$maintClass = "CalculateDistances";
require_once( RUN_MAINTENANCE_IF_MAIN );

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

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class CalculateDistances extends Maintenance {

	private const RTI_CHUNK_SIZE = 100;

	/** @var \Wikimedia\Rdbms\IDatabase */
	private $dbw = null;

	/**
	 * @var \Wikimedia\Rdbms\IDatabase
	 */
	private $db;
	/** @var int[] List of revision ids */
	private $pagelist = [];

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Outputs page text to stdout' );
		$this->addOption( 'page9', 'Ignore pages with only 9 equations or less.', false, false,
			'9' );
		$this->addArg( 'min', 'If set processing is started at the page with curid>min', false );
		$this->addArg( 'max', 'If set processing is stopped at the page with curid<=max', false );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$this->dbw = wfGetDB( DB_MASTER );
		$this->db = wfGetDB( DB_MASTER );
		$this->pagelist = [];
		$min = $this->getArg( 0, 0 );
		$max = $this->getArg( 1, PHP_INT_MAX );
		$conds = "revstat_revid >= $min";
		if ( $max < PHP_INT_MAX ) {
			$conds .= " AND revstat_revid <= $max";
		}
		if ( $this->getOption( 'page9', false ) ) {
			$res =
				$this->db->select( [ 'mathpage9', 'mathrevisionstat' ],
					[ 'page_id', 'revstat_revid' ],
					$conds . ' AND revstat_revid = page_id', __METHOD__, [ 'DISTINCT' ] );
		} else {
			$res =
				$this->db->select( 'mathrevisionstat', 'revstat_revid', $conds, __METHOD__,
					[ 'DISTINCT' ] );
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
		$count = count( $this->pagelist );
		$this->output( "Rebuilding index fields for $count pages...\n" );
		while ( $n < $count ) {
			if ( $n ) {
				$this->output( $n . " of $count \n" );
			}
			$this->dbw->begin( __METHOD__ );
			for ( $j = 0; $j < self::RTI_CHUNK_SIZE; $j++ ) {
				// TODO: USE PREPARED STATEMENTS
				$pid = $this->pagelist[$n];
				$sql =
					"INSERT IGNORE INTO mathpagesimilarity(pagesimilarity_A,pagesimilarity_B,pagesimilarity_Value) " .
					"SELECT DISTINCT $pid,`revstat_revid`, " .
					"CosProd( $pid,`revstat_revid`) FROM `mathrevisionstat` m ";
				if ( $this->getOption( 'page9', false ) ) {
					$sql .= " JOIN (SELECT page_id from mathpage9) as r WHERE m.revstat_revid=r.page_id AND ";
				} else {
					$sql .= " WHERE ";
				}
				$sql .= "m.revstat_revid < $pid ";
				echo "writing entries for page $pid...";
				$start = microtime( true );
				$this->dbw->query( $sql );
				echo 'done in ' . ( microtime( true ) - $start ) . "\n";
				$n++;
			}
			$start = microtime( true );
			$this->dbw->commit( __METHOD__ );
			echo ' committed in ' . ( microtime( true ) - $start ) . "s\n\n";
		}
	}
}

$maintClass = 'CalculateDistances';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

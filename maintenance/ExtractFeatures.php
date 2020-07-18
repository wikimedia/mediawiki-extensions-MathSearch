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

use MediaWiki\MediaWikiServices;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

/**
 * Class ExtractFeatures
 */
class ExtractFeatures extends Maintenance {
	const RTI_CHUNK_SIZE = 100;
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
		$this->addOption( 'purge',
			'If set all formulae are rendered again from strech. (Very time consuming!)', false,
			false, 'f' );
		$this->addArg( 'min', 'If set processing is started at the page with rank(pageID)>min',
			false );
		$this->addArg( 'max', 'If set processing is stopped at the page with rank(pageID)<=max',
			false );
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
		$this->output( "Rebuilding index fields for {$count} pages with option {$this->purge}...\n" );
		$fcount = 0;
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();

		while ( $n < $count ) {
			if ( $n ) {
				$this->output( $n . " of $count \n" );
			}
			$end = $n + self::RTI_CHUNK_SIZE - 1;

			$res =
				$this->db->select( [ 'page', 'revision', 'text' ],
					[ 'page_id', 'page_namespace', 'page_title', 'old_flags', 'old_text' ],
					[
						"page_id BETWEEN $n AND $end",
						'page_latest = rev_id',
						'rev_text_id = old_id'
					], __METHOD__ );
			$this->dbw->begin( __METHOD__ );
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			foreach ( $res as $s ) {
				$revtext = $revisionStore->newRevisionFromRow( $s );
				$fcount += self::doUpdate( $revtext, $s->page_title, $this->purge,
					$this->dbw );
			}
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$start = microtime( true );
			$this->dbw->commit( __METHOD__ );
			echo " committed in " . ( microtime( true ) - $start ) . "s\n\n";
			// echo "after" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$n += self::RTI_CHUNK_SIZE;
		}
		$this->output( "Clear mathvarstat\n" );
		$sql = 'DELETE FROM `mathvarstat`';
		$this->dbw->query( $sql );
		$this->output( "Generate mathvarstat\n" );
		// @codingStandardsIgnoreStart
		$sql =
			'INSERT INTO `mathvarstat` (`varstat_featurename` , `varstat_featuretype`, `varstat_featurecount`)' .
			'SELECT `mathobservation_featurename` , `mathobservation_featuretype` , count( * ) AS CNT ' .
			'FROM `mathobservation` ' .
			'JOIN mathindex ON `mathobservation_inputhash` = mathindex_inputhash ' .
			'GROUP BY `mathobservation_featurename` , `mathobservation_featuretype` ' .
			'ORDER BY CNT DESC';
		// @codingStandardsIgnoreEnd
		$this->dbw->query( $sql );
		$this->output( "Clear mathrevisionstat\n" );
		$sql = 'DELETE FROM `mathrevisionstat`';
		$this->dbw->query( $sql );
		$this->output( "Generate mathrevisionstat\n" );
		// @codingStandardsIgnoreStart
		$sql =
			'INSERT INTO `mathrevisionstat`(`revstat_featureid`,`revstat_revid`,`revstat_featurecount`) ' .
			'SELECT varstat_id, mathindex_revision_id, count(*) AS CNT FROM `mathobservation` JOIN mathindex ON `mathobservation_inputhash` =mathindex_inputhash ' .
			'JOIN mathvarstat ON varstat_featurename = `mathobservation_featurename` AND varstat_featuretype = `mathobservation_featuretype` ' .
			' GROUP BY `mathobservation_featurename`, `mathobservation_featuretype`,mathindex_revision_id ORDER BY CNT DESC';
		// @codingStandardsIgnoreEnd
		$this->dbw->query( $sql );
		$this->output( "Updated {$fcount} formulae!\n" );
	}

	/**
	 * @param string $pText
	 * @param string $pTitle
	 * @param bool|string $purge
	 * @param \Wikimedia\Rdbms\IDatabase $dbw
	 *
	 * @return number
	 */
	private static function doUpdate( $pText, $pTitle = "", $purge = false, $dbw ) {
		// TODO: fix link id problem
		$anchorID = 0;
		$math = MathObject::extractMathTagsFromWikiText( $pText );
		$matches = count( $math );
		if ( $matches ) {
			echo ( "\t processing $matches math fields for {$pTitle} page\n" );
			foreach ( $math as $formula ) {
				$mo = new MathObject( $formula[1] );
				$mo->updateObservations( $dbw );
				// Enable indexing of math formula
				$anchorID++;
			}
			return $matches;
		}
		return 0;
	}

	/**
	 *
	 */
	public function execute() {
		$this->dbw = wfGetDB( DB_MASTER );
		$this->purge = $this->getOption( 'purge', false );
		$this->db = wfGetDB( DB_MASTER );
		$this->output( "Done.\n" );
		$this->populateSearchIndex( $this->getArg( 0, 0 ), $this->getArg( 1, - 1 ) );
	}
}

$maintClass = 'ExtractFeatures';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

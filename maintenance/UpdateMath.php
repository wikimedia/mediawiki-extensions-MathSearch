#!/usr/bin/env php
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

class UpdateMath extends Maintenance {

	/** @var bool */
	private $purge = false;
	/** @var bool */
	private $verbose;
	/** @var \Wikimedia\Rdbms\IDatabase */
	private $dbw;
	/** @var \Wikimedia\Rdbms\IDatabase */
	private $db;
	/** @var MathRenderer */
	private $current;
	/** @var float */
	private $time = 0.0; // microtime( true );
	/** @var float[] */
	private $performance = [];
	/** @var string */
	private $renderingMode = 'latexml';
	/** @var int */
	private $chunkSize = 1000;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Updates the index of Mathematical formulae.' );
		$this->addOption( 'purge',
			"If set all formulae are rendered again without using caches. (Very time consuming!)",
			false, false, "f" );
		$this->addArg( 'min', "If set processing is started at the page with rank(pageID)>min",
			false );
		$this->addArg( 'max', "If set processing is stopped at the page with rank(pageID)<=max",
			false );
		$this->addOption( 'verbose', "If set output for successful rendering will produced", false,
			false, 'v' );
		$this->addOption( 'SVG', "If set SVG images will be produced", false, false );
		$this->addOption( 'hooks', "If set hooks will be skipped, but index will be updated.",
			false, false );
		$this->addOption( 'texvccheck', "If set texvccheck will be skipped", false, false );
		$this->addOption( 'mode', 'Rendering mode to be used (png, mathml, latexml)', false, true,
			'm' );
		$this->addOption( 'chunk-size',
			'Determines how many pages are updated in one database transaction.', false, true );
		$this->requireExtension( 'MathSearch' );
	}

	/**
	 * Measures time in ms.
	 * In order to have a formula centric evaluation, we can not just the build in profiler
	 * @param string $category
	 *
	 * @return int
	 */
	private function time( $category = 'default' ) {
		global $wgMathDebug;
		$delta = ( microtime( true ) - $this->time ) * 1000;
		if ( isset( $this->performance[$category] ) ) {
			$this->performance[$category] += $delta;
		} else {
			$this->performance[$category] = $delta;
		}
		if ( $wgMathDebug ) {
			$this->db->insert( 'mathperformance', [
				'math_inputhash' => $this->current->getInputHash(),
				'mathperformance_name' => substr( $category, 0, 10 ),
				'mathperformance_time' => $delta,
				'mathperformance_mode' => $this->renderingMode
			] );
		}
		$this->time = microtime( true );

		return (int)$delta;
	}

	/**
	 * Populates the search index with content from all pages
	 *
	 * @param int $n
	 * @param int $cMax
	 *
	 * @throws DBUnexpectedError
	 */
	protected function populateSearchIndex( $n = 0, $cMax = -1 ) {
		$res = $this->db->select( 'revision', 'MAX(rev_id) AS count' );
		$s = $this->db->fetchObject( $res );
		$count = $s->count;
		if ( $cMax > 0 && $count > $cMax ) {
			$count = $cMax;
		}
		$this->output(
			"Rebuilding index fields for pages with revision < {$count} with option {$this->purge}...\n"
		);
		$fCount = 0;
		// return;
		while ( $n < $count ) {
			if ( $n ) {
				$this->output( $n . " of $count \n" );
			}
			$end = min( $n + $this->chunkSize - 1, $count );

			$res = $this->db->select( [ 'page', 'revision', 'text' ],
					[ 'page_id', 'page_namespace', 'page_title', 'old_flags', 'old_text', 'rev_id' ],
					[ "rev_id BETWEEN $n AND $end", 'page_latest = rev_id', 'rev_text_id = old_id' ],
					__METHOD__
			);
			$this->dbw->begin( __METHOD__ );
			$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			foreach ( $res as $s ) {
				$this->output( "\nr{$s->rev_id}" );
				$revText = $revisionStore->newRevisionFromRow( $s );
				$fCount += $this->doUpdate( $s->page_id, $revText, $s->page_title, $s->rev_id );
			}
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$start = microtime( true );
			$this->dbw->commit( __METHOD__ );
			echo " committed in " . ( microtime( true ) - $start ) . "s\n\n";
			var_dump( $this->performance );
			// echo "after" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$n += $this->chunkSize;
		}
		$this->output( "Updated {$fCount} formulae!\n" );
	}

	/**
	 * @param int $pid
	 * @param string $pText
	 * @param string $pTitle
	 * @param int $revId
	 *
	 * @return number
	 */
	private function doUpdate( $pid, $pText, $pTitle = "", $revId = 0 ) {
		$notused = '';
		$parser = new Parser();
		$parser->mLinkID = 0;
		$parser->mRevisionId = $revId;
		// MathSearchHooks::setNextID($eId);
		$math = MathObject::extractMathTagsFromWikiText( $pText );
		$matches = count( $math );
		if ( $matches ) {
			echo ( "\t processing $matches math fields for {$pTitle} page\n" );
			foreach ( $math as $formula ) {
				$this->time = microtime( true );
				$renderer = MathRenderer::getRenderer( $formula[1], $formula[2], $this->renderingMode );
				$this->current = $renderer;
				$this->time( "loadClass" );
				if ( $this->getOption( "texvccheck", false ) ) {
					$checked = true;
				} else {
					$checked = $renderer->checkTex();
					$this->time( "checkTex" );
				}
				if ( $checked ) {
					if ( !$renderer->isInDatabase() || $this->purge ) {
						$renderer->render( $this->purge );
						if ( $renderer->getMathml() ) {
							$this->time( "render" );
						} else {
							$this->time( "Failing" );
						}
						if ( $this->getOption( "SVG", false ) ) {
							$svg = $renderer->getSvg();
							if ( $svg ) {
								$this->time( "SVG-Rendering" );
							} else {
								$this->time( "SVG-Fail" );
							}
						}
					} else {
						$this->time( 'checkInDB' );
					}
				} else {
					$this->time( "checkTex-Fail" );
					echo "\nF:\t\t" . $renderer->getMd5() . " texvccheck error:" . $renderer->getLastError();
					continue;
				}
				$renderer->writeCache();
				$this->time( "write Cache" );
				if ( !$this->getOption( "hooks", false ) ) {
					Hooks::run( 'MathFormulaPostRender', [ $parser, &$renderer, &$notused ] );
					$this->time( "hooks" );
				} else {
					$eId = null;
					MathSearchHooks::setMathId( $eId, $renderer, $revId );
					MathSearchHooks::writeMathIndex( $revId, $eId, $renderer->getInputHash(), '' );
					$this->time( "index" );
				}
				if ( $renderer->getLastError() ) {
					echo "\n\t\t" . $renderer->getLastError();
					echo "\nF:\t\t" . $renderer->getMd5() . " equation " . ( $eId ) .
						"-failed beginning with\n\t\t'" . substr( $formula, 0, 100 )
						. "'\n\t\tmathml:" . substr( $renderer->getMathml(), 0, 10 ) . "\n ";
				} else {
					if ( $this->verbose ) {
						echo "\nS:\t\t" . $renderer->getMd5();
					}
				}
			}
			return $matches;
		}
		return 0;
	}

	public function execute() {
		global $wgMathValidModes;
		$this->dbw = wfGetDB( DB_MASTER );
		$this->purge = $this->getOption( "purge", false );
		$this->verbose = $this->getOption( "verbose", false );
		$this->renderingMode = $this->getOption( "mode", 'latexml' );
		$this->chunkSize = $this->getOption( 'chunk-size', $this->chunkSize );
		$this->db = wfGetDB( DB_MASTER );
		$wgMathValidModes[] = $this->renderingMode;
		$this->output( "Loaded.\n" );
		$this->time = microtime( true );
		$this->populateSearchIndex( $this->getArg( 0, 0 ), $this->getArg( 1, -1 ) );
	}
}

$maintClass = "UpdateMath";
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

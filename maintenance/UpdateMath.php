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

class UpdateMath extends Maintenance {
	const RTI_CHUNK_SIZE = 100;
	var $purge = false;
	/** @var boolean */
	private $verbose;
	/** @var DatabaseBase */
	var $dbw = null;
	/** @var MathRenderer  */
	private $current;
	private $time = 0;//microtime( true );
	private $performance = array();

	/**
	 * @var DatabaseBase
	 */
	private $db;
	/**
	 *
	 */
	public function __construct() {
		$this->verbose = $this->verbose;
		parent::__construct();
		$this->mDescription = 'Outputs page text to stdout';
		$this->addOption( 'purge', "If set all formulae are rendered again without using caches. (Very time consuming!)", false, false, "f" );
		$this->addArg( 'min', "If set processing is started at the page with rank(pageID)>min", false );
		$this->addArg( 'max', "If set processing is stopped at the page with rank(pageID)<=max", false );
		$this->addOption( 'verbose', "If set output for successful rendering will produced",false,false,'v' );
	}
	private function time($category='default'){
		global $wgMathDebug;
		$delta = (microtime(true) - $this->time)*1000;
		if (isset ($this->performance[$category] ))
			$this->performance[$category] += $delta;
		else
			$this->performance[$category] = $delta;
		if($wgMathDebug){
			$this->db->insert('mathperformance',array(
				'math_inputhash' => $this->current->getInputHash(),
				'mathperformance_name' => substr($category,0,10),
				'mathperformance_time' =>$delta,
			));

		}
		$this->time = microtime(true);

		return (int) $delta;
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

			$res = $this->db->select( array( 'page', 'revision', 'text' ),
					array( 'page_id', 'page_namespace', 'page_title', 'old_flags', 'old_text' ),
					array( "page_id BETWEEN $n AND $end", 'page_latest = rev_id', 'rev_text_id = old_id' ),
					__METHOD__
			);
			$this->dbw->begin();
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$i = $n;
			foreach ( $res as $s ) {
				echo "\np$i:";
				$revtext = Revision::getRevisionText( $s );
				$fcount += $this->doUpdate( $s->page_id, $revtext, $s->page_title);
				$i++;
			}
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$start = microtime( true );
			$this->dbw->commit();
			echo " committed in " . ( microtime( true ) -$start ) . "s\n\n";
			var_export($this->performance);
			// echo "after" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$n += self::RTI_CHUNK_SIZE;
		}
		$this->output( "Updated {$fcount} formulae!\n" );
	}
	/**
	 * @param unknown $pId
	 * @param unknown $pText
	 * @param string $pTitle
	 * @param string $purge
	 * @return number
	 */
	private function doUpdate( $pid, $pText, $pTitle = "") {
		// TODO: fix link id problem
		$notused = null;
		$anchorID = 0;
		$pText = Sanitizer::removeHTMLcomments( $pText );
		$pText = preg_replace( '#<nowiki>(.*)</nowiki>#', '', $pText );
		$matches = preg_match_all( "#<math>(.*?)</math>#s", $pText, $math );
		if ( $matches ) {
			echo( "\t processing $matches math fields for {$pTitle} page\n" );
			foreach ( $math[1] as $formula ) {
				$this->time = microtime(true);
				$renderer = MathRenderer::getRenderer( $formula, array(), MW_MATH_LATEXML );
				$this->current = $renderer;
				$this->time("loadClass");
				if ( $renderer->checkTex() ){
					$this->time("checkTex");
					$renderer->render( $this->purge );
					if( $renderer->getMathml() ){
						$this->time("LaTeXML-Rendering");
					} else {
						$this->time("LaTeXML-Fail");
					}
//					$svg = $renderer->getSvg();
//					if( $svg ){
//						$this->time("SVG-Rendering");
//					} else {
//						$this->time("SVG-Fail");
//					}
				}else{
					$this->time("checkTex-Fail");
					echo "\nF:\t\t".$renderer->getMd5()." texvccheck error:" . $renderer->getLastError();
					continue;
				}
				wfRunHooks( 'MathFormulaRendered', array( &$renderer , &$notused, $pid, $anchorID ) );
				$this->time("hooks");
				$renderer->writeCache($this->dbw);
				$this->time("write Cache");
				if ( $renderer->getLastError() ) {
					echo "\n\t\t". $renderer->getLastError() ;
					echo "\nF:\t\t".$renderer->getMd5()." equation " . ( $anchorID -1 ) .
						"-failed beginning with\n\t\t'" . substr( $formula, 0, 100 )
						. "'\n\t\tmathml:" . substr($renderer->getMathml(),0,10) ."\n ";
				} else{
					if($this->verbose){
						echo "\nS:\t\t".$renderer->getMd5();
					}
				}
			}
			return $matches;
		}
		return 0;
	}
	/**
	 *
	 */
	public function execute() {
		global $wgMathValidModes;
		$this->dbw = wfGetDB( DB_MASTER );
		$this->purge = $this->getOption( "purge", false );
		$this->verbose = $this->getOption("verbose",false);
		$this->db = wfGetDB( DB_MASTER );
		$wgMathValidModes[] = MW_MATH_LATEXML;
		$this->output( "Loaded.\n" );
		$this->time = microtime( true );
		$this->populateSearchIndex( $this->getArg( 0, 0 ), $this->getArg( 1, -1 ) );
	}
}

$maintClass = "UpdateMath";
require_once( RUN_MAINTENANCE_IF_MAIN );

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

require_once( dirname( __FILE__ ) . '/../../maintenance/Maintenance.php' );

class UpdateMath extends Maintenance {
	const RTI_CHUNK_SIZE = 10;
	var $purge = false;

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
		$this->addOption( 'purge', "If set all formulae are rendered again from strech. (Very time consuming!)", false, false, "f" );
	}
	/**
	 * Populates the search index with content from all pages
	 */
	protected function populateSearchIndex() {
		$res = $this->db->select( 'page', 'MAX(page_id) AS count' );
		$s = $this->db->fetchObject( $res );
		$count = $s->count;
		$this->output( "Rebuilding index fields for {$count} pages with option {$this->purge}...\n" );
		$n = 0;
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

			foreach ( $res as $s ) {
				$revtext = Revision::getRevisionText( $s );
				$fcount += self::doUpdate( $s->page_id, $revtext, $s->page_title, $this->purge );
			}
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
	private static function doUpdate( $pId, $pText, $pTitle = "", $purge = false ) {
		// TODO: fix link id problem
		$anchorID = 0;
		$matches = preg_match_all( "#<math>(.*?)</math>#s", $pText, $math );
		if ( $matches ) {
			echo( "\t processing $matches math fields for {$pTitle} page\n" );
			foreach ( $math[1] as $formula ) {
				$renderer = MathRenderer::getRenderer( $formula, array(), MW_MATH_LATEXML );
				$renderer->render( $purge );
				// Enable indexing of math formula
				$res = wfRunHooks( 'MathFormulaRendered', array( &$renderer ,null,$pid,$anchorID++) );
				$renderer->writeCache();
			}
			return $matches;
		}
		return 0;
	}
	/**
	 * 
	 */
	public function execute() {
		$this->purge = $this->getOption( "purge", false );
		$this->db = wfGetDB( DB_MASTER );
		$this->output( "Done.\n" );
		$this->populateSearchIndex();
	}
}

$maintClass = "UpdateMath";
require_once( RUN_MAINTENANCE_IF_MAIN );

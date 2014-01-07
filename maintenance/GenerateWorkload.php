<?php
/**
 * Generates harvest files for the MathWebSearch Deamon.
 * Example: php CreateMathIndex.php ~/mws_harvest_files
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

require_once( dirname( __FILE__ ) . '/IndexBase.php' );

/**
 * @author Moritz Schubotz
 *
 */
class GenerateWorkload extends IndexBase {
	private $id =0;

	/**
	 * @param ResultWrapper $row
	 * @return string
	 */
	protected function generateIndexString( $row ){
		$q = MathQueryObject::newQueryFromEquationRow($row, ++$this->id );
		$out = $q->serlializeToXML();
		if( $out == false ){
			echo 'problem with '.var_export($q,true)."\n";
			$out = '';
		}
		return $out;
	}


	public function execute() {
		libxml_use_internal_errors( true );
		$i = 0;
		$inc = $this->getArg( 1, 100 );
		$db = wfGetDB( DB_SLAVE );
		echo "getting list of all equations from the database\n";
		$this->res = $db->select(
			array( 'mathindex' ),
			array( 'mathindex_page_id', 'mathindex_anchor', 'mathindex_inputhash' ),
				true
				, __METHOD__
				,array('LIMIT' => $this->getOption( 'limit', 100 ) ,
					'ORDER BY' => 'mathindex_inputhash' )
		);
		echo "write " . $this->res->numRows() . " results to index\n";
		do {
			$fn = $this->getArg( 0 ) . '/math' . sprintf( '%012d', $i ) . '.xml';
			$res = $this->wFile( $fn, $i, $inc );
			$i += $inc;
		} while ( $res );
		echo( "done" );
	}

	protected function getHead(){
		return '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
	}
	protected function getFooter(){
		return "";
	}
}
$maintClass = "GenerateWorkload";
require_once( RUN_MAINTENANCE_IF_MAIN );
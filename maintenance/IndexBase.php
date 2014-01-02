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

require_once( dirname( __FILE__ ) . '/../../../maintenance/Maintenance.php' );

/**
 * @author Moritz Schubotz
 *
 */
abstract class IndexBase extends Maintenance {
	private $res;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Exports data';
		$this->addArg( 'dir', 'The directory where the harvest files go to.' );
		$this->addArg( 'ffmax', "The maximal number of formula per file.", false );
		$this->addOption( 'limit', 'The maximal number of database entries to be considered', false );
	}

	/**
	 * @param unknown $row
	 * @return string
	 */
	protected abstract function generateIndexString( $row );

	/**
	 * @param unknown $fn
	 * @param unknown $min
	 * @param unknown $inc
	 * @return boolean
	 */
	protected function wFile( $fn, $min, $inc ) {
		$out = $this->getHead();
		$max = min( $min + $inc, $this->res->numRows() -1 );
		for ( $i = $min; $i < $max; $i++ ) {
			$this->res->seek( $i );
			$out .= $this->generateIndexString( $this->res->fetchObject() );
			restore_error_handler (  );
		}
		$out .= "\n" . $this->getFooter();
		$fh = fopen( $fn, 'w' );
		// echo $out;
		// die ("test");
		fwrite( $fh, $out );
		fclose( $fh );
		echo "written file $fn with entries($min ... $max)\n";
		if ( $max < $this->res->numRows() -1 )
			return true;
		else
			return false;
	}

	/**
	 *
	 */
	public function execute() {
		libxml_use_internal_errors( true );
		$i = 0;
		$inc = $this->getArg( 1, 100 );
		$db = wfGetDB( DB_SLAVE );
		echo "getting list of all equations from the database\n";
		$this->res = $db->select(
			array( 'mathindex', 'mathoid' ),
			array( 'mathindex_page_id', 'mathindex_anchor', 'math_mathml', 'math_inputhash', 'mathindex_inputhash' ),            // $vars (columns of the table)
			'math_inputhash = mathindex_inputhash'
				, __METHOD__
				,array( 
					'LIMIT'    =>  $this->getOption( 'limit',100000000 ) ,
					'ORDER BY' => 'mathindex_page_id' )
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
		return "";
	}
	protected function getFooter(){
		return "";
	}
}

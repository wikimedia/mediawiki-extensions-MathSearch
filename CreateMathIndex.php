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

require_once( dirname( __FILE__ ) . '/../../maintenance/Maintenance.php' );

/**
 * @author Moritz Schubotz
 *
 */
class CreateMath extends Maintenance {

	private static $XMLHead = "<?xml version=\"1.0\"?>\n<mws:harvest xmlns:mws=\"http://search.mathweb.org/ns\" xmlns:m=\"http://www.w3.org/1998/Math/MathML\">";
	private static $XMLFooter = "</mws:harvest>";
	private $res;
	
	/**
	 * 
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Generates harvest files for the MathWebSearch Deamon.';
		$this->addArg( 'dir', 'The directory where the harvest files go to.' );
		$this->addArg( 'ffmax', "The maximal number of formula per file.", false );
	}

	/**
	 * @param unknown $row
	 * @return string
	 */
	private static function generateIndexString( $row ) {
		$out = "";
		$xml = simplexml_load_string( $row->math_mathml );
		if ( !$xml ) {
			echo "ERROR while converting:\n " . var_export( $row->math_mathml, true ) . "\n";
			foreach ( libxml_get_errors() as $error )
				echo "\t", $error->message;
			libxml_clear_errors();
			return "";
		}
		if ( $xml->math ) {
			$smath = $xml->math->semantics-> { 'annotation-xml' } ->children()->asXML();
			$out .= "\n<mws:expr url=\"" . $row->mathindex_page_id . "#math" . $row->mathindex_anchor . "\">\n\t";
			$out .= $xml->math->children()->asXML();
			$out .= "\n</mws:expr>\n";
		}
	}
	
	/**
	 * @param unknown $fn
	 * @param unknown $min
	 * @param unknown $inc
	 * @return boolean
	 */
	private function wFile( $fn, $min, $inc ) {
		$out = self::$XMLHead;
		$max = max( $min + $inc, $this->res->numRows() -1 );
		for ( $i = $min; $i < $max; $i++ ) {
			$this->res->seek( $i );
			$out .= self::generateIndexString( $this->res->fetchObject() );
            restore_error_handler (  );

		}
		$out .= "\n" . self::$XMLFooter ;
		$fh = fopen( $fn, 'w' );
		fwrite( $fh, $out );
		fclose( $fh );
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
		$inc = $this->getArg( 1, 1000 );
		$db = wfGetDB( DB_SLAVE );
		$this->res = $db->select(
			array( 'mathindex', 'math' ),
			array( 'mathindex_page_id', 'mathindex_anchor', 'math_mathml', 'math_inputhash', 'mathindex_inputhash' ),            // $vars (columns of the table)
			'math_inputhash = mathindex_inputhash');
		do {
			$fn = $this->getArg( 0 ) . '/math' . sprintf( '%012d', $i ) . '.xml';
			$res = $this->wFile( $fn, $i, $inc );
			$i += $inc;
		} while ( $res );
		echo( "done" );
	}
}

$maintClass = "CreateMath";
require_once( RUN_MAINTENANCE_IF_MAIN );

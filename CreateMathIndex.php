<?php
/**
 * Outputs page text to stdout, useful for command-line editing automation.
 * Example: php getText.php "page title" | sed -e '...' | php edit.php "page title"
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

class CreateMath extends Maintenance {
	var $res;
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Outputs page text to stdout';
		$this->addArg( 'dir', 'The directory where the harvest files go to.' );
		$this->addArg( 'ffmax', "The maximal number of formula per file.", false );
	}

	private function generateIndexString( $row ) {
	// enable user error handling
	// var_dump($row);
	// return true;
	// die("EOF");
	// if(!is_null($row->mathml)){
	// var_dump($row->mathml);
	$out = "";
		// try {
		// set_error_handler( create_function( '', "throw new Exception(); return true;" ) );
                $xml = simplexml_load_string( $row->math_mathml );
                if ( !$xml ) {
    echo "Failed loading XML\n";
    foreach ( libxml_get_errors() as $error ) {
        echo "\t", $error->message;
    }
    libxml_clear_errors();
    echo "ERROR while converting " . var_export( $row->math_mathml, true ) . ":$e";
    return ""; }
			// $xml = new SimpleXMLElement( $row->math_mathml );
			// var_dump($xml->math->semantics);
			if ( $xml->math ) {
			$smath = $xml->math->semantics-> { 'annotation-xml' } ->children()->asXML();

			$out .= "\n<mws:expr url=\"" . $row->mathindex_page_id . "#math" . $row->mathindex_anchor . "\">\n\t";
		// $this->output( $smath )  ;
			// $this->output($xml->math->children()->asXML());
		// $out.=$row->mathml;
		$out .= $xml->math->children()->asXML();
		$out .= "\n</mws:expr>\n";
				return $out;
	//	}			// else //EMPTY math
		//		return "";
					// die($out);
		// } catch ( Exception $e ) {
			// echo "ERROR while converting " . var_export( $row, true ) . ":$e";

		}
		// }		else		return false;
	}
	private function wFile( $fn, $min, $inc ) {

		// if($res&&sizeof($res)>0){
		$XMLHead = <<<XML
<?xml version="1.0"?>
<mws:harvest xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML">
XML;
		$XMLFooter = "</mws:harvest>";
		$out = $XMLHead;
		$max = max( $min + $inc, $this->res->numRows() -1 );
		for ( $i = $min; $i < $max; $i++ ) {
			$this->res->seek( $i );
			$out .= $this->generateIndexString( $this->res->fetchObject() );
            restore_error_handler (  );

		}
		$out .= "\n" . $XMLFooter ;
		$fh = fopen( $fn, 'w' );
		fwrite( $fh, $out );
		fclose( $fh );
		if ( $max < $this->res->numRows() -1 )
		// die ("written");
		return true;
		else return false;
		}

	public function execute() {
        libxml_use_internal_errors( true );
		$i = 0;
		$inc = $this->getArg( 1, 1000 );
		$db = wfGetDB( DB_SLAVE );
		$this->res = $db->select(
        array( 'mathindex', 'math' ),                                   // $table
        array( 'mathindex_page_id', 'mathindex_anchor', 'math_mathml', 'math_inputhash', 'mathindex_inputhash' ),            // $vars (columns of the table)
		'math_inputhash = mathindex_inputhash'/*,
		__METHOD__,
		array( 'LIMIT'=> $inc, 'OFFSET'=>$min)//*/
		);
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

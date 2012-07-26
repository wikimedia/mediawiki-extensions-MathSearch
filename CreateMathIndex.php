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
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Outputs page text to stdout';
		//$this->addOption( 'show-private', 'Show the text even if it\'s not available to the public' );
		//$this->addArg( 'title', 'Page title' );
	}
	private function generateIndexString($row){
	// enable user error handling
	//var_dump($row);
	//return true;
	//die("EOF");
	//if(!is_null($row->mathml)){
		try{ 
			$xml=new SimpleXMLElement($row->mathml);
			//var_dump($xml->math->semantics);
			if($xml->math->semantics->{'annotation-xml'})
			$smath= $xml->math->semantics->{'annotation-xml'}->children()->asXML();
			else //EMPTY math
				return false;
		} catch (Exception $e){ 
			echo "ERROR while converting ".var_export($row,true).":$e";
			return false;
		}
		$this->output( "\n<mws:expr url=\"".$row->pageid."#math".$row->anchor."\">\n\t");
		$this->output( $smath )  ;
		$this->output( "\n</mws:expr>\n");
		return true;
		//}		else		return false;
	}

	public function execute() {
		$db = wfGetDB( DB_SLAVE );
		$res = $db->select(
        'mathsearch',                                   // $table
        array( 'pageid',	'anchor',	'mathml' )//,            // $vars (columns of the table)
		/*'',
		__METHOD__,  
		array( 'LIMIT'=> '10')*/
		);
		$XMLHead=<<<XML
<?xml version="1.0"?>
<mws:harvest xmlns:mws="http://search.mathweb.org/ns" xmlns:m="http://www.w3.org/1998/Math/MathML">
XML;
		$XMLBody="</mws:harvest>";
		$this->output( $XMLHead);
		foreach($res as $row){
			$this->generateIndexString($row);
		}

		$this->output( "\n".$XMLBody );
	}
}

$maintClass = "CreateMath";
require_once( RUN_MAINTENANCE_IF_MAIN );

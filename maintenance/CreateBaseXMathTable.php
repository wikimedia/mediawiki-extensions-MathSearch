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
class CreateBaseXMathTable extends IndexBase {
	private static $mwsns = "mws:";
	private static $XMLHead;
	private static $XMLFooter;
	/** @var \BaseXSession  */
	private $session;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Generates harvest files for the MathWebSearch Deamon.';
		$this->addOption( 'mwsns', 'The namespace or mws normally "mws:"', false );
		$this->addOption( 'truncate', 'If set the database will be recreated.' );
	}

	/**
	 * @param unknown $row
	 * @return string
	 */
	protected function generateIndexString( $row ) {
		$out = "";
		$xml = simplexml_load_string( utf8_decode($row->math_mathml) );
		if ( !$xml ) {
			echo "ERROR while converting:\n " . var_export( $row->math_mathml, true ) . "\n";
			foreach ( libxml_get_errors() as $error )
				echo "\t", $error->message;
			libxml_clear_errors();
			return "";
		}
		$out .= "\n<" . self::$mwsns . "expr url=\"" .
	        MathSearchHooks::generateMathAnchorString( $row->mathindex_revision_id, $row->mathindex_anchor, '' ) . "\">\n\t";
		$out .=  utf8_decode( $row->math_mathml );// $xml->math->children()->asXML();
		$out .= "\n</" . self::$mwsns . "expr>\n";
		// TODO: This does not work yet.
		// Find out how to insert new data without to write it into a temporary file
		// $this->session->execute("insert node $out ");
		return $out;
	}

	protected function getHead(){
		return self::$XMLHead;
	}
	protected function getFooter(){
		return self::$XMLFooter;
	}
	/**
	 * @param string $fn
	 * @param int $min
	 * @param int $inc
	 * @return boolean
	 */
	protected function wFile( $fn, $min, $inc ) {
		$retval = parent::wFile($fn,$min,$inc);
		$this->session->execute("add $fn");
		return $retval;
	}
	/**
	 *
	 */
	public function execute() {
		global $wgMathSearchBaseXDatabaseName;
		self::$mwsns = $this->getOption( 'mwsns', '' );
		self::$XMLHead = "<?xml version=\"1.0\"?>\n<" . self::$mwsns . "harvest xmlns:mws=\"http://search.mathweb.org/ns\" xmlns:m=\"http://www.w3.org/1998/Math/MathML\">";
		self::$XMLFooter = "</" . self::$mwsns . "harvest>";
		$this->session = new BaseXSession();
		if( $this->getOption('truncate',false) ){
			$this->session->execute("open ".$wgMathSearchBaseXDatabaseName);
		} else {
			$this->session->execute("create db ".$wgMathSearchBaseXDatabaseName);
		}
		parent::execute();
	}
	public function __destruct(){
		$this->session->close();
	}
}

$maintClass = "CreateBaseXMathTable";
require_once( RUN_MAINTENANCE_IF_MAIN );

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
class CreateMathIndex extends IndexBase {
	private static $mwsns = "mws:";
	private static $XMLHead;
	private static $XMLFooter;

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Generates harvest files for the MathWebSearch Deamon.';
		$this->addOption( 'mwsns', 'The namespace or mws normally "mws:"', false );
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
		// if ( $xml->math ) {
			// $smath = $xml->math->semantics-> { 'annotation-xml' } ->children()->asXML();
			$out .= "\n<" . self::$mwsns . "expr url=\"" . $row->mathindex_page_id . "#math" . $row->mathindex_anchor . "\">\n\t";
			$out .=  utf8_decode( $row->math_mathml );// $xml->math->children()->asXML();
			$out .= "\n</" . self::$mwsns . "expr>\n";
			return $out;
		/*} else {
			var_dump($xml);
			die("nomath");
		}*/

	}

	protected function getHead(){
		return self::$XMLHead;
	}
	protected function getFooter(){
		return self::$XMLFooter;
	}
	/**
	 *
	 */
	public function execute() {
		self::$mwsns = $this->getOption( 'mwsns', '' );
		self::$XMLHead = "<?xml version=\"1.0\"?>\n<" . self::$mwsns . "harvest xmlns:mws=\"http://search.mathweb.org/ns\" xmlns:m=\"http://www.w3.org/1998/Math/MathML\">";
		self::$XMLFooter = "</" . self::$mwsns . "harvest>";
		parent::execute();
	}
}

$maintClass = "CreateMathIndex";
require_once( RUN_MAINTENANCE_IF_MAIN );

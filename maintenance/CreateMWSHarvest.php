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

require_once __DIR__ . '/IndexBase.php';

/**
 * @author Moritz Schubotz
 */
class CreateMWSHarvest extends IndexBase {

	/** @var MwsDumpWriter */
	private $dw;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Generates harvest files for the MathWebSearch Daemon.' );
		$this->addOption( 'mwsns', 'The namespace or mws normally "mws:"', false );
	}

	/**
	 * @param stdClass $row
	 *
	 * @return string
	 */
	protected function generateIndexString( $row ) {
		$xml = simplexml_load_string( utf8_decode( $row->math_mathml ) );
		if ( !$xml ) {
			echo "ERROR while converting:\n " . var_export( $row->math_mathml, true ) . "\n";
			foreach ( libxml_get_errors() as $error ) {
				echo "\t", $error->message;
			}
			libxml_clear_errors();
			return '';
		}
		return $this->dw->getMwsExpression(
			utf8_decode( $row->math_mathml ),
			$row->mathindex_revision_id,
			$row->mathindex_anchor );
	}

	protected function getHead() {
		return $this->dw->getHead();
	}

	protected function getFooter() {
		return $this->dw->getFooter();
	}

	public function execute() {
		$ns = $this->getOption( 'mwsns', '' );
		$this->dw = new MwsDumpWriter( $ns );
		parent::execute();
	}
}

$maintClass = 'CreateMWSHarvest';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

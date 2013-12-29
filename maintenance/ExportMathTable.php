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
class ExportMathTable extends IndexBase {
	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Exports a db2 compatible math index table.';
	}

	/**
	 * @param unknown $row
	 * @return string
	 */
	protected function generateIndexString( $row ) {
		$mo = MathObject::constructformpagerow($row);
		$out = '"'. $mo->getMd5().'"';
		$out .= ',"'. $mo->getTex().'"';
		$out .= ',"'. $row->mathindex_page_id .'"';
		$out .= ',"'. $row->mathindex_anchor.'"';
		$out .= ',"'.str_replace(array('"',"\n"),array('""',' '), $mo->getMathml()).'"';
		return $out."\n";

	}

	/**
	 *
	 */
	public function execute() {
		parent::execute();
	}
}

$maintClass = "ExportMathTable";
require_once( RUN_MAINTENANCE_IF_MAIN );

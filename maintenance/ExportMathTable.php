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
	private $db2Pass;
	private $statment;
	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Exports a db2 compatible math index table.';
		$this->addArg( 'passw', "If set, the data is directly imported to db2", false );
		$this->addArg( 'truncate', "If true, db2 math table is deleted before import", false );
	}

	/**
	 * @param unknown $row
	 * @return string
	 */
	protected function generateIndexString( $row ) {
		$mo = MathObject::constructformpagerow($row);
		$out = '"'. $mo->getMd5().'"';
		$out .= ',"'. $mo->getTex().'"';
		$out .= ','. $row->mathindex_page_id .'';
		$out .= ','. $row->mathindex_anchor.'';
		$out .= ',"'.str_replace(array('"',"\n"),array('"',' '), $mo->getMathml()).'"';
		if( $this->db2Pass ) {
			$this->statment->execute(array($mo->getMd5(),$mo->getTex(),$row->mathindex_page_id,$row->mathindex_anchor,$mo->getMathml()));
}
		return $out."\n";

	}

	/**
	 *
	 */
	public function execute() {
		$this->db2Pass = $this->getOption( 'passw',false );
		if ( $this->db2Pass ){
			try { 
  				$connection = new PDO("ibm:MATH", "db2inst1", $this->db2Pass, array(
    					PDO::ATTR_PERSISTENT => TRUE, 
    					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
  				); 
			} catch (Exception $e) {
			  echo($e->getMessage());
			}
			if ( $this->getOption('truncate' , false ) ){
				 $connection->query('TRUNCATE TABLE "wiki"."math" IMMEDIATE');
			}
			$this->statment = $connection->prepare('insert into "wiki"."math" ("math_md5", "math_tex", "mathindex_pageid", "mathindex_anchord", "math_mathml") values(?, ?, ?, ?, ?)');
			
		}
		parent::execute();
	}
}

$maintClass = "ExportMathTable";
require_once( RUN_MAINTENANCE_IF_MAIN );

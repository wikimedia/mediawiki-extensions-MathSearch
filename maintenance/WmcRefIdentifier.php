<?php
/**
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

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class WmcRefIdentifier extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->query( 'SELECT qID, oldId, fid, math_inputtex FROM math_wmc_ref r' .
			' JOIN mathlatexml l WHERE  r.math_inputhash = l.math_inputhash;' );

		$output = [];
		foreach ( $res as $row ) {
			$md = new MathoidDriver( $row->math_inputtex );
			$md->texvcInfo();
			$identifiers = array_unique( $md->getIdentifiers() );
			$fId = "math.{$row->oldId}.{$row->fid}";
			$mo = MathObject::newFromRevisionText( $row->oldId, $fId );
			$relations = [];
			$rels = $mo->getRelations();
			$wd = new WikidataDriver();
			foreach ( $identifiers as $i ) {
				$relations[$i] = [];
				if ( isset( $rels[$i] ) ) {
					foreach ( $rels[$i] as $rel ) {
						if ( preg_match( '/\[\[(.*)\]\]/', $rel->definition, $m ) ) {
							if ( $wd->search( $m[1] ) ) {
								$res = $wd->getResults();
								$relations[$i][] = $res;
							}
						} else {
							$relations[$i][] = $rel->definition;
						}
					}
				}
			}
			$output[] = (object)[ 'definitions' => $relations, 'formula' => $row ];
		}
		$this->output( json_encode( $output ) );
	}
}

$maintClass = WmcRefIdentifier::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

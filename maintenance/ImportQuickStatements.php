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
 *
 */

require_once __DIR__ . '/BaseImport.php';

class ImportQuickStatements extends BaseImport {
	public function __construct() {
		$jobname = 'quickstatements' . date( 'ymdhms' );
		$joboptions = [ 'jobname' => $jobname, 'editsummary' => 'QuickStatements import from csv file' ];
		$jobtype = 'MediaWiki\Extension\MathSearch\Graph\Job\QuickStatements';
		$this->rowsHaveKeys = false;
		parent::__construct( $joboptions, $jobtype, 'Batch imports quick statements from a CSV file.' );
	}

	protected function readline( array $line, array $columns ): array {
		$statements = array_combine( $columns, $line );
		foreach ( $statements as $column => $value ) {
			if ( $column !== 'qid' && $value === '' ) {
				unset( $statements[$column] );
			}
		}
		return $statements;
	}

}

$maintClass = ImportQuickStatements::class;
require_once RUN_MAINTENANCE_IF_MAIN;

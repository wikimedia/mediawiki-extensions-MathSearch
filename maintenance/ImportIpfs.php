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
 * TODO: deduplicate code from OpenAlex
 */

use MediaWiki\Sparql\SparqlException;

require_once __DIR__ . '/BaseImport.php';

class ImportIpfs extends BaseImport {

	public function __construct() {
		$jobname = 'ipfs' . date( 'ymdhms' );
		$joboptions = [ 'jobname' => $jobname, 'editsummary' => 'Import IPFS CIDs' ];
		$jobtype = 'MediaWiki\Extension\MathSearch\Graph\Job\QuickStatements';
		parent::__construct( $joboptions, $jobtype, 'Batch imports IPFS CIDs from a CSV file.' );
	}

	/**
	 * @throws SparqlException
	 */
	protected function readline( array $line, array $columns ): array {
		global $wgMathSearchPropertyIpfs;
		$fields = array_combine( $columns, $line );
		$qId = preg_replace( '/^DONE:/', '', $fields['document'] );
		return [ $qId => [
			'qid' => $qId,
			"P$wgMathSearchPropertyIpfs" => $fields['cid']
		] ];
	}

}

$maintClass = ImportIpfs::class;
require_once RUN_MAINTENANCE_IF_MAIN;

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

class ImportRecommendations extends BaseImport {

	public function __construct() {
		$jobname = 'recommendation' . date( 'ymdhms' );
		$joboptions = [ 'jobname' => $jobname, 'editsummary' => 'Import recommendations run Q6534273' ];
		$jobtype = 'MediaWiki\Extension\MathSearch\Graph\Job\Recommendation';
		$this->groupConsecutiveKeys = true;
		parent::__construct( $joboptions, $jobtype, 'Batch imports Recommendation data from a CSV file.' );
		$this->addOption( 'runid', 'The QID of the item that represents the run.', false, true, 'r' );
	}

	public function execute() {
		$runid = $this->getOption( 'runid', 'Q6534273' );
		$this->jobOptions['runid'] = $runid;
		$this->jobOptions['editsummary'] = "Import recommendations run $runid";
		return parent::execute();
	}

	/**
	 * @throws SparqlException
	 */
	protected function readline( array $line, array $columns ): array {
		$fields = array_combine( $columns, $line );
		return [ $fields['seed'] => [
			$fields['recommendation'] => $fields['similarity_score']
		] ];
	}

}

$maintClass = ImportRecommendations::class;
require_once RUN_MAINTENANCE_IF_MAIN;

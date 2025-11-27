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

class ImportStacksProject extends Maintenance {
	private const URL = 'https://stacks.math.columbia.edu/data/tag/';
	private const PARTS = [ '0ELQ', '0ELP', '0ELV', '0ELT', '0ELN', '0ELW', '0ELS', '0ELR', '0ELU' ];
	/** @var bool */
	private $overwrite;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Batch imports/updates from the stacks project ' );
		$this->addArg( 'url', 'The base URL to to be read. Defaults to ' . self::URL, false );
		$this->addOption(
			'overwrite', 'Overwrite existing definitions with the same name.', false, false, "o"
		);
		$this->setBatchSize( 100 );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$url = $this->getArg( 0, self::URL );
		$this->overwrite = $this->getOption( 'overwrite' );
		if ( $this->overwrite ) {
			$this->output( "Loaded with option overwrite enabled .\n" );
		}
		$structures = [];
		foreach ( self::PARTS as $part ) {
			$json = file_get_contents( "$url/$part/structure" );
			$data = json_decode( $json, true );
			if ( $data === null ) {
				throw new Exception( "Failed to decode JSON for part $part" );
			}
			$structures[] = $data;
		}
		$list = $this->tree2list( $structures );
		$fp = fopen( 'file.csv', 'w' );
		// header
		fwrite( $fp, "qP1694,P31,P1696,Den,P37q1694,Len,P459\n" );
		foreach ( $list as $fields ) {
			fputcsv( $fp, $fields, ',', '"', '' );
		}

		fclose( $fp );
	}

	/**
	 * Recursive flattening of the tree structure
	 */
	private function tree2list( array $tree, int $depth = 0, array $parents = [] ): array {
		global $wgMathString2QMap;
		$result = [];

		foreach ( $tree as $node ) {
			// Handle missing fields roughly like the Python try/except
			$tag = $node['tag'];

			$nodeInfo = [
				'qP1694' => $tag,
				'P31' => $wgMathString2QMap['P31'][$node['type']],
				'P1696' => $node['reference'],
				'Den' => "Stacks Project tag $tag"
			];
			if ( $depth > 0 ) {
				$nodeInfo['P37q1694'] = $parents[0];
			} else {
				// missing field in the middle (first entry) temporary hack
				$nodeInfo['P37q1694'] = null;
			}
			if ( isset( $node['name'] ) ) {
				$nodeInfo['Len'] = "{$node['name']} (Stacks Project)";
				$nodeInfo['P459'] = $node['name'];
			} else {
				$nodeInfo['Len'] = "Stacks Project {$node['type']} {$node['reference']}";
			}
			$result[$tag] = $nodeInfo;

			if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
				// In Python: [node['tag']] + parents  (prepend)
				$newParents = $parents;
				array_unshift( $newParents, $tag );
				$childrenList = $this->tree2list( $node['children'], $depth + 1, $newParents );
				$result = array_merge( $result, $childrenList );
			}
		}

		return $result;
	}

}

$maintClass = ImportStacksProject::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

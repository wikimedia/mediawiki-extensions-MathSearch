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

use MediaWiki\Extension\Math\WikiTexVC\TexVC;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Writes the reference of EnWikiFormulaeTest in Math, like bin/batch-evaluate of texvcjs did for texvcjs.
 */
class NormalizeTexvc extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Writes the inputs that WikiTexVC accepts and its output for each of them, ' .
			'such as en-wiki-formulae-good.json and normalized.json.' );
		$this->addArg( 'inputs', 'JSON object of hash to TeX input, such as enwiki_texvc_inputs.json' );
		$this->addArg( 'good', 'Output: the inputs that WikiTexVC accepts' );
		$this->addArg( 'normalized', 'Output: the TeX that WikiTexVC returns for each of them' );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$inputs = json_decode( file_get_contents( $this->getArg( 0 ) ), true );
		ksort( $inputs, SORT_STRING );
		$texVC = new TexVC();
		$good = [];
		$normalized = [];
		foreach ( $inputs as $hash => $tex ) {
			try {
				// The options of EnWikiFormulaeTest
				$result = $texVC->check( $tex, [ 'debug' => false, 'usemathrm' => false, 'oldtexvc' => false ] );
			} catch ( Throwable ) {
				continue;
			}
			if ( $result['status'] === '+' ) {
				$good[$hash] = $tex;
				$normalized[$hash] = $result['output'];
			}
		}
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
		file_put_contents( $this->getArg( 1 ), json_encode( $good, $flags ) );
		file_put_contents( $this->getArg( 2 ), json_encode( $normalized, $flags ) );
		$this->output( count( $good ) . ' of ' . count( $inputs ) . " inputs are valid\n" );
	}
}

$maintClass = NormalizeTexvc::class;
require_once RUN_MAINTENANCE_IF_MAIN;

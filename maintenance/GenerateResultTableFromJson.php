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

class GenerateResultTableFromJson extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Imports results from a json file.' .
			" \n Generates wikitext template to stdout." );
		$this->addArg( 'file', 'The file to be read', true );
		$this->requireExtension( 'MathSearch' );
	}

	public function execute() {
		$filename = $this->getArg( 0 );

		if ( !is_file( $filename ) ) {
			$this->output( "{$filename} is not a directory.\n" );
			exit( 1 );
		}
		$this->output( '{| class="wikitable"
|+ Result table
|-
! # !! Formula !! Title !! Evaluation data
' );
		$json = json_decode( file_get_contents( $filename ) );
		foreach ( $json as $item ) {
			$anchor = $item->eid;
			if ( preg_match( '/-1$/', $anchor ) ) {
				$anchor = substr( $anchor, 0, -2 );
			}
			$this->output( "\n|- \n" );
			$this->output( "| $item->id \n| " );
			$this->printSource( $item->formulae[0]->formula, '', 'tex', false, false );
			$this->output( "\n| [[$item->title#$anchor| $item->title]] \n| " );
			$this->printSource( json_encode( $item, JSON_PRETTY_PRINT ), 'Full data', 'json' );
		}
		$this->output( "\n|}" );
	}

	private function printSource(
		$source, $description = "", $language = "text", $linestart = true, $collapsible = true
	) {
		// TODO: deduplicate from SpecialLaTeXTranslator
		$inline = ' inline ';
		if ( $description ) {
			$description .= ": ";
		}
		if ( $collapsible ) {
			$this->printColHeader( $description );
			$description = '';
			$inline = '';
		}
		$this->output( "$description<syntaxhighlight lang=\"$language\" $inline>" . $source .
			'</syntaxhighlight>', $linestart );
		if ( $collapsible ) {
			$this->printColFooter();
		}
	}

	private function printColHeader( string $description ): void {
		$this->output( '<div class="toccolours mw-collapsible mw-collapsed"  style="text-align: left">' );
		$this->output( $description );
		$this->output( '<div class="mw-collapsible-content">' );
	}

	private function printColFooter(): void {
		$this->output( '</div></div>' );
	}

}

$maintClass = 'GenerateResultTableFromJson';
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

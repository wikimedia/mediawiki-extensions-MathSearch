#!/usr/bin/env php
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

use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Parser\Parser;

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class UpdateMath extends Maintenance {

	/** @var bool */
	private $purge = false;
	/** @var bool */
	private $verbose;
	/** @var \Wikimedia\Rdbms\IDatabase */
	private $dbw;
	/** @var \Wikimedia\Rdbms\IDatabase */
	private $db;
	/** @var MathRenderer */
	private $current;
	/** @var float */
	private $time = 0.0; // microtime( true );
	/** @var float[] */
	private $performance = [];
	/** @var string */
	private $renderingMode = 'latexml';
	/** @var int */
	private $chunkSize = 1000;
	/** @var Parser */
	private $parser;
	/** @var ParserOptions */
	private $parserOptions;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Updates the index of Mathematical formulae.' );
		$this->addOption( 'purge',
			"If set all formulae are rendered again without using caches. (Very time consuming!)",
			false, false, "f" );
		$this->addArg( 'min', "If set processing is started at the page with rank(pageID)>min",
			false );
		$this->addArg( 'max', "If set processing is stopped at the page with rank(pageID)<=max",
			false );
		$this->addOption( 'verbose', "If set output for successful rendering will produced", false,
			false, 'v' );
		$this->addOption( 'SVG', "If set SVG images will be produced", false, false );
		$this->addOption( 'hooks', "If set hooks will be skipped, but index will be updated.",
			false, false );
		$this->addOption( 'texvccheck', "If set texvccheck will be skipped", false, false );
		$this->addOption( 'mode', 'Rendering mode to be used (mathml, latexml)', false, true,
			'm' );
		$this->addOption( 'exportmml', 'export LaTeX and generated MathML to the specified file', false, true,
			'e' );
		$this->addOption( 'chunk-size',
			'Determines how many pages are updated in one database transaction.', false, true );
		$this->requireExtension( 'MathSearch' );
	}

	/**
	 * Measures time in ms.
	 * In order to have a formula centric evaluation, we can not just the build in profiler
	 * @param string $category
	 *
	 * @return int
	 */
	private function time( $category = 'default' ) {
		global $wgMathDebug;
		$delta = ( microtime( true ) - $this->time ) * 1000;
		$this->performance[$category] ??= 0;
		$this->performance[$category] += $delta;
		if ( $wgMathDebug ) {
			$this->db->insert( 'mathperformance', [
				'math_inputhash' => $this->current->getInputHash(),
				'mathperformance_name' => substr( $category, 0, 10 ),
				'mathperformance_time' => $delta,
				'mathperformance_mode' => MathObject::MODE_2_USER_OPTION[ $this->renderingMode ]
			] );
		}
		$this->time = microtime( true );

		return (int)$delta;
	}

	/**
	 * Populates the search index with content from all pages
	 *
	 * @param int $n
	 * @param int $cMax
	 */
	protected function populateSearchIndex( $n = 0, $cMax = -1 ) {
		$s = $this->db->selectRow( 'revision', 'MAX(rev_id) AS count', '' );
		$count = $s->count;
		if ( $cMax > 0 && $count > $cMax ) {
			$count = $cMax;
		}
		$this->output(
			"Rebuilding index fields for pages with revision < {$count} with option {$this->purge}...\n"
		);
		$fCount = 0;
		// return;
		while ( $n < $count ) {
			if ( $n ) {
				$this->output( $n . " of $count \n" );
			}
			$end = min( $n + $this->chunkSize - 1, $count );

			# For filtering page by namespace add condition 'page_namespace = 4'
			$res = $this->db->select( [ 'page', 'slots', 'content', 'text', 'revision' ],
				[ 'page_id', 'page_namespace', 'page_title', 'page_latest',
					'content_address', 'old_text', 'old_flags', 'rev_id' ],
				[ "rev_id BETWEEN $n AND $end" ],
				__METHOD__,
				[],
				[
					'slots' => [ 'INNER JOIN', [ 'slot_origin = page_latest' ] ],
					'content' => [ 'INNER JOIN', [ 'content_id = slot_content_id' ] ],
					'text' => [ 'INNER JOIN', [ 'old_id = substr(content_address,4)' ] ],
					'revision' => [ 'INNER JOIN', [ 'page_latest = rev_id' ] ] ]
			);

			$this->dbw->begin( __METHOD__ );
			$revisionStore = $this->getServiceContainer()->getRevisionStore();
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			foreach ( $res as $s ) {
				$this->output( "\nr{$s->rev_id} namespace:  {$s->page_namespace} page title: {$s->page_title}" );
				$fCount += $this->doUpdate( $s->page_id, $s->old_text, $s->page_title, $s->rev_id );
			}
			// echo "before" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$start = microtime( true );
			$this->dbw->commit( __METHOD__ );
			echo " committed in " . ( microtime( true ) - $start ) . "s\n\n";
			var_dump( $this->performance );
			// echo "after" +$this->dbw->selectField('mathindex', 'count(*)')."\n";
			$n += $this->chunkSize;
		}
		$this->output( "Updated {$fCount} formulae!\n" );
	}

	/**
	 * @param int $pid
	 * @param string $pText
	 * @param string $pTitle
	 * @param int $revId
	 *
	 * @return number
	 */
	private function doUpdate( $pid, $pText, $pTitle = "", $revId = 0 ) {
		$allFormula = [];

		$notused = '';
		// MathSearchHooks::setNextID($eId);
		$math = MathObject::extractMathTagsFromWikiText( $pText );
		$matches = count( $math );
		if ( $matches ) {
			echo ( "\t processing $matches math fields for {$pTitle} page\n" );
			foreach ( $math as $formula ) {
				$this->time = microtime( true );
				/** @var MathRenderer $renderer */
				$renderer = $this->getServiceContainer()->get( 'Math.RendererFactory' )
					->getRenderer( $formula[1], $formula[2], $this->renderingMode );
				$this->current = $renderer;
				$this->time( "loadClass" );
				if ( $this->getOption( "texvccheck", false ) ) {
					$checked = true;
				} else {
					$checked = $renderer->checkTeX();
					$this->time( "checkTex" );
				}
				if ( $checked ) {
					if ( !$renderer->isInDatabase() || $this->purge ) {
						$renderer->render( $this->purge );
						if ( $renderer->getMathml() ) {
							$this->time( "render" );
						} else {
							$this->time( "Failing" );
						}
						if ( $this->getOption( "SVG", false ) ) {
							$svg = $renderer->getSvg();
							if ( $svg ) {
								$this->time( "SVG-Rendering" );
							} else {
								$this->time( "SVG-Fail" );
							}
						}
					} else {
						$this->time( 'checkInDB' );
					}
				} else {
					$this->time( "checkTex-Fail" );
					echo "\nF:\t\t" . $renderer->getInputHash() . " texvccheck error:" .
						$renderer->getLastError();
					continue;
				}
				$renderer->writeCache();
				$this->time( "write Cache" );
				if ( !$this->getOption( "hooks", false ) ) {
					$hookContainer = $this->getServiceContainer()->getHookContainer();
					$hookContainer->run(
						'MathFormulaPostRender',
						[
							$this->getParser( $revId ),
							&$renderer,
							&$notused
						]
					);
					$this->time( "hooks" );
				} else {
					$eId = null;
					MathSearchHooks::setMathId( $eId, $renderer, $revId );
					MathSearchHooks::writeMathIndex( $revId, $eId, $renderer->getInputHash(), '' );
					$this->time( "index" );
				}
				if ( $renderer->getLastError() ) {
					echo "\n\t\t" . $renderer->getLastError();
					echo "\nF:\t\t" . $renderer->getInputHash() . " equation " . ( $eId ) .
						"-failed beginning with\n\t\t'" . substr( $formula, 0, 100 )
						. "'\n\t\tmathml:" . substr( $renderer->getMathml(), 0, 10 ) . "\n ";
				} else {
					if ( $this->verbose ) {
						echo "\nS:\t\t" . $renderer->getInputHash();
					}
				}
				if ( $this->getOption( "exportmml", false ) ) {
					$allFormula = $this->getMathMLForExport( $formula[1], $renderer, $allFormula );
				}
			}
			$mmlPath = $this->getOption( "exportmml", false );
			if ( $mmlPath ) {
				$this->exportMMLtoFile( $mmlPath, $allFormula, $pTitle );
			}

			return $matches;

		}
		return 0;
	}

	private function getParserOptions(): ParserOptions {
		if ( !$this->parserOptions ) {
			$this->parserOptions = ParserOptions::newFromAnon();
		}
		return $this->parserOptions;
	}

	private function getParser( $revId ): Parser {
		if ( !$this->parser ) {
			$this->parser = $this->getServiceContainer()->getParserFactory()->create();
		}
		// hack to set private field mRevisionId id
		$this->parser->preprocess(
			'',
			null,
			$this->getParserOptions(),
			$revId );
		return $this->parser;
	}

	public function execute() {
		global $wgMathValidModes;
		$this->dbw = $this->getServiceContainer()
			->getConnectionProvider()
			->getPrimaryDatabase();
		$this->purge = $this->getOption( "purge", false );
		$this->verbose = $this->getOption( "verbose", false );
		$this->renderingMode = $this->getOption( "mode", 'latexml' );
		$this->chunkSize = $this->getOption( 'chunk-size', $this->chunkSize );
		$this->db = $this->getServiceContainer()
			->getConnectionProvider()
			->getPrimaryDatabase();
		$wgMathValidModes[] = $this->renderingMode;
		$this->output( "Loaded.\n" );
		$this->time = microtime( true );
		$this->populateSearchIndex( $this->getArg( 0, 0 ), $this->getArg( 1, -1 ) );
	}

	/**
	 * Fetches a MathML entry for exporting formulas from renderer and forms an entry for json export.
	 * @param string $formula formula in tex to save
	 * @param MathRenderer $renderer mathrenderer object which contains mathml
	 * @param array $allFormula array which is filled with formula entries
	 * @return array modified allFormula array
	 */
	public function getMathMLForExport( string $formula, MathRenderer $renderer, array $allFormula ): array {
		if ( $this->verbose ) {
			echo "\n Fetching MML for formula: " . $formula . "\n";
		}
		$mathML = $renderer->getMathml();
		if ( $this->verbose ) {
			echo "\n Input-type is: " . $renderer->getInputType();
			echo "\n MathML is" . substr( $mathML, 0, 50 );
		}
		$allFormula[] = [
			'tex' => $formula,
			'type' => $renderer->getInputType(),
			'mml' => $mathML,
		];
		return $allFormula;
	}

	/**
	 * Writes the MathML content in allFormula to a file named '<mmlPath>/mmlAllResults-<mode>-<pTitle>.json'
	 * @param string $mmlPath path for saving the mathml (without filename)
	 * @param array $allFormula all formula array with mathml for the current page
	 * @param string $pTitle title of page
	 * @return void
	 * @throws InvalidArgumentException when the filepath defined by cli-arg is not a correct folder
	 */
	public function exportMMLtoFile( string $mmlPath, array $allFormula, string $pTitle ): void {
		if ( !is_dir( $mmlPath ) ) {
			throw new InvalidArgumentException( "Filepath for exportmml at not valid at: " . $mmlPath );
		}
		$jsonData = json_encode( $allFormula, JSON_PRETTY_PRINT );
		$fullPath = realpath( $mmlPath ) . DIRECTORY_SEPARATOR . 'mmlRes-' . $this->renderingMode .
			"-" . $pTitle . ".json";
		file_put_contents( $fullPath, $jsonData );
	}
}

$maintClass = UpdateMath::class;
/** @noinspection PhpIncludeInspection */
require_once RUN_MAINTENANCE_IF_MAIN;

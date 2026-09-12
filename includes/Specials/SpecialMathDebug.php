<?php

use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathReferenceData;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\Diff\Diff;
use Wikimedia\Diff\TableDiffFormatter;

class SpecialMathDebug extends SpecialPage {

	public function __construct(
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly RendererFactory $rendererFactory,
	) {
		parent::__construct( 'MathDebug' );
	}

	/**
	 * Sets headers - this should be called from the execute() method of all derived classes!
	 */
	public function setHeaders() {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( "noindex,nofollow" );
		$out->setPageTitle( (string)$this->getDescription() );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$offset = $this->getRequest()->getVal( 'offset', 0 );
		$length = $this->getRequest()->getVal( 'length', 10 );
		$page = $this->getRequest()->getVal( 'page', 'Testpage' );
		$action = $this->getRequest()->getVal( 'action', 'show' );
		$purge = $this->getRequest()->getVal( 'purge', '' );
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
		} else {
			if ( !in_array( $action, [ 'generateParserTests', 'visualDiff' ] ) ) {
				$this->setHeaders();
				$this->displayButtons( $offset, $length, $page, $action, $purge );
			}
			switch ( $action ) {
				case 'parserTest':
					$this->generateLaTeXMLOutput( $offset, $length, $page );
					break;
				case 'parserDiff':
					$this->compareParser( $offset, $length, $page );
					break;
				case 'generateParserTests':
					$this->generateParserTests( $offset, $length, $page );
					break;
				case 'visualDiff':
					$this->setHeaders();
					$this->visualDiff();
					break;
				default:
					$this->testParser( $offset, $length, $page, $purge === 'checked' );
			}
			$this->getOutput()->addModuleStyles( [ 'ext.math.styles' ] );
		}
	}

	private function displayButtons(
		int $offset = 0, int $length = 10, string $page = 'Testpage', string $action = 'show', string $purge = ''
	) {
		$out = $this->getOutput();
		// TODO check if addHTML has to be sanitized
		$out->addHTML( '<form method=\'get\'>'
			. '<input value="Show :" type="submit">'
			. ' <input name="length" size="3" value="'
			. $length
			. '" class="textfield"  onfocus="this.select()" type="text">'
			. ' test(s) starting from test # <input name="offset" size="6" value="'
			. ( $offset + $length )
			. '" class="textfield" onfocus="this.select()" type="text"> for page'
			. ' <input name="page" size="12" value="'
			. $page
			. '" class="textfield" onfocus="this.select()" type="text">'
			. ' <input name="action" size="12" value="'
			. $action
			. '" class="textfield" onfocus="this.select()" type="text">'
			. ' purge <input type="checkbox" name="purge" value="checked"'
			. $purge
			. '></form>'
		);
	}

	public function compareParser( $offset = 0, $length = 10, $page = 'Testpage' ) {
		// phpcs:ignore MediaWiki.Usage.ExtendClassUsage.FunctionConfigUsage
		global $wgMathLaTeXMLUrl;
		$out = $this->getOutput();
		if ( !$this->getConfig()->get( 'MathUseLaTeXML' ) ) {
			$out->addWikiTextAsInterface( "MahtML support must be enabled." );
			return false;
		}
		$parserA = $this->getRequest()->getVal( 'parserA', 'http://latexml.mathweb.org/convert' );
		$parserB = $this->getRequest()->getVal( 'parserB', 'http://latexml-test.instance-proxy.wmflabs.org/' );
		$formulae = self::getMathTagsFromPage( $page );
		$i = 0;
		$str_out = '';
		$renderer = new MathLaTeXML();
		$renderer->setPurge();
		$diffFormatter = new TableDiffFormatter();
		if ( count( $formulae ) ) {
			foreach ( array_slice( $formulae, $offset, $length, true ) as $key => $formula ) {
				$out->addWikiTextAsInterface( "=== Test #" . ( $offset + $i++ ) . ": $key === " );
				$renderer->setTex( $formula );
				$wgMathLaTeXMLUrl = $parserA;
				$stringA = $renderer->render( true );
				$wgMathLaTeXMLUrl = $parserB;
				$stringB = $renderer->render( true );
				$diff = new Diff( [ $stringA ], [ $stringB ] );
				if ( $diff->isEmpty() ) {
					$out->addWikiTextAsInterface( 'Output is identical' );
				} else {
					$out->addWikiTextAsInterface( 'Request A <source lang="bash"> curl -d \'' .
						$renderer->getPostData() . '\' ' . $parserA . '</source>' );
					$out->addWikiTextAsInterface( 'Request B <source lang="bash"> curl -d \'' .
						$renderer->getPostData() . '\' ' . $parserB . '</source>' );
					$out->addWikiTextAsInterface(
						'Diff: <source lang="diff">' . $diffFormatter->format( $diff ) . '</source>'
					);
					$out->addWikiTextAsInterface( 'XML Element based:' );
					$XMLA = explode( '>', $stringA );
					$XMLB = explode( '>', $stringB );
					$diff = new Diff( $XMLA, $XMLB );
					$out->addWikiTextAsInterface(
						'<source lang="diff">' . $diffFormatter->format( $diff ) . '</source>'
					);
				}
				$i++;
			}
		} else {
			$str_out = "No math elements found";
		}
		$out->addWikiTextAsInterface( $str_out );
		return true;
	}

	public function testParser( $offset = 0, $length = 10, $page = 'Testpage', $purge = true ) {
		$out = $this->getOutput();
		$i = 0;
		foreach (
			array_slice( self::getMathTagsFromPage( $page ), $offset, $length, true ) as $key => $t
		) {
			$out->addWikiTextAsInterface( "=== Test #" . ( $offset + $i++ ) . ": $key === " );
			$out->addHTML( $this->render( $t, 'source', $purge ) );
			$out->addWikiTextAsInterface(
				'Texvc`s TeX output:<source lang="latex">' . $this->getTexvcTex( $t ) . '</source>'
			);
			if ( in_array( 'latexml', $this->getConfig()->get( 'MathValidModes' ) ) ) {
				$out->addHTML( $this->render( $t, 'latexml', $purge ) );
			}
		}
	}

	/**
	 * Generates test cases for texvcjs
	 *
	 * @param int $offset
	 * @param int $length
	 * @param string $page
	 * @param bool $purge
	 * @return bool
	 */
	public function generateParserTests(
		$offset = 0, $length = 10, $page = 'Testpage', $purge = true
	) {
		$res = $this->getRequest()->response();
		$res->header( 'Content-Type: application/json' );
		$res->header( 'Content-Disposition: attachment;filename=ParserTest.json' );

		$out = $this->getOutput();
		$out->setArticleBodyOnly( true );
		$parserTests = [];
		foreach (
			array_slice( self::getMathTagsFromPage( $page ), $offset, $length, true ) as $key => $input
		) {
			$m = new MathMathML( $input );
			$m->checkTeX();
			$parserTests[] = [ 'id' => $key, 'input' => (string)$input, 'texvcjs' => $m->getTex() ];
		}
		$out->addHTML( json_encode( $parserTests ) );
		return true;
	}

	private function generateLaTeXMLOutput( int $offset = 0, int $length = 10, string $page = 'Testpage' ): bool {
		$out = $this->getOutput();
		if ( !$this->getConfig()->get( 'MathUseLaTeXML' ) ) {
			$out->addWikiTextAsInterface( "MahtML support must be enabled." );
			return false;
		}

		$formulae = self::getMathTagsFromPage( $page );
		$i = 0;
		$renderer = new MathLaTeXML();
		$renderer->setPurge();
		$tstring = '';
		if ( count( $formulae ) ) {
			foreach ( array_slice( $formulae, $offset, $length, true ) as $key => $formula ) {
				$tstring .= "\n!! test\n Test #" . ( $offset + $i++ ) . ": $key \n!! input"
					. "\n<math>$formula</math>\n!! result\n";
				$renderer->setTex( $formula );
				$tstring .= $renderer->render( true );
				$tstring .= "\n!! end\n";
			}
		} else {
			$tstring = "No math elements found";
		}
		$out->addWikiTextAsInterface( '<source>' . $tstring . '<\source>' );
		return true;
	}

	private function render( string $t, string $mode, bool $purge = true ): string {
		$modeInt = (int)substr( $mode, 0, 1 );
		$renderer = $this->rendererFactory->getRenderer( $t, [], $modeInt );
		$renderer->setPurge( $purge );
		$renderer->render();
		$fragment = $renderer->getHtmlOutput();
		$res = $mode . ':' . $fragment;
		LoggerFactory::getInstance( 'MathSearch' )->warning( 'rendered:' . $res . ' in mode ' . $mode );
		return $res . '<br/>';
	}

	private static function getMathTagsFromPage( string $titleString = 'Testpage' ): array {
		$title = Title::newFromText( $titleString );
		if ( $title->exists() ) {
			$idGenerator = MathIdGenerator::newFromTitle( $title );
			$tags = $idGenerator->getMathTags();
			$keys = $idGenerator->formatIds( $tags );
			return array_combine( $keys, array_column( $tags, MathIdGenerator::CONTENT_POS ) );
		} else {
			return [];
		}
	}

	/** @return string HTML, empty unless the entry names a task */
	private static function taskLink( ?array $ref, ?array $master ): string {
		$task = $ref['bug'] ?? $master['bug'] ?? null;
		if ( !is_string( $task ) || !preg_match( '/^T\d+$/', $task ) ) {
			return '';
		}
		return ' Task: <a href="https://phabricator.wikimedia.org/' . $task . '">' .
			$task . '</a>';
	}

	/** MathJax typesets every math element that is not marked, including the references. */
	private static function ignoreInMathJax( string $html ): string {
		return preg_replace_callback(
			'/<math\b([^>]*)>/i',
			static function ( array $m ): string {
				if ( preg_match( '/\sclass\s*=\s*(["\'])(.*?)\1/i', $m[1], $class ) ) {
					if ( preg_match( '/(^|\s)mathjax_ignore(\s|$)/', $class[2] ) ) {
						return $m[0];
					}
					return str_replace(
						$class[0],
						' class=' . $class[1] . $class[2] . ' mathjax_ignore' . $class[1],
						$m[0]
					);
				}
				return '<math class="mathjax_ignore"' . $m[1] . '>';
			},
			$html
		);
	}

	/** @return string HTML, the same input as each pipeline draws it */
	private function renderComparison( string $tex ): string {
		return '<div class="math-diff">' .
			'<div class="math-diff-master"><h4>server SVG</h4>' .
			self::ignoreInMathJax( $this->renderInMode( $tex, MathConfig::MODE_MATHML ) ) .
			'</div><div class="math-diff-ref"><h4>client MathJax</h4>' .
			$this->renderInMode( $tex, MathConfig::MODE_NATIVE_JAX ) .
			'</div></div>';
	}

	private function renderInMode( string $tex, string $mode ): string {
		try {
			$renderer = $this->rendererFactory->getRenderer( $tex, [], $mode );
			$renderer->render();
			return $renderer->getHtmlOutput();
		} catch ( Exception $e ) {
			return '<em>' . htmlspecialchars( $e->getMessage() ) . '</em>';
		}
	}

	private function getTexvcTex( string $tex ): string {
		$renderer = $this->rendererFactory->getRenderer( $tex, [], 'source' );
		$renderer->checkTeX();
		return $renderer->getTex();
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}

	/**
	 * Fetch a base64-encoded JSON file from the given gitiles URL and return it as an array.
	 * Returns null on any failure (HTTP error, base64 decode error, JSON parse error).
	 *
	 * @param string $url
	 * @return array|null
	 */
	private function fetchJsonFromGitiles( string $url ): ?array {
		try {
			$body = $this->httpRequestFactory->get( $url );
		} catch ( Exception $e ) {
			return null;
		}

		if ( $body === false ) {
			return null;
		}

		$decoded = base64_decode( trim( $body ) );
		if ( $decoded === false ) {
			return null;
		}

		$data = json_decode( $decoded, true );
		if ( !is_array( $data ) ) {
			return null;
		}

		return $data;
	}

	/** Index output variants by hash and parameters while retaining their display positions. */
	private function indexTestsByReferenceHash( array $references ): array {
		if ( array_is_list( $references ) ) {
			// Keep supporting the legacy list format to compare revisions from before the hash format was introduced.
			$references = MathReferenceData::groupCases( $references );
		}

		$indexed = [];
		$referencePosition = 0;
		foreach ( $references as $hash => $reference ) {
			$referencePosition++;
			$input = $reference['input'];
			$hasOutputList = array_key_exists( 'outputs', $reference );
			$outputs = $reference['outputs'] ?? [ array_diff_key( $reference, [ 'input' => true ] ) ];
			foreach ( $outputs as $outputIndex => $output ) {
				$params = $output['params'] ?? [];
				ksort( $params );
				// MathReferenceData groups at most one output for each parameter set.
				$key = $hash . ':' . serialize( $params );
				$indexed[$key] = [
					'position' => (string)$referencePosition .
						( $hasOutputList ? '.' . ( $outputIndex + 1 ) : '' ),
					'hash' => (string)$hash,
					'test' => [ 'input' => $input ] + $output,
				];
			}
		}
		return $indexed;
	}

	private function visualDiff() {
		$out = $this->getOutput();
		$refHash = $this->getRequest()->getVal( 'ref' );
		$masterHash = $this->getRequest()->getVal( 'base', 'refs/heads/master' );
		$withSvg = $this->getRequest()->getVal( 'svg', 'diff' ) !== 'none';
		$out->addModuleStyles( [ 'ext.mathsearch.styles' ] );
		if ( $withSvg ) {
			// The client column is rendered by MathJax in the browser, which is
			// the only place the mmlFilter polyfills actually run.
			$out->addModules( [ 'ext.math.mathjax' ] );
		}

		$relativePath = 'tests/phpunit/integration/WikiTexVC/data/reference.json';
		$baseUrl = 'https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/Math/+/';

		$masterUrl = $baseUrl . $masterHash . '/' . $relativePath . '?format=TEXT';
		$refUrl = $baseUrl . $refHash . '/' . $relativePath . '?format=TEXT';

		if ( !$refHash ) {
			$refData = json_decode( file_get_contents( __DIR__ . '/../../../Math/' . $relativePath, 'r' ), true );
			$refHash = 'local file';
		} else {
			$refData = $this->fetchJsonFromGitiles( $refUrl );
		}

		// Use helper to fetch and decode JSON content for master and ref
		$masterData = $this->fetchJsonFromGitiles( $masterUrl );

		if ( $masterData === null || $refData === null ) {
			$out->addWikiTextAsInterface( 'Failed to fetch or decode one or both files from gitiles.' );
			return;
		}

		if ( !is_array( $masterData ) || !is_array( $refData ) ) {
			$out->addWikiTextAsInterface( 'Expected JSON arrays in both master and ref.' );
			return;
		}

		$out->addHTML( '<p>base <code>' . htmlspecialchars( $masterHash ) .
			'</code> against ref <code>' . htmlspecialchars( $refHash ) . '</code></p>' );

		$masterTests = $this->indexTestsByReferenceHash( $masterData );
		$refTests = $this->indexTestsByReferenceHash( $refData );
		$testKeys = array_unique( array_merge( array_keys( $masterTests ), array_keys( $refTests ) ) );
		foreach ( $testKeys as $key ) {
			$masterEntry = $masterTests[$key] ?? null;
			$refEntry = $refTests[$key] ?? null;
			$master = $masterEntry['test'] ?? null;
			$ref = $refEntry['test'] ?? null;
			if ( $master !== $ref ) {
				$position = $refEntry['position'] ?? $masterEntry['position'];
				$inputHash = $refEntry['hash'] ?? $masterEntry['hash'];
				$inMaster = $master['input'] ?? '';
				$inRef = $ref['input'] ?? '';
				if ( $inMaster !== '' && $inMaster === $inRef ) {
					$out->addWikiTextAsInterface( "== Difference at position {$position} ==" );
				} elseif ( $inMaster === '' ) {
					$out->addWikiTextAsInterface( "== New test at position {$position} ==" );
				} else {
					$out->addWikiTextAsInterface( "== Removed test at position {$position} ==" );
				}
				$input = $inRef !== '' ? $inRef : $inMaster;
				$out->addHTML(
					'<p>Input: <code>' . htmlspecialchars( $input ) . '</code><br>' .
					'Input hash: <code>' . htmlspecialchars( $inputHash ) . '</code>' .
					self::taskLink( $ref, $master ) . '</p>'
				);
				if ( $withSvg ) {
					$out->addHTML( $this->renderComparison( $input ) );
				}
				// If both have an 'output' field, and it differs, render the MathML / HTML raw
				$outMaster = is_array( $master ) && array_key_exists( 'output', $master );
				$outRef = is_array( $ref ) && array_key_exists( 'output', $ref );
				if ( $outMaster && $outRef && $master['output'] !== $ref['output'] ) {
					$out->addHTML(
						'<div class="math-diff"><div class="math-diff-master"><h4>base</h4>' .
						self::ignoreInMathJax( $master['output'] ) .
						'</div><div class="math-diff-ref"><h4>ref</h4>' .
						self::ignoreInMathJax( $ref['output'] ) .
						'</div></div>'
					);
				} elseif ( !$outMaster && $outRef ) {
					$out->addHTML(
						'<div class="math-diff"><div class="math-diff-master">' .
						'<h4>base</h4><em>new</em></div>' .
						'<div class="math-diff-ref"><h4>ref</h4>' .
						self::ignoreInMathJax( $ref['output'] ) .
						'</div></div>'
					);
				} else {
					$out->addHTML(
						'<h4>base</h4><pre>' .
						htmlspecialchars( json_encode( $master, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) .
						'</pre>'
					);
					$out->addHTML(
						'<h4>ref</h4><pre>' .
						htmlspecialchars( json_encode( $ref, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) .
						'</pre>'
					);
				}
			}
		}
	}
}

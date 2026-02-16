<?php

use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\Diff\Diff;
use Wikimedia\Diff\TableDiffFormatter;

class SpecialMathDebug extends SpecialPage {

	public function __construct( private readonly HttpRequestFactory $httpRequestFactory ) {
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
			$out->addHTML( self::render( $t, 'source', $purge ) );
			$out->addWikiTextAsInterface(
				'Texvc`s TeX output:<source lang="latex">' . $this->getTexvcTex( $t ) . '</source>'
			);
			if ( in_array( 'latexml', $this->getConfig()->get( 'MathValidModes' ) ) ) {
				$out->addHTML( self::render( $t, 'latexml', $purge ) );
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

	private static function render( string $t, string $mode, bool $purge = true ): string {
		$modeInt = (int)substr( $mode, 0, 1 );
		$renderer = MathRenderer::getRenderer( $t, [], $modeInt );
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

	private function getTexvcTex( string $tex ): string {
		$renderer = MathRenderer::getRenderer( $tex, [], 'source' );
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

	private function visualDiff() {
		$out = $this->getOutput();
		$refHash = $this->getRequest()->getVal( 'ref' );
		$masterHash = $this->getRequest()->getVal( 'base', 'refs/heads/master' );

		$relativePath = 'tests/phpunit/integration/WikiTexVC/data/reference.json';
		$baseUrl = 'https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/Math/+/';

		if ( !$refHash ) {
			$out->addWikiTextAsInterface( "Please provide a ref parameter (commit hash) to compare against master." );
			return;
		}

		$masterUrl = $baseUrl . $masterHash . '/' . $relativePath . '?format=TEXT';
		$refUrl = $baseUrl . $refHash . '/' . $relativePath . '?format=TEXT';

		// Use helper to fetch and decode JSON content for master and ref
		$masterData = $this->fetchJsonFromGitiles( $masterUrl );
		$refData = $this->fetchJsonFromGitiles( $refUrl );

		if ( $masterData === null || $refData === null ) {
			$out->addWikiTextAsInterface( 'Failed to fetch or decode one or both files from gitiles.' );
			return;
		}

		if ( !is_array( $masterData ) || !is_array( $refData ) ) {
			$out->addWikiTextAsInterface( 'Expected JSON arrays in both master and ref.' );
			return;
		}

		$max = max( count( $masterData ), count( $refData ) );
		for ( $i = 0; $i < $max; $i++ ) {
			$master = $masterData[$i] ?? null;
			$ref = $refData[$i] ?? null;
			if ( $master !== $ref ) {
				$inMaster = $master['input'] ?? '';
				$inRef = $ref['input'] ?? '';
				// Prepare 20-char snippets for the title; if inputs differ show "master vs ref"
				$snipMaster = htmlspecialchars( mb_substr( $inMaster, 0, 20 ) );
				$snipRef = htmlspecialchars( mb_substr( $inRef, 0, 20 ) );
				if ( $inMaster !== '' && $inMaster === $inRef ) {
					$out->addWikiTextAsInterface( "== Difference at index {$i}: {$snipMaster} ==" );
				} elseif ( $inMaster === '' ) {
					$out->addWikiTextAsInterface( "== New test at index {$i}: {$snipRef}  ==" );
				} else {
					$out->addWikiTextAsInterface( "== Difference at index {$i}: {$snipMaster} vs {$snipRef} ==" );
				}
				// If both have an 'output' field, and it differs, render the MathML / HTML raw
				$outMaster = is_array( $master ) && array_key_exists( 'output', $master );
				$outRef = is_array( $ref ) && array_key_exists( 'output', $ref );
				if ( $outMaster && $outRef && $master['output'] !== $ref['output'] ) {
					$out->addHTML(
						'<div class="math-diff"><div class="math-diff-master"><h4>master</h4>' .
						$master['output'] .
						'</div><div class="math-diff-ref"><h4>ref ' . htmlspecialchars( $refHash ) . '</h4>' .
						$ref['output'] .
						'</div></div>'
					);
				} elseif ( !$outMaster && $outRef ) {
					$out->addHTML(
						'<div class="math-diff"><div class="math-diff-master"><h4>master</h4><em>new</em></div>' .
						'<div class="math-diff-ref"><h4>ref ' . htmlspecialchars( $refHash ) . '</h4>' .
						$ref['output'] .
						'</div></div>'
					);
				} else {
					$out->addHTML(
						'<h4>master</h4><pre>' .
						htmlspecialchars( json_encode( $master, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) .
						'</pre>'
					);
					$out->addHTML(
						'<h4>ref ' . htmlspecialchars( $refHash ) . '</h4><pre>' .
						htmlspecialchars( json_encode( $ref, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) .
						'</pre>'
					);
				}
			}
		}
	}
}

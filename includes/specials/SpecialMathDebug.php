<?php

use MediaWiki\Logger\LoggerFactory;

class SpecialMathDebug extends SpecialPage {

	function __construct() {
		parent::__construct( 'MathDebug' );
	}

	/**
	 * Sets headers - this should be called from the execute() method of all derived classes!
	 */
	function setHeaders() {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( "noindex,nofollow" );
		$out->setPageTitle( $this->getDescription() );
	}

	function execute( $par ) {
		global $wgRequest;
		$offset = $wgRequest->getVal( 'offset', 0 );
		$length = $wgRequest->getVal( 'length', 10 );
		$page = $wgRequest->getVal( 'page', 'Testpage' );
		$action = $wgRequest->getVal( 'action', 'show' );
		$purge = $wgRequest->getVal( 'purge', '' );
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
		} else {
			if ( $action != 'generateParserTests' ) {
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
				default:
					$this->testParser( $offset, $length, $page, $purge == 'checked' ? true : false );
			}
		}
	}

	function displayButtons(
		$offset = 0, $length = 10, $page = 'Testpage', $action = 'show', $purge = ''
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
		global $wgMathUseLaTeXML, $wgRequest, $wgMathLaTeXMLUrl;
		$out = $this->getOutput();
		if ( !$wgMathUseLaTeXML ) {
			$out->addWikiTextAsInterface( "MahtML support must be enabled." );
			return false;
		}
		$parserA = $wgRequest->getVal( 'parserA', 'http://latexml.mathweb.org/convert' );
		$parserB = $wgRequest->getVal( 'parserB', 'http://latexml-test.instance-proxy.wmflabs.org/' );
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
		global $wgMathMathValidModes;
		$out = $this->getOutput();
		$i = 0;
		foreach (
			array_slice( self::getMathTagsFromPage( $page ), $offset, $length, true ) as $key => $t
		) {
			$out->addWikiTextAsInterface( "=== Test #" . ( $offset + $i++ ) . ": $key === " );
			$out->addHTML( self::render( $t, 'source', $purge ) );
			$out->addHTML( self::render( $t, 'png', $purge ) );
			$out->addWikiTextAsInterface(
				'Texvc`s TeX output:<source lang="latex">' . $this->getTexvcTex( $t ) . '</source>'
			);
			if ( in_array( 'latexml', $wgMathMathValidModes ) ) {
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

	function generateLaTeXMLOutput( $offset = 0, $length = 10, $page = 'Testpage' ) {
		global $wgMathUseLaTeXML;
		$out = $this->getOutput();
		if ( !$wgMathUseLaTeXML ) {
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

	private static function render( $t, $mode, $purge = true ) {
		$modeInt = (int)substr( $mode, 0, 1 );
		$renderer = MathRenderer::getRenderer( $t, [], $modeInt );
		$renderer->setPurge( $purge );
		$renderer->render();
		$fragment = $renderer->getHtmlOutput();
		$res = $mode . ':' . $fragment;
		LoggerFactory::getInstance( 'MathSearch' )->warning( 'rendered:' . $res . ' in mode ' . $mode );
		return $res . '<br/>';
	}

	private static function getMathTagsFromPage( $titleString = 'Testpage' ) {
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

	private function getTexvcTex( $tex ) {
		$renderer = MathRenderer::getRenderer( $tex, [], 'source' );
		$renderer->checkTeX();
		return $renderer->getTex();
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}

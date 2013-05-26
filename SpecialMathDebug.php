<?php
class SpecialMathDebug extends SpecialPage {


	function __construct() {
		parent::__construct( 'MathDebug', 'edit', true );
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
		global $wgDebugMath, $wgRequest;
		$output = $this->getOutput();
		$this->setHeaders();
		$offset = $wgRequest->getVal( 'offset', 0 );
		$length = $wgRequest->getVal( 'length', 10 );
		$page = $wgRequest->getVal( 'page', 'Testpage' );
		$action = $wgRequest->getVal( 'action', 'show' );
		if (  !$this->userCanExecute( $this->getUser() )  ) {
			$this->displayRestrictionError();
			return;
		} else {
			if ( $action == 'parserTest' ) {
				$out = $this->generateLaTeXMLOutput( $offset, $length, $page );
				return;
			} else {
				$this->displayButtons( $offset, $length, $page );
				$this->testParser( $offset, $length, $page );
			}
		}
	}
	function displayButtons( $offset = 0, $length = 10 ) {
		$out = $this->getOutput();
		$out->addHTML( '<form method=\'get\'>'
			. '<input value="Show :" type="submit">'
			. ' <input name="length" size="3" value="'
			. $length
			. '" class="textfield"  onfocus="this.select()" type="text">'
			. ' test(s) starting from test # <input name="offset" size="6" value="'
			. ( $offset + $length )
			. '" class="textfield" onfocus="this.select()" type="text"></form>'
			);
	}
	function compareParser() {
		$out = $this->getOutput();
		$i = 0;
		$ans = self::getAnswers();
		foreach ( self::getMathTagsFromPage() as $key => $t ) {
			if ( self::render( $t, MW_MATH_LATEXML ) == $ans[$i] ) {
				// $out->addWikiText('Success for '.$i);
			} else {
				$out->addWikiText( 'Fail for ' . $i );
			}
			$i++;
		}
	}
	function testParser( $offset = 0, $length = 10, $page = 'Testpage' ) {
		global $wgUseMathJax, $wgUseLaTeXML;
		$out = $this->getOutput();
		$out->addModules( array( 'ext.math.mathjax.enabler' ) );
		$i = 0;
		foreach ( array_slice( self::getMathTagsFromPage( $page ), $offset, $length, true ) as $key => $t ) {
			$out->addWikiText( "=== Test #" . ( $offset + $i++ ) . ": $key === " );
			$out->addHTML( self::render( $t, MW_MATH_SOURCE ) );
			$out->addHTML( self::render( $t, MW_MATH_PNG ) );
			if ( $wgUseLaTeXML ) {
				$out->addHTML( self::render( $t, MW_MATH_LATEXML ) );
			}
			if ( $wgUseMathJax ) {
				$out->addHTML( self::render( $t, MW_MATH_MATHJAX, false ) );
			}
		}
	}

	function generateLaTeXMLOutput( $offset = 0, $length = 10, $page = 'Testpage' ) {
		global $wgUseLaTeXML;
		$out = $this->getOutput();
		if ( !$wgUseLaTeXML ) {
			$out->addWikiText( "MahtML support must be enabled." );
			return false;
		}

		$formulae = self::getMathTagsFromPage( $page );
		$i = 0;
		if ( is_array( $formulae ) ) {
			$tstring = '<source>';
		foreach ( array_slice( $formulae, $offset, $length, true ) as $key => $formula ) {
			$tstring .= "\n!! test\n Test #" . ( $offset + $i++ ) . ": $key \n!! input"
				. "\n$formula\n!! output\n";
			$tstring .=  MathRenderer::renderMath( $formula, array(), MW_MATH_LATEXML ) ;
			$tstring .= "!! end\n";
		}
		$tstring .= '</source>';
		} else {
			$tstring = "No math elements found";
		}

		$out->addWikiText( $tstring );
		return true;
	}
	private static function render( $t, $mode, $aimJax = true ) {
		$res = $mode . ':' . MathRenderer::renderMath( $t, array(), $mode );
		if ( $aimJax ) {
			self::aimHTMLFromJax( $res );
		}
		return $res . '<br/>';
	}
	private static function aimHTMLFromJax( &$s ) {
		$s = str_replace( 'class="tex"', 'class="-NO-JAX-"', $s );
		return $s;
	}

	private static function getMathTagsFromPage( $titleString = 'Testpage' ) {
		$title = Title::newFromText( $titleString );
		$article = new Article( $title );
		// TODO: find a better way to extract math elements from a page
		$wikiText = $article->getPage()->getContent()->getNativeData();
		$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
		$wikiText = preg_replace( '#<nowiki>(.*)</nowiki>#', '', $wikiText );
		$matches = preg_match_all( "#<math>(.*?)</math>#s", $wikiText,  $math );
		// TODO: Find a way to specify a key e.g '\nRenderTest:(.?)#<math>(.*?)</math>#s\n'
		// leads to array('\1'->'\2') with \1 eg Bug 2345 and \2 the math content
		return $math[0];
	}
}
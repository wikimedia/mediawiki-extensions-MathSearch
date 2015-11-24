<?php

use MediaWiki\Logger\LoggerFactory;

/**
 * MediaWiki MathSearch extension
 *
 * (c) 2015 Moritz Schubotz
 * GPLv2 license; info in main package.
 *
 * @file
 * @ingroup extensions
 */
class SpecialMlpEval extends SpecialPage {
	const MAX_ATTEMPTS = 10;
	const WINDOW_SIZE = 1200;
	const OPT_CONTINUE = 0;
	const OPT_BACK = 1;
	const OPT_RETRY = 2;
	private $step;
	private $htmlForm;
	/**
	 * @var Title
	 */
	private $title;
	private $wikitext;
	private $snippet;
	private $mathTags;
	private $revison;
	private $lastError = false;
	private $ready=false;
	private $sessionTime;

	function __construct() {
		parent::__construct( 'MlpEval' );
		$this->sessionTime = time();
	}

	/**
	 * The main function
	 */
	public function execute( $par ) {
		$req = $this->getRequest();
		$this->setHeaders();
		$this->step = $req->getInt( 'step', 1 );
		$this->runStep( $req );
		$this->printSource( var_export( $req, true ) );
	}

	/**
	 * @param $req
	 */
	private function runStep( WebRequest $req ) {
		switch ( $this->step ) {
			case 1:
				$this->displayPageSelectionForm();
				break;
			case 2:
				$title = Title::newFromText( $req->getVal( "wpevalPage" ) );
				$this->setPage( $title );
				$this->displaySelectFormulaForm();
				break;
			case 3;
				switch ( $req->getInt( 'wpsnippetSelector' ) ){
					case self::OPT_BACK;
						$this->step = 1;
						$this->runStep( $req );
						break;
					case self::OPT_RETRY:
						$this->step = 2;
						$this->runStep( $req );
						break;
					case self::OPT_CONTINUE:
						$this->displayEvaluationForm();
						$this->getOutput()->addWikiText( "You made it. All done!".
							$req->getVal( 'wpsnippetSelector' ) );
						break;
					default:
				}
				break;
		}

	}

	private function displayPageSelectionForm() {
		$formDescriptor = array();
		$this->getOutput()->addModules( 'ext.MathSearch.special' );
		$formDescriptor['evalPage'] = array(
				'label'   => 'Page to evaluate',
				'class'   => 'HTMLTextField',
				'default' => $this->getRandomPage()
		);

		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Select' );
		$htmlForm->addHiddenField( 'step', 2 );
		$htmlForm->setSubmitCallback( array( $this, 'processPageInput' ) );
		$htmlForm->setHeaderText( "<h2>Step 1: Select a page</h2>" );
		$htmlForm->prepareForm();
		$htmlForm->displayForm( '' );

	}

	private function getRandomPage() {
		$rp = new RandomPage();
		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i ++ ) {
			$title = $rp->getRandomTitle();
			if ( $this->setPage( $title ) ) {
				$this->lastError = "";
				return $title;
			}
		}
		$this->log()->warning( "Could not find suitable Page with math", $this->lastError );
		$this->lastError = "";
		return "";
	}

	private function setPage( Title $title ) {
		if ( is_null( $title ) ) {
			$this->lastError = "Title was null.";
			return false;
		}
		if ( $title->isRedirect() ) {
			$this->lastError = "Redirects are not supported";
			return false;
		}
		$revision = $title->getLatestRevID();
		if ( $revision ) {
			$retVal = $this->setRevision( $revision );
			if ( $retVal ) {
				$this->title = $title;
			}
			return $retVal;
		} else {
			$this->lastError = "invalid revision";
			return false;
		}
	}

	/**
	 * @param $revisionId
	 * @return bool
	 * @throws MWException
	 */
	private function setRevision( $revisionId ) {
		$revisionId = Revision::newFromId( $revisionId );
		if ( $revisionId->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			$this->lastError = "Has invalid format";
			return false;
		}
		$handler = new WikitextContentHandler();
		$wikiText = $handler->getContentText( $revisionId->getContent() );
		$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
		// Remove <nowiki /> tags to avoid confusion
		$wikiText = preg_replace( '#<nowiki>(.*)</nowiki>#', '', $wikiText );
		$mathTags = null;
		$wikiText = Parser::extractTagsAndParams( array( 'math' ), $wikiText, $mathTags );
		$tagCount = count( $mathTags );
		if ( $tagCount == 0 ) {
			$this->lastError = "has no math tags";
			return false;
		}
		$this->wikitext = $wikiText;
		$this->mathTags = $mathTags;
		return true;
	}

	/**
	 * @return \Psr\Log\LoggerInterface
	 */
	private function log() {
		return LoggerFactory::getInstance( 'MathSearch' );
	}

	private function displaySelectFormulaForm() {
		$formDescriptor = array();
		$this->enableMathStyles();
		$formDescriptor['snippetSelector'] = array(
				'type' => 'radio',
				'label' => 'Page to evaluate',
				'options' => array(
						'Continue with this snippet' => self::OPT_CONTINUE,
						'Select another snippet from that page' => self::OPT_RETRY,
						'Go Back to page selection' => self::OPT_BACK
				),
				'default' => self::OPT_CONTINUE # The option selected by default (identified by value)
		);
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Continue' );
		$htmlForm->addHiddenField( 'step', 3 );
		$htmlForm->addHiddenField( 'wpevalPage', $this->title->getText() );
		$htmlForm->setSubmitCallback( array( $this, 'processFormulaInput' ) );
		$htmlForm->setHeaderText( "<h2>Step 2: Select a formula</h2>" );
		$htmlForm->prepareForm();
		$htmlForm->displayForm( '' );
		$this->displaySnipped();
	}

	private function enableMathStyles() {
		$out = $this->getOutput();
		$out->addModuleStyles(
			array( 'ext.math.styles', 'ext.math.desktop.styles', 'ext.math.scripts' )
		);
	}

	/**
	 * @return array
	 * @throws MWException
	 */
	private function displaySnipped() {
		$tagCount = count( $this->mathTags );
		$this->getOutput()->addWikiText( "Found $tagCount math tags." );
		$unique = array_rand( $this->mathTags );
		$tag = $this->mathTags[$unique];
		$formDescriptor = array();
		$this->getOutput()->addWikiText( $tag[3] );
		$tagPos = strpos( $this->wikitext, $unique );
		$wikiText = $this->wikitext;
		$startPos = $this->getStartPos( $tagPos, $wikiText );
		$length = $this->getEndPos( $tagPos, $wikiText ) - $startPos;
		$wikiText = substr( $wikiText, $startPos, $length );
		$wikiText = str_replace( $unique,
			'<span id="theelement" style="background-color: yellow">' . $tag[3] . '</span>',
			$wikiText );
		foreach ( $this->mathTags as $key => $content ) {
			$wikiText = str_replace( $key, $content[3], $wikiText );
		}
		$this->snippet = "== Extract ==\nStart of the extract...\n\n$wikiText\n\n...end of the extract";
		$this->getOutput()->addWikiText( $this->snippet );
		$url = $this->title->getLinkURL();
		$this->getOutput()
			->addHTML( "<a href=\"$url\" target=\"_blank\">Full article (new Window)</a>" );
		return array( $tagCount, $formDescriptor, $wikiText );
	}

	/**
	 * @param $tagPos
	 * @param $wikiText
	 * @return array
	 */
	private function getStartPos( $tagPos, $wikiText ) {
		$startPos = max( $tagPos - round( self::WINDOW_SIZE / 2 ), 0 );
		if ( $startPos > 0 ) {
			// Heuristics to find a reasonable cutting point
			$newPos = strpos( $wikiText, "\n", $startPos );
			if ( $newPos !== false && ( $newPos - $startPos ) < round( self::WINDOW_SIZE / 4 ) ) {
				// only change startPos, if it seems reasonable
				$startPos = $newPos;
			}
		}
		return $startPos;
	}

	/**
	 * @param $wikiText
	 * @param $tagPos
	 * @return bool|int|mixed
	 */
	private function getEndPos( $tagPos, $wikiText ) {
		$halfWindow = round( self::WINDOW_SIZE / 2 );
		$distance2End = strlen( $wikiText ) - $tagPos;
		if ( $distance2End > $halfWindow ) {
			$newPos = strpos( $wikiText, "\n", $tagPos + $halfWindow );
			if ( $newPos !== false && ( $newPos - $tagPos ) < round( 3 / 4 * self::WINDOW_SIZE ) ) {
				// only change startPos, if it seems reasonable
				return $newPos;
			} else {
				return $tagPos + $halfWindow;
			}
		} else {
			return strlen( $wikiText );
		}
	}

	/**
	 *
	 * @param String $src
	 * @param String $lang the language of the source snippet
	 */
	private function printSource( $src, $lang = "xml" ) {
		$out = $this->getOutput();
		$out->addWikiText( '<source lang="' . $lang . '">' . $src . '</source>' );
	}

	/**
	 * Processes the submitted Form input
	 * @return true
	 */
	public function processFormulaInput() {
		return true;
	}

	/**
	 * Processes the submitted Form input
	 * @param array $formData
	 * @return bool
	 */
	public function processPageInput( $formData ) {
		if ( gettype( $formData['evalPage'] ) !== "string" ) {
			return "Please select a page";
		}
		$title = Title::newFromText( $formData['evalPage'] );
		if ( $this->setPage( $title ) ) {
			return true;
		} else {
			return $this->lastError;
		}
	}

	public function performSearch() {
		$out = $this->getOutput();
		$out->addWikiText( '==Results==' );
		$out->addWikiText( 'You searched for the following terms:' );
		return false;
	}

	protected function getGroupName() {
		return 'mathsearch';
	}

	private function displayEvaluationForm() {
	}

}

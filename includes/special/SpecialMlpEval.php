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
	const WINDOW_SIZE = 500;
	private $step = 1;
	/**
	 * @var Title
	 */
	private $title;
	private $wikitext;
	private $mathTags;
	private $revison;
	private $lastError = false;

	function __construct() {
		parent::__construct( 'MlpEval' );
	}

	/**
	 * The main function
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->displayForm();
	}


	/**
	 * Processes the submitted Form input
	 * @param array $formData
	 * @return bool
	 */
	public function processInput( $formData ) {
		switch ( $this->step ) {
			case 1:
				$title = Title::newFromText( $formData['evalPage'] );
				if ( $this->setPage( $title ) ) {
					$this->step ++;
					return false;
				} else {
					return $this->lastError;
				}
				break;
			case 2:
				return false;
		}
	}

	public function performSearch() {
		$out = $this->getOutput();
		$out->addWikiText( '==Results==' );
		$out->addWikiText( 'You searched for the following terms:' );
		return false;
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

	private function enableMathStyles() {
		$out = $this->getOutput();
		$out->addModuleStyles(
			array( 'ext.math.styles', 'ext.math.desktop.styles', 'ext.math.scripts' )
		);
	}


	protected function getGroupName() {
		return 'mathsearch';
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

	private function displayForm() {
		$startStep = $this->step;
		switch ( $this->step ) {
			case 1:
				$this->getOutput()->addModules( 'ext.MathSearch.special' );
				$formDescriptor = array(
					'evalPage' => array(
						'label'   => 'Page to evaluate',
						'class'   => 'HTMLTextField',
						'default' => $this->getRandomPage()
					)
				);
				break;
			case 2:
				$this->enableMathStyles();
				list( $tagCount, $formDescriptor, $wikiText ) = $this->displaySnipped();
				break;
		}
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Select' );
		$htmlForm->setSubmitCallback( array( $this, 'processInput' ) );
		$htmlForm->setHeaderText( "<h2>Step {$this->step}: Select a page</h2>" );
		$htmlForm->prepareForm();
		$result = $htmlForm->tryAuthorizedSubmit();
		if ( $this->step > $startStep ) {
			$this->displayForm();
		} else {
			if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
				return $result;
			}
			$htmlForm->displayForm( $result );
		}
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
			$revision = Revision::newFromId( $revision );
			if ( $revision->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
				$this->lastError = "Has invalid format";
				return false;
			}
			$handler = new WikitextContentHandler();
			$wikiText = $handler->getContentText( $revision->getContent() );
			$wikiText = Sanitizer::removeHTMLcomments( $wikiText );
			// Remove <nowiki /> tags to avoid confusion
			$wikiText = preg_replace( '#<nowiki>(.*)</nowiki>#', '', $wikiText );
			$wikiText = Parser::extractTagsAndParams( array( 'math' ), $wikiText, $mathTags );
			$tagCount = count( $mathTags );
			if ( $tagCount == 0 ) {
				$this->lastError = "has no math tags";
				return false;
			}
			$this->title = $title;
			$this->wikitext = $wikiText;
			$this->mathTags = $mathTags;
			return true;
		} else {
			$this->lastError = "invalid revision";
			return false;
		}
	}

	/**
	 * @return \Psr\Log\LoggerInterface
	 */
	private function log() {
		return LoggerFactory::getInstance( 'MathSearch' );
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
		$wikiText = substr( $wikiText,
			max( $tagPos - self::WINDOW_SIZE, 0 ),
			min( 2 * self::WINDOW_SIZE, strlen( $wikiText ) - $tagPos ) );
		$wikiText = str_replace( $unique,
			'<span id="theelement" style="background-color: yellow">' . $tag[3] . '</span>',
			$wikiText );
		foreach ( $this->mathTags as $key => $content ) {
			$wikiText = str_replace( $key, $content[3], $wikiText );
		}

		$this->getOutput()->addWikiText( "== Extract ==\n...\n$wikiText\n..." );
		$url = $this->title->getLinkURL();
		$this->getOutput()
			->addHTML( "<a href=\"$url\" target=\"_blank\">Full article (new Window)</a>" );
		return array( $tagCount, $formDescriptor, $wikiText );
	}
}

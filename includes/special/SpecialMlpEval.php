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
	function __construct() {
		parent::__construct( 'MlpEval' );
	}

	/**
	 * The main function
	 */
	public function execute( $par ) {
		$request = $this->getRequest();
		$this->setHeaders();
		$this->selectPageForm()->show();
	}

	/**
	 * Generates the search input form
	 */
	private function selectPageForm() {
		$this->getOutput()->addModules( 'ext.MathSearch.special' );
		$formDescriptor = array(
			'evalPage' => array(
				'label' => 'Page to evaluate', # What's the label of the field
				'class' => 'HTMLTextField' # What's the input type
			)
		);
		$htmlForm =	new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Select' );
		$htmlForm->setSubmitCallback( array( $this, 'processInput' ) );
		$htmlForm->setHeaderText( "<h2>Step 1: Select a page</h2>" );
		return $htmlForm;
	}



	/**
	 * Processes the submitted Form input
	 * @param array $formData
	 * @return bool
	 */
	public function processInput( $formData ) {

		$this->performSearch();
	}

	public function performSearch() {
		$out = $this->getOutput();
		$out->addWikiText( '==Results==' );
		$out->addWikiText( 'You searched for the following terms:' );

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
			array( 'ext.math.styles' , 'ext.math.desktop.styles', 'ext.math.scripts' )
		);
	}


	protected function getGroupName() {
		return 'mathsearch';
	}
}

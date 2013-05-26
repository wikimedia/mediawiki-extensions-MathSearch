<?php
class SpecialMathIndex extends SpecialPage {


	function __construct() {
		parent::__construct( 'MathIndex', 'edit', true );
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
		global $wgDebugMath;
		$output = $this->getOutput();
		$this->setHeaders();
		if ( $wgDebugMath ) {
			if (  !$this->userCanExecute( $this->getUser() )  ) {
				$this->displayRestrictionError();
				return;
			} else {
				$this->testIndex();
			}
		} else {
			$output->addWikiText( '\'\'\'This page is avaliblible in math debug mode only.\'\'\'' . "\n\n" .
				'Enable the math debug mode by setting <code> $wgDebugMath = true</code> .' );
		}
	}
	function testIndex() {
		$out = $this->getOutput();
//		$out->addWikiText($text)
	}



}
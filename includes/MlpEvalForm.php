<?php

class MlpEvalForm extends OOUIHTMLForm {

	const OPT_CONTINUE = 0;
	const OPT_BACK = 1;
	const OPT_RETRY = 2;

	private $specialPage;
	private $step;

	/**
	 * MlpEvalForm constructor.
	 * @param SpecialMlpEval $specialPage
	 */
	public function __construct( SpecialMlpEval $specialPage ) {
		$this->specialPage = $specialPage;
		$this->step = $specialPage->getStep();
		$formDescriptor = array();
		$this->addPageControls( $formDescriptor );
		$this->addFormulaControls( $formDescriptor );
		parent::__construct( $formDescriptor, $specialPage->getContext() );
		$this->mWasSubmitted = false;
		$this->addHiddenField( 'oldId', $specialPage->getOldId() );
		$this->setSubmitCallback( function(){return false;

	 } );
		$this->addButton( "pgRst", "Select another random page" );

	}

	private function addPageControls( &$formDescriptor ) {
		$s = array(
			'label'   => 'Select page to evaluate.',
			'class'   => 'HTMLTitleTextField',
			'readonly' => $this->specialPage->getStep() > 1 ? true : false,
		);
		if ( $this->specialPage->getStep()>1 ){
			$s['readonly'] = true;
		} else {
			$s['default'] = $this->specialPage->getRandomPage()->getText();
		}
		$formDescriptor['evalPage'] = $s;
	}

	private function addFormulaControls( $formDescriptor ) {
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
	}
}

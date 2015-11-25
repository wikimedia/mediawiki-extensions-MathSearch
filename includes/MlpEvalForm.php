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
		$this->addControls( $formDescriptor );
		parent::__construct( $formDescriptor, $specialPage->getContext() );
		$this->mWasSubmitted = false;
		$this->addStateFields();
		$this->addButtons();
		$this->setSubmitCallback( function(){
			return false;
		} );

	}

	private function addControls( &$formDescriptor ) {
		switch ( $this->step ){
			case SpecialMlpEval::STEP_PAGE:
				$s = array(
						'label'    => 'Select page to evaluate.',
						'class'    => 'HTMLTitleTextField',
						'readonly' => $this->specialPage->getStep() > 1 ? true : false,
						'default'  => $this->specialPage->getRandomPage()->getText()
				);
				$formDescriptor['evalPage'] = $s;
				break;
			case SpecialMlpEval::STEP_FORMULA:
				$formDescriptor['snippetSelector'] = array(
						'type'    => 'radio',
						'label'   => 'Page to evaluate',
						'options' => array(
								'Continue with this snippet'            => self::OPT_CONTINUE,
								'Select another snippet from that page' => self::OPT_RETRY,
								'Go Back to page selection'             => self::OPT_BACK
						),
						'default' => self::OPT_CONTINUE
						# The option selected by default (identified by value)
				);
				break;
		}
	}

	private function addStateFields() {
		$specialPage = $this->specialPage;
		$this->addHiddenField( 'oldId', $specialPage->getOldId() );
		$this->addHiddenField( 'fId', $specialPage->getFId() );
		$this->addHiddenField( 'oldStep', $this->step );
	}

	private function addButtons() {
		$this->addButton( "pgRst", "Select another random page" );
	}
}

<?php

class MlpEvalForm extends OOUIHTMLForm {

	const OPT_CONTINUE = 0;
	const OPT_BACK = 1;
	const OPT_RETRY = 2;

	private $eval;
	private $step;

	/**
	 * MlpEvalForm constructor.
	 * @param SpecialMlpEval $specialPage
	 */
	public function __construct( SpecialMlpEval $specialPage ) {
		$this->eval = $specialPage;
		$this->step = $specialPage->getStep();
		$formDescriptor = array();
		$this->addControls( $formDescriptor );
		$this->addOptions( $formDescriptor );
		parent::__construct( $formDescriptor, $specialPage->getContext() );
		// $this->mWasSubmitted = false;
		$this->addStateFields();
		$this->addButtons();
		$this->setSubmitCallback( function(){
			return false;
		} );

	}

	private function addControls( &$formDescriptor ) {
		switch ( $this->step ){
			case SpecialMlpEval::STEP_PAGE:
				$formDescriptor['1-page'] = array(
					'class'         => 'HTMLTitleTextField',
					'default'       => $this->eval->getRandomPage()->getText()
				);
				break;
			case SpecialMlpEval::STEP_FORMULA:
				$formDescriptor['2-content'] = array(
						'type'    => 'radio',
						'default' => 1,
				);
				$formDescriptor['2-domain'] = array(
						'type'    => 'radio',
						'default' => 1,
				);
				break;
			case SpecialMlpEval::STEP_TEX:
				$formDescriptor['3-skip'] = array(
						'type' => 'submit',
						'value' => 'Skip step.'
				);
				$formDescriptor['3-pretty'] = array(
						'type'    => 'radio',
						'default' => 3,
						'disabled'=>!$this->eval->isTexInputChanged()
				);
				$formDescriptor['3-assessment'] = array(
						'type'    => 'radio',
						'default' => 4,
				);
				$formDescriptor['3-problems'] = array(
						'type'    => 'multiselect',
				);
				$formDescriptor['3-suggestion'] = array(
						'type'    => 'text',
						'required'=>false,
				);
				break;
			case SpecialMlpEval::STEP_RENDERING:
				$subStep = $this->eval->getSubStep();
				if ( $subStep == '4' ) {
					$formDescriptor['4-best'] = array(
							'type'    => 'radio',
							'options' => array(
									$this->eval->getMathMLRenderingAsHtmlFragment() => 'mathml',
									$this->eval->getSvgRenderingAsHtmlFragment()    => 'svg',
									$this->eval->getPngRenderingAsHtmlFragment()    => 'png'
							),
							'default' => 'mathml'
					);
				} else {
					$formDescriptor['4-size'] = array(
							'type'    => 'radio',
							'default' => 1
					);
					$formDescriptor['4-spacing'] = array(
							'type'    => 'radio',
							'default' => 1
					);
					$formDescriptor['4-integration'] = array(
							'type'    => 'radio',
							'default' => 1
					);
					$formDescriptor['4-font'] = array(
							'type'    => 'radio',
							'default' => 1
					);
					$formDescriptor['4-absolute'] = array(
								'type'    => 'radio',
								'default' => 1
						);
				}
				break;
			case SpecialMlpEval::STEP_IDENTIFIERS:
				$options = array();
				// TODO: defaults currently do not work because request->wasPosted() is set to true.
				$default = array();
				foreach ( $this->eval->getIdentifiers() as $id ) {
					$rendered = MathRenderer::renderMath( $id, array(), 'mathml' );
					$options[$rendered] = $id;
					$default[] = $id;
				}
				$formDescriptor['5-identifiers'] = array(
						'type'    => 'multiselect',
						'options' => $options,
						'default' => $default,
						// 'invert'=> true,
				);
				$formDescriptor['5-missing'] = array(
						'type' => 'textarea',
						'rows' => 3, # Display height of field
						// 'cols' => 30 # Display width of field
				);
				break;
			case SpecialMlpEval::STEP_DEFINITIONS:
				foreach ( $this->eval->getIdentifiers() as $key => $id ) {
					$options =array();
					$formDescriptor["6-separator-$key"] = array(
						'type'    => 'info',
						'default' => '<h3>' .
							wfMessage( 'math-lp-6-separator-message', $id )->parseAsBlock() . '</h3>',
						'raw'     => true
					);
					$rels = $this->eval->getRelations( $id );
					foreach ( $rels as $rel ){
						$options[$rel] = $rel;
					}
					$options['other'] = 'other';
					if ( count( $rels ) ) {
						$formDescriptor["6-id-$key"] = array(
								'label' => "Select definitions for $id",
								'type'      => 'multiselect',
								'options'   => $options,
								// 'raw' => true
						);
					}
					$formDescriptor["6-id-$key-other"] = array(
							'label' => "Other for $id",
							'type'      => 'text'
					);
				}
				$srt = $this->eval->getSpeechRuleText();
				$formDescriptor["6-srt"] = array(
					'type'    => 'info',
					'default' => "<pre>$srt</pre>",
					'raw'     => true
				);
				$formDescriptor['6-srt-assessment'] = array(
					'type'    => 'radio',
					'default' => 2,
				);
				$formDescriptor['6-srt-suggestion'] = array(
					'type'     => 'text',
					'required' => false,
				);
				break;
			case SpecialMlpEval::STEP_FINISHED:
				$formDescriptor['feedback'] = array(
						'type' => 'text',
				);
				break;
		}
		$formDescriptor['submit-info'] = array(
			'type' => 'info',
			// 'label-message' => 'math-lp-submit-info-label',
			'default' => wfMessage( 'math-lp-submit-info' )->text(),
			// 'raw' => true # if true, the above string won't be html-escaped.
		);
	}

	private function addStateFields() {
		$specialPage = $this->eval;
		$this->addHiddenField( 'oldId', $specialPage->getOldId() );
		$this->addHiddenField( 'fId', $specialPage->getFId() );
		$this->addHiddenField( 'oldStep', $this->step );
		$this->addHiddenField( 'oldSubStep', $this->eval->getSubStep() );
		if ( $this->step == 4 ){
			$subFields = array( 'best','size','spacing','integration','font' );
			foreach ( $subFields as $key ){
				$this->saveSubstepField( $key );
			}
		}
	}

	private function addButtons() {
		$this->addButton( "pgRst", wfMessage( 'math-lp-new-article' )->text() );
		if ( $this->step > 1 && $this->step < 7 ){
			$this->addButton( "fRst", wfMessage( 'math-lp-new-formula' )->text() );
		}
		if ( $this->step < 6 ){
			$this->setSubmitTextMsg(
				wfMessage( 'math-lp-submit-label' )->params( $this->eval->getNextStep() ) );
		} elseif( $this->step == 6 ) {
			$this->setSubmitTextMsg( wfMessage( 'math-lp-finish-label' ) );
		} else {
			$this->setSubmitTextMsg( wfMessage( 'math-lp-new-formula' ) );
		}
	}

	private function addOptions( &$form ) {
		static $elements = array( 'label','help' );
		foreach ( $form as $key => $control ) {
			foreach ( $elements as $element ){
				$msg = "math-lp-$key-$element";
				if ( wfMessage( $msg )->exists() ){
					$form[$key]["$element-message"] = $msg;
				}
			}
			if ( wfMessage( "math-lp-$key-option-1" )->exists() ){
				$options = array();
				for ( $i=1;$i<20;$i++ ){
					$msg = "math-lp-$key-option-$i";
					if ( wfMessage( "math-lp-$key-option-$i" )->exists() ) {
							$txt = wfMessage( "math-lp-$key-option-$i", $i )->parseAsBlock();
							$options[$txt] = $i;
					} else {
						break;
					}
				}
				$form[$key]["options"] = $options;
			}
		}
	}

	/**
	 * @param $key
	 */
	private function saveSubstepField( $key ) {
		$val = $this->getRequest()->getVal( "wp4-$key" );
		$substep = $this->eval->getSubStep();
		if ( $val ) {
			$this->addHiddenField( "4-$key-$substep", $val );
			$this->getRequest()->unsetVal( "wp4-$key" );
		}
		$substeps = array( '','4a','4b','4c' );
		foreach ( $substeps as $substep ){
			$val = $this->getRequest()->getVal( "4-$key-$substep" );
			if ( $val ){
				$this->addHiddenField( "4-$key-$substep", $val );
			}
		}
	}
}

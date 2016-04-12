<?php

class SpecialLaTeXTranslator extends SpecialPage {
	/**
	 * @var LaTeXTranslator
	 */
	private $translator;
	function __construct() {
		parent::__construct( 'LaTeXTranslator' );
		$this->translator = new LaTeXTranslator();
	}

	/**
	 * Returns corresponding Mathematica translations of LaTeX functions
	 * @param $par
	 */
	function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-intro' );
		$formDescriptor = [
			'input' => [
				'label-message' => 'math-tex2nb-input',
				'class' => 'HTMLTextAreaField',
				'default' => '\log x'
			]
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Translate' );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->setHeaderText( '<h2>' . wfMessage( 'math-tex2nb-header' )->toString() . '</h2>' );
		$htmlForm->show();
	}

	/**
	 * Processes the submitted Form input
	 * @param array $formData
	 * @return bool
	 */
	public function processInput( $formData ) {
		$data = $formData['input'];

		$translated = $this->translator->processInput( $data );

		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-latex' );
		$output->addWikiText( "<syntaxhighlight lang='latex'>$data</syntaxhighlight>" );
		$output->addWikiMsg( 'math-tex2nb-mathematica' );
		$output->addWikiText( "<syntaxhighlight lang='text'>$translated</syntaxhighlight>" );
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}

<?php

class SpecialLaTeXTranslator extends SpecialPage {

	function __construct() {
		parent::__construct( 'LaTeXTranslator' );
	}

	/**
	 * Returns corresponding Mathematica translations of LaTeX functions
	 * @param string|null $par
	 */
	function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-intro' );
		$formDescriptor = [
			'input' => [
				'label-message' => 'math-tex2nb-input',
				'class' => 'HTMLTextField',
				'default' => '(z)_n = \frac{\Gamma(z+n)}{\Gamma(z)}',
			],
			'wikitext' => [
				'label-message' => 'math-tex2nb-wikitext',
				'class' => 'HTMLTextAreaField',
				'default' => 'The Gamma function 
<math>\Gamma(z)</math>
and the pochhammer symbol
<math>(a)_n</math>
are often used together.',
			],
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

		$output = $this->getOutput();
		$output->addWikiMsg( 'math-tex2nb-latex' );
		$output->addWikiTextAsInterface( "<syntaxhighlight lang='latex'>$data</syntaxhighlight>" );
		$output->addWikiMsg( 'math-tex2nb-mathematica' );
		if ( !FormulaInfo::DisplayTranslations( $data ) ) {
			$translated = $this->translator->processInput( $data );
			$output->addWikiTextAsInterface( "<syntaxhighlight lang='text'>$translated</syntaxhighlight>" );
		}
	}

	protected function getGroupName() {
		return 'mathsearch';
	}
}

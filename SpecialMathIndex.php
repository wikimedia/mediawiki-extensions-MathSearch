<?php
class SpecialMathIndex extends SpecialPage {
	const SCRIPT_UPDATE_MATH=0;
	const SCRIPT_WRITE_INDEX=1;


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
		global $wgMathDebug;
		$output = $this->getOutput();
		$this->setHeaders();
		if ( $wgMathDebug ) {
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
		$out->addWikiText('This is a test.');
		$formDescriptor = array(
			'script' => array(
				'label' => 'Script', # What's the label of the field
				'type' => 'select', # What's the input type
				'help' => 'for example: \sin(?x^2)',
				'default' => 0,
				'options' => array( # The options available within the menu (displayed => value)
					'UpdateMath' => self::SCRIPT_UPDATE_MATH, # depends on how you see it but keys and values are kind of mixed here
					'ExportIndex' => self::SCRIPT_WRITE_INDEX, # "Option 1" is the displayed content, "1" is the value
					'something else' => 'option2id' # Hmtl Result = <option value="option2id">Option 2</option>
					)
				)
			);
		$htmlForm = new HTMLForm( $formDescriptor ); # We build the HTMLForm object
		$htmlForm->setSubmitText( 'Search' );
		$htmlForm->setSubmitCallback( array( get_class($this) , 'processInput' ) );
		$htmlForm->setTitle( $this->getTitle() );
		$htmlForm->setHeaderText("<h2>Select script to run</h2>");
		$htmlForm->show(); # Displaying the form
	}
	/* We write a callback function */
	# OnSubmit Callback, here we do all the logic we want to do...
	public static function processInput( $formData ) {
		switch ($formData['script']) {
			case self::SCRIPT_UPDATE_MATH:
				require_once dirname( __FILE__ ) .'/maintenance/UpdateMath.php';
				$updater = new UpdateMath();
				$updater->loadParamsAndArgs(null, array("max"=>1), null);
				$updater->execute();
				break;
			case self::SCRIPT_WRITE_INDEX:
				require_once dirname( __FILE__ ) .'/maintenance/CreateMathIndex.php';
				$updater = new CreateMathIndex();
				$updater->loadParamsAndArgs(null, array("mwsns"=>'mws:'), array(dirname( __FILE__ ) .'/mws/data/wiki'));
				$updater->execute();
				break;
			default:
				break;
		}

	}


}
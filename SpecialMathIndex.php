<?php
class SpecialMathIndex extends SpecialPage {
	const SCRIPT_UPDATE_MATH = 0;
	const SCRIPT_WRITE_INDEX = 1;

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
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return;
			} else {
				$this->testIndex();
			}
		} else {
			$output->addWikiTextAsInterface(
				'\'\'\'This page is avaliblible in math debug mode only.\'\'\'' . "\n\n" .
				'Enable the math debug mode by setting <code> $wgMathDebug = true</code> .'
			);
		}
	}

	function testIndex() {
		$out = $this->getOutput();
		$out->addWikiTextAsInterface( 'This is a test.' );
		$formDescriptor = [
			'script' => [
				'label' => 'Script', # What's the label of the field
				'type' => 'select', # What's the input type
				'help' => 'for example: \sin(?x^2)',
				'default' => 0,
				'options' => [ # The options available within the menu (displayed => value)
					# depends on how you see it but keys and values are kind of mixed here
					'UpdateMath' => self::SCRIPT_UPDATE_MATH,
					# "Option 1" is the displayed content, "1" is the value
					'ExportIndex' => self::SCRIPT_WRITE_INDEX,
					# Hmtl Result = <option value="option2id">Option 2</option>
					'something else' => 'option2id'
				]
			]
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() ); # We build the HTMLForm object
		$htmlForm->setSubmitText( 'Search' );
		$htmlForm->setSubmitCallback( [ get_class( $this ) , 'processInput' ] );
		$htmlForm->setHeaderText( "<h2>Select script to run</h2>" );
		$htmlForm->show(); # Displaying the form
	}

	/* We write a callback function */
	# OnSubmit Callback, here we do all the logic we want to do...
	public static function processInput( $formData ) {
		switch ( $formData['script'] ) {
			case self::SCRIPT_UPDATE_MATH:
				require_once __DIR__ . '/maintenance/UpdateMath.php';
				$updater = new UpdateMath();
				$updater->loadParamsAndArgs( null, [ "max" => 1 ], null );
				$updater->execute();
				break;
			case self::SCRIPT_WRITE_INDEX:
				require_once __DIR__ . '/maintenance/CreateMathIndex.php';
				$updater = new CreateMathIndex();
				$updater->loadParamsAndArgs( null, [ "mwsns" => 'mws:' ],
					[ __DIR__ . '/mws/data/wiki' ]
				);
				$updater->execute();
				break;
			default:
				break;
		}
	}

	protected function getGroupName() {
		return 'mathsearch';
	}

}

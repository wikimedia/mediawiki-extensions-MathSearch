<?php
namespace MediaWiki\Extension\MathSearch\Specials;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialMathIndex extends SpecialPage {

	private const SCRIPT_UPDATE_MATH = 0;
	private const SCRIPT_WRITE_INDEX = 1;

	public function __construct() {
		parent::__construct( 'MathIndex', 'edit', true );
	}

	/**
	 * Sets headers - this should be called from the execute() method of all derived classes!
	 */
	public function setHeaders() {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( "noindex,nofollow" );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		$output = $this->getOutput();
		$this->setHeaders();
		if ( $this->getConfig()->get( 'MathDebug' ) ) {
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
			} else {
				$this->testIndex();
			}
			$this->displayStats();
		} else {
			$output->addWikiTextAsInterface(
				'\'\'\'This page is available in math debug mode only.\'\'\'' . "\n\n" .
				'Enable the math debug mode by setting <code> $wgMathDebug = true</code> .'
			);
		}
	}

	private function displayStats() {
		$basex = new \MathEngineBaseX();
		$basex->getTotalIndexed();
		$this->getOutput()->addHTML( "<p> Total indexed in baseX: {$basex->getTotalIndexed()}</p>" );
	}

	private function testIndex() {
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
				]
			]
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() ); # We build the HTMLForm object
		$htmlForm->setSubmitText( 'Search' );
		$htmlForm->setSubmitCallback( [ get_class( $this ), 'processInput' ] );
		$htmlForm->setHeaderHtml( "<h2>Select script to run</h2>" );
		$htmlForm->show(); # Displaying the form
	}

	/**
	 * OnSubmit Callback, here we do all the logic we want to do...
	 * @param array $formData
	 */
	public static function processInput( $formData ) {
		switch ( $formData['script'] ) {
			case self::SCRIPT_UPDATE_MATH:
				require_once __DIR__ . '/../../maintenance/UpdateMath.php';
				$updater = new UpdateMath();
				$updater->loadParamsAndArgs( null, [ "max" => 1 ] );
				$updater->execute();
				break;
			case self::SCRIPT_WRITE_INDEX:
				require_once __DIR__ . '/../../maintenance/CreateMWSHarvest.php';
				$updater = new CreateMWSHarvest();
				$updater->loadParamsAndArgs( null, [ "mwsns" => 'mws:' ],
					[ __DIR__ . '/mws/data/wiki' ]
				);
				$updater->execute();
				break;
			default:
				break;
		}
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}

}

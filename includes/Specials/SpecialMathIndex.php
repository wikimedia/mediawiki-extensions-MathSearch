<?php
namespace MediaWiki\Extension\MathSearch\Specials;

use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\MathSearch\Engine\BaseX;
use MediaWiki\Extension\MathSearch\Graph\AutoCreateProfilePages;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Sparql\SparqlException;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialMathIndex extends SpecialPage {

	private const SCRIPT_UPDATE_MATH = 0;
	private const SCRIPT_WRITE_INDEX = 1;
	private const SCRIPT_PROFILE_PAGES = 2;

	public function __construct(
		private readonly JobQueueGroup $jobQueueGroup,
	) {
		parent::__construct( 'MathIndex', 'delete' );
	}

	/**
	 * Sets headers - this should be called from the execute() method of all derived classes!
	 */
	public function setHeaders() {
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( "noindex,nofollow" );
	}

	/** @inheritDoc
	 * @throws PermissionsError
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
		}
		$this->testIndex();
		$this->displayStats();
	}

	private function displayStats() {
		$basex = new BaseX();
		$basex->getTotalIndexed();
		$this->getOutput()->addHTML( "<p> Total indexed in baseX: {$basex->getTotalIndexed()}</p>" );
	}

	private function testIndex(): void {
		$formDescriptor = [
			'script' => [
				'label' => 'Script',
				'type' => 'select',
				'default' => 0,
				'options' => [
					'UpdateMath' => self::SCRIPT_UPDATE_MATH,
					'ExportIndex' => self::SCRIPT_WRITE_INDEX,
					'CreateProfilePages' => self::SCRIPT_PROFILE_PAGES
				]
			]
		];
		$htmlForm = new HTMLForm( $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->setHeaderHtml( "<h2>Select script to run</h2>" );
		$htmlForm->show();
	}

	/**
	 * OnSubmit Callback, here we do all the logic we want to do...
	 * @param array $formData
	 */
	public function processInput( $formData ) {
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
			case self::SCRIPT_PROFILE_PAGES:
				$creator = new AutoCreateProfilePages(
					$this->getConfig(),
					$this->jobQueueGroup,
					$this->getOutput()
				);
				try {
					$creator->run();
				} catch ( SparqlException $e ) {
					$this->getOutput()->addHTML( "<p>Error running profile page creation: {$e->getMessage()}</p>" );
				}
				break;
			default:
				break;
		}
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}

}

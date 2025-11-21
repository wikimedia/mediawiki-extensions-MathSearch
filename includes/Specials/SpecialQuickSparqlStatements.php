<?php

namespace MediaWiki\Extension\MathSearch\Specials;

use Closure;
use HTMLTextAreaField;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Extension\MathSearch\Graph\Job\QuickStatements;
use MediaWiki\Extension\MathSearch\Graph\Map;
use MediaWiki\Extension\MathSearch\Graph\Query;
use MediaWiki\HTMLForm\Field\HTMLCheckField;
use MediaWiki\HTMLForm\Field\HTMLInfoField;
use MediaWiki\HTMLForm\Field\HTMLIntField;
use MediaWiki\HTMLForm\Field\HTMLSubmitField;
use MediaWiki\HTMLForm\Field\HTMLTextField;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Sparql\SparqlException;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialQuickSparqlStatements extends SpecialPage {
	public function __construct() {
		parent::__construct( 'QuickSparqlStatements', 'import' );
	}

	public function execute( $subPage ): void {
		parent::execute( $subPage );
		# A formDescriptor Array to tell HTMLForm what to build
		$formDescriptor = [
			'user' => [
				'label' => 'User',
				'class' => HTMLInfoField::class,
				'default' => $this->getUser()->getName(),
			],
			'displayQuery' => [
				'label' => 'Endpoint',
				'class' => HTMLInfoField::class,
				'default' => Query::getEndpoint(),
			],
			'query' => [
				'label' => 'Query',
				'help' => 'Paste your query here.',
				'class' => HTMLTextAreaField::class,
			],
			'job-title' => [
				'label' => 'Title',
				'help' => 'Plan text title will appear in each change.',
				'class' => HTMLTextField::class,
			],
			'job-description' => [
				'label' => 'Description',
				'help' => 'Use Wikitext. This will be logged to a dedicated log page.',
				'class' => HTMLTextAreaField::class,
				'rows' => 3,
			],
			'overwrite' => [
				'label' => 'Overwrite',
				'help' => 'Overwrite existing values for the given properties',
				'class' => HTMLCheckField::class,
				'default' => true,
			],
			'totalLimit' => [
				'label' => 'Maximal limit',
				'help' => 'Overall maximal limit for changes. 0 means no limit.',
				'class' => HTMLIntField::class,
				'min' => 0,
				'default' => 0,
				'maxlength' => 8,
				'maximum' => PHP_INT_MAX,
			],
			'preview' => [
				'buttonlabel' => 'Preview',
				'help' => 'Preview the effects of your job (implicitly sets limit to 1)',
				'class' => HTMLSubmitField::class,
			],
		];
		$htmlForm =	HTMLForm::factory( 'codex', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitText( 'Run' );
		$htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
		$htmlForm->show();
	}

	public function processInput( $formData ) {
		if ( ( $formData['preview'] ?? false ) === true ) {
			try {
				$query = $formData['query'] . ' LIMIT 1';
				$res = Query::getResults( $query );
				$this->getOutput()->addWikiTextAsContent(
					'<h2>Preview</h2>' .
					'<p>The following query was executed:</p>' .
					'<syntaxhighlight lang="sparql">' .
					$query .
					'</syntaxhighlight>'
				);
				if ( count( $res ) == 0 ) {
					$this->getOutput()->addWikiTextAsContent( 'No results returned.' );
					return false;
				}
				$this->getOutput()->addWikiTextAsContent(
					'<p>The following results were returned:</p>' .
					'<syntaxhighlight lang="json">' .
					json_encode( $res, JSON_PRETTY_PRINT ) .
					'</syntaxhighlight> <h3>Checking keys:</h3>' );
				$qsFakeJob = new QuickStatements( [ 'rows' => $res ] );
				foreach ( array_keys( $res[0] ) as $key ) {
					$this->getOutput()->addWikiTextAsContent( "Checking $key." );
					if ( strtolower( $key ) == 'qid' ) {
						$this->getOutput()->addWikiTextAsContent( 'success! qID key found' );
						continue;
					}
					if ( str_starts_with( $key, 'P' ) ) {
						$this->getOutput()->addWikiTextAsContent( 'success! key starts with P' );
						$this->getOutput()->addWikiTextAsContent(
							'<syntaxhighlight lang="json">' .
							json_encode( $qsFakeJob->validateProperty( $key ), JSON_PRETTY_PRINT ) .
							'</syntaxhighlight> <h3>Checking keys:</h3>' );
						$this->getOutput()->addWikiTextAsContent(
							'Serialization for sample value:' .
							$qsFakeJob->testSnak( $key, $res[0][$key] ) );
						continue;
					}
					if ( str_starts_with( $key, 'L' ) ) {
						$this->getOutput()->addWikiTextAsContent( 'success! key starts with L' );
						$languageCode = substr( $key, 1 );
						if ( MediaWikiServices::getInstance()->getLanguageNameUtils()->isValidCode( $languageCode ) ) {
							$this->getOutput()->addWikiTextAsContent(
								'Language code ' . $languageCode . ' is valid for label/descriptions/aliases.' );
						} else {
							$this->getOutput()->addWikiTextAsContent(
								'Language code ' . $languageCode . ' is NOT valid for label/descriptions/aliases.' );
						}
						continue;
					}
					$this->getOutput()->addWikiTextAsContent( 'can not parse key' );

				}

			} catch ( SparqlException $e ) {
				return 'SPARQL Error: ' . $e->getMessage();
			}
			return false;
		}
		$jobParams = $this->getJobParams( $formData );
		$title  = Title::newFromText( $jobParams['page-name'] );
		$pageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
		$text = $formData['job-description'] .
			'<br/><h3>Query</h3><syntaxhighlight lang="sparql">' .
			$formData['query'] .
			'</syntaxhighlight><br/><h3>Form data</h3><syntaxhighlight lang="json">' .
			json_encode( $formData, JSON_PRETTY_PRINT ) .
			'</syntaxhighlight><h3>Endpoint</h3>' .
			Query::getEndpoint();
		$pageContent = ContentHandler::makeContent( $text, $title );
		$pageFactory->newFromTitle( $title )
			->doUserEditContent( $pageContent, $this->getUser(),
				'Created automatically via QuickSparql Statements special page.' );

		try {
			( new Map() )->scheduleJobs(
				Closure::fromCallable( [ $this->getOutput(), 'addWikiTextAsContent' ] ),
				$this->getBatchSize( $formData ),
				'qs',
				QuickStatements::class,
				$jobParams,
			);
		} catch ( SparqlException $e ) {
			return 'SPARQL Error: ' . $e->getMessage();
		}
		return true;
	}

	public function getJobParams( array $formData ): array {
		$jobData = $formData;
		$jobData['date'] = date( 'ymdhms' );
		$jobData['page-name'] = 'QuickSparqlStatements/' . $jobData['job-title'] . '/' . $jobData['date'];
		$jobData['editsummary'] = "[[{$jobData['page-name']}|{$jobData['job-title']}]]";
		$jobData['username'] = $this->getUser()->getName();
		return $jobData;
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}

	private function getBatchSize( array $formData ): int {
		return $formData['batchSize'] ?? 10000;
	}

}

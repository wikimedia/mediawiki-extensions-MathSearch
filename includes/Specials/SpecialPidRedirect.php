<?php

namespace MediaWiki\Extension\MathSearch\Specials;

use Exception;
use MediaWiki\Extension\Math\Widget\WikibaseEntitySelector;
use MediaWiki\Extension\MathSearch\Graph\Query;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use OOUI\ButtonInputWidget;
use OOUI\CheckboxInputWidget;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\FormLayout;
use OOUI\TextInputWidget;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\Repo\WikibaseRepo;

/**
 * Redirect to a page on the wiki regarding a persistent identifier (PID)
 *
 * @ingroup SpecialPage
 */
class SpecialPidRedirect extends SpecialPage {

	public function __construct() {
		parent::__construct( 'PidRedirect' );
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );
		$pid = $this->getRequest()->getInt( 'propertyId' );
		if ( $pid === 0 ) {
			$pText = $this->getRequest()->getText( 'propertyId' );
			// Check if the string starts with 'P' and remove it
			if ( strpos( $pText, 'P' ) === 0 ) {
				// Remove the 'P' and convert the rest to an integer
				$pid = (int)substr( $pText, 1 );
			}
		}
		$val = $this->getRequest()->getText( 'value' );
		$linkToItem = $this->getRequest()->getBool( 'item' );
		$siteId = $this->getRequest()->getText( 'siteId', 'mardi' );
		if ( $pid === 0 || $val === '' ) {
			$this->setHeaders();
			$this->getOutput()->addWikiTextAsContent( 'propertyId and value are required' );
			$this->showForm();
			return;
		}
		$propertyId = new NumericPropertyId( "P$pid" );
		$dataType = WikibaseRepo::getPropertyDataTypeLookup()->getDataTypeIdForProperty( $propertyId );
		if ( !in_array( $dataType, [ 'string', 'external-id' ] ) ) {
			$this->getOutput()->addWikiTextAsContent( 'Invalid property type: ' . $dataType );
			$this->showForm();
			return;
		}
		$query = Query::getQidFromPid( '"' . $val . '"', 'P' . $pid );
		$results = Query::getResults( $query );
		foreach ( $results as $row ) {
			$qid = $row['qid'];
			if ( $linkToItem ) {
				$title = Title::makeTitle( 120, "$qid" );
			} else {
				$item = WikibaseRepo::getEntityLookup()->getEntity( new ItemId( $qid ) );

				if ( !$item instanceof Item ) {
					throw new Exception( "Item $qid not found." );
				}
				$link = $item->getSiteLink( $siteId );
				$title = Title::newFromText( $link->getPageName() );

			}
			$this->getOutput()->redirect( $title->getFullURL() );
			return;
		}
		$this->getOutput()->addWikiTextAsContent( 'Value not found.' );
		$this->showForm();
	}

	private function showForm() {
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addModules( [ 'mw.widgets.MathWbEntitySelector' ] );
		$this->getOutput()->addHTML( new FormLayout(
			[
				'method' => 'GET',
				'items' => [
					new FieldsetLayout( [
						'label' => 'Your Form',
						'items' => [
							new FieldLayout(
								new WikibaseEntitySelector( [
									'name' => 'propertyId',
									'paramType' => 'property',
									'infusable' => true,
									'id' => 'wbEntitySelector',
									'value' => $this->getRequest()->getText( 'propertyId' ),
								] ),
								[
									'label' => 'Property',
									'align' => 'top',
								]
							),
							new FieldLayout(
								new TextInputWidget( [
									'name' => 'value',
									'value' => $this->getRequest()->getText( 'value' ),
								] ),
								[
									'label' => 'Value',
									'align' => 'top',
								]
							),
							new FieldLayout(
								new CheckboxInputWidget( [
									'name' => 'item',
									'checked' => $this->getRequest()->getBool( 'item' ),
								] ),
								[
									'label' => 'Item (Link to the item page instead of the site link)',
									'align' => 'inline',
								]
							),
							new FieldLayout(
								new ButtonInputWidget( [
									'name' => 'submit',
									'label' => 'Redirect',
									'type' => 'submit',
									'flags' => [ 'primary', 'progressive' ],
									'icon' => 'check',
								] ),
								[
									'label' => null,
									'align' => 'top',

								] )
						] ] )
				] ] ) );
	}

	protected function getGroupName(): string {
		return 'mathsearch';
	}
}

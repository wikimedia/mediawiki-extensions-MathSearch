<?php

namespace MediaWiki\Extension\MathSearch\Wikidata\MathML;

use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\Repo\Rdf\ValueSnakRdfBuilder;
use Wikimedia\Purtle\RdfWriter;

class MathMLRdfBuilder implements ValueSnakRdfBuilder {

	/**
	 * Adds a value
	 *
	 * @param RdfWriter $writer
	 * @param string $propertyValueNamespace Property value relation namespace
	 * @param string $propertyValueLName Property value relation name
	 * @param string $dataType Property data type
	 * @param string $snakNamespace
	 * @param PropertyValueSnak $snak
	 */
	public function addValue(
		RdfWriter $writer, $propertyValueNamespace, $propertyValueLName, $dataType,
						  $snakNamespace, PropertyValueSnak $snak
	) {
		$writer->say( $propertyValueNamespace, $propertyValueLName )->value( $snak->getDataValue()
			->getValue(), 'http://www.w3.org/1998/Math/MathML' );
	}
}

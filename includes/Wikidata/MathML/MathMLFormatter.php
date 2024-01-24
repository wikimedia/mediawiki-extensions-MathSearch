<?php
namespace MediaWiki\Extension\MathSearch\Wikidata\MathML;

use DataValues\StringValue;
use Html;
use ValueFormatters\Exceptions\MismatchingDataValueTypeException;
use ValueFormatters\ValueFormatter;
use Wikibase\Lib\Formatters\SnakFormatter;

/*
* Formats the tex string based on the known formats
* * text/plain: used in the value input field of Wikidata
* * text/x-wiki: wikitext
* * text/html: used in Wikidata to display the value of properties
* Formats can look like this: "text/html; disposition=diff"
* or just "text/plain"
*/

class MathMLFormatter implements ValueFormatter {

	/**
	 * @var string One of the SnakFormatter::FORMAT_... constants.
	 */
	private $format;

	/**
	 * Loads format to distinguish the type of formatting
	 *
	 * @param string $format One of the SnakFormatter::FORMAT_... constants.
	 */
	public function __construct( $format ) {
		$this->format = $format;
	}

	/**
	 * @param StringValue $value
	 *
	 * @return string
	 * @throws MismatchingDataValueTypeException
	 */
	public function format( $value ) {
		if ( !( $value instanceof StringValue ) ) {
			throw new InvalidArgumentException( '$value must be a StringValue' );
		}

		$mml = $value->getValue();

		switch ( $this->format ) {
			case SnakFormatter::FORMAT_WIKI:
				return "<math type='pmml'>$mml</math>";
			case SnakFormatter::FORMAT_HTML_DIFF:
				return Html::rawElement( 'h4',
						[ 'class' => 'wb-details wb-math-details wb-math-rendered' ], $mml ) .
					Html::rawElement( 'div', [ 'class' => 'wb-details wb-math-details' ],
						Html::element( 'code', [], $mml ) );
			default:
				return $mml;
		}
	}

	/**
	 * @return string One of the SnakFormatter::FORMAT_... constants.
	 */
	public function getFormat() {
		return $this->format;
	}

}

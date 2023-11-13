<?php

namespace MediaWiki\Extension\MathSearch\Wikidata\MathML;

use DataValues\StringValue;
use ValueFormatters;
use ValueValidators\Result;
use ValueValidators\ValueValidator;

/**
 * @author Duc Linh Tran
 * @author Julian Hilbig
 * @author Moritz Schubotz
 */
class MathMLValidator implements ValueValidator {

	/**
	 * Validates a value with MathLaTeXML
	 *
	 * @param mixed $value The value to validate
	 *
	 * @return \ValueValidators\Result
	 * @throws ValueFormatters\Exceptions\MismatchingDataValueTypeException
	 */
	public function validate( $value ) {
		if ( !( $value instanceof StringValue ) ) {
			throw new InvalidArgumentException( '$value must be a StringValue' );
		}

		return Result::newSuccess();
	}

	/**
	 * @param array $options
	 * @see ValueValidator::setOptions()
	 *
	 */
	public function setOptions( array $options ) {
		// Do nothing. This method shouldn't even be in the interface.
	}
}

<?php

namespace MediaWiki\Extension\MathSearch\Wikidata\Content;

use DataValues\StringValue;
use InvalidArgumentException;
use MediaWiki\Extension\Math\MathLaTeXML;
use ValueFormatters;
use ValueValidators\Error;
use ValueValidators\Result;
use ValueValidators\ValueValidator;

/**
 * @author Duc Linh Tran
 * @author Julian Hilbig
 * @author Moritz Schubotz
 */
class ContentMathValidator implements ValueValidator {

	/**
	 * Validates a value with MathLaTeXML
	 *
	 * @param mixed $value The value to validate
	 *
	 * @return Result
	 * @throws ValueFormatters\Exceptions\MismatchingDataValueTypeException
	 */
	public function validate( $value ) {
		if ( !( $value instanceof StringValue ) ) {
			throw new InvalidArgumentException( '$value must be a StringValue' );
		}

		// get input String from value
		$tex = $value->getValue();

		$checker = new MathLaTeXML( $tex );
		if ( $checker->checkTeX() ) {
			$checker->writeCache();

			return Result::newSuccess();
		}

		// TeX string is not valid
		return Result::newError(
			[
				Error::newError( null, null, 'malformed-value', [ $checker->getLastError() ] )
			]
		);
	}

	/**
	 * @param array $options
	 * @see ValueValidator::setOptions()
	 */
	public function setOptions( array $options ) {
		// Do nothing. This method shouldn't even be in the interface.
	}
}

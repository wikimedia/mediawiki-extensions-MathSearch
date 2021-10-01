<?php

use DataValues\StringValue;
use MediaWiki\Extension\Math\MathLaTeXML;
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
	 * @return \ValueValidators\Result
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
	 * @see ValueValidator::setOptions()
	 *
	 * @param array $options
	 */
	public function setOptions( array $options ) {
		// Do nothing. This method shouldn't even be in the interface.
	}
}

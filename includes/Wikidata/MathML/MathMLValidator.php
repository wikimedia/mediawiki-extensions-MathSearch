<?php

namespace MediaWiki\Extension\MathSearch\Wikidata\MathML;

use DataValues\StringValue;
use InvalidArgumentException;
use MediaWiki\Extension\MathSearch\MathMLInput;
use ValueFormatters;
use ValueValidators\Error;
use ValueValidators\Result;
use ValueValidators\ValueValidator;

/**
 * @author Duc Linh Tran
 * @author Julian Hilbig
 * @author Moritz Schubotz
 */
class MathMLValidator implements ValueValidator {

	public function __construct(
		private readonly MathMLInput $input,
	) {
	}

	/**
	 * Validates a value with the schema of MathMLInput
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

		$status = $this->input->check( $value->getValue() );
		if ( !$status->isGood() ) {
			return Result::newError( [
				Error::newError( '', null, 'malformed-value', [ wfMessage( $status->getMessages()[0] ) ] ),
			] );
		}
		return Result::newSuccess();
	}

	/**
	 * @param array $options
	 * @see ValueValidator::setOptions()
	 */
	public function setOptions( array $options ) {
		// Do nothing. This method shouldn't even be in the interface.
	}
}

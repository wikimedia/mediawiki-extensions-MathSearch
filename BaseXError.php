<?php
/**
 * @author Moritz Schubotz
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 *
 * @file
 */

/**
 * The exception class for all BaseX related errors.
 */
class BaseXError extends MWException {
	function __construct( $message ) {
		parent::__construct( 'BaseX error: ' . $message );
	}
}
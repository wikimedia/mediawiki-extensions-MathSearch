<?php
/**
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @ingroup Maintenance
 */
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point to MediaWiki.\n"
		. "To run the script use:\n"
		. 'php ../../../maintenance/dumpBackup.php --current --plugin=MathMLFilter:./MathDump.php --filter=mathml'
		. "\n" );
}

/**
 * Simple dump output filter to exclude all talk pages.
 * @ingroup Dump
 */
class MathDump extends DumpFilter {
	public static function register( $backupDumper ) {
		$backupDumper->registerFilter( 'mathml', 'MathMLFilter' );

	}

	/**
	 * Callback function that replaces TeX by MathML
	 * @param array $match
	 * @return string
	 */
	private static function renderMath( $match ) {
		$formula = $match[1];
		$renderer = MathRenderer::getRenderer( $formula, array(), MW_MATH_LATEXML );
		$renderer->render();
		// TODO: check if there is a Mediawiki function for that
		$res = htmlspecialchars( $renderer->getMathml() );
		return $res;
	}

	/**
	 * Replaces the math tags with rendered Mathml
	 * @param unknown $pText
	 * @return string
	 */
	private static function replaceMath( $pText ) {
		$pText = Sanitizer::removeHTMLcomments( $pText );
		return preg_replace_callback( "#&lt;math&gt;(.*?)&lt;/math&gt;#s", 'self::renderMath', $pText );
	}


	/**
	 * @param $rev
	 * @param $string the revision text
	 */
	function writeRevision( $rev, $string ) {
		if ( $this->sendingThisPage ) {
			$string = $this->replaceMath( $string );
			$this->sink->writeRevision( $rev, $string );
		}
	}

}

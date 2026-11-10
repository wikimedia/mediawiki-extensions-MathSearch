'use strict';

// Not a test: QUnit only steers the browser, as in Math's SVG reference test.
// normalizeSvg and renderSvg follow
// swh:1:cnt:907c9e1c8717f4ff72f533142f961aedf21d483d;lines=10-77
const pending = require( './pending.json' );

const normalizeSvg = ( svg ) => svg
	.replace( /MJX-\d+-/g, 'MJX-' )
	.replace( /vertical-align:\s*-?0(?:px|ex|em);/g, 'vertical-align: baseline;' );

const nonLazyContainer = document.createElement( 'div' );

const renderSvg = async ( mathml ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = mathml;
	nonLazyContainer.appendChild( div );
	await window.MathJax.typesetPromise( [ div ] );
	const svg = div.querySelector( 'svg' );
	const html = svg ? normalizeSvg( svg.outerHTML ) : null;
	div.remove();
	return html;
};

QUnit.module( 'ext.mathsearch.renderSvg', () => {

	QUnit.test( 'render the pending MathML to SVG', async ( assert ) => {
		await window.MathJax.startup.promise;
		window.MathJax.startup.document.options.lazyAlwaysTypeset = [ nonLazyContainer ];
		for ( const [ key, mathml ] of Object.entries( pending ) ) {
			const svg = await renderSvg( mathml );
			assert.true( svg !== null, key + ' renders to SVG' );
			// SvgRenderer.php scrapes this line.
			// eslint-disable-next-line no-console
			console.log( 'MATHSEARCH_SVG:' + JSON.stringify( { key, svg } ) );
		}
		assert.true( true, 'done' );
	} );

} );

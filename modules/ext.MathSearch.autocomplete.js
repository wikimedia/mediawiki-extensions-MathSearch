/**
 * Add autocomplete suggestions to any input.
 * @author Legoktm
 * Mainly from http://jqueryui.com/autocomplete/
 * and resources/mediawiki/mediawiki.searchSuggest.js
 */

( function ( mw, $ ) {
    $( function () {
        // mw-massmessage-form-spamlist is the id of the field to autocomplete
        $( '#mw-input-wpevalPage' ).autocomplete( {
            source: function( request, response ) {
                // Create a new Api object (see [[RL/DM#mediawiki.api]]
                var api = new mw.Api();
                // Start a "GET" request
                api.get( {
                    action: 'opensearch',
                    search: request.term, // This is the current value of the user's input
                    suggest: ''
                } ).done( function ( data ) {
                    response( data[1] ); // set the results as the autocomplete options
                } );
            }
        } );
    } );
}( mediaWiki, jQuery ) );
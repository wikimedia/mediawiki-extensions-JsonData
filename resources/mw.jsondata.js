// Jsonwidget integration into MediaWiki as JsonData extension
// Copyright (c) 2011-2012 Rob Lanphier.
// See http://robla.net/jsonwidget/LICENSE for license (3-clause BSD-style)

//
// Context object - this adds/strips context text.  This
// is used to add and remove <json>...</json> tags as appropriate.
//

const mwjsondata = function () {};

mwjsondata.context = function () {
	this.addContextText = mwjsondata.context.addContextText;
	this.removeContextText = mwjsondata.context.removeContextText;
	const defaulttag = mw.config.get( 'egJsonDataDefaultTag' );
	this.beginContext = '<' + defaulttag + '>\n';
	this.endContext = '\n</' + defaulttag + '>';
};

mwjsondata.context.addContextText = function ( jsontext ) {
	return this.beginContext + jsontext + this.endContext;
};

// remove and store context
mwjsondata.context.removeContextText = function ( jsontext ) {
	const begintag = /^\s*<[\w]+[^>]*>\s*\n?/m;
	const endtag = /\n?\s*<\/[\w]+>\s*$/m;
	let m = jsontext.match( begintag );
	if ( m !== null ) {
		this.beginContext = m;
	}
	m = jsontext.match( endtag );
	if ( m !== null ) {
		this.endContext = m;
	}
	jsontext = jsontext.replace( begintag, '' );
	jsontext = jsontext.replace( endtag, '' );
	return jsontext;
};

// check for the jsonedit form div
if ( $( '#je_formdiv' ).length > 0 ) {
	// initialize jsonwidget editor (jsonedit.js)
	jsonwidget.language = 'en';
	const je = new jsonwidget.editor();
	let defaultView = 'form';
	je.htmlids.sourcetextarea = 'wpTextbox1';
	je.htmlids.sourcetextform = 'editform';
	je.context = new mwjsondata.context();
	je.schemaContext = new mwjsondata.context();
	je.schemaContext.beginContext = '<jsonschema>\n';
	je.schemaContext.endContext = '\n</jsonschema>';

	if ( $( '#je_schemaexamplebutton' ).length > 0 ) {
		je.schemaEditInit();
		je.views = je.views.concat( [ 'schemaexample' ] );
	}
	if ( $( '#je_previewpane' ).length > 0 ) {
		je.views = je.views.concat( [ 'preview' ] );
		$( '.previewnote' ).insertAfter( '#jump-to-nav' );
		$( '#wikiPreview' ).prependTo( '#editpage-copywarn' );
		je.htmlbuttons.preview = 'je_previewpane';
		const previewHandler = {
			show: function () {
				$( '#wikiPreview' ).show();
			},
			hide: function () {
				$( '#wikiPreview' ).hide();
			}
		};
		je.viewHandler.preview = previewHandler;
		defaultView = 'preview';
	}
	if ( $( '#je_diffpane' ).length > 0 ) {
		je.views = je.views.concat( [ 'diff' ] );
		$( '#wikiDiff' ).prependTo( '#editpage-copywarn' );
		je.htmlbuttons.diff = 'je_diffpane';
		const diffHandler = {
			show: function () {
				$( '#wikiDiff' ).show();
			},
			hide: function () {
				$( '#wikiDiff' ).hide();
			}
		};
		je.viewHandler.diff = diffHandler;
		defaultView = 'diff';
	}
	je.setView( defaultView );
}

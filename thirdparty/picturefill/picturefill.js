/*! Picturefill - Responsive Images that work today. (and mimic the proposed Picture element with divs). Author: Scott Jehl, Filament Group, 2012 | License: MIT/GPLv2 */

(function( w ){

	// Enable strict mode
	"use strict";

	w.picturefill_opts = {
		wrapperTag: 'span',
		imageTag: 'span',
		// transparent 1x1.gif
		loaderImg: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
	};

	w.picturefill_timeout = null;

	w.picturefill = function(options) {
		console.log('PICTURE-FILL');
		if (options && (options.wrapperTag || options.imageTag || options.loaderImg)) {
			options = options;
		}
		else {
			options = w.picturefill_opts;
		}

		var ps = w.document.getElementsByTagName( options.wrapperTag );

		// Loop the pictures
		for( var i = 0, il = ps.length; i < il; i++ ){
			if( ps[ i ].getAttribute( "data-picture" ) !== null ){
				var sources = ps[ i ].getElementsByTagName( options.imageTag ),
					matches = [],
					width = ps[ i ].offsetWidth;

				// See if which sources match
				for( var j = 0, jl = sources.length; j < jl; j++ ){
					var media = sources[ j ].getAttribute( "data-media" ),
						ratio = ps[ j ].getAttribute( "data-ratio" );

					// if there's no media specified, OR w.matchMedia is supported 
					if( !media || ( w.matchMedia && w.matchMedia( media ).matches ) ){

						matches.push( sources[ j ] );
					}
				}

				var picImg = ps[ i ].getElementsByTagName( "img" )[ 0 ],
					img,
					src;

				if( matches.length ){
					if( !picImg ){
						picImg = w.document.createElement( "img" );
						picImg.alt = ps[ i ].getAttribute( "data-alt" );

						ps[ i ].appendChild( picImg );
					}

					img = matches.pop();
					src = img.getAttribute( "data-src");

					if (src != picImg.src) {
						ps[i].removeAttribute('class');
						
						picImg.src = w.picturefill_opts.loaderImg;//img.getAttribute( "data-src");
						picImg.setAttribute('data-original', img.getAttribute( "data-src"));
						picImg.className = 'picturefilled';
						picImg.height = width / parseFloat(img.getAttribute('data-ratio'), 10);
					}
				}
				else if( picImg ){
					ps[ i ].removeChild( picImg );
				}
			}
		}

		var evt = document.createEvent('Events');
		evt.initEvent('picturefilled', true, true); //true for can bubble, true for cancelable
		window.dispatchEvent(evt);
	};

	// Run on resize and domready (w.load as a fallback)
	if( w.addEventListener ){

		// I've added small timeout here to prevent unnecessary image loading during window resizing
		w.addEventListener( "resize", function(){
			if (w.picturefill_timeout) {
				clearTimeout(w.picturefill_timeout);
			}

			w.picturefill_timeout = setTimeout(function() {
				w.picturefill();
			}, 100);
		}, false );
		w.addEventListener( "DOMContentLoaded", function(){
			w.picturefill();
			// Run once only
			w.removeEventListener( "load", w.picturefill, false );
		}, false );
		w.addEventListener( "load", w.picturefill, false );
	}
	else if( w.attachEvent ){
		w.attachEvent( "onload", w.picturefill );
	}

}( this ));
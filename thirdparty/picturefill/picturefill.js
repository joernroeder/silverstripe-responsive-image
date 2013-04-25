/*! Picturefill - Responsive Images that work today. (and mimic the proposed Picture element with divs). Author: Scott Jehl, Filament Group, 2012 | License: MIT/GPLv2 */

(function( w ){

	// Enable strict mode
	"use strict";

	w.picturefill_opts = {
		wrapperTag: 'span',
		imageTag: 'span'
	};

	w.picturefill = function(options) {
		options = options || w.picturefill_opts;

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

						/*if (!ratio) {
							ratios.push(0);
						}
						else {
							ratios.push(width / ratio);
						}*/
					}
				}

				// Find any existing img element in the picture element
				var picImg = ps[ i ].getElementsByTagName( "img" )[ 0 ],
					img;

				if( matches.length ){
					if( !picImg ){
						picImg = w.document.createElement( "img" );
						picImg.alt = ps[ i ].getAttribute( "data-alt" );

						ps[ i ].appendChild( picImg );
					}

					img = matches.pop();

					picImg.src = img.getAttribute( "data-src");
					picImg.height = width / parseFloat(img.getAttribute('data-ratio'), 10);
				}
				else if( picImg ){
					ps[ i ].removeChild( picImg );
				}
			}
		}
	};

	// Run on resize and domready (w.load as a fallback)
	if( w.addEventListener ){
		w.addEventListener( "resize", w.picturefill, false );
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
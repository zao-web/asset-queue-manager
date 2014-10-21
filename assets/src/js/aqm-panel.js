/**
 * Asset Queue Manager
 *
 * Generate a management panel to view assets, dequeue assets and re-
 * queue them.
 */

var aqmPanel;

jQuery(document).ready(function ($) {

	// Bail early if we're missing the aqm or aqmData vars. We won't get
	// very far without them.
	if ( typeof aqm === undefined || typeof aqmData === undefined ) {
		return;
	}

	aqmPanel = {

		// Admin bar nav menu item
		admin_bar_el : $( '#wp-admin-bar-asset-queue-manager' ),

		// Add the management panel
		init : function() {

			// Create the containing div
			var el = document.createElement( 'div' );
			el.id = 'aqm-panel';
			document.body.appendChild( el );

			// Store a jQuery object of the element
			this.el = $( '#' + el.id );

			// Build the initial assets panel
			this.el.html(
				'<div class="section head scripts">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-tools"></div>' +
						aqm.strings.head_scripts +
					'</h3>' +
				'</div>' +
				'<div class="section head styles">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-appearance"></div>' +
						aqm.strings.head_styles +
					'</h3>' +
				'</div>' +
				'<div class="section footer scripts">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-tools"></div>' +
						aqm.strings.footer_scripts +
					'</h3>' +
				'</div>' +
				'<div class="section head styles">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-appearance"></div>' +
						aqm.strings.footer_styles +
					'</h3>' +
				'</div>' +
				'<div class="section dequeued styles">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-tools"></div>' +
						aqm.strings.dequeued_scripts +
					'</h3>' +
				'</div>' +
				'<div class="section dequeued scripts">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-appearance"></div>' +
						aqm.strings.dequeued_styles +
					'</h3>' +
				'</div>'
			);

			// Add assets to each section
			for ( var loc in aqmData.assets ) {
				for ( var type in aqmData.assets[loc] ) {
					for ( var handle in aqmData.assets[loc][type] ) {
						this.appendAsset( aqmData.assets[loc][type][handle], loc, type );
					}
				}
			}

		},

		// Add an asset to the panel
		appendAsset : function( asset, loc, type ) {

			var html = '<div class="asset handle-' + asset.handle + ' ' + type + '" data-type="' + type + '" data-handle="' + asset.handle + '">' +
				'<div class="header">' +
					'<div class="handle">' + asset.handle + '</div>' +
					'<div class="src">' + asset.src + '</div>' +
					'<div class="dashicons dashicons-arrow-down"></div>' +
				'</div>' +
				'<div class="body">';

			// Add notices
			html += this.getAssetNotices( asset );
			
			// Add dependencies
			if ( asset.deps.length ) {
				html += '<p class="deps"><strong>' + aqm.strings.deps + '</strong> ' + asset.deps.join( ', ' ) + '</p>';
			}

			// Add action links
			html += '<div class="links">';

			if ( loc !== 'dequeued' ) {
				html += '<a href="#" class="dequeue">' + aqm.strings.dequeue + '</a>';

				var url = this.getAssetURL( asset );
				if ( url !== false ) {
					html += '<a href="' + this.getAssetURL( asset ) + '" target="_blank" class="view">' + aqm.strings.view + '</a>';
				}

			} else {
				html += '<a href="#" class="enqueue">' + aqm.strings.enqueue + '</a>';
			}

			html += '</div>'; // .links
			html += '</div>'; // .body
			html += '</div>'; // .asset

			this.el.find( '.section.' + loc + '.' + type ).append( html );

		},

		// Get a notice if one exists for this asset
		getAssetNotices : function( asset ) {

			var notices = '';

			for ( var notice in aqmData.notices ) {
				for ( var handle in aqmData.notices[notice].handles ) {
					if ( handle === asset.handle ) {
						notices += '<p class="notice ' + notice + '">' + aqmData.notices[notice].msg + '</p>';
					}
				}
			}

			if ( asset.src === false ) {
				notices += '<p class="notice no-src">' + aqm.strings.no_src + '</p>';
			}

			return notices;
		},

		// Try to get a good URL for this asset. This is just kind of
		// guessing, really.
		getAssetURL : function( asset ) {

			var url = asset.src.toString();
			if ( url.substring( 0, 2 ) === '//' ) {
				link = 'http:' + asset.src;
			} else if ( url.substring( 0, 1 ) === '/' ) {
				url = aqm.siteurl + asset.src;
			} else if ( url.substring( 0, 4 ) === 'http' ) {
				url = asset.src;
			}

			return url;
		}
	};

	console.log( aqmData );

	aqmPanel.init();

});

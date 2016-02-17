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

		// Management panel element
		el : $( '#aqm-panel' ),

		// Menu element in the admin bar
		menu_el : $( '#wp-admin-bar-asset-queue-manager' ),

		// Add the management panel
		init : function() {

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
				'<div class="section footer styles">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-appearance"></div>' +
						aqm.strings.footer_styles +
					'</h3>' +
				'</div>' +
				'<div class="section dequeued scripts">' +
					'<h3>' +
						'<div class="dashicons dashicons-admin-tools"></div>' +
						aqm.strings.dequeued_scripts +
					'</h3>' +
				'</div>' +
				'<div class="section dequeued styles">' +
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

			// Register open/close clicks on the whole panel
			this.menu_el.click( function() {
				aqmPanel.toggle();
			});

			// Remove the emergency fallback panel
			this.menu_el.find( '.inactive' ).remove();

		},

		// Add an asset to the panel
		appendAsset : function( asset, loc, type ) {

			var html = '<div class="asset handle-' + asset.handle + ' ' + type + '" data-type="' + type + '" data-handle="' + asset.handle + '" data-location="' + loc + '">' +
				'<div class="header">' +
					'<div class="handle">' + asset.handle + '</div>' +
					'<div class="src">' + asset.src + '</div>' +
					'<div class="dashicons dashicons-arrow-down"></div>' +
				'</div>' +
				'<div class="body">';

			// Add input field for quick URL selection
			if ( typeof asset.src !== 'undefined' && asset.src.length ) {
				html += '<div class="src_input"><input type="text" value="' + asset.src + '" readonly="readonly"></div>';
			}

			// Add notices
			html += this.getAssetNotices( asset );
			
			// Add dependencies
			if ( typeof asset.deps !== 'undefined' && asset.deps.length ) {
				html += '<p class="deps"><strong>' + aqm.strings.deps + '</strong> ' + asset.deps.join( ', ' ) + '</p>';
			}

			// Add action links
			html += '<div class="links">';

			if ( loc === 'dequeued' ) {
				html += '<a href="#" class="enqueue">' + aqm.strings.enqueue + '</a>';
			} else {
				html += '<a href="#" class="dequeue">' + aqm.strings.dequeue + '</a>';
			}

			var url = this.getAssetURL( asset );
			if ( url !== 'false' ) {
				html += '<a href="' + this.getAssetURL( asset ) + '" target="_blank" class="view">' + aqm.strings.view + '</a>';
			}

			html += '</div>'; // .links
			html += '</div>'; // .body
			html += '</div>'; // .asset

			this.el.find( '.section.' + loc + '.' + type ).append( html );

			var cur = this.el.find( '.asset.handle-' + asset.handle.replace(/\./g, "\\.") + '.' + type );

			// Register click function to open/close asset panel
			cur.click( function() {
				aqmPanel.toggleAsset( $(this) );
			});

			// Register click function to select all in disabled source input field
			this.enableSrcSelect( cur.find( '.src_input input' ) );

			// Register click function to dequeue/re-enqueue asset
			cur.find( '.links .dequeue, .links .enqueue' ).click( function(e) {
				e.stopPropagation();
				e.preventDefault();

				// Bail early if we've already sent a request
				if ( $(this).hasClass( 'sending' ) ) {
					return;
				}

				$(this).addClass( 'sending' );

				var asset = $(this).parents( '.asset' );

				aqmPanel.toggleQueueState( asset.data( 'handle' ), asset.data( 'location' ), asset.data( 'type' ), $(this).hasClass( 'dequeue' ) );
			});

		},

		// Get a notice if one exists for this asset
		getAssetNotices : function( asset ) {

			var notices = '';

			for ( var notice in aqmData.notices ) {
				for ( var handle in aqmData.notices[notice].handles ) {
					if ( aqmData.notices[notice].handles[handle] === asset.handle ) {
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
		},

		// Open/close the panel
		toggle : function() {

			if ( this.menu_el.hasClass( 'open' ) ) {
				this.el.slideUp();
				this.el.removeClass( 'open' );
				this.menu_el.removeClass( 'open' );
			} else {
				this.el.addClass( 'open' );
				this.menu_el.addClass( 'open' );
				this.el.slideDown();
			}
		},

		// Open/close an asset panel
		toggleAsset : function( asset ) {

			if ( asset.hasClass( 'open' ) ) {
				asset.removeClass( 'open' );
			} else {
				asset.addClass( 'open' );
			}
		},

		// Enable the auto-selection in the source field
		enableSrcSelect : function( el ) {

			// Don't bubble up to the open/close asset toggle
			el.click( function(e) {
				e.stopPropagation();
			});

			el.click( function() {
				this.select();
			});
		},

		// Send an Ajax request to dequeue or re-enqueue an asset
		toggleQueueState : function( handle, location, type, dequeue ) {

			var asset = this.el.find( '.asset.handle-' + handle + '.' + type );

			asset.find( '.body .notice.request' ).remove();
			asset.find( '.body' ).append( '<p class="notice request"><span class="spinner"></span>' + aqm.strings.sending + '</p>' );

			var data = $.param({
				action: 'aqm-modify-asset',
				nonce: aqm.nonce,
				handle: handle,
				type: type,
				dequeue: dequeue,
				asset_data: aqmData.assets[location][type][handle]
			});

			var jqxhr = $.post( aqm.ajaxurl, data, function( r ) {

				var notice = asset.find( '.notice.request' );

				if ( r.success ) {

					// If we got a successful return but no data,
					// something's gone wonky.
					if ( typeof r.data == 'undefined' ) {
						notice.addClass( 'error' ).text( aqm.strings.unknown_error );
						console.log( r );

						return;
					}

					notice.slideUp( null, function() {
						$(this).remove();
					});

					if ( r.data.dequeue ) {
						asset.fadeOut( null, function() {
							$(this).remove();
						});
						
						aqmPanel.appendAsset( r.data.option[r.data.type][r.data.handle], 'dequeued', r.data.type );

						// Add this the array of dequeued assets so
						// the data can be retrieved if they want to
						// stop dequeuing it. Ideally we'd also remove
						// the asset data from the enqueued asset arrays
						// but this will do for now.
						if ( aqmData.assets.dequeued === false ) {
							aqmData.assets.dequeued = [];
						}
						if ( typeof aqmData.assets.dequeued[type] === 'undefined' ) {
							aqmData.assets.dequeued[type] = [];
						}
						aqmData.assets.dequeued[type][r.data.handle] = aqmData.assets[location][type][handle];

					} else {
						asset.addClass( 'requeued' ).find( '.body' ).empty().append( '<p class="notice requeued">' + aqm.strings.requeued + '</p>' );
					}

				} else {
					
					if ( typeof r.data == 'undefined' || typeof r.data.msg == 'undefined' ) {
						notice.addClass( 'error' ).text( aqm.strings.unknown_error );
					} else {
						notice.addClass( 'error' ).text( r.data.msg );
					}
				}

				asset.find( '.links .sending' ).removeClass( 'sending' );
			});

		}
	};

	aqmPanel.init();

});

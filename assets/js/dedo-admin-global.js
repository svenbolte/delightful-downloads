jQuery( document ).ready( function( $ ) {

	/**
	 * General
	 *
	 * General JS for admin area.
	 *
	 * @since  1.4
	 */
	var DEDO_Admin = {

		init: function() {
			this.confirmAction();
		},

		confirmAction: function() {
			var $confirmAction = $( '.dedo_confirm_action' );

			$confirmAction.on( 'click', function( e ) {

				if ( !confirm( $confirmAction.data( 'confirm' ) ) ) {
					e.preventDefault();
				}

			} );
		}
	};

	DEDO_Admin.init();

	/**
	 * Dashboard
	 *
	 * Updates dashboard popular downloads.
	 *
	 * @since  1.4
	 */
	var DEDO_Dashboard = {

		$popularDownloadsDropdown: $( '#popular-downloads-dropdown' ),
		$popularDownloadsSpinner: $( '#ddownload-popular .spinner' ),
		$popularDownloadsError: $( '#ddownload-popular .error' ),

		init: function( options ) {
			this.options = options;
			this.popularDownloadsChange();
		},

		popularDownloadsChange: function() {
			
			this.$popularDownloadsDropdown.on( 'change', function() {
				DEDO_Dashboard.popularDownloadsGet( $( this ).val() );
			} );
		},

		popularDownloadsGet: function( value ) {
			
			var self = this;

			// Show loading
			this.$popularDownloadsSpinner.css( 'display', 'inline-block' );

			// Send ajax request
			$.ajax( {
				url: self.options.ajaxURL,
				data: {
					action: self.options.action,
					nonce: self.options.nonce,
					days: value
				},
				dataType: 'json',
				success: function( response ) {
					self.popularDownloadsSuccess( response );
				},
				error: function() {
					self.popularDownloadsError();
				}
			} );

		},

		popularDownloadsSuccess: function( response ) {

			// Hide error 
			this.$popularDownloadsError.fadeOut( 300 );

			// Hide loading
			this.$popularDownloadsSpinner.css( 'display', 'none' );

			// Request successful
			if ( 'success' === response.status ) {
				
				var output = '<ol id="popular-downloads" style="display: none;">';

				// Success, build list
				$.each( response.content, function( key, value) {
				
					output += '<li>';
					output += '<a href="' + value.url + '">' + value.title + ' <span class="count">' + value.downloads + '</span></a>';
					output += '</li>';
				} );

				output += '</ol>';

				// Slide out and remove old list
				$( '#popular-downloads' ).slideUp( 300, function() {
					$( this ).remove();
					
					// Insert new list and fade in
					$( output ).insertAfter( '#ddownload-popular h4' );
					$( '#popular-downloads' ).slideDown( 300 );
				});
				
			}
			else {
				this.popularDownloadsError();
			}
		},

		popularDownloadsError: function() {

			// Show error
			this.$popularDownloadsError.text( this.options.errorText ).fadeIn( 300 );
		}
	};
	
	if ( 'undefined' !== typeof DEDOPopularDownloads ) {
		DEDO_Dashboard.init( DEDOPopularDownloads );
	}

} );
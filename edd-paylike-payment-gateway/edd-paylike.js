( function( $, window, document, undefined ) {
	'use strict';

	var checkout_modal_shown = false;
	var eddPaylike = {};
	window.paylikeVars = edd_paylike_vars;

	// Closure
	( function() {
		/**
		 * Decimal adjustment of a number.
		 *
		 * @param {String}  type  The type of adjustment.
		 * @param {Number}  value The number.
		 * @param {Integer} exp   The exponent (the 10 logarithm of the adjustment base).
		 * @returns {Number} The adjusted value.
		 */
		function decimalAdjust( type, value, exp ) {
			// If the exp is undefined or zero...
			if ( typeof exp === 'undefined' || +exp === 0 ) {
				return Math[ type ]( value );
			}
			value = +value;
			exp = +exp;
			// If the value is not a number or the exp is not an integer...
			if ( isNaN( value ) || ! ( typeof exp === 'number' && exp % 1 === 0 ) ) {
				return NaN;
			}
			// Shift
			value = value.toString().split( 'e' );
			value = Math[ type ]( +( value[ 0 ] + 'e' + ( value[ 1 ] ? ( +value[ 1 ] - exp ) : -exp ) ) );
			// Shift back
			value = value.toString().split( 'e' );
			return +( value[ 0 ] + 'e' + ( value[ 1 ] ? ( +value[ 1 ] + exp ) : exp ) );
		}

		// Decimal round
		if ( ! Math.round10 ) {
			Math.round10 = function( value, exp ) {
				return decimalAdjust( 'round', value, exp );
			};
		}
		// Decimal floor
		if ( ! Math.floor10 ) {
			Math.floor10 = function( value, exp ) {
				return decimalAdjust( 'floor', value, exp );
			};
		}
		// Decimal ceil
		if ( ! Math.ceil10 ) {
			Math.ceil10 = function( value, exp ) {
				return decimalAdjust( 'ceil', value, exp );
			};
		}
	} )();


	function set_window_amount() {
		// set amount variable for automated testing
		var amount = $( '.edd_cart_total .edd_cart_amount' ).data( 'total' );

		amount = Math.ceil( amount * edd_paylike_vars.multiplier );
		//automated testing purposes.
		window.paylikeAmount = amount;
	}


	if ( ! edd_paylike_vars.publishable_key ) {
		alert( edd_paylike_vars.no_key_error );
	}

	function edd_paylike_get_args() {
		var email = $( '#edd-email' ).val(),
			amount = $( '.edd_cart_total .edd_cart_amount' ).data( 'total' );

		amount = Math.ceil( amount * edd_paylike_vars.multiplier );

		var args = {
			test: ('test' == edd_paylike_vars.test_mode) ? (true) : (false),
			title: edd_paylike_vars.store_name,
			amount: {
				currency: edd_paylike_vars.currency,
				exponent: Number(edd_paylike_vars.exponent),
				value: amount
			},
			locale: edd_paylike_vars.locale,
			description: edd_paylike_vars.payment_description,
			custom: {
				//orderId: '',
				products: edd_paylike_vars.products,
				customer: {
					name: $( '#edd-first' ).val() + ' ' + $( '#edd-last' ).val(),
					email: email,
					ip: edd_paylike_vars.customer_ip
				},
				platform: {
					name: 'WordPress',
					version: edd_paylike_vars.platform_version,
				},
				ecommerce: 'Easy Digital Downloads',
				paylike_plugin: {
					version: edd_paylike_vars.version
				}
			}
		}

		if ( eddPaylike.pares ) {
			args.tds = { pares: eddPaylike.pares };
			delete eddPaylike.pares;
		}

		return args;
	}

	// ======================================================
	// Document ready event
	// ======================================================
	$( document ).ready( function() {
		var $body = $( 'body' );

		set_window_amount();

		// non ajaxed
		$body.on( 'click', '#edd_purchase_form input[type="submit"], #edd_profile_editor_form input[type="submit"]', function( event ) {

			// copied form edd-ajax.js
			var eddPurchaseform = document.getElementById( 'edd_purchase_form' );
			if ( typeof eddPurchaseform.checkValidity === "function" && false === eddPurchaseform.checkValidity() ) {
				return;
			}

			if ( $( this ).attr( 'name' ) === 'edd_login_submit' ) {
				return;
			}

			if ( ( $( 'input[name="edd-gateway"]' ).val() === 'paylike' && $( '.edd_cart_total .edd_cart_amount' ).data( 'total' ) > 0 ) ) {

				$( this ).after( '<span class="edd-cart-ajax"><i class="edd-icon-spinner edd-icon-spin"></i></span>' );

				// stops from triggering edd default callback
				if ( ! $( 'input[name="edd_paylike_token"]' ).length ) {
					event.stopPropagation();
				} else {
					event.preventDefault();
					return;
				}

				event.preventDefault();

				if ( 'true' === edd_paylike_vars.checkout ) {
					if ( checkout_modal_shown ) {
						return;
					}

					checkout_modal_shown = true;

					var paylike = Paylike( {key: edd_paylike_vars.publishable_key} );
					var args = edd_paylike_get_args();

					paylike.pay( args,
						function( err, res ) {
							var $purchaseBtn = jQuery( '#edd-purchase-button' );
							// we need to close no matter what.
							checkout_modal_shown = false;
							jQuery( '.edd-cart-ajax' ).hide();
							$purchaseBtn.prop( 'disabled', false );

							// if we have an error we bail out
							if ( err && err == 'closed' ) {
								return false;
							} else if ( err ) {
								alert( edd_paylike_vars.error_prefix + err.text );
								return false;
							}

							var form$ = jQuery( "#edd_purchase_form, #edd_profile_editor_form" );

							// insert the transaction id into the form so it gets submitted to the server
							var trxid = res.transaction.id;
							form$.append( "<input type='hidden' name='edd_paylike_token' value='" + trxid + "' />" );

							$purchaseBtn.val( edd_paylike_vars.submit_text );

							// and submit
							var submit_button = form$.find( 'input[type="submit"][name!="edd_login_submit"]' );
							submit_button.click();
						}
					);
				} else {
					window.paylike_process_embedded_form();
				}
			}
		} );
	} );

} )( jQuery, window, document );

<?php

/**
 * Load our javascript
 * @access      public
 * @since       1.0.0
 *
 * @param bool $override Allows registering paylike.js on pages other than is_checkout()
 *
 * @return      void
 */
function edd_paylike_js( $override = false ) {
	if ( function_exists( 'edd_is_checkout' ) ) {
		$publishable_key = null;
		if ( edd_is_test_mode() ) {
			$publishable_key = edd_get_option( 'paylike_test_publishable_key', '' );
		} else {
			$publishable_key = edd_get_option( 'paylike_live_publishable_key', '' );
		}
		if ( ( edd_is_checkout() || $override ) ) {
			wp_enqueue_script( 'paylike-js', 'https://sdk.paylike.io/3.js', '', '3.0', true );
			wp_enqueue_script( 'edd-paylike-js', EDD_PAYLIKE_PLUGIN_URL . 'edd-paylike.js', array(
				'jquery',
				'paylike-js'
			), EDD_PAYLIKE_VERSION );
			/* retrieving data about current format */
			$currency   = edd_get_currency();
			$manager    = new \Paylike\Data\Currencies();
			$multiplier = $manager->getPaylikeMultiplier( $currency );

			$paylike_vars = apply_filters( 'edd_paylike_js_vars', array(
				'publishable_key'     => trim( $publishable_key ),
				'is_ajaxed'           => edd_is_ajax_enabled() ? 'true' : 'false',
				'is_zero_decimal'     => edd_paylike_is_zero_decimal_currency() ? 'true' : 'false',
				'checkout'            => edd_get_option( 'paylike_disable_checkout' ) ? 'false' : 'true',
				'store_name'          => edd_get_option( 'paylike_popup_title', get_bloginfo( 'name' ) ),
				'submit_text'         => __( 'Next', 'edd-paylike' ),
				'no_key_error'        => __( 'The Paylike Public Key is missing. Insert it in Settings -> Payment Gateways -> Paylike', 'edd-paylike' ),
				'error_prefix'        => __( 'The following error occurred: ', 'edd-paylike' ),
				'payment_description' => edd_paylike_get_cart_description(),
				'currency'            => $currency,
				'multiplier'          => $multiplier,
				'locale'              => get_locale(),
				//'orderId'             => '',
				'products'            => edd_paylike_get_cart_products(),
				//'name'                => '',
				//'email'               => '',
				//'telephone'           => '',
				//'address'           => '',
				'customer_ip'         => edd_paylike_get_client_ip(),
				'platform'            => 'WordPress',
				'platform_version'    => get_bloginfo( 'version' ),
				'ecommerce'           => 'easy-digital-downloads',
				'version'             => EDD_PAYLIKE_VERSION
			) );
			wp_localize_script( 'edd-paylike-js', 'edd_paylike_vars', $paylike_vars );

		}
	}
}

add_action( 'wp_enqueue_scripts', 'edd_paylike_js', 100 );
/**
 * Load our admin javascript
 * @access      public
 * @since       1.0.0
 * @return      void
 */
function edd_paylike_admin_js( $payment_id = 0 ) {
	if ( 'paylike' !== edd_get_payment_gateway( $payment_id ) ) {
		return;
	}
	?>
	<script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('select[name=edd-payment-status]').change(function () {

                if ('refunded' == $(this).val()) {

                    // Localize refund label
                    var edd_paylike_refund_charge_label = "<?php echo esc_js( __( 'Refund Transaction in Paylike', 'edd-paylike' ) ); ?>";

                    $(this).parent().parent().append('<input type="checkbox" id="edd_refund_in_paylike" name="edd_refund_in_paylike" value="1" style="margin-top: 0;" />');
                    $(this).parent().parent().append('<label for="edd_refund_in_paylike">' + edd_paylike_refund_charge_label + '</label>');

                } else {

                    $('#edd_refund_in_paylike').remove();
                    $('label[for="edd_refund_in_paylike"]').remove();

                }

            });
        });
	</script>
	<?php

}

add_action( 'edd_view_order_details_before', 'edd_paylike_admin_js', 100 );

/**
 * Returns script for buy now button
 */
function edd_paylike_get_link_form_script( $download_id, $email ) {
	// such that we get rid of additional markup
	$product_title = the_title_attribute( array(
		'echo' => false,
		'post' => $download_id
	) );
	?>
	<script>
		<?php ob_start(); ?>
        jQuery(document).ready(function ($) {

            var edd_global_vars;
            var edd_scripts;
            var form;

            $('#edd_purchase_<?php echo $download_id; ?> .edd-add-to-cart,.edd_purchase_<?php echo $download_id; ?> .edd-add-to-cart').click(function (e) {

                form = $(this).parents('.edd_download_purchase_form');

                e.preventDefault();

                var label = form.find('.edd-add-to-cart-label').text();

                if (form.find('.edd_price_options').length || form.find('.edd_price_option_<?php echo $download_id; ?>').length) {

                    var custom_price = false;
                    var price_id;
                    var prices = [];
                    var amount = 0;

					<?php foreach( edd_get_variable_prices( $download_id ) as $price_id => $price ) : ?>
                    prices[<?php echo $price_id; ?>] = <?php echo edd_paylike_get_minor_amount( $price['amount'] ); ?>;
					<?php endforeach; ?>

                    if (form.find('.edd_price_option_<?php echo $download_id; ?>').length > 1) {

                        if (form.find('.edd_price_options input:checked').hasClass('edd_cp_radio')) {

                            custom_price = true;
                            amount = Math.ceil(form.find('.edd_cp_price').val() * edd_paylike_vars.multiplier);

                        } else {
                            price_id = form.find('.edd_price_options input:checked').val();
                        }

                    } else {

                        price_id = form.find('.edd_price_option_<?php echo $download_id; ?>').val();

                    }

                    if (!custom_price) {

                        amount = prices[price_id];

                    }

                } else if (form.find('.edd_cp_price').length && form.find('.edd_cp_price').val()) {
                    amount = Math.ceil(form.find('.edd_cp_price').val() * edd_paylike_vars.multiplier);

                } else {
                    amount = <?php echo edd_paylike_get_minor_amount( edd_get_download_price( $download_id ) );  ?>;
                }

                var paylike = Paylike(edd_paylike_vars.publishable_key);

                var args = {
                    title: edd_paylike_vars.store_name,
                    currency: edd_paylike_vars.currency,
                    amount: amount,
                    locale: edd_paylike_vars.locale,
                    description: edd_paylike_vars.payment_description,
                    custom: {
                        //orderId: '',
                        products: '<?php echo esc_js( $product_title ); ?>',
                        //name: name,
                        email: '<?php echo esc_js( $email ); ?>',
                        //telephone: '',
                        //address: '',
                        customerIP: edd_paylike_vars.customer_ip,
                        locale: edd_paylike_vars.locale,
                        platform: edd_paylike_vars.platform,
                        platform_version: edd_paylike_vars.platform_version,
                        ecommerce: edd_paylike_vars.ecommerce,
                        version: edd_paylike_vars.version
                    }
                };

                paylike.popup(args,
                    function (err, res) {
                        form.find('.edd-add-to-cart').removeAttr('data-edd-loading');
                        form.find('.edd-add-to-cart-label').text(label).show();

                        // if we have an error we bail out
                        if (err && err == 'closed') {
                            return false;
                        } else if (err) {
                            alert(edd_paylike_vars.error_prefix + err.text);
                            return false;
                        }

                        // insert the transaction id into the form so it gets submitted to the server
                        var trxid = res.transaction.id;
                        form.append("<input type='hidden' name='edd_paylike_token' value='" + trxid + "' />");
                        form.append("<input type='hidden' name='edd_email' value='" + '<?php echo esc_js( $email ); ?>' + "' />");
                        // submit
                        form.get(0).submit();
                    }
                );

                return false;
            });
        });
		<?php $script = ob_get_clean(); ?>
	</script>
	<?php
	return $script;
}
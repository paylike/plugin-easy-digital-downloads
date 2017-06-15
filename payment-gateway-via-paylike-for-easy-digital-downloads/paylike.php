<?php
/**
 * Process paylike checkout submission
 * @access      public
 * @since       1.0.0
 * @return      void
 */
function edd_paylike_process_payment( $purchase_data ) {
    global $edd_options, $edd_paylike_is_buy_now;
    if ( edd_is_test_mode() ) {
        $secret_key = trim( $edd_options['paylike_test_secret_key'] );
    } else {
        $secret_key = trim( $edd_options['paylike_live_secret_key'] );
    }
    // make sure we don't have any left over errors present
    edd_clear_errors();
    if ( ! isset( $_POST['edd_paylike_token'] ) ) {
        // no paylike token
        edd_set_error( 'no_token', __( 'Missing Paylike token. Please contact support.', 'edd-paylike' ) );
        edd_record_gateway_error( __( 'Missing Paylike Token', 'edd-paylike' ), __( 'A Paylike token failed to be generated. Please check Paylike logs for more information', 'edd-paylike' ) );

    }
    Paylike\Client::setKey( $secret_key );
    $payment_data   = array(
        'price'        => $purchase_data['price'],
        'date'         => $purchase_data['date'],
        'user_email'   => $purchase_data['user_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'currency'     => edd_get_currency(),
        'downloads'    => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info'    => $purchase_data['user_info'],
        'status'       => 'pending',
        'gateway'      => 'paylike'
    );
    $has_error      = true;
    $transaction_id = sanitize_text_field( $_POST['edd_paylike_token'] );
    if ( edd_paylike_is_zero_decimal_currency() ) {
        $amount = $purchase_data['price'];
    } else {
        $amount = $purchase_data['price'] * 100;
    }
    if ( isset( $edd_options['paylike_preapprove_only'] ) ) {
        $response = Paylike\Transaction::fetch( $transaction_id );
        // the transaction is created and pre-approved via frontend modal
        // we only test that the transaction sent matches the amount
        // and currency we have in the order.
        if ( edd_paylike_is_successful_response( $response ) && edd_paylike_check_response_data( $response, edd_get_currency(), $amount ) ) {
            $payment = edd_insert_payment( $payment_data );
            edd_set_payment_transaction_id( $payment, $transaction_id );
            edd_insert_payment_note( $payment, 'Paylike Transaction ID: ' . $transaction_id );
            edd_update_payment_status( $payment, 'preapproval' );
            add_post_meta( $payment, '_edd_paylike_captured', false );
            $has_error = false;
        } else {
            edd_record_gateway_error( __( 'Fetching Transaction Failed', 'edd-paylike' ), sprintf( __( "Fetching transaction failed while processing a preapproved only payment. \nPayment Data: %s \nError: %s", 'edd-paylike' ), json_encode( $payment_data ), edd_paylike_get_response_error( $response ) ) );
        }
    } else {
        // we try to capture the amount we have in the order,
        // this will fail if the transaction sent doesn't have at least
        // the sent amount and the same currency
        $data     = array(
            'amount'   => $amount,
            'currency' => $payment_data['currency']
        );
        $response = Paylike\Transaction::capture( $transaction_id, $data );
        if ( edd_paylike_is_successful_response( $response ) ) {
            $payment = edd_insert_payment( $payment_data );
            edd_set_payment_transaction_id( $payment, $transaction_id );
            edd_insert_payment_note( $payment, 'Paylike Transaction ID: ' . $transaction_id );
            edd_update_payment_status( $payment, 'publish' );
            add_post_meta( $payment, '_edd_paylike_captured', true );
            $has_error = false;
        } else {
            edd_record_gateway_error( __( 'Capturing Transaction Failed', 'edd-paylike' ), sprintf( __( "Capturing transaction failed while processing a payment.\nPayment Data: %s \nError: %s", 'edd-paylike' ), json_encode( $payment_data ), edd_paylike_get_response_error( $response ) ) );
        }
    }
    // empty cart, and send to confirmation page if we
    // don't have any errors
    if ( $has_error ) {
        edd_set_error( 'payment_not_recorded', __( 'Your payment could not be recorded, please contact the site administrator.', 'edd-paylike' ) );
        if ( $edd_paylike_is_buy_now ) {
            wp_die( 'Your payment could not be recorded, please contact the site administrator.', __( 'Card Processing Error', 'edd-paylike' ) );
        } else {
            // if errors are present, send the user back to the purchase page so they can be corrected
            edd_send_back_to_checkout( '?payment-mode=paylike' );
        }

    } else {
        edd_empty_cart();
        edd_send_to_success_page();
    }
}

add_action( 'edd_gateway_paylike', 'edd_paylike_process_payment' );
/**
 * Register our settings section
 * @return array
 */
function edd_paylike_settings_section( $sections ) {
    $sections['edd-paylike'] = __( 'Paylike', 'edd-paylike' );

    return $sections;
}

add_filter( 'edd_settings_sections_gateways', 'edd_paylike_settings_section' );
/**
 * Register the gateway settings
 * @access      public
 * @since       1.0.0
 * @return      array
 */
function edd_paylike_add_settings( $settings ) {
    $paylike_settings = array(
        array(
            'id'   => 'paylike_settings',
            'name' => '<strong>' . __( 'Paylike Settings', 'edd-paylike' ) . '</strong>',
            'desc' => __( 'Configure the Paylike settings', 'edd-paylike' ),
            'type' => 'header'
        ),
        array(
            'id'   => 'paylike_test_publishable_key',
            'name' => __( 'Test mode Public Key', 'edd-paylike' ),
            'desc' => __( 'Get it from your Paylike dashboard', 'edd-paylike' ),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'paylike_test_secret_key',
            'name' => __( 'Test mode App Key', 'edd-paylike' ),
            'desc' => __( 'Get it from your Paylike dashboard', 'edd-paylike' ),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'paylike_live_publishable_key',
            'name' => __( 'Live mode Public Key', 'edd-paylike' ),
            'desc' => __( 'Get it from your Paylike dashboard', 'edd-paylike' ),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'paylike_live_secret_key',
            'name' => __( 'Live mode App Key', 'edd-paylike' ),
            'desc' => __( 'Get it from your Paylike dashboard', 'edd-paylike' ),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'paylike_preapprove_only',
            'name' => __( 'Preapprove Only?', 'edd-paylike' ),
            'desc' => __( 'Check this if you would like to preapprove payments but not charge until a later date.<br/> To capture a preapproved payment use the buttons you will find in the payment history for all approval pending orders.<br/> The buttons are located in the "Preapproval" column.', 'edd-paylike' ),
            'type' => 'checkbox'
        ),
        array(
            'id'   => 'paylike_checkout_settings',
            'name' => __( 'Paylike checkout mode', 'edd-paylike' ),
            'type' => 'header'
        ),
        array(
            'id'   => 'paylike_disable_checkout',
            'name' => __( 'Disable Paylike Popup', 'edd-paylike' ),
            'desc' => __( 'Check this if you would like to disable the Paylike popup window on the main checkout screen and use the embedded form.', 'edd-paylike' ),
            'type' => 'checkbox'
        ),
        array(
            'id'          => 'paylike_popup_title',
            'name'        => __( 'Payment popup title', 'edd-paylike' ),
            'desc'        => __( 'The text shown in the popup where the customer inserts the card details', 'edd-paylike' ),
            'type'        => 'text',
            'placeholder' => get_bloginfo( 'name' ),
            'size'        => 'regular',
            'std'         => get_bloginfo( 'name' ),
        ),
        array(
            'id'          => 'paylike_method_title',
            'name'        => __( 'Payment method title', 'edd-paylike' ),
            'desc'        => '',
            'type'        => 'text',
            'size'        => 'regular',
            'placeholder' => __( 'Credit Card', 'edd-paylike' ),
            'std'         => __( 'Credit Card', 'edd-paylike' ),
        )
    );
    $paylike_settings = array( 'edd-paylike' => $paylike_settings );

    return array_merge( $settings, $paylike_settings );
}

add_filter( 'edd_settings_gateways', 'edd_paylike_add_settings' );
/**
 * Register our new payment status labels for EDD
 * @since 1.0.0
 * @return array
 */
function edd_paylike_payment_status_labels( $statuses ) {
    $statuses['preapproval'] = __( 'Preapproved', 'edd-paylike' );
    $statuses['cancelled']   = __( 'Cancelled', 'edd-paylike' );

    return $statuses;
}

add_filter( 'edd_payment_statuses', 'edd_paylike_payment_status_labels' );
/**
 * Display the Preapprove column label
 * @since 1.0.0
 * @return array
 */
function edd_paylike_payments_column( $columns ) {
    global $edd_options;
    if ( isset( $edd_options['paylike_preapprove_only'] ) ) {
        $columns['preapproval'] = __( 'Preapproval', 'edd-paylike' );
    }

    return $columns;
}

add_filter( 'edd_payments_table_columns', 'edd_paylike_payments_column' );
/**
 * Display the payment status filters
 * @since 1.0.0
 * @return array
 */
function edd_paylike_payment_status_filters( $views ) {
    $payment_count        = wp_count_posts( 'edd_payment' );
    $preapproval_count    = '&nbsp;<span class="count">(' . $payment_count->preapproval . ')</span>';
    $cancelled_count      = '&nbsp;<span class="count">(' . $payment_count->cancelled . ')</span>';
    $current              = isset( $_GET['status'] ) ? $_GET['status'] : '';
    $views['preapproval'] = sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( 'status', 'preapproval', admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) ), $current === 'preapproval' ? ' class="current"' : '', __( 'Preapproval Pending', 'edd-paylike' ) . $preapproval_count );
    $views['cancelled']   = sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( 'status', 'cancelled', admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) ), $current === 'cancelled' ? ' class="current"' : '', __( 'Cancelled', 'edd-paylike' ) . $cancelled_count );

    return $views;
}

add_filter( 'edd_payments_table_views', 'edd_paylike_payment_status_filters' );
/**
 * Show the Process / Cancel buttons for preapproved payments
 * @since 1.0.0
 * @return string
 */
function edd_paylike_payments_column_data( $value, $payment_id, $column_name ) {
    if ( $column_name == 'preapproval' ) {
        $status           = get_post_status( $payment_id );
        $transaction_id   = edd_get_payment_transaction_id( $payment_id );
        $captured_already = get_post_meta( $payment_id, '_edd_paylike_captured', true );
        if ( ! $transaction_id || $captured_already ) {
            return $value;
        }
        $nonce            = wp_create_nonce( 'edd-paylike-process-preapproval' );
        $preapproval_args = array(
            'payment_id' => $payment_id,
            'nonce'      => $nonce,
            'edd-action' => 'charge_paylike_preapproval'
        );
        $cancel_args      = array(
            'payment_id' => $payment_id,
            'nonce'      => $nonce,
            'edd-action' => 'cancel_paylike_preapproval'
        );
        if ( 'preapproval' === $status ) {
            $value = '<a href="' . esc_url( add_query_arg( $preapproval_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) ) . '" class="button-secondary button">' . __( 'Process Payment', 'edd-paylike' ) . '</a>&nbsp;';
            $value .= '<a href="' . esc_url( add_query_arg( $cancel_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) ) . '" class="button-secondary button">' . __( 'Cancel Preapproval', 'edd-paylike' ) . '</a>';
        }
    }

    return $value;
}

add_filter( 'edd_payments_table_column', 'edd_paylike_payments_column_data', 10, 3 );
/**
 * Trigger preapproved payment charge
 * @since 1.0.0
 * @return void
 */
function edd_paylike_process_preapproved_charge() {
    if ( empty( $_GET['nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_GET['nonce'], 'edd-paylike-process-preapproval' ) ) {
        return;
    }
    $payment_id = absint( $_GET['payment_id'] );
    $charge     = edd_paylike_charge_preapproved( $payment_id );
    if ( $charge ) {
        wp_redirect( esc_url_raw( add_query_arg( array( 'edd-message' => 'paylike-preapproval-charged' ), admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) ) );
        exit;
    } else {
        wp_redirect( esc_url_raw( add_query_arg( array( 'edd-message' => 'paylike-preapproval-failed' ), admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) ) );
        exit;
    }

}

add_action( 'edd_charge_paylike_preapproval', 'edd_paylike_process_preapproved_charge' );
/**
 * Cancel a preapproved payment
 * @since 1.0.0
 * @return void
 */
function edd_paylike_process_preapproved_cancel() {
    global $edd_options;
    if ( empty( $_GET['nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_GET['nonce'], 'edd-paylike-process-preapproval' ) ) {
        return;
    }
    $payment_id        = absint( $_GET['payment_id'] );
    $transaction_id    = edd_get_payment_transaction_id( $payment_id );
    $captured_already  = get_post_meta( $payment_id, '_edd_paylike_captured', true );
    $cancelled_already = get_post_meta( $payment_id, '_edd_paylike_preapproval_cancelled', true );
    if ( empty( $transaction_id ) || empty( $payment_id ) || $captured_already || $cancelled_already ) {
        return;
    }
    if ( 'preapproval' !== get_post_status( $payment_id ) ) {
        return;
    }
    $amount     = edd_paylike_get_proper_amount( $payment_id );
    $secret_key = edd_is_test_mode() ? trim( $edd_options['paylike_test_secret_key'] ) : trim( $edd_options['paylike_live_secret_key'] );
    Paylike\Client::setKey( $secret_key );
    $data     = array(
        'amount' => $amount
    );
    $response = Paylike\Transaction::void( $transaction_id, $data );
    if ( ! edd_paylike_is_successful_response( $response ) ) {
        edd_insert_payment_note( $payment_id, __( "The order was cancelled, but the transaction could not be voided in Paylike.", 'edd-paylike' ) );
        edd_record_gateway_error( __( 'Transaction Void Failed', 'edd-paylike' ), sprintf( __( "\nError: %s", 'edd-paylike' ), edd_paylike_get_response_error( $response ) ) );
    }
    // we cancel the order regardless of the void operation.
    edd_insert_payment_note( $payment_id, __( 'Preapproval cancelled', 'edd-paylike' ) );
    edd_update_payment_status( $payment_id, 'cancelled' );
    // add flag such that we don't try to cancel twice.
    update_post_meta( $payment_id, '_edd_paylike_preapproval_cancelled', true );
    wp_redirect( esc_url_raw( add_query_arg( array( 'edd-message' => 'paylike-preapproval-cancelled' ), admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) ) );
    exit;
}

add_action( 'edd_cancel_paylike_preapproval', 'edd_paylike_process_preapproved_cancel' );
/**
 * Charge a preapproved payment
 * @since 1.0.0
 * @return bool
 */
function edd_paylike_charge_preapproved( $payment_id = 0 ) {
    global $edd_options;
    if ( empty( $payment_id ) ) {
        return false;
    }
    $transaction_id   = edd_get_payment_transaction_id( $payment_id );
    $captured_already = get_post_meta( $payment_id, '_edd_paylike_captured', true );
    if ( empty( $transaction_id ) || empty( $payment_id ) || $captured_already ) {
        return;
    }
    if ( 'preapproval' !== get_post_status( $payment_id ) ) {
        return;
    }
    $secret_key = edd_is_test_mode() ? trim( $edd_options['paylike_test_secret_key'] ) : trim( $edd_options['paylike_live_secret_key'] );
    Paylike\Client::setKey( $secret_key );
    $amount   = edd_paylike_get_proper_amount( $payment_id );
    $data     = array(
        'amount'   => $amount,
        'currency' => edd_get_currency()
    );
    $response = Paylike\Transaction::capture( $transaction_id, $data );
    if ( edd_paylike_is_successful_response( $response ) ) {
        edd_insert_payment_note( $payment_id, __( 'Captured Preapproved Transaction Successful', 'edd-paylike' ) );
        edd_update_payment_status( $payment_id, 'publish' );
        // remove it such that we can't no longer try to capture.
        update_post_meta( $payment_id, '_edd_paylike_captured', true );

        return true;
    } else {
        edd_insert_payment_note( $payment_id, __( 'Capturing Preapproved Transaction Failed', 'edd-paylike' ) );
        edd_record_gateway_error( __( 'Capturing Preapproved Transaction Failed', 'edd-paylike' ), sprintf( __( "\nError: %s", 'edd-paylike' ), edd_paylike_get_response_error( $response ) ) );

        return false;
    }
}

/**
 * Admin Messages
 * @since 1.0.0
 * @return void
 */
function edd_paylike_admin_messages() {
    if ( isset( $_GET['edd-message'] ) && 'paylike-preapproval-charged' == $_GET['edd-message'] ) {
        add_settings_error( 'edd-paylike-notices', 'edd-paylike-preapproval-charged', __( 'The preapproved payment was successfully charged.', 'edd-paylike' ), 'updated' );
    }
    if ( isset( $_GET['edd-message'] ) && 'paylike-preapproval-failed' == $_GET['edd-message'] ) {
        add_settings_error( 'edd-paylike-notices', 'edd-paylike-preapproval-charged', __( 'The preapproved payment failed to be charged. View order details for further details.', 'edd-paylike' ), 'error' );
    }
    if ( isset( $_GET['edd-message'] ) && 'paylike-preapproval-cancelled' == $_GET['edd-message'] ) {
        add_settings_error( 'edd-paylike-notices', 'edd-paylike-preapproval-cancelled', __( 'The preapproved payment was successfully cancelled.', 'edd-paylike' ), 'updated' );
    }
    settings_errors( 'edd-paylike-notices' );
}

add_action( 'admin_notices', 'edd_paylike_admin_messages' );
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
                'currency'            => edd_get_currency(),
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
 * Process refund in Paylike
 *
 * @param $payment_id
 * @param $new_status
 * @param $old_status
 *
 * @access      public
 * @since       1.0.0
 * @return      void
 */
function edd_paylike_process_refund( $payment_id, $new_status, $old_status ) {
    global $edd_options;
    if ( empty( $_POST['edd_refund_in_paylike'] ) ) {
        return;
    }
    $should_process_refund = 'publish' != $old_status && 'revoked' != $old_status ? false : true;
    if ( false === $should_process_refund ) {
        return;
    }
    if ( 'refunded' != $new_status ) {
        return;
    }
    // Bail if no transaction ID was found
    $transaction_id = edd_get_payment_transaction_id( $payment_id );
    if ( empty( $transaction_id ) ) {
        return;
    }
    $secret_key = edd_is_test_mode() ? trim( $edd_options['paylike_test_secret_key'] ) : trim( $edd_options['paylike_live_secret_key'] );
    Paylike\Client::setKey( $secret_key );
    $amount   = edd_paylike_get_proper_amount( $payment_id );
    $response = Paylike\Transaction::refund( $transaction_id, array( 'amount' => $amount ) );
    if ( edd_paylike_is_successful_response( $response ) ) {
        edd_insert_payment_note( $payment_id, __( 'Transaction refunded in Paylike', 'edd-paylike' ) );
    } else {
        edd_insert_payment_note( $payment_id, __( 'Refunding transaction in Paylike failed', 'edd-paylike' ) );
        edd_record_gateway_error( __( 'Refunding transaction in Paylike failed', 'edd-paylike' ), sprintf( __( "Error: %s", 'edd-paylike' ), edd_paylike_get_response_error( $response ) ) );
    }
}

add_action( 'edd_update_payment_status', 'edd_paylike_process_refund', 200, 3 );
/**
 * Checks if the response from api call is valid.
 *
 * @param $response
 *
 * @access      public
 * @since       1.0.0
 * @return      bool
 */
function edd_paylike_is_successful_response( $response ) {
    return $response && isset( $response['transaction'] ) && $response['transaction']['successful'] == 1;
}

/**
 * Checks if the response from api call is valid.
 * @access      public
 * @since       1.0.0
 * @return      bool
 */
function edd_paylike_check_response_data( $response, $currency, $amount ) {
    return $response['transaction']['currency'] == $currency &&
           $response['transaction']['amount'] == $amount;
}

/**
 * Determines if the shop is using a zero-decimal currency
 * @access      public
 * @since       1.0.0
 *
 * @param int $payment_id
 *
 * @return bool
 */
function edd_paylike_is_zero_decimal_currency( $payment_id = 0 ) {
    $ret      = false;
    $currency = edd_get_currency();
    if ( $payment_id ) {
        $currency = edd_get_payment_meta( $payment_id, 'currency' );
    }
    switch ( $currency ) {
        case 'BIF' :
        case 'CLP' :
        case 'DJF' :
        case 'GNF' :
        case 'JPY' :
        case 'KMF' :
        case 'KRW' :
        case 'MGA' :
        case 'PYG' :
        case 'RWF' :
        case 'VND' :
        case 'VUV' :
        case 'XAF' :
        case 'XOF' :
        case 'XPF' :
            $ret = true;
            break;

    }

    return $ret;
}

/**
 * Retrieve client ip.
 * @access      public
 * @since       1.0.0
 * @return      string
 */
function edd_paylike_get_client_ip() {
    if ( getenv( 'HTTP_CLIENT_IP' ) ) {
        $ip_address = getenv( 'HTTP_CLIENT_IP' );
    } elseif ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
        $ip_address = getenv( 'HTTP_X_FORWARDED_FOR' );
    } elseif ( getenv( 'HTTP_X_FORWARDED' ) ) {
        $ip_address = getenv( 'HTTP_X_FORWARDED' );
    } elseif ( getenv( 'HTTP_FORWARDED_FOR' ) ) {
        $ip_address = getenv( 'HTTP_FORWARDED_FOR' );
    } elseif ( getenv( 'HTTP_FORWARDED' ) ) {
        $ip_address = getenv( 'HTTP_FORWARDED' );
    } elseif ( getenv( 'REMOTE_ADDR' ) ) {
        $ip_address = getenv( 'REMOTE_ADDR' );
    } else {
        $ip_address = '0.0.0.0';
    }

    return $ip_address;
}

/**
 * Returns a titles array for the products.
 * @access      public
 * @since       1.0.0
 * @return      string
 */
function edd_paylike_get_cart_products() {
    $downloads_to_send = array();
    $downloads         = edd_get_cart_contents();
    if ( ! empty( $downloads ) ) {
        foreach ( $downloads as $download ) {
            $downloads_to_send[] = array(
                'id'       => $download['id'],
                'name'     => the_title_attribute( array(
                    'echo' => false,
                    'post' => $download['id']
                ) ),
                'quantity' => $download['quantity']
            );
        }
    }

    return $downloads_to_send;
    //return edd_paylike_get_cart_description();
}

/**
 * Returns a description for the cart.
 * @access      public
 * @since       1.0.0
 * @return      string
 */
function edd_paylike_get_cart_description() {
    $summary   = '';
    $downloads = edd_get_cart_contents();
    if ( ! empty( $downloads ) ) {
        foreach ( $downloads as $download ) {
            $summary .= the_title_attribute( array(
                    'echo' => false,
                    'post' => $download['id']
                ) ) . ', ';
        }
        $summary = substr( $summary, 0, - 2 );
    }

    return $summary;
}

/**
 * Sets the paylike-checkout parameter if the direct parameter is present in the [purchase_link] short code
 * @since  1.0.0
 * @return array
 */
function edd_paylike_purchase_link_shortcode_atts( $out, $pairs, $atts ) {
    if ( ! empty( $out['direct'] ) ) {
        $out['paylike-checkout'] = true;
        $out['direct']           = true;

    } else {
        foreach ( $atts as $key => $value ) {
            if ( false !== strpos( $value, 'paylike-checkout' ) ) {
                $out['paylike-checkout'] = true;
                $out['direct']           = true;
            }
        }

    }

    return $out;
}

add_filter( 'shortcode_atts_purchase_link', 'edd_paylike_purchase_link_shortcode_atts', 10, 3 );
/**
 * Sets the paylike-checkout parameter if the direct parameter is present in edd_get_purchase_link()
 * @since  1.0.0
 * @return array
 */
function edd_paylike_purchase_link_atts( $args ) {
    if ( ! empty( $args['direct'] ) && edd_is_gateway_active( 'paylike' ) ) {
        $args['paylike-checkout'] = true;
        $args['direct']           = true;
    }

    return $args;
}

add_filter( 'edd_purchase_link_args', 'edd_paylike_purchase_link_atts', 10 );
/**
 * Outputs javascript for the Paylike Checkout modal
 * @since  1.0.0
 * @return void
 */
function edd_paylike_purchase_link_output( $download_id = 0, $args = array() ) {
    global $printed_paylike_purchase_link;
    // Stop our output from being triggered if someone is looking at the content for meta tags, like Jetpack
    if ( doing_action( 'wp_head' ) ) {
        return;
    }
    if ( ! empty( $printed_paylike_purchase_link[ $download_id ] ) ) {
        return;
    }
    if ( ! isset( $args['paylike-checkout'] ) ) {
        return;
    }
    if ( ! edd_is_gateway_active( 'paylike' ) ) {
        return;
    }
    edd_paylike_js( true );
    $email = '';
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $email        = $current_user->user_email;
    }
    wp_add_inline_script( 'edd-paylike-js',
        edd_paylike_get_link_form_script( $download_id, $email )
    );
    $printed_paylike_purchase_link[ $download_id ] = true;
}

add_action( 'edd_purchase_link_end', 'edd_paylike_purchase_link_output', 99999, 2 );
/**
 * Injects the Paylike transaction token and customer email into the pre-gateway data
 * @since  1.0.0
 * @return array
 */
function edd_paylike_straight_to_gateway_data( $purchase_data ) {
    if ( isset( $_REQUEST['edd_paylike_token'] ) ) {
        global $edd_paylike_is_buy_now;
        $edd_paylike_is_buy_now   = true;
        $purchase_data['gateway'] = 'paylike';
        $_REQUEST['edd-gateway']  = 'paylike';
        if ( isset( $_REQUEST['edd_email'] ) ) {
            $purchase_data['user_info']['email'] = sanitize_email( $_REQUEST['edd_email'] );
            $purchase_data['user_email']         = sanitize_email( $_REQUEST['edd_email'] );
        }

    }

    return $purchase_data;
}

add_filter( 'edd_straight_to_gateway_purchase_data', 'edd_paylike_straight_to_gateway_data' );
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
                    prices[<?php echo $price_id; ?>] = <?php echo $price['amount'] * 100; ?>;
                    <?php endforeach; ?>

                    if (form.find('.edd_price_option_<?php echo $download_id; ?>').length > 1) {

                        if (form.find('.edd_price_options input:checked').hasClass('edd_cp_radio')) {

                            custom_price = true;
                            amount = form.find('.edd_cp_price').val() * 100;

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
                    amount = form.find('.edd_cp_price').val() * 100;

                } else {
                    amount = <?php echo edd_get_download_price( $download_id ) * 100; ?>;
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

/**
 * Tries to get the error message from Paylike response.
 *
 * @param $response
 *
 * @return string
 */
function edd_paylike_get_response_error( $response ) {
    $error = array();
    foreach ( $response as $field_error ) {
        $error[] = $field_error['field'] . ': ' . $field_error['message'];
    }
    $error_message = implode( " ", $error );

    return $error_message;
}

/**
 * Returns the proper amount based on the currency.
 *
 * @param $payment_id
 *
 * @return float
 */
function edd_paylike_get_proper_amount( $payment_id ) {
    if ( edd_paylike_is_zero_decimal_currency( $payment_id ) ) {
        $amount = edd_get_payment_amount( $payment_id );
    } else {
        $amount = edd_get_payment_amount( $payment_id ) * 100;
    }

    return $amount;
}
<?php

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
	$columns['preapproval'] = __( 'Preapproval', 'edd-paylike' );


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
		$status           = edd_get_payment_status( $payment_id );
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
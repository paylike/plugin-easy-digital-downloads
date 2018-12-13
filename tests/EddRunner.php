<?php


namespace EDD;

use Facebook\WebDriver\Exception\NoAlertOpenException;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverExpectedCondition;

class EddRunner extends TestHelper {

	/**
	 * @param $args
	 *
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function ready( $args ) {
		$this->set( $args );
		$this->go();
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function loginAdmin() {
		$this->goToPage( 'wp-admin', '#user_login' );
		while ( ! $this->hasValue( '#user_login', $this->user ) ) {
			$this->typeLogin();
		}
		$this->click( '#wp-submit' );
		$this->waitForPage( 'wp-admin/' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function changeCurrency() {
		$this->goToPage( 'wp-admin/edit.php?post_type=download&page=edd-settings&tab=general&section=currency', '#edd_settings_currency__chosen' );
		$this->click( '#edd_settings_currency__chosen' );
		$this->click( "//*/li[contains(text(), '" . $this->currency . "')]" );

	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function changeDecimal() {
		$this->goToPage( 'wp-admin/edit.php?post_type=download&page=edd-settings&tab=general&section=currency', '#edd_settings[decimal_separator]' );
		$this->type( '#edd_settings[decimal_separator]', '.' );
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function disableEmail() {
		if ( $this->stop_email === true ) {
			$this->goToPage( 'wp-admin/options-general.php?page=disable-emails', 'disable_emails[wp_mail]' );
			$this->checkbox( 'disable_emails[wp_mail]' );
		}
	}

	/**
	 *
	 */
	public function checkoutMode() {
		$this->click( '#edd_settings[paylike_disable_checkout]' );
		if ( $this->checkout_mode != 'popup' ) {
			$this->checkbox( "#edd_settings[paylike_disable_checkout]" );
		} else {
			$this->unCheck( "#edd_settings[paylike_disable_checkout]" );
		}
	}


	/**
	 *
	 */
	public function captureMode() {
		$this->click( '#edd_settings[paylike_preapprove_only]' );
		if ( $this->capture_mode != 'instant' ) {
			$this->checkbox( "#edd_settings[paylike_preapprove_only]" );
		} else {
			$this->unCheck( "#edd_settings[paylike_preapprove_only]" );
		}
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function changeMode() {
		$this->goToPage( 'wp-admin/edit.php?post_type=download&page=edd-settings&tab=gateways&section=edd-paylike' );
		$this->checkoutMode();
		$this->captureMode();
		$this->submitAdmin();
	}

	/**
	 *
	 */
	public function submitAdmin() {
		$this->click( '#submit' );
	}


	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function selectOrder() {
		$this->goToPage( 'wp-admin/edit.php?post_type=download&page=edd-payment-history', '.column-details' );
		$this->click( "//*//a[text()='View Order Details']" );
		if ( $this->capture_mode == 'delayed' ) {
			$this->waitForElement( '.edd-payment-note' );
			$text     = $this->pluckElement( '.edd-payment-note p', 2 )->getText();
			$messages = explode( PHP_EOL, $text );
			$this->main_test->assertContains( 'Captured Preapproved Transaction Successful', $messages[1], "Captured" );
		}

	}

	/**
	 */
	public function pay() {
		$this->click( '#edd-purchase-button' );
	}


	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function finalPaylike() {
		$amount         = (int) $this->main_test->wd->executeScript( "return window.paylikeAmount" );
		$expectedAmount = $this->getText( '.edd_cart_amount' );
		$expectedAmount = preg_replace( "/[^0-9.]/", "", $expectedAmount );
		$expectedAmount = trim( $expectedAmount, '.' );
		$expectedAmount = ceil( round( $expectedAmount, 3 ) * get_paylike_currency_multiplier( $this->currency_iso ) );
		$this->main_test->assertEquals( $expectedAmount, $amount, "Checking minor amount for " . $this->currency_iso );
		if ( $this->checkout_mode === 'popup' ) {
			$this->pay();
			$this->popupPaylike();
		} else {
			$this->embedPaylike();
			$this->pay();
		}
		$this->waitForElement( "//*[text() = 'Thank you for your purchase! ']" );

	}


	/**
	 *
	 *
	 */
	public function addToCart() {

		$this->click( ".edd-add-to-cart" );
		$this->waitForElement( ".edd_go_to_checkout" );
		$this->click( ".edd_go_to_checkout" );

	}

	public function clearAdminMessage() {
		$message = $this->findElements( '#message' );
		if ( $message[0] ) {
			$dismiss = $this->findChild( '.notice-dismiss', $message[0] );
			$dismiss->click();
		}

	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function saveOrder() {
		$this->waitForPageReload( function () {
			$this->click( '.save_order' );
		}, 5000 );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function popupPaylike() {
		try {
			$this->waitForElement( '.paylike.overlay .payment form #card-number' );
			$this->type( '.paylike.overlay .payment form #card-number', 41000000000000 );
			$this->type( '.paylike.overlay .payment form #card-expiry', '11/22' );
			$this->type( '.paylike.overlay .payment form #card-code', '122' );
			$this->click( '.paylike.overlay .payment form button' );
		} catch ( NoSuchElementException $exception ) {
			$this->pay();
			$this->popupPaylike();
		}

	}

	/**
	 */
	public function embedPaylike() {
		$this->type( '#card_number', 41000000000000 );
		$this->type( '#card-expiry', '11/22' );
		$this->type( '#card_cvc', '122' );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function refund() {
		$this->selectValue( 'edd-payment-status', 'refunded' );
		$this->waitForElement( '#edd_refund_in_paylike' );
		$this->checkbox( '#edd_refund_in_paylike' );
		$this->click( '.edd-order-update-box #major-publishing-actions input' );
		$this->waitForElement( '#setting-error-edd-payment-updated' );
		$text = $this->getText( '#setting-error-edd-payment-updated' );
		$text = explode( '.', $text );
		$this->main_test->assertEquals( 'The payment has been successfully updated', $text[0], "Refund" );
		$this->click( $this->findChild( '.notice-dismiss', $this->findElements( '#setting-error-edd-payment-updated' )[0] ) );
		$this->waitElementDisappear( '#setting-error-edd-payment-updated' );
		$index = 5;
		if ( $this->capture_mode == 'instant' ) {
			$index = 4;
		}
		$text = $this->pluckElement( '.edd-payment-note p', $index )->getText();
		$this->main_test->assertContains( 'Transaction refunded in Paylike', $text, "Refunded" );
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	public function capture() {
		$this->goToPage( 'wp-admin/edit.php?post_type=download&page=edd-payment-history&status=preapproval', '.column-preapproval' );
		$this->click( "//*//td/a[text() = 'Process Payment']" );
		$this->waitForElement( '#setting-error-edd-paylike-preapproval-charged' );
		$text = $this->getText( '#setting-error-edd-paylike-preapproval-charged' );
		$text = explode( '.', $text );
		$this->main_test->assertEquals( 'The preapproved payment was successfully charged', $text[0], "Delayed capture" );
		$this->click( $this->findChild( '.notice-dismiss', $this->findElements( '#setting-error-edd-paylike-preapproval-charged' )[0] ) );
		$this->waitElementDisappear( '#setting-error-edd-paylike-preapproval-charged' );
	}


	/**
	 *  Insert user and password on the login screen
	 */
	private function typeLogin() {
		$this->type( '#user_login', $this->user );
		$this->type( '#user_pass', $this->pass );
	}

	/**
	 * @param $args
	 */
	private function set( $args ) {
		foreach ( $args as $key => $val ) {
			$name = $key;
			if ( isset( $this->{$name} ) ) {
				$this->{$name} = $val;
			}
		}
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function settings() {
		$this->changeCurrency();
		$this->submitAdmin();
		$this->disableEmail();
		$this->submitAdmin();
		$this->changeMode();
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function directPayment() {
		$this->goToPage( 'downloads/a-music-album/', '.edd-add-to-cart' );
		$this->addToCart();
		$this->finalPaylike();
		if ( $this->capture_mode == 'delayed' ) {
			$this->capture();
		}
		$this->selectOrder();
		$this->refund();
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function getVersions() {
		$this->goToPage( 'wp-admin/plugins.php' );
		$this->waitForElement( '#the-list' );
		$woo     = $this->getPluginVersion( 'easy-digital-downloads/easy-digital-downloads.php' );
		$paylike = $this->getPluginVersion( 'payment-gateway-via-paylike-for-easy-digital-downloads/edd-paylike.php' );

		return [ 'ecommerce' => $woo, 'plugin' => $paylike ];
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function outputVersions() {
		$versions = $this->getVersions();
		$this->main_test->log( '----VERSIONS----' );
		$this->main_test->log( 'EDD %s', $versions['ecommerce'] );
		$this->main_test->log( 'Paylike %s', $versions['plugin'] );
	}

	private function getPluginVersion( $file ) {
		$element = $this->wd->findElement( WebDriverBy::cssSelector( 'tr[data-plugin="' . $file . '"] .plugin-version-author-uri' ) );
		$version = $this->getText( $element );
		$version = explode( '|', $version );

		return $version[0];
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 */
	private function logVersionsRemotly() {
		$versions = $this->getVersions();
		$this->wd->get( getenv( 'REMOTE_LOG_URL' ) . '&key=' . $this->get_slug( $versions['ecommerce'] ) . '&tag=edd&view=html&' . http_build_query( $versions ) );
		$this->waitForElement( '#message' );
		$message = $this->getText( '#message' );
		$this->main_test->assertEquals( 'Success!', $message, "Remote log failed" );
	}


	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function orderCleanup() {
		try {
			$this->goToPage( 'wp-admin/edit.php?post_type=download&page=edd-payment-history&status=preapproval' );
			$this->click( '#cb-select-all-1' );
			$this->selectValue( '#bulk-action-selector-top', 'delete' );
			$this->click( '#doaction' );
		} catch ( NoSuchElementException $exception ) {
			// no orders, just move on
		}
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function settingsCheck() {
		$this->orderCleanup();
		$this->outputVersions();
		$this->changeDecimal();
		$this->submitAdmin();
	}

	/**
	 * @throws \Facebook\WebDriver\Exception\NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	private function go() {
		$this->changeWindow();
		$this->loginAdmin();

		if ( $this->log_version ) {
			$this->logVersionsRemotly();

			return $this;
		}

		if ( $this->settings_check ) {
			$this->settingsCheck();

			return $this;
		}

		$this->settings();
		$this->directPayment();
	}

	/**
	 *
	 */
	private function changeWindow() {
		$this->wd->manage()->window()->setSize( new WebDriverDimension( 1600, 996 ) );
	}


}


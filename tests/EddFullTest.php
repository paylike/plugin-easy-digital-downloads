<?php

namespace EDD;


use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Lmc\Steward\Test\AbstractTestCase;

/**
 * @group edd_full_test
 */
class EddFullTest extends AbstractTestCase {

	public $runner;

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testGeneralFunctions() {
		$this->runner = new EddRunner( $this );
		$this->runner->ready( array(
				'settings_check' => true,
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentPopupInstant() {
		$this->runner = new EddRunner( $this );
		$this->runner->ready( array(
				'checkout_mode' => 'popup',
				'capture_mode'  => 'instant',
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentEmbedInstant() {
		$this->runner = new EddRunner( $this );
		$this->runner->ready( array(
				'checkout_mode' => 'embed',
				'capture_mode'  => 'instant',
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentEmbedDelayed() {
		$this->runner = new EddRunner( $this );
		$this->runner->ready( array(
				'checkout_mode' => 'embed',
				'capture_mode'  => 'delayed',
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testUsdPaymentPopupDelayed() {
		$this->runner = new EddRunner( $this );
		$this->runner->ready( array(
				'checkout_mode' => 'popup',
				'capture_mode'  => 'delayed',
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testDkkPaymentPopupDelayed() {
		$this->runner = new EddRunner( $this );
		$this->runner->ready( array(
				'checkout_mode' => 'popup',
				'capture_mode'  => 'delayed',
				'currency'      => 'Danish Krone',
				'currency_iso'  => 'DKK'
			)
		);
	}

	/**
	 * @throws NoSuchElementException
	 * @throws \Facebook\WebDriver\Exception\TimeOutException
	 * @throws \Facebook\WebDriver\Exception\UnexpectedTagNameException
	 */
	public function testJpyPaymentPopupDelayed() {
		$this->runner = new EddRunner( $this );
		$this->runner->ready( array(
				'checkout_mode' => 'popup',
				'capture_mode'  => 'delayed',
				'currency'      => 'Japanese Yen',
				'currency_iso'  => 'JPY'
			)
		);
	}


}

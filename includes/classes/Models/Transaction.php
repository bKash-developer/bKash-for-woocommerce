<?php
/**
 * Transaction Model
 *
 * @category    Model
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW\Models;

use bKash\PGW\Utils;

class Transaction {
	public $errorMessage = "";
	private $ID;
	private $paymentID;
	private $trxID;
	private $orderID;
	private $invoiceID;
	private $integrationType;
	private $mode;
	private $amount;
	private $currency;
	private $refundID;
	private $refundAmount;
	private $status;
	private $dateTime;
	private $transactionReference;
	private $initiationTime;
	private $completionTime;
	private $transactionType;
	private $customerNumber;
	private $merchantNumber;
	private $intent;
	private $tableName;
	private $wpdb;

	final public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . "bkash_transactions";
	}

	/**
	 * @return mixed
	 */
	final public function getID() {
		return $this->ID;
	}

	/**
	 * @param int $ID
	 *
	 * @return Transaction
	 */
	final public function setID( int $ID ): Transaction {
		$this->ID = $ID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getPaymentID() {
		return $this->paymentID;
	}

	/**
	 * @param mixed $paymentID
	 *
	 * @return Transaction
	 */
	final public function setPaymentID( string $paymentID ): Transaction {
		$this->paymentID = $paymentID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getTrxID() {
		return $this->trxID;
	}

	/**
	 * @param mixed $trxID
	 *
	 * @return Transaction
	 */
	final public function setTrxID( string $trxID ): Transaction {
		$this->trxID = $trxID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getOrderID() {
		return $this->orderID;
	}

	/**
	 * @param mixed $orderID
	 *
	 * @return Transaction
	 */
	final public function setOrderID( string $orderID ): Transaction {
		$this->orderID = $orderID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getIntegrationType() {
		return $this->integrationType;
	}

	/**
	 * @param mixed $integrationType
	 *
	 * @return Transaction
	 */
	final public function setIntegrationType( string $integrationType ): Transaction {
		$this->integrationType = $integrationType;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getMode() {
		return $this->mode;
	}

	/**
	 * @param mixed $mode
	 *
	 * @return Transaction
	 */
	final public function setMode( string $mode ): Transaction {
		$this->mode = $mode;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getAmount() {
		return $this->amount;
	}

	/**
	 * @param string $amount
	 *
	 * @return Transaction
	 */
	final public function setAmount( string $amount ): Transaction {
		$this->amount = $amount;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getCurrency() {
		return $this->currency;
	}

	/**
	 * @param string $currency
	 *
	 * @return Transaction
	 */
	final public function setCurrency( string $currency ): Transaction {
		$this->currency = $currency;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getRefundID() {
		return $this->refundID;
	}

	/**
	 * @param mixed $refundID
	 *
	 * @return Transaction
	 */
	final public function setRefundID( string $refundID ): Transaction {
		$this->refundID = $refundID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getRefundAmount() {
		return $this->refundAmount;
	}

	/**
	 * @param mixed $refundAmount
	 *
	 * @return Transaction
	 */
	final public function setRefundAmount( string $refundAmount ): Transaction {
		$this->refundAmount = $refundAmount;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getStatus() {
		return $this->status;
	}

	/**
	 * @param mixed $status
	 *
	 * @return Transaction
	 */
	final public function setStatus( string $status ): Transaction {
		$this->status = $status;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getDateTime() {
		return $this->dateTime;
	}

	/**
	 * @param mixed $dateTime
	 *
	 * @return Transaction
	 */
	final public function setDateTime( $dateTime ): Transaction {
		$this->dateTime = $dateTime;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getTransactionReference() {
		return $this->transactionReference;
	}

	/**
	 * @param mixed $transactionReference
	 *
	 * @return Transaction
	 */
	final public function setTransactionReference( string $transactionReference ): Transaction {
		$this->transactionReference = $transactionReference;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getInitiationTime() {
		return $this->initiationTime;
	}

	/**
	 * @param mixed $initiationTime
	 *
	 * @return Transaction
	 */
	final public function setInitiationTime( $initiationTime ): Transaction {
		$this->initiationTime = $initiationTime;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getCompletionTime() {
		return $this->completionTime;
	}

	/**
	 * @param mixed $completionTime
	 *
	 * @return Transaction
	 */
	final public function setCompletionTime( $completionTime ): Transaction {
		$this->completionTime = $completionTime;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getTransactionType() {
		return $this->transactionType;
	}

	/**
	 * @param mixed $transactionType
	 *
	 * @return Transaction
	 */
	final public function setTransactionType( string $transactionType ): Transaction {
		$this->transactionType = $transactionType;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getCustomerNumber() {
		return $this->customerNumber;
	}

	/**
	 * @param mixed $customerNumber
	 *
	 * @return Transaction
	 */
	final public function setCustomerNumber( string $customerNumber ): Transaction {
		$this->customerNumber = $customerNumber;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getMerchantNumber() {
		return $this->merchantNumber;
	}

	/**
	 * @param mixed $merchantNumber
	 *
	 * @return Transaction
	 */
	final public function setMerchantNumber( string $merchantNumber ): Transaction {
		$this->merchantNumber = $merchantNumber;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getIntent() {
		return $this->intent;
	}

	/**
	 * @param mixed $intent
	 *
	 * @return Transaction
	 */
	final public function setIntent( string $intent ): Transaction {
		$this->intent = $intent;

		return $this;
	}

	/**
	 * Save this transaction in DB table
	 *
	 * table name: wp_bkash_transactions where wp_ is the prefix set by application
	 *
	 * @return false|Transaction|null
	 */
	final public function save() {
		if ( empty( $this->orderID ) || empty( $this->amount ) ) {
			$this->errorMessage = "Order ID or amount field is missing, both are required";

			return false;
		}


		$insert = $this->wpdb->insert( $this->tableName, [
			'order_id'         => Utils::safeString( $this->orderID ), // required
			'trx_id'           => Utils::safeString( $this->trxID ?? '' ),
			'payment_id'       => Utils::safeString( $this->paymentID ?? '' ),
			'invoice_id'       => Utils::safeString( $this->getInvoiceID() ?? '' ),
			'integration_type' => Utils::safeString( $this->integrationType ?? 'checkout' ),
			'mode'             => Utils::safeString( $this->mode ?? 'NONE' ),
			'intent'           => Utils::safeString( $this->intent ?? 'NONE' ),
			'amount'           => $this->amount, // required
			'currency'         => Utils::safeString( $this->currency ?? 'BDT' ),
			'refund_id'        => Utils::safeString( $this->refundID ?? '' ),
			'status'           => Utils::safeString( $this->status ?? 'CREATED' ),
			'datetime'         => date( 'Y-m-d H:i:s' ),
		] );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
	}

	/**
	 * @return mixed
	 */
	final public function getInvoiceID() {
		$inv_id = uniqid( "wc_bkash_", false );
		if ( empty( $this->invoiceID ) ) {
			$this->setInvoiceID( $inv_id );
		}

		return $this->invoiceID;
	}

	/**
	 * @param mixed $invoiceID
	 *
	 * @return Transaction
	 */
	final public function setInvoiceID( string $invoiceID ): Transaction {
		$this->invoiceID = $invoiceID;

		return $this;
	}

	final public function update( array $data, array $where = [] ): bool {
		$where['invoice_id'] = $this->invoiceID;
		$updated             = $this->wpdb->update( $this->tableName, $data, $where );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	final public function getTransaction( $invoice_id = "", $trx_id = "" ) {
		$transaction = null;
		$tableName   = Utils::safeSqlString( $this->tableName );
		if ( ! empty( $invoice_id ) ) {
			$whereColumn = '`invoice_id`';
			$whereValue  = Utils::safeSqlString( $invoice_id );
		} else {
			$whereColumn = '`trx_id`';
			$whereValue  = Utils::safeSqlString( $trx_id );
		}

		$sqlQuery = "SELECT * FROM $tableName WHERE $whereColumn = %s";
		if ( ! is_null( $this->wpdb ) ) {
			$transaction = $this->wpdb->get_row(
				$this->wpdb->prepare( $sqlQuery, $whereValue )
			);

			if ( $transaction ) {
				return $this->buildTransaction( $transaction );
			}
		}

		return $transaction;
	}

	private function buildTransaction( $transaction ): Transaction {
		$this->orderID         = $transaction->order_id ?? null;
		$this->trxID           = $transaction->trx_id ?? null;
		$this->paymentID       = $transaction->payment_id ?? null;
		$this->invoiceID       = $transaction->invoice_id ?? null;
		$this->integrationType = $transaction->integration_type ?? null;
		$this->mode            = $transaction->mode ?? null;
		$this->intent          = $transaction->intent ?? null;
		$this->amount          = $transaction->amount ?? null;
		$this->currency        = $transaction->currency ?? null;
		$this->refundID        = $transaction->refund_id ?? null;
		$this->refundAmount    = $transaction->refund_amount ?? null;
		$this->status          = $transaction->status ?? null;
		$this->dateTime        = $transaction->datetime ?? null;

		return $this;
	}

	final public function getTransactionByOrderId( $order_id ) {
		$tableName   = Utils::safeSqlString( $this->tableName );
		$whereValue  = Utils::safeSqlString( $order_id ?? '' );
		$sqlQuery    = "SELECT * FROM $tableName WHERE `order_id` = %s";
		$transaction = null;
		if ( ! empty( $order_id ) && ! is_null( $this->wpdb ) ) {
			$transaction = $this->wpdb->get_row(
				$this->wpdb->prepare( $sqlQuery, $whereValue )
			);

			if ( $transaction ) {
				return $this->buildTransaction( $transaction );
			}
		}

		return $transaction;
	}
}

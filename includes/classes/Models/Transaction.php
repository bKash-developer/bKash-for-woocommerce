<?php

namespace bKash\PGW\Models;
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
	private $tableName = "";
	private $wpdb = null;

	public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . "bkash_transactions";
	}

	/**
	 * @return mixed
	 */
	public function getID() {
		return $this->ID;
	}

	/**
	 * @param mixed $ID
	 *
	 * @return Transaction
	 */
	public function setID( $ID ) {
		$this->ID = $ID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getPaymentID() {
		return $this->paymentID;
	}

	/**
	 * @param mixed $paymentID
	 *
	 * @return Transaction
	 */
	public function setPaymentID( $paymentID ) {
		$this->paymentID = $paymentID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getTrxID() {
		return $this->trxID;
	}

	/**
	 * @param mixed $trxID
	 *
	 * @return Transaction
	 */
	public function setTrxID( $trxID ) {
		$this->trxID = $trxID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getOrderID() {
		return $this->orderID;
	}

	/**
	 * @param mixed $orderID
	 *
	 * @return Transaction
	 */
	public function setOrderID( $orderID ) {
		$this->orderID = $orderID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIntegrationType() {
		return $this->integrationType;
	}

	/**
	 * @param mixed $integrationType
	 *
	 * @return Transaction
	 */
	public function setIntegrationType( $integrationType ) {
		$this->integrationType = $integrationType;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getMode() {
		return $this->mode;
	}

	/**
	 * @param mixed $mode
	 *
	 * @return Transaction
	 */
	public function setMode( $mode ) {
		$this->mode = $mode;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getAmount() {
		return $this->amount;
	}

	/**
	 * @param mixed $amount
	 *
	 * @return Transaction
	 */
	public function setAmount( $amount ) {
		$this->amount = $amount;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getCurrency() {
		return $this->currency;
	}

	/**
	 * @param mixed $currency
	 *
	 * @return Transaction
	 */
	public function setCurrency( $currency ) {
		$this->currency = $currency;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getRefundID() {
		return $this->refundID;
	}

	/**
	 * @param mixed $refundID
	 *
	 * @return Transaction
	 */
	public function setRefundID( $refundID ) {
		$this->refundID = $refundID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getRefundAmount() {
		return $this->refundAmount;
	}

	/**
	 * @param mixed $refundAmount
	 *
	 * @return Transaction
	 */
	public function setRefundAmount( $refundAmount ) {
		$this->refundAmount = $refundAmount;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param mixed $status
	 *
	 * @return Transaction
	 */
	public function setStatus( $status ) {
		$this->status = $status;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getDateTime() {
		return $this->dateTime;
	}

	/**
	 * @param mixed $dateTime
	 *
	 * @return Transaction
	 */
	public function setDateTime( $dateTime ) {
		$this->dateTime = $dateTime;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getTransactionReference() {
		return $this->transactionReference;
	}

	/**
	 * @param mixed $transactionReference
	 *
	 * @return Transaction
	 */
	public function setTransactionReference( $transactionReference ) {
		$this->transactionReference = $transactionReference;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getInitiationTime() {
		return $this->initiationTime;
	}

	/**
	 * @param mixed $initiationTime
	 *
	 * @return Transaction
	 */
	public function setInitiationTime( $initiationTime ) {
		$this->initiationTime = $initiationTime;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getCompletionTime() {
		return $this->completionTime;
	}

	/**
	 * @param mixed $completionTime
	 *
	 * @return Transaction
	 */
	public function setCompletionTime( $completionTime ) {
		$this->completionTime = $completionTime;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getTransactionType() {
		return $this->transactionType;
	}

	/**
	 * @param mixed $transactionType
	 *
	 * @return Transaction
	 */
	public function setTransactionType( $transactionType ) {
		$this->transactionType = $transactionType;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getCustomerNumber() {
		return $this->customerNumber;
	}

	/**
	 * @param mixed $customerNumber
	 *
	 * @return Transaction
	 */
	public function setCustomerNumber( $customerNumber ) {
		$this->customerNumber = $customerNumber;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getMerchantNumber() {
		return $this->merchantNumber;
	}

	/**
	 * @param mixed $merchantNumber
	 *
	 * @return Transaction
	 */
	public function setMerchantNumber( $merchantNumber ) {
		$this->merchantNumber = $merchantNumber;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getIntent() {
		return $this->intent;
	}

	/**
	 * @param mixed $intent
	 *
	 * @return Transaction
	 */
	public function setIntent( $intent ) {
		$this->intent = $intent;

		return $this;
	}

	/**
	 * Save this transaction in DB table
	 *
	 * table name: wp_bkash_transactions where wp_ is the prefix set by application
	 *
	 * @return mixed
	 */
	public function save() {
		if ( empty( $this->orderID ) || empty( $this->amount ) ) {
			$this->errorMessage = "Order ID or amount field is missing, both are required";

			return false;
		}


		$insert = $this->wpdb->insert( $this->tableName, [
			'order_id'         => $this->orderID, // required
			'trx_id'           => $this->trxID ?? null,
			'payment_id'       => $this->paymentID ?? null,
			'invoice_id'       => $this->getInvoiceID(),
			'integration_type' => $this->integrationType ?? 'checkout',
			'mode'             => $this->mode ?? 'NONE',
			'intent'           => $this->intent ?? 'NONE',
			'amount'           => $this->amount, // required
			'currency'         => $this->currency ?? 'BDT',
			'refund_id'        => $this->refundID ?? null,
			'status'           => $this->status ?? 'CREATED',
			'datetime'         => date( 'Y-m-d H:i:s' ),
		] );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
	}

	/**
	 * @return mixed
	 */
	public function getInvoiceID() {
		$inv_id = uniqid( "wc_bkash_", false );
		empty( $this->invoiceID ) ? $this->setInvoiceID( $inv_id ) : null;

		return $this->invoiceID;
	}

	/**
	 * @param mixed $invoiceID
	 *
	 * @return Transaction
	 */
	public function setInvoiceID( $invoiceID ) {
		$this->invoiceID = $invoiceID;

		return $this;
	}

	public function update( array $data, array $where = [] ): bool {
		$where['invoice_id'] = $this->invoiceID;
		$updated             = $this->wpdb->update( $this->tableName, $data, $where );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	public function getTransaction( $invoice_id = "", $trx_id = "" ) {
		$transaction = null;
		if ( ! is_null( $this->wpdb ) ) {
			if ( ! empty( $invoice_id ) ) {
				$transaction = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `invoice_id` = %s", $invoice_id ) );
			} else if ( ! empty( $trx_id ) ) {
				$transaction = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `trx_id` = %s", $trx_id ) );
			}

			if ( $transaction ) {
				return $this->buildTransaction( $transaction );
			}
		}

		return $transaction;
	}

	private function buildTransaction( $transaction ) {
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

	public function getTransactionByOrderId( $order_id ) {
		$transaction = null;
		if (! empty( $order_id ) && ! is_null( $this->wpdb ) ) {
			$transaction = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `order_id` = %s", $order_id ) );

			if ( $transaction ) {
				return $this->buildTransaction( $transaction );
			}
		}

		return $transaction;
	}


}
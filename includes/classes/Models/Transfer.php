<?php

namespace bKash\PGW\Models;

class Transfer {

	public $errorMessage = "";

	private $ID;
	private $receiver;
	private $amount;
	private $currency;
	private $trx_id;
	private $merchant_invoice_no;
	private $transaction_status;
	private $b2c_fee;
	private $initiation_time;
	private $completed_time;

	private $tableName = "";
	private $wpdb = null;

	public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . "bkash_transfers";
	}

	/**
	 * @return mixed
	 */
	public function get_ID() {
		return $this->ID;
	}

	/**
	 * @param mixed $ID
	 *
	 * @return Transfer
	 */
	public function set_ID( $ID ) {
		$this->ID = $ID;

		return $this;
	}

	public function save() {
		if ( empty( $this->trx_id ) || empty( $this->amount ) ) {
			$this->errorMessage = "Trx ID or amount field is missing, both are required";

			return false;
		}


		$insert = $this->wpdb->insert( $this->tableName, [
			'receiver'            => $this->get_receiver(),
			'trx_id'              => $this->get_trx_id(),
			'amount'              => $this->get_amount(), // required
			'currency'            => $this->get_currency(),
			'merchant_invoice_no' => $this->get_merchant_invoice_no(),
			'transactionStatus'   => $this->get_transaction_status(),
			'b2cFee'              => $this->get_b_2_c_fee(),
			'initiationTime'      => $this->get_initiation_time(),
			'completedTime'       => $this->get_completed_time()
		] );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
	}

	/**
	 * @return mixed
	 */
	public function get_receiver() {
		return $this->receiver;
	}

	/**
	 * @param mixed $receiver
	 *
	 * @return Transfer
	 */
	public function set_receiver( $receiver ) {
		$this->receiver = $receiver;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_trx_id() {
		return $this->trx_id;
	}

	/**
	 * @param mixed $trx_id
	 *
	 * @return Transfer
	 */
	public function set_trx_id( $trx_id ) {
		$this->trx_id = $trx_id;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_amount() {
		return $this->amount;
	}

	/**
	 * @param mixed $amount
	 *
	 * @return Transfer
	 */
	public function set_amount( $amount ) {
		$this->amount = $amount;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * @param mixed $currency
	 *
	 * @return Transfer
	 */
	public function set_currency( $currency ) {
		$this->currency = $currency;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_merchant_invoice_no() {
		return $this->merchant_invoice_no;
	}

	/**
	 * @param mixed $merchant_invoice_no
	 *
	 * @return Transfer
	 */
	public function set_merchant_invoice_no( $merchant_invoice_no ) {
		$this->merchant_invoice_no = $merchant_invoice_no;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_transaction_status() {
		return $this->transaction_status;
	}

	/**
	 * @param mixed $transaction_status
	 *
	 * @return Transfer
	 */
	public function set_transaction_status( $transaction_status ) {
		$this->transaction_status = $transaction_status;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_b_2_c_fee() {
		return $this->b2c_fee;
	}

	/**
	 * @param mixed $b2c_fee
	 *
	 * @return Transfer
	 */
	public function set_b_2_c_fee( $b2c_fee ) {
		$this->b2c_fee = $b2c_fee;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_initiation_time() {
		return $this->initiation_time;
	}

	/**
	 * @param mixed $initiation_time
	 *
	 * @return Transfer
	 */
	public function set_initiation_time( $initiation_time ) {
		$this->initiation_time = $initiation_time;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function get_completed_time() {
		return $this->completed_time;
	}

	/**
	 * @param mixed $completed_time
	 *
	 * @return Transfer
	 */
	public function set_completed_time( $completed_time ) {
		$this->completed_time = $completed_time;

		return $this;
	}

	public function update( array $data, array $where = [] ): bool {
		$where['trx_id'] = $this->trx_id;
		$updated         = $this->wpdb->update( $this->tableName, $data, $where );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	public function getTransfer( $trx_id = "" ) {
		if ( ! is_null( $this->wpdb ) ) {
			if ( ! empty( $trx_id ) ) {
				$transaction = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `trx_id` = %s", $trx_id ) );
			} else {
				$transaction = null;
			}
			if ( $transaction ) {
				$this->ID                  = $transaction->ID ?? null;
				$this->receiver            = $transaction->receiver ?? null;
				$this->amount              = $transaction->amount ?? null;
				$this->trx_id              = $transaction->trx_id ?? null;
				$this->currency            = $transaction->currency ?? null;
				$this->completed_time      = $transaction->completedTime ?? null;
				$this->initiation_time     = $transaction->initiationTime ?? null;
				$this->merchant_invoice_no = $transaction->merchant_invoice_no ?? null;
				$this->transaction_status  = $transaction->transactionStatus ?? null;
				$this->b2c_fee             = $transaction->b2cFee ?? null;

				return $this;
			}
		}

		return null;
	}

}
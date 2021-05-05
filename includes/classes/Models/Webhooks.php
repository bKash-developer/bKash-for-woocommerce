<?php

namespace bKash\PGW\Models;

class Webhooks {
	public $errorMessage = "";
	private $sender = '';
	private $receiver = '';
	private $receiver_name = '';
	private $trx_id = '';
	private $status = '';
	private $type = '';
	private $amount = '';
	private $currency = '';
	private $reference = '';
	private $datetime = '';
	private $tableName = "";
	private $wpdb = null;

	public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . "bkash_webhooks";
	}

	/**
	 * @return mixed
	 */
	public function get_sender() {
		return $this->sender;
	}

	/**
	 * @param mixed $sender
	 */
	public function set_sender( $sender ) {
		$this->sender = $sender;
	}

	/**
	 * @return mixed
	 */
	public function get_receiver() {
		return $this->receiver;
	}

	/**
	 * @param mixed $receiver
	 */
	public function set_receiver( $receiver ) {
		$this->receiver = $receiver;
	}

	/**
	 * @return mixed
	 */
	public function get_receiver_name() {
		return $this->receiver_name;
	}

	/**
	 * @param mixed $receiver_name
	 */
	public function set_receiver_name( $receiver_name ) {
		$this->receiver_name = $receiver_name;
	}

	/**
	 * @return mixed
	 */
	public function get_trx_id() {
		return $this->trx_id;
	}

	/**
	 * @param mixed $trx_id
	 */
	public function set_trx_id( $trx_id ) {
		$this->trx_id = $trx_id;
	}

	/**
	 * @return mixed
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * @param mixed $status
	 */
	public function set_status( $status ) {
		$this->status = $status;
	}

	/**
	 * @return mixed
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * @param mixed $type
	 */
	public function set_type( $type ) {
		$this->type = $type;
	}

	/**
	 * @return mixed
	 */
	public function get_amount() {
		return $this->amount;
	}

	/**
	 * @param mixed $amount
	 */
	public function set_amount( $amount ) {
		$this->amount = $amount;
	}

	/**
	 * @return mixed
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * @param mixed $currency
	 */
	public function set_currency( $currency ) {
		$this->currency = $currency;
	}

	/**
	 * @return mixed
	 */
	public function get_reference() {
		return $this->reference;
	}

	/**
	 * @param mixed $reference
	 */
	public function set_reference( $reference ) {
		$this->reference = $reference;
	}

	/**
	 * @return mixed
	 */
	public function get_datetime() {
		return $this->datetime;
	}

	/**
	 * @param mixed $datetime
	 */
	public function set_datetime( $datetime ) {
		$this->datetime = $datetime;
	}

	/**
	 * Save this transaction in DB table
	 *
	 * table name: wp_bkash_transactions where wp_ is the prefix set by application
	 *
	 * @return mixed
	 */
	public function save() {
		if ( empty( $this->agreementID ) ) {
			$this->errorMessage = "Order ID field is missing, both are required";

			return false;
		}

		$insert = $this->wpdb->insert( $this->tableName, [
			'sender'        => $this->sender,
			'receiver'      => $this->receiver,
			'receiver_name' => $this->receiver_name,
			'trx_id'        => $this->trx_id,
			'status'        => $this->status,
			'type'          => $this->type,
			'amount'        => $this->amount,
			'currency'      => $this->currency,
			'reference'     => $this->reference,
			'datetime'      => date( 'Y-m-d H:i:s' )
		] );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
	}
}
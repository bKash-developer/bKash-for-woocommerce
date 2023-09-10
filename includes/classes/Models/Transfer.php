<?php
/**
 * Transfer Model
 *
 * @category    Model
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW\Models;

use bKash\PGW\Utils;

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

	private $tableName;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . "bkash_transfers";
	}

	/**
	 * @return int
	 */
	final public function getID(): int {
		return $this->ID;
	}

	/**
	 * @param int $ID
	 *
	 * @return Transfer
	 */
	final public function setID( int $ID ): Transfer {
		$this->ID = $ID;

		return $this;
	}

	/**
	 * @return false|Transfer|null
	 * */
	final public function save() {
		if ( empty( $this->trx_id ) || empty( $this->amount ) ) {
			$this->errorMessage = "Trx ID or amount field is missing, both are required";

			return false;
		}


		$insert = $this->wpdb->insert( $this->tableName, [
			'receiver'            => Utils::safeString( $this->getReceiver() ?? '' ),
			'trx_id'              => Utils::safeString( $this->getTrxId() ?? '' ),
			'amount'              => $this->getAmount(), // required
			'currency'            => Utils::safeString( $this->getCurrency() ?? '' ),
			'merchant_invoice_no' => Utils::safeString( $this->getMerchantInvoiceNo() ?? '' ),
			'transactionStatus'   => Utils::safeString( $this->getTransactionStatus() ?? '' ),
			'b2cFee'              => Utils::safeString( $this->getB2cFee() ?? '' ),
			'initiationTime'      => $this->getInitiationTime(),
			'completedTime'       => $this->getCompletedTime()
		] );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
	}

	/**
	 * @return string
	 */
	final public function getReceiver(): string {
		return $this->receiver;
	}

	/**
	 * @param string $receiver
	 *
	 * @return Transfer
	 */
	final public function setReceiver( string $receiver ): Transfer {
		$this->receiver = $receiver;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getTrxId(): string {
		return $this->trx_id;
	}

	/**
	 * @param string $trx_id
	 *
	 * @return Transfer
	 */
	final public function setTrxId( string $trx_id ): Transfer {
		$this->trx_id = $trx_id;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getAmount() {
		return $this->amount;
	}

	/**
	 * @param mixed $amount
	 *
	 * @return Transfer
	 */
	final public function setAmount( $amount ): Transfer {
		$this->amount = $amount;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getCurrency(): string {
		return $this->currency;
	}

	/**
	 * @param string $currency
	 *
	 * @return Transfer
	 */
	final public function setCurrency( string $currency ): Transfer {
		$this->currency = $currency;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getMerchantInvoiceNo(): string {
		return $this->merchant_invoice_no;
	}

	/**
	 * @param string $merchant_invoice_no
	 *
	 * @return Transfer
	 */
	final public function setMerchantInvoiceNo( string $merchant_invoice_no ): Transfer {
		$this->merchant_invoice_no = $merchant_invoice_no;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getTransactionStatus(): string {
		return $this->transaction_status;
	}

	/**
	 * @param string $transaction_status
	 *
	 * @return Transfer
	 */
	final public function setTransactionStatus( string $transaction_status ): Transfer {
		$this->transaction_status = $transaction_status;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getB2cFee(): string {
		return $this->b2c_fee;
	}

	/**
	 * @param string $b2c_fee
	 *
	 * @return Transfer
	 */
	final public function setB2cFee( string $b2c_fee ): Transfer {
		$this->b2c_fee = $b2c_fee;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getInitiationTime() {
		return $this->initiation_time;
	}

	/**
	 * @param mixed $initiation_time
	 *
	 * @return Transfer
	 */
	final public function setInitiationTime( $initiation_time ): Transfer {
		$this->initiation_time = $initiation_time;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getCompletedTime() {
		return $this->completed_time;
	}

	/**
	 * @param mixed $completed_time
	 *
	 * @return Transfer
	 */
	final public function setCompletedTime( $completed_time ): Transfer {
		$this->completed_time = $completed_time;

		return $this;
	}

	final public function update( array $data, array $where = [] ): bool {
		$where['trx_id'] = $this->trx_id;
		$updated         = $this->wpdb->update( $this->tableName, $data, $where );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	/**
	 * @param string $trx_id
	 *
	 * @return $this|null
	 */
	final public function getTransfer( string $trx_id = "" ) {
		$tableName  = Utils::safeSqlString( $this->tableName );
		$whereValue = Utils::safeString( $trx_id );
		$sqlQuery   = "SELECT * FROM $tableName WHERE `trx_id` = %s";

		if ( ! is_null( $this->wpdb ) ) {
			$transaction = $this->wpdb->get_row(
				$this->wpdb->prepare( $sqlQuery, $whereValue )
			);
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

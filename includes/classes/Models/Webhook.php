<?php
/**
 * Webhook Model
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

class Webhook {
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
	private $tableName;
	private $wpdb;

	public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . "bkash_webhooks";
	}

	/**
	 * @return string
	 */
	final public function getSender(): string {
		return $this->sender;
	}

	/**
	 * @param string $sender
	 *
	 * @return Webhook
	 */
	final public function setSender( string $sender ): Webhook {
		$this->sender = $sender;

		return $this;
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
	 * @return Webhook
	 */
	final public function setReceiver( string $receiver ): Webhook {
		$this->receiver = $receiver;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getReceiverName(): string {
		return $this->receiver_name;
	}

	/**
	 * @param string $receiver_name
	 *
	 * @return Webhook
	 */
	final public function setReceiverName( string $receiver_name ): Webhook {
		$this->receiver_name = $receiver_name;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getStatus(): string {
		return $this->status;
	}

	/**
	 * @param string $status
	 *
	 * @return Webhook
	 */
	final public function setStatus( string $status ): Webhook {
		$this->status = $status;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getType(): string {
		return $this->type;
	}

	/**
	 * @param string $type
	 *
	 * @return Webhook
	 */
	final public function setType( string $type ): Webhook {
		$this->type = $type;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getAmount(): string {
		return $this->amount;
	}

	/**
	 * @param string $amount
	 *
	 * @return Webhook
	 */
	final public function setAmount( string $amount ): Webhook {
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
	 * @return Webhook
	 */
	final public function setCurrency( string $currency ): Webhook {
		$this->currency = $currency;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getReference(): string {
		return $this->reference;
	}

	/**
	 * @param string $reference
	 *
	 * @return Webhook
	 */
	final public function setReference( string $reference ): Webhook {
		$this->reference = $reference;

		return $this;
	}

	/**
	 * @return string
	 */
	final public function getDatetime(): string {
		return $this->datetime;
	}

	/**
	 * @param string $datetime
	 *
	 * @return Webhook
	 */
	final public function setDatetime( string $datetime ): Webhook {
		$this->datetime = $datetime;

		return $this;
	}

	/**
	 * Save this transaction in DB table
	 *
	 * table name: wp_bkash_transactions where wp_ is the prefix set by application
	 *
	 * @return false|Webhook|null
	 */
	final public function save() {
		if ( empty( $this->trx_id ) ) {
			$this->errorMessage = "Transaction ID field is missing, both are required";

			return false;
		}

		// Check if transaction already exists
		$tableName   = Utils::safeSqlString( $this->tableName );
		$whereValue  = Utils::safeString( $this->getTrxId() ?? '' );
		$sqlQuery    = "SELECT * FROM $tableName WHERE `trx_id` = %s";
		$transaction = $this->wpdb->get_row(
			$this->wpdb->prepare( $sqlQuery, $whereValue )
		);
		if ( $transaction ) {
			$this->errorMessage = "Transaction is already saved";

			return false;
		}


		$insert = $this->wpdb->insert( $this->tableName, [
			'sender'        => Utils::safeString( $this->sender ?? '' ),
			'receiver'      => Utils::safeString( $this->receiver ?? '' ),
			'receiver_name' => Utils::safeString( $this->receiver_name ?? '' ),
			'trx_id'        => Utils::safeString( $this->trx_id ?? '' ),
			'status'        => Utils::safeString( $this->status ?? '' ),
			'type'          => Utils::safeString( $this->type ?? '' ),
			'amount'        => $this->amount,
			'currency'      => Utils::safeString( $this->currency ?? '' ),
			'reference'     => Utils::safeString( $this->reference ?? '' ),
			'datetime'      => $this->datetime //date( 'Y-m-d H:i:s' )
		] );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
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
	 * @return Webhook
	 */
	final public function setTrxId( string $trx_id ): Webhook {
		$this->trx_id = $trx_id;

		return $this;
	}
}

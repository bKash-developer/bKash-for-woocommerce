<?php
/**
 * Agreement Model
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

class Agreement {
	public $errorMessage     = '';
	private $agreementID     = '';
	private $agreementStatus = '';
	private $userID          = '';
	private $mobileNo        = '';
	private $dateTime;
	private $tableName;
	private $wpdb;


	final public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . 'bkash_agreement_mapping';
		$this->dateTime  = date( 'now' );
	}

	/**
	 * @return mixed
	 */
	final public function getAgreementID(): string {
		return $this->agreementID;
	}

	/**
	 * @param mixed $agreementID
	 *
	 * @return Agreement
	 */
	final public function setAgreementID( $agreementID ): Agreement {
		$this->agreementID = $agreementID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getAgreementStatus(): string {
		return $this->agreementStatus;
	}

	/**
	 * @param mixed $agreementStatus
	 *
	 * @return Agreement
	 */
	final public function setAgreementStatus( $agreementStatus ): Agreement {
		$this->agreementStatus = $agreementStatus;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getUserID(): string {
		return $this->userID;
	}

	/**
	 * @param mixed $userID
	 *
	 * @return Agreement
	 */
	final public function setUserID( $userID ): Agreement {
		$this->userID = $userID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	final public function getMobileNo(): string {
		return $this->mobileNo;
	}

	/**
	 * @param mixed $mobileNo
	 *
	 * @return Agreement
	 */
	final public function setMobileNo( $mobileNo ): Agreement {
		$this->mobileNo = $mobileNo;

		return $this;
	}

	/**
	 * @return false|string
	 */
	final public function getDateTime() {
		return $this->dateTime;
	}

	/**
	 * @param mixed $dateTime
	 *
	 * @return Agreement
	 */
	final public function setDateTime( $dateTime ): Agreement {
		$this->dateTime = $dateTime;

		return $this;
	}


	/**
	 * Save this transaction in DB table
	 *
	 * table name: wp_bkash_transactions where wp_ is the prefix set by application
	 *
	 * @return Agreement|false|null
	 */
	final public function save() {
		if ( empty( $this->agreementID ) ) {
			$this->errorMessage = 'Order ID field is missing, both are required';

			return false;
		}

		$insert = $this->wpdb->insert(
			$this->tableName,
			array(
				'agreement_token' => $this->agreementID, // required.
				'phone'           => $this->mobileNo,
				'user_id'         => $this->userID,
				'datetime'        => $this->dateTime,
			)
		);

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
	}

	final public function update( array $data, array $where = array() ): bool {
		$updated = $this->wpdb->update( $this->tableName, $data, $where );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	final public function delete( $agreementID = '', $id = '' ): bool {
		$updated = 0;
		if ( ! empty( $agreementID ) ) {
			$updated = $this->wpdb->delete( $this->tableName, array( 'agreement_token' => $agreementID ) );
		}
		if ( ! empty( $id ) ) {
			$updated = $this->wpdb->delete( $this->tableName, array( 'ID' => $id ) );
		}

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	/**
	 * @param string $agreementID
	 * @param string $user_id
	 * @param string $id
	 *
	 * @return $this|null
	 */
	final public function getAgreement( string $agreementID = '', string $user_id = '', string $id = '' ) {
		$primaryKey = 'ID';
		$tableName  = Utils::safeSqlString( $this->tableName );
		if ( ! empty( $agreementID ) ) {
			$whereColumn = '`agreement_token`';
			$whereValue  = Utils::safeString( $agreementID );
		} elseif ( ! empty( $user_id ) ) {
			$whereColumn = '`user_id`';
			$whereValue  = Utils::safeString( $user_id );
		} else {
			$whereColumn = $primaryKey;
			$whereValue  = Utils::safeString( $id );
		}

		$sqlQuery = "SELECT * FROM $tableName WHERE $whereColumn = %s ORDER BY `ID` DESC";
		if ( ! is_null( $this->wpdb ) ) {
			$agreement = $this->wpdb->get_row(
				$this->wpdb->prepare( $sqlQuery, $whereValue )
			);
			if ( $agreement ) {
				$this->agreementID = $agreement->agreement_token ?? null;
				$this->mobileNo    = $agreement->phone ?? null;
				$this->userID      = $agreement->user_id ?? null;
				$this->dateTime    = $agreement->datetime ?? null;

				return $this;
			}
		}

		return null;
	}

	final public function getAgreements( $user_id ) {
		$tableName = Utils::safeSqlString( $this->tableName );
		$sqlQuery  = "SELECT * FROM $tableName WHERE `user_id` = %s ORDER BY ID DESC";
		if ( ! is_null( $this->wpdb ) ) {
			return $this->wpdb->get_results(
				$this->wpdb->prepare(
					$sqlQuery,
					$user_id
				)
			);
		}

		return null;
	}
}

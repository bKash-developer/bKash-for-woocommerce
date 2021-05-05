<?php

namespace bKash\PGW\Models;

class Agreement {
	public $errorMessage = "";
	private $agreementID = "";
	private $agreementStatus = "";
	private $userID = "";
	private $mobileNo = "";
	private $dateTime;
	private $tableName;
	private $wpdb = null;


	public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->tableName = $wpdb->prefix . "bkash_agreement_mapping";
		$this->dateTime  = date( 'now' );
	}

	/**
	 * @return mixed
	 */
	public function getAgreementID() {
		return $this->agreementID;
	}

	/**
	 * @param mixed $agreementID
	 *
	 * @return Agreement
	 */
	public function setAgreementID( $agreementID ) {
		$this->agreementID = $agreementID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getAgreementStatus() {
		return $this->agreementStatus;
	}

	/**
	 * @param mixed $agreementStatus
	 *
	 * @return Agreement
	 */
	public function setAgreementStatus( $agreementStatus ) {
		$this->agreementStatus = $agreementStatus;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getUserID() {
		return $this->userID;
	}

	/**
	 * @param mixed $userID
	 *
	 * @return Agreement
	 */
	public function setUserID( $userID ) {
		$this->userID = $userID;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getMobileNo() {
		return $this->mobileNo;
	}

	/**
	 * @param mixed $mobileNo
	 *
	 * @return Agreement
	 */
	public function setMobileNo( $mobileNo ) {
		$this->mobileNo = $mobileNo;

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
	 * @return Agreement
	 */
	public function setDateTime( $dateTime ) {
		$this->dateTime = $dateTime;

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
		if ( empty( $this->agreementID ) ) {
			$this->errorMessage = "Order ID field is missing, both are required";

			return false;
		}


		$insert = $this->wpdb->insert( $this->tableName, [
			'agreement_token' => $this->agreementID, // required
			'phone'           => $this->mobileNo,
			'user_id'         => $this->userID,
			'datetime'        => $this->dateTime,
		] );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $insert > 0 ? $this : null; // if inserted then it will return value greater than zero or false on error.
	}

	public function update( array $data, array $where = [] ) {
		$updated = $this->wpdb->update( $this->tableName, $data, $where );

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	public function delete( $agreementID = "", $id = "" ) {
		if ( ! empty( $agreementID ) ) {
			$updated = $this->wpdb->delete( $this->tableName, [ 'agreement_token' => $agreementID ] );
		}
		if ( ! empty( $id ) ) {
			$updated = $this->wpdb->delete( $this->tableName, [ 'ID' => $id ] );
		}

		$this->errorMessage = $this->wpdb->last_error; // set if any error or null

		return $updated > 0;
	}

	public function getAgreement( $agreementID = "", $user_id = "", $id = "" ) {
		if ( ! is_null( $this->wpdb ) ) {
			$agreement = [];
			if ( ! empty( $agreementID ) ) {
				$agreement = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `agreement_token` = %s ORDER BY ID DESC", $agreementID ) );
			} else if ( ! empty( $user_id ) ) {
				$agreement = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `user_id` = %s ORDER BY ID DESC", $user_id ) );
			} else {
				$agreement = $this->wpdb->get_row( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `ID` = %s ORDER BY ID DESC", $id ) );
			}
			if ( $agreement ) {
				$this->agreementID = $agreement->agreement_token ?? null;
				$this->mobileNo    = $agreement->phone ?? null;
				$this->userID      = $agreement->user_id ?? null;
				$this->dateTime    = $transaction->datetime ?? null;

				return $this;
			}

			return null;
		}
	}

	public function getAgreements( $user_id ) {
		if ( ! is_null( $this->wpdb ) ) {
			return $this->wpdb->get_results( $this->wpdb->prepare( "SELECT * FROM $this->tableName WHERE `user_id` = %s ORDER BY ID DESC", $user_id ) );
		}

		return null;
	}
}
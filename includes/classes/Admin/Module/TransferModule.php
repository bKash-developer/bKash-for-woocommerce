<?php

namespace bKash\PGW\Admin\Module;

use bKash\PGW\Admin\AdminUtility;
use bKash\PGW\ApiComm;
use bKash\PGW\Models\Transfer;

class TransferModule {

	public static function transfer_history() {
		AdminUtility::loadTable( "Transfer History", "bkash_transfers",
			array(
				"ID"                       => "ID",
				"SENT TO (bKash Personal)" => "receiver",
				"Amount"                   => "amount",
				"TRANSACTION ID"           => "trx_id",
				"INVOICE NO"               => "merchant_invoice_no",
				"STATUS"                   => "transactionStatus",
				"B2C FEES"                 => "b2cFee",
				"INITIATION TIME"          => "initiationTime",
				"COMPLETION TIME"          => "completedTime"
			),

			array(
				"receiver" => "Sent To",
				"trx_id"   => "Transaction ID"
			)
		);
	}


	public static function transfer_balance() {
		try {
			$type   = sanitize_text_field( $_REQUEST['transfer_type'] ?? '' );
			$amount = sanitize_text_field( $_REQUEST['amount'] ?? '' );
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
				if ( $type && $amount ) {
					$comm         = new ApiComm();
					$transferCall = $comm->intraAccountTransfer( $amount, $type );

					$validate = AdminUtility::validate_response( $transferCall, array( 'transactionStatus' => 'Completed' ) );
					if ( $validate['valid'] ) {
						$trx = $validate['response'];
					} else {
						$trx = $validate['message'];
					}
				} else {
					$trx = "Amount or transfer type is missing, try again";
				}
			}
		} catch ( \Throwable $e ) {
			$trx = $e->getMessage();
		}

		include_once BKASH_FW_BASE_PATH . "/includes/classes/Admin/pages/transfer_balance.php";
	}


	public static function check_balances() {
		try {
			$api  = new ApiComm();
			$call = $api->checkBalances();
			if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
				$balances = isset( $call['response'] ) && is_string( $call['response'] ) ? json_decode( $call['response'], true ) : [];

				if ( isset( $balances['errorCode'] ) ) {
					$balances = $balances['errorMessage'] ?? '';
				}
			} else {
				$balances = "Cannot read balances from bKash server right now, try again";
			}
		} catch ( \Throwable $e ) {
			$balances = $e->getMessage();
		}
		include_once BKASH_FW_BASE_PATH . "/includes/classes/Admin//pages/check_balances.php";
	}

	public static function disburse_money() {
		try {
			$receiver   = sanitize_text_field( $_REQUEST['receiver'] ?? '' );
			$amount     = sanitize_text_field( $_REQUEST['amount'] ?? '' );
			$invoice_no = sanitize_text_field( $_REQUEST['invoice_no'] ?? '' );
			$initTime   = date( 'Y-m-d H:i:s' );

			if ( ! empty( $receiver ) && ! empty( $amount ) && ! empty( $invoice_no ) ) {
				$comm         = new ApiComm();
				$transferCall = $comm->b2cPayout( $amount, $invoice_no, $receiver );

				$validate = AdminUtility::validate_response( $transferCall, array( 'transactionStatus' => 'Completed' ) );
				if ( $validate['valid'] ) {
					$transfer             = $validate['response'];
					$transfer['initTime'] = $initTime;

					$save = self::build_and_save_transfer( $transfer );

					if ( $save ) {
						$trx = $transfer;
					} else {
						$trx = "Disbursement is successful but could not make it into db";
					}
				} else {
					$trx = $validate['message'];
				}
			}
		} catch ( \Throwable $e ) {
			$trx = $e->getMessage();
		}

		include_once BKASH_FW_BASE_PATH . "/includes/classes/Admin//pages/disburse_money.php";
	}

	private static function build_and_save_transfer( $transfer ) {
		$db_transfer = new Transfer();
		$db_transfer->set_trx_id( $transfer['trxID'] ?? '' );
		$db_transfer->set_amount( $transfer['amount'] ?? '' );
		$db_transfer->set_receiver( $transfer['receiverMSISDN'] ?? '' );
		$db_transfer->set_currency( $transfer['currency'] ?? '' );
		$db_transfer->set_merchant_invoice_no( $transfer['merchantInvoiceNumber'] ?? '' );
		$db_transfer->set_transaction_status( $transfer['transactionStatus'] ?? '' );
		$db_transfer->set_initiation_time( $transfer['initTime'] ?? '' );
		$db_transfer->set_completed_time( $transfer['completedTime'] ?? date( 'now' ) );
		$db_transfer->set_b_2_c_fee( 0 );
		$db_transfer->save();

		return $db_transfer;
	}
}
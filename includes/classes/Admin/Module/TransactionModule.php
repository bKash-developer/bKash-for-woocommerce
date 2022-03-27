<?php

namespace bKash\PGW\Admin\Module;

use bKash\PGW\Admin\AdminUtility;
use bKash\PGW\ApiComm;
use bKash\PGW\Models\Transaction;
use bKash\PGW\PaymentGatewaybKash;

class TransactionModule {
	public static function transaction_list() {
		AdminUtility::loadTable( "All bKash Transaction", "bkash_transactions",
			array(
				"ID"               => "ID",
				"ORDER ID"         => "order_id",
				"INVOICE ID"       => "invoice_id",
				"PAYMENT ID"       => "payment_id",
				"TRANSACTION ID"   => "trx_id",
				"AMOUNT"           => "amount",
				"INTEGRATION TYPE" => "integration_type",
				"INTENT"           => "intent",
				"MODE"             => "mode",
				"REFUNDED?"        => "refund_id",
				"REFUND AMOUNT"    => "refund_amount",
				"STATUS"           => "status",
				"DATETIME"         => "datetime",
			),
			array(
				"trx_id"     => "Transaction ID",
				"invoice_id" => "Invoice ID",
				"status"     => "Status"
			)
		);
	}

	public static function transaction_search() {
		try {
			$trx_id = "";
			if ( isset( $_REQUEST['trxid'] ) ) {
				$trx_id = sanitize_text_field( $_REQUEST['trxid'] );
			}

			if ( $trx_id !== '' ) {
				$api  = new ApiComm();
				$call = $api->searchTransaction( $trx_id );

				if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {

					$trx = [];
					if ( isset( $call['response'] ) && is_string( $call['response'] ) ) {
						$trx = json_decode( $call['response'], true );
					}

					// If any error
					if ( isset( $trx['statusMessage'] ) && $trx['statusMessage'] !== 'Successful' ) {
						$trx = $trx['statusMessage'];
					}
					if ( isset( $trx['errorMessage'] ) && ! empty( $trx['errorMessage'] ) ) {
						$trx = $trx['errorMessage'];
					}
				} else {
					$trx = "Cannot find the transaction from bKash server right now, try again";
				}
			}
		} catch ( \Exception $ex ) {
			$trx = $ex->getMessage();
		}

		include_once BKASH_FW_BASE_PATH . "/includes/classes/Admin/pages/transaction_search.php";
	}


	public static function refund_a_transaction() {
		$trx           = "";
		$trx_id        = sanitize_text_field( $_REQUEST['trxid'] ?? '' );
		$fill_trx_id   = sanitize_text_field( $_REQUEST['fill_trx_id'] ?? '' );
		$reason        = sanitize_text_field( $_REQUEST['reason'] ?? '' );
		$amount        = sanitize_text_field( $_REQUEST['amount'] ?? '' );
		$isRefund      = isset( $_REQUEST['refund'] );
		$isRefundCheck = isset( $_REQUEST['check'] );

		if ( ! empty( $trx_id ) ) {
			$trxObject   = new Transaction();
			$transaction = $trxObject->getTransaction( "", $trx_id );
			if ( $transaction ) {

				if ( $isRefund ) {
					if ( $amount > 0 ) {
						if ( $amount <= $transaction->getAmount() ) {
							$wcB    = new PaymentGatewaybKash();
							$refund = $wcB->process_refund( $transaction->getOrderID(), $amount, $reason );
							if ( $refund ) {
								$trx = $wcB->refundObj;
							} else {
								$trx = "Refund is not successful, " . ( $wcB->refundError ?? '' );
							}
						} else {
							$trx = "Refund amount cannot be greater than transaction amount";
						}
					} else {
						$trx = "Refund amount should be greater than zero";
					}
				} else if ( $isRefundCheck ) {
					$wcB    = new PaymentGatewaybKash();
					$refund = $wcB->query_refund( $transaction->getOrderID() );
					if ( $refund ) {
						$trx = $refund;
					} else {
						$trx = "Refund status not found, " . ( $wcB->refundError ?? '' );
					}
				} else {
					$trx = "Unknown refund operation";
				}
			} else {
				$trx = "Cannot find the transaction to refund in your database, try again";
			}
		}

		include_once BKASH_FW_BASE_PATH . "/includes/classes/Admin/pages/refund_transaction.php";
	}
}
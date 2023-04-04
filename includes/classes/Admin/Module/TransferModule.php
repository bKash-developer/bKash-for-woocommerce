<?php
/**
 * Transfer Module
 *
 * @category    Module
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW\Admin\Module;

use bKash\PGW\Admin\AdminUtility;
use bKash\PGW\ApiComm;
use bKash\PGW\Models\Transfer;
use bKash\PGW\Utils;
use Throwable;

class TransferModule {
	public static function transferHistory() {
		AdminUtility::loadTable(
			'Transfer History',
			'bkash_transfers',
			array(
				'ID'                       => 'ID',
				'SENT TO (bKash Personal)' => 'receiver',
				'Amount'                   => 'amount',
				'TRANSACTION ID'           => 'trx_id',
				'INVOICE NO'               => 'merchant_invoice_no',
				'STATUS'                   => 'transactionStatus',
				'B2C FEES'                 => 'b2cFee',
				'INITIATION TIME'          => 'initiationTime',
				'COMPLETION TIME'          => 'completedTime',
			),
			array(
				'receiver' => 'Sent To',
				'trx_id'   => 'Transaction ID',
			)
		);
	}


	public static function transferBalance() {
		try {
			$type   = Utils::safePostValue( 'transfer_type' ) ?? '';
			$amount = Utils::safePostValue( 'amount' ) ?? '';
			if ( Utils::safeServerValue( 'REQUEST_METHOD' ) === 'POST' ) {
				if ( $type && $amount ) {
					$comm         = new ApiComm();
					$transferCall = $comm->intraAccountTransfer( $amount, $type );

					$validate = AdminUtility::validateResponse(
						$transferCall,
						array( 'transactionStatus' => 'Completed' )
					);
					if ( $validate['valid'] ) {
						$trx = $validate['response'];
					} else {
						$trx = $validate['message'];
					}
				} else {
					$trx = 'Amount or transfer type is missing, try again';
				}
			}
		} catch ( Throwable $e ) {
			$trx = $e->getMessage();
		}

		include_once BKASH_FW_BASE_PATH . '/includes/classes/Admin/pages/transfer_balance.php';
	}


	public static function checkBalances() {
		try {
			$api  = new ApiComm();
			$call = $api->checkBalances();
			if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {
				$balances = array();
				if ( isset( $call['response'] ) && is_string( $call['response'] ) ) {
					$balances = json_decode( $call['response'], true );
				}
				if ( isset( $balances['errorCode'] ) ) {
					$balances = $balances['errorMessage'] ?? '';
				}
			} else {
				$balances = 'Cannot read balances from bKash server right now, try again';
			}
		} catch ( Throwable $e ) {
			$balances = $e->getMessage();
		}
		include_once BKASH_FW_BASE_PATH . '/includes/classes/Admin//pages/check_balances.php';
	}

	public static function disburseMoney() {
		try {
			$receiver   = Utils::safePostValue( 'receiver' ) ?? '';
			$amount     = Utils::safePostValue( 'amount' ) ?? '';
			$invoice_no = Utils::safePostValue( 'invoice_no' ) ?? '';
			$initTime   = date( 'Y-m-d H:i:s' );

			if ( ! empty( $receiver ) && ! empty( $amount ) && ! empty( $invoice_no ) ) {
				$comm         = new ApiComm();
				$transferCall = $comm->b2cPayout( $amount, $invoice_no, $receiver );

				$validate = AdminUtility::validateResponse(
					$transferCall,
					array( 'transactionStatus' => 'Completed' )
				);
				if ( $validate['valid'] ) {
					$transfer             = $validate['response'];
					$transfer['initTime'] = $initTime;

					$save = self::buildAndSaveTransfer( $transfer );
					$trx  = $transfer;
				} else {
					$trx = $validate['message'];
				}
			}
		} catch ( Throwable $e ) {
			$trx = $e->getMessage();
		}

		include_once BKASH_FW_BASE_PATH . '/includes/classes/Admin//pages/disburse_money.php';
	}

	private static function buildAndSaveTransfer( array $transfer ): Transfer {
		$db_transfer = new Transfer();
		$db_transfer->setTrxId( $transfer['trxID'] ?? '' );
		$db_transfer->setAmount( $transfer['amount'] ?? '' );
		$db_transfer->setReceiver( $transfer['receiverMSISDN'] ?? '' );
		$db_transfer->setCurrency( $transfer['currency'] ?? '' );
		$db_transfer->setMerchantInvoiceNo( $transfer['merchantInvoiceNumber'] ?? '' );
		$db_transfer->setTransactionStatus( $transfer['transactionStatus'] ?? '' );
		$db_transfer->setInitiationTime( $transfer['initTime'] ?? '' );
		$db_transfer->setCompletedTime( $transfer['completedTime'] ?? date( 'now' ) );
		$db_transfer->setB2cFee( 0 );
		$db_transfer->save();

		return $db_transfer;
	}
}

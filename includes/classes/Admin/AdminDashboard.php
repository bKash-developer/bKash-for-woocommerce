<?php

namespace bKash\PGW\Admin;

use bKash\PGW\ApiComm;
use bKash\PGW\Models\Transactions;
use bKash\PGW\PaymentGatewaybKash;

define("PGW_VERSION", "1.2.0");
define("UPGRADE_FILE", "wp-admin/includes/upgrade.php");

class AdminDashboard {
	private static $instance;
	private $slug = 'bkash_admin_menu_120beta';
	private $api;

	static function GetInstance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function PluginMenu() {
		/* Adding menu and sub-menu to the admin portal */
		$this->AddMainMenu();
		$this->AddSubMenus();

	}

	/**
	 * Add menu for bKash PGW in WP Admin
	 */
	protected function AddMainMenu() {
		add_menu_page(
			'Woocommerce Payment Gateway - bKash',
			'bKash',
			'manage_options',
			$this->slug,
			array( $this, 'RenderPage' ),
			plugins_url( '../../assets/images/bkash_favicon_0.ico', __DIR__ )
		);
	}

	/**
	 * Add submenu for bKash PGW in WP Admin
	 */
	protected function AddSubMenus() {
		$subMenus = array(
			// [Page Title, Menu Title, Route, Function to render, (0=All)(1=Checkout)(2=Tokenized)]
			[ "All Transaction", "Transactions", "", "RenderPage", 0 ],
			[ "Search a bKash Transaction", "Search", "/search", "TransactionSearch", 0 ],
			[ "Refund a bKash Transaction", "Refund", "/refund", "RefundTransaction", 0 ],
			[ "Webhook notifications", "Webhooks", "/webhooks", "Webhooks", 0 ],
			[ "Check Balances", "Check Balances", "/balances", "CheckBalances", 1 ],
			[ "Intra account transfer", "Intra Account Transfer", "/intra_account", "TransferBalance", 1 ],
			[ "B2C Payout - Disbursement", "Disburse Money (B2C)", "/b2c_payout", "DisburseMoney", 1 ],
			[ "Transfer History - All List", "Transfer History", "/transfers", "TransferHistory", 1 ],
			[ "Agreements", "Agreements", "/agreements", "Agreements", 2 ]
		);

		foreach ( $subMenus as $subMenu ) {
			$int_type = 'checkout';

			if ( function_exists( 'WC' ) ) {
				$payment_gateways = WC()->payment_gateways->payment_gateways();
				if ( isset( $payment_gateways['bkash_pgw'] ) ) {
					$int_type = WC()->payment_gateways->payment_gateways()['bkash_pgw']->integration_type;
				}
			}

			if (
				( $subMenu[4] === 0 ) || ( $subMenu[4] === 1 && $int_type === 'checkout' ) ||
				( $subMenu[4] === 2 && ( strpos( $int_type, 'tokenized' ) === 0 ) )
			) {
				$sub_page = add_submenu_page(
					$this->slug,
					$subMenu[0],
					$subMenu[1],
					'manage_options',
					$this->slug . $subMenu[2], array( $this, $subMenu[3] )
				);
				add_action( 'admin_print_styles-' . $sub_page, array( $this, "admin_styles" ) );
			}
		}
	}

	/**
	 * Outputs styles used for the bKash gateway admin in wp.
	 *
	 * @access public
	 */
	public function admin_styles() {
		wp_enqueue_style( 'bfw-admin-css', plugins_url( '../../../assets/css/admin.css', __FILE__ ) );
	}

	public function CheckBalances() {
		try {
			$this->api = new ApiComm();
			$call      = $this->api->checkBalances();
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
		include_once "pages/check_balances.php";
	}

	public function TransferBalance() {
		try {
			$type   = sanitize_text_field( $_REQUEST['transfer_type'] ?? '' );
			$amount = sanitize_text_field( $_REQUEST['amount'] ?? '' );
			if ( ! empty( $type ) && ! empty( $amount ) ) {
				$comm         = new ApiComm();
				$transferCall = $comm->intraAccountTransfer( $amount, $type );

				if ( isset( $transferCall['status_code'] ) && $transferCall['status_code'] === 200 ) {
					$transfer = isset( $transferCall['response'] ) && is_string( $transferCall['response'] ) ? json_decode( $transferCall['response'], true ) : [];

					if ( isset( $transfer['errorCode'] ) ) {
						$trx = $transfer['errorMessage'] ?? '';
					} else {
						if ( $transfer ) {
							// Sample payload - array(3) { ["status_code"]=> int(200) ["header"]=> NULL ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000","trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10","currency":"BDT","transferType":"Collection2Disbursement"}" }

							// If any error for tokenized
							if ( isset( $transfer['statusMessage'] ) && $transfer['statusMessage'] !== 'Successful' ) {
								$trx = $transfer['statusMessage'];
							} // If any error for checkout
							else if ( isset( $transfer['errorCode'] ) ) {
								$trx = $transfer['errorMessage'] ?? '';
							} else if ( isset( $transfer['transactionStatus'] ) && $transfer['transactionStatus'] === 'Completed' ) {
								$trx = $transfer;
							} else {
								$trx = "Transfer is not possible right now. try again";
							}
						} else {
							$trx = "Cannot find the transaction to transfer in your database, try again";
						}
					}
				} else {
					$trx = "Cannot transfer balances from bKash server right now, try again";
				}
			}
		} catch ( \Throwable $e ) {
			$trx = $e->getMessage();
		}

		include_once "pages/transfer_balance.php";
	}

	public function DisburseMoney() {
		try {
			$receiver   = sanitize_text_field( $_REQUEST['receiver'] ?? '' );
			$amount     = sanitize_text_field( $_REQUEST['amount'] ?? '' );
			$invoice_no = sanitize_text_field( $_REQUEST['invoice_no'] ?? '' );
			$initTime   = date( 'Y-m-d H:i:s' );

			if ( ! empty( $receiver ) && ! empty( $amount ) && ! empty( $invoice_no ) ) {
				$comm         = new ApiComm();
				$transferCall = $comm->b2cPayout( $amount, $invoice_no, $receiver );

				if ( isset( $transferCall['status_code'] ) && $transferCall['status_code'] === 200 ) {
					$transfer = isset( $transferCall['response'] ) && is_string( $transferCall['response'] ) ? json_decode( $transferCall['response'], true ) : [];

					if ( isset( $transfer['errorCode'] ) ) {
						$trx = $transfer['errorMessage'] ?? '';
					} else {
						if ( $transfer ) {
							// Sample payload - array(7) { ["completedTime"]=> string(32) "2021-02-21T19:44:14:289 GMT+0000" ["trxID"]=> string(10) "8BM604KJ58" ["transactionStatus"]=> string(9) "Completed" ["amount"]=> string(3) "100" ["currency"]=> string(3) "BDT" ["receiverMSISDN"]=> string(11) "01770618575" ["merchantInvoiceNumber"]=> string(7) "1234567" }

							// If any error for tokenized
							if ( isset( $transfer['statusMessage'] ) && $transfer['statusMessage'] !== 'Successful' ) {
								$trx = $transfer['statusMessage'];
							} // If any error for checkout
							else if ( isset( $transfer['errorCode'] ) ) {
								$trx = $transfer['errorMessage'] ?? '';
							} else if ( isset( $transfer['transactionStatus'] ) && $transfer['transactionStatus'] === 'Completed' ) {

								global $wpdb;
								$tableName = $wpdb->prefix . "bkash_transfers";

								$insert = $wpdb->insert( $tableName, [
									'receiver'            => $transfer['receiverMSISDN'] ?? '', // required
									'amount'              => $transfer['amount'] ?? '',
									'currency'            => $transfer['currency'] ?? '',
									'trx_id'              => $transfer['trxID'] ?? '',
									'merchant_invoice_no' => $transfer['merchantInvoiceNumber'] ?? '',
									'transactionStatus'   => $transfer['transactionStatus'] ?? '', // required
									'b2cFee'              => 0,
									'initiationTime'      => $initTime,
									'completedTime'       => $transfer['completedTime'] ?? date( 'now' )
								] );

								if ( $insert > 0 ) {
									$trx = $transfer;
								} else {
									$trx = "Disbursement is successful but could not make it into db";
								}

							} else {
								$trx = "Transfer is not possible right now. try again";
							}
						} else {
							$trx = "Cannot find the transaction to disburse in your database, try again";
						}
					}
				} else {
					$trx = "Cannot disburse money from bKash server right now, try again";
				}
			}
		} catch ( \Throwable $e ) {
			$trx = $e->getMessage();
		}

		include_once "pages/disburse_money.php";
	}

	public function TransactionSearch() {
		try {
			$trx_id = sanitize_text_field( $_REQUEST['trxid'] );

			$this->api = new ApiComm();
			$call      = $this->api->searchTransaction( $trx_id );

			if ( isset( $call['status_code'] ) && $call['status_code'] === 200 ) {

				$trx = [];
				if(isset($call['response']) && is_string($call['response'])) {
					$trx = json_decode($call['response'], true);
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
		} catch ( \Exception $ex ) {
			$trx = $ex->getMessage();
		}

		include_once "pages/transaction_search.php";
	}

	public function TransferHistory() {
		include_once "pages/transfer_history.php";
	}


	public function RefundTransaction() {
		$trx           = "";
		$trx_id        = sanitize_text_field( $_REQUEST['trxid'] ?? '' );
		$fill_trx_id   = sanitize_text_field( $_REQUEST['fill_trx_id'] ?? '' );
		$reason        = sanitize_text_field( $_REQUEST['reason'] ?? '' );
		$amount        = sanitize_text_field( $_REQUEST['amount'] ?? '' );
		$isRefund      = isset( $_REQUEST['refund'] );
		$isRefundCheck = isset( $_REQUEST['check'] );

		if ( ! empty( $trx_id ) ) {
			$trxObject   = new Transactions();
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

		include_once "pages/refund_transaction.php";
	}

	public function Webhooks() {
		include_once "pages/webhooks_list.php";
	}

	public function Agreements() {
		include_once "pages/agreements_list.php";
	}

	public function RenderPage() {
		include_once "pages/transaction_list.php";
	}

	public function Initiate() {
		add_action( 'admin_menu', array( $this, 'PluginMenu' ) );
	}

	public function BeginInstall() {
		$this->CreateTransactionTable();
		$this->CreateWebhookTable();
		$this->CreateAgreementMappingTable();
		$this->CreateTransferHistoryTable();

	}

	public function CreateTransactionTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_transactions";
		$my_products_db_version = PGW_VERSION;
		$charset_collate        = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

			$sql = "CREATE TABLE $table_name (
                    ID bigint NOT NULL AUTO_INCREMENT,
                    `order_id` VARCHAR(100) NOT NULL,
                    `trx_id` VARCHAR(50) NULL ,
                    `invoice_id` VARCHAR(100) NOT NULL UNIQUE,
                    `payment_id` VARCHAR(50) NULL ,
                    `integration_type` VARCHAR(50) NOT NULL,
                    `mode` VARCHAR(10) NULL,
                    `intent` VARCHAR(20) NULL,
                    `amount` decimal(15,2) NOT NULL,
                    `currency` VARCHAR(10) NOT NULL,
                    `refund_id` VARCHAR(50) NULL,
                    `refund_amount` decimal(15,2) NULL,
                    `status` VARCHAR(50) NULL,
                    `datetime` timestamp NULL,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

			require_once( ABSPATH . UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_transaction_table_version', $my_products_db_version );
		}
	}

	public function CreateWebhookTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_webhooks";
		$my_products_db_version = PGW_VERSION;
		$charset_collate        = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

			$sql = "CREATE TABLE $table_name (
                    ID bigint NOT NULL AUTO_INCREMENT,
                    `sender` VARCHAR(20) NOT NULL,
                    `receiver` VARCHAR(20) NOT NULL,
                    `receiver_name` VARCHAR(100) NULL,
                    `trx_id` VARCHAR(50) NOT NULL UNIQUE,
                    `status` VARCHAR(30) NOT NULL,
                    `type` VARCHAR(50) NOT NULL,
                    `amount` decimal(15,2) NOT NULL,
                    `currency` VARCHAR(10) NULL,
                    `reference` VARCHAR(100) NULL,
                    `datetime` timestamp NULL,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

			require_once( ABSPATH . UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_webhook_table_version', $my_products_db_version );
		}
	}

	public function CreateAgreementMappingTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_agreement_mapping";
		$my_products_db_version = PGW_VERSION;
		$charset_collate        = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

			$sql = "CREATE TABLE $table_name (
                    ID bigint NOT NULL AUTO_INCREMENT,
                    `phone` VARCHAR(20) NOT NULL,
                    `user_id` bigint NOT NULL,
                    `agreement_token` VARCHAR(300) NOT NULL,
                    `datetime` timestamp NOT NULL,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

			require_once( ABSPATH . UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_agreement_mapping_table_version', $my_products_db_version );
		}
	}

	public function CreateTransferHistoryTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_transfers";
		$my_products_db_version = PGW_VERSION;
		$charset_collate        = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

			$sql = "CREATE TABLE $table_name (
                    ID bigint NOT NULL AUTO_INCREMENT,
                    `receiver` VARCHAR(20) NOT NULL,
                    `amount` decimal(15,2) NOT NULL,
                    `currency` VARCHAR(3) NOT NULL,
                    `trx_id` VARCHAR(50) NOT NULL,
                    `merchant_invoice_no` VARCHAR(80) NOT NULL,
                    `transactionStatus` VARCHAR(30) NOT NULL,
                    `b2cFee` VARCHAR(40) NULL,
                    `initiationTime` timestamp NULL,
                    `completedTime` timestamp NULL,
                    PRIMARY KEY (ID)
            ) $charset_collate;";

			require_once( ABSPATH . UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_agreement_mapping_table_version', $my_products_db_version );
		}
	}
}
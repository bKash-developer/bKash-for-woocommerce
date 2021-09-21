<?php

namespace bKash\PGW\Admin;

use bKash\PGW\ApiComm;
use bKash\PGW\Models\Transactions;
use bKash\PGW\PaymentGatewaybKash;
use bKash\PGW\TableGeneration;

define( "BKASH_PGW_VERSION", "1.2.0" );
define( "BKASH_TABLE_LIMIT", 10 );

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
			array( $this, 'TransactionList' ),
			plugins_url( '../../assets/images/bkash_favicon_0.ico', __DIR__ )
		);
	}

	/**
	 * Add submenu for bKash PGW in WP Admin
	 */
	protected function AddSubMenus() {
		$subMenus = array(
			// [Page Title, Menu Title, Route, Function to render, (0=All)(1=Checkout)(2=Tokenized)]
			[ "All Transaction", "Transactions", "", "TransactionList", 0 ],
			[ "Search a bKash Transaction", "Search", "/search", "TransactionSearch", 0 ],
			[ "Refund a bKash Transaction", "Refund", "/refund", "RefundTransaction", 0 ],
			[ "Webhook notifications", "Webhooks", "/webhooks", "Webhooks", 0 ],
			[ "Check Balances", "Check Balances", "/balances", "CheckBalances", 1 ],
			[ "Intra account transfer", "Intra Account Transfer", "/intra_account", "TransferBalance", 1 ],
			[ "B2C Payout - Disbursement", "Disburse Money (B2C)", "/b2c_payout", "DisburseMoney", 1 ],
			[ "Transfer History - All List", "Transfer History", "/transfers", "TransferHistory", 1 ],
			[ "Agreements", "Agreements", "/agreements", "Agreements", 2 ]
		);

		$is_b2c_enabled = 'no';
		$pid            = 'bkash_pgw';
		$options        = get_option( 'woocommerce_' . $pid . '_settings' );
		if ( ! is_null( $options ) ) {
			$is_b2c_enabled = $options['enable_b2c'] ?? 'no';
		}

		foreach ( $subMenus as $subMenu ) {
			$int_type = 'checkout';

			if ( function_exists( 'WC' ) ) {
				$payment_gateways = WC()->payment_gateways->payment_gateways();
				if ( isset( $payment_gateways['bkash_pgw'] ) ) {
					$int_type = WC()->payment_gateways->payment_gateways()['bkash_pgw']->integration_type;
				}
			}

			if (
				( $subMenu[4] === 0 ) || ( ( $is_b2c_enabled === 'yes' && $subMenu[4] === 1 ) && $int_type === 'checkout' ) ||
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
		include_once "pages/check_balances.php";
	}

	public function TransferBalance() {
		try {
			$type   = sanitize_text_field( $_REQUEST['transfer_type'] ?? '' );
			$amount = sanitize_text_field( $_REQUEST['amount'] ?? '' );
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
				if ( $type && $amount ) {
					$comm         = new ApiComm();
					$transferCall = $comm->intraAccountTransfer( $amount, $type );

					$validate = $this->validateResponse( $transferCall, array( 'transactionStatus' => 'Completed' ) );
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

		include_once "pages/transfer_balance.php";
	}

	public function validateResponse( $apiResp = array(), $specificField = array() ) {
		$feedback = array(
			'valid'    => false,
			'message'  => '',
			'response' => []
		);


		if ( isset( $apiResp['status_code'], $apiResp['response'] ) && $apiResp['status_code'] === 200 ) {
			$response = $apiResp['response'];
			if ( is_string( $response ) ) {
				$response = json_decode( $response, true );
			}

			if ( isset( $response['errorMessage'] ) ) {
				$feedback['message'] = $response['errorMessage'];
			} else if ( isset( $response['statusMessage'] ) && $response['statusMessage'] !== 'Successful' ) {
				$feedback['message'] = $response['statusMessage'];
			} else {
				if ( count( $specificField ) > 0 ) {
					if ( $response[ key( $specificField ) ] === $specificField[ key( $specificField ) ] ) {
						$feedback['valid'] = true;
					} else {
						$feedback['message'] = key( $specificField ) . " is not present or not matching with the value " . $specificField[ key( $specificField ) ];
					}
				} else {
					$feedback['valid'] = true;
				}

				$feedback['response'] = $response;
			}
		} else {
			$feedback['message'] = "Action cannot be performed at bKash server right now, try again";
		}

		return $feedback;
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

				$validate = $this->validateResponse( $transferCall, array( 'transactionStatus' => 'Completed' ) );
				if ( $validate['valid'] ) {
					$transfer = $validate['response'];
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
					$trx = $validate['message'];
				}
			}
		} catch ( \Throwable $e ) {
			$trx = $e->getMessage();
		}

		include_once "pages/disburse_money.php";
	}

	public function TransactionSearch() {
		try {
			$trx_id = "";
			if ( isset( $_REQUEST['trxid'] ) ) {
				$trx_id = sanitize_text_field( $_REQUEST['trxid'] );
			}

			if ( $trx_id !== '' ) {
				$this->api = new ApiComm();
				$call      = $this->api->searchTransaction( $trx_id );

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

		include_once "pages/transaction_search.php";
	}

	public function TransferHistory() {
		$this->loadTable( "Transfer History", "bkash_transfers",
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
			) );
	}

	public function loadTable( $title, $tbl_name, $columns = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . $tbl_name;

		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;

		$limit        = BKASH_TABLE_LIMIT;
		$offset       = ( $pagenum - 1 ) * $limit;
		$total        = $wpdb->get_var( "select count(*) as total from $table_name" );
		$num_of_pages = ceil( $total / $limit );

		$rows     = $wpdb->get_results( "SELECT * from $table_name ORDER BY id DESC limit  $offset, $limit" );
		$rowcount = $wpdb->num_rows;

		?>
        <div class="wrap abs">
            <h2><?php echo esc_html( $title ); ?></h2>
            <div class="tablenav top">
                <div class="alignleft actions">
                </div>
                <br class="clear">
            </div>

            <table id="transaction-list-table" class='wp-list-table widefat fixed striped posts'
                   aria-describedby="<?php echo esc_attr( $title ); ?>">
                <tr>
					<?php
					foreach ( array_keys( $columns ) as $table_head ) {
						?>
                        <th class='manage-column ss-list-width' scope='col'>
							<?php echo esc_html( $table_head ); ?>
                        </th>
						<?php
					}
					?>
                </tr>

				<?php
				if ( $rowcount > 0 ) {
					foreach ( $rows as $row ) { ?>
                        <tr>
							<?php
							foreach ( $columns as $column ) {
								echo "<td class='manage-column ss-list-width'>" . esc_html( $row->{$column} ) . "</td>";
							}
							?>
                        </tr>
					<?php }
				} else {
					echo "<tr><td colspan='5'>No records found</td></tr>";
				} ?>
            </table>
        </div>
		<?php

		$page_links = paginate_links( array(
			'base'      => add_query_arg( 'pagenum', '%#%' ),
			'format'    => '',
			'prev_text' => __( '&laquo;', 'text-domain' ),
			'next_text' => __( '&raquo;', 'text-domain' ),
			'total'     => $num_of_pages,
			'current'   => $pagenum
		) );

		if ( $page_links ) {
			echo '<div class="tablenav pagination-links" style="width: 99%;"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
		}
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
		$this->loadTable( "All Webhooks", "bkash_webhooks",
			array(
				"ID"            => "ID",
				"TRX_ID"        => "trx_id",
				"SENDER"        => "sender",
				"RECEIVER"      => "receiver",
				"RECEIVER NAME" => "receiver_name",
				"AMOUNT"        => "amount",
				"REFERENCE"     => "reference",
				"TYPE"          => "type",
				"STATUS"        => "status",
				"DATETIME"      => "datetime"
			) );
	}

	public function Agreements() {
		include_once "pages/agreements_list.php";
	}

	public function TransactionList() {
		$this->loadTable( "All bKash Transactions", "bkash_transactions",
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
			)
		);
	}

	public function Initiate() {
		add_action( 'admin_menu', array( $this, 'PluginMenu' ) );
	}

	public function BeginInstall() {
		$tableGenerator = new TableGeneration();
		$tableGenerator->CreateTransactionTable();
		$tableGenerator->CreateWebhookTable();
		$tableGenerator->CreateAgreementMappingTable();
		$tableGenerator->CreateTransferHistoryTable();

	}
}
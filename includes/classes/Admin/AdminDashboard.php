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
		$pid                = 'bkash_pgw';
		$is_b2c_enabled     = self::get_bKash_options( $pid, 'enable_b2c' );
		$is_webhook_enabled = self::get_bKash_options( $pid, 'webhook' );
		$integration_type   = self::get_bKash_options( $pid, 'integration_type' );

		$sub_menus = array(
			array(
				'title'      => 'All Transactions',
				'menu_title' => 'Transactions',
				'route'      => '',
				'function'   => 'TransactionList',
				'show'       => true
			),
			array(
				'title'      => 'Search a bKash Transaction',
				'menu_title' => 'Search',
				'route'      => '/search',
				'function'   => 'TransactionSearch',
				'show'       => true
			),
			array(
				'title'      => 'Refund a bKash Transaction',
				'menu_title' => 'Refund',
				'route'      => '/refund',
				'function'   => 'RefundTransaction',
				'show'       => true
			),
			array(
				'title'      => 'Webhook notifications',
				'menu_title' => 'Webhooks',
				'route'      => '/webhooks',
				'function'   => 'Webhooks',
				'show'       => $is_webhook_enabled
			),
			array(
				'title'      => 'Check Balances',
				'menu_title' => 'Check Balances',
				'route'      => '/balances',
				'function'   => 'CheckBalances',
				'show'       => $integration_type === 'checkout'
			),
			array(
				'title'      => 'Intra account transfer',
				'menu_title' => 'Intra Account Transfer',
				'route'      => '/intra_account',
				'function'   => 'TransferBalance',
				'show'       => $integration_type === 'checkout'
			),
			array(
				'title'      => 'B2C Payout - Disbursement',
				'menu_title' => 'Disburse Money (B2C)',
				'route'      => '/b2c_payout',
				'function'   => 'DisburseMoney',
				'show'       => $integration_type === 'checkout' && $is_b2c_enabled
			),
			array(
				'title'      => 'Transfer History - All List',
				'menu_title' => 'Transfer History',
				'route'      => '/transfers',
				'function'   => 'TransferHistory',
				'show'       => $integration_type === 'checkout'
			),
			array(
				'title'      => 'Agreements',
				'menu_title' => 'Agreements',
				'route'      => '/agreements',
				'function'   => 'Agreements',
				'show'       => strpos( $integration_type, 'tokenized' ) === 0
			)
		);

		foreach ( $sub_menus as $sub_menu ) {


			if ( isset( $sub_menu['show'] ) && $sub_menu["show"] === true ) {
				$sub_page = add_submenu_page(
					$this->slug,
					$sub_menu['title'],
					$sub_menu['menu_title'],
					'manage_options',
					$this->slug . $sub_menu['route'], array( $this, $sub_menu['function'] )
				);
				add_action( 'admin_print_styles-' . $sub_page, array( $this, "admin_styles" ) );
			}
		}
	}

	private static function get_bKash_options( $plugin_id, $key ) {
		$option_value = false;
		$options      = get_option( 'woocommerce_' . $plugin_id . '_settings' );

		if ( ! is_null( $options ) ) {
			if ( $options[ $key ] === 'yes' || $options[ $key ] === 'no' ) {
				$option_value = $options[ $key ] === 'yes';
			} else {
				$option_value = $options[ $key ];
			}
		}

		return $option_value;
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
			),

			array(
				"receiver" => "Sent To",
				"trx_id"   => "Transaction ID"
			)
		);
	}

	public function loadTable( $title, $tbl_name, $columns = array(), $filters = array(), $actions = array() ) {
		global $wpdb;
		$table_name = $wpdb->prefix . $tbl_name;
		$pagenum    = isset( $_GET['pagenum'] ) ? absint( sanitize_text_field( $_GET['pagenum'] ) ) : 1;

		$searchFilters = [];
		if ( count( $filters ) > 0 ) {
			foreach ( $filters as $key => $filter ) {
				$input = isset( $_GET[ $key ] ) ? sanitize_text_field( $_GET[ $key ] ) : null;
				if ( $input ) {
					$partialQuery    = $wpdb->prepare( 'AND ' . $key . ' LIKE %s', esc_sql( $input ) );
					$searchFilters[] = $partialQuery;
				}
			}
		}

		$limit           = BKASH_TABLE_LIMIT;
		$offset          = ( $pagenum - 1 ) * $limit;
		$selectFrom      = "SELECT * from $table_name where ID > %d ";
		$selectCountFrom = "SELECT count(*) as total from $table_name where ID > %d ";

		$prepareQuery = $wpdb->prepare(
			$selectFrom . implode( "", $searchFilters ), 0
		);
		$rows         = $wpdb->get_results( $prepareQuery . " ORDER BY id DESC limit  $offset, $limit" );
		$rowcount     = $wpdb->num_rows ?? 0;

		$total        = $wpdb->get_var(
			$wpdb->prepare( $selectCountFrom . implode( "", $searchFilters ), 0 )
		);
		$num_of_pages = ceil( $total / $limit );
		?>
        <div class="wrap abs">
            <h2><?php echo esc_html( $title ); ?></h2>
            <div class="tablenav top">
                <div class="alignleft actions">

                    <form action="#" method="GET">
						<?php
						if ( count( $filters ) > 0 ) {
							foreach ( $filters as $key => $filter ) {
								$old_input = isset( $_GET[ $key ] ) ? sanitize_text_field( $_GET[ $key ] ) : "";
								echo "<input type='text' name='$key' value='" . $old_input . "' placeholder='$filter'/>";
							}
						}

						$page_name = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
						echo "<input type='hidden' name='page' value='$page_name'/>";
						?>
                        <button type="submit">Search</button>
                    </form>


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

					if ( count( $actions ) > 0 ) {
						?>
                        <th class='manage-column ss-list-width' scope='col'>
                            Actions
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
							if ( count( $actions ) > 0 ) {
								$this->compileAction( $actions, $row );
							}
							?>
                        </tr>
					<?php }
				} else {
					echo "<tr><td colspan='" . count( $columns ) . "'>No records found</td></tr>";
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

	private function compileAction( $actions, $row ) {
		echo "<td class='manage-column ss-list-width'>";
		foreach ( $actions as $action ) {
			?>
            <a
				<?php
				if ( isset( $action['confirm'] ) && $action['confirm'] ) {
					echo 'onclick="return confirm(\'Are you sure to do this?\');"';
				}
				?>
                    href="<?php echo esc_url(
						admin_url( 'admin.php?page=' . $this->slug . '/' . ( $action['page'] ?? '' )
						           . '&action=' . ( $action['action'] ?? '' ) . '&id=' . $row->ID )
					); ?>">
				<?php echo esc_html( $action['title'] ?? '' ) ?>
            </a>
			<?php
		}
		echo "</td>";
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
			),
			array(
				"trx_id"   => "Transaction ID",
				"receiver" => "Receiver"
			)
		);
	}

	public function Agreements() {
		$this->cancelAgreement();

		$this->loadTable( "All bKash Transactions", "bkash_agreement_mapping",
			array(
				"ID"              => "ID",
				"PHONE"           => "phone",
				"USERID"          => "user_id",
				"AGREEMENT TOKEN" => "agreement_token",
				"DATETIME"        => "datetime",
			),
			array(
				"phone"   => "Phone",
				"user_id" => "User ID"
			),
			array(
				array(
					"title"   => "Cancel Agreement",
					"page"    => "agreements",
					"action"  => "cancel",
					"confirm" => true
				)
			)
		);
	}

	private function cancelAgreement() {
		$notice = "";
		$type   = "warning";
		$action = sanitize_text_field( $_REQUEST['action'] ?? '' );

		if ( $action === 'cancel' ) {
			$id = sanitize_text_field( $_REQUEST['id'] ?? null );
			if ( $id ) {
				$agreementObj = new \bKash\PGW\Models\Agreement();
				$agreement    = $agreementObj->getAgreement( '', '', $id );
				if ( $agreement ) {
					$comm            = new \bKash\PGW\ApiComm();
					$cancelAgreement = $comm->agreementCancel( $agreement->getAgreementID() );

					if ( isset( $cancelAgreement['status_code'] ) && $cancelAgreement['status_code'] === 200 ) {
						$response = isset( $cancelAgreement['response'] ) && is_string( $cancelAgreement['response'] ) ? json_decode( $cancelAgreement['response'], true ) : [];

						if ( isset( $response['agreementStatus'] ) && $response['agreementStatus'] === 'Cancelled' ) {
							// Cancelled

							$deleteAgreement = $agreementObj->delete( '', $id );
							if ( $deleteAgreement ) {
								$notice = "Agreement Deleted!";
								$type   = "success";
							} else {
								$notice = "Agreement cancelled but could not delete from db";
							}

						} else {
							$notice = "Agreement status was not present. " . json_encode( $response );
						}
					} else {
						$notice = " Server response was not ok. " . json_encode( $cancelAgreement );
					}
				} else {
					$notice = "No agreement found related with this ID";
				}
			} else {
				$notice = "ID was not present to cancel";
			}

			PaymentGatewaybKash::add_flash_notice( $notice, $type );
			self::redirectToPage();
		}


	}

	private static function redirectToPage() {

		$page        = sanitize_text_field( $_GET["page"] ?? '' );
		$actual_link = strtok( "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]", '?' );

		wp_redirect( esc_url( $actual_link . "?page=" . $page ) );
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
			),
			array(
				"trx_id"     => "Transaction ID",
				"invoice_id" => "Invoice ID",
				"status"     => "Status"
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
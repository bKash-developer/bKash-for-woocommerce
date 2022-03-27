<?php

namespace bKash\PGW\Admin;

use bKash\PGW\TableGeneration;

define( "BKASH_FW_PGW_VERSION", "1.2.0" );
define( "BKASH_FW_TABLE_LIMIT", 10 );
define( "BKASH_FW_ADMIN_PAGE_SLUG", 'bkash_admin_menu_120beta' );

class AdminDashboard {
	private static $instance;

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
			BKASH_FW_ADMIN_PAGE_SLUG,
			array( 'bKash\PGW\Admin\Module\TransactionModule', 'transaction_list' ),
			plugins_url( '../../assets/images/bkash_favicon_0.ico', __DIR__ )
		);

	}

	/**
	 * Add submenu for bKash PGW in WP Admin
	 */
	protected function AddSubMenus() {
		$pid                = BKASH_FW_PLUGIN_SLUG;
		$is_b2c_enabled     = AdminUtility::get_bKash_options( $pid, 'enable_b2c' );
		$is_webhook_enabled = AdminUtility::get_bKash_options( $pid, 'webhook' );
		$integration_type   = AdminUtility::get_bKash_options( $pid, 'integration_type' );

		$sub_menus = array(
			array(
				'title'      => 'All Transaction',
				'menu_title' => 'Transaction',
				'route'      => '',
				'function'   => array( 'bKash\PGW\Admin\Module\TransactionModule', 'transaction_list' ),
				'show'       => true
			),
			array(
				'title'      => 'Search a bKash Transaction',
				'menu_title' => 'Search',
				'route'      => '/search',
				'function'   => array( 'bKash\PGW\Admin\Module\TransactionModule', 'transaction_search' ),
				'show'       => true
			),
			array(
				'title'      => 'Refund a bKash Transaction',
				'menu_title' => 'Refund',
				'route'      => '/refund',
				'function'   => array( 'bKash\PGW\Admin\Module\TransactionModule', 'refund_a_transaction' ),
				'show'       => true
			),
			array(
				'title'      => 'Webhook notifications',
				'menu_title' => 'Webhook',
				'route'      => '/webhooks',
				'function'   => array( 'bKash\PGW\Admin\Module\TransferModule', 'transfer_history' ),
				'show'       => $is_webhook_enabled
			),
			array(
				'title'      => 'Check Balances',
				'menu_title' => 'Check Balances',
				'route'      => '/balances',
				'function'   => array( 'bKash\PGW\Admin\Module\TransferModule', 'check_balances' ),
				'show'       => $integration_type === 'checkout'
			),
			array(
				'title'      => 'Intra account transfer',
				'menu_title' => 'Intra Account Transfer',
				'route'      => '/intra_account',
				'function'   => array( 'bKash\PGW\Admin\Module\TransferModule', 'transfer_balance' ),
				'show'       => $integration_type === 'checkout'
			),
			array(
				'title'      => 'B2C Payout - Disbursement',
				'menu_title' => 'Disburse Money (B2C)',
				'route'      => '/b2c_payout',
				'function'   => array( 'bKash\PGW\Admin\Module\TransferModule', 'disburse_money' ),
				'show'       => $integration_type === 'checkout' && $is_b2c_enabled
			),
			array(
				'title'      => 'Transfer History',
				'menu_title' => 'Transfer History',
				'route'      => '/transfers',
				'function'   => array( 'bKash\PGW\Admin\Module\TransferModule', 'transfer_history' ),
				'show'       => $integration_type === 'checkout'
			),
			array(
				'title'      => 'Agreements',
				'menu_title' => 'Agreements',
				'route'      => '/agreements',
				'function'   => array( 'bKash\PGW\Admin\Module\AgreementModule', 'agreement_list' ),
				'show'       => strpos( $integration_type, 'tokenized' ) === 0
			),
			array(
				'title'      => 'Payment Settings',
				'menu_title' => 'Settings',
				'route'      => esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . BKASH_FW_PLUGIN_SLUG ) ),
				'show'       => 'link'
			)
		);

		foreach ( $sub_menus as $sub_menu ) {


			if ( isset( $sub_menu['show'] ) && $sub_menu["show"] === true ) {
				$sub_page = add_submenu_page(
					BKASH_FW_ADMIN_PAGE_SLUG,
					$sub_menu['title'],
					$sub_menu['menu_title'],
					'manage_options',
					BKASH_FW_ADMIN_PAGE_SLUG . $sub_menu['route'], $sub_menu['function']
				);
				add_action( 'admin_print_styles-' . $sub_page, array( $this, "admin_styles" ) );
			} else if ( $sub_menu["show"] === "link" ) {
				global $submenu;
				$submenu[ BKASH_FW_ADMIN_PAGE_SLUG ][] = array(
					$sub_menu['title'],
					'manage_options',
					$sub_menu['route']
				);
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
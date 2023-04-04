<?php

namespace bKash\PGW;

define( "BKASH_UPGRADE_FILE", "wp-admin/includes/upgrade.php" );

class TableGeneration {


	public function CreateTransactionTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_transactions";
		$my_products_db_version = BKASH_FW_PGW_VERSION;
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

			require_once( ABSPATH . BKASH_UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_transaction_table_version', $my_products_db_version );
		}
	}

	public function CreateWebhookTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_webhooks";
		$my_products_db_version = BKASH_FW_PGW_VERSION;
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

			require_once( ABSPATH . BKASH_UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_webhook_table_version', $my_products_db_version );
		}
	}

	public function CreateAgreementMappingTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_agreement_mapping";
		$my_products_db_version = BKASH_FW_PGW_VERSION;
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

			require_once( ABSPATH . BKASH_UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_agreement_mapping_table_version', $my_products_db_version );
		}
	}

	public function CreateTransferHistoryTable() {
		global $wpdb;
		$table_name             = $wpdb->prefix . "bkash_transfers";
		$my_products_db_version = BKASH_FW_PGW_VERSION;
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

			require_once( ABSPATH . BKASH_UPGRADE_FILE );
			dbDelta( $sql );
			add_option( 'bkash_agreement_mapping_table_version', $my_products_db_version );
		}
	}
}
<?php

namespace bKash\PGW\Admin;

use bKash\PGW\ApiComm;
use bKash\PGW\Models\Transactions;
use bKash\PGW\WC_bKash;
use Exception;
use WC_Order_Refund;
use WP_Error;

class AdminDashboard
{
    private static $instance;
    private $slug = 'bkash_admin_menu_120beta';
    private $api;

    public function __construct()
    {
        $this->api = new ApiComm();
    }

    static function GetInstance()
    {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function PluginMenu()
    {
        /* Adding menu and sub-menu to the admin portal */
        $this->AddMainMenu();
        $this->AddSubMenus();

    }

    /**
     * Add menu for bKash PGW in WP Admin
     */
    protected function AddMainMenu(): void
    {
        add_menu_page(
            'Woocommerce Payment Gateway - bKash',
            'bKash',
            'manage_options',
            $this->slug,
            array($this, 'RenderPage'),
            plugins_url('../../assets/images/bkash_favicon_0.ico', __DIR__)
        );
    }

    /**
     * Add submenu for bKash PGW in WP Admin
     */
    protected function AddSubMenus(): void
    {
        $subMenus = array(
            // [Page Title, Menu Title, Route, Function to render, (0=All)(1=Checkout)(2=Tokenized)]
            ["All Transaction", "Transactions", "", "RenderPage", 0],
            ["Search a bKash Transaction", "Search", "/search", "TransactionSearch", 0],
            ["Refund a bKash Transaction", "Refund", "/refund", "RefundTransaction", 0],
            ["Webhook notifications", "Webhooks", "/webhooks", "Webhooks", 0],
            ["Check Balances", "Check Balances", "/balances", "CheckBalances", 1],
            ["Intra account transfer", "Transfer Balance", "/intra_account", "TransferBalance", 1],
            ["B2C Payout - Disbursement", "Disburse Money", "/b2c_payout", "DisburseMoney", 1],
            ["Transfer History - All List", "Transfer History", "/transfers", "TransferHistory", 1],
            ["Agreements", "Agreements", "/agreements", "Agreements", 2]
        );

        foreach ($subMenus as $subMenu) {
            $int_type = $this->api->get_option("integration_type");
            $restrict = null;
            add_submenu_page($this->slug, $subMenu[0], $subMenu[1], 'manage_options', $this->slug . $subMenu[2], array($this, $subMenu[3]));
        }
    }


    public function CheckBalances()
    {
        try {
            $call = $this->api->checkBalances();
            if (isset($call['status_code']) && $call['status_code'] === 200) {
                $balances = isset($call['response']) && is_string($call['response']) ? json_decode($call['response'], true) : [];

                if(isset($balances['errorCode'])) {
                    $balances = $balances['errorMessage'] ?? '';
                }
            } else {
                $balances = "Cannot read balances from bKash server right now, try again";
            }
        } catch (\Throwable $e) {
            $balances = $e->getMessage();
        }
        include_once "pages/check_balances.php";
    }

    public function TransferBalance()
    {
        try {
            $type = sanitize_text_field($_REQUEST['transfer_type'] ?? '');
            $amount = sanitize_text_field($_REQUEST['amount'] ?? '');
            if (!empty($type) && !empty($amount)) {
                $comm = new ApiComm();
                $transferCall = $comm->intraAccountTransfer($amount, $type);

                if (isset($transferCall['status_code']) && $transferCall['status_code'] === 200) {
                    $transfer = isset($transferCall['response']) && is_string($transferCall['response']) ? json_decode($transferCall['response'], true) : [];

                    if(isset($transfer['errorCode'])) {
                        $trx = $transfer['errorMessage'] ?? '';
                    } else {
                        if ($transfer) {
                            // Sample payload - array(3) { ["status_code"]=> int(200) ["header"]=> NULL ["response"]=> string(177) "{"completedTime":"2021-02-21T18:46:18:085 GMT+0000","trxID":"8BM304KJ37","transactionStatus":"Completed","amount":"10","currency":"BDT","transferType":"Collection2Disbursement"}" }

                            // If any error for tokenized
                            if (isset($transfer['statusMessage']) && $transfer['statusMessage'] !== 'Successful') {
                                $trx = $transfer['statusMessage'];
                            } // If any error for checkout
                            else if (isset($transfer['errorCode'])) {
                                $trx = $transfer['errorMessage'] ?? '';
                            } else if (isset($transfer['transactionStatus']) && $transfer['transactionStatus'] === 'Completed') {
                                $trx = $transfer;
                            } else {
                                $trx = "Transfer is not possible right now. try again";
                            }
                        } else {
                            $trx = "Cannot find the transaction in your database, try again";
                        }
                    }
                } else {
                    $trx = "Cannot read balances from bKash server right now, try again";
                }
            }
        } catch (\Throwable $e) {
            $trx = $e->getMessage();
        }

        include_once "pages/transfer_balance.php";
    }

    public function DisburseMoney()
    {
        try {
            $receiver = sanitize_text_field($_REQUEST['receiver'] ?? '');
            $amount = sanitize_text_field($_REQUEST['amount'] ?? '');
            $invoice_no = sanitize_text_field($_REQUEST['invoice_no'] ?? '');
            $initTime = date('now');

            if (!empty($receiver) && !empty($amount) && !empty($invoice_no)) {
                $comm = new ApiComm();
                $transferCall = $comm->b2cPayout($amount, $invoice_no, $receiver);

                if (isset($transferCall['status_code']) && $transferCall['status_code'] === 200) {
                    $transfer = isset($transferCall['response']) && is_string($transferCall['response']) ? json_decode($transferCall['response'], true) : [];

                    if(isset($transfer['errorCode'])) {
                        $trx = $transfer['errorMessage'] ?? '';
                    } else {
                        if ($transfer) {
                            // Sample payload - array(7) { ["completedTime"]=> string(32) "2021-02-21T19:44:14:289 GMT+0000" ["trxID"]=> string(10) "8BM604KJ58" ["transactionStatus"]=> string(9) "Completed" ["amount"]=> string(3) "100" ["currency"]=> string(3) "BDT" ["receiverMSISDN"]=> string(11) "01770618575" ["merchantInvoiceNumber"]=> string(7) "1234567" }

                            // If any error for tokenized
                            if (isset($transfer['statusMessage']) && $transfer['statusMessage'] !== 'Successful') {
                                $trx = $transfer['statusMessage'];
                            } // If any error for checkout
                            else if (isset($transfer['errorCode'])) {
                                $trx = $transfer['errorMessage'] ?? '';
                            } else if (isset($transfer['transactionStatus']) && $transfer['transactionStatus'] === 'Completed') {

                                global $wpdb;
                                $tableName = $wpdb->prefix . "bkash_transfers";

                                $insert = $wpdb->insert($tableName, [
                                    'receiver' => $transfer['receiverMSISDN'] ?? '', // required
                                    'amount' => $transfer['amount'] ?? '',
                                    'currency' => $transfer['currency'] ?? '',
                                    'trx_id' => $transfer['trxID'] ?? '',
                                    'merchant_invoice_no' => $transfer['merchantInvoiceNumber'] ?? '',
                                    'transactionStatus' => $transfer['transactionStatus'] ?? '', // required
                                    'b2cFee' => 0,
                                    'initiationTime' => $initTime,
                                    'completedTime' => $transfer['completedTime'] ?? date('now')
                                ]);

                                if ($insert > 0) {
                                    $trx = $transfer;
                                } else {
                                    $trx = "Disbursement is successful but could not make it into db";
                                }

                            } else {
                                $trx = "Transfer is not possible right now. try again";
                            }
                        } else {
                            $trx = "Cannot find the transaction in your database, try again";
                        }
                    }
                } else {
                    $trx = "Cannot read balances from bKash server right now, try again";
                }
            }
        } catch (\Throwable $e) {
            $trx = $e->getMessage();
        }

        include_once "pages/disburse_money.php";
    }

    public function TransactionSearch()
    {
        $trx_id = isset($_REQUEST['trxid']) ? sanitize_text_field($_REQUEST['trxid']) : null;
        if (!empty($trx_id)) {
            $call = $this->api->searchTransaction($trx_id);
            if (isset($call['status_code']) && $call['status_code'] === 200) {
                $trx = isset($call['response']) && is_string($call['response']) ? json_decode($call['response'], true) : [];

                // If any error
                if (isset($trx['statusMessage']) && $trx['statusMessage'] !== 'Successful') {
                    $trx = $trx['statusMessage'];
                }
                if (isset($trx['errorMessage']) && !empty($trx['errorMessage'])) {
                    $trx = $trx['errorMessage'];
                }
            } else {
                $trx = "Cannot find the transaction from bKash server right now, try again";
            }
        }

        include_once "pages/transaction_search.php";
    }

    public function TransferHistory()
    {
        include_once "pages/transfer_history.php";
    }


    public function RefundTransaction()
    {

        $trx = "";
        $trx_id = sanitize_text_field($_REQUEST['trxid'] ?? '');
        $fill_trx_id = sanitize_text_field($_REQUEST['fill_trx_id'] ?? '');
        $reason = sanitize_text_field($_REQUEST['reason'] ?? '');
        $amount = sanitize_text_field($_REQUEST['amount'] ?? '');
        if (!empty($trx_id)) {
            $trxObject = new Transactions();
            $transaction = $trxObject->getTransaction("", $trx_id);
            if ($transaction) {
                $wcB = new WC_bKash();
                $refund = $wcB->process_refund($transaction->getOrderID(), $amount, $reason);
                if ($refund) {
                    $trx = $wcB->refundObj;
                } else {
                    $trx = "Refund is not successful, " . ($wcB->refundError ?? '');
                }
            } else {
                $trx = "Cannot find the transaction in your database, try again";
            }
        }

        include_once "pages/refund_transaction.php";
    }

    /**
     * Process Order Refund through Code
     * @return WC_Order_Refund|WP_Error
     * @throws Exception
     */
    function wc_refund_order_after_bkash_refund_is_done($order_id, $amount, $refund_reason = '')
    {

        $order = wc_get_order($order_id);

        // If it's something else such as a WC_Order_Refund, we don't want that.
        if (!$order instanceof \WC_Order) {
            return new WP_Error('wc-order', __('Provided ID is not a WC Order', 'woocommerce-payment-gateway-bkash'));
        }

        if ('refunded' === $order->get_status()) {
            return new WP_Error('wc-order', __('Order has been already refunded', 'woocommerce-payment-gateway-bkash'));
        }


        // Get Items
        $order_items = $order->get_items();

        // Refund Amount
        $refund_amount = wc_format_decimal($amount);

        if ($order_items) {
            foreach ($order_items as $item_id => $item) {

                $item_meta = $order->get_meta($item_id);
                $tax_data = $item_meta['_line_tax_data'] ?? [];
                $refund_tax = 0;
                if (isset($tax_data[0]) && is_array($tax_data[0])) {
                    $refund_tax = array_map('wc_format_decimal', $tax_data[0]);
                }
            }
        }

        // Order Items were processed. We can now create a refund

        $refund = wc_create_refund(array(
            'amount' => $refund_amount,
            'reason' => $refund_reason,
            'order_id' => $order_id,
            'refund_payment' => true
        ));

        return $refund;
    }

    public function Webhooks()
    {
        include_once "pages/webhooks_list.php";
    }

    public function Agreements()
    {
        include_once "pages/agreements_list.php";
    }

    public function RenderPage()
    {
        include_once "pages/transaction_list.php";
    }

    public function Initiate(): void
    {
        add_action('admin_menu', array($this, 'PluginMenu'));
    }

    public function BeginInstall()
    {
        $this->CreateTransactionTable();
        $this->CreateWebhookTable();
        $this->CreateAgreementMappingTable();
        $this->CreateTransferHistoryTable();

    }

    public function CreateTransactionTable()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "bkash_transactions";
        $my_products_db_version = '1.2.0';
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {

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
                    `status` VARCHAR(50) NOT NULL default('CREATED'),
                    `datetime` timestamp NULL,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('bkash_transaction_table_version', $my_products_db_version);
        }
    }

    public function CreateWebhookTable()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "bkash_webhooks";
        $my_products_db_version = '1.2.0';
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {

            $sql = "CREATE TABLE $table_name (
                    ID bigint NOT NULL AUTO_INCREMENT,
                    `sender` VARCHAR(20) NOT NULL,
                    `receiver` VARCHAR(20) NOT NULL,
                    `receiver_name` VARCHAR(100) NULL,
                    `trx_id` VARCHAR(50) NOT NULL UNIQUE,
                    `status` VARCHAR(30) NOT NULL,
                    `type` VARCHAR(50) NOT NULL,
                    `amount` decimal(15,2) NOT NULL,
                    `currency` VARCHAR(10) NOT NULL,
                    `reference` VARCHAR(100) NOT NULL,
                    `datetime` int(9) NOT NULL,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('bkash_webhook_table_version', $my_products_db_version);
        }
    }

    public function CreateAgreementMappingTable()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "bkash_agreement_mapping";
        $my_products_db_version = '1.2.0';
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {

            $sql = "CREATE TABLE $table_name (
                    ID bigint NOT NULL AUTO_INCREMENT,
                    `phone` VARCHAR(20) NOT NULL,
                    `user_id` bigint NOT NULL,
                    `agreement_token` VARCHAR(300) NOT NULL,
                    `datetime` timestamp NOT NULL,
                    PRIMARY KEY  (ID)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('bkash_agreement_mapping_table_version', $my_products_db_version);
        }
    }

    public function CreateTransferHistoryTable()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "bkash_transfers";
        $my_products_db_version = '1.2.0';
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {

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

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('bkash_agreement_mapping_table_version', $my_products_db_version);
        }
    }
}
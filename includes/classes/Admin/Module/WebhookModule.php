<?php
/**
 * Webhook Module
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

class WebhookModule {
	public static function webhooks() {
		AdminUtility::loadTable(
			'All WebhookProcessor',
			'bkash_webhooks',
			array(
				'ID'            => 'ID',
				'TRX_ID'        => 'trx_id',
				'SENDER'        => 'sender',
				'RECEIVER'      => 'receiver',
				'RECEIVER NAME' => 'receiver_name',
				'AMOUNT'        => 'amount',
				'REFERENCE'     => 'reference',
				'TYPE'          => 'type',
				'STATUS'        => 'status',
				'DATETIME'      => 'datetime',
			),
			array(
				'trx_id'   => 'Transaction ID',
				'receiver' => 'Receiver',
			)
		);
	}
}

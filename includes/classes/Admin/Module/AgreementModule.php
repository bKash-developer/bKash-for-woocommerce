<?php
/**
 * Agreement Module
 *
 * @category    Module
 * @package     bkash-for-woocommerce
 * @author      Md. Shahnawaz Ahmed <shahnawaz.ahmed@bkash.com>
 * @copyright   Copyright 2022 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW\Admin\Module;

use bKash\PGW\Admin\AdminUtility;
use bKash\PGW\ApiComm;
use bKash\PGW\Models\Agreement;
use bKash\PGW\Utils;

class AgreementModule {
	/**
	 * @return void
	 */
	public static function agreementList() {
		self::processCancelAgreementIfRequested();

		AdminUtility::loadTable(
			'Agreements with bKash',
			'bkash_agreement_mapping',
			array(
				'ID'              => 'ID',
				'PHONE'           => 'phone',
				'USERID'          => 'user_id',
				'AGREEMENT TOKEN' => 'agreement_token',
				'DATETIME'        => 'datetime',
			),
			array(
				'phone'   => 'Phone',
				'user_id' => 'User ID',
			),
			array(
				array(
					'title'   => 'Cancel Agreement',
					'page'    => 'agreements',
					'action'  => 'cancel',
					'confirm' => true,
				),
			)
		);
	}

	/**
	 * @return void
	 */
	private static function processCancelAgreementIfRequested() {
		$type   = 'warning';
		$action = Utils::safeGetValue( 'action' );

		if ( $action === 'cancel' ) {
			$id = Utils::safeGetValue( 'id' ) ?? '';
			if ( $id ) {
				$agreementObj = new Agreement();
				$agreement    = $agreementObj->getAgreement( '', '', $id );
				if ( $agreement ) {
					$comm            = new ApiComm();
					$cancelAgreement = $comm->agreementCancel( $agreement->getAgreementID() );

					if ( isset( $cancelAgreement['status_code'] ) && $cancelAgreement['status_code'] === 200 ) {
						$response = array();
						if ( isset( $cancelAgreement['response'] ) && is_string( $cancelAgreement['response'] ) ) {
							$response = json_decode( $cancelAgreement['response'], true );
						}
						if ( isset( $response['agreementStatus'] ) && $response['agreementStatus'] === 'Cancelled' ) {
							// Cancelled

							$deleteAgreement = $agreementObj->delete( '', $id );
							if ( $deleteAgreement ) {
								$notice = 'Agreement Deleted!';
								$type   = 'success';
							} else {
								$notice = 'Agreement cancelled but could not delete from db';
							}
						} else {
							$notice = 'Agreement status was not present. ' . wp_json_encode( $response );
						}
					} else {
						$notice = ' Server response was not ok. ' . wp_json_encode( $cancelAgreement );
					}
				} else {
					$notice = 'No agreement found related with this ID';
				}
			} else {
				$notice = 'ID was not present to cancel';
			}

			AdminUtility::addFlashNotice( $notice, $type );
			AdminUtility::redirectToPage( wp_get_referer() );
		}
	}
}

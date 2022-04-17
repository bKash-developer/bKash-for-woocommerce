<?php

namespace bKash\PGW\Admin\Module;

use bKash\PGW\Admin\AdminUtility;
use bKash\PGW\ApiComm;
use bKash\PGW\Models\Agreement;

class AgreementModule {
	public static function agreement_list() {
		self::process_cancel_agreement_if_requested();

		AdminUtility::loadTable( "All bKash Transaction", "bkash_agreement_mapping",
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

	private static function process_cancel_agreement_if_requested() {
		$type   = "warning";
		$action = sanitize_text_field( $_REQUEST['action'] ?? '' );

		if ( $action === 'cancel' ) {
			$id = sanitize_text_field( $_REQUEST['id'] ?? null );
			if ( $id ) {
				$agreementObj = new Agreement();
				$agreement    = $agreementObj->getAgreement( '', '', $id );
				if ( $agreement ) {
					$comm            = new ApiComm();
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

			AdminUtility::add_flash_notice( $notice, $type );
			AdminUtility::redirect_to_page();
		}


	}
}
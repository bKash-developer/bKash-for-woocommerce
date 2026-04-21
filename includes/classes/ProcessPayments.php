<?php
/**
 * Process Payment
 *
 * @category    Payment
 * @package     bkash-for-woocommerce
 * @author      bKash Developer <developer@bkash.com>
 * @copyright   Copyright 2023 bKash Limited. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://bkash.com
 */

namespace bKash\PGW;

use bKash\PGW\Models\Agreement;
use bKash\PGW\Models\Transaction;
use bKash\PGW\Log;

class ProcessPayments {
	public $integration_type;
	private $bKashObj;

	public function __construct( string $integration_type ) {
		$this->integration_type = $integration_type;
		$this->bKashObj         = new ApiComm();
	}

	/**
	 * Initiate payment process by creating a payment with bKash and redirecting the user to bKash for approval.
	 * Handles both tokenized and checkout-url flows based on the integration type and agreement status.
	 * 
	 * @param string           $order_id
	 * @param string           $intent
	 * @param string           $callbackURL
	 * @param Transaction|null $trx
	 *
	 * @return array|null
	 * */
	final public function createPayment(
		string $order_id,
		string $intent = 'sale',
		string $callbackURL = '',
		$transaction = null,
		string $agreementCallbackURL = '',
		string $useAgreementId = ''
	): array {
		// To receive order id and total
		$order    = wc_get_order( $order_id );
		$amount   = $order->get_total();
		$currency = get_woocommerce_currency();

		// To receive user id and order details
		$merchantCustomerId = $order->get_user_id();
		$merchantOrderId    = $order->get_order_number();

		// Check if already has agreement
		$storedAgreementID = '';
		$mode              = null;

		if ( ! empty( $useAgreementId ) ) {
			// Agreement ID passed directly (e.g. after agreement callback)
			$storedAgreementID = $useAgreementId;
		} elseif ( $this->integration_type === 'tokenized' || $this->integration_type === 'tokenized-both' ) {
			$isAgreement = Utils::hasPostField( 'agreement' );
			if ( ! $isAgreement ) {
				$isAgreement = Utils::hasGetField( 'agreement' );
			}

			$agreement_id = trim( html_entity_decode( Utils::safePostValue( 'agreement_id' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( ! $agreement_id ) {
				$agreement_id = trim( html_entity_decode( Utils::safeGetValue( 'agreement_id' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}

			if ( $this->integration_type === 'tokenized' ) 
			{
				// ── tokenized (With Agreement) ──
				// Tokenized requires logged-in user. Agreement is mandatory.
				if ( empty( $merchantCustomerId ) ) {
					wc_add_notice( 'Please login to proceed with bKash payment', 'error' );
					return array( 'result' => 'failure' );
				}

				if ( $agreement_id === 'new' ) {
					// Customer explicitly wants to add a new number → create agreement
					$agreementObj = new Agreement();
					$existingAgreement = $agreementObj->getAgreement( '', $merchantCustomerId );
					if ( $existingAgreement ) {
						$storedAgreementID = $existingAgreement->getAgreementID();
					} else {
						return $this->initiateAgreementCreation( $order_id, $agreementCallbackURL, $intent );
					}
				} elseif ( $agreement_id && $agreement_id !== 'no' ) {
					// Customer selected an existing agreement
					$storedAgreementID = $agreement_id;
				} else {
					// No agreement_id provided → look up stored agreement
					$agreementObj = new Agreement();
					$agreement    = $agreementObj->getAgreement( '', $merchantCustomerId );
					$storedAgreementID = $agreement ? $agreement->getAgreementID() : '';

					// If no stored agreement resolved, initiate agreement creation
					// Callback URL → same callback endpoint that triggers handleAgreementCallback
					if ( empty( $storedAgreementID ) ) {
						return $this->initiateAgreementCreation( $order_id, $agreementCallbackURL, $intent );
					}
				}
			}
			elseif ( $this->integration_type === 'tokenized-both' ) 
			{
				// ── tokenized-both (With or Without Agreement) ──
				// Logged-in user with agreement → tokenized flow.
				// Guest user without agreement → checkout-url flow.
				if ( ! empty( $merchantCustomerId ) ) {
					// Logged-in user
					if ( $agreement_id === 'new' ) {
						// User chose to create a new agreement → initiate agreement creation
						$agreementObj = new Agreement();
						$existingAgreement = $agreementObj->getAgreement( '', $merchantCustomerId );
						if ( $existingAgreement ) {
							$storedAgreementID = $existingAgreement->getAgreementID();
						} else {
							return $this->initiateAgreementCreation( $order_id, $agreementCallbackURL, $intent );
						}
					} elseif ( $agreement_id === 'no' ) {
						// User explicitly chose "no agreement" → checkout-url flow (0011)
						$mode = '0011';
					} elseif ( $agreement_id ) {
						$storedAgreementID = $agreement_id;
					} else {
						// No selection → look up stored agreement, fallback to 0011
						$agreementObj = new Agreement();
						$agreement    = $agreementObj->getAgreement( '', $merchantCustomerId );
						$storedAgreementID = $agreement ? $agreement->getAgreementID() : '';
					}
				}
			}
		}

		// Validate storedAgreementID belongs to the logged-in user
		if ( ! empty( $storedAgreementID ) && ! empty( $merchantCustomerId ) ) {
			$agreementCheck = new Agreement();
			$foundAgreement = $agreementCheck->getAgreement( $storedAgreementID );
			if ( ! $foundAgreement || (int) $foundAgreement->getUserID() !== (int) $merchantCustomerId ) {
				wc_add_notice( 'Selected agreement does not belong to your account.', 'error' );
				return array( 'result' => 'failure', 'message' => 'Agreement validation failed.' );
			}
		}

			if ( ! $mode ) {
			$mode = Operations::getTokenizedPaymentMode(
				$this->integration_type,
				! empty( $useAgreementId ) ? false : ( $isAgreement ?? false ),
				$storedAgreementID
			);
		}

		$invoiceNumber = uniqid( 'bfw_', false ) . '_' . $merchantOrderId;
		$payment_payload = array(
			'payerReference'        => uniqid( 'bKash_', false ) . '_' . $merchantCustomerId,
			'callbackURL'           => $callbackURL,
			'amount'                => $amount,
			'currency'              => $currency,
			'intent'                => $intent,
			'merchantInvoiceNumber' => $invoiceNumber,
		);

		// Add agreementID only for tokenized payment with agreement
		if ( ! empty( $storedAgreementID ) ) {
			$payment_payload['agreementId'] = $storedAgreementID;

			// Store agreementId in order meta for later use (capture/void)
			$order->update_meta_data( '_bkash_agreement_id', $storedAgreementID );
			if ( method_exists( $order, 'save' ) ) {
				$order->save();
			}
		}

		// Store transaction in database if not created already during agreement creation flow
		if ( empty( $transaction ) ) {
			$transaction = new Transaction();
			$transaction->setOrderID( $order_id );
			$transaction->setAmount( $amount );
			$transaction->setIntegrationType( $this->integration_type );
			$transaction->setIntent( $intent );
			$transaction->setCurrency( $currency );
			$transaction->setMode( $mode );
			$transaction->setStatus( 'Created' );
			$transaction->setInvoiceID( $invoiceNumber );

			if ( ! $transaction->save() ) {
				$message = $transaction->errorMessage ?? 'Transaction save failed.';
				wc_add_notice( $message, 'error' );
				return array( 'result' => 'failure', 'message' => $message );
			}
		}

		// Append invoiceID to callback URL
		$payment_payload['callbackURL'] .= '&invoiceId=' . $transaction->getInvoiceID();

		$createResponse = $this->bKashObj->paymentCreate( $payment_payload, $mode );
		if ( ! isset( $createResponse['status_code'] ) || $createResponse['status_code'] !== 200 ) {
			$message = $this->processResponse( 'Cannot process this payment right now, error in communication' );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'failure', 'message' => $message );
		}

		$response = array();
		if ( isset( $createResponse['response'] ) && is_string( $createResponse['response'] ) ) {
			$response = json_decode( $createResponse['response'], true );
		}

		if ( empty( $response ) ) {
			$message = $this->processResponse( 'Cannot process this payment right now, not a valid response' );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'failure', 'message' => $message );
		}

		// Check for error responses
		if ( isset( $response['statusMessage'] ) && $response['statusMessage'] !== 'Successful' ) {
			$message = $this->processResponse( $response['statusMessage'] );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'failure', 'message' => $message );
		}

		if ( isset( $response['errorCode'] ) ) {
			$message = $this->processResponse( $response['errorMessage'] ?? '' );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'failure', 'message' => $message );
		}

		// Successful response — must have paymentID and bkashURL
		if ( empty( $response['paymentId'] ) || empty( $response['bkashURL'] ) ) {
			$message = $this->processResponse( 'Cannot process this payment right now, missing payment data' );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'failure', 'message' => $message );
		}

		// Update transaction with paymentID and empty the cart
		$updated = $transaction->update( array( 'payment_id' => $response['paymentId'] ) );
		if ( ! $updated ) {
			$message = $this->processResponse( 'Cannot process this payment right now, payment ID issue' );
			wc_add_notice( $message, 'error' );
			return array( 'result' => 'failure', 'message' => $message );
		}

		WC()->cart->empty_cart();
		return array(
			'result'   => 'success',
			'redirect' => $response['bkashURL'],
		);
	}

	/**
	 * Initiate payment execution after bKash redirects back to callback URL.
	 * 
	 * @param string $orderPageURL
	 * @param string $callbackURL
	 *
	 * @return void
	 */
	final public function executePayment( string $orderPageURL, string $callbackURL = '', string $agreement_id = '' ) {
		$message = '';

		if ( Utils::hasGetField( 'orderId' ) ) {
			$order_id   = Utils::safeGetValue( 'orderId' );
			$payment_id = Utils::safeGetValue( 'paymentID' );
			$invoice_id = Utils::safeGetValue( 'invoiceId' );
			$status     = Utils::safeGetValue( 'status' );
		} else {
			$order_id   = Utils::safePostValue( 'orderId' );
			$payment_id = Utils::safePostValue( 'paymentID' );
			$invoice_id = Utils::safePostValue( 'invoiceId' );
			$status     = Utils::safePostValue( 'status' );
		}

		// To receive order id
		$order       = wc_get_order( $order_id );
		$trx         = new Transaction();
		$transaction = $trx->getTransaction( $invoice_id );

		if ( $status === 'success' ) {
			if ( $transaction && $transaction->getPaymentID() === $payment_id ) {
				$mode = $transaction->getMode();
				$transaction->update([
					'status' => 'CALLBACK_REACHED',
				]);

				// Call executePayment API to finalize the payment.
				$response = $this->bKashObj->executePayment( $transaction->getPaymentID(), $mode, $agreement_id );
				if ( isset( $response['status_code'] ) && $response['status_code'] === 200 ) {
					// GET TRXID FROM BKASH RESPONSE
					$paymentResp = Operations::processResponse( $response, 'trxID' );

					if ( is_array( $paymentResp ) ) {
						// PAYMENT IS DONE SUCCESSFULLY, NOW START REST OF THE PROCESS TO UPDATE WC ORDER
						// Updating transaction status
						$status  = $paymentResp['transactionStatus'] ?? 'NO_STATUS_EXECUTE';
						$updated = $transaction->update([
							'status' => $status,
							'trx_id' => $paymentResp['trxID'] ?? '',
						]);

						if ( $updated && isset( $paymentResp['trxID'] ) && ! empty( $paymentResp['trxID'] ) ) {
							// Payment complete.
							if ( $paymentResp['transactionStatus'] === 'Authorized' ) {
								$order->update_status( 'on-hold' );
							} elseif ( $paymentResp['transactionStatus'] === 'Completed' ) {
								$order->payment_complete();
							} else {
								$order->update_status( 'pending' );
							}

							// Store the transaction ID using the WC CRUD methods.
							if ( method_exists( $order, 'set_transaction_id' ) ) {
								$order->set_transaction_id( $paymentResp['trxID'] );
							} else {
								$order->update_meta_data( '_transaction_id', $paymentResp['trxID'] );
							}

							// Store agreementId from response if present (backup for capture/void)
							if ( ! empty( $paymentResp['agreementId'] ) ) {
								$order->update_meta_data( '_bkash_agreement_id', $paymentResp['agreementId'] );
							}

							// Record completed time if provided by bKash (fallback to updateTime/createTime).
							$completedRaw = $paymentResp['completedTime'] ?? $paymentResp['updateTime'] ?? $paymentResp['createTime'] ?? '';
							$completedDatetime = '';
							if ( ! empty( $completedRaw ) ) {
								$clean = preg_replace( '/:(\d{3})/', '', $completedRaw );
								$clean = str_replace( 'GMT', '', $clean );
								$ts    = strtotime( $clean );
								if ( $ts !== false ) {
									$completedDatetime = date( 'Y-m-d H:i:s', $ts );
								}
							}

							// Add order note for approval and completed time if available.
							$note = sprintf( 'bKash PGW payment approved (ID: %s)', $paymentResp['trxID'] );
							if ( $completedDatetime ) {
								$note .= ' — Completed at: ' . $completedDatetime;
								$order->update_meta_data( '_bkash_completed_time', $completedDatetime );
							}
							$order->add_order_note( $note );

							if ( isset( $this->log ) && $this->log ) {
								if ( function_exists( 'wc_get_logger' ) ) {
									$logger = wc_get_logger();
									$logger->info( 'bKash PGW payment approved (ID: ' . ( $paymentResp['trxID'] ?? '' ) . ')', array( 'source' => BKASH_FW_PLUGIN_SLUG ) );
								}
							}

							// Reduce stock levels.
							wc_reduce_stock_levels( $order_id );

							if ( isset( $this->log ) && $this->log ) {
								$this->log->add( $this->id, 'Stocked reduced.' );
							}

							// Persist order changes (transaction id, meta and notes) before redirect.
							if ( method_exists( $order, 'save' ) ) {
								$order->save();
							}
							wp_safe_redirect( $orderPageURL );
							die();
						}

						if ( $updated && isset( $paymentResp['paymentID'] ) && ! empty( $paymentResp['paymentID'] ) ) {
							$msg = 'Transaction was not successful, last transaction status: ' . $paymentResp['transactionStatus'] ?? 'NO_STATUS_EXECUTE';

							wc_add_notice( $msg, 'error' );
							wp_safe_redirect( wc_get_checkout_url() );
							die();
						}
						$message = 'Could not get transaction status';
					} else {
						$message = is_string( $paymentResp ) ? $paymentResp : '';
					}

					$transaction->update([ 'status' => 'Failed' ]);
					$order->add_order_note( 'bKash Payment: ' . $message );
					$message = $this->processResponse( $message );
				} else {
					$message = $this->processResponse( 'Communication issue with payment gateway' );
				}

				wc_add_notice( $message, 'error' );
				wp_safe_redirect( wc_get_checkout_url() );
				die();
			}
			// payment ID not matching or transaction not found. or already processed
			$message = $this->processResponse( 'Invalid payment ID or Invoice ID' );
		} else {
			Log::debug( 'Payment failed or cancelled. Status: ' . $status );
			// transaction failed/cancelled.
			$status = str_replace( array( 'cancel', 'failure' ), array( 'Cancelled', 'Failed' ), $status );
			if ( $transaction->getStatus() !== 'Completed' ) {
				$transaction->update([ 'status' => esc_html( $status ) ]);
				$order->add_order_note( 'bKash Payment is not successful. Status => ' . esc_html( $status ) );
			} else {
				$order->add_order_note(
					'bKash Payment is already in Completed state. Tried to change Status to => '
					. esc_html( $status )
				);
			}

			$message = $this->processResponse( 'Transaction is ' . $status );
		}

		$order->add_order_note( 'bKash PGW payment declined (' . $message . ')' );

		wc_add_notice( $message, 'error' );
		wp_safe_redirect( wc_get_cart_url() );

		// Return message to customer.
		die();
	}

	final public function processResponse( string $message ): string {
		return "<h3 style='color:#fff;font-weight:bold;margin:0;font-size:20px;line-height: 14px;'>Payment Failed</h3>" . $message;
	}

	/**
	 * Initiate Agreement Creation
	 *
	 * Calls the bKash agreementCreate API and returns the redirect URL or failure array.
	 * Reusable for both tokenized and tokenized-both integration types
	 *
	 * @param string $order_id      WooCommerce order ID
	 * @param string $callbackURL   The callback URL (should be the checkout page)
	 * @param string $intent        Payment intent (sale/authorization)
	 *
	 */
	final public function initiateAgreementCreation( string $order_id, string $callbackURL, string $intent = 'sale' ): array {
		$order              = wc_get_order( $order_id );
		$amount             = $order->get_total();
		$currency           = get_woocommerce_currency();
		$merchantCustomerId = $order->get_user_id();
		$merchantOrderId    = $order->get_order_number();

		$agreementPayload = array(
			'payerReference' => uniqid( 'bKash_', false ) . '_' . $merchantCustomerId,
			'callbackURL'    => $callbackURL,
		);

		$agreementResponse = $this->bKashObj->agreementCreate( $agreementPayload );
		if (
			isset( $agreementResponse['status_code'] ) && $agreementResponse['status_code'] === 200 &&
			isset( $agreementResponse['response'] ) && is_string( $agreementResponse['response'] )
		) {
			$agreementData = json_decode( $agreementResponse['response'], true );
			if ( isset( $agreementData['bkashURL'] ) && ! empty( $agreementData['bkashURL'] ) ) {
				// Store a pending transaction to track this agreement flow
				$trx = new Transaction();
				$trx->setOrderID( $order_id );
				$trx->setAmount( $amount );
				$trx->setIntegrationType( $this->integration_type );
				$trx->setIntent( $intent );
				$trx->setCurrency( $currency );
				$trx->setMode( '0000' );
				$trx->setStatus( 'AgreementInitiated' );
				$trx->setInvoiceID( uniqid( 'bfw_', false ) . '_' . $merchantOrderId );

				if ( isset( $agreementData['paymentID'] ) ) {
					$trx->setPaymentID( $agreementData['paymentID'] );
				}

				$trxSaved = $trx->save();
				// Redirect user to bKash for agreement approval
				return array(
					'result'   => 'success',
					'redirect' => $agreementData['bkashURL'],
				);
			}

			// bkashURL missing
			$errorMsg = $agreementData['statusMessage'] ?? 'Agreement creation failed, no redirect URL.';
			wc_add_notice( $errorMsg, 'error' );
			return array( 'result' => 'failure', 'message' => $errorMsg );
		}

		// API call failed
		wc_add_notice( 'Error communicating with bKash for agreement creation.', 'error' );
		return array( 'result' => 'failure', 'message' => 'Agreement creation failed.' );
	}

	/**
	 * Handle Agreement Callback
	 *
	 * Called when bKash redirects the user back after agreement approval.
	 * Executes the agreement, stores it in the database, and redirects to checkout.
	 *
	 * @param string $payment_id   The paymentID from bKash callback
	 * @param int    $user_id      The WordPress user ID
	 *
	 * @return array ['success' => bool, 'agreementID' => string, 'message' => string]
	 */
	final public function handleAgreementCallback( string $agreement_id, int $user_id ): array {
		$response = $this->bKashObj->agreementExecute( $agreement_id );
		if ( isset( $response['status_code'] ) && $response['status_code'] === 200 ) {
			$agreementResp = Operations::processResponse( $response, 'agreementId' );

			if ( is_array( $agreementResp ) ) {
				if ( ( $agreementResp['agreementStatus'] ?? '' ) === 'Completed' ) {
					$agreementObj = new Agreement();
					$agreementObj->setAgreementID( $agreementResp['agreementID'] ?? '' );
					$agreementObj->setMobileNo( $agreementResp['payerAccount'] ?? '' );
					$agreementObj->setDateTime( $agreementResp['agreementExecuteTime'] ?? '' );
					$agreementObj->setUserID( $user_id );
					$stored = $agreementObj->save();

					if ( $stored ) {
						return array(
							'success'     => true,
							'agreementID' => $agreementResp['agreementID'],
							'message'     => 'Agreement created successfully.',
							'data'        => $agreementResp,
						);
					}

					return array(
						'success' => false,
						'message' => 'Could not store agreement. ' . $agreementObj->errorMessage,
					);
				}

				return array(
					'success' => false,
					'message' => 'Agreement is not completed. Status: ' . ( $agreementResp['agreementStatus'] ?? 'unknown' ),
					'data'    => $agreementResp,				
				);
			}

			$errorMsg = is_string( $agreementResp ) ? $agreementResp : 'Invalid agreement response.';
			return array(
				'success' => false,
				'message' => $errorMsg,
			);
		}

		return array(
			'success' => false,
			'message' => 'Communication issue with payment gateway during agreement execution.',
		);
	}

	final public function cancelPayment( string $order_id ): array {
		// global $woocommerce;
		// To receive order id
		$order = wc_get_order( $order_id );
		if ( $order ) {
			if ( $order->get_status() === 'pending' ) {
				$trx         = new Transaction();
				$transaction = $trx->getTransactionByOrderId( $order_id );
				if ( $transaction ) {
					$transaction->update(
						array(
							'status' => 'Cancelled',
						)
					);
					$order->add_order_note( 'bKash Payment has been cancelled, either failed or customer cancelled' );
					$order->update_status( 'cancelled', 'Payment has been cancelled!' );

					return array(
						'result'   => 'success',
						'redirect' => null,
						'response' => 'Order cancelled!',
					);
				}

				return array(
					'result'  => 'failure',
					'message' => 'Transaction not found in bKash database',
				);
			}

			return array(
				'result'  => 'failure',
				'message' => 'Order is not in pending status to cancel the payment',
			);
		}

		return array(
			'result'  => 'failure',
			'message' => 'Order not found',
		);
	}
}

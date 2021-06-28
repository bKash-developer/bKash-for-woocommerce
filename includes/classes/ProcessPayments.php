<?php

namespace bKash\PGW;

use bKash\PGW\Models\Agreement;
use bKash\PGW\Models\Transactions;

class ProcessPayments {
	public $integration_type;
	private $bKashObj;


	public function __construct( $integration_type ) {
		$this->integration_type = $integration_type;
		$this->bKashObj         = new ApiComm();
	}

	public function executePayment( string $orderPageURL, string $callbackURL = "" ) {
		$message = "";

		$order_id    = sanitize_text_field( $_REQUEST['orderId'] );
		$payment_id  = sanitize_text_field( $_REQUEST['paymentID'] );
		$invoice_id  = sanitize_text_field( $_REQUEST['invoiceID'] );
		$status      = sanitize_text_field( $_REQUEST['status'] );
		$api_version = sanitize_text_field( $_REQUEST['apiVersion'] );

		global $woocommerce;
		//To receive order id
		$order = wc_get_order( $order_id );

		if ( $status === 'success' ) {
			$trx         = new Transactions();
			$transaction = $trx->getTransaction( $invoice_id );
			if ( $transaction && $transaction->getPaymentID() === $payment_id ) {

				$transaction->update( [
					'status' => 'CALLBACK_REACHED',
				] );

				// EXECUTE OPERATION
				$response = $this->bKashObj->executePayment( $transaction->getPaymentID() );

				if ( isset( $response['status_code'] ) && $response['status_code'] === 200 ) {

					$mode = $transaction->getMode();

					// 0011 - Checkout URL, 0000 - Create Agreement, 0001 - Create Payment
					if ( $mode === '0000' ) {


						$agreementResp = Operations::processResponse( $response, "agreementID" );
						if ( is_array( $agreementResp ) ) {

							if ( $agreementResp['agreementStatus'] === 'Completed' ) {
								$agreementObj = new Agreement();
								$agreementObj->setAgreementID( $agreementResp['agreementID'] ?? '' );
								$agreementObj->setMobileNo( $agreementResp['customerMsisdn'] ?? '' );
								$agreementObj->setDateTime( $agreementResp['agreementExecuteTime'] ?? '' );
								$agreementObj->setUserID( $order->get_user_id() );
								$stored = $agreementObj->save();

								if ( $stored ) {

									$transaction->update( [ 'mode' => '0001' ], [ 'payment_id' => $transaction->getPaymentID() ] );
									add_post_meta( $order->get_id(), '_bkmode', '0001', true );

									$createResp = $this->createPayment( $transaction->getOrderID(), $transaction->getIntent(), $callbackURL );

									if ( isset( $createResp['redirect'] ) ) {
										wp_redirect( $createResp['redirect'] );
										die();
									}

									echo json_encode( $createResp );
								} else {
									$message = "Agreement cannot be done right now, cannot store in db, try again. " . $agreementObj->errorMessage;
									$message = $this->processResponse( $message );
								}

							} else {
								$message = $this->processResponse( "Agreement cannot be done right now, try again" );
							}

						} else {
							$message = is_string( $agreementResp ) ? $agreementResp : '';
							$message = $this->processResponse( $message );
						}

					} else {
						// GET TRXID FROM BKASH RESPONSE
						$paymentResp = Operations::processResponse( $response, "trxID" );

						if ( is_array( $paymentResp ) ) {

							// PAYMENT IS DONE SUCCESSFULLY, NOW START REST OF THE PROCESS TO UPDATE WC ORDER

							// Updating transaction status
							$updated = $transaction->update( [
								'status' => $paymentResp['transactionStatus'] ?? 'NO_STATUS_EXECUTE',
								'trx_id' => $paymentResp['trxID'] ?? ''
							] );

							if ( $updated && isset( $paymentResp['trxID'] ) && ! empty( $paymentResp['trxID'] ) ) {

								// Payment complete.
								if ( $paymentResp['transactionStatus'] === 'Authorized' ) {
									$order->update_status( 'on-hold' );
								} elseif ( $paymentResp['transactionStatus'] === 'Completed' ) {
									$order->payment_complete();
								} else {
									$order->update_status( 'pending' );
								}

								// Store the transaction ID for WC 2.2 or later.
								add_post_meta( $order->get_id(), '_transaction_id', $paymentResp['trxID'], true );

								// Add order note.
								$order->add_order_note( sprintf( __( 'bKash PGW payment approved (ID: %s)', 'woocommerce-payment-gateway-bkash' ), $paymentResp['trxID'] ) );

								if ( isset( $this->log ) && $this->log ) {
									$this->log->add( $this->id, 'bKash PGW payment approved (ID: ' . $response['trxID'] . ')' );
								}

								// Reduce stock levels.
								wc_reduce_stock_levels( $order_id );

								if ( isset( $this->log ) && $this->log ) {
									$this->log->add( $this->id, 'Stocked reduced.' );
								}

								// Remove items from cart.
								WC()->cart->empty_cart();

								if ( isset( $this->log ) && $this->log ) {
									$this->log->add( $this->id, 'Cart emptied.' );
								}

								// Return thank you page redirect.
								if ( $this->integration_type === 'checkout' ) {
									echo json_encode( array(
										'result'   => 'success',
										'redirect' => $orderPageURL
									) );
									die();
								}
								wp_redirect( $orderPageURL );
								die();
							}
							$message = $this->processResponse( "Could not update transaction status" );
						} else {
							$message = is_string( $paymentResp ) ? $paymentResp : '';
							$message = $this->processResponse( $message );
						}
					}
				} else {
					$message = $this->processResponse( "Communication issue with payment gateway" );
				}

				if ( $this->integration_type === 'checkout' ) {
					echo json_encode( array(
						'result'  => 'failure',
						'message' => $message
					) );
				} else {
					wc_add_notice( $message, 'error' );
					wp_redirect( wc_get_checkout_url() );
				}

				die();
			}
			// payment ID not matching or transaction not found. or already processed
			$message = $this->processResponse( "Invalid payment ID or Invoice ID" );

		} else {
			// transaction failed/cancelled.
			$message = $this->processResponse( "Transaction is " . $status );
		}

		$order->add_order_note( __( 'bKash PGW payment declined (' . $message . ')', 'woocommerce-payment-gateway-bkash' ) );

		if ( $this->integration_type === 'checkout' ) {
			echo json_encode( array(
				'result'  => 'failure',
				'message' => $message
			) );
		} else {
			wc_add_notice( __( $message, 'woocommerce-payment-gateway-bkash' ), 'error' );
			wp_redirect( $woocommerce->cart->get_cart_url() );
		}

		// Return message to customer.
		die();
	}

	/**
	 * @param string $order_id
	 * @param string $intent
	 * @param string $callbackURL
	 *
	 * @return array|null
	 * */
	public function createPayment( string $order_id, string $intent = 'sale', string $callbackURL = "" ) {
		global $woocommerce;
		$message      = '';
		$isAgreement  = isset( $_REQUEST['agreement'] );
		$agreement_id = $_REQUEST['agreement_id'] ?? null;

		//To receive order id and total
		$order    = wc_get_order( $order_id );
		$amount   = $order->get_total();
		$currency = get_woocommerce_currency();

		//To receive user id and order details
		$isLoggedIn         = is_user_logged_in();
		$merchantCustomerId = $order->get_user_id();
		$merchantOrderId    = $order->get_order_number();

		if ( $this->integration_type === 'checkout' ) {
			$payment_payload = array(
				'amount'                  => $amount,
				'currency'                => $currency,
				'intent'                  => $intent,
				'merchantInvoiceNumber'   => uniqid( "bfw_", false ) . '_' . $merchantOrderId,
				'merchantAssociationInfo' => '',
			);
		} else {

			// Check if already has agreement
			$storedAgreementID = "";
			$mode              = null;

			if ( ! empty( $order->get_user_id() ) ) {

				if ( $agreement_id === 'new' || $agreement_id === 'no' ) {
					// If customer wants to add new number then mode 0000, or without agreement 0011
					$mode = $agreement_id === 'new' ? '0000' : '0011';
				} else if ( $agreement_id ) {
					// Customer selected an agreement to pay
					$storedAgreementID = $agreement_id;
				} else {
					// Proceed with stored latest agreement id
					$agreementObj = new Agreement();
					$agreement    = $agreementObj->getAgreement( "", $order->get_user_id() );
					if ( $agreement ) {
						$storedAgreementID = $agreement->getAgreementID();
					}
				}
			}

			if ( ! $mode ) {
				$mode = Operations::getTokenizedPaymentMode( $this->integration_type, $order_id, $isAgreement, $storedAgreementID );
			}

			$payment_payload = array(
				'mode'                  => $mode,
				'payerReference'        => uniqid('bKash_', false) . '_' . $merchantCustomerId,
				'callbackURL'           => $callbackURL,
				'agreementID'           => $storedAgreementID ?? '',
				'amount'                => $amount,
				'currency'              => $currency,
				'intent'                => $intent,
				'merchantInvoiceNumber' => uniqid( "bfw_", false ) . '_' . $merchantOrderId
			);
		}

		/* Store Transaction in Database */
		$trx = new Transactions();
		$trx->setOrderID( $order_id );
		$trx->setAmount( $amount );
		$trx->setIntegrationType( $this->integration_type );
		$trx->setIntent( $intent );
		$trx->setCurrency( $currency );
		$trx->setMode( $mode ?? '' );
		$trx->setStatus("Created");

		if ( isset( $payment_payload['merchantInvoiceNumber'] ) ) {
			$trx->setInvoiceID( $payment_payload['merchantInvoiceNumber'] );
		}

		$trxSaved = $trx->save();

		if ( $trxSaved ) {
			// pass invoice number in callback string
			if ( isset( $payment_payload['callbackURL'] ) ) {
				$payment_payload['callbackURL'] .= '&invoiceID=' . $trxSaved->getInvoiceID();
			}

			$createResponse = $this->bKashObj->paymentCreate( $payment_payload );

			if ( isset( $createResponse['status_code'] ) && $createResponse['status_code'] === 200 ) {
				$response = isset( $createResponse['response'] ) && is_string( $createResponse['response'] ) ? json_decode( $createResponse['response'], true ) : [];

				if ( $response ) {
					// If any error for tokenized
					if ( isset( $response['statusMessage'] ) && $response['statusMessage'] !== 'Successful' ) {
						$message = $response['statusMessage'];
					} // If any error for checkout
					else if ( isset( $response['errorCode'] ) ) {
						$message = $response['errorMessage'] ?? '';
					} else if ( isset( $response['paymentID'] ) && ! empty( $response['paymentID'] ) ) {

						$updated = $trxSaved->update( [ 'payment_id' => $response['paymentID'] ] );
						if ( $updated ) {

							if ( $this->integration_type === 'checkout' ) {
								return array(
									'result'   => 'success',
									'redirect' => null,
									'order'    => array(
										'orderId'   => $order_id,
										'paymentID' => $response['paymentID'] ?? '',
										'invoiceID' => $trx->getInvoiceID(),
										'amount'    => $amount,
									),
									'response' => $response
								);
							} else {
								return array(
									'result'   => 'success',
									'redirect' => $response['bkashURL']
								);
							}
						} else {
							$message = $this->processResponse( "Cannot process this payment right now, payment ID issue" );
						}
					} else {
						$message = $this->processResponse( "Cannot process this payment right now, unknown error message" );
					}
				} else {
					$message = $this->processResponse( "Cannot process this payment right now, not a valid response" );
				}
			} else {
				$message = $this->processResponse( "Cannot process this payment right now, error in communication" );
			}
		} else {
			$message = $trx->errorMessage;
		}

		wc_add_notice( $message, 'error' );

		return [];
	}

	public function processResponse( $message, $type = 'error' ) {
		return "<h3 style='color: #fff;font-weight: bold;margin: 0;font-size: 20px;line-height: 14px;'>Payment Failed</h3>" . $message;
	}


}
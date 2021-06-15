<?php

namespace bKash\PGW;

class Operations
{
    public static function CheckoutScriptURL(bool $sandbox = false, string $version = "1.2.0-beta"): string
    {
        $version = str_replace("v", "", $version);
        return "https://scripts." . ($sandbox ? 'sandbox' : 'pay') .
               ".bka.sh/versions/$version/checkout/bKash-checkout".($sandbox ? '-sandbox' : '').".js";
    }

    public static function getTokenizedPaymentMode($integration_type, $order_id = "", $isAgreement = false, $agreementID = ""): string
    {
        // agreement = 0000, paymentWithAgreementID = 0001, paymentWithoutAgreementID = 0011
        $mode = "";
        switch ($integration_type) {
            case 'checkout-url':
                $mode = '0011';
                break;
            case 'tokenized':
                $mode = !empty($agreementID) ? '0001' : '0000';
                break;
            case 'tokenized-both':
                // agreement checking required
                $mode = $isAgreement ? '0000' : '0001';
                break;
            default:
                break;
        }
        return $mode;
    }

    public static function processResponse($response, $expectation = "")
    {
        $resp = "";

        if (isset($response['response'])) {
            $response = isset($response['response']) && is_string($response['response']) ?
                json_decode($response['response'], true) : [];


            // If any error for tokenized
            if (isset($response['statusMessage']) && $response['statusMessage'] !== 'Successful') {
                $resp = $response['statusMessage'];
            } // If any error for checkout
            else if (isset($response['errorCode'])) {
                $resp = $response['errorMessage'] ?? '';
            } else {
                if (!empty($expectation)) {
                    if(isset($response[$expectation]) && !empty($response[$expectation])) {
                        $resp = $response;
                    }
                    else if (isset($response['paymentID']) && !empty($response['paymentID'])) {
                        $resp = $response;
                    } else {
                        $resp = "expected parameter is not exists in response";
                    }
                } else {
                    $resp = $response;
                }
            }
        }
        return $resp;
    }
}
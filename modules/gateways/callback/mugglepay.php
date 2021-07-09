<?php
require "../../../init.php";

App::load_function("gateway");
App::load_function("invoice");

if (!class_exists('MugglePay')) {
    include("../muglepay/lib/MugglePay.php");
}

$GATEWAY = getGatewayVariables('mugglepay');
if (!$GATEWAY["type"]) {
    exit("Module Not Activated");
}

$mugglepay = new WHMCS\Module\Gateway\MugglePay\MugglePay($GATEWAY['mp_token']);
$inputString = @file_get_contents('php://input', 'r');
$inputStripped = str_replace(array("\r", "\n", "\t", "\v"), '', $inputString);
$response = json_decode($inputStripped, false);

/**
 * Payment Callback
 */
try {
    // Sign Callback Payment
    if (empty($response) || empty($response->merchant_order_id) || empty($response->token)) {
        throw new Exception($mugglepay->create_error_message(
            'Failed to check response order callback',
            $response
        ));
    }

    $str_to_sign = $mugglepay->prepareSignId($response->merchant_order_id);
    $resultVerify = $mugglepay->verify($str_to_sign, $response->token);
    if (!$resultVerify) {
        throw new Exception($mugglepay->create_error_message(
            'Checking IPN response is valid: ' . $mugglepay->convert_invoices_id($response->merchant_order_id),
            $response
        ));
    }

    // Check Order
    if ($mugglepay->order_paid($response)) {
        logTransaction($GATEWAY['paymentmethod'], $response, 'Successful');
        echo $mugglepay->send_header_json(array(
            'status' => 200
        ), 200);
    } else {
        throw new Exception($mugglepay->create_error_message(
            'Failed to Checking IPN response order callback for' . $mugglepay->convert_invoices_id($response->merchant_order_id),
            $response
        ));
    }
} catch (Exception $e) {
    $msg = json_decode($e->getMessage(), false);
    logTransaction($GATEWAY['paymentmethod'], $msg, 'Response Callback');
    echo $mugglepay->send_header_json(array(
        'message' => $msg->message,
        'status' => 500
    ), 200);
} finally {
    exit;
}

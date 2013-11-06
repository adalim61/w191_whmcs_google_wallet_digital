<?php

include '../lib/JWT.php';
include '../../../dbconnect.php';
include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';

function checkCbInvoiceID2($invoice_id, $gateway_id, $param)
{
	$query = mysql_query(sprintf('SELECT id FROM tblinvoices WHERE id=%s', mysql_real_escape_string($invoice_id)));
	$row = mysql_fetch_assoc($query);
	if ($row === null) {
		logTransaction($gateway_id, $param, 'Invoice ID Not Found');
		exit;
	}
}

header('Content-Type: text/plain');

$GATEWAY = getGatewayVariables('google_wallet_digital');
if (!$GATEWAY['type']) {
	die('Module Not Activated');
}

try {
	$payload = JWT::decode($_POST['jwt'], $GATEWAY['seller_secret']);
	# print_r($payload);
	# stdClass Object
	# (
	#     [iss] => Google
	#     [request] => stdClass Object
	#         (
	#             [name] => Piece of Cake
	#             [description] => Virtual chocolate cake to fill your virtual tummy
	#             [price] => 10.50
	#             [currencyCode] => USD
	#             [sellerData] => user_id:1224245,offer_code:3098576987,affiliate:aksdfbovu9j
	#         )
	# 
	#     [response] => stdClass Object
	#         (
	#             [orderId] => 07743248137569471886.1c74e404-7bdb-4186-b304-4a277b28af12
	#         )
	# 
	#     [typ] => google/payments/inapp/item/v1/postback/buy
	#     [aud] => 00628602766055809345
	#     [iat] => 1374487359
	#     [exp] => 1374487379
	# )

	$invoice_id = $payload->request->sellerData;
	$transaction_id = $payload->response->orderId;

	checkCbInvoiceID2($invoice_id, 'google_wallet_digital', json_encode($payload));
	checkCbTransID($transaction_id);

	$amount = floatval($payload->request->price);
	$fee = 0;

	# taken from googlecheckout.php
	if ($GATEWAY['convertto']) {

		$query = select_query('tblinvoices', 'userid,total', array('id' => $invoice_id));
		$row = mysql_fetch_assoc($query);
		$userid = $row['userid'];
		$total = $row['total'];

		$currency = getCurrency($userid);
		$amount = convertCurrency($amount, $GATEWAY['convertto'], $currency['id']);
		$fee = convertCurrency($fee, $GATEWAY['convertto'], $currency['id']);

		if ($total < $amount + 1 && $amount - 1 < $total) {
			$amount = $total;
		}
	}

	addInvoicePayment($invoice_id, $transaction_id, $amount, $fee, 'google_wallet_digital');
	logTransaction('google_wallet_digital', json_encode($payload), 'Successful');

	echo $payload->response->orderId;
	exit;
}
catch (Exception $e) {
	header('HTTP/1.1 400 Bad Request');
	echo 'error: ', $e->getMessage(), PHP_EOL;
	exit;
}

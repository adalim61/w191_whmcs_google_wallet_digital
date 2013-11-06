<?php

require_once dirname(__FILE__).'/lib/JWT.php';

function google_wallet_digital_activate()
{
	defineGatewayField('google_wallet_digital', 'text', 'seller_id', '', 'Seller ID', '30', '');
	defineGatewayField('google_wallet_digital', 'text', 'seller_secret', '', 'Seller Secret', '40', '');
	defineGatewayField('google_wallet_digital', 'yesno', 'sandbox', '', 'Sandbox Mode', '', '');
}

function google_wallet_digital_link($param)
{
	if ($param['sandbox']) {
		$js_buy = 'https://sandbox.google.com/checkout/inapp/lib/buy.js';
	}
	else {
		$js_buy = 'https://wallet.google.com/inapp/lib/buy.js';
	}

	$payload = array(
		'aud' => 'Google',
		'iss' => $param['seller_id'],
		'typ' => 'google/payments/inapp/item/v1',
		'iat' => time(),
		'exp' => time() + 3600,
		'request' => array (
			'name' => 'Invoice Payment',
			'description' => $param['description'],
			'price' => $param['amount'],
			'currencyCode' => $param['currency'],
			'sellerData' => $param['invoiceid'],
		)
	);
	$jwt = JWT::encode($payload, $param['seller_secret']);

	ob_start();
	?>
		<script src="<?php echo $js_buy ?>" type="text/javascript"></script>
		<script type="text/javascript">
		function buy() {
			// buy
			// https://developers.google.com/commerce/wallet/digital/docs/jsreference#buy
			google.payments.inapp.buy({
				parameters: {},
				jwt: <?php echo json_encode($jwt) ?>,
				success: function (action) {
					window.location = <?php echo json_encode(sprintf('%s/viewinvoice.php?id=%s', $param['systemurl'], $param['invoiceid'])) ?>;
					// alert("success");
					// console.log('success', arguments);
				},
				failure: function (action) {
					// failureHandler
					// https://developers.google.com/commerce/wallet/digital/docs/jsreference#failurehandler
					switch (action.response.errorType) {
					case 'PURCHASE_CANCELED': // buyer cancelled purchase or declined payment
						// alert('canceled');
						// break;
					case 'MERCHANT_ERROR': // purchase request contains errors such as a badly formatted JWT
					case 'POSTBACK_ERROR': // failure to acknowledge postback notification
					// > Unfortunately, we could not
					// > confirm your purchase with the
					// > merchant's server. Your order has
					// > been canceled.
					// > Please contact the merchant if
					// > this problem continues.
					case 'INTERNAL_SERVER_ERROR': // internal Google error
					default:
						alert(action.response.errorType);
						break;
					}
					// console.log('failure', arguments);
				}
			});
		}
		</script>
		<p><a href="#" onclick="buy(); return false;"><img alt="Buy with Google" src="data:image/gif;base64,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" /></a></p>
	<?php
	return ob_get_clean();

/**
	ob_start();
	?>
		<p>Hello, vb</p>
		<form action="<?php echo $url, $param['merchantid'] ?>" id="BB_BuyButtonForm" method="POST" name="BB_BuyButtonForm">
			<input type="hidden" name="_charset_" value="utf-8">
			<input type="hidden" name="item_name_1" value="Invoice Payment">
			<input type="hidden" name="item_description_1" value="<?php echo $param['description'] ?>">
			<input type="hidden" name="item_quantity_1" value="1">
			<input type="hidden" name="item_price_1" value="<?php echo $param['amount'] ?>">
			<input type="hidden" name="item_currency_1" value="<?php echo $param['currency'] ?>">
			<input type="hidden" name="item_merchant_id_1" value="<?php echo $param['invoiceid'] ?>">
			<input type="hidden" name="shopping-cart.items.item-1.digital-content.email-delivery" value="true">
			<input type="hidden" name="continue_url" value="<?php echo $param['systemurl'] ?>/viewinvoice.php?id=<?php echo $param['invoiceid'] ?>">
			<input type="image" name="Google Checkout" alt="Fast Checkout Through Google" src="https://checkout.google.com/buttons/checkout.gif?merchant_id=<?php echo $param['merchantid'] ?>&w=180&h=46&style=white&variant=text&loc=en_US" height="46" width="180">
		</form>
	<?php
	return ob_get_clean();
**/
}

$GATEWAYMODULE['google_wallet_digitalname'] = 'google_wallet_digital';
$GATEWAYMODULE['google_wallet_digitalvisiblename'] = 'Google Wallet for Digital Goods';
$GATEWAYMODULE['google_wallet_digitaltype'] = 'Invoices';
#$GATEWAYMODULE['hellonotes'] = 'In order to use Google Checkout in a live environment, you must have an SSL certificate. Inside your Google Checkout account you need to go to <i>Settings > Preferences > Order processing preferences</i> and select the option <i>Automatically authorize and charge the buyer\'s credit card.</i>  Also, in <i>Settings > Integration</i> you must enter the following callback url: '.$CONFIG['SystemSSLURL'].'/modules/gateways/callback/googlecheckout.php';

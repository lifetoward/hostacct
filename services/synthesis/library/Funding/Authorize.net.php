<?php



class AuthorizeNet extends eTransactor
{
}

class AuthorizeNetCredCard extends Instance
{
	public static $fielddefs = array(
		,'x_card_num'=>array('name'=>'x_card_num', 'class'=>'t_ccnumber', 'pattern'=>"/^[456]\d{15}$/", // The pattern must be changed to accept other card types. See http://en.wikipedia.org/wiki/Credit_card_number
				'help'=>"The card number is your 16-digit account number embossed prominently on the front of your card. We accept Visa, MasterCard, and Discover.", 'notnull'=>true, 'label'=>"Credit card number")
		,'x_exp_date'=>array('name'=>'x_exp_date', 'class'=>'t_monthyear', 'label'=>"Card expiration date", 'notnull'=>true,
				'help'=>"The expiration date for your card is usually embossed on the front of the card below the card number.")
		,'x_card_code'=>array('name'=>'x_card_code', 'class'=>'t_string', 'label'=>"Card verification code", 'pattern'=>"^\d{3}$", // See http://en.wikipedia.org/wiki/Card_Security_Code
				'help'=>"This is the 3-digit code printed on the back of your card in or near the signature bar. (Use the last number group if there are several.)", 'notnull'=>true)
		,'x_address'=>array('name'=>'x_address', 'class'=>'t_string', 'label'=>"Account street address", 'pattern'=>"^\d+ ", 'notnull'=>true,
				'help'=>"Your street address is required for security reasons. Be sure to use the street address which matches the address on file with your card provider. The city and state are not required.")
		,'x_zip'=>array('name'=>'x_zip', 'class'=>'t_string', 'label'=>"Account zip code", 'pattern'=>"^\d{5}(-\d{4})?$", 'notnull'=>true,
				help="Your zip code is required for security reasons. Be sure to use the zip code which matches the address on file with your card provider.")
		);
}


class EbizCCChargeX extends Exception
{
	// If you catch this, the submission can be corrected by the user
}

class EbizCCFailedX extends Exception
{
	// If you catch this, something is wrong which can't likely be corrected by the user
}


function ebizcc_charge_onestep( $postdata, $amount, $description )
{
	global $sysroot;
	if (!mb_strstr($amount, '.'))
		$amount = sprintf("%0.2f", $amount/100);
	$description = urlencode($description);
	eval(file_get_contents("$sysroot/authorize.net-merchant-creds"));
    $parms = 'x_delim_data=TRUE&x_delim_char=,&x_encap_char="&x_exp_date='. date('m-Y') ."&$creds" .
		"&x_amount=$amount&x_description=$description&x_version=3.1&x_method=CC";
	// the variable information defining the cardholder & transaction details...
	foreach ($_SESSION['fqn*']['ebiz:element:ccinfo']['field*'] as $fieldname => $whatever)
		$parms .= "&$fieldname=". urlencode($postdata[$fieldname]);

	$ch = curl_init($postto);
	curl_setopt_array( $ch, array(
		 CURLOPT_HEADER=>0
		,CURLOPT_RETURNTRANSFER=>1
		,CURLOPT_POSTFIELDS=>$parms
		,CURLOPT_SSL_VERIFYPEER=>FALSE // per sample-code from Authorize.net: uncomment this line if you get no gateway response
		));
	if (!$response = curl_exec($ch))
		throw new EbizCCFailedX('The enrollment portal failed to connect to the authorization gateway.');
	$result = json_decode('[null,'. $response .']', true);

	logInfo($status = "Authorize.Net RC=$result[1]; Response $result[3]:$result[4]", "Authorize.Net Authcode=$result[5]; TrxId=$result[7]; AVS=$result[6]; CCV=$result[39]", 0);

	// Notes on responses from Authorize.net:
	// Response Reason codes (result[3]) are the real unique result IDs for the call. The Response Code is merely a summary interpretation.
	// http://developer.authorize.net/guides/AIM/Transaction_Response/Response_Reason_Codes_and_Response_Reason_Text.htm
	// RCs 1 and 4 mean everything is OK from the purchaser's point of view.
	// RC 2 means the card was declined, presumably for a reason the purchaser should be aware of, including invalid verification data
	// RC 3 means the problem was in the request or in data such that the capcel/ebiz system should have known better.
	// The response reason text (result[4]) is reasonable to communicate to the purchaser in summary.
	// If the response means that some input should be corrected, those must be interpreted to provide further instruction on how to proceed.
	if ($result[1]==2) { // The card was recognized but the transaction was declined or the info failed verification tests
		switch ($result[3]) {
			default:
			case 2: case 3: // declined under normal conditions
				throw new EbizCCChargeX("Your card was declined. Please use a different card.");
			case 28: // type of card not accepted by merchant
			case 37: // invalid credit card number (per merchant(?))
				throw new EbizCCChargeX("This card cannot be accepted. Please use a different card.");
			case 27: // AVS mismatch
			case 41: // merchant-determined fraud prevention decline
			case 44: // card code mismatch detected at merchant bank
			case 45: // merchant-determined info verification failure decline
			case 65: // merchant-determined info verification failure decline
				throw new EbizCCChargeX("Information verification failed. Please double-check your address, zip code, and card verification code or use a different card.");
		}
	}
	if ($result[1]==3) { // This is an error in the submission, ie. it could not be acted upon because something was missing or invalid
		logInfo("Authorization error: $result[4]");
		if ($result[3]==8)
			throw new EbizCCChargeX('This card seems to be expired. Correct the expiration date or use a different card.');
		switch ($result[3]) {
			case 6:
				throw new EbizCCChargeX("The payment card number is invalid. Please correct the card number and try again.");
			case 5: $explanation = "invalid amount field"; break;
			case 7: $explanation = "exp date invalid"; break;
			case 8: $explanation = "card is expired"; break;
			case 11: $explanation = "duplicate submission"; break;
			case 13: $explanation = "merchant login ID invalid or inactive"; break;
			case 15: $explanation = "invalid transaction ID (missing or nonnumeric)"; break;
			case 16: $explanation = "transaction ID not found"; break;
			case 17: $explanation = "card type not allowed by merchant"; break;
			case 33: $explanation = "merchant-required field (name visible in text) is required but was blank"; break;
			case 36: $explanation = "approved but settlement failed"; break;
			case 47: $explanation = "can't capture more than previously authorized"; break;
			case 48: $explanation = "can't settle for less than previously authorized"; break;
			case 49: $explanation = "merchant bank determined transaction amount limit exceeded"; break;
			case 50: $explanation = "can't refund/credit against transaction because it's not settled yet"; break;
			case 68: $explanation = "x_version was invalid (capcel/ebiz code error)"; break;
			case 69: $explanation = "x_type was invalid (capcel/ebiz code error)"; break;
			case 70: $explanation = "x_method was invalid (capcel/ebiz code error)"; break;
			case 78: $explanation = "CCV failed format validation (shouldn't happen if front-end is working)"; break;
			default: // problem with the gateway
		}
		logInfo(($status .= " [$explanation]"));
		throw new EbizCCFailedX($status);
	}
	if ($result[1]==4)
		logInfo("NOTICE: This transaction was held for review, but otherwise authorized.");
	return array($result[1], $result[7]);
}

<?php

if (session_status() === PHP_SESSION_NONE){
  session_start();
  if (!isset($_COOKIE['session_name()'])) setcookie(session_name(), session_id(), 0, NULL, NULL, 0);
}

require_once($_SERVER['DOCUMENT_ROOT']."/common/monologger.php");
//get_logger()->info('monologger verification', array('Called from' => __FILE__));

require '../vendor/autoload.php';
require_once 'ANetConstants.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

$folder = dirname( __FILE__, 3 ) . '/Logs/ANet';
$logfile = $folder . '/' . date('Y-m') . '.anet.hjtf.log' ;
define("AUTHORIZENET_LOG_FILE", $logfile);

$_root  =$_SERVER['DOCUMENT_ROOT'];

require_once($_root."/common/utils.php");
require_once($_root."/common/ezMysql_ntw.php");

spl_autoload_register(function ($class) { require_once"{$class}.class.php"; });

require_once($_root."/common/Z_HJTFglobals.php");
require_once($_root."/common/connect_REAL.php");

// LIVE SITE
$cust = '0024';
$event = '0101'; 
define( 'DONATE_LINK', isset($_SESSION["LIVE_TESTING"]) && $_SESSION["LIVE_TESTING"] == "Y" ? 'https://donations.hjta.org/hjta/donations_form-ntw.php?event=$cust-$event' : "https://donations.hjta.org/Zdon2HJTA/ZZ_0_HJTA_donations_form.php?event=$cust-$event" ) ;
define( 'RENEW_LINK' , isset($_SESSION["LIVE_TESTING"]) && $_SESSION["LIVE_TESTING"] == "Y" ? 'https://donations.hjta.org/hjta/renewals_form-ntw.php?event=$cust-$event' : "https://donations.hjta.org/Zdon2HJTA/ZZ_0_HJTA_renewals_form.php?event=$cust-$event" ) ;


require_once($_root."/email_groups/email_groups.php");
require_once($_root."/common/email_funcs.php");


//
//////////////////////////////////////////////////////
//
//  set to the user defined error handler
//dbine.ntw:  Replaced with monologger.php $old_error_handler=set_error_handler("myErrorHandler");
date_default_timezone_set("America/Los_Angeles");

// incoming

// sid & SESSION 
//session_start();
$sid             = $_POST['sid'] ;
$_SESSION["sid"] = $sid; 

  // Update Args
  $visitor_Args = array() ;
  $visitor_Args['sid']             = $_POST['sid'] ;
  $visitor_Args['fname']           = ucfirst(strtolower($_POST['fname']));
  $visitor_Args['lname']           = ucfirst(strtolower($_POST['lname']));
  $visitor_Args['addr']            = $_POST['addr'] ;
  $visitor_Args['addr2']           = $_POST['addr2'];
  $visitor_Args['city']            = $_POST['city'] ;
  $visitor_Args['state']           = $_POST['state'];
  $visitor_Args['zip']             = $_POST['zip'] ;
  $visitor_Args['email']           = $_POST['email']; 
  $visitor_Args['contrib']         = $_POST['contrib'] ;       
  $visitor_Args['cc_last5']        = $_POST['cc_last5'] ;  
//  $visitor_Args['occupation']      = $_POST['occupation'];    
//  $visitor_Args['employer']        = $_POST['employer']   ;    
//  $visitor_Args['corp_card']       = $_POST['corp_card'] ;     
//  $visitor_Args['corp_name']       = $_POST['corp_name'] ;     
//  $visitor_Args['corp_address']    = $_POST['corp_address'] ;   
//  $visitor_Args['corp_city']       = $_POST['corp_city'] ;     
//  $visitor_Args['corp_state']      = $_POST['corp_state'];
//  $visitor_Args['corp_zip']        = $_POST['corp_zip']; 
  $visitor_Args['cc_type']         = $_POST['cc_type'] ;  
  
  /// charging only DONT SAVE
  $charge_Args = array() ;
  $charge_Args['contrib']         = $_POST['contrib'] ;   
  $charge_Args['cc_name']         = $_POST['cc_name'];
  $charge_Args['cc_number']       = $_POST['cc_number'];
  $charge_Args['cc_type']         = $_POST['cc_type'] ;  // yes we do save
  $charge_Args['cc_month']        = $_POST['cc_month'] ;
  $charge_Args['cc_year']         = $_POST['cc_year'] ;
  $charge_Args['cc_cvv']          = $_POST['cc_cvv'] ;

/// tracer
hjtaorgLog( 'Stage 1 ' , "Process $sid" , __FILE__ . ' Line: ' . __LINE__ , "HJTF");

///////////////// DATABASE  begin
$ez    =new ezMysql_ntw();

//	Do Database stuff
$sid_condition = "sid='". $visitor_Args['sid']   . "'" ;

// Need to determine what VISITTYPE is for later use
$visittype = 'DONATE' ;
$result=$ez->ezSelect_i("*", VISIT_TBL , $sid_condition );
$numb_rows = mysqli_num_rows($result);
if ( $numb_rows == 1) : 
     $data       = mysqli_fetch_object($result);
     $visittype  = trim(strtoupper($data->visittype)) ;
endif ;

// trap to see if there is ALREADY a finalized trasaction with that SID
$already_condition = $sid_condition . " AND completed = 'Y' " ;
$result=$ez->ezSelect_i("*", VISIT_TBL , $already_condition );
$numb_rows = mysqli_num_rows($result);
  
if ( $numb_rows == 1) : // ALREADY DONE !!!
  // go to decline function
  DeclinedForm('Transaction has already completed. Do NOT press BACK ARROW.') ;
  // logit
  hjtaorgLog( 'HALT Stage 1 ' , "Duplicate charge attempt found. STOPPED. $sid"  , __FILE__ . ' Line: ' . __LINE__ , "HJTF");
  // debug
  // DEBUG TRACER
  hjtaorgDBUG( 'STAGE 1 HALTED ' , "DUPLICATE COMPLETED TRANS FOUND $sid" , __FILE__ . ' Line: ' . __LINE__ , "HJTF");
  // EXCEPTION PATH TODO :???
  exit() ;
else :
  // ok to proceed
endif ;

//UPDATE trivial/clerical fields
$visitor_updated = $ez->ezUpdate_i(VISITOR_TBL, $visitor_Args, $sid_condition ); //

///  low ball testing
if ( trim($charge_Args['cc_name']) == 'UMA GUMA JR'  ):
  $charge_Args['contrib']   =  '1.01' ;
  $totalContrib             =  '1.01' ;
endif ;


///// ***************************************************
/////  BEGIN AUTHORIZE.NET CHARGE 06/20/2012
///// ***************************************************

/* Latest net\authorize\api replaces AuthorizeNetAIM */

//$sale = new AuthorizeNetAIM;
//$sale->amount     = $charge_Args["contrib"];
//$sale->card_num   = $charge_Args['cc_number'];
//$sale->exp_date   = $charge_Args["cc_month"] . '/' . $charge_Args["cc_year"] ;
//$sale->card_code  = $charge_Args['cc_cvv'] ; 

// added 05/10/2013
//$sale->first_name =  $visitor_Args['fname'] ;
//$sale->last_name  =  $visitor_Args['lname'] ;
//$sale->address    =  $visitor_Args['addr'] ;
//$sale->city       =  $visitor_Args['city'] ;
//$sale->state      =  $visitor_Args['state'] ;
//$sale->zip        =  $visitor_Args['zip'] ;
//$sale->email      =  $visitor_Args['email'] ;
//$sale->company    =  $charge_Args['cc_name'] ;
//
//if ( $visitor_Args['corp_card'] == "1"  ) :
// $sale->description     =  'COPRORATE' ;
//else :
// $sale->description     =  'PERSONAL' ;
//endif ;
//  added 10/20/2014
//$sale->description  = $visitor_Args['sid'] ;
//$sale->customer_ip  = $_SERVER['REMOTE_ADDR'];
// end


/* Create a merchantAuthenticationType object with authentication details retrieved from the constants file */
$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
$merchantAuthentication->setName(\ANetConstants::MERCHANT_LOGIN_ID);
$merchantAuthentication->setTransactionKey(\ANetConstants::MERCHANT_TRANSACTION_KEY);

// Set the transaction's refId
$refId = 'ref' . time();

// Create the payment data for a credit card
$creditCard = new AnetAPI\CreditCardType();
$creditCard->setCardNumber($charge_Args['cc_number']);
$creditCard->setExpirationDate($charge_Args["cc_month"] . '/' . $charge_Args["cc_year"]);
$creditCard->setCardCode($charge_Args['cc_cvv']);

// Add the payment data to a paymentType object
$paymentOne = new AnetAPI\PaymentType();
$paymentOne->setCreditCard($creditCard);

// Create order information
$order = new AnetAPI\OrderType();
$order->setInvoiceNumber(substr($visitor_Args['sid'], 0, 9) . "-" . substr($visitor_Args['sid'], strpos($visitor_Args['sid'], "----")+4, 10)); //string too long exception if try to log entire session id + time
$order->setDescription("HJTF:DONATE");

// Set the customer's Bill To address
$customerAddress = new AnetAPI\CustomerAddressType();
$customerAddress->setFirstName($visitor_Args['fname'] );
$customerAddress->setLastName( $visitor_Args['lname']);
$customerAddress->setCompany($charge_Args['cc_name']);
$customerAddress->setAddress($visitor_Args['addr']);
$customerAddress->setCity($visitor_Args['city']);
$customerAddress->setState($visitor_Args['state']);
$customerAddress->setZip( $visitor_Args['zip'] );
$customerAddress->setCountry("USA");

// Set the customer's identifying information
$customerData = new AnetAPI\CustomerDataType();
$customerData->setType("individual"); 
$customerData->setId(substr($visitor_Args['sid'], 0, 9) . "-" . substr($visitor_Args['sid'], strpos($visitor_Args['sid'], "----")+4, 10)); //string too long exception if try to log entire session id + time
$customerData->setEmail($visitor_Args['email']);

// Add values for transaction settings
$duplicateWindowSetting = new AnetAPI\SettingType();
$duplicateWindowSetting->setSettingName("duplicateWindow");
$duplicateWindowSetting->setSettingValue("60");

// Create a TransactionRequestType object and add the previous objects to it
$transactionRequestType = new AnetAPI\TransactionRequestType();
$transactionRequestType->setTransactionType("authCaptureTransaction"); 
$transactionRequestType->setAmount($charge_Args["contrib"]);
$transactionRequestType->setOrder($order);
$transactionRequestType->setPayment($paymentOne);
$transactionRequestType->setBillTo($customerAddress);
$transactionRequestType->setCustomer($customerData);
$transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
$transactionRequestType->setCustomerIP($_SERVER['REMOTE_ADDR']);

// Assemble the complete transaction request
$request = new AnetAPI\CreateTransactionRequest();
$request->setMerchantAuthentication($merchantAuthentication);
$request->setRefId($refId);
$request->setTransactionRequest($transactionRequestType);

// Create the controller and get the response
$controller = new AnetController\CreateTransactionController($request);
$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION); //PRODUCTION

$r_approved = FALSE;
$r_declined = FALSE;
$r_error = FALSE;
$r_error_message = "";

$r_response_reason_text  = "";
$r_authorization_code    = "";
$r_amount                = "";
$transaction_id          = "";

if ($response != null) {
  // Check to see if the API request was successfully received and acted upon
  if ($response->getMessages()->getResultCode() == "Ok") {
      // Since the API request was successful, look for a transaction response
      // and parse it to display the results of authorizing the card
      $tresponse = $response->getTransactionResponse();
  
      if ($tresponse != null && $tresponse->getMessages() != null) {

          $r_approved = TRUE;
          $r_response_reason_text  = $tresponse->getMessages()[0]->getDescription();
          $r_authorization_code    = trim($tresponse->getAuthCode());
          $r_amount                = $charge_Args["contrib"]; //(used to be pulled from response in AuthorizeNetAIM)
          $transaction_id          = $tresponse->getTransId();           

          //echo " Successfully created transaction with Transaction ID: " . $tresponse->getTransId() . "\n";
          // echo " Transaction Response Code: " . $tresponse->getResponseCode() . "\n";
          // echo " Message Code: " . $tresponse->getMessages()[0]->getCode() . "\n";
          //echo " Auth Code: " . $tresponse->getAuthCode() . "\n";
          //echo " Description: " . $tresponse->getMessages()[0]->getDescription() . "\n";
      } else {
          //echo "Transaction Failed \n";
          $r_response_reason_text = "Transaction Failed";
          if ($tresponse->getErrors() != null) {
              //echo " Error Code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
              //echo " Error Message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
              $r_error_message = $tresponse->getErrors()[0]->getErrorText() . "[" . $tresponse->getErrors()[0]->getErrorCode() . "]";
          }
      }
      // Or, print errors if the API request wasn't successful
  } else {
      //echo "Transaction Failed \n";
      $r_response_reason_text = "API Request Failed";
      $tresponse = $response->getTransactionResponse();
  
      if ($tresponse != null && $tresponse->getErrors() != null) {
          //echo " Error Code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
          //echo " Error Message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
          $r_error_message = $tresponse->getErrors()[0]->getErrorText() . "[" . $tresponse->getErrors()[0]->getErrorCode() . "]";
      } else {
          //echo " Error Code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
          //echo " Error Message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
          $r_error_message = $response->getMessages()->getMessage()[0]->getText() . "[" . $response->getMessages()->getMessage()[0]->getCode() . "]";
      }    
  }
} else {
  //echo  "No response returned \n";
  $r_error_message = "No response returned from payment processor" . "[" . $response->getMessages()->getResultCode() . "]"; 
}


//'NTW: see where customer id maps in the migration documentation'

if ($r_approved) :

    // convert new vars into old DRCC vars
    $serverRet    = 'SUCCESS' ;
    $respText     = 'APPROVAL ' . $r_authorization_code . ' TRANS ID: ' . $transaction_id ;
    $respErrNum   = '' ;
    $respErrText  = '' ;
    $auth         = 'APPROVAL ' . $r_authorization_code ;

    // build reply values for form
    $a_ReplyVals = array() ;
    $a_ReplyVals["trans_id"]    = $transaction_id;
    $a_ReplyVals["amt_charged"] = $charge_Args["contrib"];
    $a_ReplyVals["sku"]         = 'HJTF'  ;
    $a_ReplyVals["visittype"]   = 'HJTF' ;          

    /// APPROVED CHARGE
    /// tracer
    hjtaorgLog( 'END Stage 1 ' , "Successful charge. $sid" , __FILE__ . ' Line: ' . __LINE__ , "HJTF");

    // CHARGED SUCCEED !!!
    $rv = authStatusUpdate("Y");

    // EMAIL STUFF
    $ebody = HJTF_Donation_Thanks($visitor_Args['fname']) ;
    
    //echo trim($visitor_Args['email']);
    //echo $ebody;

    zEmail( trim($visitor_Args['email'])  , "Thank you for your Pres. Circle Event Donation", $ebody ) ;

    // high donor alert
    if ( $charge_Args["contrib"] >= 500 ) :  //(used to be pulled from response in AuthorizeNetAIM)
      $amt_charged = $a_ReplyVals["amt_charged"] ;
      $sid         = $visitor_Args['sid'] ;
      $donor       = $visitor_Args['fname'] . ' ' . $visitor_Args['lname']  ;
      $body        = " Amount of $amt_charged was just made to HJTF. By $donor. See: $sid" ;
      zEmail( 'hkelly32@gmail.com'  , "HIGH DONOR ALERT - HJTF", $body ) ;
      hjtaorgLog( 'HIGH DONOR ALERT ' , $body , __FILE__ . ' Line: ' . __LINE__ , "HJTF");
    endif ;

    // NEW PATH 2014 PROJECT Z
    $rv = ApprovedForm( $a_ReplyVals) ;
          
else :  // FAILED - DECLINED

    // convert new vars into old DRCC vars
    $serverRet    = '' ;
    //$respText     = $r_response_reason_text   ;
    $respText     = $r_response_reason_text . ' '  . $r_error_message   ; // VERBOSE TO interface GIU & email  
    $respErrNum   = '' ;
    $respErrText  = '' ;
    $auth         = '' ;


    /// tracer
    hjtaorgLog( 'END Stage 1' , "DECLINED charge. $sid" . " $respText . ' '  . $r_error_message " , __FILE__ . ' Line: ' . __LINE__ , "HJTF");

    // DECLINED
    $rv   = authStatusUpdate("N");

    // EMAIL stuff
    $ebody = HJTF_Donation_Sorry($visitor_Args['fname'], $respText ) ;
    zEmail( trim($visitor_Args['email'])  , "Your Donation Attempt Failed", $ebody ) ;
    
    // new path 2014 Project Zip
    $rv = DeclinedForm($respText) ;

endif ; // $r_approved

///// ***************************************************
/////  END EVERYTHING 
///// ***************************************************

function authStatusUpdate($auth = "N"){
    //  Add final information to the visit table, showing completion
    //  and authorization status
    //
    global $sid;
    global $serverRet;
    global $respText;
    global $respErrNum;
    global $respErrText;

    // LAST BITS OF COMPLETION
    $mytime    = time();
    $now       = date("Y-m-d H:i:s", $mytime);
    $myArgs['cc_server_stat']    =  $serverRet;
    $myArgs['cc_authorization']  =  $auth;
    $myArgs['cc_respText']       =  $respText;
    $myArgs['cc_respErrNum']     =  $respErrNum;
    $myArgs['cc_respErrText']    =  $respErrText;
    $myArgs['completed']         =  "Y";
    $myArgs['completedate']      =  $now;
    
    $ez    =new ezMysql_ntw();  
    $ez->ezUpdate_i( VISIT_TBL , $myArgs, "sid='".$sid."'"); 
    
    return($auth) ;
} // eof: authStatusUpdate()

function ApprovedForm( $a_ReplyVals ) {
    ?>
    <!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HJTF - President's Circle</title>
<link rel="icon" href="/global/images/hjta-favicon.ico">
<link href='https://fonts.googleapis.com/css?family=EB Garamond' rel='stylesheet'>
<style>
body {background-color: #A3BFCE; margin: 0; font-family: sans-serif; font-size: 18px; line-height: 24px !important; color: #333333;}
input, textarea, select {
    border-radius: 5px;
    border: 1px solid #499bd4;
    padding: 5px;
	font: inherit;
}
#main-wrap {width: 1280px; margin: 0 auto;}
#inner-wrap {margin: 20px 50px;}
#thank-you-image-wrap {padding-left: 26px; padding-bottom: 0;}
#thank-you-text-wrap {padding: 0 30px 0 62px;}
#thank-you-text-wrap p {font-family: 'EB Garamond';font-size: 24px; margin-top: 0;}
#form-header {background-color: #2F576E; font-family: Open Sans; color: #ffffff; padding: 20px 30px; height: 70px;}
.form-content-header {width: 45%;}
.form-content-header.left {float: left;}
.form-content-header.right {float: right; text-align: right;}
#form-header h1 {margin: 0;font-family: sans-serif;
    font-size: 24pt; line-height: 24pt;text-transform: uppercase;}
#form-header h1	span.h1-smaller {font-size: 20pt;}
#form-content {background-color: #ffffff; padding: 30px; clear: both;}
#form-content fieldset {border: 1px solid #e5e5e5; padding: 3rem; margin: 2rem auto;}
#form-content fieldset h3 {margin: 0 0 2rem; font-size: 20px; color: #0f2c71;}
#donateAmount > div {
    float: left;
    min-width: 20%;
}
#donateAmount .option {
    display: block;
    font-size: 1.28em;
    line-height: 1.5em;
}
.formitem {margin-bottom: 1em; clear: both;}
.formitem label {width: 28%; text-align: right; display: block; float: left; padding-right: 0.5em;}
input, textarea, select{border-radius: 5px; border: 1px solid #499bd4; padding: 5px;}
#CVV {
    width: auto;
}
.formitem img {
    vertical-align: top;
    margin-left: 1em;
}
#form-content fieldset#buttons {
    border: none;
    margin-top: 0;
    padding-top: 0;
	    display: inline-block;
    width: 92%;
    text-align: center;
}
.formitem {
    margin-bottom: 1em;
    clear: both;
}
#form-content fieldset#buttons input {margin: 20px 0 0 1em; border: 0px none; cursor: pointer;}
#form-content fieldset#buttons #donation-submit {background-color: #cf2e2e; border-radius: 36px; padding: 10px 20px; color: #fff; font-weight: bold; text-transform: uppercase; letter-spacing: unset; font-size: 16px;}
#form-content fieldset#buttons #donation-cancel {
    background-color: #fff !important;
    border: solid 1px #ddd;
    border-radius: 36px;
    color: #499bd4 !important;
    padding: 8px 7px !important;
}
#form-content fieldset#totals, #form-content fieldset#complete {
    border: none;
    margin-bottom: 0;
    padding-top: 0;
    padding-bottom: 0;
}
#totals-wrap {
    width: 45%;
    margin: 0 auto;
    text-align: center;
}
#form-content fieldset#totals h3 {
    /* display: inline; */
    vertical-align: bottom;
    padding-right: 1em;
    color: #000000;
    font-size: 18px;
    font-style: italic;
}
#totals-label {
    font-size: 16px;
}
.formitem label.option, .formitem .description {
    width: auto;
    margin-left: 28%;
    text-align: left;
}
.formitem > input, .formitem > textarea {
    width: 50%;
}
.description {
    font-size: 13px;
    display: block;
    font-style: italic;
    color: #333333;
    font-weight: normal;
    font-family: 'Myriad Pro', sans-serif;
}
p.warning {
    background-color: #e0e0e0;
    color: #a82512;
    font-size: 18px;
    padding: 20px 5px;
    margin-top: 15px;
    margin-bottom: 15px;
    font-weight: bold;
    line-height: 32px;
}
/* Medium devices (landscape tablets, 768px and up) */
@media only screen and (max-width: 1024px) {
    #main-wrap {
        width: 100%;
    }
    #header-image img {
        width: 100%;
    }
    #thank-you-image-wrap {
        padding-left: 0px;
    }
    #thank-you-text-wrap {
        padding: 0 30px 0 30px;
    }
}
@media only screen and (max-width: 768px) {
    #totals-wrap {
        width: 100%;
    }
    #inner-wrap {
        margin: 20px 20px;
    }
    .formitem label {
        width: 75%;
        display: block;
        padding-right: 0.5em;
        text-align: left;
    }
    .formitem > input, .formitem > textarea {
        width: 80%;
    }
    .form-content-header.left {
        display: none;
    }
    .form-content-header.right {
        float: none;
        text-align: left;
        width: 100%;
    }
    .form-content-header.right img {
        width: 100%;
    }
    .formitem label.option, .formitem .description {
        margin-left: 0;
    }
    #form-content fieldset#buttons {
        margin-left: 0;
        padding: 0;
    }
    #donateAmount .donationAmount.left {
        margin-right: 35px;
    }
    #donationDollarSign {
        display: none;
    }
    #form-content fieldset#complete {
        padding-left: 0;
    }
    #form-content fieldset#complete h1 {
        line-height: 40px;
    }
}

</style>
</head>
<body>
<div id="main-wrap">
	<div id="header-image">
		<img src="images/HJTA-Presidents-Circle-Header.jpg" width="1280" alt="President's Circle | Howard Jarvis Tax Association" />
	</div>
	<div id="inner-wrap">
		<div id="top-content-wrap">
			<div id="thank-you-image-wrap"><img src="images/Thank_You_Teal_19618a.png" width="350" alt="Thank You" /></div>
			<div id="thank-you-text-wrap"><p>...please review the notification below.</p>
			<p>NOTE: Contributions or gifts to the Howard Jarvis Taxpayers Foundation are deductible from your federal income tax and may also be deductible from your state income tax. Howard Jarvis Taxpayers Foundation Tax ID: 52-1155794</p></div>
		</div>
		<div id="form-wrap">
			<div id="form-header">
				<div class="form-content-header left"><h1><span class="h1-smaller">Secure Online</span><br />Donation Form</h1></div>
				<div class="form-content-header right"><img src="images/HJTF-white.png" width="350" /></div>
			</div>
			<div id="form-content">
				<form><div><fieldset id="complete">
				<h1><span>Thank You For Your Donation</span></h1>
				<p>Your card was charged the following: </p>
				<p><strong><span class="Label">Amount </span> $<?php echo $a_ReplyVals["amt_charged"]; ?> </strong></p>
				<a class="btn gradient" href="http://www.hjta.org">Return to Home Page</a>
			</fieldset></div></form>
			<div>
				<p>If you are using a VPN (virtual private network) connection, you may receive an error message when connecting to our payment processor. Please try again with the VPN turned off, if possible. We apologize for the inconvenience.</p>
			</div>
			</div>			
		</div>
	</div>
</div>
</body>
</html>
    <?php
}

function DeclinedForm($ccFailReason) {
 ?>
    <!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HJTF - President's Circle</title>
<link rel="icon" href="/global/images/hjta-favicon.ico">
<link href='https://fonts.googleapis.com/css?family=EB Garamond' rel='stylesheet'>
<style>
body {background-color: #A3BFCE; margin: 0; font-family: sans-serif; font-size: 18px; line-height: 24px !important; color: #333333;}
input, textarea, select {
    border-radius: 5px;
    border: 1px solid #499bd4;
    padding: 5px;
	font: inherit;
}
#main-wrap {width: 1280px; margin: 0 auto;}
#inner-wrap {margin: 20px 50px;}
#thank-you-image-wrap {padding-left: 26px; padding-bottom: 0;}
#thank-you-text-wrap {padding: 0 30px 0 62px;}
#thank-you-text-wrap p {font-family: 'EB Garamond';font-size: 24px; margin-top: 0;}
#form-header {background-color: #2F576E; font-family: Open Sans; color: #ffffff; padding: 20px 30px; height: 70px;}
.form-content-header {width: 45%;}
.form-content-header.left {float: left;}
.form-content-header.right {float: right; text-align: right;}
#form-header h1 {margin: 0;font-family: sans-serif;
    font-size: 24pt; line-height: 24pt;text-transform: uppercase;}
#form-header h1	span.h1-smaller {font-size: 20pt;}
#form-content {background-color: #ffffff; padding: 30px; clear: both;}
#form-content fieldset {border: 1px solid #e5e5e5; padding: 3rem; margin: 2rem auto;}
#form-content fieldset h3 {margin: 0 0 2rem; font-size: 20px; color: #0f2c71;}
#donateAmount > div {
    float: left;
    min-width: 20%;
}
#donateAmount .option {
    display: block;
    font-size: 1.28em;
    line-height: 1.5em;
}
.formitem {margin-bottom: 1em; clear: both;}
.formitem label {width: 28%; text-align: right; display: block; float: left; padding-right: 0.5em;}
input, textarea, select{border-radius: 5px; border: 1px solid #499bd4; padding: 5px;}
#CVV {
    width: auto;
}
.formitem img {
    vertical-align: top;
    margin-left: 1em;
}
#form-content fieldset#buttons {
    border: none;
    margin-top: 0;
    padding-top: 0;
	    display: inline-block;
    width: 92%;
    text-align: center;
}
.formitem {
    margin-bottom: 1em;
    clear: both;
}
#form-content fieldset#buttons input {margin: 20px 0 0 1em; border: 0px none; cursor: pointer;}
#form-content fieldset#buttons #donation-submit {background-color: #cf2e2e; border-radius: 36px; padding: 10px 20px; color: #fff; font-weight: bold; text-transform: uppercase; letter-spacing: unset; font-size: 16px;}
#form-content fieldset#buttons #donation-cancel {
    background-color: #fff !important;
    border: solid 1px #ddd;
    border-radius: 36px;
    color: #499bd4 !important;
    padding: 8px 7px !important;
}
#form-content fieldset#totals, #form-content fieldset#complete {
    border: none;
    margin-bottom: 0;
    padding-top: 0;
    padding-bottom: 0;
}
#totals-wrap {
    width: 45%;
    margin: 0 auto;
    text-align: center;
}
#form-content fieldset#totals h3 {
    /* display: inline; */
    vertical-align: bottom;
    padding-right: 1em;
    color: #000000;
    font-size: 18px;
    font-style: italic;
}
#totals-label {
    font-size: 16px;
}
.formitem label.option, .formitem .description {
    width: auto;
    margin-left: 28%;
    text-align: left;
}
.formitem > input, .formitem > textarea {
    width: 50%;
}
.description {
    font-size: 13px;
    display: block;
    font-style: italic;
    color: #333333;
    font-weight: normal;
    font-family: 'Myriad Pro', sans-serif;
}
p.warning {
    background-color: #e0e0e0;
    color: #a82512;
    font-size: 18px;
    padding: 20px 5px;
    margin-top: 15px;
    margin-bottom: 15px;
    font-weight: bold;
    line-height: 32px;
}
/* Medium devices (landscape tablets, 768px and up) */
@media only screen and (max-width: 1024px) {
    #main-wrap {
        width: 100%;
    }
    #header-image img {
        width: 100%;
    }
    #thank-you-image-wrap {
        padding-left: 0px;
    }
    #thank-you-text-wrap {
        padding: 0 30px 0 30px;
    }
}
@media only screen and (max-width: 768px) {
    #totals-wrap {
        width: 100%;
    }
    #inner-wrap {
        margin: 20px 20px;
    }
    .formitem label {
        width: 75%;
        display: block;
        padding-right: 0.5em;
        text-align: left;
    }
    .formitem > input, .formitem > textarea {
        width: 80%;
    }
    .form-content-header.left {
        display: none;
    }
    .form-content-header.right {
        float: none;
        text-align: left;
        width: 100%;
    }
    .form-content-header.right img {
        width: 100%;
    }
    .formitem label.option, .formitem .description {
        margin-left: 0;
    }
    #form-content fieldset#buttons {
        margin-left: 0;
        padding: 0;
    }
    #donateAmount .donationAmount.left {
        margin-right: 35px;
    }
    #donationDollarSign {
        display: none;
    }
    #form-content fieldset#complete {
        padding-left: 0;
    }
    #form-content fieldset#complete h1 {
        line-height: 40px;
    }
}

</style>
</head>
<body>
<div id="main-wrap">
	<div id="header-image">
		<img src="images/HJTA-Presidents-Circle-Header.jpg" width="1280" alt="President's Circle | Howard Jarvis Tax Association" />
	</div>
	<div id="inner-wrap">
		<div id="top-content-wrap">
			<div id="thank-you-image-wrap"><img src="images/Thank_You_Teal_19618a.png" width="350" alt="Thank You" /></div>
			<div id="thank-you-text-wrap"><p>...for attending our President Circle's House Event to Protect Proposition 13. We sincerely appreciate any support as you consider making your tax-deductible donation to HJTF today!</p></div>
		</div>
		<div id="form-wrap">
			<div id="form-header">
				<div class="form-content-header left"><h1><span class="h1-smaller">Secure Online</span><br />Donation Form</h1></div>
				<div class="form-content-header right"><img src="images/HJTF-white.png" width="350" /></div>
			</div>
			<div id="form-content">
				<form><div><fieldset id="complete">
				<h1><span>Your donation attempt failed.</span></h1>
                <p><strong><span class="Label">Reason: </span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <?php echo $ccFailReason; ?> 
			<a class="btn gradient" href="donation.php?event=0024-0101">TRY AGAIN</a>
		</strong></p><p>Unfortunately, there was a problem with your donation attempt. Nothing has been charged.</p>
			</fieldset></div></form>
			<div>
				<p>If you are using a VPN (virtual private network) connection, you may receive an error message when connecting to our payment processor. Please try again with the VPN turned off, if possible. We apologize for the inconvenience.</p>
			</div>
			</div>			
		</div>
	</div>
</div>
</body>
</html>
<?php
}

function HJTF_Donation_Thanks($fname) {
$vHJTF_SDS_ROOT = HJTF_SDS_ROOT ;
$vHJTF_SD_ROOT  = HJTF_SD_ROOT ;

$ebody  = '' ;
$ebody .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
$ebody .= "<html xmlns:v=\"urn:schemas-microsoft-com:vml\">\n";
$ebody .= "<head>\n";
$ebody .= "<meta name=\"HandheldFriendly\" content=\"false\" />\n";
$ebody .= "<meta name=\"viewport\" content=\"width=device-width; initial-scale=1; maximum-scale=1; minimum-scale=1; user-scalable=no;\" />\n";
$ebody .= "<title>Thank you! Your donation helps keep taxes low.</title>\n";
$ebody .= "<style type=\"text/css\">\n";
$ebody .= ".ReadMsgBody {width:100%;}\n";
$ebody .= ".ExternalClass {width:100%;}\n";
$ebody .= "table td {border-collapse:collapse;}\n";
$ebody .= ".appleLinksGrey a {color:#5B5B5B; text-decoration: none;}\n";
$ebody .= "@media all and (max-width:540px) {\n";
$ebody .= "	table {width:100%;}\n";
$ebody .= "	table[class=\"wrapper\"] {width:100% !important;}\n";
$ebody .= "	table[class=\"hide\"], tr[class=\"hide\"], td[class=\"hide\"], img[class=\"hide\"] {display:none!important;}\n";
$ebody .= "	td[class=\"header\"] {padding:15px;font-size:12px !important;}\n";
$ebody .= "	img[class=\"fit\"] {width:100%; height:auto;}\n";
$ebody .= "	/*Spacing*/\n";
$ebody .= "	td[class=\"gutter\"] {width:10px !important;}\n";
$ebody .= "	td[class=\"padding15\"] {padding:15px;}\n";
$ebody .= "	/* CTA */\n";
$ebody .= "	table[class=\"CTA_1\"] {width:250px;}\n";
$ebody .= "}\n";
$ebody .= "</style>\n";
$ebody .= "</head>\n";
$ebody .= "<body bgcolor=\"#F6F6F6\" text=\"#000001\" link=\"#0000EE\" alink=\"#0000EE\" vlink=\"#551A8B\" style=\"margin-left:0px;margin-right:0px;margin-top:0px;padding:0px;\">\n";
$ebody .= "<div style=\"text-decoration:none;\">\n";
$ebody .= "	<div style=\"-webkit-text-size-adjust:none;\">\n";
$ebody .= "		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\" bgcolor=\"#F6F6F6\" class=\"wrapper\">\n";
$ebody .= "			<tr valign=\"top\">\n";
$ebody .= "				<td width=\"100%\" align=\"center\" valign=\"top\">\n";
$ebody .= "\n";
$ebody .= "					<table width=\"620\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\" bgcolor=\"#F6F6F6\">\n";
$ebody .= "						<tr valign=\"top\">\n";
$ebody .= "							<td class=\"gutter\" width=\"10\"></td>\n";
$ebody .= "							<td align=\"center\" valign=\"top\">\n";
$ebody .= "								\n";
$ebody .= "								<!--HEADER-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td height=\"45\" width=\"10\"></td>\n";
$ebody .= "										<td align=\"center\" valign=\"middle\" style=\"font:normal 11px/15px Arial, Helvetica, sans-serif;color:#5B5B5B;\" class=\"header\">\n";
$ebody .= "											Thank you for supporting HJTF with your donation.\n";
$ebody .= "										</td>\n";
$ebody .= "										<td height=\"45\" width=\"10\"></td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "								\n";
$ebody .= "								<!--TOP-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#213B5D\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td align=\"center\">\n";
$ebody .= "											\n";
$ebody .= "											<!--Logo-->\n";
$ebody .= "											<table width=\"620\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\" style=\"mso-table-lspace:0;mso-table-rspace:0;\">\n";
$ebody .= "												<tr valign=\"top\">\n";
$ebody .= "													<td style=\"font:bold 12px/20px Arial, Helvetica, sans-serif;color:#FFFFFF;\" align=\"left\">\n";
$ebody .= "														<img src=\"https://donations.hjta.org/hjtf-presidents-circle/images/hjtf-presidents-circle-email-banner.jpg\" width=\"620\" alt=\"Howard Jarvis Taxpayers Association\" border=\"0\" style=\"display:block;\" class=\"fit\" />\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "											\n";
$ebody .= "										</td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "								\n";
$ebody .= "								<!--Main Body: start-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#FFFFFF\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td style=\"padding:20px 20px 0;\">\n";
$ebody .= "										\n";
$ebody .= "											<!--IMAGE-->\n";
$ebody .= "											<table width=\"90\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\" style=\"mso-table-lspace:0;mso-table-rspace:0;\">\n";
$ebody .= "												<tr valign=\"top\">\n";
$ebody .= "													<td style=\"font:bold 12px/20px Arial, Helvetica, sans-serif;color:#FFFFFF;\" align=\"left\">\n";
$ebody .= "														<img src=\"https://donations.hjta.org/hjtf/images/HJTF_graphic.jpg\" width=\"79\" height=\"79\" alt=\"Gavel\" border=\"0\" style=\"display:block;\" />\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "											\n";
$ebody .= "											<table width=\"20\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\" style=\"mso-table-lspace:0;mso-table-rspace:0;\">\n";
$ebody .= "												<tr valign=\"top\">\n";
$ebody .= "													<td width=\"20\"><img src=\"https://donations.hjta.org/global/images/shim.gif\" width=\"20\" height=\"20\" alt=\"\" border=\"0\" style=\"display:block;\" /></td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "											\n";
$ebody .= "											<!--TEXT-->\n";
$ebody .= "											<table width=\"420\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\" style=\"mso-table-lspace:0;mso-table-rspace:0;\">\n";
$ebody .= "												<tr valign=\"top\">\n";
$ebody .= "													<td style=\"font:normal 14px/20px Verdana, Geneva, sans-serif;color:#5B5B5B;\" align=\"left\">\n";
$ebody .= "														Dear $fname,\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														On behalf of everyone at the Howard Jarvis Taxpayers Association, I would like to express my heartfelt thanks for attending our President's Circle event in Granite Bay.\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "										\n";
$ebody .= "										</td>\n";
$ebody .= "									</tr>\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td style=\"padding:20px;\">\n";
$ebody .= "										\n";
$ebody .= "											<!--Copy-->\n";
$ebody .= "											<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
$ebody .= "												<tr valign=\"top\">\n";
$ebody .= "													<td style=\"font:normal 14px/20px Verdana, Geneva, sans-serif;color:#5B5B5B;padding:0 0 25px;\" align=\"left\">\n";
$ebody .= "														Your financial Support to the Howard Jarvis Taxpayers Foundation will be used wisely as we continue to defend Proposition 13 at the court level. Your contribution is fully tax-deductible and your Foundation donation funds the HJTA Office of Legal Affairs.\n";
$ebody .= "														\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														I invite you to keep tabs on our progress, and follow important news affecting California taxpayers, at our\n";
$ebody .= "														<a href=\"http://www.hjta.org/\" target=\"_blank\" style=\"outline:none;text-decoration:none;\"><span style=\"color:#0062A6;text-decoration:underline;\">official website</span></a>.\n";
$ebody .= "														You can also\n";
$ebody .= "														<a href=\"http://www.hjta.org/e-mail-updates/\" target=\"_blank\" style=\"outline:none;text-decoration:none;\"><span style=\"color:#0062A6;text-decoration:underline;\">click here</span></a>\n";
$ebody .= "														to sign up for our free weekly email updates.\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														Once again, thank you for your support.\n";
//$ebody .= "														<a href=\"http://www.hjta.org/\" target=\"_blank\" style=\"outline:none;text-decoration:none;\"><span style=\"color:#0062A6;text-decoration:underline;\">HJTA</span></a>.\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														Sincerely,<br />\n";
$ebody .= "														Jon Coupal<br />\n";
$ebody .= "														HJTA President\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "											\n";
$ebody .= "											<!--CTA-->\n";
$ebody .= "											<table width=\"250\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"CTA_1\">\n";
$ebody .= "												<tr valign=\"top\" align=\"center\">\n";
$ebody .= "													<td align=\"center\" valign=\"middle\" bgcolor=\"#2F576E\" style=\"font:bold 16px/20px Arial, Helvetica, sans-serif;color:#FFFFFF;padding:6px;border-radius:6px;-webkit-border-radius:6px;-moz-border-radius:6px;text-shadow:0 1px 2px #000000;\">\n";
$ebody .= "														<a href=\"http://www.hjta.org/e-mail-updates/\" target=\"_blank\" style=\"outline:none;text-decoration:none;\"><span style=\"color:#FFFFFF;text-decoration:none;\">FREE Weekly email updates</span></a>\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "											\n";
$ebody .= "										</td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "								<!--Main Body: end-->\n";
$ebody .= "								\n";
$ebody .= "								<!--Legal: start-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td style=\"font:normal 11px/15px Arial, Helvetica, sans-serif;color:#5B5B5B;padding:20px;\" align=\"left\">\n";
$ebody .= "											Copyright &copy; ".date("Y")." Howard Jarvis Taxpayers Association.\n";
$ebody .= "											All rights reserved. Howard Jarvis Taxpayers Association, <span class=\"appleLinksGrey\">621 South Westmoreland Avenue, Suite 200, Los Angeles, CA 90005</span>\n";
$ebody .= "											<br /><br />\n";
$ebody .= "										</td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "											\n";
$ebody .= "								\n";
$ebody .= "							</td>\n";
$ebody .= "							<td class=\"gutter\" width=\"10\"></td>\n";
$ebody .= "						</tr>\n";
$ebody .= "					</table>\n";
$ebody .= "					\n";
$ebody .= "				</td>\n";
$ebody .= "			</tr>\n";
$ebody .= "		</table>\n";
$ebody .= "	</div>\n";
$ebody .= "</div>\n";
$ebody .= "</body>\n";
$ebody .= "</html>\n";



return($ebody)	 ;
} // HJTF_Donation_Thanks()




// HTML EMAIL REPLY
function HJTF_Donation_Sorry($fname, $ccFailReason) {

$vHJTF_SDS_ROOT = HJTF_SDS_ROOT ;
$vHJTF_SD_ROOT  = HJTF_SD_ROOT ;

$ebody = '' ;

$ebody .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
$ebody .= "<html xmlns:v=\"urn:schemas-microsoft-com:vml\">\n";
$ebody .= "<head>\n";
$ebody .= "<meta name=\"HandheldFriendly\" content=\"false\" />\n";
$ebody .= "<meta name=\"viewport\" content=\"width=device-width; initial-scale=1; maximum-scale=1; minimum-scale=1; user-scalable=no;\" />\n";
$ebody .= "<title>Sorry, there has been an error with your donation attempt</title>\n";
$ebody .= "<style type=\"text/css\">\n";
$ebody .= ".ReadMsgBody {width:100%;}\n";
$ebody .= ".ExternalClass {width:100%;}\n";
$ebody .= "table td {border-collapse:collapse;}\n";
$ebody .= ".appleLinksGrey a {color:#5B5B5B; text-decoration: none;}\n";
$ebody .= "@media all and (max-width:540px) {\n";
$ebody .= "	table {width:100%;}\n";
$ebody .= "	table[class=\"wrapper\"] {width:100% !important;}\n";
$ebody .= "	table[class=\"hide\"], tr[class=\"hide\"], td[class=\"hide\"], img[class=\"hide\"] {display:none!important;}\n";
$ebody .= "	td[class=\"header\"] {padding:15px;font-size:12px !important;}\n";
$ebody .= "	img[class=\"fit\"] {width:100%; height:auto;}\n";
$ebody .= "	/*Spacing*/\n";
$ebody .= "	td[class=\"gutter\"] {width:10px !important;}\n";
$ebody .= "	td[class=\"padding15\"] {padding:15px;}\n";
$ebody .= "	/* CTA */\n";
$ebody .= "	table[class=\"CTA_1\"] {width:250px;}\n";
$ebody .= "}\n";
$ebody .= "</style>\n";
$ebody .= "</head>\n";
$ebody .= "<body bgcolor=\"#F6F6F6\" text=\"#000001\" link=\"#0000EE\" alink=\"#0000EE\" vlink=\"#551A8B\" style=\"margin-left:0px;margin-right:0px;margin-top:0px;padding:0px;\">\n";
$ebody .= "<div style=\"text-decoration:none;\">\n";
$ebody .= "	<div style=\"-webkit-text-size-adjust:none;\">\n";
$ebody .= "		<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\" bgcolor=\"#F6F6F6\" class=\"wrapper\">\n";
$ebody .= "			<tr valign=\"top\">\n";
$ebody .= "				<td width=\"100%\" align=\"center\" valign=\"top\">\n";
$ebody .= "\n";
$ebody .= "					<table width=\"620\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\" bgcolor=\"#F6F6F6\">\n";
$ebody .= "						<tr valign=\"top\">\n";
$ebody .= "							<td class=\"gutter\" width=\"10\"></td>\n";
$ebody .= "							<td align=\"center\" valign=\"top\">\n";
$ebody .= "\n";
$ebody .= "								<!--HEADER-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"center\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td height=\"45\" width=\"10\"></td>\n";
$ebody .= "										<td align=\"center\" valign=\"middle\" style=\"font:normal 12px/16px Arial, Helvetica, sans-serif;color:#5B5B5B;\" class=\"header\">\n";
$ebody .= "											Notice: Processing Error with your President Circle's Event Donation.\n";
$ebody .= "										</td>\n";
$ebody .= "										<td height=\"45\" width=\"10\"></td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "\n";
$ebody .= "								<!--TOP-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#213B5D\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td align=\"center\">\n";
$ebody .= "\n";
$ebody .= "											<!--Logo-->\n";
$ebody .= "											<table width=\"620\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\" style=\"mso-table-lspace:0;mso-table-rspace:0;\">\n";
$ebody .= "												<tr valign=\"top\">\n";
$ebody .= "													<td style=\"font:bold 12px/20px Arial, Helvetica, sans-serif;color:#FFFFFF;\" align=\"left\">\n";
$ebody .= "														<img src=\"https://donations.hjta.org/hjtf-presidents-circle/images/hjtf-presidents-circle-email-banner.jpg\" width=\"620\" alt=\"Howard Jarvis Taxpayers Association\" border=\"0\" style=\"display:block;\" class=\"fit\" />\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "\n";
$ebody .= "\n";
$ebody .= "										</td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "\n";
$ebody .= "								<!--Main Body: start-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#FFFFFF\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td style=\"padding:20px;\">\n";
$ebody .= "\n";
$ebody .= "											<!--Copy-->\n";
$ebody .= "											<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
$ebody .= "												<tr valign=\"top\">\n";
$ebody .= "													<td style=\"font:normal 14px/20px Verdana, Geneva, sans-serif;color:#5B5B5B;padding:15px 0 25px;\" align=\"left\">\n";
$ebody .= "														Dear $fname,\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														We sincerely appreciate your attendance at our President's Circle event in Granite Bay however we are sorry to inform you that we were unable to complete your donation to the Howard Jarvis Taxpayers Foundation due to a problem with your credit card information.\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														There are several reasons why this can happen, perhaps something as simple as mistyping a number.\n";
$ebody .= "														<br />\n";
$ebody .= "														Decline Reason : This transaction has been declined. (Code: $ccFailReason )\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														Please take a moment now to enter your donation and credit card information again at <a href=\"https://donations.hjta.org/hjtf-presidents-circle/donation.php?event=0024-0101\" target=\"_blank\" style=\"outline:none;text-decoration:none;\"><span style=\"color:#0062A6;text-decoration:underline;\">our website</span></a>. As you know, the Howard Jarvis Taxpayers Association relies on the Foundation to support litigations to protect Prop. 13 at the court level. We'd like to count on you to help us protect Prop. 13 and your taxpayer rights.\n";														
$ebody .= "														<br /><br />\n";
$ebody .= "														To return to our website, simply click on the button below.\n";
$ebody .= "														<br /><br />\n";
$ebody .= "														Thank you,<br />\n";
$ebody .= "														Howard Jarvis Taxpayers Association\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "\n";
$ebody .= "											<!--CTA-->\n";
$ebody .= "											<table width=\"250\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"CTA_1\">\n";
$ebody .= "												<tr valign=\"top\" align=\"center\">\n";
$ebody .= "													<td align=\"center\" valign=\"middle\" bgcolor=\"#2F576E\" style=\"font:bold 16px/20px Arial, Helvetica, sans-serif;color:#FFFFFF;padding:6px;border-radius:6px;-webkit-border-radius:6px;-moz-border-radius:6px;text-shadow:0 1px 2px #000000;\">\n";
$ebody .= "														<a href=\"http://www.hjta.org/\" target=\"_blank\" style=\"outline:none;text-decoration:none;\"><span style=\"color:#FFFFFF;text-decoration:none;\">Visit HJTA.org</span></a>\n";
$ebody .= "													</td>\n";
$ebody .= "												</tr>\n";
$ebody .= "											</table>\n";
$ebody .= "\n";
$ebody .= "										</td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "								<!--Main Body: end-->\n";
$ebody .= "\n";
$ebody .= "								<!--Legal: start-->\n";
$ebody .= "								<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n";
$ebody .= "									<tr valign=\"top\">\n";
$ebody .= "										<td style=\"font:normal 11px/15px Arial, Helvetica, sans-serif;color:#5B5B5B;padding:20px;\" align=\"left\">\n";
$ebody .= "											Copyright &copy; ".date("Y")." Howard Jarvis Taxpayers Association.\n";
$ebody .= "											All rights reserved. Howard Jarvis Taxpayers Association, <span class=\"appleLinksGrey\">621 South Westmoreland Avenue, Suite 200, Los Angeles, CA 90005</span>\n";
$ebody .= "											<br /><br />\n";
$ebody .= "										</td>\n";
$ebody .= "									</tr>\n";
$ebody .= "								</table>\n";
$ebody .= "\n";
$ebody .= "\n";
$ebody .= "							</td>\n";
$ebody .= "							<td class=\"gutter\" width=\"10\"></td>\n";
$ebody .= "						</tr>\n";
$ebody .= "					</table>\n";
$ebody .= "\n";
$ebody .= "				</td>\n";
$ebody .= "			</tr>\n";
$ebody .= "		</table>\n";
$ebody .= "	</div>\n";
$ebody .= "</div>\n";
$ebody .= "</body>\n";
$ebody .= "</html>";

return( $ebody)	 ;
} // eof: HJTF_Donation_Sorry()

?>
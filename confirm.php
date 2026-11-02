<?php
if (session_status() === PHP_SESSION_NONE){
    session_start();
    if (!isset($_COOKIE['session_name()'])) setcookie(session_name(), session_id(), 0);
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

require_once($_root."/common/Z_HJTFglobals.php");
require_once($_root."/common/connect_REAL.php");

require_once($_root."/email_groups/email_groups.php");
require_once($_root."/common/email_funcs.php");

//  set to the user defined error handler
//dbine.ntw:  Replaced with monologger.php $old_error_handler=set_error_handler("myErrorHandler");
date_default_timezone_set("America/Los_Angeles");

$sid =trim($_POST['sid']) ; // back to org method 

// get incoming via POST
// $visitorArgs
$visitor_Args=array();
// personal
$visitor_Args['sid']     =trim($_POST['sid']);
$visitor_Args['fname']   =ucfirst(strtolower(trim($_POST['FN'])));
$visitor_Args['lname']   =ucfirst(strtolower(trim($_POST['LN'])));
$visitor_Args['addr']    =trim($_POST['ADDR']);
$visitor_Args['addr2']   =trim($_POST['addr2']);
$visitor_Args['city']    =trim($_POST['CTY']);
$visitor_Args['state']   =trim($_POST['ST']);
$visitor_Args['zip']     =trim($_POST['ZIP']);
$visitor_Args['email']   =trim($_POST['EM']);
$visitor_Args['comments']=trim($_POST['COMMENTS']);
$visitor_Args['visitor_details']="Y";
// charge stuff
/// modification to accomodate Radio Buttons for Extra Donation  SAVE!!!
if (isset($_POST["donateRadioGroup"])):
    if ($_POST["donateRadioGroup"] == 'other'):
        $visitor_Args['contrib'] = $_POST['CONAMT'];
    else:
        $visitor_Args['contrib'] = $_POST["donateRadioGroup"];
    endif;
else:
    $visitor_Args['contrib'] = 0;
endif;
$visitor_Args['cc_name']   = $_POST['CNAME'];
$visitor_Args['cc_number'] = $_POST['CN'];
$visitor_Args['cc_type']   = strtoupper($_POST['CT']);
$visitor_Args['cc_last5']  = substr($_POST['CN'], -4); // changed to last 4
$visitor_Args['cc_month']  = $_POST['CD1'];
$visitor_Args['cc_year']   = $_POST['CD2'];
$visitor_Args['cc_cvv']    = trim($_POST['CVV']);




///////////////// REGULAR CREDIT CARD CHARGING PROCESSING STARTS HERE 
setlocale(LC_MONETARY, 'en_US');
$to_charge = '$ ' . sprintf("%01.2f", $visitor_Args['contrib'] ) ;

/// tracer
hjtaorgLog( 'Stage 01 ' , "Confirm form $sid" , __FILE__ . ' Line: ' . __LINE__ , "HJTF");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HJTF - President's Circle</title>
<link rel="icon" href="/global/images/hjta-favicon.ico">
<link href='https://fonts.googleapis.com/css?family=EB Garamond' rel='stylesheet'>
<script type='text/javascript' src='https://hjta.org/wp-includes/js/jquery/jquery.min.js?ver=3.7.0' id='jquery-core-js'></script>
<script type='text/javascript' src='https://hjta.org/wp-includes/js/jquery/jquery-migrate.min.js?ver=3.4.1' id='jquery-migrate-js'></script>
<style>
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
#form-content fieldset#totals {
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
			<div id="thank-you-text-wrap"><p>...please confirm your donation amount below.</p>
			<p>NOTE: Contributions or gifts to the Howard Jarvis Taxpayers Foundation are deductible from your federal income tax and may also be deductible from your state income tax. Howard Jarvis Taxpayers Foundation Tax ID: 52-1155794</p></div>
		</div>
		<div id="form-wrap">
			<div id="form-header">
				<div class="form-content-header left"><h1><span class="h1-smaller">Secure Online</span><br />Donation Form</h1></div>
				<div class="form-content-header right"><img src="images/HJTF-white.png" width="350" /></div>
			</div>
			<div id="form-content">
				<form name="confirm_charge_form" action="complete.php" method="post" onsubmit="return process(this)">
		<fieldset id="totals" name="totals">
					<div id="totals-wrap">
					    <h2 class="pageTitle">Please Confirm</h2>
						<h3 style="padding-bottom:6px;">Your card will be charged the following:</h3>
						<strong id="totals-label"><span><?php echo( $to_charge ) ; ?></span> <span class="tLabel">Donation</span></strong>
						<p class="warning"><span class="important">IMPORTANT:</span><br>Authorizing might take 30 seconds.<br>Please press "Confirm" <u>ONLY</u> once.</p>
					</div>
				</fieldset>
        
        <fieldset id="buttons" name="buttons">
        <input type="hidden" id="sid"        name="sid"        value="<?php echo $visitor_Args['sid'] ?>">
        <input type="hidden" id="fname"      name="fname"      value="<?php echo $visitor_Args['fname'] ?>">
        <input type="hidden" id="lname"      name="lname"      value="<?php echo $visitor_Args['lname'] ?>">
        <input type="hidden" id="addr"       name="addr"       value="<?php echo $visitor_Args['addr'] ?>">
        <input type="hidden" id="addr2"      name="addr2"      value="<?php echo $visitor_Args['addr2'] ?>">
        <input type="hidden" id="city"       name="city"       value="<?php echo $visitor_Args['city'] ?>">
        <input type="hidden" id="state"      name="state"      value="<?php echo $visitor_Args['state'] ?>">
        <input type="hidden" id="zip"        name="zip"        value="<?php echo $visitor_Args['zip'] ?>">
        <input type="hidden" id="email"      name="email"      value="<?php echo $visitor_Args['email'] ?>">
        <input type="hidden" id="comments"   name="comments"   value="<?php echo $visitor_Args['comments'] ?>">        
        <input type="hidden" id="cc_name"    name="cc_name"    value="<?php echo $visitor_Args['cc_name'] ?>">
        <input type="hidden" id="cc_number"  name="cc_number"  value="<?php echo $visitor_Args['cc_number'] ?>">
        <input type="hidden" id="cc_type"    name="cc_type"    value="<?php echo $visitor_Args['cc_type'] ?>">
        <input type="hidden" id="cc_last5"   name="cc_last5"   value="<?php echo $visitor_Args['cc_last5'] ?>">
        <input type="hidden" id="cc_month"   name="cc_month"   value="<?php echo $visitor_Args['cc_month'] ?>">
        <input type="hidden" id="cc_year"    name="cc_year"    value="<?php echo $visitor_Args['cc_year'] ?>">
        <input type="hidden" id="cc_cvv"     name="cc_cvv"     value="<?php echo $visitor_Args['cc_cvv']  ?>">
        <input type="hidden" id="contrib"    name="contrib"    value="<?php echo $visitor_Args['contrib'] ?>">
          
       
         <!-- buttons -->
		<input id="donation-submit" name="submit" value="Confirm" type="submit">
		
		</fieldset>
        </form>
			<div>
				<p>If you are using a VPN (virtual private network) connection, you may receive an error message when connecting to our payment processor. Please try again with the VPN turned off, if possible. We apologize for the inconvenience.</p>
			</div>
			</div>			
		</div>
	</div>
</div>
<script type='text/javascript' src='/global/scripts/main-script.js?v=14' id='main-scripts-js'></script>
</body>
</html>
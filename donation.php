<?php
$_root  =$_SERVER['DOCUMENT_ROOT'];
require_once($_root."/common/ntw-session.php");
//Remove the LIVE_TESTING indicator if exists in this session because this is a live page; not a test page:
if (isset($_SESSION['LIVE_TESTING'])) { unset($_SESSION['LIVE_TESTING']); }

require_once($_SERVER['DOCUMENT_ROOT']."/common/monologger.php");
//get_logger()->info('monologger verification', array('Called from' => __FILE__));

// used to resolve IE https IFRAME issue
header('P3P: CP="CAO PSA OUR"'); // test 4/12/2011

$_root  =$_SERVER['DOCUMENT_ROOT'];

require_once($_root."/common/utils.php");
require_once($_root."/common/ezMysql_ntw.php");

//////////////////////////////////
//
//	Get passed parameters
//
if (isset($_REQUEST['event'])) :
 list($cust, $event)=explode("-", $_REQUEST['event']);
else :
  // redirect to HJTA.org 
  header("Location: http://www.hjta.org/take-action/");
  exit(502);  
endif;

// REQUIRED email files
require_once($_root."/email_groups/email_groups.php");
require_once($_root."/common/email_funcs.php");

require_once($_root."/common/Z_HJTFglobals.php");
require_once($_root."/common/connect_REAL.php");


///////////////////////////////
//
//  Manage error reporting
//   added php 5 support for  E_STRICT
ini_set("error_reporting", E_ALL | E_STRICT & ~E_NOTICE & ~E_DEPRECATED  );


// init stuff
//dbine.ntw:  Replaced with monologger.php $error_handler=set_error_handler("myErrorHandler");
date_default_timezone_set("America/Los_Angeles");

$mytime  = time();
$now     = date("Y-m-d H:i:s", $mytime);
$ip      = $_SERVER["REMOTE_ADDR"];

////////////////// session id

session_regenerate_id();
session_destroy();
if(isset($_SESSION['sid'])) :
  $_SESSION = array();
  unset($_SESSION);
endif ;
if (session_status() === PHP_SESSION_NONE){
	session_start();
}
$sid   = session_id();
$sid = $sid . '----' . $mytime ;
$_SESSION['sid'] = $sid ;


//////////////////////////////////////////////////////////////////////////////////////
////// NEW Event & cust by database lookup
////// JUN 2017
$ska         = 0 ;
//$event      = ;
$visittype   = 'donate' ;      // because this is DONATE page
//////////////////////////////////////////////////////////////////////////////////////

/// tracer
hjtaorgLog( 'BEGIN Stage 0' , "Init write & form $sid" , __FILE__ . ' Line: ' . __LINE__ , "HJTF");

//	Do Database stuff
/// SET DATA CONNECTION
$ez    =new ezMysql_ntw();

// ARE THEY APPROVED EVENT & CUSTOMER CODES?
$search = "event='" . $event . "'" ;
$result=$ez->ezSelect_i("*", 'don2hjta_approved_codes' , $search );
$numb_rows = mysqli_num_rows($result);

// IF NOT APPROVED 
// REDIRECT 
if ( $numb_rows == 0): // search does not exist in database
   /// tracer
   // log code re-direct tracer not working ? 6/29/21 ???? debug another time
//   hjtaorgLog( 'BEGIN Stage 00' , "Redirect. Failed event code of: ( $event ) tried" , __FILE__ . ' Line: ' . __LINE__ , "HJTF");
  // redirect to HJTA.org 
  header("Location: https://www.hjta.org/take-action/");
  //exit(502);
  exit(0) ;
endif ;

/// SET session for future links
$_SESSION['cust']   = $cust   ;
$_SESSION['event']  = $event ;


//	Do Database stuff
//
$sid_condition = "sid='".$sid."'" ;
$result=$ez->ezSelect_i("*", VISIT_TBL , $sid_condition );
$numb_rows = mysqli_num_rows($result);
  
if ( $numb_rows == 0): // SID does not exist in database
    $mytime             =time();
    $now                =date("Y-m-d H:i:s", $mytime);
    $myArgs=array();
    $myArgs['sid']      =$sid;
    $myArgs['customer'] =$cust;
    $myArgs['event']    =$event;
    $myArgs['visittype']=$visittype;
    $myArgs['visitdate']=$now;
    $myArgs['ska']      =$ska ;
    
	// original IP capture method (not reliable behind proxies)
	//$myArgs['myIP']     = $_SERVER['REMOTE_ADDR'];

	// New IP capture method to get the original client IP behind proxies (Sucuri, GoDaddy, etc.)
	if (!function_exists('is_trusted_network')) {
		function is_trusted_network($ip, $cidr) {
			list($subnet, $bits) = explode('/', $cidr);
			$ip_long = ip2long($ip);
			$subnet_long = ip2long($subnet);
			$mask = -1 << (32 - $bits);
			$subnet_long &= $mask;
			return ($ip_long & $mask) == $subnet_long;
		}
	}

	$remote_ip = $_SERVER['REMOTE_ADDR'];

	// Known trusted network ranges for your setup
	$trusted_proxies = [
		'185.93.228.0/22',   // Sucuri
		'192.124.249.0/24',  // Sucuri
		'192.155.224.0/22',  // Sucuri
		'193.124.99.0/24',   // Sucuri
		'198.20.87.0/24',    // Sucuri
		'208.109.0.0/16'     // GoDaddy Data Center Backbone (covers your 208.109.x.x IPs)
	];

	$is_proxy_connection = false;
	foreach ($trusted_proxies as $range) {
		if (is_trusted_network($remote_ip, $range)) {
			$is_proxy_connection = true;
			break;
		}
	}

	// Extract the customer IP securely
	if ($is_proxy_connection) {
		
		// 1. Check for WP Engine's native visitor tracker header (passed along from the main domain routing)
		if (!empty($_SERVER['HTTP_WPE_CLUSTER_TYPE'])) {
			// WP Engine standardizes client tracking in HTTP_X_SERVER_IP or standard HTTP_X_FORWARDED_FOR
			$header_to_check = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['HTTP_X_SERVER_IP'] ?? '');
		} else {
			// 2. Fall back to standard forward header passed by Sucuri/GoDaddy routing
			$header_to_check = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
		}

		if (!empty($header_to_check)) {
			// Multi-proxy chains look like: "CustomerIP, WPengineProxyIP, SucuriProxyIP"
			// The first IP listed on the far left is always the original end-user client.
			$ip_chain = explode(',', $header_to_check);
			$candidate_ip = trim($ip_chain[0]);

			if (filter_var($candidate_ip, FILTER_VALIDATE_IP)) {
				$customer_ip = $candidate_ip;
			} else {
				$customer_ip = $remote_ip;
			}
		} else {
			$customer_ip = $remote_ip;
		}
	} else {
		// Direct, un-proxied connection
		$customer_ip = $remote_ip;
	}

	$myArgs['myIP']     = $customer_ip;
    
	$this_query         =$ez->ezInsert_i( VISIT_TBL , $myArgs, "SET");
    if ($this_query) :
      // OK
    else :
       // no INSERT
       $errmsg =  "Failed INSERT into " . VISIT_TBL ;
       // Send all people individually until looping / array method
       $toList = $DR_ADM1 ;
       $lName  = $toList[0][name];
       $lEmail = $toList[0][email];
       $sendto =  $lEmail  ;
       // The SEND
       $rvOK = TRUE ;
       if ($rvOK) :
          //$rvOK =  vEmail ($sendto, 'DR' , "NOTE - Failed INSERT into " . VISIT_TBL , $errmsg ) ;  
          $rvOK =  zEmail ($sendto,  "NOTE - Failed INSERT into " . VISIT_TBL , $errmsg ) ;  
       endif ;
       // DEBUG TRACER
       hjtaorgDBUG( 'STAGE 0 SQL err ' , "Failed INSERT"  . ' ' . $errmsg , __FILE__ . ' Line: ' . __LINE__ , "HJTF");
       // EXCEPTION PATH TODO :???
       // BETTER GO ELSEWHERE
       header("Location: https://www.hjta.org/take-action/");
       exit();       
    endif ;

else : //  SID already exists in the database
    $mess=" Duplicate SID $sid found";
    $src = __FILE__ . ' Line: ' . __LINE__ ;
    //Logit( $mess, '', $src ) ;
    // err_email( $mess , $src ) ; // todo convert to extended zEmail()
    //
    session_regenerate_id();
    session_destroy();
    if (session_status() === PHP_SESSION_NONE){
        session_start();
    }
    $sid   = session_id();
    $sid   = $mytime.'----'.$sid."----".$mytime ;
    $_SESSION['sid'] = $sid ;

    $myArgs=array();
    $myArgs['sid']       = $sid ;
    $myArgs['sid_stat']  = "duplicate";
    $myArgs['customer']  = $cust;
    $myArgs['event']     = $event;
    $myArgs['visittype'] = $visittype;
    $myArgs['visitdate'] = $now;
    $myArgs['ska']       = $ska;
    $myArgs['myIP']      = $ip;

    $this_query = $ez->ezInsert_i( VISIT_TBL , $myArgs, "SET");
    if ($this_query) :
      // OK
    else :
       // no INSERT
       $errmsg =  "Failed INSERT into " . VISIT_TBL  ;
       // Send all people individually until looping / array method
       $toList = $DR_ADM1 ;
       $lName  = $toList[0][name];
       $lEmail = $toList[0][email];
       $sendto =  $lEmail  ;
       // The SEND
       $rvOK = TRUE ;
       if ($rvOK) :
          //$rvOK =  vEmail ($sendto, 'DR' , "NOTE - Failed INSERT into " . VISIT_TBL , $errmsg ) ;
          $rvOK =  zEmail ($sendto,  "NOTE - Failed INSERT into " . VISIT_TBL , $errmsg ) ;          
       endif ;
       // DEBUG TRACER
       hjtaorgDBUG( 'STAGE 0 SQL err ' , "Failed INSERT"  . ' ' . $errmsg , __FILE__ . ' Line: ' . __LINE__ , "HJTF");
       // EXCEPTION PATH TODO :???

       // do NOT proceed
       // redirect to HJTA.org 
       header("Location: https://www.hjta.org/take-action/");
       exit(502) ;
    endif ;
endif ;
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
			<div id="thank-you-text-wrap"><p>...for attending our President Circle's House Event to Protect Proposition 13. We sincerely appreciate any support as you consider making your tax-deductible donation to HJTF today!</p>
			<p>NOTE: Contributions or gifts to the Howard Jarvis Taxpayers Foundation are deductible from your federal income tax and may also be deductible from your state income tax. Howard Jarvis Taxpayers Foundation Tax ID: 52-1155794</p></div>
		</div>
		<div id="form-wrap">
			<div id="form-header">
				<div class="form-content-header left"><h1><span class="h1-smaller">Secure Online</span><br />Donation Form</h1></div>
				<div class="form-content-header right"><img src="images/HJTF-white.png" width="350" /></div>
			</div>
			<div id="form-content">
				<!--<h3>Donations are currently unavailable</h3>-->
				<form action="confirm.php" method="post" class="joinform" id="joinform" onsubmit="return (ntwSet() &amp;&amp; FrontPage_Form2_Validator(this) ? true : ntwReset())" name="FrontPage_Form2">
			    <input type="hidden" name="sid" id="sid" value="<?php echo($sid); ?>">
				<div class="formgroup">
					<fieldset id="donateAmount" name="donateAmount">
						<h3>Choose Donation Amount:</h3>
						<div class="donationAmount left">
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup10" value="10">$10</label>
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup15" value="15" checked="checked">$15</label>
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup25" value="25">$25</label>
						</div>
						<div class="donationAmount right">
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup50" value="50">$50</label>
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup100" value="100">$100</label>
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup250" value="250">$250</label>
						</div>
						<div class="last">
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup500" value="500">$500</label>
							<label class="option"><input type="radio" name="donateRadioGroup" id="donateRadioGroup1000" value="1000">$1,000</label>
							<span class="other">
								<label class="option other"><input type="radio" name="donateRadioGroup" id="otherRadio" value="other">Other Amount: <span id="donationDollarSign">$&nbsp;</span></label>
								<input type="text" id="CONAMT" name="CONAMT" size="12" class="formfield" value="" data-min="5">
								<span class="disclaim">*Minimum $5 donation</span>
							</span>
						</div>
					</fieldset>
				</div>
				<div class="formgroup">
					<fieldset id="contactInfo" name="contactInfo">
						<h3>My Contact Information: <span class="disclaim">*required fields</span></h3>
						<div class="formitem">
							<label for="FN">First Name:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="FN" name="FN" size="35" maxlength="64" class="formfield" value="">
						</div>
						<div class="formitem">
							<label for="LN">Last Name:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="LN" name="LN" size="35" maxlength="64" class="formfield" value="">
						</div>
						<div class="formitem">
							<label for="ADDR">Address 1:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="ADDR" name="ADDR" size="35" class="formfield" value="">
						</div>
						<div class="formitem">
							<label for="addr2">Address 2:</label>
							<input type="text" id="addr2" name="addr2" size="35" class="formfield" value="">
						</div>
						<div class="formitem">
							<label for="CTY">City:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="CTY" name="CTY" size="35" class="formfield" value="">
						</div>
						<div class="formitem">
							<label for="ST">State:<span class="form-required" title="This field is required.">*</span></label>
							<select id="ST" name="ST" size="1" class="formfield" value="">
								<option value="AL">Alabama</option>
								<option value="AK">Alaska</option>
								<option value="AS">American Samoa</option>
								<option value="AZ">Arizona</option>
								<option value="AR">Arkansas</option>
								<option value="CA" selected="selected">California</option>
								<option value="CO">Colorado</option>
								<option value="CT">Connecticut</option>
								<option value="DE">Delaware</option>
								<option value="DC">District of Comumbia</option>
								<option value="FL">Florida</option>
								<option value="GA">Georgia</option>
								<option value="GU">Guam</option>
								<option value="HI">Hawaii</option>
								<option value="ID">Idaho</option>
								<option value="IL">Illinois</option>
								<option value="IN">Indiana</option>
								<option value="IA">Iowa</option>
								<option value="KS">Kansas</option>
								<option value="KY">Kentucky</option>
								<option value="LA">Louisiana</option>
								<option value="ME">Maine</option>
								<option value="MD">Maryland</option>
								<option value="MA">Massachusetts</option>
								<option value="MI">Michigan</option>
								<option value="MN">Minnesota</option>
								<option value="MO">Missouri</option>
								<option value="MS">Mississippi</option>
								<option value="MT">Montana</option>
								<option value="NE">Nebraska</option>
								<option value="NV">Nevada</option>
								<option value="NH">New Hampshire</option>
								<option value="NJ">New Jersey</option>
								<option value="NM">New Mexico</option>
								<option value="NY">New York</option>
								<option value="NC">North Carolina</option>
								<option value="ND">North Dakota</option>
								<option value="OH">Ohio</option>
								<option value="OK">Oklahoma</option>
								<option value="OR">Oregon</option>
								<option value="PA">Pennsylvania</option>
								<option value="PR">Puerto Rico</option>
								<option value="RI">Rhode Island</option>
								<option value="SC">South Carolina</option>
								<option value="SD">South Dakota</option>
								<option value="TN">Tennessee</option>
								<option value="TX">Texas</option>
								<option value="UT">Utah</option>
								<option value="VT">Vermont</option>
								<option value="VA">Virginia</option>
								<option value="VI">Virgin islands</option>
								<option value="WA">Washington</option>
								<option value="WV">West Virginia</option>
								<option value="WI">Wisconsin</option>
								<option value="WY">Wyoming</option>
							</select>
						</div>
						<div class="formitem">
							<label for="ZIP">ZIP Code:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="ZIP" name="ZIP" size="12" class="formfield" value="">
						</div>
						<div class="formitem">
							<label for="EM">Email Address:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="EM" name="EM" size="35" class="formfield" value="">
						</div>
						<div class="formitem">
							<label for="DP">Phone Number:</label>
							<input type="text" id="DP" name="DP" size="35" class="formfield" value="">
						</div>
						<div class="formitem">
							<label class="option"><input type="checkbox" id="ASUB" name="ASUB" value="ON" class="form-checkbox" checked="checked">Sign me up for the email list for HJTA updates.</label>
						</div>
					</fieldset>
					<fieldset id="comment" name="comment">
						<div class="formitem">
							<label for="COMMENTS">Comments:</label>
							<textarea rows="10" id="COMMENTS" name="COMMENTS" cols="27" class="formfield" value=""></textarea>
							<span class="description">(For security reasons, only letters, commas and periods can be entered in the comment field.<br/>Other characters will cause an error message to appear.)</span>
						</div>
					</fieldset>
					<fieldset id="billingInfo" name="billingInfo">
						<h3>My Billing Information: <span class="disclaim">*required fields</span></h3>
						<div class="formitem">
							<label for="CT">Payment Type:<span class="form-required" title="This field is required.">*</span></label>
							<select size="1" id="CT" name="CT" class="formfield">
								<option selected="selected">Select one:</option>
								<option>Mastercard</option>
								<option>Visa</option>
								<option>Discover</option>
								<option>American Express</option>
							
							</select>

						</div>
						<div class="formitem">
							<label for="CNAME">Name on Card:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="CNAME" name="CNAME" size="35" value="" maxlength="64" class="formfield">
						</div>
						<div class="formitem">
							<label for="CN">Card Number:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="CN" name="CN" size="35" maxlength="32" class="formfield">
							<span class="description">Do not use dashes or spaces between numbers.</span>
						</div>
						<div class="formitem">
							<label for="CD1">Exp. Date:<span class="form-required" title="This field is required.">*</span></label>
							<select size="1" id="CD1" name="CD1" class="formfield">
								<option selected="selected">Month</option>
								<option>01</option>
								<option>02</option>
								<option>03</option>
								<option>04</option>
								<option>05</option>
								<option>06</option>
								<option>07</option>
								<option>08</option>
								<option>09</option>
								<option>10</option>
								<option>11</option>
								<option>12</option>
							</select> &nbsp;
							<select size="1" id="CD2" name="CD2" class="formfield">
								<option selected="selected">Year</option>								
								<option>24</option><option>25</option><option>26</option><option>27</option><option>28</option><option>29</option><option>30</option><option>31</option>							</select>
						</div>
						<div class="formitem">
							<label for="CVV">CVV Code:<span class="form-required" title="This field is required.">*</span></label>
							<input type="text" id="CVV" name="CVV" size="3" value="" maxlength="4" class="formfield">
							<img src="https://donations.HJTA.org/Zdon2HJTF/images/cvv_generic.jpg" width="120" height="65" alt="cvv">
							
							
						</div>
					</fieldset>
				</div>
				<fieldset id="totals" name="totals">
					<div id="totals-wrap">
						<h3 style="padding-bottom:6px;">Your card will be charged the following:</h3>
						<strong id="totals-label">$<span id="donationValue">0</span> <span class="tLabel">Donation</span></strong>
						<p class="warning"><span class="important">IMPORTANT:</span><br>Authorizing might take 30 seconds.<br>Please press "Submit" <u>ONLY</u> once.</p>
					</div>
				</fieldset>
				<fieldset id="buttons" name="buttons">
					<input id="donation-submit" name="submit" value="Submit" type="submit">
					<input id="donation-cancel" name="cancel" value="Cancel" type="reset">					
					
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
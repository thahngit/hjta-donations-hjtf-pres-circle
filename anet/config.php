<?php

  // will be config.php for HJTA account


   // includes - required files
   $_root  =$_SERVER['DOCUMENT_ROOT'];

   // 10/20/2014
   require dirname(__FILE__) . '/AuthorizeNet.php' ;




$METHOD_TO_USE = "AIM";



// REAL HJTx MODIFIED ANET TO USB PROCESSOR 04/08/2018
define("AUTHORIZENET_API_LOGIN_ID","5d8wCxLNt65");    // Add your API LOGIN ID
define("AUTHORIZENET_TRANSACTION_KEY","9R84q4Y687ddh7WW"); // from 03/17/2014
define("AUTHORIZENET_SANDBOX",false);       // TRUE goes to FAKE MERCHANT ACCOUNT ONLY.

//TRUE goes to real account.
// put live account into TEST MODE to test it there
//define("TEST_REQUEST", "TRUE");           // You may want to set to true if testing against production


// BLAKE FAKE TEST ACCOUNT
//define("AUTHORIZENET_API_LOGIN_ID","7Xw6vhR3P");    // Add your API LOGIN ID
//define("AUTHORIZENET_TRANSACTION_KEY"," 4gDyW9BA852a85vT"); // 04/08/2018
//define("AUTHORIZENET_SANDBOX",true);       // added 04/18/2018 
//define("AUTHORIZENET_SANDBOX",true);       // Set to false to test against production
//define("TEST_REQUEST", "TRUE");           // You may want to set to true if testing against production





?>

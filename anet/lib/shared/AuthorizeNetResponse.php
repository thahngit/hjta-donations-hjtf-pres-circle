<?php
/**
 * Base class for the AuthorizeNet AIM & SIM Responses.
 *
 * @package    AuthorizeNet
 * @subpackage    AuthorizeNetResponse
 */


/**
 * Parses an AuthorizeNet Response.
 *
 * @package AuthorizeNet
 * @subpackage    AuthorizeNetResponse
 */
class AuthorizeNetResponse
{

    const APPROVED = 1;
    const DECLINED = 2;
    const ERROR = 3;
    const HELD = 4;
    
    public $approved;
    public $declined;
    public $error;
    public $held;
    public $response_code;
    public $response_subcode;
    public $response_reason_code;
    public $response_reason_text;
    public $error_message;
    public $authorization_code;
    public $avs_response;
    public $transaction_id;
    public $invoice_number;
    public $description;
    public $amount;
    public $method;
    public $transaction_type;
    public $customer_id;
    public $first_name;
    public $last_name;
    public $company;
    public $address;
    public $city;
    public $state;
    public $zip_code;
    public $country;
    public $phone;
    public $fax;
    public $email_address;
    public $ship_to_first_name;
    public $ship_to_last_name;
    public $ship_to_company;
    public $ship_to_address;
    public $ship_to_city;
    public $ship_to_state;
    public $ship_to_zip_code;
    public $ship_to_country;
    public $tax;
    public $duty;
    public $freight;
    public $tax_exempt;
    public $purchase_order_number;
    public $md5_hash;
    public $card_code_response;
    public $cavv_response; // cardholder_authentication_verification_response
    public $account_number;
    public $card_type;
    public $split_tender_id;
    public $requested_amount;
    public $balance_on_card;
    public $response; // The response string from AuthorizeNet.
    public $version;
    public $avs_code;
    public $user_ref;
    public $card_num;
    public $test_mode;
    public $ref_trans_id;
    public $approved_amount;
    public $card_balance;
    public $xml;

    private $_custom_fields = array();

    public function __set($name, $value)
    {
        $this->_custom_fields[$name] = $value;
    }

    public function __get($name)
    {
        return array_key_exists($name, $this->_custom_fields) ? $this->_custom_fields[$name] : null;
    }

    public function __isset($name)
    {
        return isset($this->_custom_fields[$name]);
    }

    public function __unset($name)
    {
        unset($this->_custom_fields[$name]);
    }

}

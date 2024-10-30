<?php
/**
 * Gateway API related functions and the functions are used to the WC_Merchantx_Gateway.
 * @package MerchantX
 * @since   1.0.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class merchantx_payments{
    
  private $endpoint = 'https://secure.networkmerchants.com/api/transact.php';
  /**
   * Set authentication details 
   * @param string $username
   * @param string $password
   */
  function setAuth($username, $password) {
    $this->login['username'] = $this->escap_string(trim($username));
    $this->login['password'] = $this->escap_string(trim($password));
   
  }

  /**
   * Set payloads for payment & append additional params here if required
   * @param int $orderid
   * @param string $orderdescription
   * @param float $tax
   * @param int $shipping
   * @param int $ponumber
   * @param string $ipaddress
   */

  function setParams($orderid,
        $orderdescription,
        $tax,
        $shipping,
        $ponumber,
        $ipaddress) {
    $this->order['orderid']          = $this->escap_string($orderid);
    $this->order['orderdescription'] = $this->escap_string($orderdescription);
    $this->order['tax']              = $this->escap_string($tax);
    $this->order['shipping']         = $this->escap_string($shipping);
    $this->order['ponumber']         = $this->escap_string($ponumber);
    $this->order['ipaddress']        = $this->escap_string($ipaddress);
  }

  /**
   * Payloads for billing address
   * @param string $firstname
   * @param string $lastname
   * @param string $company
   * @param string $address1
   * @param string $address2
   * @param string $city
   * @param string $state
   * @param string $zip
   * @param string $country
   * @param string $phone
   * @param string $fax
   * @param string $email
   * @param string $website
   */
  function setBillingAddress($firstname,
        $lastname,
        $company,
        $address1,
        $address2,
        $city,
        $state,
        $zip,
        $country,
        $phone,
        $fax,
        $email,
        $website) {
    $this->billing['firstname'] = $this->escap_string($firstname);
    $this->billing['lastname']  = $this->escap_string($lastname);
    $this->billing['company']   = $this->escap_string($company);
    $this->billing['address1']  = $this->escap_string($address1);
    $this->billing['address2']  = $this->escap_string($address2);
    $this->billing['city']      = $this->escap_string($city);
    $this->billing['state']     = $this->escap_string($state);
    $this->billing['zip']       = $this->escap_string($zip);
    $this->billing['country']   = $this->escap_string($country);
    $this->billing['phone']     = $this->escap_string($phone);
    $this->billing['fax']       = $this->escap_string($fax);
    $this->billing['email']     = $this->escap_string(sanitize_email($email));
    $this->billing['website']   = $this->escap_string($website);
  }

  /**
   * Get payloads for billing details
   * @return array
   */
  function getBillingAddress(){

    return array(
      'firstname' => $this->billing['firstname'],
      'lastname' => $this->billing['lastname'],
      'company' => $this->billing['company'],
      'address1' => $this->billing['address1'],
      'address2' => $this->billing['address2'],
      'city' => $this->billing['city'],
      'state' => $this->billing['state'],
      'zip' => $this->billing['zip'],
      'country' => $this->billing['country'],
      'phone' => $this->billing['phone'],
      'fax' => $this->billing['fax'],
      'email' => $this->billing['email'],
      'website' => $this->billing['website']
    );
  }

  /**
   * Payloads for shipping address
   * @param string $firstname
   * @param string $lastname
   * @param string $company
   * @param string $address1
   * @param string $address2
   * @param string $city
   * @param string $state
   * @param string $zip
   * @param string $country
   * @param string $email 
   */
  function setShippingAddress($firstname,
        $lastname,
        $company,
        $address1,
        $address2,
        $city,
        $state,
        $zip,
        $country,
        $email) {
    $this->shipping['firstname'] = $this->escap_string($firstname);
    $this->shipping['lastname']  = $this->escap_string($lastname);
    $this->shipping['company']   = $this->escap_string($company);
    $this->shipping['address1']  = $this->escap_string($address1);
    $this->shipping['address2']  = $this->escap_string($address2);
    $this->shipping['city']      = $this->escap_string($city);
    $this->shipping['state']     = $this->escap_string($state);
    $this->shipping['zip']       = $this->escap_string($zip);
    $this->shipping['country']   = $this->escap_string($country);
    $this->shipping['email']     = $this->escap_string(sanitize_email($email));
  }

  /**
   * Get payloads for shipping address
   * @return array
   */
  function getShippingAddress() {

    return array(
      'shipping_firstname' => $this->shipping['firstname'],
      'shipping_lastname' => $this->shipping['lastname'],
      'shipping_company' => $this->shipping['company'],
      'shipping_address1' => $this->shipping['address1'],
      'shipping_address2' => $this->shipping['address2'],
      'shipping_city' => $this->shipping['city'],
      'shipping_state' => $this->shipping['state'],
      'shipping_zip' => $this->shipping['zip'],
      'shipping_country' => $this->shipping['country'],
      'shipping_email' => $this->shipping['email']
    );
  }

  /**
   * final payload for transaction & call the curl function for final payment
   * @param float $amount
   * @param int $ccnumber
   * @param int $ccexp
   * @param int $cvv
   * @return array
   */
  function doSale($amount, $ccnumber, $ccexp, $cvv="",$vault_id=NULL) {

    $payload = array(
      'type' =>'sale',
      'customer_vault' => 'add_customer',
      'customer_vault_id' => $vault_id,
      'ccnumber' => $ccnumber,
      'ccexp' => $ccexp,
      'amount' => number_format($amount,2,".",""),
      'cvv' => $cvv,
      'ipaddress' =>$this->order['ipaddress'],
      'orderid' =>$this->order['orderid'],
      'orderdescription' =>$this->order['ipaddress'],
      'tax' =>number_format($this->order['tax'],2,".",""),
      'shipping' =>number_format($this->order['shipping'],2,".",""),
      'ponumber' =>$this->order['ponumber']
    );

    // get billing details
    $payload =array_merge($payload,$this->getBillingAddress());
    // get shipping details
    $payload =array_merge($payload,$this->getShippingAddress());
    return $this->httpRequest(http_build_query($payload));
  }

  /**
   * Do authorize and make the payment
   * @param float $amount
   * @param int $ccnumber
   * @param int $ccexp
   * @param int $cvv
   * @return array
   */
  function doAuth($amount, $ccnumber, $ccexp, $cvv="",$vault_id=0) {

    $payload = array(
      'type' =>'auth',
      'customer_vault' => 'add_customer',
      'customer_vault_id' => $vault_id,
      'ccnumber' => $ccnumber,
      'ccexp' => $ccexp,
      'amount' => number_format($amount,2,".",""),
      'cvv' => $cvv,
      'ipaddress' =>$this->order['ipaddress'],
      'orderid' =>$this->order['orderid'],
      'orderdescription' =>$this->order['ipaddress'],
      'tax' =>number_format($this->order['tax'],2,".",""),
      'shipping' =>number_format($this->order['shipping'],2,".",""),
      'ponumber' =>$this->order['ponumber']
    );

    // get billing details
    $payload =array_merge($payload,$this->getBillingAddress());
    // get shipping details
    $payload =array_merge($payload,$this->getShippingAddress());
    return $this->httpRequest(http_build_query($payload));
  }

  /**
   * Do Credit
   * @param float $amount
   * @param int $ccnumber
   * @param int $ccexp
   * @return array
   */
  function doCredit($amount, $ccnumber, $ccexp) {

    $payload = array(
      'type' =>'credit',
      'ccnumber' => $ccnumber,
      'ccexp' => $ccexp,
      'amount' => number_format($amount,2,".",""),
      'cvv' => $cvv,
      'ipaddress' =>$this->order['ipaddress'],
      'orderid' =>$this->order['orderid'],
      'orderdescription' =>$this->order['ipaddress'],
      'tax' =>number_format($this->order['tax'],2,".",""),
      'shipping' =>number_format($this->order['shipping'],2,".",""),
      'ponumber' =>$this->order['ponumber']
    );

    // get billing details
    $payload =array_merge($payload,$this->getBillingAddress());
    // get shipping details
    $payload =array_merge($payload,$this->getShippingAddress());
    return $this->httpRequest(http_build_query($payload));
  }

  /**
   * method for offline payment
   * @param type $authorizationcode
   * @param type $amount
   * @param type $ccnumber
   * @param type $ccexp
   * @return type
   */
  function doOffline($authorizationcode, $amount, $ccnumber, $ccexp) {

    $payload = array(
      'type' =>'offline',
      'ccnumber' => $ccnumber,
      'ccexp' => $ccexp,
      'amount' => number_format($amount,2,".",""),
      'authorizationcode' => $authorizationcode,
      'ipaddress' =>$this->order['ipaddress'],
      'orderid' =>$this->order['orderid'],
      'orderdescription' =>$this->order['ipaddress'],
      'tax' =>number_format($this->order['tax'],2,".",""),
      'shipping' =>number_format($this->order['shipping'],2,".",""),
      'ponumber' =>$this->order['ponumber']
    );

    // get billing details
    $payload =array_merge($payload,$this->getBillingAddress());
    // get shipping details
    $payload =array_merge($payload,$this->getShippingAddress());
    return $this->httpRequest(http_build_query($payload));
  }

  /**
   * Capture payment
   * @param int $transactionid
   * @param float $amount
   * @return array
   */
  function doCapture($transactionid, $amount =0) {

    $payload = array(
      'type' =>'capture',
      'transactionid' => $transactionid,
      'amount' => number_format($amount,2,".","")
    );
    return $this->httpRequest(http_build_query($payload));
  }

  /**
   * Void transaction
   * @param int $transactionid
   * @return array
   */
  function doVoid($transactionid) {

    $payload = array(
      'type' =>'void',
      'transactionid' => $transactionid
    );
    return $this->httpRequest(http_build_query($payload),1);
  }

  /**
   * Method for refund payment
   * @param int $transactionid
   * @param float $amount
   * @return array
   */
  function doRefund($transactionid, $amount = 0) {

    $payload = array(
      'type' =>'refund',
      'transactionid' => $transactionid,
      'amount' => number_format($amount,2,".","")
    );
    return $this->httpRequest(http_build_query($payload),1);
  }

  /**
   * 
   * @param array $inputs
   * @param boolean $return_type
   * @return array
   */
  function httpRequest($inputs,$return_type=0) {

    parse_str($inputs, $input_data);
    $input_data['username'] = $this->login['username'];
    $input_data['password'] = $this->login['password'];

    $http = _wp_http_get_object();
    $response = $http->post($this->endpoint, array('body' => $input_data));
    $response = new WP_REST_Response($response);
    $data = $response->data['body'];
    
    if($return_type==1){
      return $data;
    }

    $data = explode("&",$data);
    for($i=0;$i<count($data);$i++) {
        $rdata = explode("=",$data[$i]);
        $this->responses[$rdata[0]] = $rdata[1];
    }
    return $this->responses['response'];
  }

  /**
   * Convert string to array
   * @param array $response
   * @return array
   */
  function getResponse($response){
    parse_str($response, $get_array);
    return $get_array;
  }

  
  /**
   * Charge via customer Vault ID for recurring orders
   * @param int $token
   * @param float $amount
   * @return array
   */
  function ChargeViaVault($token, $amount =0) {

    $inputs  = "";
    // Login Details
    $inputs .= "username=" . urlencode($this->login['username']) . "&";
    $inputs .= "password=" . urlencode($this->login['password']) . "&";
    // Transaction Details
    $inputs .= "customer_vault_id=" . $token . "&";
    if ($amount>0) {
        $inputs .= "amount=" . urlencode(number_format($amount,2,".","")) . "&";
    }
    $inputs .= "billing_method=recurring";
    
    return $this->httpRequest($inputs,1);
  }
  /**
   * Add customer to vault
   */
  function add_customer_vault($amount, $ccnumber, $ccexp,$cvv) {

    $inputs  = "";
    // Login Details
    $inputs .= "username=" . urlencode($this->login['username']) . "&";
    $inputs .= "password=" . urlencode($this->login['password']) . "&";

    $inputs .= "firstname=" . urlencode($this->billing['firstname']) . "&";
    $inputs .= "lastname=" . urlencode($this->billing['lastname']) . "&";
    $inputs .= "address1=" . urlencode($this->billing['address1']) . "&";
    $inputs .= "city=" . urlencode($this->billing['city']) . "&";
    $inputs .= "state=" . urlencode($this->billing['state']) . "&";
    $inputs .= "country=" . urlencode($this->billing['country']) . "&";

    // Sales Details
    $inputs .= "ccnumber=" . urlencode($ccnumber) . "&";
    $inputs .= "ccexp=" . urlencode($ccexp) . "&";
    $inputs .= "cvv=" . urlencode($cvv) . "&";
    $inputs .= "&customer_vault=add_customer";
    
    return $this->httpRequest($inputs,1);
  }

  /**
   * Sanitizes a string from user input or from the database.
   */
  function escap_string($string=NULL){
    if(!empty($string)){
      return sanitize_text_field($string);
    }

  }
}
?>
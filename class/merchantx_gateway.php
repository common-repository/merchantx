<?php
/**
 * Initiate plugin settings & functions
 * 
 * Custom class(WC_Merchantx_Gateway) extended to  base WooCommerce class(WC_Payment_Gateway) 
 * and added the implemented the feature as for the plugin for Authorize/Authorize & capture
 * and update the refud/void for the payment gateway.
 * @package MerchantX
 * @since   1.0.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Merchantx_Gateway extends WC_Payment_Gateway {

  private $wc_payment_token  = NULL;
  private $inputs  = array();
  private $cardtype = "";
  private $has_subscription = false;

  public function __construct() {

      $this->id = 'mwcp_merchantx';
      $this->icon = plugins_url('images/merchantx.png', __FILE__);
      $this->has_fields = true;
      $this->method_title = 'MerchantX';
      $this->method_description = esc_html('MerchantX offers the best payments platform for running internet commerce. We build flexible and easy to use tools for ecommerce to help our merchants');
      $this->init_form_fields();
      $this->init_settings();
      $this->supports = array(
            'default_credit_card_form', 
            'capture_charge',
            'refunds',
            'voids',
            'pre-orders',
            'products',
            'subscriptions',
            'subscription_cancellation', 
            'subscription_suspension', 
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
            'tokenization'
        );
      $this->title = $this->get_option('merchantx_title');
      $this->merchantx_apilogin = $this->get_option('merchantx_apilogin');
      $this->merchantx_transactionkey = $this->get_option('merchantx_transactionkey');
      $this->transaction_type = $this->get_option('transaction_type');
      $this->merchantx_cardtypes = $this->get_option('merchantx_cardtypes');
      $this->two_step_checkout = $this->get_option('two_step_checkout');

      if (!defined("MTX_TRANSACTION_MODE")) {
          define("MTX_TRANSACTION_MODE", ($this->transaction_type == 'authorize' ? true : false));
      }

      if (is_admin()) {
          add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      }
      
     add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'merchantx_subscription_payment'),10,4);
     add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'merchantx_subscription_payment'),10,4);
     add_action( 'woocommerce_receipt_'.$this->id, array($this, 'receipt_page'));
  }

/**
 * Process refund & void from order details
 * @param int $order_id
 * @param float $amount
 * @param string $reason
 * @return boolean
 */
  public function process_refund($order_id, $amount = NULL, $reason = '') {
      $order = new WC_Order( $order_id );
      $pay = new merchantx_payments;
      $pay->setAuth($this->merchantx_apilogin, $this->merchantx_transactionkey);
      
       //Refund amount
       $response = $pay->doRefund($order->get_transaction_id(),$amount);
       $response = $pay->getResponse($response);

      if(!empty($response['response_code']) && $response['response_code']==100){
        $order->add_order_note( __('Order has been refunded successfully', 'woocommerce' ) );
        return true;   
      }
      else
      {
        //Void transaction
        $response = $pay->doVoid($order->get_transaction_id());
        $response = $pay->getResponse($response);
        if(!empty($response['response_code']) && $response['response_code']==100){
            $order->add_order_note( __('Order has been voided successfully', 'woocommerce' ) );
            return true;
           
        }
      }

      return false;

    }

  /**
   * Settings title & short description of payment tab.
   */
  public function admin_options() {
      ?>
      <h3><?php _e('MerchantX for WooCommerce', 'woocommerce'); ?></h3>
      <p><?php _e('MerchantX offers the best payments platform for running internet commerce. We build flexible and easy to use tools for ecommerce to help our merchants.', 'woocommerce'); ?></p>
      <table class="form-table">
      <?php $this->generate_settings_html(); ?>
      </table>
      <?php
  }

  /**
   * Initiate form fields to take inputs for the gateway information related details.
   */
  public function init_form_fields() {
      $this->form_fields = array
          (
          'enabled' => array(
              'title' => __('Enable/Disable', 'woocommerce'),
              'type' => 'checkbox',
              'label' => __('Enable MerchantX', 'woocommerce'),
              'default' => 'yes'
          ),
          'merchantx_title' => array(
              'title' => __('Title', 'woocommerce'),
              'type' => 'text',
              'description' => __('This controls the title which the buyer sees during checkout.', 'woocommerce'),
              'default' => __('MerchantX', 'woocommerce'),
              'desc_tip' => true,
          ),
          'merchantx_apilogin' => array(
              'title' => __('API Username', 'woocommerce'),
              'type' => 'text',
              'description' => __('Please provide NMI username.', 'woocommerce'),
              'default' => '',
              'desc_tip' => true,
              'placeholder' => 'Username'
          ),
          'merchantx_transactionkey' => array(
              'title' => __('API Password', 'woocommerce'),
              'type' => 'password',
              'description' => __('Please provide NMI password.', 'woocommerce'),
              'default' => '',
              'desc_tip' => true,
              'placeholder' => 'Password'
          ),
          'transaction_type' => array(
              'title' => __('Transaction Type', 'woocommerce'),
              'type' => 'select',
              'class' => 'chosen_select',
              'css' => 'width: 350px;',
              'desc_tip' => __('Select Transaction Type.', 'woocommerce'),
              'options' => array(
                  'authorize_capture' => 'Authorize & Capture',
                  'authorize' => 'Authorize Only'
              )
          ),
          'merchantx_cardtypes' => array(
              'title' => __('Accepted Cards', 'woocommerce'),
              'type' => 'multiselect',
              'class' => 'chosen_select',
              'css' => 'width: 350px;',
              'desc_tip' => __('Select the card types to accept.', 'woocommerce'),
              'options' => array(
                  'mastercard' => 'MasterCard',
                  'visa' => 'Visa',
                  'discover' => 'Discover',
                  'amex' => 'American Express',
                  'jcb' => 'JCB',
                  'dinersclub' => 'Dinners Club',
              ),
              'default' => array('mastercard', 'visa', 'discover', 'amex'),
          ),
          'two_step_checkout' => array(
            'title' => __('Two Step Checkout', 'woocommerce'),
            'type' => 'select',
            'class' => 'chosen_select',
            'css' => 'width: 350px;',
            'options' => array(
                'no' => 'No',
                'yes' => 'Yes'
            )
        )
      );
  }

  /**
   * get the icon image based on the credit card and display on the checkout page.
   * @return image path
   */
  public function get_icon() {
      $icon = '';
      if (is_array($this->merchantx_cardtypes)) {
          foreach ($this->merchantx_cardtypes as $card_type) {

              if ($url = $this->get_payment_method_image_url($card_type)) {

                  $icon .= '<img src="' . esc_url($url) . '" alt="' . esc_attr(strtolower($card_type)) . '" />';
              }
          }
      } else {
          $icon .= '<img src="' . esc_url(plugins_url('images/merchantx.png', __FILE__)) . '" alt="Mercant One Gateway" />';
      }

      return apply_filters('woocommerce_merchantx_icon', $icon, $this->id);
  }

  /**
   * pull card image based on the card type for image folder
   * @param string $type
   * @return string
   */
  public function get_payment_method_image_url($type) {

      $image_type = strtolower($type);

      return WC_HTTPS::force_https_url(plugins_url('../images/' . $image_type . '.png', __FILE__));
  }

  /**
   * check type of card with regex and we can apply here new changes based on the new BIN(s)
   * @param int $number
   * @return string
   */
  function get_card_type($number) {
      $number = preg_replace('/[^\d]/', '', $number);
      if (preg_match('/^3[47][0-9]{13}$/', $number)) {
          return 'amex';
      } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
          return 'dinersclub';
      } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
          return 'discover';
      } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
          return 'jcb';
      } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
          return 'mastercard';
      } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
          return 'visa';
      } else {
          return 'Invalid Card No';
      }
  }

  /**
   * Function to check IP & apply in the order API payload
   * @return string
   */
  function get_client_ip() {
      $ipaddress = '';
      if (getenv('HTTP_CLIENT_IP'))
          $ipaddress = getenv('HTTP_CLIENT_IP');
      else if (getenv('HTTP_X_FORWARDED_FOR'))
          $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
      else if (getenv('HTTP_X_FORWARDED'))
          $ipaddress = getenv('HTTP_X_FORWARDED');
      else if (getenv('HTTP_FORWARDED_FOR'))
          $ipaddress = getenv('HTTP_FORWARDED_FOR');
      else if (getenv('HTTP_FORWARDED'))
          $ipaddress = getenv('HTTP_FORWARDED');
      else if (getenv('REMOTE_ADDR'))
          $ipaddress = getenv('REMOTE_ADDR');
      else
          $ipaddress = '0.0.0.0';
      return $ipaddress;
  }
  
  /**
   * Initiate credit card form here
   * also you can modify as per your requirement and its presently using default form of WooCommerce
   */
  public function payment_fields() {
     if($this->two_step_checkout==='no' || empty($this->two_step_checkout)){
        $this->form();
        $this->merchantx_save_payment_method_checkbox();
     }
      
  }

  /**
   * check if the tokenization is support
   * @param string $name
   * @return string
   */
  public function field_name($name) {
      return $this->supports('tokenization') ? '' : ' name="' . esc_attr($this->id . '-' . $name) . '" ';
  }

  /**
   * Payment page card information form
   */
  public function form() {
  
   wp_enqueue_script( 'wc-credit-card-form' );

    $fields = array();
    if(!empty(get_current_user_id())){
        WC_Payment_Gateway::saved_payment_methods();
    }

	$cvc_field = '<p class="form-row form-row-last">
		<label for="' . esc_attr( $this->id ) . '-card-cvc">' . esc_html__( 'Card code', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
		<input id="' . esc_attr( $this->id ) . '-card-cvc" name="' . esc_attr($this->id) . '-card-cvc"  class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
	</p>';

	$default_fields = array(
		'card-number-field' => '<p class="form-row form-row-wide">
			<label for="' . esc_attr( $this->id ) . '-card-number">' . esc_html__( 'Card number', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-number" name="' . esc_attr($this->id) . '-card-number"  class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'card-number' ) . ' />
		</p>',
		'card-expiry-field' => '<p class="form-row form-row-first">
			<label for="' . esc_attr( $this->id ) . '-card-expiry">' . esc_html__( 'Expiry (MM/YY)', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label>
			<input id="' . esc_attr( $this->id ) . '-card-expiry" name="' . esc_attr($this->id) . '-card-expiry"  class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" ' . $this->field_name( 'card-expiry' ) . ' />
		</p>',
	);

	if ( ! $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
		$default_fields['card-cvc-field'] = $cvc_field;
	}

	$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_credit_card_form_fields', $default_fields, $this->id ) );
	?>

	<fieldset id="wc-<?= esc_attr( $this->id ); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
		<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
		<?php
		foreach ( $fields as $field ) {
			echo($field); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
		}
		?>
		<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
		<div class="clear"></div>
	</fieldset>
	<?php

	if ( $this->supports( 'credit_card_form_cvc_on_saved_method' ) ) {
		esc_html('<fieldset>' . $cvc_field . '</fieldset>'); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
    }
   
   
  }

  /**
   * Process payment via NMI gateway and handle notice in the order history
   * @global array $woocommerce
   * @param int $order_id
   * @return array
   */
  public function process_payment($order_id) {
      
      global $woocommerce;
      $wc_order = new WC_Order($order_id);
      $this->inputs = $this->escap_array($_POST);
      
      $this->has_subscription = !empty(wcs_get_subscriptions_for_order($order_id, array())) ? true : false;

      if(!empty($this->two_step_checkout) && $this->two_step_checkout==='yes' && $this->inputs['order_pay_submit']!=1){
            $pay_now_url = esc_url($wc_order->get_checkout_payment_url(true) );
            return array(
                'result' => 'success',
                'redirect' => $pay_now_url,
            );
           
      }

      $this->cardtype = $cardtype = $this->get_card_type(sanitize_text_field(str_replace(" ", "", $this->inputs[$this->id . '-card-number'])));
      $this->wc_payment_token = isset($this->inputs['wc-'. $this->id.'-payment-token'] ) ? $this->inputs['wc-'. $this->id.'-payment-token'] : '';
     
      if((empty($this->wc_payment_token) || $this->wc_payment_token =='new')){
        if($this->merchantx_validate_error()){
            return false;
            wp_die();
        }
        
      }

      $exp_date = explode("/", sanitize_text_field($this->inputs[$this->id . '-card-expiry']));
      $exp_month = str_replace(' ', '', $exp_date[0]);
      $exp_year = str_replace(' ', '', $exp_date[1]);

      if (strlen($exp_year) == 2) {
          $exp_year += 2000;
      }

      $gw = new merchantx_payments;
      $gw->setAuth($this->merchantx_apilogin, $this->merchantx_transactionkey);
      $gw->setBillingAddress(
              $wc_order->billing_first_name, $wc_order->billing_first_name, $wc_order->billing_company, $wc_order->billing_address_1, $wc_order->billing_address_2, $wc_order->shipping_city, $wc_order->billing_state, $wc_order->billing_postcode, $wc_order->billing_country, $wc_order->billing_phone, $wc_order->billing_phone, $wc_order->billing_email, get_bloginfo('url')
      );
      $gw->setShippingAddress(
              $wc_order->shipping_first_name, $wc_order->shipping_last_name, $wc_order->shipping_company, $wc_order->shipping_address_1, $wc_order->shipping_address_2, $wc_order->shipping_city, $wc_order->shipping_state, $wc_order->shipping_postcode, $wc_order->shipping_country, $wc_order->shipping_email);

      $gw->setParams(
              $wc_order->get_order_number(), get_bloginfo('blogname') . ' Order #' . $wc_order->get_order_number(), number_format($wc_order->get_total_tax(), 2, ".", ""), number_format($wc_order->get_total_shipping(), 2, ".", ""), $wc_order->get_order_number(), $this->get_client_ip()
      );

      
      if(!empty($this->wc_payment_token) && $this->wc_payment_token !='new'){ // payment via saved payment method

        $token = WC_Payment_Tokens::get($this->wc_payment_token);
        $r = $gw->ChargeViaVault($token->get_token(),$wc_order->order_total);
        $gw->responses = $gw->getResponse($r);
        $gw->responses['customer_vault_id'] = $token->get_token();

      } else{

        if (true == MTX_TRANSACTION_MODE) {
            
            $r = $gw->doAuth(
                number_format($wc_order->order_total, 2, ".", ""), sanitize_text_field(str_replace(" ", "", $this->inputs[$this->id . '-card-number'])), $exp_month . $exp_year, sanitize_text_field($this->inputs[$this->id . '-card-cvc'],$order_id)
            );
        } else {
            
              $r = $gw->doSale(
                number_format($wc_order->order_total, 2, ".", ""), sanitize_text_field(str_replace(" ", "", $this->inputs[$this->id . '-card-number'])), $exp_month . $exp_year, sanitize_text_field($this->inputs[$this->id . '-card-cvc'],$order_id)
          );
           
        }

      }

      if (count($gw->responses) > 1) {
          if (100 == $gw->responses['response_code']) {
                $wc_order->add_order_note(__('Payment has been approved successfully. Transaction ID:  '.$gw->responses['transactionid'].' & Authorization Code: '.$gw->responses['authcode'], 'woocommerce'));
                $wc_order->payment_complete($gw->responses['transactionid']);
                WC()->cart->empty_cart();
               //save customer vault
                $customer_vault_id = isset($gw->responses['customer_vault_id']) ? $gw->responses['customer_vault_id'] : 0;
                if(!empty($customer_vault_id)){
                    $wc_order->update_meta_data('customer_vault_id', $customer_vault_id);
                    $wc_order->save();
                }
                //save token
                $this->merchantx_save_token($this->inputs,$customer_vault_id);
                $token = $this->merchantx_get_payment_token(get_current_user_id()); 

                if(!empty($token)){
                    $wc_order->update_meta_data('wc_'.$this->id.'_token',$token);
                    $wc_order->save();
                }
              
              return array(
                  'result' => 'success',
                  'redirect' => $this->get_return_url($wc_order),
              );
          } else {
              $wc_order->add_order_note(__($gw->responses['responsetext'], 'woocommerce'));
              wc_add_notice($gw->responses['responsetext'], $notice_type = 'error');
          }
      } else {
          $wc_order->add_order_note(__($gw->responses['responsetext'], 'woocommerce'));
          wc_add_notice($gw->responses['responsetext'], $notice_type = 'error');
      }
  }

  /**
   * Function to validate card info
   */
  private function merchantx_validate_error(){

        if(empty($this->inputs[$this->id . '-card-number']) || empty($this->inputs[$this->id . '-card-expiry']) || empty($this->inputs[$this->id . '-card-cvc'])){
            wc_add_notice('Please enter card informations', $notice_type = 'error');
            return true;
        }
        else if (!in_array($this->cardtype, $this->merchantx_cardtypes)) {
            wc_add_notice('Merchant do not accept ' . $this->cardtype . ' card', $notice_type = 'error');
            return true;
        }
  }

  /**
   * Function to recurring charges
   * @param float $amount_to_charge
   * @param int $order
   * @param int $product_id
   */
  public function merchantx_subscription_payment($renewal_total=0, $renewal_order=array(), $product_id=0 ) {
      
            try{
                $subscription = new WC_Subscription($renewal_order);
                $get_total = $subscription->get_total();
                
                //order details
                $order_number = $renewal_order->get_order_number();
                $customer_id = $renewal_order->get_customer_id();
                $wc_order = new WC_Order($order_number);
                //token
                $tokens = $this->merchantx_get_payment_token($customer_id); 
                if(empty($tokens)){
                    $wc_order->add_order_note(__('Unable to get token for order.', 'woocommerce'));
                    return false;
                }

                $gw = new merchantx_payments;
                $gw->setAuth($this->merchantx_apilogin, $this->merchantx_transactionkey);
                $response = $gw->ChargeViaVault($tokens,$get_total);
                $response = $gw->getResponse($response);
            
                
                if (!empty($response['response_code']) && $response['response_code']==100) {
                    $wc_order->update_status('Processing');
                    $wc_order->add_order_note(__('Payment has been approved successfully. Transaction ID: '.$response['transactionid'], 'woocommerce'));
                    $wc_order->payment_complete($response['transactionid']);
                    WC_Subscriptions_Manager::process_subscription_payments_on_order( $renewal_order );   
                } else {
                    $wc_order->update_status('Failed');
                    $wc_order->add_order_note(__($response['responsetext'], 'woocommerce'));
                    WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order, $product_id );
                }
        }catch(Exception $e){
            return $e->getMessage();
        }
    }
    /**
     * Save token if enabled
     * @param type $inputs
     * @param type $customer_vault_id
     * @return type
     */
    private function merchantx_save_token($inputs=array(),$customer_vault_id){

        try{
            $token = new WC_Payment_Token_CC();
            $cardtype = $this->get_card_type(sanitize_text_field(str_replace(" ", "", $inputs[$this->id . '-card-number'])));
            $card_expiry = explode("/",$inputs[$this->id . '-card-expiry']);
            $last4 = sanitize_text_field(str_replace(" ", "", $inputs[$this->id . '-card-number']));
            $last4 = substr($last4,strlen($last4)-4,4);
            $exp_year = trim($card_expiry[1]);
            if (strlen($exp_year) == 2) {
            $exp_year += 2000;
            }

            $token->set_token($customer_vault_id);
            $token->set_gateway_id($this->id);
            $token->set_card_type($cardtype);
            $token->set_last4($last4);
            $token->set_expiry_month(trim($card_expiry[0]));
            $token->set_expiry_year($exp_year);
            $token->set_user_id(get_current_user_id());

            if($this->has_subscription==true){
                $token->save();
                return $token->get_id();
            }
            else if ( (isset( $inputs['wc-'. $this->id.'-payment-token'] ) && 'new' == $inputs['wc-'. $this->id.'-payment-token']) || (isset($inputs['wc-'. $this->id.'-payment-method'])) && $inputs['wc-'. $this->id.'-payment-method']==true) {
                $token->save();
                return $token->get_id();
        
            }

    } catch(Exception $e){
        return $e->getMessage();
    }
    
   }
    /**
     * Save payment method
     */
    private function merchantx_save_payment_method_checkbox() {
        $force_tokenization = false;
        $type               = $force_tokenization ? 'hidden' : 'checkbox';
        $desc               = $force_tokenization ? '' : esc_html__( 'Save to account', 'woocommerce' );

        printf( '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
        <input id="wc-%1$s-new-payment-method" name="wc-%1$s-payment-method" type="%2$s" value="true" style="width:auto;" />
        <label for="wc-%1$s-new-payment-method" style="display:inline;">%3$s</label>
        </p>', esc_attr( $this->id ), $type, $desc );
    }
    /**
     * get payment token
     * @param int $user_id
     * @param int $order_id
     * @return string
     */
    private function merchantx_get_payment_token($user_id=NULL,$order_id=NULL){
        $token = WC_Payment_Tokens::get_customer_default_token($user_id);
        if(empty($token)){
            return false;
        }
        //Get the actual token string (used to communicate with payment processors).
        $token = WC_Payment_Tokens::get($token->get_id());
        return $token->get_token();
    }
    /**
     * Step 2 Checkout
     * @param int $order_id
     */
    public function receipt_page( $order_id ) {
		global $woocommerce;
        $order = new WC_Order( $order_id );
        $order_button_text = 'Pay Now';
        require_once('order-pay.php');
    }
    /**
     * Sanitizes a array from user input or from the database.
     * @param: $input_array array
     * @return array
     */
    private function escap_array($input_array=array()){
        if(!empty($input_array) && is_array($input_array)){
            foreach($input_array as $key=>$value){
                $input_array[$key] = sanitize_text_field($value);
            }
            return $input_array;
        }

    }
     
}
?>
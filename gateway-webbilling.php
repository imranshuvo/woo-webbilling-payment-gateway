<?php
header("Content-Type: text/html; charset=utf-8");
include 'webbilling.php';
use Webbilling\Joinpage;
use Webbilling\Payment;
use Webbilling\Security;
use Webbilling\Customer;


/**
 * Plugin Name: Webbilling Gateway for WooCommerce
 * Plugin URI: http://ikhan.me
 * Description: WooCommerce Plugin for accepting payment through NMI Gateway.
 * Version: 1.0.0
 * Author: ikhan.me
 * Author URI: ikhan.me
 * Contributors: ikhan
 * Requires at least: 3.5
 * Tested up to: 4.8.2
 * WC requires at least: 3.0.0
 * WC tested up to: 3.2.1
 *
 * Text Domain: woo-webbilling-ikhan
 * Domain Path: /lang/
 *
 * @package Webbilling Gateway for WooCommerce
 * @author ikhan
 */



add_action('plugins_loaded', 'init_woocommerce_webbilling', 0);

function init_woocommerce_webbilling() {

  if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) { return; }

  load_plugin_textdomain('woo-webbilling-ikhan', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');


  class woocommerce_webbilling extends WC_Payment_Gateway_CC {

    public function __construct() {
      global $woocommerce;
			$this->id			= 'webbilling';
			$this->method_title = __( 'Webbilling Gateway', 'woo-webbilling-ikhan' );
			$this->icon			= apply_filters( 'woocommerce_webbilling_icon', '' );
			$this->has_fields 	= false;


      // Define user set variables
      $this->title       = $this->get_option('title');
      $this->description = $this->get_option('description');
      $this->username    = $this->get_option('merchantid');
      $this->password    = $this->get_option('merchantpassword');
      $this->salt   = $this->get_option('salt');
      $this->test_url = $this->get_option('test_target_url');
      $this->live_url = $this->get_option('live_target_url');

			$this->target_url	=   'yes' === $this->get_option('test_mode') ? $this->test_url : $this->live_url ;


			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();


			// Actions
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(	$this, 'process_admin_options' ) );

			if ( !$this->is_valid_for_use() ) $this->enabled = false;

    }


    /**
    * Check if this gateway is enabled and available in the user's country
    */
    function is_valid_for_use() {
        if ( !in_array( get_option('woocommerce_currency'), array('AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP') ) ) return false;

        return true;
    }



    private function force_ssl($url){

      if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
        $url = str_replace( 'http:', 'https:', $url );
      }

      return $url;
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @since 1.0.0
     */
    public function admin_options() {

      ?>
      <h3><?php _e('Webbilling Pay', 'woo-webbilling-ikhan'); ?></h3>
      <p><?php _e('Webbilling.com is a world-wide established technical service provider and will take care of your European billing needs.', 'woo-webbilling-ikhan'); ?></p>
      <table class="form-table">
      <?php
        if ( $this->is_valid_for_use() ) :

          // Generate the HTML For the settings form.
          $this->generate_settings_html();

        else :

          ?>
                <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woo-webbilling-ikhan' ); ?></strong>: <?php _e( 'Webbilling does not support your store currency.', 'woo-webbilling-ikhan' ); ?></p></div>
            <?php

        endif;
        ?>
      </table><!--/.form-table-->
      <?php
    } // End admin_options()

    /**
     *  Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {

      $this->form_fields = array(
        'enabled' => array(
          'title' => __( 'Enable/Disable', 'woo-webbilling-ikhan' ),
          'type' => 'checkbox',
          'label' => __( 'Enable Webbilling Payment', 'woo-webbilling-ikhan' ),
          'default' => 'yes',
          'desc_tip'    => true,
        ),
        'title' => array(
          'title' => __( 'Title', 'woo-webbilling-ikhan' ),
          'type' => 'text',
          'description' => __( 'This controls the title which the user sees during checkout.', 'woo-webbilling-ikhan' ),
          'default' => __( 'Webbilling Payment Gateway', 'woo-webbilling-ikhan' ),
          'desc_tip'    => true,
        ),
        'description' => array(
          'title' => __( 'Description', 'woo-webbilling-ikhan' ),
          'type' => 'textarea',
          'description' => __( 'This controls the description which the user sees during checkout.', 'woo-webbilling-ikhan' ),
          'default' => __("Pay via Webbilling.", 'woo-webbilling-ikhan'),
          'desc_tip'    => true,
        ),
        'merchantid' => array(
          'title' => __( 'Merchant ID', 'woo-webbilling-ikhan' ),
          'type' => 'text',
          'description' => __( 'Please enter your Merchant ID; this is needed in order to take payment.', 'woo-webbilling-ikhan' ),
          'default' => '',
          'desc_tip'    => true,
        ),
        'merchantpassword' => array(
          'title' => __( 'Password', 'woo-webbilling-ikhan' ),
          'type' => 'text',
          'description' => __( 'Please enter your Merchant Password; this is needed in order to take payment.', 'woo-webbilling-ikhan' ),
          'default' => '',
          'desc_tip'    => true,
        ),
        'salt' => array(
          'title' => __( 'Salt', 'woo-webbilling-ikhan' ),
          'type' => 'text',
          'description' => __( 'Please enter your Merchant Salt; this is needed in order to take payment.', 'woo-webbilling-ikhan' ),
          'default' => '',
          'desc_tip'    => true,
        ),
        'test_mode' => array(
          'title' => __( 'Enable Test Mode', 'woo-webbilling-ikhan' ),
          'type' => 'checkbox',
          'label' => __( 'Enable Test Mode', 'woo-webbilling-ikhan' ),
    			'description' => __( 'If enabled, test mode will be enabled. No real transaction will take place.', 'woo-webbilling-ikhan' ),
          'default' => 'yes',
          'desc_tip'    => true,
        ),
        'test_target_url' => array(
          'title' => __('Test Url','woo-webbilling-ikhan'),
          'type' => 'text',
          'description' => __('Please enter test gateway target url. ','woo-webbilling-ikhan'),
          'label' => 'Test Url',
          'default' => 'https://testjoinpage.webbilling.com/index.php',
          'desc_tip' => true,
        ),
        'live_target_url' => array(
          'title' => __('Live Url','woo-webbilling-ikhan'),
          'type' => 'text',
          'description' => __('Please enter live gateway target url. ','woo-webbilling-ikhan'),
          'label' => 'Live Url',
          'default' => 'https://joinpage.webbilling.com/index.php',
          'desc_tip' => true,
        )

      );
    } // End init_form_fields()

    /**
     * There are no payment fields for nmi, but we want to show the description if set.
     **/
    function payment_fields() {
     

      if ( $this->description ) {
  			echo wpautop( wp_kses_post( $this->description ) );
  		}

    }

    /**
     * Process the payment and return the result
     **/
    function process_payment( $order_id ) {
      global $woocommerce;

      $order = new WC_Order( $order_id );


      $objJoinpage = new Joinpage();
      $objJoinpage->setSalt($this->salt);
      $objJoinpage->setSecurityLvl(Joinpage::SECURITY_LVL_HIGH);

      $objJoinpage->merchantid  = $this->username;
      $objJoinpage->merchantpass  = $this->password; 
      $objJoinpage->post_back_url = $order->get_checkout_order_received_url();
      $mode = 'yes' === $this->get_option('test_mode') ? Joinpage::MODE_TEST : Joinpage::MODE_LIVE;
      $objJoinpage->setMode($mode); // sets url for joinpage request


      /* **************************************************************************************************************
       *  Create a payment object and setup needed data
       ****************************************************************************************************************/
      $objPayment = new Payment();

      $objPayment->domain = $_SERVER['SERVER_NAME'];

      $objPayment->setCurrency(get_woocommerce_currency());
      //$objPayment->product_currency = get_woocommerce_currency();

      $AmountInput = number_format($order->order_total, 2, '.', '');

      $objSecurity = new Security();
      $objSecurity->execute( array( 'profile' => 'bancrofthorloges'), $this->salt);
 

      // Singlepayment:
      $objPayment->setAmount($AmountInput, Payment::FULL);
      $objPayment->setPeriod(Payment::SINGLE_PAYMENT, Payment::FULL );

      /* ************************************************************
       * setup some customer data to initialize joinpage if you have
       **************************************************************/
      $objCustomer = new Customer();
      $objCustomer->fname   = $order->get_billing_first_name();
      $objCustomer->lname   = $order->get_billing_last_name();
      $objCustomer->email   = $order->get_billing_email();
      $objCustomer->street  = $order->get_billing_address_1().' ; '. $order->get_billing_address_2();
      $objCustomer->zip     = $order->get_billing_postcode();
      $objCustomer->city    = $order->get_billing_city();
      $objCustomer->countryid = $order->get_billing_country();

      // join all objects - ready to go
      $objJoinpage->setPayment($objPayment);
      $objJoinpage->setCustomer($objCustomer);

      $url =  $this->target_url.'?'.$objJoinpage->getBuildQuery();

      // Payment completed
      $order->add_order_note( sprintf( __('The Webbilling Payment transaction is processing.', 'woo-webbilling-ikhan') ) );
      $order->update_status('processing');
      //$order->payment_complete( $order_id );

			//update_post_meta( $order_id, 'Webbilling Transaction ID', $response['transactionid'] );

      return array(
        'result' 	=> 'success',
        'redirect'	=>	$url,
      );
        
    }

 }
 /**
  * Add the gateway to WooCommerce
  **/
 function add_webbilling_gateway( $methods ) {
   $methods[] = 'woocommerce_webbilling'; return $methods;
 }
 add_filter('woocommerce_payment_gateways', 'add_webbilling_gateway' );

}

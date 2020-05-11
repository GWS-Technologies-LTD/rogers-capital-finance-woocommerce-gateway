<?php
/**
 * Plugin Name: Rogers Capital Finance WooCommerce Gateway
 * Plugin URI: https://www.gws-technologies.com
 * Description: A Woocoomerce payment gateway plugin that adds instructions for checking out using Rogers Capital Finance in Mauritius. More info on Rogers Capital Finance at https://www.rogerscapital.mu/credit/credit/
 * Version: 1.0
 * Author: Jacques David Commarmond - GWS Technologies LTD
 * Author URI: https://www.gws-technologies.com
 */
defined( 'ABSPATH' ) or exit;

 // Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + WC_Rogers_Capital_Finance_WooCommerce gateway
 */
function wc_rogers_capital_finance_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Rogers_Capital_Finance_WooCommerce';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_rogers_capital_finance_add_to_gateways' );

/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_rogers_capital_finance_gateway_plugin_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rogers_capital_finance_gateway' ) . '">' . __( 'Configure', 'wc-rogers-capital-finance-gateway' ) . '</a>'
	);
	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_rogers_capital_finance_gateway_plugin_links' );

/**
 * Rogers Capital Finance WooCommerce Gateway
 *
 * Provides an Rogers Capital Finance Payment Gateway;
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Rogers_Capital_Finance_WooCommerce
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      SkyVerge
 */
add_action( 'plugins_loaded', 'wc_rogers_capital_finance_gateway_init', 11 );

function wc_rogers_capital_finance_gateway_init() {

    class WC_Rogers_Capital_Finance_WooCommerce extends WC_Payment_Gateway {

        /**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'rogers_capital_finance_gateway';
			$this->icon               = apply_filters('rogers_capital_finance_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Rogers Capital Finance', 'wc-rogers-capital-finance-gateway' );
			$this->method_description = __( 'Allows Rogers Capital Finance Applications.', 'wc-rogers-capital-finance-gateway' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {
            
            $this->form_fields = apply_filters( 'wc_rogers_capital_finance_form_fields', array(
                
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-rogers-capital-finance-gateway' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Rogers Capital Finance Payment', 'wc-rogers-capital-finance-gateway' ),
                    'default' => 'no'
                ),

                'title' => array(
                    'title'       => __( 'Title', 'wc-rogers-capital-finance-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-rogers-capital-finance-gateway' ),
                    'default'     => __( 'Rogers Capital Finance', 'wc-rogers-capital-finance-gateway' ),
                    'desc_tip'    => true,
                ),

                'description' => array(
                    'title'       => __( 'Description', 'wc-rogers-capital-finance-gateway' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-rogers-capital-finance-gateway' ),
                    'default'     => __( 'Place your order to apply for Rogers Capital Finance Credit Facilities. We will contact you for the rest of the application process once we receive your order. Make sure the contact information entered are accurate.', 'wc-rogers-capital-finance-gateway' ),
                    'desc_tip'    => true,
                ),

                'instructions' => array(
                    'title'       => __( 'Instructions', 'wc-rogers-capital-finance-gateway' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-rogers-capital-finance-gateway' ),
                    'default'     => 'Thank you very much for selecting Rogers Capital Finance for this order. Kindly print and sign this order and submit same to any of the following offices to process your application:'.PHP_EOL.
						'1. Rogers Capital Head Office Port Louis'.PHP_EOL.
						'2. Rogers Capital Office Jumbo Riche Terre'.PHP_EOL.
						'3. Rogers Capital Office Jumbo Phoenix'.PHP_EOL.
						'4. Rogers Capital Office Bagatelle Shopping Mall'.PHP_EOL.
						'5. Rogers Capital Sub-Office Curepipe'.PHP_EOL.PHP_EOLs.
						'Please bring along your ID card and the signed order.'.PHP_EOL.PHP_EOL.
						'First time Rogers Capital Clients should also bring the following documents:'.PHP_EOL.
						'(i) a utility bill as proof of address'.PHP_EOL.
						'(ii) Recent pay slip or bank statement for self employed',
                    'desc_tip'    => true,
                ),
            ) );
        }

        /**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting Rogers Capital Finance payment', 'wc-rogers-capital-finance-gateway' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}

    }
}

function rogerscapitalfinance_plugin_path() {

	// gets the absolute path to this plugin directory
  
	return untrailingslashit( plugin_dir_path( __FILE__ ) );
  }
  add_filter( 'woocommerce_locate_template', 'rogerscapitalfinance_woocommerce_locate_template', 10, 3 );
  
  
  
  function rogerscapitalfinance_woocommerce_locate_template( $template, $template_name, $template_path ) {
	global $woocommerce;
  
	$_template = $template;
  
	if ( ! $template_path ) $template_path = $woocommerce->template_url;
  
	$plugin_path  = rogerscapitalfinance_plugin_path() . '/woocommerce/';
  
	// Look within passed path within the theme - this is priority
	$template = locate_template(
  
	  array(
		$template_path . $template_name,
		$template_name
	  )
	);
  
	// Modification: Get the template from this plugin, if it exists
	if ( ! $template && file_exists( $plugin_path . $template_name ) )
	  $template = $plugin_path . $template_name;
  
	// Use default template
	if ( ! $template )
	  $template = $_template;
  
	// Return what we found
	return $template;
  }
<?php
/**
 * Plugin Name: MerchantX
 * Plugin URI: https://merchantx.com/
 * Description: MerchantX offers the best payments platform for running internet commerce. We build flexible and easy to use tools for ecommerce to help our merchants.
 * Version: 1.0.0
 * Author: MerchantX
 * Author URI: https://merchantx.com/
 * Author Email:techsupport@merchantx.com 
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 5.2
 * Tested up to: 5.2
 * WC requires at least: 3.6.0
 * WC tested up to: 3.6.0
 */

namespace Merchantx;

 // Exit if accessed directly
if (!defined('ABSPATH')) exit; 

/**
 * initialize main class to load the plugin
 */
$mwcp_plugin       = mwcp_merchantx::get_instance();
add_action( 'plugins_loaded',[$mwcp_plugin,'mwcp_merchantx_initialize']);
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),[$mwcp_plugin,'mwcp_merchantx_settings_link']);

class mwcp_merchantx {

    protected static $instance;

    protected function __construct() {
        // Made protected to prevent calls.
    }
     
    static function get_instance() {
        if ( ! self::$instance ) {
            self::$instance = new mwcp_merchantx();
        }

        return self::$instance;
    }
    /**
     * Initiate plugin core files & methods
     */
    static function mwcp_merchantx_initialize() {
   
        // Check if WooCommerce enabled
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action( 'admin_notices', [self::$instance, 'mwcp_merchantx_admin_notice' ] );
			return;
        }
        
        add_filter( 'woocommerce_payment_gateways', [self::$instance, 'mwcp_merchantx_gateway_class' ] );
    
        if (class_exists('WC_Payment_Gateway')) {
            require_once(plugin_dir_path(__FILE__) . "class/merchantx_payment.php"); 
            require_once(plugin_dir_path(__FILE__) . "class/merchantx_gateway.php");
        } 
        
    }
    /**
     * define unique method name here
     */
    static function mwcp_merchantx_gateway_class($methods) {
        $methods[] = 'WC_Merchantx_Gateway';
        return $methods;
    }

    /**
     * WooCommerce activate or install notice
     */
    static function mwcp_merchantx_admin_notice(){
        echo sprintf(
			'<div class="error"><p>%s</p></div>',
			sprintf(
				esc_html( 'MerchantX depended on the WooCommerce Plugin. So please activate or install %s to work!'),
				'<a href="'.esc_url('http://wordpress.org/extend/plugins/woocommerce/').'">'.esc_html('WooCommerce').'</a>'
			));
    }

    /**
     * Redirect to MerchantX setting for configuration related settings.
     * @param array $links
     * @return array
     */
    static function mwcp_merchantx_settings_link($links) {
        $settings = [
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mwcp_merchantx'),
				esc_html( 'Settings')
			),
		];
		return array_merge( $settings, $links );
    }
}
 
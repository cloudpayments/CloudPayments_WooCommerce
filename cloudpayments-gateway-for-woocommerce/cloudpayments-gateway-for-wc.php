<?php
/**
 * CloudPayments Gateway for WooCommerce
 *
 * @package CloudPayments Gateway
 *
 * Plugin Name: CloudPayments Gateway for WooCommerce
 * Plugin URI: https://github.com/cloudpayments/CloudPayments_WooCommerce
 * Description: Extends WooCommerce with CloudPayments Gateway.
 * Version: 3.0.3 beta
 * Text Domain: cloudpayments
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CPGWWC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CPGWWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPGWWC_PLUGIN_FILENAME', __FILE__ );

require CPGWWC_PLUGIN_DIR . 'inc/class-cloud-payments-init.php';

if ( class_exists( 'CloudPayments_Init' ) ) {
	CloudPayments_Init::init();
}

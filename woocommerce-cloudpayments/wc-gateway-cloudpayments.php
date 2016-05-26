<?php
/*
Plugin Name: WooCommerce CloudPayments Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Extends WooCommerce with CloudPayments Gateway.
Version: 1.0.0
Author: Konstantin Benko
Author URI: https://vk.com/kosteg_benko
*/
if ( ! defined( 'ABSPATH' ) ) exit;

// if ngnix, not Apache 

if (!function_exists('getallheaders'))  {
    function getallheaders()
    {
        if (!is_array($_SERVER)) {
            return array();
        }

        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

add_action('plugins_loaded', 'CloudPayments', 0);
function CloudPayments() {	
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_cp' );
	function woocommerce_add_cp( $methods ) {	
		$methods[] = 'WC_CloudPayments'; 
		return $methods;
	}

	class WC_CloudPayments extends WC_Payment_Gateway {
		
		public function __construct() {
			
			$this->id                 = 'cp';
			$this->has_fields         = true;
			$this->icon 			  = plugin_dir_url( __FILE__ ) . 'visa-mastercard.png';
//			$this->order_button_text  = __( 'Proceed to CP', 'woocommerce' );
			$this->method_title       = __( 'CloudPayments', 'woocommerce' );
			$this->method_description = 'CloudPayments – самый простой и удобный способ оплаты. Комиссий нет.';
			$this->supports           = array( 'products','pre-orders' );
            $this->enabled            =  $this->get_option( 'enabled' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title          	= $this->get_option( 'title' );
			$this->description    	= $this->get_option( 'description' );
			$this->public_id    	= $this->get_option( 'public_id' );
			$this->api_pass    	  	= $this->get_option( 'api_pass' );
			
			add_action( 'woocommerce_receipt_cp', 	array( $this, 'payment_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_'. strtolower( get_class( $this ) ), array( $this, 'handle_callback' ) );
		}
		
		// Check SSL
		public function cp_ssl_check() {			
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
				$this->msg = sprintf( __( 'CloudPayments is enabled and the <a href="%s">Force secure checkout</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
				$this->enabled = false;
				return false;
			}
			return true;
		}
		
		
		// Admin options
		public function admin_options() {
			if ( !$this->cp_ssl_check() ) {
				?>
				<div class="inline error"><p><strong><?php echo __( 'Warning', 'woocommerce' ); ?></strong>: <?php echo $this->msg; ?></p></div>
				<?php
			}				
			?>
				<h3>CloudPayments</h3>
				<p>CloudPayments – прямой и простой прием платежей с кредитных карт</p>
		        <p><strong>В личном кабинете включите Pay-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))?></p>
		        <p>Кодировка UTF-8, HTTP-метод POST, Формат запроса CloudPayments.</p>
			<?php
		
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
        }
		
		// Initialize fields
		public function init_form_fields() {
			
			$this->form_fields = array(
				'enabled' => array(
					'title' 	=> __( 'Enable/Disable', 'woocommerce' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Enable CloudPayments', 'woocommerce' ),
					'default' 	=> 'yes'
				),
				'title' => array(
					'title' 		=> __( 'Title', 'woocommerce' ),
					'type' 			=> 'text',
					'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' 		=> __( 'Банковская карта', 'woocommerce' ),
					'desc_tip'   	 => true,
				),
				'description' 	=> array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'CloudPayments – самый простой и удобный способ оплаты. Комиссий нет.', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'public_id' => array(
					'title' 		=> __( 'Public ID', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> 'Возьмите из личного кабинета CloudPayments',
					'default' 		=> '',
					'desc_tip' 		=> false,
				),
				'api_pass' => array(
					'title' 		=> __( 'Пароль для API', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> 'Возьмите из личного кабинета CloudPayments',
					'default' 		=> '',
					'desc_tip' 		=> false,
				),
			);
		}
		
		// Process payment
		public function process_payment( $order_id ) {
			
			global $woocommerce;
			
			$order = new WC_Order( $order_id );
				
			return array(
				'result'    => 'success',
				'redirect'  => add_query_arg( 'key', $order->order_key, add_query_arg( 'order-pay', $order_id, $order->get_checkout_payment_url( true ) ) )
			);
		}
		
		// Output iframe
		public function payment_page( $order_id ) {
			global $woocommerce;			
			$order = new WC_Order( $order_id );
			$title = array();
			$items = $order->get_items();
			foreach ($items as $item) {
				$title[] = $item['name'] . (isset($item['pa_ver']) ? ' ' . $item['pa_ver'] : '');
			}
			$title = implode(', ', $title);
			?>

			<script src="https://widget.cloudpayments.ru/bundles/cloudpayments"></script>
			<script>
				var widget = new cp.CloudPayments();
		    	widget.charge({ // options
		            publicId: '<?=$this->public_id?>',  //id из личного кабинета
		            description: 'Оплата заказа <?=$order_id?>: <?=$title?>', //назначение
		            amount: <?=$order->get_total()?>, //сумма
		            currency: 'RUB', //валюта
		            invoiceId: <?=$order_id?>, //номер заказа  (необязательно)
		            accountId: '<?=$order->billing_email?>', //идентификатор плательщика (необязательно)
		            data: {
		                myProp: 'myProp value' //произвольный набор параметров
		            }},
			        function (options) { // success
						window.location.replace('<?=$this->get_return_url($order)?>');
			        },
			        function (reason, options) { // fail
						window.location.replace('<?=$order->get_cancel_order_url()?>');
		        	}
		        );
			</script>

			<?php
		}

		// Callback
        public function handle_callback() {
        	echo '{"code":0}';
        	$headers = getallheaders();
        	if (!isset($headers['Content-HMAC'])) {
        		mail(get_option('admin_email'), 'не установлены заголовки', print_r($headers,1));
        		exit;
        	}
        	$message = file_get_contents('php://input');
            $posted = wp_unslash( $_POST );
            //mail(get_option('admin_email'), 'posted', print_r($posted,1));
            //Проверка подписи
			$s = hash_hmac('sha256', $message, $this->api_pass, true);
			$hmac = base64_encode($s);
			if ($headers['Content-HMAC'] != $hmac) {
        		mail(get_option('admin_email'), 'подпись платежа cloudpayments некорректна', print_r($headers,1). '     payment: '. print_r($posted,1). '     HMAC: '. print_r($hmac));
        		exit;
			}

			//Проверка суммы
			global $woocommerce;			
			$order = new WC_Order( $posted['InvoiceId'] );
			if ($posted['Amount'] != $order->get_total()) {
        		mail(get_option('admin_email'), 'сумма заказа некорректна', print_r($headers,1). '     payment: '. print_r($posted,1). '     order: '. print_r($order,1));
        		exit;
			}
            update_post_meta($posted['InvoiceId'], 'CloudPayments', json_encode($posted, JSON_UNESCAPED_UNICODE));

            if ($posted['Status'] == 'Completed') {
                // TODO: Пометить заказ, как «Оплаченный» в системе учета магазина
                $order->payment_complete();
                $order->add_order_note(__('Заказ успешно оплачен', 'woocommerce'));
                WC()->cart->empty_cart();
            } else {
                $order->update_status('on-hold', __('Заказ ожидает оплаты', 'woocommerce'));
                $order->add_order_note(__('Заказ ожидает оплаты', 'woocommerce'));
                WC()->cart->empty_cart();
            }
            exit;
        }
	}
}

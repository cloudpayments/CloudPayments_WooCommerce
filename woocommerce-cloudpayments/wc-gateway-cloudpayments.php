<?php
/*
Plugin Name: WooCommerce CloudPayments Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Extends WooCommerce with CloudPayments Gateway.
Version: 1.1.0
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
			$this->currency    	  	= $this->get_option( 'currency' );
			// Онлайн-касса
			$this->kassa_enabled    = $this->get_option( 'kassa_enabled' );
			$this->kassa_taxtype    = $this->get_option( 'kassa_taxtype' );
			$this->kassa_taxsystem  = $this->get_option( 'kassa_taxsystem' );
			$this->kassa_skubarcode  = $this->get_option( 'kassa_skubarcode' );
			$this->kassa_includeshipping  = $this->get_option( 'kassa_includeshipping' );
			
			add_action( 'woocommerce_receipt_cp', 	array( $this, 'payment_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_'. strtolower( get_class( $this ) ), array( $this, 'handle_callback' ) );
		}
		
		// Check SSL
		public function cp_ssl_check() {			
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
				$this->msg = sprintf( __( 'Включена поддержка оплаты через CloudPayments и не активирована опция <a href="%s">"Принудительная защита оформления заказа"</a>; безопасность заказов находится под угрозой! Пожалуйста, включите SSL и проверьте корректность установленных сертификатов.', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
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
					'label' 	=> __( 'Включить CloudPayments', 'woocommerce' ),
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
				'currency' => array(
					'title'       => __( 'Валюта магазина', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'default'     => 'RUB',
					'options'     => array(
						'RUB' => __( 'Российский рубль', 'woocommerce' ),
						'EUR' => __( 'Евро', 'woocommerce' ),
						'USD' => __( 'Доллар США', 'woocommerce' ),
						'GBP' => __( 'Фунт стерлингов', 'woocommerce' ),
						'UAH' => __( 'Украинская гривна', 'woocommerce' ),
						'BYN' => __( 'Белорусский рубль', 'woocommerce' ),
						'KZT' => __( 'Казахский тенге', 'woocommerce' ),
						'AZN' => __( 'Азербайджанский манат', 'woocommerce' ),
						'CHF' => __( 'Швейцарский франк', 'woocommerce' ),
						'CZK' => __( 'Чешская крона', 'woocommerce' ),
						'CAD' => __( 'Канадский доллар', 'woocommerce' ),
						'PLN' => __( 'Польский злотый', 'woocommerce' ),
						'SEK' => __( 'Шведская крона', 'woocommerce' ),
						'TRY' => __( 'Турецкая лира', 'woocommerce' ),
						'CNY' => __( 'Китайский юань', 'woocommerce' ),
						'INR' => __( 'Индийская рупия', 'woocommerce' ),
						'BRL' => __( 'Бразильский реал', 'woocommerce' ),
					),
				),
				'kassa_section' => array(
					'title'       => __( 'Онлайн-касса', 'woocommerce' ),
					'type'        => 'title',
					'description' => '',
				),
				'kassa_enabled' => array(
					'title' 	=> __( 'Enable/Disable', 'woocommerce' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Включить отправку данных для онлайн-кассы (по 54-ФЗ)', 'woocommerce' ),
					'default' 	=> 'no'
				),
				'kassa_taxtype' => array(
					'title'       => __( 'Ставка НДС', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к товарам в магазине.', 'woocommerce' ),
					'default'     => '10',
					'desc_tip'    => true,
					'options'     => array(
						'null' => __( 'НДС не облагается', 'woocommerce' ),
						'18' => __( 'НДС 18%', 'woocommerce' ),
						'10' => __( 'НДС 10%', 'woocommerce' ),
						'0' => __( 'НДС 0%', 'woocommerce' ),
						'110' => __( 'расчетный НДС 10/110', 'woocommerce' ),
						'118' => __( 'расчетный НДС 18/118', 'woocommerce' ),
					),
				), 
				'kassa_taxsystem' => array(
					'title'       => __( 'Cистема налогообложения организации', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Указанная система налогообложения должна совпадать с одним из вариантов, зарегистрированных в ККТ.', 'woocommerce' ),
					'default'     => '1',
					'desc_tip'    => true,
					'options'     => array(
						'0' => __( 'Общая система налогообложения', 'woocommerce' ),
						'1' => __( 'Упрощенная система налогообложения (Доход)', 'woocommerce' ),
						'2' => __( 'Упрощенная система налогообложения (Доход минус Расход)', 'woocommerce' ),
						'3' => __( 'Единый налог на вмененный доход', 'woocommerce' ),
						'4' => __( 'Единый сельскохозяйственный налог', 'woocommerce' ),
						'5' => __( 'Патентная система налогообложения', 'woocommerce' ),
					),
				),
				'kassa_skubarcode' => array(
								'title' 	=> __( 'Действие со штрих-кодом', 'woocommerce' ),
								'type' 		=> 'checkbox',
								'label' 	=> __( 'Отправлять артикул (SKU) товара как штрих-код', 'woocommerce' ),
								'default' 	=> 'yes'
				),	
				'kassa_includeshipping' => array(
								'title' 	=> __( 'Действие с доставкой', 'woocommerce' ),
								'type' 		=> 'checkbox',
								'label' 	=> __( 'Включать доставку в чек (с НДС 18%)', 'woocommerce' ),
								'default' 	=> 'no'
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
			$items_array = array();
			$items = $order->get_items();
			$shipping_data = array("label"=>"Доставка", "price"=>number_format((float)$order->get_shipping_total()+abs((float)$order->get_shipping_tax()), 2, '.', ''), "quantity"=>"1.00", "amount"=>number_format((float)$order->get_shipping_total()+abs((float)$order->get_shipping_tax()), 2, '.', ''), "vat"=>"18", "ean"=>null);
			foreach ($items as $item) {
				if ($this->kassa_enabled == 'yes') {
				$product = $order->get_product_from_item($item);
				$items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$product->get_price(), 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''), "amount"=>number_format((float)$item['total']+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype, "ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
				}
				$title[] = $item['name'] . (isset($item['pa_ver']) ? ' ' . $item['pa_ver'] : '');
			}
			if ($this->kassa_enabled == 'yes' && $this->kassa_includeshipping == 'yes' && $order->get_shipping_total() > 0) $items_array[] = $shipping_data;
			$kassa_array = array("cloudPayments"=>(array("customerReceipt"=>array("Items"=>$items_array, "taxationSystem"=>$this->kassa_taxsystem, "email"=>$order->billing_email, "phone"=>$order->billing_phone))));
			$title = implode(', ', $title);
			?>

			<script src="https://widget.cloudpayments.ru/bundles/cloudpayments"></script>
			<script>
				var widget = new cp.CloudPayments();
		    	widget.charge({ // options
		            publicId: '<?=$this->public_id?>',  //id из личного кабинета
		            description: 'Оплата заказа <?=$order_id?>', //назначение
		            amount: <?=$order->get_total()?>, //сумма
		            currency: '<?=$this->currency?>', //валюта
		            invoiceId: <?=$order_id?>, //номер заказа 
		            accountId: '<?=$order->billing_email?>', //идентификатор плательщика
		            data: 
		                <? echo (($this->kassa_enabled == 'yes') ? json_encode($kassa_array) : "{}") ?>
		            },
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
			if (!function_exists('getallheaders')) {
				function getallheaders() {
					$headers = [];
					foreach ($_SERVER as $name => $value) {
						if (substr($name, 0, 5) == 'HTTP_') {
							$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
						}
					}
					return $headers;
				}
			}
        	$headers = getallheaders();
        	if ((!isset($headers['Content-HMAC'])) and (!isset($headers['Content-Hmac']))) {
        		mail(get_option('admin_email'), 'не установлены заголовки', print_r($headers,1));
        		exit;
        	}
        	$message = file_get_contents('php://input');
            $posted = wp_unslash( $_POST );
            //Проверка подписи
			$s = hash_hmac('sha256', $message, $this->api_pass, true);
			$hmac = base64_encode($s);
			if (($headers['Content-HMAC'] && $headers['Content-HMAC'] != $hmac) || ($headers['Content-Hmac'] && $headers['Content-Hmac'] != $hmac)) {
        		mail(get_option('admin_email'), 'подпись платежа cloudpayments некорректна', print_r($headers,1). '     payment: '. print_r($posted,1). '     HMAC: '. $hmac);
        		exit("hmac error");
			}

			//Проверка суммы
			global $woocommerce;			
			$order = new WC_Order( $posted['InvoiceId'] );
			if ($posted['Amount'] != $order->get_total()) {
        		mail(get_option('admin_email'), 'сумма заказа некорректна', print_r($headers,1). '     payment: '. print_r($posted,1). '     order: '. print_r($order,1));
        		exit("sum error");
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

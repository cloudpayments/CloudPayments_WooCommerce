<?php
/**
 * Plugin Name: WooCommerce CloudPayments Gateway
 * Plugin URI: http://woothemes.com/woocommerce
 * Description: Extends WooCommerce with CloudPayments Gateway.
 * Version: 2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Register New Order Statuses
function wpex_wc_register_post_statuses() 
{
    register_post_status( 'wc-pay_au', array(
        'label'                     => _x( 'Payment authorized', 'WooCommerce Order status', 'text_domain' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Approved (%s)', 'Approved (%s)', 'text_domain' )
    ) );
}
add_filter( 'init', 'wpex_wc_register_post_statuses' );

// Add New Order Statuses to WooCommerce
function wpex_wc_add_order_statuses( $order_statuses )
{
    $order_statuses['wc-pay_au'] = _x( 'Payment authorized', 'WooCommerce Order status', 'text_domain' );
    return $order_statuses;
}
add_filter( 'wc_order_statuses', 'wpex_wc_add_order_statuses' );


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
function CloudPayments() 
{	  
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_cp' );
	function woocommerce_add_cp( $methods )
  {	
		$methods[] = 'WC_CloudPayments'; 
		return $methods;
	}

	class WC_CloudPayments extends WC_Payment_Gateway {
		
		public function __construct() {
			
			$this->id                 =  'cp';
			$this->has_fields         =  true;
			$this->icon 			        =  plugin_dir_url( __FILE__ ) . 'visa-mastercard.png';
			$this->method_title       =  __( 'CloudPayments', 'woocommerce' );
			$this->method_description =  'CloudPayments – самый простой и удобный способ оплаты. Комиссий нет.';
            $this->supports           =  array(
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
                                                'multiple_subscriptions'
                                                );
   
            $this->enabled            =  $this->get_option( 'enabled' );
            $this->enabledDMS         =  $this->get_option( 'enabledDMS' );
            $this->DMS_AU_status      =  $this->get_option( 'DMS_AU_status' );
            $this->DMS_CF_status      =  $this->get_option( 'DMS_CF_status' );
            $this->language           =  $this->get_option( 'language' );
            $this->skin               =  $this->get_option( 'skin' );
            $this->status_chancel     =  $this->get_option( 'status_chancel' );
            $this->status_pay         =  $this->get_option( 'status_pay' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title          	= $this->get_option( 'title' );
			$this->description    	= $this->get_option( 'description' );
			$this->public_id    	  = $this->get_option( 'public_id' );
			$this->api_pass    	  	= $this->get_option( 'api_pass' );
			$this->currency    	  	= $this->get_option( 'currency' );
            $this->order_enabled    = $this->get_option( 'order_enabled' );
			// Онлайн-касса
			$this->kassa_enabled    = $this->get_option( 'kassa_enabled' );
			$this->kassa_taxtype    = $this->get_option( 'kassa_taxtype' );
            $this->kassa_taxsystem  = $this->get_option( 'kassa_taxsystem' );
            $this->calculationPlace  = $this->get_option( 'calculationPlace' );
			$this->kassa_skubarcode  = $this->get_option( 'kassa_skubarcode' );
			add_action( 'woocommerce_receipt_cp', 	array( $this, 'payment_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_'. strtolower( get_class( $this ) ), array( $this, 'handle_callback' ) );
            add_action('woocommerce_order_status_changed', array( $this, 'update_order_status'), 10, 3);
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
		        <p><strong>В личном кабинете включите Check-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=check"?></p>
		        <p><strong>В личном кабинете включите Pay-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=pay"?></p>
		        <p><strong>В личном кабинете включите Fail-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=fail"?></p>
		        <p><strong>В личном кабинете включите Confirm-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=confirm"?></p>
		        <p><strong>В личном кабинете включите Refund-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=refund"?></p>
		        <p><strong>В личном кабинете включите Receipt-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=receipt"?></p>
		        <p><strong>В личном кабинете включите Recurrent-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=recurrent"?></p>
		        <p><strong>В личном кабинете включите Cancel-уведомление на адрес:</strong> <?=home_url('/?wc-api='.strtolower(get_class($this)))."&action=cancel"?></p>
		        <p>Кодировка UTF-8, HTTP-метод POST, Формат запроса CloudPayments.</p>
			<?php
		
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
        }
		
		// Initialize fields
		public function init_form_fields() {
			$array_status = wc_get_order_statuses();
			$this->form_fields = array(
				'enabled' => array(
					'title' 	=> __( 'Использовать модуль CloudPayments', 'woocommerce' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Включить', 'woocommerce' ),
					'default' 	=> 'yes'
				),
				'enabledDMS' => array(
					'title' 	=> __( 'Двухстадийная схема проведения платежа (DMS)', 'woocommerce' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Включить DMS', 'woocommerce' ),
					'default' 	=> 'no'
				),
				'status_pay' => array(
					'title'       => __( 'Статус для оплаченного заказа', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'woocommerce' ),
					'default'     => 'wc-completed',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'status_chancel' => array(
					'title'       => __( 'Статус для отмененного заказа', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'woocommerce' ),
					'default'     => 'wc-cancelled',
					'desc_tip'    => true,
					'options'     => $array_status,
				),
				'DMS_AU_status' => array(
					'title'       => __( 'Статус авторизованного платежа (DMS)', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( '', 'woocommerce' ),
					'default'     => 'wc-pay_au',
					'desc_tip'    => true,
					'options'     => $array_status,
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
                        'ZAR' => __( 'Южноафриканский рэнд', 'woocommerce' ),
						'UZS' => __( 'Узбекский сум', 'woocommerce' ),
						'BGL' => __( 'Болгарский лев', 'woocommerce' ),
					),
                ),
                'language' => array(
					'title'       => __( 'Язык виджета', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'default'     => 'ru-RU',
					'options'     => array(
						'ru-RU' => __( 'Русский', 'woocommerce' ),
						'en-US' => __( 'Английский', 'woocommerce' ),
						'lv' => __( 'Латышский', 'woocommerce' ),
						'az' => __( 'Азербайджанский', 'woocommerce' ),
						'kk' => __( 'Русский', 'woocommerce' ),
						'kk-KZ' => __( 'Казахский', 'woocommerce' ),
						'uk' => __( 'Украинский', 'woocommerce' ),
						'pl' => __( 'Польский', 'woocommerce' ),
                        'pt' => __( 'Португальский', 'woocommerce' ),
                        'cs-CZ' => __( 'Чешский', 'woocommerce' ),
                    ),
                ),
                'skin' => array(
					'title'       => __( 'Дизайн виджета', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'default'     => 'classic',
					'options'     => array(
						'classic' => __( 'classic', 'woocommerce' ),
						'modern' => __( 'modern', 'woocommerce' ),
						'mini' => __( 'mini', 'woocommerce' ),
						 ),
                ),
                'order_enabled' => array(
					'title' 	=> __( 'Формирование заказа в подписках', 'woocommerce' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Включить', 'woocommerce' ),
                    'default' 	=> 'yes',
                    'desc_tip' 		=> true,
					'description' => __( 'Формирование заказа при последующих платежах подписок'),
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
                'calculationPlace' => array(
					'title' 		=> __( 'Место осуществления расчёта', 'woocommerce' ),
					'type' 			=> 'text',
					'description'	=> __( 'Адрес сайта точки продаж, для печати в чеке.', 'woocommerce' ),
					'default' 		=> '',
					'desc_tip' 		=> true,
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
						'20' => __( 'НДС 20%', 'woocommerce' ),
						'10' => __( 'НДС 10%', 'woocommerce' ),
						'0' => __( 'НДС 0%', 'woocommerce' ),
						'110' => __( 'расчетный НДС 10/110', 'woocommerce' ),
						'120' => __( 'расчетный НДС 20/120', 'woocommerce' ),
					),
				), 
				'delivery_taxtype' => array(
					'title'       => __( 'Ставка НДС для доставки', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к доставке в магазине.', 'woocommerce' ),
					'default'     => '10',
					'desc_tip'    => true,
					'options'     => array(
						'null' => __( 'НДС не облагается', 'woocommerce' ),
						'20' => __( 'НДС 20%', 'woocommerce' ),
						'10' => __( 'НДС 10%', 'woocommerce' ),
						'0' => __( 'НДС 0%', 'woocommerce' ),
						'110' => __( 'расчетный НДС 10/110', 'woocommerce' ),
						'120' => __( 'расчетный НДС 20/120', 'woocommerce' ),
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
                $this->addError( "Проверка заказа" );
			    global $woocommerce;			
			    $order = new WC_Order( $order_id );
			    $title = array();
			    $items_array = array();
			    $sp_items_array = array();
			    $items = $order->get_items();
			    
			    if ($this->kassa_enabled == 'yes'){
			        $shipping_data = array("label"=>"Доставка", "price"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''),"quantity"=>"1.00", "amount"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''), "vat"=>$this->delivery_taxtype, "ean"=>null);
			    };
			    foreach ($items as $item) {
				    $product = $order->get_product_from_item( $item );
				    $product_id = $product->get_id();
				    //определяем активность woocommerce-subscriptions
				    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                    if (is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php')) {
                        
                        //определяем есть ли в заказе подписка
				        $order_subscription = wcs_order_contains_subscription( $order_id );
				        if ($order_subscription == true){
				            $sp_product         = new WC_Subscriptions_Product( $product );//добавляем продукт подписки
				            $sp_is_subscription = $sp_product->is_subscription ( $product );//признак подписки
				        }
				        else $sp_is_subscription = false;
			
				        if ($sp_is_subscription == true )//если продукт подписка
				        {
				            $sp_period        = $sp_product->get_period ( $product );//период
        			        $sp_interval      = $sp_product->get_interval ( $product );//интервал
		        	        $sp_price         = $sp_product->get_price ( $product );//цена за период
				            $sp_length        = $sp_product->get_length ( $product );//длина подписки
				            $sp_sign_up_fee   = $sp_product->get_sign_up_fee ( $product );//плата за регистрацию
				            $sp_trial_length  = $sp_product->get_trial_length ( $product );//длина (1, 2, 3...) бесплатного периода
				            $sp_trial_period  = $sp_product->get_trial_period ( $product );//период (день, месяц, год) бесплатного периода

				            //переволим период полписки год в месяц, корректируем интервал
				            if ($sp_period == 'year') 
			                {
			                    $sp_period    = 'month';
			                    $sp_interval  = 12*$sp_interval;
			                };
			            
			                //считаем количество платежей в подписке
			                if ($sp_length > 0){
			                    $sp_maxperiods = (int)($sp_length/$sp_interval);//Максимальное количество платежей в подписке
			                };
			            
				            if ($this->kassa_enabled == 'yes'){
				            
				                //формируем чек подписки
				                $sp_items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$sp_price, 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''), "amount"=>number_format((float)($sp_price*$item['quantity'])+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype, "ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
				            
				                //если есть плата за регистрацию подписки включаем ее в чек продажи
				                if ($sp_sign_up_fee > 0)
				                $items_array[] = array("label"=>'Регистрация '.$item['name'], "price"=>number_format((float)$sp_sign_up_fee, 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''), "amount"=>number_format((float)($sp_sign_up_fee*$item['quantity'])+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype, "ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
			                
			                    //если нет платы за регистрацию и есть бесплатный пробный период
			                    if ($sp_sign_up_fee == 0 && $sp_trial_length > 0){
			                        $sp_price_items_array = 1;
			                    }
			                    else {$sp_price_items_array = $product->get_price();};
			                
				                //формируем чек продажи
				                $items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$sp_price_items_array, 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''), "amount"=>number_format((float)($sp_price_items_array*$item['quantity'])+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype, "ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
			                
				                $sp_is_virtual = $product->is_virtual();
				                //если  в подписке есть доставка добавляем ее в чек подписки
				                if ($sp_is_virtual != true && $order->get_total_shipping() > 0){
				                    $sp_items_array[] = $shipping_data;
				                };
				            };
				        }
				        elseif ($this->kassa_enabled == 'yes'){
				            //формируем чек по продуктам без подписки
		                    $items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$product->get_price(), 2, '.', ''),"quantity"=>number_format((float)$item['quantity'], 2, '.', ''), "amount"=>number_format((float)$item['total']+abs((float)$item['total_tax']), 2, '.', ''),"vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype,"ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
			                };
				        }
				    else{// если плагин подписки неактивен
				        if ($this->kassa_enabled == 'yes'){
				            //формируем чек по продуктам без подписки
		                    $items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$product->get_price(), 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''), "amount"=>number_format((float)$item['total']+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype, "ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
			            };
				    };
			    };
			    if ($order->get_total_shipping() > 0) {$items_array[] = $shipping_data;}
			    //формируем данные для отправки
			    //если есть отправка чеков и подписка в заказе
			    if ($this->kassa_enabled == 'yes' && $order_subscription == true){
			        $data =array("cloudPayments"=>array(
			            "customerReceipt"=>array("Items"=>$items_array, "taxationSystem"=>$this->kassa_taxsystem,
			            "email"=>$order->billing_email, "phone"=>$order->billing_phone, "calculationPlace"=>$this->calculationPlace),
			            "recurrent"=>array("interval"=>$sp_period, "period"=>$sp_interval, "MaxPeriods"=>$sp_maxperiods,
			            "Amount"=>$sp_price, "customerReceipt"=>array("Items"=>$sp_items_array, "taxationSystem"=>$this->kassa_taxsystem,
			            "email"=>$order->billing_email, "phone"=>$order->billing_phone))
			        ));
		        }//если нет отправки чеков и подписка в заказе
		        elseif ($this->kassa_enabled == 'no' && $order_subscription == true) {
		          $data =array("cloudPayments"=>array(
			            "recurrent"=>array("interval"=>$sp_period, "period"=>$sp_interval, "MaxPeriods"=>$sp_maxperiods,
			            "Amount"=>$sp_price)
			        ));  
		        }//если есть отправка чеков и нет подписки в заказе
		        elseif ($this->kassa_enabled == 'yes' && $order_subscription != true) {
		            $data =array("cloudPayments"=>array(
			            "customerReceipt"=>array("Items"=>$items_array, "taxationSystem"=>$this->kassa_taxsystem,
			            "email"=>$order->billing_email, "phone"=>$order->billing_phone, "calculationPlace"=>$this->calculationPlace)
			        ));
		        };
			    $title = implode(', ', $title);
                $widget_f='charge';
                if ($this->enabledDMS!='no')
                {
                    $widget_f='auth';
                };
                //если в ордере подписка с бесплатным пробным периодом для активации подписки
                //выставляем счет на 1 рубль
                if ($order->get_total() == 0){
                    $price_order = 1;
                }
                else{
                   $price_order = $order->get_total();
                };
			    ?>
			    <script src="https://widget.cloudpayments.ru/bundles/cloudpayments"></script>
			    <script>
				    var widget = new cp.CloudPayments({language: '<?=$this->language?>'});// язык виджета
		        	widget.<?=$widget_f?>({ // options              <!-- /////////////???????????????  -->
		                publicId: '<?=$this->public_id?>',  //id из личного кабинета
		                description: 'Оплата заказа <?=$order_id?>', //назначение
		                amount: <?=$price_order?>, //сумма
		                currency: '<?=$this->currency?>', //валюта
		                invoiceId: <?=$order_id?>, //номер заказа 
		                accountId: '<?=$order->billing_email?>', //идентификатор плательщика
                        skin: '<?=$this->skin?>', //дизайн виджета
		                data: <?=json_encode($data)?>
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

      	public function processRequest($action,$request)   ///ok
      	{
              $this->addError("processRequest - action");
              $this->addError(print_r($action,true));
              
              $this->addError("processRequest - request");
              $this->addError(print_r($request,true));
              
              $this->addError("processRequest - params");
              $this->addError(print_r($params,true));
    
              $this->addError('processRequest - '.$action);
              $this->addError(print_r($request,true));
      
              if ($action == 'check')
              {
                  return $this->processCheckAction($request);    ///OK
                  die();
              }
              else if ($action == 'fail')
              {
                  return $this->processFailAction($request);   //  
                  die();
              }
              else if ($action == 'pay')
              {
                  return $this->processSuccessAction($request);   ///
                  die();
              }
              else if ($action == 'refund')
              {
                  return $this->processrefundAction($request);     //
                  die();
              }
              else if ($action == 'confirm')
              {
                  return $this->processconfirmAction($request);     //
                  die();
              }      
              else if ($action == 'Cancel')
              {
                  return $this->processrefundAction($request);     //
                  die();
              } 
              else if ($action == 'void')
              {
                  return $this->processrefundAction($request);     //
                  die();
              } 
              else if ($action == 'receipt')
              {
                  return $this->processreceiptAction($request);     //добавили receipt-уведомление
                  die();
              } 
              else if ($action == 'recurrent')
              {
                  return $this->processrecurrentAction($request);     //добавили recurrent-уведомление
                  die();
              } 
              else
              {
      
                  $data['TECH_MESSAGE'] = 'Unknown action: '.$action;
                  $this->addError('Unknown action: '.$action.'. Request='.print_r($request,true));
                  exit('{"code":0}');
              }
      	}
        
        private function processrecurrentAction($request)   //ok
        {     
            $data['CODE'] = 0; 
            //определяем активность woocommerce-subscriptions
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            if (is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php'))
            {   //здесь ищем ордер по id подписки, далее меняем статус подписки
                $subscription_orders = wcs_get_subscription_orders( $return_fields = 'all', $order_type = 'parent' );
                foreach ($subscription_orders as $subscription_order)
                {   
                    $meta_data = $subscription_order->get_meta_data();
                    foreach ($meta_data as $meta_key)
                    {
                        $meta_value = $meta_key->value;
                        if ($meta_value == $request['Id'])
                        {
                            $subscriptions = wcs_get_subscriptions_for_order( $subscription_order );
                            foreach ($subscriptions as $subscription)
                            {
                                if ($request['Status']=='Expired')
                                {
                                    $subscription->update_status( 'expired' );
                                }
                                else if ($request['Status']=='Cancelled')
                                {
                                    $subscription->update_status( 'cancelled' );
                                }
                                else if ($request['Status']=='Rejected')
                                {
                                    $subscription->update_status( 'cancelled' );
                                }
                                else if ($request['Status']=='PastDue')
                                {
                                    $subscription->update_status( 'on-hold' );
                                }
                                else if ($request['Status']=='Active')
                                {
                                    $subscription->update_status( 'active' );
                                    break;
                                    break;
                                };
                            };
                        };
                    };
                };
            };
            $this->addError('Subscription status changed');
            $this->addError(print_r($request,true));
            $this->addError(print_r($order,true));
            echo json_encode($data);
        }
        
        private function processreceiptAction($request)   //ok
        {     
            $data['CODE'] = 0;                         					
            $this->addError('Check is registered');
            $this->addError(print_r($request,true));
            $this->addError(print_r($order,true));
            echo json_encode($data);
        }
        
        private function processconfirmAction($request)   //ok
        {     
            $order=self::get_order($request);
            $data['CODE'] = 0;                         					
            self::OrderSetStatus($order,$this->status_pay);
            $this->addError('PAY_COMPLETE');
            $this->addError(print_r($request,true));
            $this->addError(print_r($order,true));
      
            echo json_encode($data);
        }
        
        private function confirm($order,$ps)   //ok
        {
          $API_URL='https://api.cloudpayments.ru/payments/confirm';                                                  
          if ($ps==$this->id) 
          {
            $ORDER_PRICE=$order->get_total();
            $PAY_VOUCHER_NUM=$order->get_transaction_id();
            if ($PAY_VOUCHER_NUM && $ORDER_PRICE)
            {
              if($curl = curl_init()) 
              {          
                $accesskey=$this->public_id;
                $access_psw=$this->api_pass;
                
                $request=array(
                    'TransactionId'=>$PAY_VOUCHER_NUM,
                    'Amount'=>number_format($ORDER_PRICE, 2, '.', ''),
                  //  'JsonData'=>'',
                );
                $this->addError("confirm");
                $this->addError(print_r($request,true));
                $ch = curl_init($API_URL);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch,CURLOPT_USERPWD,$accesskey . ":" . $access_psw);
                curl_setopt($ch, CURLOPT_URL, $API_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
              	$content = curl_exec($ch);
          	    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            		$curlError = curl_error($ch);
            		curl_close($ch);  
                $out=self::Object_to_array(json_decode($content));
                $this->addError(print_r($content,true)); 
              }
            }
          }
        }
        
       function refund ($order,$ps)      ///OK
       {
          if ($ps==$this->id) 
          {
            $ORDER_PRICE=$order->get_total();
            $PAY_VOUCHER_NUM=$order->get_transaction_id();
            $request=array(
                'TransactionId'=>$PAY_VOUCHER_NUM,
                'Amount'=>number_format($ORDER_PRICE, 2, '.', ''),
              //  'JsonData'=>'',
            );            
            $url = 'https://api.cloudpayments.ru/payments/refund';
  
            $accesskey=$this->public_id;
            $access_psw=$this->api_pass;
            
            if ($accesskey && $access_psw)
            {
            	$ch = curl_init($url);
              curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
              curl_setopt($ch,CURLOPT_USERPWD,$accesskey . ":" . $access_psw);
              curl_setopt($ch, CURLOPT_URL, $url);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
              curl_setopt($ch, CURLOPT_POST, true);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
            	$content = curl_exec($ch);
        	    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          		$curlError = curl_error($ch);
          		curl_close($ch);
              $out=self::Object_to_array(json_decode($content));
              $this->addError(print_r($content,true));
            }
          }
      }
        
       function void ($order,$ps)      ///OK
       {
          if ($ps==$this->id) 
          {
            $ORDER_PRICE=$order->get_total();
            $PAY_VOUCHER_NUM=$order->get_transaction_id();
            $url = 'https://api.cloudpayments.ru/payments/void';
            $request=array(
                'TransactionId'=>$PAY_VOUCHER_NUM,
            );
            $accesskey=$this->public_id;
            $access_psw=$this->api_pass;
            
            if ($accesskey && $access_psw)
            {
            	$ch = curl_init($url);
              curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
              curl_setopt($ch,CURLOPT_USERPWD,$accesskey . ":" . $access_psw);
              curl_setopt($ch, CURLOPT_URL, $url);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
              curl_setopt($ch, CURLOPT_POST, true);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
            	$content = curl_exec($ch);
        	    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          		$curlError = curl_error($ch);
          		curl_close($ch);
              $out=self::Object_to_array(json_decode($content));
              $this->addError(print_r($content,true));              
            }
          }
       }
        
        
      
       public function Object_to_array($data)      ///OK
       {
            if (is_array($data) || is_object($data))
            {
                $result = array();
                foreach ($data as $key => $value)
                {
                    $result[$key] = self::Object_to_array($value);
                }
                return $result;
            }
            return $data;
       }
        private function processRefundAction($request)  ///ok
        {
            $order=self::get_order($request);
            self::OrderSetStatus($order,$this->status_chancel);
           // self::OrderSetPaySum($order['order_id'],'0');
            $data['CODE'] = 0;
            echo json_encode($data);
        }
        
        private function processSuccessAction($request)       ///ok
        {   
            if ( $request['InvoiceId'] != null )
            {
                $order=self::get_order($request);
                $DMS_TYPE=$this->enabledDMS;    /** двухстадийка - 1 одностадийка - 0  **/
                $this->addError(print_r($DMS_TYPE,1));
                $this->addError("---------processSuccessAction--------"); 
                if ($DMS_TYPE=='yes'):
                    $data['CODE'] = 0;                         					
                    self::OrderSetStatus($order,$this->DMS_AU_status);
                    $this->addError("-----".$request['TransactionId']);
                    self::SetTransactionId($order,$request['TransactionId']);               //////////////////////////
                    $this->addError('PAY_COMPLETE - DMS_AU_status');    
                else: 
                    $data['CODE'] = 0;     
                    $order->payment_complete();
                    self::OrderSetStatus($order,$this->status_pay);
                    self::SetTransactionId($order,$request['TransactionId']);               //////////////////////////
                    $this->addError('PAY_COMPLETE');
                    
                    //определяем активность woocommerce-subscriptions
			        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                    if (is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php'))
                    {  
                        //определяем есть ли в заказе подписка
				        $order_subscription = wcs_order_contains_subscription( $request['InvoiceId'] );
				        if ($order_subscription == true)
				        {
                            $subscriptions = wcs_get_subscriptions_for_order( $request['InvoiceId'] );
                            foreach ($subscriptions as $subscription)
                            {
                                if ($this->order_enabled == 'yes')
                                {
                                    $next_payment_date = $subscription->get_date( 'next_payment' );
                                    $timestamp = wcs_date_to_time( $next_payment_date )+10200;
                                }
                                else
                                {
                                    $next_payment_date = $subscription->get_date( 'next_payment' );
                                    $timestamp = wcs_date_to_time( $next_payment_date )+11400;
                                };
                                $next_payment_date = gmdate( 'Y-m-d H:i:s', $timestamp );
                                $dates = array('next_payment'=>$next_payment_date);
                                $subscription->update_dates( $dates, $timezone = 'site' );
                            };
                        }; 
                    };
                    
                endif;
                $this->addError('----------data============');
                $this->addError(print_r($data,true));
                
                //сохраняем id подписки в ордер
                if ( $request['SubscriptionId'] != null )
                {
                    $order->update_meta_data( 'SubscriptionId', $request['SubscriptionId'] );
                    $order->save();
                };
                WC()->cart->empty_cart();
            }
            else
            {
               	$data['CODE'] = 0; 
                //определяем активность woocommerce-subscriptions
			    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                if (is_plugin_active('woocommerce-subscriptions/woocommerce-subscriptions.php'))
                {  
                    $subscription_orders = wcs_get_subscription_orders( $return_fields = 'all', $order_type = 'parent' );
                    if ($subscription_orders != null)
                    {
                        foreach ($subscription_orders as $subscription_order)
                        {   
                            $subscription_order_id = $subscription_order->get_id();
                            $meta_data = $subscription_order->get_meta_data();
                            if ($meta_data != null)
                            {
                                foreach ($meta_data as $meta_key)
                                {
                                    $meta_value = $meta_key->value;
                                    if ($meta_value == $request['SubscriptionId'])
                                    {
                                        $subscriptions = wcs_get_subscriptions_for_order( $subscription_order_id );
                                        break;
                                        break;
                                    };
                                };     
                            };  
                        };
                    };  
                    if ($subscriptions != null)            
                    {            
                        foreach ($subscriptions as $subscription)
                        {
                            $related_orders = $subscription->get_related_orders( $return_fields = 'all', $order_type = 'renewal' );
                            break;
                        };
                        if ($related_orders != null)
                        {
                            foreach ($related_orders as $related_order)
                            {
                                $related_order_status = $related_order->get_status();
                                if ($related_order_status == 'pending')
                                {
                                    $related_order->payment_complete();
                                    $related_order->set_payment_method( 'cp' );
                                    $related_order->save();
                                    self::OrderSetStatus($related_order,$this->status_pay);
                                    self::SetTransactionId($related_order,$request['TransactionId']);
                                    if ($this->order_enabled == 'yes')
                                    {
                                        $next_payment_date = $subscription->calculate_date( 'next_payment' );
                                        $timestamp = wcs_date_to_time( $next_payment_date )+10200;
                                        $next_payment_date = gmdate( 'Y-m-d H:i:s', $timestamp );
                                        $dates = array('next_payment'=>$next_payment_date);
                                        $subscription->update_dates( $dates, $timezone = 'site' );
                                    }
                                    else
                                    {
                                        $timestamp = strtotime("now")+10200;
                                        $next_payment_date = gmdate( 'Y-m-d H:i:s', $timestamp );
                                        $dates = array('next_payment'=>$next_payment_date);
                                        $subscription->update_dates( $dates, $timezone = 'site' );
                                    };
                                    echo json_encode($data);
                                    exit;
                                };
                            };
                        };
                        if ($this->order_enabled == 'yes')
                        {
                            $new_order = wcs_create_renewal_order( $subscription );
                            $new_order->payment_complete();
                            $new_order->set_payment_method( 'cp' );
                            $new_order->save();
                            self::OrderSetStatus($new_order,$this->status_pay);
                            self::SetTransactionId($new_order,$request['TransactionId']);
                            $next_payment_date = $subscription->calculate_date( 'next_payment' );
                            $timestamp = wcs_date_to_time( $next_payment_date )+10200;
                            $next_payment_date = gmdate( 'Y-m-d H:i:s', $timestamp );
                            $dates = array('next_payment'=>$next_payment_date);
                            $subscription->update_dates( $dates, $timezone = 'site' );
                        }
                        else
                        {
                            $timestamp = strtotime("now")+10200;
                            $next_payment_date = gmdate( 'Y-m-d H:i:s', $timestamp );
                            $dates = array('next_payment'=>$next_payment_date);
                            $subscription->update_dates( $dates, $timezone = 'site' );
                        };
                    };
                };
            };
        echo json_encode($data);
        }

        
        private function processFailAction($request)    // ok
        {
            $order=self::get_order($request);
            
            $data['CODE'] = 0;
            self::OrderSetStatus($order,'wc-pending');
            //return $result;
            echo json_encode($data);
        }
        
        public function SetTransactionId($order,$trans_id)  //OK
        {                                                         
          if ($order && $trans_id):
            global $wpdb;                         					
            $wpdb->update('wp_postmeta',array('meta_value' => $trans_id),array('post_id' => $order->get_id(),"meta_key"=>"_transaction_id"));
            $this->addError("SetTransactionId");
            $this->addError($trans_id);
          endif;       
        }
        
        public function OrderSetStatus($order,$status)   //OK
        {                       
              $this->addError("---------OrderSetStatus--------");
              $this->addError(print_r($status,1));
              if ($order):  
                /** Устанавливаем статус и пишем в хистори **/
                $order->update_status($status);
                ////$order->add_order_note(__('Заказ успешно оплачен!', 'woocommerce'));                 
                $this->addError("---------OrderSetStatus999--------");          
              endif;
        }
        
      	public function processCheckAction($request)     ///OK 
      	{                   
              $this->addError('processCheckAction');
              $order=self::get_order($request);
              if (!$order):
                  json_encode(array("ERROR"=>'order empty'));
                  die();
              endif;
              $accesskey=trim($this->api_pass);
    
              if($this->CheckHMac($accesskey))
              {
                  if ($this->isCorrectSum($request,$order))
                  {
                      $data['CODE'] = 0;
                      $this->addError('CorrectSum');
                  }
                  else
                  {
                      $data['CODE'] = 11;
                      $errorMessage = 'Incorrect payment sum';
      
                      $this->addError($errorMessage);
                  }
                  
                  $this->addError("Проверка заказа");
                  
                  $STATUS_CHANCEL= $this->chancel_status;
                  
                  if($this->isCorrectOrderID($order, $request))
                  {
                      $data['CODE'] = 0;
                  }
                  else
                  {
                      $data['CODE'] = 10;
                      $errorMessage = 'Incorrect order ID';
                      $this->addError($errorMessage);
                  }
      
                  $orderID=$request['InvoiceId'];
      
                  if($order->has_status($this->status_pay)):  
                      $data['CODE'] = 13;
                      $errorMessage = 'Order already paid';
                      $this->addError($errorMessage);
                  else:
                      $data['CODE'] = 0;
                  endif;
      
                   
                  if ($order->has_status($this->status_pay)|| $order->has_status($this->status_chancel))
                  {
                     $data['CODE'] = 13;
                  }  
              }
              else
              {
                  $errorMessage='ERROR HMAC RECORDS';
                  $this->addError($errorMessage);  
                  $data['CODE']=5204;            
              }
              
              $this->addError(json_encode($data));    
              
      		    echo json_encode($data);
      	}  
        
        
        private function isCorrectOrderID($order, $request)    ///ok ?????? 
        {
            $oid = $request['InvoiceId'];
            $paymentid = $order->get_id();
            $this->addError('get_id->'.$paymentid);
            return round($paymentSum, 2) == round($sum, 2);
        }
    
      	private function isCorrectSum($request,$order)  ////ok 
      	{
      		$sum = $request['Amount']; 
      		$paymentSum = $order->get_total();
          $this->addError('get_total->'.$paymentSum);
      
      		return round($paymentSum, 2) == round($sum, 2);
      	}
        
        
        private function CheckHMac($APIPASS)   //ok  
        {
            $headers = $this->detallheaders();      
            $this->addError(print_r($headers,true));        
                            
            if (isset($headers['Content-HMAC']) || isset($headers['Content-Hmac'])) 
            {
                $this->addError('HMAC_1');
                $this->addError($APIPASS);
                $message = file_get_contents('php://input');
                $s = hash_hmac('sha256', $message, $APIPASS, true);
                $hmac = base64_encode($s); 
                
                $this->addError(print_r($hmac,true));
                if ($headers['Content-HMAC']==$hmac) return true;
                else if($headers['Content-Hmac']==$hmac) return true;                                    
            }
            else return false;
        }
        
        private function detallheaders()  ///OK
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

        public function addError($text)              ///addError7
        {
              $debug=true;
              if ($debug)
              {
                $file=$_SERVER['DOCUMENT_ROOT'].'/wordpress/wp-content/plugins/woocommerce-cloudpayments/log.txt';
                $current = file_get_contents($file);
                $current .= date("d-m-Y H:i:s").":".$text."\n";
                file_put_contents($file, $current);
              }
        }
        

      	public function get_order($request)   ///OK
        {
        	global $woocommerce;			
        	$order = new WC_Order($request['InvoiceId']);
          return $order;
        }        
        
        
        public function update_order_status($order_id,$old_status,$new_status)
        {
          $DMS_TYPE=$this->enabledDMS;    /** двухстадийка - yes одностадийка - no  **/
          /** DMS CONFIRM **/
          if (("wc-".$old_status==$this->DMS_AU_status && "completed"==$new_status) || ($old_status==$this->DMS_AU_status && "completed"==$new_status) && $DMS_TYPE=='yes'):         //DMS = confirm
            $request['InvoiceId']=$order_id;
            $order=self::get_order($request);
            self::confirm($order,$order->get_payment_method());
          endif;  
          
          /** REFUND **/
          if ($this->status_chancel=="wc-".$new_status && "wc-".$old_status!=$this->DMS_AU_status):         //refund
            $request['InvoiceId']=$order_id;
            $order=self::get_order($request);
            self::refund($order,$order->get_payment_method());
          endif;  
          
          $this->addError($this->status_chancel."==wc-".$new_status." && ".$DMS_TYPE."==yes");
          /** DMS VOID **/
          if ($this->status_chancel=="wc-".$new_status && $DMS_TYPE=='yes' && "wc-".$old_status==$this->DMS_AU_status):         //DMS = void
            $request['InvoiceId']=$order_id;
            $order=self::get_order($request);
            self::void($order,$order->get_payment_method());
          endif;                    
        }
        
        
        
        
		// Callback
        public function handle_callback() 
        {
          $this->addError('handle_callback');
          self::processRequest($_GET['action'],$_POST);     //$_POST  
          exit;
          
          /**END - удалить в продакшене!!!!! **/    
        	//echo '{"code":0}';
        	$headers = getallheaders();
        	if ((!isset($headers['Content-HMAC'])) and (!isset($headers['Content-Hmac'])))
          {
        		wp_mail(get_option('admin_email'), 'не установлены заголовки', print_r($headers,1));
        		exit;
        	}
        	$message = file_get_contents('php://input');
          $posted = wp_unslash( $_POST );
            //Проверка подписи
    			$s = hash_hmac('sha256', $message, $this->api_pass, true);
    			$hmac = base64_encode($s);
    			if (!array_key_exists('Content-HMAC',$headers) && !array_key_exists('Content-Hmac',$headers) || (array_key_exists('Content-HMAC',$headers) && $headers['Content-HMAC'] != $hmac) || (array_key_exists('Content-Hmac',$headers) && $headers['Content-Hmac'] != $hmac))
          {
    			 wp_mail(get_option('admin_email'), 'подпись платежа cloudpayments некорректна', print_r($headers,1). '     payment: '. print_r($posted,1). '     HMAC: '. $hmac);
           exit("hmac error");
			    }

        	//Проверка суммы
        	global $woocommerce;			
        	$order = new WC_Order( $posted['InvoiceId'] );
        	if ($posted['Amount'] != $order->get_total())
          {
        		wp_mail(get_option('admin_email'), 'сумма заказа некорректна', print_r($headers,1). '     payment: '. print_r($posted,1). '     order: '. print_r($order,1));
        		exit("sum error");
        	}
          update_post_meta($posted['InvoiceId'], 'CloudPayments', json_encode($posted, JSON_UNESCAPED_UNICODE));

          if ($posted['Status'] == 'Completed') 
          {
            ///проверка типа системы
            if ($TYPE==2):     //двухстадийка   
             
            else:
              //Помечаем заказ, как «Оплаченный» в системе учета магазина
              $order->payment_complete();
              $order->add_order_note(__('Заказ успешно оплачен', 'woocommerce'));      
              WC()->cart->empty_cart();
            endif; 
          } 
          else
          {
            $order->update_status('on-hold', __('Заказ ожидает оплаты', 'woocommerce'));
            $order->add_order_note(__('Заказ ожидает оплаты', 'woocommerce'));
            WC()->cart->empty_cart();
          }
          exit;
        }
	}
}




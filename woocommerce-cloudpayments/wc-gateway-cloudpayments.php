<?php
/**
 * Plugin Name: WooCommerce CloudPayments Gateway
 * Plugin URI: http://woothemes.com/woocommerce
 * Description: Extends WooCommerce with CloudPayments Gateway.
 * Version: 2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// if ngnix, not Apache 
///add_action('woocommerce_order_status_changed', 'woo_order_status_change_custom', 10, 3);

 /*
function woo_order_status_change_custom($order_id,$old_status,$new_status)
{
  addError2($order_id);
  addError2(print_r($old_status,1));
  addError2(print_r($new_status,1));
}

function addError2($text)              ///addError7
{
      $debug=true;
      if ($debug)
      {
        $file=$_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/woocommerce-cloudpayments/log.txt';
        $current = file_get_contents($file);
        $current .= date("d-m-Y H:i:s").":".$text."\n";
        file_put_contents($file, $current);
      }
}   */


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
//		$this->order_button_text  =  __( 'Proceed to CP', 'woocommerce' );
			$this->method_title       =  __( 'CloudPayments', 'woocommerce' );
			$this->method_description =  'CloudPayments – самый простой и удобный способ оплаты. Комиссий нет.';
			$this->supports           =  array( 'products','pre-orders' );
      $this->enabled            =  $this->get_option( 'enabled' );
      $this->enabledDMS         =  $this->get_option( 'enabledDMS' );
      $this->DMS_AU_status      =  $this->get_option( 'DMS_AU_status' );
      $this->DMS_CF_status      =  $this->get_option( 'DMS_CF_status' );
      $this->language           =  $this->get_option( 'language' );
      
    //  $this->DMS_RE_status    =  $this->get_option( 'DMS_RE_status' );
      $this->status_chancel     =  $this->get_option( 'status_chancel' );
      $this->status_pay         =  $this->get_option( 'status_pay' );
			$this->init_form_fields();
			$this->init_settings();
      
			$this->title          	= $this->get_option( 'title' );
			$this->description    	= $this->get_option( 'description' );
			$this->public_id    	  = $this->get_option( 'public_id' );
			$this->api_pass    	  	= $this->get_option( 'api_pass' );
			$this->currency    	  	= $this->get_option( 'currency' );
      
			// Онлайн-касса
			$this->kassa_enabled    = $this->get_option( 'kassa_enabled' );
			$this->kassa_taxtype    = $this->get_option( 'kassa_taxtype' );
			$this->kassa_taxsystem  = $this->get_option( 'kassa_taxsystem' );
			$this->kassa_skubarcode  = $this->get_option( 'kassa_skubarcode' );
			add_action( 'woocommerce_receipt_cp', 	array( $this, 'payment_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      //add_action( 'woocommerce_api_callback', array( $this, 'handle_callback' ) );
      add_action( 'woocommerce_api_'. strtolower( get_class( $this ) ), array( $this, 'handle_callback' ) );
      //add_action( 'woocommerce_update_order', array( $this, 'update_order' ) );
     // add_action('woocommerce_order_status_changed',array( $this, 'update_order_status' ));
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
		        <p><strong>В личном кабинете включите Check-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=check"?></p>
		        <p><strong>В личном кабинете включите Pay-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=pay"?></p>
		        <p><strong>В личном кабинете включите Refund-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=refund"?></p>
		        <p><strong>В личном кабинете включите Confirm-уведомление на адрес:</strong> <?=home_url('/wc-api/'.strtolower(get_class($this)))."?action=confirm"?></p>
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
					'title' 	=> __( 'Enable/Disable', 'woocommerce' ),
					'type' 		=> 'checkbox',
					'label' 	=> __( 'Включить CloudPayments', 'woocommerce' ),
					'default' 	=> 'yes'
				),
				'enabledDMS' => array(
					'title' 	=> __( 'Включить DMS', 'woocommerce' ),
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
				'delivery_taxtype' => array(
					'title'       => __( 'Ставка НДС для доставки', 'woocommerce' ),
					'type'        => 'select',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Выберите ставку НДС, которая применима к доставке в магазине.', 'woocommerce' ),
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

    $this->addError("Проверка заказа");
			global $woocommerce;			
			$order = new WC_Order( $order_id );
			$title = array();
			$items_array = array();
			$items = $order->get_items();
			$shipping_data = array("label"=>"Доставка", "price"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''), "quantity"=>"1.00", "amount"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''), "vat"=>$this->delivery_taxtype, "ean"=>null);
			foreach ($items as $item) {
				if ($this->kassa_enabled == 'yes') {
				$product = $order->get_product_from_item($item);
				$items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$product->get_price(), 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''), "amount"=>number_format((float)$item['total']+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype, "ean"=>($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
				}
				$title[] = $item['name'] . (isset($item['pa_ver']) ? ' ' . $item['pa_ver'] : '');
			}
			if ($this->kassa_enabled == 'yes' && $order->get_total_shipping() > 0) $items_array[] = $shipping_data;
			$kassa_array = array("cloudPayments"=>(array("customerReceipt"=>array("Items"=>$items_array, "taxationSystem"=>$this->kassa_taxsystem, "email"=>$order->billing_email, "phone"=>$order->billing_phone))));
			$title = implode(', ', $title);
      
      $widget_f='charge';
      if ($this->enabledDMS!='no')
      {
          $widget_f='auth';
      }
			?>

			<script src="https://widget.cloudpayments.ru/bundles/cloudpayments"></script>
			<script>
				var widget = new cp.CloudPayments({language: '<?=$this->language?>'});// язык виджета
		    	widget.<?=$widget_f?>({ // options              <!-- /////////////???????????????  -->
		            publicId: '<?=$this->public_id?>',  //id из личного кабинета
		            description: 'Оплата заказа <?=$order_id?>', //назначение
		            amount: <?=$order->get_total()?>, //сумма
		            currency: '<?=$this->currency?>', //валюта
		            invoiceId: <?=$order_id?>, //номер заказа 
		            accountId: '<?=$order->billing_email?>', //идентификатор плательщика
		            data: 
		                <?php echo (($this->kassa_enabled == 'yes') ? json_encode($kassa_array) : "{}") ?>
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
      	      //$result = new PaySystem\ServiceResult();
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
              else
              {
      
                  $data['TECH_MESSAGE'] = 'Unknown action: '.$action;
                  $this->addError('Unknown action: '.$action.'. Request='.print_r($request,true));
                  exit('{"code":0}');
              }
      	}
        
        private function processconfirmAction($request)   //ok
        {     
            $order=self::get_order($request);
            $data['CODE'] = 0;                         					
            self::OrderSetStatus($order,$this->status_pay);
          //  self::OrderSetPaySum($order['order_id'],$request['PaymentAmount']);
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
            endif;
           $this->addError('----------data============');
           $this->addError(print_r($data,true));
            WC()->cart->empty_cart();
            echo json_encode($data);
        }
        
        private function processFailAction($request)    // ok
        {
            $order=self::get_order($request);
            
            $data['CODE'] = 0;
            self::OrderSetStatus($order,'wc-pending');
            return $result;
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
                $file=$_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/woocommerce-cloudpayments/log.txt';
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




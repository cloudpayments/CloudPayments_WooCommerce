<?php

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_CloudPayments_Gateway extends WC_Payment_Gateway
{
    
    public function __construct()
    {
        $this->id                 = 'wc_cloudpayments_gateway';
        $this->has_fields         = true;
        $this->icon               = plugin_dir_url(__FILE__) . '../visa-mastercard.png';
        $this->method_title       = __('CloudPayments', 'woocommerce');
        $this->method_description = 'CloudPayments – самый простой и удобный способ оплаты. Комиссий нет.';
        $this->supports           = array(
            'pre-orders',
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
            'tokenization'
        );
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title       = 'CloudPayments';
        $this->description = 'CloudPayments';
        
        $this->enabled            = $this->get_option('enabled');
        $this->enabledDMS         = $this->get_option('enabledDMS');
        $this->DMS_AU_status      = $this->get_option('DMS_AU_status');
        $this->DMS_CF_status      = $this->get_option('DMS_CF_status');
        $this->skin               = $this->get_option('skin');
        $this->language           = $this->get_option('language');
        $this->status_chancel     = $this->get_option('status_chancel');
        $this->status_pay         = $this->get_option('status_pay');
        $this->title              = $this->get_option('title');
        $this->description        = $this->get_option('description');
        $this->public_id          = $this->get_option('public_id');
        $this->api_pass           = $this->get_option('api_pass');
        $this->currency           = $this->get_option('currency');
		$this->url_return         = $this->get_option('url_return');
        $this->kassa_enabled      = $this->get_option('kassa_enabled');
        $this->kassa_taxtype      = $this->get_option('kassa_taxtype');
        $this->delivery_taxtype   = $this->get_option('delivery_taxtype');
        $this->kassa_taxsystem    = $this->get_option('kassa_taxsystem');
        $this->kassa_skubarcode   = $this->get_option('kassa_skubarcode');
        $this->kassa_method       = $this->get_option('kassa_method');
        $this->kassa_object       = $this->get_option('kassa_object');
        $this->status_delivered   = $this->get_option('status_delivered');
        $this->inn                = $this->get_option('inn');
        $this->order_text         = $this->get_option('order_text');
        $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
        $this->shipping_spic      = $this->get_option('shipping_spic');
        $this->shipping_package_code = $this->get_option('shipping_package_code');

        if($this->currency === 'UZS')
            $this->icon = plugin_dir_url(__FILE__) . '../visa-mastercard-uz.png';
	
	    if($this->currency === 'siteCurrency')
		    $this->currency = get_woocommerce_currency();

        add_action('woocommerce_receipt_wc_cloudpayments_gateway', array($this, 'payment_page'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_order_status_changed', array($this, 'update_order_status'), 10, 3);
        add_action('woocommerce_scheduled_subscription_payment_wc_cloudpayments_gateway', array($this, 'scheduled_subscription_payment'), 100, 2);
        add_action('woocommerce_api_cloud_payments_widget', array($this, 'cloud_payments_widget'));
        
        /** API init */
        $api = new CloudPayments_Api($this->enabledDMS, $this->status_chancel, $this->status_pay, $this->api_pass, $this->DMS_AU_status, $this->public_id);
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($api, 'processRequest'));
    }
    
    public function cloud_payments_widget()
    {
        require(CPGWWC_PLUGIN_DIR . 'templates/cloudpayments-widget.php');
        die();
    }
    
    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new WC_Order($order_id);
		
		if (empty($_POST['cp_card']))
			return false;
        
        if ($_POST['cp_card'] == 'widget') {
            $cp_save_card = $_POST['cp_save_card'] ? 1 : 0; // 1 : 1 всегда сохранять токен
            
            $query = array(
                'order_id' => $order_id,
                'return_ok' => $this->get_return_url($order),
                'cp_save_card' => $cp_save_card
            );
            return array(
                'result'   => 'success',
                'redirect' => home_url('/wc-api/cloud_payments_widget?') . http_build_query($query)
            );
        }
        
        $title         = array();
        $items_array   = array();
        $items         = $order->get_items();
        $shipping_data = apply_filters(
			'cloudpayments_process_payment_shipping_data',
			array(
				'label'    => __( 'Shipping', 'woocommerce' ),
				'price'    => number_format( (float) $order->get_shipping_total() + abs( (float) $order->get_shipping_tax() ), 2, '.', '' ),
				'quantity' => '1.00',
				'amount'   => number_format( (float) $order->get_shipping_total() + abs( (float) $order->get_shipping_tax() ), 2, '.', '' ),
				'vat'      => ( 'null' == $this->delivery_taxtype) ? null : $this->delivery_taxtype,
				'method'   => (int) $this->kassa_method,
				'object'   => 4,
				'ean'      => null,
			),
			$order,
			$this
		);
        
        foreach ($items as $item) {
            if ($this->kassa_enabled == 'yes') {
                $product       = $item->get_product();
                $items_array[] = apply_filters(
					'cloudpayments_process_payment_order_item',
					array(
						'label'    => $item['name'],
						'price'    => number_format( (float) $product->get_price(), 2, '.', '' ),
						'quantity' => number_format( (float) $item['quantity'], 2, '.', '' ),
						'amount'   => number_format( (float) $item['total'] + abs( (float) $item['total_tax'] ), 2, '.', '' ),
						'vat'      => ( 'null' == $this->kassa_taxtype ) ? null : $this->kassa_taxtype,
						'method'   => (int) $this->kassa_method,
						'object'   => (int) $this->kassa_object,
						'ean'      => ( 'yes' == $this->kassa_skubarcode ) ? ( ( strlen( $product->get_sku() ) < 1 ) ? null : $product->get_sku() ) : null,
					),
					$product,
					$item_id,
					$item,
					$this
				);
            }
            $title[] = $item['name'] . (isset($item['pa_ver']) ? ' ' . $item['pa_ver'] : '');
        }
        
        if ($this->kassa_enabled == 'yes' && $order->get_shipping_total() > 0) {
            $items_array[] = $shipping_data;
        }
        
        $kassa_array = array(
            "cloudPayments" => (array(
                "customerReceipt" => array(
                    "Items"            => $items_array,
                    "taxationSystem"   => $this->kassa_taxsystem,
                    'calculationPlace' => 'www.' . $_SERVER['SERVER_NAME'],
                    "email"            => $order->get_billing_email(),
                    "phone"            => $order->get_billing_phone()
                )
            ))
        );
        
        $widget_f = 'charge';
        
        if ($this->enabledDMS != 'no') {
            $widget_f = 'auth';
        }
        
        $accesskey  = $this->public_id;
        $access_psw = $this->api_pass;
        
        $token = WC_Payment_Tokens::get((int)$_POST['cp_card']);
        
        $request = array(
            'Token'       => $token->get_token(),
            'Amount'      => $order->get_total(),
            'Currency'    => $this->currency,
            'InvoiceId'   => $order_id,
            'AccountId'   => $token->get_user_id(),
            'Email'       => $order->billing_email,
            'Description' => 'Оплата заказа № ' . $order_id,
            'IpAddress'   => $_SERVER['REMOTE_ADDR'],
            'JsonData'    => $this->kassa_enabled == 'yes' ? $kassa_array : [],
        );
        
        //отправляем запрос        
        $auth     = base64_encode($accesskey . ":" . $access_psw);
        $response = wp_remote_post('https://api.cloudpayments.ru/payments/tokens/' . $widget_f,
            array(
                'timeout'     => 30,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array('Authorization' => 'Basic ' . $auth, 'Content-Type' => 'application/json'),
                'body'        => json_encode($request, JSON_UNESCAPED_UNICODE)
            )
        );
        
        $response['body'] = json_decode($response['body'], true);
        
        if ($response['body']['Success'] == true) {
            
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        if ($response['body']['Message'] != null) {
            
            wc_add_notice(__('Error:', 'woocommerce') . ' ' . $response['body']['Message'], 'error');
        }
        
        if ($response['body']['Model']['CardHolderMessage'] != null) {
            
            wc_add_notice(__('Error:', 'woocommerce') . ' ' . $response['body']['Model']['CardHolderMessage'], 'error');
        }
        
        return false;
    }
    
    public function add_payment_method()
    {
        return array(
            'redirect' => home_url('/wc-api/cloud_payments_widget') . '?action=add_payment_method&return_ok=' . wc_get_endpoint_url('payment-methods')
        );
    }
    
    public function admin_options()
    {
        if ( ! $this->ssl_check()) {
            ?>
            <div class="inline error"><p><strong><?php echo __('Warning', 'woocommerce'); ?></strong>: <?php echo $this->msg; ?></p></div>
            <?php
        }
        ?>
        <h3>CloudPayments</h3>
        <p>CloudPayments – прямой и простой прием платежей с кредитных карт</p>
        
		<?php
        $account_actions = array(
            'check',
            'pay',
            'fail',
            'confirm',
            'refund',
            'cancel',
        );

        $action_url_template = home_url( '/wc-api/' . strtolower( get_class( $this ) ) );
        ?>
        <div class="cloudpayments-attention">
            <?php foreach ( $account_actions as $key => $action ) : ?>
                <div class="account-action">
                    <label class="account-action__label">
                        <?php
                        printf(
                            esc_html__( 'В личном кабинете включите %s-уведомление на адрес', 'cloudpayments' ),
                            esc_html( ucfirst( $action ) )
                        );
                        ?>
                    </label>
                    <div class="account-action__input copy-to-clipboard-container">
                        <?php
                        printf(
                            '<input type="text" class="input-text cloudpayments_action_input" id="cloudpayments_account_action_%s_url" value="%s" readonly>',
                            esc_attr( $action ),
                            esc_attr( $action_url_template . '?action=' . $action )
                        );
                        ?>
                        <?php
                        printf(
                            '<button type="button" class="button copy-cloudpayments-action-url" data-clipboard-target="#cloudpayments_account_action_%s_url" title="%s"><svg class="svg-icon" version="1.1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M14.016 12h5.484l-5.484-5.484v5.484zM15 5.016l6 6v9.984q0 0.797-0.609 1.406t-1.406 0.609h-11.016q-0.797 0-1.383-0.609t-0.586-1.406v-14.016q0-0.797 0.609-1.383t1.406-0.586h6.984zM15.984 0.984v2.016h-12v14.016h-1.969v-14.016q0-0.797 0.586-1.406t1.383-0.609h12z"></path></svg> <span class="screen-reader-text">%s</button>',
                            esc_attr( $action ),
                            esc_attr__( 'Copy URL to clipboard' ),
                            esc_html__( 'Copy URL to clipboard' )
                        );
                        ?>
                        <span class="success hidden" aria-hidden="true"><?php esc_html_e( 'Copied!' ); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>

            <p>Кодировка UTF-8, HTTP-метод POST, Формат запроса CloudPayments.</p>
        </div>

        <?php
        
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }
    
    public function payment_fields()
    {
        if (is_account_page()) {
            return;
        }
        require(CPGWWC_PLUGIN_DIR . 'templates/payment-fields-checkout.php');
    }
    
    public function init_form_fields()
    {
        $array_status      = wc_get_order_statuses();
        $this->form_fields = array(
            'enabled'          => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Включить CloudPayments', 'woocommerce'),
                'default' => 'yes'
            ),
            'enabledDMS'       => array(
                'title'   => __('Включить DMS', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Включить DMS', 'woocommerce'),
                'default' => 'no'
            ),
            'order_text'       => array(
                'title'    => __('Текст оплаты заказа', 'woocommerce'),
                'type'     => 'text',
                'default'  => __('Оплата заказа:', 'woocommerce'),
                'desc_tip' => true,
            ),
            'status_pay'       => array(
                'title'       => __('Статус для оплаченного заказа', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('', 'woocommerce'),
                'default'     => 'wc-completed',
                'desc_tip'    => true,
                'options'     => $array_status,
            ),
            'status_chancel'   => array(
                'title'       => __('Статус для отмененного заказа', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('', 'woocommerce'),
                'default'     => 'wc-cancelled',
                'desc_tip'    => true,
                'options'     => $array_status,
            ),
            'DMS_AU_status'    => array(
                'title'       => __('Статус авторизованного платежа (DMS)', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('', 'woocommerce'),
                'default'     => 'wc-pay_au',
                'desc_tip'    => true,
                'options'     => $array_status,
            ),
            'title'            => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default'     => __('Банковская карта', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'description'      => array(
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                'default'     => __('CloudPayments – самый простой и удобный способ оплаты. Комиссий нет.', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'public_id'        => array(
                'title'       => __('Public ID', 'woocommerce'),
                'type'        => 'text',
                'description' => 'Возьмите из личного кабинета CloudPayments',
                'default'     => '',
                'desc_tip'    => false,
            ),
            'api_pass'         => array(
                'title'       => __('Пароль для API', 'woocommerce'),
                'type'        => 'text',
                'description' => 'Возьмите из личного кабинета CloudPayments',
                'default'     => '',
                'desc_tip'    => false,
            ),
            'currency'         => array(
                'title'   => __('Валюта магазина', 'woocommerce'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'RUB',
                'options' => array(
                    'siteCurrency' => __('Валюта магазина', 'woocomerce'),
                    'RUB' => __('Российский рубль', 'woocommerce'),
                    'EUR' => __('Евро', 'woocommerce'),
                    'USD' => __('Доллар США', 'woocommerce'),
                    'GBP' => __('Фунт стерлингов', 'woocommerce'),
                    'UAH' => __('Украинская гривна', 'woocommerce'),
                    'BYN' => __('Белорусский рубль', 'woocommerce'),
                    'KZT' => __('Казахский тенге', 'woocommerce'),
                    'AZN' => __('Азербайджанский манат', 'woocommerce'),
                    'CHF' => __('Швейцарский франк', 'woocommerce'),
                    'CZK' => __('Чешская крона', 'woocommerce'),
                    'CAD' => __('Канадский доллар', 'woocommerce'),
                    'PLN' => __('Польский злотый', 'woocommerce'),
                    'SEK' => __('Шведская крона', 'woocommerce'),
                    'TRY' => __('Турецкая лира', 'woocommerce'),
                    'CNY' => __('Китайский юань', 'woocommerce'),
                    'INR' => __('Индийская рупия', 'woocommerce'),
                    'BRL' => __('Бразильский реал', 'woocommerce'),
                    'ZAR' => __('Южноафриканский рэнд', 'woocommerce'),
                    'UZS' => __('Узбекский сум', 'woocommerce'),
                    'BGL' => __('Болгарский лев', 'woocommerce'),
                ),
            ),
            'skin'             => array(
                'title'   => __('Дизайн виджета', 'woocommerce'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'classic',
                'options' => array(
                    'classic' => __('Классический', 'woocommerce'),
                    'modern'  => __('Модерн', 'woocommerce'),
                    'mini'    => __('Мини', 'woocommerce'),
                ),
            ),
            'language'         => array(
                'title'   => __('Язык виджета', 'woocommerce'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'ru-RU',
                'options' => array(
                    'ru-RU' => __('Русский', 'woocommerce'),
                    'en-US' => __('Английский', 'woocommerce'),
                    'lv'    => __('Латышский', 'woocommerce'),
                    'az'    => __('Азербайджанский', 'woocommerce'),
                    'kk'    => __('Русский', 'woocommerce'),
                    'kk-KZ' => __('Казахский', 'woocommerce'),
                    'uk'    => __('Украинский', 'woocommerce'),
                    'pl'    => __('Польский', 'woocommerce'),
                    'pt'    => __('Португальский', 'woocommerce'),
                    'cs-CZ' => __('Чешский', 'woocommerce'),
                    'uz'    => __('Узбекский', 'woocommerce'),
                ),
            ),
            'enable_for_methods' => array(
                'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 400px;',
                'default'           => '',
                'description'       => __( 'Если CloudPayments доступен только для определенных методов доставки, выберите их здесь. Оставьте поле пустым, чтобы включить CloudPayments для всех методов доставки.', 'cloudpayments' ),
                'options'           => $this->load_shipping_method_options(),
                'desc_tip'          => true,
                'custom_attributes' => array(
                    'data-placeholder' => __( 'Select shipping methods', 'woocommerce' ),
                ),
            ),
            'kassa_section'    => array(
                'title'       => __('Онлайн-касса', 'woocommerce'),
                'type'        => 'title',
                'description' => '',
            ),
            'kassa_enabled'    => array(
                'title'   => __('Enable/Disable', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Включить отправку данных для онлайн-кассы (по 54-ФЗ)', 'woocommerce'),
                'default' => 'no'
            ),
            'inn'              => array(
                'title'       => __('ИНН', 'woocommerce'),
                'type'        => 'text',
                'description' => '',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'kassa_taxtype'    => array(
                'title'       => __('Ставка НДС', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Выберите ставку НДС, которая применима к товарам в магазине.', 'woocommerce'),
                'default'     => '10',
                'desc_tip'    => true,
                'options'     => array(
                    'null' => __('НДС не облагается', 'woocommerce'),
                    '20'   => __('НДС 20%', 'woocommerce'),
                    '10'   => __('НДС 10%', 'woocommerce'),
                    '0'    => __('НДС 0%', 'woocommerce'),
                    '12'   => __('НДС 12%', 'woocommerce'),
                    '110'  => __('расчетный НДС 10/110', 'woocommerce'),
                    '120'  => __('расчетный НДС 20/120', 'woocommerce'),
                ),
            ),
            'delivery_taxtype' => array(
                'title'       => __('Ставка НДС для доставки', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Выберите ставку НДС, которая применима к доставке в магазине.', 'woocommerce'),
                'default'     => '10',
                'desc_tip'    => true,
                'options'     => array(
                    'null' => __('НДС не облагается', 'woocommerce'),
                    '20'   => __('НДС 20%', 'woocommerce'),
                    '10'   => __('НДС 10%', 'woocommerce'),
                    '0'    => __('НДС 0%', 'woocommerce'),
                    '12'   => __('НДС 12%', 'woocommerce'),
                    '110'  => __('расчетный НДС 10/110', 'woocommerce'),
                    '120'  => __('расчетный НДС 20/120', 'woocommerce'),
                ),
            ),
            'kassa_taxsystem'  => array(
                'title'       => __('Cистема налогообложения организации', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Указанная система налогообложения должна совпадать с одним из вариантов, зарегистрированных в ККТ.', 'woocommerce'),
                'default'     => '1',
                'desc_tip'    => true,
                'options'     => array(
                    '0' => __('Общая система налогообложения', 'woocommerce'),
                    '1' => __('Упрощенная система налогообложения (Доход)', 'woocommerce'),
                    '2' => __('Упрощенная система налогообложения (Доход минус Расход)', 'woocommerce'),
                    '3' => __('Единый налог на вмененный доход', 'woocommerce'),
                    '4' => __('Единый сельскохозяйственный налог', 'woocommerce'),
                    '5' => __('Патентная система налогообложения', 'woocommerce'),
                ),
            ),
            'kassa_method'     => array(
                'title'       => __('Способ расчета', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Выберите способ расчета', 'woocommerce'),
                'default'     => '1',
                'desc_tip'    => true,
                'options'     => array(
                    '0' => __('Способ расчета не передается', 'woocommerce'),
                    '1' => __('Предоплата 100%', 'woocommerce'),
                    '2' => __('Предоплата', 'woocommerce'),
                    '3' => __('Аванс', 'woocommerce'),
                    '4' => __('Полный расчёт', 'woocommerce'),
                    '5' => __('Частичный расчёт и кредит', 'woocommerce'),
                    '6' => __('Передача в кредит', 'woocommerce'),
                    '7' => __('Оплата кредита', 'woocommerce'),
                ),
            ),
            'kassa_object'     => array(
                'title'       => __('Предмет расчета', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Выберите предмет расчета', 'woocommerce'),
                'default'     => '1',
                'desc_tip'    => true,
                'options'     => array(
                    '0'  => __('Предмет расчета не передается', 'woocommerce'),
                    '1'  => __('Товар', 'woocommerce'),
                    '2'  => __('Подакцизный товар', 'woocommerce'),
                    '3'  => __('Работа', 'woocommerce'),
                    '4'  => __('Услуга', 'woocommerce'),
                    '5'  => __('Ставка азартной игры', 'woocommerce'),
                    '6'  => __('Выигрыш азартной игры', 'woocommerce'),
                    '7'  => __('Лотерейный билет', 'woocommerce'),
                    '8'  => __('Выигрыш лотереи', 'woocommerce'),
                    '9'  => __('Предоставление РИД', 'woocommerce'),
                    '10' => __('Платеж', 'woocommerce'),
                    '11' => __('Агентское вознаграждение', 'woocommerce'),
                    '12' => __('Составной предмет расчета', 'woocommerce'),
                    '13' => __('Иной предмет расчета', 'woocommerce'),
                ),
            ),
            'status_delivered' => array(
                'title'       => __('Статус которым пробивать 2ой чек при отгрузке товара или выполнении услуги', 'woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Согласно ФЗ-54 владельцы онлайн-касс должны формировать чеки для зачета и предоплаты. Отправка второго чека возможна только при следующих способах расчета: Предоплата, Предоплата 100%, Аванс', 'woocommerce'),
                'default'     => 'wc-pay_delivered',
                'desc_tip'    => true,
                'options'     => $array_status,
            ),
            'kassa_skubarcode' => array(
                'title'   => __('Действие со штрих-кодом', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Отправлять артикул (SKU) товара как штрих-код', 'woocommerce'),
                'default' => 'yes'
            ),
            'shipping_spic' => array(
                'title'       => __('Код ИКПУ доставки', 'woocommerce'),
                'type'        => 'text',
                'description' => '',
                'default'     => '',
        ),
            'shipping_package_code' => array(
                'title'       => __('Код упаковки доставки', 'woocommerce'),
                'type'        => 'text',
                'description' => '',
                'default'     => '',
            )
        );
    }
    
    public function ssl_check()
    {
        if (get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes') {
            $this->msg     = sprintf(__('Включена поддержка оплаты через CloudPayments и не активирована опция <a href="%s">"Принудительная защита оформления заказа"</a>; безопасность заказов находится под угрозой! Пожалуйста, включите SSL и проверьте корректность установленных сертификатов.', 'woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout'));
            $this->enabled = false;
            
            return false;
        }
        
        return true;
    }
    
    public function update_order_status($order_id, $old_status, $new_status)
    {
        if ($this->kassa_enabled == 'yes') {
            global $woocommerce;
            $order = new WC_Order($order_id);
            if ("wc-" . $new_status == $this->status_delivered && ((int)$this->kassa_method == 1 || (int)$this->kassa_method == 2 || (int)$this->kassa_method == 3)) {
                $this->send_receipt($order, 'Income', $old_status, $new_status);
            }
        }
    }
    
    public function send_receipt($order, $type, $old_status, $new_status)
    {
        $cart         = $order->get_items();
        $total_amount = 0;
        
        foreach ($cart as $item_data) {
            $product = $item_data->get_product();
            if ("wc-" . $new_status == $this->status_delivered) {
                $method = 4;
            } else {
                $method = (int)$this->kassa_method;
            }
            $items[] = apply_filters(
				'cloudpayments_send_receipt_item',
				array(
					'label'    => $product->get_name(),
					'price'    => number_format( $product->get_price(), 2, '.', '' ),
					'quantity' => $item_data->get_quantity(),
					'amount'   => number_format( floatval( $item_data->get_total() ), 2, '.', '' ),
					'vat'      => $this->kassa_taxtype,
					'method'   => $method,
					'object'   => (int) $this->kassa_object,
				),
				$product,
				$item_id,
				$item_data,
				$method,
				$this
			);
            
            $total_amount = $total_amount + number_format(floatval($item_data->get_total()), 2, ".", '');
        }
        
        if ($order->get_total_shipping()) {
			$items[] = apply_filters(
				'cloudpayments_send_receipt_shipping_data',
				array(
					'label'    => 'Доставка',
					'price'    => $order->get_total_shipping(),
					'quantity' => 1,
					'amount'   => $order->get_total_shipping(),
					'vat'      => $this->delivery_taxtype,
					'method'   => $method,
					'object'   => 4,
				),
				$order,
				$this
			);
            
            $total_amount = $total_amount + number_format(floatval($order->get_total_shipping()), 2, ".", '');
        }
        
        $data['Items']                 = $items;
        $data['taxationSystem']        = $this->kassa_taxsystem;
        $data['calculationPlace']      = 'www.' . $_SERVER['SERVER_NAME'];
        $data['email']                 = $order->get_billing_email();
        $data['phone']                 = $order->get_billing_phone();
        $data['amounts']['electronic'] = $total_amount;
        
        if ("wc-" . $new_status == $this->status_delivered) {
            $data['amounts']['electronic']     = 0;
            $data['amounts']['advancePayment'] = $total_amount;
        }
        
        $aData = apply_filters(
			'cloudpayments_send_receipt_data',
			array(
				'Inn'             => $this->inn,
				'InvoiceId'       => $order->get_id(), // номер заказа, необязательный.
				'AccountId'       => $order->get_user_id(),
				'Type'            => $type,
				'CustomerReceipt' => $data,
			),
			$order,
			$this
		);
        
        $API_URL  = 'https://api.cloudpayments.ru/kkt/receipt';
        $request2 = json_encode($aData);
        
        $str   = date("d-m-Y H:i:s") . $aData['Type'] . $aData['InvoiceId'] . $aData['AccountId'] . $aData['CustomerReceipt']['email'];
        $reque = md5($str);
        $auth  = base64_encode($this->public_id . ":" . $this->api_pass);
        
        wp_remote_post($API_URL, array(
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array('Authorization' => 'Basic ' . $auth, 'Content-Type' => 'application/json', 'X-Request-ID' => $reque),
            'body'        => $request2,
            'cookies'     => array()
        ));
    }
    
    public function scheduled_subscription_payment($amount_to_charge, $order)
    {
        global $woocommerce;
        $order_id      = $order->get_id();
        $title         = array();
        $items_array   = array();
        $items         = $order->get_items();
      	$shipping_data = apply_filters(
			'cloudpayments_scheduled_subscription_payment_shipping_data',
			array(
				'label'    => 'Доставка',
				'price'    => number_format( (float) $order->get_total_shipping() + abs( (float) $order->get_shipping_tax() ), 2, '.', '' ),
				'quantity' => '1.00',
				'amount'   => number_format( (float) $order->get_total_shipping() + abs( (float) $order->get_shipping_tax() ), 2, '.', '' ),
				'vat'      => ( 'null' == $this->delivery_taxtype ) ? null : $this->delivery_taxtype,
				'method'   => (int) $this->kassa_method,
				'object'   => 4,
				'ean'      => null,
			),
			$order,
			$this
		);
		
        foreach ($items as $item) {
            if ($this->kassa_enabled == 'yes') {
                $product       = $order->get_product_from_item($item);
                $items_array[] = apply_filters(
					'cloudpayments_scheduled_subscription_payment_order_item',
					array(
						'label'    => $item['name'],
						'price'    => number_format( (float) $product->get_price(), 2, '.', '' ),
						'quantity' => number_format( (float) $item['quantity'], 2, '.', '' ),
						'amount'   => number_format( (float) $item['total'] + abs( (float) $item['total_tax'] ), 2, '.', '' ),
						'vat'      => ( 'null' == $this->kassa_taxtype ) ? null : $this->kassa_taxtype,
						'method'   => (int) $this->kassa_method,
						'object'   => (int) $this->kassa_object,
						'ean'      => ( 'yes' == $this->kassa_skubarcode ) ? ( ( strlen( $product->get_sku() ) < 1 ) ? null : $product->get_sku() ) : null,
					),
					$product,
					$item_id,
					$item,
					$this
				);
            }
            $title[] = $item['name'] . (isset($item['pa_ver']) ? ' ' . $item['pa_ver'] : '');
        }
        if ($this->kassa_enabled == 'yes' && $order->get_total_shipping() > 0) {
            $items_array[] = $shipping_data;
        }
        $kassa_array = array(
            "cloudPayments" => (array(
                "customerReceipt" => array(
                    "Items"            => $items_array,
                    "taxationSystem"   => $this->kassa_taxsystem,
                    'calculationPlace' => 'www.' . $_SERVER['SERVER_NAME'],
                    "email"            => $order->billing_email,
                    "phone"            => $order->billing_phone
                )
            ))
        );
        $title       = implode(', ', $title);
        
        $widget_f = 'charge';
        if ($this->enabledDMS != 'no') {
            $widget_f = 'auth';
        }
        
        $user_id = $order->get_user_id();
        $tokens   = WC_Payment_Tokens::get_customer_tokens( $user_id, 'wc_cloudpayments_gateway' );

        if (!empty($tokens)):
            $default = array_filter($tokens, fn ($t) => $t->is_default());
            $token = count($default)
                ? array_shift($default)
                : array_pop($tokens);
        endif;
        
        if (isset($token)) {
            $request = array(
                'Token'       => $token->get_token(),
                'Amount'      => $order->get_total(),
                'Currency'    => $this->currency,
                'InvoiceId'   => $order->get_id(),
                'AccountId'   => $user_id,
                'Email'       => $order->billing_email,
                'Description' => 'Оплата заказа № ' . $order_id,
                'IpAddress'   => $_SERVER['REMOTE_ADDR'],
                'JsonData'    => $this->kassa_enabled == 'yes' ? $kassa_array : [],
            );
            
            $auth     = base64_encode($this->public_id . ":" . $this->api_pass);
            $response = wp_remote_post('https://api.cloudpayments.ru/payments/tokens/' . $widget_f,
                array(
                    'timeout'     => 30,
                    'redirection' => 5,
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array('Authorization' => 'Basic ' . $auth, 'Content-Type' => 'application/json'),
                    'body'        => json_encode($request, JSON_UNESCAPED_UNICODE)
                )
            );
            
            $response['body'] = json_decode($response['body'], true);
            
            if ($response['body']['Success'] == true) {
                $order->payment_complete();
                $order->add_order_note(sprintf('Payment approved (TransactionID: %s)', $response['body']['Model']['TransactionId']));
                return;
            } else {
				$pattern = '(ReasonCode: %d), %s ';
				$order->add_order_note(wp_sprintf($pattern, $response['body']['Model']['ReasonCode'], $response['body']['Model']['Reason']));
			}

            if ($response['body']['Message'] != null || $response['body']['Model']['CardHolderMessage'] != null) {
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order->get_id());
                $order->update_status('failed', sprintf(__('Error: %s', 'woocommerce'), $response['body']['Message']));
            }
            
        }
        
    }
    
	 /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available() {
        $order          = null;
        $needs_shipping = false;

        // Test if shipping is needed first.
        if ( WC()->cart && WC()->cart->needs_shipping() ) {
            $needs_shipping = true;
        } elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
            $order_id = absint( get_query_var( 'order-pay' ) );
            $order    = wc_get_order( $order_id );

            // Test if order needs shipping.
            if ( $order && 0 < count( $order->get_items() ) ) {
                foreach ( $order->get_items() as $item ) {
                    $_product = $item->get_product();
                    if ( $_product && $_product->needs_shipping() ) {
                        $needs_shipping = true;
                        break;
                    }
                }
            }
        }

        $needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

        // Only apply if all packages are being shipped via chosen method.
        if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
            $order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
            $chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

            if ( $order_shipping_items ) {
                $canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
            } else {
                $canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
            }

            if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
                return false;
            }
        }

        return parent::is_available();
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings() {
        if ( is_admin() ) {
            // phpcs:disable WordPress.Security.NonceVerification
            if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
                return false;
            }
            if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
                return false;
            }
            if ( ! isset( $_REQUEST['section'] ) || 'wc_cloudpayments_gateway' !== $_REQUEST['section'] ) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }

        if ( Constants::is_true( 'REST_REQUEST' ) ) {
            global $wp;
            if ( isset( $wp->query_vars['rest_route'] ) && false !== strpos( $wp->query_vars['rest_route'], '/payment_gateways' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options() {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if ( ! $this->is_accessing_settings() ) {
            return array();
        }

        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();

        foreach ( $raw_zones as $raw_zone ) {
            $zones[] = new WC_Shipping_Zone( $raw_zone );
        }

        $zones[] = new WC_Shipping_Zone( 0 );

        $options = array();
        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

            $options[ $method->get_method_title() ] = array();

            // Translators: %1$s shipping method name.
            $options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'woocommerce' ), $method->get_method_title() );

            foreach ( $zones as $zone ) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

                    if ( $shipping_method_instance->id !== $method->id ) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf( __( '%1$s (#%2$s)', 'woocommerce' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf( __( '%1$s &ndash; %2$s', 'woocommerce' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'woocommerce' ), $option_instance_title );

                    $options[ $method->get_method_title() ][ $option_id ] = $option_title;
                }
            }
        }

        return $options;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     */
    private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

        $canonical_rate_ids = array();

        foreach ( $order_shipping_items as $order_shipping_item ) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        }

        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     */
    private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

        $shipping_packages  = WC()->shipping()->get_packages();
        $canonical_rate_ids = array();

        if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
            foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
                if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
                    $chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @since  3.4.0
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     */
    private function get_matching_rates( $rate_ids ) {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
    }
	
}
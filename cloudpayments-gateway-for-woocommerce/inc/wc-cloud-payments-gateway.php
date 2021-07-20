<?php

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
        
        $this->enabled          = $this->get_option('enabled');
        $this->enabledDMS       = $this->get_option('enabledDMS'); // переносим
        $this->DMS_AU_status    = $this->get_option('DMS_AU_status');
        $this->DMS_CF_status    = $this->get_option('DMS_CF_status');
        $this->skin             = $this->get_option('skin');
        $this->language         = $this->get_option('language');
        $this->status_chancel   = $this->get_option('status_chancel'); // переносим
        $this->status_pay       = $this->get_option('status_pay'); // переносим
        $this->title            = $this->get_option('title');
        $this->description      = $this->get_option('description');
        $this->public_id        = $this->get_option('public_id');
        $this->api_pass         = $this->get_option('api_pass'); // переносим
        $this->currency         = $this->get_option('currency');
        $this->kassa_enabled    = $this->get_option('kassa_enabled');
        $this->kassa_taxtype    = $this->get_option('kassa_taxtype');
        $this->delivery_taxtype = $this->get_option('delivery_taxtype');
        $this->kassa_taxsystem  = $this->get_option('kassa_taxsystem');
        $this->kassa_skubarcode = $this->get_option('kassa_skubarcode');
        $this->kassa_method     = $this->get_option('kassa_method');
        $this->kassa_object     = $this->get_option('kassa_object');
        $this->status_delivered = $this->get_option('status_delivered');
        $this->inn              = $this->get_option('inn');
        $this->order_text       = $this->get_option('order_text');
        
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
        
        if ($_POST['cp_card'] == 'widget') {
            
            $cp_save_card = $_POST['cp_save_card'] ? 1 : 0;
            
            return array(
                'result'   => 'success',
                'redirect' => home_url('/wc-api/cloud_payments_widget?order_id=') . $order_id . '&return_ok=' . $this->get_return_url($order)
                              . '&cp_save_card=' . $cp_save_card
            );
        }
        
        $title         = array();
        $items_array   = array();
        $items         = $order->get_items();
        $shipping_data = array(
            "label"    => "Доставка",
            "price"    => number_format((float)$order->get_total_shipping() + abs((float)$order->get_shipping_tax()), 2, '.', ''),
            "quantity" => "1.00",
            "amount"   => number_format((float)$order->get_total_shipping() + abs((float)$order->get_shipping_tax()), 2, '.', ''),
            "vat"      => ($this->delivery_taxtype == "null") ? null : $this->delivery_taxtype,
            'method'   => (int)$this->kassa_method,
            'object'   => 4,
            "ean"      => null
        );
        
        foreach ($items as $item) {
            if ($this->kassa_enabled == 'yes') {
                $product       = $order->get_product_from_item($item);
                $items_array[] = array(
                    "label"    => $item['name'],
                    "price"    => number_format((float)$product->get_price(), 2, '.', ''),
                    "quantity" => number_format((float)$item['quantity'], 2, '.', ''),
                    "amount"   => number_format((float)$item['total'] + abs((float)$item['total_tax']), 2, '.', ''),
                    "vat"      => ($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype,
                    'method'   => (int)$this->kassa_method,
                    'object'   => (int)$this->kassa_object,
                    "ean"      => ($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null
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
            'JsonData'    => $kassa_array
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
        <p><strong>В личном кабинете включите Check-уведомление на адрес:</strong> <?php echo home_url('/wc-api/' . strtolower(get_class($this))) . "?action=check" ?></p>
        <p><strong>В личном кабинете включите Pay-уведомление на адрес:</strong> <?php echo home_url('/wc-api/' . strtolower(get_class($this))) . "?action=pay" ?></p>
        <p><strong>В личном кабинете включите Fail-уведомление на адрес:</strong> <?php echo home_url('/wc-api/' . strtolower(get_class($this))) . "?action=fail" ?></p>
        <p><strong>В личном кабинете включите Confirm-уведомление на адрес:</strong> <?php echo home_url('/wc-api/' . strtolower(get_class($this))) . "?action=confirm" ?></p>
        <p><strong>В личном кабинете включите Refund-уведомление на адрес:</strong> <?php echo home_url('/wc-api/' . strtolower(get_class($this))) . "?action=refund" ?></p>
        <p><strong>В личном кабинете включите Cancel-уведомление на адрес:</strong> <?php echo home_url('/wc-api/' . strtolower(get_class($this))) . "?action=cancel" ?></p>
        <p>Кодировка UTF-8, HTTP-метод POST, Формат запроса CloudPayments.</p>
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
            $items[] = array(
                'label'    => $product->get_name(),
                'price'    => number_format($product->get_price(), 2, ".", ''),
                'quantity' => $item_data->get_quantity(),
                'amount'   => number_format(floatval($item_data->get_total()), 2, ".", ''),
                'vat'      => $this->kassa_taxtype,
                'method'   => $method,
                'object'   => (int)$this->kassa_object,
            );
            
            $total_amount = $total_amount + number_format(floatval($item_data->get_total()), 2, ".", '');
        }
        
        if ($order->get_total_shipping()) {
            $items[] = array(
                'label'    => "Доставка",
                'price'    => $order->get_total_shipping(),
                'quantity' => 1,
                'amount'   => $order->get_total_shipping(),
                'vat'      => $this->delivery_taxtype,
                'method'   => $method,
                'object'   => 4,
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
        
        $aData = array(
            'Inn'             => $this->inn,
            'InvoiceId'       => $order->get_id(), //номер заказа, необязательный
            'AccountId'       => $order->get_user_id(),
            'Type'            => $type,
            'CustomerReceipt' => $data
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
        $shipping_data = array(
            "label"    => "Доставка",
            "price"    => number_format((float)$order->get_total_shipping() + abs((float)$order->get_shipping_tax()), 2, '.', ''),
            "quantity" => "1.00",
            "amount"   => number_format((float)$order->get_total_shipping() + abs((float)$order->get_shipping_tax()), 2, '.', ''),
            "vat"      => ($this->delivery_taxtype == "null") ? null : $this->delivery_taxtype,
            'method'   => (int)$this->kassa_method,
            'object'   => 4,
            "ean"      => null
        );
        foreach ($items as $item) {
            if ($this->kassa_enabled == 'yes') {
                $product       = $order->get_product_from_item($item);
                $items_array[] = array(
                    "label"    => $item['name'],
                    "price"    => number_format((float)$product->get_price(), 2, '.', ''),
                    "quantity" => number_format((float)$item['quantity'], 2, '.', ''),
                    "amount"   => number_format((float)$item['total'] + abs((float)$item['total_tax']), 2, '.', ''),
                    "vat"      => ($this->kassa_taxtype == "null") ? null : $this->kassa_taxtype,
                    'method'   => (int)$this->kassa_method,
                    'object'   => (int)$this->kassa_object,
                    "ean"      => ($this->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null
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
        $token   = WC_Payment_Tokens::get_customer_default_token($user_id);
        
        if ($token) {
            $request = array(
                'Token'       => $token->get_token(),
                'Amount'      => $order->get_total(),
                'Currency'    => $this->currency,
                'InvoiceId'   => $order->get_id(),
                'AccountId'   => $user_id,
                'Email'       => $order->billing_email,
                'Description' => 'Оплата заказа № ' . $order_id,
                'IpAddress'   => $_SERVER['REMOTE_ADDR'],
                'JsonData'    => $kassa_array
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
                $order->add_order_note(sprintf('Payment approved (ID: %s)', $response['body']['TransactionId']));
                
                return;
            }
            
            if ($response['body']['Message'] != null || $response['body']['Model']['CardHolderMessage'] != null) {
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order->get_id());
                $order->update_status('failed', sprintf(__('Error: %s', 'woocommerce'), $response['body']['Message']));
            }
            
        }
        
    }
    
}
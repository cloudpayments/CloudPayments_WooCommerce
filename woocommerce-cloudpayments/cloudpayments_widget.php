<?php
//require __DIR__ . '/wp-load.php';
require_once( $_SERVER['DOCUMENT_ROOT'].'/wp-load.php' );

$options = (object)get_option( 'woocommerce_cpgwwc_settings', $default );

global $woocommerce;
$order_id = $_GET['order_id'];
$order = new WC_Order( $order_id );
$title = array();
$items_array = array();
$items = $order->get_items();
$shipping_data = array("label"=>"Доставка", "price"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''), "quantity"=>"1.00",	"amount"=>number_format((float)$order->get_total_shipping()+abs((float)$order->get_shipping_tax()), 2, '.', ''), 
"vat"=>($options->delivery_taxtype == "null") ? null : $options->delivery_taxtype, 'method'=> (int)$options->kassa_method, 'object'=>4, "ean"=>null);
foreach ($items as $item) {
	if ($options->kassa_enabled == 'yes') {
	    $product = $order->get_product_from_item($item);
	    $items_array[] = array("label"=>$item['name'], "price"=>number_format((float)$product->get_price(), 2, '.', ''), "quantity"=>number_format((float)$item['quantity'], 2, '.', ''), 
	    "amount"=>number_format((float)$item['total']+abs((float)$item['total_tax']), 2, '.', ''), "vat"=>($options->kassa_taxtype == "null") ? null : $options->kassa_taxtype, 
	    'method'=> (int)$options->kassa_method, 'object'=> (int)$options->kassa_object,
	    "ean"=>($options->kassa_skubarcode == 'yes') ? ((strlen($product->get_sku()) < 1) ? null : $product->get_sku()) : null);
	}
	$title[] = $item['name'] . (isset($item['pa_ver']) ? ' ' . $item['pa_ver'] : '');
}
if ($options->kassa_enabled == 'yes' && $order->get_total_shipping() > 0) $items_array[] = $shipping_data;
$kassa_array = array("cloudPayments"=>(array("customerReceipt"=>array("Items"=>$items_array, "taxationSystem"=>$options->kassa_taxsystem, 'calculationPlace'=>$_SERVER['SERVER_NAME'], 
"email"=>$order->billing_email, "phone"=>$order->billing_phone))));
$title = implode(', ', $title);
      
$widget_f='charge';
if ($options->enabledDMS!='no')
{
    $widget_f='auth';
};
            
?>
<script src="https://widget.cloudpayments.ru/bundles/cloudpayments"></script>
<script>
window.onload = function() {
	var widget = new cp.CloudPayments({language: '<?=$options->language?>'});
	widget.<?=$widget_f?>({
        publicId: '<?=$options->public_id?>',
        description: 'Оплата заказа <?=$order_id?>',
        amount: <?=$order->get_total()?>,
        currency: '<?=$options->currency?>',
        skin: '<?=$options->skin?>',
        invoiceId: <?=$order_id?>,
        accountId: '<?=$order->get_user_id();?>',
        email: '<?=$order->billing_email?>',
        data: 
            <?php echo (($options->kassa_enabled == 'yes') ? json_encode($kassa_array) : "{}") ?>
        },
        function (options) { 
			window.location.replace('<?=$_GET['return_ok']?>');
        },
        function (reason, options) {
			window.location.replace('<?=$order->get_cancel_order_url()?>');
    	}
    );
}
</script>
<?php

?>
<div class="cloud-payments">
    <p>Выберите карту для оплаты или оплатите новой:</p>
    <?php
    $tokens          = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), 'wc_cloudpayments_gateway');
    $i               = 1;
    $save_cart_style = '';
    if ($tokens) {
        $checked_paymentForm = '';
        $style_paymentForm   = 'display: none;';
        $save_cart_style     = 'hide-save_cart';
        foreach ($tokens as $token) {
            if ($i == 1) {
                $checked = 'checked';
            } else {
                $checked = null;
            }
            echo '<label>';
            echo '<input type="radio" onclick="saveCartBox();" name="cp_card" id="cp_card' . $i . '" value="' . $token->get_id() . '" ' . $checked . '>';
            echo $token->get_card_type();
            echo ' ************';
            echo $token->get_last4();
            echo '</label>';
            $i = $i + 1;
        }
    } else {
        $checked_paymentForm = 'checked';
        $style_paymentForm   = '';
    }
    ?>
    <label>
        <input type="radio"
               name="cp_card"
               id="cp_card_checkout"
               onclick="saveCartBox();"
               value="widget" <?php echo $checked_paymentForm; ?>>
        Оплатить другой картой
    </label>
    <?php if (is_user_logged_in()): ?>
        <label class="cp_save_card <?php echo $save_cart_style; ?>">
            <input type="checkbox"
                   name="cp_save_card"
                   id="cp_save_card"
                   value="1"
                <?php echo $checked_paymentForm; ?>>
            Сохранить как основную карту
        </label>
    <?php endif; ?>
    <div id="paymentForm"
         style="<?php echo $style_paymentForm; ?>">
        <input type="hidden"
               name="CardCryptogramPacket"
               id="CardCryptogramPacket">
        <input type="hidden"
               name="user"
               id="user"
               value="<?php echo get_current_user_id(); ?>">
    </div>
</div>
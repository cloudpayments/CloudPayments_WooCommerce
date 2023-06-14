<?php
$current_user_id     = get_current_user_id();
$tokens              = WC_Payment_Tokens::get_customer_tokens( $current_user_id, 'wc_cloudpayments_gateway' );
$wrapper_class       = $tokens ? ' cloud-payments--has-tokens' : ' cloud-payments--no-tokens';
$hide_save_cart      = $tokens ? ' hide-save_cart' : '';
$hide_widget_control = ! $tokens ? ' hide-widget_control' : '';
?>
<div class="cloud-payments<?php echo esc_attr( $wrapper_class ); ?>">

	<?php
	if ( $tokens ) {
		echo apply_filters( // phpcs:ignore
			'cloudpayments_checkout_token_choose_title_html',
			sprintf(
				'<p class="cloud-payments__choose-title">%s</p>',
				esc_html__( 'Выберите карту для оплаты или оплатите новой:', 'cloudpayments' )
			),
			$tokens
		);

		foreach ( $tokens as $key => $token ) {
			echo apply_filters( // phpcs:ignore
				'cloudpayments_checkout_token_control_html',
				sprintf(
					'<label class="cloud-payments-token-control"><input type="radio" onclick="saveCartBox();" name="cp_card" id="cp_card%s" value="%s" %s>%s</label>',
					esc_attr( $key ),
					esc_attr( $token->get_id() ),
					checked( 1, $token->is_default(), false ),
					esc_html( $token->get_card_type() . ' ************' . $token->get_last4() )
				),
				$key,
				$token,
				$tokens
			);
		}
	} else {
		echo apply_filters( // phpcs:ignore
			'cloudpayments_checkout_no_tokens_text',
			'<p>' . esc_html__( 'Для оплаты заказа вы будете перенаправлены на страницу сервиса Cloud Payments.', 'cloudpayments' ) . '</p>',
			$tokens
		);
	}
	?>

	<label class="cloud-payments-pay-other-card<?php echo esc_attr( $hide_widget_control ); ?>">
		<input
			type="radio"
			name="cp_card"
			id="cp_card_checkout"
			onclick="saveCartBox();"
			value="widget"
			<?php checked( ! $tokens, true, true ); ?>>

		<?php
		echo apply_filters( // phpcs:ignore
			'cloudpayments_checkout_pay_other_card_label',
			__( 'Оплатить другой картой', 'cloudpayments' ),
			$tokens
		);
		?>
	</label>

	<?php if ( is_user_logged_in() ) : ?>
		<label class="cloud-payments-save-card cp_save_card<?php echo esc_attr( $hide_save_cart ); ?>">
			<input
				type="checkbox"
				name="cp_save_card"
				id="cp_save_card"
				value="1"
				<?php checked( ! $tokens, true, true ); ?>>

			<?php
			echo apply_filters( // phpcs:ignore
				'cloudpayments_checkout_save_card_label',
				__( 'Сохранить как основную карту', 'cloudpayments' ),
				$tokens
			);
			?>
		</label>
	<?php endif; ?>

	<div id="paymentForm" style="display: none;">
		<input
			type="hidden"
			id="CardCryptogramPacket"
			name="CardCryptogramPacket">
		<input
			type="hidden"
			id="user"
			name="user"
			value="<?php echo esc_attr( $current_user_id ); ?>">
	</div>
</div>
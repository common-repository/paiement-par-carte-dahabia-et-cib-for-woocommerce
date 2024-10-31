<div id="satimpay-header">

    <h2> Détails de la transaction</h2>
	<?php
	$order_id = $order->get_meta( '_' . self::$gateway_id . '_custom_order_id' );
	if ( ! empty( $order_id ) ) : ?>
        <p><strong>Commande N°:</strong> <?php echo esc_html( $order_id ) ?></p>
	<?php endif; ?>
	<?php
	$transaction_data = $order->get_meta( '_' . self::$gateway_id . '_data' );
	if ( isset($transaction_data['SvfeResponse']) && $transaction_data['SvfeResponse'] === '00' ) : ?>
        <p><strong>Statut:</strong> <?php echo esc_html( $transaction_data['params']['respCode_desc'] ) ?></p>
        <p><strong>Identifiant de la transaction:</strong> <?php echo esc_html( $order->get_transaction_id() ) ?></p>
        <p><strong>Numéro d’autorisation:</strong> <?php echo esc_html( $transaction_data['approvalCode'] ) ?></p>
        <p><strong>Date et l’heure de la transaction:</strong>
			<?php echo esc_html( date( 'd.m.Y, H:i:s', $order->get_date_created()->getOffsetTimestamp() ) ) ?></p>
        <p><strong>En cas de problème de paiement, veuillez contacter le numéro vert de Satim:</strong> <a
                    href="tel:3020">3020</a></p>
        <p><img src="<?php echo esc_attr( self::$plugin_icon_error ) ?>" style="width: 70px;vertical-align: baseline;">
            <img id="satimpay-mini-logo"
            src="<?php echo esc_attr( self::$plugin_icon ) ?>" style="width: 70px;vertical-align: baseline;"></p>
        <h2>Résumé de la commande</h2>
	<?php endif; ?>
</div>

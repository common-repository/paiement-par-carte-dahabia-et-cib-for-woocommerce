<?php

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PPCD_SatimiPay_Gateway extends WC_Payment_Gateway {
	public function __construct() {
		$this->id                 = PPCD_Satimipay::$gateway_id;
		$this->icon               = PPCD_Satimipay::$plugin_icon;
		$this->icon_error         = PPCD_Satimipay::$plugin_url . 'assets/images/satimipay_error.png';
		$this->has_fields         = true;
		$this->method_title       = esc_html__( 'Carte CIB & Dahabia / Satim' );
		$this->method_description = __( "Paiement en ligne", 'paiement-par-carte-dahabia-et-cib-for-woocommerce' );
		$this->supports           = [ 'products', 'refunds' ];
		$this->enable_for_methods = $this->get_option( 'enable_for_methods', [] );

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->enabled              = $this->get_option( 'enabled' );
		$this->language             = strtoupper( explode( '_', get_locale() )[0] );
		$this->currency             = '012';
		$this->currency_symbol      = get_woocommerce_currency_symbol();
		$this->testmode             = 'yes' === $this->get_option( 'testmode' );
		$this->logging              = 'yes' === $this->get_option( 'logging' );
		$this->userName             = $this->testmode ? $this->get_option( 'test_user_name' ) : $this->get_option( 'user_name' );
		$this->password             = $this->testmode ? $this->get_option( 'test_password' ) : $this->get_option( 'password' );
		$this->terminal_id          = $this->get_option( 'terminal_id' );
		$this->api_url              = ( $this->testmode == 'yes' ) ? 'https://test.satim.dz/payment/rest/register.do' : 'https://cib.satim.dz/payment/rest/register.do';
		$this->api_confirmation_url = ( $this->testmode == 'yes' ) ? 'https://test.satim.dz/payment/rest/confirmOrder.do' : 'https://cib.satim.dz/payment/rest/confirmOrder.do';
		$this->api_refund_url       = ( $this->testmode == 'yes' ) ? 'https://test.satim.dz/payment/rest/refund.do' : 'https://cib.satim.dz/payment/rest/refund.do';
		$this->return_url           = home_url( '/wc-api/satimipay-return-url' );
		$this->fail_url             = home_url( '/wc-api/satimipay-fail-url' );

		// Hooks
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_api_satimipay-return-url', [ $this, 'order_confirm' ] );
		add_action( 'woocommerce_api_satimipay-fail-url', [ $this, 'order_failed' ] );
		add_action( 'woocommerce_gateway_method_description', [
			$this,
			'woocommerce_gateway_method_description'
		], 10, 2 );
	}

	private static function isSettingPage() {
		// page=wc-settings&tab=checkout&section=satimipay
		$isSettingPage = false;
		if (
			( isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' ) &&
			( isset( $_GET['tab'] ) && $_GET['tab'] === 'checkout' ) &&
			( isset( $_GET['section'] ) && $_GET['section'] === 'satimipay' )
		) {
			$isSettingPage = true;
		}

		return $isSettingPage;
	}

	public function woocommerce_gateway_method_description( $method_description, $gateway ) {
		if ( self::isSettingPage() ) {
			$method_description .= <<<TEXT
<h2><span style="text-decoration: underline; font-size: 30px;"><strong style="color: red; text-decoration: underline;">ACTIVATION</strong></span></h2>
<p>L'activation du paiement par cate CIB/DAHABIA coute 45 000 DA.</p>
<p>Pour proc&eacute;der; l'activation, veuillez <strong><a href="https://web-rocket.dz/activation-du-paiement-en-ligne-par-cib-dahabia/">remplir le formulaire d'activation</a></strong>.</p>
<p>Pour toute demande d'information, veuillez contacter le service support <a href="tel:+213799087124">+213 799 08 71 24</a>.</p>
TEXT;
		}

		return $method_description;
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled'            => [
				'title'       => esc_html__( 'Activer/Désactiver' ),
				'label'       => esc_html__( 'Activer le paiement par carte CIB & Dahabia / Satim' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'title'              => [
				'title'       => esc_html__( 'Titre' ),
				'type'        => 'text',
				'description' => esc_html__( 'Carte CIB/Edahabia' ),
				'default'     => 'carte CIB',
				'desc_tip'    => true,
			],
			'user_name'          => [
				'title'       => esc_html__( 'Nom d\'utilisateur' ),
				'type'        => 'text',
				'description' => ( '<a href="https://web-rocket.dz/activation-satim">Cliquez ici</a> pour obtenir vos identifiants.' )
			],
			'password'           => [
				'title'       => esc_html__( 'Mot de passe' ),
				'type'        => 'password',
				'description' => ( '<a href="https://web-rocket.dz/activation-satim">Cliquez ici</a> pour obtenir vos identifiants.' )
			],
			'terminal_id'        => [
				'title'       => esc_html__( 'Terminal ID' ),
				'type'        => 'text',
				'description' => ( '<a href="https://web-rocket.dz/activation-satim">Cliquez ici</a> pour obtenir vos identifiants.' )
			],
			'logging'            => [
				'title'       => esc_html__( 'Activer le journal' ),
				'label'       => esc_html__( 'Activer/Désactiver' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'testmode'           => [
				'title'       => esc_html__( 'Mode test' ),
				'label'       => esc_html__( 'Activer le mode test' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Utilisé par SATIM lors de l\'activation' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'test_user_name'     => [
				'title' => esc_html__( 'Nom d\'utilisateur "test"' ),
				'type'  => 'text'
			],
			'test_password'      => [
				'title' => esc_html__( 'Mots de passe "test"' ),
				'type'  => 'password',
			],
			'enable_for_methods' => [
				'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __( 'If COD is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
				'options'           => $this->load_shipping_method_options(),
				'desc_tip'          => true,
				'custom_attributes' => [
					'data-placeholder' => __( 'Select shipping methods', 'woocommerce' ),
				],
			],
		];
	}

	public function order_failed() {
		$this->log( json_encode( $_REQUEST ), 'order_failed' );
		$orderId  = $_REQUEST['orderId'];
		$args     = [
			'userName' => $this->userName,
			'password' => $this->password,
			'orderId'  => $orderId,
			'language' => $this->language
		];
		$response = wp_remote_get( add_query_arg( $args, $this->api_confirmation_url ) );
		$this->log( is_wp_error( $response ) ? $response : $response['body'] );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
		} else {
			$response = json_decode( $response['body'], true );
			if ( $response['ErrorCode'] == '0' && $response['respCode'] == '00' && $response['OrderStatus'] == '3' ) {
				$error_message = 'Votre transaction a été rejetée / Your transaction was rejected / تم رفض معاملتك';
			} else {
				if ( ! empty( $response['params']['respCode_desc'] ) ) {
					$error_message = $response['params']['respCode_desc'];
				} elseif ( ! empty( $response['actionCodeDescription'] ) ) {
					$error_message = $response['actionCodeDescription'];
				} else {
					$error_message = 'Unkown error.';
				}
			}
		}
		$order = wc_get_order( $this->decodeOrderId( sanitize_key( $_GET['order_id'] ) ) );
		wc_add_notice( '<div class="satimipay-error"><p class="msg-satim">' . $error_message . '</p> <p class="msg-fixed"> En cas de problème de paiement, contactez le numéro vert de satim <img height="50px" width="auto" alt="' . $this->title . '" src="' . $this->icon_error . '" /> </p></div>', 'error' );
		$order->update_status( 'failed', $error_message );
		wp_redirect( $order->get_cancel_order_url() );
		die;
	}

	public function order_confirm() {
		$orderId  = $_GET['orderId'];
		$order_id = $this->decodeOrderId( sanitize_key( $_GET['order_id'] ) );
		$order    = wc_get_order( $order_id );

		if ( $order->meta_exists( '_' . $this->id . '_' . $order_id ) ) {
			wp_redirect( $this->get_return_url( $order ) );
			die;
		}

		$args     = [
			'body' => [
				'userName' => $this->userName,
				'password' => $this->password,
				'orderId'  => $orderId,
				'language' => $this->language
			]
		];
		$url      = add_query_arg( $args['body'], $this->api_confirmation_url );
		$response = wp_remote_get( $url, [
			'timeout' => 60
		] );

		$this->log( [
			'source'    => 'PPCD_SatimiPay_Gateway::order_confirm',
			'$order_id' => $order_id,
			'$response' => is_wp_error( $response ) ? $response : $response['body'],
			'$url'      => $url
		] );

		if ( ! is_wp_error( $response ) ) {

			$response = json_decode( $response['body'], true );

			$response_code    = intval( $response['ErrorCode'] );
			$response_message = $response['ErrorMessage'];

			if ( $response_code == 0 ) {
				$order->payment_complete( $orderId );
				WC()->cart->empty_cart();

				// Save satimipay orderId for refund
				$order->update_meta_data( '_' . $this->id . '_' . $order_id, $orderId );
				$order->update_meta_data( '_' . $this->id . '_custom_order_id', sanitize_key( $_GET['order_id'] ) );
				$order->update_meta_data( '_' . $this->id . '_data', $response );
				$order->save();
				wp_redirect( $this->get_return_url( $order ) );
				die;
			} else {
				switch ( $response_code ) {
					case 1:
						$notice         = esc_html__( 'Empty order id' );
						$payment_status = 'Pending payment';
						break;
					case 2:
						$notice         = esc_html__( 'Already confirmed' );
						$payment_status = 'Processing';
						break;
					case 3:
						$notice         = esc_html__( 'Access denied' );
						$payment_status = 'Pending payment';
						break;
					case 5:
						$notice         = esc_html__( 'Access denied' );
						$payment_status = 'Pending payment';
						break;
					case 6:
						$notice         = esc_html__( 'Unknown order' );
						$payment_status = 'Canceled';
						break;
					case 7:
						$notice         = esc_html__( 'System error' );
						$payment_status = 'Pending payment';
						break;
					default:
						$notice         = $response_message;
						$payment_status = 'Pending payment';
				}

				wc_add_notice( $notice, 'error' );
				$order->update_status( $payment_status, $notice );

				wp_redirect( $this->get_return_url( $order ) );
				die;
			}

		} else {
			$response_message = $response['errorMessage'];

			$this->log( 'Error payment confirm ' . $response_message );

			$notice = esc_html__( 'Error payment confirm' );

			wc_add_notice( $notice, 'error' );

			wp_redirect( $order->get_cancel_order_url() );
			die;
		}
	}

	public function process_payment( $order_id ) {
		$order           = wc_get_order( $order_id );
		$total_amount    = $order->get_total();
		$custom_order_id = $order_id . rand( 1000, 9999 );

		if ( $total_amount < 50 ) {

			$notice = sprintf( esc_html__( 'The total price cannot be less than 50%s if you want to pay for the order using %s' ), $this->currency_symbol, $this->id );
			wc_add_notice( $notice, 'error' );

			$order->update_status( 'Canceled', $notice );
		} else {
			$args = [
				'body' => [
					'userName'    => $this->userName,
					'password'    => $this->password,
					'orderNumber' => $custom_order_id,
					'currency'    => $this->currency,
					'amount'      => $order->get_total() * 100,
					'language'    => $this->language,
					'returnUrl'   => $this->return_url . '?order_id=' . $custom_order_id,
					'failUrl'     => $this->fail_url . '?order_id=' . $custom_order_id,
					'jsonParams'  => json_encode( [
						'force_terminal_id' => $this->terminal_id,
						'udf1'              => '2018105301346'
					] )
				]
			];

			$url      = add_query_arg( $args['body'], $this->api_url );
			$response = wp_remote_get( $url, [
				'timeout' => 60
			] );

			$this->log( [
				'source'    => 'PPCD_SatimiPay_Gateway::process_payment',
				'$order_id' => $order_id,
				'body'      => $args,
				'$response' => is_wp_error( $response ) ? $response : $response['body'],
				'$url'      => $url
			] );

			if ( ! is_wp_error( $response ) ) {
				$response      = json_decode( $response['body'], true );
				$response_code = $response['errorCode'];

				// Check response status
				if ( $response_code == '0' ) {
					$orderId     = $response['orderId'];
					$payment_url = $response['formUrl'];

					return [
						'result'   => 'success',
						'redirect' => $payment_url
					];

				} else {
					$notice           = '';
					$response_message = $response['errorMessage'];

					switch ( $response_code ) {
						case 0:
							$notice = esc_html__( 'Payment error' );
							break;
						default:
							$notice = $response_message;
					}

					$this->log( $response_code . ' - ' . $response_message );

					wc_add_notice( $notice, 'error' );

					return;
				}
			}
		}

	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}
		if ( $amount < 50 ) {
			$notice = sprintf( esc_html__( 'Refund amount is less than 50%s' ), $this->currency_symbol );

			$this->log( $notice );

			$order->add_order_note( 'Refund Failed: ' . $notice );
		} else {
			$args     = [
				'body' => [
					'userName' => $this->userName,
					'password' => $this->password,
					'orderId'  => $order->get_transaction_id(),
					'amount'   => $amount * 100
				]
			];
			$response = wp_remote_get( add_query_arg( $args['body'], $this->api_refund_url ) );
			$this->log( is_wp_error( $response ) ? $response : $response['body'] );
			if ( ! is_wp_error( $response ) ) {
				$response = json_decode( $response['body'], true );

				$response_code  = intval( $response['errorCode'] );
				$refund_message = intval( $response['errorMessage'] );

				if ( $response_code == 0 ) {
					$refund_message = sprintf( esc_html__( 'Refunded %s%s' ), $amount, $this->currency_symbol );

					$order->add_order_note( $refund_message );
					$this->log( 'Success: ' . wp_strip_all_tags( $refund_message ) );

					return true;
				} else {
					$this->log( 'Refund Failed: ' . $refund_message, 'error' );
					$order->add_order_note( 'Refund Failed: ' . $refund_message );

					return false;
				}
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

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
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
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @param array $order_shipping_items Array of WC_Order_Item_Shipping objects.
	 *
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 * @since  3.4.0
	 *
	 */
	private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) {

		$canonical_rate_ids = [];

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @param array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 *
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 * @since  3.4.0
	 *
	 */
	private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = [];

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
	 * @param array $rate_ids Rate ids to check.
	 *
	 * @return boolean
	 * @since  3.4.0
	 *
	 */
	private function get_matching_rates( $rate_ids ) {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options() {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->is_accessing_settings() ) {
			return [];
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new WC_Shipping_Zone( 0 );

		$options = [];
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = [];

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
			if ( ! isset( $_REQUEST['section'] ) || 'satimipay' !== $_REQUEST['section'] ) {
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

	public function decodeOrderId( $order_id ) {
		return (int) ( $order_id / 10000 );
	}

	public function log( $data, $prefix = '' ) {
		if ( $this->logging ) {
			wc_get_logger()->debug( "$prefix " . print_r( $data, 1 ), [ 'source' => $this->id ] );
		}
	}
}

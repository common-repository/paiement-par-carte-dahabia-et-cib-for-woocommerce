<?php
/*
 * Plugin Name: Paiement par Carte DAHABIA et Carte CIB for WooCommerce
 * Description: Extension officielle certifiée par la SATIM pour le e-paiement en Algérie (DZ).
 * Author: Web Rocket
 * Plugin URI: https://web-rocket.dz/
 * Version: 2.3.1
 * Text Domain: paiement-par-carte-dahabia-et-cib-for-woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class PPCD_Satimipay
{
    private static $instance;
    public static $plugin_url;
    public static $gateway_id = 'satimipay';
	public static $plugin_icon;
	public static $plugin_icon_png;
    public static $plugin_icon_error;
    public static $plugin_path;

    private function __construct()
    {
        self::$plugin_url = plugin_dir_url(__FILE__);
        self::$plugin_path = plugin_dir_path(__FILE__);
        self::$plugin_icon = self::$plugin_url . 'assets/images/satimipay_icon.png';
        self::$plugin_icon_png = self::$plugin_url . 'assets/images/satimipay_icon.png';
        self::$plugin_icon_error = self::$plugin_url . 'assets/images/satimipay_error.png';

        add_action('plugins_loaded', [$this, 'pluginsLoaded']);
        add_filter('woocommerce_payment_gateways', [$this, 'woocommercePaymentGateways']);
        add_action('wp_enqueue_scripts', [$this, 'wp_enqueue_scripts']);
        add_action('woocommerce_before_thankyou', [$this, 'woocommerce_before_thankyou']);

		add_action('woocommerce_api_satimipay-download-pdf', [$this, 'download_order_pdf']);
		add_action('woocommerce_api_satimipay-send-order-pdf-to-email', [$this, 'send_order_pdf_to_email']);
		add_action('wpo_wcpdf_after_document_label', [$this, 'wcpdf_add_header'], 10, 2);
    }

    public function wp_enqueue_scripts()
    {
        if (is_cart() || is_checkout()) {
            wp_enqueue_style(
                self::$gateway_id,
                self::$plugin_url . 'assets/css/satimipay.css',
                [],
                filemtime(self::$plugin_path . 'assets/css/satimipay.css')
            );
        }
    }

    public function woocommerce_before_thankyou($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order->get_payment_method() === self::$gateway_id) {
            include_once self::$plugin_path . 'template-parts/checkout/thankyou-page.php';
        }
    }

    public function pluginsLoaded()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerceMissingWcNotice']);
            return;
        }

        require_once 'includes/class-wc-satimipay-gateway.php';
    }

	public function woocommerceMissingWcNotice()
	{
		?>
		<div class="error">
			<p>
				<strong>
					Satimipay requires WooCommerce to be installed and active. You can download
					<a href="https://woocommerce.com/" target="_blank">WooCommerce</a> here.
				</strong>
			</p>
		</div>
		<?php
	}

    public function woocommercePaymentGateways($gateways)
    {
        $gateways[] = 'PPCD_SatimiPay_Gateway';
        return $gateways;
    }

    public function pdf_invoices_installed() {
    	return class_exists('WPO_WCPDF') || class_exists('WooCommerce_PDF_IPS_Pro');
    }

    public function get_validated_order_pdf_data() {
    	if(!$this->pdf_invoices_installed()) {
    	    wp_die('"WooCommerce PDF Invoices & Packing Slips" is nt installed');
	    }

	    if ( ! isset( $_GET['id'] ) || empty( $_GET['id'] ) ) {
		    wp_die( 'Need id param' );
	    }

	    $order_id = apply_filters('woocommerce_thankyou_order_id', absint(sanitize_key($_GET['id'])));
	    $order = wc_get_order($order_id);

	    if(!$order) {
		    wp_die('Order not found');
	    }

	    $order_key = apply_filters('woocommerce_thankyou_order_key', empty($_GET['key']) ? '' : wp_unslash($_GET['key']));

	    if ($order_id <= 0 || empty($order_key)) {
		    wp_die( 'Error with order id or order key' );
	    }

	    if(!hash_equals($order->get_order_key(), $order_key)) {
		    wp_die('Key is invalid');
	    }

	    return $order;
    }

	public function download_order_pdf() {
		$order = $this->get_validated_order_pdf_data();
		$order_id = $order->get_id();

		$wcpdf = new \WPO\WC\PDF_Invoices\Main();

		$file_path = $wcpdf->get_tmp_path('attachments') . "invoice-$order_id.pdf";

		header('Content-type: application/pdf');
		header('Content-Disposition: inline; filename="invoice-' . $order_id . '.pdf"');
		header('Content-Transfer-Encoding: binary');
		header('Accept-Ranges: bytes');

		if (file_exists($file_path)) {
			@readfile($file_path);
		} else {
			$document = wcpdf_get_document( 'invoice', array( $order_id ), true );
			echo $document->get_pdf();
		}

		exit;
	}

	public function send_order_pdf_to_email() {
		$order = $this->get_validated_order_pdf_data();
		$order_id = $order->get_id();

		$wcpdf = new \WPO\WC\PDF_Invoices\Main();

		$file_name = "invoice-$order_id.pdf";
		$file_path = $wcpdf->get_tmp_path('attachments') . $file_name;

		if (!file_exists($file_path)) {
			$document = wcpdf_get_document( 'invoice', array( $order_id ), true );

			if (!file_exists($file_path)) {
				file_put_contents($file_path, $document->get_pdf());
			}
		}

		if(isset($_GET['email']) && !empty($_GET['email'])) {
			$email_to = sanitize_email( urldecode( $_GET['email'] ) );

			if(!is_email($email_to)) {
				wp_send_json(array(
					'success' => false,
					'message' => 'Invalid email'
				));
			}

		} else {
			$email_to = $order->get_billing_email();
		}

		$success = wp_mail(
			$email_to,
			'Facture ' . get_bloginfo('name'),
			'Votre facture est en piece jointe',
			'',
			array($file_path)
		);

		wp_send_json(array(
			'success' => $success
		));
	}

	public function wcpdf_add_header($type, $order) {
		self::$plugin_icon = self::$plugin_icon_png;

		include self::$plugin_path . 'template-parts/checkout/thankyou-page-header.php';

		?>
        <style>.document-type-label{display: none;}</style>
        <?php
	}

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

}

PPCD_Satimipay::getInstance();

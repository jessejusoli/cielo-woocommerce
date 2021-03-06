<?php
/**
 * WC Cielo Debit Gateway Class.
 *
 * Built the Cielo Debit methods.
 */
class WC_Cielo_Debit_Gateway extends WC_Cielo_Helper {

	/**
	 * Cielo WooCommerce API.
	 *
	 * @var WC_Cielo_API
	 */
	public $api = null;

	/**
	 * Gateway actions.
	 */
	public function __construct() {
		$this->id           = 'cielo_debit';
		$this->icon         = apply_filters( 'wc_cielo_debit_icon', '' );
		$this->has_fields   = true;
		$this->method_title = __( 'Cielo - Debit Card', 'cielo-woocommerce' );
		$this->supports     = array( 'products', 'refunds' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->store_contract   = $this->get_option( 'store_contract' );
		$this->environment      = $this->get_option( 'environment' );
		$this->number           = $this->get_option( 'number' );
		$this->key              = $this->get_option( 'key' );
		$this->methods          = $this->get_option( 'methods', 'visa' );
		$this->authorization    = $this->get_option( 'authorization' );
		$this->debit_discount   = $this->get_option( 'debit_discount' );
		$this->design           = $this->get_option( 'design' );
		$this->debug            = $this->get_option( 'debug' );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			$this->log = $this->get_logger();
		}

		// Set the API.
		$this->api = new WC_Cielo_API( $this );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_cielo_debit_gateway', array( $this, 'check_return' ) );
		add_action( 'woocommerce_' . $this->id . '_return', array( $this, 'return_handler' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'cielo-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Cielo Debit Card', 'cielo-woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title'       => __( 'Title', 'cielo-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Debit Card', 'cielo-woocommerce' )
			),
			'description' => array(
				'title'       => __( 'Description', 'cielo-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => __( 'Pay using the secure method of Cielo', 'cielo-woocommerce' )
			),
			'store_contract' => array(
				'title'       => __( 'Store Solution', 'cielo-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the store contract method with cielo.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'buypage_cielo',
				'options'     => array(
					'buypage_cielo' => __( 'BuyPage Cielo', 'cielo-woocommerce' ),
					'webservice'    => __( 'Webservice Solution', 'cielo-woocommerce' )
				)
			),
			'environment' => array(
				'title'       => __( 'Environment', 'cielo-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the environment type (test or production).', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'test',
				'options'     => array(
					'test'       => __( 'Test', 'cielo-woocommerce' ),
					'production' => __( 'Production', 'cielo-woocommerce' )
				)
			),
			'number' => array(
				'title'       => __( 'Affiliation Number', 'cielo-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Store affiliation number with Cielo.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => ''
			),
			'key' => array(
				'title'       => __( 'Affiliation Key', 'cielo-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Store access key assigned by Cielo.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => ''
			),
			'methods' => array(
				'title'       => __( 'Accepted Card Brands', 'cielo-woocommerce' ),
				'type'        => 'multiselect',
				'description' => __( 'Select the card brands that will be accepted as payment. Press the Ctrl key to select more than one brand.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => array( 'visa' ),
				'options'     => array(
					'visaelectron' => __( 'Visa Electron', 'cielo-woocommerce' ),
					'maestro'      => __( 'Maestro', 'cielo-woocommerce' ),
				)
			),
			'authorization' => array(
				'title'       => __( 'Automatic Authorization (MasterCard and Visa only)', 'cielo-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the authorization type.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '2',
				'options'     => array(
					'2' => __( 'Allow authorization for authenticated transaction and non-authenticated', 'cielo-woocommerce' ),
					'1' => __( 'Authorization transaction only if is authenticated', 'cielo-woocommerce' ),
					'0' => __( 'Only authenticate the transaction', 'cielo-woocommerce' )
				)
			),
			'debit_discount' => array(
				'title'       => __( 'Debit Discount (%)', 'cielo-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Percentage discount for payments made ​​by debit card.', 'cielo-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '0'
			),
			'design_options' => array(
				'title'       => __( 'Design', 'cielo-woocommerce' ),
				'type'        => 'title',
				'description' => ''
			),
			'design' => array(
				'title'   => __( 'Payment Form Design', 'cielo-woocommerce' ),
				'type'    => 'select',
				'default' => 'default',
				'options' => array(
					'default' => __( 'Default', 'cielo-woocommerce' ),
					'icons'   => __( 'With card icons', 'cielo-woocommerce' )
				)
			),
			'testing' => array(
				'title'       => __( 'Gateway Testing', 'cielo-woocommerce' ),
				'type'        => 'title',
				'description' => ''
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'cielo-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'cielo-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Cielo events, such as API requests, inside %s', 'cielo-woocommerce' ), $this->get_log_file_path() )
			)
		);
	}

	/**
	 * Get Checkout form field.
	 *
	 * @param string $model
	 * @param float  $order_total
	 */
	protected function get_checkout_form( $model = 'default', $order_total = 0 ) {
		woocommerce_get_template(
			'debit-card/' . $model . '-payment-form.php',
			array(
				'methods'        => $this->get_available_methods_options(),
				'discount'       => $this->debit_discount,
				'discount_total' => $this->get_debit_discount( $order_total )
			),
			'woocommerce/cielo/',
			WC_Cielo::get_templates_path()
		);
	}

	/**
	 * Checkout scripts.
	 */
	public function checkout_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( ! $this->is_available() ) {
			return;
		}

		if ( 'webservice' == $this->store_contract ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style( 'wc-cielo-checkout-webservice' );
			wp_enqueue_script( 'wc-cielo-debit-checkout-webservice', plugins_url( 'assets/js/debit-card/checkout-webservice' . $suffix . '.js', plugin_dir_path( __FILE__ ) ), array( 'jquery', 'wc-credit-card-form' ), WC_Cielo::VERSION, true );
		}
	}

	/**
	 * Process webservice payment.
	 *
	 * @param  WC_Order $order
	 *
	 * @return array
	 */
	protected function process_webservice_payment( $order ) {
		$payment_url = '';
		$card_brand  = isset( $_POST['cielo_debit_card'] ) ? sanitize_text_field( $_POST['cielo_debit_card'] ) : '';

		// Validate credit card brand.
		$valid = $this->validate_credit_brand( $card_brand );

		// Test the card fields.
		if ( $valid ) {
			$valid = $this->validate_card_fields( $_POST );
		}

		if ( $valid ) {
			$card_brand   = ( 'visaelectron' == $card_brand ) ? 'visa' : 'mastercard';
			$installments = absint( $_POST['cielo_installments'] );
			$card_data    = array(
				'name_on_card'    => $_POST['cielo_holder_name'],
				'card_number'     => $_POST['cielo_card_number'],
				'card_expiration' => $_POST['cielo_card_expiry'],
				'card_cvv'        => $_POST['cielo_card_cvc']
			);

			$response = $this->api->do_transaction( $order, $order->id . '-' . time(), $card_brand, 0, $card_data, true );

			// Set the error alert.
			if ( isset( $response->mensagem ) && ! empty( $response->mensagem ) ) {
				$this->add_error( (string) $response->mensagem );
				$valid = false;
			}

			// Save the tid.
			if ( isset( $response->tid ) && ! empty( $response->tid ) ) {
				update_post_meta( $order->id, '_transaction_id', (string) $response->tid );
			}

			update_post_meta( $order->id, '_wc_cielo_card_brand', $card_brand );

			$payment_url = str_replace( '&amp;', '&', urldecode( $this->get_api_return_url( $order ) ) );
		}

		if ( $valid && $payment_url ) {
			return array(
				'result'   => 'success',
				'redirect' => $payment_url
			);
		} else {
			return array(
				'result'   => 'fail',
				'redirect' => ''
			);
		}
	}

	/**
	 * Process buy page cielo payment.
	 *
	 * @param  WC_Order $order
	 *
	 * @return array
	 */
	protected function process_buypage_cielo_payment( $order ) {
		$payment_url = '';
		$card_brand  = isset( $_POST['cielo_debit_card'] ) ? sanitize_text_field( $_POST['cielo_debit_card'] ) : '';

		// Validate credit card brand.
		$valid = $this->validate_credit_brand( $card_brand );

		if ( $valid ) {
			$card_brand = ( 'visaelectron' == $card_brand ) ? 'visa' : 'mastercard';
			$response   = $this->api->do_transaction( $order, $order->id . '-' . time(), $card_brand, 0, array(), true );

			// Set the error alert.
			if ( isset( $response->mensagem ) && ! empty( $response->mensagem ) ) {
				$this->add_error( (string) $response->mensagem );
				$valid = false;
			}

			// Save the tid.
			if ( isset( $response->tid ) && ! empty( $response->tid ) ) {
				update_post_meta( $order->id, '_transaction_id', (string) $response->tid );
			}

			// Set the transaction URL.
			if ( isset( $response->{'url-autenticacao'} ) && ! empty( $response->{'url-autenticacao'} ) ) {
				$payment_url = (string) $response->{'url-autenticacao'};
			}

			update_post_meta( $order->id, '_wc_cielo_card_brand', $card_brand );
		}

		if ( $valid && $payment_url ) {
			return array(
				'result'   => 'success',
				'redirect' => $payment_url
			);
		} else {
			return array(
				'result'   => 'fail',
				'redirect' => ''
			);
		}
	}

	/**
	 * Thank you page message.
	 *
	 * @return string
	 */
	public function thankyou_page( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$order_url = $order->get_view_order_url();
		} else {
			$order_url = add_query_arg( 'order', $order_id, get_permalink( woocommerce_get_page_id( 'view_order' ) ) );
		}

		$card_brand = get_post_meta( $order->id, '_wc_cielo_card_brand', true );
		$card_brand = get_payment_method_name( $card_brand );

		// @TODO:

		if ( $order->status == 'processing' || $order->status == 'completed' ) {
			echo '<div class="woocommerce-message"><a href="' . $order_url . '" class="button" style="display: block !important; visibility: visible !important;">' . __( 'View order details', 'cielo-woocommerce' ) . '</a>' . sprintf( __( 'Your payment worth %s was received successfully.', 'cielo-woocommerce' ), woocommerce_price( $order->order_total ) ) . '<br />' . __( 'The authorization code was generated.', 'cielo-woocommerce' ) . '</div>';
		} else {
			echo '<div class="woocommerce-info">' . sprintf( __( 'For more information or questions regarding your order, go to the %s.', 'cielo-woocommerce' ), '<a href="' . $order_url . '">' . __( 'order details page', 'cielo-woocommerce' ) . '</a>' ) . '</div>';
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param  object $order         Order object.
	 * @param  bool   $sent_to_admin Send to admin.
	 * @param  bool   $plain_text    Plain text or HTML.
	 *
	 * @return string                Payment instructions.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! in_array( $order->status, array( 'processing', 'on-hold' ) ) || $this->id !== $order->payment_method ) {
			return;
		}

		$card_brand = get_post_meta( $order->id, '_wc_cielo_card_brand', true );
		$card_brand = get_payment_method_name( $card_brand );

		if ( $plain_text ) {
			woocommerce_get_template(
				'debit-card/emails/plain-instructions.php',
				array(
					'card_brand'     => $card_brand,
					'discount'       => $this->debit_discount,
					'discount_total' => $this->get_debit_discount( $order_total )
				),
				'woocommerce/cielo/',
				WC_Iugu::get_templates_path()
			);
		} else {
			woocommerce_get_template(
				'debit-card/emails/html-instructions.php',
				array(
					'card_brand'     => $card_brand,
					'discount'       => $this->debit_discount,
					'discount_total' => $this->get_debit_discount( $order_total )
				),
				'woocommerce/cielo/',
				WC_Cielo::get_templates_path()
			);
		}
	}
}

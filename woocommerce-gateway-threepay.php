<?php
/*
Plugin Name: 3Pay.co Payment Gateway for WooCommerce
Plugin URI: https://www.3pay.co/developer?type=express
Description: Extends WooCommerce 3Pay.co Payment Gateway.
Version: 1.0.0
Author: 3Pay.co
    Copyright: © 2009-2020 3Pay.co.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
add_action('plugins_loaded', 'woocommerce_gateway_threepay_init', 0);

define('threepay_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_threepay_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    /**
     * Gateway class
     */
    class WC_Gateway_threepay extends WC_Payment_Gateway
    {


        /**
         * Make __construct()
         **/
        public function __construct()
        {

            $this->id = 'threepay'; // ID for WC to associate the gateway values
            $this->method_title = '3Pay'; // Gateway Title as seen in Admin Dashboad
            $this->method_description = '3Pay A Bangladeshi Payment Gateway'; // Gateway Description as seen in Admin Dashboad
            $this->has_fields = false; // Inform WC if any fileds have to be displayed to the visitor in Frontend

            $this->init_form_fields();    // defines your settings to WC
            $this->init_settings();        // loads the Gateway settings into variables for WC

            $this->redirect_page_id = $this->settings['redirect_page'];

            // Special settigns if gateway is on Test Mode
            $test_ttitle = '';
            $test_description = '';
            $key_URL = 'https://www.3pay.co/api/request';
            $client_secret = $this->settings['client_secret'];


            $this->title = $this->settings['title'] . $test_ttitle; // Title as displayed on Frontend
            $this->description = $this->settings['description'] . $test_description; // Description as displayed on Frontend
            if ($this->settings['show_logo'] != "no") { // Check if Show-Logo has been allowed
                $this->icon = threepay_IMG . 'logo_' . $this->settings['show_logo'] . '.png';
            }
            $this->client_id = $this->settings['client_id'];

            $this->client_secret = $client_secret;
            $this->liveurl = $key_URL;

            $this->msg['message'] = '';
            $this->msg['class'] = '';

            add_action('init', array(&$this, 'check_threepay_response'));
            add_action('init', array(&$this, 'check_threepay_response_ipn'));

            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_threepay_response')); //update for woocommerce >2.0

            add_action('woocommerce_api_' . strtolower(get_class($this)) . "_ipn", array($this, 'check_threepay_response_ipn')); //update for woocommerce >2.0


            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options')); //update for woocommerce >2.0
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options')); // WC-1.6.6
            }
            add_action('woocommerce_receipt_threepay', array(&$this, 'receipt_page'));
        } //END-__construct

        /**
         * Initiate Form Fields in the Admin Backend
         **/
        function init_form_fields()
        {

            $this->form_fields = array(
                // Activate the Gateway
                'enabled' => array(
                    'title' => __('Enable/Disable:', 'woo_threepay'),
                    'type' => 'checkbox',
                    'label' => __('Enable ThreePay> 3Pay.co', 'woo_threepay'),
                    'default' => 'no',
                    'description' => 'Show in the Payment List as a payment option'
                ),
                // Title as displayed on Frontend
                'title' => array(
                    'title' => __('Title:', 'woo_threepay'),
                    'type' => 'text',
                    'default' => __('3Pay.co (Bkash, Rocket, Nagad, Visa, Mastercard, Amex, DBBL Nexus)', 'woo_threepay'),
                    'description' => __('This controls the title which the user sees during checkout.', 'woo_threepay'),
                    'desc_tip' => true
                ),
                // Description as displayed on Frontend
                'description' => array(
                    'title' => __('Description:', 'woo_threepay'),
                    'type' => 'textarea',
                    'default' => __('Pay securely by Credit or Debit card or internet banking through 3Pay.', 'woo_threepay'),
                    'description' => __('This controls the description which the user sees during checkout.', 'woo_threepay'),
                    'desc_tip' => true
                ),
                // ThreePay Merhcant ID
                'client_id' => array(
                    'title' => __('3Pay Client ID', 'Redwan'),
                    'type' => 'text',
                    'description' => __('This id available at <a href="www.3pay.co/merchants">www.3pay.co/merchants</a> or email at: info@3pay.co')),
                // LIVE Key-Secret
                'client_secret' => array(
                    'title' => __('3Pay Client Secret:', 'woo_threepay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by ThreePay'),
                    'desc_tip' => true
                ),

                // Page for Redirecting after Transaction
                'redirect_page' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->threepay_get_pages('Select Page'),
                    'description' => __('URL of success page', 'woo_threepay'),
                    'desc_tip' => true
                ),
                // Show Logo on Frontend
                'show_logo' => array(
                    'title' => __('Show Logo:', 'woo_threepay'),
                    'type' => 'select',
                    'label' => __('Enable threepay TEST Transactions.', 'woo_threepay'),
                    'options' => array('no' => 'No Logo', 'icon-light' => 'Light - Icon', 'icon' => 'Dark'),
                    'default' => 'no',
                    'description' => __('<strong>3Pay (Light)</strong> | Icon: <img src="' . threepay_IMG . 'logo_icon-light.png" height="24px" /><br/>' . "\n"
                        . '<strong>3Pay Dark&nbsp;&nbsp;</strong> | Icon: <img src="' . threepay_IMG . 'logo.png" height="24px" /> | Logo: <img src="' . threepay_IMG . 'logo.png" height="24px" /> | Logo (Full): <img src="' . threepay_IMG . 'logo.png" height="24px" />', 'woo_threepay'),
                    'desc_tip' => false
                )
            );

        } //END-init_form_fields

        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
        public function admin_options()
        {
            echo '<h3>' . __('3Pay', 'woo_threepay') . '</h3>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        } //END-admin_options

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        } //END-payment_fields

        /**
         * Receipt Page
         **/
        function receipt_page($order)
        {
            echo '<p><strong>' . __('Thank you for your order.', 'woo_threepay') . '</strong><br/>' . __('The payment page will open soon.', 'woo_threepay') . '</p>';
            echo $this->generate_threepay_form($order);
        } //END-receipt_page

        /**
         * Generate button link
         **/
        function generate_threepay_form($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);

            // Redirect URL
            if ($this->redirect_page_id == '' || $this->redirect_page == 0) {
                $redirect_url = get_site_url() . "/";
            } else {
                $redirect_url = get_permalink($this->redirect_page);
            }
            // Redirect URL : For WooCoomerce 2.0
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                $redirect_url = add_query_arg('wc-api', strtolower(get_class($this)), esc_url($this->get_return_url($order)));
            }

            $productinfo = "Order $order_id";

            $txnid = $order_id;


            $fields = array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'amount' => $order->order_total,
                'tran_id' => $txnid,
                'desc' => $productinfo,
                'custom' => $productinfo,
                'payment_type' => '3pay',
                'currency' => get_option('woocommerce_currency'),
                'ip' => $_SERVER["SERVER_ADDR"],
                'cus_name' => $order->billing_first_name . ' ' . $order->billing_last_name,
                'cus_email' => $order->billing_email,
                'cus_add1' => $order->billing_address_1,
                'cus_add2' => $order->billing_address_1,
                'cus_city' => $order->billing_city,
                'cus_state' => $order->billing_state,
                'cus_postcode' => $order->billing_postcode,
                'cus_country' => $order->billing_country,
                'cus_phone' => $order->billing_phone,
                'cus_fax' => $order->billing_phone,
                'ship_name' => $order->shipping_first_name . ' ' . $order->shipping_last_name,
                'ship_add1' => $order->shipping_address_1,
                'ship_add2' => $order->shipping_address_1,
                'ship_city' => $order->shipping_city,
                'ship_state' => $order->shipping_state,
                'ship_postcode' => $order->shipping_postcode,
                'ship_country' => $order->shipping_country,
                'success_url' => $redirect_url,
                'notify_url' => $redirect_url . "_ipn",
                'cancel_url' => $redirect_url,
                'opt_a' => '',
                'opt_b' => '',
                'opt_c' => '',
                'opt_d' => ''
            );



            $response = wp_remote_post( $this->liveurl, array("body"=>$fields) );

            if($response["response"]["code"]==200){
                wp_safe_redirect(esc_url($response["body"]));
                wp_redirect($response["body"]);
                exit();
            }
            else{
                wc_add_notice("Thank you for shopping with us. However, Somethings went wrong. Please Contract with Support", 'error');

                wp_redirect($order->get_checkout_payment_url());
                exit();
            }

        } //END-generate_threepay_form

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {

            global $woocommerce;
            $order = new WC_Order($order_id);

            if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) { // For WC 2.1.0
                $checkout_payment_url = $order->get_checkout_payment_url(true);
            } else {
                $checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
            }

            return array(
                'result' => 'success',
                'redirect' => add_query_arg(
                    'order',
                    $order->id,
                    add_query_arg(
                        'key',
                        $order->order_key,
                        $checkout_payment_url
                    )
                )
            );
        } //END-process_payment

        /**
         * Check for valid gateway server callback
         **/
        function check_threepay_response()
        {

            global $woocommerce;

            if (isset($_GET["threepay_txnid"])) {
                $threepay_txnid = sanitize_text_field($_GET["threepay_txnid"]);
                $key = sanitize_text_field($_GET["key"]);
                $order_id = wc_get_order_id_by_order_key($key);

                // Check the Threepay trxnid
                $response = wp_remote_get( "https://www.3pay.co/api/trxcheck?threepay_trxnid=" . $threepay_txnid . "&client_id=" . $this->client_id . "&client_secret=" . $this->client_secret . "&type=json" );

                $data = wp_remote_retrieve_body($response);

                // Payment cancelled
                if($data=="Merchant Not Matched"){
                    wc_add_notice("Thank you for shopping with us. However, the transaction has been canceled.", 'error');

                    $order = new WC_Order($order_id);
                    $order->add_order_note('ThreePay payment On Cancel');
                    //$order->update_status('cancelled');
                    $woocommerce->cart->empty_cart();

                    header("Location: " . $order->get_checkout_payment_url());
                    exit();
                }

                // Decode payment Data
                $data = json_decode($data, true);

                // If data does not valid
                if (!$data) {
                    wc_add_notice("Thank you for shopping with us. However, Somethings went wrong. Please Contract with Support", 'error');

                    $order = new WC_Order($order_id);

                    $order->add_order_note('ThreePay payment on somethings wrong');
                    //$order->update_status('cancelled');
                    $woocommerce->cart->empty_cart();

                    header("Location: " . $order->get_checkout_payment_url());
                    exit();
                }

            }

            // Check Has order number on data
            if (isset($data['mer_txnid'])) {
                $order_id = $data['mer_txnid'];
                if ($order_id != '') {
                    try {
                        $order = new WC_Order($order_id);
                        $status = $data['pay_status'];
                        $risk_level = $data['risk_level'];
                        $trans_authorised = false;

                        if ($order->status !== 'completed') {

                            $status = strtolower($status);
                            if ($status == "success") {
                                $trans_authorised = true;
                                $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                                $this->msg['class'] = 'woocommerce-message';
                                if ($order->status == 'processing') {
                                    $order->add_order_note(wp_kses('3Pay Txn Id: ' . $data['threepay_txnid'] . '<br/>Payment Processor : ' . $data['payment_processor'] . ' <br/> Bin Country : ' . $data['bin_country'] . ' <br/> Cardnumber : ' . $data['cardnumber'] . '  <br/>Bank trxid : ' . $data['bank_trxid'] . ' <br/>Risk Level: ' . $risk_level . ' ', array('br' => array())));
                                } else {
                                    $order->payment_complete();
                                    $order->add_order_note(wp_kses('3Pay payment successful.<br/>  3Pay Txn Id: ' . $data['threepay_txnid'] .  '<br/>Payment Processor : ' . $data['payment_processor'] . ' <br/> Bin Country : ' . $data['bin_country'] . '<br/> Cardnumber : ' . $data['cardnumber'] . '   <br/>Bank trxid : ' . $data['bank_trxid'] . ' <br/>Risk Level: ' . $risk_level . ' ', array('br' => array())));
                                    $order->update_status('processing');
                                    //$woocommerce->cart->empty_cart();
                                }
                            } else if ($status == "success" && $risk_level == 1) {
                                $trans_authorised = true;
                                $this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. ThreePay will keep you posted regarding the status of your order through eMail. Please Co-Operate With 3Pay.co.";
                                $this->msg['class'] = 'woocommerce-info';
                                $order->add_order_note(wp_kses('3Pay payment On Hold.<br/>  3Pay Txn Id: ' . $_POST['threepay_txnid'] .  '<br/>Payment Processor : ' . $_POST['payment_processor'] . ' <br/> Bin Country : ' . $_POST['bin_country'] . '<br/> Cardnumber : ' . $data['cardnumber'] . '   <br/>Bank trxid : ' . $_POST['bank_trxid'] . ' <br/>Risk Level: ' . $risk_level . ' ', array('br' => array())));
                                $order->update_status('on-hold');
                                $woocommerce->cart->empty_cart();
                            } else {
                                $this->msg['class'] = 'woocommerce-error';
                                $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                $order->add_order_note(wp_kses('Transaction ERROR: ' . $data['error'] . '<br/>ThreePay ID: ' . $data['threepay_txnid'] . ' (' . $data['mer_txnid'] . ')<br/>Card Type: ' . $data['payment_type'] . '(' . $data['cardnumber'] . ')<br/>Risk Level: ' . $risk_level . '', array('br' => array())));
                            }

                            if ($trans_authorised == false) {
                                $order->update_status('failed');
                            }

                            header("Location: " . esc_url($this->get_return_url($order)));

                            exit();


                            //removed for WooCommerce 2.0
                            //add_action('the_content', array(&$this, 'threepay_showMessage'));
                        }
                    } catch (Exception $e) {
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
                }

                if ($this->redirect_page_id == '' || $this->redirect_page_id == 0) {
                    $redirect_url = esc_url($this->get_return_url($order));
                } else {
                    $redirect_url = esc_url($this->get_return_url($order));
                }

                return array(
                    'result' => 'success',
                    'redirect' => $redirect_url
                );
            }

        } //END-check_3Pay_response


        /**
         *  Payment Re-verify by IPN
         */

        function check_threepay_response_ipn()
        {



            global $woocommerce;

            if (isset($_POST['mer_txnid'])) {
                $order_id = sanitize_key($_POST['mer_txnid']);
                if ($order_id != '') {
                    try {
                        $order = new WC_Order($order_id);
                        $status = sanitize_text_field($_POST['pay_status']);
                        $risk_level = (int) $_POST['risk_level'];
                        $trans_authorised = false;

                        if ($order->status !== 'completed') {

                            $status = strtolower($status);
                            if ($status == "success") {
                                $trans_authorised = true;

                                if ($order->status == 'processing') {
                                    $order->add_order_note('3Pay Txn Id: ' . sanitize_text_field($_POST['threepay_txnid']) . '<br/>Payment Processor : ' . sanitize_text_field($_POST['payment_processor']) . ' <br/> Bin Country : ' . sanitize_text_field($_POST['bin_country']) . '  <br/> Cardnumber : ' . sanitize_text_field($_POST['cardnumber'] ). '  <br/>Bank trxid : ' . sanitize_text_field($_POST['bank_trxid']) . ' <br/>Risk Level: ' . $risk_level . ' ');
                                } else {
                                    $order->payment_complete();
                                    $order->add_order_note('3Pay payment successful.<br/>  3Pay Txn Id: ' . sanitize_text_field($_POST['threepay_txnid']) .  '<br/>Payment Processor : ' . sanitize_text_field($_POST['payment_processor']) . ' <br/> Bin Country : ' . sanitize_text_field($_POST['bin_country']) . ' <br/> Cardnumber : ' . sanitize_text_field($_POST['cardnumber']) . '  <br/>Bank trxid : ' . sanitize_text_field($_POST['bank_trxid']) . ' <br/>Risk Level: ' . $risk_level . ' ');
                                    $order->update_status('processing');
                                    //$woocommerce->cart->empty_cart();
                                }
                            } else if ($status == "success" && $risk_level == 1) {
                                $trans_authorised = true;

                                $order->add_order_note('3Pay payment On Hold.<br/>  3Pay Txn Id: ' . sanitize_text_field($_POST['threepay_txnid']) .  '<br/>Payment Processor : ' . sanitize_text_field($_POST['payment_processor']) . ' <br/> Bin Country : ' . sanitize_text_field($_POST['bin_country'] ). ' <br/> Cardnumber : ' . sanitize_text_field($_POST['cardnumber'] ). '  <br/>Bank trxid : ' . sanitize_text_field($_POST['bank_trxid']) . ' <br/>Risk Level: ' . $risk_level . ' ');
                                $order->update_status('on-hold');
                                // $woocommerce -> cart -> empty_cart();
                            } else {
                                $order->add_order_note('Transaction ERROR: ' . sanitize_text_field($_POST['error_code']) . '<br/>ThreePay ID: ' . sanitize_text_field($_POST['threepay_txnid']) . ' (' . sanitize_text_field($_POST['mer_txnid']) . ')<br/>Card Type: ' . sanitize_text_field($_POST['payment_type']) . '(' . sanitize_text_field($_POST['cardnumber']) . ')<br/>Risk Level: ' . $risk_level . '');
                            }
                            //header("Location: ".esc_url($this->get_return_url($order)));
                            if ($trans_authorised == false) {
                                $order->update_status('failed');
                            }

                            //removed for WooCommerce 2.0
                            //add_action('the_content', array(&$this, 'threepay_showMessage'));
                        }
                    } catch (Exception $e) {
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
                }


            }

        } //END-check_3Pay_response


        /**
         * Get Page list from WordPress
         **/
        function threepay_get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';

                        $next_page = get_post($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        } //END-threepay_get_pages

    } //END-class

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_gateway_threepay_gateway($methods)
    {
        $methods[] = 'WC_Gateway_threepay';
        return $methods;
    }//END-wc_add_gateway

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_threepay_gateway');

} //END-init


    /**
     * 'Settings' link on plugin page
     **/
    add_filter('plugin_action_links', 'threepay_add_action_plugin', 10, 5);
    function threepay_add_action_plugin($actions, $plugin_file)
    {
        static $plugin;

        if (!isset($plugin))
            $plugin = plugin_basename(__FILE__);
        if ($plugin == $plugin_file) {

            $settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_threepay">' . __('Settings') . '</a>');

            $actions = array_merge($settings, $actions);

        }

        return $actions;
    }//END-settings_add_action_link
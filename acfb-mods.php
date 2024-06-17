<?php
    if (!defined('ABSPATH')) exit; // Exit if accessed directly

    /**
     * Plugin Name: ACFB - Mods
     * Plugin URI: https://github.com/chikumberz/ACFB-mods
     * Description: This are modification hooks for ACFB
     * Version: 0.1.3
     * Author: Benjamin Taluyo
     * E-mail: benjie.taluyo@gmail.com
     * License: GPLv2 or later
     * License URI: http://www.gnu.org/licenses/gpl-2.0.html
     * Requires Plugins: woocommerce
     */

    // this function sets the checkout form data as session transients whenever the checkout page validates
    add_action('woocommerce_checkout_update_order_review', 'acfb_woocommerce_checkout_fields_store');

    function acfb_woocommerce_checkout_fields_store ($posted_data) {
        $data = (array) WC()->session->get('store_checkout_fields');

        // Parsing posted data on checkout
        parse_str($posted_data, $posted_data_output);

        if (is_array($posted_data_output)) {
            if (array_key_exists('billing_first_name', $posted_data_output) && !empty($posted_data_output['billing_first_name'])) $data['billing_first_name'] = $posted_data_output['billing_first_name'];
            if (array_key_exists('billing_last_name', $posted_data_output) && !empty($posted_data_output['billing_last_name'])) $data['billing_last_name'] = $posted_data_output['billing_last_name'];
            if (array_key_exists('billing_email', $posted_data_output) && !empty($posted_data_output['billing_email'])) $data['billing_email'] = $posted_data_output['billing_email'];
            if (array_key_exists('order_comments', $posted_data_output) && !empty($posted_data_output['order_comments'])) $data['order_comments'] = $posted_data_output['order_comments'];
        }

        WC()->session->set('store_checkout_fields', $data);
    }

    /*
    add_filter('woocommerce_checkout_fields' , 'acfb_woocommerce_checkout_fields_default', 20);

    function acfb_woocommerce_checkout_fields_default( $fields ) {
        $data = (array) WC()->session->get('store_checkout_fields');

        if (is_array($data)) {
            if (array_key_exists('billing_first_name', $data) && !empty($data['billing_first_name'])) $fields['billing']['billing_first_name']['default'] = $data['billing_first_name'];
            if (array_key_exists('billing_last_name', $data) && !empty($data['billing_last_name'])) $fields['billing']['billing_last_name']['default'] = $data['billing_last_name'];
            if (array_key_exists('billing_email', $data) && !empty($data['billing_email'])) $fields['billing']['billing_email']['default'] = $data['billing_email'];
            if (array_key_exists('order_comments', $data) && !empty($data['order_comments'])) $fields['order']['order_comments']['default'] = $data['order_comments'];
        }

        return $fields;
    }
    */

    add_filter('woocommerce_checkout_get_value', 'acfb_woocommerce_checkout_get_value', 20, 2);

    function acfb_woocommerce_checkout_get_value ($value, $index) {
        $data = (array) WC()->session->get('store_checkout_fields');

        if ($index == 'billing_first_name' && array_key_exists('billing_first_name', $data) && !empty($data['billing_first_name'])) $value = $data['billing_first_name'];
        if ($index == 'billing_last_name' && array_key_exists('billing_last_name', $data) && !empty($data['billing_last_name'])) $value = $data['billing_last_name'];
        if ($index == 'billing_email' && array_key_exists('billing_email', $data) && !empty($data['billing_email'])) $value = $data['billing_email'];
        if ($index == 'order_comments' && array_key_exists('order_comments', $data) && !empty($data['order_comments'])) $value = $data['order_comments'];

        return $value;
    }

    // Remove billing information if the cart products are only virtual
    add_filter('woocommerce_default_address_fields' , 'acfb_woocommerce_default_address_fields_filter', 20, 1);

    function acfb_woocommerce_default_address_fields_filter ($address_fields) {
        if (!is_checkout()) return $address_fields;

        // Check chosen payment method
        $chosen_payment_method = WC()->session->get('chosen_payment_method');

        if ($chosen_payment_method == 'afterpay') return $address_fields;

        $only_virtual = true;

        // Check if there are non-virtual products
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if (!$cart_item['data']->is_virtual()) $only_virtual = false; break;
        }

        if ($only_virtual) {
            // All field keys in this array
            $key_fields = array('country','company','address_1','address_2','city','state','postcode');

            // Loop through each address fields (billing and shipping)
            foreach( $key_fields as $key_field )
                $address_fields[$key_field]['required'] = false;
        }

        return $address_fields;
    }

    add_action('wp_footer', 'acfb_add_jscript_checkout', 9999);

    function acfb_add_jscript_checkout () {
        global $wp;

        if ( is_checkout() && empty( $wp->query_vars['order-pay'] ) && ! isset( $wp->query_vars['order-received'] ) ) {
            $only_virtual          = true;
            $chosen_payment_method = WC()->session->get('chosen_payment_method');

            // Check if there are non-virtual products
            foreach( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( ! $cart_item['data']->is_virtual() ) $only_virtual = false; break;
            }

            if ( $only_virtual ) {
                echo <<<EOF
                <script>
                    (function ($) {
                        // Use this variable to store the previously selected payment method
                        let default_pay_method = '{$chosen_payment_method}';

                        $('body').on('change', 'input[name="payment_method"]', function () {
                            let fields = $('.woocommerce-billing-fields #billing_company_field, .woocommerce-billing-fields #billing_country_field, .woocommerce-billing-fields #billing_address_1_field, .woocommerce-billing-fields #billing_address_2_field, .woocommerce-billing-fields #billing_city_field, .woocommerce-billing-fields #billing_state_field, .woocommerce-billing-fields #billing_postcode_field, .woocommerce-shipping-fields');
                            let target = $('form[name="checkout"] input[name="payment_method"]:checked');

                            // Always hide the input fields if the value of selected payment gateway is not 'afterpay'
                            if (target.val() != 'afterpay') fields.hide();

                            // Trigger form checkout update once
                            $('body').trigger('update_checkout');
                        });

                        $('body').on('updated_checkout', function () {
                            let target = $('form[name="checkout"] input[name="payment_method"]:checked');
                            let target_pay_method = target.val();

                            // Check if not match with the previous payment method then reload it
                            if (target_pay_method && target_pay_method != default_pay_method ) {
                                $.blockUI(); location.reload();
                            }
                        });
                    })(jQuery);
                </script>
EOF;
            }
        }
    }

    function action_woocommerce_shortcode_products_loop_no_results( $attributes ) {
        echo __( 'No products found.', 'woocommerce' );
    }

    add_action( 'woocommerce_shortcode_products_loop_no_results', 'action_woocommerce_shortcode_products_loop_no_results', 10, 1 );
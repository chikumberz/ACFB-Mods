<?php
    if (!defined('ABSPATH')) exit; // Exit if accessed directly

    /**
     * Plugin Name: ACFB - Mods
     * Plugin URI: https://github.com/chikumberz/ACFB-mods
     * Description: This are modification hooks for ACFB
     * Version: 0.1.1
     * Author: Benjamin Taluyo
     * E-mail: benjie.taluyo@gmail.com
     * License: GPLv2 or later
     * License URI: http://www.gnu.org/licenses/gpl-2.0.html
     * Requires Plugins: woocommerce
     */

    // Remove billing information if the cart products are only virtual
    add_filter( 'woocommerce_default_address_fields' , 'filter_default_address_fields', 20, 1 );

    function filter_default_address_fields( $address_fields ) {
        if( ! is_checkout() ) return $address_fields;

        $only_virtual = true;

        // Check if there are non-virtual products
        foreach( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! $cart_item['data']->is_virtual() ) $only_virtual = false; break;
        }

        if ( $only_virtual ) {
            // All field keys in this array
            $key_fields = array('country','company','address_1','address_2','city','state','postcode');

            // Loop through each address fields (billing and shipping)
            foreach( $key_fields as $key_field )
                $address_fields[$key_field]['required'] = false;
        }

        return $address_fields;
    }

    add_action( 'wp_footer', 'add_jscript_checkout', 9999 );

    function add_jscript_checkout() {
        global $wp;

        if ( is_checkout() && empty( $wp->query_vars['order-pay'] ) && ! isset( $wp->query_vars['order-received'] ) ) {
            $only_virtual = true;

            // Check if there are non-virtual products
            foreach( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( ! $cart_item['data']->is_virtual() ) $only_virtual = false; break;
            }

            if ( $only_virtual ) {
                echo <<<EOF
                <script>
                jQuery(function(){
                    jQuery( 'body' )
                    .on( 'updated_checkout', function() {
                        usingPayGateway();

                        jQuery('input[name="payment_method"]').change(function(){
                            // console.log("payment method changed");
                            usingPayGateway();
                        });
                    });
                });

                function usingPayGateway(){
                    // console.log(jQuery("input[name='payment_method']:checked").val());
                    var fields = jQuery('.woocommerce-billing-fields #billing_company_field, .woocommerce-billing-fields #billing_country_field, .woocommerce-billing-fields #billing_address_1_field, .woocommerce-billing-fields #billing_address_2_field, .woocommerce-billing-fields #billing_city_field, .woocommerce-billing-fields #billing_state_field, .woocommerce-billing-fields #billing_postcode_field, .woocommerce-shipping-fields');

                    if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'afterpay'){
                        fields.show();
                    } else {
                        fields.hide();
                    }
                }
                </script>
EOF;
            }
        }
    }
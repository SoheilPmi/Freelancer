<?php
/**
 * Plugin Name: MH Hash Link Redirect
 * Description: افزودن فیلد لینک هش به محصولات و هدایت خودکار محصولات رایگان به آن لینک
 * Version: 1.0
 */

defined( 'ABSPATH' ) || exit; // جلوگیری از دسترسی مستقیم

// فقط وقتی ووکامرس فعال است
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    /** 1. افزودن متاباکس لینک هش به صفحه ویرایش محصول **/
    add_action( 'add_meta_boxes', function() {
        add_meta_box(
            'mh_hashed_link',
            'لینک هش محصول',
            function( $post ) {
                wp_nonce_field( 'mh_save_hashed_link', 'mh_hashed_link_nonce' );
                $val = get_post_meta( $post->ID, '_mh_hashed_link', true );
                echo '<input type="url" name="mh_hashed_link" value="' 
                     . esc_attr( $val ) 
                     . '" style="width:100%;" placeholder="https://example.com/hash-link">';
            },
            'product',
            'side',
            'default'
        );
    });

    /** 2. ذخیره لینک هش **/
    add_action( 'save_post_product', function( $post_id ) {
        if ( ! isset( $_POST['mh_hashed_link_nonce'] ) 
            || ! wp_verify_nonce( $_POST['mh_hashed_link_nonce'], 'mh_save_hashed_link' ) ) return;
        if ( isset( $_POST['mh_hashed_link'] ) ) {
            update_post_meta(
                $post_id,
                '_mh_hashed_link',
                esc_url_raw( $_POST['mh_hashed_link'] )
            );
        }
    });

    /** 3. جایگزینی قیمت برای محصولات با قیمت صفر **/
    add_filter( 'woocommerce_get_price_html', function( $price_html, $product ) {
        if ( floatval( $product->get_price() ) === 0.0 ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return '<a class="button" href="' . esc_url( $link ) . '">مشاهده</a>';
            }
        }
        return $price_html;
    }, 10, 2 );

    /** 4. جایگزینی دکمه خرید در صفحه تکی **/
    add_action( 'woocommerce_single_product_summary', function() {
        global $product;
        if ( floatval( $product->get_price() ) === 0.0 ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
                echo '<a href="' . esc_url( $link ) . '" class="button">مشاهده</a>';
            }
        }
    }, 1 );

    /** 5. جایگزینی دکمه خرید در آرشیو محصولات **/
    add_filter( 'woocommerce_loop_add_to_cart_link', function( $button, $product ) {
        if ( floatval( $product->get_price() ) === 0.0 ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return '<a href="' . esc_url( $link ) . '" class="button">مشاهده</a>';
            }
        }
        return $button;
    }, 10, 2 );

}

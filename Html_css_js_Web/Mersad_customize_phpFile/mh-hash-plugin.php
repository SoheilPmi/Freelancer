<?php
// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. اضافه کردن متاباکس برای لینک هش شده
 */
function mh_add_hashed_link_meta_box() {
    add_meta_box(
        'mh_hashed_link',
        'URL هش شده',
        'mh_hashed_link_meta_box_callback',
        'product',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'mh_add_hashed_link_meta_box' );

/**
 * 2. نمایش فیلد متاباکس
 */
function mh_hashed_link_meta_box_callback( $post ) {
    wp_nonce_field( 'mh_save_hashed_link', 'mh_hashed_link_nonce' );
    $value = get_post_meta( $post->ID, '_mh_hashed_link', true );
    echo '<label for="mh_hashed_link_field">لینک هش شده:</label>';
    echo '<input type="url" id="mh_hashed_link_field" name="mh_hashed_link_field"'
       . ' value="' . esc_attr( $value ) . '" style="width:100%;" />';
}

/**
 * 3. ذخیره لینک هش شده
 */
function mh_save_hashed_link_meta( $post_id ) {
    if (
        ! isset( $_POST['mh_hashed_link_nonce'] ) ||
        ! wp_verify_nonce( $_POST['mh_hashed_link_nonce'], 'mh_save_hashed_link' )
    ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( isset( $_POST['mh_hashed_link_field'] ) ) {
        update_post_meta(
            $post_id,
            '_mh_hashed_link',
            esc_url_raw( $_POST['mh_hashed_link_field'] )
        );
    }
}
add_action( 'save_post_product', 'mh_save_hashed_link_meta' );

/**
 * 4. جایگزینی دکمه افزودن به سبد در صفحه محصول
 */
function mh_replace_add_to_cart_button() {
    global $product;
    $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
    if ( $link ) {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        echo sprintf(
            '<a href="%1$s" class="button mh-hash-button" target="_blank">خرید / دانلود</a>',
            esc_url( $link )
        );
    }
}
add_action( 'woocommerce_single_product_summary', 'mh_replace_add_to_cart_button', 30 );

/**
 * 5. جایگزینی دکمه افزودن به سبد در آرشیو (لیست محصولات)
 */
function mh_replace_loop_add_to_cart_link( $button, $product ) {
    $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
    if ( $link ) {
        return sprintf(
            '<a href="%1$s" class="button mh-hash-button" target="_blank">خرید / دانلود</a>',
            esc_url( $link )
        );
    }
    return $button;
}
add_filter( 'woocommerce_loop_add_to_cart_link', 'mh_replace_loop_add_to_cart_link', 10, 2 );

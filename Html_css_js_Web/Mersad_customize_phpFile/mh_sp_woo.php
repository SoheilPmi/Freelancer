<?php
/**
 * Plugin Name: MH Hash Link Redirect
 * Description: افزودن فیلد لینک هش به محصولات و هدایت خودکار محصولات رایگان به آن لینک
 * Version: 1.1
 */
defined( 'ABSPATH' ) || exit;

// بررسی فعال‌بودن ووکامرس
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    // 1. افزودن متاباکس لینک هش
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

    // 2. ذخیره لینک هش (با بررسی امنیتی کامل)
    add_action( 'save_post_product', function( $post_id ) {
        // بررسی nonce
        if ( ! isset( $_POST['mh_hashed_link_nonce'] ) 
            || ! wp_verify_nonce( $_POST['mh_hashed_link_nonce'], 'mh_save_hashed_link' ) ) {
            return;
        }

        // بررسی قابلیت‌های کاربر
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // جلوگیری از ذخیره خودکار
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // ذخیره متا
        if ( isset( $_POST['mh_hashed_link'] ) ) {
            update_post_meta(
                $post_id,
                '_mh_hashed_link',
                esc_url_raw( $_POST['mh_hashed_link'] )
            );
        }
    });

    // 3. جایگزینی قیمت برای محصولات رایگان
    add_filter( 'woocommerce_get_price_html', function( $price, $product ) {
        if ( $product->is_type( 'simple' ) && $product->is_free() ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return '<a class="button" href="' . esc_url( $link ) . '">مشاهده</a>';
            }
        }
        return $price;
    }, 10, 2 );

    // 4. جایگزینی دکمه در صفحه تکی
    add_action( 'woocommerce_single_product_summary', function() {
        global $product;
        
        // فقط برای محصولات ساده و رایگان
        if ( $product->is_type( 'simple' ) && $product->is_free() ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                // حذف دکمه پیش‌فرض با روش مطمئن‌تر
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
                add_action( 'woocommerce_single_product_summary', function() use ( $link ) {
                    echo '<a href="' . esc_url( $link ) . '" class="button alt">مشاهده محصول</a>';
                }, 30 );
            }
        }
    }, 5 ); // اولویت پایین‌تر برای اجرای بهتر

    // 5. جایگزینی دکمه در آرشیو (با کش متا)
    add_filter( 'woocommerce_loop_add_to_cart_link', function( $button, $product ) {
        static $meta_cache = []; // کش متا برای بهینه‌سازی
        
        // فقط محصولات ساده و رایگان
        if ( $product->is_type( 'simple' ) && $product->is_free() ) {
            $product_id = $product->get_id();
            
            // استفاده از کش
            if ( ! isset( $meta_cache[ $product_id ] ) ) {
                $meta_cache[ $product_id ] = get_post_meta( $product_id, '_mh_hashed_link', true );
            }
            
            $link = $meta_cache[ $product_id ];
            if ( $link ) {
                return '<a href="' . esc_url( $link ) . '" class="button">مشاهده</a>';
            }
        }
        return $button;
    }, 10, 2 );
}

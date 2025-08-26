<?php
/**
 * Plugin Name: MH Hash Link Redirect
 * Description: افزودن فیلد لینک هش به محصولات و هدایت خودکار محصولات رایگان به آن لینک
 * Version: 5.3
 */
defined( 'ABSPATH' ) || exit;

// بررسی فعال‌بودن ووکامرس
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    // متغیرهای قابل تنظیم برای متن دکمه‌ها
    $chechOutTitle = "مشاهده";
    $chechOutTitleForProduct = "مشاهده محصول";
    
    // افزودن استایل‌های CSS و انیمیشن
    add_action('wp_head', function() {
        ?>
        <style>
            .mh-view-button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
                padding: 12px 28px;
                border-radius: 50px;
                text-decoration: none;
                display: inline-block;
                font-weight: 600;
                font-size: 15px;
                letter-spacing: 0.5px;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                position: relative;
                overflow: hidden;
                z-index: 1;
            }
            
            .mh-view-button:before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                transition: left 0.5s;
                z-index: -1;
            }
            
            .mh-view-button:hover {
                transform: translateY(-3px);
                box-shadow: 0 7px 20px rgba(102, 126, 234, 0.6);
                color: #ffffff;
            }
            
            .mh-view-button:hover:before {
                left: 100%;
            }
            
            .mh-view-button:active {
                transform: translateY(1px);
                box-shadow: 0 3px 10px rgba(102, 126, 234, 0.4);
            }
            
            /* استایل برای دکمه در سبد خرید و پرداخت */
            .cart .mh-view-button, 
            .checkout .mh-view-button {
                padding: 8px 18px;
                font-size: 13px;
                margin-top: 8px;
                display: inline-block;
            }
            
            /* انیمیشن ظاهر شدن */
            @keyframes mh-fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .mh-view-button {
                animation: mh-fadeIn 0.6s ease-out;
            }
        </style>
        <?php
    });

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

    // 3. مخفی کردن قیمت صفر و نمایش دکمه مشاهده
    add_filter( 'woocommerce_get_price_html', function( $price, $product ) {
        // تشخیص محصول رایگان (قیمت صفر یا خالی)
        if ( $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return ''; // مخفی کردن قیمت
            }
        }
        return $price;
    }, 10, 2 );

    // 4. جایگزینی دکمه در صفحه تکی محصول
    add_action( 'woocommerce_single_product_summary', function() use ( $chechOutTitleForProduct ) {
        global $product;
        
        // تشخیص محصول رایگان (قیمت صفر یا خالی)
        if ( $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                // حذف دکمه پیش‌فرض
                remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
                
                // افزودن دکمه جدید
                add_action( 'woocommerce_single_product_summary', function() use ( $link, $chechOutTitleForProduct ) {
                    echo '<a href="' . esc_url( $link ) . '" class="mh-view-button" target="_blank" rel="noopener noreferrer">' . esc_html( $chechOutTitleForProduct ) . '</a>';
                }, 30 );
            }
        }
    }, 5 );

    // 5. جایگزینی دکمه در آرشیو محصولات
    add_filter( 'woocommerce_loop_add_to_cart_link', function( $button, $product ) use ( $chechOutTitle ) {
        static $meta_cache = []; // کش متا برای بهینه‌سازی
        
        // تشخیص محصول رایگان (قیمت صفر یا خالی)
        if ( $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $product_id = $product->get_id();
            
            // استفاده از کش
            if ( ! isset( $meta_cache[ $product_id ] ) ) {
                $meta_cache[ $product_id ] = get_post_meta( $product_id, '_mh_hashed_link', true );
            }
            
            $link = $meta_cache[ $product_id ];
            if ( $link ) {
                return '<a href="' . esc_url( $link ) . '" class="mh-view-button" target="_blank" rel="noopener noreferrer">' . esc_html( $chechOutTitle ) . '</a>';
            }
        }
        return $button;
    }, 10, 2 );

    // 6. مخفی کردن قیمت در سبد خرید
    add_filter( 'woocommerce_cart_item_price', function( $price, $cart_item, $cart_item_key ) {
        $product = $cart_item['data'];
        if ( $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return ''; // مخفی کردن قیمت
            }
        }
        return $price;
    }, 10, 3 );

    // 7. مخفی کردن قیمت در صفحه پرداخت
    add_filter( 'woocommerce_order_item_subtotal', function( $subtotal, $item, $order ) {
        $product = $item->get_product();
        if ( $product && $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return ''; // مخفی کردن قیمت
            }
        }
        return $subtotal;
    }, 10, 3 );

    // 8. جایگزینی نام محصول در سبد خرید با دکمه مشاهده
    add_filter( 'woocommerce_cart_item_name', function( $name, $cart_item, $cart_item_key ) use ( $chechOutTitleForProduct ) {
        $product = $cart_item['data'];
        if ( $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return $name . '<br><a href="' . esc_url( $link ) . '" class="mh-view-button" target="_blank" rel="noopener noreferrer">' . esc_html( $chechOutTitleForProduct ) . '</a>';
            }
        }
        return $name;
    }, 10, 3 );

    // 9. جایگزینی نام محصول در صفحه پرداخت با دکمه مشاهده
    add_filter( 'woocommerce_order_item_name', function( $name, $item, $is_visible ) use ( $chechOutTitleForProduct ) {
        $product = $item->get_product();
        if ( $product && $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $link = get_post_meta( $product->get_id(), '_mh_hashed_link', true );
            if ( $link ) {
                return $name . '<br><a href="' . esc_url( $link ) . '" class="mh-view-button" target="_blank" rel="noopener noreferrer">' . esc_html( $chechOutTitleForProduct ) . '</a>';
            }
        }
        return $name;
    }, 10, 3 );

    // 10. جلوگیری از افزودن محصولات رایگان با لینک هش به سبد خرید
    add_filter( 'woocommerce_add_to_cart_validation', function( $valid, $product_id, $quantity ) use ( $chechOutTitleForProduct ) {
        $product = wc_get_product( $product_id );
        if ( $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
            $link = get_post_meta( $product_id, '_mh_hashed_link', true );
            if ( $link ) {
                // اگر محصول رایگان با لینک هش باشد، جلوگیری از افزودن به سبد خرید
                wc_add_notice( sprintf( 'این محصول رایگان است. برای مشاهده روی دکمه "%s" کلیک کنید.', $chechOutTitleForProduct ), 'error' );
                return false;
            }
        }
        return $valid;
    }, 10, 3 );



    // اضافه کردن کلاس مخصوص برای محصولات رایگان
add_filter( 'post_class', function( $classes, $class, $post_id ) {
    $product = wc_get_product( $post_id );
    if ( $product && $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
        $classes[] = 'mh-free-product'; // کلاس مخصوص ما
    }
    return $classes;
}, 10, 3 );

// مخفی کردن فرم add to cart فقط برای محصولات رایگان
add_action('wp_head', function() {
    ?>
    <style>
        /* وقتی محصول رایگان بود فرم سبد خرید مخفی بشه */
        .single-product.mh-free-product .cart {
            display: none !important;
        }
    </style>
    <?php
});


// 1. اضافه کردن کلاس mh-free-product برای محصولات رایگان
add_filter( 'post_class', function( $classes, $class, $post_id ) {
    $product = wc_get_product( $post_id );
    if ( $product && $product->is_type( 'simple' ) && ( $product->get_price() == 0 || $product->get_price() === '' ) ) {
        $classes[] = 'mh-free-product';
    }
    return $classes;
}, 10, 3 );



}

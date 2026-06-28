<?php

function fifu_api_product_data($post_id) {
    $product = wc_get_product($post_id);

    if (!$product)
        return;

    $data = array();
    $data['post_id'] = $post_id;
    $data['title'] = $product->get_name();
    $data['description'] = $product->get_description();

    $cf = get_option('fifu_buy_cf');
    $data['cf'] = $cf ? get_post_meta($post_id, $cf, true) : null;

    $data['type'] = $product->get_type();
    $urls = fifu_lightbox_get_urls($product);
    $data['urls'] = $urls['images'];
    $data['video_srcs'] = $urls['videos'];
    $data['permalink'] = $product->get_permalink();
    $data['out_of_stock'] = __('Out of stock', 'woocommerce');
    $data['in_stock'] = __('In stock', 'woocommerce');
    $data['currency'] = get_woocommerce_currency_symbol();

    $button_text = $product->get_data()['button_text'] ?? null;
    $button_text = $button_text ? $button_text : get_option('fifu_buy_text');
    $button_text = $button_text ? $button_text : 'Buy now';
    $data['button_text'] = $button_text;

    $data['key_lightgallery'] = get_option('fifu_key_lightgallery');

    $disclaimer = get_option('fifu_buy_disclaimer');
    $disclaimer = $disclaimer ? '<div class="fifu-disclaimer">' . $disclaimer . '</div>' : '';

    $button = '<a id="fifu-add-to-cart-link"><div id="fifu-add-to-cart"><span style="top:7px;position:relative;padding-right:25px" class="dashicons dashicons-yes-alt"></span>' . __($button_text, 'woocommerce') . '</div></a>';

    if ($product->is_type('simple') || $product->is_type('external')) {
        // price
        $data['sale_price'] = $product->get_sale_price();
        $data['regular_price'] = $product->get_regular_price();
        $data['price'] = $data['sale_price'] ? wc_price($data['sale_price']) : wc_price($data['regular_price']);
        $data['stock_status'] = $product->get_data()['stock_status'] ?? '';
        $data['stock_quantity'] = $product->get_data()['stock_quantity'] ?? '';
        $data['product_url'] = $product->get_data()['product_url'] ?? null;

        $price = '
            <tr>
                <td class="label" style="width:30%">                    
                    <label for="fifu-price">' . __('Price', 'woocommerce') . '</label>
                </td>
                <td class="value" style="height:35px;width:70%">' .
                ($data['sale_price'] ? '<div style="color:#B12704;text-decoration:line-through;opacity:0.5;float:left">' . wc_price($data['regular_price']) . '</div>' : '') . '
                    <div style="color:#B12704;text-decoration:none;float:left">&nbsp;' . $data['price'] . '</div>' .
                ($data['stock_status'] == 'instock' ? '<div id="fifu-in-stock">' . $data['stock_quantity'] . ' ' . $data['in_stock'] . '</div>' : '<div id="fifu-out-of-stock">' . $data['out_of_stock'] . '</div>') . '
                </td>
            </tr>
        ';
        $table = '';
        $quantity = '
            <tr>
                <td class="label">
                    <label for="fifu-quantity">' . __('Quantity', 'woocommerce') . '</label>
                </td>
                <td class="value">
                    <input id="fifu-quantity" type="number" step="1" min="1" max="' . $data['stock_quantity'] . '" name="quantity" value="1" title="Qty" size="4" placeholder="" inputmode="numeric" style="width:100%;height:30px;background-color:#f1f1f1;float:left">
                </td>
            </tr>
        ';

        // no price
        if (!$data['regular_price']) {
            $price = '';
            $quantity = '';
        }

        $data['table'] = $button . $disclaimer . $price . $table . $quantity;
        return json_encode($data);
    } elseif ($product->is_type('variable')) {
        // price
        $data['min_sale_price'] = $product->get_variation_sale_price('min', true);
        $data['max_sale_price'] = $product->get_variation_sale_price('max', true);
        $data['min_regular_price'] = $product->get_variation_regular_price('min', true);
        $data['max_regular_price'] = $product->get_variation_regular_price('max', true);
        $data['price'] = wc_price($data['min_sale_price']) . ' – ' . wc_price($data['max_sale_price']);

        // variations
        $available_variations = $product->get_available_variations();
        $data['variations_html'] = htmlspecialchars(json_encode($available_variations));
        for ($i = 0; $i < sizeof($available_variations); $i++) {
            $available_variations[$i]['urls'] = fifu_lightbox_get_variation_urls($available_variations[$i]);
            // fix empty price
            $price_html = $available_variations[$i]['price_html'] ?? '';
            if (!$price_html)
                $available_variations[$i]['price_html'] = $product->get_price_html();
        }
        $data['variations_json'] = json_encode($available_variations);
        $data['unavailable'] = empty($product->get_available_variations()) ? '<div><span class="dashicons dashicons-dismiss"></span> ' . __('This product is currently out of stock and unavailable.', 'woocommerce') . '</div>' : '';
        $data['variations'] = array();

        // store available options
        $available = array();
        foreach ($available_variations as $i => $variation) {
            foreach (($variation['attributes'] ?? []) as $name => $val) {
                $available[$name] = $available[$name] ?? [];
                if (!in_array($val, $available[$name]) && $val)
                    array_push($available[$name], $val);
            }
            array_push($data['variations'], $variation['attributes'] ?? []);
        }
        $data['available'] = $available;

        $price = '
            <tr>
                <td class="label" style="width:30%">
                    <label for="fifu-price">' . __('Price', 'woocommerce') . '</label>
                </td>
                <td class="value" style="height:35px;width:70%">
                    <div id="fifu-price" style="color:#B12704;text-decoration:none">' . ($data['unavailable'] ? $data['unavailable'] : $data['price']) . '</div>
                </td>
            </tr>
        ';
        $table = '';
        foreach ($product->get_attributes() as $attribute_name => $options) {
            $option_value = '';
            $is_taxonomy = $options->get_data()['is_taxonomy'] ?? false;
            foreach (($options->get_data()['options'] ?? []) as $i => $val) {
                $atr_name = 'attribute_' . sanitize_title($options->get_data()['name'] ?? '');
                $value_slug = $is_taxonomy ? get_term($val)->slug : $val;
                $value_str = $is_taxonomy ? get_term($val)->name : $val;
                if (in_array($value_slug, $available[$atr_name] ?? []))
                    $option_value .= '<option value="' . $value_slug . '" class="attached enabled">' . $value_str . '</option>';
            }

            $name = sanitize_title($attribute_name);
            $atr_name = 'attribute_' . $name;
            $table .= '
                <tr>
                    <td class="label">
                        <label for="' . $name . '">' . wc_attribute_label($attribute_name != $name ? $attribute_name : ($options->get_data()['name'] ?? '')) . '</label>
                    </td>
                    <td class="value">
                        <select id="' . $name . '" class="" name="' . $atr_name . '" data-attribute_name="' . $atr_name . '" data-show_option_none="yes" style="width:100%;height:35px;font-size:13px">
                            <option value="">' . __('Choose an option', 'woocommerce') . '</option>' .
                    $option_value . '                        
                        </select>
                    </td>
                </tr>
            ';
        }
        $quantity = '
            <tr>
                <td class="label">
                    <label for="fifu-quantity">' . __('Quantity', 'woocommerce') . '</label>
                </td>
                <td class="value">
                    <input id="fifu-quantity" type="number" step="1" min="1" max="" name="quantity" value="1" title="Qty" size="4" placeholder="" inputmode="numeric" style="width:100%;height:30px;background-color:#f1f1f1">
                </td>
            </tr>
        ';
        $data['table'] = $button . $disclaimer . $price . $table . $quantity;
        return json_encode($data);
    }
}

function fifu_lightbox_get_urls($product) {
    $arr_image_url = array();
    $arr_video_src = array();
    $att_id = $product->get_image_id();
    if ($att_id)
        array_push($arr_image_url, fifu_get_full_image_url($att_id));
    foreach ($product->get_gallery_image_ids() as $att_id) {
        $url = fifu_get_full_image_url($att_id);
        array_push($arr_image_url, $url);

        if (fifu_is_video_thumb($url)) {
            $arr_video_src[$url] = fifu_video_src_by_img($url);
        }        
    }
    return [
        'images' => $arr_image_url,
        'videos' => $arr_video_src
    ];
}

function fifu_lightbox_get_variation_urls($variation) {
    $arr = array();
    $cpt = 'fifu_image_url';
    if (isset($variation[$cpt]) && $variation[$cpt])
        array_push($arr, $variation[$cpt]);
    else
        return $arr;

    $i = 0;
    while (true) {
        $cpt = 'fifu_image_url_' . $i++;
        if (isset($variation[$cpt]) && $variation[$cpt])
            array_push($arr, $variation[$cpt]);
        else
            return $arr;
    }
}


jQuery(document).ready(function ($) {
    jQuery('img[fifu-featured=1]:not(.fifu-video), img[fifulocal-featured=1]').each(function (index) {
        if (jQuery(this).attr('fifu-category'))
            return; // continue

        jQuery(this).attr('onclick', 'return false');
        jQuery(this).on('click', function () {
            let product_id = jQuery(this).attr('product-id');

            let opts = {
                toolbar: false,
                smallBtn: false,
                iframe: {
                    preload: false
                },
            }

            jQuery.fancybox.open(`
                <div style="width:100%;max-width:${$(window).height() * 0.75}px;height:${$(window).height() * 0.82}px">
                    <div id="fifu-lightbox-title" style="padding:5px;font-size:13px;font-weight:bold;text-align:center"></div>
                    <div style="width:100%;height:${$(window).height() * 0.5}px" id="inline-gallery-container" class="inline-gallery-container"></div>
					<div id="fifu-lightbox-form"></div>
                    <div id="fifu-lightbox-description"></div>
                    <div id="fifu-lightbox-cf"></div>
                </div>
            `, opts);

            fifuGetProductData(product_id);
        });

        jQuery(this).wrap('<div></div>');
        jQuery(this).after('<span class="dashicons dashicons-fullscreen-alt fifu-expand"></span>');
    });

    jQuery('.fifu-expand').on('mouseover', function () {
        jQuery(this).prev().click();
    });
});

function fifuGetProductData(product_id) {
    let data = window[`fifuLightboxVar${product_id}`];
    data = JSON.parse(data);
    fifu_post_id = data['post_id'];
    fifu_type = data['type'];
    let title = data['title'];
    fifu_price = data['price'];
    fifu_description = data['description'];
    fifu_cf = data['cf'];
    let permalink = data['permalink'];
    let product_url = data['product_url'];
    fifu_out_of_stock = data['out_of_stock'];
    fifu_in_stock = data['in_stock'];
    let table = data['table'];
    fifu_urls = data['urls'];
    let fifu_video_srcs = data['video_srcs'];
    fifu_chosen_attributes = null;
    fifu_variation_id = null;
    fifu_prev_variation_id = null;
    fifu_variation_has_urls = false;
    fifu_key_lightgallery = data['key_lightgallery'];

    let variations_html, fifu_variations_json, fifu_variations, fifu_available;
    if (['simple', 'external'].includes(fifu_type)) {
        variations_html = null;
        fifu_variations_json = null;
        fifu_variations = null;
        fifu_available = null;
    } else {
        variations_html = data['variations_html'];
        fifu_variations_json = JSON.parse(data['variations_json']);
        fifu_variations = data['variations'];
        fifu_available = data['available'];
    }

    fifu_get_gallery(fifu_urls, null, fifu_video_srcs);
    jQuery('#fifu-lightbox-title').append(title);

    if (fifu_description) {
        jQuery('#fifu-lightbox-description').append(fifu_description);
        jQuery('#fifu-lightbox-description').addClass('fifu-description');
    }

    if (fifu_cf) {
        jQuery('#fifu-lightbox-cf').append(fifu_cf);
        jQuery('#fifu-lightbox-cf').addClass('fifu-description');
    }

    let form = `
        <form class="variations_form cart" action="${permalink}" method="post" enctype="multipart/form-data" data-product_id="${product_id}" data-product_variations="${variations_html}">
            <table class="fifu-lightbox-table" cellspacing="0">
                <tbody>
                    ${table}
                </tbody>
            </table>
        </form>
    `;
    jQuery('#fifu-lightbox-form').append(form);

    if (fifu_type == 'simple')
        fifu_update_link(1);
    else if (fifu_type == 'external')
        jQuery('a#fifu-add-to-cart-link').attr('href', product_url);
    else
        jQuery('#fifu-add-to-cart').hide();
}

var fifu_post_id;
var fifu_type;
var fifu_price;
var fifu_variations;
var fifu_available;
var fifu_variations_json;
var fifu_chosen_attributes;
var fifu_description;
var fifu_cf;
var fifu_out_of_stock;
var fifu_in_stock;
var fifu_urls;
var fifu_variation_id;
var fifu_prev_variation_id;
var fifu_variation_has_urls;
var fifu_key_lightgallery;

jQuery('body').on('mousedown change', '#fifu-lightbox-form > form > table > tbody > tr > td > select', function () {
    // clicked dropdown
    let current_attribute_name = jQuery(this).attr('name');

    // available attribute-value (update after each interaction)
    let valid = new Map();

    // previously chosen attributes
    fifu_chosen_attributes = fifu_get_chosen_attributes();

    // for each variation of the product...
    for (let i = 0; i < fifu_variations.length; i++) {
        let is_valid = true;
        // for each already chosen attribute...
        for (const [attribute_name, selected_option] of Object.entries(fifu_chosen_attributes)) {
            // if the variation in loop has a different attribute 
            // and the variation attribute isn't empty (it would support any attribute)
            // and the variation attribute is not related to the clicked dropdown
            // then the variation is invalid and their values won't be added to the attribue-value map
            if (fifu_variations[i][attribute_name] != selected_option && fifu_variations[i][attribute_name] != "" && current_attribute_name != attribute_name) {
                is_valid = false;
                break;
            }
        }
        // go to the next variation
        if (!is_valid)
            continue;

        // for each pair attribute-value of the variation
        for (const [key, value] of Object.entries(fifu_variations[i])) {
            // create a set of values
            if (!valid[key])
                valid[key] = new Set();
            // if there is a specific value, so only that goes to the map
            if (value != "") {
                valid[key].add(value);
            } else {
                // else all the values go to the map
                for (let j = 0; j < fifu_available[key].length; j++)
                    valid[key].add(fifu_available[key][j]);
            }
        }
    }

    // for each available attribute-value...
    for (const [key, value] of Object.entries(valid)) {
        // for each dropdown...
        jQuery('select[name=' + key + '] > option').each(function (index) {
            // if the option has a value (it's not the default one)
            // and the map doen't contain that
            // then the option is disabled
            if (jQuery(this).val() && !value.has(jQuery(this).val())) {
                jQuery(this).attr('disabled', '');
                jQuery(this).addClass('disabled');
                jQuery(this).removeClass('enabled');
            } else {
                // else the option is enable
                jQuery(this).removeAttr('disabled');
                jQuery(this).addClass('enabled');
                jQuery(this).removeClass('disabled');
            }
        });
    }

    if (fifu_should_load_variation_data()) {
        let variation = fifu_get_selected_variation();
        jQuery('#fifu-price').html('<p style="font-size:13px;float:left">' + variation['price_html'] + '</p>' + (variation['is_in_stock'] ? '<p id="fifu-in-stock">' + variation['max_qty'] + ' ' + fifu_in_stock + '</p>' : '<p id="fifu-out-of-stock">' + fifu_out_of_stock + '</p>'));
        jQuery('#fifu-lightbox-description').html(variation['variation_description']);
        jQuery('#fifu-quantity').attr('max', variation['max_qty']);

        fifu_prev_variation_id = fifu_variation_id;
        fifu_variation_id = variation['variation_id'];
        fifu_variation_has_urls = variation['urls'].length > 0;
        fifu_update_link(jQuery('input#fifu-quantity').val());
        variation['is_in_stock'] ? jQuery('#fifu-add-to-cart').show() : jQuery('#fifu-add-to-cart').hide();

        // there was change
        if (fifu_prev_variation_id != fifu_variation_id) {
            if (fifu_variation_has_urls) {
                jQuery('#inline-gallery-container').html('');
                fifu_get_gallery(variation['urls'], fifu_urls, null);
            } else {
                if (fifu_prev_variation_id != null) {
                    jQuery('#inline-gallery-container').html('');
                    fifu_get_gallery(fifu_urls, null, null);
                }
            }
        }
    } else {
        jQuery('#fifu-price').html(fifu_price);

        if (fifu_description) {
            jQuery('#fifu-lightbox-description').html(fifu_description);
            jQuery('#fifu-lightbox-description').addClass('fifu-description');
        }

        if (fifu_cf) {
            jQuery('#fifu-lightbox-cf').html(fifu_cf);
            jQuery('#fifu-lightbox-cf').addClass('fifu-description');
        }

        fifu_prev_variation_id = fifu_variation_id;
        fifu_variation_id = null;
        jQuery('#fifu-add-to-cart').hide();

        // there was change
        if (fifu_prev_variation_id != fifu_variation_id) {
            if (fifu_variation_has_urls) {
                jQuery('#inline-gallery-container').html('');
                fifu_get_gallery(fifu_urls, null, null);
            }
        }
    }
});

function fifu_get_chosen_attributes() {
    let map = new Map();
    jQuery('#fifu-lightbox-form > form > table > tbody > tr > td > select > option:selected').each(function (index) {
        if (jQuery(this).val())
            map[jQuery(this).parent().attr('name')] = jQuery(this).val();
    });
    return map;
}

function fifu_should_load_variation_data() {
    let complete = true;
    jQuery('#fifu-lightbox-form > form > table > tbody > tr > td > select > option:selected').each(function (index) {
        if (!jQuery(this).val()) {
            complete = false;
            return;
        }
    });
    return complete;
}

function fifu_get_selected_variation() {
    // for each variation of the product...
    for (let i = 0; i < fifu_variations_json.length; i++) {
        let variation_attributes = fifu_variations_json[i]['attributes'];
        let found = true;
        // for each chosen attribute...
        for (const [attribute_name, selected_option] of Object.entries(fifu_chosen_attributes)) {
            let value = variation_attributes[attribute_name];
            if (value && selected_option != value) {
                found = false;
                break;
            }
        }
        if (found)
            return fifu_variations_json[i];
    }
    return null;
}

function fifu_get_gallery(urls, extra_urls, fifu_video_srcs) {
    let arr = [];
    for (let i = 0; i < urls.length; i++) {
        let url = urls[i];
        let video_src = fifu_video_srcs ? fifu_video_srcs[url] : null;
        if (video_src) {
            // Add video object for lightGallery
            arr.push({
                src: video_src,
                thumb: url,
                poster: url, // optional: use image as poster
                video: {
                    source: [
                        {src: video_src, type: 'video/mp4'}
                    ],
                    attributes: {
                        preload: false,
                        controls: true,
                        autoplay: false
                    }
                }
            });
        } else {
            // Add image object
            arr.push({'src': url, 'responsive': url, 'thumb': url});
        }
    }

    const $lgContainer = document.getElementById("inline-gallery-container");

    const inlineGallery = lightGallery($lgContainer, {
        container: $lgContainer,
        dynamic: true,
        hash: false,
        closable: false,
        showMaximizeIcon: true,
        appendSubHtmlTo: ".lg-item",
        slideDelay: 400,
        plugins: [lgThumbnail, lgZoom, lgVideo],
        download: false,
        zoom: false,
        counter: false,
        dynamicEl: arr,
        thumbWidth: 60,
        thumbHeight: "40px",
        thumbMargin: 10,
        licenseKey: fifu_key_lightgallery ? fifu_key_lightgallery : ((typeof fifuLgVars !== 'undefined') ? fifuLgVars.fifu_key_lightgallery : null),
    });

    inlineGallery.openGallery();
}

jQuery('body').on('change input', 'input#fifu-quantity', function () {
    let quantity = jQuery(this).val();
    fifu_update_link(quantity);
});

function fifu_update_link(quantity) {
    let id = fifu_variation_id ? fifu_variation_id : fifu_post_id;
    if (fifu_type == 'simple' || (fifu_type == 'variable' && fifu_variation_id))
        jQuery('a#fifu-add-to-cart-link').attr('href', '/checkout/?add-to-cart=' + id + '&quantity=' + quantity);
}

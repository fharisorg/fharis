var isDraggingSlider = false;

// add meta box icon
jQuery(document).ready(function () {
    let text = jQuery("div#wooSliderImageUrlMetaBox").find('h2').text();
    jQuery("div#wooSliderImageUrlMetaBox").find('h2.hndle').text('');
    jQuery("div#wooSliderImageUrlMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-images-alt2"></span> ' + text + '</h4>');
    jQuery("div#wooSliderImageUrlMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#wooSliderImageUrlMetaBox").find('button.handle-order-lower').remove();

    text = jQuery("div#sliderImageUrlMetaBox").find('h2').text();
    jQuery("div#sliderImageUrlMetaBox").find('h2.hndle').text('');
    jQuery("div#sliderImageUrlMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-images-alt2"></span> ' + text + '</h4>');
    jQuery("div#sliderImageUrlMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#sliderImageUrlMetaBox").find('button.handle-order-lower').remove();

    if (fifuSliderVars.is_product)
        jQuery('#gridDemoSlider').attr('style', 'position:relative;left:13px');
});

// sizes
function fifu_slider_get_sizes(i) {
    slider_url = jQuery('input[id^=fifu_slider_input_url_' + i + ']').val();
    fifu_slider_get_image(slider_url, i);
}

function fifu_slider_get_image(url, i) {
    var image = new Image();
    jQuery(image).attr('onload', 'fifu_slider_store_sizes(this,' + i + ');');
    jQuery(image).attr('src', url);
}

function fifu_slider_store_sizes($, i) {
    jQuery("#fifu_slider_input_width_" + i).val($.naturalWidth);
    jQuery("#fifu_slider_input_height_" + i).val($.naturalHeight);
}

// video sizes

async function fifu_slider_video_get_sizes(i) {
    let video_url = jQuery('input[id^=fifu_slider_input_url_' + i + ']').val();
    if (!video_url || (!video_url.startsWith("http") && !video_url.startsWith("//")))
        return;
    let image_url = await fifu_video_image_thumbnail(video_url, fifuSliderVars);
    fifu_slider_video_get_image(image_url, i);
}

function fifu_slider_video_get_image(url, i) {
    var image = new Image();
    jQuery(image).attr('onload', 'fifu_slider_video_store_sizes(this,' + i + ');');
    jQuery(image).attr('src', url);
}

function fifu_slider_video_store_sizes($, i) {
    let src = $.src;
    jQuery("#fifu_slider_input_width_" + i).val($.naturalWidth);
    jQuery("#fifu_slider_input_height_" + i).val($.naturalHeight);
    if ($.naturalWidth == 120 && $.naturalHeight == 90)
        jQuery("#fifu_slider_input_image_src_" + i).val(src.replace('maxresdefault', 'mqdefault'));
    else
        jQuery("#fifu_slider_input_image_src_" + i).val(src);

    // load thumbnail gallery
    if (!src) {
        src = jQuery("#fifu_slider_input_image_src_" + i).val();
    }
    selector = `#fifu-slider-${i}`;
    jQuery(selector).css('background', `url("${src}") center center / cover no-repeat`);
    jQuery(selector).css('opacity', '1');
    jQuery(`#fifu_slider_input_url_${i}`).val(function (index, currentContent) {
        currentContent = currentContent.split('#http')[0];
        return `${currentContent}#${src}`;
    });
}

var maxSlider = 0;

// run once
jQuery(document).ready(function () {
    fifu_slider_box_init();
});

function fifu_slider_box_init() {
    const MIN = 3;

    // quick edit
    if (currentLightbox)
        fifu_slider_info(currentLightbox);

    numberUrls = fifuSliderVars.urls ? fifuSliderVars.urls.length : 0;
    numberInputs = numberUrls <= MIN ? MIN : numberUrls;

    // add placeholders
    for (i = 0; i < numberInputs; i++) {
        jQuery('#gridDemoSlider').append(`<div id="fifu-slider-${i}" class="grid-square image"></div>`);
        maxSlider = i;
    }

    // add plus button
    jQuery('#gridDemoSlider').append(`<div id="fifu-add-slider" class="grid-square image-add"></div>`);

    // add images
    for (i = 0; i < numberInputs; i++) {
        url = fifuSliderVars.urls[i];
        alt = fifuSliderVars.alts[i];

        image_url = '';
        video_url = '';
        if (url !== undefined) {
            pos = url.indexOf('#http');
            if (pos !== -1) {
                image_url = url.substring(pos + 1);
                video_url = url.substring(0, pos);
            } else
                image_url = url;
        }

        alt = alt !== undefined ? alt : "";

        if (image_url) {
            selector = `#fifu-slider-${i}`;
            jQuery(selector).css('background', `url("${image_url}") center center / cover no-repeat`);
            jQuery(selector).css('opacity', '1');
        }

        // add input hiddens
        jQuery('#inputHiddenSliders').append(`
            <input type="hidden" id="fifu_slider_input_width_${i}" name="fifu_slider_input_width_${i}" value="" >
            <input type="hidden" id="fifu_slider_input_height_${i}" name="fifu_slider_input_height_${i}" value="" >
            <input type="hidden" id="fifu_slider_input_url_${i}" name="fifu_slider_input_url_${i}" value="${url}">
            <input type="hidden" id="fifu_slider_input_alt_${i}" name="fifu_slider_input_alt_${i}" value="${alt}">
        `);

        // get sizes
        if (url) {
            fifu_slider_get_sizes(i);
        }
    }

    // start lists
    updateSliderList();

    /////////////////////////////////////////////////

    // init sortable
    if (jQuery('#gridDemoSlider').length) {
        jQuery('#gridDemoSlider').sortable({
            start: function (event, ui) {
                isDraggingSlider = false; // Reset the flag
            },
            stop: function (event, ui) {
                isDraggingSlider = true; // Set the flag to indicate dragging occurred
            }
        });
    }

    // prepare fancy boxes 
    addFancyBoxSlider();

    // add new image: onclick event
    jQuery(document).on('click', '#fifu-add-slider', function (evt) {
        if (isDraggingSlider) {
            isDraggingSlider = false;
            return;
        }

        evt.stopImmediatePropagation();
        maxSlider++;
        jQuery('#gridDemoSlider').append(`<div id="fifu-slider-${maxSlider}" class="grid-square image"></div>`);

        jQuery('#inputHiddenSliders').append(`
            <input type="hidden" id="fifu_slider_input_width_${maxSlider}" name="fifu_slider_input_width_${maxSlider}" value="" >
            <input type="hidden" id="fifu_slider_input_height_${maxSlider}" name="fifu_slider_input_height_${maxSlider}" value="" >
            <input type="hidden" id="fifu_slider_input_url_${maxSlider}" name="fifu_slider_input_url_${maxSlider}" value="">
            <input type="hidden" id="fifu_slider_input_alt_${maxSlider}" name="fifu_slider_input_alt_${maxSlider}" value="">
        `);
        addFancyBoxSlider();
    });

    jQuery('div.grid-square').on('mouseout', function (evt) {
        evt.stopImmediatePropagation();
        updateSliderList();
    });

    fifu_check_slider_grid_images();
}

// prepare fancy boxes
function addFancyBoxSlider() {
    jQuery(document).on('click', 'div[id^="fifu-slider-"]', function (evt) {
        if (isDraggingSlider) {
            isDraggingSlider = false;
            return;
        }

        evt.stopImmediatePropagation();
        let divId = jQuery(this).attr('id');
        let index = divId.split('-')[2];

        let url = jQuery(`#fifu_slider_input_url_${index}`).val();
        url = (!url || url === 'undefined') ? '' : url;
        let alt = jQuery(`#fifu_slider_input_alt_${index}`).val();
        alt = (!alt || alt === 'undefined') ? '' : alt;

        let imgTag = '';
        let iframeTag = '';
        if (url) {
            if (fifu_is_video(url)) {
                iframeTag = `<iframe id="iframe-fifu-slider" width="100%" src="${srcVideo(url)}" allowfullscreen frameborder="0" style="width:275px;margin-top:5px;margin-left:1px"></iframe><br>`;
            } else {
                imgTag = `<img loading="lazy" id="img-fifu-slider" src="${url}" style="width:275px;margin-top:5px;margin-left:1px"
                    onload="fifuToggleAltField('${divId}')"
                    onerror="this.onerror=null;this.src='${FIFU_IMAGE_NOT_FOUND_URL}'; setTimeout(function(){fifuToggleAltField('${divId}')}, 10);"
                ><br>`;
            }
        }

        jQuery.fancybox.open(`
            <input id="input-${divId}" placeholder="${fifuSliderVars.text_image_video_url}" value="${url}" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>
            <span id="span-img-fifu-slider">${imgTag}</span>
            <span id="span-iframe-fifu-slider">${iframeTag}</span>
            <span id="alt-span-${divId}" style="display:none;">
                <input id="alt-input-${divId}" placeholder="${fifuSliderVars.text_alt}" value="${alt}" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>
            </span>
            <button id="button-fifu-slider" type="button" style="width:275px;padding:10px;height:36px;margin-bottom:3px;">${fifuSliderVars.text_ok}</button>
        `);
        jQuery(`#input-${divId}`).focus();
        jQuery(`#input-${divId}`).select();
    });
}

// change URL (single field for image or video)
jQuery(document).on('keyup', 'input[id^="input-fifu-slider"]', async function (evt) {
    evt.stopImmediatePropagation();

    let inputId = jQuery(this).attr('id');
    let divId = inputId.replace('input-', '');
    let index = divId.split('-')[2];

    let url = jQuery(`#${inputId}`).val();
    url = url && url.startsWith('http') ? url : '';

    // Only update if value actually changed, or Enter/Esc is pressed
    let prevUrl = jQuery(this).data('prev-url');
    if (url === prevUrl && evt.which !== 13 && evt.which !== 27) {
        return;
    }
    jQuery(this).data('prev-url', url);

    // Always update the hidden input
    jQuery(`#fifu_slider_input_url_${index}`).val(url);

    // Clear previews
    jQuery(`#span-img-fifu-slider`).empty();
    jQuery(`#span-iframe-fifu-slider`).empty();

    if (url) {
        if (fifu_is_video(url)) {
            url = fifu_convert_video(url);
            // Show video preview
            jQuery(`#span-iframe-fifu-slider`).append(
                    `<iframe id="iframe-fifu-slider" width="100%" src="${srcVideo(url)}" allowfullscreen frameborder="0" style="width:275px;margin-top:5px;margin-left:1px"></iframe><br>`
                    );
            await fifu_slider_video_get_sizes(index);
        } else {
            url = fifu_convert(url);
            // Show image preview
            jQuery(`#span-img-fifu-slider`).append(
                    `<img loading="lazy" id="img-fifu-slider" src="${url}" style="width:275px;margin-top:5px;margin-left:1px"
                    onload="fifuToggleAltField('${divId}')"
                    onerror="this.onerror=null;this.src='${FIFU_IMAGE_NOT_FOUND_URL}'; setTimeout(function(){fifuToggleAltField('${divId}')}, 10);"><br>`
                    );
            let img = new window.Image();
            img.onload = function () {
                jQuery(`#${divId}`).css('background', `url("${url}") center center / cover no-repeat`);
                jQuery(`#${divId}`).css('opacity', '1');
                fifu_slider_get_sizes(index);
            };
            img.onerror = function () {
                jQuery(`#${divId}`).css('background', `url("${FIFU_IMAGE_NOT_FOUND_URL}") center center / cover no-repeat`);
                jQuery(`#${divId}`).css('opacity', '1');
            };
            img.src = url;
        }
    } else {
        // If the field is empty, show nothing and clear alt value
        jQuery(`#span-img-fifu-slider`).empty();
        jQuery(`#alt-span-${divId}`).hide();
        jQuery(`#alt-input-${divId}`).val('');
        jQuery(`#fifu_slider_input_alt_${index}`).val('');
        jQuery(`#${divId}`).attr('style', '');
    }

    updateSliderList();

    if (evt.which === 13 || evt.which === 27)
        jQuery.fancybox.close();
});

// Helper function to toggle alt field based on image src
function fifuToggleAltField(divId) {
    var img = document.getElementById('img-fifu-slider');
    // Always hide alt if image URL field is empty
    let url = jQuery(`#input-${divId}`).val();
    if (!url) {
        jQuery(`#alt-span-${divId}`).hide();
        return;
    }
    if (!img) {
        jQuery(`#alt-span-${divId}`).hide();
        return;
    }
    // Only show alt if not the "not found" image and src is not empty
    if (img.src && (!window.FIFU_IMAGE_NOT_FOUND_URL || img.src !== FIFU_IMAGE_NOT_FOUND_URL)) {
        // If the image src ends with the not found image, hide alt
        if (window.FIFU_IMAGE_NOT_FOUND_URL && img.src.indexOf(FIFU_IMAGE_NOT_FOUND_URL) !== -1) {
            jQuery(`#alt-span-${divId}`).hide();
        } else {
            jQuery(`#alt-span-${divId}`).show();
        }
    } else {
        jQuery(`#alt-span-${divId}`).hide();
    }
}

// change ALT
jQuery(document).on('keyup', 'input[id^="alt-input-fifu"]', function (evt) {
    evt.stopImmediatePropagation();
    let inputId = jQuery(this).attr('id');
    let divId = inputId.replace('alt-input-', '');
    let index = divId.split('-')[2];

    let alt = jQuery(`#${inputId}`).val();
    jQuery(`#fifu_slider_input_alt_${index}`).val(alt);

    updateSliderList();

    if (evt.which === 13 || evt.which === 27)
        jQuery.fancybox.close();
});

// OK button
jQuery(document).on('click', '#button-fifu-slider', function (evt) {
    evt.stopImmediatePropagation();
    updateSliderList();
    jQuery.fancybox.close();
});

// update the list of urls
function updateSliderList() {
    let sliderListIds = "";
    let i = 0;
    jQuery('div[id^="fifu-slider"]').each(function (index) {
        let divId = jQuery(this).attr('id');
        let idx = divId.split('-')[2];
        let url = jQuery(`#fifu_slider_input_url_${idx}`).val();
        if (url && url.startsWith('http')) {
            sliderListIds += (i == 0) ? '' : '|';
            sliderListIds += idx;
            i++;
        }
    });
    jQuery('#inputHiddenSliderListIds').val(sliderListIds);
    jQuery('#inputHiddenSliderLength').val(jQuery('div[id^="fifu-slider"]').length);

    fifu_check_slider_grid_images();

    fifu_update_wp_image_and_gallery_by_slider();
}

// quick edit

function fifu_slider_info(post_id) {
    fifuSliderVars.urls = fifuQuickEditVars.posts[post_id]['fifu_slider_image_urls'];
    fifuSliderVars.alts = fifuQuickEditVars.posts[post_id]['fifu_slider_image_alts'];
}

function fifu_check_slider_grid_images() {
    jQuery('div.grid-square').each(function () {
        let $div = jQuery(this);
        let bg = $div.css('background');
        let match = bg && bg.match(/url\(["']?([^"')]+)["']?\)/);
        let imageUrl = match ? match[1] : null;

        // If the slot is empty or undefined, clear the background and opacity
        if (!imageUrl || imageUrl === 'undefined' || imageUrl === '') {
            $div.css('background', '');
            $div.css('opacity', '');
            return;
        }

        // Ignore local images (relative or root-relative paths)
        if (
                imageUrl.startsWith('/') ||
                imageUrl.startsWith('./') ||
                imageUrl.startsWith('../')
                ) {
            return;
        }

        // If the slot already has the not found image, just set opacity
        if (window.FIFU_IMAGE_NOT_FOUND_URL && imageUrl === window.FIFU_IMAGE_NOT_FOUND_URL) {
            $div.css('opacity', '1');
            return;
        }

        // Only check and possibly replace if it's a real URL
        let img = new Image();
        img.onload = function () {
            // Image loaded successfully, ensure opacity is set
            $div.css('opacity', '1');
        };
        img.onerror = function () {
            // Image failed to load, set to not found image
            if (window.FIFU_IMAGE_NOT_FOUND_URL) {
                $div.css('background', 'url("' + window.FIFU_IMAGE_NOT_FOUND_URL + '") center center / cover no-repeat');
                $div.css('opacity', '1');
            } else {
                $div.css('background', '');
                $div.css('opacity', '');
            }
        };
        img.src = imageUrl;
    });
}

function fifu_update_wp_image_and_gallery_by_slider() {
    var $postImageDiv = jQuery('#postimagediv');
    var $postImageToggle = $postImageDiv.find('.handlediv');
    var $galleryBox = jQuery('#woocommerce-product-images');
    var $galleryToggle = $galleryBox.find('.handlediv');
    var $urlMetaBox = jQuery('#urlMetaBox');
    var $urlMetaToggle = $urlMetaBox.find('.handlediv');
    var $wooGalleryMetaBox = jQuery('#wooGalleryMetaBox');
    var $wooGalleryToggle = $wooGalleryMetaBox.find('.handlediv');
    var $wooVideoUrlMetaBox = jQuery('#wooVideoUrlMetaBox');
    var $wooVideoUrlToggle = $wooVideoUrlMetaBox.find('.handlediv');
    var $wooCommerceVideoGalleryMetaBox = jQuery('#wooCommerceVideoGalleryMetaBox');
    var $wooCommerceVideoGalleryToggle = $wooCommerceVideoGalleryMetaBox.find('.handlediv');
    var $audioMetaBox = jQuery('#audioMetaBox');
    var $audioMetaToggle = $audioMetaBox.find('.handlediv');

    // Check if any slider hidden input has a real URL
    var hasSliderImage = jQuery('input[id^="fifu_slider_input_url_"]').filter(function () {
        var url = jQuery(this).val();
        return url && url !== 'undefined' && url.startsWith('http');
    }).length > 0;

    if (hasSliderImage) {
        $postImageDiv.addClass('closed');
        $postImageToggle.attr('aria-expanded', 'false').prop('disabled', true);
        $galleryBox.addClass('closed');
        $galleryToggle.attr('aria-expanded', 'false').prop('disabled', true);
        $urlMetaBox.addClass('closed');
        $urlMetaToggle.attr('aria-expanded', 'false').prop('disabled', true);
        $wooGalleryMetaBox.addClass('closed');
        $wooGalleryToggle.attr('aria-expanded', 'false').prop('disabled', true);
        $wooVideoUrlMetaBox.addClass('closed');
        $wooVideoUrlToggle.attr('aria-expanded', 'false').prop('disabled', true);
        $wooCommerceVideoGalleryMetaBox.addClass('closed');
        $wooCommerceVideoGalleryToggle.attr('aria-expanded', 'false').prop('disabled', true);
        $audioMetaBox.addClass('closed');
        $audioMetaToggle.attr('aria-expanded', 'false').prop('disabled', true);
    } else {
        $postImageDiv.removeClass('closed');
        $postImageToggle.attr('aria-expanded', 'true').prop('disabled', false);
        $galleryBox.removeClass('closed');
        $galleryToggle.attr('aria-expanded', 'true').prop('disabled', false);
        $urlMetaBox.removeClass('closed');
        $urlMetaToggle.attr('aria-expanded', 'true').prop('disabled', false);
        $wooGalleryMetaBox.removeClass('closed');
        $wooGalleryToggle.attr('aria-expanded', 'true').prop('disabled', false);
        $wooVideoUrlMetaBox.removeClass('closed');
        $wooVideoUrlToggle.attr('aria-expanded', 'true').prop('disabled', false);
        $wooCommerceVideoGalleryMetaBox.removeClass('closed');
        $wooCommerceVideoGalleryToggle.attr('aria-expanded', 'true').prop('disabled', false);
        $audioMetaBox.removeClass('closed');
        $audioMetaToggle.attr('aria-expanded', 'true').prop('disabled', false);
    }
}

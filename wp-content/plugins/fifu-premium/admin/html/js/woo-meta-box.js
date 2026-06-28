var isDraggingImage = false;

// sizes
function fifu_woo_get_sizes(i) {
    let url = jQuery('input[id^=fifu_input_url_' + i + ']').val();
    if (!url || !url.startsWith("http"))
        return;
    fifu_woo_get_image(url, i);
}

function fifu_woo_get_image(url, i) {
    var image = new Image();
    jQuery(image).attr('onload', 'fifu_woo_store_sizes(this,' + i + ');');
    jQuery(image).attr('src', url);
}

function fifu_woo_store_sizes($, i) {
    jQuery("#fifu_input_width_" + i).val($.naturalWidth);
    jQuery("#fifu_input_height_" + i).val($.naturalHeight);
}

var maxImage = 0;

// run once
jQuery(document).ready(function () {
    fifu_box_init();
});

function fifu_box_init() {
    let text = jQuery("div#wooGalleryMetaBox").find('h2').text();
    jQuery("div#wooGalleryMetaBox").find('h2.hndle').text('');
    jQuery("div#wooGalleryMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-format-gallery"></span> ' + text + '</h4>');
    jQuery("div#wooGalleryMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#wooGalleryMetaBox").find('button.handle-order-lower').remove();

    const MIN = 3;

    // quick edit
    if (currentLightbox)
        fifu_image_gallery_info(currentLightbox);

    let numberUrls = fifuBoxImageVars.urls ? fifuBoxImageVars.urls.length : 0;
    let numberInputs = numberUrls <= MIN ? MIN : numberUrls;

    // add placeholders
    for (let i = 0; i < numberInputs; i++) {
        jQuery('#gridDemoImage').append(`<div id="fifu-image-${i}" class="grid-square image"></div>`);
        maxImage = i;
    }

    // add plus button
    jQuery('#gridDemoImage').append(`<div id="fifu-add-image" class="grid-square image-add"></div>`);

    // add images
    for (let i = 0; i < numberInputs; i++) {
        let url = fifuBoxImageVars.urls[i];
        let alt = fifuBoxImageVars.alts[i];
        let ifm = fifuBoxImageVars.ifms[i];

        url = url !== undefined ? url : "";
        alt = alt !== undefined ? alt : "";
        ifm = ifm !== undefined ? ifm : "";

        if (url) {
            let selector = `#fifu-image-${i}`;

            let adjustedUrl = fifu_cdn_adjust(url);

            jQuery(selector).css('background', `url("${adjustedUrl}") center center / cover no-repeat`);
            jQuery(selector).css('opacity', '1');
        }

        // add input hiddens
        jQuery('#inputHiddenImages').append(`
            <input type="hidden" id="fifu_input_width_${i}" name="fifu_input_width_${i}" value="" >
            <input type="hidden" id="fifu_input_height_${i}" name="fifu_input_height_${i}" value="" >
            <input type="hidden" id="fifu_input_url_${i}" name="fifu_input_url_${i}" value="${url}">
            <input type="hidden" id="fifu_input_alt_${i}" name="fifu_input_alt_${i}" value="${alt}">
            <input type="hidden" id="fifu_input_ifm_${i}" name="fifu_input_ifm_${i}" value="${ifm}">
        `);

        // get sizes
        if (url)
            fifu_woo_get_sizes(i);
    }

    // start lists
    updateImageList();

    /////////////////////////////////////////////////

    // init sortable
    if (jQuery('#gridDemoImage').length) {
        jQuery('#gridDemoImage').sortable({
            start: function (event, ui) {
                isDraggingImage = false; // Reset the flag
            },
            stop: function (event, ui) {
                isDraggingImage = true; // Set the flag to indicate dragging occurred
            }
        });
    }

    // prepare fancy boxes 
    addFancyBoxImage();

    // add new image: onclick event
    jQuery(document).on('click', '#fifu-add-image', function (evt) {
        if (isDraggingImage) {
            isDraggingImage = false;
            return;
        }

        evt.stopImmediatePropagation();
        maxImage++;
        jQuery('#gridDemoImage').append(`<div id="fifu-image-${maxImage}" class="grid-square image"></div>`);

        jQuery('#inputHiddenImages').append(`
            <input type="hidden" id="fifu_input_width_${maxImage}" name="fifu_input_width_${maxImage}" value="" >
            <input type="hidden" id="fifu_input_height_${maxImage}" name="fifu_input_height_${maxImage}" value="" >
            <input type="hidden" id="fifu_input_url_${maxImage}" name="fifu_input_url_${maxImage}" value="">
            <input type="hidden" id="fifu_input_alt_${maxImage}" name="fifu_input_alt_${maxImage}" value="">
            <input type="hidden" id="fifu_input_ifm_${maxImage}" name="fifu_input_ifm_${maxImage}" value="">
        `);
        addFancyBoxImage();
    });

    jQuery('div.grid-square').on('mouseout', function (evt) {
        evt.stopImmediatePropagation();
        updateImageList();
    });
}

// prepare fancy boxes
function addFancyBoxImage() {
    jQuery(document).on('click', 'div[id^="fifu-image-"]', function (evt) {
        if (isDraggingImage) {
            isDraggingImage = false;
            return;
        }

        evt.stopImmediatePropagation();
        let divId = jQuery(this).attr('id');
        let index = divId.split('-')[2];

        let url = jQuery(`#fifu_input_url_${index}`).val();
        let alt = jQuery(`#fifu_input_alt_${index}`).val();
        let ifm = jQuery(`#fifu_input_ifm_${index}`).val();
        url = url ? url : "";
        alt = alt ? alt : "";
        ifm = ifm ? ifm : "";

        let adjustedUrl = fifu_cdn_adjust(url);

        // Always hide alt/ifm fields initially; show only if image loads
        let altIfmFields = `
            <span id="alt-ifm-fields-${divId}" style="display:none;">
                <input id="alt-input-image-${divId}" placeholder="${fifuBoxImageVars.text_alt}" value="${alt}" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>
                <input id="ifm-input-image-${divId}" placeholder="${fifuBoxImageVars.text_ifm}" value="${ifm}" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>
            </span>
        `;

        // Add onerror handler to show not found image if loading fails
        let imgTag = url
                ? `<img loading="lazy" id="img-fifu-image" src="${adjustedUrl}" style="width:275px;margin-top:5px;margin-left:1px"
                onload="fifuShowAltIfmFields('${divId}')"
                onerror="this.onerror=null;this.src='${window.FIFU_IMAGE_NOT_FOUND_URL}'; fifuHideAltIfmFields('${divId}');"><br>`
                : '';

        jQuery.fancybox.open(`
            <input id="input-${divId}" placeholder="${fifuBoxImageVars.text_url}" value="${url}" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>
            <span id="span-img-fifu-image">
            ${imgTag}
            </span>
            ${altIfmFields}
            <button id="button-fifu-image" type="button" style="width:275px;padding:10px;height:36px;margin-bottom:3px;">${fifuBoxImageVars.text_ok}</button>
        `);
        jQuery(`#input-${divId}`).focus();
        jQuery(`#input-${divId}`).select();
        // Hide alt/ifm fields if url is empty
        if (!url)
            fifuHideAltIfmFields(divId);
    });
}

// Helper functions to show/hide alt/ifm fields
window.fifuShowAltIfmFields = function (divId) {
    // Only show if the image is not the not-found image
    let img = document.getElementById('img-fifu-image');
    if (img && (!window.FIFU_IMAGE_NOT_FOUND_URL || img.src.indexOf(window.FIFU_IMAGE_NOT_FOUND_URL) === -1)) {
        jQuery(`#alt-ifm-fields-${divId}`).show();
    } else {
        jQuery(`#alt-ifm-fields-${divId}`).hide();
    }
};
window.fifuHideAltIfmFields = function (divId) {
    jQuery(`#alt-ifm-fields-${divId}`).hide();
};

// change URL
jQuery(document).on('keyup', 'input[id^="input-fifu-image"]', function (evt) {
    evt.stopImmediatePropagation();

    let inputId = jQuery(this).attr('id');
    let divId = inputId.replace('input-', '');
    let index = divId.split('-')[2];

    let url = jQuery(`#${inputId}`).val();
    url = !url.startsWith('http') ? '' : fifu_convert(url);

    // Only update if value actually changed, or Enter/Esc is pressed
    let prevUrl = jQuery(this).data('prev-url');
    if (url === prevUrl && evt.which !== 13 && evt.which !== 27) {
        return;
    }
    jQuery(this).data('prev-url', url);

    jQuery(`#fifu_input_url_${index}`).val(url);

    jQuery(`#span-img-fifu-image`).empty();

    let adjustedUrl = fifu_cdn_adjust(url);

    // Add onerror handler to show not found image if loading fails
    jQuery(`#span-img-fifu-image`).append(
            url
            ? `<img loading="lazy" id="img-fifu-image" src="${adjustedUrl}" style="width:275px;margin-top:5px;margin-left:1px"
                onload="fifuShowAltIfmFields('${divId}')"
                onerror="this.onerror=null;this.src='${window.FIFU_IMAGE_NOT_FOUND_URL}'; fifuHideAltIfmFields('${divId}');"><br>`
            : ''
            );

    // Hide alt/ifm fields if url is empty
    if (!url) {
        jQuery(`#${divId}`).attr('style', '');
        // Clear alt and ifm fields (both hidden and visible)
        jQuery(`#fifu_input_alt_${index}`).val('');
        jQuery(`#fifu_input_ifm_${index}`).val('');
        jQuery(`#alt-input-image-${divId}`).val('');
        jQuery(`#ifm-input-image-${divId}`).val('');
    } else {
        let adjustedUrl = fifu_cdn_adjust(url);
        jQuery(`#${divId}`).css('background', `url("${adjustedUrl}") center center / cover no-repeat`);
        jQuery(`#${divId}`).css('opacity', '1');
        fifu_woo_get_sizes(index);
    }

    // Hide alt/ifm fields if url is empty
    if (!url)
        fifuHideAltIfmFields(divId);

    updateImageList();

    if (evt.which === 13 || evt.which === 27)
        jQuery.fancybox.close();
});

// change ALT
jQuery(document).on('keyup', 'input[id^="alt-input-image-fifu"], input[id^="ifm-input-image-fifu"]', function (evt) {
    evt.stopImmediatePropagation();
    let inputId = jQuery(this).attr('id');

    let type = inputId.startsWith('alt-input-image-') ? 'alt' : 'ifm';

    let divId = inputId.replace(`${type}-input-image-`, '');
    let index = divId.split('-')[2];

    let value = jQuery(`#${inputId}`).val();
    jQuery(`#fifu_input_${type}_${index}`).val(value);

    updateImageList();

    if (evt.which === 13 || evt.which === 27)
        jQuery.fancybox.close();
});

// OK button
jQuery(document).on('click', '#button-fifu-image', function (evt) {
    evt.stopImmediatePropagation();
    updateImageList();
    jQuery.fancybox.close();
});

// update the list of urls
function updateImageList() {
    var imageListIds = "";
    let i = 0;
    jQuery('div[id^="fifu-image"]').each(function (index) {
        let divId = jQuery(this).attr('id');
        let idx = divId.split('-')[2];
        let url = jQuery(`#fifu_input_url_${idx}`).val();
        if (url && url.startsWith('http')) {
            imageListIds += (i == 0) ? '' : '|';
            imageListIds += idx;
            i++;
        }
    });
    jQuery('#inputHiddenImageListIds').val(imageListIds);
    jQuery('#inputHiddenImageLength').val(jQuery('div[id^="fifu-image"]').length);
}

// dokan

var fifuBoxInitialized = false;
var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        let hidden = jQuery(mutation.target).attr('aria-hidden') == 'true';
        if (hidden) {
            fifuBoxInitialized = false;
        } else {
            if (!fifuBoxInitialized) {
                fifu_box_init();
                fifuBoxInitialized = true;
            }
        }
    });
});

var target = document.getElementById('dokan-add-product-popup');
if (target)
    observer.observe(target, {attributes: true, attributeFilter: ['aria-hidden']});


// quick edit

function fifu_image_gallery_info(post_id) {
    fifuBoxImageVars.urls = fifuQuickEditVars.posts[post_id]['fifu_image_urls'];
    fifuBoxImageVars.alts = fifuQuickEditVars.posts[post_id]['fifu_image_alts'];
    fifuBoxImageVars.ifms = fifuQuickEditVars.posts[post_id]['fifu_image_ifms'];
}

function fifu_update_gallery_metabox_by_fifu_gallery() {
    var $galleryBox = jQuery('#woocommerce-product-images');
    var $toggleBtn = $galleryBox.find('.handlediv');
    // Check if any image or video hidden input has a real URL
    var hasGalleryImage = jQuery('input[id^="fifu_input_url_"], input[id^="fifu_video_input_url_"]').filter(function () {
        var url = jQuery(this).val();
        return url && url.startsWith('http');
    }).length > 0;

    if (hasGalleryImage) {
        $galleryBox.addClass('closed');
        $toggleBtn.attr('aria-expanded', 'false').prop('disabled', true);
    } else {
        $galleryBox.removeClass('closed');
        $toggleBtn.attr('aria-expanded', 'true').prop('disabled', false);
    }
}

// Run on page load and whenever the FIFU gallery changes
jQuery(document).ready(function () {
    fifu_update_gallery_metabox_by_fifu_gallery();

    // Observe DOM changes in both FIFU gallery grids
    ['gridDemoImage', 'gridDemoVideo'].forEach(function (gridId) {
        var grid = document.getElementById(gridId);
        if (grid) {
            var observer = new MutationObserver(function () {
                fifu_update_gallery_metabox_by_fifu_gallery();
            });
            observer.observe(grid, {childList: true, subtree: true, attributes: true, attributeFilter: ['style']});
        }
    });

    // Also update after any input change in the galleries (for safety)
    jQuery(document).on('input change', '#gridDemoImage .grid-square.image, #gridDemoVideo .grid-square.video', function () {
        fifu_update_gallery_metabox_by_fifu_gallery();
    });
});

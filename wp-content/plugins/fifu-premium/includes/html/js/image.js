jQuery(document).ready(function ($) {
    if (fifuImageVars.fifu_block) {
        jQuery('body').on('contextmenu', 'img', function (e) {
            return false;
        });
    }

    if (typeof fifuRedirectionVarsFooter !== 'undefined' && fifuRedirectionVarsFooter) {
        for (let key in fifuRedirectionVarsFooter) {
            if (key.includes('/wp-content/uploads/') && !fifuImageVars.fifu_is_front_page)
                jQuery('img[src*="' + fifu_no_protocol(key.slice(0, key.lastIndexOf('.'))) + '"]').wrap('<a href="' + fifuRedirectionVarsFooter[key] + '" target="_blank"></a>');
            else
                jQuery('img[src*="' + fifu_no_protocol(key) + '"]').wrap('<a href="' + fifuRedirectionVarsFooter[key] + '" target="_blank"></a>');
        }
    }

    // forwarding
    if (fifuImageVars.fifu_redirection && fifuImageVars.fifu_forwarding_url && !fifuImageVars.fifu_is_front_page) {
        if (fifuImageVars.base64_main_image_url) {
            jQuery('img[src*="' + fifuImageVars.base64_main_image_url + '"]').wrap('<a href="' + fifuImageVars.fifu_forwarding_url + '" target="_blank"></a>');
        } else if (fifuImageVars.fifu_main_image_url) {
            // remote
            jQuery('img[src*="' + fifu_no_protocol(fifuImageVars.fifu_main_image_url) + '"]').wrap('<a href="' + fifuImageVars.fifu_forwarding_url + '" target="_blank"></a>');
        } else {
            // local
            let $targetImages = jQuery('img[srcset*="' + fifu_no_protocol(fifuImageVars.fifu_local_image_url) + '"]');
            if ($targetImages.length) {
                $targetImages.wrap('<a href="' + fifuImageVars.fifu_forwarding_url + '" target="_blank"></a>');
            } else {
                // exception (thumbnail, no srcset)
                modified = fifuImageVars.fifu_local_image_url.slice(0, fifuImageVars.fifu_local_image_url.lastIndexOf('.'));
                jQuery('img[src*="' + fifu_no_protocol(modified) + '"]').wrap('<a href="' + fifuImageVars.fifu_forwarding_url + '" target="_blank"></a>');
            }
        }
    }

    // woocommerce lightbox/zoom
    disableClick($);
    disableLink($);

    // zoomImg
    setTimeout(function () {
        jQuery('img.zoomImg').css('z-index', '');
        // Check if the zoomImg is missing an alt attribute and if its preceding sibling is an image
        if (!jQuery('img.zoomImg').attr('alt')) {
            const $zoomImg = jQuery('img.zoomImg');
            const $precedingImg = $zoomImg.prev('img');
            if ($precedingImg.length > 0) {
                $zoomImg.attr('alt', $precedingImg.attr('alt'));
            }
        }
    }, 1000);

    jQuery('img[height=1]').each(function (index) {
        if (jQuery(this).attr('width') != 1)
            jQuery(this).css('position', 'relative');
    });
});

jQuery(document).ajaxComplete(function ($) {
    // image not found
    jQuery('div.woocommerce-product-gallery img').on('error', function () {
        jQuery(this)[0].src = fifuImageVars.fifu_error_url;
    });
});

jQuery(window).on('ajaxComplete', function () {
    // timeout necessary (load more button of Bimber)
    setTimeout(function () {
        if (fifuImageVars.fifu_slider)
            fifu_slider = fifu_load_slider();
    }, 300);
});

function isValidImgClass(className) {
    // bimber
    return !className || !className.includes('avatar');
}

function disableClick($) {
    if (!fifuImageVars.fifu_woo_lbox_enabled) {
        let firstParentClass = '';
        let parentClass = '';
        jQuery('figure.woocommerce-product-gallery__wrapper').find('div.woocommerce-product-gallery__image').each(function (index) {
            parentClass = jQuery(this).parent().attr('class').split(' ')[0];
            if (!firstParentClass)
                firstParentClass = parentClass;

            if (parentClass != firstParentClass)
                return false;

            jQuery(this).children().click(function () {
                return false;
            });
            jQuery(this).children().children().css("cursor", "default");
        });
    }
}

function disableLink($) {
    if (!fifuImageVars.fifu_woo_lbox_enabled) {
        let firstParentClass = '';
        let parentClass = '';
        jQuery('figure.woocommerce-product-gallery__wrapper').find('div.woocommerce-product-gallery__image').each(function (index) {
            parentClass = jQuery(this).parent().attr('class').split(' ')[0];
            if (!firstParentClass)
                firstParentClass = parentClass;

            if (parentClass != firstParentClass)
                return false;

            jQuery(this).children().attr("href", "");
        });
    }
}

jQuery(document).click(function ($) {
    fifu_fix_gallery_height();
})

function fifu_fix_gallery_height() {
    if (fifuImageVars.fifu_is_flatsome_active) {
        let mainImage = jQuery('.woocommerce-product-gallery__wrapper div.flickity-viewport').find('img')[0];
        if (mainImage)
            jQuery('.woocommerce-product-gallery__wrapper div.flickity-viewport').css('height', mainImage.clientHeight + 'px');
    }
}

// for infinite scroll
jQuery(document.body).on('post-load', function () {
    setTimeout(function () {
        if (fifuImageVars.fifu_slider)
            fifu_slider = fifu_load_slider();
    }, 300);
});

function fifu_no_protocol(url) {
    return url.replace(/^https?:\/\//, '');
}

async function fifu_get_unsplash_urls(keywords, page) {
    try {
        let partialKey = null;
        let homeUrl = null;

        if (typeof fifuScriptVars !== 'undefined') {
            partialKey = fifuScriptVars.partialKey;
            homeUrl = encodeURIComponent(fifuScriptVars.homeUrl);
        } else if (typeof fifuColumnVars !== 'undefined') {
            partialKey = fifuColumnVars.partialKey;
            homeUrl = encodeURIComponent(fifuColumnVars.homeUrl);
        }
        if (!partialKey || !homeUrl)
            return;

        let orientation = fifuColumnVars.orientation && fifuColumnVars.orientation != 'all' ? `&orientation=${fifuColumnVars.orientation}` : '';

        const response = await fetch(`https://unsplash.fifu.workers.dev?partial_key=${partialKey}&site=${homeUrl}&keywords=${keywords}&page=${page}&orientation=${orientation}`);
        const data = await response.json();
        const urls = data.results.map(result => result.urls.small);

        // Add images to the masonry
        if (urls.length > 0) {
            urls.forEach(url => {
                jQuery('div.masonry').append('<div class="mItem" style="max-width:400px;object-fit:content"><img loading="lazy" src="' + url + '" style="width:100%"></div>');
            });
        }

        jQuery('#fifu-loading').remove();
        fifu_scrolling = false;

    } catch (error) {
        console.error("An error occurred:", error);
    }
}

function fifu_get_ddg_urls(post_id, is_ctgr) {
    let postTitle = fifu_get_post_title();
    if (!postTitle && !post_id)
        return;

    let aux_vars =
            typeof fifuScriptVars !== 'undefined' ? fifuScriptVars :
            typeof fifuColumnVars !== 'undefined' ? fifuColumnVars :
            null;

    const urls = [];

    jQuery.ajax({
        method: "POST",
        url: aux_vars.restUrl + 'fifu-premium/v2/ddg_search/',
        async: true,
        data: {
            "keywords": postTitle,
            "post_id": post_id,
            "is_ctgr": is_ctgr,
        },
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', aux_vars.nonce);
        },
        success: function (data) {
            urls.push(...data);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
            (async () => {
                // ready
                for (let i = 0; i < urls.length; i++) {
                    jQuery('div.masonry').append('<div class="mItem" style="max-width:400px;object-fit:content"><img loading="lazy" src="' + urls[i].thumbnail + '" original="' + urls[i].url + '" style="width:100%" onerror="fifu_handle_image_error(this);"></div>');
                }
                jQuery('#fifu-loading').remove();
                fifu_scrolling = false;
            })();
        },
    });
}

function fifu_handle_image_error(imageElement) {
    imageElement.parentNode.remove();
}

var fifu_scrolling = false;
var idSet = new Set();

function fifu_start_lightbox(keywords, unsplash, post_id, is_ctgr, context) {
    idSet = new Set();
    fifu_register_unsplash_click_event(context);

    let txt_loading = typeof fifuMetaBoxVars !== 'undefined' ? fifuMetaBoxVars.txt_loading : '';
    let txt_more = typeof fifuMetaBoxVars !== 'undefined' ? fifuMetaBoxVars.txt_more : '';

    jQuery.fancybox.open('<div><div class="masonry"></div></div>');
    jQuery('div.masonry').after('<center><div id="fifu-loading"><img loading="lazy" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.lazyloadxt/1.1.0/loading.gif"><div>' + txt_loading + '</div><div></center>');

    if (!unsplash) {
        fifu_get_ddg_urls(post_id, is_ctgr);
        return;
    }

    let page = 1;
    fifu_get_unsplash_urls(keywords, page);
    jQuery('div[class^=fancybox]').scroll(function () {
        if (jQuery(this).scrollTop() + jQuery('div.fancybox-container')[0].scrollHeight > parseInt(jQuery('div.fancybox-slide > div.fancybox-content').last().height())) {
            if (!fifu_scrolling) {
                fifu_scrolling = true;
                jQuery('#fifu-loading').remove();
                jQuery('div.masonry').after('<center><div id="fifu-loading"><img loading="lazy" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.lazyloadxt/1.1.0/loading.gif"><div>' + txt_more + '</div><div></center>');
                page += 1;
                fifu_get_unsplash_urls(keywords, page);
            }
        }
    });
}

function fifu_register_unsplash_click_event(context) {
    // Remove previous handlers to avoid duplicates
    jQuery('body').off('click', 'div.mItem > img');

    jQuery('body').on('click', 'div.mItem > img', function (evt) {
        evt.stopImmediatePropagation();

        let src = jQuery(this).attr('original');
        if (!src) {
            src = jQuery(this).attr('src');
            src = src.replace('&w=400', '&w=1200');
        }

        if (context === 'meta-box') {
            // meta-box
            if (jQuery("#fifu_input_url").length) {
                jQuery("#fifu_input_url").val(src);
                previewImage();
            }
        } else if (context === 'quick-edit') {
            // quick-edit
            if (jQuery("#fifu-quick-search-input-keywords").length) {
                jQuery("#fifu-quick-input-url").val(src);
                // jQuery("#fifu-quick-input-url").trigger('input');
                jQuery("#fifu-quick-search-input-keywords").val('');
                jQuery('#fifu-save-button').click();
            }
        }
        jQuery.fancybox.close();
    });
}

function fifu_get_post_title() {
    if (wp && wp.data && wp.data.select('core/editor'))
        return wp.data.select('core/editor').getEditedPostAttribute('title');

    var titleElement = document.getElementById('title');

    if (!titleElement)
        titleElement = document.getElementById('tag-name'); // for category (new)

    if (!titleElement)
        titleElement = document.getElementById('name'); // for category (edit)

    if (titleElement)
        return titleElement.value;

    return null;
}

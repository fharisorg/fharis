var isDraggingVideo = false;

// add meta box icon
jQuery(document).ready(function () {
    let text = jQuery("div#wooCommerceVideoGalleryMetaBox").find('h2').text();
    jQuery("div#wooCommerceVideoGalleryMetaBox").find('h2.hndle').text('');
    jQuery("div#wooCommerceVideoGalleryMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-format-video"></span> ' + text + '</h4>');
    jQuery("div#wooCommerceVideoGalleryMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#wooCommerceVideoGalleryMetaBox").find('button.handle-order-lower').remove();
});

// sizes
async function fifu_woo_video_get_sizes(i) {
    let video_url = jQuery('input[id^=fifu_video_input_url_' + i + ']').val();
    if (!video_url || (!video_url.startsWith("http") && !video_url.startsWith("//")))
        return;
    let image_url = await fifu_video_image_thumbnail(video_url, fifuVideoVars);
    fifu_woo_video_get_image(image_url, i);
}

function fifu_woo_video_get_image(url, i) {
    var image = new Image();
    jQuery(image).attr('onload', 'fifu_woo_video_store_sizes(this,' + i + ');');
    jQuery(image).attr('src', url);
}

function fifu_woo_video_store_sizes($, i) {
    jQuery("#fifu_video_input_width_" + i).val($.naturalWidth);
    jQuery("#fifu_video_input_height_" + i).val($.naturalHeight);
    if ($.naturalWidth == 120 && $.naturalHeight == 90)
        jQuery("#fifu_video_input_image_src_" + i).val($.src.replace('maxresdefault', 'mqdefault'));
    else
        jQuery("#fifu_video_input_image_src_" + i).val($.src);

    // load thumbnail gallery
    let src = jQuery("#fifu_video_input_image_src_" + i).val();
    let selector = `#fifu-video-${i}`;
    jQuery(selector).css('background', `url("${src}") center center / cover no-repeat`);
    jQuery(selector).css('opacity', '1');
}

var maxVideo = 0;

// run once
jQuery(document).ready(async function () {
    await fifu_video_box_init();
});

async function fifu_video_box_init() {
    const MIN = 3;

    // quick edit
    if (currentLightbox)
        fifu_video_gallery_info(currentLightbox);

    let numberUrls = fifuVideoVars.videoUrls ? fifuVideoVars.videoUrls.length : 0;
    let numberInputs = numberUrls <= MIN ? MIN : numberUrls;

    // add placeholders
    for (let i = 0; i < numberInputs; i++) {
        jQuery('#gridDemoVideo').append(`<div id="fifu-video-${i}" class="grid-square video"></div>`);
        maxVideo = i;
    }

    // add plus button
    jQuery('#gridDemoVideo').append(`<div id="fifu-add-video" class="grid-square image-add"></div>`);

    // add images
    for (let i = 0; i < numberInputs; i++) {
        let videoURL = fifuVideoVars.videoUrls[i];
        let imageURL = fifuVideoVars.imageUrls[i];

        videoURL = videoURL !== undefined ? videoURL : "";
        imageURL = imageURL !== undefined ? imageURL : "";

        // add input hiddens
        jQuery('#inputHiddenVideos').append(`
            <input type="hidden" id="fifu_video_input_width_${i}" name="fifu_video_input_width_${i}" value="" >
            <input type="hidden" id="fifu_video_input_height_${i}" name="fifu_video_input_height_${i}" value="" >
            <input type="hidden" id="fifu_video_input_url_${i}" name="fifu_video_input_url_${i}" value="${videoURL}">
            <input type="hidden" id="fifu_video_input_image_src_${i}" name="fifu_video_input_image_src_${i}" value="">
        `);

        // get sizes
        if (imageURL)
            await fifu_woo_video_get_sizes(i);
    }

    // start lists
    updateVideoList();

    /////////////////////////////////////////////////

    // init sortable
    if (jQuery('#gridDemoVideo').length) {
        jQuery('#gridDemoVideo').sortable({
            start: function (event, ui) {
                isDraggingVideo = false; // Reset the flag
            },
            stop: function (event, ui) {
                isDraggingVideo = true; // Set the flag to indicate dragging occurred
            }
        });
    }

    // prepare fancy boxes 
    addFancyBoxVideo();

    // add new image: onclick event
    jQuery(document).on('click', '#fifu-add-video', function (evt) {
        if (isDraggingVideo) {
            isDraggingVideo = false;
            return;
        }

        evt.stopImmediatePropagation();
        maxVideo++;
        jQuery('#gridDemoVideo').append(`<div id="fifu-video-${maxVideo}" class="grid-square video"></div>`);

        jQuery('#inputHiddenVideos').append(`
            <input type="hidden" id="fifu_video_input_width_${maxVideo}" name="fifu_video_input_width_${maxVideo}" value="" >
            <input type="hidden" id="fifu_video_input_height_${maxVideo}" name="fifu_video_input_height_${maxVideo}" value="" >
            <input type="hidden" id="fifu_video_input_url_${maxVideo}" name="fifu_video_input_url_${maxVideo}" value="">
            <input type="hidden" id="fifu_video_input_image_src_${maxVideo}" name="fifu_video_input_image_src_${maxVideo}" value="">
            <input type="hidden" id="fifu_video_input_alt_${maxVideo}" name="fifu_video_input_alt_${maxVideo}" value="">
        `);
        addFancyBoxVideo();
    });

    jQuery('div.grid-square').on('mouseout', function (evt) {
        evt.stopImmediatePropagation();
        updateVideoList();
    });
}

// prepare fancy boxes
function addFancyBoxVideo() {
    jQuery(document).on('click', 'div[id^="fifu-video-"]', function (evt) {
        if (isDraggingVideo) {
            isDraggingVideo = false;
            return;
        }

        evt.stopImmediatePropagation();
        let divId = jQuery(this).attr('id');
        let index = divId.split('-')[2];

        let url = jQuery(`#fifu_video_input_url_${index}`).val();
        url = url ? url : "";

        // Only show iframe if url is present and supported, otherwise show fallback image or nothing
        let iframeTag = '';
        if (url) {
            if (typeof fifu_is_video === 'function' && !fifu_is_video(url)) {
                iframeTag = `<img src="${window.FIFU_VIDEO_NOT_FOUND_URL}" style="width:275px;margin-top:5px;margin-left:1px"><br>`;
            } else {
                iframeTag = `<iframe id="iframe-fifu-video" width="100%" src="${srcVideo(url)}" allowfullscreen frameborder="0" style="width:275px;margin-top:5px;margin-left:1px"></iframe><br>`;
            }
        }

        jQuery.fancybox.open(`
            <input id="input-${divId}" placeholder="${fifuVideoVars.text_url}" value="${url}" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>
            <span id="span-iframe-fifu-video">
            ${iframeTag}
            </span>
            <button id="button-fifu-video" type="button" style="width:275px;padding:10px;height:36px;margin-bottom:3px;">${fifuVideoVars.text_ok}</button>
        `);
        jQuery(`#input-${divId}`).focus();
        jQuery(`#input-${divId}`).select();
    });
}

// change URL
jQuery(document).on('keyup', 'input[id^="input-fifu-video"]', async function (evt) {
    evt.stopImmediatePropagation();

    let inputId = jQuery(this).attr('id');
    let divId = inputId.replace('input-', '');
    let index = divId.split('-')[2];

    let url = jQuery(`#${inputId}`).val();
    url = !url.startsWith('http') ? '' : fifu_convert_video(url);

    // Only update if value actually changed, or Enter/Esc is pressed
    let prevUrl = jQuery(this).data('prev-url');
    if (url === prevUrl && evt.which !== 13 && evt.which !== 27) {
        return;
    }
    jQuery(this).data('prev-url', url);

    jQuery(`#fifu_video_input_url_${index}`).val(url);

    jQuery(`#span-iframe-fifu-video`).empty();

    // Only show iframe if url is present and supported, otherwise show fallback image or nothing
    if (!url) {
        jQuery(`#${divId}`).attr('style', '');
    } else if (typeof fifu_is_video === 'function' && !fifu_is_video(url)) {
        jQuery(`#span-iframe-fifu-video`).append(
                `<img src="${window.FIFU_VIDEO_NOT_FOUND_URL}" style="width:275px;margin-top:5px;margin-left:1px"><br>`
                );
        jQuery(`#${divId}`).css('background', `url("${window.FIFU_VIDEO_NOT_FOUND_URL}") center center / cover no-repeat`);
        jQuery(`#${divId}`).css('opacity', '1');
    } else {
        jQuery(`#span-iframe-fifu-video`).append(
                `<iframe id="iframe-fifu-video" width="100%" src="${srcVideo(url)}" allowfullscreen frameborder="0" style="width:275px;margin-top:5px;margin-left:1px"></iframe><br>`
                );
        await fifu_woo_video_get_sizes(index);
    }

    updateVideoList();

    if (evt.which === 13 || evt.which === 27)
        jQuery.fancybox.close();
});

// OK button
jQuery(document).on('click', '#button-fifu-video', function (evt) {
    evt.stopImmediatePropagation();
    updateVideoList();
    jQuery.fancybox.close();
});

// // update the list of urls
function updateVideoList() {
    var videoListIds = "";
    let i = 0;
    jQuery('div[id^="fifu-video"]').each(function (index) {
        let divId = jQuery(this).attr('id');
        let idx = divId.split('-')[2];
        let url = jQuery(`#fifu_video_input_url_${idx}`).val();

        // Check if the URL is present and supported
        if (url && url.startsWith('http')) {
            videoListIds += (i == 0) ? '' : '|';
            videoListIds += idx;
            i++;

            // If not a supported video, show not found image
            if (typeof fifu_is_video === 'function' && !fifu_is_video(url)) {
                jQuery(`#${divId}`).css('background', `url("${window.FIFU_VIDEO_NOT_FOUND_URL}") center center / cover no-repeat`);
                jQuery(`#${divId}`).css('opacity', '1');
            }
        } else {
            jQuery(`#${divId}`).css('opacity', '');
        }
    });
    jQuery('#inputHiddenVideoListIds').val(videoListIds);
    jQuery('#inputHiddenVideoLength').val(jQuery('div[id^="fifu-video"]').length);
}

// quick edit

function fifu_video_gallery_info(post_id) {
    fifuVideoVars.videoUrls = fifuQuickEditVars.posts[post_id]['fifu_video_urls'];
    fifuVideoVars.imageUrls = fifuQuickEditVars.posts[post_id]['fifu_thumb_urls'];
}

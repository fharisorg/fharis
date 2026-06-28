var fifuPreviousInputs = [];

function removeVideo() {
    jQuery("#fifu_video").hide();
    jQuery("#fifu_video_link").hide();

    jQuery("#fifu_video_local").hide();
    jQuery("#fifu_capture_thumbnail").hide();

    jQuery("#fifu_video_custom").hide();

    jQuery("#fifu_video_input_url").val("");

    jQuery("#fifu_video_button").show();

    // Hide fallback image when input is cleared
    ensureFallbackImage().hide();

    // Trigger category thumbnail toggle when removing video
    if (typeof toggleCategoryThumbnail === 'function') {
        toggleCategoryThumbnail(true);
    }

    // Show WooCommerce placeholder in category thumbnail
    jQuery('#product_cat_thumbnail').find('img').attr('src', WC_PLACEHOLDER_IMAGE_URL);
    jQuery('#product_cat_thumbnail_id').val('');
    jQuery('.remove_image_button').hide();
}

function previewVideo() {
    var $url = jQuery("#fifu_video_input_url").val();

    $new_url = fifu_convert_video($url);
    if ($url != $new_url) {
        jQuery("#fifu_video_input_url").val($new_url);
        $url = $new_url;
    }

    // ensure the element exists and hide it by default
    ensureFallbackImage().hide();

    // not a video? show fallback and exit
    if (!$url || !fifu_is_video($url)) {
        showVideoFallback();
        return;
    }

    jQuery("#fifu_video_button").hide();

    let $src = srcVideo($url);

    // invalid src? fallback
    if (!$src) {
        showVideoFallback();
        return;
    }

    if (isLocalVideoUrl($url)) {
        jQuery("#fifu_video_tag").attr("src", $src);
        jQuery("#fifu_video_local").show();
        jQuery("#fifu_capture_thumbnail").show();
        setTimeout(function () {
            capture();
        }, 500);
    } else if ($src && $src.includes('m3u8')) { // null-safe
        jQuery("#fifu_video_custom_tag").attr("src", $src);
        jQuery("#fifu_video_custom").show();
        jQuery("#fifu_capture_thumbnail").hide();

        if (typeof Hls === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/hls.js@latest';
            script.onload = function () {
                setupHlsPlayer($src, 'fifu_video_custom_tag');
            };
            document.body.appendChild(script);
        } else {
            setupHlsPlayer($src, 'fifu_video_custom_tag');
        }
    } else {
        jQuery("#fifu_video_iframe").attr("src", $src);
        jQuery("#fifu_video").show();
        jQuery("#fifu_capture_thumbnail").hide();
    }

    jQuery("#fifu_video_link").show();
}

jQuery(document).ready(async function () {
    // start
    await fifu_video_get_sizes();

    let url = jQuery("#fifu_video_input_url").val();
    fifuPreviousInputs['fifu_video_input_url'] = fifu_format_previous_input(url);

    // Show fallback if URL is present but not a valid video
    if (url && !fifu_is_video(url)) {
        showVideoFallback();
    }

    // blur
    jQuery("#fifu_video_input_url").on('input', async function (evt) {
        evt.stopImmediatePropagation();

        let url = jQuery(this).val();

        // Hide fallback image if input is empty
        if (!url) {
            ensureFallbackImage().hide();
            return;
        }

        // ignore thumbnail function when it's just a parameter change
        if (fifu_format_previous_input(url) != fifuPreviousInputs['fifu_video_input_url']) {
            await fifu_video_get_sizes();
            fifuPreviousInputs['fifu_video_input_url'] = fifu_format_previous_input(url);
        }
    });

    // title
    let text = jQuery("div#wooVideoUrlMetaBox").find('h2').text();
    jQuery("div#wooVideoUrlMetaBox").find('h2.hndle').text('');
    jQuery("div#wooVideoUrlMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-video-alt3"></span> ' + text + '</h4>');
    jQuery("div#wooVideoUrlMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#wooVideoUrlMetaBox").find('button.handle-order-lower').remove();

    text = jQuery("div#videoUrlMetaBox").find('h2').text();
    jQuery("div#videoUrlMetaBox").find('h2.hndle').text('');
    jQuery("div#videoUrlMetaBox").find('h2').append('<h4 style="left:-10px;position:relative;font-size:13px;font-weight:normal"><span class="dashicons dashicons-video-alt3"></span> ' + text + '</h4>');
    jQuery("div#videoUrlMetaBox").find('button.handle-order-higher').remove();
    jQuery("div#videoUrlMetaBox").find('button.handle-order-lower').remove();

    if (url && url.includes('m3u8')) {
        const hls = new Hls();
        hls.loadSource(url);
        hls.attachMedia(jQuery('#fifu_video_custom_tag')[0]);
    }
});

async function fifu_video_get_sizes() {
    const video_url = jQuery("#fifu_video_input_url").val();
    if (!video_url || (!video_url.startsWith("http") && !video_url.startsWith("//")))
        return;

    // custom
    if (!fifu_is_video(video_url))
        return;

    try {
        let image_url = await fifu_video_image_thumbnail(video_url, fifuVideoMetaBoxVars);
        fifu_video_get_image(image_url);
    } catch (error) {
        console.error('Error fetching video thumbnail:', error);
    }
}

function checkYoutubeThumbnail(url) {
    if (!url)
        return '';

    if (url.includes('mqdefault') && url.includes('youtube')) {
        var maxresUrl = url.replace('mqdefault', 'maxresdefault');
        jQuery.ajax({
            url: maxresUrl,
            type: 'HEAD',
            success: function () {
                url = maxresUrl;
                console.log('Using maxresdefault:', url);
            },
            error: function () {
                console.warn('maxresdefault not available, using mqdefault:', url);
            },
            async: false,
        });
    }
    return url;
}

function fifu_video_get_image(url) {
    if (!url)
        return;

    var image = new Image();

    url = checkYoutubeThumbnail(url);

    jQuery(image).attr('onload', 'fifu_video_store_sizes(this);');
    jQuery(image).attr('src', url);
}

function fifu_video_store_sizes($) {
    jQuery("#fifu_video_input_image_width").val($.naturalWidth);
    jQuery("#fifu_video_input_image_height").val($.naturalHeight);
    if ($.naturalWidth == 120 && $.naturalHeight == 90)
        jQuery("#fifu_video_input_image_src").val($.src.replace('maxresdefault', 'mqdefault'));
    else
        jQuery("#fifu_video_input_image_src").val($.src);
}

function fifu_video_src(url) {
    var response;

    jQuery.ajax({
        method: "POST",
        url: fifuVideoMetaBoxVars.restUrl + 'fifu-premium/v2/video_src/',
        async: false,
        data: {
            "url": url,
        },
        beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', fifuVideoMetaBoxVars.nonce);
        },
        success: function (data) {
            response = data;
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        },
        complete: function (data) {
        },
    });

    return decodeURI(response);
}

function capture() {
    const $ = jQuery;
    const video = document.getElementById('fifu_video_tag');
    const isFirefox = navigator.userAgent.toLowerCase().includes('firefox');

    // Create temporary canvas and immediately open lightbox with a spinner
    const tempCanvas = document.createElement('canvas');
    const spinnerUrl = 'https://cdnjs.cloudflare.com/ajax/libs/jquery.lazyloadxt/1.1.0/loading.gif';
    const spinnerHtml = `<div style="text-align:center;"><img src="${spinnerUrl}" alt="Loading..." style="width:50px;height:50px;"></div>`;

    // Open lightbox with spinner
    $.fancybox.open(spinnerHtml);

    // Reference to our lightbox canvas
    const canvas = tempCanvas;

    function drawVideoFrame() {
        console.log('Attempting to play video...');
        video.play().then(() => {
            if (isFirefox) {
                video.requestVideoFrameCallback(() => {
                    console.log('Firefox frame rendered at:', video.currentTime);
                    captureFrameAfterPause();
                });
            } else {
                requestAnimationFrame(() => {
                    video.pause();
                    captureFrame();
                });
            }
        }).catch(error => {
            console.error('Play failed:', error);
            captureFrameWithoutPlay();
        });
    }

    function captureFrameAfterPause() {
        video.pause();
        setTimeout(() => {
            console.log('Firefox post-pause time:', video.currentTime);
            captureFrame();
        }, isFirefox ? 100 : 0);
    }

    function captureFrame() {
        canvas.width = Math.max(video.videoWidth, 1);
        canvas.height = Math.max(video.videoHeight, 1);

        const context = canvas.getContext('2d');
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.drawImage(video, 0, 0);

        if (isFirefox) {
            const pixel = context.getImageData(0, 0, 1, 1).data;
            if (pixel[3] < 255) {
                console.log('Firefox empty frame detected, retrying...');
                setTimeout(drawVideoFrame, 200);
                return;
            }
        }

        getImageURL();
    }

    function captureFrameWithoutPlay() {
        video.currentTime = isFirefox ? 0.001 : 0;
        video.onseeked = () => {
            console.log('Seek completed at:', video.currentTime);
            if (isFirefox)
                setTimeout(captureFrame, 50);
            else
                captureFrame();
        };
    }

    function getImageURL() {
        try {
            const imageURL = canvas.toDataURL('image/png');
            if (!imageURL || imageURL === "data:,")
                throw new Error('Empty image URL');

            console.log('Image URL length:', imageURL.length);

            // Update form fields
            $('#fifu_video_captured_frame').val(imageURL);
            $('#fifu_video_time_frame').val(video.currentTime.toString().replace('.', ''));

            // Replace spinner with final image in lightbox
            const fancyboxInstance = $.fancybox.getInstance();
            if (fancyboxInstance) {
                fancyboxInstance.current.$content
                        .empty()
                        .append(`<img loading="lazy" src="${imageURL}" style="max-height:600px">`);
            }

        } catch (error) {
            console.error('Error in getImageURL:', error);
            setTimeout(getImageURL, 100);
        }
    }

    // Initial execution
    if (video.readyState >= 2) {
        drawVideoFrame();
    } else {
        video.addEventListener('loadedmetadata', drawVideoFrame, {once: true});
    }
}

function setupHlsPlayer(url, videoElementId) {
    try {
        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            const videoElement = jQuery("#" + videoElementId)[0];
            if (!videoElement) {
                console.error("Video element not found:", videoElementId);
                return;
            }

            const hls = new Hls();

            hls.loadSource(url);
            hls.attachMedia(videoElement);
        }
    } catch (error) {
        console.error("Error setting up HLS player:", error);
    }
}

function ensureFallbackImage() {
    let $img = jQuery('#fifu_video_fallback_image');
    if (!$img.length) {
        $img = jQuery('<img>', {
            id: 'fifu_video_fallback_image',
            src: FIFU_VIDEO_NOT_FOUND_URL,
            style: 'max-width:100%;display:none;border-radius:3px;'
        });
        // Try all possible containers
        if (jQuery('#wooVideoUrlMetaBox .inside').length) {
            jQuery('#wooVideoUrlMetaBox .inside').prepend($img);
        } else if (jQuery('#videoUrlMetaBox .inside').length) {
            jQuery('#videoUrlMetaBox .inside').prepend($img);
        } else {
            // Only prepend to .catbox that contains the video input
            let $catbox = jQuery('.catbox').has('#fifu_video_input_url');
            if ($catbox.length) {
                $catbox.prepend($img);
            }
        }
    }
    return $img;
}

function showVideoFallback() {
    const $img = ensureFallbackImage();
    jQuery("#fifu_video, #fifu_video_local, #fifu_video_custom, #fifu_capture_thumbnail, #fifu_video_link").hide();
    $img.attr("src", FIFU_VIDEO_NOT_FOUND_URL).show();
    jQuery("#fifu_video_button").show();
}

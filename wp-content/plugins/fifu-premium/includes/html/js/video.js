document.addEventListener("DOMContentLoaded", function () {
    if (typeof fifuVideoThumbVars !== 'undefined' && typeof fifuVideoThumbVarsFooter !== 'undefined') {
        Object.assign(fifuVideoThumbVars.thumbs, fifuVideoThumbVarsFooter);
    }
});

// Create an IntersectionObserver instance with a threshold of 0.5
const viewPortObserver = new IntersectionObserver(entries => {
    entries.forEach(async entry => {
        if (entry.isIntersecting) {
            const $target = jQuery(entry.target);
            // Check if the target is an <img> and if it is fully loaded
            if ($target.is('img') && !$target[0].complete) {
                // If it's an <img> that's not yet loaded, wait for load event using jQuery
                $target.on('load', await handleVisibleImage(entry.target));
            } else {
                // This handles both loaded <img> and elements with background images
                await handleVisibleImage(entry.target);
            }
        } else {
            // The element has become invisible
            const $target = jQuery(entry.target);
            if ($target.is('video') || $target.is('iframe')) {
                handleInvisibleVideo(entry.target);
            }
        }
    });
}, {threshold: 0.38});

// Function to start observing a new element
function observeElement(element) {
    // Check if we need to wait for pagination data
    if (typeof waitForPaginationData !== 'undefined' && waitForPaginationData) {
        function waitForPaginationDataAsync() {
            return new Promise(resolve => {
                function checkPaginationData() {
                    if (hasPaginationData) {
                        resolve();
                    } else {
                        setTimeout(checkPaginationData, 100);
                    }
                }
                checkPaginationData();
            });
        }
        waitForPaginationDataAsync().then(() => {
            viewPortObserver.observe(element);
        });
    } else {
        // If we don't need to wait for pagination data, observe the element immediately
        setTimeout(() => viewPortObserver.observe(element), 0); // You might not need the setTimeout here
    }
}

// Use MutationObserver to watch for changes in the document
const fifuChangeObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            // Check if the added node is an image or contains images
            if (node.nodeType === 1) { // Element node
                const elements = node.tagName === 'IMG' || node.tagName === 'VIDEO' || node.tagName === 'IFRAME' ? [node] : node.querySelectorAll('img, video, iframe');
                elements.forEach(observeElement);
            }
        });
    });
});

// Start observing the document body for added nodes
fifuChangeObserver.observe(document.body, {childList: true, subtree: true});

// Observe initial images as before
jQuery(getThumbnailSelectors()).each(function () {
    observeElement(this);
});

// Function to handle invisible images
function handleInvisibleVideo(video) {
    if (jQuery(video).prop('tagName') == 'VIDEO')
        video.pause();

    if (jQuery(video).prop('tagName') == 'IFRAME') {
        if (jQuery(video).hasClass('fifu_iframe')) {
            let url = jQuery(video).attr('src');
            let iframeId = jQuery(video).attr('id');

            if (is_vimeo_src(url)) {
                let vimeoPlayer = new Vimeo.Player(jQuery(`#${iframeId}`));
                vimeoPlayer.pause();
                return;
            }

            if (is_youtube_src(url)) {
                window.YT.ready(function () {
                    if (fifuPlayers[iframeId] && typeof fifuPlayers[iframeId].pauseVideo === 'function')
                        fifuPlayers[iframeId].pauseVideo();
                });
                return;
            }
        } else
            return;
    }
}

// Function to handle visible images
async function handleVisibleImage(element) {
    if (fifu_should_autoplay()) {
        if (jQuery(element).prop('tagName') == 'VIDEO') {
            element.play();
            return;
        }

        if (jQuery(element).prop('tagName') == 'IFRAME') {
            if (jQuery(element).hasClass('fifu_iframe')) {
                let url = jQuery(element).attr('src');
                let iframeId = jQuery(element).attr('id');

                if (is_vimeo_src(url)) {
                    let vimeoPlayer = new Vimeo.Player(jQuery(`#${iframeId}`));
                    vimeoPlayer.play();
                    return;
                }

                if (is_youtube_src(url)) {
                    window.YT.ready(function () {
                        // Check if the player has already been created
                        if (!fifuPlayers[iframeId] || typeof fifuPlayers[iframeId].playVideo !== 'function') {
                            // Player not initialized, so initialize it
                            fifuPlayers[iframeId] = new YT.Player(iframeId, {
                                events: {
                                    'onReady': function (event) {
                                        event.target.playVideo();
                                    },
                                    'onStateChange': fifuOnPlayerStateChange
                                }
                            });
                        } else {
                            fifuPlayers[iframeId].playVideo();
                        }
                    });
                    return;
                }
            } else
                return;

            return;
        }
    }

    let image = element;
    let $parent = jQuery(image).parent();

    if ($parent.hasClass('elementor-widget-container')) {
        setTimeout(async function () {
            await handleVisibleImage2(image);
        }, 1500);
    } else {
        await handleVisibleImage2(image);
    }
}

async function handleVisibleImage2(image) {
    let src, url, background_style, is_background, w, h, $parent, autoplay, loop, controls, iframeId, iframe_class, width, height, h_iframe, crop, is_video_tag, dataType, mouseenter, position, type, $video, extra, icon, button, parentClass;

    if (jQuery(image).prop('tagName') == 'IMG') {
        src = image.src;
        background_style = "";
        is_background = false;
    } else {
        let bgImage = jQuery(image).css('background-image');
        if (!bgImage || bgImage === 'none')
            return;
        src = bgImage.split(/url\([\'\"]/)[1].split(/[\'\"]\)/)[0];
        background_style = "style='position:unset'";
        is_background = true;
    }

    // Add a class to the image to style it differently using jQuery
    // if (!src.startsWith("data"))
    //     console.log("Image", src, "is now visible!");
    jQuery(image).addClass('visible');

    if (!(await is_video_img(src)))
        return;

    // vimeography plugin: ignore images
    if (jQuery(image).hasClass('vimeography-thumbnail-img'))
        return;

    if (jQuery(image).parents('ul.lSPager > li > a').length) {
        if (fifuVideoVars.fifu_is_product &&
                fifuVideoVars.fifu_video_gallery_icon_enabled &&
                jQuery(image).parents().attr('class') != 'fifu_play icon_gallery') {
            jQuery(image).wrap("<div class='fifu_play icon_gallery'></div>");
            jQuery(image).after("<span class='dashicons dashicons-format-video icon_gallery' style='height:24px'></span>");
        }
        return;
    }

    if (jQuery(image).hasClass('fifu_replaced'))
        return;

    if (jQuery(image).parent().parent().find('.fifu_play').length &&
            !jQuery(image).parent().parent().hasClass('fifu-product-gallery') &&
            !jQuery(image).parent().parent().hasClass('gallery') &&
            !jQuery(image).siblings('img.fifu-video').length)
        return;

    if (jQuery(image).parent().parent().hasClass('lg-item'))
        return;

    if (jQuery(image).parents('ol.flex-control-nav').length)
        return;

    if (jQuery(image).attr('class') == 'zoomImg')
        return;

    if (!isImageWidthSufficient(jQuery(image)))
        return;

    url = await video_url(src);
    if (!url)
        return;

    url = add_parameters(url, src);

    w = jQuery(image)[0].clientWidth;
    h = jQuery(image)[0].clientHeight;
    $parent = jQuery(image).parent();

    autoplay = fifu_should_autoplay() ? 'allow="autoplay"' : '';
    loop = fifu_should_loop() ? 'loop' : '';
    controls = fifuVideoVars.fifu_video_controls ? '' : ' fifu_no_controls';

    iframeId = simpleHash(url);
    iframe_class = is_background ? '' : 'fifu_iframe';
    width = `width:${w}px`;
    height = `height:${h}px`;
    h_iframe = height;

    // expand video for the whole image area
    crop = 'object-fit:cover';

    is_video_tag = (is_wpcom_video_img(src) && src.includes('mp4')) ||
            (is_local_video_img(src) && (src.includes('mp4') || src.includes('mov') || src.includes('webm') || src.includes('m3u8'))) ||
            (is_custom_video_img(src) && (url.includes('mp4') || url.includes('mov') || url.includes('webm') || url.includes('m3u8'))) ||
            (await is_otf_video_img(src) && (url.includes('mp4') || url.includes('mov') || url.includes('webm') || url.includes('m3u8'))) ||
            await is_otf_audio_img(src) || is_audio_img(src);

    dataType = is_video_tag ? "video" : "iframe";

    mouseenter = '';
    if (fifuVideoVars.fifu_mouse_video_enabled)
        mouseenter = `onmouseenter='jQuery.fancybox.open([{src:"${url}",type:"${dataType}"}])'`;

    // check if elementor exists
    position = typeof jQuery('div.elementor')[0] == "undefined" && fifuVideoVars.fifu_is_flatsome_active ? 'unset' : 'relative';

    if (shouldReplaceImageWithVideo(src)) {
        // video
        if (is_video_tag) {
            type = src.includes('mp4') ? 'type="video/mp4"' : (src.includes('mp3') ? 'type="audio/mpeg"' : '');
            if (is_background) {
                $video = `
                    <video id="${iframeId}" class="${controls}" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;" ${fifuVideoVars.fifu_video_controls ? 'controls' : ''} autoplay muted playsinline ${loop} poster="${src}">
                        <source src="${url}" ${type}></source>
                    </video>`;
                jQuery(image).prepend($video);
            } else {
                $video = `
                    <video id="${iframeId}" class="${controls}" style="${width};${height};${crop}" ${fifuVideoVars.fifu_video_controls ? 'controls' : ''} autoplay muted playsinline ${loop} poster="${src}">
                        <source src="${url}" ${type}></source>
                    </video>`;
            }
        }
        // iframe
        else {
            if (is_background) {
                $video =
                        `<iframe id="${iframeId}" class="${iframe_class} ${controls}" src="${url}" allowfullscreen frameborder="0" ${autoplay} style="${width};${h_iframe}" thumb="${src}"></iframe>`;
                jQuery(image).append($video);
            } else {
                extra = '';
                if (is_spotify_video_img(src)) {
                    h_iframe = 'height:352px';
                    extra = 'display:flex;justify-content:center;align-items:center;';
                }
                $video = `
                    <div style="background:url(https://storage.googleapis.com/featuredimagefromurl/video-loading.gif) no-repeat center center black;${width};${height};${extra};text-align:center">
                        <div class="fifu_wrapper">
                            <div class="fifu_h_iframe" style="position:${position}">
                                <img class="fifu_ratio fifu_replaced" src="${src}"/>
                                <iframe id="${iframeId}" class="${iframe_class} ${controls}" src="${url}" allowfullscreen frameborder="0" ${autoplay} style="${width};${h_iframe}" thumb="${src}" iframew="${w}" iframeh="${h}"></iframe>
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        if (is_background) {
            jQuery(image).css('background-image', '');
        } else {
            if (fifu_requires_adjustment()) {
                // theme exceptions
                let $videoElement = jQuery($video);
                jQuery(image).replaceWith($videoElement);
                fifu_adjust_for_theme($videoElement[0]);
            } else {
                jQuery(image).replaceWith($video);

                if (url.includes('m3u8'))
                    fifu_init_hls_player(url, iframeId)
            }

            if (fifuVideoVars.fifu_later_enabled) {
                icon = fifuWatchLaterQueue.has(src) ? 'yes' : 'clock';
                $parent.prepend(`
                    <div class='fifu_play start'>
                        <div class='fifu_link' href='/' onclick='return false'>
                            <span title="${fifuVideoVars.text_later}" class='dashicons dashicons-${icon} icon w-later-thumb' thumb='${src}'></span>
                            <span title="${fifuVideoVars.text_queue}" class='dashicons dashicons-playlist-video icon w-later-thumb' thumb-pl='${src}' style='top:40px'></span>
                        </div>
                    </div>`
                        );
                fifu_add_event_w_later_thumb(src);
            }
        }
    } else {
        if (await shouldAddButtonToImage(image, src)) {
            if (shouldHideFromGrid(src)) {
                jQuery(image).wrap("<div class='fifu_play icon'></div>");
                jQuery(image).after("<span class='dashicons dashicons-format-video icon'></span>");
            } else {
                if (!jQuery(image).parent().parent().parent().hasClass('lSGallery')) {
                    if (is_background) {
                        jQuery(image).addClass('fifu_video_thumb_bg');
                        if (fifuVideoVars.fifu_is_play_type_inline) { // inline
                            // for WP Grid Builder plugin
                            if (jQuery(image).parent().hasClass('wpgb-handle-lb')) {
                                jQuery(image).unwrap();
                                jQuery(image).next().remove();
                            }

                            jQuery(image).append(`
                                <div class='fifu_play_bg' href='/' onclick='return false'></div>
                            `);
                            await registerReplaceOnClick(jQuery(image).children().first()); //div.fifu_play_bg
                        } else { // lightbox                            
                            jQuery(image).after(`
                                <div class='fifu_play_bg' data-fancybox href='${url}' data-type='${dataType}' ${mouseenter}></div>
                            `);
                        }
                    } else {
                        // Helper to add play button after image is loaded and visible
                        async function addPlayButton(img) {
                            jQuery(img).wrap("<div class='fifu_play start' " + background_style + "></div>");
                            let $btn;
                            if (fifuVideoVars.fifu_is_play_type_inline) {
                                jQuery(img).after(`
                                    <div class='fifu_link' href='/' onclick='return false'>
                                        <span class='dashicons dashicons-controls-play fifubtn'></span>
                                    </div>
                                `);
                                $btn = jQuery(img).parent().find('.fifubtn');
                                await registerReplaceOnClick(jQuery(img).parent());
                            } else {
                                jQuery(img).after(`
                                    <div class='fifu_link' data-fancybox href='${url}' data-type='${dataType}' ${mouseenter}>
                                        <span class='dashicons dashicons-controls-play fifubtn'></span>
                                    </div>
                                `);
                                $btn = jQuery(img).parent().find('.fifubtn');
                            }
                            // Trigger fade-in
                            setTimeout(function () {
                                $btn.css('opacity', '0.80');
                            }, 50);
                        }

                        if (jQuery(image)[0].complete && jQuery(image).is(':visible')) {
                            await addPlayButton(image);
                        } else {
                            jQuery(image).on('load', async function () {
                                if (jQuery(image).is(':visible')) {
                                    await addPlayButton(image);
                                }
                            });
                        }
                    }
                }
            }

            if (fifuVideoVars.fifu_is_elementor_active) {
                parentClass = jQuery(image).parent().parent().attr('class');
                if (parentClass && parentClass.startsWith('elementor-'))
                    jQuery(image).parent().css('position', 'unset')
            }
        } else {
            if (is_suvideo_img(src) || await is_otf_video_img(src) || await is_otf_audio_img(src)) {
                // add z-index
                if (!is_background)
                    jQuery(image).parent().css('z-index', fifuVideoVars.fifu_video_zindex);

                // remove hyperlink
                if (jQuery(image).parent().is('a')) {
                    if (!jQuery(image).parent().hasClass('fifu_link')) {
                        jQuery(image).unwrap();
                    }
                }
                // add pointer
                jQuery(image).css('cursor', 'pointer');

                if (fifuVideoVars.fifu_is_play_type_inline) { // inline
                    if (jQuery(image).hasClass('fifu-video') || is_background || await is_otf_video_img(src) || await is_otf_audio_img(src)) {
                        await registerReplaceOnClick(image);
                    }
                } else { // lightbox
                    jQuery(image).wrap(`
                        <a class='fifu_link' data-fancybox href='${url}' data-type='${dataType}'></a>
                    `);
                }
            } else {
                if (fifuVideoVars.fifu_mouse_video_enabled) {
                    // remove hyperlink
                    if (jQuery(image).parent().is('a')) {
                        if (!jQuery(image).parent().hasClass('fifu_link')) {
                            jQuery(image).unwrap();
                        }
                    }

                    if (!is_background)
                        jQuery(image).parent().css('z-index', fifuVideoVars.fifu_video_zindex);

                    await registerReplaceOnClick(image);
                }
            }
        }

        if (fifuVideoVars.fifu_later_enabled) {
            if (!(await shouldAddButtonToImage(image, src)) || (fifuVideoVars.fifu_play_hide_grid && fifuVideoVars.fifu_is_home && !fifuVideoVars.fifu_is_shop)) {
                jQuery(image).wrap("<div class='fifu_play start' " + background_style + "></div>");
                button = "";
            } else {
                button = "<span class='dashicons dashicons-controls-play fifubtn'></span>";
            }

            icon = fifuWatchLaterQueue.has(src) ? 'yes' : 'clock';
            jQuery(image).after(`
                <div class='fifu_link' href='/' onclick='return false'>
                    ${button}
                    <span title="${fifuVideoVars.text_later}" class='dashicons dashicons-${icon} icon w-later-thumb' thumb='${src}'></span>
                    <span title="${fifuVideoVars.text_queue}" class='dashicons dashicons-playlist-video icon w-later-thumb' thumb-pl='${src}' style='top:40px'></span>
                </div>`
                    );
            fifu_add_event_w_later_thumb(src);
        }

        if (is_bunny_video_img(src))
            fifu_bunny_preview(image, src);
    }
}

function shouldReplaceImageWithVideo(src) {
    if (fifu_should_autoplay())
        return true;

    if (is_vimeo_img(src) && fifuVideoVars.fifu_video_background_enabled) {
        if (!fifuVideoVars.fifu_video_background_single_enabled)
            return true;
        if (fifuVideoVars.fifu_video_background_single_enabled && fifuVideoVars.fifu_url == src)
            return true;
    }

    if (!fifuVideoVars.fifu_video_thumb_display_home &&
            !fifuVideoVars.fifu_video_thumb_display_page &&
            !fifuVideoVars.fifu_video_thumb_display_post &&
            !fifuVideoVars.fifu_video_thumb_display_cpt)
        return true;

    return false;
}

async function shouldAddButtonToImage(image, src) {
    if (jQuery(image).parent().attr('class') == 'fifu_play')
        return false;

    if (jQuery(image).hasClass('fifu_video_thumb_bg'))
        return false;

    if (fifuVideoVars.fifu_should_hide)
        return false;

    if (!fifuVideoVars.fifu_play_button_enabled)
        return false;

    if (is_suvideo_img(src) || await is_otf_video_img(src) || await is_otf_audio_img(src))
        return false;

    return true;
}

function shouldHideFromGrid(src) {
    if (fifuVideoVars.fifu_url == src)
        return false;

    if (fifuVideoVars.fifu_play_hide_grid && fifuVideoVars.fifu_is_home && !fifuVideoVars.fifu_is_shop)
        return true;

    if (fifuVideoVars.fifu_play_hide_grid_wc && (fifuVideoVars.fifu_is_shop || fifuVideoVars.fifu_is_product_category))
        return true;

    return false;
}

function isImageWidthSufficient($image) {
    var minWidth = fifuVideoVars.fifu_video_min_width;
    var width = $image[0].clientWidth;
    if (width === 0) {
        width = $image.parent()[0].clientWidth;
    }
    return width >= minWidth;
}

function getThumbnailSelectors() {
    var selectors;

    if (fifuVideoVars.fifu_is_home)
        selectors = 'img.fifu-video,div.fifu-slider>div>div>ul>li>img';
    else
        selectors = 'img';

    if (!fifuVideoVars.fifu_is_content_views_pro_active)
        selectors += ',[style*="background-image"]';

    return selectors;
}


/* legacy */

async function registerReplaceOnClick(selector) {
    // no effect on fifu product gallery
    if (fifuVideoVars.fifu_is_product && jQuery(selector).parents('div.fifu-slider').length && fifuVideoVars.fifu_woo_lbox_enabled) {
        return;
    }

    let events = "click";
    if (fifuVideoVars.fifu_mouse_video_enabled)
        events += " mouseenter";

    jQuery(selector).on(events, async function ($) {
        $.preventDefault();
        $.stopPropagation();

        // Declare all variables used in this function
        let tag, selector, src, is_background, w, h_div, h_iframe, extra, img, greatGrandFatherClass, url, is_video_file, is_audio_file, autoplay, controls, permalink, permalink_onclick, iframeId, iframe_class, image_url, muted, type, poster, video;

        // check if has clicked on the play button instead of the thumbnail
        if (jQuery($.target).attr('class').includes('dashicons') && !jQuery($.target).attr('class').includes('controls-play'))
            return;

        tag = jQuery(this)[0].tagName == 'IMG' ? jQuery(this) : jQuery(this).find('img');
        if (tag.length) {
            selector = 'img';
            src = tag[0].src;
            is_background = false;
        } else {
            is_background = true;
            tag = jQuery(this);
            let bgImage = tag.css('background-image');

            // Check if background-image exists and is not 'none'
            if (!bgImage || bgImage === 'none') {
                // Try parent element
                tag = tag.parent();
                bgImage = tag.css('background-image');
                if (!bgImage || bgImage === 'none') {
                    return; // No background image found
                }
            }

            // Parse the background-image URL safely
            const urlMatch = bgImage.match(/url\(['"]?([^'"]*?)['"]?\)/);
            if (!urlMatch || !urlMatch[1]) {
                return; // No valid URL found in background-image
            }

            src = urlMatch[1];
        }

        w = 'width:' + tag[0].clientWidth + 'px';
        h_div = 'height:' + tag[0].clientHeight + 'px';
        if (is_spotify_video_img(src)) {
            h_iframe = 'height:352px';
            extra = 'display: flex; justify-content: center; align-items: center;';
        } else {
            h_iframe = h_div;
            extra = '';
        }

        if (!fifuVideoVars.fifu_is_product) {
            // to keep bottom padding
            if (!is_background && ((!fifuVideoVars.fifu_is_home && !fifuVideoVars.fifu_is_post) || fifuVideoVars.fifu_is_shop))
                jQuery(this).after('<img src="" style="width:0px !important; height:0px !important; display:block !important"/>');
        } else {
            // to show the image on woocommerce lightbox
            if (fifuVideoVars.fifu_woo_lbox_enabled) {
                img = tag[0];
                jQuery(this).after(img);
                jQuery(img).css('height', '0px');
                jQuery(img).css('display', 'block');
            }
        }

        greatGrandFatherClass = jQuery(this).parent().parent().parent().attr('class');
        if (fifuVideoVars.fifu_is_elementor_active && greatGrandFatherClass && greatGrandFatherClass.startsWith('elementor-post'))
            jQuery(this).parent().attr('class', '');

        url = await video_url(src);
        is_video_file = is_custom_video_img(src);
        is_audio_file = await is_otf_audio_img(src) || is_audio_img(src);
        // add parameters
        url = add_parameters(url, src);
        if (is_sprout_video(url))
            autoplay = 'autoPlay=true';
        else if (is_rumble_video(url))
            autoplay = 'pub=7a20&rel=5&autoplay=2';
        else if (is_bunny_video(url) || is_googledrive_video(url))
            autoplay = '';
        // else if (is_mega_video(url))
        // autoplay = '!1m1a';
        else if (!is_local_video_img(src) && !is_video_file && !is_audio_file)
            autoplay = 'autoplay=1';
        url += autoplay ? parameter_char(url) + autoplay : '';
        controls = fifuVideoVars.fifu_video_controls ? '' : ' fifu_no_controls';

        permalink = fifu_get_permalink(src);
        permalink_onclick =
                permalink &&
                fifuVideoVars.fifu_mouse_video_enabled &&
                !fifuVideoVars.fifu_video_controls &&
                window.location.href != permalink ?
                `onclick="window.location.href='${permalink}'"` : '';

        iframeId = simpleHash(url);
        iframe_class = 'fifu_iframe';

        // mov files are not supported by iframe, except in firefox
        image_url = src;
        if (is_local_video_img(image_url) || is_video_file || is_audio_file) {
            let muted = fifu_should_mute() ? 'muted' : '';
            autoplay = 'autoplay';
            let loop = fifu_should_loop() ? 'loop' : '';
            controls = fifuVideoVars.fifu_video_controls ? 'controls' : '';
            let type = url.includes('mp4') ? 'type="video/mp4"' : (url.includes('mp3') ? 'type="audio/mpeg"' : '');
            let poster = is_audio_file ? `poster="${image_url}"` : '';

            // Create video element without src
            let $video = jQuery(
                    `<video id="${iframeId}" style="${w};${h_iframe};background:url(https://storage.googleapis.com/featuredimagefromurl/video-loading.gif) no-repeat center center black;" ${muted} ${autoplay} ${controls} ${loop} playsinline ${type} ${poster} ${permalink_onclick}></video>`
                    );

            if (is_background) {
                tag.append($video);
                // Set src after appending
                setTimeout(function () {
                    $video.attr('src', url);
                }, 10);

                if (!(await is_otf_video_img(image_url)) && !(await is_otf_audio_img(image_url))) {
                    tag.unwrap();
                    tag.children().first().remove();
                }
            } else {
                jQuery(this).replaceWith($video);
                // Set src after replacing
                setTimeout(function () {
                    $video.attr('src', url);
                }, 10);

                if (url.includes('m3u8'))
                    fifu_init_hls_player(url, iframeId)
            }

            if (fifuVideoVars.fifu_mouse_video_enabled)
                fifu_autoplay_mouseover_file(iframeId, true);

            return;
        }

        if (is_wpcom_video_img(image_url) && image_url.includes('mp4')) {
            url = url.split('?')[0];
            let $video = jQuery(
                    `<div style="background:url(https://storage.googleapis.com/featuredimagefromurl/video-loading.gif) no-repeat center center black;${h_div}">
                    <video id="${iframeId}" class="${controls}" style="${w};${h_iframe}" controls autoplay muted playsinline ${permalink_onclick}></video>
                </div>`
                    );
            if (is_background) {
                tag.append($video);
                // Set src after appending, with a small delay
                setTimeout(function () {
                    $video.find('video').attr('src', url);
                }, 10);
                tag.unwrap();
                tag.next().remove();
            } else {
                jQuery(this).replaceWith($video);
                // Set src after replacing, with a small delay
                setTimeout(function () {
                    $video.find('video').attr('src', url);
                }, 10);
            }
            return;
        }

        video = `
            <div style="background:url(https://storage.googleapis.com/featuredimagefromurl/video-loading.gif) no-repeat center center black;${h_div};${extra}" ${permalink_onclick}>
                <iframe id="${iframeId}" class="${controls} ${iframe_class}" src="${url}" style="${w};${h_iframe}" allowfullscreen frameborder="0" allow="autoplay" thumb="${image_url}"></iframe>
            </div>
        `;
        if (is_background) {
            tag.append(video);
            tag.find('.fifu_play_bg').remove();
        } else
            jQuery(this).replaceWith(video);

        if (fifuVideoVars.fifu_mouse_video_enabled) {
            fifu_autoplay_mouseover_youtube(iframeId);
            fifu_autoplay_mouseover_vimeo(iframeId);
        } else {
            fifu_autoplay_youtube_now(iframeId, url);
        }

        if (fifuVideoVars.fifu_later_enabled) {
            setTimeout(function () {
                fifu_add_watch_later(iframeId);
            }, 500);
        }
    });
}

async function is_video_img($src) {
    $src = fifu_get_original_img_src($src);
    return !$src ? null : is_suvideo_img($src) || is_youtube_img($src) || is_vimeo_img($src) || is_cloudinary_video_img($src) || is_tumblr_video_img($src) || is_local_video_img($src) || is_publitio_video_img($src) || is_gag_video_img($src) || is_wpcom_video_img($src) || is_tiktok_video_img($src) || is_googledrive_video_img($src) || is_mega_video_img($src) || is_bunny_video_img($src) || is_bitchute_video_img($src) || is_brighteon_video_img($src) || is_soundcloud_video_img($src) || is_spotify_video_img($src) || is_amazon_video_img($src) || is_jwplayer_img($src) || is_sprout_img($src) || is_rumble_img($src) || is_dailymotion_img($src) || is_twitter_img($src) || is_cloudflarestream_img($src) || is_odysee_video_img($src) || is_custom_video_img($src) || is_audio_img($src) || await is_otf_video_img($src) || await is_otf_audio_img($src);
}

async function is_otf_video_img($src) {
    if (is_otf_img($src)) {
        let $video_src = await get_otf_video_url($src);
        if ($video_src)
            return true;
    }
    return false;
}

async function is_otf_audio_img($src) {
    if (is_otf_img($src)) {
        let $audio_url = await get_otf_audio_url($src);
        if ($audio_url)
            return true;
    }
    return false;
}

async function is_otf_permalink_img($src) {
    if (is_otf_img($src)) {
        let $permalink_url = await get_otf_permalink_url($src);
        if ($permalink_url)
            return true;
    }
    return false;
}

function is_youtube_img($src) {
    return $src.includes('img.youtube.com');
}

function is_vimeo_img($src) {
    return $src.includes('i.vimeocdn.com');
}

function is_cloudinary_video_img($src) {
    return $src.includes('res.cloudinary.com') && $src.includes('/video/');
}

function is_tumblr_video_img($src) {
    return $src.includes('tumblr.com');
}

function is_local_video_img($src) {
    return $src.includes(window.location.hostname) && $src.includes(fifuVideoVars.uploadDir) && $src.includes('-fifu-');
}

function is_publitio_video_img($src) {
    return $src.includes('publit.io');
}

function is_gag_video_img($src) {
    return $src.includes('9cache.com');
}

function is_wpcom_video_img($src) {
    return $src.includes('videos.files.wordpress.com') && $src.includes('.jpg');
}

function is_tiktok_video_img($src) {
    return $src.includes('tiktokcdn.com');
}

function is_googledrive_video_img($src) {
    return $src.includes('/fifu/videothumb/googledrive/');
}

function is_mega_video_img($src) {
    return $src.includes('/fifu/videothumb/mega/');
}

function is_bunny_video_img($src) {
    return $src.includes('b-cdn.net') && $src.includes('thumbnail');
}

function is_bitchute_video_img($src) {
    return $src.includes('bitchute.com/live');
}

function is_brighteon_video_img($src) {
    return $src.includes('photos.brighteon.com') || $src.includes('video.brighteon.com');
}

function is_soundcloud_video_img($src) {
    return $src.includes('sndcdn.com');
}

function is_spotify_video_img($src) {
    return $src.includes('i.scdn.co');
}

function is_amazon_video_img($src) {
    return $src.includes('m.media-amazon.com') && $src.includes('SX1600_.');
}

function is_jwplayer_img($src) {
    return $src.includes('jwplatform.com');
}

function is_sprout_img($src) {
    return $src.includes('cdn-thumbnails.sproutvideo.com');
}

function is_rumble_img($src) {
    return $src.includes('rmbl.ws') || $src.includes('rumble.cloud') || $src.includes('1a-1791.com');
}

function is_dailymotion_img($src) {
    return $src.includes('dmcdn.net');
}

function is_twitter_img($src) {
    return $src.includes('pbs.twimg.com');
}

function is_cloudflarestream_img($src) {
    return $src.includes('cloudflarestream.com') && $src.includes('/thumbnails/');
}

function is_suvideo_img($src) {
    return $src.includes('cdn.fifu.app') && $src.includes('video-thumb=');
}

function is_odysee_video_img($src) {
    return $src.includes('thumbnails.odycdn.com');
}

function is_custom_video_img($src) {
    if (typeof fifuVideoThumbVars === 'undefined' ||
            typeof fifuVideoThumbVars.customvideos === 'undefined' ||
            fifuVideoThumbVars.customvideos === null ||
            typeof fifuVideoThumbVars.customvideos !== 'object') {
        return false;
    }

    if (fifuVideoThumbVars['customvideos'][$src])
        return true;

    if (fifuVideoThumbVars.fifu_photon)
        $src = fifuVideoThumbVars['cdn'][$src];

    if (fifuVideoThumbVars['customvideos'][$src])
        return true;

    return false;
}

function is_audio_img($src) {
    if (typeof fifuVideoThumbVars === 'undefined' ||
            typeof fifuVideoThumbVars.audios === 'undefined' ||
            fifuVideoThumbVars.audios === null ||
            typeof fifuVideoThumbVars.audios !== 'object') {
        return false;
    }

    if (fifuVideoThumbVars['audios'][$src])
        return true;

    if (fifuVideoThumbVars.fifu_photon)
        $src = fifuVideoThumbVars['cdn'][$src];

    if (fifuVideoThumbVars['audios'][$src])
        return true;

    return false;
}

function is_sprout_video($src) {
    return $src.includes('videos.sproutvideo.com');
}

function is_googledrive_video($src) {
    return $src.includes('drive.google.com/file');
}

function is_mega_video($src) {
    return $src.includes('mega.nz');
}

function is_bunny_video($src) {
    return $src.includes('video.bunnycdn.com');
}

function is_bitchute_video($src) {
    return $src.includes('www.bitchute.com');
}

function is_brighteon_video($src) {
    return $src.includes('www.brighteon.com');
}

function is_soundcloud_video($src) {
    return $src.includes('soundcloud.com');
}

function is_spotify_video($src) {
    return $src.includes('spotify.com');
}

function is_amazon_video($src) {
    return $src.includes("m.media-amazon.com") && $src.includes(".mp4");
}

function is_rumble_video($src) {
    return $src.includes('rumble.com');
}

function is_dailymotion_video($src) {
    return $src.includes('dailymotion.com');
}

function is_twitter_video($src) {
    return $src.includes('twitter.com');
}

function is_cloudflarestream_video($src) {
    return $src.includes('cloudflarestream.com');
}

function video_id($src) {
    if (is_youtube_img($src))
        return youtube_id($src);
    if (is_vimeo_img($src))
        return vimeo_id($src);
    return null;
}

function youtube_parameter($src) {
    return $src && $src.includes('?') ? $src.split('?')[1] : '';
}

function is_jetpack_src($src) {
    return $src.includes('wp.fifu.app/');
}

function is_odycdn_src($src) {
    return $src.includes('thumbnails.odycdn.com/');
}

function is_vimeo_src($src) {
    return $src.includes('vimeo');
}

function is_youtube_src($src) {
    return $src.includes('youtu');
}

function youtube_id($src) {
    return $src.split('/vi/')[1].split('/')[0];
}

function vimeo_id($src) {
    return $src.split('?')[1].replace('/', '?h=');
}

async function video_url($src) {
    let $originalSrc = $src;

    if (is_suvideo_img($src))
        return await suvideo_url($src);

    if (await is_otf_video_img($src))
        return await get_otf_video_url($src);

    if (await is_otf_audio_img($src))
        return await get_otf_audio_url($src);

    $src = fifu_get_original_img_src($src);

    if (is_jetpack_src($src)) {
        $src = fifuDecodePubcdnUrl($src);
    }

    $src = $src.split(/[\?\&]fifu-/)[0];
    if (is_custom_video_img($originalSrc))
        return custom_url($originalSrc);
    if (is_audio_img($originalSrc))
        return audio_url($originalSrc);
    if (is_youtube_img($src))
        return youtube_url($src);
    if (is_vimeo_img($src))
        return vimeo_url($src);
    if (is_cloudinary_video_img($src))
        return cloudinary_url($src);
    if (is_tumblr_video_img($src))
        return tumblr_url($src);
    if (is_local_video_img($src))
        return local_url($src);
    if (is_publitio_video_img($src))
        return publitio_url($src);
    if (is_gag_video_img($src))
        return gag_url($src);
    if (is_wpcom_video_img($src))
        return wpcom_url($src);
    if (is_tiktok_video_img($src))
        return tiktok_url($src);
    if (is_googledrive_video_img($src))
        return googledrive_url($src);
    if (is_mega_video_img($src))
        return mega_url($src);
    if (is_bunny_video_img($src))
        return bunny_url($src);
    if (is_bitchute_video_img($src))
        return bitchute_url($src);
    if (is_brighteon_video_img($src))
        return brighteon_url($src);
    if (is_soundcloud_video_img($src))
        return soundcloud_url($src);
    if (is_spotify_video_img($src))
        return spotify_url($src);
    if (is_amazon_video_img($src))
        return amazon_url($src);
    if (is_jwplayer_img($src))
        return jwplayer_url($src);
    if (is_sprout_img($src))
        return sprout_url($src);
    if (is_rumble_img($src))
        return rumble_url($src);
    if (is_dailymotion_img($src))
        return dailymotion_url($src);
    if (is_twitter_img($src))
        return twitter_url($src);
    if (is_cloudflarestream_img($src))
        return cloudflarestream_url($src);
    if (is_odysee_video_img($src))
        return odysee_url($src);

    if (fifuVideoThumbVars &&
            fifuVideoThumbVars['thumbs'] &&
            $originalSrc in fifuVideoThumbVars['thumbs']) {
        return fifuVideoThumbVars['thumbs'][$originalSrc];
    }

    return null;
}

function isOdycdnThumbnail(url) {
    const pattern = /^https:\/\/thumbnails\.odycdn\.com/;
    const match = url.match(pattern);
    return match !== null;
}

function youtube_url(src) {
    if (isOdycdnThumbnail(src))
        src = src.split('/plain/')[1];

    embed_url = fifuVideoThumbVars['thumbs'][src];

    if (!embed_url)
        return;

    if (fifuVideoVars.fifu_privacy_enabled)
        embed_url = embed_url.replace('www.youtube.com', 'www.youtube-nocookie.com');

    domain = fifuVideoVars.fifu_privacy_enabled ? 'www.youtube-nocookie.com' : 'www.youtube.com';

    param = youtube_parameter(embed_url);
    param_char = param ? '&' : '';

    return embed_url.split('?')[0] + '?' + param + param_char + 'enablejsapi=1&rel=0';
}

function vimeo_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function cloudinary_url($src) {
    return $src.replace('jpg', 'mp4');
}

function tumblr_url($src) {
    $tmp = $src.replace('https://78.media.tumblr.com', 'https://vt.media.tumblr.com');
    return $tmp.replace('_smart1.jpg', '.mp4');
}

function local_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function publitio_url($src) {
    return $src.replace('jpg', 'mp4');
}

function gag_url($src) {
    return $src.split('_')[0] + '_460svvp9.webm';
}

function wpcom_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function tiktok_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function googledrive_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function mega_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function bunny_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function bitchute_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function brighteon_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function soundcloud_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function spotify_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function amazon_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function jwplayer_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function sprout_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function rumble_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function dailymotion_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function twitter_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function cloudflarestream_url($src) {
    return $src.replace('thumbnails/thumbnail.jpg', 'iframe');
}

function odysee_url($src) {
    return fifuVideoThumbVars['thumbs'][$src];
}

function custom_url($src) {
    if (fifuVideoThumbVars.fifu_photon)
        $src = fifuVideoThumbVars['cdn'][$src];

    return fifuVideoThumbVars['customvideos'][$src];
}

function audio_url($src) {
    if (fifuVideoThumbVars.fifu_photon)
        $src = fifuVideoThumbVars['cdn'][$src];

    return fifuVideoThumbVars['audios'][$src];
}

async function suvideo_url($src) {
    let aux = $src.split('&resize=')[0];
    aux = fifuVideoThumbVars['thumbs'][aux];
    if (aux)
        return aux;

    aux = $src.split('video-thumb=')[1];
    aux = aux.split('&resize=')[0];
    return await video_url(aux);
}

function fifu_autoplay_mouseover_vimeo(iframeId) {
    let enabled = fifuVideoVars.fifu_mouse_video_enabled;
    if (!enabled)
        return;

    const iframe = jQuery(`#${iframeId}`);
    let src = iframe.attr('src');
    if (src && !src.includes("vimeo.com"))
        return;

    setTimeout(function () {
        let vimeoPlayer = new Vimeo.Player(jQuery(`#${iframeId}`));

        // Check if this iframe is inside a background image container
        const parentDiv = iframe.parent();
        const isBackgroundImage = parentDiv.css('background-image') && parentDiv.css('background-image') !== 'none';

        // Use the appropriate target element for mouse events
        const targetElement = isBackgroundImage ? parentDiv : iframe;

        targetElement.on("mouseover", function () {
            vimeoPlayer.play();
            if (!!window.chrome)
                vimeoPlayer.setVolume(0);
        }).mouseout(function () {
            vimeoPlayer.pause();
        });
    }, 500);
}

function fifu_autoplay_mouseover_file(videoId, is_video_file) {
    let enabled = fifuVideoVars.fifu_mouse_video_enabled;
    if (!enabled)
        return;

    if (!is_video_file)
        return;

    var videoElement = document.getElementById(videoId);

    if (!videoElement || !videoElement.tagName || videoElement.tagName.toLowerCase() !== 'video')
        return;

    videoElement.addEventListener('mouseover', function () {
        videoElement.play();
    });

    videoElement.addEventListener('mouseout', function () {
        videoElement.pause();
    });
}

var fifuPlayers = fifuPlayers ? fifuPlayers : {};
var vimeo_players = [];

function fifu_autoplay_mouseover_youtube(iframeId) {
    let enabled = fifuVideoVars.fifu_mouse_video_enabled;
    if (!enabled)
        return;

    const iframe = jQuery(`#${iframeId}`);
    let src = iframe.attr('src');
    if (src === undefined || (src && !src.includes("youtu")))
        return;

    var fifuPlayers = fifuPlayers ? fifuPlayers : {};

    window.YT.ready(function () {
        fifuPlayers[iframeId] = new YT.Player(iframeId);
    });

    setTimeout(function () {
        // Check if this iframe is inside a background image container
        const parentDiv = iframe.parent();
        const isBackgroundImage = parentDiv.css('background-image') && parentDiv.css('background-image') !== 'none';

        // Use the appropriate target element for mouse events
        const targetElement = isBackgroundImage ? parentDiv : iframe;

        targetElement.on("mouseover", function () {
            if (typeof fifuPlayers[iframeId].playVideo === "function") {
                fifuPlayers[iframeId].playVideo();
                if (!!window.chrome)
                    fifuPlayers[iframeId].mute();
            }
        }).mouseout(function () {
            if (typeof fifuPlayers[iframeId].pauseVideo === "function") {
                fifuPlayers[iframeId].pauseVideo();
            }
        });
    }, 500);
}

function fifu_autoplay_youtube_now(iframeId, url) {
    const iframe = jQuery(`#${iframeId}`);
    if (iframe.length > 0 && iframe[0].src === url && iframe[0].src.includes("youtu") && iframe[0].src.includes("autoplay=1") && typeof window.YT !== 'undefined') {
        window.YT.ready(function () {
            fifuPlayers[iframeId] = new YT.Player(iframeId, {
                events: {
                    'onReady': fifuOnPlayerReady,
                    'onStateChange': fifuOnPlayerStateChange
                }
            });
        });
    }
}

function fifuOnPlayerReady(event) {
    event.target.playVideo();
}

function fifuOnPlayerStateChange(event) {
}

function add_parameters(url, src) {
    let loop, autoplay, video_background;

    src = fifu_get_original_img_src(src);

    loop = fifu_should_loop();
    autoplay = fifu_should_autoplay();
    video_background = fifuVideoVars.fifu_video_background_enabled && !(fifuVideoVars.fifu_video_background_single_enabled && fifuVideoVars.fifu_url != src);

    if ((loop || autoplay))
        url += parameter_char(url) + 'autopause=1';

    if (autoplay) {
        if (is_rumble_video(url))
            url += parameter_char(url) + 'pub=7a20&rel=5&autoplay=2';
        else
            url += parameter_char(url) + 'autoplay=1';
    }

    if (is_youtube_img(src)) {
        if (fifu_should_mute())
            url += parameter_char(url) + 'mute=1';
        if (!fifuVideoVars.fifu_video_controls)
            url += parameter_char(url) + 'controls=0';
    } else if (is_vimeo_img(src)) {
        if (fifu_should_mute())
            url += parameter_char(url) + 'muted=1';
        if (video_background)
            url += parameter_char(url) + 'background=1';
    }

    if (loop) {
        url += parameter_char(url) + 'loop=1';
        if (is_youtube_img(src))
            url += parameter_char(url) + 'playlist=' + video_id(src);
    }

    return url;
}

function parameter_char(url) {
    return url.includes('?') ? '&' : '?';
}

function fifu_should_autoplay() {
    let image_url, same_url, autoplay_single, autoplay_front, autoplay_elsewhere;

    if (typeof src !== 'undefined') {
        if (src.includes('wp.fifu.app')) {
            image_url = fifuDecodePubcdnUrl(src);
            same_url = fifuVideoVars.fifu_url && fifuVideoVars.fifu_url.split('?')[0] == image_url.split('?')[0];
        } else
            same_url = fifuVideoVars.fifu_url && fifuVideoVars.fifu_url == src;
    } else {
        same_url = true;
    }

    autoplay_single = fifuVideoVars.fifu_autoplay_enabled && same_url && !fifuVideoVars.fifu_is_front_page && !fifuVideoVars.fifu_is_home_or_shop;
    autoplay_front = fifuVideoVars.fifu_autoplay_front_enabled && fifuVideoVars.fifu_is_front_page;
    autoplay_elsewhere = fifuVideoVars.fifu_autoplay_elsewhere_enabled && !fifuVideoVars.fifu_is_front_page;
    return autoplay_single || autoplay_front || autoplay_elsewhere;
}

function fifu_should_mute() {
    return fifuVideoVars.fifu_is_mobile ? fifuVideoVars.fifu_video_mute_mobile_enabled : fifuVideoVars.fifu_video_mute_enabled;
}

function fifu_should_loop() {
    return fifuVideoVars.fifu_loop_enabled;
}

const simpleHash = str => {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = (hash << 5) - hash + char;
        hash &= hash; // Convert to 32bit integer
    }
    return new Uint32Array([hash])[0].toString(36);
};

// bunny (animated preview)
function fifu_bunny_preview(image, src) {
    jQuery(image).on('mouseover', function () {
        jQuery(image).attr('src', src.replace(/thumbnail.*jpg/, 'preview.webp'));
    });

    jQuery(image).on('mouseout', function () {
        jQuery(image).attr('src', src);
    });
}

/*** AJAX ***/

(function () {
    // Store the original XMLHttpRequest
    const originalXHR = window.XMLHttpRequest;

    // Override XMLHttpRequest
    window.XMLHttpRequest = function () {
        const xhr = new originalXHR();
        const originalOpen = xhr.open;
        let requestMethod, requestUrl;

        // Intercept the open method
        xhr.open = function (method, url) {
            requestMethod = method;
            requestUrl = url;
            return originalOpen.apply(this, arguments);
        };

        // Intercept the send method
        const originalSend = xhr.send;
        xhr.send = function (data) {
            // Dispatch event when request starts
            document.dispatchEvent(new CustomEvent('ajaxRequestStart', {
                detail: {
                    method: requestMethod,
                    url: requestUrl,
                    data: data
                }
            }));

            // Add event listeners for completion
            xhr.addEventListener('load', function () {
                waitForPaginationData = true;
                document.dispatchEvent(new CustomEvent('ajaxRequestComplete', {
                    detail: {
                        method: requestMethod,
                        url: requestUrl,
                        status: xhr.status,
                        response: xhr.response
                    }
                }));
            });

            xhr.addEventListener('error', function () {
                console.log('error')
                document.dispatchEvent(new CustomEvent('ajaxRequestError', {
                    detail: {
                        method: requestMethod,
                        url: requestUrl,
                        status: xhr.status
                    }
                }));
            });

            xhr.addEventListener('abort', function () {
                console.log('abort')
                document.dispatchEvent(new CustomEvent('ajaxRequestAbort', {
                    detail: {
                        method: requestMethod,
                        url: requestUrl
                    }
                }));
            });

            return originalSend.apply(this, arguments);
        };

        return xhr;
    };
})();

function checkForImagesInAjaxResponse() {
    document.addEventListener('ajaxRequestComplete', async function (e) {
        try {
            const response = e.detail.response;
            let htmlContent = '';

            // Handle JSON-encoded HTML responses
            if (typeof response === 'string') {
                try {
                    const jsonResponse = JSON.parse(response);
                    htmlContent = jsonResponse.html || jsonResponse.content || response;
                } catch {
                    htmlContent = response;
                }
            } else if (response instanceof Document) {
                const serializer = new XMLSerializer();
                htmlContent = serializer.serializeToString(response);
            }

            // Parse HTML and extract image data
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlContent;

            const images = Array.from(tempDiv.querySelectorAll('img')).map(img => ({
                    src: img.getAttribute('src') || '',
                    alt: img.getAttribute('alt') || '',
                    width: Number(img.getAttribute('width')) || 0,
                    height: Number(img.getAttribute('height')) || 0
                }));

            if (images.length > 0) {
                hasPaginationData = true;

                if (typeof fifuVideoThumbVars === 'undefined')
                    fifuVideoThumbVars = {'thumbs': {}, 'cdn': {}, 'customvideos': {}, 'audios': {}, 'permalinks': {}};

                for (const img of images) {
                    if (img.src && is_otf_img(img.src)) {
                        let videoSrc, audioUrl, permalinkUrl;
                        if (await is_otf_video_img(img.src)) {
                            let videoSrc = await get_otf_video_url(img.src);
                            if (videoSrc)
                                fifuVideoThumbVars['thumbs'][img.src] = videoSrc;
                        }

                        if (await is_otf_audio_img(img.src)) {
                            let audioUrl = await get_otf_audio_url(img.src);
                            if (audioUrl)
                                fifuVideoThumbVars['audios'][img.src] = audioUrl;
                        }

                        if (await is_otf_permalink_img(img.src)) {
                            let permalinkUrl = await get_otf_permalink_url(img.src);
                            if (permalinkUrl)
                                fifuVideoThumbVars['permalinks'][img.src] = permalinkUrl;
                        }

                        jQuery("img[src='" + img.src + "']").addClass("fifu-video");
                    }
                }
            } else {
                hasPaginationData = false;
            }
        } catch (error) {
            console.error('Error processing response:', error);
        }
    });
}

var waitForPaginationData = false;
var hasPaginationData = false;

// Initialize the listener
checkForImagesInAjaxResponse();

/* block changes in the iframe style for other plugins/themes */

jQuery(document).ready(function () {
    // Function to enforce iframe styles based on custom data attributes
    function enforceIframeStyles(iframe) {
        var width = jQuery(iframe).attr('iframew');
        var height = jQuery(iframe).attr('iframeh');
        if (width)
            jQuery(iframe).css('width', width + 'px');
        if (height)
            jQuery(iframe).css('height', height + 'px');
    }

    // Select all iframes
    var iframes = jQuery('iframe');

    // Enforce initial styles based on custom attributes
    iframes.each(function () {
        enforceIframeStyles(this);
    });

    // MutationObserver to observe style changes in iframes
    var iframeObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                enforceIframeStyles(mutation.target);
            }
        });
    });

    // Observe each iframe for changes in the 'style' attribute
    iframes.each(function () {
        iframeObserver.observe(this, {
            attributes: true, // Monitor attributes changes
            attributeFilter: ['style'] // Specifically monitor the 'style' attribute
        });
    });
});


function fifu_get_original_img_src($src) {
    let prevCDN = $src;

    if (typeof fifuVideoThumbVars !== 'undefined' && fifuVideoThumbVars !== null) {
        if (typeof fifuVideoThumbVars['cdn'] === 'object' && $src in fifuVideoThumbVars['cdn']) {
            prevCDN = fifuVideoThumbVars['cdn'][$src];
        }
    }

    return prevCDN;
}

function fifu_get_permalink(src) {
    if (fifuVideoThumbVars.fifu_photon) {
        let original = fifuVideoThumbVars['cdn'][src];
        return fifuVideoThumbVars['permalinks'][original];
    }
    return fifuVideoThumbVars['permalinks'][src];
}

function fifu_adjust_for_theme(element) {
    let $el = jQuery(element);

    if (fifuVideoVars.fifu_is_photolio_active) {
        let $parent = $el.closest('a');
        if ($parent.length) {
            $parent.addClass('spiner-off');
        }
    }
}

function fifu_requires_adjustment() {
    return fifuVideoVars.fifu_is_photolio_active;
}

async function getImageHeaders(src) {
    try {
        const response = await fetch(src, {
            method: 'HEAD', // Doesn't download the image body
            cache: 'force-cache', // Reuses the cache from the <img> tag
            mode: 'cors'             // Enables CORS
        });

        // Create a dictionary to store all headers
        const headersDict = {};
        response.headers.forEach((value, key) => {
            headersDict[key] = value; // Add each header to the dictionary
        });

        return headersDict; // Return the dictionary of headers

    } catch (error) {
        console.error('Error reading headers:', error);
        return null;
    }
}

const otfVideoUrlCache = new Map();

async function get_otf_video_url($src) {
    if (otfVideoUrlCache.has($src))
        return otfVideoUrlCache.get($src);

    const headers = await getImageHeaders($src);
    const videoUrl = headers['x-video-src'];
    otfVideoUrlCache.set($src, videoUrl);
    return videoUrl;
}

const otfAudioUrlCache = new Map();

async function get_otf_audio_url($src) {
    if (otfAudioUrlCache.has($src))
        return otfAudioUrlCache.get($src);

    const headers = await getImageHeaders($src);
    const audioUrl = headers['x-audio-url'];
    otfAudioUrlCache.set($src, audioUrl);
    return audioUrl;
}

const otfPermalinkUrlCache = new Map();

async function get_otf_permalink_url($src) {
    if (otfPermalinkUrlCache.has($src))
        return otfPermalinkUrlCache.get($src);

    const headers = await getImageHeaders($src);
    const permalinkUrl = headers['x-permalink-url'];
    otfPermalinkUrlCache.set($src, permalinkUrl);
    return permalinkUrl;
}

function is_otf_img($src) {
    if (!fifuVideoVars.otfcdn)
        return false;
    return ['//img.', '//i0.fifu.app'].some(substring => $src.includes(substring));
}


function fifu_init_hls_player(url, iframeId) {
    // Function to initialize the HLS player once Hls is available
    function setupHlsPlayer() {
        if (Hls.isSupported()) {
            const videoElement = jQuery("#" + iframeId)[0];
            if (!videoElement)
                return;

            const hls = new Hls();
            hls.loadSource(url);
            hls.attachMedia(videoElement);
        }
    }

    // Check if Hls is already available
    if (typeof Hls !== 'undefined') {
        setupHlsPlayer();
    } else {
        // Load HLS.js library if not already loaded
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/hls.js@latest';
        script.onload = setupHlsPlayer;
        document.body.appendChild(script);
    }
}

function fifuDecodePubcdnUrl(url) {
    const parts = url.split('/');
    if (parts.length > 4) {
        let base64 = parts[4];
        // Pad base64 if needed
        base64 += '='.repeat((4 - (base64.length % 4)) % 4);
        // Replace URL-safe chars
        base64 = base64.replace(/-/g, '+').replace(/_/g, '/');
        try {
            return atob(base64);
        } catch (e) {
            return url;
        }
    }
    return url;
}

var fifuWatchLaterQueue = new Set();
var fifuWatchLaterMap = new Map();
// Cookies.set('fifu-watch-later-queue', JSON.stringify(Array.from(new Set()))); // reset
// Cookies.set('fifu-watch-later-map', JSON.stringify(Array.from(new Map()))); // reset

queueStr = Cookies.get('fifu-watch-later-queue');
mapStr = Cookies.get('fifu-watch-later-map');
if (queueStr && mapStr) {
    queue = JSON.parse(queueStr);
    map = JSON.parse(mapStr);
    if (queue.length > 0 && map.length > 0) {
        fifuWatchLaterQueue = new Set(JSON.parse(queueStr));
        fifuWatchLaterMap = new Map(JSON.parse(mapStr));
    }
}

// Display the thumb even when there is 1 video only
const style = jQuery('<style>');
style.html('.lg-outer.lg-single-item .lg-thumb-outer {display: inline !important}');
jQuery('head').append(style);

async function fifu_watch_later_action(icon) {
    if (jQuery(icon).hasClass('dashicons-clock')) {
        jQuery(icon).removeClass('dashicons-clock');
        jQuery(icon).addClass('dashicons-yes');
        let videoThumb = jQuery(icon).attr('thumb');
        let videoSrc = await video_url(videoThumb);
        fifuWatchLaterQueue.add(videoThumb);
        fifuWatchLaterMap.set(videoThumb, videoSrc);
    } else if (jQuery(icon).hasClass('dashicons-yes')) {
        jQuery(icon).removeClass('dashicons-yes');
        jQuery(icon).addClass('dashicons-clock');
        let videoThumb = jQuery(icon).attr('thumb');
        fifuWatchLaterQueue.delete(videoThumb);
        fifuWatchLaterMap.delete(videoThumb);
    } else if (jQuery(icon).hasClass('dashicons-playlist-video')) {
        fifu_open_watch_later_gallery();
    }
    fifuWatchLaterQueue.delete(null);
    fifuWatchLaterMap.delete(null);
    Cookies.set('fifu-watch-later-queue', JSON.stringify(Array.from(fifuWatchLaterQueue)));
    Cookies.set('fifu-watch-later-map', JSON.stringify(Array.from(fifuWatchLaterMap)));
}

function fifu_open_watch_later_gallery() {
    if (fifuWatchLaterQueue.size == 0 || fifuWatchLaterMap.size == 0)
        return;

    let opts = {
        toolbar: false,
        smallBtn: false,
        iframe: {
            preload: false
        },
    }
    jQuery.fancybox.open(`
        <div style="width:100%;max-width:${jQuery(window).height() * 0.75}px;padding:1px">
            <div id="fifu-lightbox-title" style="padding:5px;font-size:13px;font-weight:bold;text-align:center;padding:0px;"></div>
            <div style="width:100%;height:${jQuery(window).height() * 0.5}px" id="inline-watch-later-gallery-container" class="inline-watch-later-gallery-container"></div>
            <div id="fifu-lightbox-form"></div>
            <div id="fifu-lightbox-description"></div>
            <div id="fifu-lightbox-cf"></div>
        </div>
    `, opts);

    let arr = [];
    for (const url of fifuWatchLaterQueue) {
        const videoSrc = fifuWatchLaterMap.get(url);
        if (typeof videoSrc === 'string' && videoSrc) {
            arr.push({'src': videoSrc, 'thumb': url, 'iframe': 'true'});
        }
    }

    const $lgContainer = document.getElementById("inline-watch-later-gallery-container");

    const inlineGallery = lightGallery($lgContainer, {
        container: $lgContainer,
        dynamic: true,
        hash: false,
        closable: false,
        showMaximizeIcon: true,
        appendSubHtmlTo: ".lg-item",
        slideDelay: 0,
        plugins: [lgThumbnail],
        download: false,
        zoom: false,
        counter: false,
        dynamicEl: arr,
        thumbWidth: 60,
        thumbHeight: "40px",
        thumbMargin: 10,
        thumbnail: true,
        loadYouTubeThumbnail: false,
        licenseKey: typeof fifuLgVars !== 'undefined' ? fifuLgVars.fifu_key_lightgallery : null,
    });

    inlineGallery.openGallery();

    fifu_add_remove_button_gallery();

    setTimeout(function () {
        fifu_register_remove_action_gallery(inlineGallery);
    }, 500);
}

function fifu_add_remove_button_gallery() {
    jQuery('div.lg-thumb-item').each(function (index) {
        jQuery(this).css('position', 'relative');
        jQuery(this).append(`<span title="Remove" class="dashicons dashicons-no w-later-remove"></span>`);
    });
}

function fifu_register_remove_action_gallery(inlineGallery) {
    jQuery('div.lg-thumb-item > span').on('click touchstart', function (event) {
        let index = jQuery(this).parent().attr('data-lg-item-id');
        let thumb = [...fifuWatchLaterQueue][index];

        fifuWatchLaterQueue.delete(thumb);
        fifuWatchLaterMap.delete(thumb);
        Cookies.set('fifu-watch-later-queue', JSON.stringify(Array.from(fifuWatchLaterQueue)));
        Cookies.set('fifu-watch-later-map', JSON.stringify(Array.from(fifuWatchLaterMap)));
        jQuery(`span[thumb='${thumb}']`).click();

        if (fifuWatchLaterQueue.size > 0 && fifuWatchLaterMap.size > 0) {
            let galleryItems = JSON.parse(
                    JSON.stringify(inlineGallery.galleryItems),
                    );
            galleryItems.splice(index, 1);
            inlineGallery.updateSlides(galleryItems, index);
        } else {
            jQuery.fancybox.close();
        }

        setTimeout(function () {
            fifu_add_remove_button_gallery();
            fifu_register_remove_action_gallery(inlineGallery);
        }, 500);
    });
}

function fifu_add_watch_later(iframeId) {
    // don't duplicate
    if (jQuery(`iframe#${iframeId}`).attr('watch-later') == 'true')
        return;
    jQuery(`iframe#${iframeId}`).attr('watch-later', 'true');

    var $iframe = jQuery(`iframe#${iframeId}`);

    let icon = fifuWatchLaterQueue.has(src) ? 'yes' : 'clock';
    let thumb = $iframe.attr('thumb');

    var $submitBtn1 = jQuery('<span>', {
        class: `dashicons dashicons-${icon} icon w-later-iframe`,
    });
    $submitBtn1.attr('title', 'Watch later');
    $submitBtn1.attr('thumb', thumb);

    var $submitBtn2 = jQuery('<span>', {
        class: 'dashicons dashicons-playlist-video icon w-later-iframe',
    });
    $submitBtn2.attr('title', 'Queue');
    $submitBtn2.attr('thumb-pl', thumb);

    jQuery('body').append($submitBtn1);
    jQuery('body').append($submitBtn2);

    fifu_add_event_w_later_iframe(thumb);

    var iframeOffset = $iframe.offset();
    $submitBtn1.css({
        top: iframeOffset.top + 5,
        left: iframeOffset.left + $iframe.width() - $submitBtn1.width() - 17
    });

    $submitBtn2.css({
        top: iframeOffset.top + 40,
        left: iframeOffset.left + $iframe.width() - $submitBtn2.width() - 17
    });

    jQuery(window).resize(function () {
        iframeOffset = $iframe.offset();
        $submitBtn1.css({
            top: iframeOffset.top + 5,
            left: iframeOffset.left + $iframe.width() - $submitBtn1.width() - 17
        });

        $submitBtn2.css({
            top: iframeOffset.top + 40,
            left: iframeOffset.left + $iframe.width() - $submitBtn2.width() - 17
        });
    });

    // Wait for the element to finish loading before positioning the icons
    $iframe.on('load', function () {
        var iframeOffset = $iframe.offset();
        $submitBtn1.css({
            top: iframeOffset.top + 5,
            left: iframeOffset.left + $iframe.width() - $submitBtn1.width() - 17
        });

        $submitBtn2.css({
            top: iframeOffset.top + 40,
            left: iframeOffset.left + $iframe.width() - $submitBtn2.width() - 17
        });

        $submitBtn1.show();
        $submitBtn2.show();
    });
}

function fifu_add_event_w_later_thumb(src) {
    let buttonLater = jQuery(`span.w-later-thumb[thumb="${src}"]`);
    jQuery(buttonLater).on('click', async function (event) {
        event.stopPropagation();
        event.preventDefault();
        await fifu_watch_later_action(buttonLater);
    });

    let buttonLaterPL = jQuery(`span.w-later-thumb[thumb-pl="${src}"]`);
    jQuery(buttonLaterPL).on('click', async function (event) {
        event.stopPropagation();
        event.preventDefault();
        await fifu_watch_later_action(buttonLaterPL);
    });
}

function fifu_add_event_w_later_iframe(src) {
    let selButtonLater = `span.w-later-iframe[thumb="${src}"]`;
    jQuery(document).on('click', selButtonLater, async function (event) {
        event.stopPropagation();
        event.preventDefault();
        let clickedButton = jQuery(this);
        await fifu_watch_later_action(clickedButton);
    });

    let selButtonLaterPL = `span.w-later-iframe[thumb-pl="${src}"]`;
    jQuery(document).on('click', selButtonLaterPL, async function (event) {
        event.stopPropagation();
        event.preventDefault();
        let clickedButton = jQuery(this);
        await fifu_watch_later_action(clickedButton);
    });
}

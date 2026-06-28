var FIFU_IMAGE_NOT_FOUND_URL = 'https://storage.googleapis.com/featuredimagefromurl/image-not-found-a.jpg';
var FIFU_VIDEO_NOT_FOUND_URL = 'https://storage.googleapis.com/featuredimagefromurl/video-not-found-a.jpg';
var WC_PLACEHOLDER_IMAGE_URL = window.location.origin + '/wp-content/uploads/woocommerce-placeholder.webp';

jQuery(document).ready(function () {
    fifu_open_quick_lightbox();
    fifu_register_help_quick_edit();

    // Check all .fifu-quick thumbnails for invalid images or unsupported videos
    fifu_check_image_validity();
});

// Extract the image validity checking into a separate function
function fifu_check_image_validity() {
    jQuery('div.fifu-quick').each(function () {
        var $div = jQuery(this);
        var imageUrl = fifu_cdn_adjust($div.attr('image-url'));
        var videoUrl = $div.attr('video-url');
        var postId = $div.attr('post-id');

        // Skip if already processed
        if ($div.data('fifu-processed')) {
            return;
        }
        $div.data('fifu-processed', true);

        // VIDEO NOT FOUND: set background only, do NOT set placeholder for <img>
        if (videoUrl && typeof fifu_is_video === 'function' && !fifu_is_video(videoUrl)) {
            $div.css('background-image', 'url("' + FIFU_VIDEO_NOT_FOUND_URL + '")');
            // Update category thumbnail <img>
            jQuery(`tr#tag-${postId} td.thumb.column-thumb img[alt="Thumbnail"]`).each(function () {
                if (!jQuery(this).attr('src').includes('woocommerce-placeholder')) {
                    this.src = WC_PLACEHOLDER_IMAGE_URL;
                    this.removeAttribute('srcset');
                    this.removeAttribute('sizes');
                }
            });
            // Update product thumbnail <img>
            jQuery(`td.thumb.column-thumb a[href*="post=${postId}"] img`).each(function () {
                if (!jQuery(this).attr('src').includes('woocommerce-placeholder')) {
                    this.src = WC_PLACEHOLDER_IMAGE_URL;
                    this.removeAttribute('srcset');
                    this.removeAttribute('sizes');
                }
            });
            return;
        }

        // IMAGE NOT FOUND: set background only, do NOT set placeholder for <img>
        if (imageUrl) {
            var img = new Image();
            img.onerror = function () {
                $div.css('background-image', 'url("' + FIFU_IMAGE_NOT_FOUND_URL + '")');
                // Update category thumbnail <img>
                jQuery(`tr#tag-${postId} td.thumb.column-thumb img[alt="Thumbnail"]`).each(function () {
                    if (!jQuery(this).attr('src').includes('woocommerce-placeholder')) {
                        this.src = WC_PLACEHOLDER_IMAGE_URL;
                    }
                });
                // Update product thumbnail <img>
                jQuery(`td.thumb.column-thumb a[href*="post=${postId}"] img`).each(function () {
                    if (!jQuery(this).attr('src').includes('woocommerce-placeholder')) {
                        this.src = WC_PLACEHOLDER_IMAGE_URL;
                    }
                });
            };
            img.src = imageUrl;
        }
    });
}

// Add a mutation observer to detect new .fifu-quick elements
var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) { // Element node
                    // Check if the added node is a .fifu-quick element
                    if (jQuery(node).hasClass('fifu-quick')) {
                        setTimeout(fifu_check_image_validity, 100);
                    }
                    // Check if the added node contains .fifu-quick elements
                    if (jQuery(node).find('.fifu-quick').length > 0) {
                        setTimeout(fifu_check_image_validity, 100);
                    }
                }
            });
        }
    });
});

// Start observing
if (document.body) {
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
}

var currentLightbox = null;
var fifuPreviousInputs = [];

function fifu_open_quick_lightbox() {
    // Use delegated event binding to support dynamic elements
    jQuery(document).on('click', 'div.fifu-quick', function (evt) {
        evt.stopImmediatePropagation();
        let post_id = jQuery(this).attr('post-id');
        let video_url = jQuery(this).attr('video-url');
        let image_url = jQuery(this).attr('image-url');
        let video_src = jQuery(this).attr('video-src');
        let is_ctgr = jQuery(this).attr('is-ctgr');
        let is_variable = jQuery(this).attr('is-variable');

        if (is_variable) {
            let variable_box = `
                <div data-variable-product="1" style="background: white; padding: 10px; border-radius: 1em;">
                    <div style="background-color:#32373c; text-align:center; width:100%; color:white; padding:6px; border-radius:5px;">
                        ${fifuColumnVars.labelVariable}
                    </div>
                    <table style="text-align:left; width:100%">
                        <tbody>
                            <tr class="color">
                                <th style="width:64px">ID</th>
                                <th style="min-width:100px">${fifuColumnVars.labelName}</th>
                                <th style="width:40px"><center><span class="dashicons dashicons-camera" style="font-size:20px;"></span></center></th>
                            </tr>
                            <tr class="color">
                                <th style="font-weight:unset">${post_id}</th>
                                <th style="font-weight:unset">${fifuQuickEditVars.posts[post_id]['title']}</th>
                                <th style="font-weight:unset">
                                    <div
                                        class="fifu-quick"
                                        post-id="${post_id}"
                                        video-url="${fifuQuickEditVars.parent[post_id]['video-url']}"
                                        video-src="${fifuQuickEditVars.parent[post_id]['video-src']}"
                                        is-ctgr="${fifuQuickEditVars.parent[post_id]['is-ctgr']}"
                                        image-url="${fifuQuickEditVars.parent[post_id]['image-url']}"
                                        is-variable=""
                                        style="height: ${fifuQuickEditVars.parent[post_id]['height']}px; width: ${fifuQuickEditVars.parent[post_id]['width']}px; background:url('${fifuQuickEditVars.parent[post_id]['image-url']}') no-repeat center center; background-size:cover; ${fifuQuickEditVars.parent[post_id]['border']}; cursor:pointer;">
                                    </div>
                                </th>
                            </tr>
                        </tbody>
                    </table>
                    <br>
                    <div style="background-color:#32373c; text-align:center; width:100%; color:white; padding:6px; border-radius:5px;">
                        ${fifuColumnVars.labelVariation}
                    </div>
                    ${fifuQuickEditVars.posts[post_id]['fifu_variable_table']}
                </div>
            `;
            jQuery.fancybox.open(variable_box, {
                touch: false,
                afterShow: function () {
                    console.log('show');
                    fifu_open_quick_lightbox();
                },
                beforeClose: function () {
                    let postParent = jQuery('table#fifu-variable-table').attr('post-parent');
                    fifuQuickEditVars.posts[postParent]['fifu_variable_table'] = jQuery('#fifu-variable-table')[0].outerHTML;
                },
                afterClose: function () {
                    console.log('close');
                },
            });
            return;
        }

        currentLightbox = post_id;

        // display
        let DISPLAY_NONE = 'display:none';
        let EMPTY = '';
        // Detect if this click originated inside the variable modal as well
        const inVariableContext = jQuery(this).closest('[data-variable-product="1"]').length > 0;
        const isVariableProduct = !!is_variable || inVariableContext;

        let showVideo = (fifuColumnVars.isVideoEnabled || video_url) ? EMPTY : DISPLAY_NONE;
        let showImageGallery = fifuColumnVars.onProductsPage ? EMPTY : DISPLAY_NONE;
        let showSlider = fifuColumnVars.isSliderEnabled && !fifuColumnVars.onCategoriesPage && !isVariableProduct ? EMPTY : DISPLAY_NONE;
        let showVideoGallery = fifuColumnVars.isVideoEnabled && fifuColumnVars.onProductsPage ? EMPTY : DISPLAY_NONE;
        let showUploadButton = fifuColumnVars.isUploadEnabled ? EMPTY : DISPLAY_NONE;

        let url = image_url;
        url = (url == 'about:invalid' ? '' : url);
        let media, box;
        if (video_url) {
            if (!fifu_is_video(video_url)) {
                // Not a supported video, show fallback image
                media = `<img id="fifu-quick-preview" src="${FIFU_VIDEO_NOT_FOUND_URL}" post-id="${post_id}" style="max-height:600px; width:100%;">`;
            } else if (isCustomVideoUrl(video_url)) {
                media = `<video id="fifu-quick-preview" post-id="${post_id}" style="min-height: 200px; max-height: 600px; width: 100%; object-fit: cover;" controls poster=""><source src="" type=""></video>`;
            } else {
                media = `<iframe id="fifu-quick-preview" src="" post-id="${post_id}" style="min-height:200px; max-height:600px; width:100%;" allowfullscreen frameborder="0"></iframe>`;
            }
            url = '';
        } else
            media = `<img loading="lazy" id="fifu-quick-preview" src="" post-id="${post_id}" style="max-height:600px; width:100%;">`;
        box = `
            <table>
                <tr>
                    <td id="fifu-left-column">${media}</td>
                    <td style="vertical-align:top; padding: 10px; background-color:#f6f7f7; width:250px; border-radius: 8px;">
                        <div>
                            <div style="padding-bottom:5px">
                                <span class="dashicons dashicons-camera" style="font-size:20px;cursor:auto;" title="${fifuColumnVars.tipImage}"></span>
                                ${fifuColumnVars.labelImage}
                            </div>
                            <input id="fifu-quick-input-url" type="text" placeholder="${fifuColumnVars.urlImage}" value="" style="width:98%"/>
                            <br><br>

                            <div style="${showImageGallery}">
                                <div style="padding-bottom:5px">
                                    <span class="dashicons dashicons-format-gallery" style="font-size:20px;cursor:auto;"></span>
                                    ${fifuColumnVars.labelImageGallery}
                                </div>
                                <div id="gridDemoImage"></div>
                                <div id="inputHiddenImages"></div>
                                <input type="hidden" id="inputHiddenImageListIds" name="inputHiddenImageListIds" val=""/>
                                <input type="hidden" id="inputHiddenImageLength" name="inputHiddenImageLength" val=""/>
                                <br>
                            </div>

                            <div style="${showVideo}">
                                <div style="padding-bottom:5px">
                                    <span class="dashicons dashicons-video-alt3" style="font-size:20px;cursor:auto;" title="${fifuColumnVars.tipVideo}"></span>
                                    ${fifuColumnVars.labelVideo}
                                </div>
                                <input id="fifu-quick-video-input-url" type="text" placeholder="${fifuColumnVars.urlVideo}" value="" style="width:98%"/>
                                <br><br>
                            </div>

                            <div style="${showVideoGallery}">
                                <div style="padding-bottom:5px">
                                    <span class="dashicons dashicons-format-video" style="font-size:20px;cursor:auto;"></span>
                                    ${fifuColumnVars.labelVideoGallery}
                                </div>
                                <div id="gridDemoVideo"></div>
                                <div id="inputHiddenVideos"></div>
                                <input type="hidden" id="inputHiddenVideoListIds" name="inputHiddenVideoListIds" val=""/>
                                <input type="hidden" id="inputHiddenVideoLength" name="inputHiddenVideoLength" val=""/>
                                <br>
                            </div>

                            <div style="${showSlider}">
                                <div style="padding-bottom:5px">
                                    <span class="dashicons dashicons-images-alt2" style="font-size:20px;cursor:auto;"></span>
                                    ${fifuColumnVars.labelSlider}
                                </div>
                                <div id="gridDemoSlider"></div>
                                <div id="inputHiddenSliders"></div>
                                <input type="hidden" id="inputHiddenSliderListIds" name="inputHiddenSliderListIds" val=""/>
                                <input type="hidden" id="inputHiddenSliderLength" name="inputHiddenSliderLength" val=""/>
                                <br>
                            </div>

                            <div style="padding-bottom:5px">
                                <span class="dashicons dashicons-search" style="font-size:20px;cursor:auto" title="${fifuColumnVars.tipSearch}"></span>
                                ${fifuColumnVars.labelSearch}
                                <span id="fifu_help_quick_edit" 
                                    class="dashicons dashicons-editor-help" 
                                    style="font-size:20px;cursor:pointer;">
                                </span>
                            </div>
                            <div>
                                <input id="fifu-quick-search-input-keywords" type="text" placeholder="${fifuColumnVars.keywords}" value="" style="width:75%"/>
                                <button id="fifu-search-button" class="fifu-quick-button" type="button" style="width:50px;border-radius:5px;height:30px;position:absolute;background-color:#3c434a"><span class="dashicons dashicons-search" style="font-size:16px"></span></button>
                            </div>
                            <br><br>
                        </div>
                        <div style="width:100%">
                            <button id="fifu-clean-button" class="fifu-quick-button" type="button" style="background-color: #e7e7e7; color: black;">${fifuColumnVars.buttonClean}</button>
                            <button id="fifu-save-button" post-id="${post_id}" is-ctgr="${is_ctgr}" class="fifu-quick-button" type="button">${fifuColumnVars.buttonSave}</button>
                            <br>
                            <div style="${showUploadButton}">
                                <button id="fifu-upload-button" post-id="${post_id}" is-ctgr="${is_ctgr}" onclick="fifu_upload_images_quick_api()" class="fifu-quick-button" style="background-color: #3c434a; width:97.5%; position:relative; top:2px" type="button">${fifuColumnVars.buttonUpload}</button>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>                           
        `;
        fifu_include_input_hidden(post_id);
        jQuery.fancybox.open(box, {
            touch: false,
            afterShow: async function () {
                if (currentLightbox) {
                    fifu_get_image_info(currentLightbox);
                    fifu_get_video_info(currentLightbox);
                }

                if (!fifuColumnVars.onCategoriesPage) {
                    if (fifuColumnVars.onProductsPage) {
                        fifu_box_init();
                        if (fifuColumnVars.isVideoEnabled)
                            await fifu_video_box_init();
                    }
                    if (fifuColumnVars.isSliderEnabled && !isVariableProduct)
                        fifu_slider_box_init();
                }

                // NEW: toggle upload button based on image URL presence
                jQuery('#fifu-upload-button').css('display',
                        (fifuColumnVars.isUploadEnabled && jQuery('#fifu-quick-input-url').val()) ? '' : 'none');
            },
            afterClose: function () {
                jQuery('input[id^=fifu-quick-video-input]').remove();
            },
        }
        );
        jQuery('#fifu-left-column').css('display', url || video_url ? 'table-cell' : 'none');
        if (video_url)
            jQuery('#fifu-quick-video-input-url').select();
        else
            jQuery('#fifu-quick-input-url').select();
        fifu_change_image_event();
        fifu_save_event();
        fifu_keypress_event();
        fifu_search_event(is_ctgr, post_id); // Pass post_id to search event
    });
}

function fifu_change_image_event() {
    // image
    jQuery('#fifu-quick-input-url').on('input', function () {
        url = jQuery('#fifu-quick-input-url').val();
        post_id = jQuery('#fifu-save-button').attr('post-id');
        jQuery('#fifu-left-column').css('display', url ? 'table-cell' : 'none');
        jQuery('#fifu-quick-preview').remove();

        // NEW: toggle upload button live while typing
        jQuery('#fifu-upload-button').css('display',
                (fifuColumnVars.isUploadEnabled && url) ? '' : 'none');

        video_url = jQuery('#fifu-quick-video-input-url').val();
        if (isCustomVideoUrl(video_url)) {
            jQuery('#fifu-left-column').empty();
            jQuery('#fifu-left-column').append(`<video id="fifu-quick-preview" post-id="${post_id}" style="min-height: 200px; max-height: 600px; width: 100%; object-fit: cover;" controls poster="${url}"><source src="${video_url}" type=""></video>`);
            jQuery('#fifu-left-column').append(`<img loading="lazy" id="fifu-quick-preview-hidden-img" src="${url}" post-id="${post_id}" style="max-height:600px; width:0%;">`);
            if (!url)
                jQuery.fancybox.open(`<p>${fifuColumnVars.txt_warning_thumbnail}</p>`);
        } else {
            jQuery('#fifu-quick-video-input-url').val('');
            let adjustedUrl = fifu_cdn_adjust(url);
            jQuery('#fifu-left-column').append(
                    `<img loading="lazy" id="fifu-quick-preview" src="${adjustedUrl}" post-id="${post_id}" style="max-height:600px; width:100%;"
                    onerror="this.onerror=null;jQuery('#fifu-upload-button').hide();this.src='${FIFU_IMAGE_NOT_FOUND_URL}';">`
                    );
        }
    });
    // video
    jQuery('#fifu-quick-video-input-url').on('input', function () {
        url = jQuery('#fifu-quick-video-input-url').val();
        post_id = jQuery('#fifu-save-button').attr('post-id');
        jQuery('#fifu-left-column').css('display', url ? 'table-cell' : 'none');
        jQuery('#fifu-quick-preview').remove();

        src = srcVideo(url);
        imgColumn = jQuery('.fifu-quick[post-id="' + post_id + '"]');
        imgColumn.attr('video-url', url);
        imgColumn.attr('video-src', src);
        src = src ? src : '#';

        if (url) {
            // ignore thumbnail function when it's just a parameter change
            if (fifu_format_previous_input(url) != fifuPreviousInputs['fifu-quick-video-input-url']) {
                video_thumb_url = fifu_video_image_thumbnail(url, fifuColumnVars);
                if (video_thumb_url !== undefined) {
                    if (isCustomVideoUrl(url)) {
                        jQuery.fancybox.open(`<p>${fifuColumnVars.txt_warning_thumbnail}</p>`);
                    } else {
                        fifu_quick_video_get_image(video_thumb_url);
                    }
                }
                fifuPreviousInputs['fifu-quick-video-input-url'] = fifu_format_previous_input(url);
            }
        }

        if (fifu_is_video(url)) {
            if (isCustomVideoUrl(url)) {
                image_url = jQuery('#fifu-quick-input-url').val();
                jQuery('#fifu-left-column').empty();
                jQuery('#fifu-left-column').append(`<video id="fifu-quick-preview" post-id="${post_id}" style="min-height: 200px; max-height: 600px; width: 100%; object-fit: cover;" controls poster="${image_url}"><source src="${url}" type=""></video>`);
                jQuery('#fifu-left-column').append(`<img loading="lazy" id="fifu-quick-preview-hidden-img" src="${image_url}" post-id="${post_id}" style="max-height:600px; width:0%;">`);
            } else {
                jQuery('#fifu-quick-input-url').val('');
                // If src is invalid, show fallback image
                if (!src || src === '#') {
                    jQuery('#fifu-left-column').append(`<img id="fifu-quick-preview" src="${FIFU_VIDEO_NOT_FOUND_URL}" post-id="${post_id}" style="max-height:600px; width:100%;">`);
                } else {
                    jQuery('#fifu-left-column').append(`<iframe id="fifu-quick-preview" src="${src}" post-id="${post_id}" style="min-height:200px; max-height:600px; width:100%;" allowfullscreen frameborder="0"></iframe>`);
                }
            }
        } else {
            // Not a supported video, show fallback image
            jQuery('#fifu-left-column').empty();
            jQuery('#fifu-left-column').append(`<img id="fifu-quick-preview" src="${FIFU_VIDEO_NOT_FOUND_URL}" post-id="${post_id}" style="max-height:600px; width:100%;">`);
        }
    });
    // clean
    jQuery('#fifu-clean-button').on('click', function () {
        jQuery('#fifu-left-column').css('display', 'none');
        jQuery('#fifu-quick-preview').remove();
        jQuery('#fifu-quick-input-url').val('');
        jQuery('#fifu-quick-video-input-url').val('');
        jQuery('#fifu-quick-search-input-keywords').val('');

        // NEW: hide upload button on clean
        jQuery('#fifu-upload-button').css('display', 'none');

        jQuery('[id^=fifu_input_], [id^=fifu_video_input_], [id^=fifu_slider_input_]').each(function () {
            jQuery(this).val('');
        });
        jQuery('[id^=fifu-image-], [id^=fifu-video-], [id^=fifu-slider-]').each(function () {
            jQuery(this).css('background', '');
            jQuery(this).css('opacity', '');
        });
    });
}

function fifu_save_event() {
    jQuery('#fifu-save-button').on('click', function () {
        post_id = jQuery(this).attr('post-id');
        is_ctgr = jQuery(this).attr('is-ctgr');

        image_url = jQuery("#fifu-quick-input-url")[0].value;
        video_url = jQuery("#fifu-quick-video-input-url")[0].value;
        video_src = jQuery("iframe#fifu-quick-preview").attr('src');

        img = jQuery("img[post-id=" + post_id + "]")[0];
        iframe = jQuery("iframe[post-id=" + post_id + "]")[0];

        width = height = video_thumb_url = null;

        // product gallery
        galleryLength = 0;
        galleryUrls = [];
        galleryAlts = [];
        galleryIfms = [];
        if (jQuery('#gridDemoImage').length) {
            galleryLength = parseInt(jQuery('#inputHiddenImageLength').val());
            galleryIds = jQuery('#inputHiddenImageListIds').val();
            for (const index of galleryIds.split('|')) {
                galleryUrls.push(jQuery(`#fifu_input_url_${index}`).val());
                galleryAlts.push(jQuery(`#fifu_input_alt_${index}`).val());
                galleryIfms.push(jQuery(`#fifu_input_ifm_${index}`).val());
            }
        }

        // product video gallery
        galleryVideoLength = 0;
        galleryVideoUrls = [];
        galleryThumbUrls = [];
        if (jQuery('#gridDemoVideo').length) {
            galleryVideoLength = parseInt(jQuery('#inputHiddenVideoLength').val());
            galleryVideoIds = jQuery('#inputHiddenVideoListIds').val();
            for (const index of galleryVideoIds.split('|')) {
                galleryVideoUrls.push(jQuery(`#fifu_video_input_url_${index}`).val());
                galleryThumbUrls.push(jQuery(`#fifu_video_input_image_src_${index}`).val());
            }
        }

        // featured slider
        sliderLength = 0;
        sliderUrls = [];
        sliderAlts = [];
        if (jQuery('#gridDemoSlider').length) {
            sliderLength = parseInt(jQuery('#inputHiddenSliderLength').val());
            sliderIds = jQuery('#inputHiddenSliderListIds').val();
            for (const index of sliderIds.split('|')) {
                sliderUrls.push(jQuery(`#fifu_slider_input_url_${index}`).val());
                sliderAlts.push(jQuery(`#fifu_slider_input_alt_${index}`).val());
            }
        }

        if (image_url && video_url && isCustomVideoUrl(video_url)) {
            img = jQuery("img#fifu-quick-preview-hidden-img[post-id=" + post_id + "]")[0];
            width = img.naturalWidth;
            height = img.naturalHeight;
            video_thumb_url = img.src;
        } else if (image_url) {
            // Fix: Use the correct selector for the preview image
            img = jQuery("img#fifu-quick-preview")[0];
            if (img) {
                width = img.naturalWidth;
                height = img.naturalHeight;
            }
        } else if (video_url) {
            width = jQuery("#fifu-quick-video-input-image-width")[0].value;
            height = jQuery("#fifu-quick-video-input-image-height")[0].value;
            video_thumb_url = jQuery("#fifu-quick-video-input-image-src")[0].value;
        }

        jQuery.ajax({
            method: "POST",
            url: fifuColumnVars.restUrl + 'fifu-premium/v2/quick_edit_save_api/',
            data: {
                "post_id": post_id,
                "is_ctgr": is_ctgr,
                "width": width,
                "height": height,
                "image_url": image_url,
                "video_url": video_url,
                "video_thumb_url": video_thumb_url,
                "gallery_length": galleryLength,
                "gallery_urls": galleryUrls,
                "gallery_alts": galleryAlts,
                "gallery_ifms": galleryIfms,
                "gallery_video_length": galleryVideoLength,
                "gallery_video_urls": galleryVideoUrls,
                "slider_length": sliderLength,
                "slider_urls": sliderUrls,
                "slider_alts": sliderAlts,
            },
            async: true,
            beforeSend: function (xhr) {
                xhr.setRequestHeader("X-WP-Nonce", fifuColumnVars.nonce);
            },
            success: function (data) {
                // featured image
                if (fifuColumnVars.onCategoriesPage) {
                    fifuQuickEditCtgrVars.terms[post_id]['fifu_image_url'] = image_url;
                    fifuQuickEditCtgrVars.terms[post_id]['fifu_image_alt'] = image_alt;
                } else {
                    fifuQuickEditVars.posts[post_id]['fifu_image_url'] = image_url;

                    if (fifuQuickEditVars.parent && fifuQuickEditVars.parent[post_id])
                        fifuQuickEditVars.parent[post_id]['image-url'] = image_url;
                }

                // featured video
                if (fifuColumnVars.onCategoriesPage) {
                    fifuQuickEditCtgrVars.terms[post_id]['fifu_video_url'] = video_url;
                    fifuQuickEditCtgrVars.terms[post_id]['fifu_video_src'] = video_src;
                } else {
                    fifuQuickEditVars.posts[post_id]['fifu_video_url'] = video_url;
                    fifuQuickEditVars.posts[post_id]['fifu_video_src'] = video_src;

                    if (fifuQuickEditVars.parent && fifuQuickEditVars.parent[post_id]) {
                        fifuQuickEditVars.parent[post_id]['video-url'] = video_url;
                        fifuQuickEditVars.parent[post_id]['video-src'] = video_src;
                    }
                }

                if (!fifuColumnVars.onCategoriesPage) {
                    // featured slider
                    fifuQuickEditVars.posts[post_id]['fifu_slider_image_urls'] = sliderUrls;
                    fifuQuickEditVars.posts[post_id]['fifu_slider_image_alts'] = sliderAlts;

                    // image gallery
                    fifuQuickEditVars.posts[post_id]['fifu_image_urls'] = galleryUrls;
                    fifuQuickEditVars.posts[post_id]['fifu_image_alts'] = galleryAlts;
                    fifuQuickEditVars.posts[post_id]['fifu_image_ifms'] = galleryIfms;

                    // video gallery
                    fifuQuickEditVars.posts[post_id]['fifu_video_urls'] = galleryVideoUrls;
                    fifuQuickEditVars.posts[post_id]['fifu_thumb_urls'] = galleryThumbUrls;
                }

                json = JSON.parse(data);
                url = json['thumb_url'];
                url = url ? url : '';

                // If url contains #http, use the part after # as the image URL
                if (url && url.includes('#http')) {
                    url = url.substring(url.indexOf('#http') + 1);
                }

                if (!fifuColumnVars.onCategoriesPage) {
                    if (fifuQuickEditVars.parent && fifuQuickEditVars.parent[post_id]) {
                        fifuQuickEditVars.parent[post_id]['image-url'] = url;
                    }
                }

                const thumbs = jQuery('div.fifu-quick[post-id=' + post_id + ']');
                for (let i = 0; i < thumbs.length; i++) {
                    const thumb = thumbs[i];
                    jQuery(thumb).attr('image-url', url);

                    // Check for invalid video URL and set fallback if needed
                    const videoUrl = jQuery(thumb).attr('video-url');
                    if (videoUrl && typeof fifu_is_video === 'function' && !fifu_is_video(videoUrl)) {
                        jQuery(thumb).css('background-image', 'url("' + FIFU_VIDEO_NOT_FOUND_URL + '")');
                        jQuery(thumb).css('border', 'none');

                        // Update category thumbnail <img> in the table cell (categories)
                        jQuery(`tr#tag-${post_id} td.thumb.column-thumb img[alt="Thumbnail"]`).each(function () {
                            this.src = WC_PLACEHOLDER_IMAGE_URL;
                            this.removeAttribute('srcset');
                            this.removeAttribute('sizes');
                        });

                        continue;
                    }

                    let adjustedUrl = fifu_cdn_adjust(url);
                    jQuery(thumb).css('background-image', 'url("' + adjustedUrl + '")');
                    url ? jQuery(thumb).css('border', 'none') : jQuery(thumb).css('color', '#ca4a1f').css('border', '2px').css('border-style', 'dotted').css('border-radius', '8px');

                    // Minimal addition: check if image loads, set fallback if not
                    if (url) {
                        let img = new window.Image();
                        img.onerror = function () {
                            jQuery(thumb).css('background-image', 'url("' + FIFU_IMAGE_NOT_FOUND_URL + '")');
                        };
                        img.src = adjustedUrl;
                    }
                }

                thumb = jQuery('div.fifu-quick[post-id=' + post_id + ']')[0];
                jQuery(thumb).attr('image-url', url);

                // Only set background if not an invalid video

                const videoUrl = jQuery(thumb).attr('video-url');
                if (!(videoUrl && typeof fifu_is_video === 'function' && !fifu_is_video(videoUrl))) {
                    jQuery(thumb).css('background-image', 'url("' + fifu_cdn_adjust(url) + '")');
                    url ? jQuery(thumb).css('border', 'none') : jQuery(thumb).css('color', '#ca4a1f').css('border', '2px').css('border-style', 'dotted').css('border-radius', '8px');
                }

                // Also update the thumbnail <img> in the table cell to the new image URL (or placeholder if empty)
                const thumbImg = jQuery(`td.thumb.column-thumb a[href*="post=${post_id}"] img`);
                if (thumbImg.length) {
                    // Check if the video is invalid
                    if (videoUrl && typeof fifu_is_video === 'function' && !fifu_is_video(videoUrl)) {
                        // Set to video not found image
                        thumbImg
                                .attr('src', WC_PLACEHOLDER_IMAGE_URL)
                                .removeAttr('srcset')
                                .removeAttr('sizes');
                    } else {
                        // Set the src to the new image URL or placeholder
                        thumbImg
                                .attr('src', url ? url : WC_PLACEHOLDER_IMAGE_URL)
                                .removeAttr('srcset')
                                .removeAttr('sizes');
                        // Add error handler to set fallback if image fails to load
                        thumbImg.off('error.fifu').on('error.fifu', function () {
                            jQuery(this).attr('src', WC_PLACEHOLDER_IMAGE_URL);
                        });
                    }
                }

                if (fifuColumnVars.onCategoriesPage) {
                    // Update the category thumbnail <img> in the table cell
                    const catThumbImg = jQuery(`tr#tag-${post_id} td.thumb.column-thumb img[alt="Thumbnail"]`);

                    // Check if the video is invalid
                    const thumbDiv = jQuery('div.fifu-quick[post-id="' + post_id + '"]');
                    const videoUrl = thumbDiv.attr('video-url');
                    if (catThumbImg.length) {
                        if (videoUrl && typeof fifu_is_video === 'function' && !fifu_is_video(videoUrl)) {
                            // Set to video not found image
                            catThumbImg
                                    .attr('src', WC_PLACEHOLDER_IMAGE_URL)
                                    .removeAttr('srcset')
                                    .removeAttr('sizes');
                        } else {
                            // Set to real image or placeholder
                            catThumbImg
                                    .attr('src', url ? url : WC_PLACEHOLDER_IMAGE_URL)
                                    .off('error.fifu')
                                    .on('error.fifu', function () {
                                        jQuery(this).attr('src', WC_PLACEHOLDER_IMAGE_URL);
                                    });
                        }
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
            },
            complete: function (data) {
                jQuery.fancybox.close();
            },
        });
    });
}

function fifu_keypress_event() {
    jQuery('div.fancybox-container.fancybox-is-open').keyup(function (e) {
        switch (e.which) {
            case 9:
                // tab (keyword)
                if (jQuery('#fifu-quick-search-input-keywords').val())
                    jQuery('#fifu-search-button').click();
                break;
            case 13:
                jQuery(this).blur();
                // enter (keyword)
                if (jQuery('#fifu-quick-search-input-keywords').val()) {
                    jQuery('#fifu-search-button').focus().click();
                    break;
                }
                // enter (save)
                jQuery('#fifu-save-button').focus().click();
                break;
            case 27:
                // esc
                jQuery.fancybox.close();
                break;
            default:
                break;
        }
    });
}

function fifu_search_event(is_ctgr, post_id) {
    jQuery('#fifu-search-button').on('click', function () {
        let keywords = jQuery('#fifu-quick-search-input-keywords').val();
        if (keywords) {
            // If keywords entered, use them and unsplash=true
            fifu_start_lightbox(keywords, true, post_id, is_ctgr, 'quick-edit');
        } else {
            // If keywords empty, use post title and unsplash=false
            let postTitle = fifu_get_post_title();
            fifu_start_lightbox(postTitle, false, post_id, is_ctgr, 'quick-edit');
        }
    });
}

function fifu_quick_video_get_image(url) {
    var image = new Image();
    jQuery(image).attr('onload', 'fifu_quick_video_store_sizes(this);');
    jQuery(image).attr('src', url);
}

function fifu_quick_video_store_sizes($) {
    jQuery("#fifu-quick-video-input-image-width").val($.naturalWidth);
    jQuery("#fifu-quick-video-input-image-height").val($.naturalHeight);
    if ($.naturalWidth == 120 && $.naturalHeight == 90)
        jQuery("#fifu-quick-video-input-image-src").val($.src.replace('maxresdefault', 'mqdefault'));
    else
        jQuery("#fifu-quick-video-input-image-src").val($.src);
}

function fifu_include_input_hidden(post_id) {
    hidden_input = `
        <input 
            post-id="${post_id}"
            type="hidden" 
            id="fifu-quick-video-input-image-width" 
            name="fifu-quick-video-input-image-width" 
            value="" >

        <input
            post-id="${post_id}"
            type="hidden" 
            id="fifu-quick-video-input-image-height" 
            name="fifu-quick-video-input-image-height" 
            value="" >

        <input 
            post-id="${post_id}"
            type="hidden" 
            id="fifu-quick-video-input-image-src" 
            name="fifu-quick-video-input-image-src" 
            value="" >
    `;
    jQuery("div.fifu-quick").after(hidden_input);
}

function fifu_get_image_info(post_id) {
    image_url = null;

    // Fix: Initialize category data if missing (new category case)
    if (fifuColumnVars.onCategoriesPage) {
        if (!fifuQuickEditCtgrVars.terms[post_id]) {
            // Try to get from DOM
            let $div = jQuery('.fifu-quick[post-id="' + post_id + '"]');
            let videoSrc = $div.attr('video-src') || '';
            fifuQuickEditCtgrVars.terms[post_id] = {
                fifu_image_url: videoSrc ? '' : ($div.attr('image-url') || ''),
                fifu_image_alt: '',
                fifu_video_url: $div.attr('video-url') || '',
                fifu_video_src: videoSrc
            };
        }
        image_url = fifuQuickEditCtgrVars.terms[post_id]['fifu_image_url'];
        image_alt = fifuQuickEditCtgrVars.terms[post_id]['fifu_image_alt'];
    } else {
        image_url = fifuQuickEditVars.posts[post_id]['fifu_image_url'];
    }

    if (image_url) {
        jQuery('input#fifu-quick-input-url').val(image_url);
        jQuery('#fifu-quick-input-url').select();
        let adjustedUrl = fifu_cdn_adjust(image_url);
        jQuery('img#fifu-quick-preview')
                .attr('src', adjustedUrl)
                // Hide upload on error (not found)
                .attr('onerror', `this.onerror=null;jQuery('#fifu-upload-button').hide();this.src='${FIFU_IMAGE_NOT_FOUND_URL}';`);
    }
}

function fifu_get_video_info(post_id) {
    video_url = null;
    video_src = null;

    if (fifuColumnVars.onCategoriesPage) {
        video_url = fifuQuickEditCtgrVars.terms[post_id]['fifu_video_url'];
        video_src = fifuQuickEditCtgrVars.terms[post_id]['fifu_video_src'];
    } else {
        video_url = fifuQuickEditVars.posts[post_id]['fifu_video_url'];
        video_src = fifuQuickEditVars.posts[post_id]['fifu_video_src'];
    }

    if (video_url) {
        jQuery('input#fifu-quick-video-input-url').val(video_url);
        jQuery('#fifu-quick-video-input-url').select();
        jQuery('iframe#fifu-quick-preview').attr('src', video_src);
        fifuPreviousInputs['fifu-quick-video-input-url'] = fifu_format_previous_input(video_url);

        if (isCustomVideoUrl(video_url)) {
            url = jQuery('#fifu-quick-input-url').val();
            jQuery('#fifu-left-column').empty();
            jQuery('#fifu-left-column').append(`<video id="fifu-quick-preview" post-id="${post_id}" style="min-height: 200px; max-height: 600px; width: 100%; object-fit: cover;" controls poster="${url}"><source src="${video_url}" type=""></video>`);
            jQuery('#fifu-left-column').append(`<img loading="lazy" id="fifu-quick-preview-hidden-img" src="${url}" post-id="${post_id}" style="max-height:600px; width:0%;">`);
        }
    }
}

function fifu_upload_images_quick_api(postId) {
    setTimeout(function () {
        // Resolve the post ID from arg, currentLightbox, or the button attribute
        const pid = postId || currentLightbox || jQuery('#fifu-upload-button').attr('post-id');

        url = jQuery("#fifu-quick-input-url").val();
        urls = '';
        alts = '';
        if (fifuColumnVars.onProductsPage) {
            if (jQuery('#gridDemoImage').length) {
                galleryIds = jQuery('#inputHiddenImageListIds').val();
                for (const index of galleryIds.split('|')) {
                    if (index > 0) {
                        urls += '|';
                        alts += '|';
                    }
                    urls += jQuery(`#fifu_input_url_${index}`).val();
                    alts += jQuery(`#fifu_input_alt_${index}`).val();
                }
            }
        }
        if (!url && !urls)
            return;

        jQuery.ajax({
            method: "POST",
            url: fifuColumnVars.restUrl + 'fifu-premium/v2/upload_images/',
            data: {
                "url": url,
                "urls": urls,
                "alts": alts,
                "post_id": pid, // was currentLightbox; use resolved pid
                "meta_box": false,
                "taxonomy": fifuColumnVars.taxonomy,
            },
            async: true,
            beforeSend: function (xhr) {
                xhr.setRequestHeader("X-WP-Nonce", fifuColumnVars.nonce);
            },
            success: function (data) {
                if (data == null)
                    return;

                // clean preview
                json = JSON.parse(data);
                url = json['local_url'];
                const thumb = jQuery('div.fifu-quick[post-id=' + pid + ']')[0]; // was post_id (undefined)
                if (thumb) {
                    jQuery(thumb).attr('image-url', url);
                    jQuery(thumb).css('background-image', 'url("' + url + '")');
                    jQuery(thumb).css('color', '#ca4a1f').css('border', '2px').css('border-style', 'dotted').css('border-radius', '8px');
                }

                // clean lightbox
                jQuery('#fifu-quick-input-url').val('');
                jQuery('[id^=fifu_input_]').each(function () {
                    jQuery(this).val('');
                });
                jQuery('[id^=fifu-image-]').each(function () {
                    jQuery(this).css('background', '');
                    jQuery(this).css('opacity', '');
                });

                // clean json
                if (fifuColumnVars.onCategoriesPage) {
                    fifuQuickEditCtgrVars.terms[pid]['fifu_image_url'] = '';
                } else {
                    fifuQuickEditVars.posts[pid]['fifu_image_url'] = '';
                    fifuQuickEditVars.posts[pid]['fifu_image_urls'] = [];
                    fifuQuickEditVars.posts[pid]['fifu_image_alts'] = [];
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
            },
            complete: function (data) {
            }
        });
        jQuery.fancybox.close();
    }, 100);
}

function fifu_register_help_quick_edit() {
    jQuery(document).on('click', '#fifu_help_quick_edit', function () {
        jQuery.fancybox.open(`
            <div style="color:#1e1e1e;width:50%">
                <h1 style="background-color:whitesmoke;padding:20px;padding-left:0">${fifuColumnVars.txt_title_examples}</h1>                
                <h3>${fifuColumnVars.txt_title_keywords}</h3>
                <p style="background-color:#1e1e1e;color:white;padding:10px;border-radius:5px">sea,sun</p>
                <p>${fifuColumnVars.txt_desc_keywords}</p>
                <h3>${fifuColumnVars.txt_title_empty}</h3>
                <p style="background-color:#1e1e1e;color:white;padding:10px;border-radius:5px;height:40px"></p>
                <p>${fifuColumnVars.txt_desc_empty}</p>
            </div>`
                );
    });
}

// Function to dynamically load a script
function loadScriptWithJQuery(url, callback) {
    var script = jQuery('<script>', {type: 'text/javascript', src: url});
    script.on('load', callback);
    jQuery('head').append(script);
}

// Function to dynamically load a stylesheet
function loadStylesheetWithJQuery(url) {
    var link = jQuery('<link>', {rel: 'stylesheet', type: 'text/css', href: url});
    jQuery('head').append(link);
}

// Load resources when fancyBox is opened
jQuery(document).on('beforeShow.fb', function () {
    loadScriptWithJQuery(fifuColumnVars.convertUrlJs, function () {});
    loadStylesheetWithJQuery(fifuColumnVars.sortableCssUrl);
});

function fifu_cdn_adjust(url) {
    if (url.includes("https://drive.google.com") || url.includes("https://drive.usercontent.google.com")) {
        let cdnUrl = 'https://res.cloudinary.com/glide/image/fetch/' + encodeURIComponent(url);
        return `https://i${Math.abs(crc32(cdnUrl) % 4)}.wp.com/${cdnUrl.replace(/^https?:\/\//, '')}`;
    }
    return url;
}

var crc32 = function (r) {
    for (var a, o = [], c = 0; c < 256; c++) {
        a = c;
        for (var f = 0; f < 8; f++)
            a = 1 & a ? 3988292384 ^ a >>> 1 : a >>> 1;
        o[c] = a
    }
    for (var n = -1, t = 0; t < r.length; t++)
        n = n >>> 8 ^ o[255 & (n ^ r.charCodeAt(t))];
    return(-1 ^ n) >>> 0
};

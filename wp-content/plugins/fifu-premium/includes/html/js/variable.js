jQuery(document).ready(function ($) {
    // Initialize variable selector
    let fifuVariableSelector = 'table.variations tbody tr td select';

    // Theme-specific selectors
    if (jQuery(fifuVariableSelector).length === 0) {
        let themeSelectorZank = 'div.zank-variations div.zank-variations-items div select';
        if (jQuery(themeSelectorZank).length > 0) {
            fifuVariableSelector = themeSelectorZank;
        }
    }

    if (jQuery(fifuVariableSelector).length === 0) {
        let themeSelectorElectron = 'div.electron-variations div.electron-variations-items div select';
        if (jQuery(themeSelectorElectron).length > 0) {
            fifuVariableSelector = themeSelectorElectron;
        }
    }

    function fifuGetGalleryContainer() {
        if (jQuery('.fifu-product-gallery').length) {
            if (jQuery('.fifu-product-gallery').closest('.lSSlideOuter').length) {
                return jQuery('.fifu-product-gallery').closest('.lSSlideOuter').parent();
            }
            return jQuery('.fifu-product-gallery').parent().parent().parent();
        }
        return jQuery('#image-gallery').parent();
    }

    const allowedImgAttrs = new Set(['class', 'alt', 'onerror', 'loading', 'decoding', 'referrerpolicy', 'title']);
    const templateImgAttrs = {};
    const $templateImg = jQuery('#image-gallery li:first img');

    if ($templateImg.length) {
        jQuery.each($templateImg[0].attributes, function () {
            if (this.name !== 'src' && this.name !== 'id' && allowedImgAttrs.has(this.name)) {
                templateImgAttrs[this.name] = this.value;
            }
        });
    }

    function captureOriginalGallery() {
        const items = [];
        const liAttrMap = {};
        const listAttrs = {};

        const $list = jQuery('#image-gallery');
        if (!$list.length)
            return {items, liAttrMap, listAttrs};

        jQuery.each($list[0].attributes, function () {
            listAttrs[this.name] = this.value;
        });

        $list.children('li').each(function () {
            const $li = jQuery(this);
            if ($li.hasClass('clone'))
                return;

            const key = $li.attr('data-thumb') || $li.attr('data-src');
            if (key) {
                liAttrMap[key] = {};
                jQuery.each(this.attributes, function () {
                    liAttrMap[key][this.name] = this.value;
                });
            }

            const liAttrs = {};
            jQuery.each(this.attributes, function () {
                liAttrs[this.name] = this.value;
            });

            const $img = $li.find('img').first();
            const imgAttrs = {};
            if ($img.length) {
                jQuery.each($img[0].attributes, function () {
                    imgAttrs[this.name] = this.value;
                });
            }
            items.push({liAttrs, imgAttrs});
        });

        return {items, liAttrMap, listAttrs};
    }

    const {
        items: originalItems,
        liAttrMap: originalLiAttrs,
        listAttrs: originalListAttrs
    } = captureOriginalGallery();

    // Debounce function to prevent multiple rapid calls
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Reusable gallery builder
    async function fifuBuildGallery(ids, merge, anyAttrSelected) {
        let dataVideoMap = fifuGetDataVideoMap();
        const galleryContainer = fifuGetGalleryContainer();

        if (!Array.isArray(ids) || ids.length === 0 || !galleryContainer || galleryContainer.length === 0)
            return;

        const $existingGallery = galleryContainer.find('.fifu-product-gallery');
        if ($existingGallery.length) {
            const sliderInstance = $existingGallery.data('lightSlider');
            if (sliderInstance && typeof sliderInstance.destroy === 'function')
                sliderInstance.destroy();
        }

        galleryContainer.empty();

        const isBaseProduct = ids.length === 1 && ids[0] === fifuVariableVars.post_id;
        if (isBaseProduct && originalItems.length) {
            const $baseGallery = jQuery('<ul/>');
            const baseAttrs = Object.keys(originalListAttrs).length ? originalListAttrs : {};
            jQuery.each(baseAttrs, function (name, value) {
                if (typeof value !== 'undefined')
                    $baseGallery.attr(name, value);
            });
            if (!$baseGallery.attr('id'))
                $baseGallery.attr('id', 'image-gallery');
            if (!$baseGallery.attr('class'))
                $baseGallery.attr('class', 'gallery list-unstyled fifu-product-gallery lightSlider');

            originalItems.forEach(({liAttrs, imgAttrs}) => {
                const $li = jQuery('<li/>');
                jQuery.each(liAttrs, function (name, value) {
                    if (typeof value !== 'undefined')
                        $li.attr(name, value);
                });

                const $img = jQuery('<img/>');
                jQuery.each(imgAttrs, function (name, value) {
                    if (typeof value !== 'undefined')
                        $img.attr(name, value);
                });
                $li.append($img);
                $baseGallery.append($li);
            });

            galleryContainer.append($baseGallery);
            if (typeof fifu_load_slider === 'function')
                fifu_load_slider();
            return;
        }

        // Create new gallery container
        galleryContainer.html('<ul id="image-gallery" class="gallery list-unstyled fifu-product-gallery lightSlider"></ul>');

        // Add images to gallery
        let urlset = new Set();
        let $gallery = galleryContainer.find('.fifu-product-gallery');
        for (let i = 0; i < ids.length; i++) {
            let imageList = Array.isArray(fifuVariableVars.url_map[ids[i]])
                    ? fifuVariableVars.url_map[ids[i]].slice()
                    : [];

            // Merge variation images if enabled
            if (merge && i === 0 && (!anyAttrSelected || anyAttrSelected === undefined)) {
                const firstImages = Object.values(fifuVariableVars.url_map)
                        .map(arr => Array.isArray(arr) ? arr[0] : undefined)
                        .filter(Boolean);
                imageList = imageList.concat(firstImages);
            }

            if (imageList.length === 0)
                continue;

            for (let j = 0; j < imageList.length; j++) {
                let clazz = (i == 0 && j == 0) ? "lslide active" : "lslide";
                let url = imageList[j];

                // Avoid duplicated urls
                if (urlset.has(url) || url === undefined)
                    continue;
                urlset.add(url);

                let src = url;
                let usePoster = false;
                if (fifuVariableVars.fifu_video && typeof is_video_img === 'function' && await is_video_img(url)) {
                    src = typeof video_url === 'function' ? await video_url(url) : url;
                    usePoster = true;
                }

                const originalAttrs = originalLiAttrs[url] || {};
                let dataVideo = dataVideoMap[url] || originalAttrs['data-video'];

                const baseClasses = (originalAttrs['class'] || '')
                        .split(/\s+/)
                        .filter(Boolean)
                        .filter(cls => cls !== 'lslide' && cls !== 'active');
                const combinedClass = Array.from(new Set(
                        baseClasses.concat(clazz.split(/\s+/).filter(Boolean))
                        )).join(' ');
                const $item = jQuery('<li/>').attr('class', combinedClass);

                jQuery.each(originalAttrs, function (name, value) {
                    if (['class', 'data-thumb', 'data-src', 'data-video', 'data-poster'].includes(name))
                        return;
                    $item.attr(name, value);
                });

                $item.attr('data-thumb', url);
                if (dataVideo) {
                    $item.attr('data-video', dataVideo);
                } else {
                    $item.attr('data-src', src);
                }
                if (usePoster) {
                    $item.attr('data-poster', url);
                } else if (originalAttrs['data-poster']) {
                    $item.attr('data-poster', originalAttrs['data-poster']);
                }

                const $img = jQuery('<img/>').attr('src', url);
                if (Object.keys(templateImgAttrs).length) {
                    jQuery.each(templateImgAttrs, function (name, value) {
                        if (typeof value !== 'undefined')
                            $img.attr(name, value);
                    });
                } else {
                    $img.attr('class', 'fifu');
                }
                $img.attr('fifu-replaced', '1');
                $item.append($img);
                $gallery.append($item);
            }
        }

        // Initialize slider
        if (typeof fifu_load_slider === 'function')
            fifu_load_slider();
    }

    // Process variation changes
    const processVariationChange = debounce(async function () {
        // Defensive: check fifuVariableVars exists
        if (typeof fifuVariableVars === 'undefined' || !fifuVariableVars)
            return;

        let ids = null;
        let anyAttrSelected = false;

        jQuery(fifuVariableSelector).each(function (index) {
            let attr_name = jQuery(this).attr('name');
            let attr_val = jQuery(this).val();

            if (!attr_val)
                return;

            anyAttrSelected = true;

            if (!ids) {
                if (typeof fifuVariableVars.attribute_map === 'undefined' ||
                        typeof fifuVariableVars.attribute_map[attr_name] === 'undefined')
                    return;
                ids = fifuVariableVars.attribute_map[attr_name][attr_val];
            } else {
                if (typeof fifuVariableVars.attribute_map === 'undefined' ||
                        typeof fifuVariableVars.attribute_map[attr_name] === 'undefined' ||
                        typeof fifuVariableVars.attribute_map[attr_name][attr_val] === 'undefined')
                    return;
                let tmp = fifuVariableVars.attribute_map[attr_name][attr_val];
                ids = ids.filter(value => tmp.includes(value));
            }
        });

        // Handle main product if no variations selected
        if (!ids) {
            ids = [fifuVariableVars.post_id];
        } else {
            let hasImages = false;
            for (let i = 0; i < ids.length; i++) {
                const list = fifuVariableVars.url_map[ids[i]];
                if (Array.isArray(list) && list[0]) {
                    hasImages = true;
                    break;
                }
            }
            if (!hasImages)
                ids = [fifuVariableVars.post_id];
        }

        // Build gallery
        await fifuBuildGallery(ids, fifuVariableVars.fifu_variations_merge, anyAttrSelected);
    }, 250); // 250ms debounce time

    // Function to initialize gallery with main product images
    async function fifuInitMainGallery() {
        if (typeof fifuVariableVars === 'undefined' || !fifuVariableVars)
            return;
        if (jQuery('#image-gallery li').length)
            return;
        let ids = [fifuVariableVars.post_id];
        await fifuBuildGallery(ids, fifuVariableVars.fifu_variations_merge, false);
    }

    // Attach event handler
    jQuery(fifuVariableSelector).on('change', processVariationChange);

    // Initialize gallery based on selected variations or main product
    let variationSelected = false;
    jQuery(fifuVariableSelector).each(function () {
        if (jQuery(this).val()) {
            variationSelected = true;
        }
    });
    if (variationSelected) {
        processVariationChange();
    } else {
        fifuInitMainGallery();
    }
});

function fifuGetDataVideoMap() {
    var map = {};
    jQuery('#image-gallery li').each(function () {
        var thumb = jQuery(this).attr('data-thumb');
        var video = jQuery(this).attr('data-video');
        if (thumb && video) {
            map[thumb] = video;
        }
    });
    return map;
}

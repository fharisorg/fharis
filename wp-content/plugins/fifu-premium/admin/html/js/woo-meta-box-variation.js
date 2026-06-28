/**
 * Variation-specific FIFU gallery initialization.
 * Creates a self-contained gallery for each variation grid with id "gridDemoImage-<vid>"
 */
(function ($) {
    'use strict';

    var isDraggingImage = false;
    var HIDDEN_PREFIX = 'fifu_var_input_';
    var IMG_PREFIX = 'fifu-var-image-';

    // --- utils ---------------------------------------------------------------

    function escAttr(s) {
        if (s == null)
            return '';
        return String(s).replace(/[&<>"']/g, function (m) {
            return ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[m]);
        });
    }

    function isHttp(u) {
        return typeof u === 'string' && /^https?:\/\//i.test(u);
    }

    function fifu_woo_get_sizes(urlSelector, wSelector, hSelector) {
        var url = $(urlSelector).val();
        if (!isHttp(url))
            return;
        var image = new Image();
        $(image).one('load', function () {
            $(wSelector).val(this.naturalWidth || '');
            $(hSelector).val(this.naturalHeight || '');
        });
        image.src = url;
    }

    function ensureHiddenInputs($container, vid, maxIndexInclusive) {
        var uid = '-' + vid;
        for (var i = 0; i <= maxIndexInclusive; i++) {
            if (!$container.find('#' + HIDDEN_PREFIX + 'url_' + i + uid).length) {
                $container.append(
                        '<input type="hidden" id="' + HIDDEN_PREFIX + 'width_' + i + uid + '" name="' + HIDDEN_PREFIX + 'width_' + i + uid + '" value="">' +
                        '<input type="hidden" id="' + HIDDEN_PREFIX + 'height_' + i + uid + '" name="' + HIDDEN_PREFIX + 'height_' + i + uid + '" value="">' +
                        '<input type="hidden" id="' + HIDDEN_PREFIX + 'url_' + i + uid + '" name="' + HIDDEN_PREFIX + 'url_' + i + uid + '" value="">' +
                        '<input type="hidden" id="' + HIDDEN_PREFIX + 'alt_' + i + uid + '" name="' + HIDDEN_PREFIX + 'alt_' + i + uid + '" value="">' +
                        '<input type="hidden" id="' + HIDDEN_PREFIX + 'ifm_' + i + uid + '" name="' + HIDDEN_PREFIX + 'ifm_' + i + uid + '" value="">'
                        );
            }
        }
    }

    function extractIndexFromDivId(divId, vid) {
        // expected: fifu-var-image-<idx>-<vid>
        var re = new RegExp('^' + IMG_PREFIX + '(\\d+)-' + vid + '$');
        var m = divId.match(re);
        if (m && m[1] != null)
            return m[1];
        var parts = divId.split('-');
        return parts.length >= 2 ? parts[parts.length - 2] : null;
    }

    // --- main ---------------------------------------------------------------

    function initVariationGrid(vid) {
        var gridId = 'gridDemoImage-' + vid;
        var $grid = $('#' + gridId);
        if (!$grid.length)
            return;

        // (Re)create hidden container
        var hiddenDivId = 'inputVarHiddenImages-' + vid;
        var $hiddenContainer = $('#' + hiddenDivId);
        if (!$hiddenContainer.length) {
            $hiddenContainer = $('<div id="' + hiddenDivId + '" style="display:none;"></div>');
            $grid.after($hiddenContainer);
        }

        // Ensure a per-variation hidden to persist how many tiles (including empties) we want to show
        var slotsHiddenId = 'inputVarHiddenImageSlots-' + vid;
        var $slotsHidden = $('#' + slotsHiddenId);
        if (!$slotsHidden.length) {
            $slotsHidden = $('<input type="hidden" id="' + slotsHiddenId + '" value="">');
            $hiddenContainer.append($slotsHidden);
        }
        // Optionally initialize savedSlots from DOM on first load
        if (!$slotsHidden.val()) {
            var initialCount = $grid.children('div[id^="' + IMG_PREFIX + '"]').length;
            $slotsHidden.val(String(initialCount));
        }

        // Persist full tile order (including empty slots)
        var orderHiddenId = 'inputVarHiddenTileOrder-' + vid;
        var $orderHidden = $('#' + orderHiddenId);
        if (!$orderHidden.length) {
            $orderHidden = $('<input type="hidden" id="' + orderHiddenId + '" value="">');
            $hiddenContainer.append($orderHidden);
        }

        // read persisted order (indices)
        var listIdsId = '#inputVarHiddenImageListIds-' + vid;
        var lengthId = '#inputVarHiddenImageLength-' + vid;
        var storedList = $(listIdsId).val() || '';
        var indices = storedList ? $.grep(storedList.split('|'), function (s) {
            return s !== '' && !isNaN(parseInt(s, 10));
        }) : [];

        // compute max existing index in the list
        var maxImage = -1;
        for (var k = 0; k < indices.length; k++) {
            var n = parseInt(indices[k], 10);
            if (!isNaN(n) && n > maxImage)
                maxImage = n;
        }

        // min 3, plus the persisted slot count, plus the highest used index (to never hide used images)
        var savedSlots = parseInt($slotsHidden.val(), 10);
        if (isNaN(savedSlots))
            savedSlots = 0;
        var totalSlots = Math.max(15, savedSlots, (maxImage >= 0 ? maxImage + 1 : 0));

        // Build a stable order of tile indices (includes empties)
        var order = ($orderHidden.val() || '')
                .split('|')
                .filter(Boolean)
                .map(function (s) {
                    var n = parseInt(s, 10);
                    return isNaN(n) ? null : n;
                })
                .filter(function (n) {
                    return n !== null;
                });

        // Ensure all indices that currently have URLs are present in 'order'
        indices.forEach(function (s) {
            var n = parseInt(s, 10);
            if (!isNaN(n) && order.indexOf(n) === -1)
                order.push(n);
        });

        // Grow or trim 'order' to match 'totalSlots' using fresh unique indices for empties
        var used = {};
        for (var i = 0; i < order.length; i++) {
            used[order[i]] = true;
        }
        var next = order.length ? Math.max.apply(null, order) + 1 : 0;
        while (order.length < totalSlots) {
            while (used[next])
                next++;
            order.push(next);
            used[next] = true;
        }
        if (order.length > totalSlots) {
            order = order.slice(0, totalSlots);
        }
        $orderHidden.val(order.join('|'));

        // ensure hidden inputs exist for every index referenced by 'order'
        var maxIdxInOrder = order.length ? Math.max.apply(null, order) : -1;
        ensureHiddenInputs($hiddenContainer, vid, maxIdxInOrder);

        // clear grid and (re)build strictly following 'order'
        if ($grid.hasClass('ui-sortable') && $.isFunction($grid.sortable)) {
            try {
                $grid.sortable('destroy');
            } catch (e) {
            }
        }
        $grid.empty();

        order.forEach(function (idx) {
            var uid = '-' + vid;
            var url = $('#' + HIDDEN_PREFIX + 'url_' + idx + uid).val() || '';
            var alt = $('#' + HIDDEN_PREFIX + 'alt_' + idx + uid).val() || '';
            var divId = IMG_PREFIX + idx + '-' + vid;

            $grid.append('<div id="' + divId + '" class="grid-square image" title="' + escAttr(alt) + '"></div>');

            if (isHttp(url)) {
                var adj = (typeof fifu_cdn_adjust === 'function') ? fifu_cdn_adjust(url) : url;
                $('#' + divId)
                        .attr('style', 'background: url("' + escAttr(adj) + '") center center / cover no-repeat; opacity:1;');
            }
        });

        // add "+" button
        var addBtnId = 'fifu-var-add-image-' + vid;
        $grid.append('<div id="' + addBtnId + '" class="grid-square image-add" title="+"></div>');

        // remember current "+" DOM index to avoid any auto-move on rebuild
        var domPlusIndex = $grid.children().index('#' + addBtnId); // -1 if "+" not present yet

        // restore "+" position strictly (no auto-move):
        // 1) if there is an explicit saved position, use it;
        // 2) otherwise use the position captured from the DOM before rebuild;
        // 3) otherwise leave it where it was appended.
        var plusPosInputId = 'inputVarHiddenPlusPos-' + vid;
        if (!$('#' + plusPosInputId).length) {
            $grid.after('<input type="hidden" id="' + plusPosInputId + '" value="">');
        }
        var savedPos = parseInt($('#' + plusPosInputId).val(), 10);
        var $tiles = $grid.children('.grid-square');

        var targetPos = !isNaN(savedPos) ? savedPos : (domPlusIndex >= 0 ? domPlusIndex : null);
        if (targetPos !== null && targetPos >= 0 && targetPos < $tiles.length) {
            $('#' + addBtnId).insertBefore($tiles.eq(targetPos));
        }

        // update persisted list/length (count only images with URL)
        function updateImageList() {
            var imageListIds = '';
            var count = 0;
            var uid = '-' + vid;

            $grid.find('div[id^="' + IMG_PREFIX + '"]').each(function () {
                var divId = $(this).attr('id');
                var idx = extractIndexFromDivId(divId, vid);
                if (idx === null)
                    return;

                var url = $('#' + HIDDEN_PREFIX + 'url_' + idx + uid).val();
                if (isHttp(url)) {
                    imageListIds += (count ? '|' : '') + idx;
                    count++;
                }
            });

            // Disable upload_image_button.remove if there is at least 1 image/url
            var $row = $grid.closest('.woocommerce_variation');
            var $uploadBtn = $row.find('.upload_image_button.remove');
            if (count > 0) {
                $uploadBtn.addClass('disabled').css('pointer-events', 'none').attr('aria-disabled', 'true');
            } else {
                $uploadBtn.removeClass('disabled').css('pointer-events', '').removeAttr('aria-disabled');
            }

            // Compare with previous values to avoid unnecessary work
            var prevIds = $(listIdsId).val() || '';
            var prevLen = $(lengthId).val() || '';
            var changed = (prevIds !== imageListIds) || (String(prevLen) !== String(count));

            // Persist new values
            $(listIdsId).val(imageListIds);
            $(lengthId).val(count);

            // Notify WooCommerce only when something actually changed
            if (changed)
                flagVariationChanged(vid);
        }

        // Utility: flag the variation as changed and enable the button
        function flagVariationChanged(vid) {
            var $grid = $('#gridDemoImage-' + vid);
            var $row = $grid.closest('.woocommerce_variation, .woocommerce_variable_attributes, .woocommerce_variations, #variable_product_options');
            $row.addClass('variation-needs-update');
            // Add a space to the test input field
            var $testInput = $('#fifu_test_input_' + vid);
            if ($testInput.length) {
                var val = $testInput.val() || '';
                $testInput.val(val + ' ').trigger('input').trigger('change');
            }
            // Fire a hook for any listeners (harmless if none)
            $(document).trigger('woocommerce_variations_input_changed', [$row, vid]);
        }

        // sortable: include "+" tile
        if ($grid.length && $.isFunction($grid.sortable)) {
            $grid.sortable({
                items: 'div[id^="' + IMG_PREFIX + '"], #' + addBtnId, // include "+"
                start: function () {
                    isDraggingImage = false;
                },
                stop: function () {
                    isDraggingImage = true;

                    // Recompute order from DOM (exclude the "+")
                    var newOrder = [];
                    $grid.children('div[id^="' + IMG_PREFIX + '"]').each(function () {
                        var idx = extractIndexFromDivId($(this).attr('id'), vid);
                        if (idx !== null)
                            newOrder.push(parseInt(idx, 10));
                    });
                    $orderHidden.val(newOrder.join('|'));

                    updateImageList();

                    // (optional) persist the "+" position
                    var plusIndex = $grid.children().index($('#' + addBtnId));
                    $('#' + plusPosInputId).val(plusIndex);
                }
            });
        }

        // open editor (Fancybox or prompt) for each tile
        $(document)
                .off('click.fifu-variation-' + vid, 'div[id^="' + IMG_PREFIX + '"][id$="-' + vid + '"]')
                .on('click.fifu-variation-' + vid, 'div[id^="' + IMG_PREFIX + '"][id$="-' + vid + '"]', function (evt) {
                    if (isDraggingImage) {
                        isDraggingImage = false;
                        return;
                    }
                    evt.stopImmediatePropagation();

                    var divId = $(this).attr('id');             // fifu-var-image-<idx>-<vid>
                    var index = extractIndexFromDivId(divId, vid);
                    if (index === null)
                        return;
                    var uid = '-' + vid;

                    var url = $('#' + HIDDEN_PREFIX + 'url_' + index + uid).val() || '';
                    var alt = $('#' + HIDDEN_PREFIX + 'alt_' + index + uid).val() || '';
                    var ifm = $('#' + HIDDEN_PREFIX + 'ifm_' + index + uid).val() || '';
                    var adjustedUrl = (typeof fifu_cdn_adjust === 'function') ? fifu_cdn_adjust(url) : url;

                    var altIfmFields =
                            '<span id="alt-ifm-fields-' + divId + '" style="display:none;">' +
                            '<input id="alt-input-image-' + divId + '" placeholder="' + escAttr((window.fifuBoxImageVars ? window.fifuBoxImageVars.text_alt : 'Alt')) + '" value="' + escAttr(alt) + '" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>' +
                            '<input id="ifm-input-image-' + divId + '" placeholder="' + escAttr((window.fifuBoxImageVars ? window.fifuBoxImageVars.text_ifm : 'IFM')) + '" value="' + escAttr(ifm) + '" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>' +
                            '</span>';

                    var imgTag = (isHttp(url))
                            ? '<img loading="lazy" id="img-fifu-image-' + divId + '" src="' + escAttr(adjustedUrl) + '" style="width:275px;margin-top:5px;margin-left:1px" ' +
                            ' onload="if(!window.FIFU_IMAGE_NOT_FOUND_URL||this.src.indexOf(window.FIFU_IMAGE_NOT_FOUND_URL)===-1){jQuery(\'#alt-ifm-fields-' + escAttr(divId) + '\').show();}" ' +
                            ' onerror="this.onerror=null;this.src=\'' + escAttr(window.FIFU_IMAGE_NOT_FOUND_URL || '') + '\';jQuery(\'#alt-ifm-fields-' + escAttr(divId) + '\').hide();"><br>'
                            : '';

                    var fancyContent =
                            '<input id="input-' + divId + '" placeholder="' + escAttr((window.fifuBoxImageVars ? window.fifuBoxImageVars.text_url : 'Image URL')) + '" value="' + escAttr(url) + '" style="width:275px;padding:10px;height:36px;margin-bottom:3px;"><br>' +
                            '<span id="span-img-fifu-image-' + divId + '">' + imgTag + '</span>' +
                            altIfmFields +
                            '<button id="button-fifu-image-' + divId + '" type="button" style="width:275px;padding:10px;height:36px;margin-bottom:3px;">' + escAttr((window.fifuBoxImageVars ? window.fifuBoxImageVars.text_ok : 'OK')) + '</button>';

                    if ($.fancybox && $.isFunction($.fancybox.open)) {
                        $.fancybox.open(fancyContent);
                    } else {
                        // Fallback: prompt
                        var newUrl = window.prompt((window.fifuBoxImageVars ? window.fifuBoxImageVars.text_url : 'Image URL'), url);
                        if (newUrl !== null) {
                            var v = isHttp(newUrl) ? (typeof fifu_convert === 'function' ? fifu_convert(newUrl) : newUrl) : '';
                            $('#' + HIDDEN_PREFIX + 'url_' + index + uid).val(v);
                            if (v) {
                                var adj = (typeof fifu_cdn_adjust === 'function') ? fifu_cdn_adjust(v) : v;
                                $('#' + divId).attr('style', 'background: url("' + escAttr(adj) + '") center center / cover no-repeat; opacity:1;');
                                fifu_woo_get_sizes('#' + HIDDEN_PREFIX + 'url_' + index + uid, '#' + HIDDEN_PREFIX + 'width_' + index + uid, '#' + HIDDEN_PREFIX + 'height_' + index + uid);
                            } else {
                                $('#' + divId).attr('style', '');
                                $('#' + HIDDEN_PREFIX + 'alt_' + index + uid).val('');
                                $('#' + HIDDEN_PREFIX + 'ifm_' + index + uid).val('');
                            }
                            updateImageList();
                        }
                        return;
                    }

                    // focus URL input inside Fancybox
                    $('#input-' + divId).focus().select();

                    // Atualiza preview apenas quando o valor da URL realmente muda (sem piscar com setas)
                    $(document)
                            .off('input.fifu-url-' + divId + ' keydown.fifu-url-' + divId + ' keyup.fifu-url-' + divId)
                            .on('input.fifu-url-' + divId, '#input-' + divId, function (e) {
                                e.stopImmediatePropagation();
                                var raw = $(this).val();
                                var v = isHttp(raw) ? (typeof fifu_convert === 'function' ? fifu_convert(raw) : raw) : '';
                                var uid = '-' + vid;

                                // Evita qualquer processamento se o valor efetivo não mudou
                                if (v === $('#' + HIDDEN_PREFIX + 'url_' + index + uid).val())
                                    return;

                                // Persiste
                                $('#' + HIDDEN_PREFIX + 'url_' + index + uid).val(v);

                                // Atualiza preview e tile só quando há mudança real
                                var adj = (typeof fifu_cdn_adjust === 'function') ? fifu_cdn_adjust(v) : v;
                                var $wrap = $('#span-img-fifu-image-' + divId);

                                if (v) {
                                    $wrap.empty().append(
                                            '<img loading="lazy" id="img-fifu-image-' + divId + '" src="' + escAttr(adj) + '" style="width:275px;margin-top:5px;margin-left:1px" ' +
                                            ' onload="jQuery(\'#alt-ifm-fields-' + escAttr(divId) + '\').show();" ' +
                                            ' onerror="this.onerror=null;this.src=\'' + escAttr(window.FIFU_IMAGE_NOT_FOUND_URL || '') + '\';jQuery(\'#alt-ifm-fields-' + escAttr(divId) + '\').hide();"><br>'
                                            );
                                    $('#' + divId).attr('style', 'background: url("' + escAttr(adj) + '") center center / cover no-repeat; opacity:1;');
                                    fifu_woo_get_sizes('#' + HIDDEN_PREFIX + 'url_' + index + uid,
                                            '#' + HIDDEN_PREFIX + 'width_' + index + uid,
                                            '#' + HIDDEN_PREFIX + 'height_' + index + uid);
                                } else {
                                    $wrap.empty();
                                    $('#' + divId).attr('style', '');
                                    $('#' + HIDDEN_PREFIX + 'alt_' + index + uid).val('');
                                    $('#' + HIDDEN_PREFIX + 'ifm_' + index + uid).val('');
                                    $('#alt-input-image-' + divId).val('');
                                    $('#ifm-input-image-' + divId).val('');
                                }

                                updateImageList();
                                flagVariationChanged(vid); // After editing URL
                            })
                            // Replace previous keydown handler for URL input:
                            .on('keydown.fifu-url-' + divId, '#input-' + divId, function (e) {
                                // Treat Enter as OK; Esc just closes. Prevent SelectWoo/global handlers.
                                if (e.isComposing)
                                    return; // IME in progress
                                var isEnter = e.key === 'Enter' || e.code === 'NumpadEnter' || e.which === 13 || e.keyCode === 13;
                                var isEsc = e.key === 'Escape' || e.which === 27 || e.keyCode === 27;
                                if (!isEnter && !isEsc)
                                    return;

                                e.preventDefault();
                                e.stopImmediatePropagation();
                                e.stopPropagation();

                                if (isEnter) {
                                    // Delegate to your existing OK button handler (updates, flags, closes)
                                    $('#button-fifu-image-' + divId).trigger('click');
                                } else {
                                    if ($.fancybox && $.isFunction($.fancybox.close))
                                        $.fancybox.close();
                                }
                                return false;
                            });

                    // ALT/IFM keyup (delegated)
                    $(document)
                            .off('keyup.fifu-altifm-' + divId)
                            .on('keyup.fifu-altifm-' + divId, '#alt-input-image-' + divId + ', #ifm-input-image-' + divId, function (e) {
                                e.stopImmediatePropagation();
                                var inputId = $(this).attr('id');
                                var val = $(this).val();
                                if (inputId.indexOf('alt-input-image-') === 0) {
                                    $('#' + HIDDEN_PREFIX + 'alt_' + index + uid).val(val);
                                } else {
                                    $('#' + HIDDEN_PREFIX + 'ifm_' + index + uid).val(val);
                                }
                                updateImageList();
                                flagVariationChanged(vid); // After editing ALT/IFM
                                if (e.which === 13 || e.which === 27) {
                                    if ($.fancybox && $.isFunction($.fancybox.close))
                                        $.fancybox.close();
                                }
                            })
                            // Add keydown for ALT/IFM fields (optional but recommended)
                            .on('keydown.fifu-altifm-' + divId, '#alt-input-image-' + divId + ', #ifm-input-image-' + divId, function (e) {
                                if (e.isComposing)
                                    return;
                                var isEnter = e.key === 'Enter' || e.code === 'NumpadEnter' || e.which === 13 || e.keyCode === 13;
                                var isEsc = e.key === 'Escape' || e.which === 27 || e.keyCode === 27;
                                if (!isEnter && !isEsc)
                                    return;

                                e.preventDefault();
                                e.stopImmediatePropagation();
                                e.stopPropagation();

                                if (isEnter) {
                                    $('#button-fifu-image-' + divId).trigger('click');
                                } else {
                                    if ($.fancybox && $.isFunction($.fancybox.close))
                                        $.fancybox.close();
                                }
                                return false;
                            });

                    // OK button (delegated)
                    $(document)
                            .off('click.fifu-ok-' + divId, '#button-fifu-image-' + divId)
                            .on('click.fifu-ok-' + divId, '#button-fifu-image-' + divId, function (e) {
                                e.stopImmediatePropagation();
                                var raw = $('#input-' + divId).val();
                                var v = isHttp(raw) ? (typeof fifu_convert === 'function' ? fifu_convert(raw) : raw) : '';

                                $('#' + HIDDEN_PREFIX + 'url_' + index + uid).val(v);
                                $('#' + HIDDEN_PREFIX + 'alt_' + index + uid).val($('#alt-input-image-' + divId).val() || '');
                                $('#' + HIDDEN_PREFIX + 'ifm_' + index + uid).val($('#ifm-input-image-' + divId).val() || '');

                                if (v) {
                                    var adj = (typeof fifu_cdn_adjust === 'function') ? fifu_cdn_adjust(v) : v;
                                    $('#' + divId).attr('style', 'background: url("' + escAttr(adj) + '") center center / cover no-repeat; opacity:1;');
                                    fifu_woo_get_sizes('#' + HIDDEN_PREFIX + 'url_' + index + uid, '#' + HIDDEN_PREFIX + 'width_' + index + uid, '#' + HIDDEN_PREFIX + 'height_' + index + uid);
                                } else {
                                    $('#' + divId).attr('style', '');
                                }

                                updateImageList();
                                // Remove image only if grid has no images/URLs left
                                var $grid = $('#gridDemoImage-' + vid);
                                var hasImages = false;
                                $grid.find('div[id^="' + IMG_PREFIX + '"]').each(function () {
                                    var divId = $(this).attr('id');
                                    var idx = extractIndexFromDivId(divId, vid);
                                    var url = $('#' + HIDDEN_PREFIX + 'url_' + idx + '-' + vid).val();
                                    if (isHttp(url)) {
                                        hasImages = true;
                                        return false; // break
                                    }
                                });
                                if (!hasImages) {
                                    var $row = $grid.closest('.woocommerce_variation');
                                    $row.find('.upload_image_button.remove').each(function () {
                                        $(this).trigger('click');
                                    });
                                    // Force remove tiptip_content regardless of text
                                    $('#tiptip_content').remove();
                                }
                                flagVariationChanged(vid); // After OK
                                // REMOVED: initVariationGrid(vid); // don't rebuild grid, keep "+" position
                                if ($.fancybox && $.isFunction($.fancybox.close))
                                    $.fancybox.close();
                            });
                });

        // add new image
        $(document)
                .off('click.fifu-var-add-' + vid, '#' + addBtnId)
                .on('click.fifu-var-add-' + vid, '#' + addBtnId, function (evt) {
                    if (isDraggingImage) {
                        isDraggingImage = false;
                        return;
                    }
                    evt.stopImmediatePropagation();

                    // Bump the persisted slot count so empties survive any future re-init
                    var currSlots = parseInt($slotsHidden.val(), 10);
                    if (isNaN(currSlots)) {
                        currSlots = $grid.children('div.grid-square').not('#' + addBtnId).length;
                    }
                    $slotsHidden.val(currSlots + 1);

                    // next index = 1 + max existing
                    var next = -1;
                    $grid.find('div[id^="' + IMG_PREFIX + '"]').each(function () {
                        var divId = $(this).attr('id');
                        var idx = parseInt(extractIndexFromDivId(divId, vid), 10);
                        if (!isNaN(idx) && idx > next)
                            next = idx;
                    });
                    next = next + 1;

                    // ensure inputs exist
                    ensureHiddenInputs($hiddenContainer, vid, next);

                    // add tile before "+"
                    var divId = IMG_PREFIX + next + '-' + vid;
                    $('<div id="' + divId + '" class="grid-square image"></div>')
                            .insertBefore('#' + addBtnId);

                    if ($grid.hasClass('ui-sortable'))
                        $grid.sortable('refresh');

                    // update the persisted order to insert the new index at the "+" position
                    var orderArr = ($orderHidden.val() || '')
                            .split('|').filter(Boolean).map(function (s) {
                        return parseInt(s, 10);
                    });
                    var insertPos = $grid.children().index($('#' + addBtnId));
                    orderArr.splice(insertPos, 0, next);
                    $orderHidden.val(orderArr.join('|'));

                    // persist current "+" index so any later re-init restores this exact spot
                    var plusIndexNow = $grid.children().index('#' + addBtnId);
                    $('#' + plusPosInputId).val(plusIndexNow);

                    updateImageList();
                    flagVariationChanged(vid);
                });

        // update list on mouse leave a tile (cheap safety)
        $grid.off('mouseleave.fifu-grid-' + vid, 'div.grid-square')
                .on('mouseleave.fifu-grid-' + vid, 'div.grid-square', function () {
                    updateImageList();
                });

        // initial sync
        updateImageList();
    }

    // init all existing grids on DOM ready
    $(function () {
        $('[id^="gridDemoImage-"]').each(function () {
            var id = $(this).attr('id');            // gridDemoImage-<vid>
            var parts = id.split('-');
            var vid = parts.slice(1).join('-');     // support hyphenated ids
            initVariationGrid(vid);
        });
    });

    // re-init when Woo components load variations (AJAX etc.)
    $(document).on('woocommerce_variations_loaded change', function () {
        $('[id^="gridDemoImage-"]').each(function () {
            var id = $(this).attr('id');
            var parts = id.split('-');
            var vid = parts.slice(1).join('-');
            initVariationGrid(vid);
        });
    });

    // observe multiple possible containers, not only the first
    var containers = document.querySelectorAll('#variable_product_options, .variations');
    if (containers && containers.length) {
        var mo = new MutationObserver(function () {
            $('[id^="gridDemoImage-"]').each(function () {
                var id = $(this).attr('id');
                var parts = id.split('-');
                var vid = parts.slice(1).join('-');
                initVariationGrid(vid);
            });
        });
        for (var i = 0; i < containers.length; i++) {
            mo.observe(containers[i], {childList: true, subtree: true});
        }
    }

    $(document).on('click', '#fifu_upload_icon', function () {
        $('#fifu_upload_cb').prop('checked', true);
    });
})(jQuery);

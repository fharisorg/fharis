// /html/js/buddyboss.jquery.js
// BuddyBoss activity comments & posts: paste image URL -> <img> with remove control.
// Firefox-friendly: NO navigator.clipboard usage (so no “Paste” chip).
// Avoids duplicate links by intercepting ONLY likely image-URL pastes.
/* global jQuery */

(function ($) {
    "use strict";

    // ---------------- constants/selectors ----------------
    var MAX_IMAGES_PER_PASTE = 12;
    var IMAGE_VALIDATE_TIMEOUT_MS = 7000;
    var DEBUG = true;

    // Editors (comments)
    var CE_SELECTOR_COMMENTS =
            'form.ac-form textarea, form.ac-form [contenteditable="true"].ac-input, .bb-activity-comment-form textarea';

    // Editors (posts) – matches your DOM: #whats-new and anything editable inside #whats-new-textarea
    var CE_SELECTOR_POSTS =
            '#whats-new[contenteditable="true"], #whats-new .medium-editor-element[contenteditable="true"], #whats-new-textarea [contenteditable="true"]';

    var CE_SELECTOR_ALL = CE_SELECTOR_COMMENTS + ', ' + CE_SELECTOR_POSTS;
    var FORM_SELECTOR = 'form.ac-form, form#whats-new-form';

    // Throttle so paste/beforeinput/input don’t triple-fire
    var LAST_HANDLE_BY_NODE = new WeakMap();
    var HANDLE_THROTTLE_MS = 180;

    // Heuristic: treat these as likely image URLs (so we intercept)
    var LIKELY_IMG_RE = /\.(?:png|jpe?g|gif|webp|bmp|svg|avif|heic|ico)(?:[?#].*)?$/i;

    // ---------------- debug ----------------
    function debugLog() {
        if (!DEBUG)
            return;
        var args = Array.prototype.slice.call(arguments);
        args.unshift("[fifu-buddyboss]");
        console.log.apply(console, args);
    }

    // ---------------- helpers ----------------
    function tokenize(text) {
        var re = /https?:\/\/[^\s<>"']+/ig, out = [], last = 0, m;
        while ((m = re.exec(text)) !== null) {
            if (m.index > last)
                out.push({type: "text", value: text.slice(last, m.index)});
            out.push({type: "url", value: m[0]});
            last = re.lastIndex;
        }
        if (last < text.length)
            out.push({type: "text", value: text.slice(last)});
        return out;
    }

    function extractUrls(text) {
        var m, out = [], re = /https?:\/\/[^\s<>"']+/ig;
        while ((m = re.exec(text)) !== null)
            out.push(m[0]);
        return out;
    }

    function hasLikelyImageUrl(text) {
        var urls = extractUrls(text);
        for (var i = 0; i < urls.length; i++) {
            if (LIKELY_IMG_RE.test(urls[i]))
                return true;
        }
        return false;
    }

    function validateImageUrl(url) {
        return new Promise(function (resolve) {
            var img = new Image(), done = false;
            var timer = setTimeout(function () {
                if (!done) {
                    done = true;
                    cleanup();
                    resolve(false);
                }
            }, IMAGE_VALIDATE_TIMEOUT_MS);
            function cleanup() {
                clearTimeout(timer);
                img.onload = img.onerror = null;
            }
            img.onload = function () {
                if (!done) {
                    done = true;
                    cleanup();
                    resolve(true);
                }
            };
            img.onerror = function () {
                if (!done) {
                    done = true;
                    cleanup();
                    resolve(false);
                }
            };
            img.referrerPolicy = "no-referrer";
            img.src = url;
        });
    }

    function escapeHTML(s) {
        return String(s)
                .replace(/&/g, "&amp;").replace(/</g, "&lt;")
                .replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    function getClipboardTextFromEvent(e) {
        var cd = e && (e.clipboardData || window.clipboardData);
        if (cd && cd.getData) {
            var t = cd.getData('text/plain') || cd.getData('text') || cd.getData('text/uri-list') || '';
            if (!t) {
                var html = cd.getData('text/html');
                if (html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = html;
                    t = tmp.textContent || '';
                }
            }
            return t || '';
        }
        return '';
    }

    function buildCombined(parts, validMap, isTextarea) {
        var out = "";
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i];
            if (p.type === "url" && validMap[p.value]) {
                var safeSrc = p.value.replace(/"/g, "&quot;");
                out += ''
                        + '<div class="fifu-img-wrap">'
                        + '<img src="' + safeSrc + '" loading="lazy" />'
                        + '<span class="fifu-img-remove" role="button" tabindex="0" aria-label="Remove image" contenteditable="false">'
                        + '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>'
                        + '</span>'
                        + '</div>';
            } else {
                out += isTextarea ? p.value : escapeHTML(p.value).replace(/\n/g, "<br>");
            }
        }
        return out;
    }

    // ---------------- editor insertion / cleanup ----------------
    function insertHTML(target, html, isTextarea, placeCaretBefore) {
        var $el = $(target);

        if (isTextarea) {
            var el = $el.get(0);
            el.focus();
            var s = el.selectionStart || 0, e = el.selectionEnd || 0, v = el.value || "";
            el.value = v.slice(0, s) + html + v.slice(e);
            var pos = placeCaretBefore ? s : s + html.length;
            el.selectionStart = el.selectionEnd = pos;
            $el.trigger("input");
            return;
        }

        var elCE = $el.get(0);
        elCE.focus();
        var sel = window.getSelection && window.getSelection();
        var range = sel && sel.rangeCount ? sel.getRangeAt(0) : null;
        if (!range) {
            range = document.createRange();
            range.selectNodeContents(elCE);
            range.collapse(false);
        }

        var startContainer = range.startContainer;
        var startOffset = range.startOffset;

        var tmp = document.createElement("div");
        tmp.innerHTML = html;
        var frag = document.createDocumentFragment();
        while (tmp.firstChild)
            frag.appendChild(tmp.firstChild);
        range.insertNode(frag);

        if (sel) {
            sel.removeAllRanges();
            var r = document.createRange();
            if (placeCaretBefore) {
                var maxOffset = startContainer && startContainer.nodeType === 3
                        ? startContainer.length
                        : (startContainer ? startContainer.childNodes.length : 0);
                r.setStart(startContainer || elCE, Math.min(startOffset, maxOffset));
                r.collapse(true);
            } else {
                r.selectNodeContents(elCE);
                r.collapse(false);
            }
            sel.addRange(r);
        }
        $el.trigger("input");
    }

    function removeBareUrlsIfImagePresent(target) {
        var $t = $(target);
        var isTA = $t.is("textarea");
        var html = isTA ? $t.val() : $t.html();

        var srcs = [], m, imgRe = /<img[^>]+(?:src|data-src|data-lazy-src|data-original)=["']([^"']+)["'][^>]*>/ig;
        while ((m = imgRe.exec(html)) !== null) {
            srcs.push(m[1]);
        }
        if (!srcs.length)
            return;

        for (var i = 0; i < srcs.length; i++) {
            var q = srcs[i].replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
            html = html.replace(new RegExp('<a[^>]+href=["\']' + q + '(?:\\?[^"\']*)?["\'][^>]*>.*?<\\/a>', "ig"), "");
            try {
                html = html.replace(new RegExp('(?<![\'">])' + q + '(?:\\?[^\\s<>"\']*)?', "ig"), "");
            } catch (_e) {
                html = html.replace(new RegExp(q + '(?:\\?[^\\s<>"\']*)?', "ig"), "");
            }
        }

        if (isTA)
            $t.val(html);
        else
            $t.html(html);
    }

    // ---------------- placeholder helpers ----------------
    function ensurePlaceholderForEditor(field) {
        var $f = $(field);
        if (!$f.length)
            return;

        if ($f.is("textarea")) {
            if ($f.data("fifuPlaceholder"))
                return;
            var val = $f.val() || "";
            if (!/\s$/.test(val))
                $f.val(val + " ");
            $f.data("fifuPlaceholder", true);
            $f.trigger("input");
            return;
        }

        if ($f.data("fifuPlaceholderNode") && $f.data("fifuPlaceholderNode").parentNode === field)
            return;
        var node = document.createTextNode(" ");
        field.appendChild(node);
        $f.data("fifuPlaceholderNode", node);
        $f.trigger("input");
    }

    function removePlaceholderFromEditor(field) {
        var $f = $(field);
        if (!$f.length)
            return;

        if ($f.is("textarea")) {
            if (!$f.data("fifuPlaceholder"))
                return;
            $f.val(($f.val() || "").replace(/\s+$/, ""));
            $f.data("fifuPlaceholder", false);
            $f.trigger("input");
            return;
        }
        var node = $f.data("fifuPlaceholderNode");
        if (node && node.parentNode === field) {
            node.parentNode.removeChild(node);
            $f.trigger("input");
        }
        $f.removeData("fifuPlaceholderNode");
    }

    function syncPlaceholderState(field) {
        var $f = $(field);
        if (!$f.length || $f.is("textarea"))
            return;
        var hasImages = $f.find("img").length > 0;
        var text = ($f.text() || "").trim();
        if (hasImages && !text)
            ensurePlaceholderForEditor(field);
        else
            removePlaceholderFromEditor(field);
    }

    // ---------------- image wrappers ----------------
    function wrapImagesInEditor(fieldCE) {
        var $root = $(fieldCE);
        if (!$root.length || $root.is("textarea"))
            return;

        $root.find("img").each(function () {
            var img = this;
            if ($(img).closest(".fifu-img-wrap").length)
                return;

            var $wrap = $('<div class="fifu-img-wrap"></div>');
            $(img).before($wrap);
            $wrap.append(img);

            var $btn = $(
                    '<span class="fifu-img-remove" role="button" tabindex="0" aria-label="Remove image" contenteditable="false">' +
                    '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>' +
                    '</span>'
                    );
            $wrap.append($btn);
        });

        syncPlaceholderState(fieldCE);
    }

    function unwrapImagesInField(fieldCE) {
        var $root = $(fieldCE);
        if (!$root.length || $root.is("textarea"))
            return;

        $root.find(".fifu-img-wrap").each(function () {
            var $wrap = $(this);
            var $img = $wrap.find("img").first();
            if (!$img.length) {
                $wrap.remove();
                return;
            }
            $wrap.before($img);
            $wrap.remove();
        });
    }

    // ---------------- remove control handlers ----------------
    function initRemoveHandlers() {
        $(document).off('mousedown keydown', '.fifu-img-remove');

        // Mouse: act before blur → first click works
        $(document).on('mousedown', '.fifu-img-remove', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            var $wrap = $(this).closest('.fifu-img-wrap');
            if (!$wrap.length)
                return;

            var field = $wrap.closest(CE_SELECTOR_ALL).get(0);
            $wrap.remove();

            if (field) {
                $(field).trigger('input');
                syncPlaceholderState(field);
            }
        });

        // Keyboard accessibility: Space / Enter
        $(document).on('keydown', '.fifu-img-remove', function (e) {
            if (e.key !== ' ' && e.key !== 'Enter')
                return;
            e.preventDefault();
            e.stopPropagation();
            $(this).trigger('mousedown');
        });
    }

    // ---------------- core transform ----------------
    function shouldThrottle(node) {
        var now = Date.now();
        var last = LAST_HANDLE_BY_NODE.get(node) || 0;
        if (now - last < HANDLE_THROTTLE_MS)
            return true;
        LAST_HANDLE_BY_NODE.set(node, now);
        return false;
    }

    function handlePasteLike(targetEl, text) {
        if (!text || !/https?:\/\//i.test(text))
            return;
        if (shouldThrottle(targetEl))
            return;

        var $target = $(targetEl);
        var isTextarea = $target.is('textarea');

        debugLog('paste-like', {target: targetEl, text: text});

        var parts = tokenize(text);
        var urls = $.map($.grep(parts, function (p) {
            return p.type === "url";
        }), function (p) {
            return p.value;
        });
        if (!urls.length)
            return; // nothing to do

        if (urls.length > MAX_IMAGES_PER_PASTE)
            urls = urls.slice(0, MAX_IMAGES_PER_PASTE);

        Promise.all($.map(urls, function (u) {
            return validateImageUrl(u);
        })).then(function (results) {
            var validMap = {};
            for (var i = 0; i < urls.length; i++)
                if (results[i])
                    validMap[urls[i]] = true;

            // No images validated -> bail; DO NOT insert text (prevents duplicate non-image links)
            if (!Object.keys(validMap).length)
                return;

            var html = buildCombined(parts, validMap, isTextarea);
            html = "<br>" + html + "<br>";
            insertHTML(targetEl, html, isTextarea, /* caret before */ true);

            setTimeout(function () {
                removeBareUrlsIfImagePresent(targetEl);
            }, 0);

            if (!isTextarea) {
                wrapImagesInEditor(targetEl);
                syncPlaceholderState(targetEl);
            } else {
                ensurePlaceholderForEditor(targetEl);
            }
        });
    }

    // ---------------- event listeners (capture phase) ----------------
    function onPasteCapture(e) {
        var $target = $(e.target).closest(CE_SELECTOR_ALL);
        if (!$target.length)
            return;

        var text = getClipboardTextFromEvent(e);
        // Intercept ONLY likely image-URL pastes; let others pass (no duplication)
        if (!text || !hasLikelyImageUrl(text))
            return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        handlePasteLike($target.get(0), text);
    }

    function onBeforeInputCapture(e) {
        if (e.inputType !== 'insertFromPaste')
            return;
        var $target = $(e.target).closest(CE_SELECTOR_ALL);
        if (!$target.length)
            return;

        var text = getClipboardTextFromEvent(e);
        if (!text || !hasLikelyImageUrl(text))
            return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        handlePasteLike($target.get(0), text);
    }

    function onInputCapture(e) {
        if (e.inputType !== 'insertFromPaste')
            return;
        var $target = $(e.target).closest(CE_SELECTOR_ALL);
        if (!$target.length)
            return;

        var text = getClipboardTextFromEvent(e);
        if (!text || !hasLikelyImageUrl(text))
            return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        handlePasteLike($target.get(0), text);
    }

    // ---------------- fallback: brief MutationObserver to catch image-URL paste when events are swallowed ----------------
    function startFallbackWatcher(editorEl) {
        var el = editorEl;
        if (!el)
            return;

        var armed = true;
        var mo = new MutationObserver(function () {
            if (!armed)
                return;
            var txt = (el.innerText || '').trim();
            if (hasLikelyImageUrl(txt)) {
                handlePasteLike(el, txt);
                armed = false;
                mo.disconnect();
            }
        });
        mo.observe(el, {childList: true, characterData: true, subtree: true});
        setTimeout(function () {
            armed = false;
            mo.disconnect();
        }, 600);
    }

    // ---------------- bind to post editors directly ----------------
    var _bound = new WeakSet();

    function bindDirect(root) {
        $(root || document).find(CE_SELECTOR_POSTS).each(function () {
            if (_bound.has(this))
                return;
            this.addEventListener('paste', onPasteCapture, true);
            this.addEventListener('beforeinput', onBeforeInputCapture, true);
            this.addEventListener('input', onInputCapture, true);

            // If a theme still swallows everything, arm the fallback watcher on Ctrl/⌘+V
            this.addEventListener('keydown', function (e) {
                var isPasteGesture = (e.key === 'v' || e.key === 'V') && (e.ctrlKey || e.metaKey) && !e.altKey;
                if (isPasteGesture)
                    setTimeout(startFallbackWatcher.bind(null, this), 0);
            }, true);

            _bound.add(this);
            debugLog('bound listeners on', this);
        });
    }

    // ---------------- focus/blur & submit ----------------
    $(document)
            .on("focusin", CE_SELECTOR_ALL, function () {
                wrapImagesInEditor(this);
            })
            .on("focusout", CE_SELECTOR_ALL, function (e) {
                var to = e.relatedTarget;
                if (to && $(to).closest(".fifu-img-remove, .fifu-img-wrap").length)
                    return;
                unwrapImagesInField(this);
            });

    $(document).on("submit", FORM_SELECTOR, function () {
        var $form = $(this);
        var ce = $form.find(CE_SELECTOR_ALL).filter('[contenteditable="true"]').get(0);
        if (ce) {
            unwrapImagesInField(ce);
            removePlaceholderFromEditor(ce);
        }
        $form.find("textarea").each(function () {
            removePlaceholderFromEditor(this);
        });
    });

    // ---------------- init ----------------
    $(document).ready(function () {
        // Safety net for comments / global
        document.addEventListener("paste", onPasteCapture, true);
        document.addEventListener("beforeinput", onBeforeInputCapture, true);
        document.addEventListener("input", onInputCapture, true);

        initRemoveHandlers();
        bindDirect(document);
    });

    if (window.MutationObserver) {
        new MutationObserver(function (ml) {
            for (var i = 0; i < ml.length; i++) {
                var nodes = ml[i].addedNodes || [];
                for (var j = 0; j < nodes.length; j++) {
                    var n = nodes[j];
                    if (n.nodeType !== 1)
                        continue;
                    bindDirect(n);
                }
            }
        }).observe(document.body, {childList: true, subtree: true});
    }

})(jQuery);

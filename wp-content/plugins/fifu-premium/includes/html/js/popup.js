jQuery(document).ready(function () {
    jQuery('body').on('click', function (e) {
        if (jQuery('.fancybox-content').length)
            return;

        // console.log('I clicked on...', e.target);
        if (jQuery(e.target).is('img[fifu-featured]') || jQuery(e.target).closest('*[style*=background-image]').length) {
            let post_id = jQuery(e.target).attr('post-id');
            let html = fifuPopupVars['html'][post_id];
            if (!html)
                return;

            e.preventDefault();
            e.stopPropagation();

            fifu_open_popup(post_id, e);
        } else {
            if (jQuery(e.target).is('a') && jQuery(e.target).has('img[fifu-featured]').length) {
                let post_id = jQuery(e.target).attr('post-id');
                let html = fifuPopupVars['html'][post_id];
                if (!html)
                    return;

                e.preventDefault();
                e.stopPropagation();

                fifu_open_popup(post_id, e);
            }
        }
    });
});

function fifu_open_popup(post_id) {
    let html = fifuPopupVars['html'][post_id];
    if (!html)
        return;

    let opts = {
        toolbar: false,
        downloadButton: true,
        closeBtn: false,
    }

    let $html = jQuery(normalizeString(html));
    let $embed = $html.children().first();

    $embed.css('width', '101%');
    $embed.css('height', '100%');

    jQuery.fancybox.open($html, opts);

    $html.css({
        width: $embed.width() + 'px',
        height: $embed.height() + 'px'
    });
}

function normalizeString(str) {
    var tempEl = jQuery('<div/>').html(str);
    let isMobile = window.innerWidth <= 768;
    let w_ratio = isMobile ? 0.95 : 0.85;
    let h_ratio = isMobile ? 0.95 : 0.85;
    let w = jQuery(window).width() * w_ratio;
    let h = jQuery(window).height() * h_ratio;
    let max_w = jQuery(window).width();
    let max_h = jQuery(window).height() * 0.95;
    return `<div class="fifu-popup-container" style="width:${w}px; height:${h}px; max-width:${max_w}px; max-height:${max_h}px;">${tempEl.text()}</div>`;
}

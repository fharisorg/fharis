function isYoutubeUrl($url) {
    return $url.includes("youtu");
}

function isVimeoUrl($url) {
    return $url.includes("vimeo.com");
}

function isCloudinaryVideoUrl($url) {
    return $url.includes("cloudinary.com") && $url.includes("/video/");
}

function isTumblrVideoUrl($url) {
    return $url.includes("tumblr.com");
}

function isLocalVideoUrl($url) {
    return $url.includes(window.location.hostname) && $url.includes(fifuVideoUtilVars.uploadDir) && ($url.includes("mp4") || $url.includes("mov") || $url.includes("webm") || $url.includes("m3u8"));
}

function isCustomVideoUrl($url) {
    if (fifu_is_automatic_video($url))
        return false;
    return $url.includes(".mp4") || $url.includes(".mov") || $url.includes(".webm") || $url.includes(".m3u8");
}

function isPublitioVideoUrl($url) {
    return $url.includes("publit.io") && $url.includes("mp4");
}

function isGagVideoUrl($url) {
    return $url.includes("9cache.com");
}

function isWpcomVideoUrl($url) {
    return $url.includes("videos.files.wordpress.com") && ($url.includes(".mp4") || $url.includes(".mov"));
}

function isTiktokVideoUrl($url) {
    return $url.includes("tiktok.com");
}

function isGoogledriveVideoUrl($url) {
    return $url.includes("drive.google.com/file");
}

function isMegaVideoUrl($url) {
    return $url.includes("mega.nz");
}

function isBunnyVideoUrl($url) {
    return $url.includes("video.bunnycdn.com");
}

function isBitchuteVideoUrl($url) {
    return $url.includes("www.bitchute.com");
}

function isBrighteonVideoUrl($url) {
    return $url.includes("www.brighteon.com");
}

function isSoundcloudVideoUrl($url) {
    return $url.includes("soundcloud.com");
}

function isSpotifyVideoUrl($url) {
    return $url.includes("spotify.com");
}

function isAmazonVideoUrl($url) {
    return $url.includes("m.media-amazon.com") && $url.includes(".mp4");
}

function isJwplayerVideoUrl($url) {
    return $url.includes("jwplayer.com");
}

function isSproutVideoUrl($url) {
    return $url.includes("vids.io");
}

function isRumbleVideoUrl($url) {
    return $url.includes("rumble.com");
}

function isDailymotionVideoUrl($url) {
    return $url.includes("dailymotion.com");
}

function isTwitterVideoUrl($url) {
    return $url.includes("twitter.com");
}

function isCloudflarestreamVideoUrl($url) {
    return $url.includes("cloudflarestream.com");
}

function isOdyseeVideoUrl($url) {
    return $url.includes("odysee.com");
}

function isSuVideoUrl($url) {
    return $url.includes("fifu-thumb=");
}

function idYoutube($url) {
    var $regex = /^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user|shorts)\/))([^\?&\"'>]+)/;
    return $res = $url.match($regex);
}

function idVimeo($url) {
    var $regex = /^(http\:\/\/|https\:\/\/)?(www\.)?(vimeo\.com\/)([0-9]+[\/]*[a-z0-9]*)+.*$/;
    return $res = $url.match($regex);
}

function idJwplayer($url) {
    return $url.split('/')[4].split('.')[0];
}

function idTiktok($url) {
    return $url.split('/')[5].split(/[^0-9]/)[0];
}

function idGoogledrive($url) {
    return $url.split('/')[5].split('/')[0];
}

function idMega($url) {
    return $url.split('/')[4].split('!')[0];
}

function idBunny($url) {
    return $url.split('/play/')[1];
}

function idBitchute($url) {
    return $url.split('/video/')[1];
}

function idBrighteon($url) {
    return $url.split('/')[3];
}

function idSpotify($url) {
    return $url.split('?')[0].split('track/')[1];
}

function idOdysee($url) {
    arr = $url.split('/');
    return arr[3] + '/' + arr[4];
}

function srcYoutube($url) {
    return "https://www.youtube.com/embed/" + idYoutube($url)[1];
}

function srcVimeo($url) {
    return "https://player.vimeo.com/video/" + idVimeo($url)[4].replace('/', '?h=');
}

function srcCloudinary($url) {
    return $url;
}

function srcTumblr($url) {
    return $url;
}

function srcLocal($url) {
    return $url;
}

function srcCustom($url) {
    return $url;
}

function srcPublitio($url) {
    return $url;
}

function srcGag($url) {
    return $url;
}

function srcWpcom($url) {
    return $url;
}

function srcTiktok($url) {
    return "https://www.tiktok.com/embed/v2/" + idTiktok($url);
}

function srcGoogledrive($url) {
    return "https://drive.google.com/file/d/" + idGoogledrive($url) + "/preview";
}

function srcMega($url) {
    return "https://mega.nz/embed/" + idMega($url);
}

function srcBunny($url) {
    return "https://video.bunnycdn.com/embed/" + idBunny($url);
}

function srcBitchute($url) {
    return "https://www.bitchute.com/embed/" + idBitchute($url);
}

function srcBrighteon($url) {
    return "https://www.brighteon.com/embed/" + idBrighteon($url);
}

function srcSoundcloud($url) {
    return fifu_video_src($url);
}

function srcSpotify($url) {
    return "https://open.spotify.com/embed/track/" + idSpotify($url) + "?utm_source=generator";
}

function srcAmazon($url) {
    return $url;
}

function srcJwplayer($url) {
    return "https://content.jwplatform.com/players/" + idJwplayer($url) + ".html";
}

function srcSprout($url) {
    return fifu_video_src($url);
}

function srcRumble($url) {
    return fifu_video_src($url);
}

function srcDailymotion($url) {
    return $url.replace('/video/', '/embed/video/');
}

function srcTwitter($url) {
    return fifu_video_src($url);
}

function srcCloudflarestream($url) {
    return $url.replace(/manifest\/video.*/, 'iframe');
}

function srcOdysee($url) {
    return "https://odysee.com/$/embed/" + idOdysee($url);
}

function srcVideo($url) {
    if (isYoutubeUrl($url))
        return srcYoutube($url);
    if (isVimeoUrl($url))
        return srcVimeo($url);
    if (isCloudinaryVideoUrl($url))
        return srcCloudinary($url);
    if (isTumblrVideoUrl($url))
        return srcTumblr($url);
    if (isLocalVideoUrl($url))
        return srcLocal($url);
    if (isPublitioVideoUrl($url))
        return srcPublitio($url);
    if (isGagVideoUrl($url))
        return srcGag($url);
    if (isWpcomVideoUrl($url))
        return srcWpcom($url);
    if (isTiktokVideoUrl($url))
        return srcTiktok($url);
    if (isGoogledriveVideoUrl($url))
        return srcGoogledrive($url);
    if (isMegaVideoUrl($url))
        return srcMega($url);
    if (isBunnyVideoUrl($url))
        return srcBunny($url);
    if (isBitchuteVideoUrl($url))
        return srcBitchute($url);
    if (isBrighteonVideoUrl($url))
        return srcBrighteon($url);
    if (isSoundcloudVideoUrl($url))
        return srcSoundcloud($url);
    if (isSpotifyVideoUrl($url))
        return srcSpotify($url);
    if (isAmazonVideoUrl($url))
        return srcAmazon($url);
    if (isJwplayerVideoUrl($url))
        return srcJwplayer($url);
    if (isSproutVideoUrl($url))
        return srcSprout($url);
    if (isRumbleVideoUrl($url))
        return srcRumble($url);
    if (isDailymotionVideoUrl($url))
        return srcDailymotion($url);
    if (isTwitterVideoUrl($url))
        return srcTwitter($url);
    if (isCloudflarestreamVideoUrl($url))
        return srcCloudflarestream($url);
    if (isOdyseeVideoUrl($url))
        return srcOdysee($url);
    if (isCustomVideoUrl($url))
        return srcCustom($url);
    return null;
}

function fifu_is_video($url) {
    return fifu_is_automatic_video($url) || fifu_is_custom_video($url);
}

function fifu_is_automatic_video($url) {
    return (
            isSuVideoUrl($url) ||
            isYoutubeUrl($url) ||
            isVimeoUrl($url) ||
            isCloudinaryVideoUrl($url) ||
            isTumblrVideoUrl($url) ||
            isLocalVideoUrl($url) ||
            isPublitioVideoUrl($url) ||
            isGagVideoUrl($url) ||
            isWpcomVideoUrl($url) ||
            isTiktokVideoUrl($url) ||
            isGoogledriveVideoUrl($url) ||
            isMegaVideoUrl($url) ||
            isBunnyVideoUrl($url) ||
            isBitchuteVideoUrl($url) ||
            isBrighteonVideoUrl($url) ||
            isSoundcloudVideoUrl($url) ||
            isSpotifyVideoUrl($url) ||
            isAmazonVideoUrl($url) ||
            isJwplayerVideoUrl($url) ||
            isSproutVideoUrl($url) ||
            isRumbleVideoUrl($url) ||
            isDailymotionVideoUrl($url) ||
            isTwitterVideoUrl($url) ||
            isCloudflarestreamVideoUrl($url) ||
            isOdyseeVideoUrl($url)
            );
}

function fifu_is_custom_video($url) {
    return isCustomVideoUrl($url);
}

async function fifu_video_image_thumbnail(url, vars) {
    if (!fifu_is_video(url))
        return;

    try {
        const data = await jQuery.ajax({
            method: "POST",
            url: vars.restUrl + 'fifu-premium/v2/video_image_thumbnail/',
            data: {"url": url},
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', vars.nonce);
            },
            dataType: "json"
        });
        return data.image_url; // <-- Access the property
    } catch (error) {
        console.error('AJAX error:', error);
        throw error;
    }
}

function fifu_format_previous_input(url) {
    if (!url)
        return '';
    let delimiter = '?';
    if (isYoutubeUrl(url))
        delimiter = '&';
    return url.split(delimiter)[0].split('#t=')[0];
}

/*! FIFU Auto-Share — minimal client (no UI libs) */
(function ($, window, document) {
    'use strict';

    // ---- Config from PHP (wp_localize_script) ----
    const V = window.fifuScriptVars || {};
    const REST_BASE = (V.restUrl || '/wp-json/').replace(/\/?$/, '/'); // ensure trailing slash
    const API_ROOT = REST_BASE + 'fifu-premium/v2';
    const NONCE = V.nonce || '';

    // ---- Endpoints (PHP will proxy to Worker) ----
    const API = {
        startAuth: (p) => `${API_ROOT}/social/${p}/auth/start`,
        finalizeAuth: (p) => `${API_ROOT}/social/${p}/auth/finalize`,
        status: (p) => `${API_ROOT}/social/${p}/status`
    };

    // ---- Small helpers ----
    async function wpFetch(url, opts = {}) {
        const headers = new Headers(opts.headers || {});
        headers.set('Accept', 'application/json');
        if (opts.method && opts.method !== 'GET' && !headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }
        if (NONCE)
            headers.set('X-WP-Nonce', NONCE);

        const res = await fetch(url, {...opts, headers, credentials: 'same-origin'});
        const text = await res.text();
        if (!res.ok)
            throw new Error(`HTTP ${res.status} ${text || res.statusText}`);
        try {
            return text ? JSON.parse(text) : {};
        } catch {
            return {};
    }
    }

    function setStatus(label, account) {
        $('#fifu-status-text').text(label || '—');
        if (account) {
            $('#fifu-account-badge').text(account);
            $('#fifu-account-row').removeClass('fifu-hidden');
        } else {
            $('#fifu-account-row').addClass('fifu-hidden');
        }
    }

    async function refreshStatus(provider) {
        try {
            const d = await wpFetch(API.status(provider), {method: 'GET'});
            if (d && d.connected) {
                setStatus('Connected', `Facebook: ${d.accountName || 'Connected'}`);
            } else {
                setStatus('Not connected');
            }
        } catch {
            setStatus('Unknown');
        }
    }

    function openPopup(url, name, w = 520, h = 680) {
        const dualLeft = window.screenLeft ?? screen.left;
        const dualTop = window.screenTop ?? screen.top;
        const width = window.innerWidth || document.documentElement.clientWidth || screen.width;
        const height = window.innerHeight || document.documentElement.clientHeight || screen.height;
        const left = (width - w) / 2 + dualLeft;
        const top = (height - h) / 2 + dualTop;
        return window.open(url, name, `scrollbars=yes,resizable=yes,width=${w},height=${h},top=${top},left=${left}`);
    }

    // ---- Core: start → popup → postMessage → finalize → status ----
    async function startAuth(provider) {
        setStatus('Starting…');

        // Ask PHP for the Worker-generated auth URL
        const {authUrl, state} = await wpFetch(API.startAuth(provider), {
            method: 'POST',
            body: JSON.stringify({provider, intent: 'login', version: 1})
        });

        if (!authUrl) {
            setStatus('Error');
            return;
        }

        const popup = openPopup(authUrl, `fifu_${provider}_oauth`);
        if (!popup) {
            setStatus('Popup blocked');
            return;
        }

        // NOTE: In production, restrict origin below (e.g., to your Worker/PHP redirect origin)
        const onMessage = async (ev) => {
            const msg = ev.data || {};
            if (msg && msg.source === 'fifu-worker' && msg.flow === 'oauth' && msg.provider === provider) {
                window.removeEventListener('message', onMessage);
                try {
                    popup.close();
                } catch {
                }

                if (msg.status !== 'success') {
                    setStatus('Authorization failed');
                    return;
                }

                setStatus('Finalizing…');

                // Exchange temp token via PHP (PHP talks to Worker)
                try {
                    await wpFetch(API.finalizeAuth(provider), {
                        method: 'POST',
                        body: JSON.stringify({provider, tempToken: msg.tempToken, state})
                    });
                    await refreshStatus(provider);
                } catch {
                    setStatus('Finalize failed');
                }
            }
        };

        window.addEventListener('message', onMessage);

        // Fallback: user closes popup without finishing
        const watcher = setInterval(async () => {
            if (popup.closed) {
                clearInterval(watcher);
                // Give backend a chance; then refresh
                await refreshStatus(provider);
            }
        }, 600);
    }

    // ---- Bind & init ----
    $(function () {
        $(document).on('click', '#fifu-connect-facebook', function (e) {
            e.preventDefault();
            startAuth($(this).data('provider') || 'facebook');
        });

        refreshStatus('facebook');
    });

})(jQuery, window, document);

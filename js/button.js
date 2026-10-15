(function () {
    var cfg = window.griffnodeButton || {};
    var cryptos = null; // one /merchant/cryptos request per page, shared by every button

    function loadCryptos() {
        if (!cryptos) {
            cryptos = fetch(cfg.apiBase + '/merchant/cryptos', { headers: { 'Authorization': 'Bearer ' + cfg.pk } })
                .then(function (r) { return r.json(); })
                .then(function (d) { return (d && d.data && d.data.cryptocurrencies) || []; });
        }
        return cryptos;
    }

    function setOptions(sel, list, emptyText) {
        sel.textContent = '';
        if (!list.length) { list = [{ symbol: '', label: emptyText }]; }
        list.forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.symbol;
            o.textContent = c.label || (c.name + ' (' + c.symbol + ')');
            sel.appendChild(o);
        });
    }

    function initButton(wrap) {
        if (wrap.dataset.cgInit) { return; }
        wrap.dataset.cgInit = '1';

        var lock = wrap.dataset.lock || '';
        var sel  = wrap.querySelector('.cg-crypto');
        var btn  = wrap.querySelector('.cg-pay');
        var msg  = wrap.querySelector('.cg-msg');
        var i18n = cfg.i18n || {};

        if (sel && cfg.pk) {
            loadCryptos()
                .then(function (list) { setOptions(sel, list, i18n.noCryptos); })
                .catch(function () { setOptions(sel, [], i18n.loadFailed); });
        } else if (sel) {
            setOptions(sel, [], i18n.noKey);
        }

        btn.addEventListener('click', function () {
            var crypto = lock || (sel ? sel.value : '');
            if (!crypto) { msg.textContent = i18n.selectCrypto; return; }
            btn.disabled = true;
            msg.textContent = i18n.creating;

            var body = new URLSearchParams();
            body.set('action', 'griffnode_create_payment');
            body.set('nonce', cfg.nonce);
            body.set('token', wrap.dataset.token);
            body.set('crypto', crypto);

            fetch(cfg.ajax, { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success && res.data && res.data.payment_url) {
                        window.location.href = res.data.payment_url;
                    } else {
                        btn.disabled = false;
                        msg.textContent = (res && res.data && res.data.error) || i18n.payError;
                    }
                })
                .catch(function () { btn.disabled = false; msg.textContent = i18n.netError; });
        });
    }

    function initAll() {
        document.querySelectorAll('.griffnode-button-wrap').forEach(initButton);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();

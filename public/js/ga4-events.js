/*
 * Eventos GA4 por delegación (Lima View Tours, H13). Sin dependencias.
 * Envía por window.lvtTrack (gtag + dataLayer), definido en layouts/app.blade.php.
 * NO toca consent ni carga tags. Si lvtTrack no existe, no hace nada.
 *
 * Atributos:
 *   data-ga-cta="etiqueta" data-ga-location="hero|card|sticky|ficha|reviews|404|whatsapp"  -> cta_click
 *   data-ga-select data-ga-item-id data-ga-item-name data-ga-price [data-ga-list]       -> select_item
 *   <details data-ga-faq="ficha">                                                       -> faq_open (al abrir)
 *   data-ga-view="social_proof_view" [data-ga-count]                                    -> evento al verse 50%
 *   <script type="application/json" id="lvt-ga-cart">                                   -> view_cart
 */
(function () {
    'use strict';

    function track(name, params) {
        if (typeof window.lvtTrack === 'function') {
            window.lvtTrack(name, params || {}, { fb: null });
        }
    }

    function pathInfo() {
        var m = location.pathname.match(/^\/(es|en|pt)(\/|$)/);
        return { idioma: m ? m[1] : document.documentElement.lang || '' };
    }

    // select_item + cta_click (clic en tarjeta de tour / CTAs marcados)
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;

        var card = t.closest('[data-ga-select]');
        if (card && t.closest('a[href]')) {
            var price = parseFloat(card.getAttribute('data-ga-price'));
            track('select_item', {
                item_list_name: card.getAttribute('data-ga-list') || (location.pathname.indexOf('/tours') !== -1 ? 'listado' : 'recomendados'),
                items: [{
                    item_id: card.getAttribute('data-ga-item-id') || '',
                    item_name: card.getAttribute('data-ga-item-name') || '',
                    price: isNaN(price) ? 0 : price,
                    quantity: 1
                }]
            });
        }

        var cta = t.closest('[data-ga-cta]');
        if (cta) {
            track('cta_click', {
                location: cta.getAttribute('data-ga-location') || 'otro',
                cta: cta.getAttribute('data-ga-cta') || '',
                idioma: pathInfo().idioma
            });
        }
    }, true);

    // faq_open: 'toggle' no burbujea -> captura en document
    document.addEventListener('toggle', function (e) {
        var d = e.target;
        if (!d || d.tagName !== 'DETAILS' || !d.open || !d.hasAttribute('data-ga-faq')) return;
        track('faq_open', { location: d.getAttribute('data-ga-faq') || 'ficha', idioma: pathInfo().idioma });
    }, true);

    // Eventos de visualización (una sola vez por elemento)
    function observeViews() {
        var els = document.querySelectorAll('[data-ga-view]');
        if (!els.length) return;
        function fire(el) {
            var p = { idioma: pathInfo().idioma };
            var c = parseInt(el.getAttribute('data-ga-count'), 10);
            if (!isNaN(c)) p.count = c;
            track(el.getAttribute('data-ga-view'), p);
        }
        if (!('IntersectionObserver' in window)) return;
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { io.unobserve(en.target); fire(en.target); }
            });
        }, { threshold: 0.5 });
        els.forEach(function (el) { io.observe(el); });
    }

    function viewCart() {
        var node = document.getElementById('lvt-ga-cart');
        if (!node) return;
        try {
            var payload = JSON.parse(node.textContent);
            if (payload && payload.items && payload.items.length) track('view_cart', payload);
        } catch (err) { /* JSON inválido: no se envía nada */ }
    }

    function init() { observeViews(); viewCart(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

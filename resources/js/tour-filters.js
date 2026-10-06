/**
 * Filtros de /tours (Tarea D) — filtrado y orden 100% cliente sobre
 * atributos data-* ya normalizados por el Blade (ver tours/index.blade.php).
 *
 * Defecto de la referencia (besttoursinlima.com/destinos-peru/cusco) que NO
 * se repite aquí: allá las tarjetas traían data-duracion="0" y
 * data-idiomas="" vacíos, así que activar esos filtros vaciaba el listado
 * completo. Acá cada dato sale normalizado del registro real del tour desde
 * el servidor; si un tour no tiene ese dato, su atributo queda vacío y
 * simplemente no aparece bajo esa faceta (no se inventa un valor).
 */
(function () {
    'use strict';

    function init() {
        var root = document.querySelector('[data-tour-filters]');
        if (!root) return;

        var i18n = {};
        try {
            i18n = JSON.parse(root.getAttribute('data-i18n') || '{}');
        } catch (e) {
            i18n = {};
        }

        var containers = Array.prototype.slice.call(root.querySelectorAll('[data-tour-cards]'));
        var countEls = Array.prototype.slice.call(root.querySelectorAll('[data-tour-count]'));
        var seeCountEls = Array.prototype.slice.call(root.querySelectorAll('[data-see-count]'));
        var emptyEl = root.querySelector('[data-tour-empty]');
        var sortSelects = Array.prototype.slice.call(root.querySelectorAll('[data-sort]'));
        var priceInputs = Array.prototype.slice.call(root.querySelectorAll('[data-filter-price]'));
        var priceOutputs = Array.prototype.slice.call(root.querySelectorAll('[data-filter-price-output]'));
        var offerInputs = Array.prototype.slice.call(root.querySelectorAll('[data-filter-offer]'));
        var groupCheckboxes = Array.prototype.slice.call(root.querySelectorAll('[data-filter-checkbox]'));
        var clearButtons = Array.prototype.slice.call(root.querySelectorAll('[data-filters-clear]'));

        var priceCeilingAttr = priceInputs.length ? priceInputs[0].getAttribute('max') : null;
        var priceCeiling = priceCeilingAttr !== null ? parseFloat(priceCeilingAttr) : null;

        var state = {
            destinations: new Set(),
            durations: new Set(),
            languages: new Set(),
            modalities: new Set(),
            onOffer: false,
            maxPrice: priceCeiling,
        };

        function formatTemplate(tpl, count) {
            return (tpl || '').replace(':count', count);
        }

        function toursFoundText(count) {
            if (count === 0) return i18n.toursFoundZero || '';
            if (count === 1) return formatTemplate(i18n.toursFoundOne, count);
            return formatTemplate(i18n.toursFoundOther, count);
        }

        function seeToursText(count) {
            if (count === 1) return formatTemplate(i18n.seeToursOne, count);
            return formatTemplate(i18n.seeToursOther, count);
        }

        function cardMatches(card) {
            var price = parseFloat(card.getAttribute('data-price') || '0');
            var duration = card.getAttribute('data-duration') || '';
            var languages = (card.getAttribute('data-languages') || '').split(',').filter(Boolean);
            var group = card.getAttribute('data-group') || '';
            var region = card.getAttribute('data-region') || '';
            var isOffer = card.getAttribute('data-offer') === '1';

            if (state.destinations.size && !state.destinations.has(region)) return false;
            if (state.durations.size && !state.durations.has(duration)) return false;
            if (state.modalities.size && !state.modalities.has(group)) return false;
            if (state.languages.size) {
                var anyMatch = languages.some(function (l) { return state.languages.has(l); });
                if (!anyMatch) return false;
            }
            if (state.onOffer && !isOffer) return false;
            if (state.maxPrice !== null && price > state.maxPrice) return false;

            return true;
        }

        function applyFilters() {
            var visibleCount = null;

            containers.forEach(function (container) {
                var cards = Array.prototype.slice.call(container.querySelectorAll('[data-tour-card]'));
                var visible = 0;
                cards.forEach(function (card) {
                    var show = cardMatches(card);
                    card.classList.toggle('hidden', !show);
                    if (show) visible++;
                });
                if (visibleCount === null) visibleCount = visible;
                // Vacío: style.display, NO la clase `hidden` — esa la usa el breakpoint
                // (flex sm:hidden / hidden sm:grid) y quitarla mostraba ambos contenedores.
                container.style.display = visible === 0 ? 'none' : '';
            });

            if (visibleCount === null) visibleCount = 0;

            countEls.forEach(function (el) { el.textContent = toursFoundText(visibleCount); });
            seeCountEls.forEach(function (el) { el.textContent = seeToursText(visibleCount); });

            if (emptyEl) emptyEl.classList.toggle('hidden', visibleCount > 0);

            return visibleCount;
        }

        function sortValue(card, key) {
            if (key === 'price-asc' || key === 'price-desc') {
                return parseFloat(card.getAttribute('data-price') || '0');
            }
            if (key === 'duration') {
                var w = card.getAttribute('data-duration-weight');
                return w ? parseFloat(w) : Number.MAX_SAFE_INTEGER;
            }
            if (key === 'name') {
                return card.getAttribute('data-name') || '';
            }
            return null;
        }

        function applySort(key) {
            containers.forEach(function (container) {
                var cards = Array.prototype.slice.call(container.querySelectorAll('[data-tour-card]'));
                if (!key || key === 'recommended') {
                    cards.sort(function (a, b) {
                        return parseInt(a.getAttribute('data-index') || '0', 10) - parseInt(b.getAttribute('data-index') || '0', 10);
                    });
                } else {
                    var dir = key === 'price-desc' ? -1 : 1;
                    cards.sort(function (a, b) {
                        var va = sortValue(a, key);
                        var vb = sortValue(b, key);
                        if (va < vb) return -1 * dir;
                        if (va > vb) return 1 * dir;
                        return 0;
                    });
                }
                cards.forEach(function (card) { container.appendChild(card); });
            });
        }

        function mirror(elements, changed) {
            var group = changed.getAttribute('data-filter-group');
            var value = changed.value;
            elements.forEach(function (el) {
                if (el === changed) return;
                if (el.getAttribute('data-filter-group') === group && el.value === value) {
                    el.checked = changed.checked;
                }
            });
        }

        function collectGroupState(group, set) {
            set.clear();
            groupCheckboxes
                .filter(function (el) { return el.getAttribute('data-filter-group') === group && el.checked; })
                .forEach(function (el) { set.add(el.value); });
        }

        groupCheckboxes.forEach(function (el) {
            el.addEventListener('change', function () {
                mirror(groupCheckboxes, el);
                collectGroupState('destination', state.destinations);
                collectGroupState('duration', state.durations);
                collectGroupState('language', state.languages);
                collectGroupState('modality', state.modalities);
                applyFilters();
            });
        });

        priceInputs.forEach(function (input) {
            input.addEventListener('input', function () {
                var val = parseFloat(input.value);
                state.maxPrice = val;
                priceInputs.forEach(function (other) { if (other !== input) other.value = input.value; });
                priceOutputs.forEach(function (out) {
                    out.textContent = formatTemplate(i18n.upToPrice, input.value);
                });
                applyFilters();
            });
        });

        offerInputs.forEach(function (el) {
            el.addEventListener('change', function () {
                state.onOffer = el.checked;
                offerInputs.forEach(function (other) { if (other !== el) other.checked = el.checked; });
                applyFilters();
            });
        });

        sortSelects.forEach(function (select) {
            select.addEventListener('change', function () {
                var key = select.value;
                sortSelects.forEach(function (other) { if (other !== select) other.value = key; });
                applySort(key);
            });
        });

        function resetFilters() {
            groupCheckboxes.forEach(function (el) { el.checked = false; });
            offerInputs.forEach(function (el) { el.checked = false; });
            priceInputs.forEach(function (el) {
                if (priceCeilingAttr !== null) el.value = priceCeilingAttr;
            });
            priceOutputs.forEach(function (out) {
                out.textContent = formatTemplate(i18n.upToPrice, priceCeilingAttr || '');
            });
            sortSelects.forEach(function (el) { el.value = 'recommended'; });
            state.destinations.clear();
            state.durations.clear();
            state.languages.clear();
            state.modalities.clear();
            state.onOffer = false;
            state.maxPrice = priceCeiling;
            applySort('recommended');
            applyFilters();
        }

        clearButtons.forEach(function (btn) {
            btn.addEventListener('click', resetFilters);
        });

        // ── Panel móvil: overlay + drawer + foco ────────────────────────────
        var openButtons = Array.prototype.slice.call(root.querySelectorAll('[data-filters-open]'));
        var closeButtons = Array.prototype.slice.call(root.querySelectorAll('[data-filters-close]'));
        var overlay = root.querySelector('[data-filters-overlay]');
        var panel = root.querySelector('[data-filters-panel]');
        var lastFocused = null;

        function trapFocus(e) {
            if (e.key !== 'Tab' || !panel) return;
            var focusable = panel.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        function openDrawer(triggerEl) {
            if (!panel || !overlay) return;
            lastFocused = triggerEl || document.activeElement;
            overlay.classList.remove('hidden');
            panel.classList.remove('translate-x-full');
            panel.setAttribute('aria-hidden', 'false');
            openButtons.forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
            document.body.classList.add('overflow-hidden');
            window.addEventListener('keydown', onKeydown);
            var closeBtn = panel.querySelector('[data-filters-close]');
            if (closeBtn) closeBtn.focus();
        }

        function closeDrawer() {
            if (!panel || !overlay) return;
            overlay.classList.add('hidden');
            panel.classList.add('translate-x-full');
            panel.setAttribute('aria-hidden', 'true');
            openButtons.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
            document.body.classList.remove('overflow-hidden');
            window.removeEventListener('keydown', onKeydown);
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        }

        function onKeydown(e) {
            if (e.key === 'Escape') { closeDrawer(); return; }
            trapFocus(e);
        }

        openButtons.forEach(function (btn) {
            btn.addEventListener('click', function () { openDrawer(btn); });
        });
        closeButtons.forEach(function (btn) {
            btn.addEventListener('click', closeDrawer);
        });
        if (overlay) overlay.addEventListener('click', closeDrawer);

        // ── Acordeón de grupos (desktop + móvil) ────────────────────────────
        var accordionTriggers = Array.prototype.slice.call(root.querySelectorAll('[data-accordion-trigger]'));
        accordionTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var expanded = trigger.getAttribute('aria-expanded') === 'true';
                trigger.setAttribute('aria-expanded', (!expanded).toString());
                var targetId = trigger.getAttribute('aria-controls');
                var target = targetId ? document.getElementById(targetId) : null;
                if (target) target.classList.toggle('hidden', expanded);
            });
        });

        applySort('recommended');
        applyFilters();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

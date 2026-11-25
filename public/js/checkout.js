/*
 * Checkout nuevo (21-nov-2026) — pantalla única.
 * Consume window.LVT_CO (inyectado por resources/views/checkout.blade.php) y los
 * endpoints del back (CheckoutController / CartController).
 * Los montos oficiales salen del servidor (LVT_CO.checkout.modes y GET checkout.summary).
 * Al editar personas se hace un recálculo OPTIMISTA local (mismo redondeo a centavos que
 * App\Support\PaymentPlan / CartService) para que la UI responda al instante; el request al
 * servidor va con debounce y su respuesta MANDA (reconcilia). Antes de cobrar/revisar se
 * espera a que no haya cambios sin confirmar. Al servidor solo viaja el MODO.
 */
(function () {
    'use strict';

    var C = window.LVT_CO;
    if (!C) return;
    var T = C.i18n;
    var doc = document;

    function $(sel, root) { return (root || doc).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }
    var CSRF = (doc.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var state = {
        mode: C.defaultMode,
        method: C.defaultMethod,
        checkout: C.checkout,
        items: C.items.slice(),
        busy: false,
        edits: 0,           // contador de ediciones locales (descarta respuestas viejas)
        dirty: 0,           // cajas con cambio local aún no enviado
        inflight: 0,        // PATCH+summary en vuelo
        calculating: false, // no se pudo replicar el total localmente
        serverCheckout: C.checkout,
        waiters: [],
        paypalRendered: false,
        lastFocus: null,
    };

    function money(n) {
        return 'US$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function plan() { return state.checkout.modes[state.mode]; }
    function reducedMotion() { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }

    // ── GA4 ──────────────────────────────────────────────────────
    function track(name, params, meta) {
        if (typeof window.lvtTrack === 'function') window.lvtTrack(name, params, meta || { fb: null });
    }
    function trackPaymentInfo() {
        var a = state.checkout.analytics && state.checkout.analytics.add_payment_info;
        if (!a) return;
        var byMode = a.value_by_mode || {};
        track('add_payment_info', {
            currency: a.currency,
            value: byMode[state.mode] != null ? byMode[state.mode] : plan().due,
            items: a.items,
            payment_type: state.mode === 'later' ? 'pay_later' : (state.method || ''),
            payment_mode: state.mode,
        });
    }
    function trackBegin() {
        var b = state.checkout.analytics && state.checkout.analytics.begin_checkout;
        if (b && b.items && b.items.length) track('begin_checkout', b, { fb: 'InitiateCheckout' });
    }

    // ── Cálculo local optimista (réplica de PaymentPlan/CartService, en centavos) ──
    function toCents(x) { return Math.round(Number(x) * 100 + (x >= 0 ? 1e-7 : -1e-7)); }
    function round2(x) { return toCents(x) / 100; }

    // Devuelve el checkout recalculado o null si no se puede replicar con certeza.
    function localCheckout() {
        var sub = 0;
        state.items.forEach(function (it) { sub += toCents(round2(it.unit_price * (it.adults + it.children))); });
        var subtotal = sub / 100, discount = 0;
        var cp = C.coupon || {};
        if (cp.code) {
            if (cp.type === 'percent' && cp.value != null) discount = round2(subtotal * Number(cp.value) / 100);
            else if (cp.type === 'fixed' && cp.value != null) discount = Math.min(Number(cp.value), subtotal);
            else return null; // cupón desconocido: que decida el servidor
        }
        var totalCents = Math.max(0, toCents(round2(subtotal - discount)));
        function split(mode) {
            var due = mode === 'full' ? totalCents : (mode === 'deposit' ? Math.round(totalCents * 50 / 100) : 0);
            return { mode: mode, total: totalCents / 100, due: due / 100, balance: (totalCents - due) / 100,
                total_cents: totalCents, due_cents: due, balance_cents: totalCents - due };
        }
        return { subtotal: subtotal, discount: discount, total: totalCents / 100, total_cents: totalCents,
            modes: { full: split('full'), deposit: split('deposit'), later: split('later') } };
    }

    function applyLocal() {
        var l = localCheckout();
        if (!l) { state.calculating = true; return; }
        state.calculating = false;
        var k = {}, x;
        for (x in state.checkout) k[x] = state.checkout[x];
        k.subtotal = l.subtotal; k.discount = l.discount; k.total = l.total; k.total_cents = l.total_cents; k.modes = l.modes;
        state.checkout = k;
    }

    // ── Pintar importes ─────────────────────────────────────────
    function renderLines() {
        var disc = state.checkout.discount || 0;
        var html = state.items.map(function (it) {
            var n = it.adults + it.children;
            return '<div class="co-moneyline"><span>' + n + ' ' + (n === 1 ? T.adultOne : T.adultMany) + ' × ' + money(it.unit_price) +
                '</span><strong>' + money(it.unit_price * n) + '</strong></div>';
        }).join('');
        if (disc > 0) {
            var label = C.coupon && C.coupon.code ? T.discountLine.replace(':code', C.coupon.code) : T.discountPlain;
            html += '<div class="co-moneyline co-moneyline--ok"><span></span><strong>−' + money(disc) + '</strong></div>';
            // el texto del cupón va por textContent (viene de sesión)
            var tmp = doc.createElement('div');
            tmp.innerHTML = html;
            var spans = tmp.querySelectorAll('.co-moneyline--ok span');
            spans[spans.length - 1].textContent = label;
            html = tmp.innerHTML;
        }
        $$('[data-co-lines]').forEach(function (n) { n.innerHTML = html; });
        var multi = state.items.length > 1 || disc > 0;
        $$('[data-co-totalrow]').forEach(function (n) { n.hidden = !multi; });
    }

    function render() {
        var p = plan();
        var m = state.mode;
        var calc = state.calculating, dots = '…';
        $$('[data-co-due]').forEach(function (n) { n.textContent = calc ? dots : money(p.due); });
        $$('[data-co-total]').forEach(function (n) { n.textContent = calc ? dots : money(p.total); });
        $$('[data-co-balance]').forEach(function (n) { n.textContent = calc ? dots : money(m === 'later' ? p.total : p.balance); });
        $$('[data-co-balance-label]').forEach(function (n) { n.textContent = m === 'later' ? T.laterBalanceLabel : T.balanceLabel; });
        $$('[data-co-balancerow]').forEach(function (n) { n.hidden = (m === 'later' ? p.total : p.balance) <= 0; });
        $$('[data-co-sticky-note]').forEach(function (n) {
            n.textContent = m === 'deposit' ? T.modeDeposit : (m === 'full' ? T.modeFull : T.modeLater);
        });
        $$('[data-co-cta-label]').forEach(function (n) { n.textContent = m === 'later' ? T.continueLater : T.continuePay; });
        $$('[data-co-copy]').forEach(function (n) { n.hidden = n.getAttribute('data-co-copy') !== m; });
        renderLines();

        var methods = $('#co-methods');
        if (methods) methods.hidden = m === 'later';

        $$('input[name="payment_mode"]').forEach(function (r) { r.closest('.co-paychoice').classList.toggle('is-selected', r.checked); });
        $$('input[name="pay_method"]').forEach(function (r) { r.closest('.co-paychoice').classList.toggle('is-selected', r.checked); });

        $$('[data-co-pax]').forEach(function (out) {
            var box = out.closest('[data-co-box]');
            var it = itemByRow(box.getAttribute('data-row-id'));
            if (!it) return;
            var n = it.adults + it.children;
            out.textContent = n;
            var dec = $('[data-co-dec]', box), inc = $('[data-co-inc]', box);
            dec.disabled = n <= 1;
            inc.disabled = n >= C.maxPax;
            var note = $('[data-co-paxnote]', box);
            if (note) note.hidden = n < C.maxPax;
        });
    }

    function itemByRow(rowId) {
        for (var i = 0; i < state.items.length; i++) if (state.items[i].row_id === rowId) return state.items[i];
        return null;
    }

    // ── Red ──────────────────────────────────────────────────────
    function api(url, opts) {
        opts = opts || {};
        var headers = {
            'X-CSRF-TOKEN': CSRF,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        };
        if (opts.body) headers['Content-Type'] = 'application/json';
        return fetch(url, {
            method: opts.method || 'GET',
            headers: headers,
            credentials: 'same-origin',
            body: opts.body ? JSON.stringify(opts.body) : undefined,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) { return { res: res, data: data }; });
        });
    }

    // Reconcilia con el servidor (manda el servidor). Si el usuario editó de nuevo mientras
    // viajaba la respuesta, se descarta: la siguiente sincronización traerá el valor vigente.
    function refreshSummary(seq) {
        return api(state.checkout.endpoints.summary).then(function (r) {
            if (r.res.ok && r.data && r.data.modes) {
                // La fecha cambió y ahora la salida es (o dejó de ser) en < 48 h: cambian los modos
                // de pago permitidos; se recarga para pintar solo los válidos.
                if (state.checkout && typeof r.data.full_only !== 'undefined' && !!r.data.full_only !== !!state.checkout.full_only) {
                    window.location.reload();
                    return;
                }
                state.serverCheckout = r.data;
                if (seq === state.edits) {
                    state.checkout = r.data;
                    state.calculating = false;
                    render();
                }
            }
        }).catch(function () {
            if (seq === state.edits && state.calculating) { state.calculating = false; render(); }
        });
    }

    function isIdle() { return state.dirty === 0 && state.inflight === 0; }
    function notifyIdle() {
        if (!isIdle()) return;
        var w = state.waiters; state.waiters = [];
        w.forEach(function (fn) { fn(); });
    }
    // Ejecuta fn cuando no haya cambios sin confirmar (nunca cobrar sobre un valor local).
    function whenSettled(fn) {
        $$('[data-co-box]').forEach(function (b) {
            if (b._co && b._co.timer) { clearTimeout(b._co.timer); b._co.timer = null; flushPax(b); }
        });
        if (isIdle()) fn(); else state.waiters.push(fn);
    }

    // ── Editar carrito: fecha y personas ─────────────────────────
    function updateRowIds(oldId, newId) {
        if (oldId === newId) return;
        $$('[data-row-id="' + oldId + '"]').forEach(function (n) { n.setAttribute('data-row-id', newId); });
        var it = itemByRow(oldId);
        if (it) it.row_id = newId;
    }

    function patchItem(box, body) {
        var rowId = box.getAttribute('data-row-id');
        var url = C.urls.cartUpdate.replace('__ROW__', encodeURIComponent(rowId));
        state.inflight++;
        var seq = state.edits;
        return api(url, { method: 'PATCH', body: body }).then(function (r) {
            if (r.res.ok && r.data && r.data.success) {
                updateRowIds(rowId, r.data.row_id || rowId);
                return refreshSummary(seq).then(function () { return { ok: true }; });
            }
            var msg = r.data && (r.data.message || (r.data.errors && Object.keys(r.data.errors).length && r.data.errors[Object.keys(r.data.errors)[0]][0]));
            return { ok: false, message: msg || T.dateSavedError };
        }).catch(function () { return { ok: false, message: T.dateSavedError }; }).then(function (res) {
            state.inflight--;
            return res;
        });
    }

    function setDateError(box, msg) {
        var field = $('[data-co-field="date"]', box);
        var err = $('[data-co-date-err]', box);
        if (msg) {
            err.textContent = msg;
            field.classList.add('is-invalid');
        } else {
            err.textContent = T.dateInvalid;
            field.classList.remove('is-invalid');
        }
    }

    function changePax(box, delta) {
        var it = itemByRow(box.getAttribute('data-row-id'));
        if (!it) return;
        var n = it.adults + it.children + delta;
        if (n < 1 || n > C.maxPax) return;
        var co = box._co || (box._co = { timer: null, running: false, again: false, active: false, prev: null });
        // Último estado confirmado por el servidor (para revertir si el PATCH falla).
        if (!co.prev) co.prev = { a: it.adults, c: it.children };
        // Adultos y niños pagan igual: el contador del diseño es solo de personas (adultos).
        it.adults = n; it.children = 0;
        state.edits++;
        // Recálculo optimista INSTANTÁNEO; el servidor reconcilia después.
        applyLocal();
        render();
        if (!co.active) { co.active = true; state.dirty++; }
        if (co.timer) clearTimeout(co.timer);
        co.timer = setTimeout(function () { co.timer = null; flushPax(box); }, 250);
    }

    // Envía al servidor el valor vigente de la caja (debounce + 1 request a la vez por caja).
    function flushPax(box) {
        var co = box._co;
        if (!co) return;
        if (co.timer) { clearTimeout(co.timer); co.timer = null; }
        var it = itemByRow(box.getAttribute('data-row-id'));
        if (co.running) { co.again = true; return; }   // al terminar el actual se reenvía
        co.running = true;
        co.again = false;
        var n = it ? it.adults + it.children : 1;
        patchItem(box, { adults: n, children: 0 }).then(function (r) {
            co.running = false;
            if (!r.ok) {
                // Falló: volver al último valor confirmado y a los montos del servidor.
                var cur = itemByRow(box.getAttribute('data-row-id'));
                if (cur && co.prev) { cur.adults = co.prev.a; cur.children = co.prev.c; }
                state.edits++;
                state.checkout = state.serverCheckout;
                state.calculating = false;
                if (co.timer) { clearTimeout(co.timer); co.timer = null; }
                co.again = false;
                co.prev = null;
                render();
                setDateError(box, r.message);
                // Marcado por un fallo de PERSONAS (no de la fecha): se limpia en el próximo PATCH exitoso.
                box.dataset.paxErr = '1';
            } else if (box.dataset.paxErr) {
                delete box.dataset.paxErr;
                setDateError(box, null);
            }
            if (r.ok && co.again) {
                flushPax(box);
                return;
            } else if (co.timer) {
                return; // hubo otro click durante el vuelo: el debounce pendiente lo enviará
            } else {
                co.prev = null;
            }
            co.active = false;
            state.dirty--;
            notifyIdle();
        });
    }

    function onDateChange(input) {
        var box = input.closest('[data-co-box]');
        var it = itemByRow(box.getAttribute('data-row-id'));
        if (!it) return;
        var min = input.getAttribute('min') || '';
        if (!input.value || (min && input.value < min)) {
            setDateError(box, T.dateInvalid);
            return;
        }
        setDateError(box, null);
        patchItem(box, { adults: it.adults + it.children, children: 0, travel_date: input.value }).then(function (r) {
            if (!r.ok) setDateError(box, r.message);
            else it.travel_date = input.value;
        });
    }

    function removeItem(article) {
        if (!window.confirm(T.removeConfirm)) return;
        var rowId = article.getAttribute('data-row-id');
        api(C.urls.cartDestroy.replace('__ROW__', encodeURIComponent(rowId)), { method: 'DELETE' }).then(function () {
            window.location.reload();
        });
    }

    // ── Validación ──────────────────────────────────────────────
    function fieldOf(el) { return el.closest('.co-field'); }
    function mark(el, valid) {
        var f = fieldOf(el);
        if (f) f.classList.toggle('is-invalid', !valid);
    }

    function customer() {
        var dial = $('#co-dial').value || '+51';
        var digits = ($('#co-phone').value || '').replace(/\D/g, '');
        return {
            customer_name: $('#co-name').value.trim(),
            customer_email: $('#co-email').value.trim(),
            customer_phone: digits ? dial + digits : '',
            pickup_point: ($('#co-hotel') || {}).value ? $('#co-hotel').value.trim() : '',
            notes: ($('#co-notes') || {}).value ? $('#co-notes').value.trim() : '',
            accept_terms: $('#co-terms').checked,
        };
    }
    function syncPhone() { $('#co-phone-full').value = customer().customer_phone; }

    function validate() {
        var first = null;
        function test(ok, el) { if (!ok && !first) first = el; }

        $$('[data-co-box]').forEach(function (box) {
            var input = $('[data-co-date]', box);
            var min = input.getAttribute('min') || '';
            var field = fieldOf(input);
            var ok = !!input.value && (!min || input.value >= min);
            // Fecha marcada por el servidor (vencida/no disponible) y sin tocar: sigue inválida.
            if (ok && field.classList.contains('is-invalid') && !input.dataset.touched) ok = false;
            if (ok) setDateError(box, null);
            else if (!field.classList.contains('is-invalid')) setDateError(box, T.dateInvalid);
            test(ok, input);
        });

        var c = customer();
        var nameEl = $('#co-name'), phoneEl = $('#co-phone'), emailEl = $('#co-email');
        var nameOk = c.customer_name.split(/\s+/).filter(Boolean).length >= 2;
        var phoneOk = /^\+\d{7,15}$/.test(c.customer_phone);
        var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(c.customer_email);
        mark(nameEl, nameOk); mark(phoneEl, phoneOk); mark(emailEl, emailOk);
        test(nameOk, nameEl); test(phoneOk, phoneEl); test(emailOk, emailEl);

        var termsErr = $('#co-terms-err');
        termsErr.classList.toggle('is-on', !c.accept_terms);
        test(c.accept_terms, $('#co-terms'));

        if (first) {
            first.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
            setTimeout(function () { try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); } }, 120);
            return false;
        }
        return true;
    }

    // ── Modales ─────────────────────────────────────────────────
    function openModal(id) {
        var m = $(id);
        state.lastFocus = doc.activeElement;
        m.hidden = false;
        doc.body.classList.add('co-lock');
        var close = $('.co-close', m);
        if (close) close.focus();
    }
    function closeModals() {
        $$('.co-modal').forEach(function (m) { m.hidden = true; });
        doc.body.classList.remove('co-lock');
        if (state.lastFocus && state.lastFocus.focus) { try { state.lastFocus.focus(); } catch (e) {} }
    }
    function trap(ev) {
        if (ev.key !== 'Tab') return;
        var m = $$('.co-modal').filter(function (x) { return !x.hidden; })[0];
        if (!m) return;
        var f = $$('button:not([disabled]):not([hidden]), a[href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])', m)
            .filter(function (n) { return n.offsetParent !== null || n === doc.activeElement; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (ev.shiftKey && doc.activeElement === first) { ev.preventDefault(); last.focus(); }
        else if (!ev.shiftKey && doc.activeElement === last) { ev.preventDefault(); first.focus(); }
    }

    function showMsg(text) {
        var el = $('#co-msg');
        el.textContent = text || T.genericError;
        el.classList.add('is-on');
        el.scrollIntoView({ block: 'nearest', behavior: reducedMotion() ? 'auto' : 'smooth' });
    }
    function hideMsg() { var el = $('#co-msg'); el.textContent = ''; el.classList.remove('is-on'); }

    function longDate(iso) {
        if (!iso) return '';
        try {
            var p = iso.split('-');
            return new Intl.DateTimeFormat(C.locale, { day: 'numeric', month: 'long', year: 'numeric' })
                .format(new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2])));
        } catch (e) { return iso; }
    }

    function openReview() {
        hideMsg();
        var p = plan();
        var list = $('[data-co-review-items]');
        list.innerHTML = '';
        state.items.forEach(function (it) {
            var n = it.adults + it.children;
            var a = doc.createElement('article'); a.className = 'co-choice';
            var img = doc.createElement('img'); img.src = it.img; img.alt = ''; img.width = 92; img.height = 74;
            var d = doc.createElement('div');
            var h = doc.createElement('h4'); h.textContent = it.title;
            var pp = doc.createElement('p');
            var inputDate = $('[data-co-box][data-row-id="' + it.row_id + '"] [data-co-date]');
            var parts = [longDate(inputDate ? inputDate.value : it.travel_date)];
            if (it.time) parts.push(it.time);
            parts.push(n + ' ' + (n === 1 ? T.peopleOne : T.peopleMany));
            pp.textContent = parts.join(' · ');
            d.appendChild(h); d.appendChild(pp); a.appendChild(img); a.appendChild(d); list.appendChild(a);
        });
        $('[data-co-r-total]').textContent = money(p.total);
        $('[data-co-r-mode]').textContent = state.mode === 'deposit' ? T.modeDeposit : (state.mode === 'full' ? T.modeFull : T.modeLater);
        $('[data-co-r-due]').textContent = money(p.due);
        $('[data-co-review-lead]').textContent = state.mode === 'later' ? T.reviewLeadLater : T.reviewLead;

        var later = state.mode === 'later';
        var culqiBtn = $('#co-pay-culqi'), ppBox = $('#co-paypal-buttons'), laterBtn = $('#co-confirm-later');
        laterBtn.hidden = !later;
        if (culqiBtn) culqiBtn.hidden = later || state.method !== 'culqi';
        ppBox.hidden = later || state.method !== 'paypal';
        var cl = $('[data-co-culqi-label]');
        if (cl) cl.textContent = T.payCard.replace(':amount', money(p.due));
        openModal('#co-modal-review');
        if (!later && state.method === 'paypal') renderPayPal();
    }

    // ── Pago pendiente de revisión (PaymentLock) ────────────────
    function showReviewPanel(message, reference) {
        closeModals();
        var panel = $('#co-review');
        $('#co-review-msg').textContent = message || '';
        $('#co-review-ref').textContent = reference || '';
        panel.hidden = false;
        panel.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
        $$('[data-co-cta]').forEach(function (b) { b.disabled = true; });
    }

    // Errores 422 de campo -> junto al campo
    function showFieldErrors(errors) {
        if (!errors || typeof errors !== 'object') return false;
        var map = { customer_name: '#co-name', customer_email: '#co-email', customer_phone: '#co-phone', accept_terms: '#co-terms' };
        var key = Object.keys(errors).filter(function (k) { return map[k]; })[0];
        if (!key) return false;
        closeModals();
        var el = $(map[key]);
        if (key === 'accept_terms') {
            $('#co-terms-err').classList.add('is-on');
        } else {
            var f = fieldOf(el);
            var err = $('.co-error', f);
            if (err && errors[key][0]) err.textContent = errors[key][0];
            mark(el, false);
        }
        el.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
        el.focus({ preventScroll: true });
        return true;
    }

    function markExpired(items) {
        (items || []).forEach(function (it) {
            var box = $('[data-co-box][data-row-id="' + it.row_id + '"]');
            if (box) setDateError(box, it.message || T.dateInvalid);
        });
        closeModals();
        var first = $('.co-field.is-invalid [data-co-date]');
        if (first) first.scrollIntoView({ block: 'center' });
    }

    function handleChargeResponse(res, data) {
        if (data.success && data.redirect) { window.location.href = data.redirect; return; }
        if (data.code === 'payment_captured_booking_pending') { showReviewPanel(data.message, data.reference); return; }
        if (res.status === 409 && data.code === 'payment_pending_manual_review') { showReviewPanel(data.error || data.message, data.reference); return; }
        if (res.status === 422 && showFieldErrors(data.errors)) return;
        if (data.code === 'date_unavailable') { markExpired(data.items); return; }
        showMsg(data.message || data.error || T.paymentFailed);
    }

    // ── Culqi ───────────────────────────────────────────────────
    function setBusy(on) {
        state.busy = on;
        var b = $('#co-pay-culqi');
        if (!b) return;
        b.disabled = on;
        b.setAttribute('aria-busy', on ? 'true' : 'false');
        $('[data-co-culqi-label]').textContent = on ? T.processing : T.payCard.replace(':amount', money(plan().due));
    }

    function openCulqi() {
        if (state.busy) return;
        // El monto a cobrar debe ser el CONFIRMADO por el servidor, no el optimista.
        if (!isIdle()) { whenSettled(openCulqi); return; }
        hideMsg();
        if (typeof window.Culqi === 'undefined') { showMsg(T.culqiScriptFail); return; }
        var cents = plan().due_cents;
        window.Culqi.publicKey = state.checkout.culqi_public_key;
        window.Culqi.settings({ title: T.culqiTitle, currency: 'USD', amount: cents });
        window.Culqi.options({
            lang: C.locale === 'en' ? 'en' : 'es',
            installments: false,
            paymentMethods: { tarjeta: true, yape: false, bancaMovil: false, agente: false, billetera: false, cuotealo: false },
            style: { logo: location.origin + '/assets/logos/logo-culqi.png', bannerColor: '#06533e', buttonBackground: '#06533e', buttonTextColor: '#FFFFFF', priceColor: '#06533e' },
        });
        window.Culqi.open();
    }

    function charge(tokenId) {
        var c = customer();
        api(state.checkout.endpoints.culqi_charge, {
            method: 'POST',
            body: {
                culqi_token: tokenId,
                payment_mode: state.mode,
                customer_name: c.customer_name,
                customer_email: c.customer_email,
                customer_phone: c.customer_phone,
                pickup_point: c.pickup_point,
                notes: c.notes,
                accept_terms: c.accept_terms,
            },
        }).then(function (r) {
            if (!(r.data.success && r.data.redirect)) setBusy(false);
            handleChargeResponse(r.res, r.data);
        }).catch(function () { setBusy(false); showMsg(T.culqiFailed); });
    }

    // Callback global que Culqi Checkout v4 invoca al tokenizar la tarjeta.
    window.culqi = function () {
        if (window.Culqi && window.Culqi.token) {
            var id = window.Culqi.token.id;
            window.Culqi.close();
            setBusy(true);
            charge(id);
        } else if (window.Culqi && window.Culqi.error) {
            if (window.console) console.error('Culqi token error:', window.Culqi.error.code || window.Culqi.error.type);
            showMsg(T.culqiTokenError);
        }
    };

    // ── PayPal ──────────────────────────────────────────────────
    function ppCreateOrder() {
        if (!validate()) return Promise.reject(new Error('validation_failed'));
        // Esperar a que el servidor confirme el carrito (PayPal cobra lo que el servidor calcula).
        return new Promise(function (resolve) { whenSettled(resolve); }).then(ppCreateOrderNow);
    }
    function ppCreateOrderNow() {
        var c = customer();
        return api(state.checkout.endpoints.paypal_create, {
            method: 'POST',
            body: {
                payment_mode: state.mode,
                customer_name: c.customer_name,
                customer_email: c.customer_email,
                customer_phone: c.customer_phone,
                accept_terms: c.accept_terms,
            },
        }).then(function (r) {
            var d = r.data;
            if (r.res.status === 409 && d.code === 'payment_pending_manual_review') {
                showReviewPanel(d.error, d.reference);
                throw new Error('payment_pending_manual_review');
            }
            if (!r.res.ok || !d.id) {
                if (showFieldErrors(d.errors)) { var fe = new Error('field_validation_failed'); fe.handled = true; throw fe; }
                if (d.code === 'date_unavailable') { markExpired(d.items); var de = new Error('date'); de.handled = true; throw de; }
                throw new Error(d.error || T.paypalCreateFailed);
            }
            return d.id;
        });
    }

    function ppOnApprove(data) {
        var c = customer();
        return api(state.checkout.endpoints.paypal_capture, {
            method: 'POST',
            body: {
                orderID: data.orderID,
                customer_name: c.customer_name,
                customer_email: c.customer_email,
                customer_phone: c.customer_phone,
                pickup_point: c.pickup_point,
                notes: c.notes,
            },
        }).then(function (r) { handleChargeResponse(r.res, r.data); });
    }

    function renderPayPal() {
        if (state.paypalRendered) return;
        var tries = 0;
        (function go() {
            if (typeof window.paypal_sdk === 'undefined') {
                if (++tries < 40) { setTimeout(go, 150); return; }
                showMsg(T.paypalError);
                return;
            }
            state.paypalRendered = true;
            window.paypal_sdk.Buttons({
                style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },
                createOrder: ppCreateOrder,
                onApprove: ppOnApprove,
                onError: function (err) {
                    if (err && (err.handled || err.message === 'validation_failed' || err.message === 'payment_pending_manual_review')) return;
                    showMsg(err && err.message && err.message !== 'Error' ? err.message : T.paypalError);
                    if (window.console) console.error('PayPal error:', err);
                },
            }).render('#co-paypal-buttons');
        })();
    }

    // ── Pagar después: POST nativo a checkout.process ───────────
    function submitLater() {
        syncPhone();
        var b = $('#co-confirm-later');
        b.disabled = true;
        $$('[data-co-cta]').forEach(function (x) { x.disabled = true; });
        $('#co-form').submit();
    }

    // ── Carrito abandonado: captura de contacto en segundo plano ─
    var lastContact = '';
    function persistContact() {
        var c = customer();
        var sig = c.customer_email + '|' + c.customer_name + '|' + c.customer_phone;
        if (!c.customer_email || sig === lastContact) return;
        lastContact = sig;
        api(C.urls.contact, { method: 'POST', body: { customer_email: c.customer_email, customer_name: c.customer_name, customer_phone: c.customer_phone } }).catch(function () {});
    }

    // ── Eventos ─────────────────────────────────────────────────
    function bind() {
        var tours = $('#co-tours');
        tours.addEventListener('click', function (ev) {
            var t = ev.target.closest('button');
            if (!t) return;
            var box = t.closest('[data-co-box]');
            if (t.hasAttribute('data-co-inc')) changePax(box, 1);
            else if (t.hasAttribute('data-co-dec')) changePax(box, -1);
            else if (t.hasAttribute('data-co-remove')) removeItem(t.closest('[data-co-item]'));
        });
        tours.addEventListener('change', function (ev) {
            if (ev.target.hasAttribute('data-co-date')) { ev.target.dataset.touched = '1'; onDateChange(ev.target); }
        });

        $$('input[name="payment_mode"]').forEach(function (r) {
            r.addEventListener('change', function () { state.mode = r.value; render(); trackPaymentInfo(); });
        });
        $$('input[name="pay_method"]').forEach(function (r) {
            r.addEventListener('change', function () { state.method = r.value; render(); trackPaymentInfo(); });
        });

        // Campos: quitar el error al corregir
        ['#co-name', '#co-phone', '#co-email'].forEach(function (sel) {
            var el = $(sel);
            el.addEventListener('input', function () { var f = fieldOf(el); if (f) f.classList.remove('is-invalid'); syncPhone(); });
        });
        $('#co-dial').addEventListener('change', syncPhone);
        $('#co-terms').addEventListener('change', function () { $('#co-terms-err').classList.toggle('is-on', !this.checked); });
        ['#co-email', '#co-phone', '#co-name'].forEach(function (s) { $(s).addEventListener('blur', persistContact); });

        $('#co-toggle-extra').addEventListener('click', function () {
            var open = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!open));
            $('#co-extra').hidden = open;
        });
        $('#co-terms-link').addEventListener('click', function (ev) { ev.preventDefault(); openModal('#co-modal-terms'); });

        $$('[data-co-cta]').forEach(function (b) {
            b.addEventListener('click', function () { if (validate()) { syncPhone(); whenSettled(openReview); } });
        });
        $('#co-form').addEventListener('submit', function (ev) { ev.preventDefault(); if (validate()) { syncPhone(); whenSettled(openReview); } });

        if ($('#co-pay-culqi')) $('#co-pay-culqi').addEventListener('click', openCulqi);
        $('#co-confirm-later').addEventListener('click', submitLater);

        doc.addEventListener('click', function (ev) { if (ev.target.closest('[data-co-close]')) closeModals(); });
        doc.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && $$('.co-modal').some(function (m) { return !m.hidden; })) closeModals();
            trap(ev);
        });

        $('#co-back').addEventListener('click', function () {
            var ref = doc.referrer, same = false;
            try { same = ref && new URL(ref).origin === location.origin && history.length > 1; } catch (e) {}
            if (same) history.back(); else location.href = this.getAttribute('data-tours');
        });

        var copy = $('#co-review-copy');
        if (copy) copy.addEventListener('click', function () {
            var ref = $('#co-review-ref').textContent, btn = this;
            var done = function () { btn.textContent = T.copied; setTimeout(function () { btn.textContent = T.copy; }, 2000); };
            if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(ref).then(done, function () {});
            else { try { var ta = doc.createElement('textarea'); ta.value = ref; ta.style.cssText = 'position:fixed;opacity:0'; doc.body.appendChild(ta); ta.select(); doc.execCommand('copy'); doc.body.removeChild(ta); done(); } catch (e) {} }
        });
    }

    // Selector de país: cerrado muestra solo "🇵🇪 +51"; abierto, la lista completa con nombre.
    function dialShort() {
        var d = $('#co-dial');
        if (!d) return;
        function full() { Array.prototype.forEach.call(d.options, function (o) { if (o.dataset.full) o.textContent = o.dataset.full; }); }
        function short() { var s = d.options[d.selectedIndex]; if (s && s.dataset.full) s.textContent = s.dataset.full.split(' · ')[0]; }
        Array.prototype.forEach.call(d.options, function (o) { o.dataset.full = o.textContent; });
        short();
        ['focus', 'mousedown', 'touchstart'].forEach(function (e) { d.addEventListener(e, full, { passive: true }); });
        d.addEventListener('change', function () { full(); short(); });
        d.addEventListener('blur', function () { full(); short(); });
    }

    // Stepper: scrollspy (progreso acumulado)
    function spy() {
        var steps = { tour: $('[data-co-step="tour"]'), data: $('[data-co-step="data"]'), pay: $('[data-co-step="pay"]') };
        var targets = [['tour', $('#co-tours')], ['data', $('#co-data')], ['pay', $('#co-pay')]];
        function update() {
            var line = window.innerHeight * 0.45, idx = 0;
            targets.forEach(function (t, i) { if (t[1].getBoundingClientRect().top <= line) idx = i; });
            targets.forEach(function (t, i) { steps[t[0]].classList.toggle('is-active', i <= idx); });
        }
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    // ── Arranque ────────────────────────────────────────────────
    function init() {
        bind();
        dialShort();
        spy();
        syncPhone();
        render();
        if (C.paymentLock) {
            $$('[data-co-cta]').forEach(function (b) { b.disabled = true; });
        }
        trackBegin();
    }
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init); else init();
})();

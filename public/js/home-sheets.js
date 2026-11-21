/* Lima View Tours — ventanas (sheets) del home TAL CUAL (21-nov-2026).
 * JS plano, sin librerías. Se carga con `defer` desde home.blade.php.
 * Contrato de datos: JSON #lvt-home-cfg (textos, URLs, beneficios) + endpoints GET
 *   /{locale}/api/tours/{slug}/sheet · /api/tours/finder · /api/reviews  y POST cart.store (CSRF).
 * TODO texto que viene del servidor se pinta con textContent (nunca innerHTML).
 * Accesibilidad: role=dialog, aria-modal, foco atrapado, Esc cierra, el foco vuelve al disparador,
 * scroll del body bloqueado. Móvil = bottom-sheet; >= 700 px = modal centrado (ver redesign.css .fh-sh*).
 * Sin configuración (homeApi null) o sin JS: los enlaces y formularios funcionan como siempre (progressive enhancement).
 */
(function () {
  'use strict';

  var cfg = null;
  try {
    var cfgEl = document.getElementById('lvt-home-cfg');
    cfg = cfgEl ? JSON.parse(cfgEl.textContent) : null;
  } catch (e) { cfg = null; }
  var api = cfg && cfg.api ? cfg.api : null;
  var tx = (cfg && cfg.tx) || {};
  var locale = (cfg && cfg.locale) || document.documentElement.lang || 'es';

  function T(k) { return tx[k] != null ? tx[k] : ''; }

  /* ---------- utilidades DOM ---------- */
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null && text !== '') n.textContent = text;
    return n;
  }
  function clear(n) { while (n.firstChild) n.removeChild(n.firstChild); }
  var STAR_D = 'M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8L12 2.5z';
  function stars(n, big) {
    var w = el('span', 'fh-stars' + (big ? ' fh-stars--lg' : ''));
    w.setAttribute('role', 'img');
    w.setAttribute('aria-label', n + ' / 5');
    var on = Math.max(0, Math.min(5, Math.round(Number(n) || 0)));
    for (var i = 1; i <= 5; i++) {
      w.insertAdjacentHTML('beforeend', '<svg viewBox="0 0 24 24" aria-hidden="true" class="' + (i <= on ? 'on' : 'off') + '"><path d="' + STAR_D + '" fill="currentColor"/></svg>');
    }
    return w;
  }
  function money(n) { return 'US$' + Math.round(Number(n) || 0); }
  /* Las URLs del servidor pueden traer otro host (APP_URL): se llama siempre al mismo origen. */
  function samePath(u) {
    try { var x = new URL(u, location.href); return x.pathname + x.search; } catch (e) { return u; }
  }
  function qs(obj) {
    var p = [];
    Object.keys(obj).forEach(function (k) {
      if (obj[k] != null && obj[k] !== '') p.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]));
    });
    return p.length ? '?' + p.join('&') : '';
  }
  function getJSON(url, signal) {
    return fetch(samePath(url), { headers: { 'Accept': 'application/json' }, credentials: 'omit', signal: signal })
      .then(function (r) {
        if (!r.ok) { var err = new Error('http_' + r.status); err.status = r.status; throw err; }
        return r.json();
      });
  }
  function safeHref(u) { return /^https?:\/\//i.test(String(u || '')) ? u : ''; }

  /* ---------- gestor de la ventana ---------- */
  var S = null;        // { root, panel, title, body, back, close, stack, trigger, ctrl, inerted }
  var uid = 0;

  function build() {
    var id = 'fh-sh-t' + (++uid);
    var root = el('div', 'fh-sh');
    var panel = el('div', 'fh-sh__panel');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', id);
    var head = el('div', 'fh-sh__head');
    var back = el('button', 'fh-sh__btn');
    back.type = 'button';
    back.hidden = true;
    back.setAttribute('aria-label', T('back'));
    back.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg>';
    var title = el('h2', 'fh-sh__title');
    title.id = id;
    title.tabIndex = -1;
    var close = el('button', 'fh-sh__btn');
    close.type = 'button';
    close.setAttribute('aria-label', T('close'));
    close.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>';
    head.appendChild(back); head.appendChild(title); head.appendChild(close);
    var body = el('div', 'fh-sh__body');
    panel.appendChild(head); panel.appendChild(body);
    root.appendChild(panel);
    S = { root: root, panel: panel, title: title, body: body, back: back, close: close, stack: [], trigger: null, ctrl: null, inerted: [] };

    close.addEventListener('click', closeSheet);
    back.addEventListener('click', goBack);
    root.addEventListener('mousedown', function (e) { if (e.target === root) closeSheet(); });
    root.addEventListener('keydown', onKey);
  }

  function focusables() {
    return Array.prototype.filter.call(
      S.panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'),
      function (n) { return !n.hidden && n.offsetParent !== null; }
    );
  }
  function onKey(e) {
    if (e.key === 'Escape') { e.stopPropagation(); closeSheet(); return; }
    if (e.key !== 'Tab') return;
    var f = focusables();
    if (!f.length) { e.preventDefault(); S.title.focus(); return; }
    var first = f[0], last = f[f.length - 1], act = document.activeElement;
    if (e.shiftKey && (act === first || act === S.title)) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && act === last) { e.preventDefault(); first.focus(); }
  }

  function lockPage(on) {
    var html = document.documentElement;
    if (on) {
      var sbw = window.innerWidth - html.clientWidth;
      html.classList.add('fh-sh-lock');
      if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
      S.inerted = [];
      Array.prototype.forEach.call(document.body.children, function (c) {
        if (c === S.root || c.tagName === 'SCRIPT' || c.tagName === 'LINK' || c.tagName === 'STYLE' || c.hasAttribute('inert')) return;
        c.setAttribute('inert', '');
        S.inerted.push(c);
      });
    } else {
      html.classList.remove('fh-sh-lock');
      document.body.style.paddingRight = '';
      S.inerted.forEach(function (c) { c.removeAttribute('inert'); });
      S.inerted = [];
    }
  }

  function renderView(v) {
    if (S.ctrl) { try { S.ctrl.abort(); } catch (e) { /* noop */ } }
    S.ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    S.title.textContent = v.title || '';
    S.panel.classList.toggle('fh-sh__panel--wide', !!v.wide);
    S.back.hidden = S.stack.length < 2;
    clear(S.body);
    v.render(S.body, S.ctrl ? S.ctrl.signal : undefined);
    S.body.scrollTop = 0;
  }

  function show(view, trigger) {
    if (!S) build();
    var opening = !S.root.parentNode;
    if (opening) {
      S.trigger = trigger || document.activeElement;
      S.stack = [];
      document.body.appendChild(S.root);
      lockPage(true);
    }
    S.stack = [view];
    renderView(view);
    if (opening) {
      requestAnimationFrame(function () { S.root.classList.add('is-open'); });
    }
    (opening ? S.close : S.title).focus({ preventScroll: true });
  }
  function push(view) {
    S.stack.push(view);
    renderView(view);
    S.title.focus({ preventScroll: true });
  }
  function goBack() {
    if (S.stack.length < 2) return;
    S.stack.pop();
    renderView(S.stack[S.stack.length - 1]);
    S.title.focus({ preventScroll: true });
  }
  function closeSheet() {
    if (!S || !S.root.parentNode) return;
    if (S.ctrl) { try { S.ctrl.abort(); } catch (e) { /* noop */ } }
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    S.root.classList.remove('is-open');
    var done = function () {
      if (S.root.parentNode) S.root.parentNode.removeChild(S.root);
      lockPage(false);
      var t = S.trigger;
      S.stack = [];
      if (t && document.body.contains(t)) { try { t.focus({ preventScroll: true }); } catch (e) { /* noop */ } }
    };
    if (reduce) done(); else setTimeout(done, 200);
  }

  /* ---------- estados de carga / error ---------- */
  function loading(body) {
    var d = el('div', 'fh-sh__state');
    d.setAttribute('role', 'status');
    d.appendChild(el('span', 'fh-sh__spin'));
    d.appendChild(document.createTextNode(T('loading')));
    body.appendChild(d);
    return d;
  }
  function failed(box, retry) {
    clear(box);
    box.className = 'fh-sh__state fh-sh__state--err';
    box.setAttribute('role', 'alert');
    box.appendChild(el('p', '', T('error')));
    var b = el('button', 'fh-sh__cta fh-sh__cta--ghost fh-sh__retry', T('retry'));
    b.type = 'button';
    b.addEventListener('click', retry);
    box.appendChild(b);
  }
  function load(body, signal, fetcher, ok) {
    var box = loading(body);
    function run() {
      clear(box); box.className = 'fh-sh__state'; box.removeAttribute('role');
      box.appendChild(el('span', 'fh-sh__spin')); box.appendChild(document.createTextNode(T('loading')));
      fetcher(signal).then(function (data) {
        if (signal && signal.aborted) return;
        if (box.parentNode) box.parentNode.removeChild(box);
        ok(data);
      }).catch(function (e) {
        if (e && e.name === 'AbortError') return;
        failed(box, run);
      });
    }
    run();
  }

  /* ---------- vista: contacto ---------- */
  function viewContact() {
    return {
      title: T('contact_title'),
      render: function (body) {
        body.appendChild(el('p', 'fh-sh__eyebrow', T('contact_eyebrow')));
        body.appendChild(el('h3', 'fh-sh__h', T('contact_h')));
        body.appendChild(el('p', 'fh-sh__p', T('contact_p')));
        var u = safeHref(api && api.whatsapp_url);
        if (u) {
          var a = el('a', 'fh-sh__cta fh-sh__cta--gold', T('contact_btn') + ' →');
          a.href = u; a.target = '_blank'; a.rel = 'noopener noreferrer';
          body.appendChild(a);
        }
      }
    };
  }

  /* ---------- vista: beneficio ---------- */
  function viewBenefit(i) {
    var b = cfg && cfg.benefits ? cfg.benefits[i] : null;
    if (!b || !b.detail) return null;
    return {
      title: b.label ? b.title + ' · ' + b.label : b.title,
      render: function (body) {
        body.appendChild(el('h3', 'fh-sh__ben-title', b.title));
        body.appendChild(el('p', 'fh-sh__ben-text', b.detail));
        if (b.key !== 'pay' && api && api.reviews_url) {
          var c = el('button', 'fh-sh__cta', T('benefit_cta') + ' →');
          c.type = 'button';
          c.addEventListener('click', function () { push(viewReviews()); });
          body.appendChild(c);
        }
      }
    };
  }

  /* ---------- vista: foto ampliada ---------- */
  function viewPhoto(src) {
    return {
      title: T('photo_title'),
      render: function (body) {
        var img = el('img', 'fh-lb');
        img.src = src; img.alt = '';
        body.appendChild(img);
      }
    };
  }

  /* ---------- vista: resultados (finder) ---------- */
  function viewResults(o) {
    // o = { mode: quick|all|dest|search|recommend, params:{}, label?: string, limit? }
    var titles = { all: T('res_all'), recommend: T('res_rec'), search: T('search_title'), quick: T('res_quick') };
    var title = o.mode === 'dest' ? T('res_dest').replace('{x}', o.label || '') : (titles[o.mode] || T('res_found'));
    return {
      title: title,
      push: o.push,
      render: function (body, signal) {
        var params = {};
        Object.keys(o.params || {}).forEach(function (k) { params[k] = o.params[k]; });
        if (o.mode === 'all') params.limit = 12;
        var area = el('div', 'fh-sr-area');
        var qInput = null;

        if (o.mode === 'search') {
          var form = el('form', 'fh-sh__form');
          form.setAttribute('role', 'search');
          qInput = el('input', 'fh-sh__input');
          qInput.type = 'search'; qInput.maxLength = 80; qInput.autocomplete = 'off';
          qInput.placeholder = T('search_ph');
          qInput.setAttribute('aria-label', T('search_title'));
          qInput.value = params.q || '';
          var sb = el('button', 'fh-sh__cta', T('search_btn') + ' →');
          sb.type = 'submit';
          form.appendChild(qInput); form.appendChild(sb);
          body.appendChild(form);
          var sug = (api && api.suggestions) || [];
          if (sug.length) {
            var sw = el('div', 'fh-sh__sugg');
            sw.setAttribute('role', 'group');
            sw.setAttribute('aria-label', T('suggest'));
            sug.forEach(function (s) {
              var b = el('button', '', s.label); b.type = 'button';
              b.addEventListener('click', function () { qInput.value = s.q; run(); });
              sw.appendChild(b);
            });
            body.appendChild(sw);
          }
          form.addEventListener('submit', function (e) { e.preventDefault(); run(); });
        }
        body.appendChild(area);

        var gen = 0;
        function run() {
          if (qInput) params.q = qInput.value.trim().slice(0, 80);
          var my = ++gen;
          clear(area);
          load(area, signal, function (sig) { return getJSON(api.finder_url + qs(params), sig); }, function (res) {
            if (my !== gen) return;
            renderResults(area, (res && res.data) || {}, o);
          });
        }
        if (o.mode !== 'search' || params.q) run();
        else if (o.mode === 'search') { /* espera a que escriba o elija una sugerencia */ }
        if (qInput) setTimeout(function () { try { qInput.focus({ preventScroll: true }); } catch (e) { /* noop */ } }, 60);
      }
    };
  }

  function renderResults(area, d, o) {
    area.appendChild(el('p', 'fh-sh__eyebrow', T('adv_eyebrow')));
    var applied = d.applied || [];
    if (applied.length) {
      var ul = el('ul', 'fh-sh__chips');
      applied.forEach(function (a) { ul.appendChild(el('li', '', a.label)); });
      area.appendChild(ul);
    } else if (d.query && d.query.q) {
      var ul2 = el('ul', 'fh-sh__chips'); ul2.appendChild(el('li', '', d.query.q)); area.appendChild(ul2);
    }
    var filtered = !!(d.has_filters || (d.query && d.query.q));
    if (filtered && !d.has_exact) {
      var n = el('p', 'fh-sh__note', T('no_exact')); n.setAttribute('role', 'status'); area.appendChild(n);
    }
    var list = d.results || [];
    list.forEach(function (r) {
      var alt = filtered && r.exact === false;
      var c = el('article', 'fh-rc' + (alt ? ' fh-rc--alt' : ''));
      var img = el('img', 'fh-rc__img');
      img.src = r.thumb || r.image || ''; img.alt = ''; img.loading = 'lazy'; img.width = 96; img.height = 96;
      c.appendChild(img);
      var info = el('div', 'fh-rc__info');
      info.appendChild(el('h3', 'fh-rc__t', r.card_title || r.title));
      if (r.summary) info.appendChild(el('p', 'fh-rc__s', r.summary));
      var m = el('p', 'fh-rc__m');
      if (r.location || r.region) { m.appendChild(el('span', '', r.location || r.region)); }
      if (r.duration) { m.appendChild(el('span', '', r.duration)); }
      var unit = r.price_unit === 'group' ? T('grp') : T('pp');
      m.appendChild(el('span', '', T('from') + ' · ' + unit));
      m.appendChild(el('b', '', money(r.price)));
      if (r.discount_percent != null && r.price_before) { m.appendChild(el('s', '', money(r.price_before))); }
      if (filtered) m.appendChild(el('span', 'fh-rc__tag' + (alt ? ' fh-rc__tag--alt' : ''), alt ? T('alt') : T('exact')));
      info.appendChild(m);
      c.appendChild(info);
      var act = el('div', 'fh-rc__act');
      var b1 = el('button', 'fh-sh__cta fh-sh__cta--ghost', T('details')); b1.type = 'button';
      b1.addEventListener('click', function () { push(viewTour(r.sheet_url, false)); });
      var b2 = el('button', 'fh-sh__cta', T('book')); b2.type = 'button';
      b2.addEventListener('click', function () { push(viewTour(r.sheet_url, true)); });
      act.appendChild(b1); act.appendChild(b2);
      c.appendChild(act);
      area.appendChild(c);
    });
    if (!list.length) area.appendChild(el('p', 'fh-sh__state', T('rev_empty')));
    var row = el('div', 'fh-sh__row');
    if (d.exact_count === 0 && safeHref(d.whatsapp_url)) {
      row.appendChild(el('p', 'fh-sh__p', T('wa_empty')));
      var w = el('a', 'fh-sh__cta fh-sh__cta--gold', T('wa_consult') + ' →');
      w.href = d.whatsapp_url; w.target = '_blank'; w.rel = 'noopener noreferrer';
      row.appendChild(w);
    }
    if (safeHref(d.see_all_url)) {
      var s = el('a', 'fh-sh__link', T('see_all') + ' →');
      s.href = d.see_all_url;
      row.appendChild(s);
    }
    area.appendChild(row);
  }

  /* ---------- vista: detalle de tour + reservar ---------- */
  function viewTour(sheetUrl, openReserve) {
    return {
      title: T('tour_title'),
      push: true,
      wide: true,
      render: function (body, signal) {
        load(body, signal, function (sig) { return getJSON(sheetUrl, sig); }, function (res) {
          renderTour(body, (res && res.data) || {}, !!openReserve);
        });
      }
    };
  }

  function renderTour(body, t, openReserve) {
    // Galería
    var gal = el('div', 'fh-st__gal');
    var items = (t.gallery && t.gallery.length) ? t.gallery : [{ src: t.image, thumb: t.image }];
    var main = el('img', 'fh-st__main');
    main.src = items[0].src || t.image || ''; main.alt = t.title || '';
    gal.appendChild(main);
    if (items.length > 1) {
      var th = el('div', 'fh-st__thumbs');
      items.forEach(function (it, i) {
        var b = el('button', ''); b.type = 'button';
        b.setAttribute('aria-label', (i + 1) + ' / ' + items.length);
        b.setAttribute('aria-current', i === 0 ? 'true' : 'false');
        var im = el('img'); im.src = it.thumb || it.src; im.alt = ''; im.loading = 'lazy';
        b.appendChild(im);
        b.addEventListener('click', function () {
          main.src = it.src;
          Array.prototype.forEach.call(th.children, function (x) { x.setAttribute('aria-current', 'false'); });
          b.setAttribute('aria-current', 'true');
        });
        th.appendChild(b);
      });
      gal.appendChild(th);
    }
    body.appendChild(gal);

    body.appendChild(el('h3', 'fh-st__t', t.card_title || t.title));
    if (t.subtitle) body.appendChild(el('p', 'fh-st__sub', t.subtitle));
    if (t.rating && t.rating.count > 0) {
      var rr = el('p', 'fh-st__rate');
      rr.appendChild(stars(t.rating.rating));
      rr.appendChild(document.createTextNode(Number(t.rating.rating).toFixed(1) + ' (' + t.rating.count + ' ' + (t.rating.count === 1 ? T('review_word') : T('reviews_word')) + ')'));
      body.appendChild(rr);
    }
    if (t.summary) body.appendChild(el('p', 'fh-st__sum', t.summary));

    var cells = [];
    if (t.duration) cells.push([T('duration'), t.duration]);
    if (t.language) cells.push([T('language'), t.language]);
    if (t.departure_time) cells.push([T('departure'), t.departure_time]);
    if (t.modality) cells.push([T('type'), t.modality]);
    var rg = (t.region && t.region.name) || t.location;
    if (rg) cells.push([T('region'), rg]);
    if (cells.length) {
      var dl = el('dl', 'fh-st__grid');
      cells.forEach(function (c) {
        var cell = el('div', 'fh-st__cell');
        cell.appendChild(el('dt', '', c[0])); cell.appendChild(el('dd', '', c[1]));
        dl.appendChild(cell);
      });
      body.appendChild(dl);
    }
    if (t.pickup && t.pickup.label) {
      var pk = (t.pickup.included ? '✓ ' : '') + t.pickup.label;
      if (t.pickup.zones && t.pickup.zones.length) pk += ' · ' + T('pickup_zones') + ': ' + t.pickup.zones.join(', ');
      body.appendChild(el('p', 'fh-st__pick', pk));
    }
    if ((t.includes && t.includes.length) || (t.excludes && t.excludes.length)) {
      var lists = el('div', 'fh-st__lists');
      [['inc', 'includes', t.includes], ['exc', 'excludes', t.excludes]].forEach(function (g) {
        if (!g[2] || !g[2].length) return;
        var box = el('div', g[0]);
        box.appendChild(el('h4', '', T(g[1])));
        var ul = el('ul');
        g[2].forEach(function (x) { ul.appendChild(el('li', '', x)); });
        box.appendChild(ul); lists.appendChild(box);
      });
      body.appendChild(lists);
    }

    // Precio
    var p = t.price || {};
    var buy = el('div', 'fh-st__buy');
    buy.appendChild(el('span', 'fh-st__price', money(p.amount)));
    if (p.before && p.discount_percent != null) buy.appendChild(el('s', 'fh-st__was', money(p.before)));
    buy.appendChild(el('span', 'fh-st__unit', (p.unit === 'group' ? T('grp') : T('pp'))));
    buy.appendChild(el('p', 'fh-st__flex', T('flex')));
    body.appendChild(buy);

    // Panel de reserva
    var rsv = t.reserve || null;
    var res = el('form', 'fh-st__res');
    res.noValidate = true;
    res.hidden = !openReserve;
    var toggle = null;
    if (rsv) {
      res.appendChild(el('h4', '', T('reserve_h')));
      var err = el('p', 'fh-st__err'); err.hidden = true; err.setAttribute('role', 'alert'); err.id = 'fh-st-err' + (++uid);
      var f1 = el('div', 'fh-st__f');
      var l1 = el('label', '', T('date')); var dId = 'fh-st-d' + uid; l1.htmlFor = dId;
      var date = el('input'); date.type = 'date'; date.id = dId; date.required = true;
      if (rsv.min_date) date.min = rsv.min_date;
      if (rsv.max_date) date.max = rsv.max_date;
      date.setAttribute('aria-describedby', err.id);
      f1.appendChild(l1); f1.appendChild(date);
      var f2 = el('div', 'fh-st__f');
      var l2 = el('label', '', T('adults')); var aId = 'fh-st-a' + uid; l2.htmlFor = aId;
      var ad = el('select'); ad.id = aId;
      var amin = Math.max(1, parseInt(rsv.adults_min, 10) || 1), amax = Math.max(amin, Math.min(40, parseInt(rsv.adults_max, 10) || 20));
      var adef = Math.min(amax, Math.max(amin, parseInt(rsv.fields && rsv.fields.adults, 10) || 2));
      for (var n = amin; n <= amax; n++) { var op = el('option', '', String(n)); op.value = String(n); if (n === adef) op.selected = true; ad.appendChild(op); }
      f2.appendChild(l2); f2.appendChild(ad);
      var go = el('button', 'fh-sh__cta', T('reserve_btn') + ' →'); go.type = 'submit';
      res.appendChild(err); res.appendChild(f1); res.appendChild(f2); res.appendChild(go);

      var showErr = function (m) { err.textContent = m; err.hidden = false; date.setAttribute('aria-invalid', 'true'); };
      var hideErr = function () { err.hidden = true; date.removeAttribute('aria-invalid'); };
      var badDate = function (v) {
        if (!v) return T('date_required');
        if ((rsv.min_date && v < rsv.min_date) || (rsv.max_date && v > rsv.max_date)) return T('date_range');
        var bd = rsv.blocked_dates || [];
        if (bd.indexOf(v) !== -1) return T('date_blocked');
        var parts = v.split('-'); var wd = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)).getDay();
        if ((rsv.blocked_weekdays || []).indexOf(wd) !== -1) return T('date_blocked');
        return '';
      };
      date.addEventListener('change', function () { var m = date.value ? badDate(date.value) : ''; if (m) showErr(m); else hideErr(); });
      res.addEventListener('submit', function (e) {
        e.preventDefault();
        var m = badDate(date.value);
        if (m) { showErr(m); date.focus(); return; }
        hideErr();
        go.disabled = true; go.textContent = T('adding');
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        fetch(samePath(rsv.url), {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          body: JSON.stringify({ tour_id: rsv.fields && rsv.fields.tour_id, travel_date: date.value, adults: parseInt(ad.value, 10) || amin, children: 0 })
        }).then(function (r) {
          return r.json().catch(function () { return {}; }).then(function (j) { return { s: r.status, j: j }; });
        }).then(function (x) {
          if (x.s >= 200 && x.s < 300 && x.j && x.j.success !== false) { location.href = samePath(rsv.cart_url); return; }
          go.disabled = false; go.textContent = T('reserve_btn') + ' →';
          if (x.s === 419) { showErr(T('reserve_419')); return; }
          var msg = '';
          if (x.j && x.j.errors) { var k = Object.keys(x.j.errors)[0]; msg = x.j.errors[k] && x.j.errors[k][0]; }
          if (!msg && x.j && x.j.message && x.s < 500) msg = x.j.message;
          showErr(msg || T('reserve_err'));
        }).catch(function () {
          go.disabled = false; go.textContent = T('reserve_btn') + ' →';
          showErr(T('reserve_err'));
        });
      });
      toggle = el('button', 'fh-sh__cta fh-sh__cta--gold', T('book') + ' →'); toggle.type = 'button';
      toggle.setAttribute('aria-expanded', openReserve ? 'true' : 'false');
      toggle.addEventListener('click', function () {
        var open = res.hidden;
        res.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { date.focus(); res.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
      });
    }
    var row = el('div', 'fh-sh__row');
    if (toggle) row.appendChild(toggle);
    body.appendChild(row);
    body.appendChild(res);
    var row2 = el('div', 'fh-sh__row');
    var wa = t.urls && safeHref(t.urls.whatsapp);
    if (wa) { var wl = el('a', 'fh-sh__cta fh-sh__cta--ghost', T('wa_consult')); wl.href = wa; wl.target = '_blank'; wl.rel = 'noopener noreferrer'; row2.appendChild(wl); }
    if (t.urls && safeHref(t.urls.page)) { var pg = el('a', 'fh-sh__link', T('full_page') + ' →'); pg.href = t.urls.page; row2.appendChild(pg); }
    body.appendChild(row2);
    if (openReserve) { var d0 = res.querySelector('input'); if (d0) setTimeout(function () { try { d0.focus({ preventScroll: true }); res.scrollIntoView({ block: 'nearest' }); } catch (e) { /* noop */ } }, 80); }
  }

  /* ---------- vista: reseñas ---------- */
  function viewReviews() {
    return {
      title: T('rev_title'),
      push: true,
      render: function (body, signal) {
        var st = { plataforma: null, destino: null, fotos: false, pagina: 1 };
        var head = el('div', 'fh-sr-head');
        var filt = el('div', 'fh-sr__filters'); filt.setAttribute('role', 'group');
        var list = el('div', 'fh-sr-list');
        var more = el('div', 'fh-sh__row');
        var first = true;
        body.appendChild(head); body.appendChild(filt); body.appendChild(list); body.appendChild(more);

        function fetchPage(append) {
          var url = api.reviews_url + qs({ plataforma: st.plataforma, destino: st.destino, fotos: st.fotos ? '1' : null, pagina: st.pagina });
          var box;
          if (!append) { clear(list); clear(more); }
          box = el('div', 'fh-sh__state'); list.appendChild(box);
          box.appendChild(el('span', 'fh-sh__spin')); box.appendChild(document.createTextNode(T('loading')));
          getJSON(url, signal).then(function (res) {
            var d = (res && res.data) || {};
            if (box.parentNode) box.parentNode.removeChild(box);
            if (first) { first = false; renderRevHead(head, d); renderRevFilters(filt, d); }
            (d.cards || []).forEach(function (c) { list.appendChild(revCard(c, d.cards)); });
            if (!(d.cards || []).length && !append) list.appendChild(el('p', 'fh-sh__state', T('rev_empty')));
            clear(more);
            var pg = d.pagination || {};
            if (pg.page && pg.last_page && pg.page < pg.last_page) {
              var b = el('button', 'fh-sh__cta fh-sh__cta--ghost', T('rev_more')); b.type = 'button';
              b.addEventListener('click', function () { st.pagina = pg.page + 1; b.disabled = true; fetchPage(true); });
              more.appendChild(b);
            }
          }).catch(function (e) {
            if (e && e.name === 'AbortError') return;
            failed(box, function () { if (box.parentNode) box.parentNode.removeChild(box); fetchPage(append); });
          });
        }

        function renderRevFilters(box, d) {
          var f = d.filters || {};
          var defs = [{ k: 'all', label: T('rev_all') }];
          if (f.show_platform_filter) (f.platforms || []).forEach(function (p) { defs.push({ k: 'p', v: p.key, label: p.label }); });
          if (f.show_destination_filter) (f.destinations || []).forEach(function (x) { defs.push({ k: 'd', v: x.slug, label: x.name }); });
          if (f.show_photos_filter) defs.push({ k: 'f', label: T('rev_photos') });
          if (defs.length < 2) { box.hidden = true; return; }
          defs.forEach(function (df, i) {
            var b = el('button', '', df.label); b.type = 'button';
            b.setAttribute('aria-pressed', i === 0 ? 'true' : 'false');
            b.addEventListener('click', function () {
              Array.prototype.forEach.call(box.children, function (x) { x.setAttribute('aria-pressed', 'false'); });
              b.setAttribute('aria-pressed', 'true');
              st.plataforma = df.k === 'p' ? df.v : null; st.destino = df.k === 'd' ? df.v : null; st.fotos = df.k === 'f'; st.pagina = 1;
              fetchPage(false);
            });
            box.appendChild(b);
          });
        }
        fetchPage(false);
      }
    };
  }

  function renderRevHead(head, d) {
    var s = d.summary;
    if (!s) return;
    head.appendChild(el('p', 'fh-sh__eyebrow', T('rev_eyebrow')));
    var sum = el('div', 'fh-sr__sum');
    sum.appendChild(el('span', 'fh-sr__avg', Number(s.average).toFixed(1)));
    var mid = el('div');
    mid.appendChild(stars(s.average, true));
    mid.appendChild(el('p', 'fh-sr__small', T('rev_avg') + ' ' + s.total + ' ' + (s.total === 1 ? T('review_word') : T('reviews_word'))));
    sum.appendChild(mid);
    head.appendChild(sum);
    var bars = el('ul', 'fh-sr__bars');
    [5, 4, 3, 2, 1].forEach(function (n) {
      var x = (s.distribution || {})[n] || { percent: 0 };
      var li = el('li');
      li.appendChild(el('span', '', n + ' ★'));
      var i = el('i'); i.style.setProperty('--w', x.percent + '%'); li.appendChild(i);
      li.appendChild(el('span', '', x.percent + '%'));
      bars.appendChild(li);
    });
    head.appendChild(bars);
  }

  function monthYear(iso) {
    try {
      var p = String(iso).split('-');
      var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, 1);
      var s = d.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
      return s.charAt(0).toUpperCase() + s.slice(1);
    } catch (e) { return ''; }
  }
  function revCard(c) {
    var art = el('article', 'fh-sr__card');
    var top = el('div', 'fh-sr__top');
    var av = el('div', 'fh-sr__av');
    if (safeHref(c.avatar_url)) { var im = el('img'); im.src = c.avatar_url; im.alt = ''; im.loading = 'lazy'; av.appendChild(im); }
    else { av.textContent = (c.name || T('traveler')).trim().charAt(0).toUpperCase(); av.setAttribute('aria-hidden', 'true'); }
    var who = el('div', 'fh-sr__who');
    who.appendChild(el('h3', 'fh-sr__name', c.name || T('traveler')));
    var metaParts = [];
    if (c.tour && c.tour.title) metaParts.push(c.tour.title);
    if (c.date_iso) { var my = monthYear(c.date_iso); if (my) metaParts.push(my); }
    if (!metaParts.length) { if (c.country) metaParts.push(c.country); }
    if (c.platform && c.platform !== 'web' && c.platform_label) metaParts.push(c.platform_label);
    if (metaParts.length) who.appendChild(el('p', 'fh-sr__meta', metaParts.join(' · ')));
    top.appendChild(av); top.appendChild(who); top.appendChild(stars(c.rating));
    art.appendChild(top);
    if (c.quote) art.appendChild(el('p', 'fh-sr__q', c.quote));
    var ph = (c.photo_items || []).slice(0, 3);
    if (ph.length) {
      var g = el('div', 'fh-sr__ph');
      ph.forEach(function (p) {
        var b = el('button'); b.type = 'button'; b.setAttribute('aria-label', T('photo_title'));
        var i = el('img'); i.src = p.thumb; i.alt = ''; i.loading = 'lazy';
        b.appendChild(i);
        b.addEventListener('click', function () { push(viewPhoto(p.full || p.thumb)); });
        g.appendChild(b);
      });
      art.appendChild(g);
    }
    return art;
  }

  /* ---------- disparadores (delegación) ---------- */
  function modified(e) { return e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey; }

  document.addEventListener('click', function (e) {
    if (!api || modified(e)) return;
    var t = e.target;
    if (!(t instanceof Element)) return;

    var lb = t.closest('[data-lightbox]');
    if (lb) {
      e.preventDefault();
      show(viewPhoto(lb.getAttribute('data-lightbox')), lb);
      return;
    }

    var trg = t.closest('[data-sheet]');
    if (trg) {
      var kind = trg.getAttribute('data-sheet');
      var v = null;
      if (kind === 'contact' && api.whatsapp_url) v = viewContact();
      else if (kind === 'all' && api.finder_url) v = viewResults({ mode: 'all', params: {} });
      else if (kind === 'reviews' && api.reviews_url) v = viewReviews();
      else if (kind === 'benefit') v = viewBenefit(parseInt(trg.getAttribute('data-benefit'), 10));
      if (v) { e.preventDefault(); show(v, trg); return; }
    }

    var find = t.closest('a[data-find]');
    if (find && api.finder_url) {
      var params = {}, label = (find.getAttribute('data-find-title') || '').trim(), mode = 'quick';
      var card = find.closest('.fh-dcard');
      if (card && card.getAttribute('data-dest')) { params.destino = card.getAttribute('data-dest'); mode = 'dest'; label = card.getAttribute('data-dest-name') || ''; }
      else {
        try {
          var u = new URL(find.href, location.href);
          ['duracion', 'experiencia', 'destino', 'q'].forEach(function (k) { if (u.searchParams.get(k)) params[k] = u.searchParams.get(k); });
        } catch (er) { /* noop */ }
      }
      if (Object.keys(params).length) { e.preventDefault(); show(viewResults({ mode: mode, params: params, label: label }), find); return; }
    }

    var cardEl = t.closest('[data-sheet-url]');
    if (cardEl) {
      var link = t.closest('a[href],button');
      if (link && cardEl.contains(link) && !link.classList.contains('fh-card__opin')) {
        e.preventDefault();
        show(viewTour(cardEl.getAttribute('data-sheet-url'), false), link);
      }
    }
  });

  /* ---------- formularios ---------- */
  var searchForm = document.querySelector('[data-search-form]');
  if (searchForm) {
    searchForm.addEventListener('submit', function (e) {
      if (!api || !api.finder_url) return;
      e.preventDefault();
      var q = (searchForm.querySelector('input[type=search]') || {}).value || '';
      show(viewResults({ mode: 'search', params: q.trim() ? { q: q.trim().slice(0, 80) } : {} }), searchForm.querySelector('button[type=submit]'));
    });
  }

  var finder = document.querySelector('[data-finder-form]');
  if (finder) {
    var err = document.getElementById('lvt-finder-err');
    finder.addEventListener('change', function () { if (err) err.hidden = true; });
    finder.addEventListener('submit', function (e) {
      var checked = finder.querySelectorAll('input[type=radio]:checked');
      if (!checked.length) { e.preventDefault(); if (err) err.hidden = false; return; }
      if (!api || !api.finder_url) return;
      e.preventDefault();
      var params = {};
      Array.prototype.forEach.call(checked, function (r) { params[r.name] = r.value; });
      show(viewResults({ mode: 'recommend', params: params }), finder.querySelector('button[type=submit]'));
    });
  }
})();

// PaySmallSmall — the little JS we need. No frameworks, no libraries.
// Motion is IntersectionObserver + CSS transitions only, and it respects
// prefers-reduced-motion.

(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---- Hero on-load: headline lines stagger, squiggle draws, marquee starts.
  // Add the class on the next frame so the initial state is painted first.
  requestAnimationFrame(function () {
    requestAnimationFrame(function () { document.body.classList.add('loaded'); });
  });

  // ---- Mobile nav toggle
  (function () {
    // Header menu button and the bottom-bar "Account" button both open it.
    var toggles = document.querySelectorAll('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');
    if (!toggles.length || !nav) return;
    toggles.forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        toggles.forEach(function (t) { t.setAttribute('aria-expanded', open ? 'true' : 'false'); });
        if (open && toggle.closest('.bottom-nav')) window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
    // Close the desktop account menu when clicking elsewhere.
    document.addEventListener('click', function (e) {
      document.querySelectorAll('details.acct[open]').forEach(function (d) {
        if (!d.contains(e.target)) d.removeAttribute('open');
      });
    });
  })();

  // ---- Scroll reveal (fade + rise, once). Children stagger via CSS.
  var revealEls = Array.prototype.slice.call(document.querySelectorAll('.reveal'));
  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealEls.forEach(function (el) { el.classList.add('in'); });
  } else {
    var revealObs = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          obs.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0 });
    revealEls.forEach(function (el) { revealObs.observe(el); });
  }

  // ---- Progress bars: grow the fill from 0 to its target width when in view.
  // The inline width is the real value, so no-JS / reduced-motion shows it correctly.
  var bars = Array.prototype.slice.call(document.querySelectorAll('.progress-fill[data-pct]'));
  if (!reduceMotion && 'IntersectionObserver' in window) {
    bars.forEach(function (el) { el.style.width = '0%'; });
    var barObs = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.style.width = entry.target.getAttribute('data-pct') + '%';
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });
    bars.forEach(function (el) { barObs.observe(el); });
  }

  // ---- Number counters: count up over ~1s when they enter view.
  function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

  function formatCount(el, value) {
    var prefix = el.getAttribute('data-prefix') || '';
    var plus = el.getAttribute('data-plus') || '';
    var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var num = decimals > 0 ? value.toFixed(decimals)
                           : Math.round(value).toLocaleString('en-US');
    el.textContent = prefix + num + plus;
  }

  function runCounter(el) {
    var literal = el.getAttribute('data-literal');
    var target = parseFloat(el.getAttribute('data-count'));
    if (literal !== null && (isNaN(target) || target === 0)) { el.textContent = literal; return; }
    if (reduceMotion) { formatCount(el, target); return; }
    var start = null, dur = 1000;
    function tick(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      formatCount(el, target * easeOut(p));
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  var counters = Array.prototype.slice.call(document.querySelectorAll('[data-count]'));
  if (!('IntersectionObserver' in window)) {
    counters.forEach(runCounter);
  } else {
    var countObs = new IntersectionObserver(function (entries, obs) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { runCounter(entry.target); obs.unobserve(entry.target); }
      });
    }, { threshold: 0.6 });
    counters.forEach(function (el) { countObs.observe(el); });
  }

  // ---- Product rails: prev/next buttons scroll one "page" of cards. Buttons
  // grey out at either end and hide entirely when nothing overflows.
  document.querySelectorAll('[data-rail]').forEach(function (rail) {
    var nav = document.querySelector('[data-rail-nav="' + rail.getAttribute('data-rail') + '"]');
    if (!nav) return;
    var prev = nav.querySelector('[data-rail-prev]');
    var next = nav.querySelector('[data-rail-next]');
    if (!prev || !next) return;

    function update() {
      var max = rail.scrollWidth - rail.clientWidth;
      nav.hidden = max <= 2;
      prev.disabled = rail.scrollLeft <= 2;
      next.disabled = rail.scrollLeft >= max - 2;
    }
    function go(dir) {
      rail.scrollBy({ left: dir * rail.clientWidth * 0.9, behavior: reduceMotion ? 'auto' : 'smooth' });
    }
    prev.addEventListener('click', function () { go(-1); });
    next.addEventListener('click', function () { go(1); });
    rail.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  });

  // ---- Money formatting, same output as PHP ghs(): GHS 1,250 / GHS 104.17
  function ghs(pesewas) {
    var cedis = Math.floor(pesewas / 100);
    var rem = pesewas % 100;
    var out = 'GHS ' + cedis.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return rem ? out + '.' + (rem < 10 ? '0' : '') + rem : out;
  }

  // Material Symbols icon markup, same as PHP micon().
  function micon(name, size, fill) {
    return '<span class="material-symbols-outlined' + (fill ? ' fill' : '') + '"' +
      (size ? ' style="font-size:' + size + 'px"' : '') + ' aria-hidden="true">' + name + '</span>';
  }

  // ---- Product page picker: options (size/colour/storage), quantity, and the
  // payment schedule. Prices are recomputed here exactly like
  // Product::planOptions() in PHP; the server recomputes again on submit.
  (function () {
    var picker = document.querySelector('[data-picker]');
    if (!picker) return;

    var data;
    try { data = JSON.parse(picker.getAttribute('data-product') || '{}'); }
    catch (e) { return; }

    var form = picker.querySelector('form');
    var freqInput = picker.querySelector('[data-frequency]');
    var countInput = picker.querySelector('[data-count]');
    var variantInput = picker.querySelector('[data-variant-id]');
    var optsBox = picker.querySelector('[data-duration-options]');
    var firstLine = picker.querySelector('[data-first-amount]');
    var fullLine = picker.querySelector('[data-full-amount]');
    var buyAmount = document.querySelector('[data-buy-amount]'); // sticky mobile bar
    var tabs = picker.querySelectorAll('[data-freq]');
    var qtyInput = picker.querySelector('[data-qty-input]');
    var qtyTotal = picker.querySelector('[data-qty-total]');
    var missingNote = picker.querySelector('[data-opt-missing]');
    var groups = Array.prototype.slice.call(picker.querySelectorAll('[data-opt-group]'));
    var actionBtns = document.querySelectorAll('[data-submit], [data-buy-submit], [data-add-cart], [data-buy-now]');
    if (!form || !freqInput || !countInput || !optsBox) return;

    var plans = {};
    var currentFreq = freqInput.value;
    var currentCount = parseInt(countInput.value, 10) || 0;

    function plansFor(price) {
      var out = {};
      Object.keys(data.defs || {}).forEach(function (freq) {
        var def = data.defs[freq];
        var options = [];
        def.counts.forEach(function (c) {
          var per = Math.ceil(price / c);
          if (per >= data.floor) options.push({ count: c, per: per });
        });
        if (!options.length) options.push({ count: def.counts[0], per: Math.ceil(price / def.counts[0]) });
        out[freq] = { unit: def.unit, noun: def.noun, options: options };
      });
      out.once = { full: true, options: [{ count: 1, per: price }] };
      return out;
    }

    function selectedValues() {
      var vals = {};
      groups.forEach(function (g) {
        var checked = g.querySelector('input:checked');
        vals[g.getAttribute('data-opt-group')] = checked ? checked.value : '';
      });
      return vals;
    }

    // A variant matching these values in every option group (and in-stock if asked).
    function findVariant(vals, needStock) {
      for (var i = 0; i < data.variants.length; i++) {
        var v = data.variants[i];
        var ok = Object.keys(vals).every(function (k) { return v.o[k - 1] === vals[k]; });
        if (ok && (!needStock || v.stock === null || v.stock > 0)) return v;
      }
      return null;
    }

    // Strike through values that can't be bought with the other current picks.
    function markAvailability(vals) {
      groups.forEach(function (g) {
        var key = g.getAttribute('data-opt-group');
        g.querySelectorAll('input').forEach(function (input) {
          var test = {};
          Object.keys(vals).forEach(function (k) { test[k] = vals[k]; });
          test[key] = input.value;
          input.closest('.opt-chip').classList.toggle('is-unavailable', !findVariant(test, true));
        });
        var cur = g.querySelector('[data-opt-current]');
        if (cur) cur.textContent = vals[key] || '';
      });
    }

    function state() {
      var hasVariants = data.variants && data.variants.length > 0;
      var vals = selectedValues();
      var variant = hasVariants ? findVariant(vals, false) : null;
      var price = variant && variant.price !== null ? variant.price : data.basePrice;
      var stock = hasVariants ? (variant ? variant.stock : 0) : data.stock;
      return {
        vals: vals,
        variant: variant,
        missing: hasVariants && !variant,
        price: price,
        stock: stock,
        sku: variant && variant.sku ? variant.sku : (data.sku || '')
      };
    }

    function renderOptions(freq) {
      var fp = plans[freq];
      if (!fp) return;
      currentFreq = freq;
      freqInput.value = freq;
      optsBox.innerHTML = '';
      var chosen = fp.options[0];
      fp.options.forEach(function (opt) { if (opt.count === currentCount) chosen = opt; });
      fp.options.forEach(function (opt) {
        var id = 'opt-' + freq + '-' + opt.count;
        var wrap = document.createElement('div');
        wrap.className = 'picker-option';
        var label = fp.full
          ? '<span class="picker-per">' + ghs(opt.per) + '</span><span class="picker-weeks">one payment &mdash; it\'s yours today</span>'
          : '<span class="picker-per">' + ghs(opt.per) + '<span class="muted"> / ' + fp.unit + '</span></span>' +
            '<span class="picker-weeks">for ' + opt.count + ' ' + fp.noun + '</span>';
        wrap.innerHTML =
          '<input type="radio" name="_dur" id="' + id + '" value="' + opt.count + '"' + (opt === chosen ? ' checked' : '') + '>' +
          '<label for="' + id + '">' + label + '</label>';
        optsBox.appendChild(wrap);
        wrap.querySelector('input').addEventListener('change', function () { choose(opt); });
      });
      choose(chosen);
      setMode(!!fp.full);
      tabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-freq') === freq); });
    }

    function choose(opt) {
      currentCount = opt.count;
      countInput.value = opt.count;
      if (firstLine) firstLine.textContent = ghs(opt.per);
      if (buyAmount) buyAmount.textContent = ghs(opt.per);
    }

    // Plan vs pay-in-full: swap the explanatory lines and button labels.
    function setMode(full) {
      document.querySelectorAll('[data-mode-plan]').forEach(function (el) { el.hidden = full; });
      document.querySelectorAll('[data-mode-full]').forEach(function (el) { el.hidden = !full; });
      document.querySelectorAll('[data-submit], [data-buy-submit]').forEach(function (b) {
        b.textContent = b.getAttribute(full ? 'data-label-full' : 'data-label-plan');
      });
      var note = document.querySelector('[data-buy-note]');
      if (note) note.textContent = note.getAttribute(full ? 'data-note-full' : 'data-note-plan');
    }

    function setHidden(sel, hide, text) {
      var el = document.querySelector(sel);
      if (!el) return;
      el.hidden = hide;
      if (text !== undefined && !hide) el.textContent = text;
    }

    function update() {
      var s = state();
      if (groups.length) markAvailability(s.vals);
      if (variantInput) variantInput.value = s.variant ? s.variant.id : '';
      if (missingNote) missingNote.hidden = !s.missing;

      // Price, old price, discount.
      var priceEl = document.querySelector('[data-price]');
      if (priceEl) priceEl.textContent = ghs(s.price);
      var off = data.compareAt && data.compareAt > s.price ? Math.round((data.compareAt - s.price) * 100 / data.compareAt) : 0;
      setHidden('[data-compare]', !off, off ? ghs(data.compareAt) : '');
      setHidden('[data-discount]', !off, '-' + off + '%');
      setHidden('[data-discount-badge]', !off, '-' + off + '%');
      setHidden('[data-save]', !off, off ? 'You save ' + ghs(data.compareAt - s.price) : '');

      // Stock and SKU.
      var soldOut = s.missing || (s.stock !== null && s.stock < 1);
      var stockLine = document.querySelector('[data-stock-line]');
      if (stockLine) {
        stockLine.innerHTML = s.missing ? ''
          : soldOut ? '<span class="stock stock-out">' + micon('block', 18) + ' Sold out for now</span>'
          : (s.stock !== null && s.stock <= 5) ? '<span class="stock stock-low">' + micon('local_fire_department', 18, true) + ' Only ' + s.stock + ' left</span>'
          : '<span class="stock stock-in">' + micon('check_circle', 18, true) + ' In stock</span>';
      }
      var skuWrap = document.querySelector('[data-sku-wrap]');
      if (skuWrap) {
        skuWrap.hidden = !s.sku;
        skuWrap.querySelector('[data-sku]').textContent = s.sku;
      }

      // Quantity: never more than we allow or than the shop has.
      var max = s.stock === null ? data.maxQty : Math.max(1, Math.min(data.maxQty, s.stock));
      var qty = 1;
      if (qtyInput) {
        qtyInput.max = max;
        qty = Math.max(1, Math.min(max, parseInt(qtyInput.value, 10) || 1));
        qtyInput.value = qty;
      }
      var total = s.price * qty;
      if (qtyTotal) {
        qtyTotal.hidden = qty < 2;
        qtyTotal.textContent = qty > 1 ? ghs(s.price) + ' × ' + qty + ' = ' + ghs(total) : '';
      }

      // Recompute the schedule options for the new total.
      plans = plansFor(total);
      if (fullLine) fullLine.textContent = ghs(total);
      document.querySelectorAll('[data-submit]').forEach(function (b) { b.setAttribute('data-label-full', 'Pay ' + ghs(total) + ' now'); });
      renderOptions(plans[currentFreq] ? currentFreq : Object.keys(plans)[0]);

      actionBtns.forEach(function (b) { b.disabled = soldOut; });
    }

    tabs.forEach(function (t) {
      t.addEventListener('click', function () { renderOptions(t.getAttribute('data-freq')); });
    });
    groups.forEach(function (g) { g.addEventListener('change', update); });

    if (qtyInput) {
      qtyInput.addEventListener('change', update);
      picker.querySelectorAll('[data-qty-step]').forEach(function (b) {
        b.addEventListener('click', function () {
          qtyInput.value = (parseInt(qtyInput.value, 10) || 1) + parseInt(b.getAttribute('data-qty-step'), 10);
          update();
        });
      });
    }

    // "Add to cart" stays on the page: post in the background, show a note.
    var toast = picker.querySelector('[data-cart-toast]');
    form.addEventListener('submit', function (e) {
      var btn = e.submitter;
      if (!btn || !btn.hasAttribute('data-add-cart') || !window.fetch) return;
      e.preventDefault();
      btn.disabled = true;
      fetch(btn.getAttribute('formaction'), {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (toast) {
            toast.hidden = false;
            toast.className = 'cart-toast' + (d.ok ? '' : ' is-error');
            toast.innerHTML = '';
            toast.appendChild(document.createTextNode(d.message + ' '));
            if (d.ok) {
              var a = document.createElement('a');
              a.href = d.cartUrl; a.textContent = 'View cart';
              toast.appendChild(a);
            }
          }
          if (d.ok) setBadge('[data-cart-count]', d.count);
        })
        .catch(function () { form.action = btn.getAttribute('formaction'); form.submit(); })
        .then(function () { btn.disabled = false; update(); });
    });

    update();
  })();

  function setBadge(sel, n) {
    document.querySelectorAll(sel).forEach(function (b) { b.textContent = n; b.hidden = !n; });
  }

  // ---- Save for later (heart). Logged-in: toggle in place. Guests: the form
  // posts normally and they're asked to log in first.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('form[data-wish]') || !window.fetch) return;
    var btn = form.querySelector('.wish-btn');
    if (!btn || btn.getAttribute('data-auth') !== '1') return;
    e.preventDefault();
    fetch(form.action, {
      method: 'POST', body: new FormData(form), credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) return;
        // Every heart for this product on the page (card + page) flips together.
        var pid = form.querySelector('[name="product_id"]').value;
        document.querySelectorAll('form[data-wish]').forEach(function (f) {
          if (f.querySelector('[name="product_id"]').value !== pid) return;
          var b = f.querySelector('.wish-btn');
          b.classList.toggle('is-saved', d.saved);
          b.setAttribute('aria-pressed', d.saved ? 'true' : 'false');
          b.setAttribute('aria-label', d.saved ? 'Remove from saved items' : 'Save for later');
          var ic = b.querySelector('.material-symbols-outlined');
          if (ic) ic.classList.toggle('fill', d.saved);
        });
        setBadge('[data-saved-count]', d.count);
      })
      .catch(function () { form.submit(); });
  });

  // ---- Product gallery: thumbnails, hover zoom (mouse), swipe (touch) and a
  // full-screen viewer with tap-to-zoom.
  document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
    var main = gallery.querySelector('[data-gallery-main]');
    if (!main) return;
    var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('[data-gallery-thumb]'));
    var srcs = thumbs.length ? thumbs.map(function (t) { return t.getAttribute('data-full'); }) : [main.getAttribute('src')];
    var index = 0;

    function show(i) {
      index = (i + srcs.length) % srcs.length;
      // The first photo arrives as <picture> with WebP/AVIF <source>s, which
      // would win over a new src — drop them once we start switching.
      if (main.parentNode && main.parentNode.tagName === 'PICTURE') {
        main.parentNode.querySelectorAll('source').forEach(function (s) { s.remove(); });
      }
      main.src = srcs[index];
      thumbs.forEach(function (t, k) { t.classList.toggle('active', k === index); });
    }
    thumbs.forEach(function (t, k) { t.addEventListener('click', function () { show(k); }); });

    var box = gallery.querySelector('[data-zoom]');
    if (box && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      box.addEventListener('mousemove', function (e) {
        var r = box.getBoundingClientRect();
        main.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
        box.classList.add('is-zooming');
      });
      box.addEventListener('mouseleave', function () { box.classList.remove('is-zooming'); });
    }
    onSwipe(box, function (dir) { if (srcs.length > 1) show(index + dir); });

    // Full-screen viewer.
    var dlg = document.querySelector('[data-lightbox]');
    var opener = gallery.querySelector('[data-lightbox-open]');
    if (!dlg || !opener || typeof dlg.showModal !== 'function') return;
    var stage = dlg.querySelector('[data-lightbox-stage]');
    var big = dlg.querySelector('[data-lightbox-img]');
    var count = dlg.querySelector('[data-lightbox-count]');

    function lbShow(i) {
      show(i);
      stage.classList.remove('is-zoomed');
      big.src = srcs[index];
      if (count) count.textContent = (index + 1) + ' / ' + srcs.length;
    }
    opener.addEventListener('click', function () { lbShow(index); dlg.showModal(); });
    dlg.querySelector('[data-lightbox-close]').addEventListener('click', function () { dlg.close(); });
    var prev = dlg.querySelector('[data-lightbox-prev]');
    var next = dlg.querySelector('[data-lightbox-next]');
    if (prev) prev.addEventListener('click', function () { lbShow(index - 1); });
    if (next) next.addEventListener('click', function () { lbShow(index + 1); });
    dlg.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') lbShow(index - 1);
      if (e.key === 'ArrowRight') lbShow(index + 1);
    });
    // Click outside the photo closes; clicking the photo zooms in on that spot.
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
    big.addEventListener('click', function (e) {
      var zoomed = stage.classList.toggle('is-zoomed');
      if (zoomed) {
        var r = big.getBoundingClientRect();
        var fx = (e.clientX - r.left) / r.width, fy = (e.clientY - r.top) / r.height;
        requestAnimationFrame(function () {
          stage.scrollLeft = fx * stage.scrollWidth - stage.clientWidth / 2;
          stage.scrollTop = fy * stage.scrollHeight - stage.clientHeight / 2;
        });
      }
    });
    onSwipe(stage, function (dir) {
      if (!stage.classList.contains('is-zoomed') && srcs.length > 1) lbShow(index + dir);
    });
  });

  // Horizontal swipe on touch screens: cb(+1) for next, cb(-1) for previous.
  function onSwipe(el, cb) {
    if (!el) return;
    var x0 = null, y0 = null;
    el.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    el.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
      x0 = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) cb(dx < 0 ? 1 : -1);
    }, { passive: true });
  }

  // ---- Search suggestions: products and categories as you type.
  (function () {
    var n = 0;
    document.querySelectorAll('[data-suggest]').forEach(function (input) {
      var form = input.closest('form');
      if (!form || !window.fetch) return;
      var endpoint = form.getAttribute('action').replace(/\/shop$/, '/search/suggest');
      var box = document.createElement('div');
      box.className = 'suggest';
      box.id = 'suggest-' + (++n);
      box.setAttribute('role', 'listbox');
      box.hidden = true;
      form.appendChild(box);
      form.classList.add('has-suggest');
      input.setAttribute('role', 'combobox');
      input.setAttribute('aria-autocomplete', 'list');
      input.setAttribute('aria-expanded', 'false');
      input.setAttribute('aria-controls', box.id);

      var timer = null, ctrl = null, links = [], active = -1, lastQ = '';

      function close() {
        box.hidden = true; active = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
      }

      // Name with the typed text marked, built from text nodes (never innerHTML).
      function highlighted(text, q) {
        var frag = document.createDocumentFragment();
        var i = text.toLowerCase().indexOf(q.toLowerCase());
        if (i < 0 || !q) { frag.appendChild(document.createTextNode(text)); return frag; }
        frag.appendChild(document.createTextNode(text.slice(0, i)));
        var m = document.createElement('mark'); m.textContent = text.slice(i, i + q.length);
        frag.appendChild(m);
        frag.appendChild(document.createTextNode(text.slice(i + q.length)));
        return frag;
      }

      function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text !== undefined) e.textContent = text;
        return e;
      }

      function render(d) {
        box.innerHTML = '';
        links = [];
        (d.categories || []).forEach(function (c) {
          var a = el('a', 'suggest-cat');
          a.href = c.url;
          a.innerHTML = micon('category', 18);
          a.appendChild(document.createTextNode(' '));
          a.appendChild(highlighted(c.label, d.q));
          a.appendChild(el('span', 'suggest-meta', ' · ' + c.count + ' item' + (c.count === 1 ? '' : 's')));
          box.appendChild(a); links.push(a);
        });
        (d.products || []).forEach(function (p) {
          var a = el('a', 'suggest-item');
          a.href = p.url;
          var thumb = el('span', 'suggest-thumb');
          if (p.photo) { var img = el('img'); img.src = p.photo; img.alt = ''; img.loading = 'lazy'; thumb.appendChild(img); }
          else { thumb.innerHTML = micon('inventory_2', 20); }
          a.appendChild(thumb);
          var body = el('span', 'suggest-body');
          var name = el('span', 'suggest-name');
          name.appendChild(highlighted(p.name, d.q));
          body.appendChild(name);
          body.appendChild(el('span', 'suggest-meta',
            p.price + ' · ' + p.weekly + ' · ' + p.shop + (p.sku ? ' · SKU ' + p.sku : '') + (p.soldOut ? ' · Sold out' : '')));
          a.appendChild(body);
          box.appendChild(a); links.push(a);
        });
        var all = el('a', 'suggest-all');
        all.href = form.getAttribute('action') + '?q=' + encodeURIComponent(d.q);
        all.textContent = (d.products || []).length ? 'See all results for "' + d.q + '"' : 'No quick matches — search for "' + d.q + '"';
        box.appendChild(all); links.push(all);
        links.forEach(function (a, i) { a.id = box.id + '-o' + i; a.setAttribute('role', 'option'); a.tabIndex = -1; });
        box.hidden = false; active = -1;
        input.setAttribute('aria-expanded', 'true');
      }

      function lookup() {
        var q = input.value.trim();
        if (q.length < 2) { close(); lastQ = ''; return; }
        if (q === lastQ && !box.hidden) return;
        lastQ = q;
        if (ctrl && ctrl.abort) ctrl.abort();
        ctrl = window.AbortController ? new AbortController() : null;
        fetch(endpoint + '?q=' + encodeURIComponent(q), {
          headers: { 'Accept': 'application/json' }, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined
        })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) { if (d && input.value.trim() === d.q) render(d); })
          .catch(function () { /* aborted or offline — the normal search still works */ });
      }

      function move(step) {
        if (box.hidden || !links.length) return;
        if (active >= 0) { links[active].classList.remove('is-active'); links[active].removeAttribute('aria-selected'); }
        active = (active + step + links.length) % links.length;
        links[active].classList.add('is-active');
        links[active].setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', links[active].id);
        links[active].scrollIntoView({ block: 'nearest' });
      }

      input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(lookup, 160); });
      input.addEventListener('focus', function () { if (input.value.trim().length >= 2) lookup(); });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Escape') { close(); }
        else if (e.key === 'Enter' && active >= 0 && !box.hidden) { e.preventDefault(); window.location.href = links[active].href; }
      });
      input.addEventListener('blur', function () { setTimeout(close, 180); });
    });
  })();

  // ---- Selects that apply as soon as you pick (sort order, cart quantity).
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
    s.addEventListener('change', function () { if (s.form) s.form.submit(); });
  });

  // ---- Shop filters fold away on phones (open on desktop, where they're a sidebar).
  (function () {
    var panel = document.querySelector('[data-filters]');
    if (!panel || !window.matchMedia) return;
    var mq = window.matchMedia('(max-width: 767px)');
    function sync() { panel.open = !mq.matches; }
    sync();
    if (mq.addEventListener) mq.addEventListener('change', sync);
  })();

  // ---- Merchant product form: option rows (add/remove) and option columns
  // that follow the option names typed above the table.
  (function () {
    var ed = document.querySelector('[data-variant-editor]');
    if (!ed) return;
    var tbody = ed.querySelector('[data-variant-rows]');
    var addBtn = ed.querySelector('[data-variant-add]');
    var wrap = ed.querySelector('.variant-table-wrap');
    var names = Array.prototype.slice.call(ed.querySelectorAll('[data-option-name]'));
    if (!tbody || !addBtn) return;

    function rows() { return Array.prototype.slice.call(tbody.querySelectorAll('[data-variant-row]')); }

    function renumber() {
      rows().forEach(function (tr, n) {
        tr.querySelectorAll('[name^="variants["]').forEach(function (inp) {
          inp.name = inp.name.replace(/^variants\[\d+\]/, 'variants[' + n + ']');
        });
      });
    }

    function rowHasValues(tr) {
      return Array.prototype.some.call(tr.querySelectorAll('input:not([type=hidden])'), function (i) { return i.value.trim() !== ''; });
    }

    function sync() {
      var any = false;
      names.forEach(function (input) {
        var i = input.getAttribute('data-option-name');
        var name = input.value.trim();
        if (name) any = true;
        ed.querySelectorAll('[data-opt-col="' + i + '"]').forEach(function (c) { c.hidden = !name; });
        var head = ed.querySelector('[data-opt-head="' + i + '"]');
        if (head) head.textContent = name || ('Option ' + i);
        tbody.querySelectorAll('[name$="[opt' + i + ']"]').forEach(function (inp) { inp.placeholder = name; });
      });
      var hasData = rows().some(rowHasValues);
      wrap.hidden = !any && !hasData;
      addBtn.hidden = !any;
    }

    addBtn.addEventListener('click', function () {
      var all = rows();
      var copy = all[all.length - 1].cloneNode(true);
      copy.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      tbody.appendChild(copy);
      renumber();
      sync();
      var first = copy.querySelector('td:not([hidden]) input');
      if (first) first.focus();
    });

    tbody.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-variant-remove]');
      if (!btn) return;
      var tr = btn.closest('[data-variant-row]');
      if (rows().length > 1) tr.remove();
      else tr.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      renumber();
      sync();
    });

    names.forEach(function (n) { n.addEventListener('input', sync); });
    sync();
  })();

  // ---- Upload preview: show thumbnails of files chosen in the product form.
  (function () {
    var input = document.querySelector('[data-image-input]');
    var box = document.querySelector('[data-image-preview]');
    if (!input || !box || typeof URL.createObjectURL !== 'function') return;
    input.addEventListener('change', function () {
      box.innerHTML = '';
      Array.prototype.slice.call(input.files).slice(0, 8).forEach(function (file) {
        if (!/^image\//.test(file.type)) return;
        var img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        img.alt = file.name;
        box.appendChild(img);
      });
    });
  })();

  // ---- Confirm dialogs on destructive forms
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // ---- Signature micro-interaction: play the "PAID" stamp when a payment lands.
  // Any element carrying [data-stamp] gets the stamp animation; its closest
  // .receipt / .plan-card flashes dusty yellow. Fired on load for a fresh
  // success (e.g. after a mock payment) and exposed for other scripts to call.
  function playStamp(el) {
    if (!el) return;
    el.classList.remove('stamp-animate');
    void el.offsetWidth; // restart the animation
    el.classList.add('stamp-animate');
    var card = el.closest('.receipt, .plan-card, .plan-row');
    if (card) {
      card.classList.remove('flash-good');
      void card.offsetWidth;
      card.classList.add('flash-good');
    }
  }
  window.PSS = window.PSS || {};
  window.PSS.playStamp = playStamp;
  document.querySelectorAll('[data-stamp="fresh"]').forEach(playStamp);

  // ---- Auto-confirm a pending MoMo payment.
  // While an installment is awaiting the customer's approval, poll the plan's
  // status endpoint (which verifies with Paystack server-side). The moment it
  // clears, reload so the receipt shows the PAID stamp — no manual tap needed.
  // Backs off after a couple of minutes; the "I've paid" button stays as a
  // fallback the whole time.
  (function () {
    var box = document.querySelector('[data-poll-status]');
    if (!box) return;
    var url = box.getAttribute('data-poll-status');
    var note = box.querySelector('[data-poll-note]');
    if (note) note.style.display = '';

    var tries = 0;
    var MAX = 40;          // ~40 polls
    var EVERY = 4000;      // every 4s  => ~2.5 min of watching
    var timer = setInterval(function () {
      if (++tries > MAX) { clearInterval(timer); if (note) note.style.display = 'none'; return; }
      fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (d && d.confirmed) { clearInterval(timer); window.location.reload(); }
        })
        .catch(function () { /* transient — keep polling */ });
    }, EVERY);
  })();

  // ---- Shop: infinite scroll. The page links still work without JS (and are
  // what search engines follow); with JS, the next batch of cards is fetched
  // as you near the bottom and added to the grid.
  (function () {
    var grid = document.querySelector('[data-infinite-grid]');
    var pager = document.querySelector('[data-pager]');
    var status = document.querySelector('[data-infinite-status]');
    if (!grid || !pager || !window.fetch || !('IntersectionObserver' in window)) return;
    var next = pager.querySelector('[data-next-page]');
    if (!next) return;
    var nextUrl = next.getAttribute('href');
    var busy = false;

    function load() {
      if (busy || !nextUrl) return;
      busy = true;
      if (status) status.textContent = 'Loading more items…';
      var u = nextUrl + (nextUrl.indexOf('?') === -1 ? '?' : '&') + 'fragment=1';
      fetch(u, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function (d) {
          grid.insertAdjacentHTML('beforeend', d.html || '');
          nextUrl = d.next;
          if (status) status.textContent = nextUrl ? 'Page ' + d.page + ' of ' + d.pages : "That's everything.";
          if (!nextUrl) { io.disconnect(); pager.hidden = true; }
          busy = false;
        })
        .catch(function () {
          // Leave the normal page links in place to fall back on.
          if (status) status.textContent = "Couldn't load more. Use the page links.";
          io.disconnect();
        });
    }

    // The numbered links would jump around as cards are added — keep just the
    // "More items" button (a tap still loads the next batch in place).
    pager.querySelectorAll('.pager-num, .pager-gap').forEach(function (el) { el.hidden = true; });
    next.addEventListener('click', function (e) { e.preventDefault(); load(); });
    var io = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting) load();
    }, { rootMargin: '600px 0px' });
    io.observe(pager);
  })();

})();

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
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
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

  // ---- Plan picker: choose a frequency (daily/weekly/monthly), then a duration.
  // Options per frequency come from the server as JSON on [data-plans]. The form
  // submits two hidden inputs: frequency + count (number of installments).
  (function () {
    var picker = document.querySelector('[data-picker]');
    if (!picker) return;

    var plans;
    try { plans = JSON.parse(picker.getAttribute('data-plans') || '{}'); }
    catch (e) { return; }

    var freqInput = picker.querySelector('[data-frequency]');
    var countInput = picker.querySelector('[data-count]');
    var optsBox = picker.querySelector('[data-duration-options]');
    var firstLine = picker.querySelector('[data-first-amount]');
    var buyAmount = document.querySelector('[data-buy-amount]'); // sticky mobile bar
    var tabs = picker.querySelectorAll('[data-freq]');
    if (!freqInput || !countInput || !optsBox) return;

    function select(opt) {
      countInput.value = opt.count;
      if (firstLine) firstLine.textContent = opt.perLabel;
      if (buyAmount) buyAmount.textContent = opt.perLabel;
    }

    function render(freq) {
      var fp = plans[freq];
      if (!fp) return;
      freqInput.value = freq;
      optsBox.innerHTML = '';
      fp.options.forEach(function (opt, i) {
        var id = 'opt-' + freq + '-' + opt.count;
        var wrap = document.createElement('div');
        wrap.className = 'picker-option';
        wrap.innerHTML =
          '<input type="radio" name="_dur" id="' + id + '" value="' + opt.count + '"' + (i === 0 ? ' checked' : '') + '>' +
          '<label for="' + id + '"><span class="picker-per">' + opt.perLabel +
          '<span class="muted"> / ' + fp.unit + '</span></span>' +
          '<span class="picker-weeks">for ' + opt.count + ' ' + fp.noun + '</span></label>';
        optsBox.appendChild(wrap);
        wrap.querySelector('input').addEventListener('change', function () { select(opt); });
      });
      if (fp.options[0]) select(fp.options[0]);
      tabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-freq') === freq); });
    }

    tabs.forEach(function (t) {
      t.addEventListener('click', function () { render(t.getAttribute('data-freq')); });
    });

    var start = freqInput.value && plans[freqInput.value] ? freqInput.value : Object.keys(plans)[0];
    if (start) render(start);
  })();

  // ---- Product gallery: click a thumbnail to swap the main image.
  document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
    var main = gallery.querySelector('[data-gallery-main]');
    var thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
    if (!main || !thumbs.length) return;
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var full = thumb.getAttribute('data-full');
        if (full) main.src = full;
        thumbs.forEach(function (t) { t.classList.remove('active'); });
        thumb.classList.add('active');
      });
    });
  });

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
  // status endpoint (which reconciles against Moolre server-side). The moment it
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

})();

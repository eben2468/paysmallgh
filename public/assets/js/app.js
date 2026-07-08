// PaySmallSmall — the little JS we need. No frameworks.

// Mobile nav toggle
(function () {
  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.getElementById('site-nav');
  if (!toggle || !nav) return;
  toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();

// Plan picker: keep the "first payment today" line in sync with the chosen option
(function () {
  var picker = document.querySelector('[data-picker]');
  if (!picker) return;
  var firstLine = picker.querySelector('[data-first-amount]');
  picker.querySelectorAll('input[name="weeks"]').forEach(function (input) {
    input.addEventListener('change', function () {
      if (firstLine && input.dataset.per) firstLine.textContent = input.dataset.per;
    });
  });
})();

// Confirm dialogs on destructive forms
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
  form.addEventListener('submit', function (e) {
    if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
  });
});

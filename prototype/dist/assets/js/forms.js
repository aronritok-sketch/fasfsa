/* Strategy call: two-step form → booking facade. Validation, honeypot + time check, GA4 events. */
(function () {
  'use strict';
  var form = document.getElementById('call-form');
  if (!form) return;
  var track = window.hpvTrack || function () {};
  var steps = [1, 2, 3].map(function (n) { return document.getElementById('call-step-' + n); });
  var inds = [1, 2, 3].map(function (n) { return document.getElementById('step-ind-' + n); });
  var status = document.getElementById('call-status');
  var ts = document.getElementById('cf-ts');
  var started = false;
  ts.value = String(Date.now());

  form.addEventListener('focusin', function () {
    if (!started) { started = true; track('form_start', { form_id: 'strategy_call', step: 1 }); }
  });

  function show(n) {
    steps.forEach(function (s, i) { s.hidden = i !== n - 1; });
    inds.forEach(function (s, i) { s.classList.toggle('is-current', i === n - 1); });
    var first = steps[n - 1].querySelector('input:not([type=hidden]):not([tabindex="-1"]), select, a.btn');
    if (first) first.focus();
  }

  function validate(scope) {
    var ok = true, firstBad = null;
    scope.querySelectorAll('[required]').forEach(function (el) {
      var field = el.closest('.field');
      var err = field.querySelector('.field__error');
      var msg = '';
      if (!el.value.trim()) msg = 'Please fill in your ' + field.querySelector('label').textContent.replace(/\(.*\)/, '').trim().toLowerCase() + '.';
      else if (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value)) msg = 'Please enter an email like name@company.com.';
      else if (el.type === 'tel' && el.value.replace(/\D/g, '').length < 10) msg = 'Please enter a 10-digit phone number.';
      field.classList.toggle('has-error', !!msg);
      el.setAttribute('aria-invalid', msg ? 'true' : 'false');
      if (msg) {
        if (!err) { err = document.createElement('span'); err.className = 'field__error'; err.id = el.id + '-err'; field.appendChild(err); el.setAttribute('aria-describedby', err.id); }
        err.textContent = msg;
        ok = false; firstBad = firstBad || el;
      } else if (err) { err.textContent = ''; }
    });
    if (firstBad) firstBad.focus();
    return ok;
  }

  document.getElementById('call-next').addEventListener('click', function () {
    if (!validate(steps[0])) return;
    track('form_submit', { form_id: 'strategy_call', step: 1 });
    show(2);
  });
  document.getElementById('call-back').addEventListener('click', function () { show(1); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (steps[0].hidden === false) { document.getElementById('call-next').click(); return; }
    if (!validate(steps[1])) return;
    // Spam checks: honeypot must be empty and a human takes > 3 seconds.
    if (form.company_hp.value || Date.now() - Number(ts.value) < 3000) {
      status.textContent = 'Something looked automated. Please try again in a few seconds.';
      return;
    }
    track('form_submit', { form_id: 'strategy_call', step: 2, city: form.city.value, timing: form.timing.value });
    // In WordPress this posts to admin-post.php (inc/forms.php) and stores a private "lead".
    document.getElementById('cf-first').textContent = form.name.value.trim().split(' ')[0] || 'there';
    show(3);
    status.textContent = '';
  });
})();

/* Strategy call: two-step form → saved as a lead via REST → booking step.
   Without JS the same form posts to admin-post.php (see inc/leads.php). */
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
  var cfg = window.hpvConfig || {};
  if (!ts.value) ts.value = String(Date.now());
  steps[1].hidden = true; // JS shows one step at a time; without JS both are visible.

  // Keep the campaign source with the lead.
  var utm = document.getElementById('cf-utm');
  if (utm) {
    var q = new URLSearchParams(location.search), keep = {};
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'].forEach(function (k) { if (q.get(k)) keep[k] = q.get(k); });
    if (document.referrer && document.referrer.indexOf(location.host) === -1) keep.referrer = document.referrer;
    utm.value = JSON.stringify(keep);
  }

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
    var submitBtn = form.querySelector('#call-step-2 button[type=submit]');
    submitBtn.disabled = true;
    status.textContent = 'Sending…';

    var data = {};
    new FormData(form).forEach(function (v, k) { data[k] = v; });
    fetch((cfg.rest || '/wp-json/hpv/v1/') + 'lead', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
      credentials: 'same-origin'
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
      .then(function (res) {
        submitBtn.disabled = false;
        if (!res.ok) { status.textContent = (res.body && res.body.message) || 'Please check the fields and try again.'; return; }
        track('generate_lead', { form_id: 'strategy_call', lead_type: 'strategy_call' });
        var first = (form.name.value.trim().split(' ')[0]) || 'there';
        document.getElementById('cf-first').textContent = first;
        var book = document.getElementById('call-book');
        if (book && cfg.bookingUrl) {
          var u = new URL(cfg.bookingUrl);
          u.searchParams.set('name', form.name.value.trim());
          u.searchParams.set('email', form.email.value.trim());
          book.href = u.toString();
        } else if (book && cfg.thankYou) { book.href = cfg.thankYou; }
        show(3);
        status.textContent = '';
      })
      .catch(function () {
        // Network or REST problem: fall back to the classic POST so the lead is never lost.
        HTMLFormElement.prototype.submit.call(form);
      });
  });
})();

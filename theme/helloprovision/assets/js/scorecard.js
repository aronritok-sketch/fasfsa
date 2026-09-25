/* Digital Growth Scorecard: 15 questions, 5 areas, scored No 0 / Partly 1 / Yes 2. */
(function () {
  'use strict';
  var lead = document.getElementById('sc-lead');
  if (!lead) return;
  var track = window.hpvTrack || function () {};
  var cfg = window.hpvConfig || {};
  var leadData = {};

  var AREAS = [
    { key: 'positioning', name: 'Positioning',
      why: 'Visitors can’t quickly tell why they should choose you. Every other channel works harder when the message is sharp.',
      steps: [
        ['Ask your last five good customers why they chose you', 'Write down their exact words. Those phrases belong on your homepage.'],
        ['Rewrite your first screen in one sentence', 'What you do, for whom, and where — plus one clear next step.'],
        ['Pick the one service you want more of', 'Make it the most visible thing on the site for the next 90 days.']
      ],
      article: ['/insights/website-traffic-no-calls/', 'Why your website gets traffic but no calls'],
      qs: [
        'Can a stranger tell what you do, for whom and where within five seconds of landing on your homepage?',
        'Do you know the two or three reasons your best customers chose you — in their own words?',
        'Is your marketing focused on the services and customers that are most profitable for you?'
      ] },
    { key: 'website', name: 'Website',
      why: 'Your site is where interested people decide. Right now it’s losing some of them before they reach out.',
      steps: [
        ['Test your site on your phone like a customer would', 'Can you find the phone number, a project and a way to ask for a quote in under a minute?'],
        ['Give your top service its own page', 'What it includes, who it’s for, one project and one review.'],
        ['Move proof next to every call to action', 'A named review or a project photo right where people decide.']
      ],
      article: ['/insights/website-traffic-no-calls/', 'Why your website gets traffic but no calls'],
      qs: [
        'Does your website work well on a phone — fast, easy to read, with a tappable phone number?',
        'Does each main service have its own page with details, proof and a way to ask about it?',
        'Are recent projects, reviews or results visible on the pages that ask for a call?'
      ] },
    { key: 'search', name: 'Search visibility',
      why: 'Customers searching for what you do are finding competitors first. Local search is often the fastest channel to fix.',
      steps: [
        ['Complete your Google Business Profile', 'Every service, service area, category, and at least ten real photos.'],
        ['Ask your last ten happy customers for a review', 'Send a direct link by text the same day the job is finished.'],
        ['Check where you rank for your main search', 'Search “[service] [city]” in an incognito window and note who shows up.']
      ],
      article: ['/insights/', 'How to rank in the Google map pack in Fort Myers'],
      qs: [
        'Do you appear in the Google map results (top 3) for your main service in your city?',
        'Is your Google Business Profile complete and updated within the last month?',
        'Do you receive new Google reviews every month?'
      ] },
    { key: 'conversion', name: 'Conversion',
      why: 'Without knowing what produces calls, every marketing dollar is a guess. Measurement comes before more spend.',
      steps: [
        ['Count last month’s calls and form requests', 'Even a manual tally tells you your real starting point.'],
        ['Set up call and form tracking', 'Know which pages and searches bring inquiries, not just visits.'],
        ['Make contact one step from every page', 'Tap-to-call, a short form or a booking link — visible without scrolling.']
      ],
      article: ['/insights/', 'The 5 numbers every owner should track monthly'],
      qs: [
        'Do you know how many calls and form requests your website produced last month?',
        'Can visitors contact you in one step from any page — call, form or booking?',
        'Do you know which marketing channel brings your most profitable customers?'
      ] },
    { key: 'followup', name: 'Follow-up',
      why: 'Leads are arriving but some go cold before you reply. This is usually the cheapest growth you’ll ever find.',
      steps: [
        ['Measure your reply time this week', 'Note when each inquiry came in and when someone answered.'],
        ['Set a one-hour reply rule', 'Decide who answers new inquiries during business hours, and what they say.'],
        ['Follow up twice with anyone who doesn’t book', 'A short personal message after two days and after a week.']
      ],
      article: ['/insights/', 'Speed-to-lead: why the first hour decides the job'],
      qs: [
        'Is every new inquiry answered within one hour during business hours?',
        'Do you follow up with leads who don’t book on the first contact?',
        'Do you track every inquiry in one place through to won or lost?'
      ] }
  ];
  var CHOICES = [['0', 'No'], ['1', 'Partly'], ['2', 'Yes']];
  var TOTAL = 15;

  var quizSec = document.getElementById('scorecard-quiz');
  var resSec = document.getElementById('scorecard-results');
  var box = document.getElementById('sc-questions');
  var quiz = document.getElementById('sc-quiz');

  // Build the questions
  var n = 0, html = '';
  AREAS.forEach(function (a) {
    a.qs.forEach(function (q, i) {
      n++;
      var name = 'q-' + a.key + '-' + i;
      html += '<fieldset class="score-q"><legend><small>' + n + ' / ' + TOTAL + ' · ' + a.name + '</small>' + q + '</legend><div class="score-scale">';
      CHOICES.forEach(function (c) {
        html += '<label class="chip"><input type="radio" name="' + name + '" id="' + name + '-' + c[0] + '" value="' + c[0] + '"><span>' + c[1] + '</span></label>';
      });
      html += '</div></fieldset>';
    });
  });
  box.innerHTML = html;

  lead.addEventListener('submit', function (e) {
    e.preventDefault();
    var bad = null;
    ['sc-name', 'sc-email'].forEach(function (id) {
      var el = document.getElementById(id), f = el.closest('.field');
      var invalid = !el.value.trim() || (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value));
      f.classList.toggle('has-error', invalid);
      el.setAttribute('aria-invalid', String(invalid));
      if (invalid && !bad) bad = el;
    });
    if (bad) { bad.focus(); return; }
    if (lead.company_hp.value) return;
    new FormData(lead).forEach(function (v, k) { leadData[k] = v; });
    track('scorecard_start', { source_page: document.referrer || 'direct' });
    quizSec.hidden = false;
    document.getElementById('scorecard-start').hidden = true;
    quizSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var first = box.querySelector('input');
    if (first) first.focus({ preventScroll: true });
  });

  function answered() { return quiz.querySelectorAll('input:checked').length; }
  quiz.addEventListener('change', function () {
    var k = answered();
    document.getElementById('sc-bar').style.width = (k / TOTAL * 100) + '%';
    document.getElementById('sc-progress').textContent = k + ' of ' + TOTAL + ' answered';
    document.getElementById('sc-missing').textContent = '';
  });

  quiz.addEventListener('submit', function (e) {
    e.preventDefault();
    var k = answered();
    if (k < TOTAL) {
      document.getElementById('sc-missing').textContent = (TOTAL - k) + ' question' + (TOTAL - k > 1 ? 's' : '') + ' left — the first one is highlighted.';
      var open = Array.prototype.find.call(quiz.querySelectorAll('.score-q'), function (fs) { return !fs.querySelector('input:checked'); });
      if (open) { open.scrollIntoView({ behavior: 'smooth', block: 'center' }); open.querySelector('input').focus({ preventScroll: true }); }
      return;
    }
    var sum = 0;
    var scores = AREAS.map(function (a) {
      var s = 0;
      a.qs.forEach(function (_, i) { s += Number(quiz.querySelector('input[name="q-' + a.key + '-' + i + '"]:checked').value); });
      sum += s;
      return { area: a, pct: Math.round(s / 6 * 100) };
    });
    var weakest = scores.reduce(function (m, s) { return s.pct < m.pct ? s : m; }, scores[0]);
    var total = Math.round(sum / (TOTAL * 2) * 100);
    render(scores, weakest, total);
    track('scorecard_complete', { score: total, bottleneck: weakest.area.key });
    save(scores, weakest, total);
  });

  function save(scores, weakest, total) {
    var answers = {};
    quiz.querySelectorAll('input:checked').forEach(function (i) { answers[i.name] = Number(i.value); });
    var areas = {};
    scores.forEach(function (s) { areas[s.area.key] = s.pct; });
    var body = Object.assign({}, leadData, { total: total, bottleneck: weakest.area.key, areas: areas, answers: answers, source_page: location.href });
    fetch((cfg.rest || '/wp-json/hpv/v1/') + 'scorecard', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin'
    }).then(function (r) { if (r.ok) track('generate_lead', { form_id: 'scorecard', lead_type: 'scorecard' }); }).catch(function () {});
  }

  function esc(s) { return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function render(scores, weakest, total) {
    document.getElementById('sc-total').textContent = total;
    document.getElementById('sc-summary').textContent =
      total >= 75 ? 'A strong foundation. Your growth now depends on fine-tuning the weakest link.' :
      total >= 45 ? 'Solid in places, with clear gaps. Fixing the weakest area first will make everything else work harder.' :
      'There’s a lot of untapped growth here. Start with one area — trying to fix everything at once rarely works.';
    document.getElementById('sc-bars').innerHTML = scores.map(function (s) {
      return '<div class="score-bar' + (s === weakest ? ' is-weakest' : '') + '"><div class="score-bar__top"><span>' + esc(s.area.name) + '</span><span>' + s.pct + '</span></div><div class="score-bar__track" role="img" aria-label="' + esc(s.area.name) + ' ' + s.pct + ' out of 100"><i style="width:' + s.pct + '%"></i></div></div>';
    }).join('');
    document.getElementById('sc-weakest').textContent = weakest.area.name;
    document.getElementById('sc-why').textContent = weakest.area.why;
    document.getElementById('sc-steps').innerHTML = weakest.area.steps.map(function (st, i) {
      return '<li><span class="steps__num">' + (i + 1) + '</span><div><h3 class="h3">' + esc(st[0]) + '</h3></div><p>' + esc(st[1]) + '</p></li>';
    }).join('');
    var art = document.getElementById('sc-article');
    var real = (cfg.articles || {})[weakest.area.key];
    art.textContent = real ? real[1] : weakest.area.article[1];
    art.href = real ? real[0] : (cfg.insights || '/');
    quizSec.hidden = true;
    resSec.hidden = false;
    resSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    resSec.focus({ preventScroll: true });
  }
})();
